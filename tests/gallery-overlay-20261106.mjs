import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_GALLERY_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_GALLERY_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try {
 const page=await browser.newPage({viewport:{width:1320,height:830}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 const base='<style>body{margin:20px;font:14px Arial}.gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}button,.btn{padding:10px}</style><style>'+css+'</style>'
 +'<div class="admin-mode"><div class="gallery-toolbar"><a class="btn" data-gallery-open="galleryAddDialog" href="?p=gallery&form=add">+ Adicionar mídia</a></div>'
 +'<dialog id="galleryAddDialog" class="gallery-admin-dialog"><div class="gallery-dialog-heading"><h2 id="galleryAddTitle">Adicionar mídia</h2><a data-gallery-close href="?p=gallery">×</a></div>'
 +'<form class="gallery-dialog-form"><div class="gallery-form-grid"><label>Onde usar<select name="scope"><option>Pousada</option><option>Quarto</option></select></label>'
 +'<label>Upload<input type="file" name="media_file"></label><label>Legenda<input name="caption"></label></div><div class="gallery-dialog-actions">'
 +'<a data-gallery-close href="?p=gallery">Cancelar</a><button type="submit">Adicionar mídia</button></div></form></dialog>'
 +'<div class="gallery"><figure>Foto existente 1</figure><figure>Foto existente 2</figure></div></div>';
 await page.setContent(base);
 assert.equal(await page.locator('dialog').isVisible(),false,'form should be hidden initially');
 assert.equal(await page.locator('.gallery figure').count(),2,'gallery must remain visible');
 await page.addScriptTag({content:js});
 await page.locator('[data-gallery-open]').click();
 const dlg=page.locator('#galleryAddDialog');
 assert(await dlg.evaluate(el=>el.matches(':modal')),'add form must open as overlay');
 let r=await dlg.boundingBox();
 assert(Math.abs((r.x+r.width/2)-660)<4,'not horizontally centered');
 assert(Math.abs((r.y+r.height/2)-415)<4,'not vertically centered');
 assert(r.width<=555,'dialog too wide');
 await page.locator('input[name="caption"]').fill('Minha imagem');
 assert.equal(await page.locator('input[name="caption"]').inputValue(),'Minha imagem');
 await page.keyboard.press('Escape');
 assert(!await dlg.evaluate(el=>el.open),'Escape must close modal');
 await page.locator('[data-gallery-open]').click();
 await page.locator('[data-gallery-close]').first().click();
 assert(!await dlg.evaluate(el=>el.open),'close control must work');
 await page.setViewportSize({width:390,height:750});
 await page.locator('[data-gallery-open]').click();
 r=await dlg.boundingBox();
 assert(r.x>=0&&r.x+r.width<=391,'mobile horizontal overflow');
 assert(r.y>=0&&r.y+r.height<=751,'mobile vertical overflow');
 assert.deepEqual(errors,[]);
 console.log('GALLERY_MODAL_QA_OK: form hidden until click, existing media grid present, centered compact modal, Escape/close, mobile fit, no errors');
}finally{await browser.close();}
