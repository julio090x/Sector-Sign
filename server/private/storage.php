<?php
declare(strict_types=1);
function storage_config():array{return config()['storage']??['driver'=>'local'];}
function storage_save(string $localPath,string $object,string $mime):array{
  $cfg=storage_config();
  if(($cfg['driver']??'local')==='ftps'){
    ftps_put($cfg,'sector-sign/media/'.$object,$localPath);
    return ['backend'=>'ftps','object_key'=>'sector-sign/media/'.$object];
  }
  if(($cfg['driver']??'local')!=='s3')return ['backend'=>'local','object_key'=>null];
  s3_put($cfg,'sector-sign/media/'.$object,$localPath,$mime);return ['backend'=>'s3','object_key'=>'sector-sign/media/'.$object];
}
function s3_path(array $cfg,string $object):array {
    $endpoint=rtrim($cfg['endpoint'],'/');$host=parse_url($endpoint,PHP_URL_HOST);
    if(!$host||parse_url($endpoint,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Endpoint S3 debe usar HTTPS');
    $prefix=rtrim(parse_url($endpoint,PHP_URL_PATH)?:'','/');
    $key=implode('/',array_map('rawurlencode',explode('/',$object)));
    $path=$prefix.'/'.rawurlencode($cfg['bucket']).'/'.$key;
    return [$endpoint,$host,$path];
}
function s3_key(array $cfg,string $shortDate):string{
  $kDate=hash_hmac('sha256',$shortDate,'AWS4'.$cfg['secret_key'],true);
  $kRegion=hash_hmac('sha256',$cfg['region'],$kDate,true);
  $kService=hash_hmac('sha256','s3',$kRegion,true);
  return hash_hmac('sha256','aws4_request',$kService,true);
}
function s3_put(array $cfg,string $object,string $path,string $mime):void{
  if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL es necesaria para S3');
  [$endpoint,$host,$uri]=s3_path($cfg,$object);$now=gmdate('Ymd\THis\Z');$day=substr($now,0,8);
  $sha=hash_file('sha256',$path);$headers="host:{$host}\nx-amz-content-sha256:{$sha}\nx-amz-date:{$now}\n";
  $signed='host;x-amz-content-sha256;x-amz-date';$canonical="PUT\n{$uri}\n\n{$headers}\n{$signed}\n{$sha}";
  $scope="{$day}/{$cfg['region']}/s3/aws4_request";
  $toSign="AWS4-HMAC-SHA256\n{$now}\n{$scope}\n".hash('sha256',$canonical);
  $sig=hash_hmac('sha256',$toSign,s3_key($cfg,$day));
  $authorization="AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/{$scope}, SignedHeaders={$signed}, Signature={$sig}";
  $fp=fopen($path,'rb');if(!$fp)throw new RuntimeException('No se pudo abrir el archivo');
  $ch=curl_init($endpoint.$uri);
  curl_setopt_array($ch,[CURLOPT_PUT=>true,CURLOPT_INFILE=>$fp,CURLOPT_INFILESIZE=>filesize($path),CURLOPT_HTTPHEADER=>['Authorization: '.$authorization,'x-amz-date: '.$now,'x-amz-content-sha256: '.$sha,'Content-Type: '.$mime,'Expect:'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_TIMEOUT=>180,CURLOPT_FOLLOWLOCATION=>false]);
  $response=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);fclose($fp);
  if($response===false||$code<200||$code>=300)throw new RuntimeException('S3 no aceptó el archivo (HTTP '.$code.'). '.$err);
}
function s3_signed_url(array $cfg,string $object):string{
  [$endpoint,$host,$uri]=s3_path($cfg,$object);$now=gmdate('Ymd\THis\Z');$day=substr($now,0,8);
  $scope="{$day}/{$cfg['region']}/s3/aws4_request";
  $query=['X-Amz-Algorithm'=>'AWS4-HMAC-SHA256','X-Amz-Credential'=>$cfg['access_key'].'/'.$scope,'X-Amz-Date'=>$now,'X-Amz-Expires'=>'900','X-Amz-SignedHeaders'=>'host'];
  ksort($query,SORT_STRING);$encoded=http_build_query($query,'','&',PHP_QUERY_RFC3986);
  $canonical="GET\n{$uri}\n{$encoded}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
  $sig=hash_hmac('sha256',"AWS4-HMAC-SHA256\n{$now}\n{$scope}\n".hash('sha256',$canonical),s3_key($cfg,$day));
  return $endpoint.$uri.'?'.$encoded.'&X-Amz-Signature='.$sig;
}
function storage_url(array $media):?string{
    if(($media['backend']??'local')==='ftps'){
       $cfg=storage_config();if(($cfg['driver']??'')!=='ftps'||empty($cfg['public_base_url']))throw new RuntimeException('Servidor FTPS de multimedia no configurado');
       return rtrim($cfg['public_base_url'],'/').'/'.implode('/',array_map('rawurlencode',explode('/',(string)$media['object_key'])));
    }
    if(($media['backend']??'local')!=='s3')return null;
    $cfg=storage_config();$key=$media['object_key']??'';
    if($key==='')return null;
    if(!empty($cfg['cdn_url']))return rtrim($cfg['cdn_url'],'/').'/'.implode('/',array_map('rawurlencode',explode('/',$key)));
    return s3_signed_url($cfg,$key);
}

/** FTP encrypted transport, with public HTTPS delivery on remote web server. */
function ftps_put(array $cfg,string $key,string $localPath):void {
  if(!function_exists('ftp_ssl_connect'))throw new RuntimeException('Activa extensión PHP ftp para FTPS');
  $host=(string)($cfg['host']??'');$port=(int)($cfg['port']??21);
  if(!preg_match('/^[a-zA-Z0-9.-]+$/',$host)||$port<1||$port>65535)throw new RuntimeException('Servidor FTPS inválido');
  $c=@ftp_ssl_connect($host,$port,15);
  if(!$c||!@ftp_login($c,(string)$cfg['username'],(string)$cfg['password']))throw new RuntimeException('No se pudo autenticar contra FTPS');
  ftp_pasv($c,true);
  $directory=rtrim((string)($cfg['directory']??'/'),'/');
  $key=trim($key,'/');
  $path=$directory.'/'.$key;$pieces=explode('/',trim(dirname($path),'/'));$current='';
  foreach($pieces as $segment){$current.='/'.$segment;@ftp_mkdir($c,$current);}
  $ok=@ftp_put($c,$path,$localPath,FTP_BINARY);
  ftp_close($c);
  if(!$ok)throw new RuntimeException('FTPS rechazó la subida: revisa ruta y permisos.');
}
