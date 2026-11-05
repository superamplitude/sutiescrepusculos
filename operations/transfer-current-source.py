#!/usr/bin/env python3
"""Temporary working source checkout for transfer; excludes production data and credentials."""
import os,pathlib,shutil
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
dest=pathlib.Path('/opt/gerente-vps-runner/_work/_temp/crepusculo-source-transfer')
assert not dest.exists()
dest.mkdir(mode=0o700)
uid=int(os.environ['SUDO_UID']);gid=int(os.environ['SUDO_GID'])
files=[]
for name in ['index.php','chat.php','ical.php','admin.php','painel.php','config/config.example.php']:
 p=root/name
 if p.is_file():files.append(p)
for folder,exts in [('app',{'.php'}),('modules',{'.php'}),('pages',{'.php'}),('assets',{'.js','.css'})]:
 for p in (root/folder).rglob('*'):
  rel=p.relative_to(root)
  if p.is_file() and not p.is_symlink() and p.suffix in exts and not str(rel).startswith('app/app/') and 'config' not in rel.parts:files.append(p)
try:
 for p in files:
  assert p.resolve().is_relative_to(root)
  rel=p.relative_to(root);out=dest/rel;out.parent.mkdir(parents=True,exist_ok=True)
  shutil.copyfile(p,out);out.chmod(0o600)
 for p in sorted(dest.rglob('*'),reverse=True):
  os.chown(p,uid,gid)
  if p.is_dir():p.chmod(0o700)
 os.chown(dest,uid,gid)
 print('SOURCE_FILES='+str(len(files))+'; PRODUCTION_CONFIG=excluded; DATABASE=excluded; UPLOADS=excluded')
except BaseException:
 shutil.rmtree(dest);raise
