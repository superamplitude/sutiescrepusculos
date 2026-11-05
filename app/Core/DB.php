<?php
namespace App\Core;
use PDO;
final class DB {
  private static ?PDO $pdo=null;
  public static function pdo():PDO{
    if(self::$pdo) return self::$pdo;
    $f=dirname(__DIR__,2).'/config/config.php';
    if(!is_file($f)) throw new \RuntimeException('Sistema não instalado. Acesse install.php');
    $c=require $f; $d=$c['db'];
    $dsn="mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";
    self::$pdo=new PDO($dsn,$d['user'],$d['pass'],[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return self::$pdo;
  }
}
