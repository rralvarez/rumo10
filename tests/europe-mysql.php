<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
function nextForeign(array $offers):array{foreach($offers as $o)if($o['international'])return $o;throw new RuntimeException('Sem proposta internacional.');}
$r=new Repository(Database::connect());$account=null;$checks=0;
function sceneDbCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
try{
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['cenas-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$account=(int)$r->db->lastInsertId();
 $g=Game::command(Game::create('Teste mercado DB','São Paulo','SP','meia','Flamengo-RJ'),'handicap');
 $g=array_replace($g,['play_mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'coletivo']);
 foreach($g['skills'] as &$skill)$skill['value']=64;unset($skill);$g['season_skill_baseline']=60;
 foreach($g['competitions'] as &$t)foreach($t['matches'] as &$m)if(in_array($g['club'],[$m['home'],$m['away']],true)){$m['stats']['minutes']=90;$m['stats']['rating']=7.3;}unset($t,$m);
 $g['matches']=$g['competitions'][$g['competition_id']]['matches'];
 $r->create($account,$g);unset($g);$g=$r->load($account);
 $key=bin2hex(random_bytes(32));$v=$g['version'];$g=$r->command($account,'offers',[],$key,$v);
 sceneDbCheck(count(array_filter($g['offers'],fn($o)=>$o['international']))===4,'Pool internacional salvo');
 sceneDbCheck($r->load($account)===$g,'Reload dos dados de proposta');
 sceneDbCheck($r->command($account,'offers',[],$key,$v)===$g,'Replay da proposta conserva sorteio');
 // Destino de teste escolhido entre as propostas internacionais realmente elegíveis.
 $selected=nextForeign($g['offers']);foreach($g['offers'] as &$offer)$offer['selected']=$offer['club']===$selected['club'];unset($offer);
 $json=json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
 $r->query('UPDATE rumo10_universos SET estado_json=? WHERE carreira_id=?',[$json,$g['id']]);
 $g=$r->load($account);$key=bin2hex(random_bytes(32));$v=$g['version'];$g=$r->command($account,'transfer',['accept'=>'yes'],$key,$v);
 sceneDbCheck($g['club']===$selected['club'] && TransferMarket::country($g['club'])!=='BR','Transferência externa salva');
 sceneDbCheck($g['history'][0]['club']==='Flamengo-RJ' && $g['season']===2,'História e temporada preservadas');
 sceneDbCheck($r->load($account)===$g,'Novo campeonato recarrega');
 sceneDbCheck($r->command($account,'transfer',['accept'=>'yes'],$key,$v)===$g,'Replay não transfere novamente');

 echo "OK: $checks verificações MySQL do mercado internacional e transferência.\n";
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
