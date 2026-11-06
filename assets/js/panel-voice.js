(() => {
 'use strict';
 const host=document.getElementById('suitesPanelVoice');
 if(!host || window.self!==window.top)return;
 const toggle=document.getElementById('suitesVoiceToggle'),status=host.querySelector('[data-voice-status]'),question=host.querySelector('textarea'),answer=host.querySelector('[data-voice-answer]'),sendButton=host.querySelector('[data-voice-send]');
 const csrf=host.querySelector('[name=_csrf]').value;
 let audio=null,objectUrl=null,lastText='',answerBusy=false,pendingGreeting=false,speechController=null,speechVersion=0;
 const sayStatus=text=>{status.textContent=text;};
 const stop=()=>{
  speechVersion++;speechController?.abort();speechController=null;pendingGreeting=false;
  audio?.pause();audio=null;
  if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;
 };
 const post=async(kind,text,signal)=>{
  const response=await fetch('chat.php?action=panel_voice',{method:'POST',credentials:'same-origin',signal,body:new URLSearchParams({_csrf:csrf,kind,text}),cache:'no-store'});
  if(!response.ok){let message='Atendimento indisponível.';try{message=(await response.json()).error||message;}catch{}throw new Error(message);}
  return response;
 };
 const speak=async text=>{
  stop();lastText=text;
  const version=speechVersion,controller=new AbortController();speechController=controller;
  const timer=setTimeout(()=>controller.abort(),85000);
  sayStatus('Preparando voz natural…');
  try{
   const response=await post('speech',text,controller.signal);
   if(!(response.headers.get('content-type')||'').includes('audio/wav'))throw new Error('A voz natural não retornou áudio.');
   const blob=await response.blob();
   if(version!==speechVersion)return;
   objectUrl=URL.createObjectURL(blob);audio=new Audio(objectUrl);
   audio.addEventListener('ended',()=>{if(version===speechVersion)sayStatus('Pode falar ou escrever.');});
   try{await audio.play();if(version===speechVersion){sayStatus('Falando…');pendingGreeting=false;}}
   catch(error){if(version!==speechVersion)return;if(error.name==='NotAllowedError'){pendingGreeting=true;sayStatus('Clique em Repetir para ouvir. Você já pode escrever.');}else throw error;}
  }catch(error){if(version===speechVersion)sayStatus(error.name==='AbortError'?'A voz demorou para responder. Sua resposta por texto continua disponível.':error.message);}
  finally{clearTimeout(timer);if(version===speechVersion)speechController=null;}
 };
 const send=async()=>{
  const text=question.value.trim();if(!text||answerBusy)return;
  stop();answerBusy=true;sendButton.disabled=true;question.setAttribute('aria-busy','true');sayStatus('Consultando atendimento…');
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),35000);
  try{
   const response=await post('answer',text,controller.signal),data=await response.json();
   if(!data.ok||typeof data.answer!=='string'||!data.answer.trim())throw new Error(data.error||'O atendimento não retornou uma resposta.');
   answer.textContent=data.answer;
   void speak(data.answer);
  }catch(error){sayStatus(error.name==='AbortError'?'O atendimento demorou para responder. Tente novamente.':error.message);}
  finally{clearTimeout(timer);answerBusy=false;sendButton.disabled=false;question.removeAttribute('aria-busy');}
 };
 toggle.addEventListener('click',()=>{host.hidden=!host.hidden;toggle.setAttribute('aria-expanded',String(!host.hidden));if(!host.hidden)question.focus();});
 host.querySelector('[data-voice-close]').addEventListener('click',()=>{stop();host.hidden=true;toggle.setAttribute('aria-expanded','false');});
 sendButton.addEventListener('click',send);
 question.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey&&!event.isComposing){event.preventDefault();void send();}});
 host.querySelector('[data-voice-repeat]').addEventListener('click',()=>{if(lastText&&!answerBusy)void speak(lastText);});
 host.querySelector('[data-voice-stop]').addEventListener('click',()=>{stop();sayStatus(answerBusy?'Consultando atendimento…':'Áudio interrompido. Pode escrever sua pergunta.');});
 host.querySelector('[data-voice-test]').addEventListener('click',()=>{if(!answerBusy)void speak('Olá. A voz natural das Suítes Crepúsculo está pronta para ajudar.');});
 const mic=host.querySelector('[data-voice-mic]'),Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 if(Recognition){const recognition=new Recognition();recognition.lang='pt-BR';recognition.interimResults=false;recognition.maxAlternatives=1;mic.addEventListener('click',()=>{if(answerBusy)return;stop();try{recognition.start();sayStatus('Ouvindo…');}catch{sayStatus('O microfone já está em uso.');}});recognition.onresult=event=>{question.value=event.results[0][0].transcript;void send();};recognition.onerror=()=>sayStatus('Não foi possível usar o microfone. Você pode escrever.');}
 else{mic.disabled=true;mic.title='Este navegador não oferece reconhecimento de voz.';}
 const retry=()=>{if(pendingGreeting&&audio&&!answerBusy){audio.play().then(()=>{pendingGreeting=false;sayStatus('Falando…');}).catch(()=>{});}};
 document.addEventListener('pointerdown',retry,{passive:true});document.addEventListener('keydown',retry);
 fetch('chat.php?action=panel_voice',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(data=>{
  if(!data.ok){sayStatus(data.error||'Entre novamente no painel para usar o atendimento.');return;}
  if(answerBusy||lastText)return;
  sayStatus(data.configured?'Voz natural pronta.':'Voz natural ainda não configurada.');
  const key='suites.voice.greeting.'+host.dataset.greeting;
  if(data.configured&&!sessionStorage.getItem(key)){sessionStorage.setItem(key,'1');void speak(data.greeting);}
 }).catch(()=>sayStatus('Não foi possível consultar a voz. Você pode tentar enviar sua pergunta.'));
 window.addEventListener('pagehide',stop);
})();
