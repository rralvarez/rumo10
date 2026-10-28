<?php
declare(strict_types=1);
require $argv[1].'/app/bootstrap.php';
$db=Database::connect();$repo=new Repository($db);$account=(int)$argv[3];$action=$argv[2];
try{
 if($action==='create'){$g=Game::legacyCreate('Compatibilidade','Sao Paulo','SP','meia');$g['play_mode']='normal';if(($argv[4]??'')==='v3')PlayerDevelopment::initialize($g,'compatible-writer-contract-v1');$repo->create($account,$g);}
 elseif($action==='command'){$g=$repo->load($account);$cmd=$argv[4];$version=(int)($argv[5]??$g['version']);$repo->command($account,$cmd,['option'=>'0'],hash('sha256',implode('|',$argv).':'.$version),$version);}
 $g=$repo->load($account);$ledger=true;
 if(isset($g['player_development']))foreach(Rules::SKILLS as $k)$ledger=$ledger&&($g['player_development']['received'][$k]===$g['player_development']['spent'][$k]+$g['skills'][$k]['xp']);
 echo json_encode(['ok'=>true,'id'=>$g['id']??null,'version'=>$g['version']??null,'stage'=>$g['stage']??null,'profile'=>$g['player_development']['version']??null,'ledger'=>$ledger],JSON_THROW_ON_ERROR);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error_class'=>$e::class,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit(2);}
