<?php
declare(strict_types=1);
// Operator-owned read-only compatibility probe. It does not create/update any career or DB row.
try{
 if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--profile')throw new RuntimeException('CLI profile required');$file=$argv[2]??'';
 if(!is_file($file)||is_link($file)||(PHP_OS_FAMILY!=='Windows'&&(fileperms($file)&0077)!==0))throw new RuntimeException('Private profile required');
 $c=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$runtime=realpath($c['runtime']);if(!$runtime||is_link($c['runtime']))throw new RuntimeException('Runtime missing');
 require $runtime.'/app/bootstrap.php';$out=[];
 foreach(['legacy','v3'] as $kind){$p=$c[$kind.'_snapshot'];if(!is_file($p)||is_link($p)||hash_file('sha256',$p)!==$c[$kind.'_sha256'])throw new RuntimeException('QA snapshot changed');$g=json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);$before=serialize($g);
  if(PlayerDevelopment::enabled($g)!==($kind==='v3'))throw new RuntimeException('Writer compatibility mismatch');
  if($kind==='v3'){foreach(Rules::SKILLS as $s)if($g['player_development']['received'][$s]!==$g['player_development']['spent'][$s]+$g['skills'][$s]['xp'])throw new RuntimeException('QA ledger mismatch');foreach($g['reward_ledger']??[] as $key=>$event){Progression::distribute($g,999,$key);break;}if(serialize($g)!==$before)throw new RuntimeException('QA replay not idempotent');}
  $out[$kind]=true;
 }
 echo json_encode(['ok'=>true,'read_only'=>true,'legacy_compatible'=>$out['legacy'],'v3_ledger_compatible'=>$out['v3'],'scope'=>'pinned private QA snapshots in memory; not live authentication']);
}catch(Throwable $e){fwrite(STDERR,'QA compatibility proof failed'.PHP_EOL);exit(1);}
