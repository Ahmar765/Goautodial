#!/usr/bin/env bash
# 04-smoke-test.sh — verify services + goAPI + TELNYX carrier for live-calling readiness
set -euo pipefail

WEB_ROOT="${WEB_ROOT:-/var/www/html}"
SIP_CARRIER_ID="${SIP_CARRIER_ID:-TELNYX}"
FAIL=0

echo "==> Smoke test"

check_svc() {
  local s="$1"
  if systemctl is-active --quiet "$s" 2>/dev/null; then
    echo "OK  service $s"
  else
    echo "FAIL service $s"
    FAIL=1
  fi
}

for s in mariadb httpd; do
  check_svc "$s"
done
for s in asterisk kamailio; do
  if systemctl list-unit-files "${s}.service" 2>/dev/null | grep -q "${s}.service"; then
    check_svc "$s"
  else
    echo "WARN service $s not installed"
  fi
done

if [[ -f "$WEB_ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$WEB_ROOT/.env"
  set +a
  echo "OK  .env present (APP_ENV=${APP_ENV:-unset})"
  if [[ "${APP_ENV:-}" != "production" ]]; then
    echo "WARN APP_ENV is not production"
  fi
  if [[ "${CRM_LOGIN_LOCAL_DB_FALLBACK:-}" == "true" ]]; then
    echo "FAIL CRM_LOGIN_LOCAL_DB_FALLBACK should be false on production"
    FAIL=1
  else
    echo "OK  local login fallback disabled"
  fi
else
  echo "FAIL missing $WEB_ROOT/.env"
  FAIL=1
fi

GO_API_BASE_URL="${GO_API_BASE_URL:-http://127.0.0.1/goAPIv2}"
GO_USER="${GO_API_USER:-goAPI}"
GO_PASS="${GO_API_PASS:-KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK}"
PING_URL="${GO_API_BASE_URL%/}/goCampaigns/goAPI.php"

BODY=$(curl -sS -m 15 -X POST "$PING_URL" \
  --data-urlencode "goUser=${GO_USER}" \
  --data-urlencode "goPass=${GO_PASS}" \
  --data-urlencode "responsetype=json" \
  --data-urlencode "goAction=goGetAllCampaigns" 2>/dev/null || echo "")

if echo "$BODY" | grep -Eqi 'success|campaign|result'; then
  echo "OK  goAPI reachable at $PING_URL"
else
  echo "FAIL goAPI not responding usefully at $PING_URL"
  echo "     preview: ${BODY:0:200}"
  FAIL=1
fi

# Carrier row check
AST_CONF="/etc/astguiclient.conf"
if [[ -f "$AST_CONF" ]] && command -v mysql >/dev/null 2>&1; then
  DB_HOST=$(awk -F'=>' '/VARDB_server/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo localhost)
  DB_NAME=$(awk -F'=>' '/^VARDB_database/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisk)
  DB_USER=$(awk -F'=>' '/^VARDB_user / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku)
  DB_PASS=$(awk -F'=>' '/^VARDB_pass / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku1234)
  COUNT=$(mysql -N -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -e "SELECT COUNT(*) FROM vicidial_server_carriers WHERE carrier_id='${SIP_CARRIER_ID}' AND active='Y';" 2>/dev/null || echo "0")
  if [[ "${COUNT:-0}" -ge 1 ]]; then
    echo "OK  carrier ${SIP_CARRIER_ID} active in DB"
  else
    echo "WARN carrier ${SIP_CARRIER_ID} not found/active (configure SIP or re-run 03)"
  fi
fi

# HTTP health endpoint
HEALTH=$(curl -sS -m 8 "http://127.0.0.1/php/health.php" 2>/dev/null || curl -sS -m 8 "http://127.0.0.1/health.php" 2>/dev/null || echo "")
if echo "$HEALTH" | grep -q '"ok"'; then
  echo "OK  health.php responded"
else
  echo "WARN health.php not reachable yet (docroot/path may differ)"
fi

if command -v asterisk >/dev/null 2>&1; then
  if asterisk -rx "core show version" 2>/dev/null | head -n 1; then
    echo "OK  asterisk CLI"
  else
    echo "FAIL asterisk CLI"
    FAIL=1
  fi
fi

if [[ "$FAIL" -ne 0 ]]; then
  echo "Smoke test FAILED"
  exit 1
fi
echo "Smoke test PASSED — agent WebRTC login, then dial ${SIP_DIAL_PREFIX:-9}+number (CID ${SIP_OUTBOUND_CID:-15123916660})."
