(() => {
  'use strict';
  let lastTrigger=null;
  const nativeDialog=(dialog)=>dialog instanceof HTMLDialogElement && typeof dialog.showModal==='function';
  const open=(dialog,trigger=null)=>{
    if(!nativeDialog(dialog))return false;
    lastTrigger=trigger;
    try{
      // A server-rendered open attribute keeps the form available even if JS is blocked.
      // Promote that fallback to a true overlay when the controller is loaded.
      if(dialog.open && !dialog.matches(':modal'))dialog.close();
      if(!dialog.open)dialog.showModal();
      dialog.querySelector('[data-room-close]')?.focus();
      return true;
    }catch(error){
      if(!dialog.open)dialog.setAttribute('open','');
      return dialog.open;
    }
  };
  const close=(dialog)=>{
    if(!dialog)return false;
    if(typeof dialog.close==='function'&&dialog.open)dialog.close();
    else dialog.removeAttribute('open');
    if(lastTrigger&&document.contains(lastTrigger))lastTrigger.focus();
    return true;
  };
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-room-open]');
    if(trigger){
      const dialog=document.getElementById(trigger.getAttribute('data-room-open'));
      // If JavaScript or the dialog API is unavailable, let the real link navigate
      // to a server-rendered, visible edit form instead of doing nothing.
      if(nativeDialog(dialog)){
        event.preventDefault();
        open(dialog,trigger);
      }
      return;
    }
    const dismiss=event.target.closest('[data-room-close]');
    if(dismiss){
      const dialog=dismiss.closest('dialog.room-admin-dialog');
      if(nativeDialog(dialog)){
        event.preventDefault();
        close(dialog);
      }
    }
  });
  document.querySelectorAll('dialog.room-admin-dialog').forEach(dialog=>{
    dialog.addEventListener('click',event=>{if(event.target===dialog)close(dialog);});
    dialog.addEventListener('close',()=>{if(lastTrigger&&document.contains(lastTrigger))lastTrigger.focus();});
  });
  const requested=document.querySelector('dialog.room-admin-dialog[data-room-auto="1"]');
  if(requested)open(requested);
})();
