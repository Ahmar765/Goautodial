from pathlib import Path
import re, json, zipfile
root = Path(__file__).resolve().parent.parent
code = (root / 'tmp/update_deployment_docs.py').read_text(encoding='utf-8')
# Regenerate HTML only; leave the already revised Markdown untouched.
exec(code[code.index('def inline(text):'):], {'root': root, 're': re, '__builtins__': __builtins__, 'html': __import__('html')})
quick = (root / 'docs/DEPLOYMENT_QUICK_GUIDE.html').read_text(encoding='utf-8')
assert quick.count('<section class="page">') == 3
assert quick.count('<footer>') == 3
assert '<code>GOAUTODIAL_CONFIG_FILE' in quick
assert 'Remove the offline fallback' not in quick
assert 'goautodial/web-interface' == json.loads((root / 'composer.json').read_text())['name']
config = (root / 'config/production.php.example').read_text()
assert 'SESSION_ENCRYPTION_KEY' in config and 'SMTP_ENCRYPTION_SECRET' in config
print('Handover files verified; three quick-guide page sections, current configuration instructions and valid Composer JSON.')
backup = Path(r'C:\Users\AiBit\AppData\Local\Temp\goautodial-before-fixes-8imwnc3k\source.zip')
if backup.exists():
    changed = []
    with zipfile.ZipFile(backup) as archive:
        for info in archive.infolist():
            if info.is_dir(): continue
            relative = info.filename.replace('\\', '/')
            target = root / relative
            if target.is_file() and target.read_bytes() != archive.read(info): changed.append(relative)
    (root / 'docs/DEPLOYMENT_MODIFIED_FILES.txt').write_text('Existing files changed compared with the pre-fix source backup. New files are described in DEPLOYMENT_FIXES.md.\n\n' + '\n'.join(sorted(changed)) + '\n')
    print(f'Recorded {len(changed)} changed existing files for review.')
