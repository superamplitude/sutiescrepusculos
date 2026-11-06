import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const script=fs.readFileSync(process.env.SUITES_PANEL_JS,'utf8');
const css=fs.readFileSync(process.env.SUITES_PANEL_CSS,'utf8');
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const page=await browser.newPage({viewport:{width:1208,height:730}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://suites-panel-qa.test/',route=>route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><html><body></body></html>'}));
 await page.goto('https://suites-panel-qa.test/');
 const fixture='<style>'+css+'</style><button id="suitesVoiceToggle" class="suites-voice-toggle">Falar com IA</button>'
 +'<section id="suitesPanelVoice" class="suites-voice-panel" data-greeting="qa-recepcao" hidden>'
 +'<header><span class="suites-voice-avatar">✦</span><span class="suites-voice-head-copy"><strong>Assistente IA</strong><small>Atendimento por chat e voz</small></span><button data-voice-close>×</button></header>'
 +'<input name="_csrf" value="fixture"><p data-voice-status></p><button data-voice-greeting hidden>Ouvir recepção</button>'
 +'<div data-voice-answer class="suites-voice-answer"></div><div data-agent-confirm-box hidden><p data-agent-confirm-text></p><button data-agent-confirm>Confirmar</button><button data-agent-cancel>Cancelar</button></div>'
 +'<textarea></textarea><div><button data-voice-send>Enviar</button><button data-voice-mic>Microfone</button><button data-voice-repeat>Repetir</button><button data-voice-stop>Parar</button><button data-voice-test>Testar voz</button></div></section>';
 await page.setContent(fixture);
 await page.evaluate(()=>{
  window.__plays=0;window.__calls=[];window.__denied=0;
  window.Audio=class{
   constructor(){this.currentTime=0;}
   addEventListener(){}
   pause(){}
   async play(){window.__plays++;if(window.__denied===0){window.__denied++;throw new DOMException('denied','NotAllowedError');}this.currentTime=1;}
  };
  window.fetch=async(url,opts={})=>{
   if(!opts.method)return new Response(JSON.stringify({ok:true,configured:true,greeting:'Olá, gestor. Como posso ajudar?'}),{status:200,headers:{'content-type':'application/json'}});
   const kind=opts.body.get('kind'),text=opts.body.get('text');window.__calls.push({kind,text});
   if(kind==='answer')return new Response(JSON.stringify({ok:true,answer:'Posso ajudar. Qual informação deseja atualizar?'}),{status:200,headers:{'content-type':'application/json'}});
   return new Response(new Blob(['RIFF',new Uint8Array(200)],{type:'audio/wav'}),{status:200,headers:{'content-type':'audio/wav'}});
  };
 });
 await page.addScriptTag({content:script});
 await page.waitForFunction(()=>window.__denied===1);
 assert.equal(await page.evaluate(()=>sessionStorage.getItem('suites.voice.greeting.qa-recepcao')),null,'blocked autoplay must not mark delivered');
 await page.locator('#suitesVoiceToggle').click();
 await page.waitForFunction(()=>sessionStorage.getItem('suites.voice.greeting.qa-recepcao')==='played');
 assert.equal(await page.locator('[data-voice-greeting]').isVisible(),false);
 assert((await page.locator('.suites-voice-panel').boundingBox()).height<470,'chat oversized');
 await page.locator('textarea').fill('Pode alterar uns detalhes?');
 await page.locator('[data-voice-send]').click();
 await page.waitForFunction(()=>document.querySelector('[data-voice-answer]').textContent.includes('Qual informação deseja atualizar?'));
 assert.equal(await page.locator('[data-voice-send]').isDisabled(),false);
 assert.equal(await page.evaluate(()=>window.__calls.filter(x=>x.kind==='answer').length),1);
 await page.setViewportSize({width:390,height:750});
 const rect=await page.locator('#suitesPanelVoice').boundingBox();
 assert(rect.x>=0&&rect.x+rect.width<=391,'panel outside viewport');
 assert.deepEqual(errors,[]);
 console.log('PANEL_CHAT_QA_OK: compact corner dock, blocked autoplay recovery, first gesture greeting, answer+voice controls, mobile width, zero JS errors');
}finally{await browser.close();}
