<?php
namespace App\Services;

final class UploadService {
    private string $base;
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    private const VIDEO_MIMES = [
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm'
    ];

    public function __construct() {
        $this->base = dirname(__DIR__,2).'/storage/uploads';
    }

    public function image(array $file, string $folder): ?string {
        return $this->store($file, $folder, self::IMAGE_MIMES, 12 * 1024 * 1024);
    }

    public function video(array $file, string $folder): ?string {
        return $this->store($file, $folder, self::VIDEO_MIMES, 120 * 1024 * 1024);
    }

    public function images(array $files, string $folder): array {
        $out=[];
        if (empty($files['name']) || !is_array($files['name'])) return $out;
        foreach ($files['name'] as $i=>$name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $one=[
                'name'=>$name,
                'type'=>$files['type'][$i]??'',
                'tmp_name'=>$files['tmp_name'][$i]??'',
                'error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,
                'size'=>$files['size'][$i]??0
            ];
            $path=$this->image($one,$folder);
            if($path)$out[]=$path;
        }
        return $out;
    }

    private function store(array $file, string $folder, array $allowed, int $max): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload. Código: '.($file['error'] ?? -1));
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $max) {
            throw new \RuntimeException('Arquivo excede o limite permitido.');
        }
        $tmp=$file['tmp_name'] ?? '';
        if (!$tmp || !is_uploaded_file($tmp)) throw new \RuntimeException('Upload inválido.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($tmp);
        if (!isset($allowed[$mime])) throw new \RuntimeException('Formato de arquivo não permitido: '.$mime);
        $safeFolder=preg_replace('/[^a-zA-Z0-9_\/-]/','',$folder);
        $dir=$this->base.'/'.$safeFolder;
        if(!is_dir($dir) && !mkdir($dir,0755,true)) throw new \RuntimeException('Não foi possível criar diretório de upload.');
        $name=date('YmdHis').'_'.bin2hex(random_bytes(10)).'.'.$allowed[$mime];
        $dest=$dir.'/'.$name;
        if(!move_uploaded_file($tmp,$dest)) throw new \RuntimeException('Não foi possível salvar o arquivo.');
        @chmod($dest,0644);
        return 'storage/uploads/'.$safeFolder.'/'.$name;
    }
}
