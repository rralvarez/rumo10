<?php
declare(strict_types=1);
// Explicit opt-in, local rumohub only. All fixture writes remain in one transaction and are rolled back.
if(!in_array('--local-rollback',$argv,true))exit("Use --local-rollback para testar somente rumohub local.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_TIMING=1');require dirname(__DIR__).'/app/bootstrap.php';
if(!in_array(config('DB_HOST'),['127.0.0.1','localhost','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Recusado: somente rumohub local.');
final class RollbackFixturePDO extends PDO {
 public function beginTransaction():bool{return $this->inTransaction()?true:parent::beginTransaction();}
 public function commit():bool{return true;}
 public function rollBack():bool{return true;}
 public function discard():void{if($this->inTransaction())parent::rollBack();}
}
$db=new RollbackFixturePDO('mysql:host='.config('DB_HOST').';port='.config('DB_PORT','3306').';dbname=rumohub;charset=utf8mb4',config('DB_USERNAME'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$r=new Repository($db);$checks=0;$times=[];function persistenceCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$counts=[];foreach(['rumo10_contas','rumo10_carreiras','rumo10_universos','rumo10_decisoes'] as $table)$counts[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
$db->beginTransaction();try{
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['rollback-v04-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$account=(int)$db->lastInsertId();
 $base=Game::legacyCreate('Fixture transacional descartável','São Paulo','SP','meia');$base['play_mode']='normal';$r->create($account,$base);
 $g=$r->load($account);$key=bin2hex(random_bytes(32));$next=$r->command($account,'handicap',[],$key,$g['version']);$retry=$r->command($account,'handicap',[],$key,$g['version']);persistenceCheck($next===$retry,'Perfil não é sorteado duas vezes');persistenceCheck(isset($retry['initial_profile']['effects']),'Antes/depois persiste');
 foreach([1,2000] as $sceneId){
  $g=$r->load($account);$g['stage']='treino';$scene=Scenes::catalog()[$sceneId];$g['active_situation']=['id'=>$sceneId,'key'=>Scenes::key($g)];if($sceneId>=2000)$g['active_situation']['snapshot']=$scene;
  $save=new ReflectionMethod(Repository::class,'save');$save->invoke($r,$g);$key=bin2hex(random_bytes(32));RequestMetrics::reset();$start=hrtime(true);$a=$r->command($account,'choice',['option'=>'0'],$key,$g['version']);$times['scene_'.$sceneId]=['elapsed_ms'=>(hrtime(true)-$start)/1e6,'components'=>RequestMetrics::read()];$b=$r->command($account,'choice',['option'=>'0'],$key,$g['version']);persistenceCheck($a===$b,'Retry idempotente cena '.$sceneId);
  persistenceCheck((int)$r->query('SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id=? AND chave=?',[$g['id'],$key])->fetchColumn()===1,'Uma gravação da decisão');
  try{$r->command($account,'choice',['option'=>'1'],$key,$g['version']);throw new RuntimeException('Token reutilizado aceito');}catch(InvalidArgumentException){persistenceCheck(true,'Mesmo token com escolha diferente bloqueado');}
 }
 // These branches use only the disposable save; no catalogue/identity/real account is modified.
 $g=$r->load($account);$g['stage']='resumo';$save->invoke($r,$g);$key=bin2hex(random_bytes(32));RequestMetrics::reset();$start=hrtime(true);$market=$r->command($account,'offers',[],$key,$g['version']);$times['offers']=['elapsed_ms'=>(hrtime(true)-$start)/1e6,'components'=>RequestMetrics::read()];$marketRetry=$r->command($account,'offers',[],$key,$g['version']);persistenceCheck($market===$marketRetry,'Ofertas e pesos não são sorteados novamente');persistenceCheck(($market['market_draw']['schema']??0)===2,'Mercado novo é persistido');
 $key=bin2hex(random_bytes(32));RequestMetrics::reset();$start=hrtime(true);$a=$r->command($account,'transfer',['accept'=>'no'],$key,$market['version']);$times['season_change']=['elapsed_ms'=>(hrtime(true)-$start)/1e6,'components'=>RequestMetrics::read()];$b=$r->command($account,'transfer',['accept'=>'no'],$key,$market['version']);persistenceCheck($a===$b&&$a['season']===$market['season']+1,'Virada de temporada exatamente uma vez');
 $other=Database::connect();$other->exec('SET SESSION innodb_lock_wait_timeout=1');$other->beginTransaction();try{$lock=$other->prepare('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE');$lock->execute([$account]);throw new RuntimeException('Lock concorrente não bloqueou');}catch(PDOException $e){persistenceCheck(($e->errorInfo[1]??0)===1205,'Segunda conexão não grava sob lock da primeira');}finally{$other->rollBack();}
}finally{$db->discard();}
foreach($counts as $table=>$n)persistenceCheck((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$n,'Fixture revertida em '.$table);
file_put_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/persistencia-local.json',json_encode(['checks'=>$checks,'transport'=>'PDO MySQL local, transação externa, commits/rollbacks internos de teste suprimidos e rollback externo final','concurrency'=>'Contenção real de lock em duas conexões e retries sequenciais; sem dois POSTs HTTP simultâneos confirmados','real_data'=>'Contagens preservadas; nenhuma gravação de fixture confirmada','times'=>$times],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações PDO/MySQL local; rollback concluído; sem commits de dados.\n";
