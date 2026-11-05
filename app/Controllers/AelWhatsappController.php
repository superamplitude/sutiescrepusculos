<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Audit;
use App\Core\U;
use App\Services\AelWaCallsService;

final class AelWhatsappController
{
    public static function handle(): void
    {
        Auth::requireLogin();
        $role=Auth::role();
        if(!in_array($role,['super_admin','admin'],true)&&!Auth::can('whatsapp.use')){
            http_response_code(403);
            \render('Acesso negado','<div class="alert error"><h2>Acesso negado</h2><p>Seu perfil não possui acesso ao WhatsApp.</p></div>');
            exit;
        }

        $svc=new AelWaCallsService();
        $companyId=max(1,(int)(Auth::user()['company_id']??1));
        $qr=null;$error='';$notice='';

        if($_SERVER['REQUEST_METHOD']==='POST'){
            try{
                Csrf::check();
                $action=(string)($_POST['action']??'');
                if($action==='ael_generate_qr'){
                    $r=$svc->pair($companyId);
                    $qr=$r['qr']??null;
                    if(!empty($r['connected']))$notice='O WhatsApp já está conectado.';
                    Audit::log('whatsapp.ael.pair','integration',null,['company_id'=>$companyId]);
                }elseif($action==='ael_disconnect'){
                    $svc->disconnect($companyId);
                    Audit::log('whatsapp.ael.disconnect','integration',null,['company_id'=>$companyId]);
                    U::flash('ok','Sessão WhatsApp AEL desconectada.');
                    U::redirect('index.php?p=whatsapp_rnl');
                }
            }catch(\Throwable $e){$error=$e->getMessage();}
        }

        $health=$svc->health();
        try{$status=$svc->status($companyId);}catch(\Throwable $e){
            $status=['configured'=>false,'exists'=>false,'paired'=>false,'state'=>'error','jid'=>''];
            if($error==='')$error=$e->getMessage();
        }
        $paired=!empty($status['paired']);
        $state=(string)($status['state']??'missing');

        $html=\adminTitle('WhatsApp AEL','Pareamento local por QR Code usando o runtime do AELFLOW na mesma VPS.');
        if($error!=='')$html.='<div class="alert error">'.U::e($error).'</div>';
        if($notice!=='')$html.='<div class="alert success">'.U::e($notice).'</div>';

        $html.='<section class="card"><div class="card-head"><div><h2>Estado do serviço</h2><p>API local restrita à própria VPS. Nenhuma credencial é exibida no navegador.</p></div></div>'
          .'<div class="grid3"><div><span class="muted">Runtime AEL</span><h3>'.(!empty($health['ok'])?'Online':'Indisponível').'</h3></div>'
          .'<div><span class="muted">Sessão</span><h3>'.U::e($status['name']??'—').'</h3></div>'
          .'<div><span class="muted">WhatsApp</span><h3>'.($paired?'Conectado':U::e(self::stateLabel($state))).'</h3></div></div></section>';

        $html.='<section class="card wa-client-connect"><h2>Conexão por QR Code</h2>';
        if($paired){
            $html.='<div class="wa-connected-box"><b>Número conectado.</b><p>O aparelho está pareado ao runtime AEL.</p></div>'
              .'<form method="post" onsubmit="return confirm(\'Desconectar esta sessão do WhatsApp?\')">'.Csrf::field().'<input type="hidden" name="action" value="ael_disconnect"><button class="danger">Desconectar WhatsApp</button></form>';
        }else{
            $html.='<p>Abra no celular: <b>WhatsApp → Aparelhos conectados → Conectar um aparelho</b>.</p>';
            if($qr){
                $html.='<div class="wa-qr-wrap"><div id="aelWhatsappQr" class="wa-qr-canvas" data-qr="'.U::e((string)$qr).'"></div><div><h3>Escaneie agora</h3><p class="muted">O QR é temporário. Se expirar, gere novamente.</p></div></div>';
            }
            $html.='<form method="post">'.Csrf::field().'<input type="hidden" name="action" value="ael_generate_qr"><button class="wa-primary-connect">'.($qr?'Gerar novo QR':'Gerar QR Code').'</button></form>';
        }
        $html.='</section>';

        $html.='<section class="card"><h2>Atendimento integrado</h2><p>O mesmo painel centraliza conversas, CRM e controle Humano / Híbrido / IA.</p><div class="map-editor-actions"><a class="btn" href="index.php?p=messages">Abrir atendimentos</a><a class="btn secondary" href="index.php?p=crm360">Abrir CRM 360°</a><a class="btn secondary" href="index.php?p=ai">Configurar IA</a></div></section>';

        if($qr){
            $html.='<script src="assets/js/vendor/rnl-qrcode.js?v=20261007"></script><script>(function(){var host=document.getElementById("aelWhatsappQr");if(!host||!window.RNLQRCodeCore)return;var raw=host.getAttribute("data-qr")||"";if(!raw)return;try{var Q=window.RNLQRCodeCore.QRCode,E=window.RNLQRCodeCore.ErrorCorrectLevel;var q=new Q(0,E.M);q.addData(raw);q.make();var n=q.getModuleCount(),size=320,pad=12,cell=(size-pad*2)/n,c=document.createElement("canvas");c.width=size;c.height=size;var x=c.getContext("2d");x.fillStyle="#fff";x.fillRect(0,0,size,size);x.fillStyle="#000";for(var r=0;r<n;r++)for(var col=0;col<n;col++)if(q.isDark(r,col))x.fillRect(Math.floor(pad+col*cell),Math.floor(pad+r*cell),Math.ceil(cell),Math.ceil(cell));host.textContent="";host.appendChild(c);}catch(e){host.textContent="Não foi possível desenhar o QR.";}})();</script>';
        }

        \render('WhatsApp AEL',$html);
        exit;
    }

    private static function stateLabel(string $state): string
    {
        return match(strtolower($state)){
            'open','connected'=>'Conectado',
            'pairing','connecting'=>'Aguardando QR',
            'logged_out','close','closed','disconnected'=>'Desconectado',
            'missing'=>'Não pareado',
            default=>$state!==''?ucfirst($state):'Não pareado',
        };
    }
}
