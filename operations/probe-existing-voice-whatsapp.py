#!/usr/bin/env python3
import pathlib,subprocess,json,urllib.request,urllib.error,struct,re
assert __import__('os').environ.get('RUNNER_NAME')=='gerente-vps'
def envfile(p):
 if not p.exists():return {}
 return {line.split('=',1)[0]:line.split('=',1)[1].strip().strip(chr(34)+chr(39)) for line in p.read_text().splitlines() if '=' in line and not line.startswith('#')}
pid=subprocess.check_output(['systemctl','show','superamplitude-crm.service','-p','MainPID','--value'],text=True).strip()
env=dict(x.split('=',1) for x in pathlib.Path('/proc/'+pid+'/environ').read_bytes().decode().split('\0') if '=' in x)
token=envfile(pathlib.Path('/etc/superamplitude/runtime/crm-central-api.env')).get('CENTRAL_COMMUNICATION_CRM_TOKEN')
req=urllib.request.Request('http://127.0.0.1:13002/communication/v1/projects/crm/voice/speech',data=json.dumps({'text':'Olá. Este é um teste da voz natural para atendimento.','provider_api_key':env.get('CRM_GOOGLE_API_KEY','')}).encode(),headers={'Content-Type':'application/json','Authorization':'Bearer '+str(token)})
try:
 with urllib.request.urlopen(req,timeout=85) as r:
  b=r.read()
  print('EXISTING_CENTRAL_VOICE='+json.dumps({'status':r.status,'bytes':len(b),'riff':b[:4]==b'RIFF','wave':b[8:12]==b'WAVE','sample_rate':struct.unpack('<I',b[24:28])[0] if len(b)>28 else None,'data_bytes':struct.unpack('<I',b[40:44])[0] if len(b)>=44 else None}))
except urllib.error.HTTPError as e:print('EXISTING_CENTRAL_VOICE_HTTP='+str(e.code))
code=r"""require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';try{$s=(new App\Services\AelWaCallsService())->status(1);echo json_encode(['session_exists'=>$s['exists'],'paired'=>$s['paired'],'state'=>$s['state']]);}catch(Throwable $e){$m=preg_replace('/[A-Za-z0-9_-]{24,}/','[REDACTED]',$e->getMessage());echo json_encode(['error'=>$m]);}"""
r=subprocess.run(['php','-r',code],capture_output=True,check=True,text=True);print('EXISTING_WHATSAPP='+r.stdout)
a=envfile(pathlib.Path('/etc/crepusculo/wacalls.env')).get('WACALLS_API_TOKEN','')
g=envfile(pathlib.Path('/etc/superamplitude/crm-voice-app.env')).get('WACALLS_API_TOKEN','')
print('WA_PROJECT_TOKEN_PRESENT='+str(bool(a))+'; WA_EXISTING_RUNTIME_TOKEN_MATCH='+str(bool(a) and a==g))
