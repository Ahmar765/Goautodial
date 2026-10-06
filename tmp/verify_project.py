from pathlib import Path
import subprocess, re, sys, json, zipfile
root = Path(__file__).resolve().parent.parent
php = r'C:\Program Files\php-8.2.33\php.exe'
files = [p for p in root.rglob('*.php') if not any(x in ('tmp', 'vendor', '.git') for x in p.relative_to(root).parts)]
failures = []
for path in files:
    result = subprocess.run([php, '-l', str(path)], capture_output=True, text=True)
    if result.returncode or 'Deprecated:' in result.stdout or 'Warning:' in result.stdout:
        failures.append((str(path.relative_to(root)), result.stdout + result.stderr))
print(f'PHP syntax: {len(files)} files checked; {len(failures)} failures/warnings.')
for name, error in failures: print(name, error)
excluded = {'Config.php', 'goCRMAPISettings.php', 'CRMDefaults.php', 'CurlCompat.php', 'smtp_settings.php', 'MailerBootstrap.php'}
unguarded = []
for path in files:
    relative = path.relative_to(root)
    if len(relative.parts) != 1 and relative.parts[0] not in ('php', 'modules'): continue
    text = path.read_text(encoding='utf-8-sig')
    if '<?php' not in text or path.name in excluded or re.search(r'\b(?:abstract\s+)?class\s+\w+|\binterface\s+\w+', text): continue
    if 'RequestGuard.php' not in text and path.name != 'RequestGuard.php': unguarded.append(str(relative))
print('Unguarded first-party executable entrypoints:', json.dumps(unguarded))
insecure = []
for path in files:
    text = path.read_text(encoding='utf-8-sig')
    if re.search(r'CURLOPT_SSL_(?:VERIFYPEER|VERIFYHOST)\s*(?:=>|,)\s*(?:false|0)\b|[\'"]verify_peer(?:_name)?[\'"]\s*=>\s*false', text):
        insecure.append(str(path.relative_to(root)))
print('Disabled TLS verification:', json.dumps(insecure))
values = dict(line.split('=', 1) for line in (root / '.env.example').read_text().splitlines() if line and not line.startswith('#') and '=' in line)
for name, value in values.items():
    if not value: values[name] = 'fixture-' + name.lower()
values['SESSION_ENCRYPTION_KEY'] = 'a' * 64
(root / 'tmp/compose-test.env').write_text('\n'.join(f'{key}={value}' for key, value in values.items()) + '\n')
summary = {'php_files': len(files), 'syntax_issues': failures, 'unguarded': unguarded, 'insecure_tls': insecure}
(root / 'tmp/verification.json').write_text(json.dumps(summary, indent=2))
sys.exit(bool(failures or unguarded or insecure))
