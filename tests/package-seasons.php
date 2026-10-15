<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';putenv('RUMOHUB_ENABLED=0');$choose=new ReflectionMethod(PlayMode::class,'option');$report=[];
foreach(['normal','rapido','expresso'] as $mode){$totals=['games'=>0,'goals'=>0,'assists'=>0,'growth'=>0,'seasons'=>30];
 for($run=0;$run<30;$run++){
  $g=Game::legacyCreate('Fixture estatística','São Paulo','SP',Rules::POSITIONS[$run%6]);$g['id']=1;$g['play_mode']=$mode;$g=Game::command($g,'handicap',[],true);$start=array_sum(array_column($g['skills'],'value'))/9;$guard=0;
  while($g['stage']!=='resumo' && $guard++<500){
   if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$action=$m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit']?'advance':'choice';}else$action='choice';
   $g=Game::command($g,$action,$action==='choice'?['option'=>$choose->invoke(null,$g,'equilibrio')]:[],true);
  }
  if($guard>=500)throw new RuntimeException('Temporada não concluiu.');$s=Game::summary($g);foreach(['games','goals','assists'] as $k)$totals[$k]+=$s[$k];$totals['growth']+=array_sum(array_column($g['skills'],'value'))/9-$start;
  foreach($g['matches'] as $m)if(in_array($g['club'],[$m['home'],$m['away']],true)){
   $own=$m['home']===$g['club']?'hg':'ag';if($m['stats']['goals']+$m['stats']['assists']>$m[$own])throw new RuntimeException('Estatísticas excedem placar coletivo.');
  }
 }
 $totals['mean_growth']=$totals['growth']/30;$totals['goals_per_game']=$totals['goals']/max(1,$totals['games']);$totals['assists_per_game']=$totals['assists']/max(1,$totals['games']);$report[$mode]=$totals;
}
$growth=array_column($report,'mean_growth');if(max($growth)-min($growth)>.45)throw new RuntimeException('Diferença injustificada de XP entre modos com mesma política.');
file_put_contents(dirname(__DIR__).'/storage/qa-package/seasons-metrics.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\nOK: 90 temporadas, seis posições, mesma política nos três ritmos e placar reconciliado.\n";
