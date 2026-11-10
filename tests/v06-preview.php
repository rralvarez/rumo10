<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);putenv('RUMOHUB_ENABLED=0');putenv('RUMO10_ACHIEVEMENTS=0');require dirname(__DIR__).'/app/bootstrap.php';
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/bootstrap.min.css"><link rel="stylesheet" href="/assets/game.css"><link rel="stylesheet" href="/assets/visual.css"><main id="conteudo">';
if(($argv[1]??'post')==='room'){
 class PreviewPDO extends PDO{public function __construct(){}}
 AchievementView::render(new Repository(new PreviewPDO()),1);
}else{
 $g=Game::legacyCreate('Fixture','São Paulo','SP','volante');$g['stage']='partida';MatchExperience::begin($g);Evolution::gain($g,'desarme',40);$i=Game::currentIndex($g);$g['matches'][$i]['stats']['minutes']=90;$g['matches'][$i]['stats']['rating']=7.3;MatchExperience::finish($g);echo '<article class="panel post-match"><h1>Apito final</h1>';MatchExperience::render($g['matches'][$i]);echo '</article>';
}echo '</main></html>';
