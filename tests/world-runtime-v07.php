<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/world-fixture-v07.php';
$checks=0;function check(bool $value,string $why):void{global $checks;$checks++;if(!$value)throw new RuntimeException($why);}
$base=Competition::catalog();$evidence=[];
foreach(Rules::POSITIONS as $n=>$position)foreach(['normal','rapido','expresso'] as $mode){$g=worldFixture(710000+$n,$position,$mode);$commands=0;
 while($g['stage']!=='resumo'){
  if(++$commands>2000)throw new RuntimeException('Loop');
  if($g['stage']==='treino'&&$mode!=='normal')$g=Game::command($g,'simulate',['plan'=>'equilibrio']);
  elseif($g['stage']==='competicao_resumo')$g=Game::command($g,'continue');
  elseif($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$g=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']?Game::command($g,'advance'):Game::command($g,'choice',['option'=>0]);}
  else $g=Game::command($g,'choice',['option'=>0]);
  check(Competition::catalog()===$base,'scope não vaza para outra carreira');
  $roundTrip=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);$reorder=function($v)use(&$reorder){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);return array_map($reorder,$v);};$g=$reorder($roundTrip);
  check(WorldSeason::scope($g,fn()=>Competition::catalog()['fx-1']['name'])==='Liga de teste — elite','referência resiste reordenação JSON');
 }
 $summary=Game::summary($g);check(count($g['competition_results'])===2,'liga e copa finalizadas');check($summary['games']>=1,'participação efetiva');check(count($g['world_completed'])===3,'mundo da fixture encerrado');
 $reward=$g['reward_ledger']??[];$g2=WorldSeason::scope($g,function()use($g){WorldSeason::next($g);return $g;});check(($g2['reward_ledger']??[])===$reward,'replay não concede XP');check($g2['competitions']===$g['competitions'],'replay não simula placar de novo');
 $forecast=WorldSeason::scope($g,fn()=>OpportunityForecast::estimate($g,$g['club']));check($forecast['guaranteed_games']===13&&$forecast['potential_extra_games']===2,'mínimo e mata-mata potencial separados');
 $evidence[]=['position'=>$position,'mode'=>$mode,'games'=>$summary['games'],'commands'=>$commands,'snapshot_bytes'=>strlen(json_encode($g))];
}
$g=worldFixture();putenv('RUMO10_WORLD_CALENDAR=0');try{Game::command($g,'choice',['option'=>0]);throw new RuntimeException('Flag ignorada');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'flag local'),'flag não converte save em legado');}
$legacy=Game::legacyCreate('Atleta de teste','São Paulo','SP','meia');check(Game::command($legacy,'handicap')['stage']==='treino','legado segue sem flag');
$counts=Trophies::counts([['kind'=>'champion'],['kind'=>'stage_title'],['title'=>'Legado']]);check($counts===['main'=>1,'stage'=>1,'legacy_unclassified'=>1],'totais de troféus separados');
$policy=json_decode(file_get_contents(dirname(__DIR__).'/database/serie-d-qualification-policy.json'),true);check(CompetitionQualification::evaluate($policy,[],[])['blocked'],'Série D incompleta bloqueada');
$policy=['id'=>'fixture-admission','version'=>'1','edition'=>2026,'target'=>'fixture-cup','country'=>'BR','capacity'=>2,'status'=>'verified','sources'=>['fixture source'],'quotas'=>['league'=>2],'allowed_evidence_sources'=>['fixture_result']];$claims=[];foreach(['A','B'] as $i=>$club)$claims[]=['club'=>$club,'priority'=>1,'rank'=>$i+1,'quota'=>'league','target_edition'=>2026,'finalized'=>true,'evidence_hash'=>hash('sha256',$club),'source_id'=>'fixture_result'];$known=['A'=>['country'=>'BR'],'B'=>['country'=>'BR']];$admission=CompetitionQualification::evaluate($policy,$claims,$known);check(!$admission['blocked'],'inscrição completa por evidência');check($admission===CompetitionQualification::evaluate($policy,$claims,$known),'inscrição idempotente');check(CompetitionQualification::evaluate($policy,[$claims[0],$claims[0]],$known)['blocked'],'duplicidade não preenche vaga');$claims[1]['target_edition']=2027;check(CompetitionQualification::evaluate($policy,$claims,$known)['blocked'],'edição errada não classifica');
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/runtime-tests.json',json_encode(['checks'=>$checks,'fixtures_only'=>true,'roles_and_modes'=>$evidence],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações, seis posições e três modos completos; nenhuma copa oficial liberada.\n";

