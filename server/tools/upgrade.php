<?php
// Upgrade 0.3.x database to 0.4; only CLI, only after full backup.
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(403);exit('Solo CLI');}
require __DIR__.'/../private/bootstrap.php';
if(!installed())die("Instala primero el servidor desde install.php.\n");
$do=in_array('--apply',$argv,true);
try{
 $fields=db_all('SHOW COLUMNS FROM users');$names=array_column($fields,'Field');
 $idx=db_all('SHOW INDEX FROM users');$keys=array_unique(array_column($idx,'Key_name'));
 $changes=[];
 if(!in_array('session_epoch',$names,true))$changes[]='ALTER TABLE users ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0 AFTER enabled';
 foreach(['uniq_oauth_google'=>'oauth_google','uniq_oauth_microsoft'=>'oauth_microsoft'] as $index=>$column){if(!in_array($index,$keys,true))$changes[]="ALTER TABLE users ADD UNIQUE KEY {$index}({$column})";}
 $pubIdx=db_all('SHOW INDEX FROM publications');if(!in_array('uniq_pub_playlist_version',array_column($pubIdx,'Key_name'),true))$changes[]='ALTER TABLE publications ADD UNIQUE KEY uniq_pub_playlist_version(playlist_id,version)';
 $rows=db_all('SHOW TABLES');$tables=array_map(fn($r)=>(string)array_values($r)[0],$rows);
 $newSql=file_get_contents(__DIR__.'/../sql/upgrade-0.6-to-0.7.mysql.sql');
 $newSql=preg_replace('/^--.*$/m','',$newSql);
 foreach(explode(';',$newSql) as $sql){$sql=trim($sql);if(preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/',$sql,$m)&&!in_array($m[1],$tables,true))$changes[]=$sql;}
 if(!$changes){echo "Base actualizada: no hay cambios pendientes.\n";exit(0);}
 if(!$do){echo "Cambios pendientes (respalda antes):\n".implode("\n",$changes)."\nEjecuta: php tools/upgrade.php --apply\n";exit(2);}
 foreach($changes as $sql){db()->exec($sql);echo "APLICADO: {$sql}\n";}
 echo "Actualización 0.7 completada.\n";
}catch(Throwable $e){fwrite(STDERR,"ERROR DE MIGRACIÓN: ".$e->getMessage()."\n");exit(1);}
