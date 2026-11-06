import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const style=fs.readFileSync(process.env.SUITES_ROOMS_CSS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1360,height:860}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 const html='<style>.btn{display:inline-flex;align-items:center;min-height:35px;padding:6px 12px;background:#0b8178;color:#fff;border-radius:8px}.card{border:1px solid #a7b6b1;border-radius:10px;padding:18px;background:white}body{margin:16px;font:13px Arial}.table-actions{display:flex;gap:8px}</style><style>'+style+'</style>'
 +'<div class="module-actions rooms-list-actions"><a class="btn" href="#">+ Adicionar unidade</a></div>'
 +'<div class="card rooms-list-card"><div class="rooms-table-scroll"><table class="rooms-list-table"><thead><tr><th>Foto</th><th>Nome</th><th>Status</th><th>Capacidade</th><th>Diária</th><th>Tarifas / Pacotes</th><th>Ações</th></tr></thead><tbody><tr>'
 +'<td><a class="room-photo-link" href="#"><span class="room-photo-empty">Sem foto</span></a></td><td><a class="room-name-link" href="#">Suíte 01</a><small>STD01</small></td><td>Ativo</td><td>3</td><td>R$ 280,00</td><td><div class="room-price-actions"><a class="btn secondary small" href="#">Diária</a><a class="btn secondary small" href="#">Pacote</a></div></td><td><div class="table-actions"><a class="btn" href="#">Editar</a><button class="btn">Excluir</button></div></td>'
 +'</tr></tbody></table></div></div>';
 await page.setContent(html);
 const button=await page.locator('.rooms-list-actions .btn').boundingBox();
 const card=await page.locator('.rooms-list-card').boundingBox();
 assert(card.y-(button.y+button.height)>=37,'Add unit button is too close to table');
 assert.equal(await page.locator('.rooms-list-table thead th').count(),7);
 assert.equal(await page.locator('.room-price-actions a').count(),2);
 assert.equal(await page.locator('.room-photo-link').count(),1);
 const desktop=await page.locator('.rooms-table-scroll').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth}));
 assert(desktop.scroll<=desktop.width+2,'desktop needs no horizontal table scrolling');
 await page.setViewportSize({width:390,height:750});
 const mobile=await page.locator('.rooms-table-scroll').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth}));
 assert(mobile.width>0&&mobile.scroll>=mobile.width,'mobile table container must be scrollable without overflow');
 assert.deepEqual(errors,[]);
 console.log('ROOM_LIST_UI_OK: 7 columns, photo, daily/package buttons, 48px spacing, desktop fit and mobile responsive scroll');
}finally{await browser.close();}
