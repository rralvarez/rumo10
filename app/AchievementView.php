<?php
declare(strict_types=1);
final class AchievementView {
 private static function hub(Repository $r,int $account):int {return (int)$r->query('SELECT hub_usuario_id FROM rumo10_identidades WHERE conta_id=?',[$account])->fetchColumn();}
 public static function render(Repository $r,int $account):void {
  $catalog=AchievementCatalog::all();$unlocks=[];$progress=[];$hub=Achievements::enabled()?self::hub($r,$account):0;
  if(Achievements::enabled() && $hub){
   $unlocks=array_column($r->query('SELECT conquista_id,pontos_g,criado_em FROM rumo10_conquistas WHERE hub_usuario_id=?',[$hub])->fetchAll(),null,'conquista_id');
   $states=$r->query('SELECT estado_json,valida FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=?',[$hub])->fetchAll();$valid=array_sum(array_column($states,'valida'));
   foreach($states as $row)foreach(Achievements::progress(json_decode($row['estado_json'],true,512,JSON_THROW_ON_ERROR),$valid) as $id=>$p)if(!isset($progress[$id]) || $p[0]>$progress[$id][0])$progress[$id]=$p;
  }
  $filter=(string)($_GET['filtro']??'todas');$filters=['todas'=>'Todas','desbloqueadas'=>'Desbloqueadas','disponiveis'=>'Disponíveis','futuras'=>'Futuras'];if(!isset($filters[$filter]))$filter='todas';
  echo '<section class="panel"><h1>Sala de Conquistas</h1><p><strong>'.e(array_sum(array_column($unlocks,'pontos_g'))).'/1000 G</strong> · '.count($unlocks).'/50 conquistas</p><p>G é reputação permanente. Não é moeda nem XP.</p><a href="/carreira">Voltar à carreira</a>';
  if(!Achievements::enabled())echo '<p role="status">Catálogo em preparação. Persistência ainda não ativada nesta instalação.</p>';
  echo '<nav aria-label="Filtrar conquistas">';foreach($filters as $value=>$name)echo '<a class="btn" href="/conquistas?filtro='.$value.'"'.($filter===$value?' aria-current="page"':'').'>'.e($name).'</a>';echo '</nav><div class="achievement-grid">';
  foreach($catalog as $id=>$a){$u=$unlocks[$id]??null;if(($filter==='desbloqueadas'&&!$u)||($filter==='disponiveis'&&(!$a['active']||$u))||($filter==='futuras'&&$a['active']))continue;
   $secret=$a['secret']&&!$u;$p=$progress[$id]??[0,1];$status=$u?'Desbloqueada':(!$a['active']?'Futura · indisponível':'Em progresso');
   echo '<article class="panel achievement"><svg viewBox="0 0 48 48" width="40" height="40" aria-hidden="true"><path d="M24 3 43 15v19L24 45 5 34V15Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15 25 6 6 13-16" fill="none" stroke="currentColor" stroke-width="3"/></svg><h2>'.e($secret?'Conquista secreta':$a['name']).'</h2><p>'.e($a['g']).' G · '.e($status).'</p>';
   if(!$secret)echo '<p>'.e($a['description']).'</p>';
   if(!$a['active'])echo '<p>'.e($secret?'Depende de um recurso futuro.':$a['inactive_reason']).'</p>';
   elseif(!$u&&!$secret)echo '<p>Progresso: '.e(min($p[0],$p[1])).'/'.e($p[1]).'</p><progress max="'.e($p[1]).'" value="'.e(min($p[0],$p[1])).'" aria-label="'.e($a['name']).'"></progress>';
   if($u)echo '<p>Desbloqueada em '.e($u['criado_em']).' UTC</p>';echo '</article>';
  }echo '</div></section>';
 }
 /** Claim notification atomically; a reload cannot replay it. No rewards on GET. */
 public static function toast(Repository $r,int $account):void {
  if(!Achievements::enabled())return;$hub=self::hub($r,$account);if(!$hub)return;
  $r->db->beginTransaction();try{
   $rows=$r->query('SELECT conquista_id,pontos_g FROM rumo10_conquistas WHERE hub_usuario_id=? AND notificada=0 ORDER BY criado_em,conquista_id LIMIT 3 FOR UPDATE',[$hub])->fetchAll();
   foreach($rows as $row)$r->query('UPDATE rumo10_conquistas SET notificada=1 WHERE hub_usuario_id=? AND conquista_id=?',[$hub,$row['conquista_id']]);$r->db->commit();
  }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
  if(!$rows)return;echo '<aside class="achievement-toast panel" role="status" aria-live="polite" aria-atomic="true"><p>Conquista desbloqueada</p>';$catalog=AchievementCatalog::all();foreach($rows as $row)echo '<p><strong>'.e($catalog[$row['conquista_id']]['name']).'</strong> · +'.e($row['pontos_g']).' G</p>';echo '<a href="/conquistas">Ver conquistas</a></aside>';
 }
}
