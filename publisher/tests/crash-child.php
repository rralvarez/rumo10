<?php
declare(strict_types=1);
// Test-only process termination, bypassing Publisher catch/finalization.
require dirname(__DIR__).'/lib/Publisher.php';
final class HardCrash extends \Rumo\Publisher\Publisher {
 protected function checkpoint(string $point):void {if($point==='after-promotion-0')exit(79);}
}
$c=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$p=new HardCrash($c);$p->apply($argv[2]);
exit(80);
