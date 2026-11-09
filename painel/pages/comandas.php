<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Services\FolioService;use App\Services\SimplePdf;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireLogin();
if(!Auth::isAdmin()&&!Auth::isSuperAdmin()&&!Auth::can('reservations.manage')){http_response_code(403);exit('Sem autorização para comandas.');}
$rid=(int)($_REQUEST['reservation_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
 Csrf::check();$action=(string)($_POST['action']??'');$actor=(int)Auth::user()['id'];
 try{
  if($action==='add')FolioService::item($rid,(string)($_POST['description']??''),(float)($_POST['qty']??0),(float)($_POST['unit_price']??-1),$actor);
  elseif($action==='void')FolioService::voidItem($rid,(int)($_POST['item_id']??0),$actor);
  elseif($action==='close')FolioService::close($rid,!empty($_POST['service_fee']),$actor);
  else throw new RuntimeException('Operação inválida.');
  Audit::log('folio.'.$action,'reservation',$rid,['actor'=>$actor]);U::flash('ok','Comanda atualizada.');
 }catch(Throwable $e){U::flash('err',$e->getMessage());}
 U::redirect('/painel/?p=comandas&reservation_id='.$rid);
}
if($rid<=0){
 $q=$pdo->query("SELECT r.id,r.code,g.name guest,rm.name room,r.checkin,r.checkout,cl.closed_at FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id LEFT JOIN suites_folio_closures cl ON cl.reservation_id=r.id ORDER BY (cl.id IS NOT NULL),r.id DESC LIMIT 200");
 $h=adminTitle('Comandas por apartamento','Consumo e fechamento vinculados à reserva.').'<div class=card><table><tr><th>Reserva</th><th>Apartamento</th><th>Hóspede</th><th>Período</th><th>Comanda</th></tr>';
 foreach($q as $r)$h.='<tr><td>'.U::e($r['code']).'</td><td>'.U::e($r['room']).'</td><td>'.U::e($r['guest']).'</td><td>'.U::e($r['checkin']).' a '.U::e($r['checkout']).'</td><td><a class="btn secondary small" href="/painel/?p=comandas&reservation_id='.(int)$r['id'].'">'.($r['closed_at']?'Ver fechamento':'Abrir comanda').'</a></td></tr>';
 render('Comandas',$h.'</table></div>');exit;
}
$d=FolioService::detail($rid);$r=$d['reservation'];$closed=(bool)$d['close'];
if(($_GET['export']??'')==='pdf'){
 if(!$closed)throw new RuntimeException('Feche a comanda antes de gerar o PDF definitivo.');
 SimplePdf::download('fechamento-'.$r['code'].'.pdf','Fechamento de conta',FolioService::pdfLines($d));
}
$h=adminTitle('Comanda · '.U::e($r['room']),'Reserva '.U::e($r['code']).' · '.U::e($r['guest']).' · '.U::e($r['checkin']).' a '.U::e($r['checkout']));
$h.='<div class="card"><p><b>Situação:</b> '.($closed?'Encerrada em '.U::e($d['close']['closed_at']):'Aberta').'</p>';
$h.='<table><tr><th>Produto / serviço</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th></th></tr>';
foreach($d['items'] as $item){
 $void=$item['voided_at']!==null;
 $h.='<tr><td>'.U::e($item['description']).($void?' (cancelado)':'').'</td><td>'.U::e($item['qty']).'</td><td>'.money((float)$item['unit_price']).'</td><td>'.($void?'—':money((float)$item['line_total'])).'</td><td>';
 if(!$void&&!$closed)$h.='<form method=post>'.Csrf::field().'<input type=hidden name=reservation_id value="'.$rid.'"><input type=hidden name=action value=void><input type=hidden name=item_id value="'.(int)$item['id'].'"><button class="secondary small">Cancelar item</button></form>';
 $h.='</td></tr>';
}
$h.='</table></div>';
if(!$closed){
 $h.='<form class=card method=post>'.Csrf::field().'<input type=hidden name=reservation_id value="'.$rid.'"><input type=hidden name=action value=add><h2>Adicionar consumo</h2><div class=grid3>'.row('Produto ou serviço','description').row('Quantidade','qty','1','number').row('Valor unitário (R$)','unit_price','0.00','number').'</div><button>Adicionar à comanda</button></form>';
}
$h.='<div class=card><h2>Resumo da conta</h2><p>Hospedagem: <b>'.money((float)$r['total']).'</b></p><p>Consumo: <b>'.money($d['consumption']).'</b></p><p>Pagamentos já confirmados: <b>'.money($d['paid']).'</b></p>';
if($closed)$h.='<p>Taxa de serviço: <b>'.money($d['fee']).'</b></p><p>Total: <b>'.money($d['total']).'</b></p><p>Saldo: <b>'.money($d['due']).'</b></p><a class=btn href="/painel/?p=comandas&reservation_id='.$rid.'&export=pdf">Baixar fechamento em PDF</a>';
else $h.='<form method=post>'.Csrf::field().'<input type=hidden name=reservation_id value="'.$rid.'"><input type=hidden name=action value=close>'.row('Aplicar taxa opcional de 10% sobre o consumo','service_fee',0,'checkbox').'<p>A hospedagem e os pagamentos já registrados serão incluídos; a taxa aplica-se apenas ao consumo.</p><button type=submit onclick="return confirm(\'Encerrar esta comanda? Não será possível alterar os lançamentos depois.\')">Fechar conta e gerar demonstrativo</button></form>';
$h.='</div><a href="/painel/?p=comandas">Voltar às comandas</a>';
render('Comanda',$h);exit;
