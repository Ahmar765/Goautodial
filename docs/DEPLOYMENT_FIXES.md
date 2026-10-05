# Pre-deployment fixes

Updated 5 October 2026. Changes apply to the web application in this checkout. The complete telephony backend is a separate dependency.

## What changed

- Removed the failed-login administrator fallback and anonymous debug privileges. Invalid credentials, malformed backend responses, disabled users and backend outages cannot authenticate through that fallback.
- Corrected email login and normalized upstream `userno` responses. Replaced PHP's ignored custom bcrypt salt option with a compatibility helper for existing truncated backend hashes.
- Added shared authentication, role and CSRF checks to executable PHP entrypoints. User/server/system settings mutations require administrator access. Other management endpoints allow staff; agent endpoints use an explicit allowlist. The backend must still enforce group, tenant and record permissions.
- Added same-origin CSRF integration for forms, XHR and fetch, and made logout a POST operation that clears the session and cookie. Restricted self-service password/avatar updates; event deletion is now scoped to its owner and message senders come from the session.
- Replaced the hardcoded session cipher with authenticated AES-256-GCM encryption and a configured key. Database session reads fail closed and writes report errors. Legacy sessions are invalidated; everyone must sign in again after this release.
- Enabled TLS certificate verification for outgoing requests, including SMTP. Shared API requests have bounded timeouts and reject unsuccessful HTTP responses. API payloads cannot replace server-supplied identity fields; legacy requests also use the signed-in user's credentials.
- Routed browser agent/monitor API calls through local authenticated proxies. Encoded agent JavaScript strings, removed redundant account password/hash fields and external password query parameters, cleared local testing URL defaults, and restricted the legacy SIP page to configured destinations. Browser SIP credentials remain necessary for registration.
- Validated avatars using image content, constrained sizes, and rejected SVG/executable payloads. Replaced attachment paths derived from filenames with random names; attachments are served as downloads. Company logo uploads now use the persistent uploads directory.
- Added environment/protected-file configuration and removed active default database/API credentials. Disabled HTTP installers/reset scripts; development resets require an explicit CLI flag and a supplied administrator password.
- Added Apache restrictions for private files, libraries, maintenance tools and executable uploads. Code remains read-only to the web service account in the image.
- Added PHP 8.3 Docker build/configuration, required secret substitutions, persistent asset volumes, database health checks and private database networking. Compose now selects MariaDB 10.11; existing databases require a reviewed upgrade and backup plan.
- Added a modern Composer PHPMailer dependency and loader to replace references to absent legacy library files. Corrected PHP 8 parameter deprecations and a GD image logging error.
- Added `bin/preflight.php` for read-only runtime, configuration, database and backend checks, with redacted output.

## Configuration and intentional behavior changes

Copy `config/production.php.example` outside the application, normally to `/etc/goautodial/production.php`, fill real values, and set `GOAUTODIAL_CONFIG_FILE` in PHP FPM and every CLI job. See `deployment/php-fpm-pool.conf.example`. Do not replace the committed configuration loaders with old include stubs. Environment variables override file values, including empty values.

Required settings include HTTPS `APP_URL` and `GO_API_URL`, API login credentials, all three database connections, database sessions and a 64-character hexadecimal `SESSION_ENCRYPTION_KEY`. Serve at the domain root. A reverse proxy must preserve Host and forward HTTPS only from an address explicitly listed in `TRUSTED_PROXIES`.

SMTP encryption settings must match the backend's encryption format and keys. Do not copy old public example keys into production. The optional legacy `modules/GOagent/jsSIP.php` page now uses `SIP_DOMAIN`, `SIP_WS_HOST` and `SIP_WS_PORT`. External integrations that depended on account passwords in URLs need a separate authentication mechanism. Stricter role checks may require updating intended staff workflows rather than weakening authorization.

## Local validation

- Security regression suite: **60 checks passed**, covering authentication failures, bcrypt compatibility, permission failure behavior, session encryption/storage failures, CSRF, proxy trust, avatar content and upload paths.
- HTTP request-guard suite: **33 checks passed** against a loopback PHP test server, covering unauthenticated requests, roles, CSRF, methods, module actions and message sender spoofing.
- API transport/identity tests passed with dummy credentials and a local fixture server, including URL encoding, multipart requests and failure handling.
- Browser CSRF integration tests passed for XHR, fetch, forms and logout.
- Docker Compose configuration validates with dummy deployment values. The local Docker client warns that its user configuration file is inaccessible.
- **327 PHP files passed syntax checks without warnings**; the entrypoint scan found no missing guards among first-party executable endpoints, and the PHP TLS scan found no disabled verification.

Run the checks on Linux with PHP 8.3 and the extensions installed:

```bash
php tests/security_test.php
node tests/security_client_test.cjs
# Separate terminal; development test fixture only, bound to loopback:
php -S 127.0.0.1:8097 tests/http_guard_router.php
php tests/http_guard_test.php
php tests/api_client_test.php
```

Stop the test server afterwards. Fixtures do not execute full management workflows or connect to a real database. Database session behavior is tested with an in-memory stub, not a live MariaDB server.

## Still required before launch

1. Supply and rotate real credentials, certificates and protected settings; import the complete matching schemas. Back up database/asset volumes before upgrading existing data.
2. Install/review Composer dependencies and the lockfile, audit them, and build the Linux image. Composer is not installed locally and the Docker daemon is unavailable; these steps were not executed here.
3. Run `php bin/preflight.php` as the web service user with production settings. `--offline` checks configuration only and is not evidence of launch readiness. This Windows environment has PHP 8.2 and incomplete extensions, so production preflight deliberately fails here.
4. Test the actual goAPIv2 release: username/email login, bcrypt/plain-password conventions, group/tenant restrictions, password changes, session persistence, logout, campaigns, reports, lead imports and enabled email/integration features.
5. Verify Apache restrictions and HTTPS/proxy behavior on the Linux server, add login rate limiting at the gateway, and test backup restoration and service recovery.
6. Verify SIP/WSS registration, inbound/outbound calls, two-way audio, hold/transfer/dispositions and recordings against the real Asterisk/Kamailio/RTPengine installation.

This pass resolves the identified code/configuration blockers; it is not a complete penetration test or a certification that every legacy workflow is safe. Public access and live dialing should follow staging acceptance.

References used for compatibility: [upstream login response](https://github.com/goautodial/goAPIv2/blob/master/goUsers/goUserLogin.php), [PHPMailer installation](https://github.com/PHPMailer/PHPMailer), [PHP crypt](https://www.php.net/manual/en/function.crypt.php).
