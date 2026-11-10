<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib/GitHub.php';
use Rumo\Publisher\GitHub;
use function Rumo\Publisher\{digest,inspectArtifactZip,record};
$results=[];$root=__DIR__.'/client-'.bin2hex(random_bytes(4));mkdir($root,0700);file_put_contents($root.'/fake.token','synthetic-credential-not-valid');chmod($root.'/fake.token',0600);
function check(string $name,callable $f):void{global $results;try{$f();$results[]=['name'=>$name,'status'=>'PASS'];}catch(Throwable $e){$results[]=['name'=>$name,'status'=>'FAIL','error'=>$e->getMessage()];}}
function yes(bool $v):void{if(!$v)throw new RuntimeException('assertion');}
function rejected(callable $f):void{try{$f();}catch(Throwable $e){return;}throw new RuntimeException('rejection absent');}
$meta=json_decode(file_get_contents(__DIR__.'/fixtures/client.json'),true);
$c=['project'=>'rumo10','repository'=>'rralvarez/rumo10','branch'=>'main','workflow_id'=>7,'required_jobs'=>['checks'],
 'private'=>$root,'token_file'=>$root.'/fake.token','baseline_commit'=>str_repeat('a',40),'max_artifact_bytes'=>10000000,
 'workflow_path'=>'.github/workflows/release.yml','workflow_sha256'=>hash('sha256','trusted workflow')];
class MockClient extends GitHub {
 public array $meta;public string $scenario='ok';public int $calls=0;public array $observations=[];
 public string $zip=__DIR__.'/fixtures/client.zip';
 protected function request(string $url,bool $auth,int $limit,?string $out=null):array {
  self::checkUrl($url,$auth);$this->observations[]=[$auth,parse_url($url,PHP_URL_HOST)];
  if(str_contains($url,'blob.core.windows.net')){
   if($this->scenario==='partial') {file_put_contents($out,'partial');throw new RuntimeException('injected EOF');}
   if($this->scenario==='expired' && $this->calls++===0)return ['status'=>403,'body'=>'','headers'=>[],'bytes'=>0];
   copy($this->zip,$out);
   return ['status'=>200,'body'=>'','headers'=>[],'bytes'=>filesize($out)];
  }
  if(str_ends_with($url,'/zip'))return ['status'=>302,'headers'=>['location'=>'https://synthetic.blob.core.windows.net/artifact?short-lived'],'body'=>'','bytes'=>0];
  if(str_contains($url,'/contents/'))$v=['encoding'=>'base64','content'=>base64_encode($this->scenario==='workflow-drift'?'untrusted workflow':'trusted workflow')];
  elseif(str_ends_with($url,'/git/ref/heads/main'))$v=['object'=>['sha'=>$this->scenario==='newer-main'?str_repeat('c',40):$this->meta['run']['head_sha']]];
  elseif(str_contains($url,'/pulls?'))$v=$this->scenario==='no-pr'?[]:[['number'=>18,'state'=>'closed','merged_at'=>'2026-11-10T00:00:00Z',
   'base'=>['ref'=>'main','repo'=>['full_name'=>'rralvarez/rumo10']],'merge_commit_sha'=>$this->meta['run']['head_sha']]];
  elseif(str_contains($url,'/compare/'))$v=['status'=>$this->scenario==='diverged'?'diverged':'ahead','merge_base_commit'=>['sha'=>str_repeat('b',40)]];
  elseif(str_contains($url,'/workflows/') && str_contains($url,'/runs?'))$v=['workflow_runs'=>[$this->meta['run']]];
  elseif(str_contains($url,'/runs/100/artifacts?'))$v=['total_count'=>1,'artifacts'=>[$this->meta['artifact']]];
  elseif(str_contains($url,'/jobs?'))$v=['total_count'=>1,'jobs'=>$this->meta['jobs']];
  elseif(str_contains($url,'/artifacts/')){
   $v=$this->meta['artifact'];if($this->scenario==='metadata-drift' && $this->calls++>0)$v['expired']=true;
  }else $v=$this->meta['run'];
  return ['status'=>200,'body'=>json_encode($v),'headers'=>[],'bytes'=>0];
 }
}
function client(string $scenario='ok'):MockClient{global $c,$meta;$p=new MockClient($c);$p->meta=$meta;$p->scenario=$scenario;return $p;}
foreach([
 ['http://api.github.com/x',true,false],['https://api.github.com/x',true,true],
 ['https://synthetic.blob.core.windows.net/x',false,true],['https://synthetic.blob.core.windows.net/x',true,false],
 ['https://api.github.com.attacker.example/x',false,false],['https://user:pass@api.github.com/x',true,false],
 ['https://api.github.com:444/x',true,false],['https://foo.actions.githubusercontent.com/x',false,true],
 ['https://blob.core.windows.net.evil/x',false,false]
] as $i=>[$url,$auth,$allowed])check('HTTPS credential boundary '.$i,function()use($url,$auth,$allowed){
 if($allowed)GitHub::checkUrl($url,$auth);else rejected(fn()=>GitHub::checkUrl($url,$auth));
});
check('mock authenticated metadata -> ZIP -> envelope -> TAR hash',function()use($root){
 $p=client();$r=$p->fetch(100,200,$root.'/ok');yes(digest($r[0])===$r[4]['package_sha256']);
 foreach($p->observations as [$auth,$host])yes(!$auth || $host==='api.github.com');
});
check('expired signed URL refreshed without forwarding credential',function()use($root){$p=client('expired');$p->fetch(100,200,$root.'/expired');yes($p->calls===2);});
foreach(['partial','workflow-drift','metadata-drift'] as $bad)check('source failure '.$bad,function()use($root,$bad){$p=client($bad);rejected(fn()=>$p->fetch(100,200,$root.'/'.$bad));});
check('descendant accepted from comparison API',fn()=>client()->assertDescendant(str_repeat('b',40),str_repeat('a',40)));
check('diverged commit rejected',fn()=>rejected(fn()=>client('diverged')->assertDescendant(str_repeat('b',40),str_repeat('a',40))));
check('duplicate outer ZIP rejected',fn()=>rejected(fn()=>inspectArtifactZip(__DIR__.'/fixtures/duplicate.zip')));
check('outer ZIP traversal rejected',fn()=>rejected(fn()=>inspectArtifactZip(__DIR__.'/fixtures/traversal.zip')));
check('automatic discovery binds latest successful run to unique artifact',function(){
 $p=client();yes($p->discover()===[100,200]);
});
check('main discovery waits for the latest main SHA',function()use($c,$meta){
 $p=new MockClient($c+['release_channel'=>'main']);$p->meta=$meta;
 yes($p->discover()===[100,200]);
 $p->scenario='newer-main';rejected(fn()=>$p->discover());
});
check('recovery profile has no automatic discovery',function()use($c,$meta){
 $p=new MockClient($c+['release_channel'=>'recovery-v8']);$p->meta=$meta;
 rejected(fn()=>$p->discover());yes($p->observations===[]);
});
check('main fetch attaches merged PR from API and blocks wrong workflow bytes',function()use($c,$meta,$root){
 $names=['syntax','regression','compatibility','publisher-safety','rumo10-ci-gate','artifact'];
 $normal=array_replace($c,['release_channel'=>'main','required_jobs'=>$names]);
 $p=new MockClient($normal);$p->meta=$meta;
 $p->zip=$root.'/main.zip';
 $original=new PharData(__DIR__.'/fixtures/client.zip');
 $e=json_decode($original['candidate.json']->getContent(),true);
 $e['release_channel']='main';$e['source_commit']=$e['commit'];
 $zip=new PharData($p->zip);
 $zip['candidate.json']=json_encode($e);
 $zip['release.tar.gz']=$original['release.tar.gz']->getContent();
 unset($zip);
 $p->meta['artifact']['digest']='sha256:'.digest($p->zip);
 $p->meta['jobs']=array_map(fn($name)=>array_replace($meta['jobs'][0],['name'=>$name]),$names);
 $result=$p->fetch(100,200,$root.'/main-ok');
 yes($result[1]['merged_pr']===18);
 $p->scenario='no-pr';rejected(fn()=>$p->fetch(100,200,$root.'/main-no-pr'));
 $p->scenario='workflow-drift';rejected(fn()=>$p->fetch(100,200,$root.'/main-workflow-drift'));
});
$failed=count(array_filter($results,fn($r)=>$r['status']==='FAIL'));
record(dirname(__DIR__).'/evidence/client-tests.json',['results'=>$results,'passed'=>count($results)-$failed,'failed'=>$failed,'live_requests'=>0]);
foreach($results as $r)echo $r['status'].' '.$r['name'].(isset($r['error'])?' '.$r['error']:'').PHP_EOL;
exit($failed?1:0);
