<?php
declare(strict_types=1);
require __DIR__.'/../private/integration-health.php';
$now=new DateTimeImmutable('2026-10-24 22:00:00',new DateTimeZone('UTC'));
$cases=[
  [['2026-10-24 21:58:00',true],'recent',2],
  [['2026-10-24 20:59:00',true],'delayed',61],
  [['2026-10-24 15:59:00',true],'stale',361],
  [['2026-10-24 22:00:00',true],'recent',0],
  [[null,true],'never',null],
  [['2026-10-24 20:00:00',false],'disabled',null],
  [['2026-02-31 05:05:05',true],'invalid_time',null],
  [['2026-10-24 22:10:00',true],'clock_skew',null],
];
foreach($cases as $i=>[$args,$status,$age]){
  $result=inventory_sync_health($args[0],$args[1],$now);
  if($result['status']!==$status||$result['age_minutes']!==$age){
     fwrite(STDERR,"FAIL integration health {$i}: ".json_encode($result)."\n");exit(1);
  }
  echo "PASS integration health {$status}\n";
}
