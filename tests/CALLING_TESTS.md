# Calling module tests

Local results on 5 October 2026: **39 HTTP checks and 84 rendered JavaScript checks passed**. The existing 60 security checks and browser CSRF checks also passed.

The HTTP tests execute the real `AgentAPI.php`, `MonitorAPI.php`, and standalone SIP renderer. A dummy backend returns simulated responses for campaign selection, login, dialing, hangup, disposition, transfer, pause/resume, and logout. This verifies forwarding, identity, permissions, encoding, and error handling; it does not verify those operations inside the telephony system.

The JavaScript tests execute the real rendered agent and admin phone scripts with mocked media and registration events. They also validate the configuration with the bundled JsSIP libraries without starting a connection. The full `GOagentJS.php` output is rendered with dummy backend settings, real English translations, and a substitute for the unused UI database constructor, then checked for JavaScript syntax.

## Run locally

Use PHP 8.2 with native cURL, mysqli, and OpenSSL, plus Node.js. Run from the project root. These commands assume the PHP extensions are already enabled.

Start the following servers in two separate terminals:

```sh
php -S 127.0.0.1:8097 tests/calling_frontend_router.php
php -S 127.0.0.1:8098 tests/calling_backend_router.php
```

In a third terminal, run in this order:

```sh
php tests/calling_render_fixture.php configured
php tests/calling_render_fixture.php fallback
php tests/calling_script_fixture.php
php tests/calling_http_test.php
node tests/calling_client_test.cjs
php tests/security_test.php
node tests/security_client_test.cjs
```

Stop both fixture servers afterward. The fixtures use dummy credentials, development file sessions, and loopback addresses. Never use them as production entry points. Generated fixture output is written to the ignored, web-blocked `tmp/` directory.

## Live acceptance still required

This workspace has no configured live API, databases, SIP domain, or WebSocket endpoint. Local tests cannot establish that calls work in production. Against an authorized test environment, verify:

1. HTTPS page loads; microphone permission succeeds; the agent extension registers over WSS.
2. Agent logs into a valid campaign and receives an inbound test call with audio in both directions.
3. Agent dials an authorized test destination with two-way audio and working hangup/disposition.
4. Mute, DTMF, hold, transfer, pause/resume, and logout work as applicable.
5. Registration loss, microphone denial, and backend failure produce a usable error state; the agent can recover afterward.

Record actual live results separately. No real calls were placed by the local test suite.
