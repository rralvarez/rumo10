<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');$base=$argv[1]??dirname(__DIR__);require $base.'/app/bootstrap.php';
function form(string $path,?array $g=null):void{echo '<form method="post" action="'.e($path).'">';}
$g=Game::legacyCreate('Fixture desempenho','São Paulo','SP','centroavante');$g['id']=999;$g['stage']='treino';Scenes::prepare($g);
$shot=$g;$shot['stage']='partida';$i=Game::currentIndex($shot);$shot['matches'][$i]['status']='em_andamento';$shot['matches'][$i]['minute']=15;$shot['matches'][$i]['entry']=0;$shot['matches'][$i]['exit']=90;$shot['matches'][$i]['clear_chance']=true;
$round=$shot;$round['matches'][$i]['status']='intervalo';$round['matches'][$i]['minute']=45;
$quick=$g;$quick['play_mode']='rapido';$market=$g;$market['stage']='resumo';$transfer=Game::command($market,'offers');
$cases=['choice'=>fn()=>Game::command($g,'choice',['option'=>0]),'shot'=>fn()=>Game::command($shot,'choice',['option'=>0]),'round'=>fn()=>Game::command($round,'advance'),'quick_match'=>fn()=>Game::command($quick,'simulate',['plan'=>'protagonismo']),'market'=>fn()=>Game::command($market,'offers'),'season'=>fn()=>Game::command($transfer,'transfer',['accept'=>'no']),'render'=>function()use($g,$base){$path='/carreira';$error=null;$_SESSION=['account'=>999,'csrf'=>'fixture'];ob_start();require $base.'/app/view.php';ob_end_clean();}];
$rows=[];foreach($cases as $name=>$fn){$samples=[];for($n=0;$n<12;$n++){$start=hrtime(true);$fn();$samples[]=(hrtime(true)-$start)/1e6;}sort($samples);$rows[$name]=['n'=>12,'p50_ms'=>$samples[5],'p95_ms'=>$samples[11],'min_ms'=>$samples[0],'max_ms'=>$samples[11]];}
echo json_encode(['environment'=>'PHP CLI, somente motor/render; sem SQL, IIS ou Hub; fixtures legadas pequenas; amostra descritiva','rows'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
