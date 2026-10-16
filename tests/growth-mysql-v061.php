<?php
declare(strict_types=1);
if(!in_array('--local-rollback',$argv,true))exit("Use --local-rollback.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_ACHIEVEMENTS=1');putenv('RUMO10_RECONCILIATION=1');putenv('RUMO10_FEEDBACK=1');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV','local')==='production'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Somente local.');
class GrowthFixturePDO extends PDO {
 public function beginTransaction():bool{return $this->inTransaction()?true:parent::beginTransaction();}
 public function commit():bool{return true;}
 public function rollBack():bool{return true;}
 public function discard():void{if($this->inTransaction())parent::rollBack();}
}
$db=new GrowthFixturePDO('mysql:host='.config('DB_HOST').';port='.config('DB_PORT','3306').';dbname=rumohub;charset=utf8mb4',config('DB_USERNAME'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$r=new Repository($db);$checks=0;function ok(bool $x,string $why):void{global $checks;$checks++;if(!$x)throw new RuntimeException($why);}
$tables=['rumo10_contas','rumo10_identidades','rumo10_carreiras','rumo10_universos','rumo10_conquistas','rumo10_conquistas_carreiras','rumo10_conquistas_reconciliacoes','rumo10_conquistas_fontes','rumo10_feedback'];$before=[];foreach($tables as $t)$before[$t]=(int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();
$db->beginTransaction();try{
 $hub=random_int(1000000000000,8000000000000);$accounts=[];foreach([0,1] as $i){$r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['v061-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$accounts[$i]=(int)$db->lastInsertId();$r->query('INSERT INTO rumo10_identidades VALUES(?,?)',[$accounts[$i],$hub+$i]);}
 $account=$accounts[0];$careerIds=[];
 foreach([0,1] as $n){$r->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(NULL,?,?,?,?,?,?,?)',['Fixture','São Paulo','SP','meia',18,'treino','Fixture']);$id=(int)$db->lastInsertId();$careerIds[]=$id;$r->query('INSERT INTO rumo10_carreiras_arquivadas(carreira_id,conta_id) VALUES(?,?)',[$id,$account]);
  $g=Game::legacyCreate('Fixture','São Paulo','SP','meia');$g['id']=$id;$g['official']=true;$g['competition_id']='br-a';$g['season_competitions']=['br-a'];$g['stage']='treino';$m=$g['matches'][Game::currentIndex($g)];$m['status']='finalizada';$m['entry']=0;$m['exit']=90;$m['stats']=array_replace($m['stats'],['minutes'=>90,'goals'=>3,'assists'=>1,'tackles'=>2]);$m[$m['home']===$g['club']?'hg':'ag']=4;$g['matches']=[$m];$r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)',[$id,json_encode($g,JSON_THROW_ON_ERROR)]);
 }
 $dry=AchievementReconciliation::run($r,$account,true);ok(count($dry['recognized'])===7,'dry-run seven proven goals');ok((int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas WHERE hub_usuario_id=?',[$hub])->fetchColumn()===0,'dry-run no awards');
 $a=AchievementReconciliation::run($r,$account);ok($a['recognized']===$dry['recognized'],'apply matches dry-run');$b=AchievementReconciliation::run($r,$account);ok($b['recognized']===['CAR03'],'second distinct career');ok(AchievementReconciliation::run($r,$account)['pending']===0,'checkpoint no repeated scan');
 ok((int)$r->query('SELECT SUM(pontos_g) FROM rumo10_conquistas WHERE hub_usuario_id=?',[$hub])->fetchColumn()===85,'exact G no double award');ok((int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas_fontes WHERE hub_usuario_id=?',[$hub])->fetchColumn()===8,'provenance each unlock');ok((int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas WHERE hub_usuario_id=? AND notificada=0',[$hub])->fetchColumn()===0,'historical events no toast avalanche');
 ok(AchievementReconciliation::run($r,$accounts[1])['recognized']===[],'second account cannot read first history');
 $r->query('DELETE FROM rumo10_universos WHERE carreira_id=?',[$careerIds[0]]);$r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$careerIds[0]]);ok((int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND valida=1',[$hub])->fetchColumn()===2,'deleted valid career retained');
 $input=['rating'=>4,'category'=>'sugestao','comment'=>'<b>Fixture</b>','key'=>bin2hex(random_bytes(32))];GameFeedback::submit($r,$account,$input);GameFeedback::submit($r,$account,$input);ok((int)$r->query('SELECT COUNT(*) FROM rumo10_feedback WHERE hub_usuario_id=?',[$hub])->fetchColumn()===1,'feedback retry unique');
 try{GameFeedback::submit($r,$account,array_replace($input,['rating'=>5]));ok(false,'conflicting retry');}catch(InvalidArgumentException){ok(true,'conflicting retry rejected');}
 for($i=0;$i<4;$i++)GameFeedback::submit($r,$account,array_replace($input,['key'=>bin2hex(random_bytes(32))]));
 // Catch expected quota exceptions outside the fixture transaction: test PDO suppresses rollback.
 try{GameFeedback::submit($r,$account,array_replace($input,['key'=>bin2hex(random_bytes(32))]));ok(false,'sixth accepted');}catch(InvalidArgumentException){ok(true,'five per 24 hours');}
 GameFeedback::submit($r,$account,$input);ok((int)$r->query('SELECT COUNT(*) FROM rumo10_feedback WHERE hub_usuario_id=?',[$hub])->fetchColumn()===5,'replay succeeds even at quota');
 GameFeedback::submit($r,$accounts[1],array_replace($input,['key'=>bin2hex(random_bytes(32))]));ok((int)$r->query('SELECT COUNT(*) FROM rumo10_feedback WHERE hub_usuario_id=?',[$hub+1])->fetchColumn()===1,'quota isolated per account');
 $other=Database::connect();$other->exec('SET SESSION innodb_lock_wait_timeout=1');try{$other->beginTransaction();$q=$other->prepare('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE');$q->execute([$account]);ok(false,'concurrent throttle lock bypassed');}catch(PDOException $e){ok((int)($e->errorInfo[1]??0)===1205,'concurrent feedback serialized');}finally{if($other->inTransaction())$other->rollBack();}
}finally{$db->discard();}
foreach($before as $t=>$count)ok((int)$r->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()===$count,'fixture rollback '.$t);
file_put_contents(dirname(__DIR__).'/Docs/pos-jogo-v061/mysql.json',json_encode(['checks'=>$checks,'recognized_fixture_first'=>7,'recognized_fixture_second'=>1,'gamerscore_fixture'=>85,'real_accounts_processed'=>0,'rollback'=>true,'result'=>'passed'],JSON_PRETTY_PRINT));echo "OK: $checks verificações MySQL v0.6.1, rollback integral.\n";
