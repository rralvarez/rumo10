<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$choose=new ReflectionMethod(PlayMode::class,'option');$rows=[];$checks=0;
foreach([45,85] as $player)foreach([[40,80],[60,60],[80,40]] as [$own,$opponent]){
 $totals=['games'=>1000,'own_goals'=>0,'opponent_goals'=>0,'player_goals'=>0,'assists'=>0,'shots'=>0,'minutes'=>0,'wins'=>0,'expected_goals'=>0.];
 $sample=[];for($n=0;$n<1000;$n++){$g=Game::legacyCreate('Fixture equilíbrio','São Paulo','SP','centroavante');$g['stage']='partida';foreach($g['skills'] as &$s)$s['value']=$player;unset($s);foreach($g['states'] as $k=>&$v)if(str_starts_with($k,'risco_'))$v=0;unset($v);$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']='pre_jogo';$m['entry']=0;$m['exit']=90;$m['home_overall']=$m['home']===$g['club']?$own:$opponent;$m['away_overall']=$m['away']===$g['club']?$own:$opponent;MatchBalance::prepare($m,$g);$side=$m['home']===$g['club']?'hg':'ag';$other=$side==='hg'?'ag':'hg';$totals['expected_goals']+=$m['expected_goals'][$side];unset($m);
  $guard=0;while($g['stage']==='partida'&&$guard++<60){$m=$g['matches'][$i];$advance=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'];$g=Game::command($g,$advance?'advance':'choice',$advance?[]:['option'=>$choose->invoke(null,$g,'protagonismo')],true);}
  if($guard>=60)throw new RuntimeException('Posse não termina');$m=$g['matches'][$i];if($m['stats']['goals']+$m['stats']['assists']>$m[$side])throw new RuntimeException('Produção individual duplicada');$sample[]=[$m[$side],$m[$other],(int)($m[$side]>$m[$other])];$checks++;$totals['own_goals']+=$m[$side];$totals['opponent_goals']+=$m[$other];$totals['wins']+=(int)($m[$side]>$m[$other]);foreach(['assists','shots','minutes'] as $key)$totals[$key]+=$m['stats'][$key];$totals['player_goals']+=$m['stats']['goals'];
 }
 $totals['own_goals_per_game']=$totals['own_goals']/1000;$totals['wins_fraction']=$totals['wins']/1000;$totals['confidence_95']=collectiveCI($sample);$rows[]=['player'=>$player,'own'=>$own,'opponent'=>$opponent]+$totals;
}
foreach([0,3] as $offset){if($rows[$offset]['wins_fraction']>.30||$rows[$offset+2]['wins_fraction']<.60||$rows[$offset]['own_goals_per_game']>$rows[$offset+2]['own_goals_per_game'])throw new RuntimeException('Clube fraco ganhou força irreal');$checks++;}
file_put_contents(dirname(__DIR__).'/Docs/pos-jogo-v06/equilibrio-coletivo.json',json_encode(['checks'=>$checks,'matches'=>6000,'policy'=>'Protagonismo via PlayMode::option em partidas completas do motor; RNG seguro independente; sem banco','rows'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações; 6000 partidas com personagem, posse e força 40×80, 60×60 e 80×40.\n";

function collectiveCI(array $sample):array {
 $n=count($sample);$x=array_column($sample,0);$y=array_column($sample,1);$mean=fn($a)=>array_sum($a)/count($a);$mx=$mean($x);$my=$mean($y);$vx=0.;$vy=0.;$cov=0.;foreach($sample as [$a,$b]){$vx+=($a-$mx)**2;$vy+=($b-$my)**2;$cov+=($a-$mx)*($b-$my);}$vx/=($n-1);$vy/=($n-1);$cov/=($n-1);
 $p=array_sum(array_column($sample,2))/$n;$z=1.95996398454;$den=1+$z*$z/$n;$mid=($p+$z*$z/(2*$n))/$den;$half=$z*sqrt($p*(1-$p)/$n+$z*$z/(4*$n*$n))/$den;
 $ratio=$my>0?$mx/$my:null;$se=$mx>0&&$my>0?sqrt(max(0,($vx/($mx*$mx)+$vy/($my*$my)-2*$cov/($mx*$my))/$n)):null;
 return ['method'=>'95%: wins Wilson; goal means CLT; paired goal ratio log delta method. Independent full matches, secure RNG.','own_goals_mean'=>[max(0,$mx-$z*sqrt($vx/$n)),$mx+$z*sqrt($vx/$n)],'opponent_goals_mean'=>[max(0,$my-$z*sqrt($vy/$n)),$my+$z*sqrt($vy/$n)],'wins_wilson'=>[$mid-$half,$mid+$half],'goal_ratio'=>$ratio,'goal_ratio_ci'=>$se===null?null:[$ratio*exp(-$z*$se),$ratio*exp($z*$se)]];
}
