#!/usr/bin/env bash
set -euo pipefail
echo '=== REMOTE ACCESS TO DRIVE ==='
command -v rclone || true
for u in root gerente-vps-runner; do
  if id "$u" >/dev/null 2>&1; then
    echo "USER=$u"
    sudo -n -u "$u" rclone listremotes 2>/dev/null | sed -E 's/[^:]+:/REMOTE_CONFIGURED/' | head -n 12 || true
  fi
done
echo '=== EXISTING ARCHIVE CANDIDATES ==='
find /opt/superamplitude /home/sasuites -maxdepth 5 -type f -iname '*crepusculo*zip' -printf '%p %s bytes\n' 2>/dev/null | head -n 20 || true
echo '=== DISK FREE ==='
df -h /home/sasuites | tail -n 1
