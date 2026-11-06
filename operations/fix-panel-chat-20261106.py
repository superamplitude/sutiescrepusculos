#!/usr/bin/env python3
import os,pathlib,hashlib,urllib.request,subprocess,tempfile,pwd,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
assert root.is_dir() and root.resolve()==root
ref='df0ebef12d6bf6596c18a3e1338004b14b1170f7'
manifest={
'assets/js/panel-voice.js':('5112e67de12586763e904356f57fa1ae480d1ea05a0328bb77fe367ac53d87cf','054cbb568fbf61fde373c2a13eae933039a3eb9ee96c52f7920411c16d929b2d'),
'app/Views/layout.php':('709ac40d99fd18368a1f534b3c7c9295e1204fb4521acaeb4ebe1202eab60924','50d029a47e9d04b6158450d757ac60bd9ef047605abec1e0e71c7bd0480e46f4')}
def fetch(path,digest):
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+path,timeout=20).read()
 assert hashlib.sha256(data).hexdigest()==digest,'Source integrity '+path
 return data
payload={p:fetch(p,v[1]) for p,v in manifest.items()}
qa=fetch('tests/panel-chat-regression-20261106.mjs','2657ee52ff9cd9918cdb69bc4fa731182e8eea9b283fb2ae6070cec6b22f87cf')
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
