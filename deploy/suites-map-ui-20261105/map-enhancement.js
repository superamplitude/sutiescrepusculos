(() => {
  'use strict';
  const init = () => {
    const modal = document.getElementById('mapReservationModal');
    const blockForm = document.getElementById('blockForm');
    const preForm = document.getElementById('mapPreReservationForm');
    const nav = document.querySelector('.mapnav');
    const toolbar = document.querySelector('.map-toolbar');
    if (!modal || !blockForm || !preForm || !nav || !toolbar) return;
    if (document.querySelector('.map-unified-toolbar')) return;

    const unified = document.createElement('div');
    unified.className = 'map-unified-toolbar';
    nav.before(unified);
    unified.append(nav, toolbar);

    const panel = modal.querySelector('.map-reservation-dialog');
    const header = modal.querySelector('.map-reservation-header');
    const title = document.getElementById('mapModalTitle');
    const subtitle = document.getElementById('mapModalSubtitle');
    const room = document.getElementById('mapRoom');
    const start = blockForm.elements.namedItem('start_date');
    const end = blockForm.elements.namedItem('end_date');
    const type = blockForm.elements.namedItem('type');
    const reason = blockForm.elements.namedItem('reason');
    const close = header.querySelector('[data-map-close]');
    const tabs = document.createElement('div');
    tabs.className = 'map-period-modes';
    tabs.setAttribute('role', 'group');
    tabs.setAttribute('aria-label', 'Tipo de período');
    const names = {reservation: 'Pré-reserva', blocked: 'Bloqueio', maintenance: 'Manutenção'};
    for (const [value, label] of Object.entries(names)) {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.periodMode = value;
      button.textContent = label;
      button.setAttribute('aria-pressed', value === 'reservation' ? 'true' : 'false');
      tabs.append(button);
    }
    header.after(tabs);
    blockForm.classList.remove('card');
    blockForm.classList.add('map-inline-block-form');
    blockForm.querySelector('h2')?.remove();
    const description = blockForm.querySelector('p');
    if (description) description.textContent = 'Confira a unidade e o período selecionados no mapa antes de gravar.';
    const formClose = document.createElement('button');
    formClose.type = 'button';
    formClose.className = 'btn secondary';
    formClose.textContent = 'Fechar';
    formClose.addEventListener('click', () => close.click());
    const actions = document.createElement('div');
    actions.className = 'map-modal-actions';
    actions.append(blockForm.querySelector('button[type="submit"], button:not([type])'), formClose);
    blockForm.append(actions);
    blockForm.hidden = true;
    panel.append(blockForm);
    let currentMode = 'reservation';
    let isNew = false;
    let opener = null;
    const setMode = mode => {
      const previousMode = currentMode;
      currentMode = mode;
      const blocking = mode !== 'reservation';
      preForm.hidden = blocking;
      blockForm.hidden = !blocking;
      tabs.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b.dataset.periodMode === mode ? 'true' : 'false'));
      if (blocking) {
        if (previousMode === 'reservation') {
          room.value = document.getElementById('mapModalRoomId').value;
          start.value = document.getElementById('mapModalCheckin').value;
          end.value = document.getElementById('mapModalCheckout').value;
        }
        type.value = mode;
        title.textContent = mode === 'maintenance' ? 'Manutenção' : 'Bloqueio';
        subtitle.textContent = 'Defina o período sem sair do mapa.';
        reason?.focus();
      } else {
        title.textContent = 'Nova pré-reserva';
        subtitle.textContent = 'Preencha os dados sem sair do mapa.';
      }
    };
    tabs.addEventListener('click', event => {
      const button = event.target.closest('[data-period-mode]');
      if (button) setMode(button.dataset.periodMode);
    });
    type.addEventListener('change', () => { if (currentMode !== 'reservation') setMode(type.value); });
    const observeModal = new MutationObserver(records => {
      if (!records.some(r => r.attributeName === 'hidden')) return;
      if (modal.hidden) {
        blockForm.hidden = true;
        opener?.focus();
        return;
      }
      opener = document.activeElement;
      isNew = !preForm.hidden;
      tabs.hidden = !isNew;
      blockForm.hidden = true;
      if (isNew) {
        blockForm.reset();
        setMode('reservation');
      }
    });
    observeModal.observe(modal, {attributes: true, attributeFilter: ['hidden']});
    // Existing app opens the reservation view first; block mode shares its selected range.
    document.addEventListener('keydown', event => {
      if (event.key !== 'Tab' || modal.hidden) return;
      const focusable = [...panel.querySelectorAll('button,input,select,textarea,a[href]')].filter(e => !e.disabled && e.getClientRects().length);
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {event.preventDefault(); last?.focus();}
      else if (!event.shiftKey && document.activeElement === last) {event.preventDefault(); first?.focus();}
    });

    // Existing blocked cells retain their original edit route and server-side permissions.
    const blockLinks = [...document.querySelectorAll('.map-block-link[href]')];
    if (!blockLinks.length) return;
    const dialog = document.createElement('dialog');
    dialog.className = 'map-block-editor';
    const editorHeader = document.createElement('header');
    const editorTitle = document.createElement('h2');
    editorTitle.textContent = 'Bloqueio ou manutenção';
    const dismiss = document.createElement('button');
    dismiss.type = 'button'; dismiss.className = 'map-modal-close';
    dismiss.textContent = '×'; dismiss.setAttribute('aria-label', 'Fechar bloqueio ou manutenção');
    editorTitle.id = 'mapBlockEditorTitle'; dialog.setAttribute('aria-labelledby', editorTitle.id);
    editorHeader.append(editorTitle, dismiss);
    const frame = document.createElement('iframe');
    frame.title = 'Editar bloqueio ou manutenção';
    dialog.append(editorHeader, frame); document.body.append(dialog);
    let edited = false;
    let blockOpener = null;
    dismiss.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
      document.body.classList.remove('map-modal-open');
      blockOpener?.focus();
      if (edited) window.location.reload();
    });
    dialog.addEventListener('click', event => {if (event.target === dialog) {const r = dialog.getBoundingClientRect();if(event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();}});
    frame.addEventListener('load', () => {
      try {
        const doc = frame.contentDocument;
        if (!doc) return;
        if (new URL(frame.contentWindow.location.href).searchParams.get('p') === 'map') {edited = true; dialog.close(); return;}
        const style = doc.createElement('style');
        style.textContent = '.admin-sidebar,.sidebar,.admin-topbar{display:none!important}.admin-shell{margin-left:0!important}.admin-main{margin:0!important;padding:16px!important;min-height:0!important}.page-head{display:none!important}';
        doc.head.append(style);
        doc.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {edited = true;}));
      } catch (_) { /* Keep the existing page visible; never bypass authentication. */ }
    });
    blockLinks.forEach(link => link.addEventListener('click', event => {
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin || url.searchParams.get('p') !== 'block_edit') return;
      event.preventDefault();
      blockOpener = link; edited = false; frame.src = url.href;
      dialog.showModal(); document.body.classList.add('map-modal-open'); dismiss.focus();
    }));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

