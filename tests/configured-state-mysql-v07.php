<?php
declare(strict_types=1);
if(!in_array('--local-rollback',$argv,true))exit("Use --local-rollback.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_ACHIEVEMENTS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV')==='production'||config('DB_DATABASE')!=='rumohub'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Somente banco local.');
class RenewalFixturePDO extends PDO {
 public int $queries=0; public bool $crash=false;
 public function prepare(string $query,array $options=[]):PDOStatement|false{$this->queries++;if($this->crash&&str_starts_with($query,'INSERT INTO rumo10_decisoes')){$this->crash=false;throw new RuntimeException('injected_crash_before_command_ledger');}return parent::prepare($query,$options);}
 public function beginTransaction():bool{return $this->inTransaction()?true:parent::beginTransaction();}
 public function commit():bool{return true;}
 public function rollBack():bool{return true;}
 public function discard():void{if($this->inTransaction())parent::rollBack();}
}
$db=new RenewalFixturePDO('mysql:host='.config('DB_HOST').';port='.config('DB_PORT','3306').';dbname=rumohub;charset=utf8mb4',config('DB_USERNAME'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$r=new Repository($db);$checks=0;function check(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}
$tables=['rumo10_contas','rumo10_carreiras','rumo10_universos','rumo10_decisoes','rumo10_atributos','rumo10_estados'];$before=[];foreach($tables as $t)$before[$t]=(int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();$seasons=[];
$db->beginTransaction();try{
 $accounts=[];$ids=[];foreach([0,1] as $n){$r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['world-'.bin2hex(random_bytes(10)).'@example.invalid',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$accounts[$n]=(int)$db->lastInsertId();$r->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?)',[$accounts[$n],'Atleta de teste','São Paulo','SP','meia',18,'treino','CRB-AL']);$ids[$n]=(int)$db->lastInsertId();$g=StateBatchTrial::create($ids[$n],'expresso');$r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)',[$ids[$n],json_encode($g,JSON_THROW_ON_ERROR)]);}
 $account=$accounts[0];$g=$r->load($account);check($g['id']===$ids[0],'load próprio');check($r->load($accounts[1])['id']===$ids[1],'isolamento de conta');check(WorldSeason::scope($g,fn()=>Competition::catalog()['al-a1']['name'])==='Alagoano Série A','contrato sobrevive ao JSON MySQL');
 $key=hash('sha256','initial-fixture-'.bin2hex(random_bytes(8)));$next=$r->command($account,'simulate',['plan'=>'equilibrio'],$key,$g['version']);$replay=$r->command($account,'simulate',['plan'=>'equilibrio'],$key,$g['version']);check($replay===$next,'token repetido não duplica');
 try{$r->command($account,'simulate',['plan'=>'ofensivo'],$key,$g['version']);throw new RuntimeException('token aceito');}catch(InvalidArgumentException $e){check(true,'token com conteúdo divergente rejeitado');}
 $otherBefore=$r->load($accounts[1]);$g=$next;$step=0;
 for($season=1;$season<=1;$season++){$start=hrtime(true);$queries=$db->queries;$commands=0;$maxCommandMs=0.;$closureMs=0.;$peakBytes=0;$latencies=[];$closureInput=null;
  while($g['stage']!=='resumo'){
   if(++$step>12000)throw new RuntimeException('Loop');$action='choice';$input=['option'=>0];
   if(in_array($g['stage'],['world_processing','competicao_resumo'],true)){$action='continue';$input=[];}
   elseif($g['stage']==='treino'){$action='simulate';$input=['plan'=>'equilibrio'];}
   elseif($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];if($m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']){$action='advance';$input=[];}}
   $checkpoint=$g;$version=$g['version'];$token=hash('sha256','world-step-'.$ids[0].'-'.$step);$commandStart=hrtime(true);$g=$r->command($account,$action,$input,$token,$version);$elapsedCommand=(hrtime(true)-$commandStart)/1e6;$latencies[]=$elapsedCommand;$maxCommandMs=max($maxCommandMs,$elapsedCommand);if($g['stage']==='resumo'){$closureMs=$elapsedCommand;$closureInput=[$checkpoint,$action,$input,$token,$version];}$commands++;$peakBytes=max($peakBytes,strlen(json_encode($g)));
  }
  $closed=$r->load($account);check($closed===$g,'estado recarregado igual');check(count($g['competition_results'])>=1,'duas competições concluídas');check(count($g['world_completed'])===11,'mundo completo');$s=Game::summary($g);check($s['games']>0,'minutos reais');
  sort($latencies);$quantile=fn($q)=>round($latencies[(int)ceil(count($latencies)*$q)-1],2);
  $seasons[]=['season'=>$season,'commands'=>$commands,'queries'=>$db->queries-$queries,'max_command_ms'=>round($maxCommandMs,2),'p50_ms'=>$quantile(.5),'p95_ms'=>$quantile(.95),'p99_ms'=>$quantile(.99),'closure_bytes'=>strlen(json_encode($g['national_pyramid_closures'])),'history_bytes'=>strlen(json_encode($g['competition_history'])),'season_close_command_ms'=>round($closureMs,2),'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'snapshot_bytes'=>strlen(json_encode($g)),'peak_snapshot_bytes'=>$peakBytes,'games'=>$s['games'],'goals'=>$s['goals'],'assists'=>$s['assists']];
  if($season<1){$g=$r->command($account,'offers',[],hash('sha256','offers-'.$ids[0].'-'.$season),$g['version']);$choice=$season===2&&!empty($g['offers'])?['accept'=>'yes','option'=>0]:['accept'=>'no'];$g=$r->command($account,'transfer',$choice,hash('sha256','stay-'.$ids[0].'-'.$season),$g['version']);}
 }

 check($g['national_pyramid_closures']['1']['state']==='blocked','closure persistida bloqueada');
 WorldSeason::scope($g,function()use($g){check(CopaBrasilCompetition::verify($g['competitions']['br-copa']),'155 partidas CDB e sorteios verificáveis após PDO');$covered=MandatoryTournamentCoverage::audit($g);check($covered['clubs']['CRB-AL']['br-copa']['completed'],'CDBCRB no calendário obrigatório');});
 $playedCup=array_filter($g['competitions']['br-copa']['matches'],fn($m)=>in_array($g['club'],[$m['home'],$m['away']],true)&&($m['stats']['minutes']??0)>0);check(count($playedCup)>0,'personagem com minutos CDB reais');
 foreach($playedCup as $m){$k='match:1:br-copa:'.$m['round'].':'.$m['home'].':'.$m['away'];check(isset($g['reward_ledger'][$k]),'XP apenas por partida do personagem');}
 $closed=$g;$ledger=$g['reward_ledger'];
 [$checkpoint,$action,$input,$token,$version]=$closureInput;
 // A savepoint keeps this injected crash isolated inside the outer rollback fixture.
 $db->exec('SAVEPOINT closure_crash');
 $r->query('UPDATE rumo10_universos SET estado_json=? WHERE carreira_id=?',[json_encode($checkpoint),$ids[0]]);
 $r->query('DELETE FROM rumo10_decisoes WHERE carreira_id=? AND chave=?',[$ids[0],$token]);
 $preCrash=$r->load($account);$db->exec('SAVEPOINT retry_point');$db->crash=true;
 try{$r->command($account,$action,$input,$token,$version);throw new LogicException('crash não injetado');}catch(RuntimeException $e){check($e->getMessage()==='injected_crash_before_command_ledger','falha após save e antes do ledger de comando');}
 $db->exec('ROLLBACK TO SAVEPOINT retry_point');check($r->load($account)===$preCrash,'crash revertido sem homologação parcial');
 $retry=$r->command($account,$action,$input,$token,$version);check(isset($retry['national_pyramid_closures']['1'])&&$retry['stage']==='resumo','retry fecha edição integralmente');
 $replay=$r->command($account,$action,$input,$token,$version);check($retry===$replay,'replay após fechamento sem duplicação');
 try{$r->command($account,'offers',[],hash('sha256','blocked-renewal'),$retry['version']);throw new LogicException('renovação indevida');}catch(InvalidArgumentException $e){check(str_contains($e->getMessage(),'68 entradas'),'impedimento esportivo visível');}
 check($r->load($account)===$retry,'renovação recusada preserva o save');
 $db->exec('ROLLBACK TO SAVEPOINT closure_crash');$g=$r->load($account);check($g===$closed,'fixture de crash restaurada sem afetar resultado medido');
 check($otherBefore===$r->load($accounts[1]),'outra conta intacta após uma edição nacional + estaduais');
 $candidateBefore=$g;$q=StateQualification::recordMany($g,['AL','PI','RO']);check($q['AL']['candidate_count']===2&&$q['PI']['candidate_count']===2,'candidaturas AL e PI após gameplay PDO');$audit=FutureSerieDAdmission::record($g);check($audit['missing_total']===96-28-array_sum(array_column($q,'candidate_count'))&&$audit['federation_missing_total']===64-array_sum(array_column($q,'candidate_count')),'déficit estadual dinâmico sem inscrição');check($g['reward_ledger']===$candidateBefore['reward_ledger'],'candidatura não altera ledger em memória');
 $db->exec('SAVEPOINT admission_ledger');
 $r->query('UPDATE rumo10_universos SET estado_json=? WHERE carreira_id=?',[json_encode($g,JSON_THROW_ON_ERROR),$ids[0]]);
 $loaded=$r->load($account);check($loaded['future_d_admissions']['2027']==$audit,'ledger de candidaturas recarregado do JSON MySQL');check(StateQualification::recordMany($loaded,['AL','PI','RO'])==$q,'recibo estadual sobrevive JSON MySQL e replay');
 check(FutureSerieDAdmission::record($loaded)==$audit,'replay após normalização JSON não duplica ledger');
 // MySQL JSON may reorder associative ledger keys; compare values, not key order.
 check($loaded['reward_ledger']==$candidateBefore['reward_ledger'],'candidatura não concede XP/R após JSON MySQL');
 $db->exec('ROLLBACK TO SAVEPOINT admission_ledger');$g=$r->load($account);check($g===$candidateBefore,'rollback ledger preserva resultado anterior');
 $second=Database::connect();$second->exec('SET SESSION innodb_lock_wait_timeout=1');$second->beginTransaction();try{$st=$second->prepare('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE');$st->execute([$account]);throw new RuntimeException('lock não observado');}catch(PDOException $e){check(($e->errorInfo[1]??null)===1205,'lock concorrente serializa');}finally{if($second->inTransaction())$second->rollBack();}
}finally{$db->discard();}
foreach($before as $t=>$count)check((int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()===$count,'rollback integral '.$t);
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/motor-estadual/mysql-expresso.json',json_encode(['checks'=>$checks,'national_seasons_with_real_repository'=>1,'national_renewals'=>0,'mode'=>'expresso','crash_retry_and_closure_replay'=>true,'all_fixture_writes_rolled_back'=>true,'rh_writes'=>false,'faf_rules_and_real_participants'=>true,'national_continuity_complete'=>false,'achievements_disabled_for_disposable_fixture'=>true,'seasons'=>$seasons,'state_candidates'=>array_column($q,'candidate_count','uf'),'state_slots_pending'=>$audit['federation_missing_total'],'peak_process_memory_bytes'=>memory_get_peak_usage(true)],JSON_PRETTY_PRINT));echo "OK: $checks verificações PDO; uma edição nacional + estaduais persistidas/recarregadas, rollback e lock concorrente.\n";
