<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('reservations.manage');$start=$_GET['start']??date('Y-m-d');$days=15;$d0=new DateTimeImmutable($start);$rooms=$pdo->query("SELECT * FROM rooms WHERE active=1 AND status='active' ORDER BY name")->fetchAll();
  $end=$d0->modify("+$days days")->format('Y-m-d');$q=$pdo->prepare("SELECT r.*,g.name guest FROM reservations r JOIN guests g ON g.id=r.guest_id WHERE r.checkout>? AND r.checkin<? AND r.status NOT IN('cancelada','recusada','expirada')");$q->execute([$start,$end]);$res=$q->fetchAll();
  $q=$pdo->prepare("SELECT * FROM room_blocks WHERE end_date>? AND start_date<?");$q->execute([$start,$end]);$blocks=$q->fetchAll();
  $weekday=['Sun'=>'Dom','Mon'=>'Seg','Tue'=>'Ter','Wed'=>'Qua','Thu'=>'Qui','Fri'=>'Sex','Sat'=>'Sáb'];
  $h=adminTitle('Mapa de Reservas','Clique em um dia livre para abrir a janela de pré-reserva. Arraste para selecionar várias diárias. Reservas existentes também abrem em janela sobreposta.');
  $h.='<div class=mapnav><a class=btn href="index.php?p=map&start='.$d0->modify('-15 days')->format('Y-m-d').'">← 15 dias</a><b>'.U::e($start).'</b><a class=btn href="index.php?p=map&start='.$d0->modify('+15 days')->format('Y-m-d').'">15 dias →</a></div>';
  $h.='<div class=map-toolbar><a class=btn href="index.php?p=reservation_new">+ Reserva</a><a class="btn secondary" href="index.php?p=rooms&view=new">+ Unidade</a><a class="btn secondary" href="index.php?p=rooms">Acomodações</a><a class="btn secondary" href="index.php?p=reservations">Reservas</a><a class="btn secondary" href="index.php?p=ai">Automação</a></div>';
  $h.='<p class=map-help><b>Como usar:</b> clique em um dia livre para abrir a pré-reserva ou arraste sobre vários dias da mesma unidade. Depois de criar a pré-reserva, você pode efetivar a reserva ou cancelá-la sem sair do mapa.</p>';
  if(!$rooms)$h.='<div class="alert error">Cadastre e publique pelo menos uma unidade para usar o mapa.</div>';
  $h.='<div class=calendar><table><tr><th>Unidade</th>';
  $dates=[];for($i=0;$i<$days;$i++){$d=$d0->modify("+$i days");$dates[]=$d->format('Y-m-d');$h.="<th><small>".$d->format('d/m')."</small><br>".$weekday[$d->format('D')]."</th>";}$h.="</tr>";
  foreach($rooms as $room){
    $h.='<tr><th><a class="map-room-name" href="index.php?p=rooms&id='.(int)$room['id'].'" title="Editar unidade">'.U::e($room['name']).'</a></th>';
    foreach($dates as $date){
      $state='available';$txt='';$title='Disponível';$reservationId=0;$blockId=0;
      foreach($blocks as $b)if((int)$b['room_id']===(int)$room['id']&&$date>=$b['start_date']&&$date<$b['end_date']){$state=$b['type']==='maintenance'?'maintenance':'blocked';$txt=$b['type']==='maintenance'?'M':'B';$title=$b['reason']?:statusPt($state);$blockId=(int)$b['id'];}
      foreach($res as $r)if((int)$r['room_id']===(int)$room['id']&&$date>=$r['checkin']&&$date<$r['checkout']){$state=$r['status'];$txt=mb_substr($r['guest'],0,10);$title=$r['guest'].' — '.statusPt($r['status']);$reservationId=(int)$r['id'];$blockId=0;}
      if($reservationId){
        $content='<a class="map-reservation-link" data-map-reservation="'.$reservationId.'" href="index.php?p=reservation_edit&id='.$reservationId.'" title="'.U::e($title).' · clique para editar">'.U::e($txt).'</a>';
        $h.='<td class="day '.U::e($state).'" data-room="'.(int)$room['id'].'" data-date="'.U::e($date).'" data-reservation="'.$reservationId.'">'.$content.'</td>';
      }elseif($blockId){
        $content='<a class="map-block-link" href="index.php?p=block_edit&id='.$blockId.'" title="'.U::e($title).' · clique para editar">'.U::e($txt).'</a>';
        $h.='<td class="day '.U::e($state).'" data-room="'.(int)$room['id'].'" data-date="'.U::e($date).'" data-block="'.$blockId.'">'.$content.'</td>';
      }else{
        $h.='<td class="day available selectable" role="button" tabindex="0" title="Disponível · selecione para cadastrar" data-room="'.(int)$room['id'].'" data-room-name="'.U::e($room['name']).'" data-date="'.U::e($date).'"></td>';
      }
    }
    $h.='</tr>';
  }
  $h.='</table></div><div id=selectionInfo class="selection-info">Clique em um dia livre para iniciar uma pré-reserva.</div>';
  $h.='<div class=map-editor-actions><button type="button" id=clearMapSelection class="btn secondary">Limpar seleção</button></div>';

  $h.='<div id="mapReservationModal" class="map-reservation-modal" hidden aria-hidden="true">';
  $h.='<div class="map-reservation-backdrop" data-map-close></div><section class="map-reservation-dialog" role="dialog" aria-modal="true" aria-labelledby="mapModalTitle">';
  $h.='<header class="map-reservation-header"><div><span class="eyebrow">MAPA DE DIÁRIAS</span><h2 id="mapModalTitle">Pré-reserva</h2><p id="mapModalSubtitle" class="muted">Preencha os dados sem sair do mapa.</p></div><button type="button" class="map-modal-close" data-map-close aria-label="Fechar">×</button></header>';
  $h.='<div id="mapModalAlert" class="map-modal-alert" hidden></div>';
  $h.='<form id="mapPreReservationForm" class="map-modal-form">'.Csrf::field().'<input type="hidden" name="action" value="create_pre"><input type="hidden" name="room_id" id="mapModalRoomId">';
  $h.='<div class="map-modal-grid"><label>Unidade<input id="mapModalRoomName" readonly></label><label>Entrada<input type="date" name="checkin" id="mapModalCheckin" required></label><label>Saída<input type="date" name="checkout" id="mapModalCheckout" required></label>'.row('Hóspedes','guests',2,'number').row('Nome do hóspede','guest_name').row('Documento','document').row('Telefone','phone').row('WhatsApp','whatsapp').row('E-mail','email','', 'email').'<div class="wide">'.row('Observações','notes','','textarea').'</div></div>';
  $h.='<div class="map-modal-actions"><button type="submit">Criar pré-reserva</button><button type="button" class="btn secondary" data-map-close>Fechar</button></div></form>';

  $h.='<section id="mapReservationResult" class="map-reservation-result" hidden><div id="mapReservationSummary"></div><div class="map-modal-actions"><button type="button" id="mapProceedReservation">Efetuar reserva</button><a class="btn secondary" id="mapEditReservation" href="#">Editar detalhes</a><button type="button" class="btn danger" id="mapCancelReservation">Cancelar pré-reserva</button><button type="button" class="btn secondary" data-map-close>Fechar</button></div></section>';

  $h.='<form id="mapConfirmReservationForm" class="map-confirm-form" hidden>'.Csrf::field().'<input type="hidden" name="action" value="confirm"><input type="hidden" name="reservation_id" id="mapConfirmReservationId"><h3>Efetuar reserva</h3><p class="muted">A pré-reserva será convertida em reserva confirmada. O saldo não pago continuará pendente.</p><div class="grid2"><label>Meio de pagamento<select name="payment_method"><option value="a_definir">A definir</option><option value="pix">PIX</option><option value="dinheiro">Dinheiro</option><option value="debito">Débito</option><option value="credito">Crédito</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></label><label>Valor já pago<input type="number" name="amount_paid" min="0" step="0.01" value="0" inputmode="decimal"></label></div><div class="map-modal-actions"><button type="submit">Confirmar reserva</button><button type="button" class="btn secondary" id="mapBackToPreReservation">Voltar</button></div></form>';
  $h.='</section></div>';

  $h.='<form id=blockForm method=post action="index.php?p=map_action" class=card>'.Csrf::field().'<h2>Bloqueio ou manutenção</h2><p class=muted>Use este formulário somente para bloqueios/manutenção. A reserva agora é feita pela janela sobreposta do mapa.</p><div class=grid3><label>Quarto<select id=mapRoom name=room_id required><option value="">Selecione</option>';foreach($rooms as $r)$h.="<option value={$r['id']}>".U::e($r['name'])."</option>";$h.='</select></label>'.row('Início','start_date','', 'date').row('Fim','end_date','', 'date').'<label>Tipo<select name=type><option value=blocked>Bloqueio</option><option value=maintenance>Manutenção</option></select></label>'.row('Motivo','reason').'</div><button>Gravar período</button></form>';
  $h.= <<<'SUITES_MAP_UI_20261105'
<!-- suites-map-ui-20261105 -->
<style>.map-unified-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:nowrap;overflow-x:auto;padding-bottom:3px;margin:15px 0}
.map-unified-toolbar .mapnav,.map-unified-toolbar .map-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;flex:0 0 auto;margin:0}
.map-unified-toolbar .mapnav{padding-right:12px;border-right:1px solid #c8d9d7}
.map-unified-toolbar .btn,.map-unified-toolbar b{white-space:nowrap}
.map-period-modes{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.map-period-modes[hidden],.map-inline-block-form[hidden]{display:none!important}
.map-period-modes button{background:#e7f3f2;color:#075f59;border:1px solid transparent}
.map-period-modes button[aria-pressed="true"]{background:#0b8178;color:#fff;border-color:#075f59}
.map-period-modes button:focus-visible{outline:3px solid #183a37;outline-offset:2px}
.map-inline-block-form{margin:0;padding:0;border:0;box-shadow:none}
.map-block-editor{width:min(940px,96vw);max-width:96vw;height:min(800px,92vh);max-height:92vh;padding:0;border:1px solid #506966;border-radius:16px;background:white;box-shadow:0 28px 80px #0005;overflow:hidden}
.map-block-editor::backdrop{background:rgba(9,31,29,.72)}
.map-block-editor>header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid #d9e2e0}
.map-block-editor>header h2{margin:0;font-size:1.25rem}
.map-block-editor iframe{display:block;width:100%;height:calc(100% - 74px);border:0}
@media(max-width:600px){.map-unified-toolbar{gap:8px}.map-unified-toolbar .btn{padding:10px 12px}.map-inline-block-form .grid3{grid-template-columns:1fr}.map-block-editor{width:96vw;height:94vh;max-height:94vh}}

</style>
<script>(() => {
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

</script>
SUITES_MAP_UI_20261105;
  $h.='<link rel="stylesheet" href="assets/css/map-workspace.css?v=20261105"><script defer src="assets/js/map-workspace.js?v=20261105"></script>';
  render('Mapa de Reservas',$h);exit;
