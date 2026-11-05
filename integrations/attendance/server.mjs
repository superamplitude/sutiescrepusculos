/**
 * Suítes Crepúsculo — integração de atendimento com os serviços centrais já existentes.
 * Processo Node 20+; credenciais somente no ambiente da VPS, nunca no navegador/GitHub.
 * Rotas: GET /health; POST /voice/speech; POST /whatsapp/reply; POST /whatsapp/webhook;
 * POST /calls/start. A interface do site permanece independente deste serviço.
 */
import http from 'node:http';
import {createHmac,timingSafeEqual} from 'node:crypto';

const env=process.env;
const port=Number(env.SUITES_ATTENDANCE_PORT||0);
const token=env.SUITES_ATTENDANCE_TOKEN||'';
const central=(env.CENTRAL_COMMUNICATION_API_URL||'').replace(/\/$/,'');
const centralToken=env.CENTRAL_COMMUNICATION_SUITES_TOKEN||'';
const chatwoot=(env.CHATWOOT_API_URL||'').replace(/\/$/,'');
const chatwootAccount=env.CHATWOOT_SUITES_ACCOUNT_ID||'';
const chatwootToken=env.CHATWOOT_SUITES_ACCESS_TOKEN||'';
const webhookSecret=env.CHATWOOT_SUITES_WEBHOOK_SECRET||'';
const botUrl=env.CRM_SUITES_REPLY_ENDPOINT||'';
const botToken=env.CRM_SUITES_REPLY_TOKEN||'';
const calls=(env.WACALLS_API_BASE_URL||'').replace(/\/$/,'');
const callsToken=env.WACALLS_API_TOKEN||'';
const session=env.SUITES_WACALLS_SESSION_ID||'';

const isString=v=>typeof v==='string'&&v.length>0;
const eq=(a,b)=>{const x=Buffer.from(String(a)),y=Buffer.from(String(b));return x.length===y.length&&timingSafeEqual(x,y)};
const json=(res,status,obj)=>{res.writeHead(status,{'content-type':'application/json; charset=utf-8','cache-control':'no-store'});res.end(JSON.stringify(obj))};
const read=async(req,max=32768)=>{const chunks=[];let n=0;for await(const chunk of req){n+=chunk.length;if(n>max)throw new Error('TOO_LARGE');chunks.push(chunk)}return Buffer.concat(chunks)};
const upstream=async(url,{method='POST',headers={},body,timeout=15000}={})=>{
 const r=await fetch(url,{method,headers,body:body===undefined?undefined:JSON.stringify(body),redirect:'error',signal:AbortSignal.timeout(timeout)});
 if(!r.ok)throw new Error('UPSTREAM_'+r.status);
 return r;
};
function authorized(req){return token.length>=32&&eq(req.headers.authorization||'','Bearer '+token)}
function phone(v){const n=String(v||'').replace(/\D/g,'');if(n.length<8||n.length>15)throw new Error('INVALID_PHONE');return n}
function reject(res,e){json(res,/TOO_LARGE/.test(e.message)?413: /INVALID_|BAD_JSON/.test(e.message)?400:502,{ok:false,error:e.message.startsWith('UPSTREAM_')?e.message:'ATTENDANCE_UNAVAILABLE'})}
export function createSuitesAttendanceServer(){
 return http.createServer(async(req,res)=>{
  const path=new URL(req.url||'/','http://localhost').pathname;
  if(req.method==='GET'&&path==='/health')return json(res,200,{ok:true,project:'suites-crepusculo',voice:!!(central&&centralToken),whatsapp:!!(chatwoot&&chatwootAccount&&chatwootToken),ai:!!(botUrl&&botToken),calls:!!(calls&&callsToken&&session)});
  if(req.method!=='POST')return json(res,404,{error:'NOT_FOUND'});
  try{
   if(path==='/whatsapp/webhook'){
    if(!webhookSecret)return json(res,503,{error:'WEBHOOK_NOT_CONFIGURED'});
    const raw=await read(req);
    // Configure the sender to use an HMAC SHA-256 signature over the raw body.
    const signature=String(req.headers['x-suites-signature']||'').replace(/^sha256=/,'');
    const expected=createHmac('sha256',webhookSecret).update(raw).digest('hex');
    if(!eq(signature,expected))return json(res,401,{error:'INVALID_SIGNATURE'});
    const event=JSON.parse(raw.toString('utf8'));
    if(event?.event!=='message_created'||event?.message_type!=='incoming')return json(res,200,{ok:true,ignored:true});
    const accountId=String(event.account?.id||event.account_id||'');
    if(accountId!==chatwootAccount)return json(res,403,{error:'WRONG_ACCOUNT'});
    if(!botUrl||!botToken)return json(res,503,{error:'AI_NOT_CONFIGURED'});
    // AI processing belongs to the already-deployed CRM service; no duplicate model or bot.
    await upstream(botUrl,{headers:{'content-type':'application/json',authorization:'Bearer '+botToken},body:{project:'suites-crepusculo',event},timeout:20000});
    return json(res,202,{ok:true,forwarded:true});
   }
   if(!authorized(req))return json(res,401,{error:'UNAUTHORIZED'});
   const data=JSON.parse((await read(req)).toString('utf8'));
   if(path==='/voice/speech'){
    if(!central||!centralToken)return json(res,503,{error:'VOICE_NOT_CONFIGURED'});
    const msg=String(data.text||'').trim();
    if(msg.length<2||msg.length>1200)return json(res,400,{error:'INVALID_TEXT'});
    // The central API needs a provider key scoped to this project; never return or log it.
    const provider=env.SUITES_GOOGLE_API_KEY||'';
    if(!provider)return json(res,503,{error:'VOICE_PROVIDER_NOT_CONFIGURED'});
    const r=await upstream(central+'/communication/v1/projects/suites-crepusculo/voice/speech',{
      headers:{authorization:'Bearer '+centralToken,'content-type':'application/json'},
      body:{text:msg,provider_api_key:provider},timeout:75000
    });
    const wav=Buffer.from(await r.arrayBuffer());
    if(wav.toString('ascii',0,4)!=='RIFF'||wav.toString('ascii',8,12)!=='WAVE')throw new Error('INVALID_AUDIO');
    res.writeHead(200,{'content-type':'audio/wav','cache-control':'no-store'});return res.end(wav);
   }
   if(path==='/whatsapp/reply'){
    if(!chatwoot||!chatwootAccount||!chatwootToken)return json(res,503,{error:'WHATSAPP_NOT_CONFIGURED'});
    const conversation=Number(data.conversation_id),msg=String(data.content||'').trim();
    if(!Number.isSafeInteger(conversation)||conversation<=0||!msg||msg.length>4000)return json(res,400,{error:'INVALID_MESSAGE'});
    const r=await upstream(chatwoot+'/api/v1/accounts/'+encodeURIComponent(chatwootAccount)+'/conversations/'+conversation+'/messages',{
      headers:{'content-type':'application/json',api_access_token:chatwootToken},
      body:{content:msg,message_type:'outgoing',private:false}});
    return json(res,200,{ok:true,message:await r.json()});
   }
   if(path==='/calls/start'){
    if(!calls||!callsToken||!session)return json(res,503,{error:'CALLS_NOT_CONFIGURED'});
    if(data.consent!==true)return json(res,403,{error:'CALL_CONSENT_REQUIRED'});
    const number=phone(data.phone);
    const r=await upstream(calls+'/api/sessions/'+encodeURIComponent(session)+'/calls',{
      headers:{'content-type':'application/json',authorization:'Bearer '+callsToken,'X-Client-Id':'suites-crepusculo'},
      body:{phone:number,record:false}});
    return json(res,200,{ok:true,call:await r.json()});
   }
   return json(res,404,{error:'NOT_FOUND'});
  }catch(e){return reject(res,e)}
 });
}
if(process.argv[1]&&import.meta.url===new URL('file://'+process.argv[1]).href){
 if(!Number.isInteger(port)||port<1||port>65535)throw Error('SUITES_ATTENDANCE_PORT_REQUIRED');
 createSuitesAttendanceServer().listen(port,'127.0.0.1');
}
