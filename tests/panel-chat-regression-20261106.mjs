import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const source=fs.readFileSync(process.env.SUITES_CHAT_SCRIPT,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
const page=await browser.newPage(),errors=[];
page.on('pageerror',e=>errors.push(e.message));
await page.setContent('<button id="suitesVoiceToggle"></button><section id="suitesPanelVoice" data-greeting="regression" hidden><input name="_csrf" value="fixture"><p data-voice-status></p><div data-voice-answer></div><textarea></textarea><button data-voice-close></button><button data-voice-send>Enviar</button><button data-voice-repeat></button><button data-voice-stop></button><button data-voice-test></button><button data-voice-mic></button></section>');
await page.evaluate(()=>{
 window.__requests=[];window.__pending=[];window.__audioPlays=0;
 window.fetch=async(url,opts={})=>{
  if(!opts.method)return new Response(JSON.stringify({ok:true,configured:true,greeting:'Olá, teste.'}),{headers:{'content-type':'application/json'}});
  const kind=opts.body.get('kind'),text=opts.body.get('text');window.__requests.push({kind,text});
  if(kind==='answer')return new Response(JSON.stringify({ok:true,answer:'Resposta de teste.'}),{headers:{'content-type':'application/json'}});
  return new Promise((resolve,reject)=>{const pending={aborted:false,resolve:()=>resolve(new Response(new Blob(['fixture audio']),{headers:{'content-type':'audio/wav'}}))};window.__pending.push(pending);opts.signal?.addEventListener('abort',()=>{pending.aborted=true;reject(new DOMException('aborted','AbortError'));});});
 };
 window.Audio=class{addEventListener(){} pause(){} async play(){window.__audioPlays++;}};
});
await page.addScriptTag({content:source});
await page.waitForFunction(()=>window.__pending.length===1);
await page.locator('#suitesVoiceToggle').click();
await page.locator('textarea').fill('Pergunta durante saudação.');
await page.locator('[data-voice-send]').click();
await page.waitForFunction(()=>window.__requests.some(x=>x.kind==='answer'),{},{timeout:3000});
assert(await page.evaluate(()=>window.__pending[0].aborted),'greeting must be cancelled');
await page.waitForFunction(()=>document.querySelector('[data-voice-answer]').textContent.includes('Assistente: Resposta de teste.'));
assert.equal(await page.locator('[data-voice-send]').isDisabled(),false);
await page.waitForFunction(()=>window.__pending.length===2);
await page.locator('[data-voice-stop]').click();
assert(await page.evaluate(()=>window.__pending[1].aborted),'stop cancels pending audio');
await page.evaluate(()=>window.__pending[1].resolve());
assert.equal(await page.evaluate(()=>window.__audioPlays),0,'cancelled response must not play');
await page.locator('textarea').fill('Segunda pergunta.');
await page.locator('textarea').press('Enter');
await page.waitForFunction(()=>window.__requests.filter(x=>x.kind==='answer').length===2);
await page.locator('[data-voice-close]').click();
assert.equal(await page.locator('#suitesPanelVoice').isVisible(),false);
assert.deepEqual(errors,[]);
console.log('CHAT_REGRESSION_OK: question during pending greeting; abort pending audio; no stale playback; second question; Enter; close; zero JS errors');
const unauth=await page.request.get('https://suitescrepusculo.com.br/chat.php?action=panel_voice');
assert.equal(unauth.status(),401);console.log('ANONYMOUS_AUTH_401_OK');
}finally{await browser.close();}
