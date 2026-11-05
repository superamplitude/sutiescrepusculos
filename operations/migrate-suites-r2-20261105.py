import os,pathlib
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/sasuites/htdocs/suitescrepusculo.com.br')
for name in ['index.php','app/Core/U.php','app/Services/UploadService.php']:
 print('FILE',name)
 lines=(root/name).read_text().splitlines()
 if name=='index.php':
  for i,l in enumerate(lines):
   if 'function ' in l and any(x in l.lower() for x in ['asset','media','image','photo']):print('\n'.join(lines[max(0,i-1):i+23]))
 else:print('\n'.join(lines[:150]))
