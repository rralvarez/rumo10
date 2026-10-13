<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$r=new Repository(Database::connect());$account=null;$checks=0;
function sceneDbCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
try{
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['cenas-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$account=(int)$r->db->lastInsertId();
 foreach([Game::legacyCreate('Teste cenas','São Paulo','SP','meia'),Game::create('Teste Bolívia','São Paulo','SP','meia','Bolívar-BO')] as $initial){
  $r->create($account,$initial,true);$g=$r->load($account);
  $key=bin2hex(random_bytes(32));$version=$g['version'];$g=$r->command($account,'handicap',[],$key,$version);
  sceneDbCheck(isset($g['story_seed'],$g['active_situation']),'Semente e situação ativa');sceneDbCheck($r->load($account)===$g,'Reload exato da situação');sceneDbCheck($r->command($account,'handicap',[],$key,$version)===$g,'Replay sem ressortear');
  for($n=0;$n<5;$n++){
   $action='choice';if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$action=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']?'advance':'choice';}
   $g=$r->command($account,$action,$action==='choice'?['option'=>1]:[],bin2hex(random_bytes(32)),$g['version']);sceneDbCheck($r->load($account)===$g,'JSON conserva eventos, memória e overall');
  }
  sceneDbCheck(!empty($g['seen_situations']['treino']),'Histórico da cena consumida salvo');
  $m=$g['matches'][Game::currentIndex($g)];sceneDbCheck(isset($m['home_overall'],$m['away_overall']),'Força congelada e persistida na partida');
 }
 echo "OK: $checks verificações MySQL de situações e overall, em carreira legada e Bolívia.\n";
}finally{
 if($account){$ids=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=? UNION SELECT carreira_id FROM rumo10_carreiras_arquivadas WHERE conta_id=?',[$account,$account])->fetchAll(PDO::FETCH_COLUMN);
 foreach($ids as $id){
  foreach(['rumo10_eventos_partida','rumo10_estatisticas'] as $table)$r->query("DELETE x FROM $table x JOIN rumo10_partidas p ON p.id=x.partida_id JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?",[$id]);
  $r->query('DELETE p FROM rumo10_partidas p JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?',[$id]);
  foreach(['rumo10_times','rumo10_ofertas'] as $table)$r->query("DELETE x FROM $table x JOIN rumo10_temporadas t ON t.id=x.temporada_id WHERE t.carreira_id=?",[$id]);
  foreach(['rumo10_temporadas','rumo10_decisoes','rumo10_atributos','rumo10_estados','rumo10_transferencias'] as $table)$r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
  $r->query('DELETE FROM rumo10_carreiras_arquivadas WHERE carreira_id=?',[$id]);$r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
 }$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}
}
