<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function check57(bool $value,string $message):void {global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function reject57(callable $fn):void {try{$fn();}catch(RuntimeException $e){check57(true,'rejeição');return;}throw new RuntimeException('Contrato inválido aceito.');}
function finish57(array &$t):void {foreach($t['matches'] as &$m)if($m['status']!=='finalizada'){$m['status']='finalizada';$m['hg']=2;$m['ag']=0;}unset($m);ConfiguredStateCompetition::next($t);}
$base=StateBatchTrial::reference()['definitions']['pi-a1'];
$base['id']='synthetic-routing-57';$base['teams']=['A','B','C','D','E','F','G','H'];unset($base['reference_clubs']);
$base['state_engine']['version']=ConfiguredStateCompetition::ROUTED_VERSION;
$base['state_engine']['relegation']=0;
$base['state_engine']['stages']=[
    ['name'=>'liga','type'=>'league','return'=>false],
    ['name'=>'quartas','type'=>'knockout','selection'=>'initial','pairs'=>[[0,7],[1,6],[2,5],[3,4]],'home_basis'=>'initial','legs'=>1,'tie_rule'=>'penalties'],
    ['name'=>'semifinal','type'=>'knockout','selection'=>'routed','entries'=>array_map(fn($i)=>['from'=>'winner','phase'=>'quartas','index'=>$i],range(0,3)),'pairs'=>[[0,3],[1,2]],'home_basis'=>'initial','legs'=>2,'tie_rule'=>'penalties'],
    ['name'=>'final','type'=>'knockout','selection'=>'winners','pairs'=>[[0,1]],'home_basis'=>'initial','legs'=>2,'tie_rule'=>'penalties'],
];
$start=hrtime(true);
Competition::referenceScope([$base['id']=>$base],function()use($base){
    $t=Competition::tournament($base['id']);finish57($t);$initial=$t['initial_ranking'];
    // Upsets in the first and third ties must not cause bracket reseeding.
    foreach($t['matches'] as &$m)if($m['status']!=='finalizada'){$m['status']='finalizada';$m['hg']=($m['tie']%2===0)?0:2;$m['ag']=($m['tie']%2===0)?2:0;}unset($m);
    ConfiguredStateCompetition::next($t);$w=$t['state_phase_winners']['quartas'];
    foreach([[0,3],[1,2]] as $i=>[$a,$b]){$expected=[$w[$a],$w[$b]];$actual=$t['groups'][$i];sort($expected);sort($actual);check57($actual===$expected,'cruzamento fixo preservado após zebras');$order=array_flip($initial);check57($order[$t['groups'][$i][0]]<$order[$t['groups'][$i][1]],'mando independente do cruzamento');}
    while(!$t['done'])finish57($t);
    check57(StateResultProof::verify($base,$t),'resultado v2 reconstruído sem nova simulação');
    $reloaded=json_decode(json_encode($t,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);ksort($reloaded['state_phase_winners']);check57(StateResultProof::verify($base,$reloaded),'reordenação de chaves JSON preserva prova');
    $bad=$t;$bad['state_phase_winners']['quartas']=array_reverse($w);check57(!StateResultProof::verify($base,$bad),'histórico de chaves adulterado rejeitado');
    $bad=$t;$bad['adapter_version']=ConfiguredStateCompetition::VERSION;check57(!StateResultProof::verify($base,$bad),'versão divergente rejeitada');
    check57(count($t['matches'])===38,'28 liga + 4 quartas + 4 semifinais + 2 finais');
});
// Synthetic bye playoff: fixed winner order and reseeding are distinct contracts.
foreach(['winner','winner_initial'] as $mode){
    $c=$base;$c['state_engine']['stages'][1]['name']='playoff';$c['state_engine']['stages'][1]['pairs']=[[2,5],[3,4]];
    $c['state_engine']['stages'][2]['entries']=[['from'=>'initial','index'=>0],['from'=>'initial','index'=>1],['from'=>$mode,'phase'=>'playoff','index'=>0],['from'=>$mode,'phase'=>'playoff','index'=>1]];
    Competition::referenceScope([$c['id']=>$c],function()use($c,$mode){$t=Competition::tournament($c['id']);finish57($t);$rank=$t['initial_ranking'];foreach($t['matches'] as &$m)if($m['status']!=='finalizada'){$m['status']='finalizada';$m['hg']=$m['tie']===0?0:2;$m['ag']=$m['tie']===0?2:0;}unset($m);ConfiguredStateCompetition::next($t);$w=$t['state_phase_winners']['playoff'];if($mode==='winner_initial'){$order=array_flip($rank);usort($w,fn($a,$b)=>$order[$a]<=>$order[$b]);}check57($t['groups'][0]===[$rank[0],$w[1]],'cabeça de chave e regra de origem '.$mode);while(!$t['done'])finish57($t);check57(StateResultProof::verify($c,$t),'prova com bye '.$mode);});
}
foreach(['old_version','future_phase','league_winner','negative_index','missing_origin','duplicate_origin','unused_entry','duplicate_pair','string_index'] as $case){$c=$base;
    if($case==='old_version')$c['state_engine']['version']=ConfiguredStateCompetition::VERSION;
    if($case==='future_phase')$c['state_engine']['stages'][2]['entries'][0]['phase']='final';
    if($case==='league_winner')$c['state_engine']['stages'][2]['entries'][0]['phase']='liga';
    if($case==='negative_index')$c['state_engine']['stages'][2]['entries'][0]['index']=-1;
    if($case==='missing_origin')$c['state_engine']['stages'][2]['entries'][0]['index']=99;
    if($case==='duplicate_origin')$c['state_engine']['stages'][2]['entries'][1]=$c['state_engine']['stages'][2]['entries'][0];
    if($case==='unused_entry')array_pop($c['state_engine']['stages'][2]['pairs']);
    if($case==='duplicate_pair')$c['state_engine']['stages'][2]['pairs'][1]=[0,3];
    if($case==='string_index')$c['state_engine']['stages'][2]['pairs'][0][0]='0';
    Competition::referenceScope([$c['id']=>$c],fn()=>reject57(function()use($c){$t=Competition::tournament($c['id']);while(!$t['done'])finish57($t);}));
}
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/expansao-estadual';if(!is_dir($dir))mkdir($dir,0777,true);
file_put_contents($dir.'/routing.json',json_encode(['checks'=>$checks,'fixture'=>'synthetic_only','new_federations'=>0,'national_renewals'=>0,'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'peak_memory_bytes'=>memory_get_peak_usage(true)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "OK: $checks verificações de roteamento; somente fixtures sintéticas.\n";
