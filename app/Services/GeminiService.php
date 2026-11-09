<?php
namespace App\Services;
use App\Core\DB;
use App\Core\Secret;
use App\Core\Site;
final class GeminiService{
  public function config():array{
    $s=DB::pdo()->query("SELECT * FROM ai_config WHERE id=1");return $s->fetch()?:[];
  }
  public function ask(string $q,bool $includeKnowledge=true):array{
    // A direct, verified service fact must not depend on external model availability.
    // Administrative and conversation prompts are longer; those are handled by callers.
    if($includeKnowledge && mb_strlen($q)<=500){
      $fact=BusinessContextService::verifiedServiceAnswer($q);
      if($fact!==null)return ['ok'=>true,'mode'=>'verified_property_fact','answer'=>$fact];
    }
    $c=$this->config();$apiKey=Secret::decrypt($c['api_key']??'');
    if(empty($c['enabled'])||empty($apiKey))return ['ok'=>false,'mode'=>'fallback','answer'=>$this->fallback($q),'error'=>'Gemini não configurado'];
    if(!function_exists('curl_init'))return ['ok'=>false,'mode'=>'fallback','answer'=>$this->fallback($q),'error'=>'cURL indisponível'];
    $model=$c['model']?:'gemini-2.5-flash';
    $ctx=$includeKnowledge?$this->knowledge():'';
    $agent=trim((string)Site::get('chat_agent_name','Assistente IA'))?:'Assistente IA';
    $central=trim((string)($c['system_instruction']??''));
    $property=BusinessContextService::property();
    $rules="Você é {$agent}, assistente de atendimento.\n".BusinessContextService::instructions($property);
    if($central!=='')$rules.="\nINSTRUÇÃO DO SUPER ADMIN:\n".$central;
    $payload=['contents'=>[['parts'=>[['text'=>$rules."\n\nBASE CONFIRMADA:\n".$ctx."\n\nPERGUNTA DO CLIENTE:\n".$q]]]]];
    $ch=curl_init("https://generativelanguage.googleapis.com/v1beta/models/".rawurlencode($model).":generateContent?key=".rawurlencode($apiKey));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>($includeKnowledge?25:38),CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4]);
    $raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($err||$http<200||$http>=300)return ['ok'=>false,'mode'=>'gemini','answer'=>$this->fallback($q),'http'=>$http,'error'=>$err?:$raw];
    $j=json_decode($raw,true);$a='';foreach(($j['candidates'][0]['content']['parts']??[]) as $part)if(isset($part['text']))$a.=$part['text'];
    if(!$a)return ['ok'=>false,'mode'=>'gemini','answer'=>$this->fallback($q),'http'=>$http,'error'=>'Resposta vazia'];
    return ['ok'=>true,'mode'=>'gemini','answer'=>$a,'http'=>$http,'model'=>$model];
  }
  private function knowledge():string{
    $pdo=DB::pdo();$parts=[];
    $p=BusinessContextService::property();
    if($p)$parts[]="ESTABELECIMENTO: ".json_encode($p,JSON_UNESCAPED_UNICODE);
    if(BusinessContextService::isLodging($p)){
      $rooms=$pdo->query("SELECT name,type,description,capacity,base_price,view_name,bathroom_type FROM rooms WHERE active=1")->fetchAll();
      $parts[]="ACOMODAÇÕES CADASTRADAS (não comprovam disponibilidade): ".json_encode($rooms,JSON_UNESCAPED_UNICODE);
    }
    $kb=$pdo->query("SELECT title,content FROM knowledge_base WHERE active=1 ORDER BY id DESC LIMIT 50")->fetchAll();
    $parts[]="FAQ/BASE: ".json_encode($kb,JSON_UNESCAPED_UNICODE);
    return implode("\n",$parts);
  }
  private function fallback(string $q):string{
    $pdo=DB::pdo();$s=$pdo->prepare("SELECT content FROM knowledge_base WHERE active=1 AND (title LIKE ? OR content LIKE ?) ORDER BY id DESC LIMIT 1");
    $like='%'.trim($q).'%';$s->execute([$like,$like]);$x=$s->fetchColumn();
    return $x?:'Não encontrei essa informação na base cadastrada. Um membro da nossa equipe poderá confirmar.';
  }
}
