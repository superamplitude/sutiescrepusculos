import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const code=fs.readFileSync(process.env.SUITES_VOICE_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1200,height:820}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 await page.setContent('<button id="suitesVoiceToggle" aria-expanded="false">Falar com IA</button><section id="suitesPanelVoice" hidden data-greeting="qa-greeting-retry-20261109"><input name="_csrf" value="qa"><p data-voice-status></p><button data-voice-greeting hidden>Ouvir recepção</button><div data-voice-answer></div><div data-agent-confirm-box hidden><span data-agent-confirm-text></span><button data-agent-confirm>Confirmar</button><button data-agent-cancel>Cancelar</button></div><textarea></textarea><button data-voice-send>Enviar</button><button data-voice-close>Fechar</button><button data-voice-repeat>Repetir</button><button data-voice-stop>Parar</button><button data-voice-test>Teste</button><button data-voice-mic>Microfone</button></section>');
 await page.evaluate(()=>{
  window.__plays=0;window.__posts=0;window.__gesture=false;
  document.addEventListener('click',()=>{window.__gesture=true;},true);
  window.Audio=class{
   constructor(src){this.src=src;this.handlers={};}
   addEventListener(name,fn){this.handlers[name]=fn;}
   pause(){}
   play(){window.__plays++;return window.__gesture?Promise.resolve():Promise.reject(Object.assign(new Error('autoplay denied'),{name:'NotAllowedError'}));}
  };
  window.fetch=async(_url,opt={})=>{
   if(opt.method==='POST'){
    window.__posts++;await new Promise(r=>setTimeout(r,400));
    return new Response(new Blob(['RIFF0000WAVE'],{type:'audio/wav'}),{headers:{'content-type':'audio/wav'}});
   }
   return new Response(JSON.stringify({ok:true,configured:true,greeting:'Olá. Bem-vindo ao painel.'}),{headers:{'content-type':'application/json'}});
  };
 });
 await page.addScriptTag({content:code});
 await page.waitForFunction(()=>window.__posts===1);
 await page.locator('#suitesVoiceToggle').click(); // user gesture while synthesis is in flight
 await page.waitForFunction(()=>document.querySelector('[data-voice-status]')?.textContent?.includes('Preparando'));
 await page.waitForFunction(()=>document.querySelector('#suitesVoiceToggle')?.classList.contains('needs-voice-gesture'));
 assert(await page.locator('[data-voice-greeting]').isVisible());
 await page.locator('[data-voice-greeting]').click();
 await page.waitForFunction(()=>document.querySelector('#suitesVoiceToggle')?.textContent?.includes('Falar com IA'));
 assert.equal(await page.evaluate(()=>window.__posts),1,'Do not request the greeting a second time');
 assert.equal(await page.evaluate(()=>window.__plays),2,'Retry the prepared audio on a gesture');
 assert.deepEqual(errors,[]);
 console.log('GREETING_DURING_SYNTHESIS_QA_OK: one TTS call, visible recovery, autoplay retry after ready');
}finally{await browser.close();}
