import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';

const css=fs.readFileSync(process.env.SUITES_HERO_CSS,'utf8');
const js=fs.readFileSync(process.env.SUITES_HERO_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
  const page=await browser.newPage({viewport:{width:1450,height:900}});
  const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('about:blank');
  const cards=Array.from({length:12},(_,i)=>
    '<article class="hero-media-tile"><button type="button" class="hero-media-open" data-hero-open="edit'+i+'"><span class="hero-media-preview"><img alt="Foto" src="data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22/%3E"></span><span class="hero-tile-caption">Foto '+i+'</span></button></article>'
    +'<dialog id="edit'+i+'" class="hero-admin-dialog"><div class="hero-dialog-head"><h2>Editar mídia</h2><button data-hero-close>×</button></div><form><input aria-label="Legenda" name="caption"><button>Salvar mídia</button></form></dialog>'
  ).join('');
  await page.setContent('<style>'+css+'</style><button data-hero-open="add">Adicionar mídia</button><dialog id="add" class="hero-admin-dialog"><h2>Adicionar mídias</h2><button data-hero-close>Fechar</button><form><input type="file" name="hero_images[]" multiple><button>Adicionar ao hero</button></form></dialog><div class="hero-admin-grid">'+cards+'</div>');
  await page.addScriptTag({content:js});
  assert.equal(await page.locator('.hero-media-tile').count(),12);
  const columns=await page.locator('.hero-admin-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length);
  assert.equal(columns,6,'desktop must have six thumbnails per line');
  await page.locator('[data-hero-open="add"]').click();
  assert(await page.locator('#add').evaluate(el=>el.open),'add dialog not opened');
  await page.locator('#add [data-hero-close]').click();
  assert(!await page.locator('#add').evaluate(el=>el.open),'add dialog not closed');
  await page.locator('[data-hero-open="edit3"]').click();
  assert(await page.locator('#edit3').evaluate(el=>el.open),'edit dialog not opened');
  await page.locator('#edit3 input[name="caption"]').fill('Atualização de legenda');
  assert.equal(await page.locator('#edit3 input[name="caption"]').inputValue(),'Atualização de legenda');
  await page.keyboard.press('Escape');
  assert(!await page.locator('#edit3').evaluate(el=>el.open),'Escape should close dialog');
  await page.setViewportSize({width:430,height:800});
  const smallColumns=await page.locator('.hero-admin-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length);
  assert.equal(smallColumns,2,'mobile grid should have two thumbnails per line');
  assert.deepEqual(errors,[]);
  console.log('HERO_UI_QA_OK: 6 desktop columns, 2 mobile columns, add/edit dialogs, close, Escape, input and no JS errors');
}finally{await browser.close();}
