#!/usr/bin/env python3
import os,pathlib,subprocess,urllib.request,json,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
with urllib.request.urlopen('http://127.0.0.1:18190/sessions/suites-crepusculo/status',timeout=5) as r:
 d=json.load(r);s=d.get('session')or{};print('MESSAGE_CONNECTION_STATE',json.dumps({'status':s.get('status'),'user_present':bool(s.get('user')),'qr_present':bool(s.get('qr'))}))
raw=subprocess.check_output(['journalctl','-u','sa-crm-whatsapp-gateway.service','--since','12 minutes ago','--no-pager','-o','cat'],text=True)
selected=[]
for line in raw.splitlines():
 if re.search(r'WA_(CLOSED|CONNECTED|RECONNECT_ERROR|STATE_ERROR)\s+suites-crepusculo\b',line):
  line=re.sub(r'\b\d{8,}\b','[NUMBER]',line);selected.append(line[:500])
 elif line.startswith('{'):
  try:v=json.loads(line)
  except:continue
  msg=str(v.get('msg',''))
  if re.search(r'connection (?:closed|failure)|stream error|failed to pair|not logged|logged out',msg,re.I):
   selected.append(json.dumps({'level':v.get('level'),'msg':re.sub(r'\b\d{8,}\b','[NUMBER]',msg)[:300],'reason':v.get('reason')}))
print('PAIRING_EVENT_ERRORS',json.dumps(selected[-20:]))
pkg=json.loads(pathlib.Path('/opt/waas/app/node_modules/@whiskeysockets/baileys/package.json').read_text());print('INSTALLED_BAILEYS_VERSION',pkg.get('version'))
node="""const b=await import('@whiskeysockets/baileys');console.log('DEFAULT_WA_VERSION',JSON.stringify(b.DEFAULT_CONNECTION_CONFIG?.version));if(b.fetchLatestBaileysVersion){const x=await b.fetchLatestBaileysVersion();console.log('VENDOR_LATEST_WA_VERSION',JSON.stringify({version:x.version,isLatest:x.isLatest}));}"""
r=subprocess.run(['sudo','-n','-u','sacrm','node','--input-type=module','-e',node],cwd='/opt/waas/app',capture_output=True,text=True,timeout=25)
print(r.stdout)
print('NO_CONNECTION_RESET=1; NO_VOICE_CHANGES=1')
