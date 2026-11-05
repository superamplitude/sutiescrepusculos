<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Audit;
use App\Core\U;
use App\Services\RnlWhatsAppService;

final class WhatsappController {
    public static function handle(): void {
        Auth::requireLogin();
        $role = Auth::role();
        if (!in_array($role, ['super_admin','admin'], true) && !Auth::can('whatsapp.use')) {
            http_response_code(403);
            \render('Acesso negado','<div class="alert error"><h2>Acesso negado</h2><p>Seu perfil não possui acesso ao WhatsApp.</p></div>');
            exit;
        }

        $service = new RnlWhatsAppService();

        // Consulta leve para o navegador acompanhar a conexão depois do QR.
        if (($_GET['ajax'] ?? '') === 'status') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $session = $service->sessionStatus();
                echo json_encode(['ok'=>true,'session'=>$session], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            exit;
        }

        $error = '';
        $notice = '';
        $qr = null;
        $qrGeneratedAt = null;
        $pairingCode = null;

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Csrf::check();
                $action = (string)($_POST['action'] ?? '');

                switch ($action) {
                    case 'save_config':
                        Auth::requireSuperAdmin();
                        RnlWhatsAppService::saveConfig($_POST);
                        Audit::log('whatsapp.config.save','integration',null,['partner_id'=>$_POST['partner_id']??'']);
                        U::flash('ok','Configuração do WhatsApp salva.');
                        U::redirect('index.php?p=whatsapp_rnl');
                        break;

                    case 'provision':
                        Auth::requireSuperAdmin();
                        $service->provisionPartner();
                        Audit::log('whatsapp.partner.provision','integration',null,['partner_id'=>$service->config()['partner_id']]);
                        U::flash('ok','Parceiro provisionado no WhatsApp Reserve no Litoral.');
                        U::redirect('index.php?p=whatsapp_rnl');
                        break;

                    case 'generate_qr':
                        $result = $service->generateQr();
                        $qr = $result['qr'] ?? null;
                        $qrGeneratedAt = $result['generated_at'] ?? time();
                        if (!empty($result['connected'])) $notice = 'O WhatsApp já está conectado.';
                        Audit::log('whatsapp.client.qr','integration',null,['instance'=>$result['instance_name']??'']);
                        break;

                    case 'generate_pairing':
                        $result = $service->generatePairingCode();
                        $pairingCode = $result['pairing_code'] ?? null;
                        if (!empty($result['connected'])) $notice = 'O WhatsApp já está conectado.';
                        Audit::log('whatsapp.client.pairing','integration',null,['instance'=>$result['instance_name']??'']);
                        break;

                    case 'recreate_qr':
                        $result = $service->recreateQrSession();
                        $qr = $result['qr'] ?? null;
                        $qrGeneratedAt = $result['generated_at'] ?? time();
                        $notice = 'A sessão antiga foi removida e um QR novo foi criado.';
                        Audit::log('whatsapp.client.recreate','integration',null,['instance'=>$result['instance_name']??'']);
                        break;

                    case 'disconnect':
                        $service->disconnectClient();
                        Audit::log('whatsapp.client.disconnect','integration',null,['instance'=>$service->config()['instance_name']]);
                        U::flash('ok','WhatsApp do cliente desconectado.');
                        U::redirect('index.php?p=whatsapp_rnl');
                        break;
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        try { $cfg = $service->config(); }
        catch (\Throwable $e) {
            $cfg = [
                'base_url'=>'https://whatsapp.reservenolitoral.com.br','fallback_ip'=>'162.35.163.68',
                'partner_id'=>'','partner_name'=>'','instance_name'=>'','phone_number'=>'',
                'admin_token_configured'=>false,'evolution_key_configured'=>false,
            ];
            if ($error === '') $error = 'Falha ao carregar a configuração: '.$e->getMessage();
        }

        $partnerResponse = null;
        $partner = [];
        if (!empty($cfg['admin_token_configured'])) {
            try {
                $partnerResponse = $service->partnerStatus();
                $partner = is_array($partnerResponse['partner'] ?? null) ? $partnerResponse['partner'] : (is_array($partnerResponse) ? $partnerResponse : []);
            } catch (\Throwable $e) { if ($error === '') $error = $e->getMessage(); }
        }

        $session = ['configured'=>false,'exists'=>false,'state'=>'not_configured','instance_name'=>$cfg['instance_name']??''];
        if (!empty($cfg['evolution_key_configured'])) {
            try { $session = $service->sessionStatus(); }
            catch (\Throwable $e) { if ($error === '') $error = $e->getMessage(); }
        }

        $partnerActive = !empty($partner['active']);
        $sessionState = strtolower((string)($session['state'] ?? 'not_configured'));
        $clientConnected = in_array($sessionState, ['open','connected'], true);

        $html = \adminTitle('WhatsApp Reserve no Litoral','Fluxo único: configuração → provisionamento → conexão do número → atendimento.');
        if ($error !== '') $html .= '<div class="alert error">'.U::e($error).'</div>';
        if ($notice !== '') $html .= '<div class="alert success">'.U::e($notice).'</div>';

        $html .= '<div class="wa-status-strip '.($partnerActive&&$clientConnected?'ok':'').'">'
            .'<div><span>Parceiro</span><b>'.($partnerActive?'Ativo':'Pendente').'</b></div>'
            .'<div><span>Partner ID</span><b>'.U::e($cfg['partner_id']?:'—').'</b></div>'
            .'<div><span>Instância</span><b>'.U::e($cfg['instance_name']?:'—').'</b></div>'
            .'<div><span>WhatsApp</span><b>'.($clientConnected?'Conectado':self::stateLabel($sessionState)).'</b></div>'
            .'</div>';

        if (Auth::isSuperAdmin()) {
            $html .= self::renderConfiguration($cfg);
            $html .= self::renderProvisioning($cfg, $partnerActive);
        } else {
            $html .= '<section class="card"><h2>1. Configuração</h2><p>O Super Admin gerencia credenciais e provisionamento. Seu acesso é operacional.</p></section>';
        }

        $html .= self::renderClientConnection($cfg, $session, $clientConnected, $qr, $qrGeneratedAt, $pairingCode);
        $html .= self::renderPartnerResponse($partnerResponse, $partner);
        $html .= '<section class="card"><h2>5. Atendimento</h2><p>Com o WhatsApp conectado, as conversas deste parceiro ficam isoladas pelo <b>partner_id</b>.</p><a class="btn" href="index.php?p=messages">Abrir atendimentos</a></section>';

        \render('WhatsApp Reserve no Litoral',$html);
        exit;
    }

    private static function renderConfiguration(array $cfg): string {
        return '<section class="card wa-setup-card"><div class="card-head"><div><h2>1. Configuração</h2><p>Servidor WhatsApp da VPS, credenciais criptografadas e número do cliente. O número deve usar país + DDD + telefone, somente dígitos.</p></div></div>'
            .'<form method="post">'.Csrf::field().'<input type="hidden" name="action" value="save_config"><div class="grid2">'
            .'<label>Servidor WhatsApp da VPS<input name="base_url" value="'.U::e($cfg['base_url']).'" placeholder="https://whatsapp.seudominio.com.br" required></label>'
            .'<label>IP de fallback da VPS<input name="fallback_ip" value="'.U::e($cfg['fallback_ip']??'').'" placeholder="162.35.163.68"></label>'
            .'<label>Partner ID<input name="partner_id" value="'.U::e($cfg['partner_id']).'" required></label>'
            .'<label>Nome do parceiro<input name="partner_name" value="'.U::e($cfg['partner_name']).'" required></label>'
            .'<label>Número do WhatsApp do cliente<input name="phone_number" inputmode="numeric" value="'.U::e($cfg['phone_number']).'" placeholder="5512999999999"></label>'
            .'<label>Token administrativo<input type="password" name="admin_token" autocomplete="new-password" placeholder="'.($cfg['admin_token_configured']?'Já configurado — deixe em branco para manter':'Cole o token administrativo').'" ></label>'
            .'<label>Chave Evolution API<input type="password" name="evolution_api_key" autocomplete="new-password" placeholder="'.($cfg['evolution_key_configured']?'Já configurada — deixe em branco para manter':'Cole a AUTHENTICATION_API_KEY').'" ></label>'
            .'<label>Instância WhatsApp<input value="'.U::e($cfg['instance_name']).'" readonly></label>'
            .'</div><button>Salvar configuração</button></form></section>';
    }

    private static function renderProvisioning(array $cfg, bool $active): string {
        $disabled = empty($cfg['admin_token_configured']);
        return '<section class="card"><h2>2. Provisionamento do parceiro</h2>'
            .($active?'<div class="wa-connected-box"><b>Parceiro ativo no Reserve no Litoral.</b></div>':'<p>Registra ou atualiza o parceiro no servidor central.</p>')
            .($disabled?'<div class="alert warning">Configure o token administrativo antes de provisionar.</div>':'')
            .'<form method="post">'.Csrf::field().'<input type="hidden" name="action" value="provision"><button '.($disabled?'disabled':'').'>'.($active?'Reprovisionar parceiro':'Habilitar parceiro').'</button></form></section>';
    }

    private static function renderClientConnection(array $cfg, array $session, bool $connected, ?string $qr, ?int $qrGeneratedAt, ?string $pairingCode): string {
        $configured = !empty($cfg['evolution_key_configured']);
        $state = strtolower((string)($session['state'] ?? 'not_configured'));
        $html = '<section class="card wa-client-connect"><div class="card-head"><div><h2>3. Conexão do WhatsApp do cliente</h2><p>QR Code e código por telefone são fluxos separados para evitar credenciais inválidas.</p></div><span class="wa-pill '.($connected?'online':'').'">'.($connected?'Conectado':self::stateLabel($state)).'</span></div>';
        $html .= '<div class="grid3"><div><span class="muted">Instância</span><h3>'.U::e($cfg['instance_name']?:'—').'</h3></div><div><span class="muted">Número</span><h3>'.U::e($cfg['phone_number']?:'—').'</h3></div><div><span class="muted">Estado</span><h3>'.U::e(self::stateLabel($state)).'</h3></div></div>';

        if (!$configured) {
            $html .= '<div class="alert warning">A Chave Evolution API ainda não está configurada pelo Super Admin.</div>';
            return $html.'</section>';
        }

        if ($connected) {
            $html .= '<div class="wa-connected-box"><b>Número conectado.</b><p>A instância está online e pronta para atendimento.</p></div>';
            $html .= '<div class="wa-connect-actions"><a class="btn secondary" href="index.php?p=whatsapp_rnl">Atualizar status</a><form method="post" onsubmit="return confirm(\'Desconectar o WhatsApp deste cliente?\')">'.Csrf::field().'<input type="hidden" name="action" value="disconnect"><button class="danger">Desconectar WhatsApp</button></form></div>';
            return $html.'</section>';
        }

        if ($qr) {
            $html .= '<div class="wa-qr-wrap" data-wa-poll-status="1"><div class="wa-qr-image"><img src="'.U::e($qr).'" alt="QR Code do WhatsApp"><div class="wa-qr-timer" data-wa-qr-timer="'.(int)($qrGeneratedAt ?? time()).'">QR recém-gerado</div></div><div><h3>Escaneie agora</h3><p>WhatsApp → Aparelhos conectados → Conectar um aparelho.</p><p class="muted">O QR é temporário. Se expirar, gere outro. A página atualiza sozinha quando o WhatsApp conectar.</p></div></div>';
        }

        if ($pairingCode) {
            $html .= '<div class="wa-pairing-code" data-wa-poll-status="1"><span>Código de pareamento</span><strong>'.U::e($pairingCode).'</strong><p>No celular, toque em <b>Conectar com número de telefone</b> e informe este código.</p></div>';
        }

        if (!$qr && !$pairingCode) {
            $html .= '<div class="wa-connect-intro"><h3>Escolha como conectar</h3><p><b>QR Code</b> não usa o número na chamada de conexão. <b>Código por telefone</b> usa o número salvo acima. Assim um modo não interfere no outro.</p></div>';
        }

        $pairingDisabled = $cfg['phone_number'] === '';
        $html .= '<div class="wa-connect-actions">'
            .'<form method="post">'.Csrf::field().'<input type="hidden" name="action" value="generate_qr"><button class="wa-primary-connect">'.($qr?'Gerar novo QR':'Gerar QR Code').'</button></form>'
            .'<form method="post">'.Csrf::field().'<input type="hidden" name="action" value="generate_pairing"><button class="secondary" '.($pairingDisabled?'disabled':'').'>Gerar código por telefone</button></form>'
            .'<form method="post" onsubmit="return confirm(\'A sessão atual será removida e recriada. Continuar?\')">'.Csrf::field().'<input type="hidden" name="action" value="recreate_qr"><button class="secondary">Recriar sessão e gerar QR</button></form>'
            .'<a class="btn secondary" href="index.php?p=whatsapp_rnl">Atualizar status</a>'
            .'</div>';

        if ($pairingDisabled) $html .= '<p class="muted">Para usar código por telefone, informe o número do cliente na configuração e salve.</p>';

        return $html.'</section>';
    }

    private static function renderPartnerResponse(?array $response, array $partner): string {
        if (!$response || !$partner) return '';
        $channels = $partner['channels'] ?? $partner['channel_bindings'] ?? [];
        $transport = $response['_transport'] ?? [];
        $html = '<section class="card"><h2>4. Estado no servidor</h2><div class="grid3"><div><span class="muted">Parceiro</span><h3>'.(!empty($partner['exists'])?'Encontrado':'Não encontrado').'</h3></div><div><span class="muted">Ativo</span><h3>'.(!empty($partner['active'])?'Sim':'Não').'</h3></div><div><span class="muted">Canais</span><h3>'.(is_array($channels)?count($channels):0).'</h3></div></div>';
        if ($transport) $html .= '<p class="muted">Conexão do painel: '.U::e($transport['attempt']??'').' · IP '.U::e($transport['ip']??'').' · HTTP '.U::e((string)($response['_http']??'')).'</p>';
        if (is_array($channels) && $channels) {
            $html .= '<table><tr><th>Canal</th><th>Agente</th><th>Status</th></tr>';
            foreach ($channels as $channel) $html .= '<tr><td>'.U::e($channel['channel']??'').'</td><td>'.U::e($channel['agent_id']??'').'</td><td>'.(!empty($channel['active'])?'Ativo':'Inativo').'</td></tr>';
            $html .= '</table>';
        }
        return $html.'</section>';
    }

    private static function stateLabel(string $state): string {
        return match ($state) {
            'open','connected' => 'Conectado',
            'connecting' => 'Conectando',
            'close','closed','disconnected' => 'Desconectado',
            'missing' => 'Instância não criada',
            'not_configured' => 'Não configurado',
            default => $state !== '' ? ucfirst($state) : 'Desconectado',
        };
    }
}
