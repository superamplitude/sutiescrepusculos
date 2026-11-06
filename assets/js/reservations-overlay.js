(()=>{
 'use strict';
 const dialog=document.getElementById('reservationOverlay');
 const frame=dialog?.querySelector('.reservation-overlay-frame');
 const canUse=()=>dialog instanceof HTMLDialogElement&&typeof dialog.showModal==='function'&&frame;
 let source=null;
 document.addEventListener('click',event=>{
  const target=event.target.closest('[data-reservation-open]');
  if(target){
   if(!canUse())return; // Preserve real link when dialog unavailable.
   let u;
   try{u=new URL(target.getAttribute('href'),window.location.href);}
   catch(err){return;}
   if(u.origin!==window.location.origin||u.searchParams.get('p')!=='reservation_edit'||!/^[0-9]+$/.test(u.searchParams.get('id')||''))return;
   event.preventDefault();
   u.searchParams.set('embed','1');
   source=target;
   frame.src=u.href;
   if(!dialog.open)dialog.showModal();
   dialog.querySelector('[data-reservation-close]')?.focus();
   return;
  }
  if(event.target.closest('[data-reservation-close]')){
   if(canUse())dialog.close();
  }
 });
 dialog?.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
 dialog?.addEventListener('close',()=>{
  if(frame)frame.removeAttribute('src');
  if(source&&document.contains(source))source.focus();
  // Refresh after the overlay closes so edits made in the iframe appear in the list.
  if(source)window.location.reload();
 });
})();
