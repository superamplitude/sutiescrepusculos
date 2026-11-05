(() => {
  'use strict';
  if (!document.getElementById('mapReservationModal')) return;
  document.body.classList.add('suites-map-workspace');
  const dialog = document.createElement('dialog');
  dialog.className = 'suites-map-editor';
  dialog.setAttribute('aria-labelledby', 'suitesMapEditorTitle');
  const header = document.createElement('header');
  const title = document.createElement('h2');
  title.id = 'suitesMapEditorTitle';
  const close = document.createElement('button');
  close.type = 'button'; close.textContent = '×'; close.setAttribute('aria-label', 'Fechar janela');
  const frame = document.createElement('iframe');
  frame.title = 'Editor do mapa de reservas';
  header.append(title, close); dialog.append(header, frame); document.body.append(dialog);
  let opener = null, changed = false;
  const names = {reservation_new: 'Cadastrar reserva', reservation_edit: 'Editar reserva', rooms: 'Acomodações', reservations: 'Lista de reservas', ai: 'Automação do atendimento'};
  const embeddedStyle = '.admin-sidebar,.admin-topbar,.public-topline,.public-header,.site-footer{display:none!important}.admin-shell,.admin-main,.admin-content{margin-left:0!important;width:auto!important;min-height:0!important}.admin-main,.admin-content{padding:14px!important}.page-head{margin-bottom:12px!important}.page-head h1{font-size:20px!important}.page-head p{display:none}.card{padding:14px!important;margin-bottom:12px!important}input:not([type=checkbox]):not([type=radio]):not([type=file]),select{height:36px!important;min-height:36px!important;padding:6px 9px!important;font-size:14px!important}textarea{min-height:72px!important;padding:8px!important}button,.btn{min-height:32px!important;padding:6px 10px!important;font-size:13px!important}.grid2,.grid3{gap:12px!important}';
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    if (changed) { window.location.reload(); return; }
    frame.removeAttribute('src'); opener?.focus();
  });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const r = dialog.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
  });
  frame.addEventListener('load', () => {
    if (!dialog.open) return;
    try {
      const doc = frame.contentDocument;
      const url = new URL(frame.contentWindow.location.href);
      if (url.origin !== location.origin || !doc) return;
      if (url.searchParams.get('p') === 'map') { changed = true; dialog.close(); return; }
      const style = doc.createElement('style'); style.textContent = embeddedStyle; doc.head.append(style);
      doc.addEventListener('submit', () => { changed = true; }, true);
    } catch (_) { /* Authentication and original page errors remain visible inside the editor. */ }
  });
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('.map-toolbar a[href],.map-room-name[href],#mapEditReservation[href]');
    if (!link) return;
    const url = new URL(link.href, location.href), page = url.searchParams.get('p');
    if (url.origin !== location.origin || !Object.hasOwn(names, page)) return;
    event.preventDefault(); opener = link; changed = false;
    title.textContent = page === 'rooms' && url.searchParams.get('view') === 'new' ? 'Nova unidade' : names[page];
    frame.title = title.textContent; dialog.showModal(); frame.src = url.href; close.focus();
  });
})();
