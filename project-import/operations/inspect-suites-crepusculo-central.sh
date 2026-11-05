#!/usr/bin/env bash
set -euo pipefail
echo '=== SUITES VHOST ==='
sudo -n sed -n '1,210p' /etc/nginx/sites-enabled/suitescrepusculo.com.br.conf | sed -E 's/(password|token|secret|api.key)[^;]*/\1=[REDACTED]/Ig'
echo '=== SITE ROOTS ==='
for base in /home /var/www /opt /srv; do
  [ -d "$base" ] || continue
  sudo -n find "$base" -maxdepth 5 \( -iname '*crepuscul*' -o -iname '*suite*' \) -print 2>/dev/null | head -n 65
done
echo '=== CENTRAL ENDPOINTS ==='
for url in https://api.superamplitude.com/communication/health https://api.superamplitude.com/astracalls/health; do
  curl -ksS -o /dev/null -w '%{http_code} %{url_effective}\n' --connect-timeout 4 --max-time 9 "$url" || true
done
echo '=== SITE RESPONSE ==='
curl -ksSI --max-time 10 https://suitescrepusculo.com.br/ | head -n 15
