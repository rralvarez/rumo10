<?php
declare(strict_types=1);

try {
    require $argv[1].'/app/bootstrap.php';
    $repo=new Repository(Database::connect());
    $account=(int)$argv[3];
    $action=$argv[2];

    if($action==='create'){
        $g=Game::legacyCreate('Compatibilidade','Sao Paulo','SP','meia');
        $g['play_mode']='normal';
        if(($argv[4]??'')==='v3')PlayerDevelopment::initialize($g,'compatible-writer-contract-v1');
        $repo->create($account,$g);
    }elseif($action==='command'){
        $g=$repo->load($account);
        $command=$argv[4];
        $version=(int)($argv[5]??$g['version']);
        $repo->command($account,$command,['option'=>'0'],hash('sha256',implode('|',$argv).':'.$version),$version);
    }

    $g=$repo->load($account);
    $ledger=true;
    if(isset($g['player_development'])){
        foreach(Rules::SKILLS as $key){
            $ledger=$ledger&&($g['player_development']['received'][$key]===$g['player_development']['spent'][$key]+$g['skills'][$key]['xp']);
        }
    }
    echo json_encode(['ok'=>true,'id'=>$g['id']??null,'version'=>$g['version']??null,'stage'=>$g['stage']??null,
        'profile'=>$g['player_development']['version']??null,'ledger'=>$ledger],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    $diagnostic=['ok'=>false,'error_class'=>$e::class,'message'=>'Detalhes não incluídos no diagnóstico do teste.'];
    if($e instanceof PDOException){
        $state=(string)($e->errorInfo[0]??$e->getCode());
        $diagnostic['sqlstate']=preg_match('/^[A-Z0-9]{5}$/D',$state)?$state:null;
        $diagnostic['driver_code']=isset($e->errorInfo[1])?(int)$e->errorInfo[1]:null;
        $diagnostic['message']='Falha SQL durante o comando.';
        if($diagnostic['sqlstate']==='42S02' && $diagnostic['driver_code']===1146 &&
            preg_match("/Table '[^']+\\.(rumo10_[a-z0-9_]+)' doesn't exist/i",$e->getMessage(),$matches)){
            $diagnostic['message']='Tabela inexistente: '.$matches[1];
        }
    }elseif($e instanceof InvalidArgumentException){
        $diagnostic['message']=str_contains($e->getMessage(),'desatualizada')
            ? 'Versão obsoleta recusada.'
            : 'Comando ou estado incompatível.';
    }elseif($e::class===RuntimeException::class && $e->getMessage()==='Nenhuma disputa de pênaltis ativa.'){
        $diagnostic['message']='Nenhuma disputa de pênaltis ativa.';
    }
    echo json_encode($diagnostic,JSON_THROW_ON_ERROR);
    exit(2);
}
