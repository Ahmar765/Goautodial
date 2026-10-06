from pathlib import Path
import shutil
import subprocess

root = Path(__file__).resolve().parent.parent
checkout = root / 'tmp' / 'github-publish'
result = subprocess.run(['git', 'ls-files', '--others', '--exclude-standard', '-z'], cwd=root, capture_output=True, check=True)
files = result.stdout.decode('utf-8').split('\0')
count = 0
for name in files:
    if not name:
        continue
    source = root / name
    if not source.is_file():
        continue
    destination = checkout / name
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, destination)
    count += 1
print(f'Prepared {count} source files in the separate Git checkout.')
