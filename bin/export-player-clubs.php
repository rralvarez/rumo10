<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$out=[];foreach(Competition::clubs() as $name=>$club)$out[$name]=['country'=>TransferMarket::country($name),'overall'=>TeamStrength::initial($name),'competitions'=>$club['competitions'],'badge_url'=>$club['badge_url']??null,'source'=>$club['source']];
$dir=dirname(__DIR__).'/storage/player-sources';if(!is_dir($dir))mkdir($dir,0775,true);
file_put_contents($dir.'/clubs.json',json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
echo count($out)." clubes exportados; sem conexão ao banco.\n";
