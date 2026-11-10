<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV','local')==='production')throw new RuntimeException('Produção recusada.');
if(!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Somente rumohub no MySQL local.');
$mode=$argv[1]??'';if(!in_array($mode,['--backup-local','--install-local'],true))exit("Use --backup-local; depois --install-local. Nunca produção.\n");
$root=dirname(__DIR__);$dir=$root.'/storage/private/growth-v061';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Backup privado indisponível.');
$receipt=$dir.'/backup.json';
if($mode==='--backup-local'){
 $db=Database::connect();$tables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,7)='rumo10_' AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
 $dump='C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe';if(!is_file($dump))throw new RuntimeException('mysqldump local não encontrado.');
 $encode=fn($v)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],$v).'"';
 $client=$dir.'/client.cnf';file_put_contents($client,"[client]\nhost=".$encode(config('DB_HOST'))."\nport=".config('DB_PORT','3306')."\nuser=".$encode(config('DB_USERNAME'))."\npassword=".$encode(config('DB_PASSWORD'))."\n");
 $file=$dir.'/rumo10-'.gmdate('Ymd-His').'.sql';$error=$dir.'/dump-error.log';
 try{$p=proc_open([$dump,'--defaults-extra-file='.$client,'--single-transaction','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--hex-blob','--complete-insert','--skip-add-drop-table','rumohub',...$tables],[0=>['pipe','r'],1=>['file',$file,'w'],2=>['file',$error,'w']],$pipes);if(!is_resource($p))throw new RuntimeException('Não iniciou backup.');fclose($pipes[0]);if(proc_close($p)!==0 || filesize($file)<100)throw new RuntimeException('Backup falhou; consulte log privado local.');}
 finally{if(is_file($client))unlink($client);}
 $data=['database'=>'rumohub','host'=>'loopback','tables'=>count($tables),'file'=>$file,'bytes'=>filesize($file),'sha256'=>hash_file('sha256',$file),'created_at'=>gmdate('c')];file_put_contents($receipt,json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Backup local: '.$data['tables'].' tabelas, '.$data['bytes'].' bytes; SHA256 '.$data['sha256']."\n";exit;
}
if(!is_file($receipt))throw new RuntimeException('Faça backup primeiro.');$b=json_decode(file_get_contents($receipt),true,512,JSON_THROW_ON_ERROR);
if(time()-strtotime($b['created_at'])>3600 || !hash_equals($b['sha256'],hash_file('sha256',$b['file'])))throw new RuntimeException('Backup inválido ou antigo.');
$db=Database::connect();$sql=file_get_contents($root.'/database/migrations/007-reconciliation-feedback.sql');$sql=preg_replace('/^--.*$/m','',$sql);foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$db->exec($statement);
echo "Migration 007 aditiva aplicada somente em rumohub local. Flag permanece desativada.\n";
