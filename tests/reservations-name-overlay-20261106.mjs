import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_RESERVATIONS_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_RESERVATIONS_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1330,height:850}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://suites.local/**',route=>{
  const url=new URL(route.request().url());
  if(url.searchParams.get('p')==='reservation_edit'){
   route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><title>Editar reserva</title><h2>Ficha de reserva</h2><form><input name="guest_name" value="Mariana"><button type="submit">Salvar</button></form>'});
  }else route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><div id="app"></div>'});
 });
 await page.goto('http://suites.local/');
 await page.setContent('<style>body{margin:14px}.card{padding:15px}</style><style>'+css+'</style>'
  +'<div class="admin-mode"><div class="card reservations-list-card"><table class="reservations-list-table">'
  +'<tr><th>Hóspede</th><th>Quarto</th><th>Período</th><th>Status</th><th>Total</th><th>Ação</th></tr>'
  +'<tr><td><a data-reservation-open class="reservations-guest-link" href="index.php?p=reservation_edit&id=42">Mariana</a></td>'
  +'<td>Suíte 01</td><td>01/10 - 03/10</td><td>Confirmada</td><td>R$ 500</td>'
  +'<td><a data-reservation-open href="index.php?p=reservation_edit&id=42">Editar</a></td></tr></table></div>'
  +'<dialog id="reservationOverlay" class="reservation-overlay" aria-labelledby="reservationOverlayTitle">'
  +'<header class="reservation-overlay-head"><h2 id="reservationOverlayTitle">Detalhes da reserva</h2><button data-reservation-close>×</button></header>'
  +'<iframe class="reservation-overlay-frame" title="Ficha de reserva"></iframe></dialog></div>');
 assert.equal(await page.locator('th').count(),6,'code column must be removed');
 assert(!await page.locator('#reservationOverlay').isVisible(),'overlay should start hidden');
 await page.addScriptTag({content:js});
 await page.locator('.reservations-guest-link').click();
 const dlg=page.locator('#reservationOverlay');
 assert(await dlg.evaluate(el=>el.matches(':modal')),'guest name must open modal');
 const src=await page.locator('.reservation-overlay-frame').getAttribute('src');
 assert(src.includes('embed=1')&&src.includes('id=42'),'iframe must reuse reservation edit route in embed mode');
 const form=page.frameLocator('.reservation-overlay-frame').locator('input[name="guest_name"]');
 await form.waitFor({state:'visible'});
 assert.equal(await form.inputValue(),'Mariana');
 const r=await dlg.boundingBox();
 assert(Math.abs(r.x+r.width/2-665)<4,'reservation dialog not centered');
 await page.locator('[data-reservation-close]').click();
 // The script reloads the listing on close to show edits.
 await page.waitForLoadState('load');
 assert(!await page.locator('#reservationOverlay').count(),'closing reloads reservation list');
 assert.deepEqual(errors,[]);
 console.log('RESERVATION_GUEST_OVERLAY_QA_OK: code removed, guest click opens existing edit form in centered modal, embedded route, close and refresh');
}finally{await browser.close();}
