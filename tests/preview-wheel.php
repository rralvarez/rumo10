<?php
// Prévia isolada da interface, sem conta real e sem acesso ao banco.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#^(/assets/(brand/)?[\w.-]+\.(css|js|svg|png)|/favicon\.(ico|svg))$#',$path) && is_file(dirname(__DIR__).'/public'.$path)) return false;
if($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
define('RUMO10_PREVIEW',true); require __DIR__.'/presentation.php';
$g=sampleGame(true); $g['stage']=match($path) {'/resumo'=>'resumo','/fim-semana'=>'fim_semana',default=>'oferta'}; $g['feedback']=null;
$g['offers']=[];
foreach(Rules::TEAMS as $i=>$name) $g['offers'][]=['club'=>$name,'tier'=>$i===7?2:1,'peso'=>$i===7?55:30,'selected'=>$i===7];
$path='/carreira'; $error=null; $_SESSION=['account'=>0,'csrf'=>'preview'];
function form(string $path,?array $g=null): void { echo '<form method="post" action="'.e($path).'">'; }
require dirname(__DIR__).'/app/view.php';
