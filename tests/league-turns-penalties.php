<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$failures=[];
function verify(bool $ok,string $msg):void{global $checks,$failures;$checks++;if(!$ok)$failures[]=$msg;}
function leagueFixture(string $id='en-2',int $round=24):array{
 $t=Competition::tournament($id);foreach($t['matches'] as &$m){if($m['round']<=$round){$m['status']='finalizada';$m['minute']=90;$m['hg']=1;$m['ag']=1;}}unset($m);
 $g=Game::legacyCreate('Fixture isolada','São Paulo','SP','meia');$g['official']=true;$g['club']=$t['teams'][0];$g['competition_id']=$id;$g['competitions']=[$id=>$t];$g['matches']=$t['matches'];$g['round']=$round;$g['stage']='fim_semana';return $g;
}
function syntheticGame(array $t,int $round):array{
 $g=leagueFixture();$g['competition_id']=$t['id'];$g['competitions']=[$t['id']=>$t];$g['matches']=$t['matches'];$g['club']=$t['teams'][0];$g['round']=$round;return $g;
}
$labels=[];
foreach([1,22,23,24,25,45,46] as $round){
 $g=leagueFixture('en-2',$round);$before=$g;$m=$g['matches'][Game::currentIndex($g)];
 verify(Competition::aggregateContext($g,$m)===null,"EN2 rodada$round não é volta eliminatória");
 verify(!PenaltyShootout::requiresShootout($g,$m),"EN2 rodada$round não exige pênaltis");
 verify(Game::choices($g)!==[],"EN2 rodada$round preserva narrativa");
 $label=Competition::phaseLabel($g);$labels[$round]=$label;
 verify(str_contains($label,$round<=23?'Primeiro turno':'Segundo turno'),"EN2 rodada$round turno correto");
 verify($g===$before,"EN2 rodada$round leitura não altera save");
 verify(!isset($m['tie']),"EN2 rodada$round sem chave eliminatória");
 $reload=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
 verify(!PenaltyShootout::requiresShootout($reload,$reload['matches'][Game::currentIndex($reload)]),"EN2 rodada$round save legado recarregado");
 if(in_array($round,[24,46],true)){
  try{$next=Game::command($reload,'choice',['option'=>0],true);verify($next['stage']!=='penaltis',"EN2 rodada$round continua sem disputa");
   verify(array_slice($next['matches'],0,count($g['matches']))===$g['matches'],"EN2 rodada$round histórico preservado");
   if($round===24)verify($next['round']===25,"EN2 continua para25");
  }catch(Throwable $e){verify(false,"Continuidade EN2 rodada$round: ".$e->getMessage());}
 }
}
$g=leagueFixture('en-2',46);$rows=Schedule::table($g['matches'],$g['competitions']['en-2']['teams']);
foreach($rows as $row){verify($row['played']===46 && $row['draws']===46 && $row['points']===46,'Classificação3/1/0 com46 empates preservados');}
foreach($g['matches'] as $m)verify(!PenaltyShootout::requiresShootout($g,$m),'Nenhuma das552 partidas regulares exige vencedor');
foreach([1,19,20,38] as $round){$g=leagueFixture('en-1',$round);$m=$g['matches'][Game::currentIndex($g)];verify(!PenaltyShootout::requiresShootout($g,$m),'EN1 liga sem pênaltis');verify(str_contains(Competition::phaseLabel($g),$round<=19?'Primeiro turno':'Segundo turno'),'EN1 turno correto');}
// Três e quatro turnos, com calendário integral explícito; sem regra global rodada/2.
foreach([3,4] as $turns){
 $teams=['A','B','C','D'];$t=['id'=>'fixture','format'=>'league','phase'=>'liga','phase_start'=>1,'phase_end'=>3*$turns,'teams'=>$teams,'groups'=>[$teams],'ties'=>[],'matches'=>[],'done'=>false];
 for($turn=0;$turn<$turns;$turn++)foreach(Schedule::generate($teams,false) as $m){$m['round']+=3*$turn;$m+=['phase'=>'liga','group'=>'1'];$m['status']='finalizada';$t['matches'][]=$m;}
 for($round=1;$round<=3*$turns;$round++){$g=syntheticGame($t,$round);$m=$g['matches'][Game::currentIndex($g)];verify(!PenaltyShootout::requiresShootout($g,$m),'Liga multi-turnos sem pênaltis');$expectedTurn=intdiv($round-1,3)+1;$expectedLabel=[1=>'Primeiro turno',2=>'Segundo turno'][$expectedTurn]??$expectedTurn.'º turno';verify(str_contains(Competition::phaseLabel($g),$expectedLabel),'Turno estrutural3/4 correto');}
 $asym=$t;array_pop($asym['matches']);$g=syntheticGame($asym,2);verify(str_starts_with(Competition::phaseLabel($g),'Fase regular'),'Calendário incompleto/assimétrico não inventa turno');
}
// Regra de liga não vira eliminatória por campos residuais ou nome de fase.
$g=leagueFixture('en-2',24);$t=&$g['competitions']['en-2'];$i=Game::currentIndex($g);$m=$g['matches'][$i];$t['ties']=[['a'=>$m['home'],'b'=>$m['away'],'rule'=>'penalties']];$g['matches'][$i]['tie']=0;
verify(!PenaltyShootout::requiresShootout($g,$g['matches'][$i]),'Chave residual não transforma liga em mata-mata');unset($t);
// Fases regulares que não se chamam liga, inclusive grupos posteriores.
foreach(['regular','quadrangular','hexagonal','split','clausura','intermedio','nacional'] as $phase){$g=leagueFixture('en-2',24);foreach($g['matches'] as &$m)$m['phase']=$phase;unset($m);$g['competitions']['en-2']['phase']=$phase;verify(!PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)]),'Fase regular'.$phase.' aceita empate');}
// Chaves explícitas legítimas, inclusive playoffs, vantagem, final única e fase arquivada.
foreach([['quartas',2,'penalties',true],['semifinal',2,'advantage',false],['final',1,'penalties',true],['acesso_preliminar',1,'advantage',false],['acesso_semifinal',2,'penalties',true]] as [$phase,$legs,$rule,$expected]){
 $t=['id'=>'fixture','format'=>'european','phase'=>'liga','phase_start'=>1,'phase_end'=>46,'teams'=>['A','B'],'groups'=>[],'ties'=>[],'matches'=>[],'done'=>false];Competition::knockout($t,[['A','B']],$phase,$legs,$rule);
 foreach($t['matches'] as &$m){$m['status']='finalizada';$m['minute']=90;$m['hg']=0;$m['ag']=0;}unset($m);
 $g=syntheticGame($t,$t['phase_end']);$m=$g['matches'][Game::currentIndex($g)];verify(PenaltyShootout::requiresShootout($g,$m)===$expected,'Chave'.$phase.' regra'.$rule.' respeitada');
 if($legs===2){$g['round']=$t['phase_start'];verify(!PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)]),'Ida não exige vencedor');$g['round']=$t['phase_end'];}
 $t['state_phase_ties'][$phase]=$t['ties'];$t['ties']=[];$t['phase']='outra';$g['competitions']['fixture']=$t;verify(PenaltyShootout::requiresShootout($g,$m)===$expected,'Regra arquivada da fase mantida');
}
// Mesmo par em chave de outra fase nao substitui a regra arquivada.
$t['state_phase_ties'][$phase][0]['rule']='advantage';$t['ties']=[['a'=>'A','b'=>'B','rule'=>'penalties']];$t['phase']='outra';$g['competitions']['fixture']=$t;
verify(!PenaltyShootout::requiresShootout($g,$m),'Regra arquivada nao e substituida pela chave atual');
$agg=Competition::aggregateContext($g,$m);verify(($agg['rule']??null)==='advantage','Agregado e motor leem a mesma regra de fase');
// Fase de grupos chamada segunda nao e mata-mata sem chave.
$g=leagueFixture('en-2',24);foreach($g['matches'] as &$x)$x['phase']='segunda';unset($x);$g['competitions']['en-2']['phase']='segunda';
verify(!PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)]),'Grupos segunda sem chave aceita empate');
$g=leagueFixture('en-2',24);$saved=$g;
try{Game::command($g,'shootout_start',[],true);verify(false,'Liga rejeita comando de inicio de penaltis');}catch(InvalidArgumentException $e){verify(true,'Liga rejeita comando de inicio de penaltis');}
verify($g===$saved,'Rejeicao nao modifica save de liga');
// A leitura nao remove disputas antigas ja registradas: requer reconciliacao especifica.
$g['shootout']=['status'=>'in_progress','kicks'=>[['scored'=>true]]];$g['stage']='penaltis';$saved=$g;
verify(!PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)]),'Disputa legada indevida e identificavel');
verify($g===$saved,'Nenhuma limpeza automatica de historico ou cobrancas');
// Regressao da competicao efetivamente relatada, com definicao do cache local do Hub.
$leagueTwo=json_decode('{"id": "en-4", "name": "English League Two", "tier": 2, "rules": {"note": "Participantes reconciliados; simulação em ida e volta. Datas, fases adicionais, playoffs, acesso e rebaixamento oficial ainda não modelados.", "system": "league", "relegations": 0}, "teams": ["Accrington Stanley-EN", "Barnet-EN", "Bristol Rovers-EN", "Cheltenham Town-EN", "Chesterfield-EN", "Colchester United-EN", "Crawley Town-EN", "Crewe Alexandra-EN", "Exeter City-EN", "Fleetwood Town-EN", "Gillingham-EN", "Grimsby Town-EN", "Newport County-EN", "Northampton Town-EN", "Oldham Athletic-EN", "Port Vale-EN", "Rochdale-EN", "Rotherham United-EN", "Salford City-EN", "Shrewsbury Town-EN", "Swindon Town-EN", "Tranmere Rovers-EN", "Walsall-EN", "York City-EN"], "family": "international", "format": "world", "groups": [["Accrington Stanley-EN", "Barnet-EN", "Bristol Rovers-EN", "Cheltenham Town-EN", "Chesterfield-EN", "Colchester United-EN", "Crawley Town-EN", "Crewe Alexandra-EN", "Exeter City-EN", "Fleetwood Town-EN", "Gillingham-EN", "Grimsby Town-EN", "Newport County-EN", "Northampton Town-EN", "Oldham Athletic-EN", "Port Vale-EN", "Rochdale-EN", "Rotherham United-EN", "Salford City-EN", "Shrewsbury Town-EN", "Swindon Town-EN", "Tranmere Rovers-EN", "Walsall-EN", "York City-EN"]], "source": "https://www.efl.com/clubs-and-competitions", "checked": "2026-10-14", "country": "EN", "edition": 2026, "baseline": 53, "season_label": "2026/27"}',true,512,JSON_THROW_ON_ERROR);
Competition::referenceScope(['en-4'=>$leagueTwo],function()use($leagueTwo){
 $t=Competition::tournament('en-4');$g=Game::legacyCreate('Fixture League Two','São Paulo','SP','centroavante');
 $g['official']=true;$g['competition_id']='en-4';$g['club']='Shrewsbury Town-EN';$g['season']=5;$g['stage']='fim_semana';
 foreach($t['matches'] as &$x){if($x['round']<=25){$x['status']='finalizada';$x['minute']=90;$x['hg']=1;$x['ag']=1;}}unset($x);
 $g['competitions']=['en-4'=>$t];$g['matches']=$t['matches'];$g['round']=25;
 verify($t['format']==='world'&&$t['phase_end']===46,'League Two usa formato world e46 rodadas');
 verify(!PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)]),'League Two returno não exige pênaltis');
 verify(str_contains(Competition::phaseLabel($g),'Segundo turno'),'League Two usa rótulo do segundo turno');
 // O placar e os nomes relatados, isolados sem alterar o calendario produtivo.
 $t['matches']=[Schedule::match(2,'Shrewsbury Town-EN','Bristol Rovers-EN')+['phase'=>'liga','group'=>'1'],Schedule::match(25,'Bristol Rovers-EN','Shrewsbury Town-EN')+['phase'=>'liga','group'=>'1']];
 foreach($t['matches'] as &$x){$x['status']='finalizada';$x['minute']=90;}unset($x);
 $t['matches'][0]['hg']=0;$t['matches'][0]['ag']=4;$t['matches'][1]['hg']=1;$t['matches'][1]['ag']=2;
 $g['competitions']['en-4']=$t;$g['matches']=$t['matches'];$before=$g;
 verify(Competition::aggregateContext($g,$g['matches'][1])===null,'League Two 0x4/1x2 não inventa classificado por5x2');
 verify($g===$before,'Leitura do caso relatado preserva placares');
});
echo json_encode(['checks'=>$checks,'failed'=>count($failures),'failures'=>$failures,'labels'=>$labels],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failures?1:0);
