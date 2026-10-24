<?php
declare(strict_types=1);
/** Video wall organisation. A mirrored wall shares a campaign; a tiled wall splits visual content.
 * Best-effort browser synchronization, NOT professional frame lock. */
function normalize_wall(array $data):array {
 $name=trim((string)($data['name']??''));
 if($name===''||mb_strlen($name)>120)throw new InvalidArgumentException('Nombre de muro obligatorio (máximo 120 caracteres)');
 $mode=(string)($data['mode']??'mirror');
 if(!in_array($mode,['mirror','span'],true))throw new InvalidArgumentException('Elige espejo o panorámico');
 $rows=filter_var($data['rows']??null,FILTER_VALIDATE_INT);
 $cols=filter_var($data['cols']??null,FILTER_VALIDATE_INT);
 if(!$rows||!$cols||$rows<1||$cols<1||$rows>4||$cols>4||$rows*$cols>16)throw new InvalidArgumentException('La matriz admite entre 1 y 16 pantallas (máximo 4×4)');
 $screens=$data['screens']??null;
 if(!is_array($screens)||count($screens)!==$rows*$cols)throw new InvalidArgumentException('Selecciona exactamente una pantalla por posición');
 $screens=array_values($screens);
 if(count(array_unique($screens))!==count($screens))throw new InvalidArgumentException('Una pantalla no puede repetirse en el muro');
 foreach($screens as $id)if(!is_string($id)||!preg_match('/^[0-9a-f]{32}$/D',$id))throw new InvalidArgumentException('Identificador de pantalla inválido');
 return compact('name','mode','rows','cols','screens');
}
function wall_for_screen(string $screen,int $org):?array {
 $wall=db_one('SELECT w.id,w.name,w.mode,w.`rows` AS `rows`,w.cols,m.slot,w.updated_at FROM video_walls w JOIN video_wall_members m ON m.wall_id=w.id WHERE m.screen_id=? AND w.org_id=?',[$screen,$org]);
 if(!$wall)return null;
 $rows=(int)$wall['rows'];$cols=(int)$wall['cols'];$slot=(int)$wall['slot'];
 return ['id'=>(int)$wall['id'],'name'=>$wall['name'],'mode'=>$wall['mode'],'rows'=>$rows,'cols'=>$cols,'slot'=>$slot,'row'=>intdiv($slot,$cols),'column'=>$slot%$cols,'revision'=>$wall['updated_at']];
}
function wall_action(string $action,array $p,array $u):never {
 $org=org_id($u);
 if($action==='walls.list'){
  $walls=db_all('SELECT * FROM video_walls WHERE org_id=? ORDER BY name',[$org]);
  foreach($walls as &$wall){$wall['screens']=array_column(db_all('SELECT screen_id FROM video_wall_members WHERE wall_id=? ORDER BY slot',[$wall['id']]),'screen_id');}unset($wall);
  ok(['items'=>$walls]);
 }
 if($action==='walls.save'){
  try{$def=normalize_wall($p);}catch(InvalidArgumentException $e){fail($e->getMessage(),422);}
  $id=(int)($p['id']??0);
  db()->beginTransaction();try{
   if($id&&!db_one('SELECT id FROM video_walls WHERE id=? AND org_id=? FOR UPDATE',[$id,$org]))throw new InvalidArgumentException('Muro no encontrado');
   foreach($def['screens'] as $device){
    if(!db_one('SELECT id FROM screens WHERE id=? AND org_id=? AND paired_at IS NOT NULL FOR UPDATE',[$device,$org]))throw new InvalidArgumentException('La pantalla no está vinculada a este cliente');
    if(db_one('SELECT wall_id FROM video_wall_members WHERE screen_id=? AND wall_id<>?',[$device,$id]))throw new InvalidArgumentException('La pantalla ya pertenece a otro muro');
   }
   if($id){db_run('UPDATE video_walls SET name=?,mode=?,`rows`=?,cols=? WHERE id=? AND org_id=?',[$def['name'],$def['mode'],$def['rows'],$def['cols'],$id,$org]);db_run('DELETE FROM video_wall_members WHERE wall_id=?',[$id]);}
   else{db_run('INSERT INTO video_walls(org_id,name,mode,`rows`,cols) VALUES(?,?,?,?,?)',[$org,$def['name'],$def['mode'],$def['rows'],$def['cols']]);$id=(int)db()->lastInsertId();}
   foreach($def['screens'] as $slot=>$device)db_run('INSERT INTO video_wall_members(wall_id,screen_id,slot) VALUES(?,?,?)',[$id,$device,$slot]);
   db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof InvalidArgumentException)fail($e->getMessage(),422);throw $e;}
  audit($org,$u['email'],'wall.save','wall',(string)$id,['mode'=>$def['mode'],'size'=>$def['rows'].'x'.$def['cols']]);ok(['id'=>$id]);
 }
 if($action==='walls.delete'){
  $id=(int)($p['id']??0);
  db()->beginTransaction();try{
   if(!db_one('SELECT id FROM video_walls WHERE id=? AND org_id=? FOR UPDATE',[$id,$org]))throw new InvalidArgumentException('Muro no encontrado');
   db_run('DELETE FROM video_wall_members WHERE wall_id=?',[$id]);db_run('DELETE FROM video_walls WHERE id=? AND org_id=?',[$id,$org]);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof InvalidArgumentException)fail($e->getMessage(),404);throw $e;}
  audit($org,$u['email'],'wall.delete','wall',(string)$id);ok();
 }
 fail('Acción de muro desconocida',404);
}
