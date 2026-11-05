#!/usr/bin/env bash
set -euo pipefail
ROOT=/home/sasuites/htdocs/suitescrepusculo.com.br
echo '=== SITE FILES ==='
sudo -n find "$ROOT" -maxdepth 2 -type f \( -name '*.php' -o -name '.htaccess' \) -printf '%P\n' 2>/dev/null | head -n 90
echo '=== ADMIN ROUTES ==='
for p in /admin /admin/ /painel /painel/ /login /login.php /admin/login.php /painel/login.php; do
  printf '%s ' "$p"; curl -ksS -o /dev/null -w '%{http_code} %{redirect_url}\n' --max-time 8 "https://suitescrepusculo.com.br$p" || true
done
echo '=== IMAGE DIRECTORIES ==='
sudo -n find "$ROOT" -maxdepth 4 -type d \( -iname '*upload*' -o -iname '*image*' -o -iname '*foto*' -o -iname '*media*' \) -print 2>/dev/null | head -n 75
echo '=== IMAGE COUNTS ==='
sudo -n find "$ROOT" -maxdepth 6 -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' \) 2>/dev/null | awk 'END {print NR}'
echo '=== SOURCE IMAGE REFERENCES ==='
sudo -n grep -RhoE '(src|href)=["\x27][^"\x27]+\.(jpg|jpeg|png|webp)(\?[^"\x27]*)?' "$ROOT" --include='*.php' --include='*.html' 2>/dev/null | head -n 30 || true
echo '=== PHP ERROR RECENT ==='
sudo -n tail -n 35 /home/sasuites/logs/php/error.log 2>/dev/null | sed -E 's/(password|token|secret|api.key)[^ ]*/[REDACTED]/Ig' || true
