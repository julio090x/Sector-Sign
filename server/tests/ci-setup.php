<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SECTOR_SIGN_QA')!=='1')exit("Solo CI explícita
");
$dest=__DIR__.'/../private/config.php';if(is_file($dest))exit("RECHAZADO: ya existe config.php
");
$dsn='mysql:host=127.0.0.1;port=3306;dbname=sector_sign_qa;charset=utf8mb4';$pdo=new PDO($dsn,'root','qa-only-test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="sector_sign_qa"')->fetchColumn()>0)exit("RECHAZADO: base QA no vacía
");
$sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/schema.mysql.sql'));foreach(explode(';',$sql) as $part)if(trim($part)!=='')$pdo->exec($part);
$cfg=['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>'sector_sign_qa','db_user'=>'root','db_pass'=>'qa-only-test','base_url'=>'http://127.0.0.1:8787','db_sessions'=>true,'secret'=>bin2hex(random_bytes(32)),'backup_key'=>bin2hex(random_bytes(32)),'storage'=>['driver'=>'local']];file_put_contents($dest,"<?php return ".var_export($cfg,true).";
");chmod($dest,0600);
require __DIR__.'/../private/bootstrap.php';
foreach([1,2] as $i){db_run('INSERT INTO organizations(name) VALUES(?)',['QA '.$i]);$org=(int)db()->lastInsertId();foreach(['admin','viewer'] as $role)db_run('INSERT INTO users(org_id,name,email,password_hash,role) VALUES(?,?,?,?,?)',[$org,'QA '.$role,"$role$i@example.invalid",password_hash('Only-QA-password-2026',PASSWORD_DEFAULT),$role]);}
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=');
$id=pkid();$stored=$id.'.png';file_put_contents(media_dir().'/'.$stored,$png);db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256) VALUES(?,1,"QA.png",?,"image/png","image",?,?)',[$id,$stored,strlen($png),hash('sha256',$png)]);
for($i=1;$i<=3;$i++){db_run('INSERT INTO playlists(org_id,name) VALUES(1,?)',['Lista '.$i]);$list=(int)db()->lastInsertId();db_run('INSERT INTO playlist_items(playlist_id,media_id,position,schedule_json) VALUES(?,?,0,?)',[$list,$id,'{}']);}
db_run('INSERT INTO playlists(org_id,name) VALUES(2,"Lista ajena")');echo "QA configurada en sector_sign_qa. NO producción.
";
