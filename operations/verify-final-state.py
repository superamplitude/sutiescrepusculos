#!/usr/bin/env python3
import os,pathlib,urllib.request,urllib.error,json,hashlib,subprocess,hmac
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
m=json.loads(urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/98cacd8a2a1091c58eb9fe793c1f67b47875be72/deploy/runtime-manifest-20261105.json',timeout=20).read())
assert m['runtime']==str(root)
bad=[]
for name,sha in m['files'].items():
 p=root/name;assert p.resolve().is_relative_to(root)
 if not p.is_file() or hashlib.sha256(p.read_bytes()).hexdigest()!=sha:bad.append(name)
assert not bad,'Source drift: '+','.join(bad)
print('DEPLOYED_SOURCE_FILES_MATCH='+str(len(m['files'])))
print('SEPARATED_PAGE_FILES='+str(len(list((root/'pages').glob('*.php')))))
for name in ['voice.json','wacalls.env']:
 p=pathlib.Path('/etc/crepusculo')/name
 assert p.is_file() and not(p.stat().st_mode&7),'Credential permissions mismatch'
print('CREDENTIALS_OUTSIDE_WEBROOT_AND_NOT_PUBLIC=1')
cfg=json.loads(pathlib.Path('/etc/superamplitude/runtime/communication-projects.json').read_text())
caller=json.loads(pathlib.Path('/etc/crepusculo/voice.json').read_text())
assert hmac.compare_digest(cfg['projects']['suites-crepusculo']['token_sha256'],hashlib.sha256(caller['token'].encode()).hexdigest())
with urllib.request.urlopen('http://127.0.0.1:13002/communication/health',timeout=8) as r:assert r.status==200
with urllib.request.urlopen('https://suitescrepusculo.com.br/',timeout=15) as r:assert r.status==200
try:urllib.request.urlopen('https://suitescrepusculo.com.br/chat.php?action=panel_voice',timeout=15);raise RuntimeError('Anonymous voice must require authentication')
except urllib.error.HTTPError as e:assert e.code==401
print('CENTRAL_HEALTH=200; PUBLIC_SITE=200; ANONYMOUS_VOICE=401')
code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=(new App\Services\AelWaCallsService())->status(1);echo json_encode(['configured'=>$s['configured'],'session_exists'=>$s['exists'],'paired'=>$s['paired'],'state'=>$s['state']]);"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,check=True,text=True)
print('WHATSAPP_FINAL='+r.stdout)
print('FINAL_VERIFICATION_OK=1; DATABASE_WRITES=0; SERVICES_RESTARTED=0; BACKUPS_CREATED=0')
