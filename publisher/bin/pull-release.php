<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__).'/lib/Publisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
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
    $c=json(file_get_contents($configPath)); $publisher=new Publisher($c); $publisher->acquire();
    if($mode==='apply') {
        need(($c['apply_enabled']??false)===true,'apply disabled by default');
        if(isset($opts['recover'])) $result=$publisher->recover();
        elseif(isset($opts['discover'])) {
            $source=new GitHub($c); $ids=$source->discover();
            $input=$source->fetch($ids[0],$ids[1],$c['private'].'/downloads');
            $staged=$publisher->stage(...$input); $result=$publisher->apply($staged['stage']);
        } else $result=$publisher->apply($opts['stage']??'');
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
