<?php
declare(strict_types=1);
/** Validated program drafts and immutable publication snapshots. No application Cron is needed. */
function validate_program(array $p):array {
 $name=trim((string)($p['name']??''));if($name===''||mb_strlen($name)>160)throw new InvalidArgumentException('Nombre requerido, máximo 160 caracteres');
 $zone=(string)($p['timezone']??'America/Lima');if(!in_array($zone,DateTimeZone::listIdentifiers(),true))throw new InvalidArgumentException('Zona horaria no válida');
 $rules=$p['rules']??[];if(!is_array($rules)||count($rules)>100)throw new InvalidArgumentException('Máximo 100 franjas');
 $normalized=[];foreach($rules as $i=>$rule){
  if(!is_array($rule))throw new InvalidArgumentException('Franja inválida');
  $ids=array_values(array_unique(array_map('intval',(array)($rule['playlist_ids']??[]))));
  if(!$ids||count($ids)>30||min($ids)<1)throw new InvalidArgumentException('Selecciona entre 1 y 30 listas por franja');
  $sc=$rule['schedule']??[];if(!is_array($sc))throw new InvalidArgumentException('Horario inválido');
  foreach(['start','end'] as $f){if(isset($sc[$f])&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',(string)$sc[$f]))throw new InvalidArgumentException('Hora no válida');}
  if(isset($sc['days'])&&(!is_array($sc['days'])||array_filter($sc['days'],fn($v)=>!is_int($v)||$v<0||$v>6)))throw new InvalidArgumentException('Días no válidos');
  foreach(['from','until'] as $f){if(!empty($sc[$f])){$d=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$sc[$f]);if(!$d||$d->format('Y-m-d')!==$sc[$f])throw new InvalidArgumentException('Fecha no válida');}}
  if(!empty($sc['from'])&&!empty($sc['until'])&&$sc['from']>$sc['until'])throw new InvalidArgumentException('La fecha final es anterior a la inicial');
  $sc=array_intersect_key($sc,array_flip(['start','end','days','from','until']));
  $normalized[]=['id'=>'r'.($i+1),'name'=>mb_substr(trim((string)($rule['name']??('Franja '.($i+1)))),0,120),'priority'=>max(0,min(100,(int)($rule['priority']??0))),'enabled'=>($rule['enabled']??true)!==false,'schedule'=>$sc,'playlist_ids'=>$ids];
 }
 $fallback=max(0,(int)($p['fallback_id']??0));if(!$normalized&&!$fallback)throw new InvalidArgumentException('Añade una franja o una lista de respaldo');
 return ['name'=>$name,'timezone'=>$zone,'rules'=>$normalized,'fallback_id'=>$fallback];
}
function program_ids(array $def):array {
 $ids=[];foreach($def['rules'] as $r)$ids=array_merge($ids,$r['playlist_ids']);if($def['fallback_id'])$ids[]=$def['fallback_id'];return array_values(array_unique($ids));
}
function program_owned_lists(int $org,array $def):void {foreach(program_ids($def) as $id)if(!db_one('SELECT id FROM playlists WHERE id=? AND org_id=?',[$id,$org]))throw new InvalidArgumentException('Una lista no pertenece a este espacio o ya no existe');}
function program_action(string $action,array $p,array $u):never {
 $org=org_id($u);
 if($action==='programs.list'){
  $rows=db_all('SELECT * FROM programs WHERE org_id=? ORDER BY updated_at DESC',[$org]);foreach($rows as &$row){$row['definition']=json_decode($row['definition_json'],true);unset($row['definition_json']);}unset($row);ok(['items'=>$rows]);
 }
 if($action==='programs.save'){
  try{$def=validate_program($p);program_owned_lists($org,$def);}catch(InvalidArgumentException $e){fail($e->getMessage(),422);}
  $id=(int)($p['id']??0);$json=json_encode($def,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if($id){$changed=db_run('UPDATE programs SET name=?,definition_json=?,revision=revision+1 WHERE id=? AND org_id=? AND revision=?',[$def['name'],$json,$id,$org,(int)($p['revision']??0)]);if(!$changed)fail('Otro usuario cambió este programa. Recarga antes de guardar.',409);}
  else{db_run('INSERT INTO programs(org_id,name,definition_json) VALUES(?,?,?)',[$org,$def['name'],$json]);$id=(int)db()->lastInsertId();}
  audit($org,$u['email'],'program.save','program',(string)$id);ok(['id'=>$id,'revision'=>(int)db_one('SELECT revision FROM programs WHERE id=? AND org_id=?',[$id,$org])['revision']]);
 }
 if($action==='programs.publish'){
  $id=(int)($p['id']??0);$targets=$p['screens']??[];if(!is_array($targets)||!count($targets)||count($targets)>500)fail('Selecciona entre 1 y 500 dispositivos');
  db()->beginTransaction();try{
   $row=db_one('SELECT * FROM programs WHERE id=? AND org_id=? FOR UPDATE',[$id,$org]);if(!$row)throw new InvalidArgumentException('Programa no encontrado');
   if((int)($p['revision']??0)!==(int)$row['revision'])throw new InvalidArgumentException('Recarga el programa antes de publicar: su revisión cambió');
   $def=validate_program(json_decode($row['definition_json'],true));program_owned_lists($org,$def);
   $ids=program_ids($def);sort($ids,SORT_NUMERIC);$lists=[];$download=[];
   // Lock list rows in stable order to serialize with editors and concurrent publication.
   foreach($ids as $list){$pl=db_one('SELECT id,name,revision FROM playlists WHERE id=? AND org_id=? FOR UPDATE',[$list,$org]);if(!$pl)throw new InvalidArgumentException('Lista no encontrada');$items=build_manifest($org,$list);if(!$items)throw new InvalidArgumentException('La lista '.$pl['name'].' está vacía');$lists[]=['id'=>(int)$list,'name'=>$pl['name'],'revision'=>(int)$pl['revision'],'items'=>$items];foreach($items as $it)$download[$it['id'].':'.$it['sha256']]=$it;}
   $anchor=$ids[0];$ver=(int)(db_one('SELECT MAX(version) n FROM publications WHERE org_id=? AND playlist_id=?',[$org,$anchor])['n']??0)+1;
   $manifest=['name'=>$def['name'],'type'=>'program','timezone'=>$def['timezone'],'program_id'=>$id,'program_revision'=>(int)$row['revision'],'program'=>$def,'playlists'=>$lists,'items'=>array_values($download)];
   db_run('INSERT INTO publications(org_id,playlist_id,version,manifest_json,created_by) VALUES(?,?,?,?,?)',[$org,$anchor,$ver,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$u['id']]);$pub=(int)db()->lastInsertId();
   foreach(array_unique($targets) as $screen){if(!is_string($screen)||!preg_match('/^[a-f0-9]{32}$/D',$screen)||!db_one('SELECT id FROM screens WHERE id=? AND org_id=? AND paired_at IS NOT NULL',[$screen,$org]))throw new InvalidArgumentException('Pantalla no disponible');db_run('UPDATE screens SET publication_id=? WHERE id=? AND org_id=?',[$pub,$screen,$org]);}
   db_run('UPDATE programs SET last_publication_id=? WHERE id=? AND org_id=?',[$pub,$id,$org]);db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof InvalidArgumentException)fail($e->getMessage(),422);throw $e;}
  audit($org,$u['email'],'program.publish','program',(string)$id,['publication_id'=>$pub,'devices'=>count(array_unique($targets))]);ok(['publication_id'=>$pub,'version'=>$ver]);
 }
 fail('Acción de programación desconocida',404);
}
