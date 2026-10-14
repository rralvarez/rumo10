<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$clients=require 'C:/inetpub/wwwroot/rumohub/storage/private/clients.php';putenv('RUMOHUB_URL='.($argv[1]??'http://rumohub.local'));putenv('RUMOHUB_CLIENT=rumo10');putenv('RUMOHUB_SECRET='.$clients['rumo10']['secret']);
putenv('RUMOHUB_ENABLED=0');$localClubs=Competition::clubs();$localCompetitions=Competition::catalog();$r=RumoHub::fetch('/api/v1/catalog');$catalog=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
if(array_diff(array_keys($localClubs),array_keys($catalog['clubs'])) || array_diff(array_keys($localCompetitions),array_keys($catalog['competitions'])))throw new RuntimeException('Catálogo original incompleto.');
foreach($catalog['competitions'] as $id=>$c){if(count(array_unique($c['teams']))!==count($c['teams']))throw new RuntimeException('Participantes repetidos.');foreach($c['teams'] as $club)if(!isset($catalog['clubs'][$club]) || !in_array($id,$catalog['clubs'][$club]['competitions'],true))throw new RuntimeException('Filiação divergente.');}
RumoHub::save(RumoHub::folder().'/catalog.json',$r['body']);putenv('RUMOHUB_ENABLED=1');
$p=ClubSquad::players('Inter Miami CF-US');if(!in_array('espn:45843',array_column($p,'id'),true))throw new RuntimeException('Messi ausente.');
ob_start();ClubBadge::render('Inter Miami CF-US');$html=ob_get_clean();if(!str_contains($html,'/escudo?club=') || str_contains($html,$clients['rumo10']['secret']))throw new RuntimeException('Renderização do escudo incorreta.');
$r=RumoHub::fetch('/api/v1/badge?club='.rawurlencode('Inter Miami CF-US'));if($r['type']!=='image/png' || $r['version']!==$catalog['version'])throw new RuntimeException('Escudo divergente.');
$r=RumoHub::fetch('/api/v1/squad?club='.rawurlencode('Inter Miami CF-US'));if($r['version']!==$catalog['version'])throw new RuntimeException('Versão do elenco divergente.');
echo 'OK: ponte Rumo10/Hub, '.count($catalog['clubs']).' clubes, '.count($catalog['competitions'])." competições, elenco e escudo de Messi.\n";
