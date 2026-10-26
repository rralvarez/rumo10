<?php
require dirname(__DIR__).'/lib/RequestDrain.php';
use Rumo\Publisher\RequestDrain;
try{
 $g=new RequestDrain($argv[2]);
 if($argv[1]==='writer'){$f=$g->enter();file_put_contents($argv[3].'/entered','1');while(!is_file($argv[3].'/finish'))usleep(10000);file_put_contents($argv[3].'/written','saved');flock($f,LOCK_UN);fclose($f);exit(0);}
 if($argv[1]==='probe'){$f=$g->enter();file_put_contents($argv[3].'/probe-write','saved');fclose($f);exit(0);}
 if($argv[1]==='crash-publisher'){$g->close($argv[3]);$g->drain(1);file_put_contents($argv[4],'leased');exit(79);}
}catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(42);}
