<?php
declare(strict_types=1);
/**
 * Read-only aaPanel readiness gate.
 * php tools/production-preflight.php [--json] [--require-inventory] [--require-device]
 * Never prints credentials; no database mutations. Physical device and restore testing remain manual.
 */
if (PHP_SAPI!=='cli') {http_response_code(403);exit('Solo CLI');}
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/integration-health.php';
$want=static fn(string $arg):bool=>in_array($arg,$argv,true);
$checks=[];
$record=static function(string $name,bool $ok,string $severity,string $hint)use(&$checks):void{
 $checks[]=['name'=>$name,'status'=>$ok?'ok':$severity,'detail'=>$hint];
};
$record('PHP 8.2+',PHP_VERSION_ID>=80200,'blocker',PHP_VERSION);
foreach(['pdo_mysql','openssl','sodium','fileinfo'] as $ext) $record('Extensión '.$ext,extension_loaded($ext),'blocker','Actívala en aaPanel si falta');
foreach(['curl','zip'] as $ext) $record('Extensión '.$ext,extension_loaded($ext),'warning','Necesaria para funciones opcionales de nube/respaldos');
$record('Instalación',installed(),'blocker','Completa /install.php con una base MySQL independiente');
$mediaDir=__DIR__.'/../storage';
$record('Archivos escribibles',is_writable($mediaDir),'blocker','Revisa los permisos de server/storage');
$free=@disk_free_space($mediaDir);
$record('Espacio en disco',is_numeric($free)&&$free>=512*1024*1024,'warning','Para videos y clientes usa S3/CDN');
$record('Raíz web',false,'warning','Verifica manualmente que Document Root sea server/public');
if(installed()){
 try{
  $cfg=config();
  $record('HTTPS',str_starts_with((string)($cfg['base_url']??''),'https://'),'blocker','Se requiere certificado y dirección HTTPS');
  $record('Secreto del servidor',preg_match('/^[a-f0-9]{64}$/i',(string)($cfg['secret']??''))===1,'blocker','Comprueba la clave aleatoria interna');
  $mysql=db();
  $record('MySQL responde',!!$mysql->query('SELECT VERSION()')->fetchColumn(),'blocker','Comprueba credenciales y conectividad');
  foreach(['organizations','users','media','playlists','screens','publications','programs','catalog_products','integrations','device_status'] as $tbl){
    $q=$mysql->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$tbl]);
    $record('Tabla '.$tbl,(int)$q->fetchColumn()===1,'blocker','Ejecuta php tools/upgrade.php --apply si está pendiente');
  }
  $integrations=db_all('SELECT name,enabled,last_sync_at FROM integrations');
  $active=array_values(array_filter($integrations,static fn($x)=>(int)$x['enabled']===1));
  $record('Inventario Pro configurado',count($active)>0,$want('--require-inventory')?'blocker':'warning','Configura el exportador público firmado del Inventario Pro real');
  foreach($active as $i){
    $h=inventory_sync_health($i['last_sync_at'],true);
    $record('Conector '.$i['name'],$h['status']==='recent',$want('--require-inventory')?'blocker':'warning',$h['label']);
  }
  $screen=db_one('SELECT COUNT(*) c FROM screens WHERE org_id IS NOT NULL AND paired_at IS NOT NULL AND last_seen_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 35 SECOND)');
  $record('Pantalla conectada',(int)($screen['c']??0)>0,$want('--require-device')?'blocker':'warning','Vincula un reproductor y confirma publicación y caché');
  $backups=glob($mediaDir.'/backups/sector-sign-*.ssbackup')?:[];
  $record('Respaldo local',count($backups)>0,'warning','Configura respaldo externo y comprueba una restauración');
  $record('Almacenamiento externo',($cfg['storage']['driver']??'local')!=='local','warning','Recomendado S3/CDN para múltiples pantallas');
  $record('Pruebas H96 y voz',false,'warning','Requieren dispositivo físico: video, narración y recuperación offline');
 }catch(Throwable $e){
  $record('MySQL / esquema',false,'blocker','Comprueba host, credenciales y migraciones; no se muestran secretos');
 }
}
$blockers=count(array_filter($checks,static fn($v)=>$v['status']==='blocker'));
$warnings=count(array_filter($checks,static fn($v)=>$v['status']==='warning'));
if($want('--json')){
 echo json_encode(['version'=>release_info()['version'],'ready_without_blockers'=>$blockers===0,'blockers'=>$blockers,'warnings'=>$warnings,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
}else{
 foreach($checks as $c)echo '['.strtoupper($c['status']).'] '.$c['name'].' — '.$c['detail']."\n";
 echo "RESULTADO: {$blockers} bloqueos, {$warnings} advertencias. Faltan pruebas en equipos reales.\n";
}
exit($blockers?1:0);
