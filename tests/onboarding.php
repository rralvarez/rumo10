<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function expectEntry(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function rejectEntry(callable $operation):void{try{$operation();}catch(InvalidArgumentException){expectEntry(true,'Rejeitada');return;}throw new RuntimeException('Operação inválida aceita');}
$origin=Onboarding::origin('sao caetano do sul','SP');
expectEntry($origin['name']==='São Caetano do Sul','Normaliza município');
rejectEntry(fn()=>Onboarding::origin('São Caetano do Sul','RJ'));
rejectEntry(fn()=>Game::create('Teste','Cidade inventada','SP','meia'));
foreach(['sp-a3','sp-a4'] as $division){
 $clubs=Onboarding::candidates($origin,$division);
 expectEntry(count($clubs)===3 && count(array_unique(array_column($clubs,'club')))===3,'Três propostas distintas');
 foreach($clubs as $c)expectEntry(in_array($c['club'],Competition::catalog()[$division]['teams'],true),'Divisão correta');
 expectEntry($clubs[0]['distance_km']<=$clubs[1]['distance_km'] && $clubs[1]['distance_km']<=$clubs[2]['distance_km'],'Ordem de proximidade');
 expectEntry($clubs[0]['club']===($division==='sp-a4'?'São Caetano-SP':'EC São Bernardo-SP'),'Clube vizinho em primeiro');
}
$badges=json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true,512,JSON_THROW_ON_ERROR);
foreach(array_merge(Competition::catalog()['sp-a3']['teams'],Competition::catalog()['sp-a4']['teams']) as $club){
 expectEntry(isset($badges[$club]),'Escudo de '.$club);
 expectEntry(getimagesize(dirname(__DIR__).'/public'.$badges[$club]['path'])!==false,'Imagem válida');
}
$g=Game::create('Teste de início','São Caetano do Sul','SP','centroavante');$g['id']=0;
expectEntry($g['starting'] && $g['club']==='Sem clube' && !$g['matches'],'Jogador antes do clube');
rejectEntry(fn()=>Game::command($g,'join',['option'=>'0']));
$g=Game::command($g,'handicap');
expectEntry($g['stage']==='clube_inicio' && $g['entry_division']===Onboarding::ENTRY[$g['handicap']],'Roleta abre divisão correspondente');
rejectEntry(fn()=>Game::command($g,'handicap'));
rejectEntry(fn()=>Game::command($g,'join',['option'=>'999']));
rejectEntry(fn()=>Game::command($g,'join',['option'=>'-1']));
$joined=Game::command($g,'join',['option'=>'2']);
expectEntry($joined['club']===$g['initial_candidates'][2]['club'] && $joined['competition_id']===$g['entry_division'],'Contrato com clube oferecido');
expectEntry($joined['stage']==='treino' && !$joined['starting'] && count($joined['matches'])>0,'Campeonato começa depois do contrato');
rejectEntry(fn()=>Game::command($joined,'join',['option'=>'0']));
echo "OK: $checks verificações de início, proximidade, escudos e escolhas inválidas.\n";
