<?php
declare(strict_types=1);

/**
 * RUMO10 | Hardened Transactional & Isolated Bootstrap Installer & Publisher (V6 Architecture)
 *
 * Implements strict phase separation, complete isolated staging hierarchy,
 * mutex-protected operational backup, atomic file promotion per file via rename(),
 * and strict 2-tier recovery state machine (official controlled-pull --recover post-apply).
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

function atomicPromote(string $source, string $dest, int $mode = 0600): void {
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    $tmpDest = $dest . '.tmp.' . bin2hex(random_bytes(6));
    if (!copy($source, $tmpDest)) {
        throw new \RuntimeException("Failed to copy temporary file: {$tmpDest}");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($tmpDest, $mode);
    }
    if (digestFile($tmpDest) !== digestFile($source)) {
        @unlink($tmpDest);
        throw new \RuntimeException("Hash verification failed on staged copy: {$tmpDest}");
    }
    if (!rename($tmpDest, $dest)) {
        @unlink($tmpDest);
        throw new \RuntimeException("Failed to atomically rename {$tmpDest} to {$dest}");
    }
}

// -------------------------------------------------------------------------
// Configuration and Paths
// -------------------------------------------------------------------------
$opts = getopt('', ['config:', 'force', 'skip-publish']);

$defaultConfig = '/home/rumoacame144a99e/rumo10-production-v062-ee8f175/receiver-config.json';
$possibleConfigs = [
    $opts['config'] ?? null,
    $defaultConfig,
    dirname(__DIR__, 2) . '/receiver-config.json',
    dirname(__DIR__, 3) . '/receiver-config.json',
];

$configPath = null;
foreach ($possibleConfigs as $cand) {
    if ($cand && is_file($cand)) {
        $configPath = $cand;
        break;
    }
}

if (!$configPath || !is_file($configPath)) {
    fwrite(STDERR, "BLOCKED: receiver-config.json não encontrado. Especifique --config=/caminho/para/receiver-config.json\n");
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

// Active production directories
$prodRoot = dirname($configPath);
$activeBinDir = $prodRoot . '/publisher/bin';
$activeLibDir = $prodRoot . '/publisher/lib';

// Source payload directories: can run from bootstrap package or repository publisher
$payloadDirs = [
    __DIR__ . '/../payload',
    dirname(__DIR__) . '/patch',
    dirname(__DIR__),
];
$resolvedPayload = null;
foreach ($payloadDirs as $pd) {
    if (is_dir($pd)) {
        $resolvedPayload = $pd;
        break;
    }
}

logMsg("=== RUMO10 | INICIALIZANDO BOOTSTRAP INSTALLER ISOLADO (V6) ===");
logMsg("Configuração ativa: {$configPath}");
logMsg("Diretório privado: {$privateDir}");
logMsg("Diretório ativo bin: {$activeBinDir}");
logMsg("Diretório ativo lib: {$activeLibDir}");

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
$controlDir = $configData['control'] ?? $prodRoot . '/control';
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

    // 1. Restore modified files from backup atomically
    $backupDir = $j['backup_dir'] ?? null;
    $modifiedFiles = $j['modified_files'] ?? [];
    if ($backupDir && is_dir($backupDir)) {
        foreach ($modifiedFiles as $dest => $meta) {
            $backupCopy = $backupDir . '/' . basename($dest);
            if (is_file($backupCopy)) {
                try {
                    atomicPromote($backupCopy, $dest, 0600);
                    $restoredHash = digestFile($dest);
                    if ($restoredHash !== $meta['hash']) {
                        logMsg("  [ERRO] Hash divergente após restauração em {$dest}: {$restoredHash} vs {$meta['hash']}");
                        $success = false;
                    } else {
                        logMsg("  [RESTAURADO] {$dest} (hash verificado)");
                    }
                } catch (\Throwable $err) {
                    logMsg("  [ERRO] Falha ao restaurar {$dest}: " . $err->getMessage());
                    $success = false;
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

$phpBin = (PHP_OS_FAMILY !== 'Windows' && is_executable('/opt/alt/php84/usr/bin/php')) ? '/opt/alt/php84/usr/bin/php' : PHP_BINARY;
$controlledPull = $activeBinDir . '/controlled-pull.php';

if (!$force && $isInstalled && ($hasSuccessReceipt || $hasValidatedJournal)) {
    logMsg("IDEMPOTÊNCIA: Release UI-001 ({$targetCommit}) já está validada e instalada em {$installedJsonPath}.");
    if (is_file($controlledPull)) {
        logMsg("Delegando para controlled-pull.php --mode=apply --discover...");
        passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($controlledPull) . ' --config=' . escapeshellarg($configPath) . ' --mode=apply --discover', $exitCode);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit($exitCode);
    }
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

// Strict 2-tier recovery state machine (Defect 4)
if (is_file($journalFile)) {
    $priorJournal = json_decode(file_get_contents($journalFile), true);
    $priorState = $priorJournal['state'] ?? 'unknown';

    if (!in_array($priorState, ['validated', 'rolled_back'], true)) {
        logMsg("RECUPERAÇÃO DE INTERRUPÇÃO: Detectada tentativa anterior no estado '{$priorState}'.");
        
        $postApplyStates = ['transferring_to_publisher', 'apply_started', 'apply_failed', 'pending_recovery'];
        if (in_array($priorState, $postApplyStates, true)) {
            // Post-apply state: NEVER revert publisher files! Acionar exclusivamente controlled-pull --recover.
            logMsg("Estado pós-apply identificado ('{$priorState}'). Preservando publisher compatível e acionando recuperação oficial (--recover)...");
            if (is_file($controlledPull)) {
                passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($controlledPull) . ' --config=' . escapeshellarg($configPath) . ' --mode=apply --recover', $recExit);
                logMsg("Resultado da recuperação oficial: código {$recExit}");
            }
        } else {
            // Pre-apply state: rollback operational patch
            logMsg("Estado pré-apply identificado ('{$priorState}'). Executando rollback do patch operacional...");
            $rbOk = executePreApplyRollback($priorJournal, $configPath, $journalFile);
            need($rbOk, "Falha na recuperação de tentativa anterior interrompida. Intervenção necessária.");
            logMsg("Recuperação pré-apply concluída com sucesso.");
        }
    }
}

// -------------------------------------------------------------------------
// FASE A: PREPARAÇÃO COM STAGING COMPLETO E ISOLADO (Defeito 1 & 2)
// -------------------------------------------------------------------------
logMsg("FASE A: Preparação com staging hierárquico isolado e preflight verdadeiro...");

$expectedHashes = [
    'publisher/bin/mysql-backup-hook.php' => '4f63075ab85d1f740a611da3e619eaa0e3ffdcab6d75be211dc4fcc167945eed',
    'publisher/bin/diagnose-uol.php'      => '9d4d6fb9f58b3daa49cd798fe7a8975ea2a00ef6c0292dc26756077066e19a6b',
    'publisher/lib/Publisher.php'         => '9bd5c7555970b37ade0ebe74d53ea913a028c921812e79272acc375927185f5c',
    'publisher/lib/DrainPublisher.php'    => 'a67da133454fdbb21347e7a75ce1726fe303c65f9fbf0e1a6e21a9c6eda924b8',
    'publisher/lib/MainAutomation.php'    => '0824c9c9a55e302fdab9060f0322df2646adf88c564903e91d0f27696ab9ff6d',
    'publisher/lib/GitHub.php'            => 'af564c8293737c053f5dac5c3f7bca3509232c62e9b1942fed316aef0649c66d',
];

$attemptId = bin2hex(random_bytes(8));
$stagingDir = $privateDir . '/patch-staging-' . $attemptId;

try {
    // Build complete functional staging hierarchy (publisher/bin and publisher/lib)
    $stagedBinDir = $stagingDir . '/publisher/bin';
    $stagedLibDir = $stagingDir . '/publisher/lib';
    mkdir($stagedBinDir, 0700, true);
    mkdir($stagedLibDir, 0700, true);

    // Also copy Core.php and other foundational libs to staging lib
    $coreLib = is_file($activeLibDir . '/Core.php') ? $activeLibDir . '/Core.php' : (dirname(__DIR__) . '/lib/Core.php');
    if (is_file($coreLib)) {
        copy($coreLib, $stagedLibDir . '/Core.php');
    }
    $reqDrainLib = is_file($activeLibDir . '/RequestDrain.php') ? $activeLibDir . '/RequestDrain.php' : (dirname(__DIR__) . '/lib/RequestDrain.php');
    if (is_file($reqDrainLib)) {
        copy($reqDrainLib, $stagedLibDir . '/RequestDrain.php');
    }

    $filesPlan = [];
    foreach ($expectedHashes as $relPath => $expectedHash) {
        $candStaged = $stagingDir . '/' . $relPath;
        $candSrc = null;
        $searchPaths = [
            dirname(__DIR__) . '/patch/' . basename($relPath),
            dirname(__DIR__, 2) . '/publisher/patch/' . basename($relPath),
            dirname(__DIR__) . '/payload/' . $relPath,
            __DIR__ . '/payload/' . $relPath,
            dirname(__DIR__, 2) . '/' . $relPath,
            dirname(__DIR__) . '/' . basename($relPath),
            __DIR__ . '/' . basename($relPath),
        ];
        foreach ($searchPaths as $sp) {
            if (is_file($sp)) {
                $candSrc = $sp;
                break;
            }
        }
        need($candSrc !== null && is_file($candSrc), "Arquivo obrigatório do payload não encontrado: {$relPath}");

        $actualHash = digestFile($candSrc);
        need($actualHash === $expectedHash, "Divergência de hash SHA-256 no arquivo " . basename($relPath) . ": {$actualHash} vs esperado {$expectedHash}");
        atomicPromote($candSrc, $candStaged, 0600);
        $stagedHash = digestFile($candStaged);

        $destPath = $prodRoot . '/' . $relPath;
        $filesPlan[$relPath] = [
            'staged' => $candStaged,
            'dest'   => $destPath,
            'hash'   => $stagedHash,
        ];
    }
    logMsg("Staging isolado construído com sucesso (" . count($filesPlan) . " componentes verificados).");
} catch (\Throwable $phaseAErr) {
    logMsg("FALHA BLOQUEANTE NA FASE A (STAGING): " . $phaseAErr->getMessage());
    if (is_dir($stagingDir)) {
        $stFiles = glob($stagingDir . '/*');
        foreach ($stFiles as $sf) { if (is_file($sf)) @unlink($sf); }
        @rmdir($stagingDir);
    }
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

// True preflight on the STAGED code with isolated libraries (Defect 1)
logMsg("Executando preflight verdadeiro com dados reais sobre o código em staging isolado...");
$stagedHook = $stagedBinDir . '/mysql-backup-hook.php';
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

    // Clean staging
    $stFiles = glob($stagingDir . '/*');
    foreach ($stFiles as $sf) { if (is_file($sf)) @unlink($sf); }
    @rmdir($stagingDir);

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

logMsg("Preflight verdadeiro aprovado com 100% de integridade.");
logMsg("  Database: " . ($preflightData['database'] ?? ''));
logMsg("  Engine: " . ($preflightData['engine'] ?? ''));
logMsg("  Triggers verificados: " . ($preflightData['triggers_count'] ?? 0));
logMsg("  Routines verificadas: " . ($preflightData['routines_count'] ?? 0));
logMsg("  Views verificadas: " . ($preflightData['views_count'] ?? 0));

// -------------------------------------------------------------------------
// FASE B: PROMOÇÃO OPERACIONAL SOB MUTEX DA CONTA (Defeito 5 & 6)
// -------------------------------------------------------------------------
logMsg("FASE B: Adquirindo account_mutex antes de qualquer captura ou promoção...");

$accountMutexFile = $configData['account_mutex'] ?? ($privateDir . '/account.lock');
if (!is_dir(dirname($accountMutexFile))) {
    @mkdir(dirname($accountMutexFile), 0700, true);
}
$accountFp = fopen($accountMutexFile, 'c+b');
need($accountFp !== false, "Cannot open account mutex file: {$accountMutexFile}");
need(flock($accountFp, LOCK_EX | LOCK_NB), "BLOCKED: account.lock retido por outro processo do publisher. Impossível promover concorrentemente.");

// Capture operational backup of active files strictly UNDER account_mutex
$backupDir = $privateDir . '/operational-backups/backup-' . time() . '-' . $attemptId;
mkdir($backupDir, 0700, true);
if (PHP_OS_FAMILY !== 'Windows') @chmod($backupDir, 0700);
copy($configPath, $backupDir . '/receiver-config.json');

$modifiedFiles = [];
$createdFiles = [];
foreach ($filesPlan as $relPath => $info) {
    $dest = $info['dest'];
    if (file_exists($dest)) {
        $backupDest = $backupDir . '/' . basename($dest);
        copy($dest, $backupDest);
        $modifiedFiles[$dest] = [
            'file' => basename($dest),
            'hash' => digestFile($dest),
        ];
    } else {
        $createdFiles[] = $dest;
    }
}

// Record durable journal
$journalData = [
    'attempt_id'             => $attemptId,
    'started_at'             => date('c'),
    'state'                  => 'promoting',
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

try {
    // Promote files atomically via atomicPromote (rename per file)
    foreach ($filesPlan as $relPath => $info) {
        $stagedSource = $info['staged'];
        $dest = $info['dest'];
        atomicPromote($stagedSource, $dest, 0600);
        $promotedHash = digestFile($dest);
        need($promotedHash === $info['hash'], "Divergência de hash pós-cópia em {$dest}");
        logMsg("  [OK] Promovido atomicamente: {$dest} ({$promotedHash})");
    }

    // Update hook pin in receiver-config.json
    $newHookHash = digestFile($filesPlan['publisher/bin/mysql-backup-hook.php']['dest']);
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
logMsg("FASE C: Transferindo controle ao publisher oficial (controlled-pull.php)...");

$journalData['state'] = 'transferring_to_publisher';
atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

// Release account mutex before launching controlled-pull so it acquires it cleanly
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

$deadline = microtime(true) + 600;
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

    $gateClosedR10 = file_exists(($configData['request_gate']['root'] ?? $prodRoot . '/public') . '/closed.json');
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
$gateClosed = file_exists(($configData['request_gate']['root'] ?? $prodRoot . '/public') . '/closed.json');
need(!$gateClosed, "RequestDrain permaneceu fechado após conclusão do deploy");

// Verify deployment.lock is absent
need(!file_exists($deployLock), "deployment.lock ainda presente após publicação bem-sucedida");

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
    'deployment_lock_absent' => true,
];
atomicWrite($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

logMsg("=== SUCESSO DEFINITIVO: RELEASE UI-001 INSTALADA E HOMOLOGADA ===");
logMsg("Recibo registrado em: {$receiptPath}");

flock($lockFp, LOCK_UN);
fclose($lockFp);
exit(0);
