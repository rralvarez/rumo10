<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);$checks=0;
function check(bool $value,string $message):void{global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function rejects(callable $action):bool{try{$action();return false;}catch(InvalidArgumentException $e){return true;}}
$reference=json_decode(file_get_contents($root.'/database/competition-references/br-al-2026.json'),true,512,JSON_THROW_ON_ERROR);
$policy=json_decode(file_get_contents($root.'/database/competition-references/br-d-admission-2026-v2.json'),true,512,JSON_THROW_ON_ERROR);
$catalog=Competition::catalog();$clubs=Competition::clubs();$results=[];
foreach(['al-a1'=>8,'al-entrada'=>6,'al-copa'=>12] as $id=>$count){$v=CompetitionParticipation::inspect($reference,$id,2026,$clubs);check(count($v['declared'])===$count,'REC nominal preservado');check(!$v['playable'],'nenhum percurso liberado por catálogo');$results[$id]=$v;}
check(in_array('Sporting-AL',$results['al-entrada']['declared'],true),'identidade confirmada pela FAF, sem criar clube novo');
check(count($results['al-entrada']['missing_clubs'])===0,'seis clubes B já identificados');
check(count($results['al-a1']['missing_clubs'])===4,'quatro ausentes na A');
check(count($results['al-copa']['missing_clubs'])===5,'cinco ausentes na copa');
check(count($results['al-copa']['excluded'])===1&&$results['al-copa']['excluded'][0]['club']==='Desportivo Aliança-AL','ato 003/26 aplicado apenas à copa 2026');
check(!in_array('Desportivo Aliança-AL',array_column($results['al-copa']['eligible'],'club'),true),'eliminado não recebe inscrição');
check(in_array('Desportivo Aliança-AL',array_column($results['al-entrada']['eligible'],'club'),true),'não extrapola sanção de copa à liga');
$future=CompetitionParticipation::inspect($reference,'al-copa',2027,$clubs);check($future['blockers']===['edition_not_verified']&&!$future['excluded'],'não extrapola sanção/REC para 2027');
$bad=$reference;$bad['competitions']['al-entrada']['participants'][]=['label'=>'Sporting FC'];check(rejects(fn()=>CompetitionParticipation::inspect($bad,'al-entrada',2026,$clubs)),'aliases não geram vagas duplicadas');
$bad=$reference;$bad['identities'][]=['club'=>'Clube inventado','labels'=>['Sporting FC'],'source'=>'faf-identity'];check(rejects(fn()=>CompetitionParticipation::inspect($bad,'al-entrada',2026,$clubs)),'alias ambíguo rejeitado');
$bad=$reference;$bad['competitions']['al-entrada']['participants'][0]['label']='Nome não comprovado';check(in_array('unresolved_identity:Nome não comprovado',CompetitionParticipation::inspect($bad,'al-entrada',2026,$clubs)['blockers'],true),'sem associação aproximada por texto');
$caps=array_fill_keys($reference['competitions']['al-entrada']['required_capabilities'],true);check(!CompetitionParticipation::inspect($reference,'al-entrada',2026,$clubs,$caps)['playable'],'capacidade declarada não instala referência');
check(count($policy['federation_ranking'])===27&&array_sum($policy['quotas'])===96,'27 federações, quotas somam 96');
check(array_sum(array_filter($policy['quotas'],fn($k)=>str_starts_with($k,'federation:'),ARRAY_FILTER_USE_KEY))===64,'64 vagas estaduais, não 96');
check($policy['quotas']['federation:SP']===4&&$policy['quotas']['federation:AL']===2,'quotas RNF, sem filler paulista');
check(SerieDAdmission::evaluate($policy,[],$clubs,[])['missing']===['edition_claim_ledger_not_verified'],'nova Série D bloqueada, sem fallback implícito');
// Admission mechanism exercised with clearly synthetic claims; NOT real 96-team certification.
$fixturePolicy=$policy;$fixturePolicy['status']='verified';$fixturePolicy['allowed_evidence_sources']=['synthetic-claim'];$fixturePolicy['allowed_context_sources']=['synthetic-context'];
$known=[];$claims=[];$n=0;
foreach($fixturePolicy['quotas'] as $quota=>$count)for($j=1;$j<=$count;$j++){
 $club='Synthetic '.++$n;$criterion=$quota==='relegated_c'?1:($quota==='retained_or_ranking'?3:2);
 $known[$club]=['country'=>'BR','uf'=>str_starts_with($quota,'federation:')?substr($quota,11):'AL'];
 $claims[]=['club'=>$club,'quota'=>$quota,'criterion'=>$criterion,'priority'=>$criterion,'rank'=>$j,'target_edition'=>2026,'finalized'=>true,'source_id'=>'synthetic-claim','evidence_hash'=>hash('sha256',$club)];
}
$context=['verified'=>true,'edition'=>2026,'source_id'=>'synthetic-context','upper_division_clubs'=>[]];
$result=SerieDAdmission::evaluate($fixturePolicy,$claims,$known,$context);check(!$result['blocked']&&count($result['accepted'])===96,'mecanismo fecha 96 apenas com ledger completo de teste');
$replay=SerieDAdmission::evaluate($fixturePolicy,$claims,$known,$context);check($replay===$result,'resultado idempotente');
$reordered=json_decode(json_encode($fixturePolicy),true);ksort($reordered['federation_ranking']);check(SerieDAdmission::evaluate($reordered,$claims,$known,$context)===$result,'ordem de chaves JSON não muda RNF');
$badContext=$context;$badContext['upper_division_clubs']=[$claims[0]['club']];$v=SerieDAdmission::evaluate($fixturePolicy,$claims,$known,$badContext);check($v['blocked']&&$v['rejected'][0]['reason']==='already_in_upper_national_division','sem duplicar divisão nacional');
$badKnown=$known;$index=4;$badKnown[$claims[$index]['club']]['uf']='AL';$v=SerieDAdmission::evaluate($fixturePolicy,$claims,$badKnown,$context);check($v['blocked']&&$v['rejected'][0]['reason']==='federation_mismatch','federação da vaga precisa coincidir');
$badClaims=$claims;$badClaims[0]['finalized']=false;check(SerieDAdmission::evaluate($fixturePolicy,$badClaims,$known,$context)['blocked'],'evidência não finalizada não qualifica');
$badClaims=$claims;$badClaims[0]['priority']=0;check(SerieDAdmission::evaluate($fixturePolicy,$badClaims,$known,$context)['blocked'],'não altera prioridade de critério');
$badClaims=$claims;$badClaims[0]['target_edition']=2027;check(SerieDAdmission::evaluate($fixturePolicy,$badClaims,$known,$context)['blocked'],'sem trocar edição da vaga');
$badClaims=$claims;$badClaims[]=$claims[0];check(count(SerieDAdmission::evaluate($fixturePolicy,$badClaims,$known,$context)['accepted'])===96,'duplicata não gera 97 inscrições');
$bad=$fixturePolicy;$bad['quotas']['federation:SP']=5;check(SerieDAdmission::evaluate($bad,$claims,$known,$context)['blocked'],'quota alterada não passa');
$bad=$fixturePolicy;$bad['edition']=2027;$badContext=$context;$badContext['edition']=2027;check(SerieDAdmission::evaluate($bad,$claims,$known,$badContext)['missing']===['unsupported_reference_edition'],'RNF/REC 2026 não vira regra oficial futura');
$bad=$fixturePolicy;$bad['capacity']=95;$bad['quotas']['retained_or_ranking']=27;check(SerieDAdmission::evaluate($bad,$claims,$known,$context)['blocked'],'capacidade nacional não é ajustada para fechar claims faltantes');
$badContext=$context;$badContext['source_id']='unapproved';check(SerieDAdmission::evaluate($fixturePolicy,$claims,$known,$badContext)['blocked'],'contexto nacional sem fonte permitida bloqueado');
check($catalog===Competition::catalog()&&!isset($catalog['al-a1'],$catalog['al-copa']),'catálogo não ampliado pela leitura');
$out=$root.'/Docs/mundializacao-v07/percurso-oficial';if(!is_dir($out))mkdir($out,0777,true);
file_put_contents($out.'/admission-tests.json',json_encode(['checks'=>$checks,'official_path_activated'=>false,'real_serie_d_ledger_complete'=>false,'synthetic_claims_only'=>count($claims),'alagoas'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "OK: $checks verificações de admissão; nenhuma competição oficial ativada.\n";
