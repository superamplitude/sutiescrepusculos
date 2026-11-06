let pending;
export function suitesMessagingProvider(){
 if(!pending)pending=(async()=>{
  const b=await import('./suites-baileys/node_modules/@whiskeysockets/baileys/lib/index.js');
  const latest=await b.fetchLatestBaileysVersion();
  if(typeof b.default!=='function'||typeof b.useMultiFileAuthState!=='function'||!Array.isArray(latest.version))throw Error('SUITES_STABLE_PROVIDER_INVALID');
  return {make:b.default,auth:b.useMultiFileAuthState,download:b.downloadMediaMessage,version:latest.version,browser:b.Browsers.ubuntu('Chrome')};
 })();
 return pending;
}
