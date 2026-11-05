<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Secret;

final class RnlWhatsAppService {
    private const DEFAULT_BASE_URL = 'https://whatsapp.reservenolitoral.com.br';
    private const DEFAULT_FALLBACK_IP = '162.35.163.68';

    private array $cfg = [];

    public function __construct() {
        $pdo = DB::pdo();
        $pdo->exec("INSERT IGNORE INTO integrations(type,enabled,status,updated_at) VALUES('whatsapp',0,'configured_unverified',NOW())");
        $q = $pdo->prepare("SELECT config_json FROM integrations WHERE type='whatsapp' LIMIT 1");
        $q->execute();
        $this->cfg = json_decode((string)($q->fetchColumn() ?: '{}'), true) ?: [];
    }

    public static function suggestedPartnerName(): string {
        return trim((string)(DB::pdo()->query("SELECT COALESCE(NULLIF(name,''),'Pousada') FROM property WHERE id=1")->fetchColumn() ?: 'Pousada'));
    }

    public static function suggestedPartnerId(): string {
        $property = DB::pdo()->query("SELECT id,name FROM property WHERE id=1")->fetch() ?: [];
        $base = trim((string)($property['name'] ?? 'pousada'));
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
            if ($ascii !== false) $base = $ascii;
        }
        $base = strtolower($base);
        $base = trim((string)preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        if ($base === '') $base = 'pousada';
        return substr($base, 0, 40).'-'.(int)($property['id'] ?? 1);
    }

    public function config(): array {
        $partnerId = trim((string)($this->cfg['partner_id'] ?? '')) ?: self::suggestedPartnerId();
        $partnerName = trim((string)($this->cfg['partner_name'] ?? '')) ?: self::suggestedPartnerName();
        return [
            'base_url' => $this->baseUrl(),
            'fallback_ip' => $this->fallbackIp(),
            'partner_id' => $partnerId,
            'partner_name' => $partnerName,
            'instance_name' => 'rnl-'.$partnerId,
            'phone_number' => preg_replace('/\D+/', '', (string)($this->cfg['phone_number'] ?? '')),
            'admin_token_configured' => $this->secretConfigured('admin_token'),
            'evolution_key_configured' => $this->secretConfigured('evolution_api_key'),
        ];
    }

    public static function saveConfig(array $input): void {
        $pdo = DB::pdo();
        $pdo->exec("INSERT IGNORE INTO integrations(type,enabled,status,updated_at) VALUES('whatsapp',0,'configured_unverified',NOW())");
        $q = $pdo->prepare("SELECT config_json FROM integrations WHERE type='whatsapp' LIMIT 1");
        $q->execute();
        $cfg = json_decode((string)($q->fetchColumn() ?: '{}'), true) ?: [];

        $partnerId = trim((string)($input['partner_id'] ?? '')) ?: self::suggestedPartnerId();
        $partnerId = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9_-]+/', '-', $partnerId), '-'));
        if ($partnerId === '') throw new \RuntimeException('Partner ID inválido.');

        $partnerName = trim((string)($input['partner_name'] ?? '')) ?: self::suggestedPartnerName();
        $phone = preg_replace('/\D+/', '', (string)($input['phone_number'] ?? ''));
        if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 15)) {
            throw new \RuntimeException('Número do WhatsApp inválido. Use país + DDD + número, somente dígitos.');
        }

        $baseUrl=rtrim(trim((string)($input['base_url'] ?? ($cfg['base_url'] ?? self::DEFAULT_BASE_URL))),'/');
        $scheme=strtolower((string)parse_url($baseUrl,PHP_URL_SCHEME));$host=(string)parse_url($baseUrl,PHP_URL_HOST);
        if(!in_array($scheme,['http','https'],true) || $host==='') throw new \RuntimeException('URL do servidor WhatsApp inválida. Use http:// ou https://.');
        $fallbackIp=trim((string)($input['fallback_ip'] ?? ($cfg['fallback_ip'] ?? self::DEFAULT_FALLBACK_IP)));
        if($fallbackIp!=='' && filter_var($fallbackIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false) throw new \RuntimeException('IP de fallback inválido.');
        $cfg['base_url'] = $baseUrl;
        $cfg['fallback_ip'] = $fallbackIp;
        $cfg['partner_id'] = $partnerId;
        $cfg['partner_name'] = $partnerName;
        $cfg['instance_name'] = 'rnl-'.$partnerId;
        $cfg['phone_number'] = $phone;

        $adminToken = trim((string)($input['admin_token'] ?? ''));
        if ($adminToken !== '') $cfg['admin_token'] = Secret::encrypt($adminToken);

        $evolutionKey = trim((string)($input['evolution_api_key'] ?? ''));
        if ($evolutionKey !== '') $cfg['evolution_api_key'] = Secret::encrypt($evolutionKey);

        $pdo->prepare("UPDATE integrations SET enabled=1,config_json=?,status='configured_unverified',last_error=NULL,updated_at=NOW() WHERE type='whatsapp'")
            ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }

    public function provisionPartner(): array {
        $cfg = $this->config();
        if (!$cfg['admin_token_configured']) throw new \RuntimeException('Token administrativo do Reserve no Litoral não configurado.');
        $result = $this->requestCentral('POST', '/api/partner/enable', [
            'partner_id' => $cfg['partner_id'],
            'name' => $cfg['partner_name'],
        ]);
        $this->markIntegrationTest(true, null);
        return $result;
    }

    public function partnerStatus(): array {
        $cfg = $this->config();
        if (!$cfg['admin_token_configured']) return ['configured'=>false, 'partner'=>null];
        try {
            $result = $this->requestCentral('GET', '/api/partner/status?partner_id='.rawurlencode($cfg['partner_id']));
            $this->markIntegrationTest(true, null);
            return $result;
        } catch (\Throwable $e) {
            $this->markIntegrationTest(false, $e->getMessage());
            throw $e;
        }
    }

    public function sessionStatus(): array {
        $cfg = $this->config();
        if (!$cfg['evolution_key_configured']) {
            return ['configured'=>false,'exists'=>false,'state'=>'not_configured','instance_name'=>$cfg['instance_name']];
        }

        $r = $this->requestEvolution('GET', '/instance/connectionState/'.rawurlencode($cfg['instance_name']), null, true);
        $http = (int)($r['_http'] ?? 0);
        if ($http === 404) return ['configured'=>true,'exists'=>false,'state'=>'missing','instance_name'=>$cfg['instance_name']];
        if ($http >= 400) throw new \RuntimeException($this->evolutionError($r, 'Falha ao consultar o estado da instância.'));
        $this->throwLogicalEvolutionError($r, 'Falha ao consultar o estado da instância.');

        $state = trim((string)($r['instance']['state'] ?? $r['instance']['status'] ?? $r['state'] ?? ''));
        if ($state === '' || in_array(strtolower($state), ['undefined','null'], true)) {
            return ['configured'=>true,'exists'=>false,'state'=>'missing','instance_name'=>$cfg['instance_name'],'raw'=>$r];
        }

        return [
            'configured'=>true,
            'exists'=>true,
            'state'=>$state,
            'instance_name'=>$cfg['instance_name'],
            'raw'=>$r,
        ];
    }

    /**
     * Fluxo QR: NÃO envia número. QR e código de pareamento são modos separados.
     */
    public function generateQr(): array {
        $cfg = $this->config();
        if (!$cfg['evolution_key_configured']) throw new \RuntimeException('Chave Evolution API não configurada pelo Super Admin.');

        $status = $this->sessionStatus();
        $state = strtolower((string)($status['state'] ?? ''));
        if (in_array($state, ['open','connected'], true)) {
            return ['connected'=>true,'state'=>$state,'instance_name'=>$cfg['instance_name'],'qr'=>null];
        }

        $createQr = null;
        if (!$status['exists']) {
            $create = $this->createInstance();
            $createQr = $this->extractQr($create);
            usleep(450000);
        }

        $connect = $this->requestEvolution('GET', '/instance/connect/'.rawurlencode($cfg['instance_name']), null, true);
        $http = (int)($connect['_http'] ?? 0);
        if ($http >= 400) throw new \RuntimeException($this->evolutionError($connect, 'Falha ao gerar QR Code.'));
        $this->throwLogicalEvolutionError($connect, 'Falha ao gerar QR Code.');

        $qr = $this->extractQr($connect) ?: $createQr;
        if (!$qr) {
            usleep(650000);
            $retry = $this->requestEvolution('GET', '/instance/connect/'.rawurlencode($cfg['instance_name']), null, true);
            if ((int)($retry['_http'] ?? 0) < 400) $qr = $this->extractQr($retry);
            if (!$qr && (int)($retry['_http'] ?? 0) >= 400) {
                throw new \RuntimeException($this->evolutionError($retry, 'A Evolution API não devolveu um QR válido.'));
            }
        }

        if (!$qr) throw new \RuntimeException('A Evolution API não devolveu a imagem do QR. Use “Recriar sessão e gerar QR” e tente novamente.');

        return [
            'connected'=>false,
            'state'=>'connecting',
            'instance_name'=>$cfg['instance_name'],
            'qr'=>$qr,
            'generated_at'=>time(),
        ];
    }

    /**
     * Fluxo por telefone: usa ?number= apenas para pedir código de pareamento.
     */
    public function generatePairingCode(): array {
        $cfg = $this->config();
        if (!$cfg['evolution_key_configured']) throw new \RuntimeException('Chave Evolution API não configurada pelo Super Admin.');
        if ($cfg['phone_number'] === '') throw new \RuntimeException('Informe e salve o número do WhatsApp antes de gerar o código de pareamento.');

        $status = $this->sessionStatus();
        $state = strtolower((string)($status['state'] ?? ''));
        if (in_array($state, ['open','connected'], true)) {
            return ['connected'=>true,'state'=>$state,'instance_name'=>$cfg['instance_name'],'pairing_code'=>null];
        }

        if (!$status['exists']) {
            $this->createInstance();
            usleep(450000);
        }

        $path = '/instance/connect/'.rawurlencode($cfg['instance_name']).'?number='.rawurlencode($cfg['phone_number']);
        $connect = $this->requestEvolution('GET', $path, null, true);
        $http = (int)($connect['_http'] ?? 0);
        if ($http >= 400) throw new \RuntimeException($this->evolutionError($connect, 'Falha ao gerar código de pareamento.'));
        $this->throwLogicalEvolutionError($connect, 'Falha ao gerar código de pareamento.');

        $pairing = $this->extractPairingCode($connect);
        if (!$pairing) throw new \RuntimeException('A Evolution API não devolveu um código de pareamento. Use QR Code ou tente novamente.');

        return [
            'connected'=>false,
            'state'=>'connecting',
            'instance_name'=>$cfg['instance_name'],
            'pairing_code'=>$pairing,
            'generated_at'=>time(),
        ];
    }

    public function recreateQrSession(): array {
        $cfg = $this->config();
        if (!$cfg['evolution_key_configured']) throw new \RuntimeException('Chave Evolution API não configurada.');

        $delete = $this->requestEvolution('DELETE', '/instance/delete/'.rawurlencode($cfg['instance_name']), null, true);
        $http = (int)($delete['_http'] ?? 0);
        if ($http >= 400 && $http !== 404) {
            throw new \RuntimeException($this->evolutionError($delete, 'Falha ao recriar a sessão do WhatsApp.'));
        }
        usleep(800000);
        return $this->generateQr();
    }

    public function disconnectClient(): array {
        $cfg = $this->config();
        if (!$cfg['evolution_key_configured']) throw new \RuntimeException('Chave Evolution API não configurada.');
        $r = $this->requestEvolution('DELETE', '/instance/logout/'.rawurlencode($cfg['instance_name']), null, true);
        $http = (int)($r['_http'] ?? 0);
        if ($http >= 400 && $http !== 404) throw new \RuntimeException($this->evolutionError($r, 'Falha ao desconectar o WhatsApp.'));
        if ($http < 400) $this->throwLogicalEvolutionError($r, 'Falha ao desconectar o WhatsApp.');
        return $r;
    }

    private function createInstance(): array {
        $cfg = $this->config();
        // Não enviar número aqui: create é neutro e serve tanto para QR quanto pairing.
        $payload = [
            'instanceName'=>$cfg['instance_name'],
            'integration'=>'WHATSAPP-BAILEYS',
            'qrcode'=>true,
        ];
        $create = $this->requestEvolution('POST', '/instance/create', $payload, true);
        $http = (int)($create['_http'] ?? 0);
        if ($http >= 400 && !in_array($http, [409,422], true)) {
            throw new \RuntimeException($this->evolutionError($create, 'Falha ao criar a instância WhatsApp.'));
        }
        if ($http < 400) $this->throwLogicalEvolutionError($create, 'Falha ao criar a instância WhatsApp.');
        return $create;
    }

    private function requestCentral(string $method, string $path, ?array $payload=null): array {
        return $this->httpJson($method, $path, [
            'Authorization: Bearer '.$this->secret('admin_token'),
            'Accept: application/json',
            'User-Agent: PousadaIA/iceberg',
        ], $payload, false);
    }

    private function requestEvolution(string $method, string $path, ?array $payload=null, bool $allowHttpError=false): array {
        return $this->httpJson($method, $path, [
            'apikey: '.$this->secret('evolution_api_key'),
            'Accept: application/json',
            'User-Agent: PousadaIA/iceberg',
        ], $payload, $allowHttpError);
    }

    private function httpJson(string $method, string $path, array $headers, ?array $payload, bool $allowHttpError): array {
        if (!function_exists('curl_init')) throw new \RuntimeException('cURL não está disponível no servidor.');

        $baseUrl=$this->baseUrl();$fallbackIp=$this->fallbackIp();
        $host=(string)parse_url($baseUrl,PHP_URL_HOST);$scheme=strtolower((string)parse_url($baseUrl,PHP_URL_SCHEME));
        $port=(int)(parse_url($baseUrl,PHP_URL_PORT) ?: ($scheme==='https'?443:80));
        $attempts = [['label'=>'IPv4','resolve'=>null]];
        if($fallbackIp!=='' && $host!=='')$attempts[]=['label'=>'IPv4 direto','resolve'=>$host.':'.$port.':'.$fallbackIp];
        $lastNetworkError = '';

        foreach ($attempts as $attempt) {
            $requestHeaders = $headers;
            $ch = curl_init($baseUrl.$path);
            $opts = [
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>7,
                CURLOPT_TIMEOUT=>25,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_HTTPHEADER=>$requestHeaders,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
                CURLOPT_DNS_CACHE_TIMEOUT=>300,
            ];
            if ($attempt['resolve']) $opts[CURLOPT_RESOLVE] = [$attempt['resolve']];
            if ($payload !== null) {
                $requestHeaders[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $requestHeaders;
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ip = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            curl_close($ch);

            if ($errno !== 0) {
                $lastNetworkError = $attempt['label'].': '.$error;
                continue;
            }

            $decoded = json_decode((string)$body, true);
            $result = is_array($decoded) ? $decoded : ['raw'=>(string)$body];
            $result['_http'] = $http;
            $result['_transport'] = ['attempt'=>$attempt['label'],'ip'=>$ip];

            if ($http >= 200 && $http < 300) return $result;
            if ($allowHttpError) return $result;

            throw new \RuntimeException('Servidor retornou HTTP '.$http.': '.$this->evolutionError($result, 'Resposta inválida do servidor.'));
        }

        throw new \RuntimeException('Falha de conexão com o servidor WhatsApp: '.$lastNetworkError);
    }


    private function baseUrl(): string {
        $url=rtrim(trim((string)($this->cfg['base_url'] ?? self::DEFAULT_BASE_URL)),'/');
        $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));$host=(string)parse_url($url,PHP_URL_HOST);
        return in_array($scheme,['http','https'],true) && $host!=='' ? $url : self::DEFAULT_BASE_URL;
    }

    private function fallbackIp(): string {
        $ip=trim((string)($this->cfg['fallback_ip'] ?? self::DEFAULT_FALLBACK_IP));
        return $ip!=='' && filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false ? $ip : '';
    }

    private function extractQr(array $response): ?string {
        $candidates = [
            $response['base64'] ?? null,
            is_array($response['qrcode'] ?? null) ? ($response['qrcode']['base64'] ?? null) : ($response['qrcode'] ?? null),
            is_array($response['qr'] ?? null) ? ($response['qr']['base64'] ?? null) : ($response['qr'] ?? null),
            $response['qrCode'] ?? null,
        ];
        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') continue;
            $value = trim($value);
            if (str_starts_with($value, 'data:image/')) return $value;
            if (strlen($value) > 200 && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
                return 'data:image/png;base64,'.preg_replace('/\s+/', '', $value);
            }
        }
        return null;
    }

    private function extractPairingCode(array $response): ?string {
        $candidates = [
            $response['pairingCode'] ?? null,
            $response['pairing_code'] ?? null,
            is_array($response['qrcode'] ?? null) ? ($response['qrcode']['pairingCode'] ?? null) : null,
        ];
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return null;
    }

    private function throwLogicalEvolutionError(array $response, string $fallback): void {
        if (!empty($response['error']) && $response['error'] !== false) {
            throw new \RuntimeException($this->evolutionError($response, $fallback));
        }
    }

    private function evolutionError(array $response, string $fallback): string {
        $message = null;
        $nested = $response['response']['message'] ?? null;
        if (is_array($nested)) $message = $nested[0] ?? $nested;
        elseif (is_string($nested) && trim($nested) !== '') $message = $nested;

        if ($message === null) {
            $top = $response['message'] ?? null;
            if (is_array($top)) $message = $top[0] ?? $top;
            elseif (is_string($top) && trim($top) !== '') $message = $top;
        }
        if ($message === null) {
            $err = $response['error'] ?? null;
            if (is_string($err) && trim($err) !== '') $message = $err;
            elseif (is_array($err)) $message = $err;
        }
        if ($message === null) $message = $fallback;
        if (is_array($message)) $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        $message = trim((string)$message);
        return $message !== '' ? $message : $fallback;
    }

    private function secretConfigured(string $key): bool {
        try { return $this->secret($key) !== ''; } catch (\Throwable $e) { return false; }
    }

    private function secret(string $key): string {
        $raw = (string)($this->cfg[$key] ?? '');
        if ($raw === '') return '';
        return trim((string)(Secret::decrypt($raw) ?? ''));
    }

    private function markIntegrationTest(bool $ok, ?string $error): void {
        DB::pdo()->prepare("UPDATE integrations SET status=?,last_test_at=NOW(),last_error=?,updated_at=NOW() WHERE type='whatsapp'")
            ->execute([$ok ? 'verified' : 'configured_unverified', $error]);
    }
}
