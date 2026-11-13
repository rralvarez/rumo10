<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/app/bootstrap.php';
$root=dirname(__DIR__,2);
$old=realpath($argv[1]??'');
$checks=[];

function ck(bool $condition,string $name): void {
    global $checks;
    if(!$condition)throw new RuntimeException($name);
    $checks[]=$name;
}

function runFixtureProcess(array $args,string $scenario,string $input=''): array {
    $stdin=tmpfile();
    $stdout=tmpfile();
    $stderr=tmpfile();
    if(!$stdin || !$stdout || !$stderr)throw new RuntimeException($scenario.' — temporary streams unavailable');
    fwrite($stdin,$input);
    rewind($stdin);
    try {
        $process=proc_open($args,[0=>$stdin,1=>$stdout,2=>$stderr],$pipes);
        if(!is_resource($process))throw new RuntimeException($scenario.' — process failed to start');
        $code=proc_close($process);
        rewind($stdout);
        $raw=stream_get_contents($stdout);
        $result=json_decode($raw,true);
        if(!is_array($result) || !is_bool($result['ok']??null)){
            $reason=trim($raw)===''?'empty response':'invalid JSON response';
            rewind($stderr);
            $diagnostic = trim((string)stream_get_contents($stderr));
            throw new RuntimeException(sprintf('%s — exit %d, %s, stderr: %s',
                $scenario,$code,$reason,substr($diagnostic,0,1000)));
        }
        return [$code,$result];
    } finally {
        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }
}

function diagnostic(int $code,array $result): string {
    return sprintf('exit %d, %s, SQLSTATE %s, driver %s, %s',$code,$result['error_class']??'no exception',
        $result['sqlstate']??'-',$result['driver_code']??'-',$result['message']??'no error message');
}

function worker(string $scenario,string $writer,string $tree,string $action,int $account,array $extra=[],bool $reject=false): array {
    $label=$scenario.' / '.$writer.' / '.$action;
    [$code,$result]=runFixtureProcess([PHP_BINARY,__DIR__.'/compatible-writer-worker.php',$tree,$action,
        (string)$account,...array_map('strval',$extra)],$label);
    $valid=$reject?$code===2 && $result['ok']===false:$code===0 && $result['ok']===true;
    if(!$valid)throw new RuntimeException($label.' — '.diagnostic($code,$result));
    return $result+['exit_code'=>$code];
}

function schema(PDO $db): array {
    $schema=[];
    foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
        $ddl=$db->query('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`')->fetch(PDO::FETCH_NUM)[1];
        $schema[$table]=preg_replace('/AUTO_INCREMENT=\d+ /','',$ddl);
    }
    return $schema;
}

function snapshot(PDO $db,int $id): array {
    $query=$db->prepare('SELECT c.versao, u.estado_json,
        (SELECT COUNT(*) FROM rumo10_decisoes d WHERE d.carreira_id=c.id) AS decisions
        FROM rumo10_carreiras c JOIN rumo10_universos u ON u.carreira_id=c.id WHERE c.id=?');
    $query->execute([$id]);
    $row=$query->fetch();
    if(!$row)throw new RuntimeException('Synthetic career snapshot missing');
    return $row;
}

function expand009(string $root): void {
    $relative='database/009-f01-canonical-telemetry.sql';
    $digest='0cb513015ddd8650885d55677df8717e3c0557838d7f42af904272744a8dda8a';
    ck(filesize($root.'/'.$relative)===6283 && hash_file('sha256',$root.'/'.$relative)===$digest,'009 bytes and SHA-256');
    $stage=sys_get_temp_dir().'/r10-compatible-'.bin2hex(random_bytes(8));
    if(!mkdir($stage.'/payload/database',0700,true))throw new RuntimeException('009 fixture stage unavailable');
    $profile=$stage.'/profile.json';
    try {
        if(!copy($root.'/'.$relative,$stage.'/payload/'.$relative))throw new RuntimeException('009 fixture copy failed');
        file_put_contents($profile,json_encode(['project'=>'rumo10','current'=>$root,
            'approved_incremental_migrations'=>[$relative=>$digest]],JSON_THROW_ON_ERROR));
        chmod($profile,0600);
        $request=['project'=>'rumo10','private'=>$stage,'attempt'=>'compatible-writers','candidate'=>['stage'=>$stage],
            'update_policy'=>['class'=>'B','compatible_writers_ci'=>true,'migrations'=>[$relative],'required_migrations'=>['009']]];
        // The real hook applies DDL outside a transaction and validates the entire canonical schema before recording 009.
        [$code,$result]=runFixtureProcess([PHP_BINARY,dirname(__DIR__).'/bin/incremental-migration-hook.php',
            'migrate','--profile',$profile],'schema expansion / migration hook / 009',json_encode($request,JSON_THROW_ON_ERROR));
        ck($code===0 && $result['ok']===true && ($result['compatible_expansion']??false)===true &&
            in_array('009',$result['versions'],true),'production migration hook validates 009 structure');
    } finally {
        foreach([$profile,$stage.'/payload/'.$relative,$stage.'/migrations-compatible-writers.json'] as $file){
            if(is_file($file))unlink($file);
        }
        rmdir($stage.'/payload/database');
        rmdir($stage.'/payload');
        rmdir($stage);
    }
}

if(!$old || !is_file($old.'/app/bootstrap.php'))throw new RuntimeException('Legacy fixture runtime not found');
if(config('DB_HOST')!=='127.0.0.1' || config('DB_DATABASE')!=='rumo_compatible_synthetic'){
    throw new RuntimeException('An isolated loopback compatibility database is required');
}
$db=Database::connect();
if(schema($db)!==[])throw new RuntimeException('Compatibility database must be empty');
foreach(['001.sql','002.sql','003.sql','004.sql','005-growth.sql'] as $file)$db->exec(file_get_contents($old.'/database/'.$file));
$before=schema($db);
$accounts=[];
foreach(['legacy','new','pre-expansion'] as $label){
    $query=$db->prepare('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)');
    $query->execute([$label.'@example.invalid',password_hash('isolated-only',PASSWORD_DEFAULT)]);
    $accounts[$label]=(int)$db->lastInsertId();
}
$pre=worker('before expansion','legacy',$old,'create',$accounts['pre-expansion']);
$preCommand=worker('before expansion','legacy',$old,'command',$accounts['pre-expansion'],['handicap']);
ck($preCommand['id']===$pre['id'] && $preCommand['version']>$pre['version'],'old writer works before expansion');
$legacy=worker('legacy save creation','legacy',$old,'create',$accounts['legacy']);
ck(is_int($legacy['id']) && $legacy['profile']===null,'old writer creates legacy save');

$db->exec(file_get_contents($root.'/database/006-balance-and-maintenance.sql'));
$db->exec("INSERT INTO rumo10_migrations(version) VALUES('006')");
require $root.'/database/seed_balance.php';
$db->beginTransaction();
seed_balance_data($db);
$db->exec(file_get_contents($root.'/database/007-recalibracao-ofensiva.sql'));
$db->exec(file_get_contents($root.'/database/008-penalties-balance.sql'));
$db->exec("INSERT INTO rumo10_migrations(version) VALUES('006-seed'),('007'),('008')");
$db->commit();

$legacyCommand=worker('after 008','legacy',$old,'command',$accounts['legacy'],['handicap']);
$beforeNegative=snapshot($db,$legacy['id']);
$missing=worker('negative without 009','F01',$root,'command',$accounts['legacy'],['choice'],true);
$missingDiagnostic=diagnostic($missing['exit_code'],$missing);
ck($missing['error_class']===PDOException::class && ($missing['sqlstate']??null)==='42S02' &&
    ($missing['driver_code']??null)===1146 && $missing['message']==='Tabela inexistente: rumo10_f01_respostas',
    'negative without 009 / F01 / command — '.$missingDiagnostic);
ck(snapshot($db,$legacy['id'])===$beforeNegative,'missing 009 leaves state and decisions unchanged');

$pre009=schema($db);
expand009($root);
$after=schema($db);
foreach($before as $table=>$ddl)ck(($after[$table]??null)===$ddl,'schema unchanged '.$table);
foreach($pre009 as $table=>$ddl)ck(($after[$table]??null)===$ddl,'009 preserves schema '.$table);
$added=array_keys(array_diff_key($after,$pre009));
sort($added);
ck($added===['rumo10_f01_aparicoes','rumo10_f01_eventos','rumo10_f01_outbox','rumo10_f01_partidas','rumo10_f01_respostas'],
    '009 adds exactly the five canonical tables');
ck((int)$db->query("SELECT COUNT(*) FROM rumo10_migrations WHERE version='009'")->fetchColumn()===1,'009 recorded once');

$resumed=worker('after 009','F01',$root,'command',$accounts['legacy'],['choice']);
ck($resumed['profile']===null && $resumed['id']===$legacy['id'] && $resumed['version']>$legacyCommand['version'],
    'new writer preserves legacy contract');
$response=$db->prepare('SELECT versao_resultante FROM rumo10_f01_respostas WHERE carreira_id=?');
$response->execute([$resumed['id']]);
ck($response->fetchAll(PDO::FETCH_COLUMN)===[$resumed['version']],'F01 persisted the canonical response');
$new=worker('V3 save creation','F01',$root,'create',$accounts['new'],['v3']);
ck($new['profile']==='development-v3-isolated','new writer creates v3 save');
$continued=worker('after 009 on V3 save','legacy',$old,'command',$accounts['new'],['handicap']);
ck($continued['ledger'] && $continued['id']===$new['id'] && $continued['version']>$new['version'],
    'old writer remains usable after expansion and processes new v3 save without ledger loss');
$next=worker('after legacy commit','F01',$root,'command',$accounts['new'],['choice']);
ck($next['ledger'] && $next['id']===$continued['id'] && $next['version']>$continued['version'],'new writer continues same save');

$state=snapshot($db,$next['id']);
$stale=worker('stale old command','legacy',$old,'command',$accounts['new'],['choice',$continued['version']],true);
ck($stale['error_class']===InvalidArgumentException::class && $stale['message']==='Versão obsoleta recusada.',
    'stale old command rejected after new commit — '.diagnostic($stale['exit_code'],$stale));
ck(snapshot($db,$next['id'])===$state,'rejected stale command leaves state and decisions unchanged');
$pending=json_decode($state['estado_json'],true,512,JSON_THROW_ON_ERROR);
$pending['stage']='penaltis';
$update=$db->prepare('UPDATE rumo10_universos SET estado_json=? WHERE carreira_id=?');
$update->execute([json_encode($pending,JSON_THROW_ON_ERROR),$next['id']]);
$state=snapshot($db,$next['id']);
$incompatible=worker('pending penalty state','legacy',$old,'command',$accounts['new'],['choice'],true);
ck(($incompatible['error_class']===InvalidArgumentException::class && $incompatible['message']==='Comando ou estado incompatível.') ||
    ($incompatible['error_class']===RuntimeException::class && $incompatible['message']==='Nenhuma disputa de pênaltis ativa.'),
    'old writer refuses incompatible penalty command — '.
    diagnostic($incompatible['exit_code'],$incompatible));
ck(snapshot($db,$next['id'])===$state,'rejected old writer leaves pending penalty save unchanged');

echo json_encode(['passed'=>count($checks),'checks'=>$checks,'contract'=>'rumo10-development-v3',
    'existing_schema_unchanged'=>true,'requires_forward_recovery_for_penalty_states'=>true,
    'negative_without_009'=>$missing],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
