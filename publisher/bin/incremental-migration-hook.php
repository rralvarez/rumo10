<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};
try {
 need(PHP_SAPI==='cli'&&($argv[1]??'')==='migrate'&&($argv[2]??'')==='--profile','Private migration provider only');
 $file=$argv[3];need(is_file($file)&&!is_link($file),'Private migration profile required');if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Private profile permissions');
 $c=json(file_get_contents($file));$q=json(stream_get_contents(STDIN));need($q['project']==='rumo10'&&$c['project']==='rumo10','Only Rumo10 incremental migrations');
 $policy=$q['update_policy'];need($policy['class']==='B'&&$policy['compatible_writers_ci'],'Compatible CI required');
 $stage=$q['candidate']['stage'].'/payload';foreach($policy['migrations'] as $path)need(($c['approved_incremental_migrations'][$path]??null)===digest($stage.'/'.$path),'Unapproved SQL or seed');
 require $c['current'].'/app/bootstrap.php';$db=Database::connect();need((int)$db->query("SELECT GET_LOCK('rumo10-publisher-migrations',0)")->fetchColumn()===1,'Concurrent migration refused');
 try {
  $versions=$db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('006',$versions,true)){$db->exec(file_get_contents($stage.'/database/006-balance-and-maintenance.sql'));$db->exec("INSERT INTO rumo10_migrations(version) VALUES('006')");}
  $db->beginTransaction();
  if(!in_array('006-seed',$versions,true)){require $stage.'/database/seed_balance.php';seed_balance_data($db);$db->exec("INSERT INTO rumo10_migrations(version) VALUES('006-seed')");}
  foreach(['007'=>'007-recalibracao-ofensiva.sql','008'=>'008-penalties-balance.sql'] as $version=>$name)if(!in_array((string)$version,$versions,true)){$db->exec(file_get_contents($stage.'/database/'.$name));$db->prepare('INSERT INTO rumo10_migrations(version) VALUES(?)')->execute([(string)$version]);}
  need($db->query("SELECT codigo FROM rumo10_balanceamentos WHERE estado='ativo'")->fetchColumn()==='bal-v062-recalibracao-ofensiva','Active balance mismatch');
  need((int)$db->query("SELECT COUNT(*) FROM rumo10_formula_parametros p JOIN rumo10_formula_versoes v ON v.id=p.versao_id WHERE v.formula_id='match.penalties.parameters'")->fetchColumn()>0,'Penalty parameters missing');
  $db->commit();$out=['ok'=>true,'compatible_expansion'=>true,'attempt'=>$q['attempt'],'versions'=>$db->query('SELECT version FROM rumo10_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN)];
  record($q['private'].'/migrations-'.$q['attempt'].'.json',$out+['approved_hashes'=>$c['approved_incremental_migrations']]);echo json_encode($out,JSON_THROW_ON_ERROR);
 }finally{if($db->inTransaction())$db->rollBack();$db->query("SELECT RELEASE_LOCK('rumo10-publisher-migrations')");}
}catch(Throwable $e){fwrite(STDERR,'Incremental migration blocked; journal retained'.PHP_EOL);exit(1);}
