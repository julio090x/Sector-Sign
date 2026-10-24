<?php
declare(strict_types=1);
// Uso: php tools/doctor.php. Read-only, no secrets disclosed.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
require __DIR__.'/../private/bootstrap.php';
$checks=[];
function checkDoctor(array &$checks,string $name,bool $ok,string $hint=''):void{$checks[]=[$name,$ok,$hint];}
checkDoctor($checks,'PHP 8.2+',PHP_VERSION_ID>=80200,PHP_VERSION);
foreach(['pdo_mysql','fileinfo','openssl','sodium'] as $ext)checkDoctor($checks,$ext,extension_loaded($ext),'Obligatorio');
foreach(['curl','zip','ftp'] as $ext)checkDoctor($checks,$ext,extension_loaded($ext),'Recomendado para nube, copias y ZIP');
checkDoctor($checks,'config.php (instalado)',installed(),'El asistente web genera este archivo');
checkDoctor($checks,'private no público',is_dir(__DIR__.'/../private'),'Verifica que tu Document Root sea server/public');
checkDoctor($checks,'storage escribible',is_writable(__DIR__.'/../storage'),'Revisa permisos del usuario PHP en aaPanel');
if(installed()){
 try{$version=db()->query('SELECT VERSION()')->fetchColumn();checkDoctor($checks,'MySQL responde',!!$version,'Versión '.$version);
 $cols=db_all('SHOW COLUMNS FROM users');$names=array_column($cols,'Field');checkDoctor($checks,'Migración 0.4',in_array('session_epoch',$names,true),'Ejecuta php tools/upgrade.php --apply si partes de 0.3');}
 catch(Throwable $e){checkDoctor($checks,'MySQL responde',false,'Comprueba host, usuario y extensión pdo_mysql');}
 $cfg=config();checkDoctor($checks,'HTTPS público',str_starts_with((string)($cfg['base_url']??''),'https://'),'Necesario para PWA; se acepta HTTP en localhost/LAN solo en pruebas');
}
$bad=0;foreach($checks as [$name,$ok,$hint]){if(!$ok)$bad++;echo ($ok?'[OK] ':'[!] ').str_pad($name,27).$hint."\n";}
echo "----------------------\n".(count($checks)-$bad).'/'.count($checks)." controles correctos\n";
exit($bad===0?0:1);
