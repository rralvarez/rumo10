<?php
declare(strict_types=1);
final class OpportunityForecast {
 public static function utilization(float $technical,float $reference):array {
  $fp=class_exists('BalanceService')?BalanceService::params('market.opportunity.forecast'):[];
  $base=(float)($fp['related_base']??.65);$scale=(float)($fp['related_scale']??.33);$rDiv=(float)($fp['related_divisor']??12.0);
  $sOff=(float)($fp['starter_offset']??2.0);$sDiv=(float)($fp['starter_divisor']??9.0);$sub=(float)($fp['sub_rate']??.8);
  $delta=$technical-$reference;$related=$base+$scale/(1+exp(-$delta/$rDiv));$starter=1/(1+exp(-($delta+$sOff)/$sDiv));
  return ['related'=>$related,'starter'=>$starter,'sub'=>$sub,'share'=>$related*($starter*78+(1-$starter)*$sub*23)/90];
 }
 public static function estimate(array $g,string $club):array {
  $members=$g['next_memberships']??$g['memberships']??[];$games=0;$competitions=[];
  if(empty($g['official']))$games=14;
  else foreach($members as $id=>$teams){if(!in_array($club,$teams,true))continue;$t=Competition::tournament($id,$teams);$count=0;foreach($t['matches'] as $m)if(in_array($club,[$m['home'],$m['away']],true))$count++;if($id==='am-a1')$count=7;$games+=$count;$competitions[]=['id'=>$id,'scheduled_minimum'=>$count];}
  $technical=array_sum(array_column($g['skills'],'value'))/max(1,count($g['skills']));$opposition=[];
  foreach(ClubSquad::players($club) as $p)if(($p['position']??'')===$g['position']&&is_numeric($p['overall']??null))$opposition[]=(float)$p['overall'];
  rsort($opposition);$reference=$opposition[0]??TeamStrength::initial($club);$util=self::utilization($technical,$reference);$related=$util['related'];$starter=$util['starter'];$share=$util['share'];
  $expectedGames=(int)round($games*$related*($starter+(1-$starter)*$util['sub']));$percent=round($share*100,1);$minutes=(int)round($games*90*$percent/100);
  return ['schema'=>1,'scheduled_games'=>$games,'expected_games'=>$expectedGames,'minutes_percent'=>$percent,'expected_minutes'=>$minutes,'related_probability'=>$related,'starter_probability'=>$starter,'sub_probability'=>$util['sub'],'role'=>$share<.25?'Reserva eventual':($share<.45?'Reserva utilizado':($share<.65?'Disputa por titularidade':'Titular provável')),'short_calendar'=>$games<12,'position'=>$g['position'],'competition_reference'=>round($reference,1),'competitions'=>$competitions,'basis'=>'Calendário mínimo da próxima temporada simulada; mata-matas adicionais não garantidos. Concorrência do elenco de referência na posição (sintético quando indisponível), sem promessa de titularidade.'];
 }
 public static function render(array $f):void {echo '<p class="usage-forecast">Clube: aproximadamente <strong>'.e($f['scheduled_games']).' jogos</strong> · você: '.e($f['expected_games']).' participações · '.e($f['minutes_percent']).'% dos minutos · '.e($f['expected_minutes']).' minutos esperados</p><p>'.e($f['role']).($f['short_calendar']?' · calendário curto':'').'</p><details><summary>Como estimamos</summary><p class="subtle">'.e($f['basis']).'</p></details>';}
}
