<?php
declare(strict_types=1);
/** Uma única expectativa ofensiva, repartida entre protagonista e companheiros. */
final class MatchBalance {
 public const VERSION=2;
 public static function prepare(array &$m,array $g):void {
  if(isset($m['balance_version']))return;$m['balance_version']=self::VERSION;
  $m['player_attack_share']=match($g['position']){'centroavante'=>.60,'ponta'=>.55,'meia'=>.48,'lateral'=>.35,default=>.30};
  [$h,$a]=TeamStrength::expectations($m['home_overall'],$m['away_overall']);$own=$m['home']===$g['club']?'home':'away';$opponent=$m[$own==='home'?'away_overall':'home_overall'];
  $technical=array_sum(array_column($g['skills'],'value'))/9;
  $contribution=1+max(-.12,min(.12,($technical-$opponent)/250))*max(0,$m['exit']-$m['entry'])/90;
  if($own==='home')$h*=$contribution;else$a*=$contribution;
  $m['expected_goals']=['hg'=>$h,'ag'=>$a];$m['balance_expected_contribution']=$contribution;
 }
 public static function eventChance(array $g,float $raw):float {
  $m=$g['matches'][Game::currentIndex($g)];if(($m['balance_version']??0)!==self::VERSION)return $raw;
  $own=$m['home']===$g['club']?'hg':'ag';$quota=100*$m['expected_goals'][$own]*$m['player_attack_share']/7;
  return round(max(0,min($raw,$quota*(.4+.6*$raw/100))),2);
 }
 public static function collectiveMean(array $m,string $side,string $club,int $from,int $to):float {
  $mean=$m['expected_goals'][$side];$own=$m['home']===$club?'hg':'ag';$span=$to-$from;
  if($side===$own){$active=max(0,min($to,$m['exit'])-max($from,$m['entry']));$span-=$active*$m['player_attack_share'];}
  return $mean*max(0,$span)/90;
 }
}
