<?php
declare(strict_types=1);
/** Only observed grants; persisted with the match in the existing universe transaction. */
final class MatchExperience {
 private const LABELS=['velocidade'=>'Velocidade','resistencia'=>'Resistência','forca'=>'Força','visao_jogo'=>'Visão de jogo','passe'=>'Passe','finalizacao'=>'Finalização','drible'=>'Drible','desarme'=>'Desarme','posicionamento'=>'Posicionamento'];
 public static function begin(array &$g):void {
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  if(isset($m['experience']))return;
  $m['experience']=['schema'=>1,'before'=>$g['skills'],'earned'=>array_fill_keys(Rules::SKILLS,0),'bonus'=>array_fill_keys(Rules::SKILLS,0),'complete'=>false];
  if(isset($g['player_development']['parameters']['normalization']))$m['experience']['development_raw_before']=$g['player_development']['normalization_ledger'][$g['season']]['raw_scaled_xp']??0;
  if(PlayerDevelopment::enabled($g))$m['experience']['development_cost']=$g['player_development']['parameters']['cost']??['base'=>20,'divisor'=>5];
 }
 public static function record(array &$g,string $skill,int $earned):void {
  if(($g['stage']??'')!=='partida' || empty($g['matches']))return;
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  if(!isset($m['experience']) || $m['experience']['complete'])return;
  $m['experience']['earned'][$skill]+=$earned;
 }
 public static function finish(array &$g):void {
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  if(!isset($m['experience']) || $m['experience']['complete'])return;
  if(isset($m['experience']['development_raw_before']))$m['experience']['development_raw_xp']=max(0,($g['player_development']['normalization_ledger'][$g['season']]['raw_scaled_xp']??0)-$m['experience']['development_raw_before']);
  $m['experience']['after']=$g['skills'];$m['experience']['complete']=true;
 }
 public static function render(array $m):void {
  echo '<section class="match-experience" aria-label="Evolução nesta partida">';
  $s=$m['stats'];
  $x=$m['experience']??null;
  $total=$x && !empty($x['complete'])?array_sum($x['earned']):0;

  echo '<div class="match-protagonist-strip">';
  echo '<span class="protagonist-pill"><b>'.e($s['minutes']).'</b> <small>minutos</small></span>';
  echo '<span class="protagonist-pill rating-pill"><b>'.e(number_format((float)$s['rating'],1,',','.')).'</b> <small>nota</small></span>';
  echo '<span class="protagonist-pill"><b>'.e($s['goals']).'</b> <small>'.($s['goals']==1?'gol':'gols').'</small></span>';
  echo '<span class="protagonist-pill"><b>'.e($s['assists']).'</b> <small>assistências</small></span>';
  echo '</div>';

  if(!$x || empty($x['complete'])){
   echo '<p class="subtle">Partida anterior à medição: não há delta de XP confiável.</p></section>';
   return;
  }

  echo '<div class="match-xp-summary">';
  if(isset($x['development_raw_xp'])){
   echo '<div class="xp-badge"><strong>+'.e($total).' XP aplicados ao desenvolvimento</strong></div>';
  } else {
   echo '<div class="xp-badge"><strong>+'.e($total).' XP recebidos nesta partida</strong></div>';
  }
  $upgrades=[];
  foreach(Rules::SKILLS as $k){
   $a=$x['before'][$k]['value'];$b=$x['after'][$k]['value'];
   if($b>$a)$upgrades[]='<span class="upgrade-chip">⚡ <strong>'.e(self::LABELS[$k]).'</strong> '.e($a).' → '.e($b).' (+'.e($b-$a).')</span>';
  }
  if($upgrades)echo '<div class="xp-upgrades">'.implode(' ',$upgrades).'</div>';
  echo '</div>';

  echo '<details class="match-details-accordion"><summary>Ver detalhes de atuação e XP por atributo</summary>';
  echo '<div class="match-details-content">';
  if(!($m['stats']['minutes']??0))echo '<p class="subtle">Você não entrou em campo.</p>';
  echo '<p class="match-detailed-stats">'.e($s['shots']).' finalizações · '.e($s['completed']).'/'.e($s['passes']).' passes certos · '.e($s['tackles']).' desarmes · '.e($s['cards']).' cartões</p>';
  if(isset($x['development_raw_xp']))echo '<p class="subtle">'.e($x['development_raw_xp']).' XP bruto de atividades. A conversão tem rendimento marginal decrescente; os ganhos também respeitam o potencial individual.</p>';
  if(!$total)echo '<p class="subtle">Nenhum XP foi concedido nesta partida.</p>';
  echo '<p class="subtle">Bônus de conquistas: '.e(array_sum($x['bonus'])).' XP, contabilizados separadamente.</p>';

  echo '<dl class="xp-breakdown">';
  foreach(Rules::SKILLS as $k){
   $a=$x['before'][$k];$b=$x['after'][$k];$cost=$x['development_cost']??null;
   $threshold=$cost===null?Rules::xpThreshold($b['value']):($cost['base']+intdiv($b['value'],$cost['divisor']));
   $remaining=round(max(0,1-$b['xp']/$threshold)*100,1);$increase=$b['value']-$a['value'];
   echo '<div><dt>'.e(self::LABELS[$k]).'</dt><dd>'.e($a['value']).' → '.e($b['value']).($increase>0?' <strong>+'.e($increase).'</strong>':'').'<br>+'.e($x['earned'][$k]).' XP · acumulado '.e($b['xp']).'/'.e($threshold).' XP · faltam '.e(number_format($remaining,1,',','.')).'%</dd></div>';
  }
  echo '</dl></div></details></section>';
 }
}
