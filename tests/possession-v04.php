<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function possessionCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$g=Game::legacyCreate('Fixture posse','São Paulo','SP','centroavante');$g['stage']='partida';$g['states']['risco_lesao']=0;$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['minute']=15;$m['status']='em_andamento';$m['entry']=0;$m['exit']=90;$m['home_overall']=$m['away_overall']=50;MatchBalance::prepare($m,$g);unset($m);
$field=new ReflectionMethod(Game::class,'fieldDecision');$o=['action'=>'drible','effects'=>[],'match_event'=>true];$roll=['success'=>true,'class'=>'sucesso','chance'=>50];$baseShot=Game::chance($g,['action'=>'chute']);
for($n=1;$n<=2;$n++){$g['feedback']=$roll+['text'=>'Continuação controlada.','effects'=>[]];$field->invokeArgs(null,[&$g,$o,$roll,2015,0]);possessionCheck($g['matches'][$i]['minute']===15,'Sem salto de vinte minutos na mesma posse');possessionCheck(MatchMomentum::builtActions($g)===$n,'Contador limitado à posse');possessionCheck(Game::situation($g)['id']===($n===1?1301:1300),'Fase da jogada explícita');
 $reload=json_decode(json_encode($g),true);possessionCheck(Game::choices($reload)===Game::choices($g),'Reload conserva continuação');
 if($n===1)possessionCheck(Game::chance($g,['action'=>'chute'])>$baseShot,'Construção parcial aumenta execução sem ganho permanente');
}
possessionCheck(ActionResolution::trace($g,Game::choices($g)[0])['extra_sequence_bonus']===0,'Classe clara já remunera a construção');
foreach(['score','minute','interval','exit'] as $end){$copy=$g;switch($end){case 'score':$copy['matches'][$i]['ag']++;break;case 'minute':$copy['matches'][$i]['minute']=35;break;case 'interval':$copy['matches'][$i]['status']='intervalo';break;default:$copy['matches'][$i]['exit']=15;}possessionCheck(!MatchMomentum::available($copy),'Interrupção invalida benefício '.$end);}
$g['feedback']=['text'=>'Tentativa.', 'effects'=>[]];$field->invokeArgs(null,[&$g,Game::choices($g)[0],['success'=>false,'class'=>'falha','chance'=>35],1300,0]);possessionCheck(!isset($g['matches'][$i]['possession'])&&!MatchMomentum::available($g),'Conclusão consome posse');
$m=$g['matches'][$i];$m['attack_sequence']=1;unset($m['possession']);MatchMomentum::record($m,true,true,false);possessionCheck($m['attack_sequence']===1&&empty($m['clear_chance']),'Contador legado sem testemunha não cria chance fantasma');
foreach([[false,true,false],[true,false,false],[true,true,true]] as [$success,$constructive,$goal]){MatchMomentum::record($m,$success,$constructive,$goal);possessionCheck(empty($m['possession']),'Perda, ação incompatível ou gol encerra posse');}
echo "OK: $checks verificações de posse, interrupção, bônus e compatibilidade.\n";
