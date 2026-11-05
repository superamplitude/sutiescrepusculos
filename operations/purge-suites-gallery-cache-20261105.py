import os,json,urllib.request,urllib.parse,urllib.error
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
token=os.environ.get('CLOUDFLARE_API_TOKEN','')
if not token:raise SystemExit('EXISTING_CLOUDFLARE_SECRET_EMPTY')
def call(method,path,body=None):
 req=urllib.request.Request('https://api.cloudflare.com/client/v4'+path,data=None if body is None else json.dumps(body).encode(),headers={'Authorization':'Bearer '+token,'Content-Type':'application/json'},method=method)
 try:
  with urllib.request.urlopen(req,timeout=30) as r:d=json.load(r)
 except urllib.error.HTTPError as e:raise SystemExit('CLOUDFLARE_HTTP_'+str(e.code))
 if not d.get('success'):raise SystemExit('CLOUDFLARE_OPERATION_FAILED')
 return d
zones=call('GET','/zones?name=suitescrepusculo.com.br&status=active')['result']
if len(zones)!=1:raise SystemExit('EXISTING_TOKEN_CANNOT_RESOLVE_SITE_ZONE')
images=json.load(open('deploy/suites-images-20261105/manifest.json'))
files=['https://suitescrepusculo.com.br/'+m['path'] for m in images if m['path'].startswith('storage/uploads/gallery/')]
assert len(files)==7
call('POST','/zones/'+zones[0]['id']+'/purge_cache',{'files':files})
print('PURGED_GALLERY_URLS',len(files))
print('DNS_CHANGED=0 RULES_CHANGED=0')
