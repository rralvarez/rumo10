<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV','local')!=='local' || config('DB_DATABASE')!=='rumohub' || !in_array(config('DB_HOST'),['localhost','127.0.0.1'],true))throw new RuntimeException('Teste restrito ao MySQL local.');
$file=$argv[1]??'';$receipt=json_decode(file_get_contents($file.'.json'),true,512,JSON_THROW_ON_ERROR);
if(hash_file('sha256',$file)!==$receipt['sha256'])throw new RuntimeException('Hash divergente.');
$db=Database::connect();$schema='rumohub_deploy_test_'.bin2hex(random_bytes(5));
$db->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
 $db->exec('USE `'.$schema.'`');$gz=gzopen($file,'rb');$statement='';$inserts=0;
 while(($line=gzgets($gz))!==false){
  if(str_starts_with($line,'-- ') || trim($line)==='')continue;
  $statement.=$line;
  if(!str_ends_with(rtrim($line),';'))continue;
  if(!preg_match('/^(CREATE TABLE `(rh|rumo10)_[a-z_]+`|INSERT INTO `?(rh|rumo10)_[a-z_]+`? |SET NAMES utf8mb4;|SET SESSION time_zone |START TRANSACTION;|COMMIT;)/',$statement))throw new RuntimeException('SQL fora do escopo.');
  $db->exec($statement);if(str_starts_with($statement,'INSERT INTO '))$inserts++;$statement='';
 }
 gzclose($gz);if(trim($statement)!=='')throw new RuntimeException('SQL incompleto.');
 $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);sort($tables);$expected=$receipt['schema_tables'];sort($expected);
 if($tables!==$expected)throw new RuntimeException('Esquema divergente.');
 foreach($receipt['reference_rows'] as $table=>$count)if((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==$count)throw new RuntimeException('Contagem divergente.');
 foreach(['rumo10_contas','rumo10_carreiras','rh_admins','rh_admin_setup','rh_admin_audit','rh_api_nonces','rh_catalogo_overrides'] as $table)if((int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==0)throw new RuntimeException('Dados pessoais ou tokens indevidos.');
 if((int)$db->query('SELECT COUNT(*) FROM rh_hub_games')->fetchColumn()!==3)throw new RuntimeException('Jogos ausentes.');
 // O preparo inicial sem SSH também deve ser seguro e repetível em um banco novo.
 $setup="INSERT INTO rh_admin_setup (id,token_hash,expira_em) SELECT 1,'".str_repeat('f',64)."',9999999999 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM rh_admins WHERE ativo=1) AND NOT EXISTS (SELECT 1 FROM rh_admin_setup WHERE id=1)";
 $db->exec($setup);$db->exec($setup);
 if((int)$db->query('SELECT COUNT(*) FROM rh_admin_setup')->fetchColumn()!==1)throw new RuntimeException('Preparo offline não é idempotente.');
 echo 'OK: SQL importado em banco local descartável: '.count($tables).' tabelas, '.array_sum($receipt['reference_rows'])." referências, 3 jogos, zero contas/carreiros/administradores.\n";
}finally{
 if($db->inTransaction())$db->rollBack();
 if(!preg_match('/^rumohub_deploy_test_[a-f0-9]{10}$/',$schema))throw new RuntimeException('Nome de teste inesperado.');
 $db->exec('DROP DATABASE `'.$schema.'`');
}
