<?php
declare(strict_types=1);
namespace App\Core;
final class PageRoutes {
 public static function all():array{return json_decode((string)file_get_contents(dirname(__DIR__,2).'/config/page-routes.json'),true,512,JSON_THROW_ON_ERROR);}
 public static function resolve(string $name,string $method,string $access):?string {
  $r=self::all()[$name]??null;
  if(!$r||$r['access']!==$access||!in_array($method,$r['methods'],true))return null;
  if($r['file']!==$name.'.php'||!preg_match('/^[a-z][a-z0-9_]*\.php$/D',$r['file']))throw new \RuntimeException('Invalid registered page');
  return dirname(__DIR__,2).'/pages/'.$r['file'];
 }
}
