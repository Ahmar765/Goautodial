from pathlib import Path
import html, re

root = Path(__file__).resolve().parent.parent
source = (root / 'docs/DEPLOYMENT_QUICK_GUIDE.md').read_text(encoding='utf-8-sig')
source = source.replace('when using the legacy SIP page', 'when using `modules/GOagent/jsSIP.php`')
(root / 'docs/DEPLOYMENT_QUICK_GUIDE.md').write_text(source, encoding='utf-8')

# Reuse the existing Markdown renderer and printable layout.
builder = (root / 'tmp/update_deployment_docs.py').read_text(encoding='utf-8')
namespace = {'root': root, 'html': html, 're': re}
exec(builder[builder.index('def inline(text):'):builder.index("quick = root / 'docs/DEPLOYMENT_QUICK_GUIDE.html'")], namespace)
render = namespace['markdown']
inline = namespace['inline']
target = root / 'docs/DEPLOYMENT_QUICK_GUIDE.html'
css = re.search(r'<style>(.*?)</style>', target.read_text(encoding='utf-8'), re.S)[1]
pages = re.split(r'^## Page \d+ (.*)$', source, flags=re.M)
assert len(pages) == 7
body = '<div class="toolbar"><button onclick="window.print()">Print or save as PDF</button> Choose A4 and turn off browser headers and footers.</div>'
for page in range(3):
    title, content = pages[1 + page * 2:3 + page * 2]
    body += '<section class="page"><div class="eyebrow">GOautodial Linux setup</div>'
    if page == 0:
        body += '<h1>Deployment setup guide</h1><p class="subtitle">Web application and Linux calling backend</p>'
    body += '<h2>' + inline(title) + '</h2>' + render(content)
    body += '<footer>GOautodial setup guide <span>' + str(page + 1) + ' / 3</span></footer></section>'
output = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GOautodial Linux Setup Guide</title><style>' + css + '</style></head><body>' + body + '</body></html>'
target.write_text(output, encoding='utf-8')
# Keep both existing deployment-guide filenames aligned with the requested setup-only handover.
(root / 'docs/LINUX_DEPLOYMENT_GUIDE.md').write_text(source, encoding='utf-8')
(root / 'docs/LINUX_DEPLOYMENT_GUIDE.html').write_text(output, encoding='utf-8')

for name in ('DEPLOYMENT_QUICK_GUIDE', 'LINUX_DEPLOYMENT_GUIDE'):
    markdown = (root / ('docs/' + name + '.md')).read_text(encoding='utf-8')
    document = (root / ('docs/' + name + '.html')).read_text(encoding='utf-8')
    for term in ('DEPLOYMENT_FIXES', 'corrected release', 'regression', 'audit results', 'security checks passed', 'login bypass'):
        assert term.lower() not in markdown.lower() and term.lower() not in document.lower(), term
    assert document.count('<section class="page">') == 3
    assert document.count('<footer>') == 3
print('Updated both Markdown and HTML deployment guides: setup-only content, three printable page sections.')
print('Guide length:', len(source.split()), 'words.')
