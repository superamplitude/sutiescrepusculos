#!/usr/bin/env python3
"""Draft for reviewed runner deployment only; modifies map UI, never reservation data."""
import os,pathlib,subprocess,hashlib,fcntl
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
target=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br/index.php')
assert target.resolve()==target
payload=pathlib.Path('deploy/suites-map-ui-20261105')
css=(payload/'map-enhancement.css').read_text(encoding='utf-8-sig')
js=(payload/'map-enhancement.js').read_text(encoding='utf-8-sig')
assert '</script' not in js.lower() and '</style' not in css.lower()
marker="  render('Mapa de Reservas',$h);exit;"
with target.open('r+b') as f:
 fcntl.flock(f,fcntl.LOCK_EX)
 original=f.read();text=original.decode()
 assert 'suites-map-ui-20261105' not in text,'Already applied; do not duplicate'
 assert text.count(marker)==1 and 'id=blockForm' in text and 'id="mapReservationModal"' in text
 insert="  $h.= <<<'SUITES_MAP_UI_20261105'\n<!-- suites-map-ui-20261105 -->\n<style>"+css+"</style>\n<script>"+js+"</script>\nSUITES_MAP_UI_20261105;\n"
 candidate=text.replace(marker,insert+marker).encode()
 lint=subprocess.run(['php','-l'],input=candidate,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 assert lint.returncode==0,lint.stdout.decode()+lint.stderr.decode()
 try:
  f.seek(0);f.write(candidate);f.truncate();f.flush();os.fsync(f.fileno())
  assert hashlib.sha256(target.read_bytes()).digest()==hashlib.sha256(candidate).digest()
 except BaseException:
  f.seek(0);f.write(original);f.truncate();f.flush();os.fsync(f.fileno());raise
print('MAP_UI_APPLIED; PHP_SYNTAX_OK; DATABASE_WRITES=0; BACKUPS_CREATED=0; RESTARTS=0')

