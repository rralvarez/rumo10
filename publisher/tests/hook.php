<?php
declare(strict_types=1);
// TEST FIXTURE ONLY. Real SQLite consistent backup + isolated restore verification.
// NOT a production MySQL provider and NEVER installed on UOL.
$r=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$root=__DIR__.'/sandbox-hook-state.json';
$c=json_decode(file_get_contents($root),true,512,JSON_THROW_ON_ERROR);
$op=$argv[1];
if(($c['hang']??'')===$op)sleep(5);
if (($c['fail']??'')===$op) { fwrite(STDERR,'injected fixture failure'); exit(2); }
if($op==='preflight') {
    $out=['ok'=>true,'legacy_publishers_fenced'=>true,'source_unchanged'=>true];
} elseif($op==='maintenance') {
    if(!is_file($c['control'].'/deployment.lock'))exit(3);
    // Real local HTTP GET and POST probes must both be blocked by the maintenance fixture.
    $port=trim(file_get_contents(__DIR__.'/http-port.txt'));
    foreach(['GET','POST'] as $method) {
        $ctx=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'timeout'=>2]]);
        $body=file_get_contents('http://127.0.0.1:'.$port.'/',false,$ctx);
        if($body===false || !str_contains($http_response_header[0]??'','503'))exit(11);
    }
    $db=new SQLite3($c['db']); $db->busyTimeout(100);
    $db->exec('BEGIN IMMEDIATE'); $db->exec('ROLLBACK'); // proves no inflight SQLite write transaction
    $out=['ok'=>true,'http_status'=>503,'writes_blocked'=>true,'inflight_writers'=>0];
} elseif($op==='backup') {
    $db=new SQLite3($c['db'],SQLITE3_OPEN_READONLY);$dest=$r['backup'].'/synthetic.sqlite';
    $b=new SQLite3($dest);
    if(!$db->backup($b))exit(4);
    if($b->querySingle('PRAGMA integrity_check')!=='ok')exit(5);
    $expected=$db->querySingle("SELECT state FROM careers WHERE id=1");
    $actual=$b->querySingle("SELECT state FROM careers WHERE id=1");
    if($actual!==$expected)exit(6);
    $b->close();$db->close();
    $rehearsal=$r['backup'].'/restore-rehearsal.sqlite';
    if(!copy($dest,$rehearsal))exit(7);
    $restore=new SQLite3($rehearsal,SQLITE3_OPEN_READONLY);
    if($restore->querySingle('PRAGMA integrity_check')!=='ok' ||
       $restore->querySingle("SELECT state FROM careers WHERE id=1")!==$expected)exit(8);
    $restore->close();
    $out=['ok'=>true,'attempt'=>$r['attempt'],'consistent'=>true,'restore_tested'=>true,
       'engine'=>'sqlite-test-only','files'=>[$dest=>hash_file('sha256',$dest),$rehearsal=>hash_file('sha256',$rehearsal)]];
} elseif($op==='smoke') {
    $db=new SQLite3($c['db'],SQLITE3_OPEN_READONLY);
    $save=json_decode($db->querySingle("SELECT state FROM careers WHERE id=1"),true);
    if($save['player_development']['version']!=='development-v3-isolated' || $save['xp']!==12345)exit(9);
    $out=['ok'=>true,'save_v3_preserved'=>true];
} else exit(10);
echo json_encode($out,JSON_THROW_ON_ERROR);
