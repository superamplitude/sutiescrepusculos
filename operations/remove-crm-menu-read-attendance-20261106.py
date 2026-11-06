#!/usr/bin/env python3
"""Remove only the requested CRM menu; read integration contracts without sessions or secrets."""
import os,pathlib,subprocess,urllib.request,hashlib,pwd,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
target=root/'app/Views/layout.php'
assert root.resolve()==root and target.is_file() and not target.is_symlink()
old=target.read_bytes()
assert hashlib.sha256(old).hexdigest()=='709ac40d99fd18368a1f534b3c7c9295e1204fb4521acaeb4ebe1202eab60924','Layout baseline changed'
data=urllib.request.urlopen('https://raw.githubusercontent.com/superamplitude/sutiescrepusculos/0f9f47878f27e70ef1bca480b19d21990bb436b7/app/Views/layout.php',timeout=20).read()
assert hashlib.sha256(data).hexdigest()=='e88721a69b868b7a44585fa78c40fd142c658da3149a93908320a860aaa1eeec'
subprocess.run(['php','-l'],input=data,check=True,capture_output=True)
assert b'<summary>CRM</summary>' not in data
for marker in [b'<summary>Reservas</summary>',b'<summary>Atendimento e conte',b'id="suitesVoiceToggle"',b'data-public-chat']:
 assert marker in data
try:
 target.write_bytes(data)
 assert target.read_bytes()==data
 print('CRM_MENU_REMOVED=1; VOICE_AND_OTHER_GROUPS_PRESERVED=1; PHP_SYNTAX_OK=1')
except BaseException:
 target.write_bytes(old);raise
crm=pathlib.Path('/opt/superamplitude/crm')
assert crm.resolve()==crm and crm.is_dir()
server=(crm/'server.mjs').read_text().splitlines()
pattern=re.compile(r'messages\.upsert|async function.*[Ww]hatsapp|function.*[Ww]hatsapp|sendMessage\(|downloadMediaMessage|transcri',re.I)
hits=[i for i,l in enumerate(server) if pattern.search(l)]
print('MESSAGE_FLOW_INDEX')
for i in hits[:100]:
 line=server[i]
 if len(line)>900:continue
 print(str(i+1)+': '+line)
print('MESSAGE_EVENT_FLOW')
hits=[i for i,l in enumerate(server) if 'messages.upsert' in l]
for i in hits[:3]:
 for j in range(max(0,i-10),min(len(server),i+120)):
  line=server[j]
  if len(line)<1200 and not re.search(r'AIza|password\s*[:=]\s*["\x27]|token\s*[:=]\s*["\x27][A-Za-z0-9_-]{20}',line,re.I):print(str(j+1)+': '+line)
print('CURRENT_REUSABLE_LIBRARIES')
for name in ['wacalls.mjs','voice-ai-live.mjs','panel-natural-voice.mjs']:
 p=crm/'lib'/name
 print(name,'sha256='+hashlib.sha256(p.read_bytes()).hexdigest(),'readable_for_site='+str(subprocess.run(['sudo','-n','-u','sasuites','test','-r',str(p)]).returncode==0))
print('GATEWAY_SOURCE_LOCATION')
r=subprocess.run(['ss','-ltnp','sport = :18180'],capture_output=True,text=True)
m=re.search(r'pid=(\d+)',r.stdout)
if m:
 pid=m.group(1)
 for name in ['cwd','exe']:
  print(name,str(pathlib.Path('/proc/'+pid+'/'+name).resolve()))
print('DATABASE_WRITES=0; SESSIONS_CREATED=0; RESTARTS=0; BACKUPS_CREATED=0')
