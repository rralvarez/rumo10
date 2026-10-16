<?php
declare(strict_types=1);
/** Only observed grants; persisted with the match in the existing universe transaction. */
final class MatchExperience {
 private const LABELS=['velocidade'=>'Velocidade','resistencia'=>'Resistência','forca'=>'Força','visao_jogo'=>'Visão de jogo','passe'=>'Passe','finalizacao'=>'Finalização','drible'=>'Drible','desarme'=>'Desarme','posicionamento'=>'Posicionamento'];
 public static function begin(array &$g):void {
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  if(isset($m['experience']))return;
  $m['experience']=['schema'=>1,'before'=>$g['skills'],'earned'=>array_fill_keys(Rules::SKILLS,0),'bonus'=>array_fill_keys(Rules::SKILLS,0),'complete'=>false];
 }
 public static function record(array &$g,string $skill,int $earned):void {
  if(($g['stage']??'')!=='partida' || empty($g['matches']))return;
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  // A match already in flight when upgrading has no reliable start snapshot.
  if(!isset($m['experience']) || $m['experience']['complete'])return;
  $m['experience']['earned'][$skill]+=$earned;
 }
 public static function finish(array &$g):void {
  $i=Game::currentIndex($g);$m=&$g['matches'][$i];
  if(!isset($m['experience']) || $m['experience']['complete'])return;
  $m['experience']['after']=$g['skills'];$m['experience']['complete']=true;
 }
 public static function render(array $m):void {
  echo '<section class="match-experience" aria-label="Evolução nesta partida"><h3>Evolução nesta partida</h3>';
  $s=$m['stats'];echo '<p>'.e($s['minutes']).' minutos · nota '.e($s['rating']).' · '.e($s['goals']).' gols · '.e($s['assists']).' assistências</p><p>'.e($s['shots']).' finalizações · '.e($s['completed']).'/'.e($s['passes']).' passes certos · '.e($s['tackles']).' desarmes · '.e($s['cards']).' cartões</p>';
  $x=$m['experience']??null;
  if(!$x || empty($x['complete'])){echo '<p>Partida anterior à medição: não há delta de XP confiável.</p></section>';return;}
  $total=array_sum($x['earned']);
  if(!($m['stats']['minutes']??0))echo '<p>Você não entrou em campo.</p>';
  echo '<p><strong>'.e($total).' XP recebidos nesta partida</strong></p>';
  if(!$total)echo '<p>Nenhum XP foi concedido nesta partida.</p>';
  foreach(Rules::SKILLS as $k){$a=$x['before'][$k]['value'];$b=$x['after'][$k]['value'];if($b>$a)echo '<p><strong>'.e(self::LABELS[$k]).' '.e($a).' → '.e($b).' (+'.e($b-$a).')</strong></p>';}
  echo '<p>Bônus de conquistas: '.e(array_sum($x['bonus'])).' XP, contabilizados separadamente.</p><details><summary>Ver XP por atributo</summary><dl class="xp-breakdown">';
  foreach(Rules::SKILLS as $k){$a=$x['before'][$k];$b=$x['after'][$k];$threshold=Rules::xpThreshold($b['value']);$remaining=round(max(0,1-$b['xp']/$threshold)*100,1);$increase=$b['value']-$a['value'];
   echo '<div><dt>'.e(self::LABELS[$k]).'</dt><dd>'.e($a['value']).' → '.e($b['value']).($increase>0?' <strong>+'.e($increase).'</strong>':'').'<br>+'.e($x['earned'][$k]).' XP · acumulado '.e($b['xp']).'/'.e($threshold).' XP · faltam '.e(number_format($remaining,1,',','.')).'%</dd></div>';
  }
  echo '</dl></details></section>';
 }
}
