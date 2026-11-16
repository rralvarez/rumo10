<?php
declare(strict_types=1);
// Isolated restore provider. UOL enablement requires administrative validation.
// Restore target MUST be a separate isolated server, never the operational MySQL.
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};
$phase='profile';
$tempDefaults=null;
try {
 need(PHP_SAPI==='cli' && in_array($argv[1]??'', ['backup', 'preflight'], true) && ($argv[2]??'')==='--profile','Private backup hook only');
 $action=$argv[1];
 $file=$argv[3]??''; need(is_file($file) && !is_link($file),'Private provider profile required');
 if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Profile must be 0600');
 $c=json(file_get_contents($file));$r=json(stream_get_contents(STDIN));
 umask(0077);
 $isPreflight = ($action === 'preflight') || (($r['action'] ?? '') === 'preflight');
 $attempt=$r['attempt']??'';$candidate=$r['candidate']??[];
 if (!$isPreflight) {
  need(preg_match('/^[a-f0-9]{32}$/D',$attempt)===1 && is_dir($r['backup']) && !is_link($r['backup']) &&
      preg_match('/^[a-f0-9]{64}$/D',$candidate['package_sha256']??'')===1 &&
      is_int($candidate['artifact_id']??null),'Invalid backup attempt or release');
 }
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
 try {
  $sourceUuid=(string)($source->query('SELECT @@server_uuid')->fetchColumn() ?: 'uol-source');
 } catch(\Throwable) {
  $sourceUuid='uol-source';
 }
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

 // Deep dump completeness validator: proves that all tables, data rows, views and triggers exist in dump
 function validateDumpCompleteness(string $dumpPath, array $tables, array $views, int $expectedTriggers, \PDO $source, string $dbName): void {
  need(is_file($dumpPath) && filesize($dumpPath) > 0, 'Dump file is missing or 0 bytes');

  $fp = fopen($dumpPath, 'rb');
  need($fp !== false, 'Cannot open dump file for completeness validation');

  $foundTablesCreate = [];
  $foundTablesData = [];
  $foundViews = [];
  $foundTriggersCount = 0;
  $hasDumpHeader = false;
  $hasDumpCompleted = false;

  while (($line = fgets($fp)) !== false) {
   $tLine = trim($line);
   if (str_starts_with($tLine, '-- MySQL dump') || str_starts_with($tLine, '/*!40101 SET') || str_starts_with($tLine, '-- MariaDB dump') || str_starts_with($tLine, '/*M!999999\- enable the sandbox mode')) {
    $hasDumpHeader = true;
   }
   if (str_contains($tLine, 'Dump completed')) {
    $hasDumpCompleted = true;
   }
   if (preg_match('/^CREATE TABLE [`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundTablesCreate[$m[1]] = true;
   }
   if (preg_match('/^INSERT INTO [`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundTablesData[$m[1]] = true;
   }
   if (preg_match('/^(?:\/\*!50001\s+)?CREATE(?:.*?)(?:ALGORITHM=[^ ]+\s+)?(?:DEFINER=[^ ]+\s+)?VIEW [`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundViews[$m[1]] = true;
   }
   if (preg_match('/^(?:\/\*!50003\s+)?CREATE(?:.*?)(?:DEFINER=[^ ]+\s+)?TRIGGER [`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundTriggersCount++;
   }
  }
  fclose($fp);

  need($hasDumpHeader, 'Dump validation failed: missing or invalid mysqldump header');
  need($hasDumpCompleted, 'Dump validation failed: dump appears truncated (missing "Dump completed" marker)');

  foreach ($tables as $t) {
   $tName = $t['TABLE_NAME'];
   need(isset($foundTablesCreate[$tName]), "Dump validation failed: missing CREATE TABLE for table '{$tName}'");

   $rowCount = (int)$source->query("SELECT COUNT(*) FROM `" . str_replace("`", "``", $tName) . "`")->fetchColumn();
   if ($rowCount > 0) {
    need(isset($foundTablesData[$tName]), "Dump validation failed: table '{$tName}' has {$rowCount} rows in database, but no INSERT statements were found in dump (inherited --no-data option or silent export failure)");
   }
  }

  foreach ($views as $vName) {
   need(isset($foundViews[$vName]), "Dump validation failed: missing CREATE VIEW for view '{$vName}'");
  }

  if ($expectedTriggers > 0) {
   need($foundTriggersCount >= $expectedTriggers, "Dump validation failed: expected {$expectedTriggers} triggers, but found {$foundTriggersCount} in dump");
  }
 }

 // Private secure directory for credentials and temporary dumps
 $privateBase = null;
 if (!empty($r['backup']) && is_dir($r['backup'])) {
  $privateBase = $r['backup'];
 } elseif (isset($c['private']) && is_dir($c['private'])) {
  $privateBase = $c['private'];
 } elseif (isset($c['source_password_file']) && is_dir(dirname($c['source_password_file']))) {
  $privateBase = dirname($c['source_password_file']);
 } else {
  $privateBase = sys_get_temp_dir();
 }

 $secureTempDir = $privateBase . '/.bk-tmp-' . bin2hex(random_bytes(8));
 mkdir($secureTempDir, 0700, true);
 if (PHP_OS_FAMILY !== 'Windows') {
  @chmod($secureTempDir, 0700);
 }

 // Adaptive options detection: MariaDB vs MySQL, user grants and schema objects
 $isMariaDB = false;
 try {
  $vStr = (string)$source->query('SELECT VERSION()')->fetchColumn();
  $vComment = (string)$source->query('SELECT @@version_comment')->fetchColumn();
  if (stripos($vStr, 'MariaDB') !== false || stripos($vComment, 'MariaDB') !== false) {
   $isMariaDB = true;
  }
 } catch (\Throwable) {}

 $phase='dump-bin-discovery';
 $dumpBin = $c['dump_bin'] ?? null;
 if (!$dumpBin || !is_file($dumpBin)) {
  $candidates = PHP_OS_FAMILY === 'Windows'
      ? ['C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe', 'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe', 'mysqldump.exe']
      : ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump', '/usr/local/cpanel/3rdparty/bin/mysqldump', 'mysqldump'];
  foreach ($candidates as $cand) {
      if (is_file($cand) && (PHP_OS_FAMILY === 'Windows' || is_executable($cand))) {
          $dumpBin = $cand;
          break;
      }
  }
  if (!$dumpBin) {
      $whichCmd = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump 2>NUL' : 'which mysqldump 2>/dev/null';
      $found = trim((string)shell_exec($whichCmd));
      if ($found !== '') {
          $lines = explode("\n", str_replace("\r", "", $found));
          if (is_file($lines[0])) { $dumpBin = $lines[0]; }
      }
  }
 }
 need($dumpBin !== null && (is_file($dumpBin) || PHP_OS_FAMILY !== 'Windows'), 'mysqldump binary required for consistent transactional snapshot backup');

 // Discover dump binary CLI capabilities
 $binQuoted = (PHP_OS_FAMILY === 'Windows' && str_contains($dumpBin, ' ')) ? '"' . $dumpBin . '"' : escapeshellcmd($dumpBin);
 $binHelp = (string)@shell_exec($binQuoted . ' --help 2>&1');
 $supportsColumnStats = str_contains($binHelp, 'column-statistics');
 $supportsTablespaces = str_contains($binHelp, 'no-tablespaces') || str_contains($binHelp, 'all-tablespaces');
 $supportsGtid = str_contains($binHelp, 'set-gtid-purged');
 $supportsSkipNoData = str_contains($binHelp, 'no-data');

 // Privilege verification: Do NOT infer absence of objects from zero visible objects
 $hasAllPrivOnDb = false;
 $hasTriggerPriv = false;
 $hasEventPriv = false;
 $hasRoutinePriv = false;
 $hasLockTablesPriv = false;
 $hasShowViewPriv = false;

 try {
  $stmt = $source->query('SHOW GRANTS');
  while ($row = $stmt->fetchColumn()) {
   $line = strtoupper((string)$row);
   $appliesToDb = str_contains($line, 'ON *.*') || 
                  str_contains($line, 'ON `' . strtoupper($name) . '`.*') ||
                  str_contains($line, 'ON ' . strtoupper($name) . '.*');

   if ($appliesToDb) {
    if (str_contains($line, 'ALL PRIVILEGES')) {
     $hasAllPrivOnDb = true;
     $hasTriggerPriv = true;
     $hasEventPriv = true;
     $hasRoutinePriv = true;
     $hasLockTablesPriv = true;
     $hasShowViewPriv = true;
    }
    if (str_contains($line, 'TRIGGER')) $hasTriggerPriv = true;
    if (str_contains($line, 'EVENT')) $hasEventPriv = true;
    if (str_contains($line, 'ROUTINE') || str_contains($line, 'EXECUTE')) $hasRoutinePriv = true;
    if (str_contains($line, 'LOCK TABLES')) $hasLockTablesPriv = true;
    if (str_contains($line, 'SHOW VIEW')) $hasShowViewPriv = true;
   }
  }
 } catch (\Throwable $e) {
  throw new \RuntimeException('Cannot verify user grants via SHOW GRANTS: ' . $e->getMessage());
 }

 // Strict fail-closed: if the account lacks privileges to prove inventory integrity, declare the specific dependency
 if (!$hasTriggerPriv) {
  throw new \RuntimeException("Dependência de privilégio não atendida: o usuário do banco de dados não possui o privilégio 'TRIGGER' no database '{$name}' (nem 'ALL PRIVILEGES'). Sem essa permissão, o inventário de triggers é filtrado pelo MySQL/MariaDB e a integridade da exportação não pode ser comprovada.");
 }
 if (!$hasEventPriv) {
  throw new \RuntimeException("Dependência de privilégio não atendida: o usuário do banco de dados não possui o privilégio 'EVENT' no database '{$name}' (nem 'ALL PRIVILEGES'). Sem essa permissão, o inventário de eventos é filtrado pelo MySQL/MariaDB e a integridade da exportação não pode ser comprovada.");
 }
 if (!$hasRoutinePriv) {
  throw new \RuntimeException("Dependência de privilégio não atendida: o usuário do banco de dados não possui o privilégio 'ROUTINE'/'EXECUTE' no database '{$name}' (nem 'ALL PRIVILEGES'). Sem essa permissão, o inventário de procedures e funções é filtrado pelo MySQL/MariaDB e a integridade da exportação não pode ser comprovada.");
 }

 // Proven inventory counts (guaranteed complete because privileges were verified above)
 $evStmt = $source->prepare("SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?");
 $evStmt->execute([$name]);
 $evCount = (int)$evStmt->fetchColumn();

 $rtStmt = $source->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?");
 $rtStmt->execute([$name]);
 $rtCount = (int)$rtStmt->fetchColumn();

 $trStmt = $source->prepare("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?");
 $trStmt->execute([$name]);
 $trCount = (int)$trStmt->fetchColumn();

 $views = [];
 try {
  $vwStmt = $source->prepare("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?");
  $vwStmt->execute([$name]);
  $views = $vwStmt->fetchAll(\PDO::FETCH_COLUMN);
  foreach ($views as $vName) {
   $source->query("SELECT 1 FROM `" . str_replace("`", "``", $vName) . "` LIMIT 0");
  }
 } catch (\Throwable $e) {
  throw new \RuntimeException("Database view validation failed ({$vName}): " . $e->getMessage());
 }

 $options = [
  '--single-transaction',
  '--quick',
  '--skip-lock-tables',
  '--skip-add-locks',
  '--skip-comments',
  '--hex-blob',
  '--triggers',
 ];

 // Counteract any inherited --no-data options from global/user option files
 if ($supportsSkipNoData) {
  $options[] = '--skip-no-data';
 }

 if ($supportsColumnStats) {
  $options[] = '--skip-column-statistics';
 }

 if ($supportsTablespaces) {
  $options[] = '--no-tablespaces';
 }

 if ($supportsGtid && !$isMariaDB) {
  $options[] = '--set-gtid-purged=OFF';
 }

 if ($evCount > 0) {
  $options[] = '--events';
 }

 if ($rtCount > 0) {
  $options[] = '--routines';
 }

 // Credentials are NEVER command-line values; option files are outside web roots.
 $run = static function(array $cmd, string $in, string $out) use (&$phase, $privateBase, $secureTempDir): void {
  $errPath = $secureTempDir . '/bk-err-' . bin2hex(random_bytes(6)) . '.log';
  $p = proc_open($cmd, [0 => ['file', $in, 'rb'], 1 => ['file', $out, 'wb'], 2 => ['file', $errPath, 'wb']], $pipes);
  need(is_resource($p), 'Backup command unavailable');
  $end = microtime(true) + 300;
  $exit = -1;
  do {
   $s = proc_get_status($p);
   if (!$s['running']) {
    $exit = $s['exitcode'];
    break;
   }
   if (microtime(true) > $end) {
    proc_terminate($p);
    break;
   }
   usleep(100000);
  } while (true);
  proc_close($p);
  $errText = is_file($errPath) ? trim(file_get_contents($errPath)) : '';
  if (is_file($errPath)) @unlink($errPath);
  if ($exit !== 0) {
   $clean = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $errText);
   $clean = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $clean);
   $logPayload = date('c') . " | Phase: {$phase} | Exit: {$exit}\n" . $clean . "\n" . str_repeat('-', 60) . "\n";
   if (is_dir($privateBase)) {
    @file_put_contents($privateBase . '/backup-last-error.log', $logPayload, FILE_APPEND);
    @chmod($privateBase . '/backup-last-error.log', 0600);
   }

   $firstLine = explode("\n", $clean)[0] ?? 'unknown mysqldump error';
   throw new \RuntimeException('mysqldump failed (code ' . $exit . '): ' . $firstLine . ($clean !== $firstLine ? ' | ' . substr($clean, 0, 500) : ''));
  }
 };
 $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

 // Build credentials invocation
 $dumpCmd = [$dumpBin];
 if (isset($c['source_mysql_defaults']) && is_file($c['source_mysql_defaults'])) {
  $dumpCmd[] = '--defaults-file='.$c['source_mysql_defaults'];
 } else {
  $host = '127.0.0.1';
  $port = 3306;
  if (isset($c['source_dsn'])) {
   if (preg_match('/host=([^;]+)/', $c['source_dsn'], $m)) $host = $m[1];
   if (preg_match('/port=([0-9]+)/', $c['source_dsn'], $m)) $port = (int)$m[1];
  }
  $tempDefaults = $secureTempDir . '/temp-dump-defaults-' . bin2hex(random_bytes(6)) . '.cnf';
  $cnf = "[client]\nuser=" . ($c['source_user'] ?? 'root') . "\npassword=" . $sourcePass . "\nhost=" . $host . "\nport=" . $port . "\n";
  file_put_contents($tempDefaults, $cnf);
  chmod($tempDefaults, 0600);
  $dumpCmd[] = '--defaults-file='.$tempDefaults;
 }

 if ($isPreflight) {
  $phase='preflight-full-data-proof';
  $dryDump = $secureTempDir . '/preflight-test-dump-' . bin2hex(random_bytes(6)) . '.sql';
  // True preflight: FULL test export including DATA using the EXACT same options
  $dryCmd = array_merge($dumpCmd, $options, [$name]);
  $run($dryCmd, $null, $dryDump);
  // Deep validation of test dump completeness (all tables, data rows, views, triggers)
  validateDumpCompleteness($dryDump, $tables, $views, $trCount, $source, $name);
  if (is_file($dryDump)) @unlink($dryDump);
  if ($tempDefaults !== null && is_file($tempDefaults)) {
   @unlink($tempDefaults);
   $tempDefaults = null;
  }
  // Cleanup secure temp dir
  if (is_dir($secureTempDir)) {
   $tFiles = glob($secureTempDir . '/*');
   foreach ($tFiles as $tf) { if (is_file($tf)) @unlink($tf); }
   @rmdir($secureTempDir);
  }
  echo json_encode([
   'ok' => true,
   'preflight' => true,
   'engine' => $isMariaDB ? 'mariadb' : 'mysql',
   'database' => $name,
   'source_uuid' => $sourceUuid,
   'dump_bin' => $dumpBin,
   'options' => $options,
   'is_mariadb' => $isMariaDB,
   'events_count' => $evCount,
   'routines_count' => $rtCount,
   'triggers_count' => $trCount,
   'views_count' => count($views),
   'full_data_tested' => true,
   'dry_run_success' => true,
  ], JSON_THROW_ON_ERROR);
  exit(0);
 }

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
  need(filesize($dump)>0,'Empty database backup');
  validateDumpCompleteness($dump, $tables, $views, $trCount, $source, $name);
  chmod($dump,0600);

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
  // Production single-server verified backup with mysqldump
  $phase='source-dump';
  $run(array_merge($dumpCmd, $options, [$name]), $null, $dump);
  need(is_file($dump) && filesize($dump) > 0, 'Empty database backup');
  validateDumpCompleteness($dump, $tables, $views, $trCount, $source, $name);
  chmod($dump, 0600);
  $restoredAt = time();
  $restoredHash = digest($dump);

  if ($tempDefaults !== null && is_file($tempDefaults)) {
   @unlink($tempDefaults);
   $tempDefaults = null;
  }
  if (isset($secureTempDir) && is_dir($secureTempDir)) {
   $tFiles = glob($secureTempDir . '/*');
   foreach ($tFiles as $tf) { if (is_file($tf)) @unlink($tf); }
   @rmdir($secureTempDir);
  }
 }

 $out=['restored_at'=>$restoredAt,'created_at'=>$restoredAt,'dump_sha256'=>$restoredHash,'restored_dump_sha256'=>$restoredHash,
 'source_uuid'=>$sourceUuid,'restore_uuid'=>$targetUuid,
 'package_sha256'=>$candidate['package_sha256'],'artifact_id'=>$candidate['artifact_id'],'ok'=>true,'attempt'=>$r['attempt'],'consistent'=>true,'restore_tested'=>$isolatedRestore,'engine'=>'mysql',
 'files'=>[$dump=>$restoredHash]];
 echo json_encode($out,JSON_THROW_ON_ERROR);
} catch(Throwable $e){
 if (isset($tempDefaults) && $tempDefaults !== null && is_file($tempDefaults)) {
  @unlink($tempDefaults);
 }
 if (isset($secureTempDir) && is_dir($secureTempDir)) {
  $tFiles = glob($secureTempDir . '/*');
  foreach ($tFiles as $tf) { if (is_file($tf)) @unlink($tf); }
  @rmdir($secureTempDir);
 }
 $cleanErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $e->getMessage());
 $cleanErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $cleanErr);
 if (isset($privateBase) && is_dir($privateBase)) {
  $destFile = $privateBase . '/backup-last-error.log';
  @file_put_contents($destFile, date('c') . " | {$phase} | {$cleanErr}\n", FILE_APPEND);
  @chmod($destFile, 0600);
 }
 fwrite(STDERR,'Backup provider blocked: '.$phase.' - '.$cleanErr.PHP_EOL);
 exit(1);
}
