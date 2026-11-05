<?php
namespace App\Core;

final class Secret {
    private static function key(): string {
        $f=dirname(__DIR__,2).'/config/config.php';
        if(!is_file($f)) throw new \RuntimeException('Configuração ausente.');
        $c=require $f;
        $raw=(string)($c['security']['app_key']??'');
        if($raw==='') throw new \RuntimeException('APP key ausente.');
        return hash('sha256',$raw,true);
    }

    public static function encrypt(?string $value): ?string {
        if($value===null || $value==='') return $value;
        if(str_starts_with($value,'enc:')) return $value;
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false) throw new \RuntimeException('Falha ao proteger credencial.');
        return 'enc:'.base64_encode($iv.$tag.$cipher);
    }

    public static function decrypt(?string $value): ?string {
        if($value===null || $value==='') return $value;
        if(!str_starts_with($value,'enc:')) return $value; // compatibilidade com valor antigo
        $raw=base64_decode(substr($value,4),true);
        if($raw===false || strlen($raw)<29) return null;
        $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        return $plain===false?null:$plain;
    }
}
