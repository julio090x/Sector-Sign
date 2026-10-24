<?php
declare(strict_types=1);
/**
 * Inventario Pro 360 → Sector Sign. Read-only publisher; no DB access required.
 * Do NOT put this file in public/documental or fiscal routes. Do not export cost, margin, taxes, stock, customers.
 */
final class SectorSignPublisher {
  private string $endpoint;private string $keyId;private string $secret;
  public function __construct(string $baseUrl,string $keyId,string $secret) {
    if(!filter_var($baseUrl,FILTER_VALIDATE_URL)||parse_url($baseUrl,PHP_URL_SCHEME)!=='https')throw new InvalidArgumentException('Sector Sign requiere HTTPS público');
    if(!preg_match('/^[0-9a-f]{20}$/',$keyId)||!preg_match('/^[0-9a-f]{64}$/',$secret))throw new InvalidArgumentException('Clave inválida');
    $this->endpoint=rtrim($baseUrl,'/').'/api.php?action=integration.push';$this->keyId=$keyId;$this->secret=$secret;
  }
  private static function cut(string $text,int $max):string{return function_exists('mb_substr')?mb_substr($text,0,$max):implode('',array_slice(preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[],0,$max));}
  public static function onlyPublicFields(array $rows):array{
    $result=[];
    foreach($rows as $p){
      if(!is_array($p))continue;
      $id=trim((string)($p['public_id']??$p['id']??''));$name=trim((string)($p['public_name']??$p['name']??''));$price=$p['public_price']??null;
      if($id===''||$name===''||!is_numeric($price)||(float)$price<0)continue;
      // Should be a monotonically increasing PUBLIC catalog revision per product.
      $rev=$p['public_revision']??$p['public_updated_at']??$p['updated_at']??null;
      if($rev===null)continue; // Sin revisión estable podrían generarse anuncios idénticos en cada sincronización.
      $sequence=is_numeric($rev)?(int)$rev:(int)(strtotime((string)$rev)?:time());
      $result[]=['id'=>self::cut($id,160),'name'=>self::cut($name,190),'presentation'=>self::cut((string)($p['public_presentation']??$p['presentation']??''),100),'public_price'=>round((float)$price,2),'min_quantity'=>isset($p['public_min_quantity'])?(float)$p['public_min_quantity']:null,'promotional'=>!empty($p['public_promotion']),'enabled'=>!array_key_exists('public_enabled',$p)||!empty($p['public_enabled']),'sequence'=>max(1,$sequence),'revision'=>(string)max(1,$sequence)];
    }
    return $result;
  }
  public function signedRequest(array $publicProducts,int $timestamp=null,string $nonce=null):array {
    $timestamp??=time();$nonce??=bin2hex(random_bytes(20));
    $body=json_encode(['products'=>$publicProducts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $signature=hash_hmac('sha256',$timestamp."\n".$nonce."\n".$body,$this->secret);
    return ['body'=>$body,'headers'=>['X-SS-Key: '.$this->keyId,'X-SS-Timestamp: '.$timestamp,'X-SS-Nonce: '.$nonce,'X-SS-Signature: '.$signature]];
  }
  public function publish(array $rows):array {
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL no está instalado');
    $results=[];$safe=self::onlyPublicFields($rows);
    foreach(array_chunk($safe,100) as $batch){
      $signed=$this->signedRequest($batch);
      $ch=curl_init($this->endpoint);
      curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>array_merge($signed['headers'],['Content-Type: application/json']),CURLOPT_POSTFIELDS=>$signed['body'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);
      $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
      if($raw===false||$code<200||$code>=300)throw new RuntimeException('Sector Sign respondió HTTP '.$code.' '.$error.' '.self::cut((string)$raw,160));
      $out=json_decode((string)$raw,true);if(!is_array($out)||empty($out['ok']))throw new RuntimeException('Respuesta inválida del receptor');
      $results[]=$out;
    }
    return ['batches'=>count($results),'sent'=>count($safe),'details'=>$results];
  }
}
