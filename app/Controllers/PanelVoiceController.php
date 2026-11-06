<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;use App\Core\Csrf;use App\Core\DB;use App\Services\PanelVoiceService;use App\Services\PanelAgentService;
final class PanelVoiceController {
    private static function json(array $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
    public static function handle(): never {
        header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        if (!Auth::user()) self::json(['ok'=>false,'error'=>'Entre no painel para usar a voz.'],401);
        if (!Auth::can('ai.use')) self::json(['ok'=>false,'error'=>'Seu perfil não permite usar a IA.'],403);
        $service=new PanelVoiceService();
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
            $name=explode(' ',trim((string)(Auth::user()['name']??'')))[0];
            $property=\App\Services\BusinessContextService::property();
            $company=trim((string)($property['name']??''))?:'seu estabelecimento';
            $text='Olá'.($name!==''?', '.$name:'').'. Bem-vindo ao painel de '.$company.'.';
            if (Auth::can('reservations.manage') && \App\Services\BusinessContextService::isLodging($property)) {
                $pdo=DB::pdo();
                $pending=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN('pre_reserva','aguardando_aprovacao')")->fetchColumn();
                $arrivals=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE checkin=CURDATE() AND status IN('paga','confirmada')")->fetchColumn();
                $text.=' Você tem '.$pending.' reserva(s) aguardando ação e '.$arrivals.' entrada(s) hoje.';
            }
            self::json(['ok'=>true,'configured'=>$service->configured(),'greeting'=>$text.' Como posso ajudar?']);
        }
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') self::json(['ok'=>false,'error'=>'Método inválido.'],405);
        Csrf::check();
        $text=trim((string)($_POST['text']??''));$kind=(string)($_POST['kind']??'speech');
        $limit=$kind==='speech'?8000:550;
        if (($text==='' && $kind!=='cancel') || mb_strlen($text)>$limit) self::json(['ok'=>false,'error'=>'Mensagem vazia ou muito longa.'],422);
        if (!in_array($kind,['speech','answer','confirm','cancel'],true)) self::json(['ok'=>false,'error'=>'Ação inválida.'],422);
        $now=time();$rate=$_SESSION['_panel_voice_rate']??['since'=>$now,'count'=>0];
        if ($now-$rate['since']>=60) $rate=['since'=>$now,'count'=>0];
        if ($rate['count']>=20) self::json(['ok'=>false,'error'=>'Aguarde um momento antes de continuar.'],429);
        $rate['count']++;$_SESSION['_panel_voice_rate']=$rate;
        try {
            if ($kind==='answer') self::json(['ok'=>true]+(new PanelAgentService())->respond($text));
            if ($kind==='confirm') self::json(['ok'=>true]+(new PanelAgentService())->confirm($text));
            if ($kind==='cancel') self::json(['ok'=>true]+(new PanelAgentService())->cancel());
            session_write_close();
            $wav=$service->speech($text);header('Content-Type: audio/wav');header('Content-Length: '.strlen($wav));echo $wav;exit;
        } catch (\Throwable $e) {self::json(['ok'=>false,'error'=>$kind==='speech'?'O serviço de voz está indisponível.':$e->getMessage()],503);}
    }
}
