<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');
$root=dirname(__DIR__);require $root.'/storage/private/stabilization-v04/reference-a5/app/bootstrap.php';
$rows=[];foreach([45,65,85] as $level)foreach([35,50,80] as $own)foreach([35,50,80] as $opp){
 $g=Game::legacyCreate('Reprodução anterior','São Paulo','SP','centroavante');$g['stage']='partida';foreach($g['skills'] as &$s)$s['value']=$level;unset($s);$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']='em_andamento';$m['minute']=35;$m['entry']=0;$m['exit']=90;$m['lineup']='titular';$m['home_overall']=$m['home']===$g['club']?$own:$opp;$m['away_overall']=$m['away']===$g['club']?$own:$opp;MatchBalance::prepare($m,$g);$m['clear_chance']=true;unset($m);
 $o=Game::choices($g)[0];if(empty($o['clear_chance'])||$o['action']!=='chute'||!MatchMomentum::available($g))throw new RuntimeException('Fixture não é uma chance clara válida.');
 $raw=Probability::chance(Probability::effective($g['skills'],'chute'),50,85,50,45,$opp);$raw=min(95,$raw+8);$goal=Probability::goalChance($raw,$level,true);
 $rows[]=['level'=>$level,'own'=>$own,'opponent'=>$opp,'normal'=>Game::chance($g,['action'=>'chute','effects'=>[]]),'clear'=>Game::chance($g,$o),'clear_before_budget'=>$goal,'clear_budget_cap'=>MatchBalance::eventChance($g,$goal),'lineup'=>'titular','entry'=>0,'exit'=>90,'valid_clear_option'=>true];
}
file_put_contents($root.'/Docs/estabilizacao-v04/antes-corrigido.json',json_encode(['git_base'=>'a5bb59e','fixture_correction'=>'O primeiro baseline deixou exit=0; Game::choices caiu em cena social no minuto 35. Hashes/esquema originais continuam válidos, suas colunas clear não são evidência de chance clara. Este arquivo usa código original isolado com entrada/saída válidas.','cases'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo 'OK: '.count($rows)." cenários do código anterior, todos com chance clara validada.\n";
