<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
try{
 if(!installed()){http_response_code(503);exit;}
 $id=(string)($_GET['id']??'');if(!preg_match('/^[0-9a-f]{32}$/',$id)){http_response_code(404);exit;}
 $media=db_one('SELECT * FROM media WHERE id=? AND deleted_at IS NULL',[$id]);if(!$media){http_response_code(404);exit;}
 $org=(int)$media['org_id'];
 $allowed=false;$bearer=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
 if(preg_match('/^Bearer ([0-9a-f]{64})$/i',$bearer,$m)){
   $screen=db_one('SELECT org_id FROM screens WHERE token_hash=? AND paired_at IS NOT NULL',[hash('sha256',$m[1])]);
   $allowed=$screen && (int)$screen['org_id']===$org;
 }
 if(!$allowed){$user=current_user();$allowed=$user && (int)$user['org_id']===$org;}
 if(!$allowed){http_response_code(403);exit;}
 header('X-Content-Type-Options: nosniff');header('Cache-Control: private, max-age=3600');
 if($media['kind']==='stream'){if(!$media['stream_url']){http_response_code(404);exit;}header('Location: '.$media['stream_url'],true,302);exit;}
 if($url=storage_url($media)){header('Location: '.$url,true,302);exit;}
 $path=media_dir().'/'.$media['stored_name'];if(!is_file($path)){http_response_code(404);exit;}
 $length=filesize($path);$start=0;$end=$length-1;
 if(isset($_SERVER['HTTP_RANGE'])){
   if(!preg_match('/^bytes=(\d*)-(\d*)$/',$_SERVER['HTTP_RANGE'],$matches)){header('Content-Range: bytes */'.$length,true,416);exit;}
   $start=$matches[1]===''?max(0,$length-(int)$matches[2]):(int)$matches[1];
   if($matches[1]===''&&($matches[2]===''||(int)$matches[2]<1)){header('Content-Range: bytes */'.$length,true,416);exit;}
   $end=$matches[1]===''?$length-1:($matches[2]===''?$end:min((int)$matches[2],$end));
   if($start>$end||$start>=$length){header('Content-Range: bytes */'.$length,true,416);exit;}
   http_response_code(206);header("Content-Range: bytes {$start}-{$end}/{$length}");
 }
 header('Accept-Ranges: bytes');header('Content-Type: '.$media['mime']);header('Content-Disposition: inline; filename="advert.'.$media['stored_name'].'"');
 header('Content-Length: '.($end-$start+1));
 if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
 $fp=fopen($path,'rb');fseek($fp,$start);$left=$end-$start+1;while($left>0&&!feof($fp)){ $buf=fread($fp,min(1024*1024,$left));if($buf==='')break;echo $buf;$left-=strlen($buf);if(connection_aborted())break;}fclose($fp);
}catch(Throwable $e){log_error($e);http_response_code(500);}
