<?php
use App\Core\Auth;use App\Core\U;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireLogin();
if(!Auth::isSuperAdmin()&&!Auth::isAdmin()&&!Auth::can('payments.view')){http_response_code(403);exit('Sem autorização financeira.');}
$h=adminTitle('Financeiro');
$h.='<style>.fin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,210px),1fr));gap:12px;margin:10px 0 20px}.fin-tile{background:#fff;border:1px solid #a6b8b0;border-radius:11px;min-height:115px;padding:15px;text-decoration:none;color:#173d34;display:flex;flex-direction:column;gap:8px;justify-content:center}.fin-tile:hover,.fin-tile:focus-visible{border-color:#127a67;box-shadow:0 5px 15px #145a3428}.fin-tile strong{display:flex;align-items:center;gap:10px;font-size:15px}.fin-tile small{color:#516b60;font-size:12px}.fin-tile svg{color:#137b69}</style>';
$h.='<nav class="fin-grid" aria-label="Módulos financeiros">';
$h.='<a class="fin-tile" href="index.php?p=payments"><strong>'.panelCardIcon('payments').' Pagamentos</strong><small>Visualizar e confirmar pagamentos de reservas</small></a>';
$h.='<a class="fin-tile" href="index.php?p=livro_caixa"><strong>'.panelCardIcon('cash').' Livro Caixa</strong><small>Entradas, saídas e relatórios</small></a>';
if(Auth::isSuperAdmin())$h.='<a class="fin-tile" href="index.php?p=integrations&service=pix"><strong>'.panelCardIcon('pix').' PIX</strong><small>Configuração do gateway e chave PIX</small></a>';
$h.='</nav>';
render('Financeiro',$h);exit;
