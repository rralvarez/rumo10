<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');require dirname(__DIR__).'/app/bootstrap.php';
$r=['name'=>'Estadual de teste','champion'=>'B','position'=>7,'promoted'=>false,'relegated'=>false,'relegation_pending'=>true,'access_pending'=>false,'continuity'=>['valid'=>false,'reason'=>'state_future_memberships_and_mandatory_calendar_pending']];
$text=CompetitionView::resultText($r,'A');
if(!str_contains($text,'Classificação final simulada: 7º')||!str_contains($text,'posição de descenso')||str_contains($text,'primeira fase')||str_contains($text,'repete a divisão'))throw new RuntimeException('Resumo promete continuidade ou fase incorreta.');
$r['champion']='A';$r['position']=1;$r['relegation_pending']=false;$text=CompetitionView::resultText($r,'A');if(!str_contains($text,'conquistou o título')||str_contains($text,'descenso'))throw new RuntimeException('Título e classificação incoerentes.');
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/motor-estadual/presentation.json',json_encode(['checks'=>6,'final_ranking_not_first_phase'=>true,'no_promised_renewal'=>true,'champion_preserved'=>true],JSON_PRETTY_PRINT));echo "OK: 6 verificações de apresentação do estadual.\n";
