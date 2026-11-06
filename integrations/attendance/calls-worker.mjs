// Glue only: uses copies of the verified CRM voice modules and the existing local gateway.
import {spawn} from 'node:child_process';

import {readFile,writeFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';

export class SuitesCallAttendance{
 constructor({client,makeBridge,context,report=()=>{}}){Object.assign(this,{client,makeBridge,context,report});this.active=new Map();this.sessionId='';}
 async refresh(){
  const rows=await this.client.listSessions(),own=rows.find(x=>x.name==='crepusculo_company_1');
  this.sessionId=String(own?.id||own?.sessionId||'');
  this.report({gateway:true,paired:Boolean(own?.paired),state:String(own?.state||'missing'),active_calls:this.active.size});
  return own;
 }
 async event(event){
  const sid=String(event?.sessionId||event?.session_id||''),type=String(event?.type||'');
  if(type==='session-list'||type==='auth-state'){await this.refresh();return;}
  if(!sid||sid!==this.sessionId)return;
  const id=String(event.id||event.callId||'');
  if(!id)return;
  if(type==='call-ended'){
   const call=this.active.get(id);this.active.delete(id);if(call){call.cancelled=true;await call.bridge?.stop('ended');}
   return;
  }
  if(type!=='incoming'||this.active.has(id)||this.active.size>=1)return;
  const own=await this.refresh();
  if(!own?.paired||String(own.id||own.sessionId)!==sid)return;
  const state={cancelled:false,bridge:null};this.active.set(id,state);
  void this.answer(event,state).catch(()=>this.report({last_error:'CALL_PREPARATION_FAILED'})).finally(()=>{if(state.cancelled)this.active.delete(id);});
 }
 async answer(event,state){
  const sid=String(event.sessionId||event.session_id),id=String(event.id||event.callId);
  try{
   const ctx=await this.context('context',{peer:event.peer||''});
   if(!ctx.enabled||!ctx.api_key||state.cancelled){state.cancelled=true;return;}
   const guarded=Object.create(this.client);
   guarded.acceptCall=async(...args)=>{if(state.cancelled||!this.active.has(id))throw Error('CALL_ALREADY_ENDED');return this.client.acceptCall(...args);};
   const bridge=this.makeBridge({wacalls:guarded,apiKey:ctx.api_key,systemPrompt:ctx.prompt,
    greeting:'Cumprimente o cliente dizendo: Olá, aqui é a assistente das Suítes Crepúsculo. Como posso ajudar? Aguarde a resposta.',
    model:'gemini-3.8-live',geminiVoice:'Callirrhoe',ttsProvider:'gemini',
    tools:[{name:'consultar_disponibilidade',description:'Consultar disponibilidade real e valores no mapa da pousada. Informe datas e hóspedes.',parameters:{type:'OBJECT',properties:{pergunta:{type:'STRING'}},required:['pergunta']}}],
    toolExecutor:async(name,args)=>{if(name!=='consultar_disponibilidade')throw Error('UNKNOWN_TOOL');return this.context('availability',{question:String(args.pergunta||'')});},
    logger:{info:()=>{},warn:()=>{},error:()=>{}}
   });state.bridge=bridge;
   if(state.cancelled)return;
   await bridge.start({sessionId:sid,callId:id,clientId:'suites_ai_1'});
   if(state.cancelled){await bridge.stop('ended');return;}
   this.report({last_call_answered:true,active_calls:this.active.size});
  }catch(error){
   if(state.bridge?.accepted)await this.client.endCall(sid,id).catch(()=>{});
   await state.bridge?.stop('preparation_error');state.cancelled=true;
   throw error;
  }finally{if(state.cancelled)this.active.delete(id);}
 }
 async stop(){for(const call of this.active.values()){call.cancelled=true;await call.bridge?.stop('service_stop');}this.active.clear();}
}
async function main(){
 const {WacallsClient}=await import('./lib/wacalls.mjs');
 const {WhatsappVoiceAiBridge}=await import('./lib/voice-ai-live.mjs');
 const raw=await readFile('/etc/crepusculo/wacalls.env','utf8'),env=Object.fromEntries(raw.split(/\r?\n/).filter(l=>l.includes('=')&&!l.startsWith('#')).map(l=>{const i=l.indexOf('=');return[l.slice(0,i),l.slice(i+1).trim()];}));
 if(env.WACALLS_API_BASE_URL!=='http://127.0.0.1:18180')throw Error('UNEXPECTED_GATEWAY');
 const client=new WacallsClient({baseUrl:env.WACALLS_API_BASE_URL,apiToken:env.WACALLS_API_TOKEN});
 let status={project:'suites-crepusculo',gateway:false,paired:false,active_calls:0};
 const report=patch=>{status={...status,...patch,updated_at:new Date().toISOString()};void writeFile('/run/suites-attendance/status.json',JSON.stringify(status),{mode:0o640}).catch(()=>{});};
 const context=(action,data)=>new Promise((resolve,reject)=>{
  const child=spawn('/usr/bin/php',[new URL('./context.php',import.meta.url).pathname,action],{stdio:['pipe','pipe','pipe']});
  const chunks=[];let n=0;const timer=setTimeout(()=>child.kill(),100000);child.stderr.resume();child.stdin.on('error',()=>{});
  child.stdout.on('data',b=>{n+=b.length;if(n>8*1024*1024)child.kill();else chunks.push(b);});
  child.on('error',reject);child.on('close',code=>{clearTimeout(timer);try{if(code!==0)throw Error('LOCAL_CONTEXT_FAILED');resolve(JSON.parse(Buffer.concat(chunks)));}catch(e){reject(e);}});
  child.stdin.end(JSON.stringify(data));
 });
 const {startMessagesServer}=await import('./messages-server.mjs');
 const messagesServer=await startMessagesServer(context);
 const attendance=new SuitesCallAttendance({client,context,makeBridge:opts=>new WhatsappVoiceAiBridge(opts),report});
 const controller=new AbortController();let quitting=false;
 const shutdown=async()=>{quitting=true;controller.abort();await attendance.stop();messagesServer.close();};
 process.once('SIGTERM',shutdown);process.once('SIGINT',shutdown);
 while(!quitting){
  try{await attendance.refresh();await client.eventStream(event=>attendance.event(event),{signal:controller.signal});}
  catch{if(!quitting)report({gateway:false,last_error:'GATEWAY_STREAM_UNAVAILABLE'});}
  if(!quitting)await new Promise(r=>setTimeout(r,3000));
 }
}
if(process.argv[1]&&import.meta.url===pathToFileURL(process.argv[1]).href)await main();
