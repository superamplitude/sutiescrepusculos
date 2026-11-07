import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';

const css=fs.readFileSync(process.env.SUITES_RESERVATIONS_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_RESERVATIONS_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try {
 const page=await browser.newPage({viewport:{width:1437,height:900}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://suites.local/**', route=>{
  const uri=new URL(route.request().url());
  if(uri.searchParams.get('p')==='reservation_edit'){
    route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><h1>Editar reserva</h1><label>Nome<input name="guest_name" value="Mariana"></label>'});
  }else route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><title>Painel</title><main>Recarregado</main>'});
 });
 await page.goto('http://suites.local/');
 const csrf='<input type="hidden" name="_csrf" value="token">';
 const form=id=>'<form method="post" action="/painel/?p=reservation_status" id="reservationStatusForm'+id+'" class="reservation-inline-form" data-reservation-inline-form hidden>'
 +csrf+'<input name="id" type="hidden" value="'+id+'"><label class="reservation-inline-field"><span>Status</span>'
 +'<select name="status" required aria-label="Novo status"><option value="">Próxima etapa</option><option value="confirmada">Confirmada</option>'
 +'<option value="cancelada">Cancelada</option></select></label><label class="reservation-inline-field reservation-reason-field">'
 +'<span>Motivo</span><input type="text" name="reason" placeholder="Motivo"></label><button type="submit" class="btn reservation-status-submit">Alterar</button></form>';
 const row=(id,guest,status,editable)=>'<tr class="reservation-list-row"><td class="reservation-guest">'
 +'<a data-reservation-open class="reservations-guest-link" href="/painel/?p=reservation_edit&id='+id+'">'+guest+'</a></td>'
 +'<td class="reservation-room">Suíte Premium Vista Jardim</td>'
 +'<td class="reservation-dates"><span>19/09/2026</span><span class="reservation-date-arrow"> até </span><span>22/09/2026</span></td>'
 +'<td class="reservation-status"><span class="reservation-status-text">'+status+'</span></td>'
 +'<td class="reservation-total">R$ 1.560,00</td>'
 +'<td class="reservation-action-cell"><div class="reservation-row-actions">'
 +(editable?'<button type="button" class="btn secondary small reservation-inline-toggle" data-reservation-edit-toggle aria-expanded="false" aria-controls="reservationStatusForm'+id+'">Editar</button>'+form(id)
 :'<a data-reservation-open class="btn secondary small" href="/painel/?p=reservation_edit&id='+id+'">Ver ficha</a>')
 +'</div></td></tr>';
 const html='<style>body{margin:0;padding:12px;font:13px Arial,sans-serif}.admin-mode{max-width:1130px;margin:auto}.card{padding:20px}table{width:100%}.btn{border:1px solid #ddd;border-radius:6px;padding:7px}</style><style>'+css+'</style>'
 +'<div class="admin-mode"><div class="card reservations-list-card"><div class="reservations-scroll">'
 +'<table class="reservations-list-table"><tr><th>Hóspede</th><th>Quarto</th><th>Período</th><th>Status</th><th>Total</th><th>Ação</th></tr>'
 +row(1,'Mariana','Concluída',false)+row(2,'Felipe','Aguardando aprovação',true)+row(3,'Carla','Confirmada',true)
 +'</table></div></div><dialog id="reservationOverlay" class="reservation-overlay" aria-labelledby="reservationOverlayTitle">'
 +'<header class="reservation-overlay-head"><h2 id="reservationOverlayTitle">Detalhes da reserva</h2><button type="button" data-reservation-close>×</button></header>'
 +'<iframe class="reservation-overlay-frame" title="Ficha de reserva"></iframe></dialog></div>';
 await page.setContent(html);
 const forms=page.locator('[data-reservation-inline-form]');
 assert.equal(await forms.count(),2);
 assert.equal(await page.locator('[data-reservation-inline-form]:visible').count(),0,'Status/reason fields must not render before Editar');
 assert.equal(await page.locator('tr.reservation-list-row').count(),3,'Existing reservation rows must stay visible');
 assert.equal(await page.locator('th').count(),6,'Preserve six informational columns');
 assert.equal(await page.locator('tr.reservation-list-row').first().getByText('Ver ficha').count(),1,'Closed reservation must keep accessible details');
 assert.equal(await page.locator('tr.reservation-list-row').nth(1).locator('.reservation-dates').innerText(),'19/09/2026 até 22/09/2026');
 await page.addScriptTag({content:js});
 const toggle2=page.locator('tr.reservation-list-row').nth(1).locator('[data-reservation-edit-toggle]');
 await toggle2.click();
 const form2=page.locator('#reservationStatusForm2');
 assert(await form2.isVisible(),'Edit must show status and reason in the same row');
 assert.equal(await toggle2.getAttribute('aria-expanded'),'true');
 assert.equal(await toggle2.innerText(),'Fechar');
 assert(await form2.locator('select[name="status"]').isVisible());
 assert(await form2.locator('input[name="reason"]').isVisible());
 assert.equal(await form2.locator('input[name="reason"]').getAttribute('value'),null);
 await form2.locator('select[name="status"]').selectOption('confirmada');
 await form2.locator('input[name="reason"]').fill('Reserva aprovada');
 assert.deepEqual(await form2.evaluate(f=>[...new FormData(f).entries()].filter(x=>['id','status','reason'].includes(x[0]))),[['id','2'],['status','confirmada'],['reason','Reserva aprovada']]);
 await page.locator('tr.reservation-list-row').nth(2).locator('[data-reservation-edit-toggle]').click();
 assert(!await form2.isVisible(),'Opening another inline editor should close previous row');
 assert(await page.locator('#reservationStatusForm3').isVisible());
 await page.keyboard.press('Escape');
 assert(!await page.locator('#reservationStatusForm3').isVisible(),'Escape must dismiss inline editor');
 // The guest name still opens the pre-existing full reservation detail modal.
 await page.locator('tr.reservation-list-row').first().locator('[data-reservation-open]').first().click();
 assert(await page.locator('#reservationOverlay').evaluate(e=>e.matches(':modal')),'Guest link must still open detail overlay');
 const src=await page.locator('.reservation-overlay-frame').getAttribute('src');
 assert(src.includes('embed=1')&&src.includes('id=1'));
 await page.setViewportSize({width:390,height:760});
 await page.keyboard.press('Escape');
 await page.waitForLoadState('load');
 // Reload on closing overlay is expected; restore the same synthetic UI for mobile layout.
 await page.setContent(html);
 await page.addScriptTag({content:js});
 const mobileToggle=page.locator('tr.reservation-list-row').nth(1).locator('[data-reservation-edit-toggle]');
 await mobileToggle.click();
 assert(await page.locator('#reservationStatusForm2').isVisible());
 const screenOverflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);
 assert.equal(screenOverflow,false,'Mobile list must not overflow horizontally');
 assert.deepEqual(errors,[],'No browser JS runtime errors');
 console.log('RESERVATION_INLINE_EDIT_QA_OK: information shown, status/reason hidden until Editar in same row, independent rows, Escape, POST values, guest overlay and mobile');
}finally{await browser.close();}
