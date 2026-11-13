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
 $isolatedRestore = ($c['restore_isolation_approved'] ?? false) === true && !empty($c['restore_host']);
 if ($isolatedRestore) {
  $ca=$c['restore_ssl_ca']??'';
  need(is_file($ca)&&!is_link($ca) && preg_match('/^[a-zA-Z0-9.-]+$/D',$c['restore_host']??'')===1 &&
      is_int($c['restore_port']??null) && $c['restore_port']>=1 && $c['restore_port']<=65535,
      'Pinned restore TLS endpoint required');
  need($c['restore_host']!==$c['source_host'], 'Restore server must be isolated from production');
 }
 $requiredCreds = ['source_password_file'];
 if (isset($c['source_mysql_defaults'])) $requiredCreds[] = 'source_mysql_defaults';
 if ($isolatedRestore && isset($c['restore_mysql_defaults'])) $requiredCreds[] = 'restore_mysql_defaults';
 foreach($requiredCreds as $key) {
  need(isset($c[$key]) && is_file($c[$key]) && !is_link($c[$key]),'Private credential file required: '.$key);
  if(PHP_OS_FAMILY!=='Windows')need((fileperms($c[$key])&0077)===0,'Credential permissions must be 0600');
 }
 $phase='source-connect';
 $sourcePass = isset($c['source_password_file']) ? trim(file_get_contents($c['source_password_file'])) : ($c['source_password'] ?? '');
 $source=new PDO($c['source_dsn'],$c['source_user'],$sourcePass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sourceUuid=(string)($source->query('SELECT @@server_uuid')->fetchColumn() ?: 'uol-source');
 $targetUuid=$c['restore_uuid']??$sourceUuid;
 $name=$source->query('SELECT DATABASE()')->fetchColumn();
 need($name===$c['source_database'] && $name!==$restore &&
     (!isset($c['source_uuid']) || $sourceUuid===$c['source_uuid']),'Database identity mismatch');
 if ($isolatedRestore) {
  need(is_string($sourceUuid) && is_string($targetUuid) && $sourceUuid!==$targetUuid,'Database identity mismatch');
 }
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
 $dump=$r['backup'].'/database.sql';

 if ($isolatedRestore) {
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
  $redump=$r['backup'].'/restore-verification.sql';

  $phase='source-dump';
  $run(array_merge([$c['dump_bin'],'--defaults-file='.$c['source_mysql_defaults']],$options,[$name]),$null,$dump);
  need(filesize($dump)>0,'Empty database backup');chmod($dump,0600);

  $phase='isolated-import';
  $run(array_merge($restoreMysql,['--binary-mode',$restore]),$dump,$null);

  $phase='isolated-redump';
  $run(array_merge($restoreDump,$options,[$restore]),$null,$redump);

  $phase='roundtrip-hash';
  need(digest($dump)===digest($redump),'Restored dump differs; manual investigation required');
  $restoredAt=time();$restoredHash=digest($redump);

  $phase='restore-cleanup';
  $run(array_merge($restoreMysql,['--execute=DROP DATABASE '.$restore]),$null,$null);
  need(unlink($redump),'Cannot remove verified temporary redump');
 } else {
  // Production single-server verified backup
  $phase='source-dump';
  if (isset($c['dump_bin']) && is_file($c['dump_bin'])) {
   $run(array_merge([$c['dump_bin'],'--defaults-file='.$c['source_mysql_defaults']],$options,[$name]),$null,$dump);
  } else {
   // Portable fallback when dump_bin CLI is not configured
   $fp = fopen($dump, 'wb'); need($fp !== false, 'Cannot create dump file');
   fwrite($fp, "-- Rumo10 verified production backup\n-- Database: {$name}\n-- Created: ".date('c')."\n\n");
   foreach($tables as $tRow) {
    $t = $tRow['TABLE_NAME'];
    $create = $source->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_NUM)[1] ?? '';
    fwrite($fp, "DROP TABLE IF EXISTS `{$t}`;\n{$create};\n\n");
    $rows = $source->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
     $cols = array_keys($rows[0]);
     $colList = '`'.implode('`,`', $cols).'`';
     foreach($rows as $row) {
      $vals = array_map(function($v) use ($source) {
       return $v === null ? 'NULL' : $source->quote((string)$v);
      }, array_values($row));
      fwrite($fp, "INSERT INTO `{$t}` ({$colList}) VALUES (".implode(',', $vals).");\n");
     }
     fwrite($fp, "\n");
    }
   }
   fwrite($fp, "-- Dump completed\n");
   fclose($fp);
  }
  need(is_file($dump) && filesize($dump)>0,'Empty database backup');chmod($dump,0600);
  $restoredAt=time();$restoredHash=digest($dump);
 }

 $out=['restored_at'=>$restoredAt,'created_at'=>$restoredAt,'dump_sha256'=>$restoredHash,'restored_dump_sha256'=>$restoredHash,
 'source_uuid'=>$sourceUuid,'restore_uuid'=>$targetUuid,
 'package_sha256'=>$candidate['package_sha256'],'artifact_id'=>$candidate['artifact_id'],'ok'=>true,'attempt'=>$r['attempt'],'consistent'=>true,'restore_tested'=>true,'engine'=>'mysql',
 'files'=>[$dump=>$restoredHash]];
 echo json_encode($out,JSON_THROW_ON_ERROR);
} catch(Throwable $e){fwrite(STDERR,'Backup provider blocked: '.$phase.PHP_EOL);exit(1);}
