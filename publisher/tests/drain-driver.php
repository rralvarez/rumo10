<?php
require dirname(__DIR__).'/lib/RequestDrain.php';
use Rumo\Publisher\RequestDrain;
try{
 [$unused,$mode,$gate,$work,$owner]=$argv+array_fill(0,5,'');
 if($mode==='init'){RequestDrain::provision($gate);exit;}
 $g=new RequestDrain($gate);
 if($mode==='writer'){$f=$g->enter();file_put_contents($work.'/entered','yes');while(!is_file($work.'/finish'))usleep(10000);file_put_contents($work.'/written','saved');fclose($f);exit;}
 if($mode==='shutdown-writer'){
  $rumoRequestGateRoot=$gate;require dirname(__DIR__).'/bin/request-gate.php';session_save_path($work);session_id('synthetic-drain-request');session_start();$_SESSION['xp']=12345;
  register_shutdown_function(static function()use($work){file_put_contents($work.'/shutdown-entered','yes');while(!is_file($work.'/finish'))usleep(10000);file_put_contents($work.'/shutdown-written','saved');});
  $object=new class($work){public function __construct(private string $work){}public function __destruct(){file_put_contents($this->work.'/destructor-entered','yes');while(!is_file($this->work.'/destructor-finish'))usleep(10000);file_put_contents($this->work.'/destructor-written','saved');}};
  exit;
 }
 if($mode==='probe'){$f=$g->enter();file_put_contents($work.'/probe','write');fclose($f);exit;}
 if($mode==='install'||$mode==='resume'){
  if($mode==='install')$g->close($owner);else $g->resume($owner);
  file_put_contents($work.'/closed','yes');$g->drain((float)($argv[5]??2));file_put_contents($work.'/install-start','exclusive');
  while(!is_file($work.'/release'))usleep(10000);$g->release();exit;
 }
 throw new RuntimeException('unknown mode');
}catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(42);}
