<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function euCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$badges=json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true);
$counts=['en'=>[20,24],'es'=>[20,22],'de'=>[18,18],'it'=>[20,20],'fr'=>[18,18],'pt'=>[18,18],'nl'=>[18,20],'be'=>[18,15],'tr'=>[18,20],'sc'=>[12,10]];
foreach($counts as $country=>$sizes)foreach($sizes as $n=>$size){
 $id=$country.'-'.($n+1);$c=Competition::catalog()[$id];euCheck(count($c['teams'])===$size,'Participantes '.$id);
 foreach($c['teams'] as $club){euCheck(isset(Competition::clubs()[$club]),'Cadastro '.$club);euCheck(isset($badges[$club])&&is_file(dirname(__DIR__).'/public'.$badges[$club]['path']),'Escudo '.$club);euCheck(count(Competition::clubs()[$club]['colors'])>=2,'Cores '.$club);euCheck(TeamStrength::initial($club)>=20 && TeamStrength::initial($club)<=95,'Overall '.$club);}
 $t=Competition::tournament($id);$busy=[];
 foreach($t['matches'] as $m){euCheck($m['home']!==$m['away'],'Adversários distintos');foreach(['home','away'] as $side){$key=$m['round'].'|'.$m[$side];euCheck(!isset($busy[$key]),'Sem sobreposição '.$id);$busy[$key]=true;}}
 foreach($c['teams'] as $club){$games=count(array_filter($t['matches'],fn($m)=>in_array($club,[$m['home'],$m['away']],true)));euCheck($games===($id==='sc-1'?33:($id==='sc-2'?36:2*($size-1))),'Jogos iniciais '.$id);}
 Competition::finish($t);euCheck($t['done'] && in_array($t['champion'],$c['teams'],true),'Campeão '.$id);
 foreach($t['promoted'] as $club)euCheck(!(Competition::clubs()[$club]['reserve']??false),'Reserva não sobe');
 foreach($t['matches'] as $m)euCheck($m['status']==='finalizada','Todas as fases concluídas');
 $copy=$t;Competition::finish($t);euCheck($copy===$t,'Sem ressorteio');
 echo $id.': '.count($t['matches'])." jogos concluídos\n";
}
foreach(array_keys($counts) as $country) {
 $club=Competition::catalog()[$country.'-2']['teams'][0];$g=Game::command(Game::create('Teste Europa','São Paulo','SP','meia',$club),'handicap');
 $g=array_replace($g,['play_mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'coletivo']);
 euCheck($g['stage']==='resumo','Carreira europeia jogável '.$country);
 foreach(EuropeanCompetition::pairs() as [$upper,$lower]){euCheck(count($g['next_memberships'][$upper])===count($g['memberships'][$upper]),'Tamanho primeira preservado');euCheck(count($g['next_memberships'][$lower])===count($g['memberships'][$lower]),'Tamanho segunda preservado');euCheck(!array_intersect($g['next_memberships'][$upper],$g['next_memberships'][$lower]),'Nenhuma dupla inscrição');}
 $g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'no']);euCheck($g['season']===2 && $g['stage']==='treino','Ano seguinte '.$country);
}
// Mercado: dados de atleta sintéticos sobre temporada descartável, sem tocar em carreira real.
$g=Game::command(Game::create('Teste mercado','São Paulo','SP','centroavante','Flamengo-RJ'),'handicap');$g=array_replace($g,['play_mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'equilibrio']);
$weak=$g;foreach($weak['skills'] as &$s)$s['value']=40;unset($s);
euCheck(!array_filter(Competition::offerPool($weak),fn($o)=>$o['international']),'Sem exterior para atleta insuficiente');
foreach($g['skills'] as &$s)$s['value']=64;unset($s);$g['season_skill_baseline']=60;
foreach($g['competitions'] as &$t)foreach($t['matches'] as &$m)if(in_array($g['club'],[$m['home'],$m['away']],true)){$m['stats']['minutes']=90;$m['stats']['rating']=7.3;}unset($t,$m);
$g['matches']=$g['competitions'][$g['competition_id']]['matches'];$pool=Competition::offerPool($g);$foreign=array_values(array_filter($pool,fn($o)=>$o['international']));
euCheck(count($foreign)===4,'Exterior não diluído por clubes brasileiros');euCheck(count(array_unique(array_column($foreign,'country')))>=2,'Diversidade internacional');
foreach($foreign as $o)euCheck($o['overall']<=78,'Grandes clubes exigem mais técnica');
$noMinutes=$g;foreach($noMinutes['competitions'] as &$t)foreach($t['matches'] as &$m)$m['stats']['minutes']=0;unset($t,$m);$noMinutes['matches']=$noMinutes['competitions'][$noMinutes['competition_id']]['matches'];
euCheck(!TransferMarket::profile($noMinutes)['abroad'],'Sem propostas exteriores só por nota sem minutos');
$target=$g['next_memberships']['pt-2'][0];$g['offers']=[['club'=>$target,'tier'=>4,'peso'=>100,'selected'=>true]];$g['stage']='oferta';
$g=Game::command($g,'transfer',['accept'=>'yes']);euCheck($g['club']===$target && $g['season_competitions']===['pt-2'],'Transferência Brasil-Portugal troca calendário');euCheck($g['history'][0]['club']==='Flamengo-RJ','História preservada');
echo "OK: $checks verificações Europa e mercado internacional.\n";
