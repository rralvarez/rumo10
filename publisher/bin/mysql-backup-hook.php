<?php
declare(strict_types=1);
// Isolated restore provider. UOL enablement requires administrative validation.
// Restore target MUST be a separate isolated server, never the operational MySQL.
// Resolve Core.php from V9 single-file pointer (runtime-active.json) when present, falling back to lib/Core.php.
$hookRoot = dirname(__DIR__);
$hookCore = $hookRoot.'/lib/Core.php';
$hookPtr = $hookRoot.'/runtime-active.json';
if (is_file($hookPtr) && !is_link($hookPtr)) {
    $ptrData = json_decode((string)file_get_contents($hookPtr), true);
    if (is_array($ptrData) && is_string($ptrData['active'] ?? null) && preg_match('/^[A-Za-z0-9_.-]+$/D', $ptrData['active']) === 1) {
        $hookCore = $hookRoot.'/runtime/'.$ptrData['active'].'/Core.php';
    }
}
require_once $hookCore;
use function Rumo\Publisher\{need,json,digest,record};

if (!function_exists('rumoParseDbServerVersion')) {
 function rumoParseDbServerVersion(string $versionStr, string $versionComment = ''): array {
  $isMariaDB = (stripos($versionStr, 'MariaDB') !== false || stripos($versionComment, 'MariaDB') !== false);
  $major = 0; $minor = 0; $patch = 0;
  if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', trim($versionStr), $m) === 1) {
   $major = (int)$m[1];
   $minor = (int)$m[2];
   $patch = (int)$m[3];
  }
  return [
   'engine' => $isMariaDB ? 'mariadb' : 'mysql',
   'is_mariadb' => $isMariaDB,
   'raw' => $versionStr,
   'comment' => $versionComment,
   'major' => $major,
   'minor' => $minor,
   'patch' => $patch,
   'normalized' => "{$major}.{$minor}.{$patch}",
  ];
 }

 function rumoUnescapeGrantIdentifier(string $raw): string {
  $id = trim($raw);
  if (strlen($id) >= 2 && $id[0] === '`' && $id[strlen($id) - 1] === '`') {
   $id = str_replace('``', '`', substr($id, 1, -1));
  }
  return str_replace(['\\_', '\\%'], ['_', '%'], $id);
 }

 function rumoParseShowGrantLine(string $grantLine): ?array {
  if (preg_match('/^\s*(GRANT|REVOKE)\s+(.+?)\s+ON\s+(?:(?:PROCEDURE|FUNCTION)\s+)?(.+?)\s+(TO|FROM)\s+/i', $grantLine, $m) !== 1) {
   return null;
  }
  $verb = strtoupper($m[1]);
  $direction = strtoupper($m[4]);
  if (($verb === 'GRANT' && $direction !== 'TO') || ($verb === 'REVOKE' && $direction !== 'FROM')) {
   return null;
  }
  $privs = [];
  foreach (explode(',', $m[2]) as $token) {
   $p = preg_replace('/\s+/', ' ', strtoupper(trim($token)));
   if ($p !== '') $privs[$p] = true;
  }
  $target = trim($m[3]);
  if ($target === '*.*') {
   return ['verb' => $verb, 'privs' => $privs, 'scope' => 'global', 'schema' => '*', 'object' => '*'];
  }
  if (preg_match('/^(`(?:[^`]|``)+`|[a-zA-Z0-9_\\\\$-]+)\s*\.\s*(`(?:[^`]|``)+`|\*|[a-zA-Z0-9_$-]+)$/', $target, $tm) !== 1) {
   return null;
  }
  $schema = rumoUnescapeGrantIdentifier($tm[1]);
  $object = rumoUnescapeGrantIdentifier($tm[2]);
  $scope = ($object === '*') ? 'schema' : 'object';
  return ['verb' => $verb, 'privs' => $privs, 'scope' => $scope, 'schema' => $schema, 'object' => $object];
 }

 /**
  * Evaluates effective MySQL/MariaDB privileges from SHOW GRANTS, properly handling
  * escaped schema names AND partial REVOKE lines.
  * Separates:
  * - routine_inventory_provable: can prove complete routine count (MySQL 8.0.20+: SHOW_ROUTINE or global SELECT)
  * - routine_export_supported: satisfies mysqldump --routines contract (MySQL 8: global SELECT without partial revoke;
  *   MariaDB / MySQL 5.x: global SELECT or SELECT on mysql.proc without revoke).
  */
 function rumoEvaluateMysqlPrivileges(array $grantLines, string $dbName, string $versionStr, string $versionComment = ''): array {
  $server = rumoParseDbServerVersion($versionStr, $versionComment);
  $dbLower = strtolower($dbName);
  $isMariaDB = $server['is_mariadb'];
  $isMysql8Plus = !$isMariaDB && $server['major'] >= 8;
  $supportsShowRoutine = !$isMariaDB && ($server['major'] > 8 || ($server['major'] === 8 && ($server['minor'] > 0 || $server['patch'] >= 20)));

  $schemaTracked = ['TRIGGER', 'EVENT', 'CREATE ROUTINE', 'ALTER ROUTINE', 'LOCK TABLES', 'SHOW VIEW'];
  $granted = [];
  $revoked = [];
  $globalSelectGranted = false;
  $selectRevoked = false;
  $showRoutineGranted = false;
  $showRoutineRevoked = false;
  $mysqlProcSelectGranted = false;
  $mysqlProcSelectRevoked = false;

  foreach ($grantLines as $rawLine) {
   $parsed = rumoParseShowGrantLine((string)$rawLine);
   if ($parsed === null) continue;
   $isGrant = ($parsed['verb'] === 'GRANT');
   $privs = $parsed['privs'];
   $all = isset($privs['ALL PRIVILEGES']) || isset($privs['ALL']);
   $isGlobal = ($parsed['scope'] === 'global');
   $isTargetSchema = ($parsed['scope'] === 'schema' && strtolower($parsed['schema']) === $dbLower);
   $isMysqlProc = (strtolower($parsed['schema']) === 'mysql' && ($parsed['object'] === '*' || strtolower($parsed['object']) === 'proc'));

   if ($isGlobal || $isTargetSchema) {
    $relevant = $all ? $schemaTracked : array_values(array_intersect($schemaTracked, array_keys($privs)));
    foreach ($relevant as $p) {
     if ($isGrant) $granted[$p] = true;
     else $revoked[$p] = true;
    }
    // Global SELECT (required by Oracle mysqldump --routines) is invalidated by ANY REVOKE SELECT/ALL on global or target schema.
    if ($isGrant && $isGlobal && ($all || isset($privs['SELECT']))) {
     $globalSelectGranted = true;
    }
    if (!$isGrant && ($all || isset($privs['SELECT']))) {
     $selectRevoked = true;
    }
    // SHOW_ROUTINE is a dynamic global privilege (NOT implied by ALL PRIVILEGES).
    if ($isGlobal && ($supportsShowRoutine || $isMariaDB) && (isset($privs['SHOW_ROUTINE']) || isset($privs['SHOW ROUTINE']))) {
     if ($isGrant) $showRoutineGranted = true;
     else $showRoutineRevoked = true;
    }
   }

   if (!$isMysql8Plus && ($isGlobal || $isMysqlProc)) {
    if ($isGrant && ($all || isset($privs['SELECT']))) {
     $mysqlProcSelectGranted = true;
    }
    if (!$isGrant && ($all || isset($privs['SELECT']))) {
     $mysqlProcSelectRevoked = true;
    }
   }
  }

  $effective = array_diff_key($granted, $revoked);
  $hasGlobalSelect = $globalSelectGranted && !$selectRevoked;
  $hasShowRoutine = $showRoutineGranted && !$showRoutineRevoked;
  $hasMysqlProcSelect = !$isMysql8Plus && $mysqlProcSelectGranted && !$mysqlProcSelectRevoked;

  if ($isMysql8Plus) {
   $routineInventoryProvable = $hasGlobalSelect || ($supportsShowRoutine && $hasShowRoutine);
   $routineExportSupported = $hasGlobalSelect;
   $routinePrivBasis = $hasGlobalSelect ? 'global:SELECT' : (($supportsShowRoutine && $hasShowRoutine) ? 'global:SHOW_ROUTINE' : 'none');
  } else {
   $routineInventoryProvable = $hasGlobalSelect || $hasMysqlProcSelect || ($isMariaDB && $hasShowRoutine);
   $routineExportSupported = $hasGlobalSelect || $hasMysqlProcSelect;
   $routinePrivBasis = $hasGlobalSelect ? 'global:SELECT' : ($hasMysqlProcSelect ? 'mysql.proc:SELECT' : (($isMariaDB && $hasShowRoutine) ? 'global:SHOW_ROUTINE' : 'none'));
  }

  return [
   'server' => $server,
   'effective_privileges' => $effective,
   'has_global_select' => $hasGlobalSelect,
   'has_show_routine' => $hasShowRoutine,
   'has_trigger_priv' => isset($effective['TRIGGER']),
   'has_event_priv' => isset($effective['EVENT']),
   'routine_inventory_provable' => $routineInventoryProvable,
   'routine_export_supported' => $routineExportSupported,
   'has_routine_definition_priv' => $routineExportSupported,
   'routine_priv_basis' => $routinePrivBasis,
   'has_schema_routine_ddl_only' => (isset($effective['CREATE ROUTINE']) || isset($effective['ALTER ROUTINE'])) && !$routineInventoryProvable,
   'has_lock_tables_priv' => isset($effective['LOCK TABLES']),
   'has_show_view_priv' => isset($effective['SHOW VIEW']),
  ];
 }

 function rumoCanProceedWithRoutines(array $privEval, int $routineCount): bool {
  if ($privEval['routine_export_supported'] === true) return true;
  // SHOW_ROUTINE proves inventory completeness when zero routines exist;
  // exporting actual routines via mysqldump --routines requires global SELECT (or mysql.proc SELECT on MariaDB/5.x).
  return ($privEval['routine_inventory_provable'] === true) && $routineCount === 0;
 }

 function rumoRoutinePrivilegeRequirementMessage(array $server, string $dbName, bool $routinesPresent = false): string {
  if ($routinesPresent) {
   if ($server['is_mariadb']) {
    return "Há rotinas em '{$dbName}': MariaDB ({$server['raw']}) exige 'SELECT' em `mysql`.`proc` ou 'SELECT' global ON *.* (sem revogação parcial) para exportar definições via mysqldump --routines.";
   }
   return "Há rotinas em '{$dbName}': o caminho mysqldump --routines no MySQL {$server['raw']} exige 'SELECT' global ON *.* (sem revogação parcial) ou um exportador de definições validado. 'SHOW_ROUTINE' sozinho comprova o inventário, mas não atende o requisito documentado do mysqldump --routines.";
  }
  if ($server['is_mariadb']) {
   return "Inventário de rotinas MariaDB ({$server['raw']}) sem visibilidade comprovada em '{$dbName}': requer 'SELECT' em `mysql`.`proc` ou 'SELECT' global ON *.* (sem revogação parcial). 'CREATE ROUTINE', 'ALTER ROUTINE' e 'ALL PRIVILEGES ON `{$dbName}`.*' são insuficientes.";
  }
  if ($server['major'] > 8 || ($server['major'] === 8 && ($server['minor'] > 0 || $server['patch'] >= 20))) {
   return "Inventário de rotinas MySQL {$server['raw']} sem visibilidade comprovada em '{$dbName}': requer 'SHOW_ROUTINE' ou 'SELECT' explicitamente global ON *.* (sem revogação parcial). 'CREATE ROUTINE', 'ALTER ROUTINE', 'EXECUTE' e 'ALL PRIVILEGES ON `{$dbName}`.*' são insuficientes.";
  }
  return "Inventário de rotinas MySQL {$server['raw']} sem visibilidade comprovada em '{$dbName}': requer 'SELECT' global ON *.* (ou 'SELECT' em `mysql`.`proc` no MySQL 5.x), sem revogação parcial.";
 }
}

if (defined('RUMO_BACKUP_HOOK_LIB_ONLY') && RUMO_BACKUP_HOOK_LIB_ONLY === true) {
 return;
}
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

 // Evidence-based dump completeness validator: proves structural and data integrity across MySQL 8 and MariaDB 10
 function validateDumpCompleteness(string $dumpPath, array $tables, array $views, int $expectedTriggers, int $expectedRoutines, int $expectedEvents, string $dbName): void {
  need(is_file($dumpPath) && filesize($dumpPath) > 0, 'Dump file is missing or 0 bytes');

  $fp = fopen($dumpPath, 'rb');
  need($fp !== false, 'Cannot open dump file for completeness validation');

  $foundTables = [];
  $foundDataTables = [];
  $foundViews = [];
  $foundTriggers = [];
  $foundRoutines = [];
  $foundEvents = [];
  $hasRoutineWarning = false;
  $lastNonEmptyLine = '';

  while (($line = fgets($fp)) !== false) {
   $tLine = trim($line);
   if ($tLine !== '') {
    $lastNonEmptyLine = $tLine;
   }
   if (preg_match('/--\s*WARNING:\s*Routine/i', $tLine)) {
    $hasRoutineWarning = true;
   }
   if (preg_match('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundTables[$m[1]] = true;
   }
   if (preg_match('/INSERT\s+INTO\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundDataTables[$m[1]] = true;
   }
   if (preg_match('/(?:\/\*[!M]*\d*\s+)?(?:CREATE)?(?:[^;]*?)\s+VIEW\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m) ||
       preg_match('/(?:\/\*[!M]*\d*\s+VIEW\s+[`"]?([a-zA-Z0-9_]+)[`"]?)/i', $tLine, $m)) {
    $foundViews[$m[1]] = true;
   }
   if (preg_match('/(?:\/\*[!M]*\d*\s+)?(?:CREATE)?(?:[^;]*?)\s+TRIGGER\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundTriggers[$m[1]] = true;
   }
   if (preg_match('/(?:\/\*[!M]*\d*\s+)?CREATE(?:[^;]*?)\s+(?:PROCEDURE|FUNCTION)\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundRoutines[$m[1]] = true;
   }
   if (preg_match('/(?:\/\*[!M]*\d*\s+)?CREATE(?:[^;]*?)\s+EVENT\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $tLine, $m)) {
    $foundEvents[$m[1]] = true;
   }
  }
  fclose($fp);

  // Validate statement termination / non-truncation
  $trimmedEnd = rtrim($lastNonEmptyLine);
  $validEnd = str_ends_with($trimmedEnd, ';') || 
             str_ends_with($trimmedEnd, '*/') || 
             str_ends_with($trimmedEnd, '*/;') || 
             str_ends_with($trimmedEnd, 'DELIMITER ;') || 
             str_contains($trimmedEnd, 'Dump completed');
  need($validEnd, "Dump validation failed: dump appears truncated or has unclosed statement at EOF");

  // Validate tables presence
  foreach ($tables as $t) {
   $tName = $t['TABLE_NAME'];
   need(isset($foundTables[$tName]), "Dump validation failed: missing CREATE TABLE for table '{$tName}'");
  }

  // Detect --no-data or omission of data across non-empty schema
  if (count($tables) > 0 && count($foundDataTables) === 0) {
   need(count($foundDataTables) > 0, "Dump validation failed: no INSERT statements found in dump (inherited --no-data option or silent export failure)");
  }

  // Validate views presence
  foreach ($views as $vName) {
   need(isset($foundViews[$vName]), "Dump validation failed: missing CREATE VIEW for view '{$vName}'");
  }

  // Validate triggers count
  if ($expectedTriggers > 0) {
   need(count($foundTriggers) >= $expectedTriggers, "Dump validation failed: expected {$expectedTriggers} triggers, but found " . count($foundTriggers) . " in dump");
  }

  // Validate routines count and errors
  if ($expectedRoutines > 0) {
   need(count($foundRoutines) >= $expectedRoutines, "Dump validation failed: expected {$expectedRoutines} routines, but found " . count($foundRoutines) . " in dump");
   need(!$hasRoutineWarning, "Dump validation failed: routine export warning encountered in dump (insufficient privileges or corrupted routine)");
  }

  // Validate events count
  if ($expectedEvents > 0) {
   need(count($foundEvents) >= $expectedEvents, "Dump validation failed: expected {$expectedEvents} events, but found " . count($foundEvents) . " in dump");
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

 // Accept only connection settings. In particular, option includes and client-side data filters
 // cannot silently turn a completed export into a partial backup.
 if(isset($c['source_mysql_defaults'])){
  $lines=file($c['source_mysql_defaults'],FILE_IGNORE_NEW_LINES);
  need($lines!==false,'Cannot read private source client options');
  $client=false;$seen=[];
  foreach($lines as $line){
   $line=trim($line);
   if($line==='' || str_starts_with($line,'#') || str_starts_with($line,';'))continue;
   if($line==='[client]' && !$client){$client=true;continue;}
   need($client && preg_match('/^([a-z-]+)\s*=/D',$line,$match)===1 &&
      in_array($match[1],['host','port','user','password','protocol','socket','ssl-mode','ssl-ca','ssl-capath',
       'ssl-cert','ssl-key','ssl-cipher','ssl-verify-server-cert','default-character-set','connect-timeout'],true) &&
      !isset($seen[$match[1]]),'Backup client options must contain only unique connection settings in [client]');
   $seen[$match[1]]=true;
  }
  need($client,'Private [client] options required');
 }

 // Adaptive options detection: MariaDB vs MySQL, user grants and schema objects
 $vStr = '';
 $vComment = '';
 try {
  $vStr = (string)$source->query('SELECT VERSION()')->fetchColumn();
  $vComment = (string)$source->query('SELECT @@version_comment')->fetchColumn();
 } catch (\Throwable) {}
 $serverInfo = rumoParseDbServerVersion($vStr, $vComment);
 $isMariaDB = $serverInfo['is_mariadb'];

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
 $grantLines = [];
 try {
  $stmt = $source->query('SHOW GRANTS');
  while ($row = $stmt->fetchColumn()) {
   $grantLines[] = (string)$row;
  }
 } catch (\Throwable $e) {
  throw new \RuntimeException('Cannot verify user grants via SHOW GRANTS: ' . $e->getMessage());
 }

 $privEval = rumoEvaluateMysqlPrivileges($grantLines, $name, $vStr, $vComment);
 $hasTriggerPriv = $privEval['has_trigger_priv'];
 $hasEventPriv = $privEval['has_event_priv'];
 $hasLockTablesPriv = $privEval['has_lock_tables_priv'];
 $hasShowViewPriv = $privEval['has_show_view_priv'];

 // Strict fail-closed: if the account lacks privileges to prove inventory integrity, declare the specific dependency
 if (!$hasTriggerPriv) {
  throw new \RuntimeException("Dependência de privilégio não atendida: o usuário do banco de dados não possui o privilégio 'TRIGGER' no database '{$name}' (nem 'ALL PRIVILEGES'). Sem essa permissão, o inventário de triggers é filtrado pelo MySQL/MariaDB e a integridade da exportação não pode ser comprovada.");
 }
 if (!$hasEventPriv) {
  throw new \RuntimeException("Dependência de privilégio não atendida: o usuário do banco de dados não possui o privilégio 'EVENT' no database '{$name}' (nem 'ALL PRIVILEGES'). Sem essa permissão, o inventário de eventos é filtrado pelo MySQL/MariaDB e a integridade da exportação não pode ser comprovada.");
 }
 if (!$privEval['routine_inventory_provable']) {
  // Fail-closed: static absence attestations are NOT accepted as a permanent guarantee
  // without a live server-side mechanism that prevents or detects subsequent routine changes.
  throw new \RuntimeException(rumoRoutinePrivilegeRequirementMessage($serverInfo, $name, false));
 }

 // Proven inventory counts (triggers, events and routine inventory visibility verified above)
 $evStmt = $source->prepare("SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?");
 $evStmt->execute([$name]);
 $evCount = (int)$evStmt->fetchColumn();

 $rtStmt = $source->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?");
 $rtStmt->execute([$name]);
 $rtCount = (int)$rtStmt->fetchColumn();

 if (!rumoCanProceedWithRoutines($privEval, $rtCount)) {
  throw new \RuntimeException(rumoRoutinePrivilegeRequirementMessage($serverInfo, $name, true));
 }
 $routineVerificationMode = $privEval['routine_export_supported'] ? 'effective_export_privilege' : 'inventory_verified_zero_routines';

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
  // Evidence-based validation of test dump completeness across MySQL 8 and MariaDB 10
  validateDumpCompleteness($dryDump, $tables, $views, $trCount, $rtCount, $evCount, $name);
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
   'routine_verification_mode' => $routineVerificationMode,
   'routine_priv_basis' => $privEval['routine_priv_basis'],
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
  validateDumpCompleteness($dump, $tables, $views, $trCount, $rtCount, $evCount, $name);
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
  validateDumpCompleteness($dump, $tables, $views, $trCount, $rtCount, $evCount, $name);
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
