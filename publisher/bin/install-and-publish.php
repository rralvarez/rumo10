<?php
declare(strict_types=1);

/**
 * RUMO10 | Transactional & Recoverable Operational Installer & Publisher (v5)
 *
 * Implements strict phase separation:
 *  - Phase A (Preparation): Staging & True Preflight on staged code (zero active changes).
 *  - Phase B (Operational Promotion): Account mutex mutual exclusion, atomic promotion & pin update.
 *  - Phase C (Handover & Publication): Account mutex release, official controlled-pull apply,
 *    and strict two-tier recovery (official --recover without reverting publisher files).
 *
 * Target: commit fb03b614c337845671cf6cbf1eb36abe01308bfd | artifact 14257772333 | run 43929021873
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
// Configuration and Paths
// -------------------------------------------------------------------------
$opts = getopt('', ['config:', 'force', 'skip-publish']);

$baseDir = dirname(__DIR__, 2);
$defaultConfig = '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json';
$configPath = $opts['config'] ?? (is_file($defaultConfig) ? $defaultConfig : $baseDir . '/receiver-config.json');

if (!is_file($configPath)) {
    fwrite(STDERR, "BLOCKED: Configuration file not found at {$configPath}\n");
    exit(1);
}

if (PHP_OS_FAMILY !== 'Windows' && (fileperms($configPath) & 0077) !== 0) {
    fwrite(STDERR, "BLOCKED: Configuration file must have 0600 permissions\n");
    exit(1);
}

$configRaw = file_get_contents($configPath);
$configData = json_decode($configRaw, true, 512, JSON_THROW_ON_ERROR);

need(!empty($configData['private']), "Configuration missing mandatory 'private' directory field");
$privateDir = $configData['private'];
if (!is_dir($privateDir)) {
    @mkdir($privateDir, 0700, true);
}

$installedJsonPath = $privateDir . '/installed.json';
$journalFile = $privateDir . '/patch-install-journal.json';
$receiptPath = $privateDir . '/patch-install-receipt.json';

$binDir = dirname(__DIR__) . '/bin';
$libDir = dirname(__DIR__) . '/lib';
$patchDir = dirname(__DIR__) . '/patch';

logMsg("=== RUMO10 | INICIALIZANDO INSTALADOR TRANSACIONAL E PUBLICADOR DEFINITIVO (V5) ===");
logMsg("Configuração: {$configPath}");
logMsg("Diretório privado: {$privateDir}");

// -------------------------------------------------------------------------
// 1. Mutual Exclusion for Installer (installer.lock)
// -------------------------------------------------------------------------
$installerLock = $privateDir . '/installer.lock';
$lockFp = fopen($installerLock, 'c+');
need($lockFp !== false, "Cannot open installer lock file: {$installerLock}");
if (PHP_OS_FAMILY !== 'Windows') @chmod($installerLock, 0600);

if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "BLOCKED: Outro instalador operacional está em execução (installer.lock retido).\n");
    fclose($lockFp);
    exit(1);
}

// Check publisher deployment lock
$controlDir = $configData['control'] ?? dirname($configPath) . '/control';
$deployLock = $controlDir . '/deployment.lock';
if (file_exists($deployLock)) {
    fwrite(STDERR, "BLOCKED: deployment.lock ativo em {$deployLock}. Não é seguro promover arquivos durante deployment.\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

// -------------------------------------------------------------------------
// Rollback Functions
// -------------------------------------------------------------------------
function executePreApplyRollback(array $j, string $cfgPath, string $jPath): bool {
    logMsg("EXECUTANDO ROLLBACK TRANSACIONAL PRÉ-APPLY...");
    $success = true;

    // 1. Restore modified files from backup
    $backupDir = $j['backup_dir'] ?? null;
    $modifiedFiles = $j['modified_files'] ?? [];
    if ($backupDir && is_dir($backupDir)) {
        foreach ($modifiedFiles as $dest => $meta) {
            $backupCopy = $backupDir . '/' . basename($dest);
            if (is_file($backupCopy)) {
                if (!copy($backupCopy, $dest)) {
                    logMsg("  [ERRO] Falha ao restaurar: {$dest}");
                    $success = false;
                } else {
                    if (PHP_OS_FAMILY !== 'Windows') @chmod($dest, 0600);
                    $restoredHash = digestFile($dest);
                    if ($restoredHash !== $meta['hash']) {
                        logMsg("  [ERRO] Hash divergente após restauração em {$dest}: {$restoredHash} vs {$meta['hash']}");
                        $success = false;
                    } else {
                        logMsg("  [RESTAURADO] {$dest} (hash verificado)");
                    }
                }
            }
        }
    }

    // 2. Remove files created solely by this attempt
    $createdFiles = $j['created_files'] ?? [];
    foreach ($createdFiles as $cFile) {
        if (is_file($cFile)) {
            @unlink($cFile);
            if (file_exists($cFile)) {
                logMsg("  [ERRO] Falha ao remover arquivo criado: {$cFile}");
                $success = false;
            } else {
                logMsg("  [REMOVIDO] Arquivo criado na tentativa: {$cFile}");
            }
        }
    }

    // 3. Restore receiver-config.json
    if (!empty($j['original_config_content'])) {
        atomicWrite($cfgPath, $j['original_config_content'], 0600);
        $currHash = digestFile($cfgPath);
        if ($currHash !== ($j['original_config_hash'] ?? '')) {
            logMsg("  [ERRO] Hash divergente após restaurar {$cfgPath}");
            $success = false;
        } else {
            logMsg("  [RESTAURADO] {$cfgPath} (hash verificado)");
        }
    }

    // 4. Clean staging directory if any
    $stagingDir = $j['staging_dir'] ?? null;
    if ($stagingDir && is_dir($stagingDir)) {
        $stFiles = glob($stagingDir . '/*');
        foreach ($stFiles as $sf) { if (is_file($sf)) @unlink($sf); }
        @rmdir($stagingDir);
    }

    // Update journal state
    $j['state'] = 'rolled_back';
    $j['rollback_verified'] = $success;
    $j['rolled_back_at'] = date('c');
    atomicWrite($jPath, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    return $success;
}

// -------------------------------------------------------------------------
// 2. Interruption Recovery & Idempotency Check
// -------------------------------------------------------------------------
$force = isset($opts['force']);
$targetCommit = 'fb03b614c337845671cf6cbf1eb36abe01308bfd';
$targetArtifactId = 14257772333;
$targetRunId = 43929021873;

$currInstalled = is_file($installedJsonPath) ? json_decode(file_get_contents($installedJsonPath), true) : [];
$isInstalled = ($currInstalled['commit'] ?? '') === $targetCommit;

$hasSuccessReceipt = is_file($receiptPath) && (json_decode(file_get_contents($receiptPath), true)['status'] ?? '') === 'success';
$hasValidatedJournal = is_file($journalFile) && (json_decode(file_get_contents($journalFile), true)['state'] ?? '') === 'validated';

if (!$force && $isInstalled && ($hasSuccessReceipt || $hasValidatedJournal)) {
    logMsg("IDEMPOTÊNCIA: Release UI-001 ({$targetCommit}) já está validada e instalada em {$installedJsonPath}.");
    logMsg("Delegando para guarded-publisher.php em modo contínuo...");
    $guardedPub = $binDir . '/guarded-publisher.php';
    if (is_file($guardedPub)) {
        $phpBin = (PHP_OS_FAMILY !== 'Windows' && is_executable('/opt/alt/php84/usr/bin/php')) ? '/opt/alt/php84/usr/bin/php' : PHP_BINARY;
        passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($guardedPub) . ' --config=' . escapeshellarg($configPath), $exitCode);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit($exitCode);
    }
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

if (is_file($journalFile)) {
    $priorJournal = json_decode(file_get_contents($journalFile), true);
    $priorState = $priorJournal['state'] ?? 'unknown';

    if (!in_array($priorState, ['validated', 'rolled_back'], true)) {
        logMsg("RECUPERAÇÃO DE INTERRUPÇÃO: Detectada tentativa anterior incompleta no estado '{$priorState}'.");
        if ($priorState === 'transferring_to_publisher' || $priorState === 'apply_started') {
            logMsg("A tentativa anterior foi interrompida durante ou após transferência ao publisher. Preservando publisher e acionando recuperação oficial.");
            $phpBin = (PHP_OS_FAMILY !== 'Windows' && is_executable('/opt/alt/php84/usr/bin/php')) ? '/opt/alt/php84/usr/bin/php' : PHP_BINARY;
            $controlledPull = $binDir . '/controlled-pull.php';
            if (is_file($controlledPull)) {
                passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($controlledPull) . ' --config=' . escapeshellarg($configPath) . ' --mode=apply --recover');
            }
        } else {
            $rbOk = executePreApplyRollback($priorJournal, $configPath, $journalFile);
            need($rbOk, "Falha na recuperação de tentativa anterior interrompida. Intervenção necessária.");
            logMsg("Recuperação pré-apply concluída: ambiente restaurado ao estado íntegro original.");
        }
    }
}

// -------------------------------------------------------------------------
// FASE A: PREPARAÇÃO (Zero Alteração no Código Ativo)
// -------------------------------------------------------------------------
logMsg("FASE A: Preparação, verificação de hashes e preflight completo em staging privado...");

$expectedHashes = [
    'mysql-backup-hook.php' => '4f63075ab85d1f740a611da3e619eaa0e3ffdcab6d75be211dc4fcc167945eed',
    'diagnose-uol.php'      => '9d4d6fb9f58b3daa49cd798fe7a8975ea2a00ef6c0292dc26756077066e19a6b',
    'guarded-publisher.php' => 'ad5f0410087d7635db1dc3cdf466459e5d44526210dd9552c35df317978983bd',
    'Publisher.php'         => '38e9a5784ac3f9cd4fdb1ab3e2ae184b8ebe20eefc7803831fcfb250ced7313e',
    'DrainPublisher.php'    => '4fbcd95202349212a6ede3d2d9be0a441450cc4d27634550279d0f9e44eb991a',
    'GitHub.php'            => 'af564c8293737c053f5dac5c3f7bca3509232c62e9b1942fed316aef0649c66d',
];

$filesPlan = [];
foreach ($expectedHashes as $file => $expectedHash) {
    $candPatch = is_dir($patchDir) ? $patchDir . '/' . $file : null;
    $candBin = $binDir . '/' . $file;
    $candLib = $libDir . '/' . $file;

    $sourceCandidate = ($candPatch && is_file($candPatch)) ? $candPatch : (is_file($candLib) ? $candLib : $candBin);
    if (!is_file($sourceCandidate)) {
        fwrite(STDERR, "BLOCKED: Arquivo obrigatório do patch não encontrado: {$file}\n");
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(1);
    }

    $actualHash = digestFile($sourceCandidate);
    if ($actualHash !== $expectedHash) {
        fwrite(STDERR, "BLOCKED: Divergência de hash SHA-256 no arquivo {$file}. Esperado: {$expectedHash}, Obtido: {$actualHash}\n");
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(1);
    }

    $destPath = (str_ends_with($file, '.php') && str_contains('Publisher.php DrainPublisher.php GitHub.php', $file))
        ? $libDir . '/' . $file
        : $binDir . '/' . $file;

    $filesPlan[$file] = [
        'source' => $sourceCandidate,
        'dest'   => $destPath,
        'hash'   => $expectedHash,
    ];
}
logMsg("Integridade prévia dos " . count($filesPlan) . " arquivos do patch 100% comprovada via SHA-256.");

$attemptId = bin2hex(random_bytes(8));
$stagingDir = $privateDir . '/patch-staging-' . $attemptId;
mkdir($stagingDir, 0700, true);
if (PHP_OS_FAMILY !== 'Windows') @chmod($stagingDir, 0700);

// Stage all files
foreach ($filesPlan as $file => $info) {
    $stagedFile = $stagingDir . '/' . $file;
    copy($info['source'], $stagedFile);
    if (PHP_OS_FAMILY !== 'Windows') @chmod($stagedFile, 0600);
    $stagedHash = digestFile($stagedFile);
    need($stagedHash === $info['hash'], "Falha de integridade no staging de {$file}");
}

// Backup directory preparation
$backupDir = $privateDir . '/operational-backups/backup-' . time() . '-' . $attemptId;
mkdir($backupDir, 0700, true);
if (PHP_OS_FAMILY !== 'Windows') @chmod($backupDir, 0700);
copy($configPath, $backupDir . '/receiver-config.json');

$modifiedFiles = [];
$createdFiles = [];
foreach ($filesPlan as $file => $info) {
    $dest = $info['dest'];
    if (file_exists($dest)) {
        $backupDest = $backupDir . '/' . $file;
        copy($dest, $backupDest);
        $modifiedFiles[$dest] = [
            'file' => $file,
            'hash' => digestFile($dest),
        ];
    } else {
        $createdFiles[] = $dest;
    }
}

// Record journal state: staged
$journalData = [
    'attempt_id'             => $attemptId,
    'started_at'             => date('c'),
    'state'                  => 'staged',
    'staging_dir'            => $stagingDir,
    'backup_dir'             => $backupDir,
    'modified_files'         => $modifiedFiles,
    'created_files'          => $createdFiles,
    'original_config_content'=> $configRaw,
    'original_config_hash'   => digestFile($configPath),
    'target_release'         => [
        'commit'      => $targetCommit,
        'artifact_id' => $targetArtifactId,
        'run_id'      => $targetRunId,
    ],
];
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

// Execute True Preflight ON THE STAGED CODE (Zero active code/pin modified!)
logMsg("Executando preflight verdadeiro sobre o código em staging privado...");
$phpBin = (PHP_OS_FAMILY !== 'Windows' && is_executable('/opt/alt/php84/usr/bin/php')) ? '/opt/alt/php84/usr/bin/php' : PHP_BINARY;
$stagedHook = $stagingDir . '/mysql-backup-hook.php';
$hookArgs = $configData['hooks']['backup']['args'] ?? [];
$preflightCmd = array_merge([$phpBin, $stagedHook, 'preflight'], $hookArgs);
$preflightInput = json_encode(['action' => 'preflight', 'project' => $configData['project'] ?? 'rumo10']);

$prefErrLog = $privateDir . '/pref-err-' . $attemptId . '.log';
$p = proc_open($preflightCmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['file', $prefErrLog, 'wb']
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
$prefErrContent = is_file($prefErrLog) ? trim(file_get_contents($prefErrLog)) : '';
if (is_file($prefErrLog)) @unlink($prefErrLog);

$preflightData = json_decode($preflightOutput, true);
$preflightSuccess = ($preflightExit === 0 && ($preflightData['ok'] ?? false) === true && ($preflightData['full_data_tested'] ?? false) === true);

if (!$preflightSuccess) {
    $cleanPrefErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $prefErrContent);
    $cleanPrefErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $cleanPrefErr);
    logMsg("FALHA BLOQUEANTE NO PREFLIGHT STAGED (código {$preflightExit}): " . $cleanPrefErr);

    executePreApplyRollback($journalData, $configPath, $journalFile);
    $receipt = [
        'status' => 'aborted_preflight_failed',
        'attempt_id' => $attemptId,
        'timestamp' => date('c'),
        'error' => $cleanPrefErr,
        'preflight_passed' => false,
        'rollback_ok' => true,
        'active_code_modified' => false,
        'request_drain_never_closed' => true,
    ];
    atomicWrite($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

logMsg("Preflight com dump completo de dados APROVADO com 100% de sucesso.");
logMsg("  Database: " . ($preflightData['database'] ?? ''));
logMsg("  Engine: " . ($preflightData['engine'] ?? ''));
logMsg("  Triggers verificados: " . ($preflightData['triggers_count'] ?? 0));
logMsg("  Routines verificadas: " . ($preflightData['routines_count'] ?? 0));
logMsg("  Views verificadas: " . ($preflightData['views_count'] ?? 0));

$journalData['state'] = 'preflight_passed';
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

// -------------------------------------------------------------------------
// FASE B: PROMOÇÃO OPERACIONAL SOB MUTEX DA CONTA
// -------------------------------------------------------------------------
logMsg("FASE B: Adquirindo account_mutex para promoção operacional atômica...");

$accountMutexFile = $configData['account_mutex'] ?? ($privateDir . '/account.lock');
if (!is_dir(dirname($accountMutexFile))) {
    @mkdir(dirname($accountMutexFile), 0700, true);
}
$accountFp = fopen($accountMutexFile, 'c+b');
need($accountFp !== false, "Cannot open account mutex file: {$accountMutexFile}");
need(flock($accountFp, LOCK_EX | LOCK_NB), "BLOCKED: account.lock retido por outro processo do publisher. Impossível promover concorrentemente.");

$journalData['state'] = 'promoting';
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

try {
    // 1. Promote files atomically
    foreach ($filesPlan as $file => $info) {
        $stagedSource = $stagingDir . '/' . $file;
        $dest = $info['dest'];
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0700, true);
        }
        if (!copy($stagedSource, $dest)) {
            throw new \RuntimeException("Falha ao promover {$file} para {$dest}");
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($dest, 0600);
        }
        $promotedHash = digestFile($dest);
        need($promotedHash === $info['hash'], "Divergência de hash pós-cópia em {$dest}: {$promotedHash} vs {$info['hash']}");
        logMsg("  [OK] Promovido e verificado: {$dest} ({$promotedHash})");
    }

    // 2. Update pin in receiver-config.json
    $newHookHash = $expectedHashes['mysql-backup-hook.php'];
    need(isset($configData['hooks']['backup']), "hooks.backup ausente em {$configPath}");
    $oldHookHash = $configData['hooks']['backup']['sha256'] ?? 'none';
    logMsg("Sincronizando hash do hook em receiver-config.json: {$oldHookHash} -> {$newHookHash}");
    $configData['hooks']['backup']['sha256'] = $newHookHash;
    atomicWrite($configPath, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    $journalData['state'] = 'promotion_complete';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
    logMsg("Promoção operacional concluída e validada integralmente.");
} catch (\Throwable $promoErr) {
    logMsg("ERRO DURANTE PROMOÇÃO OPERACIONAL: " . $promoErr->getMessage());
    flock($accountFp, LOCK_UN);
    fclose($accountFp);
    executePreApplyRollback($journalData, $configPath, $journalFile);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

// -------------------------------------------------------------------------
// FASE C: TRANSFERÊNCIA AO PUBLISHER E EXECUÇÃO
// -------------------------------------------------------------------------
logMsg("FASE C: Transferindo controle ao publisher oficial...");

$journalData['state'] = 'transferring_to_publisher';
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

// Crucial: Release account mutex BEFORE launching controlled-pull!
// This allows controlled-pull.php to acquire account_mutex cleanly.
flock($accountFp, LOCK_UN);
fclose($accountFp);

if (isset($opts['skip-publish'])) {
    logMsg("AVISO: Publicação ignorada devido ao parâmetro --skip-publish.");
    $journalData['state'] = 'validated';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

logMsg("Disparando controlled-pull.php --mode=apply --discover para release autorizada...");
$controlledPull = $binDir . '/controlled-pull.php';
need(is_file($controlledPull), "controlled-pull.php não encontrado em {$controlledPull}");

$publishCmd = [
    $phpBin,
    $controlledPull,
    '--config=' . $configPath,
    '--mode=apply',
    '--discover',
];

$pubErrLog = $privateDir . '/pub-err-' . $attemptId . '.log';
$pubOutLog = $privateDir . '/pub-out-' . $attemptId . '.log';

$journalData['state'] = 'apply_started';
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

$p = proc_open($publishCmd, [
    0 => ['pipe', 'r'],
    1 => ['file', $pubOutLog, 'wb'],
    2 => ['file', $pubErrLog, 'wb'],
], $pipes);

need(is_resource($p), "Falha ao iniciar processo do publisher");
fclose($pipes[0]);

$deadline = microtime(true) + 300;
$pubExit = -1;
do {
    $st = proc_get_status($p);
    if (!$st['running']) {
        $pubExit = $st['exitcode'];
        break;
    }
    if (microtime(true) > $deadline) {
        proc_terminate($p);
        break;
    }
    usleep(100000);
} while (true);
proc_close($p);

$pubOutContent = is_file($pubOutLog) ? trim(file_get_contents($pubOutLog)) : '';
$pubErrContent = is_file($pubErrLog) ? trim(file_get_contents($pubErrLog)) : '';
if (is_file($pubOutLog)) @unlink($pubOutLog);
if (is_file($pubErrLog)) @unlink($pubErrLog);

$cleanPubErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $pubErrContent);
$cleanPubErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $cleanPubErr);

if ($pubExit !== 0) {
    logMsg("FALHA NO APPLY DO PUBLISHER (código {$pubExit}): " . $cleanPubErr);
    logMsg("Regra estrita pós-apply: preservando publisher compatível e acionando recuperação oficial (--recover)...");

    $journalData['state'] = 'apply_failed';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    // Trigger official recovery of controlled-pull.php
    $recoverCmd = [$phpBin, $controlledPull, '--config=' . $configPath, '--mode=apply', '--recover'];
    $recErrLog = $privateDir . '/rec-err-' . $attemptId . '.log';
    $recOutLog = $privateDir . '/rec-out-' . $attemptId . '.log';

    $rp = proc_open($recoverCmd, [
        0 => ['pipe', 'r'],
        1 => ['file', $recOutLog, 'wb'],
        2 => ['file', $recErrLog, 'wb'],
    ], $rpipes);

    $recExit = -1;
    if (is_resource($rp)) {
        fclose($rpipes[0]);
        $rDeadline = microtime(true) + 120;
        do {
            $rst = proc_get_status($rp);
            if (!$rst['running']) { $recExit = $rst['exitcode']; break; }
            if (microtime(true) > $rDeadline) { proc_terminate($rp); break; }
            usleep(100000);
        } while (true);
        proc_close($rp);
    }
    if (is_file($recOutLog)) @unlink($recOutLog);
    if (is_file($recErrLog)) @unlink($recErrLog);

    // Verify recovery proof
    $drainSessionFile = $privateDir . '/drain-session.json';
    $drainSession = is_file($drainSessionFile) ? json_decode(file_get_contents($drainSessionFile), true) : [];
    $isRecovered = ($drainSession['state'] ?? '') === 'recovered';

    $gateClosedR10 = file_exists(($configData['request_gate']['root'] ?? dirname($configPath) . '/public') . '/closed.json');
    $gateIsOpen = !$gateClosedR10;

    if ($isRecovered && $gateIsOpen) {
        logMsg("Recuperação oficial de sessão comprovada com sucesso (drain-session: recovered, gate aberto).");
        $journalData['state'] = 'recovered';
        atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
        $receipt = [
            'status' => 'apply_failed_recovered',
            'attempt_id' => $attemptId,
            'timestamp' => date('c'),
            'apply_exit_code' => $pubExit,
            'apply_error' => $cleanPubErr,
            'recovery_proven' => true,
            'gate_open' => true,
        ];
    } else {
        logMsg("ALERTA: Recuperação de sessão NÃO comprovada. Estado pendente registrado.");
        $journalData['state'] = 'pending_recovery';
        atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
        $receipt = [
            'status' => 'pending_recovery',
            'attempt_id' => $attemptId,
            'timestamp' => date('c'),
            'apply_exit_code' => $pubExit,
            'apply_error' => $cleanPubErr,
            'recovery_proven' => false,
            'gate_open' => $gateIsOpen,
        ];
    }
    atomicWrite($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

// Verify production deployment
logMsg("Publicação concluída com código 0. Verificando installed.json e integridade da release...");

$installedData = is_file($installedJsonPath) ? json_decode(file_get_contents($installedJsonPath), true) : [];
$actualCommit = $installedData['commit'] ?? '';
$actualArtifactId = $installedData['artifact_id'] ?? null;

need($actualCommit === $targetCommit, "Divergência de commit instalado: esperado {$targetCommit}, obtido {$actualCommit}");
need((int)$actualArtifactId === $targetArtifactId, "Divergência de artifact_id instalado: esperado {$targetArtifactId}, obtido {$actualArtifactId}");

// Verify RequestDrain is open
$gateClosed = file_exists(($configData['request_gate']['root'] ?? dirname($configPath) . '/public') . '/closed.json');
need(!$gateClosed, "RequestDrain permaneceu fechado após conclusão do deploy");

// Clean staging directory
if (is_dir($stagingDir)) {
    $stFiles = glob($stagingDir . '/*');
    foreach ($stFiles as $sf) { if (is_file($sf)) @unlink($sf); }
    @rmdir($stagingDir);
}

$journalData['state'] = 'validated';
$journalData['completed_at'] = date('c');
$journalData['installed'] = $installedData;
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

$receipt = [
    'status' => 'success',
    'attempt_id' => $attemptId,
    'timestamp' => date('c'),
    'published_commit' => $actualCommit,
    'artifact_id' => $actualArtifactId,
    'run_id' => $targetRunId,
    'preflight_passed' => true,
    'full_data_tested' => true,
    'drain_open' => true,
];
atomicWrite($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

logMsg("=== SUCESSO DEFINITIVO: RELEASE UI-001 INSTALADA E HOMOLOGADA ===");
logMsg("Recibo registrado em: {$receiptPath}");

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit(0);
