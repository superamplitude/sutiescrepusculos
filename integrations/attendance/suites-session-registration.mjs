import {readFile,stat} from 'node:fs/promises';
export function validateSuitesRegistration(value,id){
 if(id!=='suites-crepusculo'||value?.enabled!==true||value?.project!=='suites-crepusculo'||value?.session!==id||value?.socket!=='/run/suites-messages/messages.sock')throw Error('SUITES_SESSION_NOT_PROVISIONED');
 return true;
}
export async function assertSuitesProvisioned(id){
 const file='/etc/superamplitude/runtime/suites-messaging-session.json',meta=await stat(file);
 if(meta.uid!==0||(meta.mode&0o022)!==0)throw Error('SUITES_REGISTRATION_PERMISSIONS');
 validateSuitesRegistration(JSON.parse(await readFile(file,'utf8')),id);
 const socket=await stat('/run/suites-messages/messages.sock');
 if(!socket.isSocket())throw Error('SUITES_MESSAGES_PROCESSOR_UNAVAILABLE');
}
