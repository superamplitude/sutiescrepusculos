import http from 'node:http';
export function personalIncoming(msg){
 const jid=String(msg?.key?.remoteJid||'');
 return !msg?.key?.fromMe&&/^[0-9]+@(s\.whatsapp\.net|lid)$/.test(jid)&&Boolean(msg?.key?.id);
}
function unpack(message){let m=message||{};for(let i=0;i<4;i++){const nested=m.ephemeralMessage?.message||m.viewOnceMessage?.message||m.viewOnceMessageV2?.message;if(!nested)break;m=nested;}return m;}
function localRequest(body){
 const data=Buffer.from(JSON.stringify(body));
 return new Promise((resolve,reject)=>{
  const req=http.request({socketPath:'/run/suites-messages/messages.sock',path:'/message',method:'POST',headers:{'content-type':'application/json','content-length':data.length},timeout:110000},res=>{
   const chunks=[];let size=0;res.on('data',b=>{size+=b.length;if(size>8*1024*1024){res.destroy();reject(Error('RESPONSE_TOO_LARGE'));}else chunks.push(b);});
   res.on('end',()=>{try{const value=JSON.parse(Buffer.concat(chunks));if(res.statusCode!==200)throw Error('SUITES_MESSAGE_FAILED');resolve(value);}catch(e){reject(e);}});
  });req.on('timeout',()=>req.destroy(Error('SUITES_MESSAGE_TIMEOUT')));req.on('error',reject);req.end(data);
 });
}
export async function handleSuitesMessage(sock,msg,{request=localRequest,download=null}={}){
 if(!personalIncoming(msg))return {ignored:true};
 const m=unpack(msg.message),audio=m.audioMessage;
 let text=String(m.conversation||m.extendedTextMessage?.text||'').trim(),data={};
 if(audio){
  if(Number(audio.seconds||0)>180||Number(audio.fileLength||0)>5*1024*1024)return {ignored:true,reason:'audio_too_large'};
  const get=download||((await (await import('./suites-baileys-provider.mjs')).suitesMessagingProvider()).download);
  const logger={debug(){},info(){},warn(){},error(){},trace(){},child(){return this;}};
  const buffer=await get(msg,'buffer',{}, {logger,reuploadRequest:sock.updateMediaMessage});
  if(buffer.length>5*1024*1024)return {ignored:true,reason:'audio_too_large'};
  data={audio:buffer.toString('base64'),mime:String(audio.mimetype||'audio/ogg').split(';')[0]};
 }else if(!text)return {ignored:true};
 const result=await request({id:String(msg.key.id),jid:String(msg.key.remoteJid),name:String(msg.pushName||'Contato WhatsApp').slice(0,120),text:text.slice(0,2500),...data});
 if(result.skip||!result.answer)return {saved:true,replied:false};
 await sock.sendMessage(msg.key.remoteJid,{text:result.answer});
 if(result.audio)await sock.sendMessage(msg.key.remoteJid,{audio:Buffer.from(result.audio,'base64'),mimetype:'audio/ogg; codecs=opus',ptt:true});
 return {saved:true,replied:true,audio:Boolean(result.audio)};
}
