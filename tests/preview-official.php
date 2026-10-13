<?php
// Prévia isolada, somente GET; nenhuma conta ou carreira real é modificada.
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^/assets/[\w./-]+\.(css|js|png|jpg|gif|webp)$#',$path) && is_file(dirname(__DIR__).'/public'.$path))return false;
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$g=Game::create('Jogador de teste','São Paulo','SP','centroavante','São Caetano-SP');$g['id']=0;$g['version']=1;$g['stage']='treino';$g['feedback']=null;$error=null;$_SESSION=['account'=>0,'csrf'=>'preview'];
function form(string $path,?array $g=null):void{echo '<form method="post" action="'.e($path).'">';}
if($path==='/internacional'){
    $g=Game::create('Jogador de teste','São Paulo','SP','meia','Bolívar-BO');$g['id']=0;$g=Game::command($g,'handicap');$g=Game::command($g,'choice',['option'=>1]);$g=Game::command($g,'choice',['option'=>0]);$g=Game::command($g,'advance');$g['feedback']=null;$path='/carreira';
}
if($path==='/api/cidades') {header('Content-Type: application/json; charset=utf-8');echo json_encode(array_values(array_column(array_filter(Onboarding::municipalities(),fn($m)=>$m['uf']===($_GET['uf']??'SP')),'name')),JSON_UNESCAPED_UNICODE);exit;}
if(in_array($path,['/clubes-se','/clubes-ro'],true)) {
    [$city,$uf]=$path==='/clubes-se'?['Aracaju','SE']:['Vilhena','RO'];
    $g=Game::create('Jogador de teste',$city,$uf,'centroavante');$g['id']=0;$g=Game::command($g,'handicap');$path='/carreira';
}
if(in_array($path,['/inicio','/clubes-iniciais'],true)){$g=Game::create('Jogador de teste','São Caetano do Sul','SP','centroavante');$g['id']=0;if($path==='/clubes-iniciais')$g=Game::command($g,'handicap');$path='/carreira';}
elseif($path==='/resultado'){$g['feedback']=['success'=>true,'class'=>'sucesso','text'=>'Ajudar e acalmar.','effects'=>['energia'=>-3,'confianca'=>5,'entrosamento'=>0]];$path='/carreira';}
elseif(in_array($path,['/nova','/carreira/nova'],true)){$g=null;$path='/carreira/nova';$existingCareer=['id'=>0];}
elseif($path==='/classificacao'){foreach($g['matches'] as &$m)if($m['round']===1)Competition::simulate($m);unset($m);}
elseif($path==='/recap'){
 $id=$g['competition_id'];$t=&$g['competitions'][$id];Competition::finish($t);$g['matches']=$t['matches'];$g['competition_results'][$id]=Competition::result($t,$g['club']);$g['stage']='competicao_resumo';$path='/carreira';
}elseif($path!=='/jogador') $path='/carreira';
require dirname(__DIR__).'/app/view.php';
