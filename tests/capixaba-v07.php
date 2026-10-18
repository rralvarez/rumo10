<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function escheck(bool $x,string $why):void{global $checks;$checks++;if(!$x)throw new RuntimeException($why);}function esreject(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){escheck(true,'rejeitado');return;}throw new RuntimeException('Inválido aceito.');}
$start=hrtime(true);$g=CapixabaTrial::create(857200,'expresso');$members=$g['memberships'];
WorldSeason::scope($g,function()use(&$g){foreach($g['competitions'] as &$t)Competition::finish($t,$g);unset($t);NationalPyramid::close($g);});
$t=$g['competitions']['es-a1'];$c=$g['world_reference']['es-a1']['definition'];
escheck(count($t['matches'])===59,'45+8+4+2 jogos');escheck(count($t['relegated'])===2,'mérito de descenso');escheck(count(array_unique($t['final_ranking']))===10,'ordenação operacional completa');
escheck(!in_array('SC Brasil Capixaba-ES',$t['teams'],true)&&in_array('Capixaba SC-ES',$t['teams'],true),'clubes distintos, sem alias indevido');
WorldSeason::scope($g,function()use($t,$c){escheck(StateResultProof::verify($c,$t),'prova integral');$initial=$t['initial_ranking'];$order=array_flip($initial);$w=$t['state_phase_winners']['quartas'];
    foreach($t['matches'] as $m)if($m['phase']==='quartas'){escheck(!isset($m['penalties']),'quartas sem pênaltis');$second=$m['round']===11;escheck($second?($order[$m['home']]<$order[$m['away']]):($order[$m['home']]>$order[$m['away']]),'mando quartas');}
    foreach([[0,3],[1,2]] as $tie=>[$a,$b]){$games=array_values(array_filter($t['matches'],fn($m)=>$m['phase']==='semifinal'&&$m['tie']===$tie));$actual=[$games[0]['home'],$games[0]['away']];$expected=[$w[$a],$w[$b]];sort($actual);sort($expected);escheck($actual===$expected,'cruzamento fixo semifinal');escheck($order[$games[1]['home']]<$order[$games[1]['away']],'mando semifinal inicial');}
    $final=array_values(array_filter($t['matches'],fn($m)=>$m['phase']==='final'));escheck($order[$final[1]['home']]<$order[$final[1]['away']],'mando final inicial');
});
$rows=StateQualification::recordMany($g,['AL','PI','RO','BA','ES']);$saved=$g;escheck(StateQualification::recordMany($g,['AL','PI','RO','BA','ES'])==$rows&&$g===$saved,'recibos idempotentes');$audit=FutureSerieDAdmission::record($g);
escheck($rows['ES']['quota']===2&&$rows['ES']['candidate_count']<=1&&$rows['ES']['unavailable']>=1,'só uma vaga do Capixabão, segunda indisponível');escheck($rows['BA']['candidate_count']===3,'Bahia preservada');escheck($audit['federation_missing_total']===64-array_sum(array_column($rows,'candidate_count')),'déficit mesmo save');escheck($g['memberships']===$members&&$audit['blocked']&&!$audit['memberships_applied'],'sem renovação/inscrições');
$p=CapixabaTrial::reference()['paths']['ES'];escheck(StateQualification::eligiblePositions($p,['champion'=>'X'],[])===[0],'campeão indicado');escheck(StateQualification::eligiblePositions($p,['champion'=>'X'],['X'])===[1],'vice somente por acesso C comprovado');escheck(StateQualification::eligiblePositions($p,['champion'=>'X'],['Y'])===[0],'retenção ou acesso alheio não gera repasse');
foreach(['home','ranking','penalties','contract','history','receipt'] as $case){$bad=$g;
    if($case==='home'){$m=&$bad['competitions']['es-a1']['matches'][0];[$m['home'],$m['away']]=[$m['away'],$m['home']];unset($m);}
    if($case==='ranking')$bad['competitions']['es-a1']['final_ranking']=array_reverse($t['final_ranking']);
    if($case==='penalties')foreach($bad['competitions']['es-a1']['matches'] as &$m)if($m['phase']==='quartas'){$m['penalties']=[5,4];break;}unset($m);
    if($case==='contract')$bad['world_reference']['es-a1']['sha256']='bad';
    if($case==='history')$bad['competitions']['es-a1']['state_phase_winners']['quartas']=[];
    if($case==='receipt')$bad['state_qualifications']['2027']['ES']['candidate_count']=2;
    esreject(function()use(&$bad){StateQualification::recordMany($bad,['ES']);});
}
$defs=array_map(fn($pin)=>$pin['definition'],$g['world_reference']);escheck(CapixabaTrial::validatesBudget($g,$defs,$members),'budget exato');unset($defs['es-a1']);escheck(!CapixabaTrial::validatesBudget($g,$defs,$members),'sem ampliar teto genérico');
// All draws demonstrate quarter advantage and saved shootout reconstruction.
Competition::referenceScope([$c['id']=>$c],function()use($c){$t=Competition::tournament($c['id']);$t['lots']=array_flip($t['teams']);while(!$t['done']){foreach($t['matches'] as &$m)$m['status']='finalizada';unset($m);ConfiguredStateCompetition::next($t);if($t['phase']==='semifinal')escheck($t['state_phase_winners']['quartas']===array_slice($t['initial_ranking'],0,4),'vantagem das quartas dispensa sorteio');}escheck(StateResultProof::verify($c,$t),'pênaltis salvos, sem novo resultado');});
// Points in direct confrontation are not goal difference; skip when three remain tied.
$d=$c;$d['id']='synthetic-es-tie';$d['teams']=['A','B'];$d['state_engine']['relegation']=0;$d['state_engine']['stages']=[['name'=>'liga','type'=>'league','return'=>true]];$d['state_engine']['tiebreakers']=['points','h2h_points_two','lots'];
Competition::referenceScope([$d['id']=>$d],function()use($d){$t=Competition::tournament($d['id']);$t['lots']=array_flip(['B','A']);foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=$m['home']==='A'?10:1;}unset($m);escheck(array_column(ConfiguredStateCompetition::standings($t),'team')===['B','A'],'confronto por pontos, não saldo');});
$d['teams']=['A','B','C','D'];$d['state_engine']['stages'][0]['return']=false;
Competition::referenceScope([$d['id']=>$d],function()use($d){$t=Competition::tournament($d['id']);$t['lots']=array_flip(['C','B','A','D']);$t['matches']=[];foreach([['A','B',2,0],['B','C',2,0],['C','A',1,0],['A','D',1,0],['B','D',1,0],['C','D',1,0]] as [$h,$a,$hg,$ag]){$m=Schedule::match(1,$h,$a);$m['hg']=$hg;$m['ag']=$ag;$m['status']='finalizada';$t['matches'][]=$m+['phase'=>'liga'];}escheck(array_column(ConfiguredStateCompetition::standings($t),'team')===['C','B','A','D'],'confronto direto ignorado em empate triplo');});
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/expansao-estadual/es';if(!is_dir($dir))mkdir($dir,0777,true);file_put_contents($dir.'/sports.json',json_encode(['checks'=>$checks,'rows'=>$rows,'admission'=>$audit,'games'=>59,'national_renewals'=>0,'entries'=>0,'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'peak_memory_bytes'=>memory_get_peak_usage(true)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações ES; ".$rows['ES']['candidate_count']." candidatos novos, ".$audit['federation_missing_total']." vagas estaduais pendentes.\n";
