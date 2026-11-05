import assert from 'node:assert/strict';
import {chromium} from '/var/tmp/gerente-crm-ui-check/node_modules/playwright/index.mjs';
const session=JSON.parse(process.env.SUITES_QA_SESSION),origin='https://suitescrepusculo.com.br';
const browser=await chromium.launch({headless:true,args:['--no-proxy-server']});
try{
 const context=await browser.newContext();await context.addCookies([{name:session.name,value:session.id,domain:'suitescrepusculo.com.br',path:'/',secure:true,httpOnly:true}]);
 // The real test uses only a public synthetic phrase instead of the user's greeting.
 await context.route('**/chat.php?action=panel_voice',route=>route.request().method()==='GET'?route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,configured:false,greeting:'Teste de voz.'})}):route.continue());
 await context.addInitScript(()=>{const NativeAudio=window.Audio;window.Audio=function(...args){const a=new NativeAudio(...args);window.__testAudio=a;return a;};window.Audio.prototype=NativeAudio.prototype;});
 const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(origin+'/index.php?p=dashboard',{waitUntil:'networkidle'});
 await page.locator('#suitesVoiceToggle').click();await page.locator('#suitesPanelVoice').waitFor();
 const wire=page.waitForResponse(r=>r.url().includes('chat.php?action=panel_voice')&&r.request().method()==='POST',{timeout:95000});
 await page.locator('[data-voice-test]').click();const response=await wire;assert.equal(response.status(),200);
 assert(response.headers()['content-type'].includes('audio/wav'));const bytes=await response.body();
 console.log('VOICE_WIRE='+JSON.stringify({bytes:bytes.length,length:response.headers()['content-length'],magic:bytes.subarray(0,12).toString('hex'),sampleRate:bytes.length>28?bytes.readUInt32LE(24):null}));assert(bytes.length>10000);assert.equal(bytes.toString('ascii',0,4),'RIFF');assert.equal(bytes.toString('ascii',8,12),'WAVE');
 assert(bytes.subarray(44).some(x=>x!==0));
 await page.waitForFunction(()=>window.__testAudio&&window.__testAudio.currentTime>0.05,{},{timeout:12000});
 await page.locator('[data-voice-stop]').click();
 console.log('NATURAL_VOICE_REAL_HTTP=200; WAV_BYTES='+bytes.length+'; AUDIO_PLAYBACK_STARTED=1');
 const csrf=await page.locator('#suitesPanelVoice [name=_csrf]').inputValue();
 const ai=await context.request.post(origin+'/chat.php?action=panel_voice',{form:{_csrf:csrf,kind:'answer',text:'Qual é o horário de check-in?'}});
 assert.equal(ai.status(),200);const answer=await ai.json();assert(answer.ok&&answer.answer.length>5);
 console.log('EXISTING_SITE_AI_RESPONSE_OK=1; ANSWER_CHARACTERS='+answer.answer.length);
 const bad=await context.request.post(origin+'/chat.php?action=panel_voice',{form:{_csrf:'invalid',kind:'speech',text:'Teste'}});assert.equal(bad.status(),419);
 const anonymous=await browser.newContext();const denied=await anonymous.request.get(origin+'/chat.php?action=panel_voice');assert.equal(denied.status(),401);await anonymous.close();
 await page.setViewportSize({width:390,height:844});const box=await page.locator('#suitesPanelVoice').boundingBox();assert(box.x>=0&&box.x+box.width<=391);
 assert.deepEqual(errors,[]);console.log('VOICE_AUTH_401=1; VOICE_CSRF_419=1; MOBILE_PANEL_OK=1; PAGE_ERRORS=0');
 await context.close();
}finally{await browser.close();}
