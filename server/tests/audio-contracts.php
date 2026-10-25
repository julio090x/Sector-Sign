<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/offline-exporter.php';
function good(bool $a,string $label):void{if(!$a)throw new RuntimeException('FALLO '.$label);echo "PASS {$label}\n";}
foreach(['audio/mpeg','audio/x-wav','audio/wav','audio/ogg','audio/mp4'] as $m)good(allow_mime($m)==='audio','Subida audio '.$m);
good(allow_mime('text/html')===null&&allow_mime('application/x-php')===null,'Rechazo de archivo ejecutable');
$a=normalize_clip_audio(['volume'=>999,'mode'=>'desconocido','voice'=>['text'=>'Hola mundo','lang'=>'es-PE','rate'=>99,'caption'=>false]],1);
good($a['volume']===100&&$a['mode']==='mix'&&$a['voice']['rate']===1.5,'Audio normalizado con límites de volumen/velocidad');
good($a['voice']['enabled']===true&&$a['voice']['caption']===false,'Bot lector y subtítulos opcionales');
$bad=false;try{normalize_clip_audio(['media_id'=>'NO-PERTENECE','voice'=>['text'=>'hola']],1);}catch(InvalidArgumentException){$bad=true;}
good($bad,'ID de pista inválida bloqueado sin acceder a otra cuenta');
$bad=false;try{normalize_clip_audio(['voice'=>['text'=>'hola','lang'=>'xx']],1);}catch(InvalidArgumentException){$bad=true;}
good($bad,'Idioma de narración desconocido bloqueado');
$bad=false;try{normalize_clip_audio(['voice'=>['text'=>str_repeat('A',751)]],1);}catch(InvalidArgumentException){$bad=true;}
good($bad,'Narración superior a 750 caracteres bloqueada');
$music=normalize_soundtrack(['volume'=>-99],1);good($music['volume']===0,'Volumen música limitado');
$html=offline_export_html(json_encode(['items'=>[],'assets'=>[]]));
good(str_contains($html,'speechSynthesis')&&str_contains($html,'soundtrack')&&str_contains($html,'new Audio'),'Exportador offline incluye voz y audios');
echo "FIN: audio y narración correctos\n";
