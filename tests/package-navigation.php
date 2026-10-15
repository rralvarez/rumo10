<?php
require dirname(__DIR__).'/app/bootstrap.php';putenv('RUMOHUB_ENABLED=0');
$g=Game::legacyCreate('Teste de localização','São Paulo','SP','meia');
assert(CurrentLocation::label($g)==='Cidade informada (cadastro anterior)');
$g=EntryCountries::create('Teste de localização','Lisboa','SP','meia','BR','gratuito','PT','Lisboa','SP');
if($g['city']!=='Lisboa' || $g['uf']!=='EX' || $g['location_kind']!=='current')throw new RuntimeException('Residência internacional incorreta.');
$g=Game::command($g,'handicap',[]);
if(count($g['initial_candidates'])!==3 || $g['initial_candidates'][0]['distance_km']!==null)throw new RuntimeException('Proximidade fictícia.');
$before=$g;Competition::phaseLabel($g);if($g!==$before)throw new RuntimeException('Leitura modificou save.');
$view=file_get_contents(dirname(__DIR__).'/app/view.php');
if(!str_contains($view,'← Voltar à carreira') || !str_contains($view,'href="/carreira"'))throw new RuntimeException('CTA ausente.');
echo "OK: localização atual/legada/internacional, candidatos sem distância falsa e navegação somente GET.\n";
