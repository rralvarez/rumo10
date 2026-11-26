<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function marketCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function seasonFixture(int $minutes,int $goals,int $assists=0,float $rating=7.5):array{
 $g=Game::legacyCreate('Fixture mercado','São Paulo','SP','centroavante');$g['season']=3;$template=$g['matches'][Game::currentIndex($g)];$g['matches']=[];$left=$minutes;
 for($i=0;$left>0;$i++){$m=$template;$played=min(90,$left);$left-=$played;$m['round']=$i+1;$m['status']='finalizada';$m['stats']=array_replace($m['stats'],['minutes'=>$played,'goals'=>$i===0?$goals:0,'assists'=>$i===0?$assists:0,'rating'=>$rating,'tackles'=>$i===0?5:0,'shots'=>$i===0?max(10,$goals):0]);$g['matches'][]=$m;}
 return $g;
}

$a=SeasonEvaluation::evaluate(seasonFixture(450,5));$b=SeasonEvaluation::evaluate(seasonFixture(2400,5));
marketCheck($a['per90']['goals']===1.0&&$b['per90']['goals']===.1875,'Taxas reais por minutos');
marketCheck($a['confidence']<$b['confidence'],'Amostra curta tem menos confiança');
$tiny=SeasonEvaluation::evaluate(seasonFixture(10,1));$long=SeasonEvaluation::evaluate(seasonFixture(1500,10,4,8.2));
marketCheck($tiny['score']<$long['score'],'Dez minutos não criam reputação mundial');

$g=seasonFixture(1500,10);
$g['history']=[['season'=>2,'club'=>'União do Bairro','evaluation'=>['score'=>.2]],['season'=>1,'club'=>'União do Bairro','evaluation'=>['score'=>.1]],['season'=>4,'evaluation'=>['score'=>99]]];
$eval=SeasonEvaluation::evaluate($g);
marketCheck(array_column($eval['history_weights'],'season')===[3] && $eval['wheel_uses_history']===false,'Roleta considera apenas última temporada');
marketCheck($eval['history_available']===2,'Histórico anterior válido permanece preservado sem temporadas futuras');

$def=$g;$def['position']='zagueiro';marketCheck(SeasonEvaluation::current($def)['score']!==SeasonEvaluation::current($g)['score'],'Posições têm avaliação própria');

$g=seasonFixture(1500,10);$before=$g;$draws=[];
for($n=0;$n<100;$n++){
 $window=$g;$window['stage']='resumo';$window=Game::command($window,'offers');$draw=$window['market_draw'];
 $closed=Game::command($window,'transfer',$n%2?['accept'=>'yes','option'=>$n%3]:['accept'=>'no']);
 marketCheck($closed['season']===$g['season']+1&&count($closed['history'])===1,'Fechamento isolado completo');
 $count=count($draw['offers']);$draws[$draw['quality_tier']]=($draws[$draw['quality_tier']]??0)+1;
 marketCheck($count===3,'Três propostas garantidas em carreira elegível');
 marketCheck(count(array_unique(array_column($draw['offers'],'club')))===3,'Clubes distintos');
 marketCheck(!in_array($g['club'],array_column($draw['offers'],'club'),true),'Clube atual excluído das propostas externas');
 marketCheck($draw['stay']['club']===$g['club'],'Permanência sempre disponível');
 foreach($draw['offers'] as $o)marketCheck(in_array($o['club'],Rules::TEAMS,true),'Sem clube inventado em carreira legada');
 $copy=json_decode(json_encode($draw),true);marketCheck($copy['id']===$draw['id']&&$copy['quality_tier']===$draw['quality_tier'],'Snapshot de mercado serializável');
}
marketCheck($g===$before,'Calcular não altera save');

$low=$g;$high=$g;foreach($low['skills'] as &$s)$s['value']=30;unset($s);foreach($high['skills'] as &$s)$s['value']=85;unset($s);
$fLow=OpportunityForecast::estimate($low,$g['club']);$fHigh=OpportunityForecast::estimate($high,$g['club']);
marketCheck($fHigh['minutes_percent']>$fLow['minutes_percent'],'Melhor atleta recebe projeção maior');
marketCheck($fHigh['expected_minutes']<=$fHigh['scheduled_games']*90,'Minutos limitados ao calendário');

$g['stage']='resumo';$g=Game::command($g,'offers');marketCheck(($g['market_draw']['schema']??0)===3,'Comando salva novo mercado schema 3');
$snapshot=$g;try{Game::command($g,'transfer',['accept'=>'yes','option'=>99]);marketCheck(false,'Índice forjado aceito');}catch(InvalidArgumentException){marketCheck($g===$snapshot,'Rejeição não muda save');}
$stay=Game::command($g,'transfer',['accept'=>'no']);marketCheck($stay['club']===$g['club']&&$stay['season']===$g['season']+1,'Ficar avança uma temporada');
marketCheck(isset($stay['history'][0]['evaluation'],$stay['history'][0]['market']['choice']),'Histórico preserva avaliação e escolha');

$official=$g;$official['official']=true;$official['club']=Competition::catalog()['br-b']['teams'][0];$official['memberships']=array_map(fn($c)=>$c['teams'],Competition::catalog());$official['season_competitions']=['br-b'];$official['competition_id']='br-b';
foreach($official['skills'] as &$s)$s['value']=74;unset($s);
foreach($official['matches'] as &$match){$match['home']=$official['club'];$match['away']=Competition::catalog()['br-b']['teams'][1];}unset($match);
$candidates=TransferMarket::pool($official,false);marketCheck(count($candidates)>8,'Cenários usam universo elegível amplo');
$poor=SeasonEvaluation::evaluate($official);$good=$poor;$poor['score']=0.12;$good['score']=1.05;
$sp=TransferInterest::scenarios($official,$candidates,$poor);$sg=TransferInterest::scenarios($official,$candidates,$good);
marketCheck(count($sp)===5&&count($sg)===5,'Cinco faixas de qualidade completas');
marketCheck($sp[0]['percent']>$sg[0]['percent']&&$sp[3]['percent']<$sg[3]['percent'],'Pesos dinâmicos pelo desempenho da última temporada');
foreach($candidates as $candidate)marketCheck(CompetitionContinuity::club($candidate['club'],$official),'Só destinos com continuidade');

$projection=$official;$projection['next_club_strengths'][$projection['club']]=78.4;$projectedDraw=TransferInterest::draw($projection);
marketCheck($projectedDraw['stay']['overall']===78.4,'Permanência usa a mesma referência da próxima temporada que as diferenças das propostas');

$extra=$official;$extra['memberships']['br-b'][]=$extra['memberships']['br-b'][0];
marketCheck(!CompetitionContinuity::status('br-b',$extra['memberships'])['valid'],'Calendário duplicado não habilita destino');

$items=[];foreach(TransferInterest::LABELS as $i=>$label)$items[]=['nome'=>$label,'peso'=>$i+1];$segments=Wheel::segments($items);
marketCheck(abs(array_sum(array_column($segments,'span'))-360)<1e-8,'Setores reais somam 360');
echo "OK: $checks verificações de avaliação da última temporada, roleta de 5 faixas, 3 propostas e compatibilidade.\n";
