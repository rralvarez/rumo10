<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function careerCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$g=Game::legacyCreate('Teste histórico','São Paulo','SP','meia');$g['stage']='treino';
$g['history']=[['season'=>1,'club'=>'Clube A','games'=>20,'goals'=>8,'assists'=>4,'minutes'=>1400,'next_club'=>'Clube B'],['season'=>2,'club'=>'Clube B','games'=>18,'goals'=>3,'assists'=>9,'minutes'=>1200,'next_club'=>'Clube A']];
$g['season']=3;$g['club']='Clube A';$g['teams'][0]='Clube A';foreach($g['matches'] as &$m){if($m['home']===Rules::TEAMS[0])$m['home']='Clube A';if($m['away']===Rules::TEAMS[0])$m['away']='Clube A';}unset($m);
$i=Game::currentIndex($g);$g['matches'][$i]['status']='finalizada';$g['matches'][$i]['stats']=array_replace(Schedule::stats(),['goals'=>2,'assists'=>1,'minutes'=>90,'rating'=>7.5]);
$g['matches'][$i+1]['stats']['goals']=99; // Outro clube nunca conta como gols do personagem.
$before=$g;$s=CareerStats::collect($g);
careerCheck($s['totals']===['games'=>39,'goals'=>13,'assists'=>14,'minutes'=>2690],'Total histórico + atual');
$clubs=array_column($s['clubs'],null,'club');careerCheck($clubs['Clube A']['goals']===10 && $clubs['Clube A']['assists']===5,'Retorno ao mesmo clube agrega');careerCheck($clubs['Clube B']['goals']===3 && $clubs['Clube B']['assists']===9,'Clube anterior preservado');
careerCheck(count($s['seasons'])===3 && $s['seasons'][2]['current'],'Temporadas separadas');careerCheck($g===$before,'Consulta não modifica carreira');
ob_start();CareerStats::render($g);$html=ob_get_clean();careerCheck(str_contains($html,'data-career-stat="goals">13') && str_contains($html,'14</strong>'),'Totalizador visível');
$g['history'][0]['club']='<script>alert(1)</script>';ob_start();CareerStats::render($g);$html=ob_get_clean();careerCheck(!str_contains($html,'<script>'),'Escaping');
$g=Game::create('Teste início','São Paulo','SP','meia');$s=CareerStats::collect($g);careerCheck(!$s['clubs'] && $s['totals']['goals']===0,'Sem clube não inventa passagem');
$g=Game::command(Game::legacyCreate('Teste contrato','São Paulo','SP','meia'),'handicap');$g=Game::command($g,'mode',['mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'coletivo']);$before=CareerStats::collect($g)['totals'];
$g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'yes']);careerCheck(CareerStats::collect($g)['totals']===$before,'Contrato não zera nem duplica números');
echo "OK: $checks verificações do histórico por clube e carreira.\n";
