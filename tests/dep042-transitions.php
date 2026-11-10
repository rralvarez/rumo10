<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/publisher/lib/DrainPublisher.php';

use Rumo\Publisher\{DrainPublisher, Package, Publisher, RequestDrain};
use function Rumo\Publisher\{digest,json,need,protectedPath,record};

// Hooks run in their own PHP process, so smoke loads the installed runtime.
if (in_array($argv[1]??'', ['fence','preflight','maintenance','backup','smoke'], true)) {
    $op=$argv[1];
    $profile=json(file_get_contents($argv[2]));
    $request=json(stream_get_contents(STDIN));
    $result=['ok'=>true];
    if ($op!=='fence') {
        need((new RequestDrain($profile['gate']))->closed(),'Writer admission must be closed');
    }
    if ($op==='smoke') {
        require $profile['current'].'/app/bootstrap.php';
        $db=Database::connect();
        $state=(new Repository($db))->load(1);
        $expected=(int)file_get_contents($profile['expected']);
        need(($state['version']??null)===$expected,'Installed Repository cannot read the expected snapshot');
        need($state===json($db->query('SELECT estado_json FROM rumo10_universos WHERE carreira_id=1')->fetchColumn()),'Snapshot read diverged');
        file_put_contents($profile['proof'],json_encode(['version'=>$expected,'f01'=>is_file($profile['current'].'/app/F01Telemetry.php')]).PHP_EOL,FILE_APPEND);
    } elseif ($op==='backup') {
        // This fixture only tests the installer handoff, not backup or restore of a real database.
        $file=$request['backup'].'/synthetic-backup-proof';
        file_put_contents($file,'disposable fixture');
        $result+=['consistent'=>true,'restore_tested'=>true,'attempt'=>$request['attempt'],'files'=>[$file=>digest($file)]];
    } elseif ($op==='maintenance') {
        // Match the real HTTP probe contract. DrainPublisher supplies proof from the held lease.
        $result+=['http_status'=>503,'writes_blocked'=>false,'inflight_writers'=>-1];
    } else {
        $result+=['legacy_publishers_fenced'=>true,'source_unchanged'=>true];
    }
    echo json_encode($result,JSON_THROW_ON_ERROR);
    exit;
}

function check(bool $condition,string $description):void {
    need($condition,$description);
    echo "[PASS] {$description}\n";
}
function rejected(callable $call,string $message):void {
    try {$call();} catch (Throwable $e) {
        check(str_contains($e->getMessage(),$message),$message.' ('.$e->getMessage().')');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
function stageBuild(Publisher $publisher,string $dir):array {
    $e=json(file_get_contents($dir.'/candidate.json'));
    $sha=$e['commit'];
    $id=$e['run_id'];
    $run=['id'=>$id,'repository'=>['full_name'=>'rralvarez/rumo10'],'workflow_id'=>1,
        'head_branch'=>'codex/production-consolidated-v062','event'=>'push','status'=>'completed',
        'conclusion'=>'success','head_sha'=>$sha,'run_number'=>$e['run_number'],'run_attempt'=>1];
    $artifact=['id'=>$id,'name'=>'release-rumo10-'.$sha,'expired'=>false,
        'digest'=>'sha256:'.str_repeat('a',64),'workflow_run'=>['id'=>$id,'head_sha'=>$sha]];
    $jobs=[['name'=>'compatibility','status'=>'completed','conclusion'=>'success','head_sha'=>$sha,
        'run_id'=>$id,'_requested_attempt'=>1,'steps'=>[['conclusion'=>'success']]]];
    return $publisher->stage($dir.'/release.tar.gz',$run,$artifact,$jobs,$e);
}

[$script,$root,$v8Package,$f01Dir,$recoveryDir]=$argv;
$root=str_replace('\\','/',realpath($root)?:'');
need($root!=='' && str_starts_with(basename($root),'r10-dep042-'),'Dedicated disposable directory required');
$port=(int)getenv('DEP042_MYSQL_PORT');
need($port>=33500 && $port<=33600,'Dedicated loopback MySQL port required');
$server=new PDO("mysql:host=127.0.0.1;port={$port}",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE r10_dep042_disposable');
$db=new PDO("mysql:host=127.0.0.1;port={$port};dbname=r10_dep042_disposable",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("
 CREATE TABLE rumo10_carreiras (id BIGINT UNSIGNED PRIMARY KEY, conta_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB;
 CREATE TABLE rumo10_universos (carreira_id BIGINT UNSIGNED PRIMARY KEY, estado_json JSON NOT NULL) ENGINE=InnoDB;
 CREATE TABLE rumo10_historico_encerrado (carreira_id BIGINT UNSIGNED, conta_id BIGINT UNSIGNED, dados_json JSON) ENGINE=InnoDB;
 CREATE TABLE rumo10_migrations (version VARCHAR(50) PRIMARY KEY) ENGINE=InnoDB;
 CREATE TABLE rumo10_balanceamentos (codigo VARCHAR(64), estado VARCHAR(10)) ENGINE=InnoDB;
 CREATE TABLE rumo10_formula_versoes (id INT PRIMARY KEY, formula_id VARCHAR(64)) ENGINE=InnoDB;
 CREATE TABLE rumo10_formula_parametros (versao_id INT) ENGINE=InnoDB;
 INSERT INTO rumo10_carreiras VALUES (1,1);
 INSERT INTO rumo10_universos VALUES (1,'{\"id\":1,\"version\":1,\"stage\":\"handicap\"}');
 INSERT INTO rumo10_migrations VALUES ('006'),('006-seed'),('007'),('008');
 INSERT INTO rumo10_balanceamentos VALUES ('bal-v062-recalibracao-ofensiva','ativo');
 INSERT INTO rumo10_formula_versoes VALUES (1,'match.penalties.parameters');
 INSERT INTO rumo10_formula_parametros VALUES (1);
");

foreach (['current','public','private','control'] as $dir) mkdir($root.'/'.$dir,0700);
$policy=json(file_get_contents(dirname(__DIR__).'/publisher/config/update-policy.json'));
$f01=json(file_get_contents($f01Dir.'/candidate.json'));
$v8=json(file_get_contents($recoveryDir.'/candidate.json'));
need($v8['commit']!==$v8['functional_commit'],'Recovery build must have its own authenticated commit');
foreach([$f01,$v8] as $e) $policy['authorized_releases'][$e['functional_commit']]['authorized_build_commits']=[$e['commit']];
$current=$root.'/current';
$inventory=Package::inspect($v8Package,'rumo10',$current)['files'];
$inventory=array_filter($inventory,fn($p)=>!protectedPath($p),ARRAY_FILTER_USE_KEY);
record($current.'/release.json',['project'=>'rumo10','files'=>$inventory]);
$publicInventory=[];
foreach($inventory as $p=>$hash) {
    if(!str_starts_with($p,'public/'))continue;
    $relative=substr($p,7);
    $path=$root.'/public/'.$relative;
    if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
    copy($current.'/'.$p,$path);
    if(!in_array($relative,['index.php','.htaccess'],true))$publicInventory[$relative]=$hash;
}
record($root.'/private/installed.json',['commit'=>$v8['functional_commit'],'artifact_id'=>1,'run_number'=>1,
    'package_sha256'=>digest($v8Package),'manifest_sha256'=>digest($current.'/release.json'),'public_inventory'=>$publicInventory]);
file_put_contents($current.'/.env',"DB_HOST=127.0.0.1\nDB_PORT={$port}\nDB_DATABASE=r10_dep042_disposable\nDB_USERNAME=root\nDB_PASSWORD=\n");
file_put_contents($root.'/expected','1');
record($root.'/profile.json',['current'=>$current,'gate'=>$root.'/gate','expected'=>$root.'/expected','proof'=>$root.'/smoke.log']);
record($root.'/migration.json',['project'=>'rumo10','current'=>$current,'approved_incremental_migrations'=>$policy['approved_incremental_migrations']]);
RequestDrain::provision($root.'/gate');
$hook=['script'=>__FILE__,'sha256'=>digest(__FILE__),'args'=>[$root.'/profile.json']];
$migrate=dirname(__DIR__).'/publisher/bin/incremental-migration-hook.php';
$config=['project'=>'rumo10','repository'=>'rralvarez/rumo10','workflow_id'=>1,'branch'=>'codex/production-consolidated-v062',
    'required_jobs'=>['compatibility'],'data_contracts'=>['rumo10-development-v3'],'baseline_data_contracts'=>['rumo10-development-v3'],
    'private'=>$root.'/private','current'=>$current,'public'=>$root.'/public','control'=>$root.'/control',
    'account_mutex'=>$root.'/mutex','apply_enabled'=>true,'php'=>PHP_BINARY,'update_policy'=>$policy,
    'pinned_controls'=>[$root.'/public/index.php'=>digest($root.'/public/index.php'),$root.'/public/.htaccess'=>digest($root.'/public/.htaccess')],
    'request_gate'=>['root'=>$root.'/gate','projects'=>['rumo10','rumohub'],'coverage_approved'=>true,
        'entrypoints'=>[$root.'/public/index.php'=>digest($root.'/public/index.php'),$root.'/public/.htaccess'=>digest($root.'/public/.htaccess')],'timeout_seconds'=>0],
    'homologation'=>array_fill_keys(['backup','maintenance','recovery','legacy_publishers_fenced'],true),
    'hooks'=>array_fill_keys(['preflight','fence','maintenance','backup','smoke'],$hook)+
        ['migrate'=>['script'=>$migrate,'sha256'=>digest($migrate),'args'=>['--profile',$root.'/migration.json']]]];

$plain=new Publisher($config);
$stage=stageBuild($plain,$f01Dir);
check($stage['envelope']['required_migrations']===['009'],'F01 stage carries mandatory migration');
record($current.'/build-manifest.json',['commit'=>$v8['functional_commit'],'checksums'=>['app/Game.php'=>str_repeat('0',64)]]);
rejected(fn()=>$plain->apply($stage['stage']),'Installed build manifest identity or checksums mismatch');
unlink($current.'/build-manifest.json');
rejected(fn()=>$plain->apply($stage['stage']),'Exclusive request drain required');
unset($plain);

$publisher=new DrainPublisher($config);
$writer=(new RequestDrain($root.'/gate'))->enter();
rejected(fn()=>$publisher->apply($stage['stage']),'Drain timeout');
fclose($writer);
check($publisher->recover()['state']==='recovered','Unstarted attempt recovered after writer exits');
check($publisher->apply($stage['stage'])['state']==='validated','V8 to F01 completed through the real publisher');
check($db->query("SELECT version FROM rumo10_migrations WHERE version='009'")->fetchColumn()==='009','Real hook recorded migration 009');
check(json(file_get_contents($root.'/private/installed.json'))['functional_commit']===$f01['functional_commit'],'F01 receipt records the authenticated functional identity');

$db->exec("UPDATE rumo10_universos SET estado_json='{\"id\":1,\"version\":2,\"stage\":\"handicap\",\"canonical_match_uid\":\"synthetic\"}' WHERE carreira_id=1");
$db->exec("INSERT INTO rumo10_f01_outbox(carreira_id,command_uid,chave,payload_json) VALUES(1,'dep042','dep042','{}')");
file_put_contents($root.'/expected','2');
$stage=stageBuild($publisher,$recoveryDir);
check($publisher->apply($stage['stage'])['state']==='validated','Deliberate F01 to V8 recovery build installed');
check(json(file_get_contents($root.'/private/installed.json'))['functional_commit']===$v8['functional_commit'],'V8 receipt records the authenticated functional identity');
check(str_contains(file_get_contents($root.'/smoke.log'),'{"version":2,"f01":false}'),'Restored V8 Repository read the recent save during smoke');
check(count($db->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN))===5
    && (int)$db->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE chave='dep042'")->fetchColumn()===1,'Five F01 tables and recent outbox survived the return');
check(!(new RequestDrain($root.'/gate'))->closed(),'Writer admission reopened after smoke');
