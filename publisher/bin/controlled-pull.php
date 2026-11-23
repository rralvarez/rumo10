<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }

/**
 * Trusted static bootstrap — executes BEFORE any mutable library PHP file is loaded.
 * 1. Acquires account_mutex (when --config is provided) for continuous mutual exclusion.
 * 2. Processes Cron-integrated pending-update controls (no SSH required):
 *    - pending-update/rollback.request  -> switches runtime-active.json back to previous version
 *    - pending-update/update.json       -> validates staged version & switches runtime-active.json via single-file rename
 *    - pending-update/recover.request   -> triggers official Publisher::recover() inside the Cron cycle
 * 3. Strictly verifies version.json manifest and exact 1:1 SHA-256 inventory of the active
 *    library directory BEFORE any require_once of mutable library code.
 */
function rumoBootstrapFail(string $msg, ?array $rt = null): never {
    $tag = is_array($rt) ? ' [publisher='.($rt['version']??'?').' lib='.($rt['lib_path']??'?').']' : '';
    fwrite(STDERR, 'BLOCKED: '.$msg.$tag.PHP_EOL);
    exit(1);
}
function rumoBootstrapAtomicWrite(string $dest, string $bytes): void {
    $tmp = $dest.'.new-'.bin2hex(random_bytes(8));
    $fp = fopen($tmp, 'xb');
    if ($fp === false) throw new RuntimeException('Cannot create temporary pointer file');
    try {
        if (PHP_OS_FAMILY !== 'Windows') @chmod($tmp, 0600);
        if (fwrite($fp, $bytes) !== strlen($bytes) || !fflush($fp) || !fsync($fp)) {
            throw new RuntimeException('Failed to flush pointer file');
        }
    } finally { fclose($fp); }
    if (!@rename($tmp, $dest)) {
        @unlink($tmp);
        throw new RuntimeException('Single-file pointer rename failed');
    }
}
function rumoBootstrapVerifyLib(string $libDir, bool $requireManifest, ?string $expectedManifestSha = null): array {
    $real = realpath($libDir);
    if ($real === false || !is_dir($real) || is_link($libDir)) {
        throw new RuntimeException('Active publisher library directory invalid');
    }
    $norm = str_replace('\\', '/', $real);
    if (str_contains($norm, '/receiver-bootstrap-')) {
        throw new RuntimeException('Obsolete receiver-bootstrap library path refused: '.$norm);
    }
    $versionFile = $real.DIRECTORY_SEPARATOR.'version.json';
    if (!is_file($versionFile)) {
        if ($requireManifest) {
            throw new RuntimeException('Mandatory runtime manifest missing before load: version.json');
        }
        return ['schema'=>'rumo-publisher-runtime-v1','version'=>'dev-unsealed','lib_path'=>$norm,'manifest_sha256'=>null,'verified_files'=>0,'legacy_bootstrap_path'=>false];
    }
    if (is_link($versionFile)) throw new RuntimeException('Runtime version manifest cannot be a symlink');
    $raw = file_get_contents($versionFile);
    if ($raw === false) throw new RuntimeException('Cannot read runtime version manifest');
    $manifestSha = hash('sha256', $raw);
    if ($expectedManifestSha !== null && !hash_equals($expectedManifestSha, $manifestSha)) {
        throw new RuntimeException('Runtime version manifest SHA-256 mismatch before load');
    }
    $meta = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($meta) || ($meta['schema'] ?? null) !== 'rumo-publisher-runtime-v1' || !is_string($meta['version'] ?? null) || $meta['version'] === '') {
        throw new RuntimeException('Invalid runtime version manifest');
    }
    $files = $meta['files'] ?? null;
    if (!is_array($files) || count($files) < 10) {
        throw new RuntimeException('Incomplete runtime manifest file inventory');
    }
    $actualPhp = [];
    foreach (scandir($real) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === 'version.json') continue;
        $full = $real.DIRECTORY_SEPARATOR.$entry;
        if (is_link($full) || !is_file($full)) throw new RuntimeException('Unexpected non-regular entry in publisher library: '.$entry);
        if (!str_ends_with($entry, '.php')) throw new RuntimeException('Unexpected non-PHP file in publisher library: '.$entry);
        $actualPhp[$entry] = true;
    }
    $verified = 0;
    foreach ($files as $name => $expectedHash) {
        if (!is_string($name) || preg_match('/^[A-Za-z0-9_.-]+\.php$/D', $name) !== 1) {
            throw new RuntimeException('Invalid runtime manifest entry: '.(string)$name);
        }
        if (!is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1) {
            throw new RuntimeException('Invalid runtime manifest hash for: '.$name);
        }
        $full = $real.DIRECTORY_SEPARATOR.$name;
        if (!isset($actualPhp[$name]) || !is_file($full) || is_link($full) || !hash_equals($expectedHash, (string)hash_file('sha256', $full))) {
            throw new RuntimeException('Publisher library integrity mismatch before load: '.$name);
        }
        unset($actualPhp[$name]);
        $verified++;
    }
    if ($actualPhp !== []) {
        throw new RuntimeException('Undeclared extra PHP files in publisher library before load: '.implode(', ', array_keys($actualPhp)));
    }
    return [
        'schema' => 'rumo-publisher-runtime-v1',
        'version' => $meta['version'],
        'lib_path' => $norm,
        'manifest_sha256' => $manifestSha,
        'verified_files' => $verified,
        'legacy_bootstrap_path' => false,
    ];
}
function rumoBootstrapResolveAndControl(string $publisherRoot, string $defaultLib, bool $hasLock): array {
    $pointerPath = $publisherRoot.'/runtime-active.json';
    $runtimeRoot = $publisherRoot.'/runtime';
    $pendingDir = $publisherRoot.'/pending-update';
    $controlEvents = [];
    $recoverRequested = false;

    $readPointer = static function () use ($pointerPath): ?array {
        if (!is_file($pointerPath)) return null;
        if (is_link($pointerPath)) throw new RuntimeException('runtime-active.json cannot be a symlink');
        $p = json_decode((string)file_get_contents($pointerPath), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($p) || ($p['schema'] ?? null) !== 'rumo-active-runtime-v1' || !is_string($p['active'] ?? null)) {
            throw new RuntimeException('Invalid runtime-active.json pointer');
        }
        return $p;
    };

    if ($hasLock && is_dir($pendingDir) && !is_link($pendingDir)) {
        // 1. No-SSH Publisher version rollback via pending-update/rollback.request
        $rbFlag = $pendingDir.'/rollback.request';
        if (is_file($rbFlag) && !is_link($rbFlag)) {
            $ptr = $readPointer();
            if ($ptr === null || !is_string($ptr['previous'] ?? null) || $ptr['previous'] === '') {
                throw new RuntimeException('No previous publisher runtime recorded in runtime-active.json');
            }
            $prevSlot = $ptr['previous'];
            if (preg_match('/^[A-Za-z0-9_.-]+$/D', $prevSlot) !== 1) throw new RuntimeException('Invalid previous runtime slot');
            $prevDir = $runtimeRoot.'/'.$prevSlot;
            $prevInfo = rumoBootstrapVerifyLib($prevDir, true, is_string($ptr['previous_manifest_sha256'] ?? null) ? $ptr['previous_manifest_sha256'] : null);
            $newPtr = [
                'schema' => 'rumo-active-runtime-v1',
                'active' => $prevSlot,
                'manifest_sha256' => $prevInfo['manifest_sha256'],
                'previous' => $ptr['active'],
                'previous_manifest_sha256' => $ptr['manifest_sha256'] ?? null,
                'switched_at' => gmdate('c'),
                'action' => 'rollback',
            ];
            rumoBootstrapAtomicWrite($pointerPath, json_encode($newPtr, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
            @unlink($rbFlag);
            $controlEvents[] = ['action'=>'publisher_rollback','active'=>$prevSlot,'version'=>$prevInfo['version']];
        }

        // 2. No-SSH Cron-integrated Publisher self-update via pending-update/update.json + pending-update/lib/
        $updSpecFile = $pendingDir.'/update.json';
        if (is_file($updSpecFile) && !is_link($updSpecFile)) {
            $spec = json_decode((string)file_get_contents($updSpecFile), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($spec) || ($spec['schema'] ?? null) !== 'rumo-publisher-update-v1') {
                throw new RuntimeException('Invalid pending-update/update.json schema');
            }
            $slot = $spec['slot'] ?? null;
            $expectedSha = $spec['manifest_sha256'] ?? null;
            if (!is_string($slot) || preg_match('/^[A-Za-z0-9_.-]+$/D', $slot) !== 1) {
                throw new RuntimeException('Invalid pending update slot name');
            }
            if (!is_string($expectedSha) || preg_match('/^[a-f0-9]{64}$/D', $expectedSha) !== 1) {
                throw new RuntimeException('Invalid pending update manifest_sha256');
            }
            $srcLib = $pendingDir.'/lib';
            $srcInfo = rumoBootstrapVerifyLib($srcLib, true, $expectedSha);
            if (!is_dir($runtimeRoot)) {
                if (!mkdir($runtimeRoot, 0700, true)) throw new RuntimeException('Cannot create runtime directory');
            }
            $destSlotDir = $runtimeRoot.'/'.$slot;
            if (!is_dir($destSlotDir)) {
                $tmpSlot = $runtimeRoot.'/.staging-'.$slot.'-'.bin2hex(random_bytes(4));
                if (!mkdir($tmpSlot, 0700, true)) throw new RuntimeException('Cannot create runtime staging slot');
                foreach (scandir($srcLib) ?: [] as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    if (!copy($srcLib.'/'.$entry, $tmpSlot.'/'.$entry)) throw new RuntimeException('Failed staging '.$entry);
                    if (PHP_OS_FAMILY !== 'Windows') @chmod($tmpSlot.'/'.$entry, 0600);
                }
                rumoBootstrapVerifyLib($tmpSlot, true, $expectedSha);
                if (!@rename($tmpSlot, $destSlotDir)) throw new RuntimeException('Cannot commit staged runtime slot');
            }
            $destInfo = rumoBootstrapVerifyLib($destSlotDir, true, $expectedSha);
            $curPtr = $readPointer();
            $newPtr = [
                'schema' => 'rumo-active-runtime-v1',
                'active' => $slot,
                'manifest_sha256' => $destInfo['manifest_sha256'],
                'previous' => $curPtr['active'] ?? null,
                'previous_manifest_sha256' => $curPtr['manifest_sha256'] ?? null,
                'switched_at' => gmdate('c'),
                'action' => 'self_update',
            ];
            rumoBootstrapAtomicWrite($pointerPath, json_encode($newPtr, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
            @rename($updSpecFile, $pendingDir.'/applied-'.date('YmdHis').'-update.json');
            $controlEvents[] = ['action'=>'publisher_self_update','active'=>$slot,'version'=>$destInfo['version']];
        }

        // 3. No-SSH interrupted deploy recovery trigger via pending-update/recover.request
        $recFlag = $pendingDir.'/recover.request';
        if (is_file($recFlag) && !is_link($recFlag)) {
            $recoverRequested = true;
        }
    }

    $ptr = $readPointer();
    if ($ptr !== null) {
        $slot = $ptr['active'];
        if (preg_match('/^[A-Za-z0-9_.-]+$/D', $slot) !== 1) throw new RuntimeException('Invalid active runtime slot');
        $activeDir = $runtimeRoot.'/'.$slot;
        $rt = rumoBootstrapVerifyLib($activeDir, true, is_string($ptr['manifest_sha256'] ?? null) ? $ptr['manifest_sha256'] : null);
        $rt['pointer_slot'] = $slot;
        return [$activeDir, $rt, $controlEvents, $recoverRequested];
    }

    // Standard single-directory layout: require version.json before loading any mutable library code.
    $rt = rumoBootstrapVerifyLib($defaultLib, true, null);
    return [$defaultLib, $rt, $controlEvents, $recoverRequested];
}

// Keep this exact assignment line intact for bootstrap template compatibility.
$publisherLibrary = dirname(__DIR__).'/lib';
$publisherRoot = dirname(__DIR__);
$opts = getopt('',['config:','mode:','run:','artifact:','stage:','recover','discover','retire-testecodex','ui001-bootstrap','identity']);
$bootLock = null;
$runtimeIdentity = null;
$controlEvents = [];
$recoverFlagFile = null;

try {
    // Acquire account_mutex BEFORE loading any mutable library code when --config is supplied.
    if (isset($opts['config']) && is_string($opts['config']) && is_file($opts['config']) && !is_link($opts['config'])) {
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($opts['config']) & 0077) !== 0) {
            throw new RuntimeException('Config must be 0600');
        }
        $bootCfg = json_decode((string)file_get_contents($opts['config']), true, 64, JSON_THROW_ON_ERROR);
        if (is_array($bootCfg) && is_string($bootCfg['account_mutex'] ?? null) && $bootCfg['account_mutex'] !== '' && !is_link($bootCfg['account_mutex'])) {
            $bootLock = fopen($bootCfg['account_mutex'], 'c+b');
            if ($bootLock === false || !flock($bootLock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Concurrent publisher refused');
            }
        }
    }

    [$activeLibrary, $runtimeIdentity, $controlEvents, $recoverRequested] =
        rumoBootstrapResolveAndControl($publisherRoot, $publisherLibrary, is_resource($bootLock));
    if ($recoverRequested) {
        $recoverFlagFile = $publisherRoot.'/pending-update/recover.request';
    }

    if (isset($opts['identity'])) {
        echo json_encode([
            'ok' => true,
            'runtime' => $runtimeIdentity,
            'control_events' => $controlEvents,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
        if (is_resource($bootLock)) { flock($bootLock, LOCK_UN); fclose($bootLock); }
        exit(0);
    }

    // ONLY NOW, after 100% of library files passed SHA-256 verification, load mutable PHP libraries.
    require_once $activeLibrary.'/DrainPublisher.php';
    require_once $activeLibrary.'/GitHub.php';
    require_once $activeLibrary.'/RuntimeIdentity.php';
} catch (Throwable $e) {
    if (is_resource($bootLock)) { flock($bootLock, LOCK_UN); fclose($bootLock); }
    rumoBootstrapFail($e->getMessage(), $runtimeIdentity);
}

use Rumo\Publisher\DrainPublisher;
use Rumo\Publisher\Publisher;
use Rumo\Publisher\GitHub;
use Rumo\Publisher\MainAutomation;
use function Rumo\Publisher\need;
use function Rumo\Publisher\json;

try {
    need(isset($opts['config'],$opts['mode']),'Usage: --config=private.json --mode=verify-only|stage|apply --run=ID --artifact=ID OR --stage=PATH OR --recover');
    $mode=$opts['mode']; need(in_array($mode,['verify-only','stage','apply'],true),'Unknown mode');
    $isRecover = isset($opts['recover']) || $recoverFlagFile !== null;
    $retireTesteCodex=isset($opts['retire-testecodex']);
    if($retireTesteCodex)need($mode==='apply'&&$isRecover&&
        !isset($opts['discover'])&&!isset($opts['stage'])&&
        !isset($opts['run'])&&!isset($opts['artifact']),'Retirement is only available for recovery');
    $configPath=$opts['config']; need(is_file($configPath) && !is_link($configPath),'Private config required');
    if(PHP_OS_FAMILY!=='Windows')need((fileperms($configPath)&0077)===0,'Config must be 0600');
    $c=json(file_get_contents($configPath)); MainAutomation::configuration($c);
    $exception=isset($opts['ui001-bootstrap']);
    if(isset($c['main_automation'])){
        need(!$exception || ($mode==='apply' && ($c['main_automation']['allow_ui001_bootstrap']??false)===true &&
            (isset($opts['discover']) xor $isRecover) && !$retireTesteCodex &&
            !isset($opts['run']) && !isset($opts['artifact']) && !isset($opts['stage'])), 'Invalid UI-001 exception invocation');
        $c['main_automation']['ui001_bootstrap_request']=$exception;
    }else need(!$exception, 'UI-001 exception requires main automation');
    if(isset($c['main_automation']) && $mode==='apply' && !$isRecover){
        need(isset($opts['discover']) && !isset($opts['stage']) && !isset($opts['run']) && !isset($opts['artifact']),
            'Automatic main apply requires authenticated discovery');
    }
    $publisher=!isset($c['update_policy'])||($c['request_gate']['coverage_approved']??false)===true?new DrainPublisher($c):new Publisher($c);
    if (is_resource($bootLock)) {
        $publisher->adoptLock($bootLock);
        $bootLock = null;
    }
    $publisher->acquire();
    if($mode==='apply') {
        need(($c['apply_enabled']??false)===true,'apply disabled by default');
        $channel=$c['release_channel']??null;
        if($channel!==null)need($publisher instanceof DrainPublisher && isset($c['update_policy']),
            'Production channel requires a private drain policy');
        if($retireTesteCodex) {
            need($publisher instanceof DrainPublisher,'Retirement requires shared drain');
            $result=$publisher->recover(true);
        } elseif($isRecover){
            $result=$publisher->recover();
            if(isset($c['main_automation'])){
                need(in_array($result['state']??null,['validated','recovered'],true),'Unstable recovery result');
                $publisher->verifyPublicAvailability();
            }
            if ($recoverFlagFile !== null && is_file($recoverFlagFile)) {
                @unlink($recoverFlagFile);
            }
        }
        elseif(isset($opts['discover'])) {
            $source=new GitHub($c);
            if(isset($c['main_automation'])) {
                $result=$source->automatic($publisher,$exception?MainAutomation::UI001:null);
            } else {
                $ids=$source->discover();
                $input=$source->fetch($ids[0],$ids[1],$c['private'].'/downloads');
                $staged=$publisher->stage(...$input);
                $source->assertMainTip($staged['commit']);
                $result=$publisher->apply($staged['stage']);
            }
        } else {
            $stage=$opts['stage']??'';
            if($channel==='main'){
                $path=realpath($stage);
                need($path!==false && str_starts_with(str_replace('\\','/',$path),
                    str_replace('\\','/',realpath($c['private'])).'/stage-'),'Stage outside private root');
                $ready=json(file_get_contents($path.'/ready.json'));
                (new GitHub($c))->assertMainTip($ready['run']['head_sha']);
            }
            $result=$publisher->apply($stage);
        }
    } else {
        $source=new GitHub($c);
        $ids=isset($opts['discover'])?$source->discover():[(int)($opts['run']??0),(int)($opts['artifact']??0)];
        $input=$source->fetch($ids[0],$ids[1],$c['private'].'/downloads');
        $result=$mode==='stage'?$publisher->stage(...$input):$publisher->verify(...$input);
        $result=['mode'=>$mode,'commit'=>$result['commit'],'artifact_id'=>$result['artifact_id'],
            'package_sha256'=>$result['package_sha256'],'files'=>count($result['inventory']['files']),
            'stage'=>$result['stage']??null,'operational_writes'=>false];
    }
    if(is_array($result)){
        $result['publisher_runtime']=$runtimeIdentity;
        if($controlEvents!==[])$result['control_events']=$controlEvents;
    }
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {
    if (is_resource($bootLock)) { flock($bootLock, LOCK_UN); fclose($bootLock); }
    rumoBootstrapFail($e->getMessage(), $runtimeIdentity);
}
