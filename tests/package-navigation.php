<?php
require dirname(__DIR__).'/app/bootstrap.php';putenv('RUMOHUB_ENABLED=0');
$g=Game::legacyCreate('Teste de localização','São Paulo','SP','meia');
if(CurrentLocation::label($g)!=='Cidade informada (cadastro anterior)')throw new RuntimeException('Legado não pode afirmar residência atual.');
$legacy=$g;$legacy['stage']='treino';$relocated=Game::command($legacy,'relocate',['city'=>'Campinas','uf'=>'SP','residence_country'=>'BR']);
if($relocated['legacy_location_record']['city']!==$legacy['city'] || $relocated['location_kind']!=='current' || $relocated['matches']!==$legacy['matches'] || $relocated['city']!=='Campinas')throw new RuntimeException('Edição voluntária deve preservar origem e resultados.');
$inMatch=$legacy;$inMatch['stage']='partida';$denied=false;try{Game::command($inMatch,'relocate',['city'=>'Campinas','uf'=>'SP','residence_country'=>'BR']);}catch(InvalidArgumentException){$denied=true;}if(!$denied)throw new RuntimeException('Residência editável durante a partida.');
$g=EntryCountries::create('Teste de localização','Lisboa','SP','meia','BR','gratuito','PT','Lisboa','SP');
if($g['city']!=='Lisboa' || $g['uf']!=='EX' || $g['location_kind']!=='current')throw new RuntimeException('Residência internacional incorreta.');
$g=Game::command($g,'handicap',[]);
if(count($g['initial_candidates'])!==3 || $g['initial_candidates'][0]['distance_km']!==null)throw new RuntimeException('Proximidade fictícia.');
$before=$g;Competition::phaseLabel($g);if($g!==$before)throw new RuntimeException('Leitura modificou save.');
$view=file_get_contents(dirname(__DIR__).'/app/view.php');
if(!str_contains($view,'← Voltar à carreira') || !str_contains($view,'href="/carreira"'))throw new RuntimeException('CTA ausente.');
echo "OK: localização atual/legada/internacional, candidatos sem distância falsa e navegação somente GET.\n";
