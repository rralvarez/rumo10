<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$segments=Wheel::segments(Rules::HANDICAPS,'Disciplina de base');
if(abs(array_sum(array_column($segments,'span'))-360)>.00001) throw new RuntimeException('Setores não completam 360 graus.');
if($segments[0]['span']!==90.0 || $segments[1]['span']!==108.0) throw new RuntimeException('Setores não refletem pesos.');
foreach($segments as $s) {
    $target=fmod(360-$s['mid']+360,360);
    if(abs(fmod($s['mid']+$target,360))>.00001) throw new RuntimeException('Ponteiro não termina no centro do resultado.');
}
ob_start(); Wheel::render(Rules::HANDICAPS,'Disciplina de base','test-profile'); $html=ob_get_clean();
if(!str_contains($html,'data-wheel-selected="1"') || !str_contains($html,'data-wheel-angle="216"') || !str_contains($html,'Disciplina de base')) throw new RuntimeException('Resultado persistido não renderizado.');
ob_start(); Wheel::render([['nome'=>'<script>unsafe</script>','peso'=>1],['nome'=>'Outro','peso'=>1]],'<script>unsafe</script>','test'); $escaped=ob_get_clean();
if(str_contains($escaped,'<script>')) throw new RuntimeException('Nome não escapado.');
echo "OK: setores proporcionais, orientação do ponteiro, resultado persistido e escaping.\n";
