<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
$publisherLibrary = dirname(__DIR__).'/lib';
require_once $publisherLibrary.'/DrainPublisher.php';
require_once $publisherLibrary.'/GitHub.php';
require_once $publisherLibrary.'/RuntimeIdentity.php';
use Rumo\Publisher\DrainPublisher;
use Rumo\Publisher\Publisher;
use Rumo\Publisher\GitHub;
use Rumo\Publisher\MainAutomation;
use Rumo\Publisher\RuntimeIdentity;
use function Rumo\Publisher\need;
use function Rumo\Publisher\json;
$runtimeIdentity = null;
try {
    $runtimeIdentity = RuntimeIdentity::inspect($publisherLibrary);
    $opts=getopt('',['config:','mode:','run:','artifact:','stage:','recover','discover','retire-testecodex','ui001-bootstrap','identity']);
    if(isset($opts['identity'])){
        echo json_encode(['ok'=>true,'runtime'=>$runtimeIdentity],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }
    need(isset($opts['config'],$opts['mode']),'Usage: --config=private.json --mode=verify-only|stage|apply --run=ID --artifact=ID OR --stage=PATH OR --recover');
    $mode=$opts['mode']; need(in_array($mode,['verify-only','stage','apply'],true),'Unknown mode');
    $retireTesteCodex=isset($opts['retire-testecodex']);
    if($retireTesteCodex)need($mode==='apply'&&isset($opts['recover'])&&
        !isset($opts['discover'])&&!isset($opts['stage'])&&
        !isset($opts['run'])&&!isset($opts['artifact']),'Retirement is only available for recovery');
    $configPath=$opts['config']; need(is_file($configPath) && !is_link($configPath),'Private config required');
    if(PHP_OS_FAMILY!=='Windows')need((fileperms($configPath)&0077)===0,'Config must be 0600');
    $c=json(file_get_contents($configPath)); MainAutomation::configuration($c);
    $exception=isset($opts['ui001-bootstrap']);
    if(isset($c['main_automation'])){
        need(!$exception || ($mode==='apply' && ($c['main_automation']['allow_ui001_bootstrap']??false)===true &&
            (isset($opts['discover']) xor isset($opts['recover'])) && !$retireTesteCodex &&
            !isset($opts['run']) && !isset($opts['artifact']) && !isset($opts['stage'])), 'Invalid UI-001 exception invocation');
        $c['main_automation']['ui001_bootstrap_request']=$exception;
    }else need(!$exception, 'UI-001 exception requires main automation');
    if(isset($c['main_automation']) && $mode==='apply' && !isset($opts['recover'])){
        need(isset($opts['discover']) && !isset($opts['stage']) && !isset($opts['run']) && !isset($opts['artifact']),
            'Automatic main apply requires authenticated discovery');
    }
    $publisher=!isset($c['update_policy'])||($c['request_gate']['coverage_approved']??false)===true?new DrainPublisher($c):new Publisher($c); $publisher->acquire();
    if($mode==='apply') {
        need(($c['apply_enabled']??false)===true,'apply disabled by default');
        $channel=$c['release_channel']??null;
        if($channel!==null)need($publisher instanceof DrainPublisher && isset($c['update_policy']),
            'Production channel requires a private drain policy');
        if($retireTesteCodex) {
            need($publisher instanceof DrainPublisher,'Retirement requires shared drain');
            $result=$publisher->recover(true);
        } elseif(isset($opts['recover'])){
            $result=$publisher->recover();
            if(isset($c['main_automation'])){
                need(in_array($result['state']??null,['validated','recovered'],true),'Unstable recovery result');
                $publisher->verifyPublicAvailability();
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
    }
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {
    // No curl headers, signed URLs, token or environment values are logged.
    $rtTag = is_array($runtimeIdentity)
        ? ' [publisher=' . $runtimeIdentity['version'] . ' lib=' . $runtimeIdentity['lib_path'] . ']'
        : '';
    fwrite(STDERR,'BLOCKED: '.$e->getMessage().$rtTag.PHP_EOL); exit(1);
}
