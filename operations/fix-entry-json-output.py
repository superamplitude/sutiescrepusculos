#!/usr/bin/env python3
import os,pathlib,subprocess,tempfile,json,pwd,urllib.request,hashlib,re,time,fcntl
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
ref='4aebeaf2cbea5e06577afd3310ab701c4a0bf129'
targets={'index.php':('0fd23108b794e7683e748f003f84a737d61bda57e4102a3906b03ca52fbb4217','e826eb1d5f8233b87049f2011281a83f73a04d79a194dfcb1549dc3f3f4049ab'),'app/Views/layout.php':('bfa3cb1b42e0aef0a52ad094d7c28f45c423c88943d976d4ce1611646f3a81e8','709ac40d99fd18368a1f534b3c7c9295e1204fb4521acaeb4ebe1202eab60924')}
def fetch(path,sha):
 b=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+path,timeout=20).read()
 assert hashlib.sha256(b).hexdigest()==sha,path+' payload mismatch'
 return b
candidate={p:fetch(p,v[1]) for p,v in targets.items()}
qa=fetch('tests/entry-json-browser-qa.mjs','f7cabd245910d4c2fcd920aecf07e414e17cd2d1c669b3b06dadf7f8b3077583')
original={}
for p,(before,after) in targets.items():
 original[p]=(root/p).read_bytes();assert hashlib.sha256(original[p]).hexdigest()==before,p+' baseline mismatch'
 subprocess.run(['php','-l'],input=candidate[p],capture_output=True,check=True)
php_cmd=['php']
for pool in pathlib.Path('/etc/php').glob('*/fpm/pool.d/*.conf'):
 conf=pool.read_text(errors='ignore')
 if '19001' in conf:
  match=re.search(r'php_(?:admin_)?value\[session.save_path\]\s*=\s*(.+)',conf)
  if match:php_cmd+=['-d','session.save_path='+match.group(1).strip()]
code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
App\Core\Auth::start();$pdo=App\Core\DB::pdo();
$u=$pdo->query("SELECT id,company_id,name,email,role FROM users WHERE active=1 AND role='super_admin' ORDER BY id LIMIT 1")->fetch();
if(!$u)throw new RuntimeException('Existing admin missing');
$_SESSION['user']=$u;$out=['name'=>session_name(),'id'=>session_id(),'path'=>session_save_path(),'reservation_id'=>(int)$pdo->query('SELECT id FROM reservations ORDER BY id LIMIT 1')->fetchColumn()];
session_write_close();echo json_encode($out);
"""
session=json.loads(subprocess.run(php_cmd+['-r',code],capture_output=True,check=True).stdout)
base=session['path'].split(';')[-1] or tempfile.gettempdir();session_file=pathlib.Path(base)/('sess_'+session['id']);user=pwd.getpwnam('sasuites')
os.chown(session_file,user.pw_uid,user.pw_gid);session_file.chmod(0o600)
qa_path=None;changed=False
try:
 fd,qa_path=tempfile.mkstemp(suffix='.mjs',prefix='suites-entry-qa-')
 with os.fdopen(fd,'wb') as f:f.write(qa)
 def run_qa(clean):
  r=subprocess.run(['node',qa_path],env={**os.environ,'SUITES_QA_SESSION':json.dumps(session),'SUITES_EXPECT_CLEAN':str(int(clean))},capture_output=True,text=True,timeout=100)
  print(r.stdout,end='')
  if r.returncode:raise RuntimeError('Entry QA failed: '+r.stderr[-2500:])
 run_qa(False)
 with (root/'index.php').open('rb') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX)
  for p,b in candidate.items():(root/p).write_bytes(b)
  changed=True;time.sleep(3);run_qa(True)
 print('ENTRY_OUTPUT_FIXED=1; DATABASE_WRITES=0; BACKUPS_CREATED=0; RESTARTS=0')
except BaseException:
 if changed:
  for p,b in original.items():(root/p).write_bytes(b)
  print('ENTRY_CHANGE_REVERTED_IN_MEMORY=1')
 raise
finally:
 if qa_path:pathlib.Path(qa_path).unlink(missing_ok=True)
 session_file.unlink(missing_ok=True)
