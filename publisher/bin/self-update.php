<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

/**
 * Controlled, atomic self-update tool for the consolidated Publisher runtime.
 * Never mixes library versions: stages into a sibling directory, verifies SHA-256
 * manifest integrity and PHP syntax, acquires account_mutex, and performs atomic
 * directory swaps (lib -> lib-prev, lib-next -> lib), with instant --rollback.
 */
$publisherRoot = dirname(__DIR__);
$publisherLib = $publisherRoot . '/lib';
require_once $publisherLib . '/RuntimeIdentity.php';

use Rumo\Publisher\RuntimeIdentity;
use function Rumo\Publisher\{json, need, record};

$lockHandle = null;
try {
    $opts = getopt('', ['config:', 'source:', 'manifest-sha256:', 'rollback']);
    need(isset($opts['config']), 'Usage: --config=receiver-config.json (--source=DIR --manifest-sha256=SHA256 | --rollback)');
    $configPath = $opts['config'];
    need(is_file($configPath) && !is_link($configPath), 'Private config required');
    if (PHP_OS_FAMILY !== 'Windows') {
        need((fileperms($configPath) & 0077) === 0, 'Config must be 0600');
    }
    $cfg = json(file_get_contents($configPath));
    $mutexPath = $cfg['account_mutex'] ?? null;
    need(is_string($mutexPath) && $mutexPath !== '' && !is_link($mutexPath), 'Valid account_mutex required');
    $lockHandle = fopen($mutexPath, 'c+b');
    need($lockHandle !== false && flock($lockHandle, LOCK_EX | LOCK_NB), 'Concurrent publisher refused');

    $activeLib = $publisherRoot . '/lib';
    $prevLib = $publisherRoot . '/lib-prev';
    $failedLib = $publisherRoot . '/lib-failed-' . date('YmdHis');

    if (isset($opts['rollback'])) {
        need(is_dir($prevLib) && !is_link($prevLib), 'No previous library backup (lib-prev) available for rollback');
        $prevIdentity = RuntimeIdentity::inspect($prevLib, true);
        need(rename($activeLib, $failedLib), 'Cannot move active library aside during rollback');
        if (!rename($prevLib, $activeLib)) {
            @rename($failedLib, $activeLib);
            throw new RuntimeException('Cannot restore lib-prev during rollback');
        }
        $restored = RuntimeIdentity::inspect($activeLib, true);
        echo json_encode([
            'state' => 'rolled_back',
            'active_runtime' => $restored,
            'quarantined_path' => str_replace('\\', '/', $failedLib),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    need(isset($opts['source'], $opts['manifest-sha256']), 'Both --source=DIR and --manifest-sha256=SHA256 are required');
    $sourceDir = realpath($opts['source']);
    need($sourceDir !== false && is_dir($sourceDir) && !is_link($opts['source']), 'Valid staged source directory required');
    $expectedManifestSha = strtolower(trim($opts['manifest-sha256']));
    need(preg_match('/^[a-f0-9]{64}$/D', $expectedManifestSha) === 1, 'Valid 64-hex manifest SHA-256 required');

    $candidateIdentity = RuntimeIdentity::inspect($sourceDir, true);
    need($candidateIdentity['manifest_sha256'] !== null &&
        hash_equals($expectedManifestSha, $candidateIdentity['manifest_sha256']),
        'Staged publisher manifest SHA-256 mismatch');

    $stageNext = $publisherRoot . '/lib-next-' . bin2hex(random_bytes(4));
    need(mkdir($stageNext, 0700, true), 'Cannot create staging directory for publisher self-update');
    foreach (scandir($sourceDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $srcFile = $sourceDir . DIRECTORY_SEPARATOR . $entry;
        need(is_file($srcFile) && !is_link($srcFile), 'Unexpected non-file entry in staged library: '.$entry);
        need(preg_match('/^(?:[A-Za-z0-9_.-]+\.php|version\.json)$/D', $entry) === 1,
            'Unexpected file in staged publisher library: '.$entry);
        need(copy($srcFile, $stageNext . DIRECTORY_SEPARATOR . $entry), 'Copy failed for '.$entry);
        if (PHP_OS_FAMILY !== 'Windows') {
            @chmod($stageNext . DIRECTORY_SEPARATOR . $entry, 0600);
        }
    }
    RuntimeIdentity::inspect($stageNext, true);

    $beforeIdentity = RuntimeIdentity::inspect($activeLib, false);
    if (is_dir($prevLib)) {
        $oldPrev = $publisherRoot . '/lib-prev-archived-' . date('YmdHis');
        need(rename($prevLib, $oldPrev), 'Cannot archive previous lib-prev');
    }
    need(rename($activeLib, $prevLib), 'Cannot move active lib to lib-prev');
    if (!rename($stageNext, $activeLib)) {
        @rename($prevLib, $activeLib);
        throw new RuntimeException('Atomic swap of new publisher library failed; restored previous library');
    }
    $afterIdentity = RuntimeIdentity::inspect($activeLib, true);

    echo json_encode([
        'state' => 'updated',
        'previous_runtime' => $beforeIdentity,
        'active_runtime' => $afterIdentity,
        'rollback_dir' => str_replace('\\', '/', $prevLib),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'SELF-UPDATE BLOCKED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
