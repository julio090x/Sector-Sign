<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';require __DIR__.'/../private/programs.php';
$count=0;function check(string $name,callable $run):void{global $count;$run();$count++;echo "PASS $name\n";}
function expect(bool $v):void{if(!$v)throw new RuntimeException('Assertion failed');}
function reject(array $p):void{try{validate_program($p);}catch(InvalidArgumentException){return;}throw new RuntimeException('Expected rejection');}
$base=['name'=>'Horario tienda','timezone'=>'America/Lima','rules'=>[['name'=>'Mañana','playlist_ids'=>[1,2,3],'schedule'=>['days'=>[1,2,3,4,5],'start'=>'08:00','end'=>'12:00']]],'fallback_id'=>3];
check('tres listas aceptadas',fn()=>expect(validate_program($base)['rules'][0]['playlist_ids']===[1,2,3]));
check('horario noche válido',function()use($base){$p=$base;$p['rules'][0]['schedule']=['start'=>'22:00','end'=>'02:00','days'=>[5]];expect(validate_program($p)['rules'][0]['schedule']['end']==='02:00');});
check('zona falsa rechazada',fn()=>reject(array_replace($base,['timezone'=>'Bad/Place'])));
check('nombre vacío rechazado',fn()=>reject(array_replace($base,['name'=>'  '])));
check('fecha imposible rechazada',function()use($base){$p=$base;$p['rules'][0]['schedule']['from']='2026-02-30';reject($p);});
check('fecha inversa rechazada',function()use($base){$p=$base;$p['rules'][0]['schedule']['from']='2026-12-01';$p['rules'][0]['schedule']['until']='2026-11-01';reject($p);});
check('hora 24 rechazada',function()use($base){$p=$base;$p['rules'][0]['schedule']['start']='24:00';reject($p);});
check('día 7 rechazado',function()use($base){$p=$base;$p['rules'][0]['schedule']['days']=[7];reject($p);});
check('sin listas rechazado',function()use($base){$p=$base;$p['rules'][0]['playlist_ids']=[];reject($p);});
check('list id negativo rechazado',function()use($base){$p=$base;$p['rules'][0]['playlist_ids']=[-1];reject($p);});
check('limita 100 franjas',function()use($base){$p=$base;$p['rules']=array_fill(0,101,$p['rules'][0]);reject($p);});
check('limita 30 listas por franja',function()use($base){$p=$base;$p['rules'][0]['playlist_ids']=range(1,31);reject($p);});
check('respaldo sin franjas permitido',fn()=>expect(validate_program(['name'=>'Solo respaldo','rules'=>[],'fallback_id'=>3])['fallback_id']===3));
check('sin respaldo ni franjas rechazado',fn()=>reject(['name'=>'Vacío','rules'=>[],'fallback_id'=>0]));
check('ignora campos extra',function()use($base){$p=$base;$p['rules'][0]['schedule']['sql']='DROP';expect(!isset(validate_program($p)['rules'][0]['schedule']['sql']));});
check('lista de ids deduplicada',function()use($base){$p=$base;$p['rules'][0]['playlist_ids']=[2,1,2];expect(validate_program($p)['rules'][0]['playlist_ids']===[2,1]);});
echo "$count validation checks passed\n";
