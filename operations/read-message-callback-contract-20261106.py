#!/usr/bin/env python3
"""Read narrowly scoped messaging callback contract, never whole files or credentials."""
import os,pathlib,re,json
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
p=pathlib.Path('/opt/waas/app/server.mjs')
s=p.read_text()
def safe(value):
 return re.sub(r'AIza[A-Za-z0-9_-]+|(?i:Bearer)\s+[A-Za-z0-9_.-]{24,}','[REDACTED]',value)
for token in ['messages.upsert','async function saveMessage','async function handleMessage','sendMessage(','createServer(']:
 i=s.find(token)
 if i>=0:
  excerpt=s[max(0,i-250):min(len(s),i+2400)]
  print('CONTRACT_EXCERPT',token,safe(excerpt))
print('IMPORT_MODULE_NAMES',json.dumps(sorted(set(re.findall(r'from\s*["\x27]([^"\x27]+)',s)))))
print('ENVIRONMENT_KEY_NAMES',json.dumps(sorted(set(re.findall(r'process\.env\.([A-Z_0-9]+)',s)))))
print('NO_FILES_CHANGED=1; NO_SESSIONS_CREATED=1; NO_MESSAGES_SENT=1')
