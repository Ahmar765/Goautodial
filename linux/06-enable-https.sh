#!/usr/bin/env bash
# 06-enable-https.sh — optional Let's Encrypt cert when CRM_PUBLIC_URL is https://domain
set -euo pipefail

CRM_PUBLIC_URL="${CRM_PUBLIC_URL:-}"
if [[ -z "$CRM_PUBLIC_URL" ]]; then
  echo "CRM_PUBLIC_URL not set — skipping HTTPS"
  exit 0
fi

if [[ "$CRM_PUBLIC_URL" != https://* ]]; then
  echo "CRM_PUBLIC_URL is not https — skipping certbot ($CRM_PUBLIC_URL)"
  exit 0
fi

# Extract hostname
HOST="${CRM_PUBLIC_URL#https://}"
HOST="${HOST%%/*}"
if [[ -z "$HOST" || "$HOST" == *example.com* ]]; then
  echo "Placeholder or empty host ($HOST) — skipping certbot"
  exit 0
fi

echo "==> Enabling HTTPS for $HOST"

if ! command -v certbot >/dev/null 2>&1; then
  yum -y install certbot python3-certbot-apache || dnf -y install certbot python3-certbot-apache
fi

systemctl enable --now httpd || true
certbot --apache -d "$HOST" --non-interactive --agree-tos -m "admin@${HOST}" --redirect || {
  echo "WARN: certbot failed — check DNS A record for $HOST points to this VPS"
  exit 0
}

echo "HTTPS enabled for $HOST"
