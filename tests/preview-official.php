<?php
// Prévia isolada, somente GET; nenhuma conta ou carreira real é modificada.
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^/assets/[\w.-]+\.(css|js)$#',$path) && is_file(dirname(__DIR__).'/public'.$path))return false;
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$g=Game::create('Jogador de teste','São Paulo','SP','centroavante','São Caetano-SP');$g['id']=0;$g['version']=1;$g['stage']='treino';$g['feedback']=null;$error=null;$_SESSION=['account'=>0,'csrf'=>'preview'];
function form(string $path,?array $g=null):void{echo '<form method="post" action="'.e($path).'">';}
if($path==='/resultado'){$g['feedback']=['success'=>true,'class'=>'sucesso','text'=>'Ajudar e acalmar.','effects'=>['energia'=>-3,'confianca'=>5,'entrosamento'=>0]];$path='/carreira';}
elseif($path==='/nova'){$g=null;$path='/carreira/nova';$existingCareer=['id'=>0];}
elseif($path==='/classificacao'){foreach($g['matches'] as &$m)if($m['round']===1)Competition::simulate($m);unset($m);}
elseif($path==='/recap'){
 $id=$g['competition_id'];$t=&$g['competitions'][$id];Competition::finish($t);$g['matches']=$t['matches'];$g['competition_results'][$id]=Competition::result($t,$g['club']);$g['stage']='competicao_resumo';$path='/carreira';
}else $path='/carreira';
require dirname(__DIR__).'/app/view.php';
