<?php
namespace App\Services;

use App\Core\DB;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class ChatAvailabilityService {
    public function answer(string $message,array $history=[]): ?array {
        $message=trim($message);
        if($message==='')return null;

        $normalized=$this->normalize($message);
        $source=$normalized;

        // Mensagens de continuação retomam a última pergunta operacional do visitante.
        if(!$this->isAvailabilityIntent($normalized) || !$this->extractDateRange($normalized)){
            $previous=$this->findPreviousAvailabilityQuestion($history);
            if($previous!==null && $this->isFollowUp($normalized)){
                $source=$previous;
            }
        }

        if(!$this->isAvailabilityIntent($source))return null;

        $range=$this->extractDateRange($source);
        if(!$range){
            return [
                'handled'=>true,
                'ok'=>true,
                'answer'=>'Posso consultar a disponibilidade em tempo real. Informe a data de entrada, por exemplo: "tem vaga amanhã?" ou "tem vaga dia 20/08?".'
            ];
        }

        [$checkin,$checkout,$label]=$range;

        try{
            $result=$this->availability($checkin,$checkout);
        }catch(Throwable $e){
            // Perguntas operacionais nunca devem cair silenciosamente no Gemini.
            return [
                'handled'=>true,
                'ok'=>false,
                'answer'=>'Não consegui consultar o mapa de reservas neste instante. A equipe já consegue ver esta conversa no painel e pode continuar o atendimento sem você repetir a pergunta.',
                'error'=>$e->getMessage(),
                'checkin'=>$checkin,
                'checkout'=>$checkout
            ];
        }

        if(!$result['rooms']){
            return [
                'handled'=>true,
                'ok'=>true,
                'answer'=>"Para {$label}, não encontrei acomodação livre no mapa de reservas neste momento. Posso verificar outra data para você.",
                'checkin'=>$checkin,
                'checkout'=>$checkout,
                'available_count'=>0,
                'rooms'=>[]
            ];
        }

        $count=count($result['rooms']);
        $names=array_column($result['rooms'],'name');
        $first=array_slice($names,0,5);
        $list=implode(', ',$first);
        if($count>5)$list.=' e mais '.($count-5);

        $minPrice=min(array_map(fn($r)=>(float)$r['base_price'],$result['rooms']));
        $priceText=$minPrice>0?' As opções começam em R$ '.number_format($minPrice,2,',','.').'.':'';

        $answer="Sim. Consultei o mapa de reservas e encontrei {$count} ".($count===1?'acomodação disponível':'acomodações disponíveis')." para {$label}: {$list}.{$priceText} Quantas pessoas vão se hospedar?";

        return [
            'handled'=>true,
            'ok'=>true,
            'answer'=>$answer,
            'checkin'=>$checkin,
            'checkout'=>$checkout,
            'available_count'=>$count,
            'rooms'=>$result['rooms'],
            'availability_url'=>'index.php?p=availability&checkin='.rawurlencode($checkin).'&checkout='.rawurlencode($checkout).'&adults=2&children=0'
        ];
    }

    private function availability(string $checkin,string $checkout): array {
        $pdo=DB::pdo();
        $service=new ReservationService();

        // active=1 é a trava funcional. Não depende de strings antigas em rooms.status.
        $rooms=$pdo->query("SELECT id,name,type,capacity,base_price,status FROM rooms WHERE active=1 ORDER BY base_price,id")->fetchAll();

        $available=[];
        foreach($rooms as $room){
            if($service->available((int)$room['id'],$checkin,$checkout)){
                $available[]=[
                    'id'=>(int)$room['id'],
                    'name'=>(string)$room['name'],
                    'type'=>(string)($room['type']??''),
                    'capacity'=>(int)$room['capacity'],
                    'base_price'=>(float)$room['base_price']
                ];
            }
        }

        return ['rooms'=>$available];
    }

    private function findPreviousAvailabilityQuestion(array $history): ?string {
        for($i=count($history)-1;$i>=0;$i--){
            $m=$history[$i]??[];
            if(($m['sender_type']??'')!=='guest')continue;
            $body=$this->normalize((string)($m['body']??''));
            if($this->isAvailabilityIntent($body) && $this->extractDateRange($body)){
                return $body;
            }
        }
        return null;
    }

    private function isFollowUp(string $m): bool {
        $m=trim($m);
        if($m==='' || $m==='?')return true;
        $tokens=[
            'estou aguardando','aguardando','e ai','e a resposta','qual a resposta',
            'perguntei sobre reserva','perguntei sobre reservas','minha pergunta',
            'vc ja disse isso','voce ja disse isso','ja perguntei','responde',
            'sobre a reserva','sobre reservas','sobre disponibilidade'
        ];
        foreach($tokens as $token){
            if(str_contains($m,$token))return true;
        }
        return mb_strlen($m,'UTF-8')<=12 && preg_match('/^(e|entao|responde|oi|ola|\?)+[ !?]*$/u',$m);
    }

    private function isAvailabilityIntent(string $m): bool {
        $tokens=[
            'vaga','vagas','disponivel','disponibilidade','tem quarto','tem apto',
            'tem apartamento','quarto livre','acomodacao livre','hospedagem livre',
            'posso reservar','da para reservar','consigo reservar','reservar para'
        ];
        foreach($tokens as $token){
            if(str_contains($m,$token))return true;
        }
        return false;
    }

    private function extractDateRange(string $m): ?array {
        $tzName=date_default_timezone_get();
        if(!$tzName || $tzName==='UTC')$tzName='America/Sao_Paulo';
        $tz=new DateTimeZone($tzName);
        $today=new DateTimeImmutable('today',$tz);

        if(str_contains($m,'depois de amanha')){
            $in=$today->modify('+2 days');
            return $this->oneNight($in,'depois de amanhã ('.$in->format('d/m').')');
        }

        if(str_contains($m,'amanha')){
            $in=$today->modify('+1 day');
            return $this->oneNight($in,'amanhã ('.$in->format('d/m').')');
        }

        if(preg_match('/\bhoje\b/',$m)){
            return $this->oneNight($today,'hoje ('.$today->format('d/m').')');
        }

        if(preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/',$m,$match)){
            $day=(int)$match[1];$month=(int)$match[2];
            $year=isset($match[3])&&$match[3]!==''?(int)$match[3]:(int)$today->format('Y');
            if($year<100)$year+=2000;
            if(!checkdate($month,$day,$year))return null;
            $in=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),$tz);
            if((!isset($match[3])||$match[3]==='') && $in<$today)$in=$in->modify('+1 year');
            return $this->oneNight($in,$in->format('d/m/Y'));
        }

        if(preg_match('/\bdia\s+(\d{1,2})\b/',$m,$match)){
            $day=(int)$match[1];
            $year=(int)$today->format('Y');$month=(int)$today->format('m');
            if(!checkdate($month,$day,$year))return null;
            $in=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),$tz);
            if($in<$today){
                $next=$today->modify('first day of next month');
                $year=(int)$next->format('Y');$month=(int)$next->format('m');
                if(!checkdate($month,$day,$year))return null;
                $in=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day),$tz);
            }
            return $this->oneNight($in,$in->format('d/m/Y'));
        }

        return null;
    }

    private function oneNight(DateTimeImmutable $in,string $label): array {
        $out=$in->modify('+1 day');
        return [$in->format('Y-m-d'),$out->format('Y-m-d'),$label.' até '.$out->format('d/m')];
    }

    private function normalize(string $text): string {
        $text=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
        $map=[
            'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
            'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
            'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
            'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
            'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','Ç'=>'c',
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'
        ];
        return strtr($text,$map);
    }
}
