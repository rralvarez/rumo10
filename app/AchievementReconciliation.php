<?php
declare(strict_types=1);
final class AchievementReconciliation {
 public static function ready(Repository $r):bool {return Achievements::enabled()&&config('RUMO10_RECONCILIATION','0')==='1'&&FeatureSchema::ready($r,array_merge(FeatureSchema::ACHIEVEMENTS,FeatureSchema::RECONCILIATION));}
 public static function baseline(Repository $r,int $hub,int $career,array $live):array {
  if(!FeatureSchema::ready($r,FeatureSchema::RECONCILIATION))return $live;
  $json=$r->query('SELECT dados_json FROM rumo10_conquistas_reconciliacoes WHERE hub_usuario_id=? AND carreira_id=?',[$hub,$career])->fetchColumn();return $json?AchievementHistory::merge($live,json_decode($json,true,512,JSON_THROW_ON_ERROR)):$live;
 }
 /** Exactly one owned career per call, locked against game writes; no client user ID. */
 public static function run(Repository $r,int $account,bool $dryRun=false):array {
  if(!self::ready($r))return ['recognized'=>[],'pending'=>0,'available'=>false];
  $hub=FeatureSchema::hub($r,$account);$r->query("SET time_zone='+00:00'");$r->db->beginTransaction();try{
   $r->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
   $where='(c.conta_id=? OR (c.conta_id IS NULL AND a.conta_id=?) OR h.conta_id=?)';
   $pending=$r->query('SELECT c.id,c.versao FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id LEFT JOIN rumo10_historico_encerrado h ON h.carreira_id=c.id LEFT JOIN rumo10_conquistas_reconciliacoes x ON x.carreira_id=c.id AND x.hub_usuario_id=? WHERE '.$where.' AND (x.versao IS NULL OR x.versao<>c.versao) ORDER BY c.id LIMIT 1',[$hub,$account,$account,$account])->fetch();
   if(!$pending){$r->db->commit();return ['recognized'=>[],'pending'=>0,'available'=>true];}
   $id=(int)$pending['id'];$row=$r->query('SELECT u.estado_json,h.dados_json FROM rumo10_carreiras c LEFT JOIN rumo10_universos u ON u.carreira_id=c.id LEFT JOIN rumo10_historico_encerrado h ON h.carreira_id=c.id WHERE c.id=?',[$id])->fetch();
   $g=$row['dados_json']?RetiredCareer::state(json_decode($row['dados_json'],true,512,JSON_THROW_ON_ERROR)):($row['estado_json']?json_decode($row['estado_json'],true,512,JSON_THROW_ON_ERROR):self::relational($r,$id));
   $audit=$g&&((int)($g['id']??0)===$id)?AchievementHistory::audit($g):['state'=>Achievements::apply([],['type'=>'none']),'proof'=>[],'notes'=>['Identidade divergente do snapshot; histórico preservado sem concessões.'],'conflict'=>false];
   $liveJson=$r->query('SELECT estado_json FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND carreira_id=?',[$hub,$id])->fetchColumn();$live=$liveJson?json_decode($liveJson,true,512,JSON_THROW_ON_ERROR):[];$audit['live_baseline']=$live;
   $valid=(int)($audit['state']['matches']>0);$count=(int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND valida=1',[$hub])->fetchColumn();
   $already=(bool)$r->query('SELECT valida FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND carreira_id=?',[$hub,$id])->fetchColumn();$count+=(int)($valid&&!$already);
   $progress=Achievements::progress(AchievementHistory::merge($live,$audit),$count);$unlocked=[];$reference=hash('sha256',json_encode(['career'=>$id,'version'=>$pending['versao'],'audit'=>$audit],JSON_THROW_ON_ERROR));
   foreach(AchievementCatalog::all() as $code=>$a){if(!$a['active']||!isset($progress[$code])||$progress[$code][0]<$progress[$code][1])continue;
    if($r->query('SELECT 1 FROM rumo10_conquistas WHERE hub_usuario_id=? AND conquista_id=?',[$hub,$code])->fetchColumn())continue;$unlocked[]=$code;
    if(!$dryRun){$r->query('INSERT IGNORE INTO rumo10_conquistas(hub_usuario_id,conquista_id,carreira_id,pontos_g,evento_chave,xp_bonus,notificada) VALUES(?,?,?,?,?,0,1)',[$hub,$code,$id,$a['g'],$reference]);$r->query("INSERT IGNORE INTO rumo10_conquistas_fontes(hub_usuario_id,conquista_id,origem,referencia) VALUES(?,?,'reconciliacao',?)",[$hub,$code,$reference]);}
   }
   if(!$dryRun){
    if($unlocked)$r->query('INSERT IGNORE INTO rumo10_conquistas_eventos(hub_usuario_id,carreira_id,chave,fonte_json) VALUES(?,?,?,?)',[$hub,$id,$reference,json_encode(['type'=>'historical_evidence','version'=>$pending['versao'],'audit'=>$audit],JSON_THROW_ON_ERROR)]);
    $r->query('INSERT INTO rumo10_conquistas_reconciliacoes(hub_usuario_id,carreira_id,versao,dados_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE versao=VALUES(versao),dados_json=VALUES(dados_json),verificado_em=CURRENT_TIMESTAMP',[$hub,$id,$pending['versao'],json_encode($audit,JSON_THROW_ON_ERROR)]);
    $r->query('INSERT INTO rumo10_conquistas_carreiras(hub_usuario_id,carreira_id,valida,estado_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE valida=GREATEST(valida,VALUES(valida))',[$hub,$id,$valid,'{}']);
   }
   $remaining=(int)$r->query('SELECT COUNT(*) FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id LEFT JOIN rumo10_historico_encerrado h ON h.carreira_id=c.id LEFT JOIN rumo10_conquistas_reconciliacoes x ON x.carreira_id=c.id AND x.hub_usuario_id=? WHERE '.$where.' AND (x.versao IS NULL OR x.versao<>c.versao)',[$hub,$account,$account,$account])->fetchColumn();
   $r->db->commit();return ['recognized'=>$unlocked,'pending'=>$remaining,'available'=>true,'notes'=>$audit['notes'],'dry_run'=>$dryRun];
  }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
 }
 private static function relational(Repository $r,int $id):array {
  $c=$r->query('SELECT id,clube,temporada,etapa FROM rumo10_carreiras WHERE id=?',[$id])->fetch();
  $g=['id'=>(int)$c['id'],'club'=>$c['clube'],'season'=>(int)$c['temporada'],'stage'=>$c['etapa'],'official'=>false,'matches'=>[],'history'=>[]];
  foreach($r->query('SELECT id,numero,clube,finalizada FROM rumo10_temporadas WHERE carreira_id=? ORDER BY numero',[$id])->fetchAll() as $season){
   $rows=$r->query('SELECT p.rodada,p.mandante,p.visitante,p.gols_mandante,p.gols_visitante,p.status,p.entrada,p.saida,s.minutos,s.gols,s.assistencias,s.desarmes FROM rumo10_partidas p JOIN rumo10_estatisticas s ON s.partida_id=p.id WHERE p.temporada_id=?',[$season['id']])->fetchAll();$games=0;$minutes=0;$goals=0;$assists=0;
   foreach($rows as $m){
    $match=['round'=>(int)$m['rodada'],'home'=>$m['mandante'],'away'=>$m['visitante'],'hg'=>(int)$m['gols_mandante'],'ag'=>(int)$m['gols_visitante'],'status'=>$m['status'],'entry'=>(int)$m['entrada'],'exit'=>(int)$m['saida'],'stats'=>['minutes'=>(int)$m['minutos'],'goals'=>(int)$m['gols'],'assists'=>(int)$m['assistencias'],'tackles'=>(int)$m['desarmes']]];
    if((int)$season['numero']===$g['season'])$g['matches'][]=$match;
    if($m['status']==='finalizada'&&(int)$m['minutos']>0&&in_array($season['clube'],[$m['mandante'],$m['visitante']],true)){$games++;$minutes+=(int)$m['minutos'];$goals+=(int)$m['gols'];$assists+=(int)$m['assistencias'];}
   }
   if($season['finalizada']&&(int)$season['numero']<$g['season'])$g['history'][]=['season'=>(int)$season['numero'],'club'=>$season['clube'],'games'=>$games,'minutes'=>$minutes,'goals'=>$goals,'assists'=>$assists];
  }return $g;
 }

}
