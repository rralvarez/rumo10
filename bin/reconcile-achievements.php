<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV','local')==='production'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Somente ambiente local; produção exige autorização e procedimento próprios.');
$account=0;foreach($argv as $arg)if(preg_match('/^--account=([1-9][0-9]*)$/',$arg,$m))$account=(int)$m[1];
if(!$account)exit("Informe --account=ID_LOCAL_VALIDADO --dry-run. Nenhum e-mail é exibido. --apply processa apenas uma carreira desse usuário local.\n");
$r=new Repository(Database::connect());FeatureSchema::hub($r,$account);
if(!in_array('--apply',$argv,true)&&!in_array('--dry-run',$argv,true))throw new InvalidArgumentException('Escolha --dry-run ou --apply explicitamente.');
echo json_encode(AchievementReconciliation::run($r,$account,!in_array('--apply',$argv,true)),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
