import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const code=fs.readFileSync(process.env.SUITES_VOICE_JS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('about:blank');
 await page.setContent('<button id="suitesVoiceToggle" aria-expanded="false">Falar com IA</button><section id="suitesPanelVoice" hidden data-greeting="qa-webaudio-on-login-20261109"><input name="_csrf" value="qa"><p data-voice-status></p><button data-voice-greeting hidden>Ouvir recepção</button><div data-voice-answer></div><div data-agent-confirm-box hidden><span data-agent-confirm-text></span><button data-agent-confirm>Confirmar</button><button data-agent-cancel>Cancelar</button></div><textarea></textarea><button data-voice-send>Enviar</button><button data-voice-close>Fechar</button><button data-voice-repeat>Repetir</button><button data-voice-stop>Parar</button><button data-voice-test>Teste</button><button data-voice-mic>Microfone</button></section>');
 await page.evaluate(()=>{
  window.__posts=0;window.__htmlPlays=0;window.__audioSourcePlays=0;
  window.Audio=class{
   constructor(src){this.src=src;}
   addEventListener(){}
   pause(){}
   play(){window.__htmlPlays++;return Promise.reject(Object.assign(new Error('Autoplay not permitted'),{name:'NotAllowedError'}));}
  };
  window.AudioContext=class{
   state='suspended';
   destination={};
   resume(){this.state='running';return Promise.resolve();}
   decodeAudioData(){return Promise.resolve({duration:2});}
   createBufferSource(){return {connect(){},addEventListener(){},start(){window.__audioSourcePlays++;},stop(){}};}
  };
  window.fetch=async(url,opt={})=>{
   if(opt.method==='POST'){window.__posts++;await new Promise(r=>setTimeout(r,500));return new Response(new Blob(['RIFF0000WAVE'],{type:'audio/wav'}),{headers:{'content-type':'audio/wav'}});}
   return new Response(JSON.stringify({ok:true,configured:true,greeting:'Olá. Bem-vindo ao painel.'}),{headers:{'content-type':'application/json'}});
  };
 });
 await page.addScriptTag({content:code});
 await page.waitForFunction(()=>window.__posts===1);
 await page.locator('#suitesVoiceToggle').click();
 await page.waitForFunction(()=>window.__audioSourcePlays===1,{timeout:5000});
 assert.equal(await page.evaluate(()=>window.__posts),1,'Single synthesis request on panel entry');
 assert.equal(await page.evaluate(()=>window.__audioSourcePlays),1,'Approved natural WAV starts after first interaction');
 assert.equal(await page.evaluate(()=>window.__htmlPlays),0,'No blocked autoplay fallback needed');
 assert.deepEqual(errors,[]);
 console.log('PANEL_ENTRY_NATURAL_VOICE_QA_OK: one synthesis request, first-gesture audio context, WAV starts without Test');
}finally{await browser.close();}
