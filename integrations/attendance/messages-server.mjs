import http from 'node:http';
import {chmod,unlink} from 'node:fs/promises';
import {spawn} from 'node:child_process';
export async function opus(wav){
 return new Promise((resolve,reject)=>{
  const child=spawn('/usr/bin/ffmpeg',['-hide_banner','-loglevel','error','-i','pipe:0','-map_metadata','-1','-map_chapters','-1','-c:a','libopus','-b:a','32k','-f','ogg','pipe:1'],{stdio:['pipe','pipe','pipe']});
  const chunks=[];let n=0;const timer=setTimeout(()=>child.kill(),15000);
  child.stdout.on('data',b=>{n+=b.length;if(n>2*1024*1024)child.kill();else chunks.push(b);});
  child.stderr.resume();child.stdin.on('error',()=>{});child.on('error',reject);
  child.on('close',code=>{clearTimeout(timer);if(code!==0)return reject(Error('AUDIO_ENCODING_FAILED'));resolve(Buffer.concat(chunks));});
  child.stdin.end(wav);
 });
}
export async function startMessagesServer(context){
 const socket='/run/suites-attendance/messages.sock';
 await unlink(socket).catch(e=>{if(e.code!=='ENOENT')throw e;});
 const server=http.createServer(async(req,res)=>{
  const send=(status,data)=>{res.writeHead(status,{'content-type':'application/json'});res.end(JSON.stringify(data));};
  if(req.method!=='POST'||req.url!=='/message')return send(404,{ok:false});
  try{
   const chunks=[];let n=0;for await(const b of req){n+=b.length;if(n>8*1024*1024)return send(413,{ok:false});chunks.push(b);}
   const input=JSON.parse(Buffer.concat(chunks).toString());
   if(typeof input.id!=='string'||input.id.length>190||!/^[0-9]+@(s\.whatsapp\.net|lid)$/.test(input.jid||''))return send(422,{ok:false});
   const result=await context('message',input);
   if(result.wav){try{result.audio=(await opus(Buffer.from(result.wav,'base64'))).toString('base64');}catch{result.audio_error=true;}delete result.wav;}
   send(200,result);
  }catch{send(503,{ok:false,error:'MESSAGE_PROCESSING_UNAVAILABLE'});}
 });
 await new Promise((resolve,reject)=>{server.once('error',reject);server.listen(socket,resolve);});await chmod(socket,0o660);
 return server;
}
