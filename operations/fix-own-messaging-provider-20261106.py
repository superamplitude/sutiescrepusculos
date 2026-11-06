#!/usr/bin/env python3
"""Fix the observed rc14 QR revocation by using stable 6.7.23 ONLY for Suites messages.
Existing CRM session library and confirmed voice services stay untouched.
"""
import os,pathlib,subprocess,hashlib,urllib.request,json,pwd,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
base=pathlib.Path('/opt/waas/app');gateway=base/'server.mjs';old=gateway.read_bytes()
assert hashlib.sha256(old).hexdigest()=='f26871eb84ef75f388b6cd330193d33e1f89f8e56d4220c34fb6579601fb5080','Gateway baseline changed'
oldversion=json.loads((base/'node_modules/@whiskeysockets/baileys/package.json').read_text())['version']
assert oldversion=='7.0.0-rc14'
adapter=base/'suites-messages-adapter.mjs';adapter_old=adapter.read_bytes()
assert hashlib.sha256(adapter_old).hexdigest()=='62bf2c9ed94d46e5bb181b8be469453fc119ab8fed44c2768e3b81efe270ba6e'
rootadapter=pathlib.Path('/opt/suites-crepusculo-messages/suites-messages-adapter.mjs');rootold=rootadapter.read_bytes()
assert rootold==adapter_old
provider=base/'suites-baileys-provider.mjs';assert not provider.exists()
ref='716bc74ab585540c39260def74732680b863d5ba'
payload={}
for name,digest in {'suites-baileys-provider.mjs':'f1a0358330f3095d4dedc554580bb283a22315215bd29b2128cd2e22b68337bb','suites-messages-adapter.mjs':'7d5597a38832fdff2030fcfd3462683e1262c25f3d29f7f7b55ea426451fbb48'}.items():
 data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/'+ref+'/integrations/attendance/'+name,timeout=20).read();assert hashlib.sha256(data).hexdigest()==digest
 subprocess.run(['node','--input-type=module','--check'],input=data,check=True,capture_output=True);payload[name]=data
def pid(s):return subprocess.check_output(['systemctl','show',s,'-p','MainPID','--value'],text=True).strip()
preserve={s:pid(s) for s in ['suites-crepusculo-attendance.service','superamplitude-crm.service']}
u=pwd.getpwnam('sacrm');deps=base/'suites-baileys'
assert not deps.exists(),'Dependency directory already exists'
deps.mkdir(mode=0o750);os.chown(deps,u.pw_uid,u.pw_gid)
package=deps/'package.json';package.write_text(json.dumps({'private':True,'type':'module','dependencies':{'@whiskeysockets/baileys':'6.7.23'}}));os.chown(package,u.pw_uid,u.pw_gid)
r=subprocess.run(['sudo','-n','-u','sacrm','npm','install','--ignore-scripts','--omit=dev','--no-audit','--no-fund'],cwd=deps,capture_output=True,text=True,timeout=240)
assert r.returncode==0,'Pinned stable package installation failed: '+r.stderr[-1300:]
stable=json.loads((deps/'node_modules/@whiskeysockets/baileys/package.json').read_text())['version'];assert stable=='6.7.23'
text=old.decode()
replacements={
'  const dir=path.join(ROOT,id);':'  const dir=path.join(ROOT,id,...(id==="suites-crepusculo"?["stable-v6"]:[]));',
'  const {state,saveCreds}=await useMultiFileAuthState(dir);':'  const suitesProvider=id==="suites-crepusculo"?await suitesMessagingProvider():null;\n  const {state,saveCreds}=await (suitesProvider?.auth||useMultiFileAuthState)(dir);',
'  const sock=makeWASocket({':'  const sock=(suitesProvider?.make||makeWASocket)({\n    ...(suitesProvider?{version:suitesProvider.version,browser:suitesProvider.browser}:{}),',
'    markOnlineOnConnect:false,':'    markOnlineOnConnect:id==="suites-crepusculo",',
'  sock.ev.on("creds.update",saveCreds);':'  let savePending=Promise.resolve();\n  if(suitesProvider)sock.ev.on("creds.update",()=>{savePending=savePending.catch(()=>{}).then(saveCreds);void savePending.catch(()=>console.error("SUITES_AUTH_SAVE_FAILED"));});\n  else sock.ev.on("creds.update",saveCreds);',
'      const code=lastDisconnect?.error?.output?.statusCode;':'      const code=lastDisconnect?.error?.output?.statusCode;\n      if(suitesProvider&&code===515)await savePending.catch(()=>{});',
'        s.timer=setTimeout(()=>connect(id).catch(e=>console.error("WA_RECONNECT_ERROR",id,e.message)),3000);':'        s.timer=setTimeout(()=>connect(id).catch(e=>console.error("WA_RECONNECT_ERROR",id,e.message)),suitesProvider&&code===515?0:3000);'
}
for a,b in replacements.items():assert text.count(a)==1,'Socket anchor changed';text=text.replace(a,b)
text='import {suitesMessagingProvider} from "./suites-baileys-provider.mjs";\n'+text
subprocess.run(['node','--input-type=module','--check'],input=text.encode(),check=True,capture_output=True)
changed=False
try:
 for name,data in payload.items():
  p=base/name;p.write_bytes(data);os.chown(p,u.pw_uid,u.pw_gid);p.chmod(0o640)
 check="const p=await (await import('./suites-baileys-provider.mjs')).suitesMessagingProvider();if(typeof p.make!=='function'||typeof p.auth!=='function'||typeof p.download!=='function')throw Error('Provider contract');console.log('STABLE_PROVIDER_READY',JSON.stringify(p.version));"
 r=subprocess.run(['sudo','-n','-u','sacrm','node','--input-type=module','-e',check],cwd=base,capture_output=True,text=True,timeout=30);assert r.returncode==0,'Stable provider import failed: '+r.stderr[-1000:];print(r.stdout,end='')
 rootadapter.write_bytes(payload['suites-messages-adapter.mjs'])
 gateway.write_text(text);changed=True
 test=subprocess.run(['sudo','-n','-u','sasuites','node','/opt/suites-crepusculo-messages/messages.test.mjs'],capture_output=True,text=True,timeout=30);assert test.returncode==0,test.stderr[-1000:];print(test.stdout,end='')
 subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],check=True,capture_output=True);time.sleep(5)
 code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=new App\Services\SuitesMessagesService();$qr=$s->qr();echo json_encode(['qr_generated'=>is_string($qr)&&strlen($qr)>50,'qr_length'=>strlen($qr??''),'state'=>$s->status()['status']??'unknown']);"""
 r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=30);assert r.returncode==0,'Stable pairing generation failed: '+r.stderr[-1000:]
 status=json.loads(r.stdout);assert status['qr_generated'] or status['state']=='connected';print('STABLE_PAIRING_READY',json.dumps(status))
 assert json.loads((base/'node_modules/@whiskeysockets/baileys/package.json').read_text())['version']==oldversion
 for s,p in preserve.items():assert pid(s)==p,'Voice or CRM restarted'
 print('SUITES_ONLY_STABLE_VERSION=6.7.23; EXISTING_CRM_LIBRARY_UNCHANGED=7.0.0-rc14; VOICE_CHANGES=0; VOICE_RESTARTS=0; OLD_REVOKED_AUTH_NOT_DELETED=1; EXTERNAL_MESSAGES_SENT=0')
 print('GATEWAY_SOURCE_SHA256='+hashlib.sha256(gateway.read_bytes()).hexdigest())
except BaseException:
 gateway.write_bytes(old);adapter.write_bytes(adapter_old);rootadapter.write_bytes(rootold);provider.unlink(missing_ok=True)
 if changed:subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],capture_output=True)
 print('STABLE_PROVIDER_NOT_ACTIVATED=1; VOICE_UNTOUCHED=1');raise
