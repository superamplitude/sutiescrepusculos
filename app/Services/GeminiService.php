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
    $c=$this->config();
    if(empty($c['enabled']))return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'error'=>'Atendimento por IA desativado'];
    // Project identity and access remain in the existing protected Suites voice configuration.
    // No browser key, no new credential, no direct call to the Google API.
    $file='/etc/crepusculo/voice.json';
    if(!is_readable($file))return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'error'=>'Credencial central indisponível'];
    try{$auth=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}
    catch(\Throwable){return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'error'=>'Credencial central inválida'];}
    $token=(string)($auth['token']??'');
    // Prefer the already configured and validated textual Gemini credential.
    $key=Secret::decrypt((string)($c['api_key']??''));
    if($key==='')$key=(string)($auth['provider_api_key']??'');
    if($token===''||$key==='')return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'error'=>'Projeto sem credenciais para IA'];
    $ctx=$includeKnowledge?$this->knowledge():'';
    $agent=trim((string)Site::get('chat_agent_name','Assistente IA'))?:'Assistente IA';
    $central=trim((string)($c['system_instruction']??''));
    $property=BusinessContextService::property();
    $rules="Você é {$agent}, assistente de atendimento.\\n".BusinessContextService::instructions($property);
    if($central!=='')$rules.="\\nINSTRUÇÃO DO SUPER ADMIN:\\n".$central;
    $prompt=$rules."\\n\\nBASE CONFIRMADA:\\n".$ctx."\\n\\nPERGUNTA DO CLIENTE:\\n".$q;
    $model=(string)($c['model']?:'gemini-2.5-flash');
    if(!preg_match('/^gemini-[a-z0-9.\\-]{2,55}$/D',$model))$model='gemini-2.5-flash';
    if(!function_exists('curl_init'))return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'error'=>'cURL indisponível'];
    $url='http://127.0.0.1:13002/communication/v1/projects/suites-crepusculo/ai/generate';
    $ch=curl_init($url);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','Authorization: Bearer '.$token],
      CURLOPT_POSTFIELDS=>json_encode(['prompt'=>$prompt,'model'=>$model,'provider_api_key'=>$key],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR),
      CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>($includeKnowledge?42:48),
      CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4
    ]);
    $raw=curl_exec($ch);
    $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    $payload=is_string($raw)?json_decode($raw,true):null;
    if($err||$http!==200||!is_array($payload)||empty($payload['ok'])||!is_string($payload['answer']??null)){
      return ['ok'=>false,'mode'=>'central','answer'=>$this->fallback($q),'http'=>$http,'error'=>(string)($payload['error']??($err?'CENTRAL_UNAVAILABLE':'CENTRAL_REQUEST_FAILED'))];
    }
    return ['ok'=>true,'mode'=>'central','answer'=>$payload['answer'],'http'=>200,'model'=>$payload['model']??$model];
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
