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
  $s=$m['stats']??[];
  $minutes=isset($s['minutes'])?(int)$s['minutes']:0;
  $rating=isset($s['rating'])?(float)$s['rating']:0.0;
  $ratingStr=number_format($rating,1,',','.');
  $goals=isset($s['goals'])?(int)$s['goals']:0;
  $assists=isset($s['assists'])?(int)$s['assists']:0;
  $goalsLabel=($goals===1?'Gol':'Gols');
  $assistsLabel=($assists===1?'Assistência':'Assistências');
  $x=$m['experience']??null;
  $total=$x && !empty($x['complete'])?array_sum($x['earned']):0;

  echo '<div class="match-protagonist-strip" role="group" aria-label="Estatísticas do jogador na partida">';

  // 1. Minutos
  echo '<div class="stat-chip stat-chip-minutes" aria-label="Minutos jogados: '.e($minutes).'">';
  echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg><span class="stat-value">'.e($minutes).'</span></div>';
  echo '<span class="stat-label">Minutos</span>';
  echo '</div>';

  // 2. Nota
  echo '<div class="stat-chip stat-chip-rating" aria-label="Nota do jogador: '.e($ratingStr).'">';
  echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span class="stat-value">'.e($ratingStr).'</span></div>';
  echo '<span class="stat-label">Nota</span>';
  echo '</div>';

  // 3. Gols
  echo '<div class="stat-chip stat-chip-goals" aria-label="'.e($goals.' '.$goalsLabel).'">';
  echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7l3 2v3.5l-3 2-3-2V9z"/><path d="M12 3v4M15 9l3.5-1.5M15 12.5l3.5 1.5M12 14.5v4.5M9 12.5L5.5 14M9 9L5.5 7.5"/></svg><span class="stat-value">'.e($goals).'</span></div>';
  echo '<span class="stat-label">'.e($goalsLabel).'</span>';
  echo '</div>';

  // 4. Assistências
  echo '<div class="stat-chip stat-chip-assists" aria-label="'.e($assists.' '.$assistsLabel).'">';
  echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 17l4-1 6 3h5a2 2 0 0 0 2-2v-1l-3-4-5-1-4 2-5 3z"/><path d="M10 11l-2 3M13 12l-1 3"/></svg><span class="stat-value">'.e($assists).'</span></div>';
  echo '<span class="stat-label">'.e($assistsLabel).'</span>';
  echo '</div>';

  echo '</div>';

  if(!$x || empty($x['complete'])){
   echo '<p class="subtle">Partida anterior à medição: não há delta de XP confiável.</p></section>';
   return;
  }

  echo '<div class="match-xp-summary">';
  echo '<div class="xp-badge">';
  echo '<svg class="xp-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
  echo '<span class="xp-val">+'.e($total).' XP</span>';
  if(isset($x['development_raw_xp'])){
   echo '<span class="xp-desc">· Aplicados ao desenvolvimento</span>';
  } else {
   echo '<span class="xp-desc">· Recebidos nesta partida</span>';
  }
  echo '</div>';

  $upgrades=[];
  foreach(Rules::SKILLS as $k){
   $a=$x['before'][$k]['value'];$b=$x['after'][$k]['value'];
   if($b>$a)$upgrades[]='<span class="upgrade-chip"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <strong>'.e(self::LABELS[$k]).'</strong> '.e($a).' → '.e($b).' (+'.e($b-$a).')</span>';
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
