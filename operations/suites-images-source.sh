#!/usr/bin/env bash
set -euo pipefail
test "$RUNNER_NAME" = gerente-vps
python3 - <<'PY'
import subprocess,io,zipfile,tarfile
cmd=['rclone','--config','/etc/rclone/rclone.conf']
p=subprocess.run(cmd+['cat','gdrive:backup/Suites-Crepusculo/Backup_A/SUITES-CREPUSCULO-FULL-20261024.zip'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=600)
if p.returncode:
 import urllib.request
 url='https://drive.usercontent.google.com/download?id=1koyo6TgLhO3kjgM-6cB4ZmrSvcmR0qCn&export=download&confirm=t'
 with urllib.request.urlopen(url,timeout=180) as response: data=response.read()
 if not zipfile.is_zipfile(io.BytesIO(data)):
  print('SOURCE_NOT_ZIP',len(data)); raise SystemExit(1)
 p.stdout=data
print('SOURCE_BYTES',len(p.stdout))
z=zipfile.ZipFile(io.BytesIO(p.stdout))
for n in z.infolist()[:80]: print(n.filename,n.file_size)
PY
