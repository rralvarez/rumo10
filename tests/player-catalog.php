<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function squadCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$seen=[];$real=$fake=$rated=0;$index=ClubSquad::catalog();
squadCheck(count($index['clubs'])===count(Competition::clubs()),'Todos os clubes oficiais têm elenco');
foreach($index['clubs'] as $club=>$entry) {
 $players=ClubSquad::players($club);squadCheck(count($players)===$entry['total'] && count($players)>=24,'Quantidade '.$club);$keepers=0;$rr=$ff=$pp=0;
 foreach($players as $p) {
  squadCheck(!isset($seen[$p['id']]),'Identidade global única '.$p['id']);$seen[$p['id']]=true;
  squadCheck(in_array($p['position'],array_merge(['goleiro'],Rules::POSITIONS),true),'Posição válida');
  squadCheck($p['overall']>=20 && $p['overall']<=99,'Overall válido');
  squadCheck(in_array($p['origin'],['real','fictional'],true),'Origem explícita');
  if($p['position']==='goleiro')$keepers++;
  if($p['origin']==='real'){$rr++;$real++;squadCheck((str_starts_with($p['id'],'espn:') && str_starts_with($p['source'],'https://site.api.espn.com/')) || (str_starts_with($p['id'],'premierleague:') && str_starts_with($p['source'],'https://www.premierleague.com/')),'Real com fonte');}
  else {$ff++;$fake++;squadCheck($p['source']===null && $p['overall_origin']==='estimated_rumo10_v1','Fictício não usa nota publicada');}
  if($p['overall_origin']==='published_ea_fc27'){$pp++;$rated++;squadCheck(str_starts_with($p['rating_source'],'https://www.ea.com/') && isset($p['rating_external_id']),'Nota publicada com origem');}
 }
 squadCheck($keepers>=2,'Dois goleiros');squadCheck([$rr,$ff,$pp]===[$entry['real'],$entry['fictional'],$entry['published_ratings']],'Contadores por clube');
 squadCheck(ClubSquad::players($club)===$players,'Consulta não recria fictícios');
}
squadCheck([$real,$fake,$rated]===[$index['summary']['real_players'],$index['summary']['fictional_players'],$index['summary']['published_ratings']],'Totais');
$players=ClubSquad::players('Arsenal-EN');$saka=current(array_filter($players,fn($p)=>str_contains($p['name'],'Saka')));squadCheck($saka && $saka['overall_origin']==='published_ea_fc27','Cruzamento verificado Arsenal');
$g=Game::legacyCreate('Teste elenco','São Paulo','SP','meia');$g['club']='Arsenal-EN';$before=$g;ob_start();ClubSquad::render($g);$html=ob_get_clean();squadCheck(str_contains($html,'Bukayo Saka') && str_contains($html,'Publicado · FC 27'),'Elenco e notas visíveis');squadCheck($g===$before,'Render não muda carreira');
echo "OK: $checks verificações dos elencos; $real reais, $fake fictícios, $rated notas publicadas.\n";
