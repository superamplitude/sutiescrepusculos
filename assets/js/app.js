document.addEventListener('DOMContentLoaded',()=>{
  const days=[...document.querySelectorAll('.day.selectable[data-room][data-date]')];
  const reservationLinks=[...document.querySelectorAll('.map-reservation-link[data-map-reservation]')];
  const modal=document.getElementById('mapReservationModal');
  if(!modal || (!days.length && !reservationLinks.length))return;

  const preForm=document.getElementById('mapPreReservationForm');
  const resultBox=document.getElementById('mapReservationResult');
  const summary=document.getElementById('mapReservationSummary');
  const confirmForm=document.getElementById('mapConfirmReservationForm');
  const confirmId=document.getElementById('mapConfirmReservationId');
  const proceedBtn=document.getElementById('mapProceedReservation');
  const cancelBtn=document.getElementById('mapCancelReservation');
  const editBtn=document.getElementById('mapEditReservation');
  const backBtn=document.getElementById('mapBackToPreReservation');
  const alertBox=document.getElementById('mapModalAlert');
  const title=document.getElementById('mapModalTitle');
  const subtitle=document.getElementById('mapModalSubtitle');
  const roomId=document.getElementById('mapModalRoomId');
  const roomName=document.getElementById('mapModalRoomName');
  const checkin=document.getElementById('mapModalCheckin');
  const checkout=document.getElementById('mapModalCheckout');
  const info=document.getElementById('selectionInfo');
  const roomSel=document.getElementById('mapRoom');
  const blockStart=document.querySelector('#blockForm [name="start_date"]');
  const blockEnd=document.querySelector('#blockForm [name="end_date"]');
  const clearBtn=document.getElementById('clearMapSelection');

  let activeReservation=null;
  let dirty=false;
  let dragging=false,dragStart=null,dragEnd=null,dragMoved=false,suppressClick=false;

  const esc=(value)=>String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const brl=(value)=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(value||0));
  const dateBR=(iso)=>{if(!iso)return '—';const p=String(iso).slice(0,10).split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0]}`:iso;};
  const isoNext=(iso)=>{const [y,m,d]=String(iso).split('-').map(Number);const dt=new Date(Date.UTC(y,m-1,d+1));return dt.toISOString().slice(0,10);};

  const showAlert=(message,type='error')=>{
    if(!alertBox)return;
    alertBox.hidden=!message;
    alertBox.className=`map-modal-alert ${type}`;
    alertBox.textContent=message||'';
  };

  const setBusy=(form,busy)=>{
    if(!form)return;
    form.querySelectorAll('button,input,select,textarea').forEach(el=>{
      if(el.type!=='hidden')el.disabled=busy;
    });
  };

  const openModal=()=>{
    modal.hidden=false;
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('map-modal-open');
  };

  const clearSelection=()=>{
    document.querySelectorAll('.day.selected').forEach(x=>x.classList.remove('selected'));
    if(roomSel)roomSel.value='';
    if(blockStart)blockStart.value='';
    if(blockEnd)blockEnd.value='';
    if(info)info.textContent='Clique em um dia livre para iniciar uma pré-reserva.';
  };

  const closeModal=()=>{
    modal.hidden=true;
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('map-modal-open');
    showAlert('');
    if(dirty){window.location.reload();return;}
    clearSelection();
  };

  const applyVisualRange=(a,b)=>{
    document.querySelectorAll('.day.selected').forEach(x=>x.classList.remove('selected'));
    if(!a||!b||a.dataset.room!==b.dataset.room)return false;
    const lo=a.dataset.date<=b.dataset.date?a.dataset.date:b.dataset.date;
    const hi=a.dataset.date<=b.dataset.date?b.dataset.date:a.dataset.date;
    const row=[...document.querySelectorAll(`.day[data-room="${a.dataset.room}"]`)];
    const invalid=row.some(x=>x.dataset.date>=lo&&x.dataset.date<=hi&&!x.classList.contains('selectable'));
    if(invalid){if(info)info.textContent='O período cruza uma reserva ou bloqueio. Selecione somente dias livres.';return false;}
    const current=days.filter(x=>x.dataset.room===a.dataset.room&&x.dataset.date>=lo&&x.dataset.date<=hi);
    current.forEach(x=>x.classList.add('selected'));
    if(roomSel)roomSel.value=a.dataset.room;
    if(blockStart)blockStart.value=lo;
    if(blockEnd)blockEnd.value=isoNext(hi);
    if(info)info.textContent=`Selecionado: ${a.dataset.roomName||'Unidade'} · ${dateBR(lo)} até ${dateBR(isoNext(hi))} · ${current.length} diária(s)`;
    return {lo,hi,current};
  };

  const resetPreForm=()=>{
    preForm?.reset();
    if(preForm?.querySelector('[name="guests"]'))preForm.querySelector('[name="guests"]').value='2';
    if(roomId)roomId.value='';
    if(roomName)roomName.value='';
    activeReservation=null;
    showAlert('');
  };

  const openNew=(a,b=a)=>{
    const range=applyVisualRange(a,b);if(!range)return;
    resetPreForm();
    title.textContent='Nova pré-reserva';
    subtitle.textContent='Preencha os dados. A pré-reserva será mantida por 30 minutos e poderá ser efetivada ou cancelada nesta mesma janela.';
    roomId.value=a.dataset.room;
    roomName.value=a.dataset.roomName||`Unidade ${a.dataset.room}`;
    checkin.value=range.lo;
    checkout.value=isoNext(range.hi);
    preForm.hidden=false;
    resultBox.hidden=true;
    confirmForm.hidden=true;
    dirty=false;
    openModal();
    setTimeout(()=>preForm.querySelector('[name="guest_name"]')?.focus(),40);
  };

  const apiPost=async(formData)=>{
    const response=await fetch('index.php?p=map_reservation_api',{method:'POST',body:formData,credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'});
    let data=null;
    try{data=await response.json();}catch(e){throw new Error(response.status===419?'Sua sessão expirou. Recarregue a página e tente novamente.':'Resposta inválida do servidor.');}
    if(!response.ok || !data?.ok)throw new Error(data?.message||'Não foi possível concluir a operação.');
    return data;
  };

  const apiGet=async(id)=>{
    const response=await fetch(`index.php?p=map_reservation_api&id=${encodeURIComponent(id)}`,{credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'});
    const data=await response.json();
    if(!response.ok||!data?.ok)throw new Error(data?.message||'Não foi possível carregar a reserva.');
    return data;
  };

  const renderReservation=(r,message='')=>{
    activeReservation=r;
    const expires=r.expires_at?`<div><span>Validade</span><b>${esc(String(r.expires_at).replace(' ',' às '))}</b></div>`:'';
    summary.innerHTML=`
      <div class="map-reservation-status"><span class="status-pill status-${esc(r.status)}">${esc(r.status_label||r.status)}</span><strong>${esc(r.code)}</strong></div>
      <div class="map-reservation-summary-grid">
        <div><span>Hóspede</span><b>${esc(r.guest_name)}</b></div>
        <div><span>Unidade</span><b>${esc(r.room_name)}</b></div>
        <div><span>Entrada</span><b>${esc(dateBR(r.checkin))}</b></div>
        <div><span>Saída</span><b>${esc(dateBR(r.checkout))}</b></div>
        <div><span>Hóspedes</span><b>${esc(r.guests)}</b></div>
        <div><span>Total</span><b>${esc(brl(r.total))}</b></div>
        <div><span>Pago</span><b>${esc(brl(r.amount_paid))}</b></div>
        ${expires}
      </div>`;
    if(message)showAlert(message,'success'); else showAlert('');
    title.textContent=r.status==='pre_reserva'?'Pré-reserva':'Reserva';
    subtitle.textContent='Acompanhe o status e escolha a próxima ação sem sair do mapa.';
    preForm.hidden=true;
    confirmForm.hidden=true;
    resultBox.hidden=false;
    editBtn.href=`index.php?p=reservation_edit&id=${encodeURIComponent(r.id)}`;
    proceedBtn.hidden=r.status!=='pre_reserva';
    const canCancel=['pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada'].includes(r.status);
    cancelBtn.hidden=!canCancel;
    cancelBtn.textContent=r.status==='pre_reserva'?'Cancelar pré-reserva':'Cancelar reserva';
  };

  const openExisting=async(id)=>{
    resetPreForm();
    title.textContent='Carregando reserva...';
    subtitle.textContent='Aguarde.';
    preForm.hidden=true;resultBox.hidden=true;confirmForm.hidden=true;dirty=false;openModal();
    try{const data=await apiGet(id);renderReservation(data.reservation);}catch(err){showAlert(err.message);title.textContent='Reserva';subtitle.textContent='Não foi possível carregar os dados.';}
  };

  days.forEach(td=>{
    td.addEventListener('mousedown',e=>{
      if(e.button!==0)return;
      dragging=true;dragStart=td;dragEnd=td;dragMoved=false;suppressClick=false;
      applyVisualRange(td,td);e.preventDefault();
    });
    td.addEventListener('mouseenter',()=>{
      if(!dragging||!dragStart||dragStart.dataset.room!==td.dataset.room)return;
      dragEnd=td;dragMoved=dragStart!==td;applyVisualRange(dragStart,td);
    });
    td.addEventListener('click',e=>{
      e.preventDefault();
      if(suppressClick){suppressClick=false;return;}
      openNew(td,td);
    });
    td.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();openNew(td,td);}});
  });

  document.addEventListener('mouseup',()=>{
    if(!dragging)return;
    if(dragMoved&&dragStart&&dragEnd){suppressClick=true;openNew(dragStart,dragEnd);}
    dragging=false;dragStart=null;dragEnd=null;dragMoved=false;
  });

  reservationLinks.forEach(link=>link.addEventListener('click',e=>{
    e.preventDefault();openExisting(link.dataset.mapReservation);
  }));

  preForm?.addEventListener('submit',async e=>{
    e.preventDefault();showAlert('');setBusy(preForm,true);
    try{
      const data=await apiPost(new FormData(preForm));dirty=true;renderReservation(data.reservation,data.message);
    }catch(err){showAlert(err.message);}finally{setBusy(preForm,false);}
  });

  proceedBtn?.addEventListener('click',()=>{
    if(!activeReservation)return;
    resultBox.hidden=true;confirmForm.hidden=false;showAlert('');confirmId.value=activeReservation.id;
    const paid=confirmForm.querySelector('[name="amount_paid"]');if(paid)paid.value=activeReservation.amount_paid||'0';
    title.textContent='Efetuar reserva';subtitle.textContent=`Pré-reserva ${activeReservation.code} · ${activeReservation.room_name}`;
  });

  backBtn?.addEventListener('click',()=>{if(activeReservation)renderReservation(activeReservation);});

  confirmForm?.addEventListener('submit',async e=>{
    e.preventDefault();showAlert('');setBusy(confirmForm,true);
    try{const data=await apiPost(new FormData(confirmForm));dirty=true;renderReservation(data.reservation,data.message);}catch(err){showAlert(err.message);}finally{setBusy(confirmForm,false);}
  });

  cancelBtn?.addEventListener('click',async()=>{
    if(!activeReservation)return;
    const label=activeReservation.status==='pre_reserva'?'esta pré-reserva':'esta reserva';
    if(!window.confirm(`Cancelar ${label}? O período voltará a ficar disponível.`))return;
    const fd=new FormData();
    const csrf=preForm.querySelector('[name="_csrf"]');fd.append('_csrf',csrf?.value||'');fd.append('action','cancel');fd.append('reservation_id',activeReservation.id);
    cancelBtn.disabled=true;showAlert('');
    try{const data=await apiPost(fd);dirty=true;renderReservation(data.reservation,data.message);}catch(err){showAlert(err.message);}finally{cancelBtn.disabled=false;}
  });

  modal.querySelectorAll('[data-map-close]').forEach(btn=>btn.addEventListener('click',closeModal));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)closeModal();});
  clearBtn?.addEventListener('click',clearSelection);
});

document.querySelectorAll('[data-menu-toggle]').forEach(btn=>{
  btn.addEventListener('click',()=>document.querySelector('[data-menu]')?.classList.toggle('open'));
});


document.querySelectorAll('[data-hero-slider]').forEach(slider=>{
  const slides=[...slider.querySelectorAll('[data-hero-slide]')];
  const dots=[...document.querySelectorAll('[data-hero-dot]')];
  if(slides.length<=1) return;
  let i=0;
  const setActive=(next)=>{
    slides.forEach((el,idx)=>el.classList.toggle('active',idx===next));
    dots.forEach((el,idx)=>el.classList.toggle('active',idx===next));
    i=next;
  };
  dots.forEach((dot,idx)=>dot.addEventListener('click',()=>setActive(idx)));
  setInterval(()=>setActive((i+1)%slides.length),5000);
});

document.querySelectorAll('[data-admin-menu-toggle]').forEach(btn=>{
  btn.addEventListener('click',()=>document.querySelector('.admin-sidebar')?.classList.toggle('open'));
});

document.querySelectorAll('[data-file-trigger]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const input=document.getElementById(btn.getAttribute('data-file-trigger'));
    if(input){
      input.click();
      input.scrollIntoView({behavior:'smooth',block:'center'});
    }
  });
});

// WhatsApp: acompanha a conexão após QR/código sem alterar a VPS.
(() => {
  const pollTargets=document.querySelectorAll('[data-wa-poll-status="1"]');
  if(!pollTargets.length) return;

  let stopped=false;
  const poll=async()=>{
    if(stopped) return;
    try{
      const r=await fetch('index.php?p=whatsapp_rnl&ajax=status',{credentials:'same-origin',headers:{'Accept':'application/json'},cache:'no-store'});
      const data=await r.json();
      const state=(data?.session?.state||'').toLowerCase();
      if(state==='open'||state==='connected'){
        stopped=true;
        window.location.href='index.php?p=whatsapp_rnl';
        return;
      }
    }catch(e){}
    setTimeout(poll,4000);
  };
  setTimeout(poll,3500);

  document.querySelectorAll('[data-wa-qr-timer]').forEach(el=>{
    const created=Number(el.getAttribute('data-wa-qr-timer')||0)*1000;
    const ttl=45000;
    const tick=()=>{
      const left=Math.max(0,Math.ceil((created+ttl-Date.now())/1000));
      if(left>0){
        el.textContent=`QR temporário · aproximadamente ${left}s`;
        setTimeout(tick,1000);
      }else{
        el.textContent='QR pode ter expirado · gere um novo antes de escanear';
        el.classList.add('expired');
      }
    };
    tick();
  });
})();


/* ===== Chat público ===== */
document.addEventListener('DOMContentLoaded',()=>{
  const root=document.querySelector('[data-public-chat]');
  if(!root)return;

  const panel=root.querySelector('[data-chat-panel]');
  const body=root.querySelector('[data-chat-body]');
  const errorBox=root.querySelector('[data-chat-error]');
  const propertyLabel=root.querySelector('[data-chat-property]');
  const toggles=root.querySelectorAll('[data-chat-toggle]');

  let token='';
  let active=false;
  let polling=null;
  let lastMessageId=0;

  const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const showError=(msg='')=>{
    if(!msg){errorBox.hidden=true;errorBox.textContent='';return;}
    errorBox.textContent=msg;errorBox.hidden=false;
  };
  const api=async(action,data={})=>{
    const res=await fetch('chat.php',{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify({action,token,...data})
    });
    const json=await res.json().catch(()=>({ok:false,error:'Resposta inválida do servidor.'}));
    if(!res.ok||!json.ok)throw new Error(json.error||'Falha no atendimento.');
    return json;
  };

  const messageHtml=(m)=>{
    const cls=m.sender==='guest'?'guest':(m.sender==='ai'?'ai':'staff');
    return `<div class="public-chat-message ${cls}" data-message-id="${Number(m.id)||0}">
      <small>${esc(m.label)}</small>
      <p>${esc(m.body).replace(/\n/g,'<br>')}</p>
      <time>${esc(m.created_at)}</time>
    </div>`;
  };

  const renderMessages=(messages=[])=>{
    const wrap=body.querySelector('[data-chat-messages]');
    if(!wrap)return;
    if(!messages.length){
      wrap.innerHTML='<div class="public-chat-empty">Nenhuma mensagem ainda.</div>';
      return;
    }
    const nextLast=Math.max(...messages.map(m=>Number(m.id)||0));
    const shouldScroll=nextLast>lastMessageId;
    wrap.innerHTML=messages.map(messageHtml).join('');
    if(shouldScroll){
      wrap.scrollTop=wrap.scrollHeight;
      lastMessageId=nextLast;
    }
  };

  const renderStart=()=>{
    active=false;
    stopPolling();
    body.innerHTML=`
      <div class="public-chat-intro">
        <h3>Como podemos ajudar?</h3>
        <p>Inicie uma conversa com a pousada. Informe um contato para que a equipe possa continuar o atendimento se necessário.</p>
      </div>
      <form class="public-chat-start" data-chat-start>
        <label>Nome<input name="name" maxlength="160" autocomplete="name" required></label>
        <div class="public-chat-grid">
          <label>E-mail<input name="email" type="email" maxlength="190" autocomplete="email"></label>
          <label>WhatsApp<input name="whatsapp" inputmode="tel" maxlength="20" autocomplete="tel" placeholder="DDD + número"></label>
        </div>
        <label class="public-chat-consent"><input type="checkbox" name="privacy_consent" value="1" required> <span>Autorizo o uso desses dados apenas para este atendimento.</span></label>
        <button type="submit">Iniciar atendimento</button>
      </form>`;
    body.querySelector('[data-chat-start]').addEventListener('submit',startConversation);
  };

  const renderConversation=(messages=[],status='open')=>{
    active=true;
    body.innerHTML=`
      <div class="public-chat-messages" data-chat-messages></div>
      ${status==='open'?`
      <form class="public-chat-compose" data-chat-send>
        <textarea name="body" maxlength="2500" rows="2" placeholder="Digite sua mensagem…" required></textarea>
        <button type="submit" aria-label="Enviar mensagem">Enviar</button>
      </form>
      <div class="public-chat-foot"><button type="button" data-chat-end>Encerrar conversa</button></div>
      `:`<div class="public-chat-ended">Conversa encerrada. <button type="button" data-chat-new>Novo atendimento</button></div>`}
    `;
    renderMessages(messages);
    const send=body.querySelector('[data-chat-send]');
    if(send)send.addEventListener('submit',sendMessage);
    const end=body.querySelector('[data-chat-end]');
    if(end)end.addEventListener('click',closeConversation);
    const fresh=body.querySelector('[data-chat-new]');
    if(fresh)fresh.addEventListener('click',async()=>{await api('close');renderStart();});
    if(status==='open')startPolling();
  };

  const boot=async()=>{
    showError('');
    try{
      const data=await api('boot');
      token=data.token||'';
      if(propertyLabel&&data.property_name)propertyLabel.textContent=data.property_name;
      if(data.conversation?.active)renderConversation(data.messages||[],data.conversation.status||'open');
      else renderStart();
    }catch(e){
      body.innerHTML='<div class="public-chat-empty">Não foi possível carregar o atendimento.</div>';
      showError(e.message);
    }
  };

  async function startConversation(ev){
    ev.preventDefault();showError('');
    const form=ev.currentTarget;
    const fd=new FormData(form);
    const btn=form.querySelector('button[type=submit]');
    btn.disabled=true;btn.textContent='Iniciando…';
    try{
      const data=await api('start',{
        name:fd.get('name')||'',
        email:fd.get('email')||'',
        whatsapp:fd.get('whatsapp')||'',
        privacy_consent:fd.get('privacy_consent')?1:0
      });
      renderConversation(data.messages||[],data.conversation?.status||'open');
    }catch(e){showError(e.message);btn.disabled=false;btn.textContent='Iniciar atendimento';}
  }

  async function sendMessage(ev){
    ev.preventDefault();showError('');
    const form=ev.currentTarget;
    const field=form.querySelector('textarea[name=body]');
    const btn=form.querySelector('button[type=submit]');
    const text=field.value.trim();
    if(!text)return;
    btn.disabled=true;field.disabled=true;
    try{
      const data=await api('send',{body:text});
      field.value='';
      renderMessages(data.messages||[]);
    }catch(e){showError(e.message);}
    finally{btn.disabled=false;field.disabled=false;field.focus();}
  }

  async function poll(){
    if(!active||panel.hidden)return;
    try{
      const data=await api('poll');
      if(!data.conversation?.active){renderStart();return;}
      renderMessages(data.messages||[]);
      if(data.conversation.status!=='open')renderConversation(data.messages||[],data.conversation.status);
    }catch(e){}
  }

  function startPolling(){
    stopPolling();
    polling=setInterval(poll,5000);
  }
  function stopPolling(){
    if(polling){clearInterval(polling);polling=null;}
  }

  async function closeConversation(){
    if(!confirm('Encerrar esta conversa?'))return;
    try{await api('close');renderStart();}catch(e){showError(e.message);}
  }

  toggles.forEach(btn=>btn.addEventListener('click',()=>{
    panel.hidden=!panel.hidden;
    localStorage.setItem('pousadaPublicChatOpen',panel.hidden?'0':'1');
    if(!panel.hidden){
      if(!token)boot(); else if(active)poll();
    }
  }));

  if(localStorage.getItem('pousadaPublicChatOpen')==='1'){
    panel.hidden=false;
  }
  boot();
});
