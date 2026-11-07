import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const code=fs.readFileSync(process.env.SUITES_VOICE_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1300,height:850}});
 const pageErrors=[];page.on('pageerror',e=>pageErrors.push(e.message));
 await page.goto('about:blank');
 const form='<button id="suitesVoiceToggle" aria-expanded="false">💬 Falar com IA</button>'
  +'<section id="suitesPanelVoice" hidden data-greeting="qa-login-20261107">'
  +'<input type="hidden" name="_csrf" value="qa-csrf">'
  +'<p data-voice-status></p><button data-voice-greeting hidden>Ouvir recepção</button>'
  +'<div data-voice-answer></div>'
  +'<div data-agent-confirm-box hidden><span data-agent-confirm-text></span><button data-agent-confirm>Confirmar</button><button data-agent-cancel>Cancelar</button></div>'
  +'<textarea></textarea><button data-voice-send>Enviar</button><button data-voice-close>Fechar</button>'
  +'<button data-voice-repeat>Repetir</button><button data-voice-stop>Parar</button><button data-voice-test>Teste</button>'
  +'<button data-voice-mic>Microfone</button></section>';
 await page.setContent('<body>'+form+'</body>');
 await page.evaluate(()=>{
  window.__audioPlays=0;window.__userGesture=false;window.__apiCalls=[];
  document.addEventListener('click',()=>{window.__userGesture=true;},{capture:true});
  window.Audio=class{
   constructor(src){this.src=src;}
   addEventListener(){}
   pause(){}
   play(){
    window.__audioPlays++;
    return window.__userGesture?Promise.resolve():Promise.reject(Object.assign(new Error('Autoplay blocked'),{name:'NotAllowedError'}));
   }
  };
  window.fetch=async(url,options={})=>{
   window.__apiCalls.push({url:String(url),method:options.method||'GET'});
   if(options.method==='POST'){
    return new Response(new Blob(['RIFF0000WAVE'],{type:'audio/wav'}),{status:200,headers:{'content-type':'audio/wav'}});
   }
   return new Response(JSON.stringify({ok:true,configured:true,greeting:'Olá. Bem-vindo ao painel.'}),{status:200,headers:{'content-type':'application/json'}});
  };
 });
 await page.addScriptTag({content:code});
 const toggle=page.locator('#suitesVoiceToggle'),panel=page.locator('#suitesPanelVoice');
 await page.waitForFunction(()=>document.querySelector('#suitesVoiceToggle')?.classList.contains('needs-voice-gesture'),null,{timeout:9000});
 assert(await panel.isVisible(),'Welcome recovery must become visible, never remain inside a hidden chat');
 assert(await page.locator('[data-voice-greeting]').isVisible(),'Welcome button must be visible after autoplay rejection');
 assert((await toggle.innerText()).includes('Ouvir recepção'),'Launcher must explain the required action');
 assert.equal(await page.evaluate(()=>window.__audioPlays),1,'Automatic playback must be attempted once');
 await toggle.click();
 await page.waitForFunction(()=>document.querySelector('#suitesVoiceToggle')?.textContent?.includes('Falar com IA'));
 assert.equal(await page.evaluate(()=>window.__audioPlays),2,'User gesture should retry the same audio once');
 assert.equal(await panel.isVisible(),true,'Chat stays open while welcome plays');
 assert.equal(await page.locator('[data-voice-greeting]').isVisible(),false,'Manual prompt hides after playback starts');
 assert.deepEqual(await page.evaluate(()=>window.__apiCalls.map(x=>x.url)),['/chat.php?action=panel_voice','/chat.php?action=panel_voice']);
 assert.deepEqual(pageErrors,[]);
 console.log('VOICE_BROWSER_RECOVERY_QA_OK: blocked autoplay displays visible prompt, launcher retries on gesture, same natural audio endpoint, no JS errors');
}finally{await browser.close();}
