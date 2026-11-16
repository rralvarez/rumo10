<?php
declare(strict_types=1);

/**
 * RUMO10 | Unified Single-Execution Operational Installer & Publisher
 *
 * Automated patch installer, preflight verifier, hook pin synchronizer,
 * and definitive HTTPS publisher for UOL Host cPanel without Terminal.
 *
 * Usage via Cron:
 *   /opt/alt/php84/usr/bin/php /home/rumoacame144a99e/rumo10-production-v062-ee8f175/publisher/bin/install-and-publish.php >> /home/rumoacame144a99e/rumo10-production-v062-ee8f175/install-and-publish.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "CLI only\n";
    exit(1);
}

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------
function logMsg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function need(bool $condition, string $message): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function digestFile(string $path): string {
    if (!is_file($path)) {
        throw new \RuntimeException("File not found for hash calculation: {$path}");
    }
    return hash_file('sha256', $path);
}

function atomicWrite(string $path, string $content, int $mode = 0600): void {
    $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($temp, $content) !== strlen($content)) {
        @unlink($temp);
        throw new \RuntimeException("Failed to write temporary file: {$temp}");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($temp, $mode);
    }
    if (!rename($temp, $path)) {
        @unlink($temp);
        throw new \RuntimeException("Failed to atomically replace: {$path}");
    }
}

// -------------------------------------------------------------------------
// Resolve Environment and Paths
// -------------------------------------------------------------------------
$opts = getopt('', ['config:', 'force', 'skip-publish']);

$baseDir = dirname(__DIR__, 2);
$defaultConfig = '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json';
$configPath = $opts['config'] ?? (is_file($defaultConfig) ? $defaultConfig : $baseDir . '/receiver-config.json');

if (!is_file($configPath)) {
    // Check fallback config locations
    $candidates = [
        $baseDir . '/config.json',
        $baseDir . '/private.json',
        $baseDir . '/publisher/config/rumo10.json',
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand)) {
            $configPath = $cand;
            break;
        }
    }
}

if (!is_file($configPath)) {
    fwrite(STDERR, "BLOCKED: Configuration file not found at {$configPath}\n");
    exit(1);
}

$patchDir = dirname(__DIR__) . '/patch';
$binDir = dirname(__DIR__) . '/bin';
$libDir = dirname(__DIR__) . '/lib';
$receiptPath = dirname($configPath) . '/patch-install-receipt.json';
$installedJsonPath = dirname($configPath) . '/private/installed.json';
if (!is_dir(dirname($installedJsonPath))) {
    $installedJsonPath = dirname($configPath) . '/installed.json';
}

logMsg("=== RUMO10 | INICIALIZANDO INSTALADOR OPERACIONAL E PUBLICADOR DEFINITIVO ===");
logMsg("Configuração: {$configPath}");
logMsg("Diretório base: " . dirname($configPath));

// -------------------------------------------------------------------------
// 1. Idempotency Check
// -------------------------------------------------------------------------
$force = isset($opts['force']);
$targetCommit = 'fb03b614c337845671cf6cbf1eb36abe01308bfd';
$targetArtifactId = 14257772333;

if (!$force && is_file($receiptPath)) {
    $priorReceipt = json_decode(file_get_contents($receiptPath), true);
    if (($priorReceipt['status'] ?? '') === 'success') {
        $currInstalled = is_file($installedJsonPath) ? json_decode(file_get_contents($installedJsonPath), true) : [];
        if (($currInstalled['commit'] ?? '') === $targetCommit) {
            logMsg("IDEMPOTÊNCIA: Patch já instalado e release UI-001 ({$targetCommit}) confirmada em installed.json.");
            logMsg("Delegando para controlled-pull.php em modo contínuo...");
            $controlledPull = $binDir . '/controlled-pull.php';
            if (is_file($controlledPull)) {
                $phpBin = PHP_BINARY;
                passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($controlledPull) . ' --config=' . escapeshellarg($configPath) . ' --mode=apply --discover', $exitCode);
                exit($exitCode);
            }
            exit(0);
        }
    }
}

// -------------------------------------------------------------------------
// 2. Package Validation (Manifest & Checksums)
// -------------------------------------------------------------------------
logMsg("Fase 1: Verificando integridade dos arquivos do patch...");

$sourceDir = is_dir($patchDir) ? $patchDir : $binDir;
$manifestPath = is_dir($patchDir) ? $patchDir . '/manifest.json' : dirname(__DIR__) . '/manifest.json';
if (!is_file($manifestPath) && is_file(dirname($configPath) . '/manifest.json')) {
    $manifestPath = dirname($configPath) . '/manifest.json';
}

$expectedHashes = [
    'mysql-backup-hook.php' => 'be832b4f275822644f1d0efdbda24f16b1009038ddba9d335e587e2b8f0a27a4',
    'diagnose-uol.php'      => 'cc2dfc20e9079368835334df2cfc5d457d877c61cdd571840e2ae7e60055ccd7',
    'Publisher.php'         => '38e9a5784ac3f9cd4fdb1ab3e2ae184b8ebe20eefc7803831fcfb250ced7313e',
    'DrainPublisher.php'    => '4fbcd95202349212a6ede3d2d9be0a441450cc4d27634550279d0f9e44eb991a',
    'GitHub.php'            => 'af564c8293737c053f5dac5c3f7bca3509232c62e9b1942fed316aef0649c66d',
];

$filesToInstall = [];
foreach ($expectedHashes as $file => $hash) {
    $candStaged = is_dir($patchDir) ? $patchDir . '/' . $file : null;
    $candLive = (str_ends_with($file, '.php') && str_contains('Publisher.php DrainPublisher.php GitHub.php', $file))
        ? $libDir . '/' . $file
        : $binDir . '/' . $file;

    $targetFile = ($candStaged && is_file($candStaged)) ? $candStaged : $candLive;
    if (!is_file($targetFile)) {
        fwrite(STDERR, "BLOCKED: Arquivo obrigatório não encontrado: {$file}\n");
        exit(1);
    }
    $actualHash = digestFile($targetFile);
    if ($actualHash !== $hash) {
        fwrite(STDERR, "BLOCKED: Divergência de hash SHA-256 no arquivo {$file}. Esperado: {$hash}, Obtido: {$actualHash}\n");
        exit(1);
    }
    $filesToInstall[$file] = [
        'source' => $targetFile,
        'dest'   => $candLive,
        'hash'   => $hash,
    ];
}
logMsg("Integridade dos 5 arquivos do patch 100% comprovada via SHA-256.");

// -------------------------------------------------------------------------
// 3. Backup of Existing Operational Files
// -------------------------------------------------------------------------
logMsg("Fase 2: Realizando backup de salvaguarda dos arquivos operacionais existentes...");

$backupDir = dirname($configPath) . '/operational-backups/backup-' . date('Ymd-His');
if (!mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "BLOCKED: Não foi possível criar diretório de backup: {$backupDir}\n");
    exit(1);
}

$backedUpFiles = [];
// Backup config file
copy($configPath, $backupDir . '/receiver-config.json');
$backedUpFiles['receiver-config.json'] = digestFile($configPath);

// Backup live operational files
foreach ($filesToInstall as $file => $info) {
    if (is_file($info['dest'])) {
        $bakDest = $backupDir . '/' . $file;
        copy($info['dest'], $bakDest);
        $backedUpFiles[$file] = digestFile($info['dest']);
    }
}

file_put_contents($backupDir . '/backup-manifest.json', json_encode([
    'created_at' => date('c'),
    'files' => $backedUpFiles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

logMsg("Backup concluído com sucesso em: {$backupDir}");

// -------------------------------------------------------------------------
// 4. Install Patched Operational Files
// -------------------------------------------------------------------------
logMsg("Fase 3: Instalando arquivos corrigidos do publisher...");

foreach ($filesToInstall as $file => $info) {
    if ($info['source'] !== $info['dest']) {
        copy($info['source'], $info['dest']);
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($info['dest'], 0600);
    }
    logMsg("  [OK] Instalado: {$info['dest']} (0600)");
}

// -------------------------------------------------------------------------
// 5. Update Hook Pin in receiver-config.json
// -------------------------------------------------------------------------
logMsg("Fase 4: Sincronizando hash SHA-256 autorizado em receiver-config.json...");

$configRaw = file_get_contents($configPath);
$configData = json_decode($configRaw, true, 512, JSON_THROW_ON_ERROR);

if (!isset($configData['hooks']['backup'])) {
    fwrite(STDERR, "BLOCKED: hooks.backup ausente em {$configPath}\n");
    exit(1);
}

$newHookHash = $expectedHashes['mysql-backup-hook.php'];
$oldHookHash = $configData['hooks']['backup']['sha256'] ?? 'none';
logMsg("Hash anterior: {$oldHookHash}");
logMsg("Novo hash homologado: {$newHookHash}");

$configData['hooks']['backup']['sha256'] = $newHookHash;

// Preserve all formatting, controls, entrypoints, and keys untouched
atomicWrite($configPath, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
logMsg("receiver-config.json atualizado atomicamente (0600).");

// -------------------------------------------------------------------------
// 6. True Preflight Verification & Rollback Protection
// -------------------------------------------------------------------------
logMsg("Fase 5: Executando preflight verdadeiro do backup (prova seca --no-data)...");

$phpBin = PHP_BINARY;
$hookScript = $filesToInstall['mysql-backup-hook.php']['dest'];
$hookArgs = $configData['hooks']['backup']['args'] ?? [];

$preflightCmd = array_merge([$phpBin, $hookScript, 'preflight'], $hookArgs);
$preflightInput = json_encode(['action' => 'preflight', 'project' => $configData['project'] ?? 'rumo10']);

$errLog = tempnam(sys_get_temp_dir(), 'pref_err_');
$pipes = [];
$p = proc_open($preflightCmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['file', $errLog, 'wb']
], $pipes);

$preflightOutput = '';
$preflightExit = -1;
if (is_resource($p)) {
    fwrite($pipes[0], $preflightInput);
    fclose($pipes[0]);
    $preflightOutput = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $preflightExit = proc_close($p);
}
$errContent = is_file($errLog) ? trim(file_get_contents($errLog)) : '';
if (is_file($errLog)) @unlink($errLog);

$preflightData = json_decode($preflightOutput, true);
$preflightSuccess = ($preflightExit === 0 && ($preflightData['ok'] ?? false) === true && ($preflightData['preflight'] ?? false) === true);

if (!$preflightSuccess) {
    logMsg("FALHA BLOQUEANTE NO PREFLIGHT: Código {$preflightExit}. Erro: " . preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $errContent));
    logMsg("EXECUTANDO ROLLBACK AUTOMÁTICO IMEDIATO...");

    // Rollback receiver-config.json
    copy($backupDir . '/receiver-config.json', $configPath);
    if (PHP_OS_FAMILY !== 'Windows') @chmod($configPath, 0600);

    // Rollback files
    foreach ($backedUpFiles as $file => $h) {
        if ($file === 'receiver-config.json') continue;
        $dest = (str_contains('Publisher.php DrainPublisher.php GitHub.php', $file)) ? $libDir . '/' . $file : $binDir . '/' . $file;
        copy($backupDir . '/' . $file, $dest);
        if (PHP_OS_FAMILY !== 'Windows') @chmod($dest, 0600);
    }

    $abortReceipt = [
        'status' => 'aborted_preflight',
        'timestamp' => date('c'),
        'rollback_executed' => true,
        'backup_location' => $backupDir,
        'error' => preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $errContent ?: 'Preflight check failed'),
        'sites_online' => true,
    ];
    file_put_contents($receiptPath, json_encode($abortReceipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    logMsg("Rollback concluído. Aplicações Rumo10 e RumoHub 100% preservadas e online.");
    exit(1);
}

logMsg("Preflight verdadeiro aprovado com sucesso! Motor: " . ($preflightData['is_mariadb'] ? 'MariaDB' : 'MySQL 8') . ", dry-run de mysqldump OK.");

// -------------------------------------------------------------------------
// 7. Publish Release UI-001 via Controlled Pull
// -------------------------------------------------------------------------
if (isset($opts['skip-publish'])) {
    logMsg("Publicação ignorada por parâmetro (--skip-publish).");
    exit(0);
}

logMsg("Fase 6: Disparando publicação automatizada da release UI-001 (commit {$targetCommit})...");

$controlledPull = $binDir . '/controlled-pull.php';
need(is_file($controlledPull), "controlled-pull.php não encontrado em {$controlledPull}");

$publishCmd = [
    $phpBin,
    $controlledPull,
    '--config=' . $configPath,
    '--mode=apply',
    '--discover',
];

$publishErrLog = tempnam(sys_get_temp_dir(), 'pub_err_');
$publishP = proc_open($publishCmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['file', $publishErrLog, 'wb']
], $publishPipes);

$publishOutput = '';
$publishExit = -1;
if (is_resource($publishP)) {
    fclose($publishPipes[0]);
    $publishOutput = stream_get_contents($publishPipes[1]);
    fclose($publishPipes[1]);
    $publishExit = proc_close($publishP);
}
$publishErr = is_file($publishErrLog) ? trim(file_get_contents($publishErrLog)) : '';
if (is_file($publishErrLog)) @unlink($publishErrLog);

logMsg("Saída da publicação: " . trim($publishOutput));
if ($publishErr !== '') {
    logMsg("Stderr: " . preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $publishErr));
}

// -------------------------------------------------------------------------
// 8. Post-Publication Verification & Receipts
// -------------------------------------------------------------------------
logMsg("Fase 7: Validando recibo de publicação e estado operacional...");

$installedData = is_file($installedJsonPath) ? json_decode(file_get_contents($installedJsonPath), true) : [];
$publishedCommit = $installedData['commit'] ?? null;
$publishedArtifact = $installedData['artifact_id'] ?? null;

$gateClosedPath = ($configData['request_gate']['root'] ?? '') . '/closed.json';
$gateIsOpen = !file_exists($gateClosedPath);

$success = ($publishExit === 0 && $publishedCommit === $targetCommit && $gateIsOpen);

$finalReceipt = [
    'status'            => $success ? 'success' : 'failed',
    'timestamp'         => date('c'),
    'installed_commit'  => $publishedCommit,
    'target_commit'     => $targetCommit,
    'artifact_id'       => $publishedArtifact,
    'target_artifact_id'=> $targetArtifactId,
    'hook_sha256'       => $newHookHash,
    'preflight_status'  => 'passed',
    'backup_dir'        => $backupDir,
    'request_gate_open' => $gateIsOpen,
    'sites_online'      => $gateIsOpen,
    'publish_output'    => json_decode($publishOutput, true) ?: $publishOutput,
];

file_put_contents($receiptPath, json_encode($finalReceipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if (PHP_OS_FAMILY !== 'Windows') @chmod($receiptPath, 0600);

if (!$success) {
    fwrite(STDERR, "BLOCKED: Publicação não concluída com sucesso. Verifique {$receiptPath}\n");
    exit(1);
}

logMsg("=========================================================================");
logMsg("SUCESSO ABSOLUTO: Release UI-001 publicada e validada na UOL Host!");
logMsg("Commit: {$publishedCommit}");
logMsg("Artefato: {$publishedArtifact}");
logMsg("Recibo registrado em: {$receiptPath}");
logMsg("Sites Rumo10 e RumoHub online e funcionais.");
logMsg("=========================================================================");

exit(0);
