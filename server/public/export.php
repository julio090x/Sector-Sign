<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
try{
 $user=auth();$id=(int)($_GET['id']??0);$format=in_array($_GET['format']??'html',['html','zip'],true)?$_GET['format']:'html';
 $list=db_one('SELECT * FROM playlists WHERE id=? AND org_id=?',[$id,$user['org_id']]);if(!$list){http_response_code(404);exit('Lista no encontrada');}
 $items=build_manifest((int)$user['org_id'],$id);if(!$items){http_response_code(400);exit('Lista vacía');}
 // Live streams are inherently online-only and cannot be embedded in a portable ZIP.
 if(array_filter($items,static fn($it)=>($it['kind']??'')==='stream')){http_response_code(422);exit('Esta lista contiene una transmisión online. Retira el directo para descargar la versión offline.');}
 $music=playlist_soundtrack((int)$user['org_id'],$id);
 $sounds=audio_assets((int)$user['org_id'],$items,$music);
 $total=array_sum(array_column(array_merge($items,$sounds),'bytes'));
 if($total>120*1024*1024){http_response_code(413);exit('La lista excede 120 MB. Utiliza el reproductor online con caché o divide la lista.');}
 if($format==='html'&&$total>40*1024*1024){http_response_code(413);exit('HTML único limitado a 40 MB. Elige exportar como ZIP, que admite hasta 120 MB.');}
 $assets=[];$zip=null;$zipPath=null;$tempFiles=[];
 if($format==='zip'){
   if(!class_exists('ZipArchive')){http_response_code(500);exit('Activa PHP zip en aaPanel');}
   $zipPath=tempnam(sys_get_temp_dir(),'ssz_');$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo preparar ZIP');
 }
 foreach(array_merge($items,$sounds) as $item){
  $m=db_one('SELECT * FROM media WHERE id=? AND org_id=?',[$item['id'],$user['org_id']]);if(!$m)continue;
  $path=media_dir().'/'.$m['stored_name'];$temp=false;
  if(!is_file($path)){
    $url=storage_url($m);if(!$url)throw new RuntimeException('Archivo no disponible: '.$item['name']);
    $bytes=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>30,'follow_location'=>0]]));
    if($bytes===false)throw new RuntimeException('Descarga remota no disponible: '.$item['name']);
    $path=tempnam(sys_get_temp_dir(),'ssm_');file_put_contents($path,$bytes);$temp=true;
  }
  if(hash_file('sha256',$path)!==$item['sha256']){if($temp)@unlink($path);throw new RuntimeException('El archivo no pasó verificación SHA-256');}
  if($format==='zip'){$name='media/'.$m['stored_name'];$zip->addFile($path,$name);$assets[$m['id']]=$name;}
  else $assets[$m['id']]='data:'.$item['mime'].';base64,'.base64_encode((string)file_get_contents($path));
  if($temp){$tempFiles[]=$path;}
 }
 $safeName=preg_replace('/[^a-z0-9_-]/i','-',(string)$list['name'])?:'lista';
 $tz=db_one('SELECT timezone FROM organizations WHERE id=?',[$user['org_id']])['timezone']??'America/Lima';
 $payload=json_encode(['name'=>$list['name'],'timezone'=>$tz,'items'=>$items,'soundtrack'=>$music,'audio_assets'=>$sounds,'assets'=>$assets],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 require_once __DIR__.'/../private/offline-exporter.php';
 $html=offline_export_html($payload);
 if($format==='html'){header('Content-Type: text/html; charset=utf-8');header('Content-Disposition: attachment; filename="sector-sign-'.$safeName.'-offline.html"');header('Cache-Control: no-store');echo $html;exit;}
 $zip->addFromString('index.html',$html);$zip->addFromString('README.txt','Extrae el ZIP. Abre index.html en un navegador compatible y concede reproducción de videos cuando sea necesario. No requiere internet.');$zip->close();foreach($tempFiles as $tmp)@unlink($tmp);
 header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="sector-sign-'.$safeName.'-offline.zip"');header('Content-Length: '.filesize($zipPath));readfile($zipPath);@unlink($zipPath);
}catch(Throwable $e){log_error($e);http_response_code(500);echo 'No se pudo exportar: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
