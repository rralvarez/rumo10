<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);$out=$root.'/Docs/estabilizacao-v04';if(!is_dir($out))mkdir($out,0775,true);
if(is_file($out.'/baseline.json'))throw new RuntimeException('Baseline existente: não sobrescrever.');
$files=array_merge(glob($root.'/app/*.php'),glob($root.'/database/*.json'),glob($root.'/database/scenes/*.json'),glob($root.'/database/editorial/*'),[$root.'/public/assets/game.js',$root.'/public/assets/game.css']);$hashes=[];
foreach($files as $file)$hashes[str_replace('\\','/',substr($file,strlen($root)+1))]=hash('sha256',str_replace("\r\n","\n",file_get_contents($file)));
$cases=[];
foreach([45,65,85] as $level)foreach([35,50,80] as $own)foreach([35,50,80] as $opp){
 $g=Game::legacyCreate('Baseline sintética','São Paulo','SP','centroavante');$g['stage']='partida';foreach($g['skills'] as &$s)$s['value']=$level;unset($s);$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']='em_andamento';$m['minute']=35;$m['entry']=0;$m['exit']=90;$m['home_overall']=$m['home']===$g['club']?$own:$opp;$m['away_overall']=$m['away']===$g['club']?$own:$opp;MatchBalance::prepare($m,$g);$m['clear_chance']=true;unset($m);
 $cases[]=['level'=>$level,'own'=>$own,'opponent'=>$opp,'normal'=>Game::chance($g,['action'=>'chute','effects'=>[]]),'clear'=>Game::chance($g,Game::choices($g)[0]),'expected_goals'=>$g['matches'][$i]['expected_goals']];
}
$result=['git_base'=>'a5bb59e','hashes'=>$hashes,'cases'=>$cases,'catalog'=>count(Scenes::catalog()),'new'=>count(NarrativeCatalog::all()),'active'=>count(array_filter(NarrativeCatalog::all(),fn($s)=>$s['enabled']))];
if(in_array('--mysql-readonly',$argv,true)){
 if(!in_array(config('DB_HOST'),['127.0.0.1','localhost','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Recusado: esta inspeção permite somente rumohub local.');
 $db=Database::connect();$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();try{
  $result['database']=$db->query('SELECT DATABASE()')->fetchColumn();
  $result['schema']=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (LEFT(TABLE_NAME,7)='rumo10_' OR LEFT(TABLE_NAME,3)='rh_') ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll();
  foreach(['rumo10_carreiras','rumo10_universos','rumo10_historico_encerrado'] as $table)$result['save_counts'][$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
 }finally{$db->rollBack();}
}
file_put_contents($out.'/baseline.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode(['cases'=>count($cases),'catalog'=>$result['catalog'],'database'=>$result['database']??'não consultado','save_counts'=>$result['save_counts']??[]],JSON_UNESCAPED_UNICODE).PHP_EOL;
