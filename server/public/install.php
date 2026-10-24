<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
if(installed()){header('Location: /');exit;}
if(!extension_loaded('pdo_mysql'))$notice='Falta la extensión pdo_mysql. Actívala en aaPanel → PHP → extensiones.';
else $notice='';
$keyFile=__DIR__.'/../private/install.key';
if(!is_file($keyFile)){
    if(!is_writable(dirname($keyFile)))die('Da permiso de escritura a la carpeta private para iniciar instalación.');
    file_put_contents($keyFile,bin2hex(random_bytes(20)),LOCK_EX);@chmod($keyFile,0600);
}
start_session();
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $token=(string)($_POST['install_key']??'');
    if(!hash_equals(trim((string)file_get_contents($keyFile)),$token))throw new RuntimeException('Código de instalación incorrecto.');
    if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??'')))throw new RuntimeException('Recarga la página e intenta nuevamente.');
    if(!extension_loaded('pdo_mysql'))throw new RuntimeException('Activa pdo_mysql en PHP.');
    $dbName=trim((string)($_POST['db_name']??''));$dbHost=trim((string)($_POST['db_host']??'127.0.0.1'));
    $dbUser=trim((string)($_POST['db_user']??''));$dbPass=(string)($_POST['db_pass']??'');$dbPort=(int)($_POST['db_port']??3306);
    if(!preg_match('/^[\w-]{1,64}$/',$dbName)||!preg_match('/^[a-zA-Z0-9_.:-]{1,190}$/',$dbHost)||!preg_match('/^[\w.-]{1,90}$/',$dbUser)||$dbPort<1||$dbPort>65535)throw new RuntimeException('Revisa la conexión MySQL.');
    $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$pw=(string)($_POST['password']??'');$org=trim((string)($_POST['organization']??'Mi empresa'));
    if(mb_strlen($name)<2||mb_strlen($org)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<12||strlen($pw)>200)throw new RuntimeException('Ingresa nombre y correo válidos y una contraseña de 12 caracteres como mínimo.');
    if(!hash_equals($pw,(string)($_POST['password2']??'')))throw new RuntimeException('Las contraseñas no coinciden.');
    $base=trim((string)($_POST['base_url']??''));
    if(!filter_var($base,FILTER_VALIDATE_URL)||!in_array(parse_url($base,PHP_URL_SCHEME),['https','http'],true))throw new RuntimeException('Indica la URL de tu instalación.');
    $h=(string)parse_url($base,PHP_URL_HOST);$isLocal=in_array($h,['localhost','127.0.0.1','::1'],true)||(filter_var($h,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)!==false&&filter_var($h,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false);
    if(parse_url($base,PHP_URL_SCHEME)!=='https'&&!$isLocal)throw new RuntimeException('Activa HTTPS para un dominio público antes de instalar.');
    $pdo=new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $sql=file_get_contents(__DIR__.'/../sql/schema.mysql.sql');
    foreach(explode(';',$sql) as $statement){$statement=trim(preg_replace('/^--[^\n]*\n/m','',(string)$statement));if($statement!=='')$pdo->exec($statement);}
    $existing=(int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();if($existing>0)throw new RuntimeException('Esta base ya contiene datos. Usa una base de datos vacía para evitar sobrescribir otra instalación.');
    $pdo->beginTransaction();
    $st=$pdo->prepare('INSERT INTO organizations(name,timezone) VALUES(?,?)');$st->execute([mb_substr($org,0,150),'America/Lima']);$orgId=(int)$pdo->lastInsertId();
    $st=$pdo->prepare('INSERT INTO users(org_id,name,email,password_hash,role) VALUES(?,?,?,?,?)');$st->execute([$orgId,mb_substr($name,0,120),$email,password_hash($pw,PASSWORD_DEFAULT),'admin']);
    $pdo->commit();
    $cfg=['db_host'=>$dbHost,'db_port'=>$dbPort,'db_name'=>$dbName,'db_user'=>$dbUser,'db_pass'=>$dbPass,'secret'=>bin2hex(random_bytes(32)),'backup_key'=>bin2hex(random_bytes(32)),'base_url'=>rtrim($base,'/'),'db_sessions'=>true,'storage'=>['driver'=>'local'],'oauth'=>['google'=>['client_id'=>'','client_secret'=>''],'microsoft'=>['client_id'=>'','client_secret'=>'']],'backup'=>['driver'=>'local','keep'=>7]];
    $temp=__DIR__.'/../private/config.php.tmp';
    if(file_put_contents($temp,"<?php\nreturn ".var_export($cfg,true).";\n",LOCK_EX)===false)throw new RuntimeException('No se pudo guardar la configuración.');
    @chmod($temp,0600);rename($temp,__DIR__.'/../private/config.php');
    @unlink($keyFile);file_put_contents(__DIR__.'/../private/install.lock',date(DATE_ATOM));
    session_regenerate_id(true);$_SESSION=[];header('Location: /?installed=1');exit;
  }catch(Throwable $e){$err=$e->getMessage();error_log('[SectorSign install] '.$err);}
}
$host=($_SERVER['HTTP_HOST']??'tudominio.com');$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
function e(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar Sector Sign</title><style>body{background:#f4f7fb;color:#182638;font:16px system-ui;margin:0;padding:30px 12px}main{max-width:760px;margin:auto;background:white;padding:clamp(20px,4vw,40px);border-radius:22px;box-shadow:0 9px 40px #2341a019}h1{margin-top:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{font-size:14px;font-weight:700;display:grid;gap:7px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #c8d1e0;border-radius:9px;font:inherit}input:focus{outline:3px solid #b6d2ff}button{border:0;background:#204caa;color:white;padding:15px 20px;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;margin-top:20px}.tip{background:#e8f1ff;padding:14px;border-radius:10px}.error{background:#fff2f0;color:#af2424;padding:14px;border-radius:9px}@media(max-width:600px){.grid{grid-template-columns:1fr}}</style></head><body><main><h1>Configurar Sector Sign</h1><p>Solo necesitas crear una base de datos MySQL vacía en aaPanel. La instalación genera las tablas, la cuenta principal y las claves internas.</p><div class="tip"><b>Seguridad:</b> abre el archivo <code>server/private/install.key</code> desde el administrador de archivos de aaPanel y copia su código. No se publica en la web.</div><?php if($notice):?><p class="error"><?=e($notice)?></p><?php endif;?><?php if($err):?><p class="error"><?=e($err)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><h3>Protección inicial</h3><label>Código privado de instalación<input name="install_key" required autocomplete="off"></label><h3>Base de datos</h3><div class="grid"><label>Servidor MySQL<input name="db_host" value="127.0.0.1" required></label><label>Puerto<input name="db_port" value="3306" inputmode="numeric" required></label><label>Nombre de la base<input name="db_name" required></label><label>Usuario MySQL<input name="db_user" required></label><label>Contraseña MySQL<input type="password" name="db_pass" autocomplete="new-password"></label><label>Dirección del sitio<input name="base_url" value="<?=e($scheme.'://'.$host)?>" required></label></div><h3>Administrador y negocio</h3><div class="grid"><label>Nombre del negocio<input name="organization" value="Sector Sign" required></label><label>Tu nombre<input name="name" required></label><label>Correo<input type="email" name="email" required></label><label>Contraseña (mínimo 12 caracteres)<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Repetir contraseña<input type="password" name="password2" minlength="12" required></label></div><button type="submit">Instalar de forma segura</button></form></main></body></html>
