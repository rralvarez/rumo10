<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$_SESSION=['account'=>999,'csrf'=>'fixture-only'];$path='/carreira';$error=null;$careerPlan='gratuito';
function form(string $path,?array $g=null):void{echo '<form action="'.e($path).'" method="post"><input type="hidden" name="csrf" value="fixture-only">';if($g)echo '<input type="hidden" name="version" value="'.$g['version'].'"><input type="hidden" name="key" value="'.str_repeat('a',64).'">';}
$g=Game::legacyCreate('Jogador de teste','São Paulo','SP','centroavante');$g['id']=999;$mode=$argv[1]??'decision';
if($mode==='market'){$g['stage']='resumo';$g=Game::command($g,'offers');}
elseif($mode!=='handicap'){$g['stage']='treino';Scenes::prepare($g);if($mode==='resolved')$g=Game::command($g,'choice',['option'=>0]);}
require dirname(__DIR__).'/app/view.php';
