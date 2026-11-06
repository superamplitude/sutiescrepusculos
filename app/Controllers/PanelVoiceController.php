<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;use App\Core\Csrf;use App\Core\DB;use App\Services\PanelVoiceService;use App\Services\GeminiService;
final class PanelVoiceController {
    private static function json(array $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
    public static function handle(): never {
        header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        if (!Auth::user()) self::json(['ok'=>false,'error'=>'Entre no painel para usar a voz.'],401);
        $service=new PanelVoiceService();
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
            $name=explode(' ',trim((string)(Auth::user()['name']??'')))[0];
            $text='Olá'.($name!==''?', '.$name:'').'. Bem-vindo às Suítes Crepúsculo.';
            if (Auth::can('reservations.manage')) {
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
        if ($text==='' || mb_strlen($text)>$limit) self::json(['ok'=>false,'error'=>'Mensagem vazia ou muito longa.'],422);
        if (!in_array($kind,['speech','answer'],true)) self::json(['ok'=>false,'error'=>'Ação inválida.'],422);
        $now=time();$rate=$_SESSION['_panel_voice_rate']??['since'=>$now,'count'=>0];
        if ($now-$rate['since']>=60) $rate=['since'=>$now,'count'=>0];
        if ($rate['count']>=20) self::json(['ok'=>false,'error'=>'Aguarde um momento antes de continuar.'],429);
        $rate['count']++;$_SESSION['_panel_voice_rate']=$rate;session_write_close();
        try {
            if ($kind==='answer') {
                $r=(new GeminiService())->ask('Oriente o usuário do painel da pousada sobre reservas, acomodações, mapa de diárias e atendimento. Converse em uma ou duas frases conectadas, sem saudações repetidas e com no máximo uma pergunta. Não afirme ter executado ações. Pergunta: '.$text);
                if (empty($r['ok'])) throw new \RuntimeException('O atendimento por IA está indisponível.');
                self::json(['ok'=>true,'answer'=>trim((string)$r['answer'])]);
            }
            $wav=$service->speech($text);header('Content-Type: audio/wav');header('Content-Length: '.strlen($wav));echo $wav;exit;
        } catch (\Throwable) {self::json(['ok'=>false,'error'=>'Não foi possível concluir o atendimento de voz agora.'],503);}
    }
}
