<?php
declare(strict_types=1);
namespace App\Services;
final class SimplePdf {
  // Self-contained PDF writer: no third-party dependency or shell process.
  public static function download(string $filename,string $title,array $lines):never {
    $pages=[];$chunk=[];
    foreach($lines as $line){$chunk[]=mb_substr((string)$line,0,115);if(count($chunk)>=44){$pages[]=$chunk;$chunk=[];}}
    if($chunk||!$pages)$pages[]=$chunk;
    $objects=[];$pageIds=[];$contentIds=[];
    $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    foreach($pages as $i=>$page){
      $pid=4+$i*2;$cid=$pid+1;$pageIds[]=$pid;$contentIds[]=$cid;
      $stream="BT /F1 17 Tf 42 799 Td ".self::lit($title)." Tj ET\n";
      $y=763;
      foreach($page as $row){$stream.="BT /F1 10 Tf 42 ".$y." Td ".self::lit($row)." Tj ET\n";$y-=16;}
      $objects[$pid]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$cid.' 0 R >>';
      $objects[$cid]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
    }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($i)=>$i.' 0 R',$pageIds)).'] /Count '.count($pages).' >>';
    ksort($objects);$body="%PDF-1.4\n";$offsets=[0];
    foreach($objects as $n=>$object){$offsets[$n]=strlen($body);$body.=$n." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($body);$max=max(array_keys($objects));
    $body.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=$max;$i++)$body.=sprintf('%010d 00000 n ',$offsets[$i]??0)."\n";
    $body.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_.-]/','_',$filename).'"');
    header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($body));echo $body;exit;
  }
  private static function lit(string $text):string {
    $converted=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
    $s=$converted===false?'':$converted;
    return '('.str_replace(["\\","(",")","\r","\n"],["\\\\","\\(","\\)"," "," "],$s).')';
  }
}
