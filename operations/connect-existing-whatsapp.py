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
assert url=='http://127.0.0.1:18080','Existing gateway URL differs; refuse route substitution'
req=urllib.request.Request(url+'/api/sessions',headers={'Authorization':'Bearer '+token})
with urllib.request.urlopen(req,timeout=15) as r:assert r.status==200
target=pathlib.Path('/etc/crepusculo/wacalls.env');assert not target.exists(),'Do not replace an existing project credential'
user=pwd.getpwnam('sasuites');target.parent.mkdir(parents=True,exist_ok=True)
try:
 target.write_text('WACALLS_API_TOKEN='+token+'\n');os.chown(target,0,user.pw_gid);target.chmod(0o640)
 code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=(new App\Services\AelWaCallsService())->status(1);echo json_encode(['configured'=>$s['configured'],'session_exists'=>$s['exists'],'paired'=>$s['paired'],'state'=>$s['state']]);"""
 result=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],check=True,capture_output=True,text=True)
 print('WHATSAPP_PROJECT_STATE='+result.stdout)
 print('EXISTING_GATEWAY_REUSED=1; NEW_GATEWAYS=0; SESSION_MUTATIONS=0; BACKUPS_CREATED=0')
except BaseException:
 target.unlink(missing_ok=True);raise
