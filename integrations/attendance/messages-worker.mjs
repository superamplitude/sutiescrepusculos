import {spawn} from 'node:child_process';
import {startMessagesServer} from './messages-server.mjs';
const context=(_action,data)=>new Promise((resolve,reject)=>{
 const child=spawn('/usr/bin/php',[new URL('./messages-context.php',import.meta.url).pathname],{stdio:['pipe','pipe','pipe']});
 const chunks=[];let size=0;const timer=setTimeout(()=>child.kill(),100000);child.stdin.on('error',()=>{});child.stderr.resume();
 child.stdout.on('data',b=>{size+=b.length;if(size>8*1024*1024)child.kill();else chunks.push(b);});
 child.on('error',reject);child.on('close',code=>{clearTimeout(timer);try{if(code!==0)throw Error('MESSAGE_CONTEXT_FAILED');resolve(JSON.parse(Buffer.concat(chunks)));}catch(e){reject(e);}});
 child.stdin.end(JSON.stringify(data));
});
const server=await startMessagesServer(context);
console.log('SUITES_MESSAGES_SOCKET_READY');
for(const signal of ['SIGTERM','SIGINT'])process.once(signal,()=>server.close());
