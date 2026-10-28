<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
$root=dirname(__DIR__,2);$old=$argv[1];$db=Database::connect();$checks=[];
function ck($condition,$name){global $checks;if(!$condition)throw new RuntimeException($name);$checks[]=$name;}
function worker($tree,$action,$account,...$extra){global $root;$args=array_merge([PHP_BINARY,__DIR__.'/compatible-writer-worker.php',$tree,$action,(string)$account],array_map('strval',$extra));$p=proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);return [$code,json_decode($raw,true,512,JSON_THROW_ON_ERROR)];}
foreach(['001.sql','002.sql','003.sql','004.sql','005-growth.sql'] as $f)$db->exec(file_get_contents($old.'/database/'.$f));
$before=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t)$before[$t]=preg_replace('/AUTO_INCREMENT=\d+ /','',$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1]);
$accounts=[];foreach(['legacy','new','stale'] as $label){$q=$db->prepare('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)');$q->execute([$label.'@example.invalid',password_hash('isolated-only',PASSWORD_DEFAULT)]);$accounts[$label]=(int)$db->lastInsertId();}
[$code,$a]=worker($old,'create',$accounts['legacy']);ck($code===0,'old writer creates legacy save');
$db->exec(file_get_contents($root.'/database/006-balance-and-maintenance.sql'));require $root.'/database/seed_balance.php';$db->beginTransaction();seed_balance_data($db);$db->exec(file_get_contents($root.'/database/007-recalibracao-ofensiva.sql'));$db->exec(file_get_contents($root.'/database/008-penalties-balance.sql'));$db->commit();
foreach($before as $t=>$ddl)ck(preg_replace('/AUTO_INCREMENT=\d+ /','',$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1])===$ddl,'schema unchanged '.$t);
[$code,$a]=worker($old,'command',$accounts['legacy'],'handicap');ck($code===0,'old writer remains usable after expansion');
[$code,$a]=worker($root,'command',$accounts['legacy'],'choice');ck($code===0&&$a['profile']===null,'new writer preserves legacy contract');
[$code,$a]=worker($root,'create',$accounts['new'],'v3');ck($code===0&&$a['profile']==='development-v3-isolated','new writer creates v3 save');
[$code,$a]=worker($old,'command',$accounts['new'],'handicap');ck($code===0&&$a['ledger'],'old writer processes new v3 save without ledger loss');
$version=$a['version'];[$code,$b]=worker($root,'command',$accounts['new'],'choice');ck($code===0&&$b['ledger'],'new writer continues same save');
[$code,$a]=worker($old,'command',$accounts['new'],'choice',$version);ck($code!==0,'stale old command rejected after new commit');
$state=$db->prepare('SELECT estado_json FROM rumo10_universos WHERE carreira_id=?');$state->execute([$b['id']]);$g=json_decode($state->fetchColumn(),true);$g['stage']='penaltis';$update=$db->prepare('UPDATE rumo10_universos SET estado_json=? WHERE carreira_id=?');$update->execute([json_encode($g),$b['id']]);$hash=hash('sha256',json_encode($g));
[$code,$a]=worker($old,'command',$accounts['new'],'choice');ck($code!==0,'old writer refuses unknown penalty command');$state->execute([$b['id']]);ck(hash('sha256',json_encode(json_decode($state->fetchColumn(),true)))===$hash,'rejected old writer leaves pending penalty save unchanged');
echo json_encode(['passed'=>count($checks),'checks'=>$checks,'contract'=>'rumo10-development-v3','existing_schema_unchanged'=>true,'requires_forward_recovery_for_penalty_states'=>true],JSON_PRETTY_PRINT);
