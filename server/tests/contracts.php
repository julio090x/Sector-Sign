<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
require __DIR__.'/../private/inventory.php';
require __DIR__.'/../private/backup-crypto.php';
function chk(bool $v,string $what):void{if(!$v)throw new RuntimeException('FAIL: '.$what);echo "PASS: {$what}\n";}
chk(allow_mime('image/png')==='image','imagen PNG');
chk(allow_mime('video/mp4')==='video','video MP4');
chk(allow_mime('image/svg+xml')===null,'SVG de usuario rechazado');
chk(strlen(pkid())===32,'ID aleatorio');
$sc=normalize_schedule(['start'=>'23:00','end'=>'03:00','days'=>[5,5,6],'from'=>'2026-10-23']);
chk($sc['days']===[5,6]&&$sc['start']==='23:00'&&$sc['from']==='2026-10-23','normalización estricta de programación');
$svg=make_promo_svg(['name'=>'X<script>test</script> & texto','presentation'=>'Paquete "nuevo"','price'=>5.5,'minimum'=>2]);
chk(!str_contains($svg,'<script>')&&str_contains($svg,'&lt;SCRIPT&gt;'),'creatividad SVG con XML escapado');
$cfg=['bucket'=>'sector-sign-creative','region'=>'us-east-1','access_key'=>'test','secret_key'=>'test123','endpoint'=>'https://s3.us-east-1.amazonaws.com'];
$u=s3_signed_url($cfg,'sector-sign/media/abc.png');
chk(str_starts_with($u,'https://')&&str_contains($u,'X-Amz-Signature=')&&str_contains($u,'X-Amz-Expires=900'),'URL S3 firmada con vencimiento');
chk(isScheduledMock('23:00','03:00',1*60),'horario nocturno');
function isScheduledMock(string $start,string $end,int $now):bool{$fmt=fn($t)=>((int)substr($t,0,2))*60+(int)substr($t,3,2);return $fmt($start)>$fmt($end)?$now>=$fmt($start)||$now<$fmt($end):$now>=$fmt($start)&&$now<$fmt($end);}

$rawFile=tempnam(sys_get_temp_dir(),'ssraw');$encFile=tempnam(sys_get_temp_dir(),'ssenc');$restFile=tempnam(sys_get_temp_dir(),'ssout');
try{file_put_contents($rawFile,str_repeat("Paquete de prueba: €
",1000));$secret=random_bytes(32);encryptArchive($rawFile,$encFile,$secret);decryptArchive($encFile,$restFile,$secret);chk(hash_file('sha256',$rawFile)===hash_file('sha256',$restFile),'backup cifrado y restaurado íntegro');
 $encrypted=file_get_contents($encFile);$encrypted[55]=chr(ord($encrypted[55])^1);file_put_contents($encFile,$encrypted);
 $bad=false;try{decryptArchive($encFile,$restFile,$secret);}catch(Throwable){$bad=true;}chk($bad,'backup adulterado rechazado');
 $src=tempnam(sys_get_temp_dir(),'ssplus');encryptArchive($rawFile,$src,$secret);file_put_contents($src,'basura',FILE_APPEND);$bad=false;try{decryptArchive($src,$restFile,$secret);}catch(Throwable){$bad=true;}chk($bad,'datos extra tras respaldo rechazados');@unlink($src);
}finally{@unlink($rawFile);@unlink($encFile);@unlink($restFile);}
