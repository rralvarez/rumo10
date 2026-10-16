<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function executionCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function executionFixture(int $level=45,int $own=50,int $opp=50):array{
 $g=Game::legacyCreate('Fixture execução','São Paulo','SP','centroavante');$g['stage']='partida';$g['states']['risco_lesao']=0;foreach($g['skills'] as &$s)$s['value']=$level;unset($s);$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']='em_andamento';$m['minute']=35;$m['entry']=0;$m['exit']=90;$m['home_overall']=$m['home']===$g['club']?$own:$opp;$m['away_overall']=$m['away']===$g['club']?$own:$opp;MatchBalance::prepare($m,$g);return $g;
}
$randomizer=new Random\Randomizer(new Random\Engine\Mt19937(16042026));$rng=fn(int $min,int $max):int=>$randomizer->getInt($min,$max);$rows=[];
foreach([45,65,85] as $level)foreach([35,50,80] as $own)foreach([35,50,80] as $opp){$g=executionFixture($level,$own,$opp);$previous=0;
 foreach(['long','difficult','normal','clear','one_on_one'] as $kind){$o=['action'=>$kind==='one_on_one'?'drible':'chute','effects'=>[],'shot_kind'=>$kind];if(in_array($kind,['clear','one_on_one'],true))$o['clear_chance']=true;
  $p=Game::chance($g,$o);executionCheck($p>$previous,'Ordenação por qualidade da oportunidade');$previous=$p;$wins=0;
  for($n=0;$n<10000;$n++)$wins+=(int)Probability::roll($p,$rng)['success'];$observed=$wins/100;$tolerance=6*sqrt(($p/100)*(1-$p/100)/10000)*100+.02;executionCheck(abs($observed-$p)<=$tolerance,'Monte Carlo coerente com chance declarada');
  foreach(['normal','rapido','expresso'] as $mode){$copy=$g;$copy['play_mode']=$mode;executionCheck(Game::chance($copy,$o)===$p,'Mesmo cálculo por modo');}
  $better=$g;$better['skills']['finalizacao']['value']++;executionCheck(Game::chance($better,$o)>=$p,'Finalização monotônica');$weaker=$g;$i=Game::currentIndex($weaker);$side=$weaker['matches'][$i]['home']===$g['club']?'away_overall':'home_overall';$weaker['matches'][$i][$side]--;executionCheck(Game::chance($weaker,$o)>=$p,'Defesa menor não piora chance');
  $stronger=$g;$side=$side==='away_overall'?'home_overall':'away_overall';$stronger['matches'][$i][$side]++;executionCheck(Game::chance($stronger,$o)>=$p,'Time próprio melhor não piora chance');
  $rows[]=['level'=>$level,'own'=>$own,'opponent'=>$opp,'kind'=>$kind,'n'=>10000,'chance'=>$p,'successes'=>$wins,'observed_pct'=>$observed];
 }
}
$g=executionFixture();$i=Game::currentIndex($g);$g['matches'][$i]['clear_chance']=true;$o=Game::choices($g)[0];$p=Game::chance($g,$o);executionCheck($p>=30&&$p<=50,'Chance clara típica iniciante não colapsa');
$spent=$g;$spent['matches'][$i]['personal_expected_spent']=100;executionCheck(Game::chance($spent,$o)===$p,'Orçamento coletivo não impede conclusão já criada');
foreach([1,10000] as $draw){$copy=$g;$copy['active_situation']=['id'=>2015,'key'=>Scenes::key($copy),'snapshot'=>NarrativeCatalog::all()[2015]];$next=Game::command($copy,'choice',['option'=>0],false,fn($min,$max)=>$max===10000?$draw:50);$m=$next['matches'][$i];executionCheck($next['feedback']['chance']===$p,'UI e sorteio usam mesma chance');executionCheck($m['stats']['goals']===($draw===1?1:0),'Um gol apenas no sucesso');executionCheck($next['feedback']['probability_trace']['chance']===$p,'Rastreio persistível usa chance sorteada');}
$before=json_decode(file_get_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/baseline.json'),true);$comparison=[];foreach($before['cases'] as $case){$sample=executionFixture($case['level'],$case['own'],$case['opponent']);$sample['matches'][Game::currentIndex($sample)]['clear_chance']=true;$comparison[]=$case+['normal_after'=>Game::chance($sample,['action'=>'chute','effects'=>[]]),'clear_after'=>Game::chance($sample,Game::choices($sample)[0])];}
file_put_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/execucao.json',json_encode(['checks'=>$checks,'trials'=>count($rows)*10000,'rows'=>$rows,'comparison'=>$comparison],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações; ".(count($rows)*10000)." sorteios, 135 cenários, chance clara iniciante $p%.\n";
