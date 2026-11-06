<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
final class VoiceProfileService {
 public static function normalize(array $p):array {
  return ['id'=>'suites-conversational-v1','tone'=>in_array($p['tone']??'', ['natural','acolhedor','profissional'],true)?$p['tone']:'natural','pace'=>in_array($p['pace']??'', ['equilibrado','tranquilo'],true)?$p['pace']:'equilibrado'];
 }
 public function get():array {
  try{$raw=DB::pdo()->query("SELECT profile_json FROM suites_voice_profiles WHERE profile_key='main'")->fetchColumn();return self::normalize(json_decode((string)$raw,true)?:[]);}catch(\Throwable){return self::normalize([]);}
 }
 public function save(array $p):void {
  $p=self::normalize($p);
  DB::pdo()->prepare("INSERT INTO suites_voice_profiles(profile_key,profile_json,updated_at) VALUES('main',?,NOW()) ON DUPLICATE KEY UPDATE profile_json=VALUES(profile_json),updated_at=NOW()")->execute([json_encode($p,JSON_UNESCAPED_UNICODE)]);
 }
 public function conversationInstructions():string {
  $p=$this->get();$tone=['natural'=>'próximo e espontâneo','acolhedor'=>'acolhedor e empático','profissional'=>'seguro, cordial e profissional'][$p['tone']];
  return "Converse com uma pessoa, com tom {$tone}. Em uma resposta comum, use uma a três frases conectadas e no máximo uma pergunta. Desenvolva mais quando a pessoa pedir. Evite repetir saudações, bordões e listas sem necessidade. Ao falar, use ritmo ".($p['pace']==='tranquilo'?'tranquilo, sem arrastar':'conversacional, sem correr').", variações suaves de entonação e pausas entre ideias, nunca entre todas as palavras. Ouça interrupções e aguarde a resposta do cliente. Não use cadência de GPS, anúncio ou narração. Preserve todos os nomes, datas, valores e fatos confirmados.";
 }
 public static function spokenText(string $text):string {
  $text=preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)]+\)/u','$1',$text);
  $text=preg_replace('/^\s{0,3}(?:#{1,6}\s+|[-*]\s+)/mu','',$text);
  $text=str_replace(['**','__',chr(96)],'',$text);$text=trim($text);
  if(mb_strlen($text)<=1100)return $text;
  $out='';
  foreach(preg_split('/(?<=[.!?])\s+/u',$text)?:[] as $sentence){if(mb_strlen($out.' '.$sentence)>1000)break;$out=trim($out.' '.$sentence);}
  return ($out!==''?$out.' ':'').'A resposta completa está na mensagem de texto. Posso explicar os detalhes por partes.';
 }
}
