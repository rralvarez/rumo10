<?php
declare(strict_types=1);

/**
 * RUMO10 | Transactional & Recoverable Operational Installer & Publisher (v4)
 *
 * Implements strict mutual exclusion, durable step-by-step journaling,
 * private staging, post-copy hash/permission verification, preflight full data dump proof,
 * verified idempotent recovery and rollback, and end-to-end production verification.
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

// Requirement 4: Obtain private dir and installed.json EXCLUSIVELY via config
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

logMsg("=== RUMO10 | INICIALIZANDO INSTALADOR TRANSACIONAL E PUBLICADOR DEFINITIVO (V4) ===");
logMsg("Configuração: {$configPath}");
logMsg("Diretório privado: {$privateDir}");

// -------------------------------------------------------------------------
// 1. Mutual Exclusion & Lock Compatibility
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

// Check publisher locks
$controlDir = $configData['control'] ?? dirname($configPath) . '/control';
$deployLock = $controlDir . '/deployment.lock';
if (file_exists($deployLock)) {
    fwrite(STDERR, "BLOCKED: deployment.lock ativo em {$deployLock}. Não é seguro promover arquivos durante deployment.\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}

$accountLock = $configData['account_mutex'] ?? ($privateDir . '/account.lock');
if (file_exists($accountLock)) {
    $afp = @fopen($accountLock, 'r+');
    if ($afp && !flock($afp, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "BLOCKED: account.lock retido por outro processo do publisher.\n");
        fclose($afp);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(1);
    }
    if ($afp) {
        flock($afp, LOCK_UN);
        fclose($afp);
    }
}

// -------------------------------------------------------------------------
// Verified Rollback Function
// -------------------------------------------------------------------------
function executeRollback(array $j, string $cfgPath, string $jPath): bool {
    logMsg("EXECUTANDO ROLLBACK TRANSACIONAL VERIFICADO...");
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
        $phpBin = PHP_BINARY;
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
        logMsg("RECUPERAÇÃO DE INTERRUPÇÃO: Detectada tentativa anterior interrompida no estado '{$priorState}'.");
        $rbOk = executeRollback($priorJournal, $configPath, $journalFile);
        need($rbOk, "Falha na recuperação de tentativa anterior interrompida. Intervenção necessária.");
        logMsg("Recuperação concluída: ambiente restaurado ao estado íntegro original.");
    }
}

// -------------------------------------------------------------------------
// 3. Pre-validation of Patch Files and Hashes
// -------------------------------------------------------------------------
logMsg("Fase 1: Verificação estrita de integridade prévia dos arquivos do patch...");

$expectedHashes = [
    'mysql-backup-hook.php' => 'f32510fab84346c19b0164a03aa694829f1a2778746e12cdc2d6468bd1f08aaa',
    'diagnose-uol.php'      => '801dc65e94020b2a5e23a16673c04e4b0b945be7c701076f19368a6286843d51',
    'guarded-publisher.php' => '4bcddba4b8d717bc5eb045da77358eb7298e63f4d65877caf9e3cf61d4249015',
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
logMsg("Integridade dos " . count($filesPlan) . " arquivos do patch 100% comprovada via SHA-256.");

// -------------------------------------------------------------------------
// 4. Private Staging & Backup Creation
// -------------------------------------------------------------------------
logMsg("Fase 2: Preparando staging privado e backup de salvaguarda...");

$attemptId = bin2hex(random_bytes(8));
$stagingDir = $privateDir . '/patch-staging-' . $attemptId;
mkdir($stagingDir, 0700, true);
if (PHP_OS_FAMILY !== 'Windows') @chmod($stagingDir, 0700);

// Stage files
foreach ($filesPlan as $file => $info) {
    $stagedFile = $stagingDir . '/' . $file;
    copy($info['source'], $stagedFile);
    if (PHP_OS_FAMILY !== 'Windows') @chmod($stagedFile, 0600);
    $stagedHash = digestFile($stagedFile);
    need($stagedHash === $info['hash'], "Falha de integridade no staging de {$file}");
}

// Backup existing files and categorize
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

// Initialize durable journal
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
logMsg("Journal durável registrado em {$journalFile} (estado: staged).");

// -------------------------------------------------------------------------
// 5. Atomic Promotion & Post-Copy Verification
// -------------------------------------------------------------------------
logMsg("Fase 3: Promovendo arquivos operacionais e verificando permissões e hashes...");

try {
    foreach ($filesPlan as $file => $info) {
        $stagedSource = $stagingDir . '/' . $file;
        $dest = $info['dest'];
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0700, true);
        }
        if (!copy($stagedSource, $dest)) {
            throw new \RuntimeException("Falha ao copiar {$file} para {$dest}");
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($dest, 0600);
        }
        $promotedHash = digestFile($dest);
        need($promotedHash === $info['hash'], "Divergência de hash pós-cópia em {$dest}: {$promotedHash} vs {$info['hash']}");
        logMsg("  [OK] Promovido e verificado: {$dest} ({$promotedHash})");
    }

    $journalData['state'] = 'promoted';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    // -------------------------------------------------------------------------
    // 6. Update Hook Pin in receiver-config.json
    // -------------------------------------------------------------------------
    logMsg("Fase 4: Sincronizando hash SHA-256 do hook em receiver-config.json...");

    $newHookHash = $expectedHashes['mysql-backup-hook.php'];
    need(isset($configData['hooks']['backup']), "hooks.backup ausente em {$configPath}");
    $oldHookHash = $configData['hooks']['backup']['sha256'] ?? 'none';
    logMsg("Hash anterior: {$oldHookHash}");
    logMsg("Novo hash homologado: {$newHookHash}");

    $configData['hooks']['backup']['sha256'] = $newHookHash;
    atomicWrite($configPath, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    $journalData['state'] = 'pinned';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    // -------------------------------------------------------------------------
    // 7. True Preflight Proof (Full Data Dump Test Before Maintenance)
    // -------------------------------------------------------------------------
    logMsg("Fase 5: Executando preflight verdadeiro (exportação completa com dados)...");

    $phpBin = PHP_BINARY;
    $uolPhp = '/opt/alt/php84/usr/bin/php';
    if (PHP_OS_FAMILY !== 'Windows' && is_executable($uolPhp)) {
        $phpBin = $uolPhp;
    }

    $hookScript = $filesPlan['mysql-backup-hook.php']['dest'];
    $hookArgs = $configData['hooks']['backup']['args'] ?? [];

    $preflightCmd = array_merge([$phpBin, $hookScript, 'preflight'], $hookArgs);
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
        throw new \RuntimeException("FALHA BLOQUEANTE NO PREFLIGHT COMPLETO (código {$preflightExit}): " . $cleanPrefErr);
    }

    logMsg("Preflight com dump completo de dados APROVADO com 100% de sucesso.");
    logMsg("  Database: " . ($preflightData['database'] ?? ''));
    logMsg("  Engine: " . ($preflightData['engine'] ?? ''));
    logMsg("  Triggers verificados: " . ($preflightData['triggers_count'] ?? 0));
    logMsg("  Views verificadas: " . ($preflightData['views_count'] ?? 0));

    $journalData['state'] = 'preflight_passed';
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    // -------------------------------------------------------------------------
    // 8. Publish Authorized Release (Commit fb03b614)
    // -------------------------------------------------------------------------
    if (isset($opts['skip-publish'])) {
        logMsg("AVISO: Publicação ignorada devido ao parâmetro --skip-publish.");
        $journalData['state'] = 'validated';
        atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(0);
    }

    logMsg("Fase 6: Disparando publicação HTTPS da release autorizada UI-001...");
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

    $pubOut = is_file($pubOutLog) ? trim(file_get_contents($pubOutLog)) : '';
    $pubErr = is_file($pubErrLog) ? trim(file_get_contents($pubErrLog)) : '';
    if (is_file($pubOutLog)) @unlink($pubOutLog);
    if (is_file($pubErrLog)) @unlink($pubErrLog);

    if ($pubExit !== 0) {
        $cleanPubErr = preg_replace('/password=[^\s]+/i', 'password=[REDACTED]', $pubErr);
        $cleanPubErr = preg_replace('/(token|bearer)\s+[a-zA-Z0-9_\-]+/i', '$1 [REDACTED]', $cleanPubErr);
        throw new \RuntimeException("FALHA NA PUBLICAÇÃO (código {$pubExit}): " . $cleanPubErr);
    }

    logMsg("Publicação concluída com código 0.");

    // -------------------------------------------------------------------------
    // 9. Comprehensive Post-Publish Verification
    // -------------------------------------------------------------------------
    logMsg("Fase 7: Verificação integral pós-publicação da release e do ambiente...");

    // A. Check installed.json
    need(is_file($installedJsonPath), "installed.json ausente em {$installedJsonPath}");
    $installed = json_decode(file_get_contents($installedJsonPath), true);
    need(($installed['commit'] ?? '') === $targetCommit, "Commit divergente em installed.json: esperava {$targetCommit}, obteve " . ($installed['commit'] ?? ''));
    need((int)($installed['artifact_id'] ?? 0) === $targetArtifactId, "Artifact ID divergente em installed.json");
    logMsg("  [OK] installed.json confirmado: commit {$targetCommit}, artifact {$targetArtifactId}");

    // B. Check journal.json
    $prodJournal = $privateDir . '/journal.json';
    need(is_file($prodJournal), "journal.json ausente em {$prodJournal}");
    $pJourn = json_decode(file_get_contents($prodJournal), true);
    need(($pJourn['state'] ?? '') === 'validated', "Estado do journal.json não é 'validated'");
    logMsg("  [OK] journal.json confirmado: state validated");

    // C. Check locks and RequestDrain
    need(!file_exists($deployLock), "deployment.lock ainda retido após publicação");
    logMsg("  [OK] deployment.lock ausente e liberado");

    // D. Smoke verification of public entrypoint
    $publicDir = $configData['public'] ?? (dirname($configPath) . '/public_html');
    $indexFile = $publicDir . '/index.php';
    need(is_file($indexFile), "index.php público ausente em {$indexFile}");
    logMsg("  [OK] index.php público preservado e acessível");

    // Success receipt
    $receipt = [
        'status'         => 'success',
        'attempt_id'     => $attemptId,
        'installed_at'   => date('c'),
        'target_commit'  => $targetCommit,
        'artifact_id'    => $targetArtifactId,
        'run_id'         => $targetRunId,
        'config_file'    => $configPath,
        'files_installed'=> array_keys($filesPlan),
        'backup_dir'     => $backupDir,
        'preflight'      => $preflightData,
    ];

    atomicWrite($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);
    @copy($receiptPath, dirname($configPath) . '/patch-install-receipt.json');

    $journalData['state'] = 'validated';
    $journalData['completed_at'] = date('c');
    atomicWrite($journalFile, json_encode($journalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    // Clean up staging
    if (is_dir($stagingDir)) {
        $stFiles = glob($stagingDir . '/*');
        foreach ($stFiles as $sf) { if (is_file($sf)) @unlink($sf); }
        @rmdir($stagingDir);
    }

    logMsg("=== INSTALAÇÃO E PUBLICAÇÃO DEFINITIVA CONCLUÍDAS COM SUCESSO! ===");
    logMsg("Recibo registrado em {$receiptPath}");

    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);

} catch (\Throwable $e) {
    logMsg("ERRO BLOQUEANTE DETECTADO: " . $e->getMessage());
    $rbOk = executeRollback($journalData, $configPath, $journalFile);
    if ($rbOk) {
        logMsg("Rollback transacional concluído com sucesso e verificado.");
    } else {
        logMsg("ALERTA CRÍTICO: Falha na verificação final do rollback!");
    }

    $failedReceipt = [
        'status'       => 'aborted_rollback_executed',
        'attempt_id'   => $attemptId,
        'failed_at'    => date('c'),
        'error'        => $e->getMessage(),
        'rollback_ok'  => $rbOk,
        'backup_dir'   => $backupDir,
    ];
    @atomicWrite($receiptPath, json_encode($failedReceipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, 0600);

    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}
