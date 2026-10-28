<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/Publisher.php';
use Rumo\Publisher\Publisher;
use Rumo\Publisher\Package;
use Rumo\Publisher\Evidence;
use function Rumo\Publisher\{record,digest,privateDir,protectedPath,pathAt};
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$results=[];$root=__DIR__.'/runs-'.date('YmdHis').'-'.bin2hex(random_bytes(3));mkdir($root,0700);
function check(string $name,callable $fn):void {
 global $results;$t=hrtime(true);
 try {$fn();$results[]=['name'=>$name,'status'=>'PASS','ms'=>round((hrtime(true)-$t)/1e6,3)];}
 catch(Throwable $e){$results[]=['name'=>$name,'status'=>'FAIL','error'=>$e->getMessage(),'at'=>$e->getFile().':'.$e->getLine()];}
}
function yes(bool $b,string $why='assertion failed'):void{if(!$b)throw new RuntimeException($why);}
function rejected(callable $fn,string $reason=''):void {
 try{$fn();}catch(Throwable $e){ if($reason!=='')yes(str_contains($e->getMessage(),$reason),'unexpected rejection: '.$e->getMessage());return;}
 throw new RuntimeException('Expected rejection missing: '.$reason);
}
function input(string $pkg,string $project='rumo10',int $order=2):array {
 $run=['id'=>100,'repository'=>['full_name'=>'rralvarez/'.$project],'workflow_id'=>7,'head_branch'=>'main',
 'event'=>'push','status'=>'completed','conclusion'=>'success','head_sha'=>str_repeat('a',40),'run_number'=>$order,'run_attempt'=>1];
 $a=['id'=>200,'name'=>'release-'.$project.'-'.$run['head_sha'],'expired'=>false,'digest'=>'sha256:'.str_repeat('b',64),
 'workflow_run'=>['id'=>100,'head_sha'=>$run['head_sha']]];
 $jobs=[['name'=>'checks','status'=>'completed','conclusion'=>'success','head_sha'=>$run['head_sha'],'run_id'=>100,'_requested_attempt'=>1,'steps'=>[['conclusion'=>'success']]]];
 $e=['schema'=>'rumo-pull-v1','project'=>$project,'repository'=>'rralvarez/'.$project,'commit'=>$run['head_sha'],
 'workflow_id'=>7,'run_id'=>100,'run_number'=>$order,'run_attempt'=>1,'package_sha256'=>digest($pkg),
 'data_contracts'=>$project==='rumo10'?['rumo10-development-v3']:['rumohub-writer-v1']];
 return [$pkg,$run,$a,$jobs,$e];
}
function fixture(?string $pkg=null,string $project='rumo10'):array {
 global $root;
 $pkg??=__DIR__.'/fixtures/good.tar.gz';
 $d=$root.'/case-'.bin2hex(random_bytes(4));mkdir($d,0700);
 foreach(['private','current','public','control'] as $dir)mkdir($d.'/'.$dir,0700);
 $inv=Package::inspect($pkg,$project,$d.'/current');
 $files=$inv['files'];
 // Baseline is already a v3-compatible writer; no legacy writer rollback is allowed.
 unset($files['app/new.php']);if(is_file($d.'/current/app/new.php'))unlink($d.'/current/app/new.php');
 file_put_contents($d.'/current/app/bootstrap.php',"<?php // old synthetic bootstrap\n");
 $files['app/bootstrap.php']=digest($d.'/current/app/bootstrap.php');
 foreach($files as $p=>$h)if(str_starts_with($p,'public/')&&!in_array($p,['public/index.php','public/.htaccess'],true)&&!protectedPath($p)){
  $dest=$d.'/public/'.substr($p,7);if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);copy($d.'/current/'.$p,$dest);
 }
 ksort($files);record($d.'/current/release.json',['project'=>$project,'files'=>$files]);
 file_put_contents($d.'/current/.env',"PRIVATE_DO_NOT_TOUCH");
 mkdir($d.'/current/storage',0700);file_put_contents($d.'/current/storage/save.json','{"xp":12345}');
 file_put_contents($d.'/public/index.php','<?php // pinned bridge');
 file_put_contents($d.'/public/.htaccess',"# cPanel block preserved\n");
 file_put_contents($d.'/public/user-owned.txt',"UNKNOWN_KEEP");
 $db=new SQLite3($d.'/save.sqlite');$db->exec('CREATE TABLE careers (id INTEGER PRIMARY KEY, state TEXT NOT NULL)');
 $state=['xp'=>12345,'attributes'=>array_fill(0,9,75),'player_development'=>['version'=>'development-v3-isolated','spent'=>400,'received'=>12745]];
 $q=$db->prepare('INSERT INTO careers VALUES(1,:state)');$q->bindValue(':state',json_encode($state));$q->execute();$db->close();
 $c=['project'=>$project,'repository'=>'rralvarez/'.$project,'workflow_id'=>7,'branch'=>'main','required_jobs'=>['checks'],
 'private'=>$d.'/private','current'=>$d.'/current','public'=>$d.'/public','control'=>$d.'/control','account_mutex'=>$d.'/account.lock',
 'apply_enabled'=>true,'data_contracts'=>$project==='rumo10'?['rumo10-development-v3']:['rumohub-writer-v1'],
 'baseline_data_contracts'=>$project==='rumo10'?['rumo10-development-v3']:['rumohub-writer-v1'],
 'backup_roots'=>['env'=>$d.'/current/.env','storage'=>$d.'/current/storage'],'php'=>PHP_BINARY,'php_args'=>(PHP_OS_FAMILY==='Windows'?['-n','-d','extension_dir=C:/PHP/php-8.4.26/ext','-d','extension=php_sqlite3.dll']:[]),
 'pinned_controls'=>[$d.'/public/index.php'=>digest($d.'/public/index.php'),$d.'/public/.htaccess'=>digest($d.'/public/.htaccess')],
 'unmanaged_allowlist'=>['public/user-owned.txt'=>digest($d.'/public/user-owned.txt')],'public_inventory'=>[],'homologation'=>array_fill_keys(['backup','maintenance','recovery','legacy_publishers_fenced'],true),'hooks'=>[]];
 foreach($files as $p=>$h)if(str_starts_with($p,'public/')&&!in_array($p,['public/index.php','public/.htaccess'],true)&&!protectedPath($p))
  $c['public_inventory'][substr($p,7)]=$h;
 foreach(['preflight','maintenance','backup','smoke'] as $h)$c['hooks'][$h]=['script'=>__DIR__.'/hook.php','sha256'=>digest(__DIR__.'/hook.php')];
 record(__DIR__.'/sandbox-hook-state.json',['control'=>$d.'/control','db'=>$d.'/save.sqlite']);
 return [$c,input($pkg,$project),$d];
}
class Fault extends Publisher {
 public string $fault='';public mixed $action=null;
 protected function checkpoint(string $p):void {if($p===$this->fault){if($this->action!==null)($this->action)();throw new RuntimeException('INJECTED '.$p);}}
}
check('valid strict TAR',fn()=>yes(count(Package::inspect(__DIR__.'/fixtures/good.tar.gz','rumo10')['files'])===6));
foreach(['duplicate','symlink','hardlink','traversal','truncated'] as $bad)
 check('reject archive '.$bad,fn()=>rejected(fn()=>Package::inspect(__DIR__.'/fixtures/'.$bad.'.tar.gz','rumo10')));
foreach(['repo','branch','event','run','sha','artifact','expired','digest','job-failed','job-skipped','job-duplicate','attempt','envelope'] as $bad)
 check('provenance '.$bad,function()use($bad){
  [$c,$i]=fixture();[$p,$r,$a,$jobs,$e]=$i;
  switch($bad){
   case'repo':$r['repository']['full_name']='attacker/other';break;
   case'branch':$r['head_branch']='dev';break;
   case'event':$r['event']='pull_request';break;
   case'run':$r['conclusion']='failure';break;
   case'sha':$r['head_sha']='latest';break;
   case'artifact':$a['workflow_run']['id']=99;break;
   case'expired':$a['expired']=true;break;
   case'digest':unset($a['digest']);break;
   case'job-failed':$jobs[0]['conclusion']='failure';break;
   case'job-skipped':$jobs[0]['steps'][0]['conclusion']='skipped';break;
   case'job-duplicate':$jobs[]=$jobs[0];break;
   case'attempt':$jobs[0]['_requested_attempt']=0;break;
   case'envelope':$e['commit']=str_repeat('b',40);break;
  }
  rejected(fn()=>Evidence::validate($c,$r,$a,$jobs,$e));
 });
check('stage does not touch current/public/DB',function(){
 [$c,$i,$d]=fixture();$before=digest($d.'/current/release.json').digest($d.'/save.sqlite').digest($d.'/public/index.php');
 $p=new Publisher($c);$p->stage(...$i);
 yes($before===digest($d.'/current/release.json').digest($d.'/save.sqlite').digest($d.'/public/index.php'));yes(!file_exists($d.'/control/deployment.lock'));
});
check('default apply disabled',function(){
 [$c,$i]=fixture();$c['apply_enabled']=false;$p=new Publisher($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'disabled');
});
check('missing homologation blocked before maintenance',function(){
 [$c,$i,$d]=fixture();$c['homologation']['legacy_publishers_fenced']=false;$p=new Publisher($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'Unhomologated');yes(!file_exists($d.'/control/deployment.lock'));
});
check('apply + actual SQLite backup restore + protected data',function(){
 [$c,$i,$d]=fixture();$db=digest($d.'/save.sqlite');$env=digest($d.'/current/.env');$save=digest($d.'/current/storage/save.json');
 $p=new Publisher($c);$s=$p->stage(...$i);yes($p->apply($s['stage'])['state']==='validated');
 yes(!file_exists($d.'/control/deployment.lock'));yes(digest($d.'/save.sqlite')===$db);
 yes(digest($d.'/current/.env')===$env && digest($d.'/current/storage/save.json')===$save);
 yes(file_get_contents($d.'/public/user-owned.txt')==='UNKNOWN_KEEP');
});
check('protected package paths excluded from staging/install',function(){
 [$c,$i,$d]=fixture(__DIR__.'/fixtures/protected.tar.gz');$p=new Publisher($c);$s=$p->stage(...$i);
 yes(!file_exists($s['stage'].'/payload/app/.env') && !file_exists($s['stage'].'/payload/app/clients.php'));
 yes($p->apply($s['stage'])['state']==='validated');yes(file_get_contents($d.'/current/.env')==='PRIVATE_DO_NOT_TOUCH');
});
check('unknown matching target never adopted',function(){
 [$c,$i,$d]=fixture();file_put_contents($d.'/current/app/new.php',"<?php // new");$p=new Publisher($c);$s=$p->stage(...$i);
 rejected(fn()=>$p->apply($s['stage']),'Unknown existing');yes(file_get_contents($d.'/current/app/new.php')==="<?php // new");
});
check('foreign lock retained',function(){
 [$c,$i,$d]=fixture();file_put_contents($d.'/control/deployment.lock','foreign');$p=new Publisher($c);$s=$p->stage(...$i);
 rejected(fn()=>$p->apply($s['stage']),'Foreign');yes(file_get_contents($d.'/control/deployment.lock')==='foreign');
});
check('global account flock rejects concurrent project',function(){
 [$c,$i]=fixture();$p=new Publisher($c);$s=$p->stage(...$i);$q=new Publisher($c);rejected(fn()=>$q->stage(...$i),'Concurrent');
});
check('staged mutation detected',function(){
 [$c,$i]=fixture();$p=new Publisher($c);$s=$p->stage(...$i);file_put_contents($s['stage'].'/payload/app/bootstrap.php','corrupted');
 rejected(fn()=>$p->apply($s['stage']),'Staged payload');
});
check('public control drift rejected',function(){
 [$c,$i,$d]=fixture();$p=new Publisher($c);$s=$p->stage(...$i);file_put_contents($d.'/public/.htaccess','changed');
 rejected(fn()=>$p->apply($s['stage']),'control drift');
});
foreach(['preflight','maintenance','backup','smoke'] as $failure)
 check('hook failure '.$failure,function()use($failure){
  [$c,$i,$d]=fixture();$state=json_decode(file_get_contents(__DIR__.'/sandbox-hook-state.json'),true);$state['fail']=$failure;record(__DIR__.'/sandbox-hook-state.json',$state);
  $p=new Publisher($c);$s=$p->stage(...$i);$old=digest($d.'/current/release.json');rejected(fn()=>$p->apply($s['stage']),'Hook failed');
  yes(digest($d.'/current/release.json')===$old);
  if($failure!=='preflight')yes(file_exists($d.'/control/deployment.lock'));
 });
foreach(['after-backup','before-promotion-0','after-promotion-0','after-manifest'] as $point)
 check('interruption + recovery '.$point,function()use($point){
  [$c,$i,$d]=fixture();$db=digest($d.'/save.sqlite');$old=digest($d.'/current/release.json');$p=new Fault($c);$s=$p->stage(...$i);$p->fault=$point;
  rejected(fn()=>$p->apply($s['stage']),'INJECTED');yes(file_exists($d.'/control/deployment.lock'));
  $j=json_decode(file_get_contents($d.'/private/journal.json'),true);yes($j['state']==='blocked');
  rejected(fn()=>$p->apply($s['stage']),'Pending journal');
  $p->fault='';$out=$p->recover();yes($out['state']==='recovered');yes(digest($d.'/save.sqlite')===$db);
  yes(digest($d.'/current/release.json')===$old && !file_exists($d.'/control/deployment.lock'));
 });
check('tampered residual preserves lock and blocks recovery',function(){
 [$c,$i,$d]=fixture();$p=new Fault($c);$s=$p->stage(...$i);$p->fault='after-manifest';
 rejected(fn()=>$p->apply($s['stage']),'INJECTED');file_put_contents($d.'/current/app/new.php','tampered PHP');
 rejected(fn()=>$p->recover(),'Changed/unknown');yes(file_get_contents($d.'/current/app/new.php')==='tampered PHP');
 yes(file_exists($d.'/control/deployment.lock'));
});
check('old writer baseline refused before first content mutation',function(){
 [$c,$i,$d]=fixture();$c['baseline_data_contracts']=[];$p=new Publisher($c);$s=$p->stage(...$i);
 rejected(fn()=>$p->apply($s['stage']),'Baseline writer incompatible');yes(!file_exists($d.'/control/deployment.lock'));
});
check('lock replaced after backup aborts without content promotion',function(){
 [$c,$i,$d]=fixture();$p=new Fault($c);$s=$p->stage(...$i);$old=digest($d.'/current/app/bootstrap.php');
 $p->fault='after-backup';$p->action=fn()=>file_put_contents($d.'/control/deployment.lock','different-owner');
 rejected(fn()=>$p->apply($s['stage']),'INJECTED');$p->fault='';rejected(fn()=>$p->recover(),'lost/replaced');
 yes(digest($d.'/current/app/bootstrap.php')===$old);
});
check('late successful run cannot downgrade',function(){
 [$c,$i]=fixture();$p=new Publisher($c);$s=$p->stage(...$i);$p->apply($s['stage']);
 $older=input($i[0],'rumo10',1);rejected(fn()=>$p->verify(...$older),'Downgrade');
});
foreach([
 ['rumo10',getenv('REPRESENTATIVE_RUMO10')?:'C:/Users/rodri/AppData/Local/Temp/rumo10-release62-final-786zub0y/rumo10-entrega62-selective.tar.gz'],
 ['rumohub',getenv('REPRESENTATIVE_RUMOHUB')?:'C:/Users/rodri/AppData/Local/Temp/rumo-uol-ssh-audit-20261024-zixkb0z1/rumohub-audit-only.tar.gz']
] as [$project,$package]) {
 if(getenv('PUBLISHER_TEST_PROJECT') && getenv('PUBLISHER_TEST_PROJECT')!==$project) {
    $results[]=['name'=>'representative package '.$project,'status'=>'NOT_TESTED','reason'=>'CI scoped to other project'];continue;
 }
 check('representative complete package install '.$project,function()use($project,$package){
  [$c,$i,$d]=fixture($package,$project);$db=digest($d.'/save.sqlite');
  $p=new Publisher($c);$s=$p->stage(...$i);$res=$p->apply($s['stage']);
  yes($res['state']==='validated' && digest($d.'/save.sqlite')===$db);
  file_put_contents($d.'/representative-summary.json',json_encode(['project'=>$project,'files'=>count($s['inventory']['files']),'compressed_bytes'=>filesize($package),'expanded_bytes'=>$s['inventory']['bytes'],'peak_memory'=>memory_get_peak_usage(true),'db_unchanged'=>true]));
 });
}

class IOFailure extends Publisher {
 public string $failure='';
 protected function transfer($in,$out):int|false { return $this->failure==='disk-full'?0:parent::transfer($in,$out); }
 protected function promote(string $a,string $b):bool { return $this->failure==='rename'?false:parent::promote($a,$b); }
 protected function remove(string $p):bool { return $this->failure==='delete'?false:parent::remove($p); }
}
foreach(['disk-full','rename'] as $failure)check('injected filesystem '.$failure,function()use($failure){
 [$c,$i,$d]=fixture();$p=new IOFailure($c);$s=$p->stage(...$i);$old=digest($d.'/current/release.json');$p->failure=$failure;
 rejected(fn()=>$p->apply($s['stage']));yes(file_exists($d.'/control/deployment.lock') && digest($d.'/current/release.json')===$old);
});
check('managed deletion refusal never reports success',function(){
 [$c,$i,$d]=fixture();file_put_contents($d.'/current/app/obsolete.php','<?php // known old');
 $m=json_decode(file_get_contents($d.'/current/release.json'),true);$m['files']['app/obsolete.php']=digest($d.'/current/app/obsolete.php');record($d.'/current/release.json',$m);
 $p=new IOFailure($c);$s=$p->stage(...$i);$p->failure='delete';
 rejected(fn()=>$p->apply($s['stage']),'Managed deletion failed');yes(file_exists($d.'/control/deployment.lock'));
});
check('successive recovery evidence not overwritten',function(){
 [$c,$i,$d]=fixture();$p=new Fault($c);$s=$p->stage(...$i);$p->fault='after-manifest';
 rejected(fn()=>$p->apply($s['stage']));$old=file_get_contents($d.'/private/journal.json');
 rejected(fn()=>$p->apply($s['stage']),'Pending journal');yes(file_get_contents($d.'/private/journal.json')===$old);
 file_put_contents($d.'/current/app/new.php','modified');rejected(fn()=>$p->recover());
 $j=json_decode(file_get_contents($d.'/private/journal.json'),true);yes(count($j['operations'])>0 && $j['state']==='blocked');
});

check('validated interruption finalizes without code rollback',function(){
 [$c,$i,$d]=fixture();$p=new Fault($c);$s=$p->stage(...$i);$p->fault='before-unlock';
 rejected(fn()=>$p->apply($s['stage']),'INJECTED');yes(file_exists($d.'/control/deployment.lock'));
 $p->fault='';yes($p->recover()['state']==='validated');yes(!file_exists($d.'/control/deployment.lock'));
 yes($p->apply($s['stage'])['already_installed']===true);
});
check('disk failure temporary reconciled before release',function(){
 [$c,$i,$d]=fixture();$p=new IOFailure($c);$s=$p->stage(...$i);$p->failure='disk-full';
 rejected(fn()=>$p->apply($s['stage']));$p->failure='';
 yes($p->recover()['state']==='recovered');
 $left=glob($d.'/current/app/*.pull-*');yes(count($left)===0 && !file_exists($d.'/control/deployment.lock'));
});

check('hook timeout stops before lock or content mutation',function(){
 [$c,$i,$d]=fixture();$c['hooks']['preflight']['timeout_seconds']=0.1;
 $state=json_decode(file_get_contents(__DIR__.'/sandbox-hook-state.json'),true);$state['hang']='preflight';record(__DIR__.'/sandbox-hook-state.json',$state);
 $p=new Publisher($c);$s=$p->stage(...$i);$old=digest($d.'/current/release.json');
 rejected(fn()=>$p->apply($s['stage']),'Hook timeout');
 yes(!file_exists($d.'/control/deployment.lock') && digest($d.'/current/release.json')===$old);
});
check('pending foreign FTPS journal prevents apply',function(){
 [$c,$i,$d]=fixture();record($d.'/control/deployment-journal.json',['state'=>'blocked','pending'=>['unknown.php']]);
 $p=new Publisher($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'Unresolved legacy');
 yes(!file_exists($d.'/control/deployment.lock'));
});

check('second release public ownership follows installed checkpoint',function(){
 [$c,$i,$d]=fixture();$p=new Publisher($c);$s=$p->stage(...$i);$p->apply($s['stage']);
 // New run of a new commit, identical source package, simulates documentation-only commit.
 $next=$i;$next[1]['run_number']=3;$next[1]['head_sha']=str_repeat('c',40);$next[1]['id']=101;
 $next[2]['id']=201;$next[2]['name']='release-rumo10-'.str_repeat('c',40);$next[2]['workflow_run']=['id'=>101,'head_sha'=>str_repeat('c',40)];
 $next[3][0]['head_sha']=str_repeat('c',40);$next[3][0]['run_id']=101;
 $next[4]['run_number']=3;$next[4]['commit']=str_repeat('c',40);$next[4]['run_id']=101;
 $s2=$p->stage(...$next);yes($p->apply($s2['stage'])['state']==='validated');
});
check('backup failure safely aborts untouched version',function(){
 [$c,$i,$d]=fixture();$state=json_decode(file_get_contents(__DIR__.'/sandbox-hook-state.json'),true);$state['fail']='backup';record(__DIR__.'/sandbox-hook-state.json',$state);
 $p=new Publisher($c);$s=$p->stage(...$i);$old=digest($d.'/current/release.json');rejected(fn()=>$p->apply($s['stage']));
 $state['fail']='';record(__DIR__.'/sandbox-hook-state.json',$state);
 yes($p->recover()['content_transferred']===false);yes(!file_exists($d.'/control/deployment.lock') && digest($d.'/current/release.json')===$old);
});

check('CLI automatic apply remains disabled before token/network',function(){
 [$c,$i,$d]=fixture();$c['apply_enabled']=false;record($d.'/config.json',$c);
 $cmd=array_merge([PHP_BINARY],$c['php_args'],[dirname(__DIR__).'/bin/pull-release.php','--config='.$d.'/config.json','--mode=apply','--discover']);
 $proc=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 yes(proc_close($proc)===1 && str_contains($err,'apply disabled') && !file_exists($d.'/control/deployment.lock'));
});

foreach(['app/.. /.. /escape.php','app/a.php.','app/NUL','app/CON.txt','app/a:stream','app/a?.php'] as $path)
 check('Windows path normalization rejected: '.$path,function()use($path){rejected(fn()=>\Rumo\Publisher\relative($path));});

check('unknown executable residual blocks before maintenance and is preserved',function(){
 [$c,$i,$d]=fixture();file_put_contents($d.'/public/orphan.php','<?php // unknown executable');
 $p=new Publisher($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'Unknown existing');
 yes(is_file($d.'/public/orphan.php') && !file_exists($d.'/control/deployment.lock'));
});
check('unknown residual introduced during apply retains maintenance',function(){
 [$c,$i,$d]=fixture();$p=new Fault($c);$s=$p->stage(...$i);$p->fault='after-backup';
 $p->action=fn()=>file_put_contents($d.'/public/orphan.php','<?php // unknown executable');
 rejected(fn()=>$p->apply($s['stage']),'INJECTED');$p->fault='';
 rejected(fn()=>$p->recover(),'Unknown existing');
 yes(is_file($d.'/public/orphan.php') && file_exists($d.'/control/deployment.lock'));
});

check('two separate PHP processes share account mutex across projects',function(){
 [$c,$i,$d]=fixture();[$other,$unused,$d2]=fixture();$other['project']='rumohub';$other['repository']='rralvarez/rumohub';
 $other['account_mutex']=$c['account_mutex'];$other['apply_enabled']=false;record($d2.'/config.json',$other);
 $p=new Publisher($c);$p->stage(...$i);
 $cmd=array_merge([PHP_BINARY],$c['php_args'],[dirname(__DIR__).'/bin/pull-release.php','--config='.$d2.'/config.json','--mode=apply','--discover']);
 $child=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 yes(proc_close($child)===1 && str_contains($err,'Concurrent publisher refused'));
 yes(!file_exists($d.'/control/deployment.lock') && !file_exists($d2.'/control/deployment.lock'));
});
check('real child termination after rename recovers durable intent',function(){
 [$c,$i,$d]=fixture();$old=digest($d.'/current/release.json');$db=digest($d.'/save.sqlite');
 $p=new Publisher($c);$stage=$p->stage(...$i);unset($p);record($d.'/config.json',$c);
 $cmd=array_merge([PHP_BINARY],$c['php_args'],[__DIR__.'/crash-child.php',$d.'/config.json',$stage['stage']]);
 $child=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 yes(proc_close($child)===79,'child must terminate at the real crash point');
 $j=json_decode(file_get_contents($d.'/private/journal.json'),true);yes($j['state']==='installing' && $j['operations'][0]['state']==='intent');
 yes(file_exists($d.'/control/deployment.lock'));$p=new Publisher($c);yes($p->recover()['state']==='recovered');
 yes(digest($d.'/current/release.json')===$old && digest($d.'/save.sqlite')===$db && !file_exists($d.'/control/deployment.lock'));
});

check('journal receipts omit arbitrary API messages and actor metadata',function(){
 [$c,$i,$d]=fixture();$i[1]['head_commit']=['message'=>'PRIVATE_SENTINEL_DO_NOT_PERSIST'];
 $i[1]['actor']=['anything'=>'PRIVATE_SENTINEL_DO_NOT_PERSIST'];$i[2]['unused']='PRIVATE_SENTINEL_DO_NOT_PERSIST';
 $i[4]['unused']='PRIVATE_SENTINEL_DO_NOT_PERSIST';
 $p=new Publisher($c);$v=$p->stage(...$i);yes(!str_contains(file_get_contents($v['stage'].'/ready.json'),'PRIVATE_SENTINEL_DO_NOT_PERSIST'));
});
check('locking journal with own lock and untouched files can abort safely',function(){
 [$c,$i,$d]=fixture();$old=digest($d.'/current/release.json');$db=digest($d.'/save.sqlite');
 $p=new Fault($c);$s=$p->stage(...$i);$p->fault='after-backup';
 rejected(fn()=>$p->apply($s['stage']),'INJECTED');
 $j=json_decode(file_get_contents($d.'/private/journal.json'),true);$j['state']='locking';record($d.'/private/journal.json',$j);
 $p->fault='';yes($p->recover()['state']==='recovered');
 yes(digest($d.'/current/release.json')===$old && digest($d.'/save.sqlite')===$db && !file_exists($d.'/control/deployment.lock'));
});

require_once dirname(__DIR__).'/lib/DrainPublisher.php';
function drainFixture(?string $package=null,string $project='rumo10'):array{
 [$c,$i,$d]=fixture($package,$project);\Rumo\Publisher\RequestDrain::provision($d.'/account-gate');
 $c['request_gate']=['root'=>$d.'/account-gate','coverage_approved'=>true,'projects'=>['rumo10','rumohub'],'timeout_seconds'=>.1,
 'entrypoints'=>[__DIR__.'/drain-child.php'=>digest(__DIR__.'/drain-child.php'),__DIR__.'/ops-hook.php'=>digest(__DIR__.'/ops-hook.php')]];
 $c['hooks']['fence']=['script'=>__DIR__.'/ops-hook.php','sha256'=>digest(__DIR__.'/ops-hook.php')];
 mkdir($d.'/hub-sessions',0700);file_put_contents($d.'/hub-private.env','SYNTHETIC_HUB_CONFIG');file_put_contents($d.'/hub-sessions/qa.session','SYNTHETIC_HUB_SESSION');$c['backup_roots']['hub_env']=$d.'/hub-private.env';$c['backup_roots']['hub_sessions']=$d.'/hub-sessions';return [$c,$i,$d];
}
class OpsFault extends \Rumo\Publisher\DrainPublisher{
 public string $fault='';protected function checkpoint(string $p):void{if($this->fault===$p)throw new RuntimeException('OPS_CRASH '.$p);}
 protected function sessionCheckpoint(string $p):void{if($this->fault===$p)throw new RuntimeException('OPS_CRASH '.$p);}
}
check('operational drain publisher integrates backup installation and release',function(){
 [$c,$i,$d]=drainFixture();$db=digest($d.'/save.sqlite');$p=new OpsFault($c);$s=$p->stage(...$i);yes($p->apply($s['stage'])['state']==='validated');
 yes(!file_exists($d.'/account-gate/closed.json')&&!file_exists($d.'/control/deployment.lock')&&digest($d.'/save.sqlite')===$db);
});
foreach(['after-close','after-drain','after-backup','before-promotion-0','after-promotion-0','after-manifest','before-unlock','before-release'] as $point)check('operational failure retains gate and recovery '.$point,function()use($point){
 [$c,$i,$d]=drainFixture();$db=digest($d.'/save.sqlite');$old=digest($d.'/current/release.json');$p=new OpsFault($c);$s=$p->stage(...$i);$p->fault=$point;
 rejected(fn()=>$p->apply($s['stage']),'OPS_CRASH');yes(file_exists($d.'/account-gate/closed.json'));unset($p);
 $p=new OpsFault($c);$result=$p->recover();yes(in_array($result['state'],['recovered','validated'],true));yes(!file_exists($d.'/account-gate/closed.json'));
 yes(digest($d.'/save.sqlite')===$db);if(!in_array($point,['before-unlock','before-release'],true))yes(digest($d.'/current/release.json')===$old);
});
check('apply disabled cannot even close account gate',function(){[$c,$i,$d]=drainFixture();$c['apply_enabled']=false;$p=new OpsFault($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'disabled');yes(!file_exists($d.'/account-gate/closed.json'));});
check('incomplete enrollment cannot close gate',function(){[$c,$i,$d]=drainFixture();$c['request_gate']['projects']=['rumo10'];$p=new OpsFault($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'both projects');yes(!file_exists($d.'/account-gate/closed.json'));});
check('changed entrypoint cannot close gate',function(){[$c,$i,$d]=drainFixture();$c['request_gate']['entrypoints'][__DIR__.'/ops-hook.php']=str_repeat('0',64);$p=new OpsFault($c);$s=$p->stage(...$i);rejected(fn()=>$p->apply($s['stage']),'entrypoint');yes(!file_exists($d.'/account-gate/closed.json'));});
check('changed unstarted content prevents automatic release',function(){[$c,$i,$d]=drainFixture();$p=new OpsFault($c);$s=$p->stage(...$i);$p->fault='after-drain';rejected(fn()=>$p->apply($s['stage']),'OPS_CRASH');unset($p);file_put_contents($d.'/current/app/bootstrap.php','unknown change');$p=new OpsFault($c);rejected(fn()=>$p->recover(),'code drift');yes(file_exists($d.'/account-gate/closed.json'));});
check('changed completed public file retains drain gate',function(){[$c,$i,$d]=drainFixture();$p=new OpsFault($c);$s=$p->stage(...$i);$p->fault='before-release';rejected(fn()=>$p->apply($s['stage']),'OPS_CRASH');unset($p);file_put_contents($d.'/public/assets/style.css','tampered');$p=new OpsFault($c);rejected(fn()=>$p->recover(),'divergence');yes(file_exists($d.'/account-gate/closed.json'));});
foreach([
 ['rumo10',getenv('REPRESENTATIVE_RUMO10')?:'C:/Users/rodri/AppData/Local/Temp/rumo10-release62-final-786zub0y/rumo10-entrega62-selective.tar.gz'],
 ['rumohub',getenv('REPRESENTATIVE_RUMOHUB')?:'C:/Users/rodri/AppData/Local/Temp/rumo-uol-ssh-audit-20261024-zixkb0z1/rumohub-audit-only.tar.gz']
] as [$project,$package]){
 if(getenv('PUBLISHER_TEST_PROJECT')&&getenv('PUBLISHER_TEST_PROJECT')!==$project)continue;
 check('gated representative install and shared private backup '.$project,function()use($project,$package){
  [$c,$i,$d]=drainFixture($package,$project);$db=digest($d.'/save.sqlite');$hub=digest($d.'/hub-sessions/qa.session');$p=new OpsFault($c);$stage=$p->stage(...$i);yes($p->apply($stage['stage'])['state']==='validated');
  $j=json_decode(file_get_contents($d.'/private/journal.json'),true);yes(isset($j['private_backup']['files']['hub_env'],$j['private_backup']['files']['hub_sessions/qa.session']));
  yes(digest($d.'/hub-sessions/qa.session')===$hub&&digest($d.'/save.sqlite')===$db);yes(!file_exists($d.'/account-gate/closed.json'));
  yes(digest($j['backup'].'/private-files/rehearsal/hub_sessions/qa.session')===$hub);
 });
}
check('concrete site hooks integrated with exclusive drain and backup',function(){
 [$c,$i,$d]=drainFixture();$port=trim(file_get_contents(__DIR__.'/http-port.txt'));
 $profile=['project'=>'rumo10','current'=>$c['current'],'public'=>$c['public'],'control'=>$c['control'],'gate_root'=>$c['request_gate']['root'],'pinned_controls'=>$c['pinned_controls'],
 'maintenance_urls'=>['http://127.0.0.1:'.$port.'/','http://127.0.0.1:'.$port.'/login'],'test_only_loopback'=>true,'maintenance_body_sha256'=>hash('sha256','maintenance'),
 'fence_hook'=>['script'=>__DIR__.'/ops-hook.php','sha256'=>digest(__DIR__.'/ops-hook.php'),'args'=>[]],
 'qa_probe'=>['script'=>__DIR__.'/qa-probe-fixture.php','sha256'=>digest(__DIR__.'/qa-probe-fixture.php'),'args'=>[]]];
 record($d.'/site-profile.json',$profile);if(PHP_OS_FAMILY==='Windows')$c['php_args']=array_merge($c['php_args'],['-d','extension=php_curl.dll']);
 foreach(['preflight','maintenance','smoke'] as $name)$c['hooks'][$name]=['script'=>dirname(__DIR__).'/bin/site-check-hook.php','sha256'=>digest(dirname(__DIR__).'/bin/site-check-hook.php'),'args'=>['--profile',$d.'/site-profile.json']];
 $p=new OpsFault($c);$stage=$p->stage(...$i);yes($p->apply($stage['stage'])['state']==='validated');yes(!file_exists($d.'/account-gate/closed.json'));
});
check('interruption before gate creation leaves service open and can resume safely',function(){[$c,$i,$d]=drainFixture();$db=digest($d.'/save.sqlite');$p=new OpsFault($c);$stage=$p->stage(...$i);$p->fault='before-close';rejected(fn()=>$p->apply($stage['stage']),'OPS_CRASH');yes(!file_exists($d.'/account-gate/closed.json'));unset($p);$p=new OpsFault($c);$r=$p->recover();yes($r['gate_never_closed']&&digest($d.'/save.sqlite')===$db);});
check('idempotent installation cannot hide changed public mirror',function(){[$c,$i,$d]=drainFixture();$p=new OpsFault($c);$stage=$p->stage(...$i);yes($p->apply($stage['stage'])['state']==='validated');file_put_contents($d.'/public/assets/style.css','tampered repeat');rejected(fn()=>$p->apply($stage['stage']),'Public mirror');yes(file_exists($d.'/account-gate/closed.json'));unset($p);$p=new OpsFault($c);rejected(fn()=>$p->recover(),'Public mirror');yes(file_exists($d.'/account-gate/closed.json'));});
class CompatibleFixturePublisher extends Publisher {
 public string $crash='';public bool $short=false;
 protected function hook(string $name,array $r):array {
  if($name==='maintenance') { $x=parent::hook($name,$r);return $x+['new_requests_blocked'=>true,'publisher_exclusive'=>true]; }
  if($name==='fence')return ['ok'=>true,'legacy_publishers_fenced'=>true];
  if($name==='migrate')return ['ok'=>true,'compatible_expansion'=>true];
  return parent::hook($name,$r);
 }
 protected function checkpoint(string $p):void {if($this->crash===$p)throw new RuntimeException('COMPATIBLE_CRASH');}
 protected function transfer($a,$b):int|false {if($this->short){fwrite($b,'bad');return 3;}return parent::transfer($a,$b);}
}
function compatibleFixture():array {
 [$c,$i,$d]=fixture();$c['update_policy']=['schema'=>'rumo-update-policy-v1','protected_contract_paths'=>[],'approved_incremental_migrations'=>[]];
 $c['required_jobs'][]='compatibility';$j=$i[3][0];$j['name']='compatibility';$i[3][]=$j;return [$c,$i,$d];
}
check('compatible A installs with admission proof without false old-worker drain',function(){[$c,$i,$d]=compatibleFixture();$p=new CompatibleFixturePublisher($c);$v=$p->stage(...$i);yes($p->apply($v['stage'])['state']==='validated');});
check('compatible recovery completes forward after lost rename acknowledgement',function(){[$c,$i,$d]=compatibleFixture();$p=new CompatibleFixturePublisher($c);$v=$p->stage(...$i);$p->crash='after-promotion-0';rejected(fn()=>$p->apply($v['stage']),'COMPATIBLE_CRASH');unset($p);$p=new CompatibleFixturePublisher($c);yes($p->recover()['state']==='validated');yes(!file_exists($c['control'].'/deployment.lock'));});
check('compatible recovery reconciles partial owned temporary',function(){[$c,$i,$d]=compatibleFixture();$p=new CompatibleFixturePublisher($c);$v=$p->stage(...$i);$p->short=true;rejected(fn()=>$p->apply($v['stage']),'Short install write');unset($p);$p=new CompatibleFixturePublisher($c);yes($p->recover()['state']==='validated');});
check('compatible recovery refuses replaced target and retains maintenance',function(){[$c,$i,$d]=compatibleFixture();$p=new CompatibleFixturePublisher($c);$v=$p->stage(...$i);$p->crash='after-promotion-0';rejected(fn()=>$p->apply($v['stage']),'COMPATIBLE_CRASH');unset($p);$j=json_decode(file_get_contents($c['private'].'/journal.json'),true);file_put_contents($j['operations'][0]['dest'],'foreign write');$p=new CompatibleFixturePublisher($c);rejected(fn()=>$p->recover(),'Ambiguous write');yes(file_exists($c['control'].'/deployment.lock'));});
check('policy rejects unknown migration and requires actual compatible CI and admission',function(){
 $policy=['schema'=>'rumo-update-policy-v1','protected_contract_paths'=>[],'approved_incremental_migrations'=>[]];
 $x=Rumo\Publisher\UpdatePolicy::assess(['files'=>[]],['files'=>['database/999.sql'=>str_repeat('a',64)]],$policy);yes($x['class']==='C');
 $x=Rumo\Publisher\UpdatePolicy::assess(['files'=>[]],['files'=>['app/new.php'=>str_repeat('a',64)]],$policy);yes($x['class']==='A');rejected(fn()=>Rumo\Publisher\UpdatePolicy::maintenance(['http_status'=>503,'new_requests_blocked'=>true,'publisher_exclusive'=>true],$x),'compatible-writer');
});
$failed=count(array_filter($results,fn($r)=>$r['status']==='FAIL'));
record(dirname(__DIR__).'/evidence/tests.json',['php'=>PHP_VERSION,'root'=>$root,'results'=>$results,'passed'=>count(array_filter($results,fn($r)=>$r['status']==='PASS')),'failed'=>$failed,'peak_memory'=>memory_get_peak_usage(true)]);
foreach($results as $r)echo $r['status'].' '.$r['name'].(isset($r['error'])?' :: '.$r['error'].' '.$r['at']:'').PHP_EOL;
exit($failed?1:0);
