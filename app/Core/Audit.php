<?php
namespace App\Core;
final class Audit{
  public static function log(string $a,string $entity='',?int $id=null,array $meta=[]):void{
    try{
      $u=Auth::user();
      $s=DB::pdo()->prepare("INSERT INTO audit_logs(user_id,action,entity,entity_id,ip,meta_json,created_at) VALUES(?,?,?,?,?,?,NOW())");
      $s->execute([$u['id']??null,$a,$entity,$id,$_SERVER['REMOTE_ADDR']??null,json_encode($meta,JSON_UNESCAPED_UNICODE)]);
    }catch(\Throwable $e){}
  }
}
