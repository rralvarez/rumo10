<?php
declare(strict_types=1);
final class AchievementView {
 private static function hub(Repository $r,int $account):int {return (int)$r->query('SELECT hub_usuario_id FROM rumo10_identidades WHERE conta_id=?',[$account])->fetchColumn();}
 public static function brazilTime(string $utc):string {
  $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$utc,new DateTimeZone('UTC'));
  return $date?$date->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y \à\s H:i:s').' (Brasília)':'Data não disponível';
 }
 public static function render(Repository $r,int $account):void {
  $catalog=AchievementCatalog::all();$unlocks=[];$progress=[];$available=Achievements::enabled()&&FeatureSchema::ready($r,FeatureSchema::ACHIEVEMENTS);$hub=$available?self::hub($r,$account):0;
  if($available && $hub){
   $r->query("SET time_zone='+00:00'");
   $sourceReady=FeatureSchema::ready($r,FeatureSchema::RECONCILIATION);$sql=$sourceReady?'SELECT c.*,f.origem,f.ocorrido_em FROM rumo10_conquistas c LEFT JOIN rumo10_conquistas_fontes f ON f.hub_usuario_id=c.hub_usuario_id AND f.conquista_id=c.conquista_id WHERE c.hub_usuario_id=?':'SELECT * FROM rumo10_conquistas WHERE hub_usuario_id=?';$unlocks=array_column($r->query($sql,[$hub])->fetchAll(),null,'conquista_id');
   $states=$r->query('SELECT carreira_id,estado_json,valida FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=?',[$hub])->fetchAll();$valid=array_sum(array_column($states,'valida'));
   foreach($states as $row)if($row['valida'])foreach(Achievements::progress(AchievementReconciliation::baseline($r,$hub,(int)$row['carreira_id'],json_decode($row['estado_json'],true,512,JSON_THROW_ON_ERROR)),$valid) as $id=>$p)if(!isset($progress[$id]) || $p[0]>$progress[$id][0])$progress[$id]=$p;
  }
  $filter=(string)($_GET['filtro']??'todas');$filters=['todas'=>'Todas','desbloqueadas'=>'Desbloqueadas','bloqueadas'=>'Bloqueadas','futuras'=>'Futuras'];if(!isset($filters[$filter]))$filter='todas';
  $g=array_sum(array_column($unlocks,'pontos_g'));$availableG=array_sum(array_column(array_filter($catalog,fn($a)=>$a['active']),'g'));
  echo '<section class="panel achievement-room"><p class="tag">SEU LEGADO NO FUTEBOL</p><h1>Sala de Conquistas</h1><div class="gamerscore"><strong>'.e($g).' <span>R</span></strong><span>'.count($unlocks).' de 50 conquistas</span></div><p>'.e($availableG).' R atualmente conquistáveis / 1.000 R do catálogo</p><label>Objetivos disponíveis: '.e($g).' / '.e($availableG).' R<progress max="'.e($availableG).'" value="'.e(min($g,$availableG)).'"></progress></label><label>Catálogo completo: '.e($g).' / 1.000 R<progress max="1000" value="'.e($g).'"></progress></label><p class="subtle">R é reputação permanente. Não é moeda nem XP. Objetivos futuros ainda não podem ser desbloqueados.</p><a href="/carreira">Voltar à carreira</a>';
  if(!$available)echo '<p role="status">Persistência ainda não disponível nesta instalação. Seu histórico está preservado.</p>';
  if(isset($GLOBALS['reconciliation'])&&!empty($GLOBALS['reconciliation']['pending'])){echo '<p>Uma carreira foi verificada. Se houver outras, continue a verificação segura do seu histórico.</p>';form('/conquistas/reconciliar');echo '<button class="btn" type="submit">Verificar próxima carreira</button></form>';}
  echo '<nav aria-label="Filtrar conquistas">';foreach($filters as $value=>$name)echo '<a class="btn" href="/conquistas?filtro='.$value.'"'.($filter===$value?' aria-current="page"':'').'>'.e($name).'</a>';echo '</nav><div class="achievement-groups">';
  foreach($catalog as $id=>$a){$u=$unlocks[$id]??null;if(($filter==='desbloqueadas'&&!$u)||($filter==='bloqueadas'&&(!$a['active']||$u))||($filter==='futuras'&&$a['active']))continue;
   if(($category??null)!==$a['category']){if(isset($category))echo '</div></details>';$category=$a['category'];$members=array_filter($catalog,fn($c)=>$c['category']===$category);$done=count(array_filter($members,fn($c)=>isset($unlocks[$c['id']])));echo '<details class="achievement-group"'.($filter!=='todas'?' open':'').'><summary><span>'.e($category==='TITULOS'?'Títulos':ucfirst(strtolower($category))).'</span><small>'.e($done).' / '.count($members).'</small></summary><div class="achievement-grid">';}
   $secret=$a['secret']&&!$u;$p=$progress[$id]??null;$status=$u?'Desbloqueada':(!$a['active']?'Futura · indisponível':'Em progresso');
   $art=match($a['category']){'CARREIRA'=>'carreira','DESEMPENHO'=>'desempenho','TITULOS'=>'titulos','SOCIAL'=>'social',default=>'segredo'};
   echo '<article class="panel achievement '.($u?'is-unlocked':(!$a['active']?'is-future':'is-locked')).'"><div class="achievement-art"><img src="/assets/achievements/'.$art.'.svg" alt="" width="800" height="400" loading="lazy"></div><div class="achievement-body"><div class="achievement-heading"><h3>'.e($secret?'Conquista secreta':$a['name']).'</h3><span class="achievement-value" aria-label="'.e($a['g']).' pontos R"><b aria-hidden="true">R</b> '.e($a['g']).'</span></div><p class="achievement-status">'.e($status).'</p>';
   if(!$secret)echo '<p>'.e($a['description']).'</p>';
   if(!$a['active'])echo '<p>'.e($secret?'Depende de um recurso futuro.':$a['inactive_reason']).'</p>';
   elseif(!$u&&!$secret&&$p&&$p[0]>0)echo '<p>Comprovado: '.e(min($p[0],$p[1])).'/'.e($p[1]).'</p><progress max="'.e($p[1]).'" value="'.e(min($p[0],$p[1])).'" aria-label="'.e($a['name']).'"></progress>';
   if(!$u&&$a['active']&&(!$p||$p[0]===0))echo '<p class="subtle">Histórico anterior não verificável ou ainda não analisado.</p>';
   if($u)echo '<progress class="achievement-completed" max="1" value="1" aria-label="Conquista concluída"></progress><p class="achievement-date">'.(($u['origem']??'')==='reconciliacao'?'Reconhecida em ':'Desbloqueada em ').e(self::brazilTime($u['criado_em'])).'</p>';echo '</div></article>';
  }if(isset($category))echo '</div></details>';echo '</div></section>';
 }
 /** Claim notification atomically; a reload cannot replay it. No rewards on RET. */
 public static function toast(Repository $r,int $account):void {
  if(!Achievements::enabled()||!FeatureSchema::ready($r,FeatureSchema::ACHIEVEMENTS))return;$hub=self::hub($r,$account);if(!$hub)return;
  $r->db->beginTransaction();try{
   $rows=$r->query('SELECT conquista_id,pontos_g FROM rumo10_conquistas WHERE hub_usuario_id=? AND notificada=0 ORDER BY criado_em,conquista_id LIMIT 50 FOR UPDATE',[$hub])->fetchAll();
   foreach($rows as $row)$r->query('UPDATE rumo10_conquistas SET notificada=1 WHERE hub_usuario_id=? AND conquista_id=?',[$hub,$row['conquista_id']]);$r->db->commit();
  }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
  $historical=(int)($_SESSION['historical_achievements']??0);unset($_SESSION['historical_achievements']);
  if(!$rows&&!$historical)return;
  echo '<div class="achievement-notifications" data-achievement-queue><div class="achievement-announcement" role="status" aria-live="polite" aria-atomic="true"></div>';
  if($historical)echo '<div hidden data-achievement-notice data-title="'.e($historical.' conquistas históricas reconhecidas').'" data-description="Confira os feitos comprovados na sua Sala de Conquistas." data-g=""></div>';
  $catalog=AchievementCatalog::all();foreach($rows as $row){$a=$catalog[$row['conquista_id']];echo '<div hidden data-achievement-notice data-title="'.e($a['name']).'" data-description="'.e($a['description']).'" data-g="'.e($row['pontos_g']).'"></div>';}
  echo '<noscript><p>Há novas conquistas reconhecidas. <a href="/conquistas">Ver Sala de Conquistas</a></p></noscript></div>';

 }
}
