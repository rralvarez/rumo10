<?php
declare(strict_types=1);
final class Achievements {
 public static function enabled():bool{return config('RUMO10_ACHIEVEMENTS','0')==='1';}
 /** Pure projection from deduplicated, authoritative events in ONE career. */
 public static function apply(array $state,array $event):array {
  $state+=['matches'=>0,'goals'=>0,'assists'=>0,'tackles'=>0,'clubs'=>[],'seasons'=>[],'titles'=>[],'match_flags'=>[],'participation'=>[]];
  if($event['type']==='match' && ($event['stats']['minutes']??0)>0){
   $s=$event['stats'];$state['matches']++;$state['clubs'][$event['club']]=true;$state['participation'][$event['season']][$event['club']]=true;
   foreach(['goals','assists','tackles'] as $k)$state[$k]+=$s[$k];
   foreach(['DES03'=>$s['goals']+$s['assists']>=2,'DES04'=>$s['goals']>=3,'DES05'=>$s['goals']>0&&$s['assists']>0]+($event['flags']??[]) as $id=>$yes)if($yes)$state['match_flags'][$id]=true;
   foreach($state['promotions']??[] as $p)if($p['season']+1===$event['season']&&$p['club']===$event['club']&&$p['upper']===($event['competition']??null))$state['match_flags']['ESP06']=true;
  }
  if($event['type']==='season' && !empty($state['participation'][$event['season']][$event['club']])){
   $state['seasons'][(string)$event['season']]=$event['club'];foreach($event['titles'] as $t)$state['titles'][$t['season'].'|'.$t['competition'].'|'.$t['title']]=$t;
   foreach($event['promotions']??[] as $p)$state['promotions'][]=$p;
   if(!empty($event['invincible']))$state['match_flags']['ESP07']=true;
  }
  return $state;
 }
 public static function progress(array $s,int $validCareers):array {
  $s+=['matches'=>0,'goals'=>0,'assists'=>0,'tackles'=>0,'clubs'=>[],'seasons'=>[],'titles'=>[],'match_flags'=>[]];
  $p=[];foreach(['CAR01'=>1,'CAR03'=>2,'CAR04'=>3,'CAR06'=>5,'CAR09'=>10] as $id=>$target)$p[$id]=[$validCareers,$target];
  foreach(['CAR05'=>1,'CAR08'=>5,'CAR10'=>15] as $id=>$target)$p[$id]=[count($s['seasons']),$target];
  $p['CAR02']=[$s['matches'],1];$p['CAR07']=[count($s['clubs']),3];
  foreach(['DES01'=>['goals',1],'DES02'=>['assists',1],'DES06'=>['tackles',100],'DES07'=>['assists',50],'DES08'=>['goals',100],'DES09'=>['tackles',500],'DES10'=>['goals',300]] as $id=>[$k,$target])$p[$id]=[$s[$k],$target];
  foreach(['DES03','DES04','DES05','ESP01','ESP04','ESP05','ESP06','ESP07','ESP08'] as $id)$p[$id]=[(int)!empty($s['match_flags'][$id]),1];
  $competitions=[];$bySeason=[];$countries=[];$elite=0;
  foreach($s['titles'] as $t){$edition=$t['competition'].'|'.$t['title'];$competitions[$edition]=($competitions[$edition]??0)+1;$bySeason[$t['season']][$edition]=true;if(!empty($t['country']))$countries[$t['country']]=true;$c=Competition::catalog()[$t['competition']]??[];if(TransferMarket::sportingLevel($t['competition'])===1 || ($c['official_level']??null)===1 || $t['competition']==='sp-a1')$elite++;}
  $p['TIT01']=[count($s['titles']),1];$p['TIT02']=[count($s['titles']),2];$p['TIT04']=[$competitions?max($competitions):0,2];$p['TIT06']=[$bySeason?max(array_map('count',$bySeason)):0,3];$p['TIT07']=[$elite,1];$p['TIT10']=[count($s['titles']),10];$p['ESP09']=[count($countries),2];
  $seasons=$s['seasons'];ksort($seasons,SORT_NUMERIC);$streak=0;$best=0;$club=null;$year=null;
  foreach($seasons as $n=>$c){$streak=$c===$club && (int)$n===$year+1?$streak+1:1;$club=$c;$year=(int)$n;$best=max($best,$streak);}$p['ESP02']=[$best,3];return $p;
 }
 /** Called under Repository's account lock and transaction. No Hub calls. */
 public static function persist(Repository $r,int $account,array &$g):void {
  if(!self::enabled()||!FeatureSchema::ready($r,FeatureSchema::ACHIEVEMENTS)){unset($g['achievement_events']);return;}
  if(!$r->db->inTransaction())throw new LogicException('Conquistas exigem transação.');
  $hub=$r->query('SELECT hub_usuario_id FROM rumo10_identidades WHERE conta_id=?',[$account])->fetchColumn();
  if(!$hub){unset($g['achievement_events']);return;}
  $career=(int)$g['id'];$r->query('INSERT IGNORE INTO rumo10_conquistas_carreiras(hub_usuario_id,carreira_id,estado_json) VALUES(?,?,?)',[$hub,$career,'{}']);
  $s=json_decode($r->query('SELECT estado_json FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND carreira_id=? FOR UPDATE',[$hub,$career])->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
  foreach($g['achievement_events']??[] as $key=>$event){
   $insert=$r->query('INSERT IGNORE INTO rumo10_conquistas_eventos(hub_usuario_id,carreira_id,chave,fonte_json) VALUES(?,?,?,?)',[$hub,$career,$key,json_encode($event,JSON_THROW_ON_ERROR)]);
   if(!$insert->rowCount())continue;
   $s=self::apply($s,$event);
   $r->query('UPDATE rumo10_conquistas_carreiras SET estado_json=?,valida=GREATEST(valida,?) WHERE hub_usuario_id=? AND carreira_id=?',[json_encode($s,JSON_THROW_ON_ERROR),(int)($s['matches']>0),$hub,$career]);
   $count=(int)$r->query('SELECT COUNT(*) FROM rumo10_conquistas_carreiras WHERE hub_usuario_id=? AND valida=1',[$hub])->fetchColumn();
   $progress=self::progress(AchievementReconciliation::baseline($r,(int)$hub,$career,$s),$count);
   foreach(AchievementCatalog::all() as $id=>$a){if(!$a['active'] || !isset($progress[$id]) || $progress[$id][0]<$progress[$id][1])continue;
    $created=$r->query('INSERT IGNORE INTO rumo10_conquistas(hub_usuario_id,conquista_id,carreira_id,pontos_g,evento_chave,xp_bonus) VALUES(?,?,?,?,?,0)',[$hub,$id,$career,$a['g'],$key]);
    if($created->rowCount()&&FeatureSchema::ready($r,FeatureSchema::RECONCILIATION))$r->query("INSERT IGNORE INTO rumo10_conquistas_fontes(hub_usuario_id,conquista_id,origem,referencia) VALUES(?,?,'evento_normal',?)",[$hub,$id,$key]);
   }
  }
  unset($g['achievement_events']);
 }
}
