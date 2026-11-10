<?php
require dirname(__DIR__).'/app/bootstrap.php';putenv('DB_USERNAME=');putenv('RUMOHUB_ENABLED=0');putenv('RUMO10_DEVELOPMENT_V2=1');$rows=[];
foreach(['normal','rapido','expresso'] as $mode){$g=Game::create('Carreira Integrada','Sao Paulo','SP','meia','Mauá-SP');$g=Game::command($g,'handicap');$g['play_mode']=$mode;$steps=0;
while(!in_array($g['stage'],['resumo','aposentado'],true)&&++$steps<2500){
 if($g['stage']==='competicao_resumo')$g=Game::command($g,'continue');
 elseif($g['stage']==='treino'&&$mode!=='normal')$g=Game::command($g,'simulate',['plan'=>'evolucao']);
 elseif($g['stage']==='penaltis'){
  if($g['shootout']['status']==='completed')$g=Game::command($g,'shootout_finish');
  elseif(PenaltyShootout::isProtagonistTurn($g))$g=Game::command($g,'choice',['option'=>0],true);
  else $g=Game::command($g,'shootout_advance');
 }elseif($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$advance=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'];$g=Game::command($g,$advance?'advance':'choice',['option'=>0]);}
 else $g=Game::command($g,'choice',['option'=>0],true);
 $restored=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);if($restored!=$g)throw new RuntimeException('JSON mismatch');$g=$restored;
}
if($g['stage']!=='resumo')throw new RuntimeException('Season did not complete');$d=$g['player_development'];foreach(Rules::SKILLS as $k)if($d['received'][$k]!==$d['spent'][$k]+$g['skills'][$k]['xp'])throw new RuntimeException('Ledger mismatch');
$rows[]=['mode'=>$mode,'stage'=>$g['stage'],'age'=>$g['age'],'commands'=>$steps,'received'=>array_sum($d['received']),'spent'=>array_sum($d['spent']),'matches'=>Game::summary($g)['played']??null,'ledger_ok'=>true,'json_ok'=>true,'attributes'=>array_column($g['skills'],'value')];}
echo json_encode($rows,JSON_PRETTY_PRINT);
