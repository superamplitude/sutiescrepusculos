import os,pathlib
assert os.environ.get('RUNNER_NAME')=='gerente-vps'
root=pathlib.Path('/home/reservenolitoral/htdocs/reservenolitoral.com.br')
view=root/'resources/views/partner/availability.php'
print('RNL_AVAILABILITY_VIEW')
for i,line in enumerate(view.read_text().splitlines()[:240],1):print(str(i)+':'+line)
css=(root/'public/assets/app.css').read_text()
print('RNL_MAP_STYLES')
for selector in ['.availability-toolbar','.map-month-board','.timeline-unit-header','.timeline-date-head','.timeline-event','.map-day-occupancy','.map-event-dialog','.availability-screen']:
 pos=css.rfind(selector)
 if pos>=0:print(selector,css[pos:pos+1000])
