<?php
declare(strict_types=1);
function player_auth():array {
    $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    if(!preg_match('/^Bearer ([0-9a-f]{64})$/i',$header,$m))fail('Reproductor sin vincular',401);
    $s=db_one('SELECT * FROM screens WHERE token_hash=?',[hash('sha256',$m[1])]);
    if(!$s)fail('Dispositivo revocado o desconocido',401);
    return $s;
}
function new_pair_code():string{return str_pad((string)random_int(0,99999999),8,'0',STR_PAD_LEFT);}
function player_action(string $action):never {
    $p=input();
    if($action==='player.register'){
        if($_SERVER['REQUEST_METHOD']!=='POST')fail('Método inválido',405);
        $remote=hash('sha256','register|'.client_ip());
        $attempt=db_one('SELECT COUNT(*) c FROM login_attempts WHERE remote_key=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)',[$remote]);
        if((int)($attempt['c']??0)>=12)fail('Límite de vinculaciones: inténtalo luego',429);
        db_run('INSERT INTO login_attempts(remote_key) VALUES(?)',[$remote]);
        $id=pkid();$token=bin2hex(random_bytes(32));$code=new_pair_code();
        db_run('INSERT INTO screens(id,name,token_hash,pair_code_hash,pair_expires_at) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE))',[$id,'Nueva pantalla',hash('sha256',$token),hash('sha256',$code)]);
        ok(['device_id'=>$id,'device_token'=>$token,'pair_code'=>$code,'expires_minutes'=>15]);
    }
    $s=player_auth();$id=$s['id'];
    if($action==='player.pair_code'){
        if($s['paired_at']!==null)fail('El equipo ya está vinculado');
        $code=new_pair_code();db_run('UPDATE screens SET pair_code_hash=?,pair_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE) WHERE id=?',[hash('sha256',$code),$id]);
        ok(['pair_code'=>$code,'expires_minutes'=>15]);
    }
    if($action==='player.sync'){
        if($_SERVER['REQUEST_METHOD']!=='GET')fail('Método inválido',405);
        // Avoid one database write per device every five seconds. A 15s heartbeat is enough for a 35s online window.
        db_run('UPDATE screens SET last_seen_at=UTC_TIMESTAMP() WHERE id=? AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 SECOND))',[$id]);
        if($s['org_id']===null)ok(['paired'=>false,'software'=>release_info(),'server_utc'=>gmdate(DATE_ATOM)]);
        $manifest=null;
        if($s['publication_id']!==null){
            $pub=db_one('SELECT id,version,manifest_json,created_at FROM publications WHERE id=? AND org_id=?',[$s['publication_id'],$s['org_id']]);
            if($pub)$manifest=['id'=>(int)$pub['id'],'version'=>(int)$pub['version'],'created_at'=>$pub['created_at']]+(json_decode($pub['manifest_json'],true)?:[]);
        }
        $commands=db_all('SELECT id,action FROM screen_commands WHERE screen_id=? AND status IN ("queued","delivered") ORDER BY id ASC LIMIT 20',[$id]);
        foreach($commands as $c)db_run('UPDATE screen_commands SET status="delivered",delivered_at=COALESCE(delivered_at,UTC_TIMESTAMP()) WHERE id=? AND status="queued"',[$c['id']]);
        
        $org=db_one('SELECT timezone FROM organizations WHERE id=?',[$s['org_id']]);
        ok(['paired'=>true,'timezone'=>$org['timezone']??'America/Lima','screen'=>['id'=>$id,'name'=>$s['name'],'orientation'=>$s['orientation']],'manifest'=>$manifest,'applied_publication_id'=>(int)($s['applied_publication_id']??0),'commands'=>$commands,'software'=>release_info(),'server_utc'=>gmdate(DATE_ATOM)]);
    }
    if($action==='player.status'){
        if(!$s['org_id'])fail('Vinculación pendiente',403);
        $strings=['app_version'=>40,'web_version'=>30,'platform'=>140,'screen'=>40,'state'=>30,'current_item'=>190,'schedule'=>190];$clean=[];
        foreach($strings as $key=>$limit)$clean[$key]=mb_substr(trim((string)($p[$key]??'')),0,$limit);
        $clean['playlists']=array_map(fn($v)=>mb_substr((string)$v,0,160),array_slice((array)($p['playlists']??[]),0,30));
        foreach(['storage_bytes','quota_bytes','cached_files','download_percent'] as $key)$clean[$key]=max(0,min(9007199254740991,(float)($p[$key]??0)));
        $clean['reported_at']=gmdate(DATE_ATOM);
        db_run('INSERT INTO device_status(screen_id,telemetry_json) VALUES(?,?) ON DUPLICATE KEY UPDATE telemetry_json=VALUES(telemetry_json),updated_at=UTC_TIMESTAMP()',[$id,json_encode($clean,JSON_UNESCAPED_UNICODE)]);
        db_run('UPDATE screens SET last_seen_at=UTC_TIMESTAMP() WHERE id=?',[$id]);ok();
    }
    if($action==='player.ack'){
        if($_SERVER['REQUEST_METHOD']!=='POST')fail('Método inválido',405);
        $pub=(int)($p['publication_id']??0);
        if($pub>0 && $pub===(int)$s['publication_id'])db_run('UPDATE screens SET applied_publication_id=?,last_error=NULL WHERE id=?',[$pub,$id]);
        if(isset($p['error']))db_run('UPDATE screens SET last_error=? WHERE id=?',[mb_substr((string)$p['error'],0,300),$id]);
        foreach(array_slice(array_unique(array_map('intval',(array)($p['command_ids']??[]))),0,20) as $cmd){db_run('UPDATE screen_commands SET status="ack",ack_at=UTC_TIMESTAMP() WHERE id=? AND screen_id=?',[$cmd,$id]);}
        ok();
    }
    if($action==='player.capture'){
       if($_SERVER['REQUEST_METHOD']!=='POST')fail('Método inválido',405);
       if(!$s['org_id'])fail('Vinculación pendiente',403);
       $encoded=(string)($p['png_base64']??'');if(strlen($encoded)>440000)fail('Captura demasiado grande');
       $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)>320000||substr($raw,0,8)!=="\x89PNG\r\n\x1a\n")fail('Captura PNG inválida');
       $name='database';
       db_run('INSERT INTO screen_capture_blobs(screen_id,png_base64) VALUES(?,?) ON DUPLICATE KEY UPDATE png_base64=VALUES(png_base64),updated_at=UTC_TIMESTAMP()',[$id,$encoded]);
       db_run('UPDATE screens SET capture_file=?,last_capture_at=UTC_TIMESTAMP() WHERE id=?',[$name,$id]);ok();
    }
    fail('Acción de reproductor desconocida',404);
}
