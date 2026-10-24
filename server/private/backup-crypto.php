<?php
declare(strict_types=1);
function encryptArchive(string $source,string $target,string $key):void{
  $in=fopen($source,'rb');$out=fopen($target,'wb');[$st,$hdr]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
  fwrite($out,"SSBACK1".$hdr);
  while(!feof($in)){
    $chunk=fread($in,65536);if($chunk==='')break;
    $encrypted=sodium_crypto_secretstream_xchacha20poly1305_push($st,$chunk,'',0);
    fwrite($out,pack('N',strlen($encrypted)).$encrypted);
  }
  $final=sodium_crypto_secretstream_xchacha20poly1305_push($st,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);fwrite($out,pack('N',strlen($final)).$final);
  fclose($in);fclose($out);@chmod($target,0600);
}
function decryptArchive(string $source,string $target,string $key):void{
  $in=fopen($source,'rb');if(!$in)throw new RuntimeException('No existe el archivo cifrado');
  $header=fread($in,31);if(strlen($header)!==31||substr($header,0,7)!=='SSBACK1')throw new RuntimeException('Archivo no reconocido');
  $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($header,7),$key);
  $out=fopen($target,'wb');$final=false;
  while(!feof($in)){
    $sizeRaw=fread($in,4);if($sizeRaw==='')break;
    if(strlen($sizeRaw)!==4)throw new RuntimeException('Copia truncada');
    $size=unpack('N',$sizeRaw)[1];if($size<16||$size>66000)throw new RuntimeException('Bloque cifrado inválido');
    $buf='';while(strlen($buf)<$size&&!feof($in))$buf.=fread($in,$size-strlen($buf));
    if(strlen($buf)!==$size)throw new RuntimeException('Copia truncada');
    $pair=sodium_crypto_secretstream_xchacha20poly1305_pull($state,$buf);
    if($pair===false)throw new RuntimeException('La firma no coincide: clave incorrecta o copia dañada');
    fwrite($out,$pair[0]);if($pair[1]===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL){$final=true;break;}
  }
  $trailing=fread($in,1);fclose($in);fclose($out);if(!$final)throw new RuntimeException('Falta final autenticado');if($trailing!==''&&$trailing!==false)throw new RuntimeException('Hay datos adicionales tras el final autenticado');
}
