<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireLogin();
if(!Auth::isAdmin()&&!Auth::isSuperAdmin()&&!Auth::can('reservations.manage')){http_response_code(403);exit('Sem autorização.');}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 Csrf::check();
 $action=(string)($_POST['action']??'save');
 $id=(int)($_POST['id']??0);
 if($action==='save'){
  $name=trim((string)($_POST['name']??''));$category=(string)($_POST['category']??'');
  $unit=trim((string)($_POST['unit']??'un'));$raw=trim((string)($_POST['price']??''));
  if(mb_strlen($name)<2||mb_strlen($name)>180)throw new RuntimeException('Informe nome entre 2 e 180 caracteres.');
  if(!in_array($category,['bar','service'],true))throw new RuntimeException('Categoria inválida.');
  if($unit===''||mb_strlen($unit)>35)throw new RuntimeException('Unidade de medida inválida.');
  if(!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>1000000)throw new RuntimeException('Preço inválido.');
  $price=round((float)$raw,2);
  if($id){
   $q=$pdo->prepare("UPDATE suites_catalog_items SET name=?,category=?,unit=?,price=?,updated_at=NOW() WHERE id=?");
   $q->execute([$name,$category,$unit,$price,$id]);
   $q=$pdo->prepare('SELECT id FROM suites_catalog_items WHERE id=?');$q->execute([$id]);
   if(!$q->fetchColumn())throw new RuntimeException('Produto ou serviço não encontrado.');
  }else{
   $pdo->prepare("INSERT INTO suites_catalog_items(name,category,unit,price,active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())")->execute([$name,$category,$unit,$price]);
   $id=(int)$pdo->lastInsertId();
  }
  Audit::log('catalog.save','catalog',$id);U::flash('ok','Produto ou serviço salvo.');
 }elseif($action==='toggle'){
  if($id<=0)throw new RuntimeException('Item não encontrado.');
  $q=$pdo->prepare('UPDATE suites_catalog_items SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=?');
  $q->execute([$id]);if($q->rowCount()!==1)throw new RuntimeException('Item não encontrado.');
  Audit::log('catalog.toggle','catalog',$id);U::flash('ok','Disponibilidade atualizada.');
 }else throw new RuntimeException('Operação inválida.');
 U::redirect('/painel/?p=products_services');
}
$items=$pdo->query('SELECT id,name,category,unit,price,active FROM suites_catalog_items ORDER BY active DESC,category,name,id')->fetchAll();
$h=adminTitle('Produtos e Serviços');
$h.='<style>
.catalog-head{display:flex;justify-content:flex-end;margin-bottom:12px}.catalog-table{width:100%;border-collapse:collapse}.catalog-table th,.catalog-table td{text-align:left;padding:11px;border-bottom:1px solid #dce8e2}.catalog-table td:last-child{white-space:nowrap}.catalog-actions{display:flex;gap:6px;align-items:center}.catalog-modal{border:1px solid #b9cdc3;border-radius:12px;padding:0;width:min(540px,calc(100vw - 24px));max-height:90dvh;overflow:hidden;box-shadow:0 20px 70px #102c2466;background:#fff;color:#173f35}.catalog-modal::backdrop{background:#0e281f99}.catalog-modal[open]{display:flex;flex-direction:column}.catalog-modal-head{display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid #dde6e0}.catalog-modal-head h2{margin:0;font-size:17px}.catalog-modal-body{padding:18px;overflow:auto}.catalog-modal-body form{display:grid;gap:12px}.catalog-modal-body label{display:flex;flex-direction:column;gap:5px}.catalog-modal-body input,.catalog-modal-body select{max-width:100%;box-sizing:border-box}.catalog-modal-close{border:1px solid #b9cdc3;border-radius:8px;padding:5px 12px;background:#fff;cursor:pointer}
</style>';
$h.='<div class="catalog-head"><button class="btn" type="button" data-catalog-new>'.panelCardIcon('payments').' Adicionar produto ou serviço</button></div>';
$h.='<section class="card"><table class="catalog-table"><thead><tr><th>Nome</th><th>Categoria</th><th>Unidade</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
foreach($items as $item){
 $h.='<tr><td>'.U::e($item['name']).'</td><td>'.($item['category']==='bar'?'Bar':'Serviço').'</td><td>'.U::e($item['unit']).'</td><td>'.money((float)$item['price']).'</td><td>'.($item['active']?'Ativo':'Inativo').'</td><td><div class="catalog-actions">';
 $h.='<button type="button" class="btn secondary small" data-catalog-edit="'.(int)$item['id'].'">Editar</button>';
 $h.='<form method="post">'.Csrf::field().'<input type="hidden" name="id" value="'.(int)$item['id'].'"><input type="hidden" name="action" value="toggle"><button type="submit" class="btn secondary small">'.($item['active']?'Desativar':'Ativar').'</button></form></div></td></tr>';
}
if(!$items)$h.='<tr><td colspan="6">Nenhum produto ou serviço cadastrado.</td></tr>';
$h.='</tbody></table></section>';
$h.='<dialog class="catalog-modal" id="catalogItemDialog" aria-label="Cadastrar ou editar produto e serviço"><div class="catalog-modal-head"><h2 id="catalogTitle">Novo produto ou serviço</h2><button class="catalog-modal-close" type="button" data-catalog-close>×</button></div><div class="catalog-modal-body"><form method="post">'.Csrf::field().'<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="" id="catalogId"><label>Nome<input name="name" id="catalogName" maxlength="180" required></label><label>Categoria<select name="category" id="catalogCategory" required><option value="bar">Bar</option><option value="service">Serviço</option></select></label><label>Unidade<input name="unit" id="catalogUnit" maxlength="35" value="un" required></label><label>Preço (R$)<input name="price" id="catalogPrice" type="number" min="0" max="1000000" step="0.01" required></label><button type="submit">Salvar</button></form></div></dialog>';
$public=[];foreach($items as $item)$public[(int)$item['id']]=['name'=>$item['name'],'category'=>$item['category'],'unit'=>$item['unit'],'price'=>$item['price']];
$h.='<script type="application/json" id="catalogItemData">'.json_encode($public,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
$h.='<script>(()=>{const d=document.getElementById("catalogItemDialog"),items=JSON.parse(document.getElementById("catalogItemData").textContent);const close=()=>d.close();document.querySelector("[data-catalog-new]").addEventListener("click",()=>{document.getElementById("catalogId").value="";document.getElementById("catalogName").value="";document.getElementById("catalogCategory").value="bar";document.getElementById("catalogUnit").value="un";document.getElementById("catalogPrice").value="";document.getElementById("catalogTitle").textContent="Novo produto ou serviço";d.showModal();});document.querySelectorAll("[data-catalog-edit]").forEach(b=>b.addEventListener("click",()=>{const x=items[b.dataset.catalogEdit];if(!x)return;document.getElementById("catalogId").value=b.dataset.catalogEdit;document.getElementById("catalogName").value=x.name;document.getElementById("catalogCategory").value=x.category;document.getElementById("catalogUnit").value=x.unit;document.getElementById("catalogPrice").value=x.price;document.getElementById("catalogTitle").textContent="Editar produto ou serviço";d.showModal();}));d.querySelector("[data-catalog-close]").addEventListener("click",close);d.addEventListener("click",e=>{if(e.target===d)close()});})();</script>';
render('Produtos e Serviços',$h);exit;
