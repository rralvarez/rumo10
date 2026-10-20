<?php
declare(strict_types=1);
if(!in_array('--local-rollback',$argv,true))exit("Use --local-rollback.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_ACHIEVEMENTS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');putenv('RUMO10_NEUTRAL_SIMULATION=1');putenv('RUMO10_ADMIN_TEST_DESIGNATIONS=1');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV')==='production'||config('DB_DATABASE')!=='rumohub'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local fixtures only.');
$db=new PDO('mysql:host='.config('DB_HOST').';port='.config('DB_PORT','3306').';dbname=rumohub;charset=utf8mb4',config('DB_USERNAME'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$r=new Repository($db);
$g=json_decode(file_get_contents(dirname(__DIR__).'/storage/qa/states27/all-available-npc-fixture.json'),true,512,JSON_THROW_ON_ERROR);$e=json_decode(file_get_contents(dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/all-available-sports.json'),true,512,JSON_THROW_ON_ERROR);if($g['id']!==876001||count($e['sports'])!==24)throw new RuntimeException('Requires the single audit snapshot.');$checks=0;
function proofAssert(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
$db->beginTransaction();try{
 proofAssert(!$r->query('SELECT id FROM rumo10_carreiras WHERE id=?',[$g['id']])->fetch(),'No occupied fixture overwrite');
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['audit-'.bin2hex(random_bytes(10)).'@example.invalid',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$account=(int)$db->lastInsertId();
 $r->query('INSERT INTO rumo10_carreiras(id,conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?,?)',[$g['id'],$account,'Atleta de teste','Localidade de teste','RS','meia',18,'resumo',$g['club']]);$r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)',[$g['id'],json_encode($g,JSON_THROW_ON_ERROR)]);$loaded=$r->load($account);
 foreach($e['sports'] as $uf=>$row){$id=$row['competition'];$t=$loaded['competitions'][$id];$c=$loaded['world_reference'][$id]['definition'];$valid=WorldSeason::scope($loaded,fn()=>$t['done']?StateResultProof::verify($c,$t):ProgrammedStateCompetition::verify($c,$t));proofAssert($valid,'Persisted proof '.$uf);proofAssert($t['done']===$row['done'],'Homologation unchanged '.$uf);}
 $before=$loaded;$blocked=false;try{StateQualification::recordMany($loaded,array_keys(StateQualification::policies()));}catch(RuntimeException $error){$blocked=true;}proofAssert($blocked&&$loaded===$before,'No partial candidacy from incomplete global season');
}finally{if($db->inTransaction())$db->rollBack();}
proofAssert(!$r->query('SELECT id FROM rumo10_carreiras WHERE id=?',[876001])->fetch(),'Fixture rolled back');
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/all-available-mysql.json',json_encode(['checks'=>$checks,'career_id'=>876001,'federations'=>24,'competitions'=>32,'matches'=>$e['matches'],'reload'=>true,'all_writes_rolled_back'=>true,'entries'=>0,'renewals'=>0,'scope'=>'NPC snapshot persistence and proof reconstruction; not full Game season'],JSON_PRETTY_PRINT));echo "OK: $checks persisted audit checks.\n";
