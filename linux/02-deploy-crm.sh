#!/usr/bin/env bash
# 02-deploy-crm.sh — deploy this CRM into WEB_ROOT and write production config
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

WEB_ROOT="${WEB_ROOT:-/var/www/html}"
CRM_GIT_URL="${CRM_GIT_URL:-https://github.com/Ahmar765/Goautodial.git}"
CRM_GIT_BRANCH="${CRM_GIT_BRANCH:-main}"
CRM_PUBLIC_URL="${CRM_PUBLIC_URL:-}"
DEPLOY_MODE="${DEPLOY_MODE:-overlay}" # overlay | clone

echo "==> Deploy CRM into $WEB_ROOT"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "ERROR: run as root" >&2
  exit 1
fi

mkdir -p "$WEB_ROOT"

# Prefer copying from the same repo tree (when scripts were scp'd with the repo)
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
if [[ -f "$REPO_ROOT/agent.php" || -f "$REPO_ROOT/index.php" ]]; then
  echo "Overlaying CRM files from $REPO_ROOT -> $WEB_ROOT"
  rsync -a \
    --exclude '.git' \
    --exclude 'linux/server.env' \
    --exclude '.env' \
    --exclude 'php/Config.php' \
    --exclude 'php/goCRMAPISettings.php' \
    "$REPO_ROOT/" "$WEB_ROOT/"
else
  echo "Cloning $CRM_GIT_URL ($CRM_GIT_BRANCH)"
  tmp="$(mktemp -d)"
  git clone --depth 1 --branch "$CRM_GIT_BRANCH" "$CRM_GIT_URL" "$tmp/crm"
  rsync -a --exclude '.git' --exclude 'linux/server.env' --exclude '.env' \
    "$tmp/crm/" "$WEB_ROOT/"
  rm -rf "$tmp"
fi

# Pull DB defaults from astguiclient.conf when present
AST_CONF="/etc/astguiclient.conf"
if [[ ! -f "$AST_CONF" ]]; then
  AST_CONF="$WEB_ROOT/astguiclient.conf"
fi

read_conf() {
  local key="$1" default="$2"
  if [[ -f "$AST_CONF" ]]; then
    local v
    v=$(awk -F'=>' -v k="$key" '$1 ~ k {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" || true)
    if [[ -n "${v:-}" ]]; then
      echo "$v"
      return
    fi
  fi
  echo "$default"
}

DB_HOST="$(read_conf 'VARDBgo_server' '127.0.0.1')"
DB_NAME="$(read_conf 'VARDBgo_database' 'goautodial')"
DB_USER="$(read_conf 'VARDBgo_user' 'goautodialu')"
DB_PASS="$(read_conf 'VARDBgo_pass' 'goautodialu1234')"
DB_PORT="$(read_conf 'VARDBgo_port' '3306')"
DB_AST="$(read_conf 'VARDB_database' 'asterisk')"
DB_KAM_HOST="$(read_conf 'VARDBgokam_server' '127.0.0.1')"
DB_KAM_NAME="$(read_conf 'VARDBgokam_database' 'kamailio')"
DB_KAM_USER="$(read_conf 'VARDBgokam_user' 'kamailiou')"
DB_KAM_PASS="$(read_conf 'VARDBgokam_pass' 'kamailiou1234')"

HOST_FQDN="$(hostname -f 2>/dev/null || hostname)"
if [[ -z "$CRM_PUBLIC_URL" ]]; then
  CRM_PUBLIC_URL="http://${HOST_FQDN}"
fi
# goAPIv2 is usually under the same vhost
GO_API_BASE_URL="${GO_API_BASE_URL:-${CRM_PUBLIC_URL%/}/goAPIv2}"
# Prefer loopback for server-side PHP -> API when possible
if [[ -d "$WEB_ROOT/goAPIv2" || -d /var/www/html/goAPIv2 ]]; then
  GO_API_BASE_URL="${GO_API_BASE_URL_OVERRIDE:-http://127.0.0.1/goAPIv2}"
fi

umask 077
cat > "$WEB_ROOT/.env" <<EOF
APP_ENV=production
GO_API_BASE_URL=${GO_API_BASE_URL}
GO_API_USER=goAPI
GO_API_PASS=KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_NAME=${DB_NAME}
DB_NAME_ASTERISK=${DB_AST}
DB_HOST_KAMAILIO=${DB_KAM_HOST}
DB_NAME_KAMAILIO=${DB_KAM_NAME}
DB_USERNAME_KAMAILIO=${DB_KAM_USER}
DB_PASSWORD_KAMAILIO=${DB_KAM_PASS}
CRM_LOGIN_LOCAL_DB_FALLBACK=false
EOF
chmod 640 "$WEB_ROOT/.env"
chown apache:apache "$WEB_ROOT/.env" 2>/dev/null || chown www-data:www-data "$WEB_ROOT/.env" 2>/dev/null || true

# Prefer env-driven Config / goCRMAPISettings from this repo (overwrite stock samples)
if [[ -f "$REPO_ROOT/php/Config.php" ]]; then
  cp -a "$REPO_ROOT/php/Config.php" "$WEB_ROOT/php/Config.php"
elif [[ ! -f "$WEB_ROOT/php/Config.php" && -f "$WEB_ROOT/php/Config.php-sample" ]]; then
  cp -a "$WEB_ROOT/php/Config.php-sample" "$WEB_ROOT/php/Config.php"
fi

if [[ -f "$REPO_ROOT/php/goCRMAPISettings.php" ]]; then
  cp -a "$REPO_ROOT/php/goCRMAPISettings.php" "$WEB_ROOT/php/goCRMAPISettings.php"
elif [[ ! -f "$WEB_ROOT/php/goCRMAPISettings.php" && -f "$WEB_ROOT/php/goCRMAPISettings.php-sample" ]]; then
  sed "s|https://HOSTNAME/goAPIv2|${GO_API_BASE_URL}|g" \
    "$WEB_ROOT/php/goCRMAPISettings.php-sample" > "$WEB_ROOT/php/goCRMAPISettings.php"
fi

chmod 640 "$WEB_ROOT/php/Config.php" "$WEB_ROOT/php/goCRMAPISettings.php" 2>/dev/null || true

# SELinux contexts if enforcing
if command -v getenforce >/dev/null 2>&1 && [[ "$(getenforce)" == "Enforcing" ]]; then
  chcon -R -t httpd_sys_rw_content_t "$WEB_ROOT" 2>/dev/null || true
  setsebool -P httpd_can_network_connect 1 2>/dev/null || true
fi

echo "CRM deployed."
echo "  WEB_ROOT=$WEB_ROOT"
echo "  GO_API_BASE_URL=$GO_API_BASE_URL"
echo "  Public URL hint: $CRM_PUBLIC_URL"
echo "  .env written (not for git)"
