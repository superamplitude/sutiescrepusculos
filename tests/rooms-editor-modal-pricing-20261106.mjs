import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';

const css=fs.readFileSync(process.env.SUITES_ROOMS_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_ROOMS_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try {
 const page=await browser.newPage({viewport:{width:1400,height:860}});
 const errors=[];
 page.on('pageerror',err=>errors.push(err.message));
 await page.goto('about:blank');
 const html='<body class="admin-mode"><style>'+css+'</style>'
  +'<div class="room-actions-toolbar"><a class="btn room-toolbar-back" href="#">Voltar</a><button data-room-open="roomEditDialog">Editar unidade</button>'
  +'<button data-room-open="roomPhotosDialog">Fotos da unidade</button><a class="btn room-section-button" href="#room-pricing">Diária</a>'
  +'<a class="btn room-section-button" href="#room-pricing">Pacote</a><form><button class="btn room-delete-button">Excluir unidade</button></form></div>'
  +'<dialog class="room-admin-dialog" id="roomEditDialog"><div class="room-dialog-head"><h2>Editar unidade</h2><button data-room-close>×</button></div>'
  +'<form class="room-edit-form"><div class="grid2"><label>Nome<input name="name"></label><fieldset class="room-status-switch"><legend>Status</legend><div class="room-status-options">'
  +'<label><input type="radio" name="status" value="active" checked><span>Ligado</span></label>'
  +'<label><input type="radio" name="status" value="inactive"><span>Desligado</span></label>'
  +'<label><input type="radio" name="status" value="maintenance"><span>Manutenção</span></label></div></fieldset></div><div class="form-actions"><button type="button" data-room-close>Cancelar</button></div></form></dialog>'
  +'<dialog class="room-admin-dialog room-photo-dialog" id="roomPhotosDialog"><div class="room-dialog-head"><h2>Fotos da unidade</h2><button data-room-close>×</button></div>'
  +'<form class="room-media-form"><div class="grid2"><input type="file"><input type="file"></div><div class="room-dialog-actions"><button data-room-close type="button">Cancelar</button></div></form></dialog>'
  +'<section class="card rooms-pricing-card" id="room-pricing"><form class="room-base-price-form"><label>Diária-base<input type="number" step="0.01" name="base_price" value="280.00"></label><button>Salvar diária</button></form></section></body>';
 await page.setContent(html);
 await page.addScriptTag({content:js});
 assert.equal(await page.locator('.room-actions-toolbar > button').count(),2);
 const orange=await page.locator('.room-actions-toolbar [data-room-open]').first().evaluate(el=>getComputedStyle(el).backgroundColor);
 assert.equal(orange,'rgb(237, 122, 30)','orange buttons must be visible');
 await page.locator('[data-room-open="roomEditDialog"]').click();
 assert(await page.locator('#roomEditDialog').evaluate(el=>el.open));
 let b=await page.locator('#roomEditDialog').boundingBox();
 assert(Math.abs(b.x+b.width/2-700)<3,'edit dialog horizontal center');
 assert(Math.abs(b.y+b.height/2-430)<3,'edit dialog vertical center');
 await page.locator('[value="inactive"]').check();
 assert(await page.locator('[value="inactive"]').isChecked());
 await page.locator('#roomEditDialog [data-room-close]').first().click();
 assert(!await page.locator('#roomEditDialog').evaluate(el=>el.open));
 await page.locator('[data-room-open="roomPhotosDialog"]').click();
 assert(await page.locator('#roomPhotosDialog').evaluate(el=>el.open));
 await page.keyboard.press('Escape');
 assert(!await page.locator('#roomPhotosDialog').evaluate(el=>el.open));
 await page.setViewportSize({width:390,height:700});
 await page.locator('[data-room-open="roomEditDialog"]').click();
 b=await page.locator('#roomEditDialog').boundingBox();
 assert(b.x>=0&&b.x+b.width<=391,'mobile horizontal fit');
 assert(b.y>=0&&b.y+b.height<=701,'mobile vertical fit');
 assert.deepEqual(errors,[]);
 console.log('ROOM_EDITOR_QA_OK: orange top buttons, centered edit/gallery dialogs, status on/off, Escape, mobile fit, no JS errors');
} finally {await browser.close();}
