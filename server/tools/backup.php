<?php
declare(strict_types=1);
/** CLI: php tools/backup.php [--ftps|--s3|--verify=/path/file.ssbackup|--extract=/path/file.ssbackup --out=/empty/directory]. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
require __DIR__.'/../private/bootstrap.php';
$cfg=config();
if(!class_exists('ZipArchive')||!extension_loaded('sodium'))die("Requiere extensiones PHP zip y sodium.\n");
$key=hex2bin((string)($cfg['backup_key']??''));if(strlen((string)$key)!==32)die("backup_key inválida en private/config.php.\n");
require_once __DIR__.'/../private/backup-crypto.php';
function sqlDump(string $target):void{
  $pdo=db();$file=fopen($target,'wb');fwrite($file,"-- Sector Sign MySQL backup generated ".gmdate(DATE_ATOM)."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
  $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  $pdo->beginTransaction(); // InnoDB REPEATABLE READ: all rows share one consistent snapshot.
  $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
  $omit=['web_sessions','oauth_states','login_attempts','integration_nonces','auth_tokens'];
  foreach($tables as $entry){$t=(string)$entry[0];if(!preg_match('/^[a-zA-Z0-9_]+$/',$t)||in_array($t,$omit,true))continue;
    $create=$pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM);
    fwrite($file,"\nDROP TABLE IF EXISTS `{$t}`;\n".$create[1].";\n");
    $rows=$pdo->query('SELECT * FROM `'.$t.'`');
    while($r=$rows->fetch(PDO::FETCH_ASSOC)){
       $values=[];foreach($r as $val)$values[]=$val===null?'NULL':$pdo->quote((string)$val);
       fwrite($file,'INSERT INTO `'.$t.'` VALUES('.implode(',',$values).");\n");
    }
    $rows->closeCursor();
  }
  $pdo->commit();fwrite($file,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($file);
}
function validateZip(string $zipPath):array{
  $zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('ZIP corrupto');
  $entries=[];for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$name=$s['name'];if(str_contains($name,'../')||str_starts_with($name,'/')||str_contains($name,"\0"))throw new RuntimeException('Nombre inseguro en ZIP');$entries[]=$name;}
  $zip->close();return $entries;
}
function maybeOption(string $prefix,array $argv):?string{foreach($argv as $a)if(str_starts_with($a,$prefix.'='))return substr($a,strlen($prefix)+1);return null;}
try{
 $verify=maybeOption('--verify',$argv);$extract=maybeOption('--extract',$argv);
 if($verify||$extract){
  $src=$verify?:$extract;$tmp=tempnam(sys_get_temp_dir(),'ssrest_');
  try{decryptArchive($src,$tmp,$key);$list=validateZip($tmp);echo 'Copia auténtica. Archivos: '.count($list)."\n";
    if($extract){$out=maybeOption('--out',$argv);if(!$out||!is_dir($out)||count(array_diff(scandir($out),['.','..']))>0)throw new RuntimeException('--out debe apuntar a una carpeta existente y VACÍA');$zip=new ZipArchive();$zip->open($tmp);$zip->extractTo($out);$zip->close();echo 'Extraído en '.$out." (NO se importó MySQL automáticamente).\n";}
  }finally{@unlink($tmp);}exit(0);
 }
 $store=__DIR__.'/../storage/backups';if(!is_dir($store))mkdir($store,0700,true);
 $tmp=tempnam(sys_get_temp_dir(),'ssbd_');sqlDump($tmp);
 $zipPath=tempnam(sys_get_temp_dir(),'sszip_');$zip=new ZipArchive();$zip->open($zipPath,ZipArchive::OVERWRITE);
 $zip->addFile($tmp,'database.sql');$cfgFile=__DIR__.'/../private/config.php';$zip->addFile($cfgFile,'private/config.php');
 $root=dirname(__DIR__).'/storage/';
 foreach(['media','captures'] as $part){$dir=$root.$part;if(!is_dir($dir))continue;$iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($iter as $entry)if($entry->isFile())$zip->addFile($entry->getPathname(),$part.'/'.substr($entry->getPathname(),strlen($dir)+1));}
 $zip->addFromString('INSTRUCCIONES.txt',"Copia cifrada de Sector Sign. Recupera los archivos en una instalación aislada y usa database.sql en MySQL vacío. Revisa private/config.php antes de usarla. La clave externa es obligatoria.\n");
 $zip->close();@unlink($tmp);
 $name='sector-sign-'.gmdate('Ymd-His').'.ssbackup';$target=$store.'/'.$name;encryptArchive($zipPath,$target,$key);@unlink($zipPath);
 $hash=hash_file('sha256',$target);echo "Copia cifrada: {$target}\nSHA256: {$hash}\n";
 if(in_array('--ftps',$argv,true)){
   $ftp=$cfg['backup']['ftps']??[];
   if(empty($ftp['host'])||empty($ftp['user'])||empty($ftp['pass'])||!function_exists('ftp_ssl_connect'))throw new RuntimeException('FTPS no configurado. Añade backup.ftps y activa extensión ftp.');
   $conn=ftp_ssl_connect($ftp['host'],(int)($ftp['port']??21),20);if(!$conn||!ftp_login($conn,$ftp['user'],$ftp['pass']))throw new RuntimeException('No se pudo conectar a FTPS');ftp_pasv($conn,true);
   $directory=rtrim((string)($ftp['directory']??'/'),'/');$remote=$directory.'/'.$name;
   if(!ftp_put($conn,$remote,$target,FTP_BINARY))throw new RuntimeException('No se pudo copiar al servidor FTPS');ftp_close($conn);echo "Guardado por FTPS: {$remote}\n";
 }
 if(in_array('--s3',$argv,true)){
   $s3=$cfg['backup']['s3']??($cfg['storage']??[]);
   if(($s3['driver']??'s3')!=='s3'||empty($s3['bucket']))throw new RuntimeException('S3 no configurado');
   s3_put($s3,'sector-sign/backups/'.$name,$target,'application/octet-stream');echo "Guardado en S3: {$name}\n";
 }
 $keep=max(1,min(40,(int)($cfg['backup']['keep']??3)));
 $items=glob($store.'/sector-sign-*.ssbackup')?:[];rsort($items,SORT_STRING);foreach(array_slice($items,$keep) as $old)@unlink($old);
}catch(Throwable $e){fwrite(STDERR,'Error de respaldo: '.$e->getMessage()."\n");exit(1);}
