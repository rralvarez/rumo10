<?php
declare(strict_types=1);

/**
 * Standalone read-only diagnostic tool for UOL Host MySQL backup environment.
 * Safe to run via Cron. Produces sanitized, non-secret diagnostic log.
 * Usage: php diagnose-uol.php [--profile=/path/to/profile.json] [--config=/path/to/private.json]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "CLI only\n";
    exit(1);
}

$opts = getopt('', ['profile:', 'config:', 'output:']);
$profilePath = $opts['profile'] ?? null;
$configPath = $opts['config'] ?? null;

// Candidate paths on UOL Host and local environments
$candidateConfigs = [
    $configPath,
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/config/rumo10.json',
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/private.json',
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/config.json',
    dirname(__DIR__) . '/config/rumo10.json',
    dirname(__DIR__, 2) . '/private.json',
    '/home/rumoacame144a99e/private-publisher/rumo10/private.json',
    dirname(__DIR__) . '/config/rumo10.example.json',
    dirname(__DIR__, 2) . '/private-publisher/rumo10/private.json',
];

$candidateProfiles = [
    $profilePath,
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/backup-profile.json',
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/private-profile.json',
    '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/config/backup-profile.json',
    '/home/rumoacame144a99e/private-publisher/rumo10/backup-profile.json',
    '/home/rumoacame144a99e/private-publisher/rumo10/private-profile.json',
    dirname(__DIR__) . '/config/backup-profile.example.json',
];

$resolvedConfig = null;
foreach ($candidateConfigs as $cand) {
    if ($cand && is_file($cand)) {
        $resolvedConfig = $cand;
        break;
    }
}

$resolvedProfile = null;
foreach ($candidateProfiles as $cand) {
    if ($cand && is_file($cand)) {
        $resolvedProfile = $cand;
        break;
    }
}

$report = [
    'timestamp' => date('c'),
    'system' => [],
    'publisher_state' => [],
    'database' => [],
    'privileges' => [],
    'schema_objects' => [],
    'dump_binary' => [],
    'options_compatibility' => [],
    'authentication_test' => [],
    'diagnosis_summary' => [],
];

function sanitize(string $text): string {
    $text = preg_replace('/password[\'"]?\s*[:=]\s*[\'"][^\'"]*[\'"]/i', 'password="[REDACTED]"', $text);
    $text = preg_replace('/(identified\s+by\s+password\s+)[\'"][^\'"]*[\'"]/i', '$1"[REDACTED]"', $text);
    $text = preg_replace('/(identified\s+by\s+)[\'"][^\'"]*[\'"]/i', '$1"[REDACTED]"', $text);
    $text = preg_replace('/--password=[^\s]+/i', '--password=[REDACTED]', $text);
    return $text;
}

// 1. System & PHP Environment
$report['system']['php_version'] = PHP_VERSION;
$report['system']['php_sapi'] = PHP_SAPI;
$report['system']['php_os'] = PHP_OS_FAMILY;
$report['system']['uname'] = php_uname();
$report['system']['proc_open_available'] = function_exists('proc_open');
$report['system']['shell_exec_available'] = function_exists('shell_exec');
$report['system']['pdo_mysql_loaded'] = extension_loaded('pdo_mysql');
$report['system']['curl_loaded'] = extension_loaded('curl');
$report['system']['json_loaded'] = extension_loaded('json');

// Check disable_functions
$disabled = array_filter(array_map('trim', explode(',', ini_get('disable_functions') ?: '')));
$report['system']['disabled_functions'] = $disabled;

// 2. Publisher Paths & State
$configData = [];
if ($resolvedConfig) {
    $report['publisher_state']['config_file'] = $resolvedConfig;
    try {
        $configData = json_decode(file_get_contents($resolvedConfig), true, 512, JSON_THROW_ON_ERROR);
        $report['publisher_state']['project'] = $configData['project'] ?? 'unknown';
        $report['publisher_state']['private_dir'] = $configData['private'] ?? 'none';
        $report['publisher_state']['current_dir'] = $configData['current'] ?? 'none';
        $report['publisher_state']['control_dir'] = $configData['control'] ?? 'none';
        $report['publisher_state']['public_dir'] = $configData['public'] ?? 'none';

        // Check locks and journal files
        $privateDir = $configData['private'] ?? '';
        $controlDir = $configData['control'] ?? '';
        if ($privateDir && is_dir($privateDir)) {
            $journalPath = $privateDir . '/journal.json';
            if (is_file($journalPath)) {
                $jData = json_decode(file_get_contents($journalPath), true) ?: [];
                $report['publisher_state']['journal_exists'] = true;
                $report['publisher_state']['journal_state'] = $jData['state'] ?? 'unknown';
                $report['publisher_state']['journal_attempt'] = $jData['attempt'] ?? 'unknown';
            } else {
                $report['publisher_state']['journal_exists'] = false;
            }

            $drainPath = $privateDir . '/drain-session.json';
            if (is_file($drainPath)) {
                $dData = json_decode(file_get_contents($drainPath), true) ?: [];
                $report['publisher_state']['drain_session_exists'] = true;
                $report['publisher_state']['drain_session_state'] = $dData['state'] ?? 'unknown';
            } else {
                $report['publisher_state']['drain_session_exists'] = false;
            }
        }

        if ($controlDir && is_dir($controlDir)) {
            $lockPath = $controlDir . '/deployment.lock';
            $report['publisher_state']['deployment_lock_exists'] = is_file($lockPath);
        }
    } catch (\Throwable $e) {
        $report['publisher_state']['config_error'] = $e->getMessage();
    }
}

// 3. Database Connectivity
$profileData = [];
if ($resolvedProfile) {
    $report['database']['profile_file'] = $resolvedProfile;
    try {
        $profileData = json_decode(file_get_contents($resolvedProfile), true, 512, JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        $report['database']['profile_error'] = $e->getMessage();
    }
} else {
    // Check if backup hook args in config point to profile
    $hookBackup = $configData['hooks']['backup'] ?? null;
    if (is_array($hookBackup)) {
        $args = $hookBackup['args'] ?? [];
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '--profile' && isset($args[$i + 1]) && is_file($args[$i + 1])) {
                $resolvedProfile = $args[$i + 1];
                $report['database']['profile_file'] = $resolvedProfile;
                try {
                    $profileData = json_decode(file_get_contents($resolvedProfile), true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $report['database']['profile_error'] = $e->getMessage();
                }
                break;
            }
        }
    }
}

$pdo = null;
$dbName = null;
if (!empty($profileData['source_dsn']) && !empty($profileData['source_user'])) {
    $report['database']['dsn'] = preg_replace('/password=[^;]+/i', 'password=[REDACTED]', $profileData['source_dsn']);
    $report['database']['user'] = $profileData['source_user'];
    $passFile = $profileData['source_password_file'] ?? null;
    $password = '';
    if ($passFile && is_file($passFile)) {
        $password = trim(file_get_contents($passFile));
        $report['database']['password_file_exists'] = true;
        $report['database']['password_file_perms'] = substr(sprintf('%o', fileperms($passFile)), -4);
    } elseif (isset($profileData['source_password'])) {
        $password = $profileData['source_password'];
        $report['database']['password_inline'] = true;
    } else {
        $report['database']['password_missing'] = true;
    }

    try {
        $pdo = new PDO($profileData['source_dsn'], $profileData['source_user'], $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $report['database']['connection_status'] = 'CONNECTED';
        $report['database']['server_version'] = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $report['database']['version_comment'] = (string)$pdo->query('SELECT @@version_comment')->fetchColumn();
        $report['database']['collation'] = (string)$pdo->query('SELECT @@collation_database')->fetchColumn();

        try {
            $report['database']['server_uuid'] = (string)$pdo->query('SELECT @@server_uuid')->fetchColumn();
        } catch (\Throwable $e) {
            $report['database']['server_uuid'] = 'UNAVAILABLE (' . $e->getMessage() . ')';
        }

        try {
            $report['database']['gtid_mode'] = (string)$pdo->query('SELECT @@gtid_mode')->fetchColumn();
        } catch (\Throwable) {
            $report['database']['gtid_mode'] = 'NOT_SUPPORTED';
        }

        try {
            $report['database']['event_scheduler'] = (string)$pdo->query('SELECT @@event_scheduler')->fetchColumn();
        } catch (\Throwable) {
            $report['database']['event_scheduler'] = 'NOT_SUPPORTED';
        }

        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $report['database']['current_database'] = $dbName;
    } catch (\Throwable $e) {
        $report['database']['connection_status'] = 'FAILED';
        $report['database']['connection_error'] = sanitize($e->getMessage());
    }
} else {
    $report['database']['connection_status'] = 'SKIPPED (no profile found)';
}

// 4. Privileges & Grants
if ($pdo) {
    try {
        $grantsStmt = $pdo->query('SHOW GRANTS');
        $rawGrants = [];
        $hasEvent = false;
        $hasProcess = false;
        $hasRoutine = false;
        $hasTrigger = false;
        $hasLockTables = false;
        $isSuper = false;

        while ($row = $grantsStmt->fetchColumn()) {
            $cleanGrant = sanitize((string)$row);
            $rawGrants[] = $cleanGrant;
            if (stripos($row, 'ALL PRIVILEGES') !== false) {
                $hasEvent = true;
                $hasProcess = true;
                $hasRoutine = true;
                $hasTrigger = true;
                $hasLockTables = true;
                $isSuper = true;
            }
            if (stripos($row, 'EVENT') !== false) $hasEvent = true;
            if (stripos($row, 'PROCESS') !== false) $hasProcess = true;
            if (stripos($row, 'ROUTINE') !== false || stripos($row, 'EXECUTE') !== false) $hasRoutine = true;
            if (stripos($row, 'TRIGGER') !== false) $hasTrigger = true;
            if (stripos($row, 'LOCK TABLES') !== false) $hasLockTables = true;
            if (stripos($row, 'SUPER') !== false) $isSuper = true;
        }

        $report['privileges']['grants'] = $rawGrants;
        $report['privileges']['has_event'] = $hasEvent;
        $report['privileges']['has_process'] = $hasProcess;
        $report['privileges']['has_routine'] = $hasRoutine;
        $report['privileges']['has_trigger'] = $hasTrigger;
        $report['privileges']['has_lock_tables'] = $hasLockTables;
        $report['privileges']['is_super'] = $isSuper;
    } catch (\Throwable $e) {
        $report['privileges']['error'] = sanitize($e->getMessage());
    }

    // 5. Schema Objects Check
    try {
        $tablesStmt = $pdo->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'");
        $tablesStmt->execute([$dbName]);
        $tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);
        $report['schema_objects']['tables_count'] = count($tables);

        $nonInnoDB = [];
        foreach ($tables as $t) {
            if (strtoupper($t['ENGINE'] ?? '') !== 'INNODB') {
                $nonInnoDB[] = $t['TABLE_NAME'] . ' (' . ($t['ENGINE'] ?? 'none') . ')';
            }
        }
        $report['schema_objects']['all_innodb'] = count($nonInnoDB) === 0;
        $report['schema_objects']['non_innodb_tables'] = $nonInnoDB;

        // Count events in database
        $evCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
        $report['schema_objects']['events_in_db'] = $evCount;

        // Count routines in database
        $rtCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
        $report['schema_objects']['routines_in_db'] = $rtCount;

        // Count triggers in database
        $trCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = " . $pdo->quote($dbName))->fetchColumn();
        $report['schema_objects']['triggers_in_db'] = $trCount;
    } catch (\Throwable $e) {
        $report['schema_objects']['error'] = sanitize($e->getMessage());
    }
}

// 6. mysqldump / mariadb-dump Binary Discovery
$configuredBin = $profileData['dump_bin'] ?? null;
$candidates = PHP_OS_FAMILY === 'Windows'
    ? ['C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe', 'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe', 'mysqldump.exe']
    : ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump', '/usr/local/cpanel/3rdparty/bin/mysqldump', 'mysqldump'];

if ($configuredBin) {
    array_unshift($candidates, $configuredBin);
}

$foundBin = null;
$binaryProbe = [];
foreach ($candidates as $cand) {
    $exists = is_file($cand);
    $executable = $exists && (PHP_OS_FAMILY === 'Windows' || is_executable($cand));
    $verOutput = '';
    if ($executable || (!file_exists($cand) && $cand === 'mysqldump')) {
        $binQuoted = (PHP_OS_FAMILY === 'Windows' && str_contains($cand, ' ')) ? '"' . $cand . '"' : escapeshellcmd($cand);
        $cmd = $binQuoted . ' --version 2>&1';
        $out = @shell_exec($cmd);
        if ($out) {
            $verOutput = trim($out);
            if (!$foundBin) {
                $foundBin = $cand;
            }
        }
    }
    $binaryProbe[$cand] = [
        'is_file' => $exists,
        'is_executable' => $executable,
        'version_output' => $verOutput ?: 'NOT_EXECUTABLE',
    ];
}

$report['dump_binary']['candidates_probe'] = $binaryProbe;
$report['dump_binary']['selected_binary'] = $foundBin ?: 'NONE_FOUND';

// 7. CLI Option Compatibility Probing
if ($foundBin) {
    $testOptions = [
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--skip-add-locks',
        '--skip-comments',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--order-by-primary',
        '--hex-blob',
        '--triggers',
        '--routines',
        '--events',
    ];

    $binQuoted = (PHP_OS_FAMILY === 'Windows' && str_contains($foundBin, ' ')) ? '"' . $foundBin . '"' : escapeshellcmd($foundBin);
    $optionResults = [];
    foreach ($testOptions as $opt) {
        // Run mysqldump --help with the option or dry test to see if it causes unknown option error
        $cmd = $binQuoted . ' ' . escapeshellarg($opt) . ' --help 2>&1';
        $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($p);
            $fullOutput = $stdout . ' ' . $stderr;
            if (stripos($fullOutput, 'unknown option') !== false || stripos($fullOutput, 'unknown variable') !== false) {
                $optionResults[$opt] = 'UNSUPPORTED_OPTION';
            } elseif ($exit === 0 || stripos($stdout, 'Usage: mysqldump') !== false || stripos($stdout, 'Usage: mariadb-dump') !== false) {
                $optionResults[$opt] = 'SUPPORTED_BY_CLI';
            } else {
                $optionResults[$opt] = 'EXIT_' . $exit;
            }
        } else {
            $optionResults[$opt] = 'PROC_FAILED';
        }
    }
    $report['options_compatibility'] = $optionResults;
}

// 8. Authentication & Dump Capability Test
if ($foundBin && $pdo && $dbName) {
    $defaultsFile = $profileData['source_mysql_defaults'] ?? null;
    $authMethod = 'NONE';
    $tempDefaultsCreated = false;
    $tempDefaultsPath = null;

    if ($defaultsFile && is_file($defaultsFile)) {
        $authMethod = 'CONFIGURED_DEFAULTS_FILE';
        $effectiveDefaults = $defaultsFile;
    } else {
        // Test creating temporary defaults file
        $host = '127.0.0.1';
        $port = 3306;
        if (!empty($profileData['source_dsn'])) {
            if (preg_match('/host=([^;]+)/', $profileData['source_dsn'], $m)) $host = $m[1];
            if (preg_match('/port=([0-9]+)/', $profileData['source_dsn'], $m)) $port = (int)$m[1];
        }
        $tempDefaultsPath = sys_get_temp_dir() . '/test-dump-diag-' . bin2hex(random_bytes(6)) . '.cnf';
        $cnf = "[client]\nuser=" . ($profileData['source_user'] ?? 'root') . "\npassword=" . $password . "\nhost=" . $host . "\nport=" . $port . "\n";
        file_put_contents($tempDefaultsPath, $cnf);
        chmod($tempDefaultsPath, 0600);
        $effectiveDefaults = $tempDefaultsPath;
        $tempDefaultsCreated = true;
        $authMethod = 'GENERATED_TEMPORARY_DEFAULTS_FILE';
    }

    $report['authentication_test']['method'] = $authMethod;

    // Test a dry dump of 0 rows / 1 table schema
    $firstTable = $tables[0]['TABLE_NAME'] ?? null;
    if ($firstTable) {
        $testDumpCmd = [
            $foundBin,
            '--defaults-file=' . $effectiveDefaults,
            '--no-data',
            '--single-transaction',
            $dbName,
            $firstTable,
        ];

        $p = proc_open($testDumpCmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            fclose($pipes[0]);
            $dumpOut = stream_get_contents($pipes[1]);
            $dumpErr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $dumpExit = proc_close($p);
            $report['authentication_test']['dry_dump_exit'] = $dumpExit;
            if ($dumpExit === 0) {
                $report['authentication_test']['dry_dump_status'] = 'SUCCESS';
                $report['authentication_test']['bytes_produced'] = strlen($dumpOut);
            } else {
                $report['authentication_test']['dry_dump_status'] = 'FAILED';
                $report['authentication_test']['error_output'] = sanitize($dumpErr);
            }
        }
    }

    if ($tempDefaultsCreated && $tempDefaultsPath && is_file($tempDefaultsPath)) {
        @unlink($tempDefaultsPath);
    }
}

// 9. Root Cause Synthesis & Recommendations
$recs = [];
$rootCauses = [];

if (empty($report['dump_binary']['selected_binary']) || $report['dump_binary']['selected_binary'] === 'NONE_FOUND') {
    $rootCauses[] = 'mysqldump/mariadb-dump executable not found in PATH or standard candidate directories.';
    $recs[] = 'Specify "dump_bin": "/usr/bin/mysqldump" explicitly in backup profile.';
}

if (!empty($report['options_compatibility']['--set-gtid-purged=OFF']) && $report['options_compatibility']['--set-gtid-purged=OFF'] === 'UNSUPPORTED_OPTION') {
    $rootCauses[] = '--set-gtid-purged=OFF is unrecognized by MariaDB/current dump binary.';
    $recs[] = 'Omit --set-gtid-purged=OFF on MariaDB or older MySQL variants.';
}

if (!empty($report['options_compatibility']['--no-tablespaces']) && $report['options_compatibility']['--no-tablespaces'] === 'UNSUPPORTED_OPTION') {
    $rootCauses[] = '--no-tablespaces is unrecognized by current dump binary.';
    $recs[] = 'Omit --no-tablespaces on MariaDB.';
}

if (isset($report['privileges']['has_event']) && !$report['privileges']['has_event']) {
    $rootCauses[] = 'Database user lacks EVENT privilege. Passing --events causes access denied error 1044.';
    $recs[] = 'Omit --events when EVENT privilege is not granted and zero events exist in database.';
}

if (isset($report['database']['password_missing']) && $report['database']['password_missing']) {
    $rootCauses[] = 'Database credentials missing or source_mysql_defaults not specified.';
    $recs[] = 'Ensure source_password_file or source_mysql_defaults is present with 0600 permissions.';
}

if (empty($rootCauses)) {
    $rootCauses[] = 'No hard incompatibilities identified in static probes. Verify runtime stderr capture.';
}

$report['diagnosis_summary']['identified_issues'] = $rootCauses;
$report['diagnosis_summary']['recommended_actions'] = $recs;

// Output report
$jsonOutput = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo $jsonOutput . PHP_EOL;

// Save to sanitized log file in private directory or current directory
$logDest = $opts['output'] ?? null;
if (!$logDest) {
    if (!empty($configData['private']) && is_dir($configData['private'])) {
        $logDest = $configData['private'] . '/diagnostic.log';
    } elseif (is_dir(dirname(__DIR__, 2))) {
        $logDest = dirname(__DIR__, 2) . '/diagnostic.log';
    } else {
        $logDest = __DIR__ . '/diagnostic.log';
    }
}

try {
    file_put_contents($logDest, $jsonOutput);
    @chmod($logDest, 0600);
    echo "\nDiagnostic report written to: {$logDest}\n";
} catch (\Throwable $e) {
    echo "\nCould not write log file: " . $e->getMessage() . "\n";
}

exit(0);
