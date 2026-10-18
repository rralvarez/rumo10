<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$definition=ParanaenseTrial::reference()['definitions']['pr-a1'];$checks=[];
foreach(['reversed_home','unknown_club','missing_relegation','same_barrier_dependency','overlapping_branches'] as $case){
    $c=$definition;
    if($case==='reversed_home'){[$r,$h,$a]=$c['state_engine']['league']['fixtures'][0];$c['state_engine']['league']['fixtures'][0]=[$r,$a,$h];}
    if($case==='unknown_club')$c['state_engine']['league']['fixtures'][0][1]='not_a_participant';
    if($case==='missing_relegation')array_pop($c['state_engine']['barriers'][0]);
    if($case==='same_barrier_dependency')$c['state_engine']['barriers'][0][1]['home_phases']=['quartas'];
    if($case==='overlapping_branches')$c['state_engine']['barriers'][0][1]['entries']=array_slice($c['state_engine']['barriers'][0][0]['entries'],0,4);
    $rejected=false;
    try{Competition::referenceScope(['pr-a1'=>$c],function()use($c){$t=ConfiguredStateCompetition::create($c,$c['teams']);foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=1;$m['ag']=0;}unset($m);ConfiguredStateCompetition::next($t);});}
    catch(RuntimeException $e){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Contrato inválido aceito: '.$case);$checks[]=['case'=>$case,'rejected'=>true];
}
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/expansao-estadual/lote2/pr';file_put_contents($dir.'/contract-negatives.json',json_encode($checks,JSON_PRETTY_PRINT)."\n");echo 'OK: '.count($checks)." contratos negativos rejeitados.\n";
