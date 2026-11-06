#!/usr/bin/env python3
"""Read-only provider probe. No session, login bypass, or message persistence."""
import os,subprocess,json
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
code=r"""
require '/home/sasuites/htdocs/suitescrepusculo.com.br/app/bootstrap.php';
$s=new App\Services\GeminiService();$c=$s->config();
echo 'CONFIG '.json_encode(['enabled'=>(bool)($c['enabled']??false),'model'=>$c['model']??null,'key_present'=>!empty($c['api_key'])]).PHP_EOL;
$r=$s->ask('Responda apenas: atendimento disponível.');
echo 'PROVIDER '.json_encode(['ok'=>$r['ok']??false,'http'=>$r['http']??null,'model'=>$r['model']??null,'error'=>mb_substr(preg_replace('/AIza[A-Za-z0-9_-]+|key=[^&\s"]+/','[REDACTED]',(string)($r['error']??'')),0,1400),'answer_length'=>mb_strlen($r['answer']??'')]);
"""
r=subprocess.run(['sudo','-n','-u','sasuites','php','-r',code],capture_output=True,text=True,timeout=50)
print(r.stdout);assert r.returncode==0,'Provider probe failed'
