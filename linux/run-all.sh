#!/usr/bin/env bash
# run-all.sh — full Linux live-calling bootstrap on the PBX (as root)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Fix CRLF if copied from Windows
if command -v sed >/dev/null 2>&1; then
  sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$(cd "$SCRIPT_DIR/.." && pwd)/install_goautodial.sh" 2>/dev/null || true
fi

if [[ -f "$SCRIPT_DIR/server.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$SCRIPT_DIR/server.env"
  set +a
fi

export SKIP_REBOOT="${SKIP_REBOOT:-1}"
export SKIP_STACK_INSTALL="${SKIP_STACK_INSTALL:-0}"
export WEB_ROOT="${WEB_ROOT:-/var/www/html}"
export CRM_PUBLIC_URL="${CRM_PUBLIC_URL:-}"
export CRM_GIT_URL="${CRM_GIT_URL:-https://github.com/Ahmar765/Goautodial.git}"
export CRM_GIT_BRANCH="${CRM_GIT_BRANCH:-main}"

chmod +x "$SCRIPT_DIR"/0*.sh "$SCRIPT_DIR"/run-all.sh 2>/dev/null || true

bash "$SCRIPT_DIR/00-check-server.sh"
bash "$SCRIPT_DIR/01-install-stack.sh"
bash "$SCRIPT_DIR/02-deploy-crm.sh"
bash "$SCRIPT_DIR/03-configure-sip-trunk.sh"
bash "$SCRIPT_DIR/05-provision-calling.sh"
bash "$SCRIPT_DIR/06-enable-https.sh" || true
bash "$SCRIPT_DIR/04-smoke-test.sh"

echo ""
echo "All phases finished."
echo "Open ${CRM_PUBLIC_URL:-http://$(hostname -f)} (health: /php/health.php)"
echo "Log in, confirm WebRTC on phone ${PROVISION_PHONE:-8001}, dial prefix ${SIP_DIAL_PREFIX:-9} + number."
echo "Telnyx: allow this VPS public IP on the IP-auth connection."
