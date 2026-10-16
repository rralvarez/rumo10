<?php
declare(strict_types=1);
/** Observational score witnesses, never a source of goals or probability. */
final class GoalAudit {
 public static function score(array &$m,string $side,string $club,bool $playerGoal=false,array $facts=[]):void {
  $own=$m['home']===$club?'hg':'ag';
  $m['score_events'][]=['minute'=>$m['minute'],'side'=>$side,'score'=>[$m['hg'],$m['ag']],'player_goal'=>$playerGoal,'on_field'=>$m['minute']>=$m['entry']&&$m['minute']<$m['exit'],'behind'=>$m[$own]<$m[$own==='hg'?'ag':'hg']]+$facts;
 }
 public static function flags(array $m,string $club):array {
  $own=$m['home']===$club?0:1;$other=1-$own;$score=[$m['hg'],$m['ag']];$flags=[];
  foreach($m['score_events']??[] as $e){
   if($e['behind']&&($m['stats']['minutes']??0)>0&&$score[$own]>$score[$other])$flags['ESP01']=true;
   if(!$e['player_goal'])continue;
   if(($e['shot_kind']??'')==='difficult')$flags['ESP04']=true;
   if(($e['valid_sequence_actions']??0)>=2)$flags['ESP05']=true;
   if($e['minute']>=80&&$score[$own]>$score[$other]&&$e['score'][$own]===$score[$other]+1)$flags['ESP08']=true;
  }return $flags;
 }
}
