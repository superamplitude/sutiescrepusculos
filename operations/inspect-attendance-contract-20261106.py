#!/usr/bin/env python3
"""Read existing canonical attendance implementation; no writes or external communications."""
import os,pathlib,re,subprocess,json
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/opt/superamplitude/crm')
assert root.is_dir() and root.resolve()==root
for f in [root/'server.mjs',root/'panel-natural-voice.mjs']:
 if not f.is_file():continue
 lines=f.read_text().splitlines()
 print('SOURCE',str(f))
 hits=[i for i,l in enumerate(lines) if re.search(r'api/events|auth-state|incoming.call|message.received|WACALLS|auto.?answer|auto.?reply',l,re.I)]
 selected=set()
 for i in hits[:35]:selected.update(range(max(0,i-4),min(len(lines),i+14)))
 for i in sorted(selected):
  line=lines[i]
  if len(line)>1200:continue
  if re.search(r'(Bearer\s+[A-Za-z0-9_-]{20}|AIza|password\s*[:=]\s*["\x27]|token\s*[:=]\s*["\x27][A-Za-z0-9_-]{20})',line,re.I):continue
  print(str(i+1)+': '+line)
print('GATEWAY_LISTEN_PROCESS')
r=subprocess.run(['ss','-ltnp','sport = :18180'],capture_output=True,text=True)
print(r.stdout)
print('SUITES_STATE')
code="require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';$s=(new App\\Services\\AelWaCallsService())->status(1);echo json_encode(array_intersect_key($s,array_flip(['configured','exists','paired','state'])));"
r=subprocess.run(['php','-r',code],capture_output=True,text=True)
assert r.returncode==0
print(r.stdout)
