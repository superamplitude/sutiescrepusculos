#!/usr/bin/env python3
"""Install Suites-only call event glue using exact copies of existing CRM libraries.
No new gateway, listener, runner, authentication sessions, or local backups.
"""
import os,pathlib,hashlib,urllib.request,subprocess,json,pwd,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/opt/suites-crepusculo-attendance')
unit=pathlib.Path('/etc/systemd/system/suites-crepusculo-attendance.service')
assert not root.exists() and not unit.exists(),'Already installed: refusing overwrite'
ref='057804591741e19ec79d90a892c574ae6709d5d8'
files={'calls-worker.mjs':'5f12d5bd6a5f674b746e382b10537e04651b622ed1b2226b5e8039bfa9f436b5','context.php':'e93655ff7aa93e719420c692a3bf9d2930b0a9731a45177059a87f7f65deb399','calls-worker.test.mjs':'c696b385fa9e8514ea7d741fee65cbe014ba4c4fc617417f00917400a73f7734'}
libs={'wacalls.mjs':'3c54dd6f413373871f08b00b6f1bb646a73e8b1eb09844537d6942790d31b048','voice-ai-live.mjs':'fcd9ac3483fc6afce8f4b9240f702c5adb086db11de3000ab15797d87e115d8b','panel-natural-voice.mjs':'4f915de6021f226a828fdc5fc254d56757603f525f0dd5910d63b0864ec69f69'}
payload={}
for name,digest in files.items():
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/integrations/attendance/'+name,timeout=20).read()
 assert hashlib.sha256(data).hexdigest()==digest,name
 payload[name]=data
for name,digest in libs.items():
 p=pathlib.Path('/opt/superamplitude/crm/lib')/name
 data=p.read_bytes();assert hashlib.sha256(data).hexdigest()==digest,'CRM source changed '+name
 payload['lib/'+name]=data
crm_pid=subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()
u=pwd.getpwnam('sasuites');root.mkdir(mode=0o750);os.chown(root,u.pw_uid,u.pw_gid)
(root/'lib').mkdir(mode=0o750);os.chown(root/'lib',u.pw_uid,u.pw_gid)
for name,data in payload.items():
 p=root/name;p.write_bytes(data);os.chown(p,u.pw_uid,u.pw_gid);p.chmod(0o640)
 subprocess.run(['php','-l',str(p)] if name.endswith('.php') else ['node','--check',str(p)],check=True,capture_output=True)
(root/'package.json').write_text(json.dumps({'private':True,'type':'module','dependencies':{'werift':'0.24.4'}}))
os.chown(root/'package.json',u.pw_uid,u.pw_gid)
subprocess.run(['sudo','-n','-u','sasuites','npm','install','--omit=dev','--ignore-scripts','--no-audit','--no-fund'],cwd=root,check=True,capture_output=True,timeout=180)
test=subprocess.run(['sudo','-n','-u','sasuites','node',str(root/'calls-worker.test.mjs')],capture_output=True,text=True,timeout=20)
assert test.returncode==0,test.stderr[-1200:];print(test.stdout,end='')
subprocess.run(['sudo','-n','-u','sasuites','node','--input-type=module','-e',"await import('./lib/voice-ai-live.mjs');await import('./lib/wacalls.mjs');console.log('COPIED_CRM_IMPORTS_OK')"],cwd=root,check=True,capture_output=True,timeout=20)
context=subprocess.check_output(['sudo','-n','-u','sasuites','php',str(root/'context.php'),'context','{}'],text=True,timeout=15)
cfg=json.loads(context);assert cfg['enabled'] and bool(cfg.get('api_key')) and len(cfg.get('prompt',''))>100
print('SUITES_CONTEXT_READY=1; EXISTING_PROVIDER_CREDENTIAL_REUSED=1; CREDENTIAL_NOT_EXPORTED=1')
service="""[Unit]
Description=Suites Crepusculo WhatsApp voice attendance using existing CRM modules
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=sasuites
Group=sasuites
WorkingDirectory=/opt/suites-crepusculo-attendance
ExecStart=/usr/bin/node /opt/suites-crepusculo-attendance/calls-worker.mjs
Restart=on-failure
RestartSec=5
RuntimeDirectory=suites-attendance
RuntimeDirectoryMode=0750
UMask=0027
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=read-only
ReadWritePaths=/run/suites-attendance
[Install]
WantedBy=multi-user.target
"""
try:
 unit.write_text(service);unit.chmod(0o644)
 subprocess.run(['systemctl','daemon-reload'],check=True,capture_output=True)
 subprocess.run(['systemctl','enable','--now','suites-crepusculo-attendance.service'],check=True,capture_output=True)
 time.sleep(5)
 assert subprocess.check_output(['systemctl','is-active','suites-crepusculo-attendance.service'],text=True).strip()=='active'
 status=json.loads(pathlib.Path('/run/suites-attendance/status.json').read_text())
 assert status.get('gateway') is True,'Gateway stream unavailable'
 print('SUITES_CALL_ATTENDANCE_STATUS',json.dumps(status))
 for name,digest in libs.items():
  assert hashlib.sha256((pathlib.Path('/opt/superamplitude/crm/lib')/name).read_bytes()).hexdigest()==digest
 assert subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()==crm_pid
 print('CALL_WORKER_INSTALLED=1; COPIED_CRM_MODULES=3; NEW_GATEWAYS=0; CRM_RESTARTS=0; BUSINESS_WRITES=0; SESSIONS_CREATED=0; BACKUPS_CREATED=0; LIVE_CALL_TEST_PENDING_PAIRING=1')
except BaseException:
 subprocess.run(['systemctl','disable','--now','suites-crepusculo-attendance.service'],capture_output=True)
 unit.unlink(missing_ok=True);subprocess.run(['systemctl','daemon-reload'],capture_output=True)
 print('CALL_WORKER_NOT_ENABLED=1');raise
