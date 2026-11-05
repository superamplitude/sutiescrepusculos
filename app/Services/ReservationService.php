<?php
namespace App\Services;
use App\Core\DB;
use App\Core\Audit;
final class ReservationService{
  public function available(int $room,string $in,string $out,?int $ignore=null):bool{
    $pdo=DB::pdo();
    $sql="SELECT COUNT(*) FROM reservations WHERE room_id=? AND status IN ('pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada') AND NOT(checkout<=? OR checkin>=?)";
    $a=[$room,$in,$out];
    if($ignore){$sql.=" AND id<>?";$a[]=$ignore;}
    $s=$pdo->prepare($sql);$s->execute($a); if((int)$s->fetchColumn()>0)return false;
    $s=$pdo->prepare("SELECT COUNT(*) FROM room_blocks WHERE room_id=? AND NOT(end_date<=? OR start_date>=?)");
    $s->execute([$room,$in,$out]); return (int)$s->fetchColumn()===0;
  }
  public function price(int $room,string $in,string $out,int $guests):float{
    $pdo=DB::pdo();
    $s=$pdo->prepare("SELECT base_price,extra_guest_fee,capacity FROM rooms WHERE id=? AND active=1 AND status='active'");$s->execute([$room]);$r=$s->fetch();
    if(!$r)throw new \RuntimeException('Quarto inválido.');
    if($guests>(int)$r['capacity'])throw new \RuntimeException('Capacidade excedida.');
    $d1=new \DateTimeImmutable($in);$d2=new \DateTimeImmutable($out);$n=(int)$d1->diff($d2)->days;
    if($n<1)throw new \RuntimeException('Período inválido.');
    $total=(float)$r['base_price']*$n;
    if($guests>1)$total+=(($guests-1)*(float)$r['extra_guest_fee']*$n);
    $s=$pdo->prepare("SELECT price FROM tariffs WHERE room_id=? AND start_date<=? AND end_date>=? ORDER BY priority DESC,id DESC LIMIT 1");
    $s->execute([$room,$in,$out]);
    if($t=$s->fetch())$total=(float)$t['price']*$n;
    return $total;
  }
  public function create(array $d):int{
    $pdo=DB::pdo();
    $ownsTransaction=!$pdo->inTransaction();
    if($ownsTransaction)$pdo->beginTransaction();
    try{
      $roomId=(int)($d['room_id']??0);
      $checkin=(string)($d['checkin']??'');
      $checkout=(string)($d['checkout']??'');
      $guests=max(1,(int)($d['guests']??1));
      $initial=(string)($d['initial_status']??'pre_reserva');
      if(!in_array($initial,['pre_reserva','confirmada'],true))throw new \RuntimeException('Status inicial inválido.');
      if(!$roomId||!$checkin||!$checkout||$checkout<=$checkin)throw new \RuntimeException('Período inválido.');

      $s=$pdo->prepare("SELECT id,active,status FROM rooms WHERE id=? FOR UPDATE");
      $s->execute([$roomId]);$room=$s->fetch();
      if(!$room)throw new \RuntimeException('Quarto não encontrado.');
      if(!(int)$room['active']||$room['status']!=='active')throw new \RuntimeException('A unidade não está disponível para novas reservas.');
      if(!$this->available($roomId,$checkin,$checkout))throw new \RuntimeException('Período indisponível.');

      $total=$this->price($roomId,$checkin,$checkout,$guests);
      $code='R'.date('ymd').strtoupper(substr(bin2hex(random_bytes(4)),0,6));
      $expiresAt=$initial==='pre_reserva'?date('Y-m-d H:i:s',time()+1800):null;
      $notes=trim((string)($d['notes']??''));

      $s=$pdo->prepare("INSERT INTO reservations(code,guest_id,room_id,checkin,checkout,guests,status,total,source,expires_at,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
      $s->execute([$code,(int)$d['guest_id'],$roomId,$checkin,$checkout,$guests,$initial,$total,$d['source']??'site',$expiresAt,$notes?:null]);
      $id=(int)$pdo->lastInsertId();

      $reason=$initial==='confirmada'?'reserva confirmada criada no painel':'criação de pré-reserva';
      $actor=($d['source']??'site')==='painel'?'user':'system';
      $s=$pdo->prepare("INSERT INTO reservation_status_history(reservation_id,old_status,new_status,actor_type,reason,created_at) VALUES(?,NULL,?,?,?,NOW())");
      $s->execute([$id,$initial,$actor,$reason]);

      if($initial==='confirmada'){
        $method=trim((string)($d['payment_method']??'a_definir'))?:'a_definir';
        $paid=max(0,min($total,(float)($d['amount_paid']??0)));
        $remaining=max(0,$total-$paid);
        if($paid>0){
          $s=$pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,paid_at,created_at,updated_at) VALUES(?,?,'paid',?,'manual',NOW(),NOW(),NOW())");
          $s->execute([$id,$method,$paid]);
        }
        if($remaining>0){
          $s=$pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,created_at,updated_at) VALUES(?,?,'pending',?,'manual',NOW(),NOW())");
          $s->execute([$id,$method,$remaining]);
        }
        $this->ensureVoucher($id);
      }

      if($ownsTransaction)$pdo->commit();
      Audit::log('reservation.create','reservation',$id,['status'=>$initial]);
      return $id;
    }catch(\Throwable $e){
      if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
      throw $e;
    }
  }
  public function changeStatus(int $id,string $new,string $reason=''):void{
    $allowedStates=['pre_reserva','aguardando_aprovacao','aguardando_pagamento','paga','confirmada','recusada','expirada','cancelada','reembolsada','concluida','no_show'];
    if(!in_array($new,$allowedStates,true))throw new \RuntimeException('Status inválido.');
    $transitions=[
      'pre_reserva'=>['aguardando_aprovacao','aguardando_pagamento','recusada','expirada','cancelada'],
      'aguardando_aprovacao'=>['aguardando_pagamento','recusada','cancelada'],
      'aguardando_pagamento'=>['paga','cancelada','expirada'],
      'paga'=>['confirmada','reembolsada','cancelada'],
      'confirmada'=>['concluida','no_show','cancelada'],
      'cancelada'=>['reembolsada'],
      'concluida'=>[],
      'recusada'=>[],
      'expirada'=>[],
      'reembolsada'=>[],
      'no_show'=>[]
    ];
    $pdo=DB::pdo();$pdo->beginTransaction();
    try{
      $s=$pdo->prepare("SELECT status,room_id,checkin,checkout FROM reservations WHERE id=? FOR UPDATE");$s->execute([$id]);$r=$s->fetch();if(!$r)throw new \RuntimeException('Reserva inexistente.');
      $old=$r['status'];if($old===$new){$pdo->commit();return;}
      if(!in_array($new,$transitions[$old]??[],true))throw new \RuntimeException("Transição não permitida: $old -> $new.");
      if($new==='confirmada'){
        $ps=$pdo->prepare("SELECT COUNT(*) FROM payments WHERE reservation_id=? AND status='paid'");$ps->execute([$id]);
        if((int)$ps->fetchColumn()<1)throw new \RuntimeException('Não é possível confirmar sem pagamento registrado como pago.');
        if(!$this->available((int)$r['room_id'],$r['checkin'],$r['checkout'],$id))throw new \RuntimeException('O período entrou em conflito com outro bloqueio/reserva.');
      }
      $pdo->prepare("UPDATE reservations SET status=?,updated_at=NOW() WHERE id=?")->execute([$new,$id]);
      $pdo->prepare("INSERT INTO reservation_status_history(reservation_id,old_status,new_status,actor_type,reason,created_at) VALUES(?,?,?,?,?,NOW())")
          ->execute([$id,$old,$new,'user',$reason]);
      if($new==='confirmada')$this->ensureVoucher($id);
      $pdo->commit(); Audit::log('reservation.status','reservation',$id,['old'=>$old,'new'=>$new]);
    }catch(\Throwable $e){$pdo->rollBack();throw $e;}
  }
  public function confirmPreReservation(int $id,string $method='a_definir',float $amountPaid=0):void{
    $pdo=DB::pdo();$pdo->beginTransaction();
    try{
      $s=$pdo->prepare("SELECT id,status,room_id,checkin,checkout,total,expires_at FROM reservations WHERE id=? FOR UPDATE");$s->execute([$id]);$r=$s->fetch();
      if(!$r)throw new \RuntimeException('Pré-reserva inexistente.');
      if($r['status']==='confirmada'){$pdo->commit();return;}
      if($r['status']!=='pre_reserva')throw new \RuntimeException('Somente uma pré-reserva ativa pode ser efetivada por esta janela.');
      if(!empty($r['expires_at'])&&strtotime((string)$r['expires_at'])<time())throw new \RuntimeException('A pré-reserva expirou. Atualize o mapa e faça uma nova seleção.');
      if(!$this->available((int)$r['room_id'],(string)$r['checkin'],(string)$r['checkout'],$id))throw new \RuntimeException('O período entrou em conflito com outra reserva ou bloqueio.');

      $total=(float)$r['total'];$paid=max(0,min($total,$amountPaid));$remaining=max(0,$total-$paid);$method=trim($method)?:'a_definir';
      $pdo->prepare("DELETE FROM payments WHERE reservation_id=? AND status='pending' AND provider='manual'")->execute([$id]);
      if($paid>0){$s=$pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,paid_at,created_at,updated_at) VALUES(?,?,'paid',?,'manual',NOW(),NOW(),NOW())");$s->execute([$id,$method,$paid]);}
      if($remaining>0){$s=$pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,created_at,updated_at) VALUES(?,?,'pending',?,'manual',NOW(),NOW())");$s->execute([$id,$method,$remaining]);}
      $pdo->prepare("UPDATE reservations SET status='confirmada',expires_at=NULL,updated_at=NOW() WHERE id=?")->execute([$id]);
      $pdo->prepare("INSERT INTO reservation_status_history(reservation_id,old_status,new_status,actor_type,reason,created_at) VALUES(?,'pre_reserva','confirmada','user','Pré-reserva efetivada pelo mapa',NOW())")->execute([$id]);
      $this->ensureVoucher($id);$pdo->commit();
      Audit::log('reservation.confirm_from_map','reservation',$id,['paid'=>$paid,'remaining'=>$remaining,'method'=>$method]);
    }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }

  public function ensureVoucher(int $id):void{
    $pdo=DB::pdo();
    $s=$pdo->prepare("SELECT id FROM vouchers WHERE reservation_id=?");$s->execute([$id]);if($s->fetch())return;
    $token=strtoupper(bin2hex(random_bytes(8)));
    $pdo->prepare("INSERT INTO vouchers(reservation_id,code,created_at) VALUES(?,?,NOW())")->execute([$id,$token]);
  }
}
