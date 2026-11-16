<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Services\FolioService;use App\Services\SimplePdf;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireLogin();
if(!Auth::isAdmin()&&!Auth::isSuperAdmin()&&!Auth::can('reservations.manage')){http_response_code(403);exit('Sem autorização para comandas.');}
$rid=(int)($_REQUEST['reservation_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
 Csrf::check();$action=(string)($_POST['action']??'');$actor=(int)Auth::user()['id'];
 try{
  if($action==='add_catalog')FolioService::catalogItem($rid,(int)($_POST['catalog_id']??0),(float)($_POST['qty']??0),$actor);
  elseif($action==='add')FolioService::item($rid,(string)($_POST['description']??''),(float)($_POST['qty']??0),(float)($_POST['unit_price']??-1),$actor);
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
 $catalog=$pdo->query("SELECT id,name,category,unit,price FROM suites_catalog_items WHERE active=1 ORDER BY category,name")->fetchAll();
 $h.='<div style="display:flex;justify-content:flex-end;margin:12px 0"><button class="btn" type="button" data-open-consumption>+ Adicionar consumo</button></div>';
 $h.='<style>.consumption-modal{border:1px solid #b6c9c0;border-radius:12px;padding:0;width:min(540px,calc(100vw - 24px));max-height:90dvh;background:#fff;color:#193c34;box-shadow:0 20px 80px #152f2b66}.consumption-modal::backdrop{background:#0e281f99}.consumption-modal[open]{display:flex;flex-direction:column}.consumption-modal header{display:flex;align-items:center;justify-content:space-between;padding:15px;border-bottom:1px solid #dce9df}.consumption-modal header h2{margin:0;font-size:17px}.consumption-modal form{padding:18px;display:grid;gap:12px;overflow-y:auto}.consumption-modal form label{display:flex;flex-direction:column;gap:5px}.consumption-modal form input,.consumption-modal form select{width:100%;box-sizing:border-box}.consumption-close{padding:5px 12px;border:1px solid #bccdc1;border-radius:8px;background:#fff}</style>';
 $h.='<dialog class="consumption-modal" id="consumptionDialog" aria-label="Adicionar consumo"><header><h2>Adicionar consumo</h2><button type="button" class="consumption-close" data-close-consumption>×</button></header><form method="post">'.Csrf::field().'<input type="hidden" name="reservation_id" value="'.$rid.'"><input type="hidden" name="action" value="add_catalog"><label>Produto ou serviço<select name="catalog_id" required><option value="">Selecione</option>';
 foreach($catalog as $product)$h.='<option value="'.(int)$product['id'].'">'.U::e(($product['category']==='bar'?'Bar':'Serviço').' · '.$product['name'].' ('.money((float)$product['price']).' / '.$product['unit'].')').'</option>';
 $h.='</select></label><label>Quantidade<input type="number" name="qty" min=".001" step=".001" max="10000" value="1" required></label><button type="submit"'.(!$catalog?' disabled':'').'>Adicionar à comanda</button>';
 if(!$catalog)$h.='<p>Nenhum produto cadastrado. <a href="/painel/products_services.php">Cadastrar produtos e serviços</a></p>';
 $h.='</form></dialog><script>(()=>{const d=document.getElementById("consumptionDialog");document.querySelector("[data-open-consumption]").addEventListener("click",()=>d.showModal());d.querySelector("[data-close-consumption]").addEventListener("click",()=>d.close());d.addEventListener("click",e=>{if(e.target===d)d.close()});})();</script>';
}
$h.='<div class=card><h2>Resumo da conta</h2><p>Hospedagem: <b>'.money((float)$r['total']).'</b></p><p>Consumo: <b>'.money($d['consumption']).'</b></p><p>Pagamentos já confirmados: <b>'.money($d['paid']).'</b></p>';
if($closed)$h.='<p>Taxa de serviço: <b>'.money($d['fee']).'</b></p><p>Total: <b>'.money($d['total']).'</b></p><p>Saldo: <b>'.money($d['due']).'</b></p><a class=btn href="/painel/?p=comandas&reservation_id='.$rid.'&export=pdf">Baixar fechamento em PDF</a>';
else $h.='<form method=post>'.Csrf::field().'<input type=hidden name=reservation_id value="'.$rid.'"><input type=hidden name=action value=close>'.row('Aplicar taxa opcional de 10% sobre o consumo','service_fee',0,'checkbox').'<p>A hospedagem e os pagamentos já registrados serão incluídos; a taxa aplica-se apenas ao consumo.</p><button type=submit onclick="return confirm(\'Encerrar esta comanda? Não será possível alterar os lançamentos depois.\')">Fechar conta e gerar demonstrativo</button></form>';
$h.='</div><a href="/painel/?p=comandas">Voltar às comandas</a>';
render('Comanda',$h);exit;
