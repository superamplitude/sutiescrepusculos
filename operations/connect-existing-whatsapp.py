#!/usr/bin/env python3
import os,pathlib,subprocess,pwd,json,urllib.request
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
pid=subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()
env=dict(x.split('=',1) for x in pathlib.Path('/proc/'+pid+'/environ').read_bytes().decode().split('\0') if '=' in x)
token=env.get('WACALLS_API_TOKEN','').strip()
if not token:
 p=pathlib.Path('/etc/superamplitude/crm-voice-app.env')
 if p.exists():
  values={line.split('=',1)[0]:line.split('=',1)[1].strip().strip(chr(34)+chr(39)) for line in p.read_text().splitlines() if '=' in line and not line.startswith('#')}
  token=values.get('WACALLS_API_TOKEN','')
assert token,'Existing gateway token unavailable'
url=env.get('WACALLS_API_BASE_URL','http://127.0.0.1:18080').rstrip('/')
assert url in ['http://127.0.0.1:18180'],'Existing gateway must be one of the already authorized local endpoints'
req=urllib.request.Request(url+'/api/sessions',headers={'Authorization':'Bearer '+token})
with urllib.request.urlopen(req,timeout=15) as r:assert r.status==200
target=pathlib.Path('/etc/crepusculo/wacalls.env');assert not target.exists(),'Do not replace an existing project credential'
service=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br/app/Services/AelWaCallsService.php')
old_service=service.read_bytes()
assert __import__('hashlib').sha256(old_service).hexdigest()=='14cf045cf875ff2957716c64100e8cc889db87934348db2bc8c47dbbbe052ff5','Service baseline changed'
candidate=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/e99e746f8e50a86b4a9ed5272ce7cf1ee9c2cb85/app/Services/AelWaCallsService.php',timeout=20).read()
assert __import__('hashlib').sha256(candidate).hexdigest()=='dfaff72b4be2f6342f81358a5b343ac950834c5d6d2b6c9368c11fa598e1903b'
subprocess.run(['php','-l'],input=candidate,check=True,capture_output=True)
user=pwd.getpwnam('sasuites');target.parent.mkdir(parents=True,exist_ok=True)
try:
 service.write_bytes(candidate)
 target.write_text('WACALLS_API_TOKEN='+token+'\nWACALLS_API_BASE_URL='+url+'\n');os.chown(target,0,user.pw_gid);target.chmod(0o640)
 code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=(new App\Services\AelWaCallsService())->status(1);echo json_encode(['configured'=>$s['configured'],'session_exists'=>$s['exists'],'paired'=>$s['paired'],'state'=>$s['state']]);"""
 result=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],check=True,capture_output=True,text=True)
 print('WHATSAPP_GATEWAY_URL='+url)
 print('WHATSAPP_PROJECT_STATE='+result.stdout)
 print('EXISTING_GATEWAY_REUSED=1; NEW_GATEWAYS=0; SESSION_MUTATIONS=0; BACKUPS_CREATED=0')
except BaseException:
 service.write_bytes(old_service);target.unlink(missing_ok=True);raise
