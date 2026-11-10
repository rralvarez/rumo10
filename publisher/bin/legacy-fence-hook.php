<?php
require_once dirname(__DIR__).'/lib/LegacyFence.php';
use Rumo\Publisher\{LegacyFence,FenceReader};use function Rumo\Publisher\{need,json,digest};
try{
 need(PHP_SAPI==='cli'&&($argv[1]??'')==='fence'&&($argv[2]??'')==='--profile','Private fence hook only');
 $p=$argv[3]??'';need(is_file($p)&&!is_link($p),'Private fence profile required');if(PHP_OS_FAMILY!=='Windows')need((fileperms($p)&0077)===0,'Profile must be 0600');
 $c=json(file_get_contents($p));$readers=[];foreach($c['repositories'] as $repo=>$v)$readers[$repo]=new FenceReader(['repository'=>$repo,'token_file'=>$v['token_file']]);
 $r=LegacyFence::verify($c,fn($repo,$path)=>$readers[$repo]->get($path));echo json_encode($r,JSON_THROW_ON_ERROR);
}catch(Throwable $e){fwrite(STDERR,'Publication fence not demonstrated; no changes performed'.PHP_EOL);exit(1);}
