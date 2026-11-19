<?php
declare(strict_types=1);
define('RUMO10_PREVIEW',true); require __DIR__.'/presentation.php';
function seasonHtml(string $stage): string {
    $g=sampleGame(true); $g['stage']=$stage; $g['feedback']=null;
    $g['offers']=[];
    foreach(Rules::TEAMS as $i=>$name) $g['offers'][]=['club'=>$name,'tier'=>1,'peso'=>30,'selected'=>$i===7];
    $path='/carreira'; $error=null; $_SESSION=['account'=>0,'csrf'=>'preview'];
    if(!function_exists('form')) {function form(string $path,?array $g=null): void {echo '<form method="post" action="'.e($path).'">';}}
    ob_start(); require dirname(__DIR__).'/app/view.php'; return ob_get_clean();
}
$resumo=seasonHtml('resumo'); $oferta=seasonHtml('oferta');
if(!str_contains($resumo,'Ver minha proposta')) throw new RuntimeException('Ação de proposta ausente no resumo.');
if(!str_contains($oferta,'Aceitar proposta')) throw new RuntimeException('Ação de aceite ausente na oferta.');
if(!str_contains($resumo,'annual-recap-panel') && !str_contains($resumo,'BALANÇO ANUAL')) throw new RuntimeException('Retrospectiva anual aberta ausente no resumo.');
if(substr_count($resumo,'Seus gols no campeonato')!==1) throw new RuntimeException('Histórico duplicado no resumo.');
if(substr_count($oferta,'Seus gols no campeonato')!==1) throw new RuntimeException('Histórico duplicado na oferta.');
if(!str_contains($resumo,'marcou 4 gols e deu 0 assistências')) throw new RuntimeException('Narrativa não reflete estatísticas.');
if(!str_contains($oferta,'<details class="wheel-candidates">')) throw new RuntimeException('Lista da roleta não recolhida.');
echo "OK: retrospectiva anual aberta, continuação para o mercado, narrativa com dados reais e histórico sem duplicação.\n";
