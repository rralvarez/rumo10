<?php
declare(strict_types=1);
/** Probabilidade da execução, condicionada à oportunidade já apresentada. */
final class ActionResolution {
 public const VERSION='execution-v04';
 public const SHOT_BASE=['long'=>.035,'difficult'=>.075,'normal'=>.18,'clear'=>.38,'one_on_one'=>.43];
 // Classificação mecânica revisada; não lê palavras nem altera snapshots editoriais.
 private const SHOTS=[1104=>[2=>'long'],1107=>[2=>'long'],1115=>[3=>'long'],1113=>[2=>'difficult'],1117=>[2=>'difficult'],1123=>[3=>'difficult'],2579=>[2=>'difficult'],2649=>[0=>'difficult']];
 public static function options(array $options,int $id):array {
  foreach($options as $index=>&$o)if($o['action']==='chute')$o['shot_kind']=self::SHOTS[$id][$index]??($id===0 && $index===3?'long':'normal');unset($o);return $options;
 }
 public static function trace(array $g,array $o):array {
  $type=$o['action'];$quality=Probability::effective($g['skills'],$type);$overall=array_sum(array_column($g['skills'],'value'))/count($g['skills']);
  $difficulty=$o['difficulty']??['social'=>42,'passe'=>43,'chute'=>65,'drible'=>60,'desarme'=>53][$type];
  if($g['stage']!=='partida')return ['version'=>self::VERSION,'chance'=>Probability::chance($quality,$g['states']['confianca'],$g['states']['energia'],$g['states']['ritmo_jogo'],$difficulty),'quality'=>$quality,'goal_execution'=>false];
  $m=$g['matches'][Game::currentIndex($g)];$home=$m['home']===$g['club'];$own=(float)($m[$home?'home_overall':'away_overall']??TeamStrength::rating($g['club'],$g));$oppSide=$home?'away':'home';$opponent=(float)($m[$oppSide.'_overall']??TeamStrength::rating($m[$oppSide],$g));
  $relative=.50*$overall+.25*$own+.25*(100-$opponent);$clear=!empty($o['clear_chance']);$goal=$type==='chute'||($clear&&in_array($type,['passe','drible'],true));
  if($clear && $type==='drible')$quality=.6*$quality+.4*$g['skills']['finalizacao']['value'];
  $energy=$g['states']['energia'];$confidence=$g['states']['confianca'];$rhythm=$g['states']['ritmo_jogo'];
  $components=['relative'=>.012*($relative-50),'quality'=>.024*($quality-50),'energy'=>.006*($energy-75),'confidence'=>.004*($confidence-50),'rhythm'=>.004*($rhythm-50)];
  if($goal){
   $kind=$clear?($type==='drible'?'one_on_one':($type==='passe'?'clear_assist':'clear')):($o['shot_kind']??($difficulty>=80?'difficult':'normal'));
   $base=$kind==='clear_assist'?.50:(self::SHOT_BASE[$kind]??throw new LogicException('Classe de finalização inválida.'));
   $reference=$clear?($type==='passe'?35:($type==='drible'?65:45)):($kind==='difficult'?80:65);$components['difficulty']=-.008*($difficulty-$reference);
   $logit=log($base/(1-$base))+array_sum($components);$chance=100/(1+exp(-$logit));
   // A promoção normal -> clara já remunera as duas ações. Não somar chance_bonus
   // nem reaplicar o orçamento coletivo como penalidade da execução.
   $sequenceIncluded=$clear?2:0;
   $extraSequenceBonus=$clear?0:min(.10,.10*MatchMomentum::builtActions($g));
   $chance+=(100-$chance)*$extraSequenceBonus;
  }else{
   $kind=null;$sequenceIncluded=0;$extraSequenceBonus=0;
   $chance=Probability::chance($quality,$confidence,$energy,$rhythm,$difficulty,50)+.4*($relative-50);
  }
  return ['version'=>self::VERSION,'chance'=>round(max($goal?.5:5,min($goal?90:95,$chance)),2),'goal_execution'=>$goal,'kind'=>$kind,'quality'=>round($quality,3),'player_overall'=>round($overall,3),'own_overall'=>$own,'opponent_overall'=>$opponent,'opponent_unit_source'=>'collective_fallback','relative'=>round($relative,3),'components'=>$components,'sequence_actions_included'=>$sequenceIncluded,'extra_sequence_bonus'=>$extraSequenceBonus,'difficulty'=>$difficulty];
 }
}
