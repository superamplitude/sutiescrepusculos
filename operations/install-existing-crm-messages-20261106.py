#!/usr/bin/env python3
"""Install text/audio routing for the Suites session on the existing CRM gateway.
The confirmed working call gateway, call worker and CRM application are not changed or restarted.
"""
import os,pathlib,subprocess,json,urllib.request,urllib.error,hashlib,pwd,grp,time,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/opt/suites-crepusculo-messages');site=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
gateway=pathlib.Path('/opt/waas/app/server.mjs');adapter=pathlib.Path('/opt/waas/app/suites-messages-adapter.mjs')
unit=pathlib.Path('/etc/systemd/system/suites-crepusculo-messages.service')
dropin=pathlib.Path('/etc/systemd/system/sa-crm-whatsapp-gateway.service.d/suites-messages.conf')
assert not root.exists() and not unit.exists() and not adapter.exists() and not dropin.exists(),'Refusing to overwrite an existing integration'
if not pathlib.Path('/usr/bin/ffmpeg').is_file():
 env={**os.environ,'DEBIAN_FRONTEND':'noninteractive','NEEDRESTART_MODE':'l'}
 subprocess.run(['apt-get','update','-qq'],env=env,check=True,capture_output=True,timeout=180)
 subprocess.run(['apt-get','install','-y','--no-install-recommends','ffmpeg'],env=env,check=True,capture_output=True,timeout=240)
 print('OFFICIAL_AUDIO_ENCODER_INSTALLED=1; AUTOMATIC_SERVICE_RESTARTS_DISABLED=1')
ref='e61ef664d164303113331f80aa800d0d8ec01e41'
files={
'integrations/attendance/messages-worker.mjs':'a7de20ac1d2f66957e4506ac109e10d591d9aba6cfb733dfa48b4adf7243ede4',
'integrations/attendance/messages-context.php':'14535a84ad491c1dec37867ce6add52f9505bb0360cc84fa2e95065258d84466',
'integrations/attendance/messages.php':'b07f562371c1f5f968c41ddc46dcf3694e40ef7eafb3e864ed9442a8e87b0867',
'integrations/attendance/messages-server.mjs':'0fa036bc8cd2c415ad5dad6a3eb401241ac38882d4747704a3c6afcc147df5ff',
'integrations/attendance/suites-messages-adapter.mjs':'62bf2c9ed94d46e5bb181b8be469453fc119ab8fed44c2768e3b81efe270ba6e',
'integrations/attendance/messages.test.mjs':'9cd396fcad7cdaabbd71b786110ea082fe4d578d92299d5d15c27f33a78f45e4',
'app/Services/SuitesMessagesService.php':'d8e77551083f2bbb1271ba9122d3906ffe686864b6ba5549c05c3220dbe0710d',
'app/Controllers/AelWhatsappController.php':'1a7b7881628ebed897d6ded9be81fe74c7bb31862471c7aa163bbda6a33658b2',
'pages/messages.php':'72e995e2d2b323db612985f704b6b17dd13fd886687c25dbd50ffddebabeb266'}
payload={}
for name,digest in files.items():
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+name,timeout=20).read()
 assert hashlib.sha256(data).hexdigest()==digest,'Source integrity '+name
 payload[name]=data
 subprocess.run(['php','-l'] if name.endswith('.php') else ['node','--check'],input=data,check=True,capture_output=True)
before={'app/Services/SuitesMessagesService.php':None,'app/Controllers/AelWhatsappController.php':'4cc139a8cfbe6c8176755ecbfc0fa562754bb2e24a72093b47c451ee1f5964f5','pages/messages.php':'dcaf90a68822c87bf5bf2dc142b9dc0e7a77a21a0348836b64a54020d80d3a8d'}
original={}
for name,digest in before.items():
 p=site/name;assert not p.is_symlink()
 data=p.read_bytes() if p.exists() else None
 assert (hashlib.sha256(data).hexdigest() if data is not None else None)==digest,'Site baseline '+name
 original[name]=data
old=gateway.read_bytes();assert hashlib.sha256(old).hexdigest()=='28de8c57cb159cdb1277855369107693543cc3d948aca9aa1c442a818c911a47','Gateway changed'
anchor='const result=await storeWhatsAppMessage(id,msg);'
text=old.decode();assert text.count(anchor)==1
text='import {handleSuitesMessage} from "./suites-messages-adapter.mjs";\n'+text.replace(anchor,'if(id==="suites-crepusculo"){await handleSuitesMessage(s.sock,msg);continue;}\n        '+anchor)
subprocess.run(['node','--check'],input=text.encode(),check=True,capture_output=True)
def pid(service):return subprocess.check_output(['systemctl','show',service,'-p','MainPID','--value'],text=True).strip()
preserve={s:pid(s) for s in ['suites-crepusculo-attendance.service','superamplitude-crm.service']}
voice_files={str(p):hashlib.sha256(p.read_bytes()).hexdigest() for p in [pathlib.Path('/opt/suites-crepusculo-attendance/calls-worker.mjs'),pathlib.Path('/opt/suites-crepusculo-attendance/context.php'),site/'app/Services/AelWaCallsService.php']}
bootstrap="require '"+str(site)+"/app/bootstrap.php';"
schema=bootstrap+r"""
$p=App\Core\DB::pdo();
$c=$p->query("SHOW COLUMNS FROM conversations LIKE 'channel'")->fetch();
$type=strtolower($c['Type']??'');
if(str_starts_with($type,'enum(')&&!str_contains($type,"'whatsapp'"))throw new RuntimeException('Channel schema does not permit WhatsApp');
$p->exec("CREATE TABLE IF NOT EXISTS suites_whatsapp_contacts(jid VARCHAR(80) COLLATE utf8mb4_bin NOT NULL PRIMARY KEY,guest_id BIGINT UNSIGNED NOT NULL,INDEX(guest_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$p->exec("CREATE TABLE IF NOT EXISTS suites_whatsapp_receipts(message_id VARCHAR(190) COLLATE utf8mb4_bin NOT NULL PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,received_at DATETIME NOT NULL,INDEX(conversation_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo 'ADDITIVE_MESSAGE_SCHEMA_READY';
"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',schema],capture_output=True,text=True,timeout=15)
assert r.returncode==0,'Message schema preparation failed: '+r.stderr[-1000:];print(r.stdout)
try:g=grp.getgrnam('suites-atendimento')
except KeyError:
 subprocess.run(['groupadd','--system','suites-atendimento'],check=True,capture_output=True);g=grp.getgrnam('suites-atendimento')
u=pwd.getpwnam('sasuites');gu=pwd.getpwnam('sacrm')
root.mkdir(mode=0o750);os.chown(root,u.pw_uid,u.pw_gid)
for name,data in payload.items():
 if not name.startswith('integrations/'):continue
 p=root/pathlib.Path(name).name;p.write_bytes(data);os.chown(p,u.pw_uid,u.pw_gid);p.chmod(0o640)
test=subprocess.run(['sudo','-n','-u','sasuites','node',str(root/'messages.test.mjs')],capture_output=True,text=True,timeout=30)
assert test.returncode==0,'Message tests failed: '+test.stderr[-1500:];print(test.stdout,end='')
# The installed gateway exports the media downloader; no new package or gateway is installed.
subprocess.run(['sudo','-n','-u','sacrm','node','--input-type=module','-e',"const b=await import('@whiskeysockets/baileys');if(typeof b.downloadMediaMessage!=='function')throw Error('Media downloader missing')"],cwd=gateway.parent,check=True,capture_output=True,timeout=20)
service="""[Unit]
Description=Suites Crepusculo WhatsApp text and audio processor
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=sasuites
Group=suites-atendimento
SupplementaryGroups=sasuites
WorkingDirectory=/opt/suites-crepusculo-messages
ExecStart=/usr/bin/node /opt/suites-crepusculo-messages/messages-worker.mjs
Restart=on-failure
RestartSec=5
RuntimeDirectory=suites-messages
RuntimeDirectoryMode=0750
UMask=0027
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=read-only
ReadWritePaths=/run/suites-messages
[Install]
WantedBy=multi-user.target
"""
restarted=False
try:
 unit.write_text(service);unit.chmod(0o644)
 dropin.parent.mkdir(parents=True,exist_ok=True);dropin.write_text('[Service]\nSupplementaryGroups=suites-atendimento\n')
 adapter.write_bytes(payload['integrations/attendance/suites-messages-adapter.mjs']);os.chown(adapter,gu.pw_uid,gu.pw_gid);adapter.chmod(0o640)
 gateway.write_text(text)
 subprocess.run(['systemctl','daemon-reload'],check=True,capture_output=True)
 subprocess.run(['systemctl','enable','--now','suites-crepusculo-messages.service'],check=True,capture_output=True)
 time.sleep(2)
 assert pid('suites-crepusculo-messages.service')!='0'
 probe=subprocess.run(['sudo','-n','-u','sacrm','-g','suites-atendimento','curl','--silent','--unix-socket','/run/suites-messages/messages.sock','-H','Content-Type: application/json','-d','{"id":"invalid-test","jid":"invalid"}','-o','/dev/null','-w','%{http_code}','http://localhost/message'],capture_output=True,text=True,timeout=10)
 assert probe.returncode==0 and probe.stdout=='422','Restricted socket validation failed'
 subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],check=True,capture_output=True);restarted=True
 time.sleep(5)
 with urllib.request.urlopen('http://127.0.0.1:18190/health',timeout=5) as response:assert response.status==200
 for name,data in payload.items():
  if name not in before:continue
  p=site/name;p.write_bytes(data);os.chown(p,u.pw_uid,u.pw_gid);p.chmod(0o644)
 for service,oldpid in preserve.items():assert pid(service)==oldpid,service+' must not restart'
 for name,digest in voice_files.items():assert hashlib.sha256(pathlib.Path(name).read_bytes()).hexdigest()==digest,'Voice must remain untouched'
 for name,digest in files.items():
  p=site/name if name in before else root/pathlib.Path(name).name
  assert hashlib.sha256(p.read_bytes()).hexdigest()==digest
 with urllib.request.urlopen('http://127.0.0.1:18190/sessions/suites-crepusculo/status',timeout=5) as response:st=json.load(response)
 print('MESSAGE_SESSION_STATE',str((st.get('session')or{}).get('status','offline')))
 print('MESSAGING_INSTALLED=1; RESTRICTED_SOCKET_422=1; FILES_MATCH=9; CALL_WORKER_UNCHANGED=1; CALL_WORKER_RESTARTS=0; CRM_APP_RESTARTS=0; EXISTING_MESSAGING_GATEWAY_RESTARTS=1; NEW_GATEWAYS=0; BACKUPS_CREATED=0; BUSINESS_FIXTURES_CREATED=0')
except BaseException:
 for name,data in original.items():
  p=site/name
  if data is None:p.unlink(missing_ok=True)
  else:p.write_bytes(data)
 gateway.write_bytes(old);adapter.unlink(missing_ok=True);dropin.unlink(missing_ok=True)
 subprocess.run(['systemctl','disable','--now','suites-crepusculo-messages.service'],capture_output=True)
 unit.unlink(missing_ok=True);subprocess.run(['systemctl','daemon-reload'],capture_output=True)
 if restarted:subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],capture_output=True)
 print('MESSAGING_REVERTED_IN_MEMORY=1; VOICE_UNTOUCHED=1');raise
