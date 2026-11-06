import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_GALLERY_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_GALLERY_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1300,height:820}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 const base='index.php?p=gallery';
 const editable='<div class="admin-mode"><div class="gallery-toolbar"><a class="btn" href="'+base+'&form=add" data-gallery-open="galleryAddDialog">+ Adicionar mídia</a></div>'
 +'<dialog id="galleryAddDialog" class="gallery-admin-dialog"><div class="gallery-dialog-heading"><h2>Adicionar mídia</h2><a data-gallery-close href="'+base+'">×</a></div><form><input type="file" name="media_file"><button>Adicionar mídia</button></form></dialog>'
 +'<div class="gallery"><figure class="gallery-edit-tile"><a class="gallery-photo-open" href="'+base+'&form=edit&id=42" data-gallery-open="galleryEditDialog42" aria-label="Editar Praia">'
 +'<img alt="Praia" src="data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22/%3E"></a>'
 +'<figcaption>Praia<form><button type="button" class="danger small">Remover</button></form></figcaption></figure></div>'
 +'<dialog id="galleryEditDialog42" class="gallery-admin-dialog gallery-edit-dialog" aria-labelledby="editTitle">'
 +'<div class="gallery-dialog-heading"><h2 id="editTitle">Editar mídia</h2><a data-gallery-close href="'+base+'">×</a></div>'
 +'<div class="gallery-edit-preview"><img alt="Praia" src="data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22/%3E"></div>'
 +'<form class="gallery-dialog-form"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="42">'
 +'<div class="gallery-form-grid"><label>Substituir foto<input name="media_file" type="file"></label><label>Legenda<input name="caption" value="Praia"></label>'
 +'<label>Texto alternativo<input name="alt_text" value="Praia do Jabaquara"></label><label>Ordem<input name="sort_order" value="2" type="number"></label></div>'
 +'<div class="gallery-dialog-actions"><a data-gallery-close href="'+base+'">Cancelar</a><button type="submit">Salvar alterações</button></div></form></dialog></div>';
 await page.setContent('<style>body{font:13px Arial;margin:20px}.gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.gallery img{width:100%;height:150px}</style><style>'+css+'</style>'+editable);
 assert(!(await page.locator('#galleryEditDialog42').isVisible()),'edit hidden until photo click');
 assert(!(await page.locator('#galleryAddDialog').isVisible()),'add form hidden until click');
 await page.addScriptTag({content:js});
 await page.locator('.gallery-photo-open').click();
 const dlg=page.locator('#galleryEditDialog42');
 assert(await dlg.evaluate(el=>el.matches(':modal')),'click photo must open edit modal');
 assert.equal(await dlg.locator('input[name="caption"]').inputValue(),'Praia');
 assert.equal(await dlg.locator('input[name="alt_text"]').inputValue(),'Praia do Jabaquara');
 assert.equal(await dlg.locator('input[name="sort_order"]').inputValue(),'2');
 await dlg.locator('input[name="caption"]').fill('Foto revisada');
 assert.equal(await dlg.locator('input[name="caption"]').inputValue(),'Foto revisada');
 const bound=await dlg.boundingBox();
 assert(Math.abs(bound.x+bound.width/2-650)<3,'edit modal not centered horizontally');
 await page.keyboard.press('Escape');
 assert(!await dlg.evaluate(el=>el.open),'Escape must close modal');
 await page.locator('[data-gallery-open="galleryAddDialog"]').click();
 assert(await page.locator('#galleryAddDialog').evaluate(el=>el.matches(':modal')),'add button must still open add form');
 await page.locator('#galleryAddDialog [data-gallery-close]').click();
 assert(!(await page.locator('#galleryAddDialog').isVisible()));
 assert.equal(await page.locator('.gallery figure').count(),1,'gallery remains present');
 await page.setViewportSize({width:390,height:720});
 await page.locator('.gallery-photo-open').click();
 const b=await dlg.boundingBox();
 assert(b.x>=0&&b.x+b.width<=391,'mobile modal overflows width');
 assert(b.y>=0&&b.y+b.height<=721,'mobile modal overflows height');
 assert.deepEqual(errors,[]);
 console.log('GALLERY_CLICK_EDIT_OK: add overlay retained, photo click opens image/text edit, fields preserved, centered mobile/desktop and Escape');
}finally{await browser.close();}
