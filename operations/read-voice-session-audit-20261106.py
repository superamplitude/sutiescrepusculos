#!/usr/bin/env python3
"""Read only: explain missing voice session after text connection; never reconnects or changes voice."""
import os,pathlib,subprocess,json,re
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
status=pathlib.Path('/run/suites-attendance/status.json')
if status.exists():
 s=json.loads(status.read_text());print('CALL_WORKER_STATE',json.dumps({k:s.get(k) for k in ['gateway','paired','state','active_calls','last_error','last_call_answered','updated_at']}))
r=subprocess.check_output(['systemctl','show','suites-crepusculo-attendance.service','-p','MainPID','-p','ExecMainStartTimestamp','-p','ActiveEnterTimestamp'],text=True);print('CALL_WORKER_PROCESS',r.strip())
ss=subprocess.check_output(['ss','-ltnp','sport = :18180'],text=True);pids=re.findall(r'pid=(\d+)',ss);print('CALL_GATEWAY_ORIGINAL_PID_STILL_PRESENT',str('1418597' in pids))
code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$p=App\Core\DB::pdo();
$q=$p->query("SELECT action,created_at FROM audit_logs WHERE action IN('whatsapp.ael.pair','whatsapp.ael.disconnect') AND created_at>=NOW()-INTERVAL 3 HOUR ORDER BY id DESC LIMIT 12");
echo 'VOICE_UI_AUDIT '.json_encode($q->fetchAll()).PHP_EOL;
$s=(new App\Services\AelWaCallsService())->status(1);
echo 'VOICE_CURRENT '.json_encode(['exists'=>$s['exists'],'paired'=>$s['paired'],'state'=>$s['state']]).PHP_EOL;
"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=15);assert r.returncode==0;print(r.stdout)
print('VOICE_READ_ONLY=1; NO_RESTARTS=1; NO_PAIRING_REQUESTS=1; NO_CONNECTIONS_DELETED=1')
