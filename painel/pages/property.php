<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('property.edit');
  $sections=[
    'identity'=>'Identidade',
    'profile'=>'Perfil e conteúdo',
    'cover'=>'Capa do site',
    'location'=>'Localização',
    'contact'=>'Contato',
    'operation'=>'Operação',
    'rules'=>'Regras e políticas',
    'amenities'=>'Comodidades'
  ];
  $section=$_GET['section']??($_POST['section']??'identity');
  if(!isset($sections[$section]))$section='identity';
  $x=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();

  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();

    $coverAction=$_POST['cover_action']??'';
    if($section==='cover' && in_array($coverAction,['delete_image','delete_video','delete_poster'],true)){
      $fieldMap=['delete_image'=>'hero_image','delete_video'=>'hero_video','delete_poster'=>'hero_poster'];
      $field=$fieldMap[$coverAction];
      $q=$pdo->query("SELECT $field FROM property WHERE id=1");
      $old=(string)($q->fetchColumn()?:'');
      $pdo->beginTransaction();
      try{
        $pdo->exec("UPDATE property SET $field=NULL,updated_at=NOW() WHERE id=1");
        if($coverAction==='delete_image' && $old!==''){
          $del=$pdo->prepare("DELETE FROM media WHERE scope='property' AND entity_id=1 AND type='image' AND (file_path=? OR url=?)");
          $del->execute([$old,$old]);
        }
        if($coverAction==='delete_video' && $old!==''){
          $del=$pdo->prepare("DELETE FROM media WHERE scope='property' AND entity_id=1 AND type='video' AND (file_path=? OR url=?)");
          $del->execute([$old,$old]);
          $pdo->exec("UPDATE property SET hero_video_url=NULL WHERE id=1");
        }
        $coverExists=(int)$pdo->query("SELECT COUNT(*) FROM media WHERE scope='property' AND entity_id=1 AND is_cover=1 AND active=1")->fetchColumn();
        if($coverExists===0){
          $next=$pdo->query("SELECT id,type,file_path,url FROM media WHERE scope='property' AND entity_id=1 AND active=1 ORDER BY sort_order,id LIMIT 1")->fetch();
          if($next){
            $pdo->prepare("UPDATE media SET is_cover=1 WHERE id=?")->execute([(int)$next['id']]);
            $src=$next['file_path']?:$next['url'];
            if($next['type']==='image'){
              $pdo->prepare("UPDATE property SET hero_media_type='image',hero_image=?,hero_video=NULL,hero_video_url=NULL,updated_at=NOW() WHERE id=1")->execute([$src]);
            }else{
              $pdo->prepare("UPDATE property SET hero_media_type='video',hero_image=NULL,hero_video=?,hero_video_url=?,updated_at=NOW() WHERE id=1")
                ->execute([$next['file_path']?:null,$next['url']?:null]);
            }
          }else{
            $pdo->exec("UPDATE property SET hero_media_type='none',hero_image=NULL,hero_video=NULL,hero_video_url=NULL,updated_at=NOW() WHERE id=1");
          }
        }
        $pdo->commit();
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
      if($old!=='' && str_starts_with($old,'storage/uploads/')){
        $full=dirname(__DIR__,2).'/'.$old;
        if(is_file($full))@unlink($full);
      }
      Audit::log('property.cover.delete','property',1,['field'=>$field,'source'=>$old]);
      U::flash('ok','Mídia removida da capa.');
      U::redirect('index.php?p=property&section=cover');
    }

    $fieldGroups=[
      'identity'=>['name','category','subcategory','short_description','description'],
      'profile'=>['highlights','history','concept','target_audience','region_info','location_highlights','own_services','general_policies'],
      'cover'=>['announcement_text','announcement_link_text','announcement_link_url','hero_video_url'],
      'location'=>['currency','country','state','city','neighborhood','address','address_number','complement','zip','landmark','latitude','longitude','map_url','directions','distance_beach','distance_center','distance_bus','distance_airport','access_conditions','parking_notes'],
      'contact'=>['manager_name','phone','whatsapp','email','site_url','instagram','facebook','tiktok','youtube'],
      'operation'=>['operating_days','checkin','checkout'],
      'rules'=>['special_hours','quiet_hours','visitor_rules','cancellation_policy','checkin_rules','checkout_rules','early_checkin','late_checkout','damage_deposit','house_rules','accessibility_notes','security_notes']
    ];
    $boolGroups=[
      'identity'=>['active','verified'],
      'cover'=>['announcement_enabled'],
      'operation'=>['open_24h','reception_24h','self_checkin','long_stay','accepts_children','accepts_pets','assistance_animals','smoking_allowed','parties_allowed','parking']
    ];

    if($section==='amenities'){
      $pdo->beginTransaction();
      try{
        $pdo->prepare("DELETE FROM property_amenities WHERE property_id=1")->execute();
        $ins=$pdo->prepare("INSERT INTO property_amenities(property_id,amenity_id) VALUES(1,?)");
        foreach($_POST['amenities']??[] as $aid)$ins->execute([(int)$aid]);
        $pdo->commit();
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
    }else{
      $sets=[];$vals=[];
      foreach($fieldGroups[$section]??[] as $f){
        $sets[]="$f=?";
        $v=$_POST[$f]??null;
        if(in_array($f,['latitude','longitude'],true) && trim((string)$v)==='')$v=null;
        $vals[]=$v;
      }
      foreach($boolGroups[$section]??[] as $f){
        $sets[]="$f=?";
        $vals[]=isset($_POST[$f])?1:0;
      }

      if($section==='cover'){
        $upload=new UploadService();
        $heroImage=$x['hero_image'];$heroVideo=$x['hero_video'];$heroPoster=$x['hero_poster'];
        if(!empty($_FILES['hero_image']['name']))$heroImage=$upload->image($_FILES['hero_image'],'property/cover');
        if(!empty($_FILES['hero_video']['name']))$heroVideo=$upload->video($_FILES['hero_video'],'property/cover');
        if(!empty($_FILES['hero_poster']['name']))$heroPoster=$upload->image($_FILES['hero_poster'],'property/cover');
        foreach(['hero_media_type'=>($_POST['hero_media_type']??'none'),'hero_image'=>$heroImage,'hero_video'=>$heroVideo,'hero_poster'=>$heroPoster] as $f=>$v){
          $sets[]="$f=?";$vals[]=$v;
        }
      }

      if($section==='operation'){
        $sets[]="parking_spaces=?";$vals[]=(int)($_POST['parking_spaces']??0);
        $sets[]="min_stay=?";$vals[]=(int)($_POST['min_stay']??1);
        $sets[]="max_stay=?";$vals[]=($_POST['max_stay']??'')!==''?(int)$_POST['max_stay']:null;
      }

      if($sets){
        $sets[]="updated_at=NOW()";$vals[]=1;
        $pdo->prepare("UPDATE property SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
      }
    }

    Audit::log('property.section.update','property',1,['section'=>$section]);
    U::flash('ok',$sections[$section].' salvos.');
    U::redirect('index.php?p=property&section='.$section);
  }

  $x=$pdo->query("SELECT * FROM property WHERE id=1")->fetch();
  $sel=$pdo->query("SELECT amenity_id FROM property_amenities WHERE property_id=1")->fetchAll(PDO::FETCH_COLUMN);
  $ams=$pdo->query("SELECT * FROM amenities WHERE scope='property' AND active=1 ORDER BY category,name")->fetchAll();

  $h=adminTitle('Informações do Estabelecimento · '.$sections[$section],'Edite esta parte sem carregar a ficha inteira.');
  $h.='<nav class="property-section-tabs">';
  foreach($sections as $key=>$label)$h.='<a class="'.($section===$key?'active':'').'" href="index.php?p=property&section='.$key.'">'.U::e($label).'</a>';
  $h.='</nav>';

  $h.='<form method=post enctype="multipart/form-data" class="card property-section-form">'.Csrf::field().'<input type=hidden name=section value="'.U::e($section).'">';

  if($section==='identity'){
    $h.='<section class=form-section><h2>Identidade</h2><div class=grid2>'.row('Nome','name',$x['name']).'<label>Categoria<select name="category"><option value="Pousada" '.($x['category']==='Pousada'?'selected':'').'>Pousada</option><option value="Hotel" '.($x['category']==='Hotel'?'selected':'').'>Hotel</option><option value="Hostel" '.($x['category']==='Hostel'?'selected':'').'>Hostel</option><option value="Chalé/Cabana" '.($x['category']==='Chalé/Cabana'?'selected':'').'>Chalé / Cabana</option>'.(function()use($x){$html='';foreach(['Serviços','Comércio','Restaurante','Turismo','Educação','Outra empresa'] as $t)$html.='<option value="'.U::e($t).'" '.(($x['category']??'')===$t?'selected':'').'>'.U::e($t).'</option>';return $html;})().'</select></label>'.row('Subcategoria','subcategory',$x['subcategory']).'</div>'.row('Descrição curta','short_description',$x['short_description'],'textarea').row('Descrição completa','description',$x['description'],'textarea').'<div class=checks>'.row('Cadastro ativo','active',$x['active'],'checkbox').row('Dados verificados','verified',$x['verified'],'checkbox').'</div></section>';
  }

  if($section==='profile'){
    $h.='<section class=form-section><h2>Perfil e conteúdo</h2>'.row('Diferenciais / destaques','highlights',$x['highlights'],'textarea').row('História','history',$x['history'],'textarea').row('Conceito / proposta','concept',$x['concept'],'textarea').row('Público principal','target_audience',$x['target_audience'],'textarea').row('Informações da região','region_info',$x['region_info'],'textarea').row('Destaques da localização','location_highlights',$x['location_highlights'],'textarea').row('Serviços próprios','own_services',$x['own_services'],'textarea').row('Políticas gerais','general_policies',$x['general_policies'],'textarea').'</section>';
  }

  if($section==='cover'){
    $h.='<section class=form-section><h2>Barra superior da capa</h2><div class=grid2>'.row('Exibir barra','announcement_enabled',$x['announcement_enabled']??1,'checkbox').row('Texto da barra','announcement_text',$x['announcement_text']??'').row('Texto do link','announcement_link_text',$x['announcement_link_text']??'').row('URL do link','announcement_link_url',$x['announcement_link_url']??'').'</div></section>';
    $h.='<section class="form-section media-section"><h2>Capa do site</h2><div class=grid2><label>Tipo de capa<select name=hero_media_type><option value=none '.(($x['hero_media_type']??'none')==='none'?'selected':'').'>Sem mídia</option><option value=image '.(($x['hero_media_type']??'')==='image'?'selected':'').'>Foto</option><option value=video '.(($x['hero_media_type']??'')==='video'?'selected':'').'>Vídeo</option></select></label><label>Upload da foto<input id=hero_image_file type=file name=hero_image accept="image/jpeg,image/png,image/webp"></label><label>Upload do vídeo<input id=hero_video_file type=file name=hero_video accept="video/mp4,video/webm"></label><label>Poster do vídeo<input id=hero_poster_file type=file name=hero_poster accept="image/jpeg,image/png,image/webp"></label>'.row('URL direta do vídeo','hero_video_url',$x['hero_video_url']).'</div>';
    $h.='<div class="cover-media-grid">';
    if(!empty($x['hero_image']))$h.='<article class="cover-media-item">'.mediaPreview($x['hero_image']).'<div class="cover-media-actions"><button type=button class="secondary small" data-file-trigger="hero_image_file">Editar</button><button type=submit class="danger small" name=cover_action value=delete_image onclick="return confirm(\'Excluir esta foto da capa?\')">Excluir</button></div></article>';
    if(!empty($x['hero_video'])||!empty($x['hero_video_url'])){$src=$x['hero_video']?:$x['hero_video_url'];$h.='<article class="cover-media-item">'.mediaPreview($src,'video').'<div class="cover-media-actions"><button type=button class="secondary small" data-file-trigger="hero_video_file">Editar</button><button type=submit class="danger small" name=cover_action value=delete_video onclick="return confirm(\'Excluir este vídeo da capa?\')">Excluir</button></div></article>';}
    if(!empty($x['hero_poster']))$h.='<article class="cover-media-item">'.mediaPreview($x['hero_poster']).'<div class="cover-media-actions"><button type=button class="secondary small" data-file-trigger="hero_poster_file">Editar</button><button type=submit class="danger small" name=cover_action value=delete_poster onclick="return confirm(\'Excluir este poster da capa?\')">Excluir</button></div></article>';
    $h.='</div><div class="cover-editor-link"><a class="btn secondary" href="index.php?p=hero">Abrir Editor completo do Hero</a></div></section>';
  }

  if($section==='location'){
    $h.='<section class=form-section><h2>Localização</h2><div class=grid3><label>Moeda<select name=currency><option value=BRL '.($x['currency']==='BRL'?'selected':'').'>Real (BRL)</option><option value=USD '.($x['currency']==='USD'?'selected':'').'>Dólar (USD)</option><option value=EUR '.($x['currency']==='EUR'?'selected':'').'>Euro (EUR)</option><option value=GBP '.($x['currency']==='GBP'?'selected':'').'>Libra (GBP)</option></select></label>'.row('País','country',$x['country']).row('Estado','state',$x['state']).row('Cidade','city',$x['city']).row('Bairro','neighborhood',$x['neighborhood']).row('Endereço','address',$x['address']).row('Número','address_number',$x['address_number']).row('Complemento','complement',$x['complement']).row('CEP','zip',$x['zip']).row('Ponto de referência','landmark',$x['landmark']).row('Latitude','latitude',$x['latitude']).row('Longitude','longitude',$x['longitude']).row('Link do mapa / rota','map_url',$x['map_url']).'</div>'.row('Como chegar','directions',$x['directions'],'textarea').'<div class=grid3>'.row('Distância da praia','distance_beach',$x['distance_beach']).row('Distância do centro','distance_center',$x['distance_center']).row('Distância rodoviária','distance_bus',$x['distance_bus']).row('Distância aeroporto','distance_airport',$x['distance_airport']).'</div>'.row('Condições de acesso','access_conditions',$x['access_conditions'],'textarea').row('Observações sobre estacionamento','parking_notes',$x['parking_notes'],'textarea').'</section>';
  }

  if($section==='contact'){
    $h.='<section class=form-section><h2>Contato</h2><div class=grid3>'.row('Responsável','manager_name',$x['manager_name']).row('Telefone','phone',$x['phone']).row('WhatsApp','whatsapp',$x['whatsapp']).row('E-mail','email',$x['email'],'email').row('Site','site_url',$x['site_url']).row('Instagram','instagram',$x['instagram']).row('Facebook','facebook',$x['facebook']).row('TikTok','tiktok',$x['tiktok']).row('YouTube','youtube',$x['youtube']).'</div></section>';
  }

  if($section==='operation'){
    $h.='<section class=form-section><h2>Operação</h2><div class=grid3>'.row('Dias de funcionamento','operating_days',$x['operating_days']).row('Check-in','checkin',$x['checkin'],'time').row('Check-out','checkout',$x['checkout'],'time').row('Estadia mínima','min_stay',$x['min_stay'],'number').row('Estadia máxima','max_stay',$x['max_stay'],'number').row('Vagas de estacionamento','parking_spaces',$x['parking_spaces'],'number').'</div><div class=checks>'.row('Funcionamento 24 horas','open_24h',$x['open_24h'],'checkbox').row('Recepção 24h','reception_24h',$x['reception_24h'],'checkbox').row('Self check-in','self_checkin',$x['self_checkin'],'checkbox').row('Aceita longa permanência','long_stay',$x['long_stay'],'checkbox').row('Aceita crianças','accepts_children',$x['accepts_children'],'checkbox').row('Aceita pets','accepts_pets',$x['accepts_pets'],'checkbox').row('Aceita animais de assistência','assistance_animals',$x['assistance_animals'],'checkbox').row('Fumar permitido','smoking_allowed',$x['smoking_allowed'],'checkbox').row('Festas permitidas','parties_allowed',$x['parties_allowed'],'checkbox').row('Possui estacionamento','parking',$x['parking'],'checkbox').'</div></section>';
  }

  if($section==='rules'){
    $h.='<section class=form-section><h2>Regras e políticas</h2>';
    foreach(['special_hours'=>'Horários especiais','quiet_hours'=>'Horário de silêncio','visitor_rules'=>'Regras para visitantes','cancellation_policy'=>'Política de cancelamento','checkin_rules'=>'Regras de check-in','checkout_rules'=>'Regras de check-out','early_checkin'=>'Early check-in','late_checkout'=>'Late check-out','damage_deposit'=>'Caução / danos','house_rules'=>'Regras de convivência','accessibility_notes'=>'Informações de acessibilidade','security_notes'=>'Informações de segurança'] as $f=>$lab)$h.=row($lab,$f,$x[$f],'textarea');
    $h.='</section>';
  }

  if($section==='amenities'){
    $h.='<section class=form-section><h2>Comodidades do estabelecimento</h2><div class=amenities>';$cat='';
    foreach($ams as $a){if($cat!==$a['category']){$cat=$a['category'];$h.='<h3>'.U::e($cat).'</h3>';}$h.='<label class=check><input type=checkbox name="amenities[]" value="'.$a['id'].'" '.(in_array($a['id'],$sel)?'checked':'').'> '.U::e($a['name']).'</label>';}
    $h.='</div></section>';
  }

  $h.='<div class=form-actions><button>Salvar '.$sections[$section].'</button></div></form>';
  render('Informações do Estabelecimento · '.$sections[$section],$h);exit;
