#!/usr/bin/env python3
import os,pathlib,hashlib,urllib.request,subprocess,tempfile,json,pwd,fcntl,time,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
ref='13fbcab317f38c2435b1c0e559a725ece4f585bb'
manifest={
'pages/map.php':('800f9cf269328b76148b95b1ea1743d80ea9220a6267fe7179759baadbdbc049','6d63d39dce4948ade44da60a0d631f45ae10119f5a2c1c5892b612ce07ecabaf'),
'assets/css/map-workspace.css':(None,'7eebf3d7b0eaf73ef85bf11a4855c1a8025b99334ebdccb6408f9dddcd9dcec2'),
'assets/js/map-workspace.js':(None,'74c42b948607cb3534b5d43d1c59457aae8c7935060abea557b9b80b31593aa4')}
def fetch(path,sha):
 b=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/'+path,timeout=20).read()
 assert hashlib.sha256(b).hexdigest()==sha,path+' payload integrity'
 return b
payload={p:fetch(p,after) for p,(before,after) in manifest.items()}
qa=fetch('tests/map-browser-qa.mjs','a3bb7affecc751675bf5f4eab1132936a93129b42b905bdf6942061b28ae3eb9')
for p,b in payload.items():
 if p.endswith('.php'):subprocess.run(['php','-l'],input=b,check=True,capture_output=True)
 if p.endswith('.js'):subprocess.run(['node','--check'],input=b,check=True,capture_output=True)
original={}
for p,(before,after) in manifest.items():
 path=root/p
 assert not path.is_symlink()
 old=path.read_bytes() if path.exists() else None
 assert (hashlib.sha256(old).hexdigest() if old is not None else None)==before,p+' baseline mismatch'
 original[p]=old
session=None;qa_path=None
try:
 with (root/'index.php').open('rb') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX)
  for p,b in payload.items():
   path=root/p;path.write_bytes(b);os.chown(path,os.stat(root/'index.php').st_uid,os.stat(root/'index.php').st_gid);path.chmod(0o644)
  time.sleep(3)
  code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
App\Core\Auth::start();
$pdo=App\Core\DB::pdo();
$u=$pdo->query("SELECT id,company_id,name,email,role FROM users WHERE active=1 AND role='super_admin' ORDER BY id LIMIT 1")->fetch();
if(!$u)throw new RuntimeException('No existing super admin for QA');
$_SESSION['user']=$u;
$out=['name'=>session_name(),'id'=>session_id(),'path'=>session_save_path()];
session_write_close();echo json_encode($out);
"""
  php_cmd=['php']
  for pool in pathlib.Path('/etc/php').glob('*/fpm/pool.d/*.conf'):
   conf=pool.read_text(errors='ignore')
   if '19001' in conf:
    match=re.search(r'php_(?:admin_)?value\[session.save_path\]\s*=\s*(.+)',conf)
    if match:php_cmd+=['-d','session.save_path='+match.group(1).strip()]
  r=subprocess.run(php_cmd+['-r',code],check=True,capture_output=True)
  session=json.loads(r.stdout)
  base=session['path'].split(';')[-1] or tempfile.gettempdir()
  session_file=pathlib.Path(base)/('sess_'+session['id'])
  user=pwd.getpwnam('sasuites');os.chown(session_file,user.pw_uid,user.pw_gid);session_file.chmod(0o600)
  fd,qa_path=tempfile.mkstemp(suffix='.mjs',prefix='suites-map-qa-')
  with os.fdopen(fd,'wb') as f:f.write(qa)
  r=subprocess.run(['node',qa_path],env={**os.environ,'SUITES_QA_SESSION':json.dumps(session)},capture_output=True,text=True,timeout=180)
  print(r.stdout,end='')
  if r.returncode:raise RuntimeError('Map browser QA failed: '+r.stderr[-2500:])
  print('MAP_WORKSPACE_APPLIED=1; BACKUPS_CREATED=0; DATABASE_WRITES=0; RESTARTS=0')
except BaseException:
 for p,b in original.items():
  path=root/p
  if b is None:path.unlink(missing_ok=True)
  else:path.write_bytes(b)
 print('MAP_WORKSPACE_REVERTED_IN_MEMORY=1')
 raise
finally:
 if qa_path:pathlib.Path(qa_path).unlink(missing_ok=True)
 if session:
  base=session['path'].split(';')[-1] or tempfile.gettempdir()
  (pathlib.Path(base)/('sess_'+session['id'])).unlink(missing_ok=True)
