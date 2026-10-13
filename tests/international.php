<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function internationalCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$badges=json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true);
foreach(['ar-1'=>30,'ar-2'=>36,'uy-1'=>16,'uy-2'=>14,'py-1'=>12,'py-2'=>16,'bo-1'=>16,'bo-2'=>69] as $id=>$count){
 $c=Competition::catalog()[$id];internationalCheck(count($c['teams'])===$count,'Participantes '.$id);
 foreach($c['teams'] as $club){internationalCheck(isset(Competition::clubs()[$club]),'Clube cadastrado');internationalCheck(isset($badges[$club])&&is_file(dirname(__DIR__).'/public'.$badges[$club]['path']),'Escudo local '.$club);internationalCheck(count(Competition::clubs()[$club]['colors']??[])>=2,'Paleta '.$club);}
 $t=Competition::tournament($id);$busy=[];
 foreach($t['matches'] as $m){internationalCheck($m['home']!==$m['away'],'Sem auto-confronto');foreach(['home','away'] as $s){$key=$m['round'].'|'.$m[$s];internationalCheck(!isset($busy[$key]),'Sem dois jogos no mesmo dia '.$id.' '.$key);$busy[$key]=true;}}
 foreach($c['teams'] as $club){$games=count(array_filter($t['matches'],fn($m)=>in_array($club,[$m['home'],$m['away']],true)));$expected=match($id){'ar-1'=>16,'ar-2'=>36,'uy-1'=>15,'uy-2'=>6,'py-1'=>22,'py-2','bo-1'=>30,default=>null};if($expected!==null)internationalCheck($games===$expected,'Jogos iniciais '.$id.' '.$club);}
 Competition::finish($t);internationalCheck($t['done']&&in_array($t['champion'],$c['teams'],true),'Campeão '.$id);
 internationalCheck(count($t['promoted'])===match($id){'ar-2','py-2'=>2,'uy-2'=>3,'bo-2'=>1,default=>0},'Acessos '.$id);
 foreach($t['matches'] as $m)internationalCheck($m['status']==='finalizada','Calendário concluído');
 $copy=$t;Competition::finish($t);internationalCheck($copy===$t,'Resultados preservados');
 $changed=$c['teams'];$replace=Competition::catalog()[substr($id,0,3).($id[-1]==='1'?'2':'1')]['teams'][0];$changed[0]=$replace;$next=Competition::tournament($id,$changed);Competition::finish($next);internationalCheck($next['done'],'Nova composição finaliza '.$id);
 echo "$id: ".count($t['matches'])." partidas, calendário e nova composição OK\n";
}
foreach(['Boca Juniors-AR','Acassuso-AR','Peñarol-UY','Fénix-UY','Olimpia-PY','Sol de América-PY','Bolívar-BO','FATIC-BO'] as $club){
 $g=Game::create('Teste internacional','São Paulo','SP','meia',$club);$g=Game::command($g,'handicap');$guard=0;
 while($g['stage']!=='resumo'&&$guard++<1200){
  $action=$g['stage']==='competicao_resumo'?'continue':'choice';
  if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$action=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']?'advance':'choice';}
  $g=Game::command($g,$action,$action==='choice'?['option'=>1]:[]);
 }
 internationalCheck($g['stage']==='resumo','Ano do jogador concluído '.$club);internationalCheck(isset($g['next_club_strengths'][$club]),'Força atualizada pela temporada simulada');
 $g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'no']);internationalCheck($g['season']===2&&$g['stage']==='treino','Ano seguinte '.$club);
 echo "$club: ano jogável e temporada seguinte OK ($guard passos)\n";
}
echo "$checks verificações internacionais OK.\n";
