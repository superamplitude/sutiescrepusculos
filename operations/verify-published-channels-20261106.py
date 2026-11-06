#!/usr/bin/env python3
"""Final read-only verification of the published channels. Never resets connections or creates QR."""
import os,pathlib,json,hashlib,urllib.request,subprocess,time
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/907f2ece317d35cfa96256133d54dc82159bf8a9/deploy/runtime-manifest-20261106.json',timeout=20).read()
assert hashlib.sha256(data).hexdigest()=='2b4806ae9a573a0cea1b50edac11700cbb5d4999989cc492f7141a8d6a15687f'
m=json.loads(data);root=pathlib.Path(m['runtime'])
for name,digest in m['files'].items():assert hashlib.sha256((root/name).read_bytes()).hexdigest()==digest,'Site source '+name
for section in ['messages','calls']:
 for name,digest in m[section]['files'].items():assert hashlib.sha256((pathlib.Path(m[section]['runtime'])/name).read_bytes()).hexdigest()==digest,section+' source '+name
for name,digest in m['messages']['gateway_files'].items():assert hashlib.sha256((pathlib.Path('/opt/waas/app')/name).read_bytes()).hexdigest()==digest,'Gateway adapter '+name
assert hashlib.sha256(pathlib.Path('/opt/waas/app/server.mjs').read_bytes()).hexdigest()==m['messages']['gateway_source_sha256']
for name in ['suites-crepusculo-attendance.service','suites-crepusculo-messages.service','sa-crm-whatsapp-gateway.service']:
 assert subprocess.check_output(['systemctl','is-active',name],text=True).strip()=='active'
layout=(root/'app/Views/layout.php').read_text();assert '<summary>CRM</summary>' not in layout and 'Chat e voz</button>' in layout
def state():
 with urllib.request.urlopen('http://127.0.0.1:18190/sessions/suites-crepusculo/status',timeout=5) as response:
  s=json.load(response).get('session')or{};return str(s.get('status','offline'))
first=state();samples=[first]
if first=='connected':
 time.sleep(15);samples.append(state())
print('MESSAGE_CONNECTION_STABILITY',json.dumps(samples))
code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$p=App\Core\DB::pdo();$v=(new App\Services\AelWaCallsService())->status(1);
echo json_encode(['voice_paired'=>(bool)$v['paired'],'voice_state'=>$v['state'],
'inbound_receipts'=>(int)$p->query("SELECT COUNT(*) FROM suites_whatsapp_receipts")->fetchColumn(),
'whatsapp_conversations'=>(int)$p->query("SELECT COUNT(*) FROM conversations WHERE channel='whatsapp'")->fetchColumn(),
'whatsapp_ai_replies'=>(int)$p->query("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.channel='whatsapp' AND m.sender_type='ai'")->fetchColumn()]);
"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=15)
assert r.returncode==0,'Read-only channel state failed'
print('FINAL_CHANNEL_COUNTS',r.stdout)
print('PUBLISHED_SITE_FILES_MATCH='+str(len(m['files'])))
print('MESSAGE_FILES_MATCH='+str(len(m['messages']['files']))+'; FROZEN_CALL_FILES_MATCH='+str(len(m['calls']['files'])))
print('CRM_MENU_REMOVED=1; MANAGEMENT_TEXT_CHAT_PRESENT=1; SERVICES_ACTIVE=3; NO_VOICE_CHANGES=1; CONNECTIONS_RESET=0; AUTH_SESSIONS_CREATED=0; BUSINESS_WRITES=0')
print('EXTERNAL_TEXT_TEST_PENDING_OWNER_CONFIRMATION=1')
