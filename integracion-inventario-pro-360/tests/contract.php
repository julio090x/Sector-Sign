<?php
declare(strict_types=1);
require __DIR__.'/../src/SectorSignPublisher.php';
$rows=[['id'=>'p1','name'=>'Producto de prueba','public_price'=>2.5,'cost'=>1.0,'stock'=>28,'ruc'=>'11111111111','public_enabled'=>false,'updated_at'=>'2026-10-24T00:00:00-05:00']];
$public=SectorSignPublisher::onlyPublicFields($rows);
if(count($public)!==1||$public[0]['enabled']!==false||isset($public[0]['cost'])||isset($public[0]['stock'])||isset($public[0]['ruc']))throw new RuntimeException('Fuga de campo interno');
$publisher=new SectorSignPublisher('https://sign.example.org','0123456789abcdef1234',str_repeat('a',64));
$sent=$publisher->signedRequest($public,1700000000,str_repeat('b',40));
$headers=$sent['headers'];$signature=substr($headers[3],strlen('X-SS-Signature: '));
$expected=hash_hmac('sha256',"1700000000\n".str_repeat('b',40)."\n".$sent['body'],str_repeat('a',64));
if(!hash_equals($signature,$expected))throw new RuntimeException('Firma HMAC inválida');
echo "PASS: API de solo campos públicos, firma HMAC e idempotencia de revisión\n";
