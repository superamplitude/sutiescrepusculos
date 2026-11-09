(() => {
 'use strict';
 const host=document.getElementById('suitesPanelVoice'),toggle=document.getElementById('suitesVoiceToggle');
 if(!host||!toggle||window.self!==window.top)return;
 const status=host.querySelector('[data-voice-status]'),question=host.querySelector('textarea');
 const answer=host.querySelector('[data-voice-answer]'),sendButton=host.querySelector('[data-voice-send]');
 const confirmBox=host.querySelector('[data-agent-confirm-box]'),confirmText=host.querySelector('[data-agent-confirm-text]');
 const csrf=host.querySelector('[name=_csrf]').value;
 const welcomeButton=host.querySelector('[data-voice-greeting]');
 let audio=null,objectUrl=null,lastText='',busy=false,speechController=null,speechVersion=0,pendingGreeting='',pendingToken='',welcomeText='',welcomeKey='',greetingPlayed=false,blockedPlayback=false,blockedWelcome=false,greetingGestureRequested=false;
 const sayStatus=t=>{status.textContent=t;};
 const saveWelcome=()=>{try{if(welcomeKey)sessionStorage.setItem(welcomeKey,'played');}catch{}};
 const welcomePreviouslyPlayed=()=>{try{return sessionStorage.getItem(welcomeKey)==='played';}catch{return false;}};
 const showVoiceAction=(message,label='▶ Ouvir recepção')=>{
   // Autoplay is restricted by some browsers; never hide the one-click recovery
   // inside a collapsed chat. Preserve the original natural voice provider.
   host.hidden=false;toggle.setAttribute('aria-expanded','true');
   toggle.classList.add('needs-voice-gesture');
   toggle.textContent=label;
   welcomeButton.hidden=false;
   sayStatus(message||'Clique em Ouvir recepção para ativar a voz natural.');
 };
 const welcomeStarted=()=>{
   greetingPlayed=true;pendingGreeting='';greetingGestureRequested=false;welcomeButton.hidden=true;blockedPlayback=false;blockedWelcome=false;
   toggle.classList.remove('needs-voice-gesture');toggle.textContent='💬 Falar com IA';
   sayStatus('Falando…');
 };
 const cleanAudio=()=>{
   speechVersion++;speechController?.abort();speechController=null;
   audio?.pause();audio=null;blockedPlayback=false;blockedWelcome=false;if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;
 };
 const append=(who,message)=>{
   const bubble=document.createElement('div');bubble.className='suites-chat-message '+(who==='Você'?'from-user':'from-agent');
   const small=document.createElement('small');small.textContent=who;
   const body=document.createElement('p');body.textContent=message;
   bubble.append(small,body);answer.appendChild(bubble);answer.scrollTop=answer.scrollHeight;
 };
 const post=async(kind,text,signal)=>{
   const res=await fetch('/chat.php?action=panel_voice',{method:'POST',credentials:'same-origin',cache:'no-store',signal,headers:{'Accept':'application/json'},body:new URLSearchParams({_csrf:csrf,kind,text})});
   if(!res.ok){let reason='Atendimento indisponível.';try{const data=await res.json();reason=data.error||reason;}catch{}throw Error(reason);}
   return res;
 };
 const playText=async(text,isWelcome=false)=>{
   cleanAudio();lastText=text;
   const version=speechVersion,controller=new AbortController();speechController=controller;
   sayStatus('Preparando voz…');
   const timer=setTimeout(()=>controller.abort(),85000);
   try{
     const res=await post('speech',text,controller.signal);
     if(!(res.headers.get('content-type')||'').includes('audio/wav'))throw Error('Áudio não retornado pelo serviço.');
     const blob=await res.blob();if(version!==speechVersion)return;
     objectUrl=URL.createObjectURL(blob);audio=new Audio(objectUrl);audio.muted=false;audio.volume=1;audio.preload='auto';
     // If a user clicked while synthesis was running, honor that request as soon as WAV is ready.
     if(isWelcome&&greetingGestureRequested){greetingGestureRequested=false;showVoiceAction('Recepção preparada. Clique para ouvir a saudação.');}
     audio.addEventListener('ended',()=>{if(version===speechVersion){if(isWelcome)saveWelcome();sayStatus('Pronta para conversar.');}});
     try{await audio.play();if(version===speechVersion){if(isWelcome)welcomeStarted();else{sayStatus('Falando…');pendingGreeting='';}}}
     catch(error){
       if(version!==speechVersion)return;
       if(error.name==='NotAllowedError'){
         blockedPlayback=true;blockedWelcome=isWelcome;
         if(isWelcome)pendingGreeting=text;
         showVoiceAction('O navegador exige um clique para liberar o som.','▶ '+(isWelcome?'Ouvir recepção':'Ativar áudio'));
       }
       else throw error;
     }
   }catch(e){if(version===speechVersion){sayStatus(e.name==='AbortError'?'Áudio excedeu o tempo de resposta.':e.message);if(isWelcome&&!greetingPlayed){pendingGreeting=text;greetingGestureRequested=false;showVoiceAction('Não foi possível iniciar a saudação. Clique para tentar novamente.','▶ Tentar recepção');}}}
   finally{clearTimeout(timer);if(version===speechVersion)speechController=null;}
 };
 const busyUI=v=>{busy=v;sendButton.disabled=v;question.disabled=v;host.setAttribute('aria-busy',String(v));};
 const act=async(kind,text)=>{
   if(busy)return;busyUI(true);sayStatus('Consultando IA…');
   const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),47000);
   try{
     const res=await post(kind,text,controller.signal),data=await res.json();
     if(!data.ok||!data.answer)throw Error(data.error||'Sem resposta do atendimento.');
     append('Assistente',data.answer);
     pendingToken=data.proposal?.token||'';
     confirmBox.hidden=!pendingToken;
     if(pendingToken)confirmText.textContent=data.proposal.label;
     sayStatus(pendingToken?'Confirme ou cancele a alteração.':'Resposta recebida.');
     if(!pendingToken)void playText(data.answer);
   }catch(e){const message=e.name==='AbortError'?'A IA demorou a responder.':e.message;sayStatus(message);append('Assistente',message);}
   finally{clearTimeout(timer);busyUI(false);}
 };
 const send=()=>{
   const text=question.value.trim();if(!text||busy)return;
   append('Você',text);question.value='';pendingToken='';confirmBox.hidden=true;cleanAudio();
   void act('answer',text);
 };
 toggle.addEventListener('click',()=>{
   if((blockedPlayback&&audio)||(pendingGreeting&&!greetingPlayed)){host.hidden=false;toggle.setAttribute('aria-expanded','true');userGesture();return;}
   const visible=host.hidden;host.hidden=!visible;toggle.setAttribute('aria-expanded',String(visible));
   if(visible){question.focus();}
 });
 host.querySelector('[data-voice-close]').addEventListener('click',()=>{cleanAudio();host.hidden=true;toggle.setAttribute('aria-expanded','false');});
 sendButton.addEventListener('click',send);
 question.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();send();}});
 host.querySelector('[data-voice-repeat]').addEventListener('click',()=>{if(busy)return;if(blockedPlayback&&audio)userGesture();else if(lastText)void playText(lastText);});
 host.querySelector('[data-voice-stop]').addEventListener('click',()=>{cleanAudio();sayStatus('Voz interrompida. Chat disponível.');});
 host.querySelector('[data-voice-test]').addEventListener('click',()=>{if(busy)return;if(blockedPlayback&&audio)userGesture();else void playText('Olá. Estou pronta para conversar e ajudar no seu painel.');});
 welcomeButton.addEventListener('click',()=>{if(!busy)userGesture();});
 host.querySelector('[data-agent-confirm]').addEventListener('click',()=>{if(!pendingToken)return;const token=pendingToken;pendingToken='';confirmBox.hidden=true;void act('confirm',token);});
 host.querySelector('[data-agent-cancel]').addEventListener('click',()=>{pendingToken='';confirmBox.hidden=true;void act('cancel','cancelar');});
 const mic=host.querySelector('[data-voice-mic]'),Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 if(Recognition){
   const recognition=new Recognition();recognition.lang='pt-BR';recognition.interimResults=false;recognition.maxAlternatives=1;
   mic.addEventListener('click',()=>{if(busy)return;cleanAudio();try{recognition.start();sayStatus('Ouvindo…');}catch{sayStatus('Microfone em uso.');}});
   recognition.onresult=e=>{question.value=e.results[0][0].transcript;send();};
   recognition.onerror=()=>sayStatus('O reconhecimento de voz falhou; use o teclado.');
 }else{mic.disabled=true;mic.title='Reconhecimento de voz indisponível neste navegador.';}
 const userGesture=()=>{
   if(busy)return;
   if(blockedPlayback&&audio){
     const welcome=blockedWelcome;
     audio.muted=false;audio.volume=1;
     audio.play().then(()=>{
       blockedPlayback=false;blockedWelcome=false;welcomeButton.hidden=true;
       toggle.classList.remove('needs-voice-gesture');toggle.textContent='💬 Falar com IA';
       if(welcome)welcomeStarted();else sayStatus('Falando…');
     }).catch(()=>showVoiceAction('Permita áudio nas configurações deste site e clique novamente.',welcome?'▶ Ouvir recepção':'▶ Ativar áudio'));
     return;
   }
   if(!pendingGreeting||greetingPlayed||!welcomeText)return;
   if(speechController){greetingGestureRequested=true;showVoiceAction('Preparando sua recepção. O áudio será liberado assim que estiver pronto.');return;}
   void playText(welcomeText,true);
 };
 document.addEventListener('pointerdown',e=>{
   if(e.target.closest('[data-voice-greeting], [data-voice-send], [data-voice-close], [data-voice-mic], [data-voice-stop], [data-voice-repeat], [data-voice-test], #suitesVoiceToggle'))return;
   userGesture();
 },{passive:true});
 fetch('/chat.php?action=panel_voice',{credentials:'same-origin',cache:'no-store'})
   .then(r=>r.json()).then(data=>{
     if(!data.ok){host.hidden=false;toggle.setAttribute('aria-expanded','true');sayStatus(data.error||'Entre no painel novamente.');return;}
     sayStatus(data.configured?'Voz conectada.':'Serviço de voz não configurado.');
     welcomeKey='suites.voice.greeting.completed.v2.'+host.dataset.greeting;
     welcomeText=data.greeting||'Olá. Como posso ajudar?';
     if(!data.configured){host.hidden=false;toggle.setAttribute('aria-expanded','true');append('Assistente','A recepção por voz está indisponível; o chat continua disponível.');return;}
     if(!welcomePreviouslyPlayed()){
       pendingGreeting=welcomeText;
       append('Assistente',welcomeText);
       welcomeButton.hidden=false;
       toggle.textContent='▶ Recepção por voz';
       sayStatus('Iniciando saudação de boas-vindas…');
       void playText(welcomeText,true);
     }
   }).catch(()=>{host.hidden=false;toggle.setAttribute('aria-expanded','true');sayStatus('Falha ao conectar à voz. Verifique a sessão ou tente novamente.');});
 window.addEventListener('pagehide',cleanAudio);
})();
