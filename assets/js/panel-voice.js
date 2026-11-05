(() => {
 'use strict';
 const host=document.getElementById('suitesPanelVoice');
 if(!host || window.self!==window.top)return;
 const toggle=document.getElementById('suitesVoiceToggle'),status=host.querySelector('[data-voice-status]'),question=host.querySelector('textarea'),answer=host.querySelector('[data-voice-answer]');
 const csrf=host.querySelector('[name=_csrf]').value;
 let audio=null,objectUrl=null,lastText='',busy=false,greeting='',pendingGreeting=false;
 const sayStatus=text=>{status.textContent=text;};
 const stop=()=>{audio?.pause();if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;};
 const post=async(kind,text)=>{
  const response=await fetch('chat.php?action=panel_voice',{method:'POST',credentials:'same-origin',body:new URLSearchParams({_csrf:csrf,kind,text}),cache:'no-store'});
  if(!response.ok){let message='Atendimento indisponível.';try{message=(await response.json()).error||message;}catch{}throw new Error(message);}
  return response;
 };
 const speak=async text=>{
  if(busy)return;busy=true;lastText=text;sayStatus('Preparando voz natural…');stop();
  try{
   const response=await post('speech',text);
   if(!(response.headers.get('content-type')||'').includes('audio/wav'))throw new Error('A voz natural não retornou áudio.');
   objectUrl=URL.createObjectURL(await response.blob());audio=new Audio(objectUrl);
   audio.addEventListener('ended',()=>sayStatus('Pode falar ou escrever.'));
   try{await audio.play();sayStatus('Falando…');pendingGreeting=false;}
   catch(error){if(error.name==='NotAllowedError'){pendingGreeting=true;sayStatus('A voz será iniciada na próxima interação.');}else throw error;}
  }catch(error){sayStatus(error.message);}
  finally{busy=false;}
 };
 const send=async()=>{
  const text=question.value.trim();if(!text||busy)return;
  busy=true;sayStatus('Consultando atendimento…');
  try{const response=await post('answer',text),data=await response.json();answer.textContent=data.answer;busy=false;await speak(data.answer);}catch(error){sayStatus(error.message);}finally{busy=false;}
 };
 toggle.addEventListener('click',()=>{host.hidden=!host.hidden;toggle.setAttribute('aria-expanded',String(!host.hidden));if(!host.hidden)question.focus();});
 host.querySelector('[data-voice-close]').addEventListener('click',()=>{host.hidden=true;toggle.setAttribute('aria-expanded','false');});
 host.querySelector('[data-voice-send]').addEventListener('click',send);
 host.querySelector('[data-voice-repeat]').addEventListener('click',()=>{if(lastText)speak(lastText);});
 host.querySelector('[data-voice-stop]').addEventListener('click',()=>{stop();pendingGreeting=false;sayStatus('Áudio interrompido.');});
 host.querySelector('[data-voice-test]').addEventListener('click',()=>speak('Olá. A voz natural das Suítes Crepúsculo está pronta para ajudar.'));
 const mic=host.querySelector('[data-voice-mic]'),Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 if(Recognition){const recognition=new Recognition();recognition.lang='pt-BR';recognition.interimResults=false;recognition.maxAlternatives=1;mic.addEventListener('click',()=>{stop();try{recognition.start();sayStatus('Ouvindo…');}catch{sayStatus('O microfone já está em uso.');}});recognition.onresult=event=>{question.value=event.results[0][0].transcript;send();};recognition.onerror=()=>sayStatus('Não foi possível usar o microfone. Você pode escrever.');}
 else{mic.disabled=true;mic.title='Este navegador não oferece reconhecimento de voz.';}
 const retry=()=>{if(pendingGreeting&&audio){audio.play().then(()=>{pendingGreeting=false;sayStatus('Falando…');}).catch(()=>{});}};
 document.addEventListener('pointerdown',retry,{passive:true});document.addEventListener('keydown',retry);
 fetch('chat.php?action=panel_voice',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(data=>{
  if(!data.ok)return;sayStatus(data.configured?'Voz natural pronta.':'Voz natural ainda não configurada.');greeting=data.greeting;
  const key='suites.voice.greeting.'+host.dataset.greeting;
  if(data.configured&&!sessionStorage.getItem(key)){sessionStorage.setItem(key,'1');speak(greeting);}
 }).catch(()=>sayStatus('Não foi possível consultar a voz.'));
 window.addEventListener('pagehide',stop);
})();
