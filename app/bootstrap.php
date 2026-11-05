<?php
spl_autoload_register(function(string $class){
    $prefix='App\\';
    if(!str_starts_with($class,$prefix)) return;
    $path=__DIR__.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path)) require $path;
});
date_default_timezone_set('America/Sao_Paulo');
