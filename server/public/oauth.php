<?php
declare(strict_types=1);
require __DIR__.'/../private/bootstrap.php';
function oauth_error(string $msg):never{header('Location: /?auth_error='.rawurlencode($msg),true,302);exit;}
try{
 if(!installed())oauth_error('Sistema sin instalar');
 start_session();$provider=(string)($_GET['provider']??'');if(!in_array($provider,['google','microsoft'],true))oauth_error('Proveedor desconocido');
 $cfg=config()['oauth'][$provider]??[];if(empty($cfg['client_id'])||empty($cfg['client_secret']))oauth_error('Proveedor todavía no configurado');
 if(!extension_loaded('curl'))oauth_error('Activa extensión PHP cURL');
 $redirect=config()['base_url'].'/oauth.php?provider='.$provider;
 if(isset($_GET['code'])||isset($_GET['error'])){
   if(isset($_GET['error']))oauth_error('Acceso cancelado por el proveedor');
   $state=(string)($_GET['state']??'');
   if(!preg_match('/^[a-f0-9]{64}$/',$state)||empty($_SESSION['oauth_state'])||!hash_equals($_SESSION['oauth_state'],hash('sha256',$state)))oauth_error('Estado de acceso no válido');
   $row=db_one('SELECT * FROM oauth_states WHERE state_hash=? AND provider=? AND expires_at>UTC_TIMESTAMP()',[hash('sha256',$state),$provider]);
   unset($_SESSION['oauth_state']);db_run('DELETE FROM oauth_states WHERE state_hash=?',[hash('sha256',$state)]);
   if(!$row)oauth_error('Acceso vencido');
   $tokenUrl=$provider==='google'?'https://oauth2.googleapis.com/token':'https://login.microsoftonline.com/common/oauth2/v2.0/token';
   $payload=['code'=>$_GET['code'],'client_id'=>$cfg['client_id'],'client_secret'=>$cfg['client_secret'],'redirect_uri'=>$redirect,'grant_type'=>'authorization_code','code_verifier'=>$row['verifier']];
   $ch=curl_init($tokenUrl);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true]);
   $res=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
   $token=json_decode((string)$res,true);if($status!==200||empty($token['access_token']))oauth_error('Error en validación del acceso');
   $profileUrl=$provider==='google'?'https://openidconnect.googleapis.com/v1/userinfo':'https://graph.microsoft.com/oidc/userinfo';
   $ch=curl_init($profileUrl);curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token['access_token']],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8]);
   $profRaw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
   $profile=json_decode((string)$profRaw,true);
   if($code!==200||empty($profile['sub']))oauth_error('No pudimos verificar la identidad del proveedor');
   $sub=(string)$profile['sub'];$email=strtolower(trim((string)($profile['email']??'')));
   // External emails are never used to implicitly take over an existing Sector Sign account.
   $verified=$provider==='google'&&($profile['email_verified']??false)===true;
   $column=$provider==='google'?'oauth_google':'oauth_microsoft';
   $mode=$row['redirect_path']==='link'?'link':'login';
   if($mode==='link'){
      $user=current_user();$initiator=(int)($_SESSION['oauth_link_uid']??0);unset($_SESSION['oauth_link_uid']);
      if(!$user||$initiator!==(int)$user['id'])oauth_error('La sesión cambió: vuelve a iniciar la vinculación');
      if(db_one("SELECT id FROM users WHERE {$column}=? AND id<>?",[$sub,$user['id']]))oauth_error('Esta cuenta externa ya está vinculada');
      db_run("UPDATE users SET {$column}=? WHERE id=?",[$sub,$user['id']]);audit(org_id($user),$user['email'],'oauth.link',$provider);header('Location: /?linked='.$provider);exit;
   }
   $user=db_one("SELECT id,org_id,email,session_epoch FROM users WHERE {$column}=? AND enabled=1",[$sub]);
   // Linking by email is intentionally forbidden even if the provider reports verified=true.
   // Log in with password and link from the settings page instead.
   if(!$user)oauth_error('Cuenta no vinculada. Entra con tu contraseña y enlázala en Ajustes.');
   session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['session_epoch']=(int)$user['session_epoch'];$_SESSION['csrf']=bin2hex(random_bytes(24));
   audit((int)$user['org_id'],$user['email'],'oauth.login',$provider);header('Location: /');exit;
 }
 $mode=($_GET['mode']??'login')==='link'?'link':'login';
 if($mode==='link'){ $u=current_user();if(!$u)oauth_error('Debes iniciar sesión antes de vincular tu cuenta');$_SESSION['oauth_link_uid']=(int)$u['id']; }
 $state=bin2hex(random_bytes(32));$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
 $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
 db_run('INSERT INTO oauth_states(state_hash,provider,verifier,redirect_path,expires_at) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))',[hash('sha256',$state),$provider,$verifier,$mode]);
 $_SESSION['oauth_state']=hash('sha256',$state);
 $params=['client_id'=>$cfg['client_id'],'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>$provider==='google'?'openid profile email':'openid profile email','state'=>$state,'code_challenge'=>$challenge,'code_challenge_method'=>'S256'];
 if($provider==='google')$params['prompt']='select_account';
 $authorize=$provider==='google'?'https://accounts.google.com/o/oauth2/v2/auth':'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
 header('Location: '.$authorize.'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986));exit;
}catch(Throwable $e){log_error($e);oauth_error('Servicio de inicio de sesión no disponible');}
