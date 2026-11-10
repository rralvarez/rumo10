<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
// Exportador puro: não conecta ao banco nem ao Hub, não abre saves de usuários.
$out=[];
foreach(Scenes::catalog() as $id=>$s){
 if($id>=2000)continue;
 $g=Game::legacyCreate('Auditoria sintética','São Paulo','SP','meia');$g['stage']=$s['stage'];
 if($s['stage']==='partida'){$i=Game::currentIndex($g);$g['matches'][$i]['minute']=70;$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;}
 $g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];
 $out[]=['raw'=>$s,'resolved'=>Scenes::current($g)];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
