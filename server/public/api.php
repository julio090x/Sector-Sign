<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/inventory.php';
require __DIR__.'/../private/integration-health.php';
$action=(string)($_GET['action']??$_POST['action']??'');
$method=(string)($_SERVER['REQUEST_METHOD']??'GET');
$readOnlyActions=['health','auth.me','dashboard','media.list','playlists.list','playlist.get','screens.list','groups.list','locations.list','catalog.list','users.list','audit.list','integrations.list','settings.get','backup.info','system.diagnostics','system.release','programs.list','walls.list','player.sync'];
// Security invariant: no state mutation can be performed with GET, including password/login flows.
if($method==='GET'&&!in_array($action,$readOnlyActions,true))fail('Esta acción requiere POST',405);
if(!in_array($method,['GET','POST'],true))fail('Método no permitido',405);
if($method==='POST'&&in_array($action,$readOnlyActions,true))fail('Esta consulta requiere GET',405);
try{
 if(!installed())fail('Instala Sector Sign primero',503);
 // Player requests use their own bearer credential and never admin sessions.
 if(str_starts_with($action,'player.')){require __DIR__.'/../private/player-api.php';player_action($action);exit;}
 if($action==='integration.push'){integration_push();exit;}
 if($action==='health'){try{db()->query('SELECT 1');ok(['version'=>release_info()['version'],'database'=>'ready','server_utc'=>gmdate(DATE_ATOM)]);}catch(Throwable){fail('No se pudo conectar a MySQL',503);}}
 if($action==='auth.me'){
    start_session();$u=current_user();ok(['user'=>$u,'csrf'=>$_SESSION['csrf']??'','language'=>$_SESSION['language']??'es']);
 }
 if($action==='auth.login'){
    csrf();$p=input();$email=strtolower(required($p,'email',190));$password=(string)($p['password']??'');
    $remote=hash('sha256',client_ip().'|'.$email);
    $row=db_one('SELECT COUNT(*) AS c FROM login_attempts WHERE remote_key=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$remote]);
    if((int)($row['c']??0)>=8)fail('Demasiados intentos. Prueba nuevamente en 15 minutos.',429);
    $u=db_one('SELECT * FROM users WHERE email=? AND enabled=1 ORDER BY id LIMIT 1',[$email]);
    if(!$u||!is_string($u['password_hash'])||!password_verify($password,$u['password_hash'])){
        db_run('INSERT INTO login_attempts(remote_key) VALUES(?)',[$remote]);fail('Correo o contraseña incorrectos',401);
    }
    db_run('DELETE FROM login_attempts WHERE remote_key=?',[$remote]);
    if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT))db_run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]);
    session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['session_epoch']=(int)$u['session_epoch'];$_SESSION['csrf']=bin2hex(random_bytes(24));
    audit((int)$u['org_id'],$email,'login');ok(['csrf'=>$_SESSION['csrf']]);
 }
 if($action==='auth.logout'){csrf();$u=current_user();if($u)audit(org_id($u),$u['email'],'logout');$_SESSION=[];session_regenerate_id(true);ok();}
 if($action==='auth.claim_invite'){
    csrf();$p=input();$token=required($p,'token',100);$name=required($p,'name',120);$pw=(string)($p['password']??'');
    if(strlen($pw)<12)fail('La contraseña requiere 12 caracteres');
    $auth=db_one('SELECT * FROM auth_tokens WHERE token_hash=? AND purpose="invite" AND used_at IS NULL AND expires_at>UTC_TIMESTAMP()',[hash('sha256',$token)]);
    if(!$auth)fail('Invitación vencida o inválida',410);
    db()->beginTransaction();try{
        $locked=db_one('SELECT * FROM auth_tokens WHERE id=? FOR UPDATE',[$auth['id']]);if($locked['used_at']!==null){db()->rollBack();fail('Invitación ya utilizada',409);}
        db_run('INSERT INTO users(org_id,name,email,password_hash,role) VALUES(?,?,?,?,?)',[$auth['org_id'],$name,$auth['email'],password_hash($pw,PASSWORD_DEFAULT),$auth['role']]);
        db_run('UPDATE auth_tokens SET used_at=UTC_TIMESTAMP() WHERE id=?',[$auth['id']]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit((int)$auth['org_id'],$auth['email'],'invite_redeemed');ok();
 }
 if($action==='auth.reset_password'){
    csrf();$p=input();$token=required($p,'token',100);$pw=(string)($p['password']??'');
    if(strlen($pw)<12||strlen($pw)>200)fail('La contraseña debe tener entre 12 y 200 caracteres');
    db()->beginTransaction();try{
      $reset=db_one('SELECT * FROM auth_tokens WHERE token_hash=? AND purpose="reset" AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)]);
      if(!$reset){db()->rollBack();fail('El enlace de recuperación venció o ya se utilizó',410);}
      $user=db_one('SELECT id FROM users WHERE org_id=? AND email=? AND enabled=1 FOR UPDATE',[$reset['org_id'],$reset['email']]);
      if(!$user){db()->rollBack();fail('Usuario desactivado o desconocido',410);}
      db_run('UPDATE users SET password_hash=?,session_epoch=session_epoch+1 WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$user['id']]);
      db_run('UPDATE auth_tokens SET used_at=UTC_TIMESTAMP() WHERE id=?',[$reset['id']]);
      db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit((int)$reset['org_id'],'system','auth.reset','user',(string)$user['id']);ok(['notice'=>'Contraseña actualizada. Inicia sesión.']);
 }
 $read=['dashboard','media.list','playlists.list','playlist.get','screens.list','groups.list','locations.list','catalog.list','users.list','audit.list','integrations.list','settings.get','backup.info','system.diagnostics','system.release','programs.list','walls.list'];
 $u=auth(in_array($action,$read,true)?'viewer':(str_starts_with($action,'users.')||str_starts_with($action,'integrations.')||str_starts_with($action,'settings.')||str_starts_with($action,'backup.')?'admin':'editor'));
 $org=org_id($u);
 if($_SERVER['REQUEST_METHOD']!=='GET')csrf();
 $p=input();
 if(str_starts_with($action,'programs.')){require __DIR__.'/../private/programs.php';program_action($action,$p,$u);}
 if(str_starts_with($action,'walls.')){require __DIR__.'/../private/walls.php';wall_action($action,$p,$u);}
 switch($action){
 case 'dashboard':
    $screen=db_one('SELECT COUNT(*) AS total,SUM(last_seen_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 35 SECOND)) AS online,SUM(paired_at IS NULL) AS pending FROM screens WHERE org_id=?',[$org]);
    $med=db_one('SELECT COUNT(*) AS c FROM media WHERE org_id=? AND deleted_at IS NULL',[$org]);
    $lists=db_one('SELECT COUNT(*) AS c FROM playlists WHERE org_id=?',[$org]);
    $last=db_all('SELECT action,actor,created_at,object_type FROM audit_events WHERE org_id=? ORDER BY id DESC LIMIT 6',[$org]);
    ok(['stats'=>['screens'=>(int)$screen['total'],'online'=>(int)$screen['online'],'media'=>(int)$med['c'],'playlists'=>(int)$lists['c']],'activity'=>$last,'storage_free_bytes'=>@disk_free_space(SS_ROOT.'/storage')?:0]);
 case 'media.list':
    ok(['items'=>db_all('SELECT id,original_name AS name,mime,kind,bytes,sha256,stream_url,created_at FROM media WHERE org_id=? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1000',[$org])]);
 case 'media.upload':
    if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))fail('Selecciona un archivo válido');
    $file=$_FILES['file'];$max=150*1024*1024;
    if((int)$file['error']!==UPLOAD_ERR_OK)fail('No se pudo recibir el archivo. Revisa post_max_size/upload_max_filesize.');
    if($file['size']>$max||$file['size']<=0)fail('Archivo demasiado grande (máximo 150 MB)');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$kind=allow_mime($mime);
    if(!$kind)fail('Tipo no permitido: usa JPG, PNG, WebP, GIF, MP4, WebM, MP3, WAV, OGG o M4A');
    if($kind==='image' && !@getimagesize($file['tmp_name']))fail('La imagen está dañada o no es válida');
    if($kind==='audio' && $file['size']>50*1024*1024)fail('El audio no puede superar 50 MB',413);
    $name=basename((string)$file['name']);$name=mb_substr(preg_replace('/[\x00-\x1F\x7F]/u','',$name)?:'archivo',0,190);
    $id=pkid();$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','video/mp4'=>'mp4','video/webm'=>'webm','audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/wave'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/aac'=>'aac'][$mime];
    $stored=$id.'.'.$ext;$destination=media_dir().'/'.$stored;
    if(!move_uploaded_file($file['tmp_name'],$destination))fail('No se pudo guardar el archivo',500);
    @chmod($destination,0640);
    try {
      $storage=storage_save($destination,$stored,$mime);
      db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256,backend,object_key) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$org,$name,$stored,$mime,$kind,(int)$file['size'],hash_file('sha256',$destination),$storage['backend'],$storage['object_key']??null]);
      if($storage['backend']!=='local')@unlink($destination);
      audit($org,$u['email'],'media.upload','media',$id,['name'=>$name]);ok(['id'=>$id]);
    }catch(Throwable $e){@unlink($destination);throw $e;}
 case 'media.intro':
    require_once __DIR__.'/../private/creatives.php';
    try{$svg=make_intro_svg($p);}catch(InvalidArgumentException $e){fail($e->getMessage(),422);}
    $id=pkid();$stored=$id.'.svg';$path=media_dir().'/'.$stored;
    if(file_put_contents($path,$svg,LOCK_EX)===false)fail('No se pudo preparar la introducción',500);
    try{$storage=storage_save($path,$stored,'image/svg+xml');
      db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256,backend,object_key) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$org,mb_substr('Intro · '.field($p,'title',90),0,190),$stored,'image/svg+xml','image',strlen($svg),hash('sha256',$svg),$storage['backend'],$storage['object_key']??null]);
      if($storage['backend']!=='local')@unlink($path);
      audit($org,$u['email'],'media.intro','media',$id);ok(['id'=>$id]);
    }catch(Throwable $e){@unlink($path);throw $e;}
 case 'media.stream':
    require_once __DIR__.'/../private/creatives.php';
    $name=required($p,'name',190);$url=required($p,'url',1500);
    if(!validate_stream_url($url))fail('Utiliza una URL pública HTTPS terminada en .mp4 o .m3u8. No admite direcciones privadas.',422);
    $path=(string)parse_url($url,PHP_URL_PATH);
    $mime=str_ends_with(strtolower($path),'.m3u8')?'application/vnd.apple.mpegurl':'video/mp4';
    $id=pkid();$stored=$id.'.url';
    db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256,stream_url) VALUES(?,?,?,?,? ,"stream",0,?,?)',[$id,$org,$name,$stored,$mime,hash('sha256','stream:'.$url),$url]);
    audit($org,$u['email'],'media.stream','media',$id,['format'=>$mime]);ok(['id'=>$id,'online_only'=>true]);
 case 'media.delete':
    $id=required($p,'id',32);$used=db_one('SELECT COUNT(*) AS c FROM playlist_items pi JOIN playlists pl ON pl.id=pi.playlist_id WHERE pi.media_id=? AND pl.org_id=?',[$id,$org]);
    if((int)$used['c']>0)fail('Retira el archivo de todas las listas antes de eliminarlo',409);
    $audioUses=db_one('SELECT COUNT(*) c FROM playlist_items pi JOIN playlists pl ON pl.id=pi.playlist_id WHERE pl.org_id=? AND pi.audio_json LIKE ?',[$org,'%"'.$id.'"%']);
    $musicUses=db_one('SELECT COUNT(*) c FROM playlists WHERE org_id=? AND soundtrack_json LIKE ?',[$org,'%"'.$id.'"%']);
    if((int)($audioUses['c']??0)+(int)($musicUses['c']??0)>0)fail('El audio está asignado a una lista. Quítalo antes de eliminarlo.',409);
    $historical=db_one('SELECT COUNT(*) c FROM publications WHERE org_id=? AND manifest_json LIKE ?',[$org,'%"id":"'.$id.'"%']);
    if((int)($historical['c']??0)>0)fail('Este archivo pertenece a una publicación histórica; no puede borrarse mientras los reproductores puedan necesitarla.',409);
    db_run('UPDATE media SET deleted_at=UTC_TIMESTAMP() WHERE id=? AND org_id=?',[$id,$org]);audit($org,$u['email'],'media.delete','media',$id);ok();
 case 'playlists.list':
    ok(['items'=>db_all('SELECT id,name,description,revision,updated_at,(SELECT COUNT(*) FROM playlist_items WHERE playlist_id=playlists.id) AS items FROM playlists WHERE org_id=? ORDER BY updated_at DESC',[$org])]);
 case 'playlists.create':
    $name=required($p,'name',160);db_run('INSERT INTO playlists(org_id,name) VALUES(?,?)',[$org,$name]);$id=(int)db()->lastInsertId();audit($org,$u['email'],'playlist.create','playlist',(string)$id);ok(['id'=>$id,'revision'=>0]);
 case 'playlists.rename':
    $id=(int)($p['id']??0);$name=required($p,'name',160);if(!db_run('UPDATE playlists SET name=? WHERE id=? AND org_id=?',[$name,$id,$org]))fail('Lista no encontrada',404);ok();
 case 'playlist.get':
    $id=(int)($_GET['id']??0);$row=db_one('SELECT * FROM playlists WHERE id=? AND org_id=?',[$id,$org]);if(!$row)fail('Lista no encontrada',404);
    ok(['playlist'=>$row,'items'=>build_manifest($org,$id),'soundtrack'=>json_decode((string)($row['soundtrack_json']??''),true)?:[]]);
 case 'playlist.save':
    $id=(int)($p['id']??0);$items=$p['items']??[];if(!is_array($items))fail('Lista inválida');
    $saved=write_playlist($org,$id,$items,(int)($p['revision']??0),$p['soundtrack']??[]);audit($org,$u['email'],'playlist.save','playlist',(string)$id);ok($saved);
 case 'playlists.duplicate':
    $id=(int)($p['id']??0);$base=db_one('SELECT * FROM playlists WHERE id=? AND org_id=?',[$id,$org]);if(!$base)fail('Lista no encontrada',404);
    db()->beginTransaction();try{
      db_run('INSERT INTO playlists(org_id,name,description,soundtrack_json) VALUES(?,?,?,?)',[$org,mb_substr($base['name'].' (copia)',0,160),$base['description'],$base['soundtrack_json']]);$new=(int)db()->lastInsertId();
      db_run('INSERT INTO playlist_items(playlist_id,media_id,position,duration_sec,fit,transition,schedule_json,audio_json) SELECT ?,media_id,position,duration_sec,fit,transition,schedule_json,audio_json FROM playlist_items WHERE playlist_id=?',[$new,$id]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit($org,$u['email'],'playlist.duplicate','playlist',(string)$new);ok(['id'=>$new]);
 case 'playlists.delete':
    $id=(int)($p['id']??0);$pub=db_one('SELECT COUNT(*) AS c FROM publications WHERE playlist_id=? AND org_id=?',[$id,$org]);if((int)$pub['c']>0)fail('Esta lista tiene publicaciones históricas; archívala para conservar auditoría',409);
    db()->beginTransaction();db_run('DELETE FROM playlist_items WHERE playlist_id=? AND playlist_id IN (SELECT id FROM playlists WHERE org_id=?)',[$id,$org]);db_run('DELETE FROM playlists WHERE id=? AND org_id=?',[$id,$org]);db()->commit();ok();
 case 'playlists.transfer_item':
    $source=(int)($p['source_id']??0);$target=(int)($p['target_id']??0);
    $index=(int)($p['index']??-1);$mode=(string)($p['mode']??'copy');
    if($source<1||$target<1||$source===$target||$index<0||!in_array($mode,['copy','move'],true))fail('Origen, destino o acción inválida',422);
    db()->beginTransaction();try{
      // Consistent lock order reduces deadlocks when two editors exchange assets.
      $ordered=[$source,$target];sort($ordered,SORT_NUMERIC);$locked=[];
      foreach($ordered as $key){$row=db_one('SELECT id,revision FROM playlists WHERE id=? AND org_id=? FOR UPDATE',[$key,$org]);if(!$row)throw new InvalidArgumentException('Lista de origen o destino no disponible');$locked[$key]=$row;}
      if((int)$locked[$source]['revision']!==(int)($p['source_revision']??-1)||(int)$locked[$target]['revision']!==(int)($p['target_revision']??-1))throw new RuntimeException('Las listas cambiaron en otra sesión. Vuelve a abrirlas.');
      $item=db_one('SELECT * FROM playlist_items WHERE playlist_id=? AND position=? FOR UPDATE',[$source,$index]);if(!$item)throw new InvalidArgumentException('El anuncio ya no está en esta posición');
      $n=(int)(db_one('SELECT COUNT(*) n FROM playlist_items WHERE playlist_id=?',[$target])['n']??0);if($n>=250)throw new InvalidArgumentException('La lista destino alcanzó 250 anuncios');
      db_run('INSERT INTO playlist_items(playlist_id,media_id,position,duration_sec,fit,transition,schedule_json,audio_json) VALUES(?,?,?,?,?,?,?,?)',[$target,$item['media_id'],$n,$item['duration_sec'],$item['fit'],$item['transition'],$item['schedule_json'],$item['audio_json']]);
      db_run('UPDATE playlists SET revision=revision+1 WHERE id=?',[$target]);
      if($mode==='move'){
       db_run('DELETE FROM playlist_items WHERE id=? AND playlist_id=?',[$item['id'],$source]);
       db_run('UPDATE playlist_items SET position=position-1 WHERE playlist_id=? AND position>?',[$source,$index]);
       db_run('UPDATE playlists SET revision=revision+1 WHERE id=?',[$source]);
      }
      db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof InvalidArgumentException)fail($e->getMessage(),422);if($e instanceof RuntimeException)fail($e->getMessage(),409);throw $e;}
    audit($org,$u['email'],'playlist.'.$mode,'playlist',(string)$target,['from'=>$source,'position'=>$index]);ok(['mode'=>$mode]);
 case 'playlist.publish':
    $id=(int)($p['id']??0);$targets=$p['screens']??[];
    if(!is_array($targets)||count($targets)<1||count($targets)>500)fail('Selecciona entre 1 y 500 pantallas');
    db()->beginTransaction();try{
       // Serialize publish and playlist editing on the same row. No duplicate versions under concurrent requests.
       $row=db_one('SELECT name FROM playlists WHERE id=? AND org_id=? FOR UPDATE',[$id,$org]);
       if(!$row){db()->rollBack();fail('Lista no encontrada',404);}
       $manifest=build_manifest($org,$id);if(!$manifest){db()->rollBack();fail('La lista no contiene anuncios válidos');}
       $version=(int)(db_one('SELECT MAX(version) AS n FROM publications WHERE playlist_id=? AND org_id=?',[$id,$org])['n']??0)+1;
       db_run('INSERT INTO publications(org_id,playlist_id,version,manifest_json,created_by) VALUES(?,?,?,?,?)',[$org,$id,$version,json_encode(['name'=>$row['name'],'items'=>$manifest,'soundtrack'=>playlist_soundtrack($org,$id),'audio_assets'=>audio_assets($org,$manifest,playlist_soundtrack($org,$id))],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$u['id']]);$pub=(int)db()->lastInsertId();
       $count=0;foreach(array_unique(array_map('strval',$targets)) as $screen){
          if(!preg_match('/^[a-f0-9]{32}$/',$screen)||!db_one('SELECT id FROM screens WHERE id=? AND org_id=? AND paired_at IS NOT NULL',[$screen,$org]))throw new RuntimeException('Una de las pantallas seleccionadas no está disponible');
          $count+=db_run('UPDATE screens SET publication_id=? WHERE id=? AND org_id=? AND paired_at IS NOT NULL',[$pub,$screen,$org]);
       }
       db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit($org,$u['email'],'playlist.publish','playlist',(string)$id,['version'=>$version,'screens'=>$count]);ok(['publication_id'=>$pub,'version'=>$version,'assigned'=>$count]);
 case 'locations.list':case 'groups.list':
    $table=$action==='locations.list'?'locations':'display_groups';ok(['items'=>db_all("SELECT * FROM {$table} WHERE org_id=? ORDER BY name",[$org])]);
 case 'locations.create':case 'groups.create':
    $table=$action==='locations.create'?'locations':'display_groups';$name=required($p,'name',120);
    if($table==='locations')db_run('INSERT INTO locations(org_id,name,address) VALUES(?,?,?)',[$org,$name,field($p,'address',255)]);
    else db_run('INSERT INTO display_groups(org_id,name) VALUES(?,?)',[$org,$name]);ok(['id'=>(int)db()->lastInsertId()]);
 case 'groups.assign':
    $group=(int)($p['group_id']??0);$targets=$p['screens']??[];
    if(!is_array($targets)||count($targets)>500)fail('Máximo 500 pantallas',422);
    if(!db_one('SELECT id FROM display_groups WHERE id=? AND org_id=?',[$group,$org]))fail('Grupo no encontrado',404);
    foreach($targets as $s)if(!is_string($s)||!preg_match('/^[a-f0-9]{32}$/D',$s)||!db_one('SELECT id FROM screens WHERE id=? AND org_id=? AND paired_at IS NOT NULL',[$s,$org]))fail('Pantalla ajena o inválida',422);
    db()->beginTransaction();try{
       db_run('UPDATE screens SET group_id=NULL WHERE org_id=? AND group_id=?',[$org,$group]);
       foreach(array_unique($targets) as $s)db_run('UPDATE screens SET group_id=? WHERE id=? AND org_id=?',[$group,$s,$org]);
       db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit($org,$u['email'],'group.assign','group',(string)$group,['devices'=>count(array_unique($targets))]);ok(['count'=>count(array_unique($targets))]);
 case 'groups.command':
    $group=(int)($p['group_id']??0);$command=field($p,'command',20);
    if(!in_array($command,['pause','resume','reload','capture'],true))fail('Comando inválido',422);
    if(!db_one('SELECT id FROM display_groups WHERE id=? AND org_id=?',[$group,$org]))fail('Grupo no encontrado',404);
    $members=db_all('SELECT id FROM screens WHERE group_id=? AND org_id=? AND paired_at IS NOT NULL LIMIT 500',[$group,$org]);
    foreach($members as $row)db_run('INSERT INTO screen_commands(screen_id,action,requested_by) VALUES(?,?,?)',[$row['id'],$command,$u['id']]);
    audit($org,$u['email'],'group.command','group',(string)$group,['command'=>$command,'devices'=>count($members)]);ok(['devices'=>count($members)]);
 case 'screens.list':
    ok(['items'=>db_all('SELECT s.id,s.name,s.location_id,s.group_id,s.orientation,s.last_seen_at,s.last_error,s.paired_at,s.publication_id,s.applied_publication_id,s.last_capture_at, d.telemetry_json, d.updated_at AS telemetry_at, l.name AS location_name,g.name AS group_name, pub.version AS assigned_version, applied.version AS applied_version, (SELECT COUNT(*) FROM screen_commands WHERE screen_id=s.id AND status IN ("queued","delivered")) AS pending_commands FROM screens s LEFT JOIN device_status d ON d.screen_id=s.id LEFT JOIN locations l ON l.id=s.location_id AND l.org_id=s.org_id LEFT JOIN display_groups g ON g.id=s.group_id AND g.org_id=s.org_id LEFT JOIN publications pub ON pub.id=s.publication_id LEFT JOIN publications applied ON applied.id=s.applied_publication_id WHERE s.org_id=? ORDER BY s.name LIMIT 1000',[$org])]);
 case 'screens.pair':
    $code=preg_replace('/\D/','',required($p,'code',20));if(strlen($code)!==8)fail('El código debe tener 8 dígitos');
    $attemptKey=hash('sha256','pair|'.$u['id'].'|'.client_ip());
    $attempts=db_one('SELECT COUNT(*) c FROM login_attempts WHERE remote_key=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$attemptKey]);
    if((int)($attempts['c']??0)>=20)fail('Demasiados intentos de vinculación. Espera 15 minutos.',429);
    db_run('INSERT INTO login_attempts(remote_key) VALUES(?)',[$attemptKey]);
    $hash=hash('sha256',$code);
    $name=field($p,'name',150)?:'Nueva pantalla';$loc=(int)($p['location_id']??0)?:null;$group=(int)($p['group_id']??0)?:null;
    if($loc&&!db_one('SELECT id FROM locations WHERE id=? AND org_id=?',[$loc,$org]))fail('Local no válido');
    if($group&&!db_one('SELECT id FROM display_groups WHERE id=? AND org_id=?',[$group,$org]))fail('Grupo no válido');
    db()->beginTransaction();
    try{
      $s=db_one('SELECT id FROM screens WHERE pair_code_hash=? AND paired_at IS NULL AND pair_expires_at>UTC_TIMESTAMP() FOR UPDATE',[$hash]);
      if(!$s){db()->rollBack();fail('Código vencido, usado o incorrecto',404);}
      $paired=db_run('UPDATE screens SET org_id=?,name=?,location_id=?,group_id=?,paired_at=UTC_TIMESTAMP() WHERE id=? AND org_id IS NULL',[$org,$name,$loc,$group,$s['id']]);
      if(!$paired){db()->rollBack();fail('Este código ya se usó',409);}
      db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit($org,$u['email'],'screen.pair','screen',$s['id']);ok(['id'=>$s['id']]);
 case 'screens.update':
    $id=required($p,'id',32);$name=required($p,'name',150);$orientation=in_array($p['orientation']??'landscape',['landscape','portrait'],true)?$p['orientation']:'landscape';
    $loc=(int)($p['location_id']??0)?:null;$group=(int)($p['group_id']??0)?:null;
    if($loc&&!db_one('SELECT id FROM locations WHERE id=? AND org_id=?',[$loc,$org]))fail('Local inválido');if($group&&!db_one('SELECT id FROM display_groups WHERE id=? AND org_id=?',[$group,$org]))fail('Grupo inválido');
    if(!db_run('UPDATE screens SET name=?,orientation=?,location_id=?,group_id=? WHERE id=? AND org_id=?',[$name,$orientation,$loc,$group,$id,$org]))fail('Pantalla no encontrada',404);
    audit($org,$u['email'],'screen.update','screen',$id);ok();
 case 'screens.command':
    $id=required($p,'id',32);$command=required($p,'command',20);if(!in_array($command,['pause','resume','reload','capture'],true))fail('Comando inválido');
    if(!db_one('SELECT id FROM screens WHERE id=? AND org_id=?',[$id,$org]))fail('Pantalla no encontrada',404);
    db_run('INSERT INTO screen_commands(screen_id,action,requested_by) VALUES(?,?,?)',[$id,$command,$u['id']]);audit($org,$u['email'],'screen.command','screen',$id,['command'=>$command]);ok();
 case 'screens.unpair':
    $id=required($p,'id',32);if(!db_one('SELECT id FROM screens WHERE id=? AND org_id=?',[$id,$org]))fail('No existe',404);
    db_run('DELETE FROM device_status WHERE screen_id=?',[$id]);db_run('DELETE FROM screen_commands WHERE screen_id=?',[$id]);db_run('DELETE FROM screen_capture_blobs WHERE screen_id=?',[$id]);db_run('DELETE FROM screens WHERE id=? AND org_id=?',[$id,$org]);audit($org,$u['email'],'screen.unpair','screen',$id);ok();
 case 'catalog.list':
    ok(['items'=>db_all('SELECT external_id,name,presentation,public_price,min_quantity,promotional,media_id,updated_at FROM catalog_products WHERE org_id=? AND enabled=1 ORDER BY updated_at DESC LIMIT 200',[$org])]);
 case 'integrations.list':
    $connections=db_all('SELECT id,name,key_id,enabled,auto_publish,last_sync_at FROM integrations WHERE org_id=? ORDER BY id',[$org]);
    foreach($connections as &$conn){$conn['sync_health']=inventory_sync_health($conn['last_sync_at'],(bool)$conn['enabled']);}
    unset($conn);
    ok(['items'=>$connections]);
 case 'integrations.create':
    if(!extension_loaded('sodium'))fail('Falta la extensión PHP sodium');
    $name=required($p,'name',120);$key=bin2hex(random_bytes(10));$secret=bin2hex(random_bytes(32));
    $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$k=hash('sha256',config()['secret'],true);
    $encrypted=base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,$k));
    db_run('INSERT INTO integrations(org_id,name,key_id,encrypted_secret) VALUES(?,?,?,?)',[$org,$name,$key,$encrypted]);
    audit($org,$u['email'],'integration.create','integration',(string)db()->lastInsertId());
    ok(['id'=>(int)db()->lastInsertId(),'key_id'=>$key,'secret'=>$secret,'notice'=>'Copia la clave ahora: no volverá a mostrarse.']);
 case 'integrations.update':
    $id=(int)($p['id']??0);db_run('UPDATE integrations SET enabled=?,auto_publish=? WHERE id=? AND org_id=?',[(int)!empty($p['enabled']),(int)!empty($p['auto_publish']),$id,$org]);ok();
 case 'users.list':ok(['items'=>db_all('SELECT id,name,email,role,enabled,created_at FROM users WHERE org_id=? ORDER BY id',[$org])]);
 case 'users.invite':
    $email=strtolower(required($p,'email',190));if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Correo inválido');
    if(db_one('SELECT id FROM users WHERE email=? AND org_id=?',[$email,$org]))fail('Ya existe una cuenta con ese correo',409);
    $role=in_array($p['role']??'viewer',['viewer','editor','admin'],true)?$p['role']:'viewer';$token=bin2hex(random_bytes(24));
    db_run('INSERT INTO auth_tokens(org_id,email,token_hash,purpose,role,expires_at,created_by) VALUES(?,?,?,"invite",?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),?)',[$org,$email,hash('sha256',$token),$role,$u['id']]);
    audit($org,$u['email'],'users.invite','user',$email);
    ok(['invite_url'=>rtrim(config()['base_url'],'/').'/?invite='.$token,'expires_hours'=>48,'notice'=>'Comparte este enlace por un canal privado. Aún no se envió ningún correo.']);
 case 'users.reset_link':
    $id=(int)($p['id']??0);$target=db_one('SELECT id,email FROM users WHERE id=? AND org_id=? AND enabled=1',[$id,$org]);
    if(!$target)fail('Usuario no encontrado o desactivado',404);
    $token=bin2hex(random_bytes(32));
    db_run('DELETE FROM auth_tokens WHERE org_id=? AND email=? AND purpose="reset" AND used_at IS NULL',[$org,$target['email']]);
    db_run('INSERT INTO auth_tokens(org_id,email,token_hash,purpose,expires_at,created_by) VALUES(?,?,?,"reset",DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?)',[$org,$target['email'],hash('sha256',$token),$u['id']]);
    audit($org,$u['email'],'users.reset_link','user',(string)$id);
    ok(['reset_url'=>rtrim(config()['base_url'],'/').'/?reset='.$token,'expires_minutes'=>60,'notice'=>'Enlace de un solo uso. Compártelo por un canal privado.']);
 case 'users.toggle':
    $id=(int)($p['id']??0);if($id===(int)$u['id'])fail('No puedes desactivar tu propia cuenta');
    db_run('UPDATE users SET enabled=? WHERE id=? AND org_id=?',[(int)!empty($p['enabled']),$id,$org]);audit($org,$u['email'],'user.toggle','user',(string)$id);ok();
 case 'users.password':
    $old=(string)($p['old_password']??'');$new=(string)($p['new_password']??'');
    $current=db_one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
    if(!$current||!password_verify($old,$current['password_hash']))fail('Contraseña actual incorrecta',401);
    if(strlen($new)<12)fail('Usa al menos 12 caracteres');
    db_run('UPDATE users SET password_hash=?,session_epoch=session_epoch+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);unset($_SESSION['user_id'],$_SESSION['session_epoch']);audit($org,$u['email'],'users.password');ok(['notice'=>'Por seguridad, inicia sesión con tu nueva contraseña.']);
 case 'audit.list':ok(['items'=>db_all('SELECT actor,action,object_type,object_id,created_at FROM audit_events WHERE org_id=? ORDER BY id DESC LIMIT 100',[$org])]);
 case 'settings.get':
    $o=db_one('SELECT name,timezone FROM organizations WHERE id=?',[$org]);
    $cfg=config();$storage=$cfg['storage']??['driver'=>'local'];
    ok(['organization'=>$o,'storage'=>['driver'=>$storage['driver']??'local','bucket'=>$storage['bucket']??'','region'=>$storage['region']??'','endpoint'=>$storage['endpoint']??'','cdn_url'=>$storage['cdn_url']??'','host'=>$storage['host']??'','port'=>$storage['port']??21,'directory'=>$storage['directory']??'','public_base_url'=>$storage['public_base_url']??'','configured'=>!empty($storage['access_key'])||!empty($storage['username'])],'oauth'=>['google'=>!empty($cfg['oauth']['google']['client_id']),'microsoft'=>!empty($cfg['oauth']['microsoft']['client_id'])],'base_url'=>$cfg['base_url']]);
 case 'settings.organization':
    $name=required($p,'name',150);$tz=field($p,'timezone',80)?:'America/Lima';if(!in_array($tz,timezone_identifiers_list(),true))fail('Zona horaria inválida');
    db_run('UPDATE organizations SET name=?,timezone=? WHERE id=?',[$name,$tz,$org]);ok();
 case 'settings.storage':
    $driver=in_array($p['driver']??'local',['local','s3','ftps'],true)?$p['driver']:'local';$storage=['driver'=>$driver];
    if($driver==='s3'){
      foreach(['bucket','region','endpoint'] as $key)$storage[$key]=required($p,$key,250);
      foreach(['access_key','secret_key'] as $key)$storage[$key]=field($p,$key,250);
      if($storage['access_key']==='' && ($cfgOld=config()['storage']??[]) && ($cfgOld['driver']??'')==='s3'){$storage['access_key']=$cfgOld['access_key']??'';$storage['secret_key']=$cfgOld['secret_key']??'';}
      if($storage['access_key']===''||$storage['secret_key']==='')fail('Indica Access Key y Secret Key de S3');
      $storage['cdn_url']=field($p,'cdn_url',250);
      if(!preg_match('/^[a-z0-9][a-z0-9.-]{2,62}$/',$storage['bucket'])||!validate_public_url($storage['endpoint'])||parse_url($storage['endpoint'],PHP_URL_SCHEME)!=='https')fail('Bucket o endpoint S3 inválido (requiere HTTPS)');
      if($storage['cdn_url']!==''&&(!validate_public_url($storage['cdn_url'])||parse_url($storage['cdn_url'],PHP_URL_SCHEME)!=='https'))fail('La CDN debe usar HTTPS');
    }
    if($driver==='ftps'){
      foreach(['host','directory','public_base_url'] as $key)$storage[$key]=required($p,$key,250);
      $storage['port']=(int)($p['port']??21);
      foreach(['username','password'] as $key)$storage[$key]=field($p,$key,250);
      $older=config()['storage']??[];
      if($storage['username']===''&&($older['driver']??'')==='ftps'){$storage['username']=$older['username']??'';$storage['password']=$older['password']??'';}
      if(!preg_match('/^[a-zA-Z0-9.-]+$/',$storage['host'])||!validate_public_url($storage['public_base_url'])||parse_url($storage['public_base_url'],PHP_URL_SCHEME)!=='https')fail('FTPS requiere servidor válido y URL pública HTTPS');
      if(!$storage['username']||!$storage['password'])fail('FTPS necesita usuario y contraseña');
    }
    $cfg=config();
    $old=$cfg['storage']??['driver'=>'local'];
    $existingRemote=(int)(db_one('SELECT COUNT(*) c FROM media WHERE backend<>"local" AND deleted_at IS NULL',[])['c']??0);
    if($existingRemote>0 && $driver!==($old['driver']??'local'))fail('Hay archivos en almacenamiento externo: migra primero antes de cambiar de proveedor.',409);
    if($existingRemote>0 && $driver==='s3' && (($storage['endpoint']??'')!==($old['endpoint']??'')||($storage['bucket']??'')!==($old['bucket']??'')))fail('No cambies endpoint/bucket con anuncios S3 existentes. Migra primero.',409);
    if($existingRemote>0 && $driver==='ftps' && (($storage['host']??'')!==($old['host']??'')||($storage['directory']??'')!==($old['directory']??'')||($storage['public_base_url']??'')!==($old['public_base_url']??'')))fail('No cambies host/ruta FTPS con anuncios existentes. Migra primero.',409);
    if($driver==='s3' && ($storage['access_key']??'')==='' && ($old['driver']??'')==='s3'){$storage['access_key']=$old['access_key']??'';$storage['secret_key']=$old['secret_key']??'';}
    $cfg['storage']=$storage;
    save_private_config($cfg);
    audit($org,$u['email'],'storage.update');ok(['notice'=>'La nueva configuración afecta cargas futuras; los archivos anteriores conservan su backend.']);
 case 'settings.oauth':
    $provider=required($p,'provider',20);if(!in_array($provider,['google','microsoft'],true))fail('Proveedor no reconocido',422);
    $cfg=config();$existing=$cfg['oauth'][$provider]??[];
    if(!empty($p['remove'])){
       $cfg['oauth'][$provider]=['client_id'=>'','client_secret'=>''];
    }else{
       $id=field($p,'client_id',300)?:($existing['client_id']??'');
       $secret=field($p,'client_secret',400)?:($existing['client_secret']??'');
       if(!preg_match('/^[a-zA-Z0-9._~@:+\/-]{10,300}$/',(string)$id)||strlen((string)$secret)<12)fail('Indica Client ID y Client Secret válidos',422);
       $cfg['oauth'][$provider]=['client_id'=>$id,'client_secret'=>$secret];
    }
    save_private_config($cfg);audit($org,$u['email'],'oauth.configure','provider',$provider);
    ok(['notice'=>'Configuración guardada. Registra la URL de devolución exacta en el proveedor.', 'callback'=>rtrim($cfg['base_url'],'/').'/oauth.php?provider='.$provider]);
 case 'settings.backup_policy':
    $keep=max(1,min(30,(int)($p['keep']??7)));
    $cfg=config();$cfg['backup']['keep']=$keep;save_private_config($cfg);
    audit($org,$u['email'],'backup.retention','config','',['keep'=>$keep]);ok(['keep'=>$keep]);
 case 'system.release':
    ok(['release'=>release_info(),'server_utc'=>gmdate(DATE_ATOM)]);
 case 'system.diagnostics':
    $cfg=config();$storageCfg=$cfg['storage']??[];
    $phpDir=dirname(__DIR__).'/storage';
    $tests=[
      ['label'=>'Versión de PHP (8.2+)','pass'=>PHP_VERSION_ID>=80200,'value'=>PHP_VERSION],
      ['label'=>'PDO MySQL','pass'=>extension_loaded('pdo_mysql'),'value'=>extension_loaded('pdo_mysql')?'activo':'inactivo'],
      ['label'=>'Fileinfo y Sodium','pass'=>extension_loaded('fileinfo')&&extension_loaded('sodium'),'value'=>(extension_loaded('fileinfo')&&extension_loaded('sodium'))?'activos':'revisar'],
      ['label'=>'MySQL responde','pass'=>false,'value'=>''],
      ['label'=>'Directorio de archivos','pass'=>is_writable($phpDir),'value'=>is_writable($phpDir)?'escribible':'verifica permisos'],
      ['label'=>'Archivo de configuración privada','pass'=>is_file(dirname(__DIR__).'/private/config.php'),'value'=>'fuera del directorio público'],
      ['label'=>'Extensión ZIP para exportar','pass'=>class_exists('ZipArchive'),'value'=>class_exists('ZipArchive')?'activa':'opcional'],
      ['label'=>'Extensión cURL para S3/OAuth','pass'=>extension_loaded('curl'),'value'=>extension_loaded('curl')?'activa':'requerida para S3/OAuth'],
      ['label'=>'HTTPS para instalar PWA','pass'=>str_starts_with((string)$cfg['base_url'],'https://'),'value'=>(string)$cfg['base_url']],
    ];
    try{$tests[3]['value']=(string)(db()->query('SELECT VERSION()')->fetchColumn()?:'MySQL disponible');$tests[3]['pass']=true;}
    catch(Throwable){$tests[3]['value']='sin conexión';}
    $free=@disk_free_space($phpDir);
    ok(['version'=>release_info()['version'],'tests'=>$tests,'php_upload_max'=>ini_get('upload_max_filesize'),'php_post_max'=>ini_get('post_max_size'),'storage_driver'=>$storageCfg['driver']??'local','disk_free_bytes'=>$free===false?null:$free,'base_url'=>$cfg['base_url']]);
 case 'backup.info':
    ok(['method'=>'aaPanel Cron → Backup Website + Backup Database → Google Drive/FTP/AWS S3','backup_key_configured'=>isset(config()['backup_key']),'keep'=>(int)(config()['backup']['keep']??7),'cron'=>dirname(__DIR__).'/tools/backup.php']);
 default:fail('Acción no disponible: '.$action,404);
 }
}catch(PDOException $e){log_error($e);fail('Problema de base de datos; consulta el registro del servidor',500);}
 catch(Throwable $e){log_error($e);fail('No se pudo completar: '.$e->getMessage(),500);}
