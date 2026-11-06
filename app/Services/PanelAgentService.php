<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\DB;
use PDO;

final class PanelAgentService {
    private const PERMISSIONS=[
        'content_update'=>'content.publish','room_price'=>'rooms.manage',
        'property_update'=>'property.edit','map_block'=>'calendar.block',
        'map_unblock'=>'calendar.block','guest_register'=>'guests.view',
        'reservation_prebook'=>'reservations.manage'
    ];
    private static function pending():?array {
        $p=$_SESSION['_suites_agent_pending']??null;
        return is_array($p)&&((int)($p['at']??0)>time()-600)?$p:null;
    }
    public function respond(string $question):array {
        if(!Auth::can('ai.use'))throw new \RuntimeException('Sem autorização para usar a IA.');
        unset($_SESSION['_suites_agent_pending']);
        $pdo=DB::pdo();
        $property=BusinessContextService::property();
        $prop=[];
        foreach(['name','category','city','state','address','address_number','neighborhood','zip','map_url','latitude','longitude','phone','whatsapp','checkin','checkout'] as $field) {
            if(isset($property[$field]))$prop[$field]=mb_substr((string)$property[$field],0,300);
        }
        $permissions=[];foreach(self::PERMISSIONS as $action=>$permission)if(Auth::can($permission))$permissions[]=$action;
        $input=['authorized_types'=>$permissions,'property'=>$prop,'question'=>$question];
        $lower=mb_strtolower($question);
        if(preg_match('/di[aá]ria|pre[cç]o|tarifa|quarto|acomoda[cç][aã]o|su[ií]te|reserv|h[oó]sped|calend[aá]rio|mapa|bloque/iu',$lower)){
            $input['rooms']=$pdo->query("SELECT id,name,base_price,capacity,status FROM rooms WHERE active=1 ORDER BY id LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
            $input['blocks']=$pdo->query("SELECT id,room_id,start_date,end_date,type FROM room_blocks WHERE end_date>=CURDATE() ORDER BY start_date LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/texto|p[aá]gina|conte[uú]do|site|descri[cç][aã]o|t[ií]tulo|seo/iu',$lower)){
            $input['contents']=$pdo->query("SELECT id,slug,title,LEFT(body,1000) AS body,status FROM contents ORDER BY id DESC LIMIT 18")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/reserv|h[oó]sped|check.in|check.out|entrada|sa[ií]da/iu',$lower)){
            $input['upcoming_reservations']=$pdo->query("SELECT r.id,r.code,r.room_id,r.checkin,r.checkout,r.status FROM reservations r WHERE r.checkout>=CURDATE() ORDER BY r.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        }
        $instructions="Você é a assistente virtual do painel de gestão. Converse em português brasileiro, com acolhimento e respostas curtas e naturais. Perguntas vagas como 'pode alterar uns detalhes?' devem ser respondidas normalmente, perguntando o que o gestor deseja modificar. NUNCA afirme que já alterou algo. Se o usuário pedir uma mudança com todos os dados, retorne JSON com 'answer' (texto breve) e 'action' (objeto); em dúvidas ou pedidos incompletos action=null. Nunca invente IDs, valores, disponibilidade ou confirmação de pagamento. Ações permitidas apenas em authorized_types: content_update {content_id,body}; room_price {room_id,price}; property_update {field,value} com field em address,address_number,city,state,neighborhood,zip,map_url,latitude,longitude,phone,whatsapp; map_block {room_id,start_date,end_date,reason}; map_unblock {block_id}; guest_register {name,phone,email}; reservation_prebook {guest_id,room_id,checkin,checkout,guests}. Para content_update inclua o texto integral revisado (pode pedir para o gestor abrir o editor se o trecho disponível for incompleto). Toda ação será proposta e exigirá confirmação; para mapa_unblock não remova reservas. Retorne apenas JSON válido, sem cercas Markdown.";
        $payload=json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
        if($payload===false)throw new \RuntimeException('Não foi possível preparar o contexto.');
        $response=(new GeminiService())->ask($instructions."\nCONTEXTO OPERACIONAL SELECIONADO:\n".$payload,false);
        if(empty($response['ok'])){
            $http=(int)($response['http']??0);
            $reason=$http===429?'O provedor está ocupado. Tente novamente.':($http===0?'A IA demorou para responder. Tente novamente.':'A IA não concluiu a resposta. Tente novamente.');
            throw new \RuntimeException($reason);
        }
        $raw=trim((string)($response['answer']??''));
        $json=json_decode($raw,true);
        if(!is_array($json)&&preg_match('/\{[\s\S]*\}/u',$raw,$match))$json=json_decode($match[0],true);
        $answer=trim((string)($json['answer']??$raw));
        $action=$json['action']??null;
        if(!is_array($action)||!isset(self::PERMISSIONS[$action['type']??'']))return ['answer'=>$answer?:'Informe o que deseja consultar ou alterar.'];
        $kind=(string)$action['type'];
        if(!Auth::can(self::PERMISSIONS[$kind]))return ['answer'=>'Seu perfil não tem permissão para executar essa alteração.'];
        try{
            [$clean,$label]=self::validate($action);
        }catch(\Throwable $e){return ['answer'=>$answer."\nNão foi possível preparar a alteração: ".$e->getMessage()];}
        $token=bin2hex(random_bytes(12));
        $_SESSION['_suites_agent_pending']=['at'=>time(),'token'=>$token,'action'=>$clean,'label'=>$label];
        return ['answer'=>($answer?:'Verifique a alteração solicitada.')."\nProposta: ".$label."\nA alteração ainda NÃO foi aplicada.",'proposal'=>['token'=>$token,'label'=>$label]];
    }
    private static function date(string $value):string{
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$d||$d->format('Y-m-d')!==$value)throw new \RuntimeException('Data inválida, use AAAA-MM-DD.');
        return $value;
    }
    private static function validate(array $action):array {
        $pdo=DB::pdo();$type=(string)($action['type']??'');$a=['type'=>$type];
        if($type==='content_update'){
            $a['content_id']=(int)($action['content_id']??0);$a['body']=trim((string)($action['body']??''));
            $q=$pdo->prepare('SELECT title FROM contents WHERE id=?');$q->execute([$a['content_id']]);$title=$q->fetchColumn();
            if(!$title||$a['body']===''||mb_strlen($a['body'])>30000)throw new \RuntimeException('Conteúdo inexistente ou texto inválido.');
            return [$a,'Atualizar texto integral de "'.$title.'" (ID '.$a['content_id'].')'];
        }
        if($type==='room_price'){
            $a['room_id']=(int)($action['room_id']??0);$a['price']=(float)($action['price']??-1);
            $q=$pdo->prepare('SELECT name FROM rooms WHERE id=?');$q->execute([$a['room_id']]);$name=$q->fetchColumn();
            if(!$name||!is_finite($a['price'])||$a['price']<0||$a['price']>1000000)throw new \RuntimeException('Unidade ou valor inválido.');
            return [$a,'Alterar a diária-base de "'.$name.'" para R$ '.number_format($a['price'],2,',','.')];
        }
        if($type==='property_update'){
            $a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            if(!in_array($a['field'],['address','address_number','city','state','neighborhood','zip','map_url','latitude','longitude','phone','whatsapp'],true)||mb_strlen($a['value'])>500)throw new \RuntimeException('Campo não autorizado.');
            if($a['field']==='map_url'&&$a['value']!==''&&!preg_match('~^https://~i',$a['value']))throw new \RuntimeException('Mapa exige endereço HTTPS.');
            if(in_array($a['field'],['latitude','longitude'],true)&&$a['value']!==''&&(!is_numeric($a['value'])||abs((float)$a['value'])>($a['field']==='latitude'?90:180)))throw new \RuntimeException('Coordenada inválida.');
            return [$a,'Atualizar '.$a['field'].' para "'.$a['value'].'"'];
        }
        if($type==='map_block'){
            $a['room_id']=(int)($action['room_id']??0);$a['start_date']=self::date((string)($action['start_date']??''));$a['end_date']=self::date((string)($action['end_date']??''));$a['reason']=mb_substr(trim((string)($action['reason']??'Bloqueio pelo gestor via IA')),0,250);
            if($a['start_date']>=$a['end_date'])throw new \RuntimeException('A data final deve ser posterior à inicial.');
            $q=$pdo->prepare('SELECT name FROM rooms WHERE id=? AND active=1');$q->execute([$a['room_id']]);$name=$q->fetchColumn();
            if(!$name)throw new \RuntimeException('Unidade inválida.');
            return [$a,'Bloquear "'.$name.'" de '.$a['start_date'].' até '.$a['end_date']];
        }
        if($type==='map_unblock'){
            $a['block_id']=(int)($action['block_id']??0);
            $q=$pdo->prepare('SELECT b.start_date,b.end_date,r.name FROM room_blocks b JOIN rooms r ON r.id=b.room_id WHERE b.id=?');$q->execute([$a['block_id']]);$b=$q->fetch();
            if(!$b)throw new \RuntimeException('Bloqueio não encontrado.');
            return [$a,'Remover apenas bloqueio #'.$a['block_id'].' de "'.$b['name'].'" ('.$b['start_date'].' a '.$b['end_date'].')'];
        }
        if($type==='guest_register'){
            $a['name']=trim((string)($action['name']??''));$a['phone']=preg_replace('/[^0-9+]/','',(string)($action['phone']??''));$a['email']=trim((string)($action['email']??''));
            if(mb_strlen($a['name'])<3||mb_strlen($a['name'])>120||($a['phone']===''&&$a['email']==='')||($a['email']!==''&&!filter_var($a['email'],FILTER_VALIDATE_EMAIL)))throw new \RuntimeException('É necessário nome completo e telefone ou e-mail válido.');
            return [$a,'Cadastrar hóspede "'.$a['name'].'" (sem confirmar reserva)'];
        }
        if($type==='reservation_prebook'){
            $a['guest_id']=(int)($action['guest_id']??0);$a['room_id']=(int)($action['room_id']??0);
            $a['checkin']=self::date((string)($action['checkin']??''));$a['checkout']=self::date((string)($action['checkout']??''));$a['guests']=(int)($action['guests']??1);
            if($a['checkin']>=$a['checkout']||$a['guests']<1||$a['guests']>30)throw new \RuntimeException('Período ou número de hóspedes inválido.');
            $q=$pdo->prepare('SELECT name FROM guests WHERE id=?');$q->execute([$a['guest_id']]);$name=$q->fetchColumn();
            if(!$name)throw new \RuntimeException('Hóspede não identificado.');
            (new ReservationService())->price($a['room_id'],$a['checkin'],$a['checkout'],$a['guests']);
            return [$a,'Gerar pré-reserva de '.$name.' entre '.$a['checkin'].' e '.$a['checkout'].'; pagamento NÃO confirmado'];
        }
        throw new \RuntimeException('Operação indisponível.');
    }
    public function confirm(string $token):array {
        $pending=self::pending();
        if(!$pending||!hash_equals((string)$pending['token'],$token))throw new \RuntimeException('Confirmação expirada. Solicite a alteração novamente.');
        unset($_SESSION['_suites_agent_pending']);
        $a=$pending['action'];$type=$a['type'];
        if(!Auth::can(self::PERMISSIONS[$type]))throw new \RuntimeException('Sem permissão para esta operação.');
        self::validate($a);
        $pdo=DB::pdo();$id=0;
        if($type==='content_update'){
            $pdo->prepare('UPDATE contents SET body=?,updated_at=NOW() WHERE id=?')->execute([$a['body'],$a['content_id']]);$id=$a['content_id'];
        }elseif($type==='room_price'){
            $pdo->prepare('UPDATE rooms SET base_price=?,updated_at=NOW() WHERE id=?')->execute([$a['price'],$a['room_id']]);$id=$a['room_id'];
        }elseif($type==='property_update'){
            $field=$a['field'];$value=($a['value']===''&&in_array($field,['latitude','longitude'],true))?null:$a['value'];
            $pdo->prepare("UPDATE property SET {$field}=?,updated_at=NOW() WHERE id=1")->execute([$value]);$id=1;
        }elseif($type==='map_block'){
            if(!(new ReservationService())->available($a['room_id'],$a['start_date'],$a['end_date']))throw new \RuntimeException('O período está ocupado ou bloqueado. Nada foi modificado.');
            $pdo->prepare("INSERT INTO room_blocks(room_id,start_date,end_date,type,reason,created_by,created_at) VALUES(?,?,?,'blocked',?,?,NOW())")->execute([$a['room_id'],$a['start_date'],$a['end_date'],$a['reason'],(int)Auth::user()['id']]);$id=(int)$pdo->lastInsertId();
        }elseif($type==='map_unblock'){
            $pdo->prepare('DELETE FROM room_blocks WHERE id=?')->execute([$a['block_id']]);$id=$a['block_id'];
        }elseif($type==='guest_register'){
            $pdo->prepare('INSERT INTO guests(name,phone,email,privacy_consent,created_at,updated_at) VALUES(?,?,?,0,NOW(),NOW())')->execute([$a['name'],$a['phone']?:null,$a['email']?:null]);$id=(int)$pdo->lastInsertId();
        }elseif($type==='reservation_prebook'){
            $id=(new ReservationService())->create([...$a,'initial_status'=>'pre_reserva','source'=>'painel']);
        }
        Audit::log('panel_agent.'.$type,$type,$id,['from'=>'confirmed_chat','actor'=>(int)Auth::user()['id']]);
        return ['answer'=>'Alteração confirmada e registrada: '.$pending['label'].'. Registro #'.$id.'.'];
    }
    public function cancel():array {unset($_SESSION['_suites_agent_pending']);return ['answer'=>'Alteração cancelada. Nenhum dado foi modificado.'];}
}
