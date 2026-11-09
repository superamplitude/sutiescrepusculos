<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\DB;
use PDO;
final class FolioService {
 public static function detail(int $reservationId,bool $lock=false):array {
  $pdo=DB::pdo();
  $q=$pdo->prepare('SELECT r.id,r.code,r.guest_id,r.room_id,r.checkin,r.checkout,r.status,r.total,g.name guest,rm.name room FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE r.id=?'.($lock?' FOR UPDATE':''));
  $q->execute([$reservationId]);$r=$q->fetch();
  if(!$r)throw new \RuntimeException('Reserva não encontrada.');
  $q=$pdo->prepare('SELECT id,description,qty,unit_price,line_total,created_at,voided_at FROM suites_folio_items WHERE reservation_id=? ORDER BY id');$q->execute([$reservationId]);$items=$q->fetchAll();
  $subtotal=0.0;foreach($items as $item)if($item['voided_at']===null)$subtotal+=(float)$item['line_total'];
  $q=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=? AND status=\'paid\'');$q->execute([$reservationId]);$paid=(float)$q->fetchColumn();
  $q=$pdo->prepare('SELECT * FROM suites_folio_closures WHERE reservation_id=?');$q->execute([$reservationId]);$close=$q->fetch()?:null;
  $fee=$close?(float)$close['service_fee']:0.0;
  return ['reservation'=>$r,'items'=>$items,'consumption'=>round($subtotal,2),'paid'=>round($paid,2),'close'=>$close,'fee'=>$fee,
    'total'=>round((float)$r['total']+$subtotal+$fee,2),'due'=>round((float)$r['total']+$subtotal+$fee-$paid,2)];
 }
 public static function item(int $reservationId,string $description,float $qty,float $price,int $actor):void {
  $description=trim($description);
  if(mb_strlen($description)<2||mb_strlen($description)>250||!is_finite($qty)||$qty<=0||$qty>10000||!is_finite($price)||$price<0||$price>1000000)throw new \RuntimeException('Produto, quantidade ou valor inválido.');
  $pdo=DB::pdo();$pdo->beginTransaction();
  try{$d=self::detail($reservationId,true);if($d['close'])throw new \RuntimeException('Conta encerrada; lançamento bloqueado.');
   $pdo->prepare('INSERT INTO suites_folio_items(reservation_id,description,qty,unit_price,line_total,created_by,created_at) VALUES(?,?,?,?,?,?,NOW())')
     ->execute([$reservationId,$description,$qty,$price,round($qty*$price,2),$actor]);$pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public static function voidItem(int $reservationId,int $itemId,int $actor):void {
  $pdo=DB::pdo();$pdo->beginTransaction();
  try{$d=self::detail($reservationId,true);if($d['close'])throw new \RuntimeException('Conta encerrada.');
   $q=$pdo->prepare('UPDATE suites_folio_items SET voided_at=NOW(),voided_by=? WHERE id=? AND reservation_id=? AND voided_at IS NULL');
   $q->execute([$actor,$itemId,$reservationId]);if($q->rowCount()!==1)throw new \RuntimeException('Lançamento não encontrado.');
   $pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public static function close(int $reservationId,bool $applyTenPercent,int $actor):array {
  $pdo=DB::pdo();$pdo->beginTransaction();
  try{$d=self::detail($reservationId,true);if($d['close'])throw new \RuntimeException('A conta já foi encerrada.');
   $fee=$applyTenPercent?round($d['consumption']*.10,2):0.0;
   $total=round((float)$d['reservation']['total']+$d['consumption']+$fee,2);$balance=round($total-$d['paid'],2);
   $pdo->prepare('INSERT INTO suites_folio_closures(reservation_id,lodging_total,consumption_total,service_rate,service_fee,paid_total,grand_total,balance_due,closed_by,closed_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())')
    ->execute([$reservationId,$d['reservation']['total'],$d['consumption'],$applyTenPercent?10:0,$fee,$d['paid'],$total,$balance,$actor]);
   $pdo->commit();return self::detail($reservationId);
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public static function pdfLines(array $d):array {
  $r=$d['reservation'];$c=$d['close'];
  $lines=['Reserva: '.$r['code'].'   Apartamento: '.$r['room'],'Hospede: '.$r['guest'],'Estadia: '.$r['checkin'].' a '.$r['checkout'],'','CONSUMO'];
  foreach($d['items'] as $x)if($x['voided_at']===null)$lines[]=sprintf('%s | %s x R$ %.2f | R$ %.2f',$x['description'],(float)$x['qty'],(float)$x['unit_price'],(float)$x['line_total']);
  $money=static fn($n)=>'R$ '.number_format((float)$n,2,',','.');
  array_push($lines,'','Hospedagem: '.$money($r['total']),'Consumo: '.$money($d['consumption']),
   'Taxa de servico ('.($c?(int)$c['service_rate']:0).'%): '.$money($d['fee']),
   'TOTAL: '.$money($d['total']),'Pagamentos registrados: '.$money($d['paid']),
   'Saldo: '.$money($d['due']),'','Fechamento: '.($c['closed_at']??'Em aberto'));
  return $lines;
 }
}
