import assert from 'node:assert/strict';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const session=JSON.parse(process.env.SUITES_QA_SESSION);
const browser=await chromium.launch({headless:true,args:['--host-resolver-rules=MAP suitescrepusculo.com.br 127.0.0.1','--no-proxy-server']});
try {
 const context=await browser.newContext();
 await context.addCookies([{name:session.name,value:session.id,domain:'suitescrepusculo.com.br',path:'/',secure:true,httpOnly:true}]);
 await context.route('**/*',route=>route.request().method()==='GET'?route.continue():route.abort());
 const page=await context.newPage(),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 const response=await page.goto('https://suitescrepusculo.com.br/index.php?p=map',{waitUntil:'networkidle'});
 assert.equal(response.status(),200);await page.locator('body.suites-map-workspace').waitFor();
 const actions=[['index.php?p=reservation_new','[name=room_id]'],['index.php?p=rooms&view=new','[name=code]'],['index.php?p=rooms','.module-actions'],['index.php?p=reservations','h1'],['index.php?p=ai','h1']];
 for(const [href,selector] of actions){
  await page.locator('.map-toolbar a[href="'+href+'"]').click();
  await page.locator('.suites-map-editor[open]').waitFor();
  await page.frameLocator('.suites-map-editor iframe').locator(selector).first().waitFor();
  assert.equal(new URL(page.url()).searchParams.get('p'),'map');
  const box=await page.locator('.suites-map-editor').boundingBox();assert(box.width>300&&box.height>300);
  await page.locator('.suites-map-editor>header button').click();
  await page.locator('.suites-map-editor[open]').waitFor({state:'hidden'});
  console.log('MAP_OVERLAY_OK='+new URL(href,'https://suitescrepusculo.com.br').searchParams.get('p'));
 }
 await page.setViewportSize({width:390,height:844});
 await page.locator('.map-toolbar a[href="index.php?p=rooms&view=new"]').click();
 await page.frameLocator('.suites-map-editor iframe').locator('[name=code]').waitFor();
 const box=await page.locator('.suites-map-editor').boundingBox();
 assert(box.x>=0&&box.x+box.width<=391&&box.y>=0&&box.y+box.height<=845);
 assert.deepEqual(errors,[]);
 console.log('MAP_MOBILE_OVERLAY_OK=1; PAGE_ERRORS=0; BUSINESS_POST_REQUESTS=0');
 await context.close();
} finally {await browser.close();}
