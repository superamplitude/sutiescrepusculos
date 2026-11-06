#!/usr/bin/env python3
"""Focused diagnosis of the concrete Suites messaging connect error; no QR or secret output."""
import os,urllib.request,urllib.error,json,pathlib,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
url='http://127.0.0.1:18190/sessions/suites-crepusculo/connect'
req=urllib.request.Request(url,data=b'{}',headers={'Content-Type':'application/json'},method='POST')
try:
 with urllib.request.urlopen(req,timeout=20) as r:
  d=json.load(r);print('CONNECT_RESPONSE',json.dumps({'http':r.status,'ok':d.get('ok'),'state':(d.get('session')or{}).get('status')}))
except urllib.error.HTTPError as e:
 try:d=json.loads(e.read())
 except:d={}
 print('CONNECT_ERROR',json.dumps({'http':e.code,'error':str(d.get('error',''))[:400]}))
s=pathlib.Path('/opt/waas/app/server.mjs').read_text()
i=s.find('async function connect(')
if i>=0:print('CONNECT_PRELUDE',s[i:i+1800])
print('NO_VOICE_CHANGES=1; NO_EXTERNAL_MESSAGES_SENT=1')
