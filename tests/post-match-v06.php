<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function check(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
foreach(Rules::POSITIONS as $position)foreach(['normal','rapido','expresso'] as $mode){
 $g=Game::legacyCreate('Fixture','São Paulo','SP',$position);$g['stage']='partida';$g['play_mode']=$mode;$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['entry']=0;$m['exit']=90;$m['status']='pre_jogo';TeamStrength::snapshot($m,$g);MatchBalance::prepare($m,$g);unset($m);MatchExperience::begin($g);
 $before=$g['skills'];$seasonXp=$g['season_xp_earned'][$g['season']]??0;$guard=0;
 while($g['stage']==='partida'&&$guard++<100){$m=$g['matches'][$i];$advance=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'];$g=Game::command($g,$advance?'advance':'choice',$advance?[]:['option'=>0],true);}
 $m=$g['matches'][$i];$x=$m['experience'];check($x['complete'],'finished ledger');check($before===$x['before'],'start snapshot');check(array_sum($x['earned'])===($g['season_xp_earned'][$g['season']]??0)-$seasonXp,'actual grants only');check($x['after']===$g['skills'],'end snapshot');
 $saved=serialize($g);ob_start();MatchExperience::render($m);MatchExperience::render($m);$html=ob_get_clean();check($saved===serialize($g),'GET no rewards');check(str_contains($html,'minutos'),'minutes visible');
}
$g=Game::legacyCreate('Fixture','São Paulo','SP','meia');$g['stage']='partida';MatchExperience::begin($g);$g['skills']['passe']=['value'=>54,'xp'=>Rules::xpThreshold(54)-1];$before=$g['skills']['passe'];$earned=Evolution::gain($g,'passe',1);check($earned===1,'age multiplier round');check($g['skills']['passe']['value']===55,'skill increase');check($g['matches'][Game::currentIndex($g)]['experience']['earned']['passe']===1,'earned differs from spent balance');
$g['age']=35;$earned=Evolution::gain($g,'velocidade',50);check($earned===0,'aging physical grant remains zero');
$legacy=$g['matches'][Game::currentIndex($g)];unset($legacy['experience']);ob_start();MatchExperience::render($legacy);$html=ob_get_clean();check(str_contains($html,'não há delta'),'honest legacy message');
foreach(Rules::POSITIONS as $position)foreach(['rapido','expresso'] as $mode){
 $g=Game::legacyCreate('Fixture','São Paulo','SP',$position);$g['stage']='treino';$g['play_mode']=$mode;Scenes::prepare($g);$g=PlayMode::simulate($g,'equilibrio');
 check(!empty($g['pace_report']['matches']),'actual automatic mode report');foreach($g['pace_report']['matches'] as $m)check(!empty($m['experience']['complete']),'automatic mode retains actual per-match XP');
}
echo "OK: $checks verificações pós-jogo; seis posições e três modos, sem banco.\n";
