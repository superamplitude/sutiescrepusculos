<?php
declare(strict_types=1);
namespace App\Services;
final class SuitesMessagesService {
 private const BASE='http://127.0.0.1:18190/sessions/suites-crepusculo';
 private function request(string $method,string $action):array{
  $ch=curl_init(self::BASE.'/'.$action);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json']]);
  if($method==='POST')curl_setopt($ch,CURLOPT_POSTFIELDS,'{}');
  $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode((string)$raw,true);
  if($http!==200||!is_array($data)||empty($data['ok']))throw new \RuntimeException('O serviço de mensagens está indisponível.');
  return $data;
 }
 public function status():array{return $this->request('GET','status')['session']??['status'=>'offline'];}
 public function qr():?string{
  $state=(string)($this->status()['status']??'offline');
  if($state==='connected')return null;
  if(!in_array($state,['qr','connecting'],true))$this->request('POST','connect');
  for($i=0;$i<8;$i++){
   $result=$this->request('GET','qr');if(!empty($result['qr']))return (string)$result['qr'];
   usleep(300000);
  }
  throw new \RuntimeException('O QR de mensagens está sendo preparado. Clique novamente em alguns segundos.');
 }
}
