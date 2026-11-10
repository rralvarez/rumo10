<?php
require dirname(__DIR__).'/app/bootstrap.php';
putenv('RUMOHUB_ENABLED=0');putenv('DB_USERNAME=');putenv('RUMO10_DEVELOPMENT_V2=1');
$n=0;function ck($b,$msg){global $n;if(!$b)throw new RuntimeException($msg);$n++;}
function fixture($mode){
$t=Competition::tournament('sp-a3');$teams=array_slice($t['teams'],0,4);Competition::knockout($t,[[$teams[0],$teams[1]],[$teams[2],$teams[3]]],'semifinal',2);
$t['initial_ranking']=$t['teams'];
foreach($t['matches'] as &$m){$m['status']='finalizada';$m['minute']=90;$m['hg']=0;$m['ag']=0;$m['entry']=0;$m['exit']=90;$m['home_overall']=60;$m['away_overall']=60;}unset($m);
$g=Game::legacyCreate('Teste Integrado','Sao Paulo','SP','meia');PlayerDevelopment::initialize($g,'independent-consolidation-v1');$g['club']=$teams[0];$g['official']=true;$g['competition_id']='sp-a3';$g['competitions']=['sp-a3'=>$t];$g['matches']=$t['matches'];$g['round']=$t['phase_end'];$g['stage']='partida';$g['play_mode']=$mode;return $g;
}
foreach(['normal','rapido','expresso'] as $mode){
$g=fixture($mode);$goals=array_map(fn($m)=>[$m['hg'],$m['ag']],$g['matches']);$skills=$g['skills'];
$first=$g['matches'][0];ck(!PenaltyShootout::requiresShootout($g,$first),'first leg does not trigger penalties');
$g=Game::command($g,'shootout_start');ck($g['version']===1,'start version increment');
$stable=$g['shootout'];$idx=Game::currentIndex($g);PenaltyShootout::start($g,$g['matches'][$idx]);ck($g['shootout']===$stable,'start replay preserves kicks');
$before=$g;try{PenaltyShootout::complete($g);throw new RuntimeException('early completion accepted');}catch(LogicException $e){ck($g===$before,'early finish unchanged');}
ob_start();PenaltyShootout::render($g);$html=ob_get_clean();ck(str_contains($html,'/penaltis/'),'functional penalty control rendered');
for($i=0;$i<100 && $g['shootout']['status']==='in_progress';$i++){
$v=$g['version'];if(PenaltyShootout::isProtagonistTurn($g))$g=Game::command($g,'choice',['option'=>0],true);else $g=Game::command($g,'shootout_advance');ck($g['version']===$v+1,'kick version increment');
$g=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
}
ck($g['shootout']['status']==='completed','shootout terminates');$winner=$g['shootout']['winner'];$g=Game::command($g,'shootout_finish');ck($g['stage']==='fim_semana','post shootout continuation');
$after=$g;PenaltyShootout::complete($g);ck($g===$after,'complete idempotence');ck($skills===$g['skills'],'shootout awards no ordinary goal XP');ck($goals===array_map(fn($m)=>[$m['hg'],$m['ag']],$g['matches']),'regulation score unchanged');
ck($g['competitions']['sp-a3']['ties'][0]['winner']===$winner,'bracket winner preserved');
$g=Game::command($g,'choice',['option'=>0],true);ck(in_array($g['stage'],['treino','competicao_resumo'],true),'career continues beyond penalties');
}
for($i=0;$i<20;$i++){$tie=['a'=>'A','b'=>'B'];$st=PenaltyShootout::simulateTie($tie,[],'final');ck($st['status']==='completed'&&$st['score'][$st['winner']]>$st['score'][$st['winner']==='A'?'B':'A'],'NPC legitimate winner');}
echo json_encode(['passed'=>$n,'modes'=>3,'npc_shootouts'=>20],JSON_PRETTY_PRINT);
