<?php
require dirname(__DIR__).'/lib/LegacyFence.php';use Rumo\Publisher\{FenceCheckFailed,LegacyFence};
$tests=[];function ck($name,$ok){global $tests;if(!$ok)throw new RuntimeException($name);$tests[]=['test'=>$name,'status'=>'PASS'];}
$profile=['manual_channels_fenced'=>true,'repositories'=>['rralvarez/rumo10'=>['branch'=>'main','approved_active'=>[]],'rralvarez/rumohub'=>['branch'=>'main','approved_active'=>[]]]];
function metadata($repo,$path,$fault=''){
 if(str_contains($path,'/actions/workflows?')){
  if($fault==='api')throw new RuntimeException('API 401');
  $w=[['id'=>1,'path'=>'.github/workflows/production.yml','state'=>'disabled_manually'],['id'=>2,'path'=>'.github/workflows/production-ftps.yml','state'=>'disabled_manually']];
  if($fault==='enabled'&&$repo==='rralvarez/rumohub')$w[1]['state']='active';
  if($fault==='unknown')$w[]=['id'=>3,'path'=>'.github/workflows/unreviewed.yml','state'=>'active'];
  if($fault==='missing')array_pop($w);
  return ['total_count'=>count($w),'workflows'=>$w];
 }
 $pending=$fault!==''&&str_contains($path,'status='.$fault.'&');return ['total_count'=>$pending?1:0,'workflow_runs'=>$pending?[['id'=>10,'status'=>$fault]]:[]];
}
ck('both repositories fenced',LegacyFence::verify($profile,fn($r,$p)=>metadata($r,$p))['ok']);
foreach(['enabled','unknown','missing','api','in_progress','queued','requested','waiting','pending'] as $fault){$rejected=false;try{LegacyFence::verify($profile,fn($r,$p)=>metadata($r,$p,$fault));}catch(Throwable){$rejected=true;}ck('refuse '.$fault,$rejected);}
$p=$profile;$p['manual_channels_fenced']=false;$bad=false;try{LegacyFence::verify($p,fn($r,$p)=>metadata($r,$p));}catch(Throwable){$bad=true;}ck('manual channel assumption mandatory',$bad);
$active=$profile;foreach($active['repositories'] as &$c)$c['approved_active']['.github/workflows/safe.yml']=['commit'=>str_repeat('a',40),'sha256'=>hash('sha256','approved synthetic workflow')];unset($c);
$fetch=function($repo,$path,$fault=''){
 if(str_contains($path,'/git/ref/'))return ['object'=>['sha'=>str_repeat($fault==='moved'?'b':'a',40)]];
 if(str_contains($path,'/contents/'))return ['encoding'=>'base64','content'=>base64_encode($fault==='hash'?'changed workflow':'approved synthetic workflow')];
 $v=metadata($repo,$path);
 if(str_contains($path,'/actions/workflows?')){
  if($fault==='inactivity')$v['workflows'][1]['state']='disabled_inactivity';
  $v['workflows'][]=['id'=>3,'path'=>'.github/workflows/safe.yml','state'=>'active'];$v['total_count']=3;
  if($fault==='duplicate'){$v['workflows'][]=$v['workflows'][0];$v['total_count']=4;}
  if($fault==='pagination')$v['total_count']=104;
 }
 return $v;
};
ck('active approved workflow hash and commit verified',LegacyFence::verify($active,fn($r,$p)=>$fetch($r,$p))['ok']);
foreach(['moved','hash','inactivity','duplicate','pagination'] as $fault){$bad=false;try{LegacyFence::verify($active,fn($r,$p)=>$fetch($r,$p,$fault));}catch(Throwable){$bad=true;}ck('refuse workflow '.$fault,$bad);}
$calls=0;$reposSeen=[];
$brieflyBusy=static function($repo,$path)use(&$calls,&$reposSeen){
 $reposSeen[$repo]=true;
 if($repo==='rralvarez/rumo10' && str_contains($path,'status=in_progress&') && ++$calls===1)
  return ['total_count'=>1,'workflow_runs'=>[['id'=>42]]];
 return metadata($repo,$path);
};
$oldFailed=false;
try{LegacyFence::verify($profile,$brieflyBusy);}catch(FenceCheckFailed $e){$oldFailed=$e->reason==='workflow_busy'&&str_contains($e->getMessage(),'run=42');}
ck('single observation fails with repo, state and run', $oldFailed);
$calls=0;$reposSeen=[];
ck('retry repeats verification then fences both repositories',LegacyFence::verifyWithRetry($profile,$brieflyBusy,microtime(true)+8)['ok'] && $calls===2 && count($reposSeen)===2);
$calls=0;$permanent=static function($repo,$path)use(&$calls){$calls++;return metadata($repo,$path,'unknown');};
try{LegacyFence::verifyWithRetry($profile,$permanent,microtime(true)+8);ck('unapproved workflow is a hard block',false);}
catch(FenceCheckFailed $e){ck('unapproved workflow is a hard block',$calls===1&&$e->reason==='workflow_unapproved'&&!$e->retryable);}
$calls=0;
try{
 LegacyFence::verifyWithRetry($profile,static function($repo,$path)use(&$calls){
  $calls++;
  throw new FenceCheckFailed('api_unavailable',true,['repo'=>$repo,'http'=>503]);
 },microtime(true)+8);
 ck('API failure retries limited to three attempts',false);
}catch(FenceCheckFailed $e){ck('API failure retries limited to three attempts',$calls===3&&$e->retryable);}
$calls=0;
try{
 LegacyFence::verifyWithRetry($profile,static function()use(&$calls){
  $calls++;throw new FenceCheckFailed('api_unavailable',true);
 },microtime(true));
 ck('expired retry budget exits without waiting for another probe',false);
}catch(FenceCheckFailed $e){ck('expired retry budget exits without waiting for another probe',$calls===1&&$e->retryable);}
file_put_contents(dirname(__DIR__).'/evidence/legacy-fence.json',json_encode(['results'=>$tests,'passed'=>count($tests),'network'=>'API responses mocked; no remote mutation'],JSON_PRETTY_PRINT));echo json_encode(['passed'=>count($tests)]).PHP_EOL;
