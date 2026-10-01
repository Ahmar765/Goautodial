#!/usr/bin/env bash
# 00-check-server.sh — validate AlmaLinux/Rocky 9 before stack install
set -euo pipefail

EXPECT_OS="${EXPECT_OS:-el9}"

echo "==> GOautodial server preflight"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "ERROR: run as root (sudo -i)" >&2
  exit 1
fi

if [[ ! -f /etc/os-release ]]; then
  echo "ERROR: /etc/os-release missing" >&2
  exit 1
fi
# shellcheck disable=SC1091
. /etc/os-release

ID_LIKE_LOWER="$(echo "${ID_LIKE:-} ${ID:-}" | tr '[:upper:]' '[:lower:]')"
VERSION_ID_MAJOR="${VERSION_ID%%.*}"

ok=0
case "${ID:-}" in
  almalinux|rocky|centos|rhel) ok=1 ;;
esac
if echo "$ID_LIKE_LOWER" | grep -Eq 'rhel|fedora|centos'; then
  ok=1
fi

if [[ "$ok" -ne 1 ]]; then
  echo "ERROR: unsupported OS id=${ID:-unknown}. Need AlmaLinux/Rocky/RHEL 9." >&2
  exit 1
fi

if [[ "$EXPECT_OS" == "el9" && "$VERSION_ID_MAJOR" != "9" ]]; then
  echo "ERROR: expected EL9, got VERSION_ID=${VERSION_ID}" >&2
  exit 1
fi

echo "OS OK: ${PRETTY_NAME:-$ID $VERSION_ID}"
echo "Kernel: $(uname -r)"
echo "Hostname: $(hostname -f 2>/dev/null || hostname)"

if ! command -v timedatectl >/dev/null 2>&1; then
  echo "WARN: timedatectl not found"
else
  timedatectl status | head -n 5 || true
fi

MEM_KB=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
MEM_GB=$((MEM_KB / 1024 / 1024))
echo "RAM: ~${MEM_GB}G"
if [[ "$MEM_GB" -lt 2 ]]; then
  echo "WARN: <2G RAM — Asterisk build may fail or swap heavily"
fi

DISK_AVAIL=$(df -BG / | awk 'NR==2 {gsub(/G/,"",$4); print $4}')
echo "Disk free (/): ${DISK_AVAIL}G"
if [[ "${DISK_AVAIL:-0}" -lt 20 ]]; then
  echo "WARN: recommend >=20G free for sources + RPMs"
fi

echo "Preflight passed."
