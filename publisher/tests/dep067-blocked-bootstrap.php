<?php
declare(strict_types=1);

// Run with PHP 8.4: php publisher/tests/dep067-blocked-bootstrap.php <DEP058-FINAL directory>
require $argv[1].'/lib/Bootstrap.php';
require dirname(__DIR__).'/lib/RequestDrain.php';
require dirname(__DIR__).'/bootstrap/lib/BlockedReceiver.php';

use Rumo\Publisher\RequestDrain;
use function Dep058\{ensure, hashFile, save, updateBlockedReceiver};
use const Dep058\BLOCKED_RECEIVER_FILES;

function blob(string $commit, string $path): string
{
    $process = proc_open(['git', '-C', dirname(__DIR__, 2), 'show', $commit.':'.$path],
        [1=>['pipe', 'wb']], $pipes);
    ensure(is_resource($process), 'Cannot read Git blob');
    $bytes = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    ensure(proc_close($process) === 0, 'Missing Git blob');
    return $bytes;
}

function reject(callable $action, string $expected): void
{
    try {
        $action();
    } catch (RuntimeException $error) {
        ensure(str_contains($error->getMessage(), $expected), 'Unexpected rejection: '.$error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: '.$expected);
}

$root = str_replace('\\', '/', sys_get_temp_dir()).'/r10-dep067-'.bin2hex(random_bytes(6));
foreach (['receiver/state', 'source', 'current', 'public/testeCodex', 'hub-public', 'apps/rumo10', 'apps/rumohub'] as $dir) {
    ensure(mkdir($root.'/'.$dir, 0700, true), 'Fixture directory creation failed');
}
$receiver = $root.'/receiver';
$private = $receiver.'/state';
$source = $root.'/source';
$gate = $root.'/gate';
$control = $root.'/apps/rumo10';
$profile = $root.'/profile.json';
$public = $root.'/public';
$owner = str_repeat('a', 32);
RequestDrain::provision($gate);
(new RequestDrain($gate))->close($owner);
foreach ([$control.'/maintenance.lock', $root.'/apps/rumohub/maintenance.lock', $root.'/publisher.lock'] as $lock) {
    file_put_contents($lock, '');
}
save($profile, ['receiver'=>$receiver, 'gate'=>$gate, 'rumo10_public'=>$public, 'rumohub_public'=>$root.'/hub-public']);
save($receiver.'/receiver-config.json', ['project'=>'rumo10', 'private'=>$private, 'public'=>$public,
    'control'=>$control, 'current'=>$root.'/current', 'account_mutex'=>$root.'/publisher.lock', 'request_gate'=>['root'=>$gate]]);
save($root.'/current/release.json', ['files'=>[]]);
save($private.'/installed.json', ['commit'=>'f23c2c3be2fbc322daf817fa14346ab02073a97b', 'artifact_id'=>13328263163,
    'manifest_sha256'=>hashFile($root.'/current/release.json')]);
save($private.'/journal.json', ['state'=>'validated']);
file_put_contents($public.'/testeCodex/.htaccess', '# synthetic protected file');
$legacyHash = hashFile($public.'/testeCodex/.htaccess');
$session = ['state'=>'blocked', 'owner'=>$owner, 'stage'=>$private.'/stage-13792649622-adb5114913f2cafb',
    'journal_before'=>hashFile($private.'/journal.json'), 'content_before'=>['public/testeCodex/.htaccess'=>$legacyHash]];
save($private.'/drain-session.json', $session);

foreach (BLOCKED_RECEIVER_FILES as $path => [$old, $new]) {
    foreach ([$receiver=>'61f0f431', $source=>'0d437e5'] as $dir => $commit) {
        if (!is_dir(dirname($dir.'/'.$path))) mkdir(dirname($dir.'/'.$path), 0700, true);
        file_put_contents($dir.'/'.$path, blob($commit, $path));
    }
    ensure(hashFile($receiver.'/'.$path) === $old && hashFile($source.'/'.$path) === $new, 'Fixture blob mismatch');
}
$operation = static fn (?string $approval = null) => updateBlockedReceiver($profile, $source, $approval, $legacyHash);
$plan = $operation()['plan_sha256'];
foreach (BLOCKED_RECEIVER_FILES as $path => [$old]) ensure(hashFile($receiver.'/'.$path) === $old, 'Plan wrote to receiver');
reject(fn () => $operation(str_repeat('0', 64)), 'approval mismatch');
save($private.'/drain-session.json', array_replace($session, ['owner'=>str_repeat('b', 32)]));
reject($operation, 'Foreign gate owner');
save($private.'/drain-session.json', $session);
file_put_contents($control.'/deployment.lock', '');
reject($operation, 'Deployment lock marker is pending');
unlink($control.'/deployment.lock');
$launcher = '/publisher/bin/controlled-pull.php';
$bytes = file_get_contents($source.$launcher);
file_put_contents($source.$launcher, '<?php // unauthorized');
reject(fn () => $operation($plan), 'Unapproved receiver payload');
file_put_contents($source.$launcher, $bytes);

$protected = [$private.'/journal.json', $private.'/drain-session.json', $private.'/installed.json',
    $receiver.'/receiver-config.json', $gate.'/closed.json', $gate.'/identity.json',
    $gate.'/writers.lock', $public.'/testeCodex/.htaccess'];
$snapshot = array_map('Dep058\\hashFile', $protected);
ensure($operation($plan)['state'] === 'installed-held', 'Installation from original files failed');
// Emulate interruption after publishing the backward-compatible library.
file_put_contents($receiver.$launcher, blob('61f0f431', substr($launcher, 1)));
ensure($operation($plan)['state'] === 'installed-held', 'Installation did not resume');
ensure($operation($plan)['state'] === 'installed-held', 'Installation was not idempotent');
foreach (BLOCKED_RECEIVER_FILES as $path => [, $new]) ensure(hashFile($receiver.'/'.$path) === $new, 'Incorrect installed bytes');
ensure($snapshot === array_map('Dep058\\hashFile', $protected), 'Protected file changed');
echo "PASS: exact receiver update, interrupted resume, and rejection of an unapproved plan, foreign gate, active deployment and altered payload\n";
