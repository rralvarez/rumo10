<?php
declare(strict_types=1);

namespace Dep058;

use Rumo\Publisher\RequestDrain;

const BLOCKED_RECEIVER_FILES = [
    // Install the library first: the old launcher can still call recover() without the new flag.
    'publisher/lib/DrainPublisher.php' => [
        '13187f8c1c60921be4b2747c6488ea16dfce293e8e046f1ead86a9099a300b45',
        '5cc1e6d5e3c2b1ba730b284ce7edd709a79c4551dac5a893c24c8fd182c61783',
    ],
    'publisher/bin/controlled-pull.php' => [
        '1c240fbf0616effa587a2c197f70c8b8cc6ff7e98b9367564e3f2328b8cb8dc0',
        '527f32612f6d7be66050d37bb9b4da80e8cb728197a0e4bfef8ab5ba9000b219',
    ],
];

function updateBlockedReceiver(string $profilePath, string $source, ?string $approval, string $legacyHash): array
{
    physical($profilePath);
    ensure(PHP_OS_FAMILY === 'Windows' || (fileperms($profilePath) & 0077) === 0, 'Profile must be private');
    $profile = readJson($profilePath);
    $receiver = $profile['receiver'];
    $gatePath = $profile['gate'];
    $configPath = $receiver.'/receiver-config.json';
    physical($configPath);
    ensure(PHP_OS_FAMILY === 'Windows' || (fileperms($configPath) & 0077) === 0, 'Receiver config must be private');
    $config = readJson($configPath);
    ensure(($config['project'] ?? null) === 'rumo10' && $config['request_gate']['root'] === $gatePath &&
        $config['public'] === $profile['rumo10_public'], 'Unexpected receiver profile');
    foreach ([$source, $receiver, $gatePath, $config['private'], $config['control'], $config['current']] as $root) {
        physical($root);
        ensure(is_dir($root), 'Expected physical directory');
    }
    foreach ([$profile['rumo10_public'], $profile['rumohub_public'], $config['current']] as $public) {
        physical($public);
        ensure(is_dir($public), 'Expected physical site root');
        $public = str_replace('\\', '/', realpath($public)).'/';
        foreach ([$source, $receiver, $gatePath, $config['private']] as $private) {
            $private = str_replace('\\', '/', realpath($private)).'/';
            ensure(!str_starts_with($private, $public), 'Recovery files must be outside site roots');
        }
    }

    $mutexPath = $config['account_mutex'];
    physical($mutexPath);
    $mutex = fopen($mutexPath, 'r+b');
    ensure($mutex !== false && fstat($mutex)['nlink'] === 1, 'Invalid publisher mutex');
    try {
        ensure(flock($mutex, LOCK_EX | LOCK_NB), 'Publisher/bootstrap already active');
        $private = $config['private'];
        $sessionPath = $private.'/drain-session.json';
        $journalPath = $private.'/journal.json';
        $installedPath = $private.'/installed.json';
        $closedPath = $gatePath.'/closed.json';
        $deploymentLock = $config['control'].'/deployment.lock';
        $maintenance = [$config['control'], dirname($config['control']).'/rumohub'];
        $protected = [$configPath, $sessionPath, $journalPath, $installedPath, $closedPath,
            $gatePath.'/identity.json'];
        foreach ($maintenance as $control) $protected[] = $control.'/maintenance.lock';
        $snapshot = static function () use ($protected): array {
            clearstatcache();
            $state = [];
            foreach ($protected as $file) {
                physical($file);
                $state[$file] = [hashFile($file), fileperms($file) & 0777];
            }
            return $state;
        };
        $before = $snapshot();
        $session = readJson($sessionPath);
        $installed = readJson($installedPath);
        ensure(($session['state'] ?? null) === 'blocked' &&
            ($session['stage'] ?? null) === $private.'/stage-13792649622-adb5114913f2cafb' &&
            ($session['journal_before'] ?? null) === $before[$journalPath][0] &&
            (readJson($journalPath)['state'] ?? null) === 'validated' &&
            ($installed['commit'] ?? null) === 'f23c2c3be2fbc322daf817fa14346ab02073a97b' &&
            ($installed['artifact_id'] ?? null) === 13328263163 &&
            ($installed['manifest_sha256'] ?? null) === hashFile($config['current'].'/release.json'),
            'Expected blocked pre-install session and V8 receipt');
        $legacy = $config['public'].'/testeCodex/.htaccess';
        physical($legacy);
        ensure(($session['content_before']['public/testeCodex/.htaccess'] ?? null) === $legacyHash &&
            hashFile($legacy) === $legacyHash, 'Expected original testeCodex file');
        $assertState = static function () use ($snapshot, $before, $maintenance, $deploymentLock): void {
            ensure($snapshot() === $before, 'Protected receiver state changed');
            physical($deploymentLock);
            ensure(!file_exists($deploymentLock), 'Deployment lock marker is pending');
            foreach ($maintenance as $control) {
                $exception = $control.'/maintenance-allow.json';
                physical($exception);
                ensure(!file_exists($exception), 'Maintenance exception present');
            }
        };
        $assertState();
        $gate = new RequestDrain($gatePath);
        $gate->resume($session['owner']);
        $gate->drain(1);
        $gate->assertDrained();

        $changes = [];
        $sawOld = false;
        foreach (BLOCKED_RECEIVER_FILES as $relative => [$old, $new]) {
            $target = $receiver.'/'.$relative;
            $candidate = $source.'/'.$relative;
            physical($target);
            physical($candidate);
            $installedHash = hashFile($target);
            ensure(lstat($target)['nlink'] === 1 &&
                ($installedHash === $old || (!$sawOld && $installedHash === $new)),
                'Unexpected receiver bytes or install order: '.$relative);
            $sawOld = $sawOld || $installedHash === $old;
            $bytes = file_get_contents($candidate);
            ensure(is_string($bytes) && hash('sha256', $bytes) === $new,
                'Unapproved receiver payload: '.$relative);
            $changes[$relative] = [$target, $installedHash, $new, $bytes, fileperms($target) & 0777];
        }
        $plan = hash('sha256', encode([$receiver, $before, $legacyHash, BLOCKED_RECEIVER_FILES]));
        if ($approval !== null) {
            ensure(hash_equals($plan, $approval), 'Blocked receiver plan approval mismatch');
            foreach ($changes as [$target, $installedHash, $new, $bytes, $mode]) {
                $gate->assertDrained();
                $assertState();
                ensure(hashFile($target) === $installedHash, 'Receiver target changed');
                if ($installedHash !== $new) atomic($target, $bytes, $mode);
                clearstatcache(true, $target);
                ensure(hashFile($target) === $new && (fileperms($target) & 0777) === $mode, 'Receiver verification failed');
            }
        }
        $gate->assertDrained();
        $assertState();
        return ['state' => $approval === null ? 'planned' : 'installed-held', 'plan_sha256' => $plan,
            'receiver_files' => BLOCKED_RECEIVER_FILES];
    } finally {
        fclose($mutex);
    }
}
