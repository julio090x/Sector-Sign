<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
try{$u=auth();$s=db_one('SELECT capture_file FROM screens WHERE id=? AND org_id=?',[(string)($_GET['id']??''),$u['org_id']]);if(!$s||!$s['capture_file']){http_response_code(404);exit;}$image=db_one('SELECT png_base64 FROM screen_capture_blobs WHERE screen_id=?',[(string)($_GET['id']??'')]);
if($image){$raw=base64_decode($image['png_base64'],true);if($raw===false){http_response_code(500);exit;}send_security_headers();header('Content-Type: image/png');header('Content-Length: '.strlen($raw));echo $raw;exit;}
$f=capture_dir().'/'.basename($s['capture_file']);if(!is_file($f)){http_response_code(404);exit;}send_security_headers();header('Content-Type: image/png');header('Content-Length: '.filesize($f));readfile($f);}catch(Throwable $e){http_response_code(403);}
