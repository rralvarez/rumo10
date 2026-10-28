<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/Publisher.php';require_once __DIR__.'/RequestDrain.php';
/** Operator entry point. Does not activate/modify existing bridges or legacy publishers. */
class DrainPublisher extends Publisher {
 private array $ops;private ?RequestDrain $drain=null;private string $session;
 public function __construct(array $config){parent::__construct($config);$this->ops=$config;$this->session=$config['private'].'/drain-session.json';}
 protected function hook(string $name,array $request):array{
  if(in_array($name,['maintenance','backup','smoke'],true)){$this->drain?->assertDrained();need($this->drain!==null,'Drain integration missing');}
  $receipt=parent::hook($name,$request);
  if($name==='maintenance'){$this->drain->assertDrained();$receipt['inflight_writers']=0;$receipt['writes_blocked']=true;}
  return $receipt;
 }
 private function enroll():void{
  $c=$this->ops;need(($c['apply_enabled']??false)===true,'apply disabled');
  $e=$c['request_gate']??null;need(is_array($e)&&($e['coverage_approved']??false)===true,'All-writer coverage not approved');
  $projects=$e['projects']??[];sort($projects);need($projects===['rumo10','rumohub'],'Shared DB requires both projects fenced');
  need(count($e['entrypoints']??[])>=2,'Pinned web and CLI entrypoints required');
  foreach($e['entrypoints'] as $p=>$hash)need(is_file($p)&&!is_link($p)&&digest($p)===$hash,'Writer entrypoint missing/changed');
  $this->acquire();
  // Mandatory trusted CLI hook must check disabled legacy workflows, queued runs and other writer admission.
  $f=parent::hook('fence',['project'=>$c['project'],'action'=>'verify-account-fence']);
  need(($f['legacy_publishers_fenced']??false)===true,'Legacy publisher fence not confirmed');
  $this->drain=new RequestDrain($e['root']);
 }
 protected function sessionCheckpoint(string $point):void{}
 private function content():array{
  $out=[];foreach(['current','public'] as $kind){$root=$this->ops[$kind];$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
   foreach($it as $f){$rel=str_replace(chr(92),'/',substr($f->getPathname(),strlen($root)+1));if(protectedPath($rel))continue;need(!$f->isLink(),'Unknown linked operational entry');if($f->isFile())$out[$kind.'/'.$rel]=digest($f->getPathname());}
  }ksort($out);return $out;
 }
 public function apply(string $stage):array{
  $this->enroll();need(!is_file($this->session)||in_array(json(file_get_contents($this->session))['state'],['validated','recovered'],true),'Pending drain session: recover first');
  $owner=bin2hex(random_bytes(16));$receipt=['owner'=>$owner,'state'=>'closing','stage'=>$stage,'content_before'=>$this->content(),'journal_before'=>is_file($this->ops['private'].'/journal.json')?digest($this->ops['private'].'/journal.json'):null];record($this->session,$receipt);$this->sessionCheckpoint('before-close');$this->drain->close($owner);
  try{$this->sessionCheckpoint('after-close');$this->drain->drain((float)($this->ops['request_gate']['timeout_seconds']??30));$receipt['state']='installing';record($this->session,$receipt);$this->sessionCheckpoint('after-drain');
   $result=parent::apply($stage);$this->drain->assertDrained();need($result['state']==='validated','Installation not validated');
   $receipt['state']='validated';record($this->session,$receipt);$this->sessionCheckpoint('before-release');$this->drain->release();return $result;
  }catch(\Throwable $e){$receipt['state']='blocked';record($this->session,$receipt);throw $e;}
 }
 public function recover():array{
  $this->enroll();$s=json(file_get_contents($this->session));
  if(!$this->drain->closed()){
   $j=$this->ops['private'].'/journal.json';$prior=(is_file($j)?digest($j):null);
   need($prior===($s['journal_before']??null)&&!file_exists($this->ops['control'].'/deployment.lock')&&$this->content()===$s['content_before'],'Open gate with changed attempt; manual reconciliation required');
   $this->verifyUnstartedBaseline();$s['state']='recovered';record($this->session,$s);return ['state'=>'recovered','content_transferred'=>false,'database_restored'=>false,'gate_never_closed'=>true];
  }
  $this->drain->resume($s['owner']);$this->drain->drain((float)($this->ops['request_gate']['timeout_seconds']??30));
  $journal=$this->ops['private'].'/journal.json';$unchanged=(is_file($journal)?digest($journal):null)===($s['journal_before']??null);
  if($unchanged&&!file_exists($this->ops['control'].'/deployment.lock')){
   need($this->content()===$s['content_before'],'Unstarted attempt code drift; manual reconciliation required');$this->verifyUnstartedBaseline();$this->hook('smoke',['project'=>$this->ops['project'],'action'=>'abort-before-install']);
   $s['state']='recovered';record($this->session,$s);$this->drain->release();return ['state'=>'recovered','content_transferred'=>false,'database_restored'=>false];
  }
  $completed=is_file($journal)&&json(file_get_contents($journal))['state']==='validated'&&!file_exists($this->ops['control'].'/deployment.lock');
  $result=$completed?$this->completedWithoutLock():parent::recover();$this->drain->assertDrained();need(in_array($result['state'],['recovered','validated'],true),'Recovery not validated');
  record($this->session,['owner'=>$s['owner'],'state'=>$result['state'],'stage'=>$s['stage']]);$this->drain->release();return $result;
 }
}
