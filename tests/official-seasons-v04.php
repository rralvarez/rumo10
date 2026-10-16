<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$club=Competition::catalog()['am-entrada']['teams'][0];$g=Game::create('Fixture cinco temporadas','Manaus','AM','centroavante',$club);$g['id']=999;$g['play_mode']='normal';$g['stage']='treino';$rows=[];$checks=0;$start=hrtime(true);
for($season=1;$season<=5;$season++){
 foreach($g['season_competitions'] as $id){$t=$g['competitions'][$id]??Competition::tournament($id,$g['memberships'][$id]);$guard=0;
  while(!$t['done']&&$guard++<40){foreach($t['matches'] as &$m)if($m['status']!=='finalizada'){if(in_array($club,[$m['home'],$m['away']],true)){$m['hg']=$m['home']===$club?2:0;$m['ag']=$m['away']===$club?2:0;$m['status']='finalizada';$m['minute']=90;}else Competition::simulate($m,$g);}unset($m);Competition::nextPhase($t);}
  if(!$t['done'])throw new RuntimeException('Torneio não encerra');$g['competitions'][$id]=$t;$g['competition_results'][$id]=Competition::result($t,$club);$checks++;
 }
 $last=end($g['season_competitions']);$g['competition_id']=$last;$g['matches']=$g['competitions'][$last]['matches'];$g['stage']='resumo';Competition::completeWorld($g);
 $promoted=in_array($club,$g['next_memberships']['am-a1'],true);if(!$promoted)throw new RuntimeException('Campeão da B não chega à A');$checks++;
 $games=0;foreach($g['competitions'] as $t)$games+=count($t['matches']);$rows[]=['season'=>$season,'player_competitions'=>$g['season_competitions'],'next_am_a1'=>true,'world_matches'=>$games,'summary'=>Game::summary($g)];
 $g=Game::command($g,'offers');if(count($g['offers'])>3||!isset($g['market_draw']['stay']))throw new RuntimeException('Mercado inválido');$g=Game::command($g,'transfer',['accept'=>'no']);$checks++;
 if(!in_array('am-a1',$g['season_competitions'],true)||!$g['matches']||count($g['history'])!==$season)throw new RuntimeException('Próxima temporada sem A, calendário ou histórico');$checks++;
}
file_put_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/cinco-temporadas-oficiais.json',json_encode(['checks'=>$checks,'seasons'=>5,'elapsed_ms'=>(hrtime(true)-$start)/1e6,'fixture'=>'Somente memória: placares do clube controlados para validar campeão/acesso; demais jogos simulados; sem banco ou Hub','rows'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações; cinco temporadas oficiais simuladas com acesso AM B→A, mercado, permanência e histórico.\n";
