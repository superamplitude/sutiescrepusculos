<?php
use App\Core\Auth;use App\Core\Csrf;use App\Core\U;use App\Core\Audit;use App\Services\SimplePdf;
if(!defined('SUITES_PAGE_ENTRY')){http_response_code(404);exit;}
Auth::requireLogin();
if(!Auth::isAdmin()&&!Auth::isSuperAdmin()&&!Auth::can('payments.view')){http_response_code(403);exit('Sem autorização financeira.');}
$canEdit=Auth::isAdmin()||Auth::isSuperAdmin()||Auth::can('payments.confirm');
if($_SERVER['REQUEST_METHOD']==='POST'){
 Csrf::check();if(!$canEdit){http_response_code(403);exit('Sem permissão para lançar.');}
 $action=(string)($_POST['action']??'');
 try{
  if($action==='create'){
   $type=(string)($_POST['type']??'');$category=trim((string)($_POST['category']??''));$description=trim((string)($_POST['description']??''));$method=trim((string)($_POST['method']??''));$amount=(float)($_POST['amount']??0);$date=(string)($_POST['entry_date']??'');
   $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
   if(!in_array($type,['in','out'],true)||!$d||$d->format('Y-m-d')!==$date||mb_strlen($category)<2||mb_strlen($category)>100||mb_strlen($description)<3||mb_strlen($description)>500||mb_strlen($method)>80||!is_finite($amount)||$amount<=0||$amount>1000000)throw new RuntimeException('Preencha corretamente os dados do lançamento.');
   $pdo->prepare('INSERT INTO suites_cash_entries(entry_date,type,category,description,method,amount,created_by,created_at) VALUES(?,?,?,?,?,?,?,NOW())')
      ->execute([$date,$type,$category,$description,$method,$amount,(int)Auth::user()['id']]);
   Audit::log('cash.create','suites_cash_entries',(int)$pdo->lastInsertId());U::flash('ok','Lançamento adicionado.');
  }elseif($action==='void'){
   $entry=(int)($_POST['entry_id']??0);$q=$pdo->prepare('UPDATE suites_cash_entries SET voided_at=NOW(),voided_by=? WHERE id=? AND voided_at IS NULL');
   $q->execute([(int)Auth::user()['id'],$entry]);if($q->rowCount()!==1)throw new RuntimeException('Lançamento já cancelado ou inexistente.');
   Audit::log('cash.void','suites_cash_entries',$entry);U::flash('ok','Lançamento estornado, preservando histórico.');
  }else throw new RuntimeException('Operação inválida.');
 }catch(Throwable $e){U::flash('err',$e->getMessage());}
 U::redirect('/painel/?p=livro_caixa');
}
$start=(string)($_GET['start']??date('Y-m-01'));$end=(string)($_GET['end']??date('Y-m-t'));
foreach([$start,$end] as $dt){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$dt);if(!$d||$d->format('Y-m-d')!==$dt)throw new RuntimeException('Período inválido.');}
if($start>$end)throw new RuntimeException('Período invertido.');
$st=$pdo->prepare('SELECT e.*,u.name actor FROM suites_cash_entries e LEFT JOIN users u ON u.id=e.created_by WHERE e.entry_date BETWEEN ? AND ? ORDER BY e.entry_date DESC,e.id DESC LIMIT 2000');$st->execute([$start,$end]);$rows=$st->fetchAll();
$prior=$pdo->prepare('SELECT COALESCE(SUM(CASE WHEN type=\'in\' THEN amount ELSE -amount END),0) FROM suites_cash_entries WHERE voided_at IS NULL AND entry_date<?');$prior->execute([$start]);$opening=(float)$prior->fetchColumn();
$in=0.;$out=0.;foreach($rows as $e)if($e['voided_at']===null){if($e['type']==='in')$in+=(float)$e['amount'];else $out+=(float)$e['amount'];}
$paid=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND DATE(paid_at) BETWEEN ? AND ?");$paid->execute([$start,$end]);$paidTotal=(float)$paid->fetchColumn();
$closing=$opening+$in-$out;
if(($_GET['export']??'')==='pdf'){
 $fmt=static fn($v)=>'R$ '.number_format((float)$v,2,',','.');
 $lines=['Período: '.$start.' a '.$end,'Saldo anterior: '.$fmt($opening),'Entradas manuais: '.$fmt($in),'Saídas manuais: '.$fmt($out),'Saldo manual: '.$fmt($closing),'Pagamentos de reservas (painel de pagamentos, não somados novamente): '.$fmt($paidTotal),'','MOVIMENTAÇÕES'];
 foreach($rows as $e)$lines[]=$e['entry_date'].' | '.($e['type']==='in'?'Entrada':'Saída').' | '.$e['category'].' | '.$e['description'].' | '.$fmt($e['amount']).($e['voided_at']?' [ESTORNADO]':'');
 SimplePdf::download('livro-caixa-'.$start.'-'.$end.'.pdf','Livro Caixa · Suites Crepusculo',$lines);
}
$h=adminTitle('Livro Caixa','Entradas e saídas manuais, com histórico de estornos; pagamentos de reservas informados separadamente para evitar duplicidade.');
$h.='<form method=get class=card><input type=hidden name=p value=livro_caixa><div class=grid3>'.row('Início','start',$start,'date').row('Fim','end',$end,'date').'</div><button>Filtrar</button> <a class=btn href="/painel/?p=livro_caixa&start='.U::e($start).'&end='.U::e($end).'&export=pdf">Exportar PDF</a></form>';
$h.='<div class="card"><p>Saldo anterior: <b>'.money($opening).'</b> · Entradas: <b>'.money($in).'</b> · Saídas: <b>'.money($out).'</b> · Saldo: <b>'.money($closing).'</b></p><p>Pagamentos confirmados de reservas no período (informativo, não duplicado): '.money($paidTotal).'</p></div>';
if($canEdit)$h.='<form class=card method=post>'.Csrf::field().'<input type=hidden name=action value=create><h2>Novo lançamento</h2><div class=grid3><label>Tipo<select name=type><option value=in>Entrada</option><option value=out>Saída</option></select></label>'.row('Data','entry_date',date('Y-m-d'),'date').row('Categoria','category').row('Descrição','description').row('Forma de pagamento','method').row('Valor (R$)','amount','0.00','number').'</div><button>Salvar lançamento</button></form>';
$h.='<div class=card><table><tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Responsável</th><th></th></tr>';
foreach($rows as $e){
 $void=$e['voided_at']!==null;
 $h.='<tr><td>'.U::e($e['entry_date']).'</td><td>'.($e['type']==='in'?'Entrada':'Saída').($void?' · Estornado':'').'</td><td>'.U::e($e['category']).'</td><td>'.U::e($e['description']).'</td><td>'.money((float)$e['amount']).'</td><td>'.U::e($e['actor']??'—').'</td><td>';
 if($canEdit&&!$void)$h.='<form method=post>'.Csrf::field().'<input type=hidden name=action value=void><input type=hidden name=entry_id value="'.(int)$e['id'].'"><button class="secondary small" onclick="return confirm(\'Estornar lançamento?\')">Estornar</button></form>';
 $h.='</td></tr>';
}
render('Livro Caixa',$h.'</table></div>');exit;
