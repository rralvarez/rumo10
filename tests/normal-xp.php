<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function xpCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$base=Game::legacyCreate('Teste de progresso','São Paulo','SP','meia');
foreach([18,24,31,33,39] as $age)foreach(Rules::SKILLS as $skill)foreach([1,2,3,6] as $xp){
    $normal=$base;$normal['age']=$age;$fast=$normal;$fast['play_mode']='rapido';$express=$normal;$express['play_mode']='expresso';
    $n=Evolution::gain($normal,$skill,$xp);$f=Evolution::gain($fast,$skill,$xp);$e=Evolution::gain($express,$skill,$xp);
    xpCheck($n===$f*2 && $e===$f,'Normal dobra XP e modos simulados mantêm taxa.');
    xpCheck($normal['skills'][$skill]['value']<=Evolution::ceiling($age,$skill),'XP maior respeita limites internos.');
}
$normal=$base;$normal['skills']['passe']['xp']=Rules::xpThreshold(55)-5;$fast=$normal;$fast['play_mode']='rapido';Evolution::gain($normal,'passe',3);Evolution::gain($fast,'passe',3);
xpCheck($normal['skills']['passe']['value']===56 && $fast['skills']['passe']['value']===55,'XP adicional antecipa avanço de habilidade.');
ob_start();PlayerProfile::render($base);$html=ob_get_clean();
foreach(['Ciclo da carreira','Auge potencial','aos 32','aos 35','aos 40'] as $text)xpCheck(!str_contains($html,$text),'Regras internas de idade não aparecem na ficha.');
ob_start();Retirement::confirmation($base);$html=ob_get_clean();
foreach(['a partir dos 35','35 anos','aos 40'] as $text)xpCheck(!str_contains($html,$text),'Confirmação não antecipa critérios internos.');
echo "OK: $checks verificações de XP em dobro, evolução e informações internas ocultas.\n";
