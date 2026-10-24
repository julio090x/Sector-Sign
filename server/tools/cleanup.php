<?php
declare(strict_types=1);
// php tools/cleanup.php — safe, idempotent cleanup for aaPanel Cron; no actual business advertisements removed.
if(PHP_SAPI!=='cli')die('Solo CLI');
require __DIR__.'/../private/bootstrap.php';
try{
 $old=db_all('SELECT id,stored_name,backend FROM media WHERE deleted_at IS NOT NULL AND deleted_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)');
 foreach($old as $m){
   $ref=db_one('SELECT COUNT(*) c FROM playlist_items WHERE media_id=?',[$m['id']]);
   $hist=db_one('SELECT COUNT(*) c FROM publications WHERE manifest_json LIKE ?', ['%"id":"'.$m['id'].'"%']);
   if((int)$ref['c']>0||(int)$hist['c']>0)continue;
   if($m['backend']==='local')@unlink(media_dir().'/'.$m['stored_name']);
   db_run('DELETE FROM media WHERE id=?',[$m['id']]);
 }
 $n=db_run('DELETE FROM login_attempts WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)');
 db_run('DELETE FROM auth_tokens WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)');
 db_run('DELETE FROM oauth_states WHERE expires_at<UTC_TIMESTAMP()');
 db_run('DELETE FROM integration_nonces WHERE seen_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
 db_run('DELETE FROM web_sessions WHERE expires_at<UTC_TIMESTAMP()');
 db_run('DELETE FROM screens WHERE org_id IS NULL AND paired_at IS NULL AND pair_expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
 echo "Cleanup completed; transient login rows: {$n}\n";
}catch(Throwable $e){log_error($e);fwrite(STDERR,'Cleanup error: '.$e->getMessage()."\n");exit(1);}
