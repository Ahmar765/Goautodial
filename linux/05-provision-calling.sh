#!/usr/bin/env bash
# 05-provision-calling.sh — WebRTC phone, agent, campaign dial prefix, DID via goAPI/SQL
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="${WEB_ROOT:-/var/www/html}"

if [[ -f "$SCRIPT_DIR/server.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$SCRIPT_DIR/server.env"
  set +a
elif [[ -f "$SCRIPT_DIR/server.env.example" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$SCRIPT_DIR/server.env.example"
  set +a
fi

SIP_DIAL_PREFIX="${SIP_DIAL_PREFIX:-9}"
SIP_DID="${SIP_DID:-${SIP_OUTBOUND_CID:-15123916660}}"
SIP_OUTBOUND_CID="${SIP_OUTBOUND_CID:-15123916660}"
SIP_CARRIER_ID="${SIP_CARRIER_ID:-TELNYX}"
PROVISION_AGENT="${PROVISION_AGENT:-agent1}"
PROVISION_AGENT_PASS="${PROVISION_AGENT_PASS:-agent123}"
PROVISION_PHONE="${PROVISION_PHONE:-8001}"
PROVISION_PHONE_PASS="${PROVISION_PHONE_PASS:-Go$(date +%Y)}"
PROVISION_CAMPAIGN_ID="${PROVISION_CAMPAIGN_ID:-TELOUT}"
PROVISION_CAMPAIGN_NAME="${PROVISION_CAMPAIGN_NAME:-Telnyx Outbound}"
PROVISION_INGROUP="${PROVISION_INGROUP:-TELIN}"
PROVISION_USER_GROUP="${PROVISION_USER_GROUP:-ADMIN}"

if [[ -f "$WEB_ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$WEB_ROOT/.env"
  set +a
fi

GO_API_BASE_URL="${GO_API_BASE_URL:-http://127.0.0.1/goAPIv2}"
GO_USER="${GO_API_USER:-goAPI}"
GO_PASS="${GO_API_PASS:-KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK}"

SERVER_IP="${SIP_SERVER_IP:-}"
if [[ -z "$SERVER_IP" && -f /etc/astguiclient.conf ]]; then
  SERVER_IP=$(awk -F'=>' '/VARserver_ip/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' /etc/astguiclient.conf || true)
fi
SERVER_IP="${SERVER_IP:-127.0.0.1}"

go_post() {
  local path="$1"
  shift
  curl -sS -m 30 -X POST "${GO_API_BASE_URL%/}/${path}" \
    --data-urlencode "goUser=${GO_USER}" \
    --data-urlencode "goPass=${GO_PASS}" \
    --data-urlencode "responsetype=json" \
    --data-urlencode "session_user=goadmin" \
    --data-urlencode "log_user=goadmin" \
    --data-urlencode "log_group=ADMIN" \
    "$@" || true
}

echo "==> Provision calling defaults (phone/agent/campaign/DID)"

# Phone (WebRTC-capable SIP extension)
PHONE_RESP=$(go_post "goPhones/goAPI.php" \
  --data-urlencode "goAction=goAddPhones" \
  --data-urlencode "seats=1" \
  --data-urlencode "extension=${PROVISION_PHONE}" \
  --data-urlencode "server_ip=${SERVER_IP}" \
  --data-urlencode "pass=${PROVISION_PHONE_PASS}" \
  --data-urlencode "protocol=SIP" \
  --data-urlencode "dialplan_number=9999${PROVISION_PHONE}" \
  --data-urlencode "voicemail_id=${PROVISION_PHONE}" \
  --data-urlencode "status=ACTIVE" \
  --data-urlencode "active=Y" \
  --data-urlencode "fullname=WebRTC Agent Phone" \
  --data-urlencode "gmt=-5:00" \
  --data-urlencode "messages=0" \
  --data-urlencode "old_messages=0" \
  --data-urlencode "user_group=${PROVISION_USER_GROUP}")
echo "Phone API: ${PHONE_RESP:0:300}"

# Agent user
USER_RESP=$(go_post "goUsers/goAPI.php" \
  --data-urlencode "goAction=goAddUser" \
  --data-urlencode "user=${PROVISION_AGENT}" \
  --data-urlencode "pass=${PROVISION_AGENT_PASS}" \
  --data-urlencode "full_name=Live Agent" \
  --data-urlencode "user_group=${PROVISION_USER_GROUP}" \
  --data-urlencode "email=${PROVISION_AGENT}@localhost.com" \
  --data-urlencode "active=Y" \
  --data-urlencode "seats=1" \
  --data-urlencode "phone_login=${PROVISION_PHONE}" \
  --data-urlencode "phone_pass=${PROVISION_PHONE_PASS}" \
  --data-urlencode "server_ip=${SERVER_IP}")
echo "User API: ${USER_RESP:0:300}"

# Outbound campaign with dial prefix matching Telnyx carrier
CAMP_RESP=$(go_post "goCampaigns/goAPI.php" \
  --data-urlencode "goAction=goAddCampaign" \
  --data-urlencode "campaign_id=${PROVISION_CAMPAIGN_ID}" \
  --data-urlencode "campaign_name=${PROVISION_CAMPAIGN_NAME}" \
  --data-urlencode "campaign_type=outbound" \
  --data-urlencode "dial_method=MANUAL" \
  --data-urlencode "auto_dial_level=OFF" \
  --data-urlencode "dial_prefix=${SIP_DIAL_PREFIX}" \
  --data-urlencode "caller_id=${SIP_OUTBOUND_CID}" \
  --data-urlencode "status=ACTIVE" \
  --data-urlencode "campaign_recording=ONDEMAND")
echo "Campaign API: ${CAMP_RESP:0:300}"

# DID (inbound number)
DID_RESP=$(go_post "goInbound/goAPI.php" \
  --data-urlencode "goAction=goAddDID" \
  --data-urlencode "did_pattern=${SIP_DID}" \
  --data-urlencode "did_description=Telnyx DID ${SIP_DID}" \
  --data-urlencode "user_group=${PROVISION_USER_GROUP}" \
  --data-urlencode "did_active=Y" \
  --data-urlencode "did_route=AGENT" \
  --data-urlencode "user=${PROVISION_AGENT}")
echo "DID API: ${DID_RESP:0:300}"

# SQL fallbacks for webrtc flag + campaign dial_prefix / callerid
AST_CONF="/etc/astguiclient.conf"
if [[ -f "$AST_CONF" ]]; then
  DB_HOST=$(awk -F'=>' '/VARDB_server/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo localhost)
  DB_NAME=$(awk -F'=>' '/^VARDB_database/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisk)
  DB_USER=$(awk -F'=>' '/^VARDB_user / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku)
  DB_PASS=$(awk -F'=>' '/^VARDB_pass / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku1234)

  export DB_HOST DB_NAME DB_USER DB_PASS PROVISION_AGENT PROVISION_AGENT_PASS PROVISION_PHONE PROVISION_PHONE_PASS
  export PROVISION_CAMPAIGN_ID SIP_DIAL_PREFIX SIP_OUTBOUND_CID SIP_DID SERVER_IP
  php -r '
$m = @new mysqli(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), getenv("DB_NAME"));
if ($m->connect_error) { fwrite(STDERR, "SQL skip: ".$m->connect_error."\n"); exit(0); }
$agent = $m->real_escape_string(getenv("PROVISION_AGENT"));
$pass = $m->real_escape_string(getenv("PROVISION_AGENT_PASS"));
$phone = $m->real_escape_string(getenv("PROVISION_PHONE"));
$ppass = $m->real_escape_string(getenv("PROVISION_PHONE_PASS"));
$camp = $m->real_escape_string(getenv("PROVISION_CAMPAIGN_ID"));
$prefix = $m->real_escape_string(getenv("SIP_DIAL_PREFIX"));
$cid = $m->real_escape_string(getenv("SIP_OUTBOUND_CID"));
$did = $m->real_escape_string(getenv("SIP_DID"));
$sip = $m->real_escape_string(getenv("SERVER_IP"));

// Enable WebRTC on agent if column exists
@$m->query("UPDATE vicidial_users SET use_webrtc=1, phone_login=\"$phone\", phone_pass=\"$ppass\", pass=\"$pass\", active=\"Y\" WHERE user=\"$agent\"");
@$m->query("UPDATE phones SET active=\"Y\", status=\"ACTIVE\" WHERE extension=\"$phone\"");
// Campaign dial prefix + CID
@$m->query("UPDATE vicidial_campaigns SET dial_prefix=\"$prefix\", campaign_cid=\"$cid\", active=\"Y\" WHERE campaign_id=\"$camp\"");
// DID active
@$m->query("UPDATE vicidial_inbound_dids SET did_active=\"Y\", did_route=\"AGENT\", user=\"$agent\" WHERE did_pattern=\"$did\"");
echo "SQL provision tweaks applied (best-effort)\n";
'
fi

echo "Provision summary:"
echo "  Agent: ${PROVISION_AGENT} / ${PROVISION_AGENT_PASS}"
echo "  Phone: ${PROVISION_PHONE} / ${PROVISION_PHONE_PASS} (enable WebRTC in UI if needed)"
echo "  Campaign: ${PROVISION_CAMPAIGN_ID} dial_prefix=${SIP_DIAL_PREFIX} cid=${SIP_OUTBOUND_CID}"
echo "  DID: ${SIP_DID}"
echo "  Carrier dial prefix ${SIP_DIAL_PREFIX} must match campaign; Telnyx IP allowlist = PBX public IP"
