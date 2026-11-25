<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

/**
 * Optional CLI companion to the Cron-integrated pending-update mechanism.
 * Uses the exact same single-file pointer (runtime-active.json) and immutable
 * runtime/<slot>/ layout, avoiding dual-directory rename races.
 * On hosts without SSH/Terminal (like UOL cPanel), drop pending-update/update.json +
 * pending-update/lib/ (or pending-update/rollback.request) and let the Cron apply it.
 */
$publisherRoot = dirname(__DIR__);
$pointerPath = $publisherRoot . '/runtime-active.json';
$runtimeRoot = $publisherRoot . '/runtime';
$fallbackLib = $publisherRoot . '/lib';

$bootstrapLib = $fallbackLib;
if (is_file($pointerPath) && !is_link($pointerPath)) {
    $ptr = json_decode((string)file_get_contents($pointerPath), true);
    if (is_array($ptr) && is_string($ptr['active'] ?? null) && preg_match('/^[A-Za-z0-9_.-]+$/D', $ptr['active']) === 1) {
        $bootstrapLib = $runtimeRoot . '/' . $ptr['active'];
    }
}
require_once $bootstrapLib . '/RuntimeIdentity.php';

use Rumo\Publisher\RuntimeIdentity;
use function Rumo\Publisher\{atomic, json, need};

$lockHandle = null;
try {
    $opts = getopt('', ['config:', 'source:', 'slot:', 'manifest-sha256:', 'rollback']);
    need(isset($opts['config']), 'Usage: --config=receiver-config.json (--source=DIR --slot=VERSION --manifest-sha256=SHA256 | --rollback)');
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

    $curPtr = is_file($pointerPath) ? json(file_get_contents($pointerPath)) : null;

    if (isset($opts['rollback'])) {
        need(is_array($curPtr) && is_string($curPtr['previous'] ?? null) && $curPtr['previous'] !== '',
            'No previous runtime slot recorded in runtime-active.json');
        $prevSlot = $curPtr['previous'];
        need(preg_match('/^[A-Za-z0-9_.-]+$/D', $prevSlot) === 1, 'Invalid previous runtime slot');
        $prevDir = $runtimeRoot . '/' . $prevSlot;
        $prevInfo = RuntimeIdentity::inspect($prevDir, true, true, is_string($curPtr['previous_manifest_sha256'] ?? null) ? $curPtr['previous_manifest_sha256'] : null);
        $newPtr = [
            'schema' => RuntimeIdentity::POINTER_SCHEMA,
            'active' => $prevSlot,
            'manifest_sha256' => $prevInfo['manifest_sha256'],
            'previous' => $curPtr['active'],
            'previous_manifest_sha256' => $curPtr['manifest_sha256'] ?? null,
            'switched_at' => gmdate('c'),
            'action' => 'rollback',
        ];
        atomic($pointerPath, json_encode($newPtr, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        echo json_encode(['state' => 'rolled_back', 'active_runtime' => $prevInfo, 'pointer_slot' => $prevSlot], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    need(isset($opts['source'], $opts['slot'], $opts['manifest-sha256']),
        'Requires --source=DIR --slot=VERSION --manifest-sha256=SHA256');
    $sourceDir = realpath($opts['source']);
    need($sourceDir !== false && is_dir($sourceDir) && !is_link($opts['source']), 'Valid staged source directory required');
    $slot = trim($opts['slot']);
    need(preg_match('/^[A-Za-z0-9_.-]+$/D', $slot) === 1, 'Invalid runtime slot name');
    $expectedSha = strtolower(trim($opts['manifest-sha256']));
    need(preg_match('/^[a-f0-9]{64}$/D', $expectedSha) === 1, 'Valid 64-hex manifest SHA-256 required');

    RuntimeIdentity::inspect($sourceDir, true, true, $expectedSha);
    if (!is_dir($runtimeRoot)) need(mkdir($runtimeRoot, 0700, true), 'Cannot create runtime directory');
    $destSlot = $runtimeRoot . '/' . $slot;
    if (!is_dir($destSlot)) {
        $tmpSlot = $runtimeRoot . '/.staging-' . $slot . '-' . bin2hex(random_bytes(4));
        need(mkdir($tmpSlot, 0700, true), 'Cannot create staging slot');
        foreach (scandir($sourceDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            need(copy($sourceDir . '/' . $entry, $tmpSlot . '/' . $entry), 'Copy failed: ' . $entry);
            if (PHP_OS_FAMILY !== 'Windows') @chmod($tmpSlot . '/' . $entry, 0600);
        }
        RuntimeIdentity::inspect($tmpSlot, true, true, $expectedSha);
        need(rename($tmpSlot, $destSlot), 'Cannot commit staged runtime slot');
    }
    $destInfo = RuntimeIdentity::inspect($destSlot, true, true, $expectedSha);
    $newPtr = [
        'schema' => RuntimeIdentity::POINTER_SCHEMA,
        'active' => $slot,
        'manifest_sha256' => $destInfo['manifest_sha256'],
        'previous' => is_array($curPtr) ? ($curPtr['active'] ?? null) : null,
        'previous_manifest_sha256' => is_array($curPtr) ? ($curPtr['manifest_sha256'] ?? null) : null,
        'switched_at' => gmdate('c'),
        'action' => 'self_update',
    ];
    atomic($pointerPath, json_encode($newPtr, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo json_encode(['state' => 'updated', 'active_runtime' => $destInfo, 'pointer_slot' => $slot], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'SELF-UPDATE BLOCKED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
