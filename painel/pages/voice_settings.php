<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }


Auth::requirePerm('ai.use');
$profileService=new \App\Services\VoiceProfileService();
if($_SERVER['REQUEST_METHOD']==='POST'){
 Csrf::check();Auth::requireSuperAdmin();$profileService->save($_POST);
 Audit::log('voice.profile.save','voice_profile',1);U::flash('ok','Perfil de voz salvo para o painel, os áudios e os próximos atendimentos.');U::redirect('index.php?p=voice_settings');
}
$c=$profileService->get();
$h=adminTitle('Voz e atendimento','Uma configuração de fala para o painel, os áudios e as chamadas.');
$h.='<section class=card><h2>Identidade da voz</h2><p>A identidade vocal atual é mantida. O perfil abaixo controla a forma de conversar e permanece salvo quando outras telas forem atualizadas.</p></section>';
$h.='<form method=post class=card>'.Csrf::field().'<div class=grid2><label>Tom<select name=tone>';
foreach(['natural'=>'Natural e conversacional','acolhedor'=>'Acolhedor','profissional'=>'Profissional'] as $k=>$v)$h.='<option value="'.$k.'" '.($c['tone']===$k?'selected':'').'>'.$v.'</option>';
$h.='</select></label><label>Ritmo<select name=pace>';
foreach(['equilibrado'=>'Equilibrado e fluido','tranquilo'=>'Tranquilo'] as $k=>$v)$h.='<option value="'.$k.'" '.($c['pace']===$k?'selected':'').'>'.$v.'</option>';
$h.='</select></label></div><p>Frases conectadas, pausas entre ideias e entonação suave. As informações, datas e valores continuam sendo preservados.</p>';
if(Auth::isSuperAdmin())$h.='<button>Salvar perfil</button>';
$h.='</form><section class=card><h2>Ouvir o perfil salvo</h2><p>Abra <b>Chat e voz</b> e use <b>Testar voz</b>, ou envie uma pergunta. O mesmo perfil é aplicado aos áudios do WhatsApp.</p></section>';
$h.='<section class=card><a class="btn secondary" href="index.php?p=attendance_settings">Configurar atendimento da IA</a></section>';
render('Voz e atendimento',$h);exit;
