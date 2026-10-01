#!/usr/bin/env bash
# 03-configure-sip-trunk.sh — insert/update Vicidial SIP carrier via goAPI or SQL
# Supports SIP_AUTHENTICATION=ip (Telnyx IP auth) or reg (username/password register).
set -euo pipefail

WEB_ROOT="${WEB_ROOT:-/var/www/html}"

if [[ -z "${SIP_REG_HOST:-}" ]]; then
  echo "SIP_REG_HOST not set. Skipping carrier insert."
  exit 0
fi

SIP_AUTH="${SIP_AUTHENTICATION:-reg}"
SIP_CARRIER_ID="${SIP_CARRIER_ID:-GOCARRIER}"
SIP_CARRIER_NAME="${SIP_CARRIER_NAME:-PrimaryCarrier}"
SIP_USERNAME="${SIP_USERNAME:-}"
SIP_PASSWORD="${SIP_PASSWORD:-}"
SIP_REG_PORT="${SIP_REG_PORT:-5060}"
SIP_SERVER_IP="${SIP_SERVER_IP:-}"
SIP_DIAL_PREFIX="${SIP_DIAL_PREFIX:-9}"
SIP_CODECS="${SIP_CODECS:-ulaw,alaw}"
SIP_DTMF="${SIP_DTMF:-rfc2833}"
SIP_PROTOCOL="${SIP_PROTOCOL:-SIP}"
SIP_OUTBOUND_CID="${SIP_OUTBOUND_CID:-${SIP_USERNAME}}"
SIP_DID="${SIP_DID:-${SIP_OUTBOUND_CID}}"

if [[ "$SIP_AUTH" != "ip" && -z "$SIP_USERNAME" ]]; then
  echo "SIP_USERNAME required for authentication=${SIP_AUTH}."
  echo "Skipping carrier insert."
  exit 0
fi

# IP auth: use Caller ID as fromuser when username empty
if [[ -z "$SIP_USERNAME" && -n "$SIP_OUTBOUND_CID" ]]; then
  SIP_USERNAME="$SIP_OUTBOUND_CID"
fi

if [[ -z "$SIP_SERVER_IP" ]]; then
  if [[ -f /etc/astguiclient.conf ]]; then
    SIP_SERVER_IP=$(awk -F'=>' '/VARserver_ip/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' /etc/astguiclient.conf || true)
  fi
  SIP_SERVER_IP="${SIP_SERVER_IP:-127.0.0.1}"
fi

if [[ -f "$WEB_ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$WEB_ROOT/.env"
  set +a
fi

GO_API_BASE_URL="${GO_API_BASE_URL:-http://127.0.0.1/goAPIv2}"
GO_USER="${GO_API_USER:-goAPI}"
GO_PASS="${GO_API_PASS:-KToB93bzjGd1RS4mDqePJ6Uk.jgNRrK}"
API_URL="${GO_API_BASE_URL%/}/goCarriers/goAPI.php"

echo "==> Adding SIP carrier via goAPI ($SIP_CARRIER_ID @ $SIP_REG_HOST, auth=$SIP_AUTH)"

RESP=$(curl -sS -X POST "$API_URL" \
  --data-urlencode "goUser=${GO_USER}" \
  --data-urlencode "goPass=${GO_PASS}" \
  --data-urlencode "responsetype=json" \
  --data-urlencode "goAction=goAddCarrier" \
  --data-urlencode "carrier_type=sip" \
  --data-urlencode "carrier_id=${SIP_CARRIER_ID}" \
  --data-urlencode "carrier_name=${SIP_CARRIER_NAME}" \
  --data-urlencode "active=Y" \
  --data-urlencode "protocol=${SIP_PROTOCOL}" \
  --data-urlencode "carrier_description=Telnyx ${SIP_AUTH} auth CID ${SIP_OUTBOUND_CID}" \
  --data-urlencode "user_group=---ALL---" \
  --data-urlencode "authentication=${SIP_AUTH}" \
  --data-urlencode "username=${SIP_USERNAME}" \
  --data-urlencode "password=${SIP_PASSWORD}" \
  --data-urlencode "reg_host=${SIP_REG_HOST}" \
  --data-urlencode "reg_port=${SIP_REG_PORT}" \
  --data-urlencode "sip_server_ip=${SIP_SERVER_IP}" \
  --data-urlencode "codecs=${SIP_CODECS}" \
  --data-urlencode "dtmf=${SIP_DTMF}" \
  --data-urlencode "dialprefix=${SIP_DIAL_PREFIX}" \
  --data-urlencode "manual_server_ip=${SIP_SERVER_IP}" \
  || true)

echo "goAPI response: ${RESP:0:500}"

if ! echo "$RESP" | grep -qi 'success'; then
  echo "WARN: goAPI add may have failed — attempting PHP/mysqli upsert"
  AST_CONF="/etc/astguiclient.conf"
  export DB_HOST DB_NAME DB_USER DB_PASS
  DB_HOST=$(awk -F'=>' '/VARDB_server/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo localhost)
  DB_NAME=$(awk -F'=>' '/^VARDB_database/ {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisk)
  DB_USER=$(awk -F'=>' '/^VARDB_user / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku)
  DB_PASS=$(awk -F'=>' '/^VARDB_pass / {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' "$AST_CONF" 2>/dev/null || echo asterisku1234)

  export SIP_CARRIER_ID SIP_CARRIER_NAME SIP_USERNAME SIP_PASSWORD SIP_REG_HOST SIP_REG_PORT
  export SIP_DTMF SIP_PROTOCOL SIP_SERVER_IP SIP_DIAL_PREFIX SIP_AUTH SIP_OUTBOUND_CID
  php -r '
$carrier_id = getenv("SIP_CARRIER_ID");
$carrier_name = getenv("SIP_CARRIER_NAME");
$user = getenv("SIP_USERNAME");
$pass = getenv("SIP_PASSWORD");
$host = getenv("SIP_REG_HOST");
$port = getenv("SIP_REG_PORT");
$dtmf = getenv("SIP_DTMF");
$protocol = getenv("SIP_PROTOCOL");
$server_ip = getenv("SIP_SERVER_IP");
$prefix = getenv("SIP_DIAL_PREFIX");
$auth = getenv("SIP_AUTH");
$cid = getenv("SIP_OUTBOUND_CID");
if ($cid === false || $cid === "") { $cid = $user; }
$plen = strlen($prefix);
if ($auth === "ip") {
  $reg = "";
  $account = "[{$carrier_id}]\n"
    . "type=peer\n"
    . "host={$host}\n"
    . "port={$port}\n"
    . "fromdomain={$host}\n"
    . "fromuser={$cid}\n"
    . "callerid={$cid}\n"
    . "disallow=all\n"
    . "allow=ulaw\n"
    . "allow=alaw\n"
    . "dtmfmode={$dtmf}\n"
    . "context=trunkinbound\n"
    . "insecure=port,invite\n"
    . "nat=force_rport,comedia\n"
    . "qualify=yes\n";
} else {
  $reg = "register => {$user}:{$pass}@{$host}:{$port}";
  $account = "[{$carrier_id}]\n"
    . "disallow=all\nallow=ulaw\nallow=alaw\n"
    . "type=friend\nusername={$user}\nfromuser={$user}\nsecret={$pass}\n"
    . "host={$host}\nport={$port}\ndtmfmode={$dtmf}\n"
    . "context=trunkinbound\ninsecure=port,invite\nnat=force_rport,comedia\n";
}
$dial = "exten => _{$prefix}.,1,AGI(agi://127.0.0.1:4577/call_log)\n"
  . "exten => _{$prefix}.,n,Set(CALLERID(num)={$cid})\n"
  . "exten => _{$prefix}.,n,Dial({$protocol}/{$carrier_id}/\${EXTEN:{$plen}},,tTo)\n"
  . "exten => _{$prefix}.,n,Hangup\n";
$mysqli = new mysqli(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASS"), getenv("DB_NAME"));
if ($mysqli->connect_error) { fwrite(STDERR, $mysqli->connect_error."\n"); exit(1); }
$mysqli->query("DELETE FROM vicidial_server_carriers WHERE carrier_id=\"".$mysqli->real_escape_string($carrier_id)."\"");
$stmt = $mysqli->prepare("INSERT INTO vicidial_server_carriers (carrier_id, carrier_name, registration_string, template_id, account_entry, protocol, globals_string, dialplan_entry, server_ip, active, carrier_description, user_group) VALUES (?,?,?,?,?,?,?,?,?,\"Y\",?,\"---ALL---\")");
$template = "--NONE--";
$globals = "";
$desc = "Telnyx {$auth} auth CID {$cid}";
$stmt->bind_param("ssssssssss", $carrier_id, $carrier_name, $reg, $template, $account, $protocol, $globals, $dial, $server_ip, $desc);
$stmt->execute();
echo "SQL carrier insert OK\n";
'
fi

if command -v asterisk >/dev/null 2>&1; then
  asterisk -rx "sip reload" 2>/dev/null || true
  asterisk -rx "pjsip reload" 2>/dev/null || true
  asterisk -rx "dialplan reload" 2>/dev/null || true
fi

if [[ -n "${SIP_DID:-}" ]]; then
  echo "NOTE: Map DID ${SIP_DID} to an inbound group in Admin -> Inbound / DIDs (CRM UI)."
fi

echo "SIP trunk phase finished."
echo "  Host: ${SIP_REG_HOST}  Auth: ${SIP_AUTH}  CID/DID: ${SIP_OUTBOUND_CID}"
echo "  Campaign dial prefix: ${SIP_DIAL_PREFIX}"
echo "  Telnyx Mission Control: allow this PBX public IP on the IP Auth connection."
