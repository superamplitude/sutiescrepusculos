<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;
use App\Services\BusinessContextService;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requirePerm('ai.use');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 Csrf::check();BusinessContextService::savePolicy($_POST);
 Audit::log('ai.attendance_policy.save','site_settings',1);
 U::flash('ok','Atendimento configurado para este estabelecimento.');
 U::redirect('index.php?p=attendance_settings');
}
$c=BusinessContextService::policy();
$select=function(string $key,string $label,array $options)use($c):string{
 $s='<label>'.U::e($label).'<select name="'.U::e($key).'">';
 foreach($options as $k=>$v)$s.='<option value="'.U::e($k).'" '.($c[$key]===$k?'selected':'').'>'.U::e($v).'</option>';
 return $s.'</select></label>';
};
$h=adminTitle('Atendimento da IA','Definições do gestor para chat, WhatsApp e chamadas.');
$h.='<form method=post class=card>'.Csrf::field().'<div class=grid2>'
 .$select('collect_name','Nome completo',['first_contact'=>'Perguntar no primeiro contato','on_reservation'=>'Pedir ao avançar para reserva/orçamento'])
 .$select('collect_phone','Telefone',['caller_id'=>'Usar telefone do canal, com confirmação','ask_if_missing'=>'Pedir quando necessário'])
 .$select('collection_method','Coleta de dados',['conversation'=>'Durante conversa','form_link'=>'Formulário seguro'])
 .$select('reservation_mode','Processo de reservas',['off'=>'Desativado','quote'=>'Simulação e orçamento','prebooking'=>'Orientar pré-reserva, sem confirmação automática'])
 .'</div>'.row('URL HTTPS do formulário','form_url',$c['form_url']).'<div class=grid2>'
 .row('Sinal previsto (%)','deposit_percent',$c['deposit_percent'],'number')
 .row('Validade da pré-reserva (min)','prebooking_minutes',$c['prebooking_minutes'],'number')
 .'</div><p class=muted>Estas opções orientam a conversa. Esta página não emite cobranças Pix nem cria reservas. Uma reserva só fica confirmada após verificação real do pagamento.</p><button>Salvar atendimento</button></form>';
$h.='<section class=card><p>Nome, categoria e endereço são definidos em <a href="index.php?p=property">Informações do Estabelecimento</a>. A IA não estima distâncias.</p></section>';
render('Atendimento da IA',$h);exit;
