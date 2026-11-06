import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {panelNaturalVoiceWav} from './central/natural-voice.mjs';
import {createCommunicationServer} from './central/server.mjs';
const profile={id:'suites-conversational-v1',tone:'natural',pace:'equilibrado'};
const originalFetch=globalThis.fetch,originalWs=globalThis.WebSocket;
let requests=[];const wav=Buffer.alloc(100);wav.write('RIFF');wav.write('WAVE',8);
try{
 globalThis.fetch=async(url,options)=>{requests.push({url,body:JSON.parse(options.body)});return{ok:true,json:async()=>({output_audio:{data:wav.toString('base64')}})};};
 const output=await panelNaturalVoiceWav({apiKey:'fixture',text:'Valor de teste: R$ 1.234,56. Dia 12/12, às 14h.',voice:'Callirrhoe',profile});
 assert.equal(output.voiceEngine,'gemini-3.8-flash-tts');assert.equal(requests.length,1);
 const body=requests[0].body;assert.equal(body.generation_config.speech_config[0].voice,'Callirrhoe');
 assert(body.input[0].content[0].annotations[0].style.includes('connected phrases'));
 assert(body.input[0].content[0].text.includes('R$ 1.234,56'));
 let wires=[];
 globalThis.fetch=async()=>({ok:false,status:429,json:async()=>({})});
 globalThis.WebSocket=class{constructor(){setTimeout(()=>this.onopen(),0);}send(raw){const m=JSON.parse(raw);wires.push(m);if(m.setup)setTimeout(()=>this.onmessage({data:JSON.stringify({setupComplete:{}})}),0);else setTimeout(()=>this.onmessage({data:JSON.stringify({serverContent:{modelTurn:{parts:[{inlineData:{mimeType:'audio/pcm;rate=24000',data:Buffer.alloc(100).toString('base64')}}]},turnComplete:true}})}),0);}close(){}};
 const fallback=await panelNaturalVoiceWav({apiKey:'fixture',text:'Teste R$ 1.234,56 às 14h.',voice:'Callirrhoe',profile});
 assert.equal(fallback.voiceEngine,'gemini-3.8-live');
 assert(wires[0].setup.systemInstruction.parts[0].text.includes('somente o texto'));
 assert(wires[1].realtimeInput.text.includes('Teste R$ 1.234,56 às 14h.'));
}finally{globalThis.fetch=originalFetch;globalThis.WebSocket=originalWs;}
let passed=[];
const token='a'.repeat(40),hash=createHash('sha256').update(token).digest('hex');
const server=createCommunicationServer({projects:{'suites-crepusculo':{token_sha256:hash,voice:'Callirrhoe'},crm:{token_sha256:hash,voice:'ExistingVoice'}},synthesize:async opts=>{passed.push(opts);return wav;}});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
try{
 const base='http://127.0.0.1:'+server.address().port+'/communication/v1/projects/';
 const request=async(project,p,authorization='Bearer '+token)=>fetch(base+project+'/voice/speech',{method:'POST',headers:{'content-type':'application/json',authorization},body:JSON.stringify({text:'Teste seguro.',provider_api_key:'fixture',voice_profile:p})});
 assert.equal((await request('suites-crepusculo',profile)).status,200);assert.deepEqual(passed[0].profile,profile);
 assert.equal((await request('crm',profile)).status,200);assert(!('profile' in passed[1]));assert.equal(passed[1].voice,'ExistingVoice');
 assert.equal((await request('suites-crepusculo',{...profile,tone:'injected'})).status,400);
 assert.equal((await request('suites-crepusculo',profile,'Bearer invalid')).status,401);
}finally{await new Promise(r=>server.close(r));}
console.log('VOICE_PROFILE_CONTRACT_OK: fixed identity; natural style; exact facts; fallback; project isolation; invalid profile; auth');
