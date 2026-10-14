<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$g=Game::legacyCreate('Teste contexto','São Paulo','SP','meia');$g['stage']='fim_semana';$g['active_situation']=['id'=>1178,'key'=>Scenes::key($g)];$choice=Game::choices($g)[3];
if($choice['text']!=='Passar o tempo com um familiar' || !str_contains($choice['success_text'],'família') || !str_contains($choice['failure_text'],'família') || isset($choice['effects']['entrosamento']))throw new RuntimeException('Escolha familiar ambígua ou com efeito de equipe.');
$before=$g['states']['entrosamento'];$g=Game::command($g,'choice',['option'=>3]);
if($g['states']['entrosamento']!==$before || !str_contains($g['feedback']['text'],'família') || $g['feedback']['situation']!==1178)throw new RuntimeException('Resultado familiar incoerente.');
foreach([1151=>2,1158=>2,1166=>2,1173=>0,1175=>1,1193=>2] as $id=>$option){$g['stage']='fim_semana';$g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];$choice=Game::choices($g)[$option];if(isset($choice['effects']['entrosamento']) || !isset($choice['success_text']))throw new RuntimeException('Convívio pessoal afeta indevidamente o elenco.');}
$g['active_situation']=['id'=>1185,'key'=>Scenes::key($g)];$team=Game::choices($g)[0];if(!isset($team['effects']['entrosamento']) || !str_contains($team['success_text'],'companheiro'))throw new RuntimeException('Apoio ao companheiro perdeu seu contexto de elenco.');
echo "OK: escolhas familiares e pessoais não afetam entrosamento; mensagens contextualizadas; apoio ao elenco preservado.\n";
