<?php
declare(strict_types=1);
/** Read-only ingestion from Inventario Pro 360. No stock, costs, financial or tax records are accepted. */
function integration_secret(string $encrypted):?string {
    if(!extension_loaded('sodium'))return null;
    $raw=base64_decode($encrypted,true);if(!$raw||strlen($raw)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)return null;
    $nonce=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,hash('sha256',config()['secret'],true))?:null;
}
function integration_push():never {
   if($_SERVER['REQUEST_METHOD']!=='POST')fail('Usa POST',405);
   $key=(string)($_SERVER['HTTP_X_SS_KEY']??'');$time=(string)($_SERVER['HTTP_X_SS_TIMESTAMP']??'');$nonce=(string)($_SERVER['HTTP_X_SS_NONCE']??'');$sig=(string)($_SERVER['HTTP_X_SS_SIGNATURE']??'');
   if(!preg_match('/^[0-9a-f]{20}$/',$key)||!ctype_digit($time)||abs(time()-(int)$time)>300||!preg_match('/^[0-9a-f]{32,64}$/',$nonce)||!preg_match('/^[0-9a-f]{64}$/',$sig))fail('Firma incompleta o vencida',401);
   $row=db_one('SELECT * FROM integrations WHERE key_id=? AND enabled=1',[$key]);if(!$row)fail('Integración no autorizada',401);
   $secret=integration_secret($row['encrypted_secret']);if(!$secret)fail('Clave de integración inválida',401);
   $raw=file_get_contents('php://input',false,null,0,1024*1024+1);if(strlen($raw)>1024*1024)fail('Límite: 1 MB por envío',413);
   $expect=hash_hmac('sha256',"{$time}\n{$nonce}\n{$raw}",$secret);
   if(!hash_equals($expect,strtolower($sig)))fail('Firma HMAC incorrecta',401);
   $data=json_decode($raw,true);if(!is_array($data)||!isset($data['products'])||!is_array($data['products'])||count($data['products'])>150)fail('Usa products: array (máximo 150)');
   $org=(int)$row['org_id'];$int=(int)$row['id'];$imported=0;$skipped=0;$changed=[];
   db()->beginTransaction();try{
      db_run('INSERT INTO integration_nonces(integration_id,nonce_hash) VALUES(?,?)',[$int,hash('sha256',$nonce)]);
      foreach($data['products'] as $product){
        if(!is_array($product)){++$skipped;continue;}
        $ext=field($product,'id',160);$name=field($product,'name',190);$presentation=field($product,'presentation',100);
        $price=$product['public_price']??null;$seq=$product['sequence']??null;
        if(!$ext||!$name||!is_numeric($price)||(float)$price<0||(float)$price>9999999999||!is_numeric($seq)||!preg_match('/^\d{1,18}$/',(string)$seq)){++$skipped;continue;}
        $previous=db_one('SELECT source_sequence,media_id FROM catalog_products WHERE integration_id=? AND external_id=? FOR UPDATE',[$int,$ext]);
        if($previous && (int)$previous['source_sequence']>=(int)$seq){++$skipped;continue;}
        $min=isset($product['min_quantity'])&&is_numeric($product['min_quantity'])?max(0,min(999999,(float)$product['min_quantity'])):null;
        $promo=!empty($product['promotional'])?1:0;
        $enabled=!array_key_exists('enabled',$product)||!empty($product['enabled'])?1:0;
        $url=field($product,'image_url',500);if($url!==''&&!validate_public_url($url))$url='';
        db_run('INSERT INTO catalog_products(org_id,integration_id,external_id,name,presentation,public_price,min_quantity,promotional,image_url,source_revision,source_sequence,enabled) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),presentation=VALUES(presentation),public_price=VALUES(public_price),min_quantity=VALUES(min_quantity),promotional=VALUES(promotional),image_url=VALUES(image_url),source_revision=VALUES(source_revision),source_sequence=VALUES(source_sequence),enabled=VALUES(enabled)',[$org,$int,$ext,$name,$presentation,number_format((float)$price,2,'.',''),$min,$promo,$url,field($product,'revision',100),(int)$seq,$enabled]);
        $productId=db_one('SELECT id,media_id FROM catalog_products WHERE integration_id=? AND external_id=?',[$int,$ext]);
        if(!$enabled){
          // Retirada de catálogo: no crear otro anuncio con un precio antiguo.
          // El cambio sí activa publicación para sustituir campañas obsoletas.
          $changed[]='disabled:'.$ext;++$imported;continue;
        }
        $mid=pkid(); // Immutable new asset for every public-price revision. Old campaigns retain old IDs.
        $creative=make_promo_svg(['name'=>$name,'presentation'=>$presentation,'price'=>(float)$price,'minimum'=>$min]);
        $stored=$mid.'.svg';$full=media_dir().'/'.$stored;file_put_contents($full,$creative,LOCK_EX);
        $storage=storage_save($full,$stored,'image/svg+xml');
        $newSha=hash('sha256',$creative);
        db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256,backend,object_key) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name),bytes=VALUES(bytes),sha256=VALUES(sha256),backend=VALUES(backend),object_key=VALUES(object_key),deleted_at=NULL',[$mid,$org,'Publicidad '.$name,$stored,'image/svg+xml','image',strlen($creative),$newSha,$storage['backend'],$storage['object_key']??null]);
        if($storage['backend']!=='local')@unlink($full);
        db_run('UPDATE catalog_products SET media_id=? WHERE id=?',[$mid,$productId['id']]);
        $changed[]=$mid;++$imported;
      }
      db_run('UPDATE integrations SET last_sync_at=UTC_TIMESTAMP() WHERE id=?',[$int]);
      db()->commit();
   }catch(PDOException $e){if(db()->inTransaction())db()->rollBack();if($e->getCode()==='23000')fail('Solicitud repetida o dato duplicado',409);throw $e;}
    catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
   $publishState='not_requested';
   if(!empty($row['auto_publish'])&&$changed){
      try{auto_publish_inventory($row);$publishState='published';}
      catch(Throwable $e){log_error($e);$publishState='failed';}
   }
   audit($org,'integration:'.$row['name'],'inventory.sync','integration',(string)$int,['imported'=>$imported,'skipped'=>$skipped,'auto_publish'=>$publishState]);
   ok(['imported'=>$imported,'skipped'=>$skipped,'auto_publish'=>$publishState,'notice'=>'Solo se admitieron datos comerciales públicos. Comprueba las publicaciones si auto_publish=failed.']);
}
function make_promo_svg(array $p):string {
  $safe=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');
  $name=mb_strtoupper((string)$p['name'],'UTF-8');
  // Deterministic creative; the names come from catalog but XML is escaped, no user SVG is accepted.
  $line1=$safe(mb_substr($name,0,31));$line2=$safe(mb_substr($name,31,31));
  $pres=$safe(mb_substr((string)$p['presentation'],0,54));
  $price=$safe(number_format((float)$p['price'],2,'.',''));
  $minimum=($p['minimum']!==null&&$p['minimum']>1)?'<text x="75" y="589" fill="#faffff" font-size="34" font-weight="700">DESDE '.$safe($p['minimum']).' UNIDADES</text>':'';
  return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
<defs><linearGradient id="g" x2="1" y2="1"><stop stop-color="#0D5BD6"/><stop offset="1" stop-color="#073779"/></linearGradient></defs>
<rect width="1280" height="720" fill="url(#g)"/><circle cx="1130" cy="85" r="280" fill="#fff" opacity=".08"/>
<rect x="42" y="42" width="1196" height="636" rx="40" fill="#ffffff" opacity=".06"/>
<text x="75" y="130" fill="#d6f8ff" font-family="sans-serif" font-size="34" font-weight="800">OFERTA DEL DÍA</text>
<text x="75" y="230" fill="#fff" font-family="sans-serif" font-size="64" font-weight="900">{$line1}</text>
<text x="75" y="304" fill="#fff" font-family="sans-serif" font-size="56" font-weight="800">{$line2}</text>
<text x="75" y="377" fill="#d8e9ff" font-family="sans-serif" font-size="35">{$pres}</text>
<rect x="67" y="419" width="710" height="196" rx="33" fill="#fff"/>
<text x="105" y="551" fill="#10326f" font-family="sans-serif" font-size="100" font-weight="900">S/ {$price}</text>
{$minimum}
<text x="75" y="651" fill="#d2efff" font-family="sans-serif" font-size="25">Promoción sujeta a disponibilidad · Precio publicado</text>
</svg>
SVG;
}
function auto_publish_inventory(array $row):void{
  $org=(int)$row['org_id'];$int=(int)$row['id'];$name='Promociones · '.$row['name'];
  $pl=db_one('SELECT id FROM playlists WHERE org_id=? AND name=?',[$org,$name]);
  if(!$pl){db_run('INSERT INTO playlists(org_id,name,description) VALUES(?,?,?)',[$org,$name,'Actualizada desde Inventario Pro 360']);$pl=['id'=>(int)db()->lastInsertId()];}
  $playlist=(int)$pl['id'];
  $products=db_all('SELECT media_id FROM catalog_products WHERE integration_id=? AND org_id=? AND promotional=1 AND enabled=1 AND media_id IS NOT NULL ORDER BY name LIMIT 100',[$int,$org]);
  if(!$products){
    // No active promotions: publish a neutral screen rather than an obsolete price.
    $mid=pkid();$stored=$mid.'.svg';
    $creative='<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720"><rect width="1280" height="720" fill="#092c56"/><text x="640" y="300" text-anchor="middle" font-family="sans-serif" fill="white" font-weight="bold" font-size="68">PRÓXIMAS PROMOCIONES</text><text x="640" y="390" text-anchor="middle" font-family="sans-serif" fill="#bedaff" font-size="36">Consulta nuestras novedades en tienda</text></svg>';
    $full=media_dir().'/'.$stored;
    if(file_put_contents($full,$creative,LOCK_EX)===false)throw new RuntimeException('No se pudo crear el anuncio neutro');
    $remote=storage_save($full,$stored,'image/svg+xml');
    db_run('INSERT INTO media(id,org_id,original_name,stored_name,mime,kind,bytes,sha256,backend,object_key) VALUES(?,?,?,?,?,?,?,?,?,?)',[$mid,$org,'Sin promociones activas',$stored,'image/svg+xml','image',strlen($creative),hash('sha256',$creative),$remote['backend'],$remote['object_key']??null]);
    if($remote['backend']!=='local')@unlink($full);
    $products=[['media_id'=>$mid]];
  }
  db()->beginTransaction();try{
    db_one('SELECT id FROM playlists WHERE id=? AND org_id=? FOR UPDATE',[$playlist,$org]);
    db_run('DELETE FROM playlist_items WHERE playlist_id=?',[$playlist]);
    foreach($products as $i=>$prod)db_run('INSERT INTO playlist_items(playlist_id,media_id,position,duration_sec,fit,transition,schedule_json) VALUES(?,?,?,8,"contain","fade","{}")',[$playlist,$prod['media_id'],$i]);
    db_run('UPDATE playlists SET revision=revision+1 WHERE id=?',[$playlist]);
    $version=(int)(db_one('SELECT MAX(version) n FROM publications WHERE playlist_id=?',[$playlist])['n']??0)+1;
    $admin=db_one('SELECT id FROM users WHERE org_id=? AND role="admin" ORDER BY id LIMIT 1',[$org]);
    db_run('INSERT INTO publications(org_id,playlist_id,version,manifest_json,created_by) VALUES(?,?,?,?,?)',[$org,$playlist,$version,json_encode(['name'=>$name,'items'=>build_manifest($org,$playlist)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$admin['id']??1]);
    $newId=(int)db()->lastInsertId();
    db_run('UPDATE screens SET publication_id=? WHERE org_id=? AND publication_id IN (SELECT id FROM publications WHERE playlist_id=? AND id<>?)',[$newId,$org,$playlist,$newId]);
    db()->commit();
  }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
