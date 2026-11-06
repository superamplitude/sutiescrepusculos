<?php
declare(strict_types=1);
namespace App\Services;
final class PanelVoiceService {
    public function configured(): bool { return is_readable('/etc/crepusculo/voice.json'); }
    public function speech(string $text): string {
        if (!$this->configured()) throw new \RuntimeException('Voz natural ainda não configurada.');
        $cfg=json_decode((string)file_get_contents('/etc/crepusculo/voice.json'),true,512,JSON_THROW_ON_ERROR);
        $profile=new VoiceProfileService();$text=VoiceProfileService::spokenText($text);
        $ch=curl_init('http://127.0.0.1:13002/communication/v1/projects/suites-crepusculo/voice/speech');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$cfg['token']],CURLOPT_POSTFIELDS=>json_encode(['text'=>$text,'provider_api_key'=>$cfg['provider_api_key'],'voice_profile'=>$profile->get()],JSON_THROW_ON_ERROR),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>80]);
        $wav=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if ($status!==200 || !is_string($wav) || strlen($wav)<=44 || substr($wav,0,4)!=='RIFF' || substr($wav,8,4)!=='WAVE') throw new \RuntimeException('A voz natural está indisponível. Tente novamente.');
        return $wav;
    }
}
