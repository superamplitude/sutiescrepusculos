#!/usr/bin/env python3
import os,pathlib,subprocess,json,pwd,tempfile,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
php=['php']
for pool in pathlib.Path('/etc/php').glob('*/fpm/pool.d/*.conf'):
 text=pool.read_text()
 if '19001' in text:
  m=re.search(r'php_(?:admin_)?value\[session.save_path\]\s*=\s*(.+)',text)
  if m:php+=['-d','session.save_path='+m.group(1).strip()]
boot="require '"+str(root)+"/app/bootstrap.php';"
code=boot+r"""
App\Core\Auth::start();$u=App\Core\DB::pdo()->query("SELECT id,company_id,name,email,role FROM users WHERE active=1 AND role='super_admin' ORDER BY id LIMIT 1")->fetch();
if(!$u)throw new RuntimeException('No admin');$_SESSION['user']=$u;$s=['name'=>session_name(),'id'=>session_id(),'path'=>session_save_path()];session_write_close();echo json_encode($s);
"""
s=json.loads(subprocess.check_output(php+['-r',code],text=True))
f=pathlib.Path(s['path'].split(';')[-1] or tempfile.gettempdir())/('sess_'+s['id'])
u=pwd.getpwnam('sasuites');os.chown(f,u.pw_uid,u.pw_gid);f.chmod(0o600)
qa=r"""
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const s=JSON.parse(process.env.SUITES_QA_SESSION),origin='https://suitescrepusculo.com.br';
const b=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
const c=await b.newContext();await c.addCookies([{name:s.name,value:s.id,domain:'suitescrepusculo.com.br',path:'/',secure:true,httpOnly:true}]);
const p=await c.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
await p.goto(origin+'/index.php?p=dashboard',{waitUntil:'networkidle'});
console.log('PAGE',p.url(),'TOGGLE',await p.locator('#suitesVoiceToggle').count());
await p.locator('#suitesVoiceToggle').click();
console.log('PANEL_VISIBLE',await p.locator('#suitesPanelVoice').isVisible());
await p.locator('#suitesPanelVoice textarea').fill('Qual é o horário de check-in?');
let response;
try{
 const wait=p.waitForResponse(r=>r.url().includes('panel_voice')&&r.request().method()==='POST'&&r.request().postData()?.includes('kind=answer'),{timeout:45000});
 await p.locator('[data-voice-send]').click();response=await wait;
 console.log('ANSWER_HTTP',response.status(),'BODY',(await response.text()).slice(0,1200));
}catch(e){console.log('SEND_ERROR',e.message.slice(0,350));}
console.log('PANEL_STATUS',await p.locator('[data-voice-status]').innerText(),'ANSWER_LENGTH',(await p.locator('[data-voice-answer]').innerText()).length,'JS_ERRORS',JSON.stringify(errors));
await c.close();
}finally{await b.close();}
"""
fd,qpath=tempfile.mkstemp(suffix='.mjs')
try:
 with os.fdopen(fd,'w') as q:q.write(qa)
 r=subprocess.run(['node',qpath],env={**os.environ,'SUITES_QA_SESSION':json.dumps(s)},capture_output=True,text=True,timeout=140)
 print(r.stdout);print(r.stderr[-2000:]);assert r.returncode==0
 code=boot+r"""
$r=(new App\Services\GeminiService())->ask('Qual é o horário de check-in?');
echo json_encode(['ok'=>$r['ok']??false,'http'=>$r['http']??null,'model'=>$r['model']??null,'error'=>mb_substr(preg_replace('/AIza[A-Za-z0-9_-]+|key=[^&\s"]+/','[REDACTED]',(string)($r['error']??'')),0,1000),'answer_length'=>mb_strlen($r['answer']??'')]);
"""
 print('PROVIDER',subprocess.check_output(php+['-r',code],text=True,timeout=50))
finally:
 pathlib.Path(qpath).unlink(missing_ok=True);f.unlink(missing_ok=True)
