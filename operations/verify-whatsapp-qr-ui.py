#!/usr/bin/env python3
import os,pathlib,subprocess,tempfile,json,pwd,urllib.request,hashlib,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
qa=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/70b19c90d19a6433d019d129d6ebcebe0bb3e6c6/tests/whatsapp-qr-browser-qa.mjs',timeout=20).read()
assert hashlib.sha256(qa).hexdigest()=='e059ec77217c82eb132fcab7aa8ac62e1bfc65d000ead22ee670275d82f30902'
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
if(!$u)throw new RuntimeException('Existing admin not found');
$_SESSION['user']=$u;$out=['name'=>session_name(),'id'=>session_id(),'path'=>session_save_path()];session_write_close();echo json_encode($out);
"""
r=subprocess.run(php_cmd+['-r',code],capture_output=True,check=True)
session=json.loads(r.stdout);base=session['path'].split(';')[-1] or tempfile.gettempdir()
session_file=pathlib.Path(base)/('sess_'+session['id']);user=pwd.getpwnam('sasuites')
os.chown(session_file,user.pw_uid,user.pw_gid);session_file.chmod(0o600)
qa_path=None
try:
 fd,qa_path=tempfile.mkstemp(suffix='.mjs',prefix='suites-qr-ui-')
 with os.fdopen(fd,'wb') as f:f.write(qa)
 r=subprocess.run(['node',qa_path],env={**os.environ,'SUITES_QA_SESSION':json.dumps(session)},capture_output=True,text=True,timeout=150)
 print(r.stdout,end='')
 if r.returncode:raise RuntimeError('WhatsApp QR UI QA failed: '+r.stderr[-2500:])
finally:
 if qa_path:pathlib.Path(qa_path).unlink(missing_ok=True)
 session_file.unlink(missing_ok=True)
