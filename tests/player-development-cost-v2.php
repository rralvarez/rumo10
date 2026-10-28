<?php
declare(strict_types=1);
$root=str_replace('\\','/',dirname(__DIR__));
spl_autoload_register(function($name)use($root){if(preg_match('/^[A-Za-z]+$/',$name)&&is_file($root.'/app/'.$name.'.php'))require $root.'/app/'.$name.'.php';});
function config(string $key,string $default=''):string{return in_array($key,['RUMOHUB_ENABLED','RUMOHUB_BENEFITS','RUMO10_ACHIEVEMENTS','RUMO10_WORLD_CALENDAR'],true)?'0':$default;}
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
set_error_handler(function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
$g=Game::legacyCreate('Pinned cost fixture','São Paulo','SP','meia');PlayerDevelopment::initialize($g,'cost-fixture');$g['player_development']['parameters']['cost']=['base'=>30,'divisor'=>5];if(PlayerDevelopment::cost($g,45)!==39||Rules::xpThreshold(45)!==29)throw new RuntimeException('Cost not pinned');$g['stage']='partida';MatchExperience::begin($g);$i=Game::currentIndex($g);if($g['matches'][$i]['experience']['development_cost']!==['base'=>30,'divisor'=>5])throw new RuntimeException('Cost not captured');Evolution::gain($g,'passe',3);MatchExperience::finish($g);ob_start();MatchExperience::render($g['matches'][$i]);$html=ob_get_clean();if(!str_contains($html,'/39 XP')||!str_contains($html,'/41 XP'))throw new RuntimeException('Report cost drift');echo 'OK:3 pinned cost checks'.PHP_EOL;
