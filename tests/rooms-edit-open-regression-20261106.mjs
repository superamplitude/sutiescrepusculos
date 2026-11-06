import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_ROOMS_CSS,'utf8');
const script=fs.readFileSync(process.env.SUITES_ROOMS_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try {
 const page=await browser.newPage({viewport:{width:1280,height:820}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 const base='index.php?p=rooms&view=edit&id=1';
 await page.setContent('<style>body{margin:20px} .room-actions-toolbar{display:flex;gap:8px}</style><style>'+css+'</style>'
  +'<div class="admin-mode"><div class="room-actions-toolbar">'
  +'<a class="btn" data-room-open="roomEditDialog" href="'+base+'&form=edit">Editar unidade</a>'
  +'<a class="btn" data-room-open="roomPhotosDialog" href="'+base+'&form=photos">Fotos da unidade</a></div>'
  +'<dialog id="roomEditDialog" class="room-admin-dialog" aria-labelledby="roomEditTitle" open data-room-auto="1">'
  +'<div class="room-dialog-head"><h2 id="roomEditTitle">Editar unidade · Suíte 01</h2><a data-room-close href="'+base+'">×</a></div>'
  +'<form class="room-edit-form" method="post"><input name="name" value="Suíte 01" aria-label="Nome público">'
  +'<button type="submit">Salvar alterações</button><a data-room-close href="'+base+'">Cancelar</a></form></dialog>'
  +'<dialog id="roomPhotosDialog" class="room-admin-dialog room-photo-dialog"><div class="room-dialog-head"><h2>Fotos da unidade</h2>'
  +'<a data-room-close href="'+base+'">×</a></div><form class="room-media-form"><input type="file" name="cover_image">'
  +'<button>Salvar mídias</button></form></dialog></div>');
 const edit=page.locator('#roomEditDialog'),photos=page.locator('#roomPhotosDialog');
 // The server-side open attribute must expose an editable form even before JS loads.
 assert(await edit.isVisible(),'No-JS fallback should display an editable form');
 assert.equal(await edit.locator('input[name="name"]').inputValue(),'Suíte 01');
 await edit.locator('input[name="name"]').fill('Suíte 01 revisada');
 assert.equal(await edit.locator('input[name="name"]').inputValue(),'Suíte 01 revisada');
 await page.addScriptTag({content:script});
 assert(await edit.evaluate(el=>el.matches(':modal')),'Arriving from the list should open an actual modal');
 const bounds=await edit.boundingBox();
 assert(Math.abs(bounds.x+bounds.width/2-640)<4,'Edit dialog must be horizontally centered');
 await edit.locator('[data-room-close]').first().click();
 assert(!await edit.evaluate(el=>el.open),'Edit close did not work');
 await page.locator('[data-room-open="roomEditDialog"]').click();
 assert(await edit.evaluate(el=>el.matches(':modal')),'Editing from room toolbar should open a modal');
 await page.keyboard.press('Escape');
 assert(!await edit.evaluate(el=>el.open),'Escape did not close edit dialog');
 await page.locator('[data-room-open="roomPhotosDialog"]').click();
 assert(await photos.evaluate(el=>el.matches(':modal')),'Photos button did not open the form');
 await photos.locator('[data-room-close]').first().click();
 assert(!await photos.evaluate(el=>el.open),'Photos close did not work');
 await page.setViewportSize({width:390,height:750});
 await page.locator('[data-room-open="roomEditDialog"]').click();
 const mobile=await edit.boundingBox();
 assert(mobile.x>=0&&mobile.x+mobile.width<=391,'Mobile edit dialog horizontally outside viewport');
 assert(mobile.y>=0&&mobile.y+mobile.height<=751,'Mobile edit dialog vertically outside viewport');
 assert.deepEqual(errors,[],'JavaScript browser errors');
 console.log('ROOM_EDIT_REAL_FLOW_OK: list Edit opens form without JS, JS promotes to centered modal, toolbar Edit/Photos open, cancel/Escape, mobile and no runtime errors');
} finally {await browser.close();}
