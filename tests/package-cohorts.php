<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';putenv('RUMOHUB_ENABLED=0');$report=['development'=>[],'leagues'=>[]];$checks=0;
function cohort(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
foreach(['fraca'=>6.0,'regular'=>6.6,'boa'=>7.2,'excepcional'=>8.1] as $label=>$rating){$growth=[];
 foreach(Rules::POSITIONS as $pos)for($run=0;$run<20;$run++){
  $g=Game::legacyCreate('Coorte sintética','São Paulo','SP',$pos);$g['stage']='resumo';$i=0;
  foreach($g['matches'] as &$m)if(in_array($g['club'],[$m['home'],$m['away']],true)){$m['status']='finalizada';$m['balance_version']=MatchBalance::VERSION;$m['stats']['minutes']=90;$m['stats']['rating']=$rating;$m['stats']['goals']=$label==='excepcional' && $pos==='centroavante'?1:0;$m['stats']['assists']=$label==='boa' && $pos==='meia'?(++$i%3===0?1:0):0;$m['stats']['tackles']=in_array($pos,['zagueiro','volante','lateral'],true) && $rating>7?2:0;}unset($m);
  $before=array_sum(array_column($g['skills'],'value'))/9;Progression::season($g);$after=array_sum(array_column($g['skills'],'value'))/9;$growth[]=$after-$before;$copy=$g;Progression::season($g);cohort($g===$copy,'Balanço de evolução idempotente');
 }
 $report['development'][$label]=['n'=>count($growth),'mean'=>array_sum($growth)/count($growth),'min'=>min($growth),'max'=>max($growth)];
}
cohort($report['development']['fraca']['mean']<=1,'Campanha fraca sem salto técnico');cohort($report['development']['regular']['mean']>=1 && $report['development']['regular']['mean']<=2,'Regular 1..2');cohort($report['development']['boa']['mean']>=2 && $report['development']['boa']['mean']<=3,'Boa 2..3');cohort($report['development']['excepcional']['mean']>=3 && $report['development']['excepcional']['mean']<=4,'Excepcional 3..4');
$teams=['weak', 'elite-1','elite-2','elite-3','elite-4','elite-5','elite-6','elite-7'];$strength=[40,70,73,75,78,80,83,85];
foreach([false,true] as $hero){$champions=$survival=$promotions=$gf=$ga=0;$runs=300;
 for($run=0;$run<$runs;$run++){$matches=Schedule::generate($teams);
  foreach($matches as &$m){$hi=array_search($m['home'],$teams,true);$ai=array_search($m['away'],$teams,true);[$h,$a]=TeamStrength::expectations($strength[$hi],$strength[$ai]);$personal=0;
   if($hero && ($hi===0 || $ai===0)){
    $own=$hi===0?'hg':'ag';$model=$m+['balance_version'=>MatchBalance::VERSION,'expected_goals'=>['hg'=>$h,'ag'=>$a],'entry'=>0,'exit'=>90];$m['hg']=$m['ag']=0;$from=0;
    foreach([15,35,45,60,70,80,90] as $to){if(in_array($from,[15,35,60,70,80],true)){$mean=$model['expected_goals'][$own];$available=max(0,$mean-($model['background_expected_spent']??0)-($model['personal_expected_spent']??0));$p=min($available,$mean*.6/7*.76);MatchBalance::book($model,$p);if(random_int(1,100000)<=round($p*100000)){$m[$own]++;$personal++;}}
     foreach(['hg','ag'] as $side)$m[$side]+=TeamStrength::goals(MatchBalance::collectiveMean($model,$side,'weak',$from,$to));$from=$to;
    }
   }else{$m['hg']=TeamStrength::goals($h);$m['ag']=TeamStrength::goals($a);}
   $m['status']='finalizada';if($hi===0){$gf+=$m['hg'];$ga+=$m['ag'];}elseif($ai===0){$gf+=$m['ag'];$ga+=$m['hg'];}
  }unset($m);$rows=Schedule::table($matches,$teams);$rank=array_search('weak',array_column($rows,'team'),true)+1;$champions+=(int)($rank===1);$survival+=(int)($rank<=6);$promotions+=(int)($rank<=2);
 }
 $report['leagues'][$hero?'with_protagonist':'control']=['seasons'=>$runs,'champions'=>$champions,'top_two'=>$promotions,'survival_top_six'=>$survival,'goals_per_game'=>$gf/($runs*14),'conceded_per_game'=>$ga/($runs*14)];
 cohort($champions<$runs*.05 && $promotions<$runs*.1,'Azarão não domina a elite');
}
$club='São Caetano-SP';$anchor=TeamStrength::initial($club);$other=Competition::catalog()['br-a']['teams'][0];$g=['club_strengths'=>[$club=>$anchor+30],'memberships'=>['br-a'=>[$club,$other]],'competitions'=>['br-a'=>['id'=>'br-a','format'=>'league','teams'=>[$club,$other],'lots'=>[$club=>0,$other=>1],'matches'=>[['home'=>$club,'away'=>$other,'hg'=>5,'ag'=>0,'phase'=>'liga','status'=>'finalizada']]]]];TeamStrength::nextSeason($g);cohort($g['next_club_strengths'][$club]<=$anchor+4,'Título não transforma clube fraco em elite');
$report['checks']=$checks;file_put_contents(dirname(__DIR__).'/storage/qa-package/cohorts-metrics.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\nOK: coortes em seis posições e 600 temporadas sintéticas de elite.\n";
