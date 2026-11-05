<?php
namespace App\Core;

final class Auth {
  public static function start():void{
    if(session_status()===PHP_SESSION_ACTIVE)return;
    $name='pousada_ia';
    $f=dirname(__DIR__,2).'/config/config.php';
    if(is_file($f)){ $c=require $f; $name=$c['security']['session_name']??$name; }
    session_name($name);
    session_set_cookie_params([
      'httponly'=>true,
      'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),
      'samesite'=>'Lax'
    ]);
    session_start();
  }

  public static function user():?array{self::start();return $_SESSION['user']??null;}
  public static function login(array $u):void{self::start();session_regenerate_id(true);$_SESSION['user']=$u;}
  public static function logout():void{self::start();$_SESSION=[];session_destroy();}
  public static function requireLogin():void{if(!self::user()){header('Location:index.php?p=login');exit;}}
  public static function role():string{return (string)(self::user()['role']??'');}
  public static function isSuperAdmin():bool{return self::role()==='super_admin';}
  public static function isAdmin():bool{return self::role()==='admin';}

  public static function requireSuperAdmin():void{
    self::requireLogin();
    if(!self::isSuperAdmin()){http_response_code(403);exit('Acesso exclusivo do Super Admin');}
  }

  public static function can(string $perm):bool{
    $u=self::user();if(!$u)return false;
    if(($u['role']??'')==='super_admin')return true;
    return in_array($perm,$u['permissions']??[],true);
  }

  public static function requirePerm(string $p):void{
    self::requireLogin();
    if(!self::can($p)){http_response_code(403);exit('Acesso negado');}
  }
}
