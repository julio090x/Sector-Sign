<?php
declare(strict_types=1);
// Sector Sign — standalone PHP/MySQL, no Composer or Node runtime required.
const SS_ROOT = __DIR__ . '/..';
// Unicode-safe fallbacks keep the package usable even if mbstring was not enabled in aaPanel.
if (!function_exists('mb_substr')) {function mb_substr(string $value,int $start,?int $length=null,?string $encoding=null):string{
    $chars=preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY);if($chars===false)return substr($value,$start,$length);
    return implode('',array_slice($chars,$start,$length));
}}
if (!function_exists('mb_strlen')) {function mb_strlen(string $value,?string $encoding=null):int{return count(preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY)?:[]);}}
if (!function_exists('mb_strtoupper')) {function mb_strtoupper(string $value,?string $encoding=null):string{return strtoupper($value);}}

require_once __DIR__.'/release.php';
function config(): array {
    static $config = null;
    if ($config !== null) return $config;
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) throw new RuntimeException('Sistema sin instalar. Abra /install.php');
    $config = require $file;
    return $config;
}
function installed(): bool {return is_file(__DIR__.'/config.php');}
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = config();
    $dsn = 'mysql:host='.$cfg['db_host'].';port='.($cfg['db_port']??3306).';dbname='.$cfg['db_name'].';charset=utf8mb4';
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::MYSQL_ATTR_INIT_COMMAND=>"SET time_zone = '+00:00'"]);
    return $pdo;
}
function start_session(): void {
    if (PHP_SAPI==='cli') return;
    if (session_status()===PHP_SESSION_ACTIVE) return;
    $cfg = installed()?config():[];
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (str_starts_with((string)($cfg['base_url']??''),'https://'));
    session_name('SSSESSION');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    // DB-backed sessions support multiple load-balanced PHP instances sharing MySQL.
    if (installed() && !empty($cfg['db_sessions'])) {
        $handler = new SessionHandlerDb();
        session_set_save_handler($handler, true);
    }
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
}
class SessionHandlerDb implements SessionHandlerInterface {
    public function open(string $path,string $name):bool{return true;}
    public function close():bool{return true;}
    public function read(string $id):string {
        try {$st=db()->prepare('SELECT payload FROM web_sessions WHERE id_hash=? AND expires_at>UTC_TIMESTAMP()');$st->execute([hash('sha256',$id)]);return (string)($st->fetchColumn()?:'');}
        catch(Throwable){return '';}
    }
    public function write(string $id,string $data):bool {
        try {$st=db()->prepare('INSERT INTO web_sessions(id_hash,payload,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 12 HOUR)) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)');return $st->execute([hash('sha256',$id),$data]);}
        catch(Throwable){return false;}
    }
    public function destroy(string $id):bool {try{$st=db()->prepare('DELETE FROM web_sessions WHERE id_hash=?');return $st->execute([hash('sha256',$id)]);}catch(Throwable){return false;}}
    public function gc(int $max_lifetime):int|false {try{return db()->exec('DELETE FROM web_sessions WHERE expires_at<UTC_TIMESTAMP()');}catch(Throwable){return false;}}
}
function send_security_headers(bool $json=false):void {
    if (PHP_SAPI==='cli') return;
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY'); header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store');
    if ($json) header('Content-Type: application/json; charset=utf-8');
}
function json_response(array $data,int $status=200):never{http_response_code($status);send_security_headers(true);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function ok(array $data=[]):never {json_response(['ok'=>true]+$data);}
function fail(string $message,int $code=400,array $more=[]):never {json_response(['ok'=>false,'error'=>$message]+$more,$code);}
function input():array {
    if (stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==false){$raw=file_get_contents('php://input');$p=json_decode($raw?:'[]',true);if(!is_array($p))fail('JSON inválido');return $p;}
    return $_POST;
}
function current_user():?array {
    start_session(); if(empty($_SESSION['user_id'])||!installed())return null;
    $st=db()->prepare('SELECT id,org_id,name,email,role,enabled,session_epoch FROM users WHERE id=? LIMIT 1');$st->execute([$_SESSION['user_id']]);$user=$st->fetch();
    if(!$user||(int)$user['enabled']!==1||(int)($_SESSION['session_epoch']??-1)!==(int)$user['session_epoch']){unset($_SESSION['user_id'],$_SESSION['session_epoch']);return null;}
    return $user;
}
function auth(string $min='viewer'):array{
    $u=current_user();if(!$u)fail('Inicia sesión primero',401);
    $rank=['viewer'=>0,'editor'=>1,'admin'=>2];if(($rank[$u['role']]??-1)<($rank[$min]??0))fail('No tienes permiso',403);
    return $u;
}
function csrf():void{
    start_session();$token=$_SERVER['HTTP_X_CSRF_TOKEN']??(string)($_POST['csrf']??'');
    if(!hash_equals((string)($_SESSION['csrf']??''),(string)$token))fail('Sesión vencida: actualiza la página',419);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin!=='' && installed() && rtrim($origin,'/')!==rtrim((string)config()['base_url'],'/'))fail('Origen no permitido',403);
}
function client_ip():string {
    $remote=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $trusted=config()['trusted_proxy_ips']??[];
    if(in_array($remote,$trusted,true)&&isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
       $candidate=trim(explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
       if(filter_var($candidate,FILTER_VALIDATE_IP))return $candidate;
    }
    return $remote;
}
function s(string $key,array $args=[]):string{return strtr($key,$args);}
function field(array $arr,string $key,int $limit=200):string{return trim(mb_substr((string)($arr[$key]??''),0,$limit));}
function required(array $p,string $key,int $limit=200):string{$v=field($p,$key,$limit);if($v==='')fail('Falta: '.$key);return $v;}
function pkid():string{return bin2hex(random_bytes(16));}
function audit(?int $org,string $actor,string $action,string $type='',string $object='',array $info=[]):void{
    try {$st=db()->prepare('INSERT INTO audit_events(org_id,actor,action,object_type,object_id,info_json) VALUES(?,?,?,?,?,?)');$st->execute([$org,$actor,$action,$type,$object, json_encode($info,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE)]);}catch(Throwable){}
}
function log_error(Throwable $e):void {
    error_log('[SectorSign] '.$e->getMessage().' '.$e->getFile().':'.$e->getLine());
}
function allow_mime(string $mime):?string {
    return match($mime){'image/jpeg','image/png','image/webp','image/gif'=>'image','video/mp4','video/webm'=>'video', default=>null};
}
function media_dir():string {$dir=dirname(__DIR__).'/storage/media';if(!is_dir($dir))mkdir($dir,0750,true);return $dir;}
function capture_dir():string {$dir=dirname(__DIR__).'/storage/captures';if(!is_dir($dir))mkdir($dir,0750,true);return $dir;}
function db_all(string $sql,array $params=[]):array {$st=db()->prepare($sql);$st->execute($params);return $st->fetchAll();}
function db_one(string $sql,array $params=[]):?array{$st=db()->prepare($sql);$st->execute($params);return $st->fetch()?:null;}
function db_run(string $sql,array $params=[]):int {$st=db()->prepare($sql);$st->execute($params);return $st->rowCount();}
function org_id(array $u):int{return (int)$u['org_id'];}
function save_private_config(array $values):void {
    $dest=__DIR__.'/config.php';$lockPath=__DIR__.'/config.write.lock';
    $lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la configuración');
    try{
        $tmp=$dest.'.'.bin2hex(random_bytes(5)).'.tmp';
        if(file_put_contents($tmp,"<?php\nreturn ".var_export($values,true).";\n",LOCK_EX)===false)throw new RuntimeException('No se pudo escribir la configuración');
        @chmod($tmp,0600);
        if(!rename($tmp,$dest)){$msg='No se pudo activar la configuración';@unlink($tmp);throw new RuntimeException($msg);}
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function validate_public_url(string $url):bool{return (bool)filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower(parse_url($url,PHP_URL_SCHEME)?:''),['http','https'],true);}
function normalize_schedule(array $input):array {
    $out=[];
    foreach(['from','until'] as $date){if(!empty($input[$date])){
       $val=(string)$input[$date];$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$val);
       if(!$dt||$dt->format('Y-m-d')!==$val)fail('Fecha inválida: '.$date,422);
       $out[$date]=$val;
    }}
    if(isset($out['from'],$out['until'])&&$out['from']>$out['until'])fail('La fecha inicial es posterior a la final',422);
    foreach(['start','end'] as $time){if(!empty($input[$time])){
      $val=(string)$input[$time];if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$val))fail('Hora inválida: '.$time,422);
      $out[$time]=$val;
    }}
    if(!empty($input['days'])){
      if(!is_array($input['days']))fail('Días inválidos',422);
      foreach($input['days'] as $day)if(!is_int($day)&&!(is_string($day)&&preg_match('/^[0-6]$/',$day)))fail('Día inválido',422);
      $days=array_values(array_unique(array_map('intval',$input['days'])));sort($days);
      foreach($days as $day)if($day<0||$day>6)fail('Día inválido',422);
      $out['days']=$days;
    }
    return $out;
}
function build_manifest(int $org,int $playlistId):array {
    $items=db_all('SELECT pi.media_id,pi.position,pi.duration_sec,pi.fit,pi.transition,pi.schedule_json,m.original_name,m.mime,m.sha256,m.bytes FROM playlist_items pi JOIN media m ON m.id=pi.media_id AND m.deleted_at IS NULL JOIN playlists p ON p.id=pi.playlist_id WHERE pi.playlist_id=? AND p.org_id=? ORDER BY pi.position,pi.id',[$playlistId,$org]);
    return array_map(static function($r){return ['id'=>$r['media_id'],'name'=>$r['original_name'],'mime'=>$r['mime'],'sha256'=>$r['sha256'],'bytes'=>(int)$r['bytes'],'duration'=>(int)$r['duration_sec'],'fit'=>$r['fit'],'transition'=>$r['transition'],'schedule'=>json_decode($r['schedule_json'],true)?:[]];},$items);
}
function write_playlist(int $org,int $id,array $items,int $revision):array{
    if(count($items)>250)fail('Máximo 250 elementos por lista');
    db()->beginTransaction();try{
        $row=db_one('SELECT id,revision FROM playlists WHERE id=? AND org_id=? FOR UPDATE',[$id,$org]);
        if(!$row){db()->rollBack();fail('Lista no encontrada',404);}
        if((int)$row['revision']!==$revision){db()->rollBack();fail('La lista cambió en otra sesión; actualiza antes de guardar',409,['revision'=>(int)$row['revision']]);}
        db_run('DELETE FROM playlist_items WHERE playlist_id=?',[$id]);
        $pos=0;
        foreach($items as $item){
            if(!is_array($item))continue;
            $media=field($item,'id',32);
            $exists=db_one('SELECT id FROM media WHERE id=? AND org_id=? AND deleted_at IS NULL',[$media,$org]);if(!$exists){db()->rollBack();fail('Archivo no encontrado: '.$media,422);}
            $duration=max(2,min(600,(int)($item['duration']??8)));
            $fit=in_array($item['fit']??'contain',['contain','cover'],true)?$item['fit']:'contain';
            $transition=in_array($item['transition']??'fade',['fade','slide','none'],true)?$item['transition']:'fade';
            $schedule=normalize_schedule(is_array($item['schedule']??null)?$item['schedule']:[]);
            db_run('INSERT INTO playlist_items(playlist_id,media_id,position,duration_sec,fit,transition,schedule_json) VALUES(?,?,?,?,?,?,?)',[$id,$media,$pos++,$duration,$fit,$transition,json_encode($schedule,JSON_UNESCAPED_UNICODE)]);
        }
        db_run('UPDATE playlists SET revision=revision+1 WHERE id=?',[$id]);db()->commit();return ['revision'=>$revision+1];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
require_once __DIR__.'/storage.php';
