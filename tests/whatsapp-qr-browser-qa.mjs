import assert from 'node:assert/strict';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const session=JSON.parse(process.env.SUITES_QA_SESSION),origin='https://suitescrepusculo.com.br';
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const context=await browser.newContext();await context.addCookies([{name:session.name,value:session.id,domain:'suitescrepusculo.com.br',path:'/',secure:true,httpOnly:true}]);
 await context.route('**/chat.php?action=panel_voice',route=>route.fulfill({status:200,contentType:'application/json',body:'{"ok":true,"configured":false}'}));
 const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 const response=await page.goto(origin+'/index.php?p=whatsapp_rnl',{waitUntil:'networkidle'});assert.equal(response.status(),200);
 const connected=await page.locator('.wa-connected-box').count();
 if(connected){console.log('WHATSAPP_ALREADY_CONNECTED_PRESERVED=1');}
 else{
  await page.getByRole('button',{name:'Gerar QR Code',exact:true}).click();
  await page.locator('#aelWhatsappQr canvas').waitFor({timeout:45000});
  const canvas=await page.locator('#aelWhatsappQr canvas').evaluate(c=>{const p=c.getContext('2d').getImageData(0,0,c.width,c.height).data;let black=0,white=0;for(let i=0;i<p.length;i+=4){if(p[i]<20)black++;if(p[i]>240)white++;}return {width:c.width,height:c.height,hasBlack:black>1000,hasWhite:white>1000};});
  assert.equal(canvas.width,320);assert.equal(canvas.height,320);assert(canvas.hasBlack&&canvas.hasWhite);
  assert.deepEqual(errors,[]);
  console.log('REAL_PAIRING_QR_CANVAS_RENDERED='+JSON.stringify(canvas));
  console.log('WHATSAPP_PAIRING_REQUIRES_USER_SCAN=1; EXTERNAL_MESSAGES_SENT=0; EXTERNAL_CALLS_STARTED=0; PAGE_ERRORS=0');
 }
 await context.close();
}finally{await browser.close();}
