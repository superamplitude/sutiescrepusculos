import assert from 'node:assert/strict';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const session=JSON.parse(process.env.SUITES_QA_SESSION),clean=process.env.SUITES_EXPECT_CLEAN==='1',origin='https://suitescrepusculo.com.br';
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const context=await browser.newContext();await context.addCookies([{name:session.name,value:session.id,domain:'suitescrepusculo.com.br',path:'/',secure:true,httpOnly:true}]);
 await context.route('**/*',route=>route.request().method()==='GET'?route.continue():route.abort());
 await context.route('**/chat.php?action=panel_voice',route=>route.fulfill({status:200,contentType:'application/json',body:'{"ok":true,"configured":false}'}));
 const invalid=await context.request.get(origin+'/index.php?p=map_reservation_api&id=0');const raw=await invalid.text();let parsed=null;
 try{parsed=JSON.parse(raw);}catch{}
 if(!clean){assert.equal(parsed,null);assert(raw.trimStart().startsWith('<style'));console.log('PREEXISTING_CSS_BEFORE_JSON_CONFIRMED=1');}
 else{
  assert.equal(invalid.status(),422);assert.equal(parsed.ok,false);assert.equal(typeof parsed.message,'string');
  if(session.reservation_id){const positive=await context.request.get(origin+'/index.php?p=map_reservation_api&id='+session.reservation_id);assert.equal(positive.status(),200);const json=await positive.json();assert(json.ok&&Number(json.reservation.id)===session.reservation_id);console.log('EXISTING_RESERVATION_JSON_READ_OK=1');}
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(origin+'/index.php?p=map',{waitUntil:'networkidle'});
  assert.equal(await page.evaluate(()=>document.compatMode),'CSS1Compat');
  assert.equal(await page.locator('head #crepusculo-room-actions').count(),1);
  await page.locator('#suitesVoiceToggle').waitFor();
  await page.locator('.map-toolbar a[href="index.php?p=rooms&view=new"]').click();await page.frameLocator('.suites-map-editor iframe').locator('[name=code]').waitFor();
  await page.setViewportSize({width:390,height:844});const b=await page.locator('.suites-map-editor').boundingBox();assert(b.x>=0&&b.x+b.width<=391);
  assert.deepEqual(errors,[]);console.log('MAP_JSON_VALID=1; ERROR_STATUS=422; STANDARDS_LAYOUT=1; VOICE_CONTROL_PRESERVED=1; MOBILE_OVERLAY_OK=1; BUSINESS_WRITES=0');
 }
 await context.close();
}finally{await browser.close();}
