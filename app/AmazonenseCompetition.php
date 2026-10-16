<?php
declare(strict_types=1);
final class AmazonenseCompetition {
 public static function create(array $c,array $teams):array {
  if(count($teams)!==8||count(array_unique($teams))!==8)throw new LogicException('Amazonense Série A exige oito clubes distintos.');
  $groups=[array_values(array_intersect($c['groups'][0],$teams)),array_values(array_intersect($c['groups'][1],$teams))];$extra=array_values(array_diff($teams,...$groups));sort($extra);
  foreach($groups as &$group)while(count($group)<4)$group[]=array_shift($extra);unset($group);$lots=$teams;shuffle($lots);
  $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'amazonense','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>4,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'am_turn'=>1,'am_winners'=>[]];
  for($r=0;$r<4;$r++)for($i=0;$i<4;$i++){[$a,$b]=[$groups[0][$i],$groups[1][($i+$r)%4]];if(($r+$i)%2)[$a,$b]=[$b,$a];$t['matches'][]=Schedule::match($r+1,$a,$b)+['phase'=>'liga','group'=>'cruzado','am_turn'=>1];}
  return $t;
 }
 private static function rank(array $t,array $teams,?int $turn=null):array {
  $copy=$t;$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['phase']==='liga'&&($turn===null||$m['am_turn']===$turn)));return Competition::rank($copy,$teams,'liga');
 }
 public static function next(array &$t):void {
  $turn=$t['am_turn'];$phase=$t['phase'];
  if($phase==='liga'){
   $r=array_map(fn($group)=>self::rank($t,$group,$turn),$t['initial_groups']);$t['am_leaders']=[$r[0][0],$r[1][0]];
   $pairs=$turn===1?[[$r[0][1],$r[0][2]],[$r[1][1],$r[1][2]]]:[[$r[0][1],$r[1][2]],[$r[1][1],$r[0][2]]];
   Competition::knockout($t,$pairs,'am_playoff',1,'advantage');return;
  }
  $w=Competition::resolve($t);
  if($phase==='am_playoff'){if($turn===2)$w=array_reverse($w);Competition::knockout($t,[[$t['am_leaders'][0],$w[0]],[$t['am_leaders'][1],$w[1]]],'am_semifinal',1);return;}
  if($phase==='am_semifinal'){
   $rank=self::rank($t,$t['teams'],$turn);
   if($turn===1)usort($w,fn($a,$b)=>array_search($a,$rank,true)<=>array_search($b,$rank,true));
   else { $groupRanks=array_map(fn($group)=>self::rank($t,$group,$turn),$t['initial_groups']);$places=[];foreach($groupRanks as $group)foreach($group as $i=>$club)$places[$club]=$i;usort($w,fn($a,$b)=>($places[$a]<=>$places[$b])?:(array_search($a,$rank,true)<=>array_search($b,$rank,true))); }
   Competition::knockout($t,[$w],'am_turn_final',1);return;
  }
  if($phase==='am_turn_final'){
   $t['am_winners'][]=$w[0];
   if($turn===1){$t['am_turn']=2;Competition::leaguePhase($t,$t['initial_groups'],'liga',false);foreach($t['matches'] as &$m)if($m['phase']==='liga'&&!isset($m['am_turn']))$m['am_turn']=2;unset($m);return;}
   $t['initial_ranking']=self::rank($t,$t['teams']);$eligible=array_values(array_diff(array_reverse($t['initial_ranking']),$t['am_winners']));$t['relegated']=array_slice($eligible,0,1);
   if($t['am_winners'][0]===$t['am_winners'][1]){$t['champion']=$w[0];$t['done']=true;return;}
   $final=$t['am_winners'];usort($final,fn($a,$b)=>array_search($a,$t['initial_ranking'],true)<=>array_search($b,$t['initial_ranking'],true));Competition::knockout($t,[$final],'am_final',1);return;
  }
  if($phase==='am_final'){$t['champion']=$w[0];$t['done']=true;return;}
  throw new LogicException('Fase amazonense desconhecida.');
 }
}
