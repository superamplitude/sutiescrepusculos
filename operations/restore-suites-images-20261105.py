#!/usr/bin/env python3
import subprocess,os,json,pathlib,hashlib,pwd,urllib.request,urllib.parse
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
assert root.is_dir() and root.resolve()==root
source=pathlib.Path('deploy/suites-images-20261105')
manifest=json.loads((source/'manifest.json').read_text())
owner=pwd.getpwnam('sasuites')
plan=[]; conflicts=[]
for m in manifest:
 rel=pathlib.PurePosixPath(m['path'])
 assert not rel.is_absolute() and '..' not in rel.parts
 assert str(rel).startswith(('assets/demo/real/','storage/uploads/'))
 src=source/rel; dst=root/rel
 assert hashlib.sha256(src.read_bytes()).hexdigest()==m['sha256']
 assert dst.resolve().is_relative_to(root)
 assert not any(p.is_symlink() for p in [dst,*list(dst.parents)[:len(rel.parts)]])
 if dst.exists() and hashlib.sha256(dst.read_bytes()).hexdigest()!=m['sha256']:
  conflicts.append(m['path'])
 plan.append((m,src,dst))
print('SOURCE_IMAGES',len(plan))
print('EXISTING_DIFFERENT',len(conflicts))
for c in conflicts:print('PRESERVED_DIFFERENT',c)
restored=0
for m,src,dst in plan:
 if dst.exists():continue
 dst.parent.mkdir(parents=True,exist_ok=True)
 # Only new files; O_EXCL prevents overwriting a concurrent write.
 fd=os.open(dst,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644)
 with os.fdopen(fd,'wb') as f:
  f.write(src.read_bytes());f.flush();os.fsync(f.fileno())
 os.chown(dst,owner.pw_uid,owner.pw_gid)
 restored+=1
print('RESTORED_MISSING',restored)
verified=0;httpok=0;failed=[]
for m,src,dst in plan:
 match=hashlib.sha256(dst.read_bytes()).hexdigest()==m['sha256']
 verified+=int(match)
 url='https://suitescrepusculo.com.br/'+urllib.parse.quote(m['path'],safe='/')
 try:
  r=subprocess.run(['curl','--fail','--silent','--show-error','--max-time','30',url],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  data=r.stdout;ok=r.returncode==0 and hashlib.sha256(data).hexdigest()==m['sha256']
  httpok+=int(ok)
  print('PUBLIC_IMAGE',m['path'],'MATCH' if ok else 'CONTENT_DIFFERENT')
  if not ok:failed.append(m['path'])
 except Exception as e:
  print('PUBLIC_IMAGE_FAILED',m['path'],type(e).__name__);failed.append(m['path'])
print('DISK_SHA256_MATCH',verified,'OF',len(plan))
print('PUBLIC_HTTPS_SHA256_MATCH',httpok,'OF',len(plan))
print('DATABASE_CHANGED=0 CODE_CHANGED=0 ACCOUNTS_CHANGED=0 LOCAL_BACKUPS=0')
if conflicts or failed:raise SystemExit(2)
