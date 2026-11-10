<?php
declare(strict_types=1);
final class InitialProfile {
 public static function effects(array $g,array $effects):array {
  $out=[];foreach($effects as $key=>$delta){$before=$g['skills'][$key]['value']??$g['states'][$key];$after=max(0,min(100,$before+$delta));$out[$key]=['before'=>$before,'after'=>$after,'delta'=>$after-$before];}return $out;
 }
 public static function render(array $g):void {
  echo '<section class="initial-impacts" aria-labelledby="initial-impacts-title"><h3 id="initial-impacts-title">O que você pode ganhar ou perder</h3><p>O servidor sorteia um perfil uma única vez. Confira os efeitos antes de girar.</p><ul>';$total=array_sum(array_column(Rules::HANDICAPS,'peso'));
  foreach(Rules::HANDICAPS as $h){echo '<li><strong>'.e($h['nome']).' · '.e(100*$h['peso']/$total).'%</strong><ul>';foreach(self::effects($g,$h['efeitos']) as $k=>$v)echo '<li>'.e(Presentation::label($k)).': '.e($v['before']).' → '.e($v['after']).' ('.e($v['delta']>=0?'+':'').e($v['delta']).')</li>';echo '</ul></li>';}
  echo '</ul></section>';
 }
}
