<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('calendar.block');$id=(int)($_GET['id']??0);
  $q=$pdo->prepare("SELECT b.*,r.name room_name FROM room_blocks b JOIN rooms r ON r.id=b.room_id WHERE b.id=?");$q->execute([$id]);$x=$q->fetch();
  if(!$x){http_response_code(404);render('Bloqueio','<div class="alert error">Bloqueio não encontrado.</div>');exit;}
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$action=$_POST['action']??'save';
    try{
      if($action==='delete'){
        $pdo->prepare("DELETE FROM room_blocks WHERE id=?")->execute([$id]);Audit::log('calendar.block.delete','room_block',$id);U::flash('ok','Bloqueio removido.');U::redirect('index.php?p=map&start='.urlencode($x['start_date']));
      }
      $room=(int)($_POST['room_id']??0);$a=(string)($_POST['start_date']??'');$b=(string)($_POST['end_date']??'');$type=in_array($_POST['type']??'', ['blocked','maintenance'],true)?$_POST['type']:'blocked';
      if(!$room||!$a||!$b||$b<=$a)throw new RuntimeException('Informe unidade e período válidos.');
      $c=$pdo->prepare("SELECT COUNT(*) FROM reservations WHERE room_id=? AND status NOT IN('cancelada','recusada','expirada','reembolsada','concluida','no_show') AND NOT(checkout<=? OR checkin>=?)");$c->execute([$room,$a,$b]);if((int)$c->fetchColumn()>0)throw new RuntimeException('Existe reserva ativa nesse período.');
      $c=$pdo->prepare("SELECT COUNT(*) FROM room_blocks WHERE room_id=? AND id<>? AND NOT(end_date<=? OR start_date>=?)");$c->execute([$room,$id,$a,$b]);if((int)$c->fetchColumn()>0)throw new RuntimeException('Existe outro bloqueio nesse período.');
      $pdo->prepare("UPDATE room_blocks SET room_id=?,start_date=?,end_date=?,type=?,reason=? WHERE id=?")->execute([$room,$a,$b,$type,trim((string)($_POST['reason']??'')),$id]);Audit::log('calendar.block.update','room_block',$id);U::flash('ok','Bloqueio atualizado.');U::redirect('index.php?p=block_edit&id='.$id);
    }catch(Throwable $e){U::flash('err',$e->getMessage());U::redirect('index.php?p=block_edit&id='.$id);}
  }
  $rooms=$pdo->query("SELECT id,name FROM rooms WHERE active=1 ORDER BY name")->fetchAll();
  $h=adminTitle('Editar período','Altere ou remova este bloqueio/manutenção do mapa.');
  $h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=action value=save><div class=grid3><label>Unidade<select name=room_id>';foreach($rooms as $r)$h.='<option value="'.$r['id'].'" '.((int)$x['room_id']===(int)$r['id']?'selected':'').'>'.U::e($r['name']).'</option>';$h.='</select></label>'.row('Início','start_date',$x['start_date'],'date').row('Fim','end_date',$x['end_date'],'date').'<label>Tipo<select name=type><option value=blocked '.($x['type']==='blocked'?'selected':'').'>Bloqueio</option><option value=maintenance '.($x['type']==='maintenance'?'selected':'').'>Manutenção</option></select></label>'.row('Motivo','reason',$x['reason']).'</div><div class=map-editor-actions><button>Salvar alterações</button><a class="btn secondary" href="index.php?p=map&start='.U::e($x['start_date']).'">Voltar ao mapa</a></div></form>';
  $h.='<form method=post class=card onsubmit="return confirm(\'Remover este período do mapa?\')">'.Csrf::field().'<input type=hidden name=action value=delete><h3>Remover do mapa</h3><p class=muted>Esta ação exclui apenas o bloqueio/manutenção selecionado.</p><button class=danger>Excluir período</button></form>';
  render('Editar período',$h);exit;
