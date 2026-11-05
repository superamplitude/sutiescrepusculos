<?php
namespace App\Core;
final class Csrf{
  public static function token():string{
    Auth::start(); if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf'];
  }
  public static function field():string{return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(self::token(),ENT_QUOTES,'UTF-8').'">';}
  public static function check():void{
    Auth::start();$v=$_POST['_csrf']??'';
    if(!$v||!hash_equals($_SESSION['_csrf']??'',$v)){http_response_code(419);exit('Token CSRF inválido');}
  }
}
