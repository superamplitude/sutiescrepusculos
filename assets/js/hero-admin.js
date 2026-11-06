(() => {
  'use strict';
  let priorFocus=null;
  const open=(id,trigger)=>{
    const dlg=document.getElementById(id);
    if(!(dlg instanceof HTMLDialogElement))return;
    priorFocus=trigger;
    if(typeof dlg.showModal==='function')dlg.showModal();
    else dlg.setAttribute('open','');
    dlg.querySelector('[data-hero-close]')?.focus();
  };
  const close=dlg=>{
    if(!dlg)return;
    if(typeof dlg.close==='function')dlg.close();
    else dlg.removeAttribute('open');
    priorFocus?.focus();
  };
  document.addEventListener('click',e=>{
    const trigger=e.target.closest('[data-hero-open]');
    if(trigger){e.preventDefault();open(trigger.getAttribute('data-hero-open'),trigger);return;}
    const dismiss=e.target.closest('[data-hero-close]');
    if(dismiss){e.preventDefault();close(dismiss.closest('.hero-admin-dialog'));}
  });
  document.querySelectorAll('dialog.hero-admin-dialog').forEach(dlg=>{
    dlg.addEventListener('click',e=>{if(e.target===dlg)close(dlg);});
    dlg.addEventListener('close',()=>{priorFocus?.focus();});
  });
})();
