<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function lifeCheck(bool $v,string $label):void{global $checks;$checks++;if(!$v)throw new RuntimeException($label);}
$base=Game::legacyCreate('Teste ciclo','São Paulo','SP','meia');$base['stage']='treino';
foreach([18,24,31,32,33,35,39] as $age){$g=$base;$g['age']=$age;foreach(Rules::SKILLS as $skill){Evolution::gain($g,$skill,100000);lifeCheck($g['skills'][$skill]['value']===($age>=33 && in_array($skill,['velocidade','resistencia','forca'],true)?$base['skills'][$skill]['value']:Evolution::ceiling($age,$skill)),'Teto por idade e fim do aumento físico aos 33');lifeCheck($g['skills'][$skill]['xp']<Rules::xpThreshold($g['skills'][$skill]['value']),'Sem estoque infinito no teto');}}
$young=$base;$young['age']=26;Evolution::gain($young,'passe',100000);lifeCheck($young['skills']['passe']['value']<100,'Auge máximo antes de 27 bloqueado');$young['age']=27;Evolution::gain($young,'passe',10000);lifeCheck($young['skills']['passe']['value']===100,'Faixa de auge começa aos 27');
$weak=$base;$weak['age']=32;lifeCheck($weak['skills']['passe']['value']===55,'Idade não concede talento automaticamente');
$old=$base;$old['age']=33;$changes=Evolution::birthday($old);lifeCheck($changes['velocidade']===-1 && !isset($changes['visao_jogo']),'Decaimento físico e experiência preservada');
$penalty=$base;$before=$penalty['skills']['resistencia']['value'];$method=new ReflectionMethod(Game::class,'effects');$args=[['resistencia'=>-3],['success'=>false,'class'=>'falha'],&$penalty];$applied=$method->invokeArgs(null,$args);lifeCheck($penalty['skills']['resistencia']['value']===$before-3 && $applied['resistencia']===-3,'Penalidade reduz habilidade, inclusive na falha');
$r=$base;$r['age']=34;try{Game::command($r,'retire',['confirm'=>'retire']);lifeCheck(false,'Bloqueia abaixo de 35');}catch(InvalidArgumentException){lifeCheck(true,'Bloqueia abaixo de 35');}
$r['age']=35;$totals=CareerStats::collect($r)['totals'];$retired=Game::command($r,'retire',['confirm'=>'retire']);lifeCheck($retired['stage']==='aposentado' && $retired['age']===35,'Voluntária aos 35');lifeCheck(CareerStats::collect($retired)['totals']===$totals,'Histórico sem duplicar');lifeCheck($retired['retirement']['reason']==='voluntary','Motivo salvo');
foreach(['choice','offers','transfer','simulate','mode'] as $action)try{Game::command($retired,$action);lifeCheck(false,'Não reabre carreira');}catch(InvalidArgumentException){lifeCheck(true,'Não reabre carreira');}
$r['age']=39;$r['stage']='resumo';$forced=Game::command($r,'offers');lifeCheck($forced['stage']==='aposentado' && $forced['age']===40 && !$forced['offers'],'Não oferece contrato aos 40');
$r['stage']='oferta';$forced=Game::command($r,'transfer',['accept'=>'no']);lifeCheck($forced['stage']==='aposentado' && $forced['season']===$r['season'],'Oferta antiga não inicia temporada aos 40');
$r=$base;$r['age']=39;$r['play_mode']='expresso';$r=Game::command($r,'simulate',['plan'=>'equilibrio']);lifeCheck($r['stage']==='aposentado' && $r['age']===40,'Expresso termina na aposentadoria');
function form(string $path,?array $g=null):void{echo '<form method="post">';}
ob_start();Retirement::confirmation($retired);$html=ob_get_clean();lifeCheck(!str_contains($html,'name="confirm"'),'Aposentado sem formulário de retorno');
echo "OK: $checks verificações de evolução e aposentadoria.\n";
