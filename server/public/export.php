<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
try{
 $user=auth();$id=(int)($_GET['id']??0);$format=in_array($_GET['format']??'html',['html','zip'],true)?$_GET['format']:'html';
 $list=db_one('SELECT * FROM playlists WHERE id=? AND org_id=?',[$id,$user['org_id']]);if(!$list){http_response_code(404);exit('Lista no encontrada');}
 $items=build_manifest((int)$user['org_id'],$id);if(!$items){http_response_code(400);exit('Lista vacía');}
 $total=array_sum(array_column($items,'bytes'));
 if($total>120*1024*1024){http_response_code(413);exit('La lista excede 120 MB. Utiliza el reproductor online con caché o divide la lista.');}
 if($format==='html'&&$total>40*1024*1024){http_response_code(413);exit('HTML único limitado a 40 MB. Elige exportar como ZIP, que admite hasta 120 MB.');}
 $assets=[];$zip=null;$zipPath=null;$tempFiles=[];
 if($format==='zip'){
   if(!class_exists('ZipArchive')){http_response_code(500);exit('Activa PHP zip en aaPanel');}
   $zipPath=tempnam(sys_get_temp_dir(),'ssz_');$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo preparar ZIP');
 }
 foreach($items as $item){
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
 $payload=json_encode(['name'=>$list['name'],'timezone'=>$tz,'items'=>$items,'assets'=>$assets],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 $html='<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sector Sign offline</title><style>html,body{margin:0;height:100%;background:#020712;color:white;font:14px system-ui;overflow:hidden}#screen{position:fixed;inset:0;display:grid;place-items:center}#screen img,#screen video{width:100%;height:100%;object-fit:contain}#label{position:fixed;bottom:12px;right:14px;color:#a4b4cc;font-size:10px}button{position:fixed;top:12px;right:12px;padding:8px;background:#123872;border:0;border-radius:7px;color:white}</style><div id="screen">Cargando anuncios…</div><button onclick="document.documentElement.requestFullscreen?.()">⛶ Pantalla completa</button><div id="label">SECTOR SIGN · OFFLINE</div><script>const manifest='.$payload.';let i=0;
function scheduled(sc){if(!sc||!Object.keys(sc).length)return true;const parts={};new Intl.DateTimeFormat("en-US",{timeZone:manifest.timezone,year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",hour12:false,weekday:"short"}).formatToParts(new Date()).forEach(p=>{if(p.type!=="literal")parts[p.type]=p.value});const ymd=parts.year+"-"+parts.month+"-"+parts.day,m=(Number(parts.hour)%24)*60+Number(parts.minute),days=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];const toM=x=>Number(x.slice(0,2))*60+Number(x.slice(3)),start=sc.start?toM(sc.start):0,end=sc.end?toM(sc.end):1440;let wd=days.indexOf(parts.weekday);let valid,effectiveDate=ymd;if(sc.start&&sc.end&&start>end){valid=m>=start||m<end;if(m<end){wd=(wd+6)%7;const prev=new Date(ymd+"T12:00:00Z");prev.setUTCDate(prev.getUTCDate()-1);effectiveDate=prev.toISOString().slice(0,10);}}else valid=start===end?true:m>=start&&m<end;if(sc.from&&effectiveDate<sc.from||sc.until&&effectiveDate>sc.until)return false;return valid&&(!Array.isArray(sc.days)||!sc.days.length||sc.days.includes(wd));}
function next(){const list=manifest.items.filter(x=>scheduled(x.schedule));const stage=document.getElementById("screen");if(!list.length){stage.textContent="Sin anuncios programados";setTimeout(next,60000);return;}const x=list[i++%list.length];const src=manifest.assets[x.id];if(!src)return setTimeout(next,1000);stage.innerHTML="";const node=document.createElement(x.mime.startsWith("video")?"video":"img");node.src=src;node.style.objectFit=x.fit||"contain";if(node.tagName==="VIDEO"){node.autoplay=true;node.muted=true;node.loop=true;node.playsInline=true;node.play().catch(()=>{});}stage.appendChild(node);setTimeout(next,Math.max(2,x.duration||8)*1000)}next();</script></html>';
 if($format==='html'){header('Content-Type: text/html; charset=utf-8');header('Content-Disposition: attachment; filename="sector-sign-'.$safeName.'-offline.html"');header('Cache-Control: no-store');echo $html;exit;}
 $zip->addFromString('index.html',$html);$zip->addFromString('README.txt','Extrae el ZIP. Abre index.html en un navegador compatible y concede reproducción de videos cuando sea necesario. No requiere internet.');$zip->close();foreach($tempFiles as $tmp)@unlink($tmp);
 header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="sector-sign-'.$safeName.'-offline.zip"');header('Content-Length: '.filesize($zipPath));readfile($zipPath);@unlink($zipPath);
}catch(Throwable $e){log_error($e);http_response_code(500);echo 'No se pudo exportar: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
