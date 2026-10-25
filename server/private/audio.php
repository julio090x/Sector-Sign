<?php
declare(strict_types=1);
/** Player audio/narration contracts; no TTS service or secret required for local voices. */
function normalize_clip_audio(array $raw,int $org):array {
 $id=trim((string)($raw['media_id']??''));
 if($id!=='' && (!preg_match('/^[0-9a-f]{32}$/D',$id)||!db_one('SELECT id FROM media WHERE id=? AND org_id=? AND kind="audio" AND deleted_at IS NULL',[$id,$org])))
   throw new InvalidArgumentException('La pista de audio no pertenece a este espacio');
 $mode=in_array((string)($raw['mode']??'mix'),['mix','replace'],true)?(string)($raw['mode']??'mix'):'mix';
 $voice=is_array($raw['voice']??null)?$raw['voice']:[];
 $text=trim(preg_replace('/[\x00-\x1F\x7F]/u',' ',(string)($voice['text']??''))??'');
 if(mb_strlen($text)>750)throw new InvalidArgumentException('La narración admite máximo 750 caracteres');
 $lang=(string)($voice['lang']??'es-PE');if(!in_array($lang,['es-PE','es-ES','en-US','pt-BR'],true))throw new InvalidArgumentException('Idioma de voz no admitido');
 $rate=max(0.65,min(1.5,(float)($voice['rate']??1)));
 return ['media_id'=>$id,'mode'=>$mode,'volume'=>max(0,min(100,(int)($raw['volume']??85))),
   'voice'=>['text'=>$text,'lang'=>$lang,'rate'=>round($rate,2),'enabled'=>($voice['enabled']??($text!==''))!==false&&$text!=='','caption'=>($voice['caption']??true)!==false]];
}
function normalize_soundtrack(array $raw,int $org):array {
 $id=trim((string)($raw['media_id']??''));
 if($id!==''&&(!preg_match('/^[0-9a-f]{32}$/D',$id)||!db_one('SELECT id FROM media WHERE id=? AND org_id=? AND kind="audio" AND deleted_at IS NULL',[$id,$org])))
   throw new InvalidArgumentException('Música de fondo no disponible');
 return ['media_id'=>$id,'volume'=>max(0,min(100,(int)($raw['volume']??35)))];
}
function audio_meta(int $org,string $id):?array {
 if(!preg_match('/^[0-9a-f]{32}$/D',$id))return null;
 $a=db_one('SELECT id,original_name,mime,kind,bytes,sha256 FROM media WHERE id=? AND org_id=? AND kind="audio" AND deleted_at IS NULL',[$id,$org]);
 return $a?['id'=>$a['id'],'name'=>$a['original_name'],'mime'=>$a['mime'],'kind'=>'audio','bytes'=>(int)$a['bytes'],'sha256'=>$a['sha256']]:null;
}
function playlist_soundtrack(int $org,int $id):array {
 $row=db_one('SELECT soundtrack_json FROM playlists WHERE id=? AND org_id=?',[$id,$org]);
 return $row?(json_decode((string)$row['soundtrack_json'],true)?:[]):[];
}
function audio_assets(int $org,array $items,array $music=[]):array {
 $ids=[];foreach($items as $it){$a=$it['audio']??[];if(!empty($a['media_id']))$ids[]=$a['media_id'];}
 if(!empty($music['media_id']))$ids[]=$music['media_id'];
 $assets=[];foreach(array_unique($ids) as $id){$a=audio_meta($org,$id);if($a)$assets[]=$a;}
 return $assets;
}
