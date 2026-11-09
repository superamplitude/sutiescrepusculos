<?php
declare(strict_types=1);
// Execute only through the existing authorized gerente-vps runner.
// Explicit correction requested by the owner: this property has neither breakfast nor pool.
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=\App\Core\DB::pdo();
$patterns=['caf[eé][[:space:]]+da[[:space:]]+manh[aã]|breakfast','piscina|pool'];
$pdo->beginTransaction();
try{
  $disabledAmenities=0;$disabledFaq=0;
  foreach($patterns as $pattern){
    $q=$pdo->prepare("DELETE pa FROM property_amenities pa INNER JOIN amenities a ON a.id=pa.amenity_id WHERE pa.property_id=1 AND a.scope='property' AND LOWER(a.name) REGEXP ?");
    $q->execute([$pattern]);$disabledAmenities+=$q->rowCount();
    $q=$pdo->prepare("UPDATE knowledge_base SET active=0 WHERE active=1 AND (title REGEXP ? OR content REGEXP ?)");
    $q->execute([$pattern,$pattern]);$disabledFaq+=$q->rowCount();
  }
  $pdo->commit();
  echo 'SERVICE_CORRECTION_OK amenities_disabled='.$disabledAmenities.' faq_deactivated='.$disabledFaq.PHP_EOL;
}catch(\Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  throw $e;
}
