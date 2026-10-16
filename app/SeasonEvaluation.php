<?php
declare(strict_types=1);
final class SeasonEvaluation {
 public const SAMPLE_MINUTES=900;
 public static function current(array $g):array {
  $totals=array_fill_keys(['goals','assists','minutes','shots','passes','completed','tackles','cards'],0);$rating=0.;$games=0;$contextSum=0.;$contextMinutes=0;
  foreach(Competition::playerMatches($g) as $m){if(($m['status']??'')!=='finalizada'||!in_array($g['club'],[$m['home'],$m['away']],true))continue;$s=$m['stats']??[];$minutes=max(0,(int)($s['minutes']??0));if(!$minutes)continue;$games++;foreach($totals as $key=>$_)$totals[$key]+=max(0,(int)($s[$key]??0));$rating+=(float)($s['rating']??6)*$minutes;
   if(isset($m['home_overall'],$m['away_overall'])){$own=$m['home']===$g['club']?'home_overall':'away_overall';$other=$own==='home_overall'?'away_overall':'home_overall';$contextSum+=max(.85,min(1.15,1+($m[$other]-$m[$own])/200))*$minutes;$contextMinutes+=$minutes;}
  }
  $minutes=$totals['minutes'];$rates=[];foreach($totals as $key=>$value)if($key!=='minutes')$rates[$key]=$minutes?round(90*$value/$minutes,4):0.;
  $weights=match($g['position']){'zagueiro'=>[.15,.35,.50],'lateral'=>[.20,.45,.35],'volante'=>[.15,.35,.50],'meia'=>[.30,.55,.15],'ponta'=>[.50,.45,.05],default=>[.70,.28,.02]};
  // Defensive actions only contribute for positions where they are meaningful. No unrecorded saves/clean sheets are invented.
  $production=$rates['goals']*$weights[0]+$rates['assists']*$weights[1]+min(2.,$rates['tackles'])*$weights[2]*.35;
  $confidence=$minutes/($minutes+self::SAMPLE_MINUTES);$average=$minutes?$rating/$minutes:6.;
  $context=$contextMinutes?$contextSum/$contextMinutes:1.;$score=max(-.5,min(1.5,($average-6)/2+$production*.65*$context))*$confidence;
  return ['schema'=>1,'position'=>$g['position'],'totals'=>$totals,'per90'=>$rates,'games'=>$games,'average'=>round($average,3),'confidence'=>round($confidence,4),'opponent_context_factor'=>round($context,4),'context_minutes'=>$contextMinutes,'sample_minutes_reference'=>self::SAMPLE_MINUTES,'conversion'=>$totals['shots']?$totals['goals']/$totals['shots']:null,'overall'=>array_sum(array_column($g['skills'],'value'))/max(1,count($g['skills'])),'unavailable_metrics'=>['passes_decisivos','interceptacoes','duelos','defesas','gols_evitados'],'score'=>round($score,4)];
 }
 public static function evaluate(array $g):array {
  $current=self::current($g);$parts=[['season'=>(int)$g['season'],'weight'=>.70,'score'=>$current['score']]];$history=$g['history']??[];usort($history,fn($a,$b)=>($b['season']??0)<=>($a['season']??0));$seen=[$g['season']=>true];
  foreach($history as $h){$season=$h['season']??0;if($season>=$g['season']||isset($seen[$season])||!isset($h['evaluation']['score']))continue;$seen[$season]=true;$parts[]=['season'=>$season,'weight'=>count($parts)===1?.20:.10,'score'=>(float)$h['evaluation']['score']];if(count($parts)===3)break;}
  $weight=array_sum(array_column($parts,'weight'));$score=0.;foreach($parts as &$part){$part['weight']/=$weight;$score+=$part['weight']*$part['score'];}unset($part);
  return $current+['history_weights'=>$parts,'weighted_score'=>round($score,4),'history_available'=>count($parts)-1];
 }
}
