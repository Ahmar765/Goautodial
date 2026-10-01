# remote-bootstrap.ps1 — from Windows: copy repo + run linux/run-all.sh on the PBX
# Usage:
#   1. Copy linux\server.env.example -> linux\server.env and fill SSH_HOST / SSH_USER / auth + SIP_*
#   2. powershell -ExecutionPolicy Bypass -File linux\remote-bootstrap.ps1

$ErrorActionPreference = "Stop"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $PSScriptRoot "server.env"

if (-not (Test-Path $EnvFile)) {
  throw "Missing linux/server.env. Copy server.env.example to server.env and fill SSH_HOST, SSH_USER, and auth."
}

function Get-DotEnv {
  param([string]$Path)
  $map = @{}
  Get-Content -LiteralPath $Path | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    $i = $line.IndexOf("=")
    if ($i -lt 1) { return }
    $k = $line.Substring(0, $i).Trim()
    $v = $line.Substring($i + 1).Trim()
    $map[$k] = $v
  }
  return $map
}

$cfg = Get-DotEnv -Path $EnvFile
foreach ($req in @("SSH_HOST", "SSH_USER")) {
  if (-not $cfg.ContainsKey($req) -or [string]::IsNullOrWhiteSpace($cfg[$req])) {
    throw "server.env missing required $req"
  }
}

$sshHost = $cfg["SSH_HOST"]
$sshUser = $cfg["SSH_USER"]
$sshPort = "22"
if ($cfg.ContainsKey("SSH_PORT") -and -not [string]::IsNullOrWhiteSpace($cfg["SSH_PORT"])) {
  $sshPort = $cfg["SSH_PORT"]
}
$remote = $sshUser + "@" + $sshHost

$sshBase = @("-p", $sshPort, "-o", "StrictHostKeyChecking=accept-new")
$scpBase = @("-P", $sshPort, "-o", "StrictHostKeyChecking=accept-new")
if ($cfg.ContainsKey("SSH_KEY") -and -not [string]::IsNullOrWhiteSpace($cfg["SSH_KEY"])) {
  $sshBase += @("-i", $cfg["SSH_KEY"])
  $scpBase += @("-i", $cfg["SSH_KEY"])
}

Write-Host ("==> Checking SSH to {0} ..." -f $remote)
$preflight = 'uname -a; . /etc/os-release; echo PRETTY_NAME=$PRETTY_NAME'
& ssh @sshBase $remote $preflight
if ($LASTEXITCODE -ne 0) {
  throw "SSH preflight failed. Fix host/user/key/password in linux/server.env."
}

$remoteDir = "/usr/src/goautodial-crm-deploy"
$tarName = "goautodial-deploy.tgz"
$tarPath = Join-Path $env:TEMP $tarName
if (Test-Path -LiteralPath $tarPath) {
  Remove-Item -LiteralPath $tarPath -Force
}

Write-Host "==> Packaging repo..."
Push-Location -LiteralPath $RepoRoot
try {
  & tar -czf $tarPath --exclude=.git --exclude=linux/server.env --exclude=.env --exclude=php/Config.php --exclude=php/goCRMAPISettings.php .
  if ($LASTEXITCODE -ne 0) { throw "tar failed" }
} finally {
  Pop-Location
}

Write-Host ("==> Uploading to {0}:{1}" -f $remote, $remoteDir)
& ssh @sshBase $remote ("mkdir -p {0}/linux" -f $remoteDir)
& scp @scpBase $tarPath ($remote + ":" + $remoteDir + "/" + $tarName)
& scp @scpBase $EnvFile ($remote + ":" + $remoteDir + "/linux/server.env")

$remoteLines = @(
  '#!/usr/bin/env bash'
  'set -euo pipefail'
  ('cd ''{0}''' -f $remoteDir)
  ('tar -xzf ''{0}''' -f $tarName)
  'chmod +x linux/*.sh install_goautodial.sh 2>/dev/null || true'
  'set -a'
  '. linux/server.env'
  'set +a'
  'export SKIP_REBOOT=1'
  'bash linux/run-all.sh'
)
$remoteScriptPath = Join-Path $env:TEMP "goautodial-remote-run.sh"
[System.IO.File]::WriteAllText($remoteScriptPath, ($remoteLines -join "`n") + "`n")
& scp @scpBase $remoteScriptPath ($remote + ":" + $remoteDir + "/remote-run.sh")

Write-Host "==> Running linux/run-all.sh on remote (Asterisk compile can take 30-90+ minutes)..."
& ssh @sshBase $remote ("bash {0}/remote-run.sh" -f $remoteDir)
if ($LASTEXITCODE -ne 0) {
  throw ("Remote run-all failed (exit {0})" -f $LASTEXITCODE)
}

Write-Host "Remote bootstrap finished."
