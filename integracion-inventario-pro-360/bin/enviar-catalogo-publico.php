<?php
declare(strict_types=1);
/** Private read-only bridge: a public catalogue exported as JSON -> Sector Sign.
 * Run under aaPanel Cron. This does NOT inspect Inventario Pro's internal storage.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Solo CLI');}
require __DIR__.'/../src/SectorSignPublisher.php';
$usage="Uso: SECTOR_SIGN_URL=https://... SECTOR_SIGN_KEY_ID=... SECTOR_SIGN_SECRET=... php enviar-catalogo-publico.php /ruta/privada/catalogo-publico.json\n";
$file=$argv[1]??'';
if($file===''||!is_file($file)||!is_readable($file)){fwrite(STDERR,$usage);exit(2);}
if(filesize($file)>10*1024*1024){fwrite(STDERR,"Catálogo excede 10 MB\n");exit(2);}
try{
 $rows=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
 if(isset($rows['products'])&&is_array($rows['products']))$rows=$rows['products'];
 if(!is_array($rows)||!array_is_list($rows))throw new InvalidArgumentException('Se requiere un arreglo de productos públicos');
 $public=SectorSignPublisher::onlyPublicFields($rows);
 if(!$public)throw new InvalidArgumentException('Sin productos válidos con ID, nombre, precio público y revisión estable');
 $url=getenv('SECTOR_SIGN_URL')?:'';$id=getenv('SECTOR_SIGN_KEY_ID')?:'';$secret=getenv('SECTOR_SIGN_SECRET')?:'';
 if(!$url||!$id||!$secret)throw new InvalidArgumentException('Configura las variables privadas SECTOR_SIGN_URL, SECTOR_SIGN_KEY_ID y SECTOR_SIGN_SECRET');
 $client=new SectorSignPublisher($url,$id,$secret);
 $result=$client->publish($rows);
 echo json_encode(['status'=>'ok','public_products'=>count($public),'result'=>$result],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(STDERR,'Sector Sign: '.$e->getMessage()."\n");exit(1);}
