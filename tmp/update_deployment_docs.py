from pathlib import Path
import re, html
root = Path(__file__).resolve().parent.parent
path = root / 'docs/LINUX_DEPLOYMENT_GUIDE.md'
text = path.read_text(encoding='utf-8')
text = text.replace('Prepared 5 October 2026', 'Prepared and updated 5 October 2026\n\n**Corrected release:** use `DEPLOYMENT_QUICK_GUIDE.md`, `DEPLOYMENT_FIXES.md` and the committed production configuration examples. Local fixes have been applied; complete the remaining staging checks before launch.')
text = text.replace('Reads `DB_*` environment variables and has unsafe development defaults', 'Loads required `DB_*` settings from environment or protected external configuration')
text = text.replace('Points to localhost; change to the actual goAPIv2 HTTPS URL', 'Loads the configured HTTPS API URL and scopes action credentials to the signed-in account')
text = text.replace('Web and MariaDB only; unsuitable unchanged for production', 'PHP 8.3 web image and MariaDB 10.11; needs secrets, full schemas, HTTPS proxy and staging validation')
text = text.replace('`composer.json` contains metadata without dependency requirements. Do not invent npm, PM2, or Composer build steps for this checkout.', '`composer.json` now requires PHPMailer and PHP 8.3. Install Composer dependencies for the reviewed release; there is still no root npm or PM2 service.')
start = text.index('## 2 Required corrections before public access')
end = text.index('## 3 Information developers need before starting', start)
text = text[:start] + '''## 2 Corrections and remaining launch checks

The failed-login administrator fallback, anonymous debug identity, disabled TLS checks, embedded runtime credentials and hardcoded session cipher have been removed. Executable PHP entrypoints now share authentication, role and CSRF checks. Browser agent/monitor API calls use same-origin proxies. Avatar/attachment handling, logout, email login and PHP 8 compatibility issues were corrected. See `DEPLOYMENT_FIXES.md` for the complete change summary and tests.

Supply new production credentials and a session key; rotate previously embedded or reused credentials. Install and audit Composer dependencies, build the Linux image, import full compatible schemas, and test the intended PHP 8.3 runtime with the real goAPIv2/backend. Verify staff/group/tenant permissions, SMTP, calls, audio and recovery before public access. The local tests use PHP 8.2 and fixtures; they do not validate live dialing.

Keep staging limited to the development team's network until these checks pass. A successful login page response is not evidence of working calls.

''' + text[end:]
text = text.replace('The sample declares Asterisk `13.X`, while the local installer downloads an 18 build. Align these with the tested distribution.', 'The sample requires the installed Asterisk version to be filled explicitly; the local installer downloads an 18 build. Align settings with the tested distribution.')
text = text.replace("  --exclude='uploads/' --exclude='php/Config.php' \\\n  --exclude='php/goCRMAPISettings.php' \\", "  --exclude='uploads/' --exclude='tmp/' --exclude='tests/' --exclude='vendor/' \\")
text = text.replace('Give write access only to verified runtime paths.', 'Copy `uploads/.htaccess` into the shared uploads directory and preserve `img/avatars/.htaccess` with the shared avatars. Install Composer dependencies in the staged release, review/retain the generated lockfile, and run `composer audit`. Give write access only to verified runtime paths.')
text = text.replace('If a custom logo is writable through the UI, provide a separate shared asset path or a reviewed deployment procedure.', 'The corrected company logo uploader writes to the shared uploads directory.')
start = text.index('## 7 Configure secrets and application URLs')
end = text.index('## 8 Prepare or migrate the databases', start)
text = text[:start] + '''## 7 Configure secrets and application URLs

Keep the committed `php/Config.php` and `php/goCRMAPISettings.php` loaders. They read `php/RuntimeConfig.php`; do not replace them with legacy constant/include stubs.

Copy `config/production.php.example` to `/etc/goautodial/production.php`, outside the application directory, and fill the returned configuration array in a secure editor. Set HTTPS `APP_URL` and `GO_API_URL`, backend API credentials, separate GOautodial/Asterisk/Kamailio database settings, `SESSION_DRIVER=database`, and a 64-character hexadecimal `SESSION_ENCRYPTION_KEY`. Generate a new key using `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Keep it stable across normal releases; key rotation invalidates existing sessions.

```bash
sudo chown root:www-data /etc/goautodial/production.php
sudo chmod 0640 /etc/goautodial/production.php
sudo -u www-data php -l /etc/goautodial/production.php
```

Add `env[GOAUTODIAL_CONFIG_FILE] = /etc/goautodial/production.php` to the application's PHP FPM pool and restart FPM. See `deployment/php-fpm-pool.conf.example` and `deployment/php-production.ini`. Supply the same `GOAUTODIAL_CONFIG_FILE` environment variable to CLI jobs and preflight checks. Native PHP does not load `.env`; shell exports do not automatically reach FPM. Environment variables override file values, including empty values.

Leave `TRUSTED_PROXIES` empty for direct HTTPS. For a TLS reverse proxy, configure its exact source address as seen by PHP, preserve the original Host, and forward `X-Forwarded-Proto: https`. The application rejects HTTP in production and rejects hosts that do not match `APP_URL`. Serve at the domain root.

Configure optional SMTP encryption secrets to match the backend. Do not reuse the old public example keys. The optional legacy SIP page uses configured `SIP_DOMAIN`, `SIP_WS_HOST` and `SIP_WS_PORT`; request parameters cannot redirect its credentials. External web forms no longer receive account/password fields and need their own authentication.

Align the stored CRM base URL, timezone and enabled integration settings with production. Once schemas and dependencies are present, run this read-only preflight as the web service user:

```bash
cd /srv/goautodial/releases/release-001
sudo -u www-data env GOAUTODIAL_CONFIG_FILE=/etc/goautodial/production.php php bin/preflight.php
```

`--offline` skips database/API tests; it is not a readiness check. Passing preflight also does not replace staging workflow/call tests.

''' + text[end:]
text = text.replace('Apply the reviewed production corrections and config include stubs.', 'Deploy the corrected configuration loaders and supply the protected external configuration.')
text = text.replace('Ensure the file is root-owned, mode 0644, and ends with a newline.', 'Set `GOAUTODIAL_CONFIG_FILE=/etc/goautodial/production.php` in the cron environment. Ensure the cron definition is root-owned, mode 0644, and ends with a newline.')
start = text.index('## 14 Notes about the supplied Docker setup')
end = text.index('## 15 Handover checklist', start)
text = text[:start] + '''## 14 Notes about the supplied Docker setup

The corrected Dockerfile builds a PHP 8.3 Apache image with required extensions and Composer dependencies. Compose starts this image and MariaDB 10.11, requires secret substitutions, enables restarts/database health checks, preserves uploads/avatars in volumes, and keeps the web listener on `127.0.0.1:8080`. It does not deploy goAPIv2, Asterisk, VICIdial, Kamailio, RTPengine or full schemas.

Copy `.env.example` to a protected `.env` and fill required values. Compose uses it for substitutions; native PHP does not load it. Use `docker compose config --quiet` to validate without printing secrets, then build in staging. Configure an HTTPS reverse proxy with correct Host/forwarded-protocol/trusted-proxy settings. Supply the compatible schemas before preflight or login tests.

Back up existing database and asset volumes before changing images. Review the MariaDB 10.6-to-10.11 upgrade separately; environment password changes do not rotate accounts in an already initialized database volume. The Linux image build and dependency installation were not run locally because the Docker daemon and Composer are unavailable.

In a container, `127.0.0.1` refers to that container. Use private reachable backend/database hosts. Docker-published ports can bypass UFW rules; retain the loopback binding or reviewed Docker firewall controls. [Docker firewall guidance](https://docs.docker.com/engine/install/ubuntu/), [published ports](https://docs.docker.com/engine/network/port-publishing/).

''' + text[end:]
path.write_text(text, encoding='utf-8')
report = root / 'docs/DEPLOYMENT_FIXES.md'
text = report.read_text(encoding='utf-8').replace('Full PHP syntax/entrypoint/TLS scan results are recorded after the final verification below.', '**327 PHP files passed syntax checks without warnings**; the entrypoint scan found no missing guards among first-party executable endpoints, and the PHP TLS scan found no disabled verification.')
report.write_text(text, encoding='utf-8')

def inline(text):
    text = html.escape(text)
    text = re.sub(r'`([^`]+)`', r'<code>\1</code>', text)
    text = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', text)
    return re.sub(r'\[([^\]]+)\]\((https?://[^)]+)\)', r'<a href="\2">\1</a>', text)

def markdown(text):
    lines, out, index = text.splitlines(), [], 0
    while index < len(lines):
        line = lines[index]
        if not line.strip(): index += 1; continue
        if line.startswith('```'):
            index += 1; code = []
            while index < len(lines) and not lines[index].startswith('```'): code.append(lines[index]); index += 1
            out.append('<pre><code>' + html.escape('\n'.join(code)) + '</code></pre>'); index += 1; continue
        match = re.match(r'^(#{1,6})\s+(.*)', line)
        if match:
            n = len(match[1]); out.append(f'<h{n}>{inline(match[2])}</h{n}>'); index += 1; continue
        if line.startswith('|'):
            rows = []
            while index < len(lines) and lines[index].startswith('|'):
                row = [cell.strip() for cell in lines[index].strip().strip('|').split('|')]
                if not all(re.fullmatch(r'[-: ]+', cell) for cell in row): rows.append(row)
                index += 1
            out.append('<table><thead><tr>' + ''.join('<th>'+inline(cell)+'</th>' for cell in rows[0]) + '</tr></thead><tbody>')
            for row in rows[1:]: out.append('<tr>' + ''.join('<td>'+inline(cell)+'</td>' for cell in row) + '</tr>')
            out.append('</tbody></table>'); continue
        if re.match(r'^(?:- |\d+\. )', line):
            ordered = bool(re.match(r'^\d+\. ', line)); tag = 'ol' if ordered else 'ul'; out.append('<'+tag+'>')
            while index < len(lines) and re.match(r'^(?:- |\d+\. )', lines[index]):
                out.append('<li>'+inline(re.sub(r'^(?:- |\d+\. )', '', lines[index]))+'</li>'); index += 1
            out.append('</'+tag+'>'); continue
        paragraph = [line]; index += 1
        while index < len(lines) and lines[index].strip() and not re.match(r'^(?:#|```|\||- |\d+\. )', lines[index]): paragraph.append(lines[index]); index += 1
        out.append('<p>'+inline(' '.join(paragraph))+'</p>')
    return '\n'.join(out)

quick = root / 'docs/DEPLOYMENT_QUICK_GUIDE.html'
css = re.search(r'<style>(.*?)</style>', quick.read_text(encoding='utf-8'), re.S)[1]
source = (root / 'docs/DEPLOYMENT_QUICK_GUIDE.md').read_text(encoding='utf-8')
pages = re.split(r'^## Page \d+ (.*)$', source, flags=re.M)
body = '<div class="toolbar"><button onclick="window.print()">Print or save as PDF</button> Choose A4 and turn off browser headers and footers.</div>'
for page in range(3):
    title, content = pages[1 + page * 2:3 + page * 2]
    body += '<section class="page"><div class="eyebrow">GOautodial Linux deployment</div>'
    if page == 0: body += '<h1>Deployment essentials</h1><p class="subtitle">Corrected release | 5 October 2026</p>'
    body += '<h2>'+inline(title)+'</h2>'+markdown(content)+'<footer>GOautodial developer handover <span>'+str(page+1)+' / 3</span></footer></section>'
quick.write_text('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>GOautodial Deployment Essentials</title><style>'+css+'</style></head><body>'+body+'</body></html>', encoding='utf-8')
for name in ('LINUX_DEPLOYMENT_GUIDE', 'DEPLOYMENT_FIXES'):
    source = (root / ('docs/'+name+'.md')).read_text(encoding='utf-8')
    style = 'body{font:11pt/1.5 Arial,sans-serif;max-width:1000px;margin:32px auto;padding:0 24px;color:#17212a}h1,h2,h3{line-height:1.2}pre{white-space:pre-wrap;background:#f2f5f7;padding:12px}code{font:9pt Consolas,monospace;overflow-wrap:anywhere}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px;text-align:left}th{background:#edf3f7}a{color:#17527e}@media print{@page{size:A4;margin:16mm}body{margin:0;padding:0}h2,h3{break-after:avoid}pre,tr{break-inside:avoid}}'
    (root / ('docs/'+name+'.html')).write_text('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'+html.escape(name.replace('_',' '))+'</title><style>'+style+'</style></head><body>'+markdown(source)+'</body></html>', encoding='utf-8')
print('Updated deployment guides and generated HTML handovers; quick guide retains three page sections.')
