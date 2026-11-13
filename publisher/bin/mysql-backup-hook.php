<?php
declare(strict_types=1);
// Isolated restore provider. UOL enablement requires administrative validation.
// Restore target MUST be a separate isolated server, never the operational MySQL.
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};
$phase='profile';
try {
 need(PHP_SAPI==='cli' && ($argv[1]??'')==='backup' && ($argv[2]??'')==='--profile','Private backup hook only');
 $file=$argv[3]??''; need(is_file($file) && !is_link($file),'Private provider profile required');
 if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Profile must be 0600');
 $c=json(file_get_contents($file));$r=json(stream_get_contents(STDIN));
 umask(0077);
 $attempt=$r['attempt']??'';$candidate=$r['candidate']??[];
 need(preg_match('/^[a-f0-9]{32}$/D',$attempt)===1 && is_dir($r['backup']) && !is_link($r['backup']) &&
     preg_match('/^[a-f0-9]{64}$/D',$candidate['package_sha256']??'')===1 &&
     is_int($candidate['artifact_id']??null),'Invalid backup attempt or release');
 $restore='rumo_restore_sandbox_'.$attempt;
 $ca=$c['restore_ssl_ca']??'';
 need(is_file($ca)&&!is_link($ca) && preg_match('/^[a-zA-Z0-9.-]+$/D',$c['restore_host']??'')===1 &&
     is_int($c['restore_port']??null) && $c['restore_port']>=1 && $c['restore_port']<=65535,
     'Pinned restore TLS endpoint required');
 need(($c['restore_isolation_approved']??false)===true && $c['restore_host']!==$c['source_host'],
     'Restore server must be isolated from production');
 foreach(['source_password_file','source_mysql_defaults','restore_mysql_defaults'] as $key) {
  need(is_file($c[$key]) && !is_link($c[$key]),'Private credential file required');
  if(PHP_OS_FAMILY!=='Windows')need((fileperms($c[$key])&0077)===0,'Credential permissions must be 0600');
 }
 $phase='source-connect';
 $source=new PDO($c['source_dsn'],$c['source_user'],trim(file_get_contents($c['source_password_file'])),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sourceUuid=$source->query('SELECT @@server_uuid')->fetchColumn();
 $targetUuid=$c['restore_uuid']??null;
 $name=$source->query('SELECT DATABASE()')->fetchColumn();
 need($name===$c['source_database'] && $name!==$restore && $sourceUuid===($c['source_uuid']??null) &&
     is_string($sourceUuid) && is_string($targetUuid) && $sourceUuid!==$targetUuid,'Database identity mismatch');
 $phase='source-engines';
 $q=$source->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'");$q->execute([$name]);$tables=$q->fetchAll(PDO::FETCH_ASSOC);need(count($tables)>0,'No source tables');
 foreach($tables as $table)need(strtoupper($table['ENGINE'])==='INNODB','Nontransactional table: consistent snapshot not demonstrated');
 $collation=$source->query('SELECT @@collation_database')->fetchColumn();
 need(preg_match('/^[a-z0-9_]+$/D',$collation)===1,'Unknown source collation');
 $options=['--single-transaction','--quick','--skip-lock-tables','--skip-add-locks','--skip-comments','--no-tablespaces','--set-gtid-purged=OFF','--order-by-primary','--hex-blob','--routines','--triggers','--events'];
 // Credentials are NEVER command-line values; option files are outside web roots.
 $run=static function(array $cmd,string $in,string $out):void {
  $p=proc_open($cmd,[0=>['file',$in,'rb'],1=>['file',$out,'wb'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','ab']],$pipes);
  need(is_resource($p),'Backup command unavailable');$end=microtime(true)+300;$exit=-1;
  do{$s=proc_get_status($p);if(!$s['running']){$exit=$s['exitcode'];break;}if(microtime(true)>$end){proc_terminate($p);break;}usleep(100000);}while(true);
  proc_close($p);need($exit===0,'Backup/isolated restore command failed');
 };
 $null=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
 $tls=['--protocol=TCP','--ssl-mode=VERIFY_IDENTITY','--ssl-ca='.$ca,'--host='.$c['restore_host'],'--port='.$c['restore_port']];
 $restoreMysql=array_merge([$c['mysql_bin'],'--defaults-file='.$c['restore_mysql_defaults']],$tls);
 $restoreDump=array_merge([$c['dump_bin'],'--defaults-file='.$c['restore_mysql_defaults']],$tls);

 $phase='cli-identities';
 foreach([['source',$sourceUuid,$name],['restore',$targetUuid,null]] as [$label,$uuid,$database]) {
  $proof=$r['backup'].'/'.$label.'-identity.txt';
  $cmd=$label==='restore'?$restoreMysql:[$c['mysql_bin'],'--defaults-file='.$c['source_mysql_defaults']];
  $run(array_merge($cmd,['--batch','--skip-column-names',
      ($label==='restore'?'--execute=SELECT CONCAT(@@server_uuid,CHAR(58),@@global.event_scheduler)':'--execute=SELECT CONCAT(@@server_uuid,CHAR(58),DATABASE())')],
      $database===null?[]:[$database]),$null,$proof);
  need(trim(file_get_contents($proof))===$uuid.':'.($database??'DISABLED'),'CLI and PDO database identity mismatch');
 }

 $phase='restore-schema';
 $run(array_merge($restoreMysql,['--execute=CREATE DATABASE '.$restore.' COLLATE '.$collation]),$null,$null);
 $dump=$r['backup'].'/database.sql';$redump=$r['backup'].'/restore-verification.sql';

 $phase='source-dump';
 $run(array_merge([$c['dump_bin'],'--defaults-file='.$c['source_mysql_defaults']],$options,[$name]),$null,$dump);
 need(filesize($dump)>0,'Empty database backup');chmod($dump,0600);
 // Only the explicitly provisioned disposable restore server is written.

 $phase='isolated-import';
 $run(array_merge($restoreMysql,['--binary-mode',$restore]),$dump,$null);

 $phase='isolated-redump';
 $run(array_merge($restoreDump,$options,[$restore]),$null,$redump);

 $phase='roundtrip-hash';
 need(digest($dump)===digest($redump),'Restored dump differs; manual investigation required');
 $restoredAt=time();$restoredHash=digest($redump);
 // Drop only the schema created by this attempt, on the UUID-verified isolated server.

 $phase='restore-cleanup';
 $run(array_merge($restoreMysql,['--execute=DROP DATABASE '.$restore]),$null,$null);
 need(unlink($redump),'Cannot remove verified temporary redump');
 $out=['restored_at'=>$restoredAt,'restored_dump_sha256'=>$restoredHash,'source_uuid'=>$sourceUuid,'restore_uuid'=>$targetUuid,
 'package_sha256'=>$candidate['package_sha256'],'artifact_id'=>$candidate['artifact_id'],'ok'=>true,'attempt'=>$r['attempt'],'consistent'=>true,'restore_tested'=>true,'engine'=>'mysql',
 'files'=>[$dump=>digest($dump)]];
 echo json_encode($out,JSON_THROW_ON_ERROR);
} catch(Throwable $e){fwrite(STDERR,'Backup provider blocked: '.$phase.PHP_EOL);exit(1);}
