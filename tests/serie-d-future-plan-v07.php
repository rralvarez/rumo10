<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function ok(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}
$p=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-d-future-proposal-v1.json'),true,512,JSON_THROW_ON_ERROR);
ok(!$p['runtime_enabled']&&!$p['selection_engine_implemented'],'direção aprovada não ativa política incompleta');
$a=$p['simulation_adaptations'];ok(array_sum($a['first_transition'])===96,'transição26para27fecha96');ok($a['following_transitions']['retained_d']+$a['following_transitions']['relegated_c']+$a['following_transitions']['federation']===96,'edições posteriores fecham96');ok($a['following_transitions']['d_to_c']===$a['following_transitions']['c_to_d'],'C24 não cresce silenciosamente');
$g=NationalTrial::createWithCopaBrasil(854031);WorldSeason::scope($g,function()use(&$g){foreach($g['competitions'] as &$t)Competition::finish($t,$g);unset($t);NationalPyramid::close($g);});$before=$g;$plan=SerieDFuturePlan::build($g);ok($g===$before,'plano não altera save');ok(SerieDFuturePlan::build($g)===$plan,'seleção proposta determinística');
ok(count($plan['residual_proposal'])===4&&!array_intersect($plan['residual_proposal'],array_merge(...array_values($g['national_pyramid_closures']['1']['draft_memberships']))),'quatro residuais sem duplicidade nem divisão superior');
ok($plan['current_missing_verified_entries']===68&&$plan['remaining_if_residual_proposal_accepted']===64,'não chama proposta de inscrição');ok(count($plan['federation_slots'])===27,'27 federações auditáveis');ok($plan['blocked']&&!$plan['memberships_applied'],'sem renovação parcial');
$bad=$g;$bad['competitions']['br-d']['matches'][0]['hg']++;try{SerieDFuturePlan::build($bad);throw new LogicException('resultado adulterado aceito');}catch(RuntimeException $e){ok(true,'prova adulterada rejeitada');}
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/copa-brasil-executavel';file_put_contents($dir.'/future-d-plan.json',json_encode(['checks'=>$checks,'scope'=>'isolated NPC rules fixture; not a second national season','plan'=>$plan],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações plano futuroD,sem inscrição/renovação.\n";
