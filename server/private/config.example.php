<?php
// EJEMPLO SOLAMENTE. No incluyas contraseñas reales en el ZIP ni en Git.
// /install.php crea private/config.php automáticamente con claves aleatorias.
return [
 'db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>'sector_sign',
 'db_user'=>'sector_sign','db_pass'=>'REEMPLAZAR',
 'secret'=>'CLAVE_GENERADA_POR_EL_INSTALADOR','backup_key'=>'CLAVE_RECUPERACION_GENERADA',
 'base_url'=>'https://sign.ejemplo.com','db_sessions'=>true,
 'storage'=>['driver'=>'local'], // or ['driver'=>'s3','endpoint'=>'https://s3.us-east-1.amazonaws.com','bucket'=>'...','region'=>'us-east-1','access_key'=>'...','secret_key'=>'...','cdn_url'=>'']
 // or ['driver'=>'ftps','host'=>'ftp.ejemplo.com','port'=>21,'username'=>'...','password'=>'...','directory'=>'/public_html','public_base_url'=>'https://media.ejemplo.com']
 'oauth'=>[
   'google'=>['client_id'=>'','client_secret'=>''],
   'microsoft'=>['client_id'=>'','client_secret'=>''],
 ],
 'backup'=>['keep'=>3], // aaPanel Cron is recommended for Google Drive, S3 and FTP offsite copies.
];
