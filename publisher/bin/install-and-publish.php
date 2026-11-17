<?php
declare(strict_types=1);

/* One-time receiver bootstrap. The existing controlled-pull entry is the only publication entry. */
use Rumo\Publisher\MainAutomation;
use function Rumo\Publisher\{atomic,digest,json,need,privateDir,record};

if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
umask(0077);
$mutex=null;
try {
    $opts=getopt('',['config:','manifest-sha256:','rollback']);
    $hash=$opts['manifest-sha256']??'';
    $root=dirname(__DIR__,2);
    $manifestPath=$root.'/bootstrap-manifest.json';
    if(!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D',$hash)!==1 ||
        is_link($manifestPath) || !is_file($manifestPath) || !hash_equals($hash,hash_file('sha256',$manifestPath)))
        throw new RuntimeException('Verified bootstrap manifest SHA-256 required');

    $manifest=json_decode(file_get_contents($manifestPath),true,64,JSON_THROW_ON_ERROR);
    $files=$manifest['files']??[];
    if(($manifest['schema']??null)!=='rumo-receiver-bootstrap-v1' || !isset($files['publisher/lib/Core.php']))
        throw new RuntimeException('Unrecognized bootstrap manifest');
    foreach($files as $file=>$digest){
        if(preg_match('#^publisher/(?:lib/[A-Za-z0-9]+\.php|bin/(?:controlled-pull|install-and-publish|mysql-backup-hook)\.php)$#D',$file)!==1 ||
            !is_file($root.'/'.$file) || is_link($root.'/'.$file) || !hash_equals($digest,hash_file('sha256',$root.'/'.$file)))
            throw new RuntimeException('Bootstrap payload integrity failure');
    }
    require_once $root.'/publisher/lib/Core.php';
    require_once $root.'/publisher/lib/MainAutomation.php';

    $config=$opts['config']??'';
    need(is_string($config) && is_file($config) && !is_link($config),'Explicit private receiver config required');
    if(PHP_OS_FAMILY!=='Windows')need((fileperms($config)&0077)===0,'Config must be 0600');
    $configHash=digest($config);
    $c=json(file_get_contents($config));
    MainAutomation::configuration($c);
    need(isset($c['main_automation'],$c['account_mutex'],$c['private'],$c['control']), 'Main receiver configuration required');

    $entry=dirname($config).'/publisher/bin/controlled-pull.php';
    need(is_file($entry) && !is_link($entry), 'Existing physical controlled-pull entry required');
    $active=realpath(dirname($config).'/publisher');
    $staged=realpath($root.'/publisher');
    $private=realpath($c['private']);
    need($active!==false && $staged!==false && $private!==false && !is_link($c['private']) &&
        $active!==$staged && !str_starts_with($staged,$active.DIRECTORY_SEPARATOR),'Extract bootstrap outside the active publisher');
    need(!is_link($c['account_mutex']),'Account mutex cannot be a symlink');
    $mutex=fopen($c['account_mutex'],'c+b');
    need(is_resource($mutex) && flock($mutex,LOCK_EX|LOCK_NB),'Concurrent publisher refused');
    need(fstat($mutex)['nlink']===1 && digest($config)===$configHash,'Account mutex or configuration changed');

    MainAutomation::stable($c);
    $receiverState=[];
    foreach(['installed.json','journal.json','drain-session.json'] as $name){
        $file=$private.'/'.$name;
        need(!is_link($file),'Linked receiver state');
        if(is_file($file)){
            if($name!=='installed.json')need(in_array(json(file_get_contents($file))['state']??null,['validated','recovered'],true),
                'Pending receiver session requires official recovery');
            $receiverState[$name]=digest($file);
        }
    }

    $generation=$private.'/receiver-bootstrap-'.$hash;
    $journal=$generation.'/bootstrap.json';
    $original=$generation.'/controlled-pull.original.php';
    $entrySource=file_get_contents($root.'/publisher/bin/controlled-pull.php');
    need($entrySource!==false && isset($files['publisher/bin/controlled-pull.php']),'Missing receiver entry payload');
    $assignment='$publisherLibrary = dirname(__DIR__).\'/lib\';';
    need(substr_count($entrySource,$assignment)===1,'Invalid receiver entry template');
    $deployed=str_replace($assignment,'$publisherLibrary = '.var_export(str_replace('\\','/',$generation.'/lib'),true).';',$entrySource);
    $postHash=hash('sha256',$deployed);
    $currentHash=digest($entry);

    if(is_file($journal)){
        $j=json(file_get_contents($journal));
        need(($j['post_sha256']??null)===$postHash && digest($original)===$j['before_sha256'],
            'Bootstrap journal or original entry drift');
    }else{
        need(!isset($opts['rollback']) && $currentHash!==$postHash,'Bootstrap recovery record required');
        privateDir($generation);
        $bytes=file_get_contents($entry);
        need(is_string($bytes) && hash('sha256',$bytes)===$currentHash,'Cannot verify original entry');
        atomic($original,$bytes);
        $j=['state'=>'prepared','before_sha256'=>$currentHash,'post_sha256'=>$postHash,
            'original_mode'=>fileperms($entry)&0777,'receiver_state'=>$receiverState];
        record($journal,$j);
    }

    if(isset($opts['rollback'])){
        need($receiverState===$j['receiver_state'],'Receiver state changed; reverse via official recovery');
        need(in_array($currentHash,[$postHash,$j['before_sha256']],true),'Current entry has unexpected identity');
        atomic($entry,file_get_contents($original));
        if(PHP_OS_FAMILY!=='Windows')need(chmod($entry,$j['original_mode']),'Cannot restore original entry permissions');
        need(digest($entry)===$j['before_sha256'],'Rollback entry hash failed');
        $j['state']='rolled_back';
    }else{
        need(in_array($currentHash,[$postHash,$j['before_sha256']],true),'Current entry has unexpected identity');
        privateDir($generation.'/lib');
        foreach($files as $file=>$expected){
            if(!str_starts_with($file,'publisher/lib/'))continue;
            $dest=$generation.'/lib/'.basename($file);
            if($currentHash!==$postHash)atomic($dest,file_get_contents($root.'/'.$file));
            need(digest($dest)===$expected,'Private receiver generation integrity failed');
        }
        $hook='publisher/bin/mysql-backup-hook.php';
        need(isset($files[$hook]),'Missing versioned backup hook payload');
        privateDir($generation.'/bin');
        if($currentHash!==$postHash)atomic($generation.'/bin/mysql-backup-hook.php',file_get_contents($root.'/'.$hook));
        need(digest($generation.'/bin/mysql-backup-hook.php')===$files[$hook],'Private backup hook integrity failed');
        if($currentHash!==$postHash)atomic($entry,$deployed);
        need(digest($entry)===$postHash,'Installed entry hash failed');
        $j['state']='installed-held';
    }
    record($journal,$j);
    echo json_encode(['state'=>$j['state'],'entry_sha256'=>digest($entry),'manifest_sha256'=>$hash,'generation'=>$generation],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    fwrite(STDERR,'BOOTSTRAP BLOCKED: '.$e->getMessage().PHP_EOL);
    exit(1);
}finally{
    if(is_resource($mutex))fclose($mutex);
}
