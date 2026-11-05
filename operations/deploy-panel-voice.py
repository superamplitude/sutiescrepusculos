#!/usr/bin/env python3
import os,pathlib,hashlib,urllib.request,urllib.error,subprocess,tempfile,json,pwd,time,secrets,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
ref='2748f8dffbb685c6b881171ad74d5aa6083275f8'
manifest=json.loads('{"chat.php":{"before":"737170a9f407305b55883e76fc36e34321d4cd3232c0f3890ff3019bbb837719","after":"9f1efb99101767b87bd2cf4f6a840bcf38f2258cf004975900878f8af707996c"},"app/Views/layout.php":{"before":"11d40e3650b583d7eeb0b1a7ac5f27fedfebba7a57ee614d38a8706cefd24326","after":"bfa3cb1b42e0aef0a52ad094d7c28f45c423c88943d976d4ce1611646f3a81e8"},"app/Services/PanelVoiceService.php":{"before":null,"after":"778d2cfc0c8f4b4d4d412b74a4b3d37bc00fe060226a2d132f62f2b7e823368b"},"app/Controllers/PanelVoiceController.php":{"before":null,"after":"5c2d922bdf4a774a48b4cd4d0c40d09e934b819ed4a27a29006ce69cb439e7f8"},"assets/css/panel-voice.css":{"before":null,"after":"77ac9b6b1d3defa35094b525842fff22556c72597d09168eddd260533765ece0"},"assets/js/panel-voice.js":{"before":null,"after":"5112e67de12586763e904356f57fa1ae480d1ea05a0328bb77fe367ac53d87cf"}}')
def fetch(path,sha):
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+path,timeout=20).read()
 assert hashlib.sha256(data).hexdigest()==sha,path+' integrity'
 return data
payload={p:fetch(p,v['after']) for p,v in manifest.items()}
qa=fetch('tests/voice-browser-qa.mjs','d333128143320a75c4b402508010d7b50f8d74a7f34bf0bfba9b19dc50de2933')
original={}
for p,v in manifest.items():
 path=root/p;assert not path.is_symlink()
 old=path.read_bytes() if path.exists() else None
 assert (hashlib.sha256(old).hexdigest() if old is not None else None)==v['before'],p+' changed'
 original[p]=old
 if p.endswith('.php'):subprocess.run(['php','-l'],input=payload[p],check=True,capture_output=True)
 if p.endswith('.js'):subprocess.run(['node','--check'],input=payload[p],check=True,capture_output=True)
registry=pathlib.Path('/etc/superamplitude/runtime/communication-projects.json')
registry_old=registry.read_bytes();config=json.loads(registry_old)
assert 'crm' in config.get('projects',{}),'Existing communication project not configured'
caller=pathlib.Path('/etc/crepusculo/voice.json');caller_old=caller.read_bytes() if caller.exists() else None
assert not caller.is_symlink()
crm_pid=subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()
assert pathlib.Path('/proc/'+crm_pid+'/cwd').resolve()==pathlib.Path('/opt/superamplitude/crm')
env=dict(x.split('=',1) for x in pathlib.Path('/proc/'+crm_pid+'/environ').read_bytes().decode().split('\0') if '=' in x)
provider_key=env.get('CRM_GOOGLE_API_KEY','').strip()
assert provider_key,'Existing platform provider credential unavailable'
if caller_old:
 existing=json.loads(caller_old);token=existing['token']
 assert config['projects'].get('suites-crepusculo',{}).get('token_sha256')==hashlib.sha256(token.encode()).hexdigest(),'Project credential mismatch'
else:
 assert 'suites-crepusculo' not in config['projects'],'Project registration already exists without matching local configuration'
 token=secrets.token_urlsafe(48)
central_active=subprocess.check_output(['ss','-Htn','state','established','( sport = :13002 )'],text=True).strip()
assert not central_active,'Central synthesis has active requests; no restart performed'
user=pwd.getpwnam('sasuites')
php_cmd=['php']
for pool in pathlib.Path('/etc/php').glob('*/fpm/pool.d/*.conf'):
 conf=pool.read_text(errors='ignore')
 if '19001' in conf:
  match=re.search(r'php_(?:admin_)?value\[session.save_path\]\s*=\s*(.+)',conf)
  if match:php_cmd+=['-d','session.save_path='+match.group(1).strip()]
def php(code,extra=None):
 return subprocess.run(php_cmd+['-r',code],env={**os.environ,**(extra or {})},capture_output=True,check=True).stdout
bootstrap="require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';"
session=None;qa_path=None;ai_before=None;central_changed=False
try:
 voice_cfg=config['projects']['crm']
 config['projects']['suites-crepusculo']={'token_sha256':hashlib.sha256(token.encode()).hexdigest(),'voice':voice_cfg.get('voice','Callirrhoe'),'model':voice_cfg['model']}
 caller.parent.mkdir(parents=True,exist_ok=True)
 if caller.parent.stat().st_gid!=user.pw_gid and not (caller.parent.stat().st_mode&1):raise RuntimeError('Existing app credential directory is not traversable by site user')
 caller.write_text(json.dumps({'token':token,'provider_api_key':provider_key}));os.chown(caller,0,user.pw_gid);caller.chmod(0o640)
 registry.write_text(json.dumps(config,indent=2));central_changed=True
 subprocess.run(['systemctl','restart','superamplitude-communication.service'],check=True,capture_output=True)
 ready=False
 for attempt in range(15):
  try:
   with urllib.request.urlopen('http://127.0.0.1:13002/communication/health',timeout=2) as response:ready=response.status==200
   if ready:break
  except (OSError,urllib.error.URLError):time.sleep(.3)
 assert ready,'Central did not become ready'
 isolation=urllib.request.Request('http://127.0.0.1:13002/communication/v1/projects/crm/voice/speech',data=b'{}',headers={'Authorization':'Bearer '+token,'Content-Type':'application/json'})
 try:urllib.request.urlopen(isolation,timeout=5);raise RuntimeError('Cross-project isolation failed')
 except urllib.error.HTTPError as e:assert e.code==401
 print('CENTRAL_PROJECT_ISOLATION=PASS; VOICE='+str(voice_cfg.get('voice'))+'; MODEL='+str(voice_cfg.get('model')))
 ai_code=bootstrap+r"""
$pdo=App\Core\DB::pdo();
$old=$pdo->query("SELECT enabled,api_key,updated_at FROM ai_config WHERE id=1")->fetch();
if(!$old)throw new RuntimeException('Existing AI configuration missing');
$key=$old['api_key'];if(!$key)$key=App\Core\Secret::encrypt(getenv('SUITES_PROVIDER_KEY'));
$pdo->prepare("UPDATE ai_config SET enabled=1,api_key=?,updated_at=NOW() WHERE id=1")->execute([$key]);
echo json_encode($old);
"""
 ai_before=json.loads(php(ai_code,{'SUITES_PROVIDER_KEY':provider_key}))
 for p,data in payload.items():
  path=root/p;path.write_bytes(data);os.chown(path,user.pw_uid,user.pw_gid);path.chmod(0o644)
 time.sleep(3)
 code=bootstrap+r"""
App\Core\Auth::start();$pdo=App\Core\DB::pdo();
$u=$pdo->query("SELECT id,company_id,name,email,role FROM users WHERE active=1 AND role='super_admin' ORDER BY id LIMIT 1")->fetch();
if(!$u)throw new RuntimeException('No existing admin for QA');
$_SESSION['user']=$u;$out=['name'=>session_name(),'id'=>session_id(),'path'=>session_save_path()];session_write_close();echo json_encode($out);
"""
 session=json.loads(php(code))
 base=session['path'].split(';')[-1] or tempfile.gettempdir();session_file=pathlib.Path(base)/('sess_'+session['id'])
 os.chown(session_file,user.pw_uid,user.pw_gid);session_file.chmod(0o600)
 fd,qa_path=tempfile.mkstemp(suffix='.mjs',prefix='suites-voice-qa-')
 with os.fdopen(fd,'wb') as f:f.write(qa)
 result=subprocess.run(['node',qa_path],env={**os.environ,'SUITES_QA_SESSION':json.dumps(session)},capture_output=True,text=True,timeout=210)
 print(result.stdout,end='')
 if result.returncode:raise RuntimeError('Voice QA failed: '+result.stderr[-2500:])
 print('PANEL_VOICE_APPLIED=1; EXISTING_AI_ENABLED=1; BUSINESS_DATA_WRITES=0; BACKUPS_CREATED=0; CRM_RESTARTS=0; AEL_RESTARTS=0; CENTRAL_RESTARTS=1')
except BaseException:
 for p,data in original.items():
  path=root/p
  if data is None:path.unlink(missing_ok=True)
  else:path.write_bytes(data)
 if ai_before is not None:
  restore=bootstrap+r"""$v=json_decode(getenv('SUITES_AI_RESTORE'),true);App\Core\DB::pdo()->prepare("UPDATE ai_config SET enabled=?,api_key=?,updated_at=? WHERE id=1")->execute([$v['enabled'],$v['api_key'],$v['updated_at']]);"""
  php(restore,{'SUITES_AI_RESTORE':json.dumps(ai_before)})
 if caller_old is None:caller.unlink(missing_ok=True)
 else:caller.write_bytes(caller_old)
 if central_changed:
  registry.write_bytes(registry_old);subprocess.run(['systemctl','restart','superamplitude-communication.service'],capture_output=True)
 print('VOICE_DEPLOYMENT_REVERTED_IN_MEMORY=1')
 raise
finally:
 if qa_path:pathlib.Path(qa_path).unlink(missing_ok=True)
 if session:
  base=session['path'].split(';')[-1] or tempfile.gettempdir()
  (pathlib.Path(base)/('sess_'+session['id'])).unlink(missing_ok=True)
