(() => {
  'use strict';
  let lastTrigger=null;
  const open=(id,button)=>{
    const dlg=document.getElementById(id);
    if(!(dlg instanceof HTMLDialogElement))return;
    lastTrigger=button;
    if(!dlg.open)dlg.showModal();
    dlg.querySelector('[data-room-close]')?.focus();
  };
  const close=dlg=>{
    if(!(dlg instanceof HTMLDialogElement))return;
    if(dlg.open)dlg.close();
    if(lastTrigger && document.contains(lastTrigger))lastTrigger.focus();
  };
  document.addEventListener('click',event=>{
    const button=event.target.closest('[data-room-open]');
    if(button){event.preventDefault();open(button.getAttribute('data-room-open'),button);return;}
    const dismiss=event.target.closest('[data-room-close]');
    if(dismiss){event.preventDefault();close(dismiss.closest('dialog.room-admin-dialog'));}
  });
  document.querySelectorAll('dialog.room-admin-dialog').forEach(dialog=>{
    dialog.addEventListener('click',event=>{if(event.target===dialog)close(dialog);});
    dialog.addEventListener('close',()=>{if(lastTrigger && document.contains(lastTrigger))lastTrigger.focus();});
  });
})();
