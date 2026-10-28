<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Core.php';
/** Classifies concrete changed paths. SQL is compatible only by an operator-approved digest. */
final class UpdatePolicy {
 public static function assess(array $old,array $new,array $policy,array $jobs=[]):array {
  need(($policy['schema']??'')==='rumo-update-policy-v1','Unknown update policy');
  $class='A';$reasons=[];$changed=[];$migrations=[];
  foreach(array_unique(array_merge(array_keys($old['files']),array_keys($new['files']))) as $p){
   if(($old['files'][$p]??null)===($new['files'][$p]??null))continue;$changed[]=$p;
   if(!isset($new['files'][$p])||protectedPath($p)||in_array($p,$policy['protected_contract_paths']??[],true)){$class='C';$reasons[]='destructive/protected contract: '.$p;continue;}
   if(str_starts_with($p,'database/')&&(str_ends_with($p,'.sql')||str_ends_with($p,'.php'))){
    if(($policy['approved_incremental_migrations'][$p]??null)!==$new['files'][$p]){$class='C';$reasons[]='unapproved database change: '.$p;}
    else {$migrations[]=$p;if($class==='A')$class='B';}
   }
  }
  $compatible=false;
  foreach($jobs as $job)if(($job['name']??'')==='compatibility'&&($job['status']??'')==='completed'&&($job['conclusion']??'')==='success')$compatible=true;
  return ['class'=>$class,'changed'=>$changed,'migrations'=>$migrations,'reasons'=>$reasons,'requires_full_drain'=>$class==='C','compatible_writers_ci'=>$compatible];
 }
 public static function maintenance(array $receipt,array $policy):void{
  need(($receipt['http_status']??null)===503,'Maintenance HTTP503 required');
  if($policy['requires_full_drain']){
   need(($receipt['writes_blocked']??false)&&($receipt['inflight_writers']??-1)===0,'Incompatible change requires proven writer exclusion');
  }else{
   need(($policy['compatible_writers_ci']??false)===true,'Pinned compatible-writer CI proof required');
   need(($receipt['new_requests_blocked']??false)===true,'New request admission not blocked');
   need(($receipt['publisher_exclusive']??false)===true,'Publisher exclusion not demonstrated');
  }
 }
}
