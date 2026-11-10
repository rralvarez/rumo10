<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';putenv('RUMOHUB_ENABLED=0');$checks=0;
function pkg(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$base=Game::legacyCreate('Fixture sem pessoa real','São Paulo','SP','centroavante');
$auditGame=Game::command($base,'handicap');$auditGame['play_mode']='rapido';$auditGame=Game::command($auditGame,'simulate',['plan'=>'coletivo']);$attempts=$auditGame['pace_report']['attempts'];
pkg(count($attempts)===$auditGame['pace_report']['decisions'],'Cada escolha automática tem trilha individual');
foreach($attempts as $attempt)if(!isset($attempt['chance'],$attempt['roll'],$attempt['rules']) || $attempt['roll']<1 || $attempt['roll']>10000)throw new RuntimeException('Roll automático sem auditoria.');
pkg(count($attempts)>0,'Simulação auditou decisões reais');
foreach([18,24,27,32,33,35,38,39] as $age)foreach(Rules::SKILLS as $skill){$states=[];foreach(['normal','rapido','expresso'] as $mode){$g=$base;$g['age']=$age;$g['play_mode']=$mode;Evolution::gain($g,$skill,20);$states[]=$g['skills'];}pkg($states[0]===$states[1] && $states[1]===$states[2],'XP independe do ritmo');}
foreach([33=>1,35=>2,38=>3,40=>3] as $age=>$loss){$g=$base;$g['age']=$age;$before=$g['skills'];Evolution::birthday($g);pkg($g['skills']['velocidade']['value']===$before['velocidade']['value']-$loss,'Queda física por faixa');$copy=$g;Evolution::birthday($g);pkg($g===$copy,'Aniversário idempotente');pkg($g['skills']['visao_jogo']===$before['visao_jogo'],'Experiência preservada');}
$g=$base;$g['stage']='partida';$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['home_overall']=50;$m['away_overall']=70;$m['status']='em_andamento';$m['entry']=0;$m['exit']=90;MatchBalance::prepare($m,$g);$m['clear_chance']=true;unset($m);
$options=Game::choices($g);$shot=Game::chance($g,$options[0]);$assist=Game::chance($g,$options[2]);pkg($assist>$shot && $shot>=30 && $shot<60,'Chance criada não sofre teto coletivo de 5–8%; assistência segue mais provável');
$elite=$g;foreach($elite['skills'] as &$s)$s['value']=90;unset($s);pkg(Game::chance($elite,$options[0])>$shot,'Finalização boa melhora chance');
foreach(['normal','rapido','expresso'] as $mode){$clone=$g;$clone['play_mode']=$mode;pkg(Game::chance($clone,$options[0])===$shot,'Mesma chance em todos os modos');}
$mm=$g['matches'][$i];$own=$mm['home']===$g['club']?'hg':'ag';MatchBalance::book($mm,.2);$background=MatchBalance::collectiveMean($mm,$own,$g['club'],0,90);pkg($background<$mm['expected_goals'][$own],'Produção coletiva compensa parcela pessoal');pkg($mm['balance_expected_contribution']>=.88 && $mm['balance_expected_contribution']<=1.12,'Influência individual limitada');
$g['stage']='competicao_resumo';$g['progression_version']=2;$g['matches'][$i]['status']='finalizada';$g['matches'][$i]['stats']['minutes']=90;
Progression::competition($g,'sp-a4',['champion'=>$g['club']]);$once=$g;Progression::competition($g,'sp-a4',['champion'=>$g['club']]);pkg($g===$once,'Título concede bônus só uma vez');
$g=$base;$g['competition_results']=['sp-a4'=>['name'=>'Paulista A4','champion'=>$g['club'],'titles'=>[]]];$items=Trophies::collect($g);pkg(count($items)===1,'Título rastreável');$g['stage']='aposentado';$g['id']=999;$g['retirement']=['reason'=>'fixture'];$summary=RetiredCareer::build($g);pkg($summary['trophies']===$items,'Troféu preservado na compactação');
foreach([['BR','AR',true],['BR','GY',false],['EN','TR',true],['JP','AU',true],['AU','NZ',false]] as [$a,$b,$same])pkg((FootballGeography::region($a)===FootballGeography::region($b))===$same,'Confederações explícitas');
pkg(TransferMarket::sportingLevel('br-a')===1 && TransferMarket::sportingLevel('sp-a3')===5 && TransferMarket::sportingLevel('en-2')===2,'Divisões explícitas');
$metrics=[];$n=15000;
foreach([[40,80],[60,60],[80,40]] as [$home,$away]){$w=$d=$gf=$ga=0;[$h,$a]=TeamStrength::expectations($home,$away);for($j=0;$j<$n;$j++){$x=TeamStrength::goals($h);$y=TeamStrength::goals($a);$w+=(int)($x>$y);$d+=(int)($x===$y);$gf+=$x;$ga+=$y;}$metrics[]=['home'=>$home,'away'=>$away,'n'=>$n,'wins'=>$w/$n,'draws'=>$d/$n,'goals_for'=>$gf/$n,'goals_against'=>$ga/$n];}
pkg($metrics[0]['wins']<.15 && $metrics[2]['wins']>.65,'Diferença de força relevante sem resultado fixo');pkg($metrics[0]['wins']>0,'Azarão ainda pode vencer');
if(!is_dir(dirname(__DIR__).'/storage/qa-package'))mkdir(dirname(__DIR__).'/storage/qa-package',0770,true);
file_put_contents(dirname(__DIR__).'/storage/qa-package/engine-metrics.json',json_encode(['rules'=>Rules::VERSION,'checks'=>$checks,'metrics'=>$metrics],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "OK: $checks verificações; 45.000 partidas Monte Carlo. Iniciante gol $shot%, assistência $assist%.\n";
echo json_encode($metrics,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
