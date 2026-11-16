<?php
declare(strict_types=1);

/**
 * RUMO10 | Standalone Read-Only Diagnostic Tool for UOL Host.
 * Safe to run via Cron. Produces strictly technical, non-sensitive diagnostic report.
 * Usage: php diagnose-uol.php --config=/path/to/receiver-config.json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "CLI only\n";
    exit(1);
}

$opts = getopt('', ['config:', 'output:']);
$configPath = $opts['config'] ?? null;

$defaultConfig = '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json';
if (!$configPath && is_file($defaultConfig)) {
    $configPath = $defaultConfig;
}

if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "BLOCKED: Configuration file required. Specify --config=/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json\n");
    exit(1);
}

if (PHP_OS_FAMILY !== 'Windows' && (fileperms($configPath) & 0077) !== 0) {
    fwrite(STDERR, "BLOCKED: Configuration file must be 0600\n");
    exit(1);
}

$configData = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

// Resolve backup hook and profile from receiver configuration
$backupHook = $configData['hooks']['backup'] ?? null;
if (!is_array($backupHook)) {
    fwrite(STDERR, "BLOCKED: hooks.backup missing from receiver-config.json\n");
    exit(1);
}

$profilePath = null;
$hookArgs = $backupHook['args'] ?? [];
for ($i = 0; $i < count($hookArgs); $i++) {
    if ($hookArgs[$i] === '--profile' && isset($hookArgs[$i + 1])) {
        $profilePath = $hookArgs[$i + 1];
        break;
    }
}

if (!$profilePath || !is_file($profilePath)) {
    fwrite(STDERR, "BLOCKED: Backup profile missing or not found: {$profilePath}\n");
    exit(1);
}

$profileData = json_decode(file_get_contents($profilePath), true, 512, JSON_THROW_ON_ERROR);

$report = [
    'timestamp' => date('c'),
    'php_environment' => [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'os' => PHP_OS_FAMILY,
        'proc_open_available' => function_exists('proc_open'),
        'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
        'json_loaded' => extension_loaded('json'),
    ],
    'receiver_configuration' => [
        'config_file' => $configPath,
        'project' => $configData['project'] ?? 'unknown',
        'current_dir' => $configData['current'] ?? 'unknown',
        'control_dir' => $configData['control'] ?? 'unknown',
        'public_dir' => $configData['public'] ?? 'unknown',
        'hook_script' => $backupHook['script'] ?? 'unknown',
        'hook_pin_matches' => is_file($backupHook['script'] ?? '') && (hash_file('sha256', $backupHook['script']) === ($backupHook['sha256'] ?? '')),
    ],
    'database' => [
        'connection' => 'PENDING',
        'engine' => 'UNKNOWN',
        'version' => 'UNKNOWN',
        'all_tables_innodb' => false,
        'table_count' => 0,
        'events_in_db' => 0,
        'routines_in_db' => 0,
        'triggers_in_db' => 0,
        'views_in_db' => 0,
    ],
    'privilege_capabilities' => [
        'has_event_privilege' => false,
        'has_routine_privilege' => false,
        'has_trigger_privilege' => false,
        'has_lock_tables_privilege' => false,
    ],
    'dump_binary' => [
        'selected_path' => 'NONE',
        'executable' => false,
        'version' => 'NONE',
        'supports_column_statistics' => false,
        'supports_tablespaces' => false,
        'supports_gtid' => false,
    ],
    'options_compatibility' => [],
    'dry_run_dump' => 'NOT_TESTED',
    'preflight_verdict' => 'BLOCKED',
];

// Database connection
$password = '';
if (isset($profileData['source_password_file']) && is_file($profileData['source_password_file'])) {
    $password = trim(file_get_contents($profileData['source_password_file']));
} elseif (isset($profileData['source_password'])) {
    $password = $profileData['source_password'];
}

$pdo = null;
$dbName = null;
try {
    $pdo = new PDO($profileData['source_dsn'] ?? '', $profileData['source_user'] ?? '', $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $report['database']['connection'] = 'CONNECTED';
    $vStr = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $vComm = (string)$pdo->query('SELECT @@version_comment')->fetchColumn();
    $isMariaDB = (stripos($vStr, 'MariaDB') !== false || stripos($vComm, 'MariaDB') !== false);
    $report['database']['engine'] = $isMariaDB ? 'MariaDB' : 'MySQL 8';
    $report['database']['version'] = $vStr;

    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

    // Tables check
    $tablesStmt = $pdo->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
    $tablesStmt->execute([$dbName]);
    $tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);
    $report['database']['table_count'] = count($tables);

    $allInnoDB = true;
    foreach ($tables as $t) {
        if (strtoupper($t['ENGINE'] ?? '') !== 'INNODB') {
            $allInnoDB = false;
            break;
        }
    }
    $report['database']['all_tables_innodb'] = $allInnoDB;

    // Events, routines, triggers and views counts
    $evCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
    $report['database']['events_in_db'] = $evCount;

    $rtCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
    $report['database']['routines_in_db'] = $rtCount;

    $trCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
    $report['database']['triggers_in_db'] = $trCount;

    $viewsStmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ?");
    $viewsStmt->execute([$dbName]);
    $views = $viewsStmt->fetchAll(PDO::FETCH_COLUMN);
    $report['database']['views_in_db'] = count($views);

    // Grants check (rigorous parsing for database/global scope)
    $stmt = $pdo->query('SHOW GRANTS');
    while ($row = $stmt->fetchColumn()) {
        $line = strtoupper((string)$row);
        $appliesToDb = str_contains($line, 'ON *.*') || 
                       str_contains($line, 'ON `' . strtoupper($dbName) . '`.*') ||
                       str_contains($line, 'ON ' . strtoupper($dbName) . '.*');

        if ($appliesToDb) {
            if (str_contains($line, 'ALL PRIVILEGES')) {
                $report['privilege_capabilities']['has_event_privilege'] = true;
                $report['privilege_capabilities']['has_routine_privilege'] = true;
                $report['privilege_capabilities']['has_trigger_privilege'] = true;
                $report['privilege_capabilities']['has_lock_tables_privilege'] = true;
            }
            if (str_contains($line, 'EVENT')) $report['privilege_capabilities']['has_event_privilege'] = true;
            if (str_contains($line, 'ROUTINE') || str_contains($line, 'EXECUTE')) $report['privilege_capabilities']['has_routine_privilege'] = true;
            if (str_contains($line, 'TRIGGER')) $report['privilege_capabilities']['has_trigger_privilege'] = true;
            if (str_contains($line, 'LOCK TABLES')) $report['privilege_capabilities']['has_lock_tables_privilege'] = true;
        }
    }
} catch (\Throwable $e) {
    $report['database']['connection'] = 'FAILED: ' . $e->getMessage();
}

// Check missing dependencies
$missingDeps = [];
if (!$report['privilege_capabilities']['has_trigger_privilege']) {
    $missingDeps[] = "Privilégio 'TRIGGER' no banco '{$dbName}' não comprovado em SHOW GRANTS";
}
if (!$report['privilege_capabilities']['has_event_privilege']) {
    $missingDeps[] = "Privilégio 'EVENT' no banco '{$dbName}' não comprovado em SHOW GRANTS";
}
if (!$report['privilege_capabilities']['has_routine_privilege']) {
    $missingDeps[] = "Privilégio 'ROUTINE'/'EXECUTE' no banco '{$dbName}' não comprovado em SHOW GRANTS";
}
$report['privilege_capabilities']['missing_dependencies'] = $missingDeps;

// Dump binary discovery
$configuredBin = $profileData['dump_bin'] ?? null;
$candidates = PHP_OS_FAMILY === 'Windows'
    ? ['C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe', 'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe', 'mysqldump.exe']
    : ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump', '/usr/local/cpanel/3rdparty/bin/mysqldump', 'mysqldump'];

if ($configuredBin) {
    array_unshift($candidates, $configuredBin);
}

$foundBin = null;
$supportsColumnStats = false;
$supportsTablespaces = false;
$supportsGtid = false;
$supportsSkipNoData = false;

foreach ($candidates as $cand) {
    if (is_file($cand) && (PHP_OS_FAMILY === 'Windows' || is_executable($cand))) {
        $binQuoted = (PHP_OS_FAMILY === 'Windows' && str_contains($cand, ' ')) ? '"' . $cand . '"' : escapeshellcmd($cand);
        $cmd = $binQuoted . ' --version 2>&1';
        $out = @shell_exec($cmd);
        if ($out) {
            $foundBin = $cand;
            $report['dump_binary']['selected_path'] = $cand;
            $report['dump_binary']['executable'] = true;
            $report['dump_binary']['version'] = trim($out);

            $helpOut = (string)@shell_exec($binQuoted . ' --help 2>&1');
            $supportsColumnStats = str_contains($helpOut, 'column-statistics');
            $supportsTablespaces = str_contains($helpOut, 'no-tablespaces') || str_contains($helpOut, 'all-tablespaces');
            $supportsGtid = str_contains($helpOut, 'set-gtid-purged');
            $supportsSkipNoData = str_contains($helpOut, 'no-data');

            $report['dump_binary']['supports_column_statistics'] = $supportsColumnStats;
            $report['dump_binary']['supports_tablespaces'] = $supportsTablespaces;
            $report['dump_binary']['supports_gtid'] = $supportsGtid;
            break;
        }
    }
}

// Dry run test with mysqldump
if ($foundBin && $pdo && $dbName && count($tables) > 0 && empty($missingDeps)) {
    $options = ['--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks', '--skip-comments', '--hex-blob', '--triggers'];

    if ($supportsSkipNoData) {
        $options[] = '--skip-no-data';
    }
    if ($supportsColumnStats) {
        $options[] = '--skip-column-statistics';
    }
    if ($supportsTablespaces) {
        $options[] = '--no-tablespaces';
    }
    if ($supportsGtid && $report['database']['engine'] !== 'MariaDB') {
        $options[] = '--set-gtid-purged=OFF';
    }
    if ($report['database']['events_in_db'] > 0) {
        $options[] = '--events';
    }
    if ($report['database']['routines_in_db'] > 0) {
        $options[] = '--routines';
    }

    $report['options_compatibility'] = $options;

    $diagPrivBase = $configData['private'] ?? dirname($configPath) . '/private';
    if (!is_dir($diagPrivBase)) @mkdir($diagPrivBase, 0700, true);
    $secureDiagDir = $diagPrivBase . '/.diag-tmp-' . bin2hex(random_bytes(6));
    @mkdir($secureDiagDir, 0700, true);

    $tempCnf = $secureDiagDir . '/diag-cnf.cnf';
    $host = '127.0.0.1';
    $port = 3306;
    if (!empty($profileData['source_dsn'])) {
        if (preg_match('/host=([^;]+)/', $profileData['source_dsn'], $m)) $host = $m[1];
        if (preg_match('/port=([0-9]+)/', $profileData['source_dsn'], $m)) $port = (int)$m[1];
    }
    file_put_contents($tempCnf, "[client]\nuser=" . ($profileData['source_user'] ?? 'root') . "\npassword=" . $password . "\nhost=" . $host . "\nport=" . $port . "\n");
    chmod($tempCnf, 0600);

    $dryDump = $secureDiagDir . '/diag-dump.sql';
    // True preflight in diagnostic: test FULL export with data
    $cmd = array_merge([$foundBin, '--defaults-file=' . $tempCnf], $options, [$dbName]);

    $errLog = $secureDiagDir . '/diag-err.log';
    $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $dryDump, 'wb'], 2 => ['file', $errLog, 'wb']], $pipes);
    if (is_resource($p)) {
        fclose($pipes[0]);
        $exit = proc_close($p);
        $errOut = is_file($errLog) ? trim(file_get_contents($errLog)) : '';
        if ($exit === 0 && is_file($dryDump) && filesize($dryDump) > 0) {
            $report['dry_run_dump'] = 'SUCCESS (Full data test passed)';
            $report['preflight_verdict'] = 'PASS';
        } else {
            $cleanErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $errOut);
            $cleanErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $cleanErr);
            $report['dry_run_dump'] = 'FAILED (exit ' . $exit . '): ' . $cleanErr;
            $report['preflight_verdict'] = 'BLOCKED';
            @file_put_contents($diagPrivBase . '/diagnostic-error.log', $cleanErr);
            @chmod($diagPrivBase . '/diagnostic-error.log', 0600);
        }
    }
    if (is_dir($secureDiagDir)) {
        $tFiles = glob($secureDiagDir . '/*');
        foreach ($tFiles as $tf) { if (is_file($tf)) @unlink($tf); }
        @rmdir($secureDiagDir);
    }
} elseif (!empty($missingDeps)) {
    $report['preflight_verdict'] = 'BLOCKED: Missing database privileges (' . implode(', ', $missingDeps) . ')';
}

$outputJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo $outputJson . PHP_EOL;

$logDest = $opts['output'] ?? dirname($configPath) . '/diagnostic.log';
try {
    file_put_contents($logDest, $outputJson);
    @chmod($logDest, 0600);
    echo "\nDiagnostic report written to: {$logDest}\n";
} catch (\Throwable $e) {
    echo "\nWarning: Could not write log file: " . $e->getMessage() . "\n";
}

exit($report['preflight_verdict'] === 'PASS' ? 0 : 1);
