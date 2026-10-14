<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$successes=0;$failures=0;$variants=0;
function finishCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$base=Game::legacyCreate('Teste finalização','São Paulo','SP','centroavante');$base['stage']='partida';$base['states']['risco_lesao']=0;
$i=Game::currentIndex($base);$base['matches'][$i]['status']='em_andamento';$base['matches'][$i]['minute']=15;$base['matches'][$i]['exit']=90;
$samples=[$base];
foreach(Scenes::catalog() as $id=>$s)if($s['stage']==='partida'){$sample=$base;$sample['active_situation']=['id'=>$id,'key'=>Scenes::key($sample)];$samples[]=$sample;}
foreach($samples as $g)foreach(Game::choices($g) as $index=>$choice)if($choice['action']==='chute'){
    $variants++;
    for($n=0;$n<40;$n++){
        $p=Game::chance($g,$choice);$next=Game::command($g,'choice',['option'=>$index]);$f=$next['feedback'];$m=$next['matches'][$i];
        finishCheck($f['chance']===$p,'Porcentagem corresponde ao sorteio da finalização.');
        finishCheck($m['stats']['shots']===1,'Uma finalização por tentativa.');
        finishCheck($m['stats']['goals']===($f['success']?1:0),'Todo chute bem-sucedido é gol; falha não marca.');
        finishCheck(($f['highlight']??null)===($f['success']?'goal':null),'Gol pessoal recebe destaque.');
        finishCheck(!($f['success'] && str_contains($f['text'],'A bola não entrou')),'Não existe sucesso sem gol no chute.');
        finishCheck($m[$m['home']===$g['club']?'hg':'ag']>=$m['stats']['goals'],'Placar inclui gol pessoal.');
        if($f['success'])$successes++;else $failures++;
    }
}
finishCheck($successes>0 && $failures>0,'Mantém gols e erros probabilísticos.');
echo "OK: $checks verificações em $variants alternativas de chute; $successes gols e $failures falhas.\n";
