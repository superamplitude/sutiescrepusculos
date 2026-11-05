#!/usr/bin/env bash
set -euo pipefail
URL='https://drive.google.com/uc?export=download&id=1koyo6TgLhO3kjgM-6cB4ZmrSvcmR0qCn'
echo '=== GOOGLE DRIVE DOWNLOAD PROBE ==='
curl -sSL --max-time 35 -r 0-127 -o /dev/null -w 'http=%{http_code} type=%{content_type} bytes=%{size_download} final=%{url_effective}\n' "$URL" | sed -E 's/(confirm|uuid|token)=[^& ]+/\1=[REDACTED]/g' | cut -c1-260
echo '=== SITE SERVICE ACCOUNT RCLONE ==='
for u in sasuites clp; do if id "$u" >/dev/null 2>&1; then printf '%s ' "$u"; sudo -n -u "$u" rclone listremotes 2>/dev/null | sed -E 's/[^:]+:/configured/' | head -n 3 || true; fi; done
