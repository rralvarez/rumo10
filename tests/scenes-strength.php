<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function checkScene(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$catalog=Scenes::catalog();checkScene(count($catalog)===214,'214 situações');$prompts=[];$choices=0;
foreach($catalog as $id=>$s){checkScene(!isset($prompts[$s['text']]),'Enunciado único '.$id);$prompts[$s['text']]=true;
    $g=Game::legacyCreate('Teste','São Paulo','SP','centroavante');$g['stage']=$s['stage'];
    if($s['stage']==='partida'){$i=Game::currentIndex($g);$g['matches'][$i]['minute']=70;$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;}
    $g['active_situation']=['id'=>$id,'key'=>Scenes::key($g)];$entry=Scenes::current($g);
    checkScene(count($entry['options'])===4,'Quatro escolhas '.$id);$choices+=count($entry['options']);
    foreach($entry['options'] as $o){checkScene(in_array($o['action'],['social','passe','chute','drible','desarme'],true),'Mecânica válida');foreach($o['effects'] as $key=>$value)checkScene(in_array($key,array_merge(Rules::SKILLS,Rules::STATES),true),'Atributo existente '.$key);}
}
checkScene($choices===856,'856 alternativas');
$g=Game::legacyCreate('Teste','São Paulo','SP','centroavante');$g['stage']='treino';$seen=[];
for($n=1;$n<=40;$n++){$g['round']=$n;Scenes::prepare($g);$id=Scenes::current($g)['id'];checkScene(!in_array($id,array_slice($seen,-10),true),'Sem repetição nos últimos dez treinos');$copy=$g;Scenes::prepare($copy);checkScene($copy===$g,'Preparação não sorteia de novo');checkScene(Game::situation($copy)===Game::situation($g),'Refresh mantém alternativas');Scenes::remember($g,Scenes::current($g));$seen[]=$id;}
$g['round']=1;$g['stage']='partida';$i=Game::currentIndex($g);$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['minute']=15;$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;
foreach(['centroavante','zagueiro'] as $position){$g['position']=$position;$pool=Scenes::eligible($g);foreach($pool as $id)checkScene($position==='centroavante'?!($id>=1125&&$id<=1139):!($id>=1100&&$id<=1124),'Contexto por posição');checkScene(!in_array(1143,$pool,true)&&!in_array(1149,$pool,true),'Lesão e substituição exigem contexto');}
$g['stage']='pre_jogo';$g['round']=4;foreach(Scenes::eligible($g) as $id)checkScene(in_array($id,[6,1200,1201],true),'Reserva consistente');
checkScene(TeamStrength::initial('Flamengo-RJ')>TeamStrength::initial('Palmeiras-SP'),'Campanha 2025 diferencia clubes na mesma divisão');
checkScene(TeamStrength::initial('Corinthians-SP')>TeamStrength::initial('São Caetano-SP'),'Divisões têm pesos diferentes');
$strong=0;$weak=0;$draw=0;$total=0;$iterations=6000;
for($n=0;$n<$iterations;$n++){[$h,$a]=TeamStrength::expectations(85,40);$hg=TeamStrength::goals($h);$ag=TeamStrength::goals($a);$total+=$hg+$ag;if($hg>$ag)$strong++;elseif($hg<$ag)$weak++;else$draw++;}
checkScene($strong>$weak*2,'Favorito ganha mais');checkScene($weak>0&&$draw>0,'Zebras e empates continuam possíveis');checkScene($total/$iterations>2&&$total/$iterations<4.5,'Média de gols plausível');
$m=Schedule::match(1,'Flamengo-RJ','Palmeiras-SP');Competition::simulate($m);$copy=$m;Competition::simulate($m);checkScene($copy===$m,'Partida finalizada permanece imutável');
echo "$checks verificações OK; 214 situações, 856 alternativas; favorito=$strong, zebra=$weak, empate=$draw; média=".round($total/$iterations,2)." gols.\n";
