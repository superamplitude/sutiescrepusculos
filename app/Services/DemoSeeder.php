<?php
namespace App\Services;

use PDO;

final class DemoSeeder {
    public static function isEmpty(PDO $pdo): bool {
        $rooms=(int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
        $reservations=(int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
        $name=(string)($pdo->query("SELECT COALESCE(name,'') FROM property WHERE id=1")->fetchColumn() ?: '');
        return $rooms===0 && $reservations===0 && trim($name)==='';
    }

    public static function seed(PDO $pdo): array {
        if(!self::isEmpty($pdo)){
            throw new \RuntimeException('O conteúdo demo só pode ser carregado em uma instalação sem quartos, reservas e nome de pousada cadastrados.');
        }

        $today=new \DateTimeImmutable('today');
        $d=fn(int $n)=>$today->modify(($n>=0?'+':'').$n.' days')->format('Y-m-d');

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE property SET
                name=?,category='Pousada',subcategory='Charme',
                short_description=?,description=?,highlights=?,history=?,concept=?,target_audience=?,
                region_info=?,location_highlights=?,own_services=?,general_policies=?,status='published',
                active=1,verified=1,currency='BRL',announcement_enabled=1,announcement_text='Reserve direto e tenha atendimento rápido pelo site',announcement_link_text='Ver disponibilidade',announcement_link_url='index.php?p=availability',country='Brasil',state='RJ',city='Paraty',neighborhood='Caborê',
                address='Rua das Palmeiras',address_number='120',complement='Próximo ao Centro Histórico',zip='23970-000',
                landmark='A 8 minutos do Centro Histórico',latitude=-23.2205,longitude=-44.7204,
                map_url='https://www.openstreetmap.org/?mlat=-23.2205&mlon=-44.7204#map=15/-23.2205/-44.7204',
                directions=?,distance_beach='1,4 km',distance_center='900 m',distance_bus='1,2 km',distance_airport='Não se aplica',
                access_conditions=?,parking_notes=?,manager_name='Marina Costa',phone='(24) 3333-0000',
                whatsapp='(24) 99999-0000',email='reservas@brisadomar.demo',site_url='https://demo.brisadomar.local',
                instagram='@pousadabrisadomar.demo',facebook='Pousada Brisa do Mar Demo',youtube='Brisa do Mar Demo',
                operating_days='Todos os dias',special_hours='Recepção presencial das 07:00 às 23:00; suporte remoto fora desse horário.',
                open_24h=1,reception_24h=0,checkin='14:00:00',checkout='12:00:00',self_checkin=1,min_stay=1,max_stay=30,long_stay=1,
                accepts_children=1,accepts_pets=1,assistance_animals=1,smoking_allowed=0,parties_allowed=0,
                quiet_hours='22:00 às 08:00',parking=1,parking_spaces=12,visitor_rules=?,cancellation_policy=?,
                checkin_rules=?,checkout_rules=?,early_checkin=?,late_checkout=?,damage_deposit=?,house_rules=?,
                accessibility_notes=?,security_notes=?,hero_image='assets/demo/real/piscina.jpg',hero_media_type='image',hero_video='assets/demo/hero-demo.mp4',hero_video_url=NULL,hero_poster='assets/demo/real/piscina.jpg',updated_at=NOW()
              WHERE id=1")->execute([
                'Pousada Brisa do Mar',
                'Charme, conforto e hospitalidade em Paraty, com atendimento inteligente e reserva direta.',
                'A Pousada Brisa do Mar é um refúgio de charme em Paraty para quem quer descansar com conforto sem ficar longe do Centro Histórico. A proposta combina quartos acolhedores, café da manhã artesanal, piscina, jardim e atendimento próximo.',
                'Reserva direta • Café da manhã incluso • Piscina • Estacionamento • Wi-Fi • Atendimento por WhatsApp • Dicas personalizadas de Paraty',
                'A Brisa do Mar nasceu do desejo de receber viajantes como amigos. O projeto foi pensado para valorizar a experiência em Paraty: manhãs tranquilas, contato com a natureza e acesso rápido às praias, cachoeiras, gastronomia e cultura local.',
                'Uma pousada de charme com operação simples, acolhedora e tecnológica. A tecnologia fica nos bastidores para facilitar a reserva, enquanto a experiência do hóspede permanece humana.',
                'Casais, famílias pequenas, viajantes de fim de semana, grupos de amigos e hóspedes que valorizam localização, conforto e atendimento rápido.',
                'Paraty reúne patrimônio histórico, praias, ilhas, cachoeiras, trilhas, gastronomia e cultura caiçara. A pousada funciona como ponto de apoio para explorar a cidade e a Costa Verde.',
                'Próxima ao Centro Histórico, com acesso rápido à Praia do Jabaquara e às saídas para passeios de barco e cachoeiras.',
                'Café da manhã, apoio para passeios, estacionamento, guarda de bagagem, atendimento por WhatsApp e orientações de chegada.',
                'As políticas podem variar em feriados e pacotes especiais. Sempre consulte as condições exibidas no momento da reserva.',
                'Saindo da Rodoviária de Paraty, siga em direção ao bairro Caborê. A pousada fica a poucos minutos do Centro Histórico. O mapa e a rota podem ser abertos pelo próprio site.',
                'Acesso por via urbana pavimentada. Não é necessário veículo 4x4.',
                'Estacionamento interno gratuito, sujeito à disponibilidade da vaga vinculada à hospedagem.',
                'Visitantes somente com autorização prévia da recepção e identificação.',
                'Cancelamento gratuito até 7 dias antes do check-in em tarifas flexíveis. Entre 7 dias e 48 horas, poderá ser retido 50% do valor. Com menos de 48 horas ou no-show, poderá ser cobrado 100%. Pacotes e tarifas promocionais podem possuir regras próprias.',
                'Documento com foto no check-in. O titular da reserva deve apresentar os dados informados no cadastro. Check-in a partir das 14:00.',
                'Check-out até 12:00. Consulte a recepção caso precise guardar bagagens após a saída.',
                'Early check-in sujeito à disponibilidade. Quando disponível, poderá haver cobrança adicional.',
                'Late check-out sujeito à disponibilidade e pode gerar cobrança proporcional.',
                'Não cobramos caução padrão no demo. Danos comprovados poderão ser cobrados conforme avaliação da administração.',
                'Não fumar nos quartos. Respeitar o horário de silêncio. Crianças devem permanecer acompanhadas nas áreas comuns. Pets são aceitos mediante aviso prévio.',
                'Acesso em nível nas principais áreas comuns, vaga acessível e possibilidade de acomodação adaptada mediante disponibilidade.',
                'Extintores, sinalização de emergência, iluminação de apoio, câmeras apenas em áreas comuns permitidas e equipe treinada para orientações básicas.'
            ]);

            // Amenidades da pousada
            $wantedProperty=[
                'Wi-Fi','Espaço de trabalho','Piscina','Piscina infantil','Churrasqueira','Jardim','Terraço',
                'Café da manhã','Bar','Serviço de limpeza','Limpeza diária','Depósito de bagagem','Concierge',
                'Transfer','Táxi','Serviço de praia','Guarda-sol','Segurança','Estacionamento',
                'Rampas','Vaga acessível','Extintores','Sinalização de emergência'
            ];
            $getAmenity=$pdo->prepare("SELECT id FROM amenities WHERE name=? AND scope=? LIMIT 1");
            $insPA=$pdo->prepare("INSERT IGNORE INTO property_amenities(property_id,amenity_id) VALUES(1,?)");
            foreach($wantedProperty as $name){
                $getAmenity->execute([$name,'property']);$id=$getAmenity->fetchColumn();if($id)$insPA->execute([$id]);
            }

            $rooms=[
                [
                    'STD01','Suíte Standard','Suíte',
                    'Conforto essencial para casal ou viajante solo.',
                    'Suíte silenciosa e acolhedora com cama de casal, ar-condicionado, frigobar, Wi-Fi e banheiro privativo. Ideal para estadias curtas e casais que querem praticidade.',
                    2,2,0,['casal'=>1],'Privativo','Térreo','Ala Jardim',0,'Jardim',24,'assets/demo/real/quarto-standard.jpg',280,0,0,1,10,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout','Secador de cabelo']
                ],
                [
                    'VAR02','Suíte Varanda','Suíte',
                    'Varanda privativa para relaxar depois de um dia em Paraty.',
                    'Suíte com varanda, cama queen, frigobar, ar-condicionado e vista para o jardim interno. Uma opção romântica e confortável para duas pessoas.',
                    2,2,0,['queen'=>1],'Privativo','1º andar','Ala Varanda',1,'Jardim',28,'assets/demo/real/quarto-varanda.jpg',340,0,0,1,10,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout','Secador de cabelo','Cofre']
                ],
                [
                    'FAM03','Suíte Família','Quarto família',
                    'Espaço confortável para famílias de até quatro hóspedes.',
                    'Acomodação ampla com uma cama de casal e duas camas de solteiro, banheiro privativo, ar-condicionado e espaço para organizar a rotina com crianças.',
                    4,2,2,['casal'=>1,'solteiro'=>2],'Privativo','Térreo','Ala Família',0,'Pátio interno',36,'assets/demo/real/quarto-familia.jpg',460,80,0,2,14,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout','Berço infantil','Micro-ondas']
                ],
                [
                    'PRE04','Suíte Premium Vista Jardim','Suíte',
                    'Cama king, mais espaço e vista tranquila para o jardim.',
                    'Nossa suíte premium foi pensada para casais que desejam uma experiência mais confortável, com cama king, área de estar, varanda e vista para o jardim.',
                    2,2,0,['king'=>1],'Privativo','1º andar','Ala Premium',1,'Jardim',34,'assets/demo/real/quarto-premium.jpg',520,0,0,2,14,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Netflix / streaming','Cofre','Secador de cabelo','Espaço de trabalho']
                ],
                [
                    'APT05','Apartamento Família Plus','Apartamento',
                    'Até cinco hóspedes com ambientes mais amplos.',
                    'Apartamento ideal para famílias maiores, com cama queen, duas camas de solteiro e sofá-cama. Possui frigobar, micro-ondas, varanda e banheiro privativo.',
                    5,3,2,['queen'=>1,'solteiro'=>2,'sofa_cama'=>1],'Privativo','Térreo','Ala Família Plus',1,'Jardim',44,'assets/demo/real/apartamento-familia.jpg',590,90,0,2,20,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout','Micro-ondas','Mini copa','Sofá-cama']
                ],

                [
                    'APT06','Apartamento Jardim','Apartamento',
                    'Apartamento térreo com varanda e acesso rápido ao jardim.',
                    'Acomodação para até três hóspedes com cama queen, sofá-cama, banheiro privativo, varanda e localização próxima às áreas de lazer.',
                    3,2,1,['queen'=>1,'sofa_cama'=>1],'Privativo','Térreo','Ala Jardim',1,'Jardim',32,'assets/demo/real/apartamento-jardim.jpg',410,70,0,2,14,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Sofá-cama']
                ],
                [
                    'CHA07','Chalé Romance','Chalé',
                    'Mais privacidade para uma viagem a dois.',
                    'Chalé independente com cama king, varanda, frigobar, ar-condicionado e atmosfera reservada para casais.',
                    2,2,0,['king'=>1],'Privativo','Térreo','Área dos Chalés',1,'Jardim',38,'assets/demo/real/chale-romance.jpg',650,0,0,2,14,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Cofre','Secador de cabelo']
                ],
                [
                    'LOFT08','Loft Família','Apartamento',
                    'Loft amplo para famílias e estadias maiores.',
                    'Loft com cama queen, duas camas de solteiro, sofá-cama e mini copa. Espaço confortável para até cinco hóspedes.',
                    5,3,2,['queen'=>1,'solteiro'=>2,'sofa_cama'=>1],'Privativo','1º andar','Ala Loft',1,'Pátio interno',48,'assets/demo/real/loft-familia.jpg',620,90,0,2,21,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Micro-ondas','Mini copa','Sofá-cama']
                ],
                [
                    'SUP09','Suíte Superior','Suíte',
                    'Suíte ampla com cama queen e ambiente de descanso.',
                    'Suíte superior com cama queen, poltrona de leitura, frigobar, ar-condicionado, banheiro privativo e vista para a área verde.',
                    2,2,0,['queen'=>1],'Privativo','1º andar','Ala Superior',1,'Área verde',30,'assets/demo/real/quarto-premium.jpg',430,0,0,2,14,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout','Secador de cabelo']
                ],
                [
                    'TRI10','Apartamento Triplo','Apartamento',
                    'Praticidade para três hóspedes.',
                    'Apartamento com uma cama de casal e uma cama de solteiro, banheiro privativo, frigobar, Wi-Fi e ar-condicionado.',
                    3,3,0,['casal'=>1,'solteiro'=>1],'Privativo','Térreo','Ala Central',0,'Pátio',29,'assets/demo/real/quarto-standard.jpg',390,50,0,1,12,
                    ['Ar-condicionado','Roupa de cama','Toalhas','Frigobar','Wi-Fi','Televisão','Blackout']
                ],
            ];
            $insRoom=$pdo->prepare("INSERT INTO rooms(code,name,type,short_description,description,status,capacity,adults,children,beds_count,beds_json,bathroom_type,floor_name,internal_location,balcony,view_name,area_m2,video_url,cover_image,video_path,rules,base_price,extra_guest_fee,min_nights,max_nights,cleaning_fee,active,created_at,updated_at) VALUES(?,?,?,?,?,'active',?,?,?,?,?,?,?,?,?,?,?,NULL,?,NULL,?,?,?,?,?,?,1,NOW(),NOW())");
            $insRA=$pdo->prepare("INSERT IGNORE INTO room_amenities(room_id,amenity_id) VALUES(?,?)");
            $roomIds=[];
            foreach($rooms as $r){
                [$code,$name,$type,$short,$desc,$cap,$adults,$children,$beds,$bath,$floor,$loc,$balcony,$view,$area,$cover,$price,$extra,$clean,$min,$max,$ams]=$r;
                $insRoom->execute([$code,$name,$type,$short,$desc,$cap,$adults,$children,array_sum($beds),json_encode($beds,JSON_UNESCAPED_UNICODE),$bath,$floor,$loc,$balcony,$view,$area,$cover,'Silêncio após 22:00. Não fumar no quarto.',$price,$extra,$min,$max,$clean]);
                $rid=(int)$pdo->lastInsertId();$roomIds[$code]=$rid;
                foreach($ams as $am){$getAmenity->execute([$am,'room']);$aid=$getAmenity->fetchColumn();if($aid)$insRA->execute([$rid,$aid]);}
                $pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,created_at) VALUES('room',?,'image',?,?,?,0,1,NOW())")
                    ->execute([$rid,$cover,$name,$name]);
            }

            // Mídia geral
            $propertyMedia=[
                ['image','assets/demo/real/piscina.jpg','Pousada Brisa do Mar','Área externa da Pousada Brisa do Mar',0,1],
                ['video','assets/demo/hero-demo.mp4','Vídeo da pousada','Vídeo demonstrativo da Pousada Brisa do Mar',1,0],
                ['image','assets/demo/real/paraty-praia.jpg','Praias próximas','Praia do Jabaquara',2,0],
                ['image','assets/demo/real/paraty-centro.jpg','Paraty e sua história','Centro Histórico de Paraty',3,0],
                ['image','assets/demo/real/paraty-praia.jpg','Natureza em Paraty','Cachoeiras de Paraty',4,0],
            ];
            $im=$pdo->prepare("INSERT INTO media(scope,entity_id,type,file_path,caption,alt_text,sort_order,is_cover,created_at) VALUES('property',1,?,?,?,?,?,?,NOW())");
            foreach($propertyMedia as $m)$im->execute($m);

            // Promoções
            $promo=$pdo->prepare("INSERT INTO promotions(title,description,code,discount_type,discount_value,start_date,end_date,active) VALUES(?,?,?,?,?,?,?,1)");
            $promo->execute(['Fim de Semana em Paraty','Hospede-se por duas ou mais noites entre sexta e domingo e aproveite 10% de desconto na tarifa selecionada.','PARATY10','percent',10,$d(-15),$d(120)]);
            $promo->execute(['3 noites, mais tempo para aproveitar','Reserve três noites ou mais e receba 12% de desconto em períodos participantes.','MAISTEMPO12','percent',12,$d(-15),$d(180)]);
            $promo->execute(['Romance na Costa Verde','Condição especial para duas noites na Suíte Premium, com decoração romântica de boas-vindas.','ROMANCE','fixed',80,$d(1),$d(90)]);

            // Atrações
            $attractions=[
                ['Centro Histórico de Paraty','Cultura','900 m','12 min a pé','Centro Histórico, Paraty - RJ',-23.2195,-44.7135,'Caminhe pelas ruas de pedra, visite ateliês, igrejas, cafés e restaurantes em um dos conjuntos coloniais mais preservados do Brasil.','assets/demo/real/paraty-centro.jpg',''],
                ['Praia do Jabaquara','Praia','1,4 km','6 min de carro','Jabaquara, Paraty - RJ',-23.2275,-44.7126,'Praia urbana de águas tranquilas, boa para caminhar, relaxar e apreciar o fim de tarde.','assets/demo/real/paraty-praia.jpg',''],
                ['Cachoeiras da Estrada Paraty-Cunha','Natureza','8 km','18 min de carro','Estrada Paraty-Cunha, Paraty - RJ',-23.165,-44.755,'Circuito de natureza com quedas d’água, poços e opções de visita guiada.','assets/demo/real/paraty-praia.jpg',''],
                ['Passeio de barco pela Baía de Paraty','Passeio','1,2 km','15 min a pé até o cais','Cais de Turismo, Paraty - RJ',-23.2188,-44.7109,'Passeios de escuna e embarcações menores com paradas em praias e ilhas da baía.','assets/demo/real/paraty-praia.jpg',''],
            ];
            $ia=$pdo->prepare("INSERT INTO attractions(name,category,distance_text,time_text,address,latitude,longitude,description,photo,url,active) VALUES(?,?,?,?,?,?,?,?,?,?,1)");
            foreach($attractions as $a)$ia->execute($a);

            // Conteúdo e SEO
            $content=$pdo->prepare("INSERT INTO contents(slug,title,body,seo_title,meta_description,status,updated_at) VALUES(?,?,?,?,?,'published',NOW())");
            $content->execute(['gastronomia','Café da manhã com sabores de Paraty',"Comece o dia com frutas frescas, pães, bolos caseiros, café, leite, sucos e opções quentes preparadas diariamente. Sempre que possível, valorizamos ingredientes locais e produtores da região.\n\nInforme alergias ou restrições alimentares antes da chegada para verificarmos as possibilidades de atendimento.",'Café da manhã e gastronomia | Pousada Brisa do Mar','Conheça o café da manhã e a proposta de gastronomia da Pousada Brisa do Mar em Paraty.']);
            $content->execute(['experiencia-paraty','Uma estadia para viver Paraty',"Paraty pode ser aproveitada de muitas formas: caminhando pelo Centro Histórico, navegando pela baía, mergulhando em cachoeiras ou simplesmente desacelerando.\n\nNossa equipe ajuda a organizar seu roteiro de acordo com o tempo disponível e o perfil da viagem.",'O que fazer em Paraty | Pousada Brisa do Mar','Dicas para aproveitar Paraty durante sua hospedagem na Pousada Brisa do Mar.']);

            $content->execute(['nossa-historia','Nossa história',"A Pousada Brisa do Mar nasceu de uma ideia simples: criar um lugar onde a viagem começasse antes mesmo de sair para conhecer Paraty.\n\nO projeto fictício combina hospitalidade, natureza e tecnologia. A pousada foi pensada para receber casais, famílias e viajantes de fim de semana com atendimento próximo e reserva direta.",'Nossa história | Pousada Brisa do Mar','Conheça a história fictícia da Pousada Brisa do Mar em Paraty.']);
            $content->execute(['sobre','Sobre a Pousada Brisa do Mar',"Uma pousada fictícia de charme em Paraty, criada para demonstrar todos os recursos do sistema Pousada IA.\n\nO conceito reúne acomodações confortáveis, café da manhã, piscina, estacionamento, informações turísticas, reserva direta e atendimento integrado.",'Sobre | Pousada Brisa do Mar','Conheça a proposta e a estrutura da Pousada Brisa do Mar.']);
            $content->execute(['acomodacoes','Acomodações',"O demo possui suítes, apartamentos, chalés e opções para famílias.\n\nCada unidade possui capacidade, tipo de cama, banheiro, localização interna, comodidades, fotos, vídeo, regras e tarifa-base próprias.",'Acomodações | Pousada Brisa do Mar','Suítes, apartamentos e chalés para sua hospedagem em Paraty.']);
            $content->execute(['tarifas-promocoes','Tarifas e promoções',"As tarifas do demo variam de acordo com a acomodação, quantidade de hóspedes, período e condições promocionais.\n\nPromoções demonstrativas incluem desconto para fim de semana, estadias mais longas, famílias e viagens a dois.",'Tarifas e promoções | Pousada Brisa do Mar','Veja tarifas e promoções fictícias da Pousada Brisa do Mar.']);

            // Base de conhecimento / FAQ
            $faq=[
                ['Qual o horário de check-in?','O check-in começa às 14:00. Early check-in depende de disponibilidade e pode ter cobrança adicional.'],
                ['Qual o horário de check-out?','O check-out deve ser realizado até 12:00. A pousada pode guardar bagagens depois da saída.'],
                ['O café da manhã está incluído?','Sim. No conteúdo demo, o café da manhã está incluído nas tarifas padrão, salvo indicação diferente em uma promoção específica.'],
                ['A pousada tem estacionamento?','Sim. Há estacionamento interno gratuito com 12 vagas, sujeito à disponibilidade.'],
                ['Aceita crianças?','Sim. A pousada recebe crianças e possui opções de acomodação para famílias.'],
                ['Aceita pets?','Sim, mediante aviso prévio. Algumas regras de circulação e acomodação podem ser aplicadas.'],
                ['Tem Wi-Fi?','Sim. Há Wi-Fi nas acomodações e principais áreas comuns.'],
                ['Tem piscina?','Sim. A pousada possui piscina e piscina infantil.'],
                ['Como funciona o cancelamento?','Tarifas flexíveis podem ser canceladas gratuitamente até 7 dias antes do check-in. Depois disso, podem existir retenções conforme a proximidade da chegada.'],
                ['A pousada fica longe do Centro Histórico?','Não. O conteúdo demo considera cerca de 900 metros até o Centro Histórico de Paraty.'],
                ['Vocês ajudam com passeios?','Sim. A equipe pode orientar sobre passeios de barco, praias, cachoeiras e atrações da região.'],
                ['Como funciona o PIX?','Quando um provedor PIX real estiver configurado, o painel do viajante exibirá a cobrança e a confirmação será vinculada à reserva.'],
            ];
            $kb=$pdo->prepare("INSERT INTO knowledge_base(title,content,source,active,updated_at) VALUES(?,?,'Conteúdo demo aprovado',1,NOW())");
            foreach($faq as $f)$kb->execute($f);

            // Hóspedes
            $guests=[
                ['Ana Martins','ana.demo@example.com','(11) 99911-1111'],
                ['Ricardo Souza','ricardo.demo@example.com','(21) 99922-2222'],
                ['Carla Mendes','carla.demo@example.com','(31) 99933-3333'],
                ['Felipe Rocha','felipe.demo@example.com','(41) 99944-4444'],
                ['Mariana Lima','mariana.demo@example.com','(51) 99955-5555'],
            ];
            $ig=$pdo->prepare("INSERT INTO guests(name,email,whatsapp,city,state,country,language,privacy_consent,created_at,updated_at) VALUES(?,?,?,'São Paulo','SP','Brasil','pt-BR',1,NOW(),NOW())");
            $guestIds=[];
            foreach($guests as $g){$ig->execute($g);$guestIds[$g[0]]=(int)$pdo->lastInsertId();}

            // Reservas relativas à data de instalação
            $reservations=[
                ['Ana Martins','STD01',2,5,2,'confirmada',840],
                ['Ricardo Souza','VAR02',4,7,2,'aguardando_pagamento',1020],
                ['Carla Mendes','FAM03',7,11,4,'confirmada',1840],
                ['Felipe Rocha','PRE04',10,13,2,'pre_reserva',1560],
                ['Mariana Lima','APT05',-10,-7,4,'concluida',1770],
            ];
            $ir=$pdo->prepare("INSERT INTO reservations(code,guest_id,room_id,checkin,checkout,guests,status,total,source,expires_at,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?, 'demo',NULL,'Reserva demonstrativa',NOW(),NOW())");
            $hist=$pdo->prepare("INSERT INTO reservation_status_history(reservation_id,old_status,new_status,actor_type,reason,created_at) VALUES(?,NULL,?,'system','conteúdo demo',NOW())");
            $pay=$pdo->prepare("INSERT INTO payments(reservation_id,method,status,amount,provider,external_id,paid_at,created_at,updated_at) VALUES(?,'pix',?,?, 'demo',?, ?,NOW(),NOW())");
            $voucher=$pdo->prepare("INSERT INTO vouchers(reservation_id,code,created_at) VALUES(?,?,NOW())");
            $reservationIds=[];
            $n=1;
            foreach($reservations as $r){
                [$guest,$room,$a,$b,$guestsN,$status,$total]=$r;
                $code='DEMO'.str_pad((string)$n,4,'0',STR_PAD_LEFT);
                $ir->execute([$code,$guestIds[$guest],$roomIds[$room],$d($a),$d($b),$guestsN,$status,$total]);
                $rid=(int)$pdo->lastInsertId();$reservationIds[$guest]=$rid;$hist->execute([$rid,$status]);
                $isPaid=in_array($status,['confirmada','concluida'],true);
                $pay->execute([$rid,$isPaid?'paid':'pending',$total,'DEMO-PAY-'.$n,$isPaid?date('Y-m-d H:i:s'):null]);
                if($isPaid)$voucher->execute([$rid,'VOUCHER-DEMO-'.str_pad((string)$n,4,'0',STR_PAD_LEFT)]);
                $n++;
            }

            // Pré-bloqueio de manutenção
            $pdo->prepare("INSERT INTO room_blocks(room_id,start_date,end_date,type,reason,created_by,source,created_at) VALUES(?,?,?,'maintenance','Manutenção preventiva',NULL,'demo',NOW())")
                ->execute([$roomIds['STD01'],$d(12),$d(14)]);

            // Conversa demonstrativa
            $pdo->prepare("INSERT INTO conversations(guest_id,reservation_id,channel,mode,status,priority,created_at,updated_at) VALUES(?,?,'site','hybrid','open','normal',NOW(),NOW())")
                ->execute([$guestIds['Ricardo Souza'],$reservationIds['Ricardo Souza']]);
            $cid=(int)$pdo->lastInsertId();
            $msg=$pdo->prepare("INSERT INTO messages(conversation_id,sender_type,body,created_at) VALUES(?,?,?,NOW())");
            $msg->execute([$cid,'guest','Olá! Gostaria de saber se consigo fazer o check-in um pouco antes.']);
            $msg->execute([$cid,'staff','Olá, Ricardo! O early check-in depende da saída do hóspede anterior. Podemos confirmar mais perto da data.']);

            // Avaliação concluída
            $pdo->prepare("INSERT INTO reviews(reservation_id,guest_id,rating,comment,property_reply,status,reported,created_at) VALUES(?,?,?,?,?,'published',0,NOW())")
                ->execute([$reservationIds['Mariana Lima'],$guestIds['Mariana Lima'],5,'A pousada é muito agradável, o atendimento foi rápido e a localização facilitou bastante nosso passeio por Paraty.','Mariana, muito obrigado pela avaliação! Ficamos felizes que tenha aproveitado a estadia e esperamos receber vocês novamente.']);

            // Mais avaliações simuladas ligadas à reserva concluída? Uma por reserva é permitida; criamos apenas a verificável.
            // Feed iCal por quarto
            $ical=$pdo->prepare("INSERT IGNORE INTO ical_feeds(room_id,export_token,active,created_at,updated_at) VALUES(?,?,1,NOW(),NOW())");
            foreach($roomIds as $rid)$ical->execute([$rid,bin2hex(random_bytes(24))]);

            $pdo->commit();

            return [
                'property'=>'Pousada Brisa do Mar',
                'rooms'=>count($roomIds),
                'guests'=>count($guestIds),
                'reservations'=>count($reservations),
                'demo_access'=>[
                    'email'=>'mariana.demo@example.com',
                    'reservation'=>'DEMO0005'
                ]
            ];
        } catch(\Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }


}
