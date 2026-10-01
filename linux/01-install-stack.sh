#!/usr/bin/env bash
# 01-install-stack.sh — run GOautodial AlmaLinux stack install + enable services + set public IP
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INSTALL_SH="${REPO_ROOT}/install_goautodial.sh"
SKIP_STACK_INSTALL="${SKIP_STACK_INSTALL:-0}"
SKIP_REBOOT="${SKIP_REBOOT:-1}"

# Normalize CRLF if scripts were copied from Windows
if command -v sed >/dev/null 2>&1; then
  sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$INSTALL_SH" 2>/dev/null || true
fi

echo "==> GOautodial stack install"

bash "$SCRIPT_DIR/00-check-server.sh"

# Auto-skip if Asterisk already present unless forced
if [[ "$SKIP_STACK_INSTALL" != "1" ]] && command -v asterisk >/dev/null 2>&1; then
  if asterisk -V >/dev/null 2>&1 || [[ -x /usr/sbin/asterisk ]]; then
    echo "Asterisk already present — setting SKIP_STACK_INSTALL=1 (override with SKIP_STACK_INSTALL=0 FORCE_STACK_INSTALL=1)"
    if [[ "${FORCE_STACK_INSTALL:-0}" != "1" ]]; then
      SKIP_STACK_INSTALL=1
    fi
  fi
fi

if [[ "$SKIP_STACK_INSTALL" == "1" ]]; then
  echo "SKIP_STACK_INSTALL=1 — skipping install_goautodial.sh"
else
  if [[ ! -f "$INSTALL_SH" ]]; then
    echo "ERROR: missing $INSTALL_SH" >&2
    exit 1
  fi
  chmod +x "$INSTALL_SH"
  export SKIP_REBOOT
  echo "Running install_goautodial.sh (SKIP_REBOOT=$SKIP_REBOOT) — this takes a long time..."
  bash "$INSTALL_SH"
fi

echo "==> Detecting public IP for VARserver_ip"
PUBLIC_IP="${PUBLIC_IP:-}"
if [[ -z "$PUBLIC_IP" ]]; then
  PUBLIC_IP=$(curl -4 -sS --max-time 5 https://ifconfig.me 2>/dev/null || true)
fi
if [[ -z "$PUBLIC_IP" ]]; then
  PUBLIC_IP=$(curl -4 -sS --max-time 5 https://api.ipify.org 2>/dev/null || true)
fi
if [[ -z "$PUBLIC_IP" ]]; then
  PUBLIC_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
fi
echo "Using server IP: ${PUBLIC_IP:-unknown}"

if [[ -n "$PUBLIC_IP" && -f /etc/astguiclient.conf ]]; then
  if grep -q 'VARserver_ip' /etc/astguiclient.conf; then
    sed -i "s|^VARserver_ip[[:space:]]*=>.*|VARserver_ip => ${PUBLIC_IP}|" /etc/astguiclient.conf
  else
    echo "VARserver_ip => ${PUBLIC_IP}" >> /etc/astguiclient.conf
  fi
  echo "Wrote VARserver_ip => ${PUBLIC_IP} to /etc/astguiclient.conf"
  echo "TELNYX: In Mission Control, allow this IP on your IP-auth connection: ${PUBLIC_IP}"
fi

echo "==> Enabling core services"
systemctl daemon-reload || true

for svc in mariadb httpd asterisk kamailio; do
  if systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -q "${svc}.service"; then
    systemctl enable "$svc" || true
    systemctl start "$svc" || echo "WARN: could not start $svc"
  else
    echo "WARN: $svc.service not present yet"
  fi
done

for svc in ngcp-rtpengine rtpengine; do
  if systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -q "${svc}.service"; then
    systemctl enable "$svc" || true
    systemctl start "$svc" || echo "WARN: could not start $svc"
  fi
done

if command -v firewall-cmd >/dev/null 2>&1 && systemctl is-active --quiet firewalld; then
  echo "==> Opening firewall ports (HTTP/HTTPS/SIP/RTP/WebRTC)"
  firewall-cmd --permanent --add-service=http || true
  firewall-cmd --permanent --add-service=https || true
  firewall-cmd --permanent --add-port=5060/udp || true
  firewall-cmd --permanent --add-port=5060/tcp || true
  firewall-cmd --permanent --add-port=10000-20000/udp || true
  firewall-cmd --permanent --add-port=8089/tcp || true
  firewall-cmd --permanent --add-port=8088/tcp || true
  firewall-cmd --reload || true
fi

echo "Stack install phase finished."
