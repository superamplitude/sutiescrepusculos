import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const css=fs.readFileSync(process.env.SUITES_ROOMS_CSS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1300,height:800}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 const html='<style>body{margin:24px;font:14px Arial}.btn,button{min-height:40px;padding:8px 12px;border-radius:8px;white-space:nowrap}form{margin:8px 0}</style><style>'+css+'</style>'
 +'<div class="admin-mode"><div class="room-actions-toolbar">'
 +'<a class="btn room-toolbar-back" href="#">← Voltar às unidades</a>'
 +'<button class="btn">Editar unidade</button><button class="btn">Fotos da unidade</button>'
 +'<a class="btn room-section-button" href="#">Diária</a><a class="btn room-section-button" href="#">Pacote</a>'
 +'<form class="room-toolbar-delete"><button class="btn room-delete-button" type="button">Excluir unidade</button></form>'
 +'</div></div>';
 await page.setContent(html);
 const selectors='.room-actions-toolbar > a,.room-actions-toolbar > button,.room-actions-toolbar > form > button';
 const boxes=await page.locator(selectors).evaluateAll(els=>els.map(e=>{const r=e.getBoundingClientRect();return {text:e.textContent.trim(),y:r.y,height:r.height,x:r.x}}));
 assert.equal(boxes.length,6);
 assert(boxes.every(b=>Math.abs(b.y-boxes[0].y)<=1),'Buttons must all start on same line');
 assert(boxes.every(b=>Math.abs(b.height-boxes[0].height)<=1),'Buttons must have same height');
 const desktop=await page.locator('.room-actions-toolbar').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,wrap:getComputedStyle(el).flexWrap}));
 assert.equal(desktop.wrap,'nowrap');
 assert(desktop.scroll<=desktop.width+2,'desktop toolbar should fit without scrolling');
 await page.setViewportSize({width:390,height:780});
 const mobile=await page.locator('.room-actions-toolbar').evaluate(el=>({width:el.clientWidth,scroll:el.scrollWidth,wrap:getComputedStyle(el).flexWrap}));
 assert.equal(mobile.wrap,'nowrap');
 assert(mobile.scroll>mobile.width,'small screens keep one row with horizontal scroll');
 assert.deepEqual(errors,[]);
 console.log('ROOM_TOOLBAR_ONE_ROW_OK: six actions aligned on desktop and scrollable in one row on mobile, equal heights, zero JS errors');
}finally{await browser.close();}
