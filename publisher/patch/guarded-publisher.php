<?php
declare(strict_types=1);

/**
 * RUMO10 | Guarded Publisher Cron Wrapper for UOL Host.
 * Provides mutual exclusion for Cron executions (guarded-cron.lock),
 * environment verification, secure execution without interfering with account_mutex,
 * and structured diagnostic logging.
 *
 * Usage: php guarded-publisher.php --config=/path/to/receiver-config.json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$opts = getopt('', ['config:', 'timeout:']);
$configPath = $opts['config'] ?? null;

$defaultConfig = '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json';
if (!$configPath && is_file($defaultConfig)) {
    $configPath = $defaultConfig;
}

if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "BLOCKED: Configuration file not found. Usage: php guarded-publisher.php --config=/path/to/receiver-config.json\n");
    exit(1);
}

if (PHP_OS_FAMILY !== 'Windows' && (fileperms($configPath) & 0077) !== 0) {
    fwrite(STDERR, "BLOCKED: Configuration file must have 0600 permissions\n");
    exit(1);
}

$configRaw = file_get_contents($configPath);
$c = json_decode($configRaw, true, 512, JSON_THROW_ON_ERROR);

// Mutual exclusion lock strictly for Cron wrapper to prevent concurrent cron overlaps.
// Note: account_mutex is NEVER acquired here; account_mutex belongs exclusively to controlled-pull / Publisher.
$cronLockFile = ($c['private'] ?? dirname($configPath) . '/private') . '/guarded-cron.lock';
if (!is_dir(dirname($cronLockFile))) {
    @mkdir(dirname($cronLockFile), 0700, true);
}

$lockFp = fopen($cronLockFile, 'c+');
if ($lockFp === false) {
    fwrite(STDERR, "BLOCKED: Cannot open cron lock file: {$cronLockFile}\n");
    exit(1);
}

if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('c') . "] [NOTICE] Outra instância do cron já está em execução. Encerrando execução do cron com segurança.\n";
    fclose($lockFp);
    exit(0);
}

// Discover PHP binary: prefer UOL Host production path if available
$phpBin = PHP_BINARY;
$uolPhp = '/opt/alt/php84/usr/bin/php';
if (PHP_OS_FAMILY !== 'Windows' && is_executable($uolPhp)) {
    $phpBin = $uolPhp;
}

$controlledPull = __DIR__ . '/controlled-pull.php';
if (!is_file($controlledPull)) {
    fwrite(STDERR, "BLOCKED: controlled-pull.php not found at {$controlledPull}\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

$cmd = [
    $phpBin,
    $controlledPull,
    '--config=' . $configPath,
    '--mode=apply',
    '--discover',
];

$timeoutSeconds = isset($opts['timeout']) ? (int)$opts['timeout'] : 300;
$logPrivateDir = $c['private'] ?? dirname($configPath) . '/private';
$errLog = $logPrivateDir . '/guarded-cron-err-' . bin2hex(random_bytes(6)) . '.log';
$outLog = $logPrivateDir . '/guarded-cron-out-' . bin2hex(random_bytes(6)) . '.log';

$p = proc_open($cmd, [
    0 => ['pipe', 'r'],
    1 => ['file', $outLog, 'wb'],
    2 => ['file', $errLog, 'wb'],
], $pipes);

if (!is_resource($p)) {
    fwrite(STDERR, "BLOCKED: Failed to launch controlled-pull process\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

fclose($pipes[0]);
$deadline = microtime(true) + $timeoutSeconds;
$exitCode = -1;
$timedOut = false;

do {
    $status = proc_get_status($p);
    if (!$status['running']) {
        $exitCode = $status['exitcode'];
        break;
    }
    if (microtime(true) > $deadline) {
        proc_terminate($p);
        $timedOut = true;
        break;
    }
    usleep(100000);
} while (true);

proc_close($p);

$outContent = is_file($outLog) ? trim(file_get_contents($outLog)) : '';
$errContent = is_file($errLog) ? trim(file_get_contents($errLog)) : '';
if (is_file($outLog)) @unlink($outLog);
if (is_file($errLog)) @unlink($errLog);

$sanitizedErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $errContent);
$sanitizedErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $sanitizedErr);

if ($timedOut) {
    echo "[" . date('c') . "] [ERROR] Processo controlled-pull excedeu o tempo limite de {$timeoutSeconds}s.\n";
    $exitCode = 124;
} elseif ($exitCode === 0) {
    echo "[" . date('c') . "] [OK] Publicação verificada e executada com sucesso.\n";
    if ($outContent !== '') {
        echo $outContent . PHP_EOL;
    }
} else {
    // Check if error was a backup preflight refusal before maintenance
    if (str_contains($sanitizedErr, 'Backup provider blocked') || str_contains($sanitizedErr, 'preflight') || str_contains($sanitizedErr, 'source-dump')) {
        $phase = 'source-dump';
        if (preg_match('/Phase:\s*([a-zA-Z0-9_\-]+)/', $sanitizedErr, $m)) $phase = $m[1];
        elseif (preg_match('/blocked:\s*([a-zA-Z0-9_\-]+)/', $sanitizedErr, $m)) $phase = $m[1];
        echo "[" . date('c') . "] [BACKUP] Prova completa recusada. Fase={$phase} codigo={$exitCode} timeout=" . ($timedOut ? 'yes' : 'no') . PHP_EOL;
        echo "[" . date('c') . "] [BLOCKED] Backup completo falhou ANTES da manutencao" . PHP_EOL;
    } else {
        echo "[" . date('c') . "] [BLOCKED] Execução do publicador recusada com código {$exitCode}" . PHP_EOL;
    }
    if ($sanitizedErr !== '') {
        echo "Detalhe técnico: " . $sanitizedErr . PHP_EOL;
    }
}

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit($exitCode);
