<?php
declare(strict_types=1);
/** Engine-owned facts. No free-form prose or client-supplied claims. */
final class AchievementEvents {
 public static function capture(array &$g,string $previousStage):void {
  if(empty($g['official']))return;
  foreach($g['matches'] as &$m){
   if($m['status']!=='finalizada' || empty($m['experience']['complete']) || !empty($m['achievement_event_recorded']) || !in_array($g['club'],[$m['home'],$m['away']],true))continue;
   $m['achievement_event_recorded']=true;$g['achievement_tracking_started']??=$g['season'];
   $key=hash('sha256',implode('|',['match',$g['season'],$g['competition_id']??'',$m['round'],$m['home'],$m['away']]));
   $g['achievement_events'][$key]=['type'=>'match','season'=>$g['season'],'competition'=>$g['competition_id'],'club'=>$g['club'],'stats'=>$m['stats'],'flags'=>GoalAudit::flags($m,$g['club'])];
  }unset($m);
  if($g['stage']==='resumo' && $previousStage!=='resumo' && isset($g['achievement_tracking_started'])){
   $titles=array_values(array_filter(Trophies::collect($g),fn($t)=>$t['season']===$g['season'] && self::recognizedTitle($t)));
   $promotions=[];foreach($g['competition_results']??[] as $id=>$r)if(!empty($r['promoted'])&&!empty($r['continuity']['valid'])&&!empty($r['continuity']['upper']))$promotions[]=['upper'=>$r['continuity']['upper'],'club'=>$g['club'],'season'=>$g['season']];
   $matches=array_filter(Competition::playerMatches($g),fn($m)=>in_array($g['club'],[$m['home'],$m['away']],true));$played=0;$losses=0;$finished=0;
   foreach($matches as $m){if($m['status']!=='finalizada')continue;$finished++;$played+=(int)(($m['stats']['minutes']??0)>0);$own=$m['home']===$g['club']?'hg':'ag';$losses+=(int)($m[$own]<$m[$own==='hg'?'ag':'hg']);}
   $invincible=count($matches)>0&&$finished===count($matches)&&$losses===0&&$played*2>=count($matches);
   $g['achievement_events'][hash('sha256','season|'.$g['season'])]=['type'=>'season','season'=>$g['season'],'club'=>$g['club'],'titles'=>$titles,'promotions'=>$promotions,'invincible'=>$invincible];
  }
 }
 private static function recognizedTitle(array $t):bool {
  if($t['kind']==='champion')return true;$id=$t['competition'];$c=Competition::catalog()[$id]??[];
  return in_array($id,['ar-1','py-1','us-1','ca-1'],true)||str_starts_with($c['rules']['system']??'','short_');
 }
}
