<?php
declare(strict_types=1);
/** Transactional MySQL integration smoke test. Requires a disposable DB ending in _qa.
 * Does not create tables or modify durable application data; always rolls back.
 * Run: php tests/mysql-smoke.php --run  (after configuring a separate QA installation)
 */
if (PHP_SAPI !== 'cli') {http_response_code(403); exit('Solo CLI');}
if (!in_array('--run', $argv, true)) {fwrite(STDERR, "Uso: php tests/mysql-smoke.php --run (solo DB cuyo nombre acaba en _qa)\n"); exit(2);}
require __DIR__.'/../private/bootstrap.php';
if (!installed()) {fwrite(STDERR, "Instala antes una copia aislada para QA\n"); exit(2);}
$cfg=config();
$dbname=(string)($cfg['db_name']??'');
if (!preg_match('/_qa$/i',$dbname)) {fwrite(STDERR,"PROTECCIÓN: base de datos '$dbname' no acaba en _qa. NO SE EJECUTÓ.\n"); exit(2);}
function ensureTest(bool $value,string $message):void {if (!$value)throw new RuntimeException('FAIL: '.$message); echo 'PASS: '.$message."\n";}
$pdo=null;
try {
 $pdo=db();
 $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
 ensureTest($version!=='','Conexión MySQL '.$version);
 $tables=array_map('strval',array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM),0));
 foreach(['organizations','users','media','playlists','playlist_items','screens','publications','web_sessions','auth_tokens','catalog_products'] as $table)ensureTest(in_array($table,$tables,true),'Tabla '.$table);
 $columns=array_column(db_all('SHOW COLUMNS FROM users'),'Field');
 ensureTest(in_array('session_epoch',$columns,true),'Migración 0.4 aplicada');
 $pdo->beginTransaction();
 // All fixtures use random values, fake e-mails and a rollback. No writes to production permitted.
 $id=pkid();$id2=pkid();$token=hash('sha256',random_bytes(32));
 db_run('INSERT INTO organizations(name,timezone) VALUES(?,?)',['SECTOR SIGN PRUEBA QA','America/Lima']);$org=(int)$pdo->lastInsertId();
 db_run('INSERT INTO users(org_id,name,email,password_hash,role) VALUES(?,?,?,?,?)',[$org,'QA','qa-'.$id.'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'admin']);$user=(int)$pdo->lastInsertId();
 db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256) VALUES(?,?,?,? ,"image/png","image",?,?)',[$id,$org,'Imagen de prueba','qa-'.$id.'.png',10,hash('sha256','fake')]);
 db_run('INSERT INTO playlists(org_id,name) VALUES(?,?)',[$org,'Lista QA']);$list=(int)$pdo->lastInsertId();
 db_run('INSERT INTO playlist_items(playlist_id,media_id,position,duration_sec,fit,transition,schedule_json) VALUES(?,?,0,7,"contain","fade",?)',[$list,$id,json_encode(normalize_schedule(['days'=>[0,6],'start'=>'22:00','end'=>'02:00']))]);
 $items=build_manifest($org,$list);
 ensureTest(count($items)===1&&$items[0]['id']===$id&&$items[0]['duration']===7,'Lista produce manifiesto íntegro');
 db_run('INSERT INTO screens(id,org_id,name,token_hash,pair_code_hash,pair_expires_at,paired_at) VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),UTC_TIMESTAMP())',[$id2,$org,'TV QA',$token,hash('sha256',random_bytes(16))]);
 db_run('INSERT INTO publications(org_id,playlist_id,version,manifest_json,created_by) VALUES(?,?,1,?,?)',[$org,$list,json_encode(['name'=>'Lista QA','items'=>$items]),$user]);$pub=(int)$pdo->lastInsertId();
 db_run('UPDATE screens SET publication_id=? WHERE id=? AND org_id=?',[$pub,$id2,$org]);
 $screen=db_one('SELECT s.publication_id,p.version FROM screens s JOIN publications p ON p.id=s.publication_id WHERE s.id=? AND s.org_id=?',[$id2,$org]);
 ensureTest((int)$screen['publication_id']===$pub&&(int)$screen['version']===1,'Publicación asignada a pantalla');
 // Check simultaneous version protection inside the current transaction.
 $uniqueRejected=false;
 try {db_run('INSERT INTO publications(org_id,playlist_id,version,manifest_json,created_by) VALUES(?,?,1,?,?)',[$org,$list,'{}',$user]);}
 catch(PDOException $e){$uniqueRejected=true;}
 ensureTest($uniqueRejected,'MySQL rechaza versión de publicación duplicada');
 $pdo->rollBack();
 ensureTest(db_one('SELECT id FROM organizations WHERE id=?',[$org])===null,'Rollback elimina fixtures QA');
 echo "OK: integración transaccional comprobada sin persistir datos.\n";
} catch(Throwable $e) {
 if ($pdo && $pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,'ERROR DE QA: '.$e->getMessage()."\n");exit(1);
}
