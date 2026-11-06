(()=>{
 'use strict';
 let lastTrigger=null;
 const modal=(dialog)=>dialog instanceof HTMLDialogElement&&typeof dialog.showModal==='function';
 const open=(dialog,trigger)=>{
  if(!modal(dialog))return false;
  lastTrigger=trigger||lastTrigger;
  try{
   if(dialog.open&&!dialog.matches(':modal'))dialog.close();
   if(!dialog.open)dialog.showModal();
   dialog.querySelector('[data-gallery-close]')?.focus();
   return true;
  }catch(err){
   if(!dialog.open)dialog.setAttribute('open','');
   return dialog.open;
  }
 };
 const close=dialog=>{
  if(!dialog)return;
  if(dialog.open&&typeof dialog.close==='function')dialog.close();
  else dialog.removeAttribute('open');
  if(lastTrigger&&document.contains(lastTrigger))lastTrigger.focus();
 };
 document.addEventListener('click',event=>{
  const trigger=event.target.closest('[data-gallery-open]');
  if(trigger){
   const dialog=document.getElementById(trigger.getAttribute('data-gallery-open'));
   if(modal(dialog)){event.preventDefault();open(dialog,trigger);}
   return;
  }
  const dismiss=event.target.closest('[data-gallery-close]');
  if(dismiss){
   const dialog=dismiss.closest('dialog.gallery-admin-dialog');
   if(modal(dialog)){event.preventDefault();close(dialog);}
  }
 });
 document.querySelectorAll('dialog.gallery-admin-dialog').forEach(dialog=>{
  dialog.addEventListener('click',event=>{if(event.target===dialog)close(dialog);});
  dialog.addEventListener('close',()=>{if(lastTrigger&&document.contains(lastTrigger))lastTrigger.focus();});
 });
 const auto=document.querySelector('dialog.gallery-admin-dialog[data-gallery-auto="1"]');
 if(auto)open(auto);
})();
