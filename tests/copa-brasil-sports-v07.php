<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function ok(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}function rejects(callable $f):void{try{$f();}catch(RuntimeException $e){ok(true,'rejeitado');return;}throw new RuntimeException('Entrada inválida aceita.');}
$dir=dirname(__DIR__).'/Docs/mundializacao-v07/copa-brasil-executavel';if(!is_dir($dir))mkdir($dir,0777,true);
$g=NationalTrial::createWithCopaBrasil(854010);
WorldSeason::scope($g,function()use(&$g,$dir){
 $audit=CopaBrasilReference::audit(Competition::clubs());ok($audit['resolved_ids']===126&&$audit['missing_ids']===[],'126 identidades canônicas locais');
 $c=Competition::catalog()['br-copa'];$t=$g['competitions']['br-copa'];ok(count($t['matches'])===14,'primeira fase14jogos');
 foreach([['duplicate','teams'],['edition','edition'],['pot','sports_contract'],['rank','sports_contract']] as [$case,$_]){$bad=$c;$teams=$c['teams'];if($case==='duplicate')$teams[0]=$teams[1];if($case==='edition')$bad['edition']=2027;if($case==='pot')array_pop($bad['sports_contract']['pots']['A']);if($case==='rank')unset($bad['sports_contract']['rank']['CRB-AL']);rejects(fn()=>CopaBrasilCompetition::create($bad,$teams));}
 $phases=[];$start=hrtime(true);
 while(!$t['done']){$n=$t['phase_number'];$phase=$t['phase'];$matches=array_filter($t['matches'],fn($m)=>$m['phase']===$phase);$participants=array_unique(array_merge(array_column($matches,'home'),array_column($matches,'away')));$rule=CopaBrasilReference::phases()[$n];ok(count($participants)===$rule[1]&&count($matches)===$rule[2],'quantidades fase'.$n);
  foreach($participants as $club)ok($t['entry_phases'][$club]<=$n,'entrada respeitada');
  foreach($t['matches'] as &$m)if($m['status']!=='finalizada'){$m['status']='finalizada';$m['hg']=0;$m['ag']=0;}unset($m);
  CopaBrasilCompetition::next($t);$phases[]=['phase'=>$n,'participants'=>count($participants),'matches'=>count($matches)];
  if($n===2){foreach([0=>'Barra-SC',11=>'Confiança-SE',12=>'Ponte Preta-SP',23=>'Paysandu-PA'] as $tie=>$club){ok($t['ties'][$tie]['a']===$club,'REC grupos59/70/71/82 critério2 é mandante');}}
 }
 ok(count($t['matches'])===155&&count(array_unique($t['final_ranking']))===126,'copa completa');ok(CopaBrasilCompetition::verify($t),'reconstrução de todos sorteios/placares/pênaltis');
 $again=$t;CopaBrasilCompetition::next($again);ok($again===$t,'done idempotente');
 foreach(['home','score','duplicate','winner','draw','penalty'] as $case){$bad=$t;if($case==='home')[$bad['matches'][0]['home'],$bad['matches'][0]['away']]=[$bad['matches'][0]['away'],$bad['matches'][0]['home']];if($case==='score')$bad['matches'][0]['hg']=-1;if($case==='duplicate')$bad['matches'][1]=$bad['matches'][0];if($case==='winner')$bad['champion']=$t['final_ranking'][1];if($case==='draw')$bad['draws']['2'][0]=array_reverse($bad['draws']['2'][0]);if($case==='penalty')$bad['matches'][0]['penalties']=[3,3];ok(!CopaBrasilCompetition::verify($bad),'adulteração rejeitada '.$case);}
 $view=$g;$view['club']=$t['champion'];$view['competition_results']=['br-copa'=>CopaBrasilCompetition::result($t,$t['champion'])];ok(count(Trophies::collect($view))===1,'título apósfinal');$view['club']=$t['final_ranking'][1];$view['competition_results']=['br-copa'=>CopaBrasilCompetition::result($t,$view['club'])];ok(Trophies::collect($view)===[],'vice não recebe taça');
 ok(str_contains(CompetitionView::resultText(CopaBrasilCompetition::result($t,$t['final_ranking'][1]),$t['final_ranking'][1]),'chegou à final'),'vice recebe descrição correta');
 $firstLoser=$t['eliminated']['1'][0];ok(str_contains(CompetitionView::resultText(CopaBrasilCompetition::result($t,$firstLoser),$firstLoser),'eliminado na 1ª fase'),'eliminação usa fase real');
 $before=Game::summary($g);ok($before['goals']===0&&$before['assists']===0,'NPC não soma estatística própria');
 foreach(['CRB-AL'=>[1,11],'Flamengo-RJ'=>[2,7],'Vasco-AC'=>[1,12]] as $club=>[$minimum,$optional]){$forecast=OpportunityForecast::estimate($g,$club);$row=array_values(array_filter($forecast['competitions'],fn($c)=>$c['id']==='br-copa'))[0];ok($row['scheduled_minimum']===$minimum&&$row['potential_extra_games']===$optional,'projeção mínima por entrada e campanha opcional '.$club);}
 file_put_contents($dir.'/sports-contracts.json',json_encode(['checks'=>$GLOBALS['checks'],'phases'=>$phases,'matches'=>155,'result_type'=>'synthetic_zero_scores_to_exercise_shootouts_not_historical_results','elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'identity_audit'=>$audit,'national_renewals'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
});echo "OK: $checks verificações Copa do Brasil,126inscritos/9fases/155jogos.\n";
