<?php
declare(strict_types=1);
/** Fontes verificáveis; ausência de dado nunca significa evento verdadeiro. */
final class NarrativeContext {
 public const UNSUPPORTED=['weather','rivalry_verified','table_context','competition_type','knockout_round','leg','active_benefit','pressure','squad_morale'];
 public static function values(array $g):array {
  $out=['position'=>$g['position'],'bench'=>$g['states']['energia']<40 || $g['states']['confianca_treinador']<35 || $g['round']%4===0,'injury_risk'=>$g['states']['risco_lesao'],'disciplinary_risk'=>$g['states']['risco_cartao']];
  $available=$g['states']['energia']>=15 && $g['states']['risco_lesao']<=85;
  $out['bench']=$out['bench'] && $available;$out['starter']=!$out['bench'] && $available;
  // No pré-jogo a escalação é calculada depois da escolha. Só a regra da rodada 4
  // confirma reserva sem depender de bônus/penalidades da opção escolhida.
  $out['bench_confirmed']=$out['bench'] && $g['round']%4===0;
  if(in_array($g['stage'],['partida','fim_semana'],true)){
   $m=$g['matches'][Game::currentIndex($g)];$home=$m['home']===$g['club'];$diff=($home?$m['hg']-$m['ag']:$m['ag']-$m['hg']);
   $out+=['home_away'=>$home?'home':'away','score_state'=>$diff>0?'ganhando':($diff<0?'perdendo':'empatando'),'margin'=>abs($diff),'minute'=>$m['minute'],'played_minutes'=>max(0,min($m['minute'],$m['exit'])-$m['entry']),'own_goals'=>$m['stats']['goals'],'own_cards'=>$m['stats']['cards'],'on_field'=>$m['status']==='em_andamento' && $m['minute']>=$m['entry'] && $m['minute']<$m['exit'],'finished'=>$m['status']==='finalizada'];
   $out['bench']=($m['lineup']??'')==='reserva';$out['starter']=($m['lineup']??'')==='titular';$out['bench_confirmed']=$out['bench'];
  }
  $teamPlayed=array_values(array_filter($g['matches'],fn($m)=>$m['status']==='finalizada' && in_array($g['club'],[$m['home'],$m['away']],true)));
  $lastTeam=array_slice($teamPlayed,-3);$losses=0;$wins=0;
  foreach($lastTeam as $m){$delta=$m['home']===$g['club']?$m['hg']-$m['ag']:$m['ag']-$m['hg'];$losses+=$delta<0?1:0;$wins+=$delta>0?1:0;}
  $out['team_results_bad']=$losses>=2 && $wins===0;
  $played=array_values(array_filter($teamPlayed,fn($m)=>($m['stats']['minutes']??0)>0));
  if($played){$last=array_slice($played,-3);$avg=array_sum(array_column(array_column($last,'stats'),'rating'))/count($last);$out['recent_form']=$avg<6?'ruim':($avg>=7?'boa':'regular');$out['last_performance_bad']=end($played)['stats']['rating']<6;}
  return $out;
 }
 public static function allows(array $s,array $g,?array $context=null):bool {
  if($s['stage']!==$g['stage'] || !($s['enabled']??true))return false;
  $c=$context??self::values($g);$gate=$s['gating']??[];
  if($g['stage']==='partida' && empty($c['on_field']))return false;
  foreach($gate as $key=>$value){
   if(in_array($key,self::UNSUPPORTED,true) || $key==='future_competition')return false;
   $ok=match($key){
    'positions_allow'=>in_array($g['position'],$value,true),'positions_deny'=>!in_array($g['position'],$value,true),
    'home_away','score_state','recent_form','starter','bench','bench_confirmed','finished'=>array_key_exists($key,$c) && $c[$key]===$value,
    'minute_min'=>isset($c['minute']) && $c['minute']>=$value,'minute_max'=>isset($c['minute']) && $c['minute']<=$value,
    'margin_max'=>isset($c['margin']) && $c['margin']<=$value,'played_minutes_min'=>isset($c['played_minutes']) && $c['played_minutes']>=$value,
    'injury_risk_min'=>$c['injury_risk']>=$value,'disciplinary_risk_min'=>$c['disciplinary_risk']>=$value,
    'own_goals_min'=>isset($c['own_goals']) && $c['own_goals']>=$value,'own_cards_min'=>isset($c['own_cards']) && $c['own_cards']>=$value,
    default=>false,
   };if(!$ok)return false;
  }
  return true;
 }
}

