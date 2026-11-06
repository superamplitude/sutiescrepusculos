<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Auth;use App\Core\Audit;use App\Core\DB;use App\Core\U;
final class GuestDocumentService {
 private const MAX=5242880;
 private const MIMES=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 private static function root():string{return dirname(__DIR__,4).'/private-documents';}
 public function storeUpload(array $file,int $guestId,?int $reservationId,string $kind):int{
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new \RuntimeException('Selecione um arquivo válido.');
  if(($file['size']??0)<1||$file['size']>self::MAX)throw new \RuntimeException('Tamanho máximo: 5 MB.');
  $bytes=file_get_contents($file['tmp_name']);if($bytes===false)throw new \RuntimeException('Leitura indisponível.');
  return $this->storeBytes($bytes,(string)($file['name']??'documento'),$guestId,$reservationId,$kind,'painel_hospede',null);
 }
 public function storeBase64(string $encoded,string $name,int $guestId,?int $reservationId,string $kind,?int $conversationId):int{
  if(strlen($encoded)>self::MAX*1.4)throw new \RuntimeException('Anexo excede 5 MB.');
  $bytes=base64_decode($encoded,true);if($bytes===false)throw new \RuntimeException('Anexo inválido.');
  return $this->storeBytes($bytes,$name,$guestId,$reservationId,$kind,'whatsapp',$conversationId);
 }
 private function storeBytes(string $bytes,string $name,int $guestId,?int $reservationId,string $kind,string $source,?int $conversationId):int{
  if(strlen($bytes)<1||strlen($bytes)>self::MAX)throw new \RuntimeException('Arquivo inválido.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!isset(self::MIMES[$mime]))throw new \RuntimeException('Use PDF, JPG, PNG ou WEBP.');
  if(!in_array($kind,['documento','comprovante'],true))throw new \RuntimeException('Tipo inválido.');
  $db=DB::pdo();$q=$db->prepare('SELECT name FROM guests WHERE id=?');$q->execute([$guestId]);$guest=(string)$q->fetchColumn();
  if(!$guest)throw new \RuntimeException('Hóspede inexistente.');
  if($reservationId!==null){$q=$db->prepare('SELECT COUNT(*) FROM reservations WHERE id=? AND guest_id=?');$q->execute([$reservationId,$guestId]);if(!(int)$q->fetchColumn())throw new \RuntimeException('Reserva não pertence ao hóspede.');}
  $slug=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$guest);
  $slug=trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',(string)$slug)),'-')?:'hospede';
  $folder='cliente-'.$guestId.'-'.substr($slug,0,45);
  $dir=self::root().'/'.$folder;
  if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Pasta privada não disponível.');
  if(is_link($dir))throw new \RuntimeException('Pasta inválida.');
  $filename=bin2hex(random_bytes(20)).'.'.self::MIMES[$mime];$path=$dir.'/'.$filename;
  if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes))throw new \RuntimeException('Falha ao gravar arquivo.');
  chmod($path,0600);
  $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u','',basename($name))?:'documento',0,180);
  try{
   $db->prepare('INSERT INTO guest_documents(guest_id,reservation_id,conversation_id,source,kind,original_name,mime,size_bytes,relative_path,sha256,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
      ->execute([$guestId,$reservationId,$conversationId,$source,$kind,$name,$mime,strlen($bytes),$folder.'/'.$filename,hash('sha256',$bytes)]);
   $id=(int)$db->lastInsertId();Audit::log('guest_document.upload','guest_document',$id,['guest_id'=>$guestId,'source'=>$source]);return $id;
  }catch(\Throwable $e){@unlink($path);throw $e;}
 }
 public function listFor(int $guestId,?int $reservationId):array{
  $q=DB::pdo()->prepare('SELECT id,kind,source,original_name,created_at FROM guest_documents WHERE guest_id=? AND (reservation_id IS NULL OR reservation_id=?) ORDER BY id DESC LIMIT 100');
  $q->execute([$guestId,$reservationId??0]);return $q->fetchAll();
 }
 public static function links(array $docs):string{
  if(!$docs)return '<p class="muted">Nenhum documento recebido.</p>';
  $s='<ul class="guest-documents-list">';
  foreach($docs as $d)$s.='<li><a href="index.php?p=guest_document_download&id='.(int)$d['id'].'">'.U::e($d['original_name']).'</a> <small>'.U::e($d['kind']).' · '.U::e($d['source']).' · '.U::e($d['created_at']).'</small></li>';
  return $s.'</ul>';
 }
 public function download(int $id):never{
  $q=DB::pdo()->prepare('SELECT * FROM guest_documents WHERE id=?');$q->execute([$id]);$d=$q->fetch();
  if(!$d){http_response_code(404);exit('Documento não encontrado.');}
  $allowed=Auth::can('messages.manage')||Auth::can('reservations.manage')||Auth::can('guests.view');
  if(!$allowed){$t=$_SESSION['traveler']??[];$allowed=(int)($t['guest_id']??0)===(int)$d['guest_id']&&($d['reservation_id']===null||(int)($t['reservation_id']??0)===(int)$d['reservation_id']);}
  if(!$allowed){http_response_code(403);exit('Acesso negado.');}
  $relative=(string)$d['relative_path'];
  if(!preg_match('~^cliente-[a-zA-Z0-9-]+/[a-f0-9]{40}\.(pdf|jpg|png|webp)$~D',$relative))throw new \RuntimeException('Arquivo inválido.');
  $root=realpath(self::root());$path=realpath(self::root().'/'.$relative);
  if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('Arquivo indisponível.');}
  if((int)filesize($path)!==(int)$d['size_bytes'])throw new \RuntimeException('Integridade inválida.');
  header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
  $name=preg_replace('/[^a-zA-Z0-9._-]/','_',(string)$d['original_name'])?:'documento';
  header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($path));
  readfile($path);exit;
 }
}
