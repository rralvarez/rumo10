<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function topCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$root=dirname(__DIR__);$rank=json_decode(file_get_contents($root.'/database/world-top250-2026.json'),true,512,JSON_THROW_ON_ERROR);$known=Competition::clubs();
topCheck(count($rank['clubs'])===250,'250 clubes pesquisados');$seen=[];
foreach($rank['clubs'] as $i=>$row){topCheck($row['rank']===$i+1,'Ordenação Opta');topCheck(isset($known[$row['club']]),'Clube do recorte integrado');topCheck(!isset($seen[$row['club']]),'Sem duplicação por alias');$seen[$row['club']]=true;}
$comps=json_decode(file_get_contents($root.'/database/top250-competitions-2026.json'),true,512,JSON_THROW_ON_ERROR);$pin=json_decode(file_get_contents($root.'/database/top250-participants-2026.json'),true,512,JSON_THROW_ON_ERROR);$countries=[];
foreach($comps as $c){
 $countries[$c['country']][]=$c['id'];topCheck($c['teams']===$pin[$c['id']]['teams'],'Participantes conciliados preservados');
 $t=Competition::tournament($c['id']);$busy=[];$counts=[];
 foreach($t['matches'] as $m){topCheck($m['home']!==$m['away'],'Sem autoconfronto');foreach(['home','away'] as $side){$key=$m['round'].'|'.$m[$side];topCheck(!isset($busy[$key]),'Sem partida simultânea');$busy[$key]=true;$counts[$m[$side]]=($counts[$m[$side]]??0)+1;}}
 foreach($c['teams'] as $club){topCheck(isset($known[$club]),'Clube existe');topCheck(in_array($c['id'],$known[$club]['competitions'],true),'Vínculo competição');topCheck($counts[$club]===(count($c['teams'])-1)*2,'Ida e volta completa');topCheck(TeamStrength::initial($club)>=20 && TeamStrength::initial($club)<=95,'Overall de referência válido');}
 Competition::finish($t);topCheck($t['done'] && in_array($t['champion'],$c['teams'],true),'Temporada conclui');$old=$t;Competition::finish($t);topCheck($old===$t,'Resultado concluído não ressorteado');
}
topCheck(count($countries)===9 && count($comps)===18,'Nove mercados, duas divisões');foreach($countries as $ids)topCheck(count($ids)===2,'Duas divisões em cada mercado');topCheck(TransferMarket::country('Vaduz-LI')==='LI','Vaduz mantém país do clube');topCheck(in_array('Vaduz-LI',Competition::catalog()['ch-1']['teams'],true),'Vaduz na liga suíça');
$players=json_decode(file_get_contents($root.'/database/players-top250-2026.json'),true,512,JSON_THROW_ON_ERROR);topCheck(count($players['players'])===250,'250 jogadores pesquisados');$ids=[];$last=100;
foreach($players['players'] as $i=>$p){topCheck($p['male_rank']===$i+1,'Ordem masculina');topCheck(!isset($ids[$p['id']]),'EA IDs únicos');$ids[$p['id']]=true;topCheck($p['overallRating']<=$last,'Overall decrescente');$last=$p['overallRating'];if($p['catalog_id']){$roster=ClubSquad::players($p['catalog_club']);$matches=array_values(array_filter($roster,fn($a)=>$a['id']===$p['catalog_id']));topCheck(count($matches)===1 && $matches[0]['origin']==='real','ID real conciliado');topCheck($matches[0]['rating_external_id']===$p['id'] && $matches[0]['overall']===$p['overallRating'],'Rating publicado preservado');}}
topCheck($players['catalog_matched']+$players['catalog_pending']===250,'Pendências declaradas');
topCheck($players['catalog_matched']===250 && $players['catalog_pending']===0,'250 atletas conciliados');
$hall=ClubSquad::players('Newcastle United-EN');topCheck(count(array_filter($hall,fn($p)=>$p['id']==='premierleague:487838' && $p['birthdate']==='2004-09-08'))===1,'Hall identificado na fonte oficial');topCheck(!array_filter($hall,fn($p)=>$p['id']==='espn:106139'),'Homônimo de outro clube excluído');
$forest=ClubSquad::players('Nottingham Forest-EN');topCheck(count(array_filter($forest,fn($p)=>$p['id']==='espn:146679' && $p['overall']===82))===1,'Muñoz em clube confirmado');
$coverage=json_decode(file_get_contents($root.'/database/top250-coverage-2026.json'),true,512,JSON_THROW_ON_ERROR);$badges=json_decode(file_get_contents($root.'/database/club-badges.json'),true,512,JSON_THROW_ON_ERROR);foreach($coverage['pending_badges'] as $p)topCheck(!isset($badges[$p['club']]),'Placeholder não é escudo');
$g=Game::command(Game::create('Teste Japão','São Paulo','SP','meia','Sanfrecce Hiroshima-JP'),'handicap');$g=array_replace($g,['play_mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'equilibrio']);topCheck($g['stage']==='resumo','J1 jogável até o fim');$g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'no']);topCheck($g['season']===2,'Segunda temporada');
echo "OK: $checks verificações top250 e novas ligas.\n";
