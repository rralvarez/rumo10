<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$choose=new ReflectionMethod(PlayMode::class,'option');$rows=[];$checks=0;
foreach([45,85] as $player)foreach([[40,80],[60,60],[80,40]] as [$own,$opponent]){
 $totals=['games'=>80,'own_goals'=>0,'opponent_goals'=>0,'player_goals'=>0,'assists'=>0,'shots'=>0,'minutes'=>0,'wins'=>0,'expected_goals'=>0.];
 for($n=0;$n<80;$n++){$g=Game::legacyCreate('Fixture equilíbrio','São Paulo','SP','centroavante');$g['stage']='partida';foreach($g['skills'] as &$s)$s['value']=$player;unset($s);foreach($g['states'] as $k=>&$v)if(str_starts_with($k,'risco_'))$v=0;unset($v);$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']='pre_jogo';$m['entry']=0;$m['exit']=90;$m['home_overall']=$m['home']===$g['club']?$own:$opponent;$m['away_overall']=$m['away']===$g['club']?$own:$opponent;MatchBalance::prepare($m,$g);$side=$m['home']===$g['club']?'hg':'ag';$other=$side==='hg'?'ag':'hg';$totals['expected_goals']+=$m['expected_goals'][$side];unset($m);
  $guard=0;while($g['stage']==='partida'&&$guard++<60){$m=$g['matches'][$i];$advance=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'];$g=Game::command($g,$advance?'advance':'choice',$advance?[]:['option'=>$choose->invoke(null,$g,'protagonismo')],true);}
  if($guard>=60)throw new RuntimeException('Posse não termina');$m=$g['matches'][$i];if($m['stats']['goals']+$m['stats']['assists']>$m[$side])throw new RuntimeException('Produção individual duplicada');$checks++;$totals['own_goals']+=$m[$side];$totals['opponent_goals']+=$m[$other];$totals['wins']+=(int)($m[$side]>$m[$other]);foreach(['assists','shots','minutes'] as $key)$totals[$key]+=$m['stats'][$key];$totals['player_goals']+=$m['stats']['goals'];
 }
 $totals['own_goals_per_game']=$totals['own_goals']/80;$totals['wins_fraction']=$totals['wins']/80;$rows[]=['player'=>$player,'own'=>$own,'opponent'=>$opponent]+$totals;
}
foreach([0,3] as $offset){if($rows[$offset]['wins_fraction']>.30||$rows[$offset+2]['wins_fraction']<.60||$rows[$offset]['own_goals_per_game']>$rows[$offset+2]['own_goals_per_game'])throw new RuntimeException('Clube fraco ganhou força irreal');$checks++;}
file_put_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/equilibrio-coletivo.json',json_encode(['checks'=>$checks,'matches'=>480,'policy'=>'Protagonismo via PlayMode::option em partidas completas do motor; RNG seguro independente; sem banco','rows'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações; 480 partidas com personagem, posse e força 40×80, 60×60 e 80×40.\n";
