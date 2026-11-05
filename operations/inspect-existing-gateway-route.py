#!/usr/bin/env python3
import os,pathlib,subprocess,urllib.parse,json
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
pid=subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()
env=dict(x.split('=',1) for x in pathlib.Path('/proc/'+pid+'/environ').read_bytes().decode().split('\0') if '=' in x)
raw=env.get('WACALLS_API_BASE_URL','')
p=urllib.parse.urlsplit(raw.strip().strip(chr(34)+chr(39)))
print('EXISTING_GATEWAY_ROUTE='+json.dumps({'scheme':p.scheme,'hostname':p.hostname,'port':p.port,'path':p.path,'query_present':bool(p.query),'userinfo_present':bool(p.username),'outer_quotes':raw!=raw.strip().strip(chr(34)+chr(39))}))
print('EXISTING_GATEWAY_TOKEN_PRESENT='+str(bool(env.get('WACALLS_API_TOKEN'))))
