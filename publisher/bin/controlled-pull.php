<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
use Rumo\Publisher\DrainPublisher;
use Rumo\Publisher\Publisher;
use Rumo\Publisher\GitHub;
use function Rumo\Publisher\need;
use function Rumo\Publisher\json;
try {
    $opts=getopt('',['config:','mode:','run:','artifact:','stage:','recover','discover']);
    need(isset($opts['config'],$opts['mode']),'Usage: --config=private.json --mode=verify-only|stage|apply --run=ID --artifact=ID OR --stage=PATH OR --recover');
    $mode=$opts['mode']; need(in_array($mode,['verify-only','stage','apply'],true),'Unknown mode');
    $configPath=$opts['config']; need(is_file($configPath) && !is_link($configPath),'Private config required');
    if(PHP_OS_FAMILY!=='Windows')need((fileperms($configPath)&0077)===0,'Config must be 0600');
    $c=json(file_get_contents($configPath)); $publisher=!isset($c['update_policy'])||($c['request_gate']['coverage_approved']??false)===true?new DrainPublisher($c):new Publisher($c); $publisher->acquire();
    if($mode==='apply') {
        need(($c['apply_enabled']??false)===true,'apply disabled by default');
        $channel=$c['release_channel']??null;
        if($channel!==null)need($publisher instanceof DrainPublisher && isset($c['update_policy']),
            'Production channel requires a private drain policy');
        if(isset($opts['recover'])) $result=$publisher->recover();
        elseif(isset($opts['discover'])) {
            $source=new GitHub($c); $ids=$source->discover();
            $input=$source->fetch($ids[0],$ids[1],$c['private'].'/downloads');
            $staged=$publisher->stage(...$input);
            $source->assertMainTip($staged['commit']);
            $result=$publisher->apply($staged['stage']);
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
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {
    // No curl headers, signed URLs, token or environment values are logged.
    fwrite(STDERR,'BLOCKED: '.$e->getMessage().PHP_EOL); exit(1);
}
