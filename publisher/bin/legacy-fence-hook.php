<?php
use Rumo\Publisher\{LegacyFence,FenceReader,FenceCheckFailed};
use function Rumo\Publisher\{need,json};
function fenceLibrary(string $root): string {
    $pointer=$root.'/runtime-active.json';
    if (!file_exists($pointer)&&!is_link($pointer)) return $root.'/lib';
    $entry=$root.'/bin/controlled-pull.php';
    $runtimeRoot=realpath($root.'/runtime');
    if (!is_file($entry)||is_link($entry)||$runtimeRoot===false||is_link($root.'/runtime'))
        throw new RuntimeException('Fence runtime unavailable');

    // No configuration or apply flags: use the receiver's existing fail-closed
    // manifest verifier without opening admission or asking for the parent's lock.
    $process=proc_open([PHP_BINARY,$entry,'--identity'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','ab']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Fence runtime identity unavailable');
    fclose($pipes[0]);
    $raw=stream_get_contents($pipes[1],16384);
    fclose($pipes[1]);
    $exit=proc_close($process);
    $receipt=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    $claimed=$receipt['runtime']['lib_path']??null;
    $active=is_string($claimed)?realpath($claimed):false;
    if ($exit!==0||($receipt['ok']??null)!==true||$active===false||dirname($active)!==$runtimeRoot||
        ($receipt['runtime']['pointer_slot']??null)!==basename($active))
        throw new RuntimeException('Fence runtime identity refused');
    return $active;
}

try {
    if(PHP_SAPI!=='cli'||($argv[1]??'')!=='fence'||($argv[2]??'')!=='--profile')
        throw new RuntimeException('Private fence hook only');
    require_once fenceLibrary(dirname(__DIR__)).'/LegacyFence.php';
    $path=$argv[3]??'';
    need(is_file($path)&&!is_link($path),'Private fence profile required');
    if (PHP_OS_FAMILY!=='Windows') need((fileperms($path)&0077)===0,'Profile must be 0600');
    $profile=json(file_get_contents($path));
    $deadline=microtime(true)+LegacyFence::HOOK_BUDGET_SECONDS;
    $readers=[];
    foreach ($profile['repositories'] as $repo=>$config)
        $readers[$repo]=new FenceReader(['repository'=>$repo,'token_file'=>$config['token_file']],$deadline);
    $receipt=LegacyFence::verifyWithRetry($profile,fn($repo,$request)=>$readers[$repo]->get($request),$deadline);
    echo json_encode($receipt,JSON_THROW_ON_ERROR);
} catch (FenceCheckFailed $e) {
    fwrite(STDERR,($e->retryable?'FENCE_DEFERRED ':'FENCE_BLOCKED ').$e->getMessage().PHP_EOL);
    exit($e->retryable?75:1);
} catch (Throwable) {
    fwrite(STDERR,'FENCE_BLOCKED configuration_or_internal_error'.PHP_EOL);
    exit(1);
}
