<?php
declare(strict_types=1);
// Demo: replace public-products.json with an authenticated, READ-ONLY adapter for Inventario Pro 360.
// Do not expose this script on the web. Run from an approved CLI/Cron context.
if(PHP_SAPI!=='cli')die('Solo CLI');
require __DIR__.'/../src/SectorSignPublisher.php';
$url=getenv('SECTOR_SIGN_URL');$key=getenv('SECTOR_SIGN_KEY_ID');$secret=getenv('SECTOR_SIGN_SECRET');
if(!$url||!$key||!$secret)die("Faltan variables SECTOR_SIGN_URL, SECTOR_SIGN_KEY_ID, SECTOR_SIGN_SECRET\n");
$data=json_decode(file_get_contents(__DIR__.'/productos-publicos.json'),true,512,JSON_THROW_ON_ERROR);
$publisher=new SectorSignPublisher($url,$key,$secret);
$result=$publisher->publish($data);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
