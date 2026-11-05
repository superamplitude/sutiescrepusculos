<?php
use App\Core\DB;use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Core\Secret;use App\Core\Site;use App\Services\ReservationService;use App\Services\GeminiService;use App\Services\UploadService;use App\Services\ICalService;use App\Controllers\WhatsappController;
if (!defined('SUITES_PAGE_ENTRY')) { http_response_code(404); exit; }

  Auth::requirePerm('ai.use');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::check();$action=$_POST['action']??'save';
    if($action==='save'){
      Auth::requireSuperAdmin();
      $current=$pdo->query("SELECT api_key FROM ai_config WHERE id=1")->fetchColumn();$key=trim($_POST['api_key']??'');if($key==='')$key=$current;else $key=Secret::encrypt($key);
      $model=in_array($_POST['model']??'gemini-2.5-flash',['gemini-2.5-flash','gemini-3.6-flash'],true)?$_POST['model']:'gemini-2.5-flash';
      $pdo->prepare("UPDATE ai_config SET enabled=?,provider='gemini',model=?,api_key=?,system_instruction=?,updated_at=NOW() WHERE id=1")->execute([isset($_POST['enabled'])?1:0,$model,$key,trim($_POST['system_instruction']??'')]);U::flash('ok','Configuração da IA salva.');U::redirect('index.php?p=ai');
    }
    if($action==='kb_add'){
      $title=trim($_POST['kb_title']??'');$content=trim($_POST['kb_content']??'');if($title===''||$content==='')throw new RuntimeException('Título e conteúdo da base são obrigatórios.');
      $pdo->prepare("INSERT INTO knowledge_base(title,content,source,active,updated_at) VALUES(?,?,?,1,NOW())")->execute([$title,$content,trim($_POST['kb_source']??'')]);Audit::log('ai.knowledge.create','knowledge_base',(int)$pdo->lastInsertId());U::redirect('index.php?p=ai');
    }
    if($action==='kb_toggle'){
      $id=(int)$_POST['id'];$pdo->prepare("UPDATE knowledge_base SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=?")->execute([$id]);U::redirect('index.php?p=ai');
    }
  }
  $c=$pdo->query("SELECT * FROM ai_config WHERE id=1")->fetch();$kb=$pdo->query("SELECT * FROM knowledge_base ORDER BY active DESC,id DESC LIMIT 100")->fetchAll();
  $h=adminTitle('Inteligência Artificial','Gemini real quando houver chave. A base abaixo define o que a IA pode afirmar.');
  if(Auth::isSuperAdmin()){
    $model=(string)($c['model']??'gemini-2.5-flash');
    $h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=action value=save>'
      .row('Habilitar IA','enabled',$c['enabled'],'checkbox')
      .'<label>Modelo Gemini<select name=model><option value="gemini-2.5-flash" '.($model==='gemini-2.5-flash'?'selected':'').'>Gemini 2.5 Flash</option><option value="gemini-3.6-flash" '.($model==='gemini-3.6-flash'?'selected':'').'>Gemini 3.6 Flash</option></select></label>'
      .row('API Key (deixe em branco para manter a atual)','api_key','', 'password')
      .row('Instrução central','system_instruction',$c['system_instruction'],'textarea')
      .'<button>Salvar configuração</button></form>';
  }
  $h.='<form method=post class=card>'.Csrf::field().'<input type=hidden name=action value=kb_add><h2>Base de conhecimento</h2><p class=muted>Cadastre perguntas frequentes, políticas e informações que foram confirmadas pela pousada.</p><div class=grid2>'.row('Título / pergunta','kb_title').row('Fonte / observação','kb_source').'</div>'.row('Conteúdo confirmado','kb_content','', 'textarea').'<button>Adicionar à base</button></form><div class=card><table><tr><th>Título</th><th>Fonte</th><th>Status</th><th></th></tr>';foreach($kb as $x)$h.='<tr><td>'.U::e($x['title']).'</td><td>'.U::e($x['source']).'</td><td>'.($x['active']?'Ativo':'Inativo').'</td><td><form method=post>'.Csrf::field().'<input type=hidden name=action value=kb_toggle><input type=hidden name=id value="'.$x['id'].'"><button class=small>'.($x['active']?'Desativar':'Ativar').'</button></form></td></tr>';$h.='</table></div>';
  $h.='<form method=post action="index.php?p=ai_test" class=card>'.Csrf::field().row('Pergunta para teste','question','Qual é o horário de check-in?','textarea').'<button>Testar IA agora</button></form>';
  render('IA',$h);exit;
