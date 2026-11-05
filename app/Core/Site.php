<?php
namespace App\Core;

final class Site {
    private static ?array $cache = null;

    public static function defaults(): array {
        return [
            'site_name' => '',
            'tagline' => '',
            'logo_path' => '',
            'favicon_path' => '',
            'layout_variant' => 'layout1',
            'primary_color' => '#0d756d',
            'secondary_color' => '#173f3b',
            'accent_color' => '#bf8d39',
            'body_font' => 'Inter',
            'heading_font' => 'Georgia',
            'chat_enabled' => 1,
            'chat_agent_name' => 'Assistente IA',
            'chat_default_mode' => 'hybrid',
            'chat_welcome' => '',
            'footer_text' => 'Hospedagem, conforto e experiências para aproveitar cada momento da viagem.',
            'footer_legal_text' => '',
            'ui_json' => '{}',
            'custom_css' => '',
        ];
    }

    public static function all(bool $refresh=false): array {
        if (!$refresh && self::$cache !== null) return self::$cache;
        $data = self::defaults();
        try {
            $row = DB::pdo()->query("SELECT * FROM site_settings WHERE id=1")->fetch() ?: [];
            foreach ($row as $k=>$v) if (array_key_exists($k,$data) && $v !== null) $data[$k]=$v;
        } catch (\Throwable $e) {
            // Compatibilidade com instalações ainda não migradas: usa os padrões.
        }
        $data['layout_variant'] = in_array($data['layout_variant'],['layout1','layout2','layout3','layout4'],true) ? $data['layout_variant'] : 'layout1';
        $data['chat_default_mode'] = in_array($data['chat_default_mode'],['human','hybrid','ai'],true) ? $data['chat_default_mode'] : 'hybrid';
        $data['chat_enabled'] = (int)!empty($data['chat_enabled']);
        self::$cache = $data;
        return $data;
    }

    public static function get(string $key, mixed $default=''): mixed {
        $all=self::all();
        return array_key_exists($key,$all) ? $all[$key] : $default;
    }

    public static function ui(string $key, string $default=''): string {
        $all=self::all();
        $json=json_decode((string)($all['ui_json']??'{}'),true);
        if(!is_array($json))$json=[];
        $value=trim((string)($json[$key]??''));
        return $value!=='' ? $value : $default;
    }

    public static function color(string $key, string $fallback): string {
        $value=(string)self::get($key,$fallback);
        return preg_match('/^#[0-9a-fA-F]{6}$/',$value) ? strtolower($value) : $fallback;
    }

    public static function font(string $key, string $fallback): string {
        $allowed=['Inter','Arial','Verdana','Tahoma','Trebuchet MS','Georgia','Times New Roman','system-ui'];
        $value=(string)self::get($key,$fallback);
        return in_array($value,$allowed,true)?$value:$fallback;
    }

    public static function resetCache(): void { self::$cache=null; }
}
