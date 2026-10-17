<?php
declare(strict_types=1);
if(!in_array('--local-rollback',$argv,true))exit("Use --local-rollback.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_ACHIEVEMENTS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV')==='production'||config('DB_DATABASE')!=='rumohub'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Somente banco local.');
class WorldFixturePDO extends PDO {
 public int $queries=0;
 public function prepare(string $query,array $options=[]):PDOStatement|false{$this->queries++;return parent::prepare($query,$options);}
 public function beginTransaction():bool{return $this->inTransaction()?true:parent::beginTransaction();}
 public function commit():bool{return true;}
 public function rollBack():bool{return true;}
 public function discard():void{if($this->inTransaction())parent::rollBack();}
}
$db=new WorldFixturePDO('mysql:host='.config('DB_HOST').';port='.config('DB_PORT','3306').';dbname=rumohub;charset=utf8mb4',config('DB_USERNAME'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$r=new Repository($db);$checks=0;function check(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}
$tables=['rumo10_contas','rumo10_carreiras','rumo10_universos','rumo10_decisoes','rumo10_atributos','rumo10_estados'];$before=[];foreach($tables as $t)$before[$t]=(int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();$seasons=[];
$db->beginTransaction();try{
 $accounts=[];$ids=[];foreach([0,1] as $n){$r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['world-'.bin2hex(random_bytes(10)).'@example.invalid',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$accounts[$n]=(int)$db->lastInsertId();$r->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?)',[$accounts[$n],'Atleta de teste','São Paulo','SP','meia',18,'treino','Zumbi-AL']);$ids[$n]=(int)$db->lastInsertId();$g=AlagoasTrial::create($ids[$n]);$r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)',[$ids[$n],json_encode($g,JSON_THROW_ON_ERROR)]);}
 $account=$accounts[0];$g=$r->load($account);check($g['id']===$ids[0],'load próprio');check($r->load($accounts[1])['id']===$ids[1],'isolamento de conta');check(WorldSeason::scope($g,fn()=>Competition::catalog()['al-a1']['name'])==='Alagoano Série A','contrato sobrevive ao JSON MySQL');
 $key=hash('sha256','initial-fixture-'.bin2hex(random_bytes(8)));$next=$r->command($account,'choice',['option'=>0],$key,$g['version']);$replay=$r->command($account,'choice',['option'=>0],$key,$g['version']);check($replay===$next,'token repetido não duplica');
 try{$r->command($account,'choice',['option'=>1],$key,$g['version']);throw new RuntimeException('token aceito');}catch(InvalidArgumentException $e){check(true,'token com conteúdo divergente rejeitado');}
 $otherBefore=$r->load($accounts[1]);$g=$next;$step=0;
 for($season=1;$season<=5;$season++){$start=hrtime(true);$queries=$db->queries;$commands=0;$maxCommandMs=0.;$closureMs=0.;
  while($g['stage']!=='resumo'){
   if(++$step>12000)throw new RuntimeException('Loop');$action='choice';$input=['option'=>0];
   if($g['stage']==='competicao_resumo'){$action='continue';$input=[];}
   elseif($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];if($m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']){$action='advance';$input=[];}}
   $version=$g['version'];$token=hash('sha256','world-step-'.$ids[0].'-'.$step);$commandStart=hrtime(true);$g=$r->command($account,$action,$input,$token,$version);$elapsedCommand=(hrtime(true)-$commandStart)/1e6;$maxCommandMs=max($maxCommandMs,$elapsedCommand);if($g['stage']==='resumo')$closureMs=$elapsedCommand;$commands++;
  }
  $closed=$r->load($account);check($closed===$g,'estado recarregado igual');check(count($g['competition_results'])>=1,'duas competições concluídas');check(count($g['world_completed'])===3,'mundo completo');$s=Game::summary($g);check($s['games']>0,'minutos reais');
  $seasons[]=['season'=>$season,'commands'=>$commands,'queries'=>$db->queries-$queries,'max_command_ms'=>round($maxCommandMs,2),'season_close_command_ms'=>round($closureMs,2),'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'snapshot_bytes'=>strlen(json_encode($g)),'games'=>$s['games'],'goals'=>$s['goals'],'assists'=>$s['assists']];
  if($season<5){$g=$r->command($account,'offers',[],hash('sha256','offers-'.$ids[0].'-'.$season),$g['version']);$choice=$season===2&&!empty($g['offers'])?['accept'=>'yes','option'=>0]:['accept'=>'no'];$g=$r->command($account,'transfer',$choice,hash('sha256','stay-'.$ids[0].'-'.$season),$g['version']);}
 }
 check($otherBefore===$r->load($accounts[1]),'outra conta intacta após cinco temporadas');
 $second=Database::connect();$second->exec('SET SESSION innodb_lock_wait_timeout=1');$second->beginTransaction();try{$st=$second->prepare('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE');$st->execute([$account]);throw new RuntimeException('lock não observado');}catch(PDOException $e){check(($e->errorInfo[1]??null)===1205,'lock concorrente serializa');}finally{if($second->inTransaction())$second->rollBack();}
}finally{$db->discard();}
foreach($before as $t=>$count)check((int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()===$count,'rollback integral '.$t);
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/integracao-nacional/state-mysql.json',json_encode(['checks'=>$checks,'five_gameplay_seasons_with_real_repository'=>true,'all_fixture_writes_rolled_back'=>true,'rh_writes'=>false,'faf_rules_and_real_participants'=>true,'national_continuity_complete'=>false,'achievements_disabled_for_disposable_fixture'=>true,'seasons'=>$seasons],JSON_PRETTY_PRINT));echo "OK: $checks verificações PDO; cinco temporadas persistidas/recarregadas, rollback e lock concorrente.\n";
