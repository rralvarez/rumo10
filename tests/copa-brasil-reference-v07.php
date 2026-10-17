<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function check(bool $ok,string $m):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($m);}
function rejects(callable $fn):void{try{$fn();}catch(RuntimeException $e){check(true,'rejeitado');return;}throw new RuntimeException('Não rejeitado');}
$r=CopaBrasilReference::reference();$global=CopaBrasilReference::audit(Competition::clubs());
check($global['annex_count']===126&&$global['criteria']===[1=>20,2=>4,3=>102],'126 por critério');
check($global['resolved_ids']===113&&count($global['missing_ids'])===13,'lacunas globais explícitas');
$g=NationalTrial::createABCDWithNordeste(853001);$original=$g;
$scoped=WorldSeason::scope($g,fn()=>CopaBrasilReference::audit(Competition::clubs()));
check($scoped['resolved_ids']===114&&count($scoped['missing_ids'])===12,'Penedense resolvido pelo complemento anterior');
check(!$scoped['runtime_allowed']&&$scoped['future']['capacity']===128,'sem falsa ativação nem 126 repetidos em 2027');
check(array_sum(array_column(CopaBrasilReference::phases(),2))===155,'155 partidas previstas, não disputadas');
$entry=[];$w=[];$offset=0;$phaseEvidence=[];
foreach(CopaBrasilReference::phases() as $phase=>[$count,$total,$matches,$legs]){
    // Synthetic IDs here ONLY test the capacity contract, never an official cup.
    $new=[];for($i=0;$i<$count;$i++){$club='synthetic-slot-'.++$offset;$entry[$club]=$phase;$new[]=$club;}
    CopaBrasilReference::validateEntry($phase,$w,$new,$entry);check(count($w)+count($new)===$total,'capacidade fase');
    $bad=$new;if($bad)$bad[0]='unregistered';else $bad=['unregistered'];rejects(fn()=>CopaBrasilReference::validateEntry($phase,$w,$bad,$entry));
    $all=array_merge($w,$new);$w=array_slice($all,0,intdiv($total,2));$phaseEvidence[]=['phase'=>$phase,'entrants'=>$count,'participants'=>$total,'matches'=>$matches,'legs'=>$legs,'type'=>'capacity_contract_only_not_gameplay'];
}
check($offset===126,'126 entradas sem reutilização');
WorldSeason::scope($g,function()use(&$g){foreach($g['competitions'] as &$t)Competition::finish($t,$g);unset($t);NationalPyramid::close($g);});
$closed=$g;$d=FutureSerieDAdmission::audit($g);$again=FutureSerieDAdmission::audit($g);
check($d===$again&&$g===$closed,'auditoria determinística, idempotente, sem gravação');
check($d['candidate_count']===28&&$d['missing_total']===68,'não copiar96 nem preencher artificialmente');
check($d['federation_missing_total']===64&&$d['residual_missing']===4,'déficit discriminado64+4');
check(count($d['federations'])===27&&$d['blocked']&&!$d['memberships_applied'],'27 federações e portão bloqueado');
check(count(array_unique(array_column($d['ledger'],'key')))===count($d['ledger']),'chaves distintas de evidências');
$recorded=FutureSerieDAdmission::record($g);$afterRecord=$g;check(FutureSerieDAdmission::record($g)===$recorded&&$g===$afterRecord,'ledger persistível e replay sem duplicação');
$bad=$g;$bad['future_d_admissions']['2027']['missing_total']=0;rejects(function()use(&$bad){FutureSerieDAdmission::record($bad);});
$g=$closed;
foreach($d['ledger'] as $row)if($row['state']==='not_selected')check(isset($row['reason']),'rejeição explicada');
$bad=$g;$bad['competitions']['br-d']['matches'][0]['hg']++;rejects(fn()=>FutureSerieDAdmission::audit($bad));
$bad=$g;$bad['competitions']['al-a1']['done']=false;rejects(fn()=>FutureSerieDAdmission::audit($bad));
$coverage=MandatoryTournamentCoverage::audit($g);check($coverage['clubs']['Ceará-CE']['br-copa']['required_uncovered'],'nova obrigação Ceará conhecida');
check($coverage['clubs']['CRB-AL']['br-copa']['required_uncovered'],'CRB não dispensado');
$bad=$g;unset($bad['competitions']['br-nordeste']);$coverage=MandatoryTournamentCoverage::audit($bad);check($coverage['clubs']['CRB-AL']['br-nordeste']['required_uncovered'],'membership sem torneio não cobre obrigação');
$bad=$g;$bad['season']=2;$coverage=MandatoryTournamentCoverage::audit($bad);check($coverage['clubs']['CRB-AL']['br-copa']['evidence']==='unassessed_not_exempt','anexo2026 não certifica 2027');
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/copa-brasil-admissao-d';if(!is_dir($dir))mkdir($dir,0777,true);
foreach(['copa-global'=>$global,'copa-pilot'=>$scoped,'future-d'=>$d,'mandatory-coverage'=>MandatoryTournamentCoverage::audit($g)] as $file=>$value)file_put_contents($dir.'/'.$file.'.json',json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
file_put_contents($dir.'/contracts.json',json_encode(['checks'=>$checks,'capacity_contract'=>$phaseEvidence,'copa_brasil_games_played'=>0,'national_renewals_applied'=>0,'input_fixture_replaced'=>false,'flags_publicly_activated'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo "OK: $checks verificações de anexo, contratos e candidaturas D sem inscrições artificiais.\n";
