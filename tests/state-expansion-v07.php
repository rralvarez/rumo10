<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function assert57(bool $x,string $why):void{global $checks;$checks++;if(!$x)throw new RuntimeException($why);}function reject57(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException $e){assert57(true,'rejeitado');return;}throw new RuntimeException('Dado inválido aceito.');}
$start=hrtime(true);$g=StateExpansionTrial::create(857100,'expresso');$members=$g['memberships'];
WorldSeason::scope($g,function()use(&$g){foreach($g['competitions'] as &$t)Competition::finish($t,$g);unset($t);NationalPyramid::close($g);});
$t=$g['competitions']['ba-a1'];$c=$g['world_reference']['ba-a1']['definition'];
assert57(count($t['matches'])===48,'45 liga+2 semifinal+1 final');assert57(count($t['relegated'])===2,'mérito de descenso; não aplica membership');assert57(count($t['final_ranking'])===10,'ranking completo');
WorldSeason::scope($g,function()use($t,$c){assert57(StateResultProof::verify($c,$t),'prova de resultados Bahia');$initial=$t['initial_ranking'];$tail=array_slice($t['final_ranking'],4);assert57($tail===array_slice($initial,4),'5º-10º por primeira fase');
    foreach($t['matches'] as $m)if($m['phase']==='semifinal')assert57(array_search($m['home'],$initial,true)<array_search($m['away'],$initial,true),'mando semifinal por liga');
    $beforeFinal=$t;$beforeFinal['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['phase']!=='final'));$last=array_values(array_filter($t['matches'],fn($m)=>$m['phase']==='final'))[0];$finalists=[$last['home'],$last['away']];$best=ConfiguredStateCompetition::standings($beforeFinal,$finalists,null,true)[0]['team'];assert57($last['home']===$best,'mando final campanha liga+semifinal');
});
$rows=StateQualification::recordMany($g,['AL','PI','RO','BA']);$saved=$g;assert57(StateQualification::recordMany($g,['AL','PI','RO','BA'])==$rows&&$g===$saved,'recibos idempotentes');$audit=FutureSerieDAdmission::record($g);
assert57($rows['BA']['candidate_count']===3,'três candidatos BA após exclusões/repasse');assert57($audit['federation_missing_total']===64-array_sum(array_column($rows,'candidate_count')),'déficit mesmo save');assert57($g['memberships']===$members&&!$audit['memberships_applied']&&$audit['blocked'],'renovação bloqueada; sem inscrições');
$reordered=$g;ksort($reordered['state_qualifications']['2027']);assert57(FutureSerieDAdmission::record($reordered)===$audit,'ordem das UFs em JSON não altera ledger');
foreach(['score','home','ranking','contract','phase_history'] as $case){$bad=$g;
    if($case==='score')$bad['competitions']['ba-a1']['matches'][0]['hg']++;
    if($case==='home'){$m=&$bad['competitions']['ba-a1']['matches'][0];[$m['home'],$m['away']]=[$m['away'],$m['home']];unset($m);}
    if($case==='ranking')$bad['competitions']['ba-a1']['final_ranking']=array_reverse($t['final_ranking']);
    if($case==='contract')$bad['world_reference']['ba-a1']['sha256']='bad';
    if($case==='phase_history')$bad['competitions']['ba-a1']['state_phase_winners']['semifinal']=[];
    reject57(function()use(&$bad){StateQualification::recordMany($bad,['BA']);});
}
$defs=array_map(fn($p)=>$p['definition'],$g['world_reference']);assert57(StateExpansionTrial::validatesBudget($g,$defs,$members),'budget identificado');$defs['ba-a1']['teams'][]='fake';assert57(!StateExpansionTrial::validatesBudget($g,$defs,$members),'budget rejeita contrato alterado');
// Direct-confrontation points: matches against teams outside the tied set must not count.
$d=$c;$d['id']='synthetic-ba-tie';$d['teams']=['A','B','C','D'];$d['state_engine']['stages']=[['name'=>'liga','type'=>'league','return'=>false]];$d['state_engine']['tiebreakers']=['points','wins','gd','gf','h2h_points_group','h2h_gd_group','red','yellow','lots'];$d['state_engine']['relegation']=0;
Competition::referenceScope([$d['id']=>$d],function()use($d){$t=Competition::tournament($d['id']);$t['matches']=[];$t['lots']=array_flip(['B','A','C','D']);foreach([['A','B',1,0],['A','C',0,1],['A','D',2,0],['B','C',2,0],['B','D',1,0],['C','D',1,0]] as [$h,$a,$hg,$ag]){$m=Schedule::match(1,$h,$a);$m['status']='finalizada';$m['hg']=$hg;$m['ag']=$ag;$t['matches'][]=$m+['phase'=>'liga'];}$r=array_column(ConfiguredStateCompetition::standings($t),'team');assert57(array_search('A',$r)<array_search('B',$r),'confronto direto resolve empate A/B');});
$d['state_engine']['tiebreakers']=['points','h2h_points_group','h2h_gd_group','gd','lots'];
Competition::referenceScope([$d['id']=>$d],function()use($d){$t=Competition::tournament($d['id']);$t['matches']=[];$t['lots']=array_flip(['C','B','A','D']);foreach([['A','B',2,0],['B','C',2,0],['C','A',1,0],['A','D',1,0],['B','D',1,0],['C','D',1,0]] as [$h,$a,$hg,$ag]){$m=Schedule::match(1,$h,$a);$m['status']='finalizada';$m['hg']=$hg;$m['ag']=$ag;$t['matches'][]=$m+['phase'=>'liga'];}assert57(array_column(ConfiguredStateCompetition::standings($t),'team')===['A','B','C','D'],'empate triplo usa pontos e saldo internos, não comparação cíclica par a par');});
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/expansao-estadual/ba';if(!is_dir($dir))mkdir($dir,0777,true);file_put_contents($dir.'/sports.json',json_encode(['checks'=>$checks,'rows'=>$rows,'admission'=>$audit,'games'=>48,'national_renewals'=>0,'entries'=>0,'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'peak_memory_bytes'=>memory_get_peak_usage(true)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações Bahia; ".$rows['BA']['candidate_count']." candidatos adicionais; ".$audit['federation_missing_total']." vagas estaduais pendentes.\n";
