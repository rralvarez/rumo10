<?php
declare(strict_types=1);
$root=dirname(__DIR__);$baseline=json_decode(file_get_contents($root.'/Docs/estabilizacao-v04/baseline.json'),true,512,JSON_THROW_ON_ERROR);
$allowed=['app/Game.php'=>'Resolução unificada de execução e rastreio; RNG do teste.','app/Probability.php'=>'RNG injetável validado; random_int continua padrão.','app/PlayMode.php'=>'Audita componentes de chance sem alterar seleção.'];$rows=[];
foreach($baseline['hashes'] as $file=>$hash){$same=hash_equals($hash,hash('sha256',str_replace("\r\n","\n",file_get_contents($root.'/'.$file))));if(!$same&&!isset($allowed[$file]))throw new RuntimeException('Fora da superfície mecânica autorizada: '.$file);$rows[]=['file'=>$file,'unchanged'=>$same,'intentional_reason'=>$same?null:$allowed[$file]];}
file_put_contents($root.'/Docs/estabilizacao-v04/fronteiras.json',json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo 'OK: '.count($rows)." arquivos comparados; catálogo, gates, pesos, sementes, snapshots e demais mecânicas preservados.\n";
