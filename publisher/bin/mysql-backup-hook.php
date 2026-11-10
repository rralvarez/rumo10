<?php
declare(strict_types=1);
// Provider reference. Not enabled, connected or homologated for UOL.
// Restore target MUST be a separate isolated server, never the operational MySQL.
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};
try {
 need(PHP_SAPI==='cli' && ($argv[1]??'')==='backup' && ($argv[2]??'')==='--profile','Private backup hook only');
 $file=$argv[3]??''; need(is_file($file) && !is_link($file),'Private provider profile required');
 if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Profile must be 0600');
 $c=json(file_get_contents($file));$r=json(stream_get_contents(STDIN));
 need(($c['restore_isolation_approved']??false) && $c['restore_host']!==$c['source_host'],'Restore server must be isolated from production');
 foreach(['source_password_file','restore_password_file','source_mysql_defaults','restore_mysql_defaults'] as $key) {
  need(is_file($c[$key]) && !is_link($c[$key]),'Private credential file required');
  if(PHP_OS_FAMILY!=='Windows')need((fileperms($c[$key])&0077)===0,'Credential permissions must be 0600');
 }
 $source=new PDO($c['source_dsn'],$c['source_user'],trim(file_get_contents($c['source_password_file'])),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $target=new PDO($c['restore_dsn'],$c['restore_user'],trim(file_get_contents($c['restore_password_file'])),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sourceUuid=$source->query('SELECT @@server_uuid')->fetchColumn();
 $targetUuid=$target->query('SELECT @@server_uuid')->fetchColumn();
 need(is_string($sourceUuid) && is_string($targetUuid) && $sourceUuid!==$targetUuid,'Different database instances must be demonstrated');
 $name=$source->query('SELECT DATABASE()')->fetchColumn();$restore=$target->query('SELECT DATABASE()')->fetchColumn();
 need($name===$c['source_database'] && $restore===$c['restore_database'] && preg_match('/^rumo_restore_sandbox_[a-z0-9_]+$/D',$restore)===1,'Database identity mismatch');
 $q=$source->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'");$q->execute([$name]);$tables=$q->fetchAll(PDO::FETCH_ASSOC);need(count($tables)>0,'No source tables');
 foreach($tables as $table)need(strtoupper($table['ENGINE'])==='INNODB','Nontransactional table: consistent snapshot not demonstrated');
 $q=$target->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');$q->execute([$restore]);need((int)$q->fetchColumn()===0,'Disposable restore database must be empty');
 $options=['--single-transaction','--quick','--skip-lock-tables','--skip-add-locks','--skip-comments','--no-tablespaces','--hex-blob','--routines','--triggers','--events'];
 // Credentials are NEVER command-line values; option files are outside web roots.
 $run=static function(array $cmd,string $in,string $out):void {
  $p=proc_open($cmd,[0=>['file',$in,'rb'],1=>['file',$out,'wb'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','ab']],$pipes);
  need(is_resource($p),'Backup command unavailable');$end=microtime(true)+300;$exit=-1;
  do{$s=proc_get_status($p);if(!$s['running']){$exit=$s['exitcode'];break;}if(microtime(true)>$end){proc_terminate($p);break;}usleep(100000);}while(true);
  proc_close($p);need($exit===0,'Backup/isolated restore command failed');
 };
 $null=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
 foreach([['source',$sourceUuid,$name],['restore',$targetUuid,$restore]] as [$label,$uuid,$database]) {
  $proof=$r['backup'].'/'.$label.'-identity.txt';
  $run([$c['mysql_bin'],'--defaults-extra-file='.$c[$label.'_mysql_defaults'],'--batch','--skip-column-names',
      $database,'--execute=SELECT CONCAT(@@server_uuid,CHAR(58),DATABASE())'],$null,$proof);
  need(trim(file_get_contents($proof))===$uuid.':'.$database,'CLI and PDO database identity mismatch');
 }
 $dump=$r['backup'].'/database.sql';$redump=$r['backup'].'/restore-verification.sql';
 $run(array_merge([$c['dump_bin'],'--defaults-extra-file='.$c['source_mysql_defaults']],$options,[$name]),$null,$dump);
 need(filesize($dump)>0,'Empty database backup');chmod($dump,0600);
 // Only the explicitly provisioned disposable restore server is written.
 $run([$c['mysql_bin'],'--defaults-extra-file='.$c['restore_mysql_defaults'],$restore],$dump,$null);
 $run(array_merge([$c['dump_bin'],'--defaults-extra-file='.$c['restore_mysql_defaults']],$options,[$restore]),$null,$redump);
 need(digest($dump)===digest($redump),'Restored dump differs; manual investigation required');
 $out=['ok'=>true,'attempt'=>$r['attempt'],'consistent'=>true,'restore_tested'=>true,'engine'=>'mysql',
 'files'=>[$dump=>digest($dump),$redump=>digest($redump)]];
 echo json_encode($out,JSON_THROW_ON_ERROR);
} catch(Throwable $e){fwrite(STDERR,'Backup provider blocked'.PHP_EOL);exit(1);}
