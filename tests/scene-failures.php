<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function checkFailure(bool $ok,string $message):void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$authored=require dirname(__DIR__).'/database/scene-failures.php';
$base=Game::legacyCreate('Teste de respostas','São Paulo','SP','centroavante');
foreach(Scenes::catalog() as $id=>$scene){
    $g=$base;$g['stage']=$scene['stage'];
    if($g['stage']==='partida'){$i=Game::currentIndex($g);$g['matches'][$i]['minute']=70;$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;}
    $g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];$before=$g;
    $choices=Scenes::current($g)['options'];
    checkFailure($g===$before,'Consultar respostas não modifica a carreira.');
    foreach($choices as $index=>$choice){
        checkFailure(!empty($choice['failure_text']) && !str_contains($choice['failure_text'],'A tentativa não sai') && !str_contains($choice['failure_text'],'a intenção não teve'),'Falha sem resposta padrão: '.$id.':'.$index);
        if(isset($authored[$id][$index]))checkFailure($choice['failure_text']===$authored[$id][$index],'Desfecho específico: '.$id.':'.$index);
        if(isset($scene['options'][$index]['failure_text']))checkFailure($choice['failure_text']===$scene['options'][$index]['failure_text'],'Resposta contextual anterior preservada.');
    }
}
foreach([1160=>'entrevista',1183=>'rumor'] as $id=>$context){
    $g=$base;$g['stage']='fim_semana';$g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];
    $choice=Game::choices($g)[1];
    checkFailure(str_contains($choice['failure_text'],'comunicação do clube') && str_contains($choice['failure_text'],$context),'Consulta identifica responsável e motivo.');
    checkFailure($choice['effects']===['foco'=>5,'confianca_treinador'=>3],'Consulta preserva efeitos.');
    $failure=null;
    for($attempt=0;$attempt<100;$attempt++){$result=Game::command($g,'choice',['option'=>1]);if(!$result['feedback']['success']){$failure=$result;break;}}
    checkFailure($failure!==null,'Gerar uma falha para verificar o resultado exibido.');
    checkFailure($failure['feedback']['text']===$choice['text'].': '.$choice['failure_text'],'Resultado usa a resposta específica.');
    checkFailure(in_array($failure['feedback']['effects']['confianca'],[-2,-5],true),'Penalidade de confiança preservada.');
}
echo "OK: $checks verificações; falhas das 856 alternativas, consultas contextualizadas e efeitos preservados.\n";
