import os,pathlib,hashlib,json
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
for f in ['/opt/superamplitude/communication/server.mjs','/opt/superamplitude/communication/natural-voice.mjs','/opt/suites-crepusculo-attendance/context.php','/opt/suites-crepusculo-messages/messages.php']:
 p=pathlib.Path(f);print('BASELINE',f,hashlib.sha256(p.read_bytes()).hexdigest())
c=json.loads(pathlib.Path('/etc/superamplitude/runtime/communication-projects.json').read_text());print('PROJECT_VOICE',json.dumps({k:{'voice':v.get('voice'),'model':v.get('model')} for k,v in c.get('projects',{}).items()}))
print('READ_ONLY=1')
