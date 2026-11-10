<?php
declare(strict_types=1);
/** Experimental opt-in curve. No target overall or population tier is stored. */
final class PlayerDevelopment {
 public const VERSION='development-v3-isolated';
 private const PREVIOUS_VERSION='development-v2-experimental';
 public const ANNUAL_BASE=410;
 public const ANNUAL_MERIT=175;
 private const PHYSICAL=['velocidade','resistencia','forca'];
 public static function enabled(array $g):bool{if(!array_key_exists('player_development',$g))return false;if(!is_array($g['player_development'])||!in_array($g['player_development']['version']??null,[self::VERSION,self::PREVIOUS_VERSION],true))throw new LogicException('Unsupported development version.');return true;}
 private static function unit(string $seed,string $key):float{return (hexdec(substr(hash('sha256',$seed.'|'.$key),0,13))+.5)/4503599627370496;}
 public static function initialize(array &$g,?string $seed=null):void{
  if(isset($g['player_development']))throw new LogicException('Development profile already fixed.');
  if($g['season']!==1 || $g['age']!==16 || !empty($g['history']))throw new LogicException('New 16-year-old careers only; no implicit migration.');
  $seed??=bin2hex(random_bytes(16));
  $z=sqrt(-2*log(self::unit($seed,'normal-a')))*cos(2*M_PI*self::unit($seed,'normal-b'));
  $main=match($g['position']){'zagueiro'=>['desarme','posicionamento','forca'],'lateral'=>['velocidade','passe','resistencia'],'volante'=>['posicionamento','desarme','passe'],'meia'=>['passe','visao_jogo','posicionamento'],'ponta'=>['drible','velocidade','finalizacao'],default=>['finalizacao','posicionamento','forca']};
  $ceilings=[];foreach(Rules::SKILLS as $skill){$offset=(in_array($skill,$main,true)?1.5:-.75)+(self::unit($seed,'skill:'.$skill)-.5)*3;$ceilings[$skill]=(int)round(max(60,min(100,92+7*$z+$offset)));}
  $annualBase = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'annual_base', self::ANNUAL_BASE) : self::ANNUAL_BASE;
  $annualMerit = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'annual_merit', self::ANNUAL_MERIT) : self::ANNUAL_MERIT;
  $normScale = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'normalization_scale', 250) : 250;
  $costBase = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'cost_base', 20) : 20;
  $costDivisor = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'cost_divisor', 5) : 5;
  $ceilingOpenAge = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'ceiling_open_age', 27) : 27;
  $agingAccel = class_exists('BalanceService') ? (float)BalanceService::param('dev.v3.parameters', 'aging_acceleration', 0.075) : 0.075;
  $g['player_development']=['version'=>self::VERSION,'seed'=>$seed,'parameters'=>['calibration'=>'calendar-log250-smooth024016-budget410-isolated','normalization'=>['schema'=>'marginal-log-v1','scale'=>$normScale],'learning_model'=>['schema'=>'smooth-log-learning-v1','linear'=>.24,'saturating'=>.16],'cost'=>['base'=>$costBase,'divisor'=>$costDivisor],'annual_base'=>$annualBase,'annual_merit'=>$annualMerit,'rates'=>[1.45,1.2,.85,.15,.08],'ceiling_open_age'=>$ceilingOpenAge,'aging_acceleration'=>$agingAccel],'learning'=>round(max(.35,min(2.6,exp(.24*$z+.16*$z/(1+abs($z))))),6),'ceilings'=>$ceilings,'physical_decline_start'=>32+(int)floor(3*self::unit($seed,'maturity')),'aging_rate'=>round(.65+.65*self::unit($seed,'aging'),6),'technical_decline_start'=>37+(int)floor(3*self::unit($seed,'technical-maturity')),'received'=>array_fill_keys(Rules::SKILLS,0),'spent'=>array_fill_keys(Rules::SKILLS,0),'points'=>array_fill_keys(Rules::SKILLS,0)];
 }
 public static function cost(array $g,int $level):int{$c=$g['player_development']['parameters']['cost']??['base'=>20,'divisor'=>5];return $c['base']+intdiv($level,$c['divisor']);}
 public static function rate(array $g):float{$r=$g['player_development']['parameters']['rates'];return $r[$g['age']<=22?0:($g['age']<=27?1:($g['age']<=31?2:($g['age']<=35?3:4)))];}
 public static function ceiling(array $g,string $skill):int{return min($g['player_development']['ceilings'][$skill],65+(int)floor(max(0,$g['age']-16)*35/($g['player_development']['parameters']['ceiling_open_age']-16)));}
 public static function eligible(array $g,string $skill):bool{return !(in_array($skill,self::PHYSICAL,true)&&$g['age']>$g['player_development']['physical_decline_start'])&&$g['skills'][$skill]['value']<self::ceiling($g,$skill);}
 public static function budget(array &$g,int $base,string $source):int {
  $factor=self::rate($g)*$g['player_development']['learning'];
  $n=$g['player_development']['parameters']['normalization']??null;
  if($n===null){if(($g['player_development']['version']??null)===self::VERSION)throw new LogicException('Missing normalization contract.');return max(0,(int)round($base*$factor));}
  if(($g['player_development']['version']??null)!==self::VERSION || !is_array($n) || ($n['schema']??null)!=='marginal-log-v1' || !is_numeric($n['scale']??null) || !is_finite((float)$n['scale']) || $n['scale']<=0)throw new LogicException('Unsupported normalization contract.');
  if($source==='annual')return max(0,(int)round($base*$factor));
  $base=max(0,$base);$d=&$g['player_development']['normalization_ledger'][$g['season']];
  if(!is_array($d))$d=['raw_base'=>0,'raw_scaled_xp'=>0,'conversion_budget'=>0,'sources'=>[]];
  $previous=$d['conversion_budget'];$d['raw_base']+=$base;$beforeRaw=$d['raw_scaled_xp'];$d['raw_scaled_xp']=(int)round($d['raw_base']*$factor);$raw=$d['raw_scaled_xp']-$beforeRaw;
  $d['conversion_budget']=(int)round($n['scale']*log1p($d['raw_base']/$n['scale'])*$factor);
  $delta=$d['conversion_budget']-$previous;
  if(!isset($d['sources'][$source]))$d['sources'][$source]=['raw_base'=>0,'raw_scaled_xp'=>0,'conversion_budget'=>0];
  $d['sources'][$source]['raw_base']+=$base;$d['sources'][$source]['raw_scaled_xp']+=$raw;$d['sources'][$source]['conversion_budget']+=$delta;
  return max(0,$delta);
 }
 public static function gain(array &$g,string $skill,int $base):int{return self::credit($g,$skill,self::budget($g,$base,'activity'));}
 private static function credit(array &$g,string $skill,int $earned):int{
  if(!self::eligible($g,$skill)||$earned<=0)return 0;
  $s=&$g['skills'][$skill];$s['xp']+=$earned;$g['player_development']['received'][$skill]+=$earned;
  $g['season_xp_earned'][$g['season']]=($g['season_xp_earned'][$g['season']]??0)+$earned;
  while($s['value']<self::ceiling($g,$skill)&&$s['xp']>=self::cost($g,$s['value'])){
   $cost=self::cost($g,$s['value']);$s['xp']-=$cost;$s['value']++;$g['player_development']['spent'][$skill]+=$cost;$g['player_development']['points'][$skill]++;
  }
  MatchExperience::record($g,$skill,$earned);return $earned;
 }
 public static function distribute(array &$g,int $base,string $key):void{
  if(isset($g['reward_ledger'][$key]))return;
  $source=str_starts_with($key,'development:')?'annual':(str_starts_with($key,'match:')?'match':(str_starts_with($key,'title:')?'title':'other'));
  $budget=self::budget($g,$base,$source);
  $g['reward_ledger'][$key]=['base_xp'=>$base,'budget_xp'=>$budget,'rules'=>$g['player_development']['version']];
  $weights=[];$main=match($g['position']){'zagueiro'=>['desarme','posicionamento','forca'],'lateral'=>['velocidade','passe','resistencia'],'volante'=>['posicionamento','desarme','passe'],'meia'=>['passe','visao_jogo','posicionamento'],'ponta'=>['drible','velocidade','finalizacao'],default=>['finalizacao','posicionamento','forca']};
  foreach(Rules::SKILLS as $skill)if(self::eligible($g,$skill))$weights[$skill]=(in_array($skill,$main,true)?1.12:1)*max(.25,(110-$g['skills'][$skill]['value'])/60);
  if(!$weights){$g['reward_ledger'][$key]['credited_xp']=0;return;}
  $sum=array_sum($weights);$parts=[];$fractions=[];
  foreach($weights as $skill=>$w){$exact=$budget*$w/$sum;$parts[$skill]=(int)floor($exact);$fractions[$skill]=$exact-floor($exact);}
  // Stable tie order from SKILLS; conserve one rounded budget rather than nine rounded slices.
  uksort($fractions,static fn($a,$b)=>($fractions[$b]<=>$fractions[$a])?:array_search($a,Rules::SKILLS,true)<=>array_search($b,Rules::SKILLS,true));
  $left=$budget-array_sum($parts);foreach(array_keys($fractions) as $skill){if($left--<=0)break;$parts[$skill]++;}
  $credited=0;foreach($parts as $skill=>$amount)$credited+=self::credit($g,$skill,$amount);
  $g['reward_ledger'][$key]['credited_xp']=$credited;
 }
 public static function season(array &$g):void{
  $key='development:'.$g['season'];if(isset($g['reward_ledger'][$key]))return;
  $games=0;$minutes=0;$ratings=0.;$contributions=0.;$def=in_array($g['position'],['zagueiro','lateral','volante'],true);
  foreach(Competition::playerMatches($g) as $m){$s=$m['stats']??[];if(!in_array($m['balance_version']??0,[2,3],true)||$m['status']!=='finalizada'||!($s['minutes']??0)||!in_array($g['club'],[$m['home'],$m['away']],true))continue;$games++;$minutes+=$s['minutes'];$ratings+=$s['rating'];$contributions+=$s['assists']+$s['goals']*($def?.6:1)+($def?$s['tackles']*.12:0);}
  if($games<6||$minutes<360)return;
  $quality=max(0,min(1,($ratings/$games-6)/2+min(1,$contributions*90/$minutes)*.15));
  $noise=.95+.1*self::unit($g['player_development']['seed'],'season:'.$g['season']);
  $target=($g['player_development']['parameters']['annual_base']+$g['player_development']['parameters']['annual_merit']*$quality)*min(1,$minutes/900)*self::rate($g)*$g['player_development']['learning']*$noise;
  $base=max(0,(int)round(($target-($g['season_xp_earned'][$g['season']]??0))/(self::rate($g)*$g['player_development']['learning'])));
  self::distribute($g,$base,$key);$g['reward_ledger'][$key]+=['quality'=>round($quality,6),'qualified_games'=>$games,'minutes'=>$minutes,'target_xp'=>(int)round($target)];
 }
 private static function cumulativeLoss(array $g,string $skill,int $age):int{
  $d=$g['player_development'];
  if(in_array($skill,self::PHYSICAL,true)){$years=max(0,$age-$d['physical_decline_start']);$factor=match($skill){'velocidade'=>1.12,'forca'=>.88,default=>1.};return (int)floor($d['aging_rate']*$factor*($years+$d['parameters']['aging_acceleration']*$years*$years));}
  if(in_array($skill,['visao_jogo','posicionamento'],true))return (int)floor(max(0,$age-39)*$d['aging_rate']*.15);
  return (int)floor(max(0,$age-$d['technical_decline_start'])*$d['aging_rate']*.45);
 }
 public static function birthday(array &$g):array{
  if(($g['aging_applied_age']??0)>=$g['age'])return [];
  $previous=(int)($g['aging_applied_age']??($g['age']-1));$g['aging_applied_age']=$g['age'];$changes=[];
  foreach(Rules::SKILLS as $skill){$loss=max(0,self::cumulativeLoss($g,$skill,$g['age'])-self::cumulativeLoss($g,$skill,$previous));$old=$g['skills'][$skill]['value'];$g['skills'][$skill]['value']=max(0,$old-$loss);if($old!==$g['skills'][$skill]['value'])$changes[$skill]=$g['skills'][$skill]['value']-$old;}
  return $changes;
 }
}
