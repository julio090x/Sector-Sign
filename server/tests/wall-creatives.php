<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/walls.php';
require __DIR__.'/../private/creatives.php';
function check(bool $condition,string $name):void {if(!$condition)throw new RuntimeException('FALLÓ: '.$name);echo "PASS: {$name}\n";}
function expectFailure(callable $job,string $name):void {$failed=false;try{$job();}catch(InvalidArgumentException){$failed=true;}check($failed,$name);}
$ids=array_map(static fn($i)=>str_pad(dechex($i),32,'0',STR_PAD_LEFT),range(1,4));
$wall=normalize_wall(['name'=>'Primer piso · pasillo A','mode'=>'span','rows'=>2,'cols'=>2,'screens'=>$ids]);
check($wall['rows']===2&&$wall['cols']===2&&count($wall['screens'])===4,'muro de cuatro pantallas');
check(normalize_wall(['name'=>'Espejo','mode'=>'mirror','rows'=>1,'cols'=>2,'screens'=>array_slice($ids,0,2)])['mode']==='mirror','grupo espejo independiente');
expectFailure(fn()=>normalize_wall(['name'=>'Test','mode'=>'span','rows'=>2,'cols'=>2,'screens'=>[$ids[0],$ids[0],$ids[2],$ids[3]]]),'rechaza pantallas duplicadas');
expectFailure(fn()=>normalize_wall(['name'=>'Test','mode'=>'span','rows'=>2,'cols'=>2,'screens'=>array_slice($ids,0,3)]),'rechaza cantidad insuficiente');
expectFailure(fn()=>normalize_wall(['name'=>'Test','mode'=>'span','rows'=>5,'cols'=>2,'screens'=>$ids]),'rechaza matriz fuera de límite');
expectFailure(fn()=>normalize_wall(['name'=>'Test','mode'=>'unknown','rows'=>1,'cols'=>1,'screens'=>[$ids[0]]]),'rechaza modo desconocido');
expectFailure(fn()=>normalize_wall(['name'=>'','mode'=>'span','rows'=>1,'cols'=>1,'screens'=>[$ids[0]]]),'requiere nombre');
expectFailure(fn()=>normalize_wall(['name'=>'Test','mode'=>'span','rows'=>1,'cols'=>1,'screens'=>['../../etc/passwd']]),'rechaza identificador inválido');
check(validate_stream_url('https://cdn.example.org/live/playlist.m3u8'),'autoriza fuente HLS HTTPS');
check(validate_stream_url('https://cdn.example.org/videos/spot.mp4?auth=demo'),'autoriza MP4 HTTPS');
foreach(['http://example.org/stream.mp4','https://localhost/stream.mp4','https://127.0.0.1/foo.mp4','https://10.0.0.1/foo.mp4','https://user:pass@example.org/a.mp4','file:///video.mp4','https://example.org/movie.exe'] as $url){check(!validate_stream_url($url),'bloquea origen de stream no permitido');}
$svg=make_intro_svg(['title'=>'Oferta <script>alert(1)</script> & supermercado','subtitle'=>'Desde hoy y todo el mes','brand'=>'JA ANTHONY','call_to_action'=>'VEN A VISITARNOS']);
check(str_contains($svg,'&lt;script&gt;')&&!str_contains($svg,'<script>'),'escapa XML en títulos');
check(!str_contains($svg,'foreignObject')&&str_contains($svg,'<tspan'),'intro usa SVG puro compatible');
check(str_contains($svg,'width="1920"')&&str_contains($svg,'height="1080"'),'intro Full HD horizontal');
expectFailure(fn()=>make_intro_svg(['title'=>'  ']),'intro exige título');
echo "PASS: pruebas de creatividad, streaming y videowall\n";
