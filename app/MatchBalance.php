<?php
declare(strict_types=1);
/** Uma única expectativa ofensiva, repartida entre protagonista e companheiros. */
final class MatchBalance {
 public const VERSION=3;
 public static function prepare(array &$m,array $g):void {
  if(isset($m['balance_version']))return;$m['balance_version']=self::VERSION;
  $quotas = class_exists('BalanceService') ? BalanceService::params('match.balance.quotas') : [];
  $m['player_attack_share']=match($g['position']){
      'centroavante'=>(float)($quotas['share_ca']??.60),
      'ponta'=>(float)($quotas['share_pt']??.55),
      'meia'=>(float)($quotas['share_me']??.48),
      'lateral'=>(float)($quotas['share_lt']??.35),
      default=>(float)($quotas['share_def']??.30)
  };
  [$h,$a]=TeamStrength::expectations($m['home_overall'],$m['away_overall']);$own=$m['home']===$g['club']?'home':'away';$opponent=$m[$own==='home'?'away_overall':'home_overall'];
  $technical=array_sum(array_column($g['skills'],'value'))/9;
  $maxBoost=(float)($quotas['team_boost_max']??.12);
  $contribution=1+max(-$maxBoost,min($maxBoost,($technical-$opponent)/250))*max(0,$m['exit']-$m['entry'])/90;
  if($own==='home')$h*=$contribution;else$a*=$contribution;
  $m['expected_goals']=['hg'=>$h,'ag'=>$a];$m['balance_expected_contribution']=$contribution;
  $m['pass_conversion']=(int)round(max(12,min(32,18+.25*(Probability::effective($g['skills'],'passe')-45))));
 }
 public static function eventChance(array $g,float $raw):float {
  $m=$g['matches'][Game::currentIndex($g)];if(!in_array($m['balance_version']??0,[2,self::VERSION],true))return $raw;
  $own=$m['home']===$g['club']?'hg':'ag';$quota=100*$m['expected_goals'][$own]*$m['player_attack_share']/7;
  $available=($m['balance_version']??0)===self::VERSION?100*max(0,$m['expected_goals'][$own]-($m['background_expected_spent']??0)-($m['personal_expected_spent']??0)):100;
  return round(max(0,min($raw,$available,$quota*(.4+.6*$raw/100))),2);
 }
 public static function book(array &$m,float $probability):void {
  if(($m['balance_version']??0)!==self::VERSION)return;
  $m['personal_expected_spent']=($m['personal_expected_spent']??0)+$probability;
  $m['pending_personal_expectation']=($m['pending_personal_expectation']??0)+$probability;
 }
 public static function collectiveMean(array &$m,string $side,string $club,int $from,int $to):float {
  $mean=$m['expected_goals'][$side];$own=$m['home']===$club?'hg':'ag';$span=$to-$from;
  if(($m['balance_version']??0)===2){if($side===$own){$active=max(0,min($to,$m['exit'])-max($from,$m['entry']));$span-=$active*$m['player_attack_share'];}return $mean*max(0,$span)/90;}
  $expected=$mean*max(0,$span)/90;
  if($side===$own){$pending=$m['pending_personal_expectation']??0;$discount=min($expected,$pending);$expected-=$discount;$m['pending_personal_expectation']=$pending-$discount;
   $available=max(0,$mean-($m['background_expected_spent']??0)-($m['personal_expected_spent']??0));$expected=min($expected,$available);$m['background_expected_spent']=($m['background_expected_spent']??0)+$expected;}
  return max(0,$expected);
 }
}
