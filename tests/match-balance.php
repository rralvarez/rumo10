<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$count=0;
function balance(bool $ok,string $label):void{global $count;$count++;if(!$ok)throw new RuntimeException($label);}
$g=Game::legacyCreate('Teste equilíbrio','São Paulo','SP','centroavante');$g['stage']='partida';$i=Game::currentIndex($g);$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;$g['matches'][$i]['clear_chance']=true;$g['matches'][$i]['away_overall']=30;
$o=Game::choices($g);$shot=Game::chance($g,$o[0]);$assist=Game::chance($g,$o[2]);balance($shot<65,'Iniciante não recebe 70%+ para marcar');balance($assist>$shot,'Companheiro livre é uma alternativa mais provável');
$elite=$g;foreach($elite['skills'] as &$s)$s['value']=90;unset($s);balance(Game::chance($elite,$o[0])>$shot+10,'Boa finalização aumenta chance de gol');
$tired=$g;$tired['states']['energia']=10;balance(Game::chance($tired,$o[0])<$shot,'Desgaste reduz chance');
$strong=$g;$strong['matches'][$i]['away_overall']=85;balance(Game::chance($strong,$o[0])<$shot,'Adversário forte dificulta finalização');
foreach(['normal','rapido','expresso'] as $mode)foreach(['handicap','treino','partida','resumo','oferta'] as $stage){$sample=$g;$sample['play_mode']=$mode;$sample['stage']=$stage;balance(!PlayMode::canChange($sample),'Modo nunca é alterável');try{Game::command($sample,'mode',['mode'=>'normal']);balance(false,'Aceitou mudança de modo');}catch(InvalidArgumentException){balance(true,'Comando antigo bloqueado');}}
echo "OK: $count verificações. Iniciante: gol $shot%; assistência $assist%.\n";
