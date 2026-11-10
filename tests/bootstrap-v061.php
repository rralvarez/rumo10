<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=0;
$old=str_replace("\r\n","\n",shell_exec('git show 2534b53:app/bootstrap.php')??'');$now=str_replace("\r\n","\n",file_get_contents($root.'/app/bootstrap.php'));
if(str_replace('[A-Z_]+','[A-Z_][A-Z0-9_]*',$old)!==$now)throw new RuntimeException('Bootstrap modificado além do parser autorizado.');$checks++;
$env=file_get_contents($root.'/.env');foreach(['RUMO10_ACHIEVEMENTS','RUMO10_RECONCILIATION','RUMO10_FEEDBACK'] as $key){
 if(!preg_match('/^'.preg_quote($key,'/').'=(0|1)\s*$/m',$env,$m))throw new RuntimeException('Flag local ausente: '.$key);
 $code='putenv("'.$key.'");require "app/bootstrap.php";echo config("'.$key.'");';$p=proc_open([PHP_BINARY,'-n','-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);fclose($pipes[0]);$value=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!==''||$value!==$m[1])throw new RuntimeException('Flag com dígito não carregada: '.$key);$checks++;
}
file_put_contents($root.'/Docs/pos-jogo-v061/bootstrap.json',json_encode(['checks'=>$checks,'actual_dotenv_numeric_keys'=>'passed','no_secrets_recorded'=>true],JSON_PRETTY_PRINT));echo "OK: $checks verificações do bootstrap real e flags locais.\n";
