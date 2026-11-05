<?php
namespace App\Core;
final class U{
  public static function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
  public static function redirect(string $u):never{header("Location:$u");exit;}
  public static function money(float $v,string $currency='BRL'):string{
    $symbols=['BRL'=>'R$','USD'=>'US$','EUR'=>'€','GBP'=>'£'];
    $sym=$symbols[$currency]??$currency;
    return $sym.' '.number_format($v,2,',','.');
  }
  public static function flash(string $k,?string $v=null):?string{
    Auth::start();
    if($v!==null){$_SESSION['_flash'][$k]=$v;return null;}
    $x=$_SESSION['_flash'][$k]??null;unset($_SESSION['_flash'][$k]);return $x;
  }
}
