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
        'reservation_prebook'=>'reservations.manage',
        'tariff_price'=>'prices.edit','package_price'=>'prices.edit',
        'seo_update'=>'content.publish','promotion_create'=>'content.publish',
        'site_text'=>'site.manage','amenity_toggle'=>'property.edit',
        'room_update'=>'rooms.manage','content_manage'=>'content.publish',
        'tariff_create'=>'prices.edit','package_create'=>'prices.edit',
        'tariff_toggle'=>'prices.edit','package_toggle'=>'prices.edit',
        'promotion_manage'=>'content.publish','knowledge_manage'=>'ai.use',
        'reservation_status'=>'reservations.manage','media_manage'=>'content.publish',
        'room_amenity_toggle'=>'rooms.manage','site_setting'=>'site.manage','media_cover'=>'property.edit'
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
        foreach(['name','category','short_description','description','city','state','address','address_number','neighborhood','zip','map_url','latitude','longitude','phone','whatsapp','checkin','checkout','cancellation_policy','house_rules'] as $field) {
            if(isset($property[$field]))$prop[$field]=mb_substr((string)$property[$field],0,300);
        }
        $permissions=[];foreach(self::PERMISSIONS as $action=>$permission)if(Auth::can($permission))$permissions[]=$action;
        $input=['authorized_types'=>$permissions,'property'=>$prop,'question'=>$question];
        if(Auth::can('site.manage')){
            $input['site_public_settings']=['site_name'=>\App\Core\Site::get('site_name'),'tagline'=>\App\Core\Site::get('tagline'),'chat_agent_name'=>\App\Core\Site::get('chat_agent_name'),'chat_welcome'=>\App\Core\Site::get('chat_welcome'),'footer_text'=>\App\Core\Site::get('footer_text')];
            $ui=json_decode((string)\App\Core\Site::get('ui_json','{}'),true);$input['site_texts']=is_array($ui)?array_intersect_key($ui,array_flip(['hero_kicker','hero_title','hero_description','intro_title','footer_bottom','booking_cta','menu_home','menu_about','menu_accommodations','menu_gastronomy','menu_experiences','menu_offers','menu_contact','hero_primary_cta','hero_secondary_cta'])):[];
        }
        $lower=mb_strtolower($question);
        if(preg_match('/di[aá]ria|pre[cç]o|tarifa|quarto|acomoda[cç][aã]o|su[ií]te|reserv|h[oó]sped|calend[aá]rio|mapa|bloque/iu',$lower)){
            $input['rooms']=$pdo->query("SELECT id,name,short_description,capacity,status,active,min_nights FROM rooms WHERE active=1 ORDER BY id LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
            $input['blocks']=$pdo->query("SELECT id,room_id,start_date,end_date,type FROM room_blocks WHERE end_date>=CURDATE() ORDER BY start_date LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/texto|p[aá]gina|conte[uú]do|site|descri[cç][aã]o|t[ií]tulo|seo|marketing|promo[cç][aã]o|campanha/iu',$lower)){
            $input['contents']=$pdo->query("SELECT id,slug,title,seo_title,meta_description,LEFT(body,1000) AS body,status FROM contents ORDER BY id DESC LIMIT 18")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/di[aá]ria|tarifa|pacote|pre[cç]o|marketing|promo[cç][aã]o/iu',$lower)){
            $input['tariffs']=$pdo->query("SELECT id,room_id,name,start_date,end_date,price,active FROM tariffs ORDER BY id DESC LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
            $input['packages']=$pdo->query("SELECT id,room_id,name,start_date,end_date,price,active FROM room_packages ORDER BY id DESC LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/caf[eé]|manh[aã]|comodidade|servi[cç]o|piscina|estacionamento|wi.fi/iu',$lower)){
            $input['room_amenities']=$pdo->query("SELECT r.id room_id,r.name room_name,a.id amenity_id,a.name amenity_name,IF(ra.amenity_id IS NULL,0,1) AS enabled FROM rooms r JOIN amenities a ON a.scope='room' AND a.active=1 LEFT JOIN room_amenities ra ON ra.room_id=r.id AND ra.amenity_id=a.id WHERE r.active=1 ORDER BY r.id,a.name LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);
            $input['property_amenities']=$pdo->query("SELECT a.id,a.name,IF(pa.amenity_id IS NULL,0,1) AS enabled FROM amenities a LEFT JOIN property_amenities pa ON pa.amenity_id=a.id AND pa.property_id=1 WHERE a.scope='property' AND a.active=1 ORDER BY a.name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/marketing|promo[cç][aã]o|campanha|cupom|oferta/iu',$lower)){
            $input['promotions']=$pdo->query("SELECT id,title,description,code,discount_type,discount_value,start_date,end_date,active FROM promotions ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/foto|imagem|galeria|legenda|capa|m[ií]dia/iu',$lower)){
            $input['media']=$pdo->query("SELECT id,scope,entity_id,type,caption,alt_text,sort_order,active FROM media ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/ia|atend|resposta|conhecimento|faq|pergunta|pol[ií]tica/iu',$lower)){
            $input['knowledge']=$pdo->query("SELECT id,title,LEFT(content,600) AS content,active FROM knowledge_base ORDER BY id DESC LIMIT 35")->fetchAll(PDO::FETCH_ASSOC);
        }
        if(preg_match('/reserv|h[oó]sped|check.in|check.out|entrada|sa[ií]da/iu',$lower)){
            $input['upcoming_reservations']=$pdo->query("SELECT r.id,r.code,r.room_id,r.checkin,r.checkout,r.status FROM reservations r WHERE r.checkout>=CURDATE() ORDER BY r.id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        }
        $instructions="Você é a assistente virtual do painel de gestão. Converse em português brasileiro, com acolhimento e respostas curtas e naturais. Perguntas vagas como 'pode alterar uns detalhes?' devem ser respondidas normalmente, perguntando o que o gestor deseja modificar. NUNCA afirme que já alterou algo. Se o usuário pedir uma mudança com todos os dados, retorne JSON com 'answer' (texto breve) e 'action' (objeto); em dúvidas ou pedidos incompletos action=null. Nunca invente IDs, valores, disponibilidade ou confirmação de pagamento. A tarifa real da diária está em tariffs e o pacote em room_packages; room_price é apenas preço-base legado. Para pedidos de diária com tarifa identificada use tariff_price {tariff_id,price}; para pacote identificado use package_price {package_id,price}. Comodidade da pousada: amenity_toggle {amenity_id,enabled} para a lista property_amenities; o switch deve refletir no atendimento. SEO de página existente: seo_update {content_id,seo_title,meta_description}. Marketing: promotion_create {title,description,code,discount_type,discount_value,start_date,end_date}; o administrador precisa confirmar a criação. Texto visual do site: site_text {key,value}, key entre hero_title,hero_description,hero_kicker,intro_title,footer_bottom,booking_cta,menu_home,menu_about,menu_accommodations,menu_gastronomy,menu_experiences,menu_offers,menu_contact,hero_primary_cta,hero_secondary_cta. Nunca prometa publicação em Google, Facebook ou Instagram sem conexão real. Adicionalmente: room_amenity_toggle {room_id,amenity_id,enabled} controla o switch de comodidade por unidade; site_setting {field,value} para site_name,tagline,chat_agent_name,chat_welcome,footer_text; media_cover {media_id} para tornar uma imagem da propriedade a capa principal (não exclui arquivos). Adicionalmente: room_update {room_id,field,value} para name,short_description,description,capacity,min_nights,status,active; content_manage {content_id,field,value} para title,slug,status,seo_title,meta_description; tariff_create {room_id,name,start_date,end_date,price,min_nights,priority}; package_create {room_id,name,description,start_date,end_date,price,nights}; tariff_toggle {tariff_id,enabled}; package_toggle {package_id,enabled}; promotion_manage {promotion_id,field,value} para title,description,active; knowledge_manage {knowledge_id,field,value} para title,content,active; reservation_status {reservation_id,status,reason} somente conforme transições reais, nunca afirme pagamento; media_manage {media_id,field,value} para caption,alt_text,sort_order,active, sem adicionar/remover arquivos. Para operações de criação, exija todos os dados e confirmação. Não invente IDs. Ações permitidas apenas em authorized_types: content_update {content_id,body}; room_price {room_id,price}; property_update {field,value} com field em address,address_number,city,state,neighborhood,zip,map_url,latitude,longitude,phone,whatsapp; map_block {room_id,start_date,end_date,reason}; map_unblock {block_id}; guest_register {name,phone,email}; reservation_prebook {guest_id,room_id,checkin,checkout,guests}. Para content_update inclua o texto integral revisado (pode pedir para o gestor abrir o editor se o trecho disponível for incompleto). Toda ação será proposta e exigirá confirmação; para mapa_unblock não remova reservas. Retorne apenas JSON válido, sem cercas Markdown.";
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
        if($type==='room_amenity_toggle'){
            $a['room_id']=(int)($action['room_id']??0);$a['amenity_id']=(int)($action['amenity_id']??0);$a['enabled']=!empty($action['enabled'])?1:0;
            $q=$pdo->prepare("SELECT r.name room_name,a.name amenity_name FROM rooms r JOIN amenities a ON a.id=? AND a.scope='room' AND a.active=1 WHERE r.id=? AND r.active=1");$q->execute([$a['amenity_id'],$a['room_id']]);$r=$q->fetch();
            if(!$r)throw new \RuntimeException('Unidade ou comodidade inexistente.');
            return [$a,($a['enabled']?'Ativar':'Desativar').' "'.$r['amenity_name'].'" em "'.$r['room_name'].'"'];
        }
        if($type==='site_setting'){
            $a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            $limits=['site_name'=>180,'tagline'=>300,'chat_agent_name'=>100,'chat_welcome'=>1500,'footer_text'=>3000];
            if(!isset($limits[$a['field']])||mb_strlen($a['value'])>$limits[$a['field']])throw new \RuntimeException('Configuração de site inválida.');
            return [$a,'Atualizar configuração global '.$a['field']];
        }
        if($type==='media_cover'){
            $a['media_id']=(int)($action['media_id']??0);
            $q=$pdo->prepare("SELECT id FROM media WHERE id=? AND scope='property' AND entity_id=1 AND type='image'");$q->execute([$a['media_id']]);
            if(!$q->fetchColumn())throw new \RuntimeException('Imagem da propriedade inexistente.');
            return [$a,'Definir mídia #'.$a['media_id'].' como capa principal'];
        }
        if($type==='room_update'){
            $a['room_id']=(int)($action['room_id']??0);$a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            $allowed=['name'=>120,'short_description'=>2000,'description'=>12000,'capacity'=>3,'min_nights'=>3,'status'=>20,'active'=>1];
            $q=$pdo->prepare("SELECT name FROM rooms WHERE id=? AND code NOT LIKE '__deleted__%'");$q->execute([$a['room_id']]);$name=$q->fetchColumn();
            if(!$name||!isset($allowed[$a['field']])||mb_strlen($a['value'])>$allowed[$a['field']])throw new \RuntimeException('Quarto/campo inválido.');
            if(in_array($a['field'],['capacity','min_nights'],true)&&(!ctype_digit($a['value'])||(int)$a['value']<1||(int)$a['value']>100))throw new \RuntimeException('Capacidade/noites inválidas.');
            if($a['field']==='status'&&!in_array($a['value'],['active','inactive','maintenance'],true))throw new \RuntimeException('Status inválido.');
            if($a['field']==='active'&&!in_array($a['value'],['0','1'],true))throw new \RuntimeException('Use 0 ou 1.');
            if($a['field']==='name'&&mb_strlen($a['value'])<2)throw new \RuntimeException('Nome curto demais.');
            return [$a,'Alterar '.$a['field'].' da unidade "'.$name.'"'];
        }
        if($type==='content_manage'){
            $a['content_id']=(int)($action['content_id']??0);$a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            $limits=['title'=>180,'slug'=>200,'status'=>20,'seo_title'=>180,'meta_description'=>500];
            $q=$pdo->prepare("SELECT title FROM contents WHERE id=?");$q->execute([$a['content_id']]);$name=$q->fetchColumn();
            if(!$name||!isset($limits[$a['field']])||mb_strlen($a['value'])>$limits[$a['field']])throw new \RuntimeException('Conteúdo/campo inválido.');
            if($a['field']==='status'&&!in_array($a['value'],['draft','published'],true))throw new \RuntimeException('Status de publicação inválido.');
            if($a['field']==='slug'&&!preg_match('/^[a-z0-9-]{2,200}$/D',$a['value']))throw new \RuntimeException('Slug inválido.');
            return [$a,'Atualizar '.$a['field'].' do conteúdo "'.$name.'"'];
        }
        if($type==='tariff_create'||$type==='package_create'){
            $a['room_id']=(int)($action['room_id']??0);
            $q=$pdo->prepare("SELECT name FROM rooms WHERE id=? AND active=1 AND code NOT LIKE '__deleted__%'");$q->execute([$a['room_id']]);$room=$q->fetchColumn();
            $a['name']=trim((string)($action['name']??''));$a['start_date']=self::date((string)($action['start_date']??''));$a['end_date']=self::date((string)($action['end_date']??''));$a['price']=(float)($action['price']??-1);
            if(!$room||mb_strlen($a['name'])<3||mb_strlen($a['name'])>120||$a['start_date']>$a['end_date']||!is_finite($a['price'])||$a['price']<0||$a['price']>1000000)throw new \RuntimeException('Período, unidade ou preço inválido.');
            if($type==='tariff_create'){
                $a['min_nights']=(int)($action['min_nights']??1);$a['priority']=(int)($action['priority']??0);
                if($a['min_nights']<1||$a['min_nights']>365||$a['priority']<0||$a['priority']>10000)throw new \RuntimeException('Noites ou prioridade inválidas.');
            }else{
                $a['nights']=(int)($action['nights']??0);$a['description']=trim((string)($action['description']??''));
                if($a['nights']<1||$a['nights']>365||mb_strlen($a['description'])>3000)throw new \RuntimeException('Pacote inválido.');
            }
            return [$a,'Criar '.($type==='tariff_create'?'diária':'pacote').' "'.$a['name'].'" para "'.$room.'"'];
        }
        if($type==='tariff_toggle'||$type==='package_toggle'){
            $table=$type==='tariff_toggle'?'tariffs':'room_packages';$key=$type==='tariff_toggle'?'tariff_id':'package_id';
            $a[$key]=(int)($action[$key]??0);$a['enabled']=!empty($action['enabled'])?1:0;
            $q=$pdo->prepare("SELECT name FROM {$table} WHERE id=?");$q->execute([$a[$key]]);$name=$q->fetchColumn();
            if(!$name)throw new \RuntimeException('Registro inexistente.');
            return [$a,($a['enabled']?'Ativar':'Desativar').' "'.$name.'"'];
        }
        if($type==='promotion_manage'||$type==='knowledge_manage'){
            $table=$type==='promotion_manage'?'promotions':'knowledge_base';$key=$type==='promotion_manage'?'promotion_id':'knowledge_id';
            $a[$key]=(int)($action[$key]??0);$a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            $allowed=$type==='promotion_manage'?['title'=>120,'description'=>3000,'active'=>1]:['title'=>180,'content'=>10000,'active'=>1];
            $q=$pdo->prepare("SELECT title FROM {$table} WHERE id=?");$q->execute([$a[$key]]);$name=$q->fetchColumn();
            if(!$name||!isset($allowed[$a['field']])||mb_strlen($a['value'])>$allowed[$a['field']])throw new \RuntimeException('Registro/campo inválido.');
            if($a['field']==='active'&&!in_array($a['value'],['0','1'],true))throw new \RuntimeException('Use 0 ou 1.');
            return [$a,'Alterar '.$a['field'].' de "'.$name.'"'];
        }
        if($type==='reservation_status'){
            $a['reservation_id']=(int)($action['reservation_id']??0);$a['status']=(string)($action['status']??'');$a['reason']=mb_substr(trim((string)($action['reason']??'')),0,500);
            $q=$pdo->prepare('SELECT code,status FROM reservations WHERE id=?');$q->execute([$a['reservation_id']]);$r=$q->fetch();
            if(!$r||!in_array($a['status'],['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada','concluida','no_show'],true))throw new \RuntimeException('Reserva ou status inválido.');
            return [$a,'Solicitar mudança da reserva "'.$r['code'].'" de '.$r['status'].' para '.$a['status'].' (validada pelo serviço de reservas)'];
        }
        if($type==='media_manage'){
            $a['media_id']=(int)($action['media_id']??0);$a['field']=(string)($action['field']??'');$a['value']=trim((string)($action['value']??''));
            $allowed=['caption'=>180,'alt_text'=>180,'sort_order'=>7,'active'=>1];
            $q=$pdo->prepare('SELECT id FROM media WHERE id=?');$q->execute([$a['media_id']]);$exists=$q->fetchColumn();
            if(!$exists||!isset($allowed[$a['field']])||mb_strlen($a['value'])>$allowed[$a['field']])throw new \RuntimeException('Mídia/campo inválido.');
            if($a['field']==='active'&&!in_array($a['value'],['0','1'],true))throw new \RuntimeException('Use 0 ou 1.');
            if($a['field']==='sort_order'&&!preg_match('/^-?\\d{1,6}$/',$a['value']))throw new \RuntimeException('Ordem inválida.');
            return [$a,'Atualizar '.$a['field'].' da mídia #'.$a['media_id']];
        }
        if($type==='amenity_toggle'){
            $a['amenity_id']=(int)($action['amenity_id']??0);$a['enabled']=!empty($action['enabled'])?1:0;
            $q=$pdo->prepare("SELECT name FROM amenities WHERE id=? AND scope='property' AND active=1");$q->execute([$a['amenity_id']]);$name=$q->fetchColumn();
            if(!$name)throw new \RuntimeException('Comodidade não encontrada.');
            return [$a,($a['enabled']?'Ativar':'Desativar').' serviço "'.$name.'"'];
        }
        if($type==='tariff_price'||$type==='package_price'){
            $table=$type==='tariff_price'?'tariffs':'room_packages';$key=$type==='tariff_price'?'tariff_id':'package_id';
            $a[$key]=(int)($action[$key]??0);$a['price']=(float)($action['price']??-1);
            $q=$pdo->prepare("SELECT name FROM {$table} WHERE id=?");$q->execute([$a[$key]]);$name=$q->fetchColumn();
            if(!$name||!is_finite($a['price'])||$a['price']<0||$a['price']>1000000)throw new \RuntimeException('Tarifa, pacote ou valor inválido.');
            return [$a,'Alterar valor de "'.$name.'" para R$ '.number_format($a['price'],2,',','.')];
        }
        if($type==='seo_update'){
            $a['content_id']=(int)($action['content_id']??0);$a['seo_title']=trim((string)($action['seo_title']??''));$a['meta_description']=trim((string)($action['meta_description']??''));
            $q=$pdo->prepare('SELECT title FROM contents WHERE id=?');$q->execute([$a['content_id']]);$name=$q->fetchColumn();
            if(!$name||$a['seo_title']===''||mb_strlen($a['seo_title'])>180||mb_strlen($a['meta_description'])>500)throw new \RuntimeException('Página ou metadados SEO inválidos.');
            return [$a,'Atualizar título SEO e descrição de "'.$name.'"'];
        }
        if($type==='site_text'){
            $a['key']=(string)($action['key']??'');$a['value']=trim((string)($action['value']??''));
            if(!in_array($a['key'],['hero_title','hero_description','hero_kicker','intro_title','footer_bottom','booking_cta','menu_home','menu_about','menu_accommodations','menu_gastronomy','menu_experiences','menu_offers','menu_contact','hero_primary_cta','hero_secondary_cta'],true)||mb_strlen($a['value'])>3000)throw new \RuntimeException('Campo visual não permitido.');
            return [$a,'Atualizar texto do site: '.$a['key']];
        }
        if($type==='promotion_create'){
            foreach(['title','description','code','discount_type'] as $f)$a[$f]=trim((string)($action[$f]??''));
            $a['discount_value']=(float)($action['discount_value']??-1);
            foreach(['start_date','end_date'] as $f)$a[$f]=self::date((string)($action[$f]??''));
            if(mb_strlen($a['title'])<3||mb_strlen($a['title'])>120||mb_strlen($a['description'])>3000||!preg_match('/^[A-Za-z0-9_-]{3,40}$/',$a['code'])||!in_array($a['discount_type'],['fixed','percent'],true)||!is_finite($a['discount_value'])||$a['discount_value']<=0||($a['discount_type']==='percent'&&$a['discount_value']>100)||$a['start_date']>$a['end_date'])throw new \RuntimeException('Dados da campanha inválidos.');
            return [$a,'Criar promoção "'.$a['title'].'" com cupom '.$a['code'].'; sem divulgar em redes sociais'];
        }
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
        if($type==='room_amenity_toggle'){
            $id=$a['room_id'];
            if($a['enabled'])$pdo->prepare('INSERT IGNORE INTO room_amenities(room_id,amenity_id) VALUES(?,?)')->execute([$id,$a['amenity_id']]);
            else $pdo->prepare('DELETE FROM room_amenities WHERE room_id=? AND amenity_id=?')->execute([$id,$a['amenity_id']]);
        }elseif($type==='site_setting'){
            $field=$a['field'];$id=1;$pdo->prepare("UPDATE site_settings SET {$field}=?,updated_at=NOW() WHERE id=1")->execute([$a['value']]);\App\Core\Site::resetCache();
        }elseif($type==='media_cover'){
            $id=$a['media_id'];$pdo->beginTransaction();try{
                $q=$pdo->prepare("SELECT file_path,url FROM media WHERE id=? AND scope='property' AND entity_id=1 AND type='image' FOR UPDATE");$q->execute([$id]);$m=$q->fetch();
                if(!$m)throw new \RuntimeException('Imagem não encontrada.');
                $src=(string)($m['file_path']?:$m['url']);if($src==='')throw new \RuntimeException('Imagem sem arquivo.');
                $pdo->exec("UPDATE media SET is_cover=0 WHERE scope='property' AND entity_id=1");
                $pdo->prepare("UPDATE media SET is_cover=1,active=1 WHERE id=?")->execute([$id]);
                $pdo->prepare("UPDATE property SET hero_media_type='image',hero_image=?,updated_at=NOW() WHERE id=1")->execute([$src]);
                $pdo->commit();
            }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }elseif($type==='room_update'||$type==='content_manage'||$type==='promotion_manage'||$type==='knowledge_manage'||$type==='media_manage'){
            $mapping=['room_update'=>['rooms','room_id'],'content_manage'=>['contents','content_id'],'promotion_manage'=>['promotions','promotion_id'],'knowledge_manage'=>['knowledge_base','knowledge_id'],'media_manage'=>['media','media_id']];
            [$table,$key]=$mapping[$type];$id=$a[$key];$field=$a['field'];
            $pdo->prepare("UPDATE {$table} SET {$field}=? WHERE id=?")->execute([$a['value'],$id]);
        }elseif($type==='tariff_create'){
            $pdo->prepare('INSERT INTO tariffs(room_id,name,start_date,end_date,price,min_nights,priority,active) VALUES(?,?,?,?,?,?,?,1)')->execute([$a['room_id'],$a['name'],$a['start_date'],$a['end_date'],$a['price'],$a['min_nights'],$a['priority']]);$id=(int)$pdo->lastInsertId();
        }elseif($type==='package_create'){
            $pdo->prepare('INSERT INTO room_packages(room_id,name,description,start_date,end_date,nights,price,active,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())')->execute([$a['room_id'],$a['name'],$a['description'],$a['start_date'],$a['end_date'],$a['nights'],$a['price']]);$id=(int)$pdo->lastInsertId();
        }elseif($type==='tariff_toggle'||$type==='package_toggle'){
            $table=$type==='tariff_toggle'?'tariffs':'room_packages';$key=$type==='tariff_toggle'?'tariff_id':'package_id';$id=$a[$key];
            $pdo->prepare("UPDATE {$table} SET active=? WHERE id=?")->execute([$a['enabled'],$id]);
        }elseif($type==='reservation_status'){
            $id=$a['reservation_id'];(new ReservationService())->changeStatus($id,$a['status'],$a['reason']);
        }elseif($type==='amenity_toggle'){
            $id=$a['amenity_id'];
            if($a['enabled'])$pdo->prepare('INSERT IGNORE INTO property_amenities(property_id,amenity_id) VALUES(1,?)')->execute([$id]);
            else $pdo->prepare('DELETE FROM property_amenities WHERE property_id=1 AND amenity_id=?')->execute([$id]);
        }elseif($type==='tariff_price'||$type==='package_price'){
            $table=$type==='tariff_price'?'tariffs':'room_packages';$key=$type==='tariff_price'?'tariff_id':'package_id';$id=$a[$key];
            $pdo->prepare("UPDATE {$table} SET price=? WHERE id=?")->execute([$a['price'],$id]);
        }elseif($type==='seo_update'){
            $pdo->prepare('UPDATE contents SET seo_title=?,meta_description=?,updated_at=NOW() WHERE id=?')->execute([$a['seo_title'],$a['meta_description'],$a['content_id']]);$id=$a['content_id'];
        }elseif($type==='site_text'){
            $pdo->beginTransaction();try{$raw=$pdo->query('SELECT ui_json FROM site_settings WHERE id=1 FOR UPDATE')->fetchColumn();$ui=json_decode((string)$raw,true);if(!is_array($ui))$ui=[];$ui[$a['key']]=$a['value'];$pdo->prepare('UPDATE site_settings SET ui_json=?,updated_at=NOW() WHERE id=1')->execute([json_encode($ui,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$pdo->commit();\App\Core\Site::resetCache();$id=1;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }elseif($type==='promotion_create'){
            $pdo->prepare('INSERT INTO promotions(title,description,code,discount_type,discount_value,start_date,end_date,active) VALUES(?,?,?,?,?,?,?,1)')->execute([$a['title'],$a['description'],$a['code'],$a['discount_type'],$a['discount_value'],$a['start_date'],$a['end_date']]);$id=(int)$pdo->lastInsertId();
        }elseif($type==='content_update'){
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
