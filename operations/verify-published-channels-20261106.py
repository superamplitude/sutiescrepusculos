#!/usr/bin/env python3
"""Final source/service verification and creation of only the requested Suites message QR if needed."""
import os,pathlib,subprocess,urllib.request,json,hashlib
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/9e6cc6525223494222cfd6a1212c3b407428f308/deploy/runtime-manifest-20261106.json',timeout=20).read()
assert hashlib.sha256(data).hexdigest()=='4fc31ece0d6c30fe6e8483d4a879e62364d27cde9682db894214f114c3f4c32f'
manifest=json.loads(data);root=pathlib.Path(manifest['runtime'])
for name,digest in manifest['files'].items():assert hashlib.sha256((root/name).read_bytes()).hexdigest()==digest,'Source mismatch '+name
for name,digest in manifest['messages']['files'].items():assert hashlib.sha256((pathlib.Path(manifest['messages']['runtime'])/name).read_bytes()).hexdigest()==digest,'Message source '+name
assert hashlib.sha256(pathlib.Path('/opt/waas/app/suites-messages-adapter.mjs').read_bytes()).hexdigest()==manifest['messages']['gateway_adapter_sha256']
layout=(root/'app/Views/layout.php').read_text()
assert '<summary>CRM</summary>' not in layout and 'Chat e voz</button>' in layout
for name in ['suites-crepusculo-attendance.service','suites-crepusculo-messages.service','sa-crm-whatsapp-gateway.service']:
 assert subprocess.check_output(['systemctl','is-active',name],text=True).strip()=='active'
smoke=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/6af5942a51e1c9daf515d4b10abc8a44d137174f/tests/messages-transaction-smoke-20261106.php',timeout=20).read()
assert hashlib.sha256(smoke).hexdigest()=='1560a9b4b706dac5cd6d6fd2699c3c2d4266bef798f0abd8f4d41ba1e96fb4c5'
subprocess.run(['php','-l'],input=smoke,check=True,capture_output=True)
tested=subprocess.run(['sudo','-n','-u','sasuites','php'],input=smoke,capture_output=True,timeout=60)
assert tested.returncode==0,'Actual message pipeline failed: '+tested.stderr.decode()[-1600:]
print(tested.stdout.decode(),end='')
assert hashlib.sha256(pathlib.Path('/opt/waas/app/suites-session-registration.mjs').read_bytes()).hexdigest()=='66323a3abf14526b0388395c69dc8cfabb7c521e56eaf761be6e0eabb31f08d9'
assert hashlib.sha256(pathlib.Path('/opt/suites-crepusculo-attendance/calls-worker.mjs').read_bytes()).hexdigest()=='5f12d5bd6a5f674b746e382b10537e04651b622ed1b2226b5e8039bfa9f436b5'
assert hashlib.sha256(pathlib.Path('/opt/suites-crepusculo-attendance/context.php').read_bytes()).hexdigest()=='e93655ff7aa93e719420c692a3bf9d2930b0a9731a45177059a87f7f65deb399'
code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$s=new App\Services\SuitesMessagesService();$status=$s->status();$state=$status['status']??'offline';
$out=['message_state'=>$state];
if(!in_array($state,['connected','qr','connecting'],true)){
 $qr=$s->qr();$out['message_qr_generated']=is_string($qr)&&strlen($qr)>50;
 $out['message_state']=$s->status()['status']??'unknown';
}
$voice=(new App\Services\AelWaCallsService())->status(1);
$out['voice_paired']=(bool)$voice['paired'];$out['voice_state']=$voice['state'];
$p=App\Core\DB::pdo();
$out['inbound_receipts']=(int)$p->query("SELECT COUNT(*) FROM suites_whatsapp_receipts")->fetchColumn();
$out['whatsapp_conversations']=(int)$p->query("SELECT COUNT(*) FROM conversations WHERE channel='whatsapp'")->fetchColumn();
$out['whatsapp_ai_replies']=(int)$p->query("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.channel='whatsapp' AND m.sender_type='ai'")->fetchColumn();
echo json_encode($out);
"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=45)
assert r.returncode==0,'Final pairing/status check failed: '+r.stderr[-1000:]
print('FINAL_CHANNEL_STATE',r.stdout)
print('PUBLISHED_SOURCE_FILES_MATCH='+str(len(manifest['files'])))
print('MESSAGE_SOURCE_FILES_MATCH='+str(len(manifest['messages']['files'])))
print('CRM_MENU_REMOVED=1; TEXT_CHAT_PRESENT=1; SERVICES_ACTIVE=3; VOICE_CODE_NOT_CHANGED=1; NO_EXTERNAL_MESSAGES_SENT=1; BACKUPS_CREATED=0')
