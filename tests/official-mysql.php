<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$r=new Repository(Database::connect());$account=null;$ids=[];$checks=0;
function dbCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
try {
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['oficial-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$account=(int)$r->db->lastInsertId();
 $r->create($account,Game::create('Teste Persistente','São Paulo','SP','meia','Água Santa-SP'));$g=$r->load($account);$first=$g['id'];$ids[]=$first;
 dbCheck($g['competition_id']==='sp-a2' && count($g['season_competitions'])===2,'Clube inscrito no estadual e nacional');
 $key=bin2hex(random_bytes(32));$g=$r->command($account,'handicap',[],$key,0);dbCheck($r->command($account,'handicap',[],$key,0)===$g,'Replay oficial');
 $guard=0;
 while($g['stage']!=='resumo' && $guard++<1000) {
  $action=$g['stage']==='competicao_resumo'?'continue':'choice';
  if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$action=$m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit']?'advance':'choice';}
  $g=$r->command($account,$action,$action==='choice'?['option'=>'1']:[],bin2hex(random_bytes(32)),$g['version']);
  dbCheck($r->load($account)===$g,'Reload exato');
 }
 dbCheck($g['stage']==='resumo' && count($g['competition_results'])===2,'Ano completo persistido');
 $g=$r->command($account,'offers',[],bin2hex(random_bytes(32)),$g['version']);$offerSnapshot=$g;
 dbCheck($r->load($account)===$g,'Proposta salva');$g=$r->command($account,'transfer',['accept'=>'yes'],bin2hex(random_bytes(32)),$g['version']);dbCheck($g['season']===2 && count($g['history'])===1,'Transferência e histórico');
 $saved=$g;$r->create($account,Game::create('Nova Teste','Santos','SP','zagueiro','Jabaquara-SP'),true);$new=$r->load($account);$ids[]=$new['id'];
 dbCheck($new['id']!==$first && count($r->archives($account))===1,'Nova carreira arquiva sem apagar');
 try{$r->restore($account+999999,$first);dbCheck(false,'Isolamento de arquivo');}catch(InvalidArgumentException){dbCheck(true,'Arquivo isolado por conta');}
 $r->restore($account,$first);dbCheck($r->load($account)===$saved,'Restaura carreira integralmente');
 $r->restore($account,$new['id']);dbCheck($r->load($account)===$new,'Nova carreira também restaurável');
 echo "OK: $checks verificações MySQL oficiais; estadual/nacional completos, replay, transferência, arquivo e restauração.\n";
}finally {
 foreach($ids as $id) {
  $r->query('DELETE FROM rumo10_carreiras_arquivadas WHERE carreira_id=?',[$id]);
  foreach(['rumo10_decisoes','rumo10_atributos','rumo10_estados'] as $table)$r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
  $r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
 }
 if($account)$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);
}
