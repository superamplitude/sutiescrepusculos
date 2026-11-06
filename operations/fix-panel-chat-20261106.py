#!/usr/bin/env python3
import os,pathlib,hashlib,urllib.request,subprocess,tempfile,pwd,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
assert root.is_dir() and root.resolve()==root
ref='68d1fd9eed4b0f7c61b6f3320ae148e47635f998'
manifest={
'assets/js/panel-voice.js':('5112e67de12586763e904356f57fa1ae480d1ea05a0328bb77fe367ac53d87cf','65d2e539c224843432035a255fa7a6f7cb5a86a31433ff61a4e1d3ae554a9a20'),
'app/Views/layout.php':('e88721a69b868b7a44585fa78c40fd142c658da3149a93908320a860aaa1eeec','eb89828c495ad5911eafc1fcb54120024fe68473ebef1c5f7c58975c90852fb3')}
def fetch(path,digest):
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+path,timeout=20).read()
 assert hashlib.sha256(data).hexdigest()==digest,'Source integrity '+path
 return data
payload={p:fetch(p,v[1]) for p,v in manifest.items()}
qa=fetch('tests/panel-chat-regression-20261106.mjs','2b99782865fcdf599609f67efa3a1598695b13569e08dd58fcc6a6db28fb37cd')
original={}
for p,v in manifest.items():
 f=root/p;assert f.is_file() and not f.is_symlink()
 original[p]=f.read_bytes()
 assert hashlib.sha256(original[p]).hexdigest() in v,'Changed baseline '+p
 subprocess.run(['php','-l'] if p.endswith('.php') else ['node','--check'],input=payload[p],capture_output=True,check=True)
u=pwd.getpwnam('sasuites')
fd,qpath=tempfile.mkstemp(suffix='.mjs',prefix='suites-chat-qa-')
try:
 with os.fdopen(fd,'wb') as f:f.write(qa)
 for p,data in payload.items():
  f=root/p;f.write_bytes(data);os.chown(f,u.pw_uid,u.pw_gid);f.chmod(0o644)
 time.sleep(3)
 r=subprocess.run(['node',qpath],env={**os.environ,'SUITES_CHAT_SCRIPT':str(root/'assets/js/panel-voice.js')},capture_output=True,text=True,timeout=90)
 print(r.stdout,end='')
 assert r.returncode==0,'Chat regression: '+r.stderr[-1800:]
 code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$r=(new App\Services\GeminiService())->ask('Responda apenas: atendimento disponível.');
if(empty($r['ok']))exit(1);
echo 'REAL_PROVIDER_HTTP='.($r['http']??0).'; ANSWER_CHARACTERS='.mb_strlen($r['answer']).PHP_EOL;
"""
 r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=45)
 print(r.stdout,end='');assert r.returncode==0,'Real provider failure'
 for p,v in manifest.items():assert hashlib.sha256((root/p).read_bytes()).hexdigest()==v[1]
 print('CHAT_FIX_DEPLOYED=1; RUNTIME_FILES_MATCH=2; BUSINESS_WRITES=0; SESSIONS_CREATED=0; SERVICE_RESTARTS=0; BACKUPS_CREATED=0')
except BaseException:
 for p,data in original.items():(root/p).write_bytes(data)
 print('REVERTED_IN_MEMORY=1')
 raise
finally:pathlib.Path(qpath).unlink(missing_ok=True)


# Inspect only route metadata; do not export internal source or credentials.
import json,re
p=pathlib.Path('/opt/waas/app/server.mjs')
if p.is_file():
 text=p.read_text()
 paths=sorted(set(re.findall(r'["\x27](/(?:sessions|health|api|webhook)[A-Za-z0-9_:/.*?{}-]*)["\x27]',text)))
 print('MESSAGING_ROUTE_NAMES',json.dumps(paths))
 print('MESSAGING_FEATURES',json.dumps({k:bool(re.search(v,text,re.I)) for k,v in {'inbound_events':'messages.upsert','audio_download':'downloadMediaMessage','audio_send':'audio:|audio\\s*\\}', 'webhook':'webhook','event_stream':'text/event-stream'}.items()}))
 print('MESSAGING_GATEWAY_CODE_SHA256',hashlib.sha256(p.read_bytes()).hexdigest())
for name in ['wacalls.mjs','voice-ai-live.mjs','panel-natural-voice.mjs']:
 p=pathlib.Path('/opt/superamplitude/crm/lib')/name
 print('LIBRARY_SHA256',name,hashlib.sha256(p.read_bytes()).hexdigest())
p=pathlib.Path('/opt/superamplitude/crm/package.json')
cfg=json.loads(p.read_text());print('CRM_WERIFT_VERSION',cfg.get('dependencies',{}).get('werift'))
