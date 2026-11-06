import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_ROOMS_CSS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
  const page=await browser.newPage({viewport:{width:1280,height:800}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const amenity=(name,id,checked)=>'<label class="room-amenity-toggle"><span class="room-amenity-name">'+name+'</span>'
    +'<span class="room-amenity-control"><input type="checkbox" name="amenities[]" value="'+id+'" '+(checked?'checked':'')+'>'
    +'<span class="room-amenity-switch" aria-hidden="true"></span><span class="room-amenity-state" aria-hidden="true"></span></span></label>';
  await page.setContent('<style>body{margin:20px;font:14px Arial}</style><style>'+css+'</style>'
    +'<div class="admin-mode"><form class="room-edit-form" id="roomForm"><h3>Comodidades</h3>'
    +'<div class="room-amenities"><section class="room-amenities-group"><h4>Conforto</h4><div class="room-amenities-grid">'
    +amenity('Frigobar',31,true)+amenity('Wi-Fi',32,false)+amenity('Netflix / streaming',33,true)
    +'</div></section></div><button type="submit">Salvar alterações</button></form></div>');
  const controls=page.locator('input[name="amenities[]"]');
  assert.equal(await controls.count(),3);
  assert.deepEqual(await page.locator('#roomForm').evaluate(f=>new FormData(f).getAll('amenities[]')),['31','33'],'current selected amenities not preserved');
  const state=await page.locator('.room-amenity-state').first().evaluate(el=>getComputedStyle(el,'::after').content);
  assert.equal(state,'"Ligado"','checked switch must show Ligado');
  const state2=await page.locator('.room-amenity-state').nth(1).evaluate(el=>getComputedStyle(el,'::after').content);
  assert.equal(state2,'"Desligado"','unchecked switch must show Desligado');
  await page.locator('.room-amenity-toggle').first().click();
  await page.locator('.room-amenity-toggle').nth(1).click();
  assert.deepEqual(await page.locator('#roomForm').evaluate(f=>new FormData(f).getAll('amenities[]')),['32','33'],'switch must preserve original PHP POST values');
  await page.locator('input[value="33"]').focus();
  await page.keyboard.press('Space');
  assert.deepEqual(await page.locator('#roomForm').evaluate(f=>new FormData(f).getAll('amenities[]')),['32'],'keyboard toggle should update POST');
  const colors=await page.locator('.room-amenity-switch').evaluateAll(els=>els.map(el=>getComputedStyle(el).backgroundColor));
  assert.notEqual(colors[0],colors[1],'on/off colors must be distinct');
  await page.setViewportSize({width:390,height:750});
  const cols=await page.locator('.room-amenities-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.split(' ').length);
  assert.equal(cols,1,'mobile amenities should be one column');
  assert.deepEqual(errors,[],'no JS errors');
  console.log('ROOM_AMENITIES_SWITCH_QA_OK: existing selections, Ligado/Desligado, form POST, mouse/keyboard, visual colors and mobile');
}finally{await browser.close();}
