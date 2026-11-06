#!/usr/bin/env python3
"""Provision the owner's Suites application as its own permitted messaging tenant.
Retains mandatory provisioning for CRM sessions and validates a root-owned Suites registration.
"""
import os,pathlib,json,hashlib,urllib.request,subprocess,pwd,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
gateway=pathlib.Path('/opt/waas/app/server.mjs')
module=pathlib.Path('/opt/waas/app/suites-session-registration.mjs')
configdir=pathlib.Path('/etc/suites-messaging-gateway');config=configdir/'session.json'
assert not module.exists() and not config.exists(),'Registration already exists'
old=gateway.read_bytes();text=old.decode()
original=text.replace('import {handleSuitesMessage} from "./suites-messages-adapter.mjs";\n','',1).replace('if(id==="suites-crepusculo"){await handleSuitesMessage(s.sock,msg);continue;}\n        const result=await storeWhatsAppMessage(id,msg);','const result=await storeWhatsAppMessage(id,msg);',1)
assert hashlib.sha256(original.encode()).hexdigest()=='28de8c57cb159cdb1277855369107693543cc3d948aca9aa1c442a818c911a47','Unexpected gateway source'
anchor='  await accountForSession(id);'
assert text.count(anchor)==1
text=text.replace(anchor,'  if(id==="suites-crepusculo")await assertSuitesProvisioned(id);\n  else await accountForSession(id);')
assert 'setAccountState(' in text
text=text.replace('setAccountState(','gatewayAccountState(')
prefix='import {assertSuitesProvisioned} from "./suites-session-registration.mjs";\nasync function gatewayAccountState(id,...args){if(id==="suites-crepusculo"){await assertSuitesProvisioned(id);return;}return setAccountState(id,...args);}\n'
text=prefix+text
text=text.replace('qrcode.generate(qr,{small:true});','if(id!=="suites-crepusculo")qrcode.generate(qr,{small:true});')
subprocess.run(['node','--input-type=module','--check'],input=text.encode(),check=True,capture_output=True)
data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/1342ed8ce6077e805669d9297b62ba472c9aaa76/integrations/attendance/suites-session-registration.mjs',timeout=20).read()
assert hashlib.sha256(data).hexdigest()=='66323a3abf14526b0388395c69dc8cfabb7c521e56eaf761be6e0eabb31f08d9'
subprocess.run(['node','--input-type=module','--check'],input=data,check=True,capture_output=True)
def pid(s):return subprocess.check_output(['systemctl','show',s,'-p','MainPID','--value'],text=True).strip()
preserve={s:pid(s) for s in ['suites-crepusculo-attendance.service','superamplitude-crm.service']}
u=pwd.getpwnam('sacrm');changed=False
try:
 configdir.mkdir(mode=0o750,exist_ok=True);os.chown(configdir,0,u.pw_gid);configdir.chmod(0o750)
 config.write_text(json.dumps({'enabled':True,'project':'suites-crepusculo','session':'suites-crepusculo','socket':'/run/suites-messages/messages.sock'}));os.chown(config,0,u.pw_gid);config.chmod(0o640)
 module.write_bytes(data);os.chown(module,0,u.pw_gid);module.chmod(0o640)
 check=r"""import assert from 'node:assert/strict';import {validateSuitesRegistration} from './suites-session-registration.mjs';
const c={enabled:true,project:'suites-crepusculo',session:'suites-crepusculo',socket:'/run/suites-messages/messages.sock'};
assert(validateSuitesRegistration(c,'suites-crepusculo'));
assert.throws(()=>validateSuitesRegistration(c,'crm_company_824'));
assert.throws(()=>validateSuitesRegistration({...c,enabled:false},'suites-crepusculo'));
assert.throws(()=>validateSuitesRegistration({...c,socket:'/tmp/other'},'suites-crepusculo'));
console.log('ROOT_PROVISIONING_CONTRACT_TESTS_OK');"""
 r=subprocess.run(['node','--input-type=module','-e',check],cwd=gateway.parent,capture_output=True,text=True,timeout=10);assert r.returncode==0,r.stderr;print(r.stdout,end='')
 gateway.write_text(text);changed=True
 subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],check=True,capture_output=True);time.sleep(4)
 code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=new App\Services\SuitesMessagesService();$qr=$s->qr();echo json_encode(['qr_generated'=>is_string($qr)&&strlen($qr)>50,'qr_length'=>strlen($qr??''),'state'=>$s->status()['status']??'unknown']);"""
 r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=30)
 assert r.returncode==0,'Pairing failed: '+r.stderr[-1000:]
 result=json.loads(r.stdout);assert result['qr_generated'] or result['state']=='connected'
 print('SUITES_MESSAGE_PAIRING',json.dumps(result))
 for s,p in preserve.items():assert pid(s)==p,'Voice/CRM process changed'
 print('REGISTERED_SUITES_APP_SESSION=1; CRM_PROVISIONING_RETAINED=1; VOICE_RESTARTS=0; VOICE_CHANGES=0; CRM_APP_RESTARTS=0; QR_NOT_LOGGED=1; NEW_GATEWAYS=0')
 print('GATEWAY_SOURCE_SHA256='+hashlib.sha256(gateway.read_bytes()).hexdigest())
except BaseException:
 gateway.write_bytes(old);module.unlink(missing_ok=True);config.unlink(missing_ok=True)
 if changed:subprocess.run(['systemctl','restart','sa-crm-whatsapp-gateway.service'],capture_output=True)
 print('REGISTRATION_ROLLED_BACK_IN_MEMORY=1; VOICE_UNTOUCHED=1');raise
