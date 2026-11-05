import assert from 'node:assert/strict';
import {createSuitesAttendanceServer} from './server.mjs';
const server=createSuitesAttendanceServer();
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const url='http://127.0.0.1:'+server.address().port;
try{
 const health=await fetch(url+'/health');
 assert.equal(health.status,200);
 assert.equal((await health.json()).project,'suites-crepusculo');
 const unauthorized=await fetch(url+'/calls/start',{method:'POST',headers:{'content-type':'application/json'},body:'{}'});
 assert.equal(unauthorized.status,401);
 const invalidWebhook=await fetch(url+'/whatsapp/webhook',{method:'POST',headers:{'content-type':'application/json'},body:'{}'});
 assert.equal(invalidWebhook.status,503);
 console.log('SUITES_ATTENDANCE_UNIT_TESTS=PASS');
}finally{await new Promise(resolve=>server.close(resolve))}
