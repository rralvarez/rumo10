<?php
declare(strict_types=1);
/** Conservative projection. Never mutates saves or infers events from narrative prose. */
final class AchievementHistory {
 public static function audit(array $g):array {
  $s=Achievements::apply([],['type'=>'none']);$notes=[];$proof=[];$seen=[];$officialParticipation=[];
  $retired=$g['retired_summary']??null;
  $history=$retired?($retired['statistics']['seasons']??[]):($g['history']??[]);
  foreach($history as $h){
   $year=(int)($h['season']??0);if(!$year||(!$retired&&$year>=($g['season']??1)))continue;
   if(isset($seen[$year])){if($seen[$year]!==$h){$notes[]='Temporada divergente: '.$year;return ['state'=>Achievements::apply([],['type'=>'none']),'notes'=>$notes,'proof'=>[],'conflict'=>true];}continue;}$seen[$year]=$h;
   // Compact retirement summaries preserve authoritative totals, not individual matches.
   $official=$retired?self::officialSeason($retired,$year):count(array_intersect(array_keys($h['competitions']??[]),array_keys(Competition::catalog())))>0;
   if(!self::validTotals($h)){$notes[]='Temporada sem participação verificável: '.$year;continue;}
   $t=$h['evaluation']['totals']??null;
   if($t&&((int)($t['goals']??-1)!==(int)$h['goals']||(int)($t['assists']??-1)!==(int)$h['assists']||(int)($t['minutes']??-1)!==(int)$h['minutes'])){$notes[]='Resumo e avaliação divergentes na temporada '.$year.'; fonte não contabilizada.';continue;}
   $club=$h['club'];$s['matches']+=(int)$h['games'];if($official){$officialParticipation[$year][$club]=true;foreach(['goals','assists'] as $k)$s[$k]+=(int)$h[$k];}
   $t=$h['evaluation']['totals']??null;if($official&&$t && (int)$t['goals']===(int)$h['goals'] && (int)$t['assists']===(int)$h['assists'] && (int)$t['minutes']===(int)$h['minutes'])$s['tackles']+=max(0,(int)($t['tackles']??0));
   $s['clubs'][$club]=true;$s['participation'][$year][$club]=true;
   // Retirement compaction labels even a partial last season current=false.
   // That flag alone is not proof that the last season was completed.
   if(!$retired || $year<(int)($retired['identity']['season']??0))$s['seasons'][$year]=$club;
   foreach($h['competitions']??[] as $r)if(!empty($r['promoted'])&&!empty($r['continuity']['valid'])&&!empty($r['continuity']['upper']))$s['promotions'][]=['season'=>$year,'club'=>$club,'upper'=>$r['continuity']['upper']];
   $proof[]='season:'.$year.':summary';
  }
  if(!$retired){
   $official=!empty($g['official'])&&isset(Competition::catalog()[$g['competition_id']??'']);
   foreach(Competition::playerMatches($g) as $m){
    if(($m['status']??'')!=='finalizada'||!in_array($g['club'],[$m['home'],$m['away']],true)||!self::participation($m))continue;
    $id=implode('|',[$g['season'],$m['competition']??$g['competition_id']??'', $m['round'],$m['home'],$m['away']]);
    if(isset($seen['match:'.$id])){if($seen['match:'.$id]!==$m){$notes[]='Partida divergente';return ['state'=>Achievements::apply([],['type'=>'none']),'notes'=>$notes,'proof'=>[],'conflict'=>true];}continue;}$seen['match:'.$id]=$m;
    if($official&&self::validMatch($m,$g['club'])){$officialParticipation[$g['season']][$g['club']]=true;$s=Achievements::apply($s,['type'=>'match','season'=>$g['season'],'club'=>$g['club'],'competition'=>$m['competition']??$g['competition_id']??'','stats'=>$m['stats'],'flags'=>GoalAudit::flags($m,$g['club'])]);}
    else{$s['matches']++;$s['clubs'][$g['club']]=true;$s['participation'][$g['season']][$g['club']]=true;}
    $proof[]='match:'.hash('sha256',$id);
   }
   if(in_array($g['stage'],['resumo','oferta'],true)&&!empty($s['participation'][$g['season']][$g['club']])){
    $s['seasons'][$g['season']]=$g['club'];$ownMatches=array_filter(Competition::playerMatches($g),fn($m)=>in_array($g['club'],[$m['home'],$m['away']],true));$played=0;$safe=(bool)$ownMatches;
    foreach($ownMatches as $m){$side=$m['home']===$g['club']?'hg':'ag';if($m['status']!=='finalizada'||$m[$side]<$m[$side==='hg'?'ag':'hg'])$safe=false;if(self::validMatch($m,$g['club']))$played++;}
    if($official&&$safe&&$played*2>=count($ownMatches))$s['match_flags']['ESP07']=true;
   }
  }
  foreach(Trophies::collect($g) as $t){$year=(int)$t['season'];if(empty($officialParticipation[$year][$t['club']])||!isset(Competition::catalog()[$t['competition']]))continue;
   $id=$t['competition'];$c=Competition::catalog()[$id];$recognized=($t['kind']??'')==='champion'||in_array($id,['ar-1','py-1','us-1','ca-1'],true)||str_starts_with($c['rules']['system']??'','short_');
   if($recognized){$s['titles'][$year.'|'.$id.'|'.$t['title']]=$t;$proof[]='title:'.hash('sha256',$year.'|'.$id.'|'.$t['title']);}
  }
  $notes[]='Histórico anterior não verificável para feitos por jogada sem eventos estruturados; resumos não comprovam hat-trick, sequência ou gol decisivo.';
  return ['state'=>$s,'proof'=>$proof,'notes'=>$notes,'conflict'=>false];
 }
 private static function officialSeason(array $summary,int $year):bool {
  foreach($summary['competitions']??[] as $c)if((int)$c['season']===$year&&isset(Competition::catalog()[$c['competition']]))return true;return false;
 }
 private static function validTotals(array $s):bool {
  return isset($s['club'],$s['games'],$s['minutes'],$s['goals'],$s['assists'])&&is_int($s['games'])&&$s['games']>0&&$s['minutes']>0&&$s['minutes']<=$s['games']*120&&$s['goals']>=0&&$s['assists']>=0;
 }
 private static function validMatch(array $m,string $club):bool {
  $s=$m['stats']??[];if(!isset($s['minutes'],$s['goals'],$s['assists'],$s['tackles'])||$s['minutes']<=0||$s['minutes']>120)return false;
  foreach(['goals','assists','tackles'] as $k)if(!is_int($s[$k])||$s[$k]<0)return false;
  return isset($m['entry'],$m['exit'])&&$s['minutes']===max(0,$m['exit']-$m['entry'])&&$s['goals']+$s['assists']<=$m[$m['home']===$club?'hg':'ag'];
 }
 private static function participation(array $m):bool {
  $minutes=$m['stats']['minutes']??null;
  return is_int($minutes)&&$minutes>0&&$minutes<=120&&isset($m['entry'],$m['exit'])&&$minutes===max(0,$m['exit']-$m['entry']);
 }
 /** Baseline + only events added AFTER the audit; overlapping data never summed twice. */
 public static function merge(array $live,array $audit):array {
  $s=$audit['state'];$base=$audit['live_baseline']??[];
  foreach(['matches','goals','assists','tackles'] as $k)$s[$k]=max($s[$k]??0,$base[$k]??0)+max(0,($live[$k]??0)-($base[$k]??0));
  foreach(['clubs','seasons','titles','match_flags','participation'] as $k)$s[$k]=array_replace($s[$k]??[],$live[$k]??[]);
  return $s;
 }
}
