<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$options=0;
function consequenceCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function consequenceGame(string $stage='treino',int $id=1000):array{
    $g=Game::legacyCreate('Teste de efeitos','São Paulo','SP','meia');$g['stage']=$stage;$g['states']['risco_lesao']=0;
    if($stage==='partida'){$i=Game::currentIndex($g);$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['minute']=15;$g['matches'][$i]['exit']=90;}
    $g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];return $g;
}
$apply=new ReflectionMethod(Game::class,'effects');$field=new ReflectionMethod(Game::class,'fieldDecision');
$outcomes=[['success'=>true,'class'=>'sucesso'],['success'=>true,'class'=>'excepcional'],['success'=>false,'class'=>'falha'],['success'=>false,'class'=>'falha_grave']];
foreach(Scenes::catalog() as $id=>$scene){
    $g=consequenceGame($scene['stage'],$id);
    foreach(Game::choices($g) as $index=>$o){$options++;
        foreach(['effects','failure_effects','severe_failure_effects'] as $kind)foreach($o[$kind]??[] as $key=>$value){
            consequenceCheck(in_array($key,$kind==='effects'?array_merge(Rules::SKILLS,Rules::STATES):Rules::STATES,true) && is_int($value),'Atributo e inteiro válidos '.$id.':'.$index.':'.$kind);
        }
        foreach($outcomes as $roll){
            $next=$g;$applied=$apply->invokeArgs(null,[$o['effects'],$roll,&$next,$o]);
            foreach($next['states'] as $key=>$value){
                consequenceCheck($value>=0 && $value<=100,'Estado limitado.');
                consequenceCheck(($applied[$key]??0)===$value-$g['states'][$key],'Feedback representa variação real '.$id.':'.$index.':'.$key);
            }
            foreach(Rules::SKILLS as $skill){
                if(!$roll['success'])consequenceCheck($next['skills'][$skill]['xp']===$g['skills'][$skill]['xp'],'Falha não dá XP.');
                consequenceCheck($next['skills'][$skill]['value']>=0 && $next['skills'][$skill]['value']<=100,'Habilidade limitada.');
            }
            if(!$roll['success'])foreach($next['states'] as $key=>$value)consequenceCheck(str_starts_with($key,'risco_')?$value>=$g['states'][$key]:$value<=$g['states'][$key],'Falha não concede benefício de estado '.$id.':'.$index.':'.$key);
        }
    }
}
consequenceCheck($options===4*count(Scenes::catalog()),'Todas as alternativas visitadas.');
foreach([1051,1056,1060,1063,1070,1072,1082,1094,1096,1153,1154,1160,1167,1179,1182,1186] as $id){$g=consequenceGame($id<1100?'pre_jogo':'fim_semana',$id);$o=Game::choices($g)[3];consequenceCheck(!isset($o['effects']['risco_lesao']) && !isset($o['effects']['energia']),'Fala ou mensagem não causa desgaste físico '.$id);}
$g=consequenceGame('fim_semana',1193);$party=Game::choices($g)[3];consequenceCheck($party['effects']['energia']<0 && !isset($party['effects']['risco_lesao']),'Evento até tarde custa energia, sem inventar lesão.');
$g=consequenceGame('treino',1008);$complaint=Game::choices($g)[3];
foreach($outcomes as $roll){$next=$g;$result=$apply->invokeArgs(null,[$complaint['effects'],$roll,&$next,$complaint]);consequenceCheck($result['entrosamento']===-3 && $result['confianca_treinador']===-2,'Culpar outro setor desgasta relações em sucesso/falha, sem multiplicar punição.');}
$g=consequenceGame('pre_jogo',4);$alone=Game::choices($g)[1];consequenceCheck(!isset($alone['effects']['entrosamento']),'Ouvir música não penaliza entrosamento.');
$g=consequenceGame('fim_semana',1153);consequenceCheck(!isset(Game::choices($g)[0]['effects']['posicionamento']),'Conversa de carreira não dá XP de posicionamento.');
$g=consequenceGame('treino',1048);consequenceCheck(isset(Game::choices($g)[1]['effects']['finalizacao']) && !isset(Game::choices($g)[1]['effects']['passe']),'Cabeceio treina finalização.');
$g=consequenceGame('fim_semana',1172);consequenceCheck(!isset(Game::choices($g)[1]['effects']['resistencia']),'Planejar treino físico não é executar treino.');
$g=consequenceGame('treino',1009);$rest=Game::choices($g)[3];$roll=['success'=>false,'class'=>'falha_grave'];$next=$g;$result=$apply->invokeArgs(null,[$rest['effects'],$roll,&$next,$rest]);consequenceCheck(!isset($result['confianca']),'Descanso frustrado não pune confiança futebolística.');
$g['states']['confianca']=1;$next=$g;$result=$apply->invokeArgs(null,[['confianca'=>-3],$roll,&$next,['failure_effects'=>['confianca'=>-4]]]);consequenceCheck($result['confianca']===-1 && $next['states']['confianca']===0,'Penalidades somadas respeitam clamp e feedback real.');
foreach(['chute','passe','drible'] as $type)for($n=0;$n<40;$n++){
    $g=consequenceGame('partida');unset($g['active_situation']);$o=['text'=>'Teste','action'=>$type,'effects'=>[]];$g['feedback']=$roll+['text'=>'Falhou.','effects'=>[]];$field->invokeArgs(null,[&$g,$o,$roll,0,0]);$m=$g['matches'][Game::currentIndex($g)];
    consequenceCheck($m['stats']['fouls']===0 && $m['stats']['cards']===0,'Erro técnico não vira falta/cartão.');
}
foreach(['chute'=>'finalizacao','passe'=>'passe','drible'=>'drible','desarme'=>'desarme'] as $type=>$skill){
    $g=consequenceGame('partida');$success=['success'=>true,'class'=>'sucesso'];$o=['text'=>'Teste','action'=>$type,'effects'=>[]];$g['feedback']=$success+['text'=>'Deu certo.','effects'=>[]];$field->invokeArgs(null,[&$g,$o,$success,0,0]);
    consequenceCheck(($g['feedback']['effects']['XP '.$skill]??0)>0,'Prática bem-sucedida ganha XP '.$type);
    if($type==='chute')consequenceCheck($g['feedback']['effects']['confianca']===4,'Gol melhora confiança.');
}
echo "OK: $checks verificações das $options alternativas, quatro resultados, efeitos reais, contexto e disciplina.\n";
