<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$hub=class_exists('HubDatabase');$get=$hub?'hub_config':'config';
try{
 if($get('APP_ENV')!=='production' || !str_starts_with($get('APP_URL'),'https://'))throw new RuntimeException('Configure ambiente e URL de produção.');
 if(!$hub && ($get('RUMOHUB_ENABLED')!=='1' || !str_starts_with($get('RUMOHUB_URL'),'https://') || !preg_match('/^[a-f0-9]{64}$/',$get('RUMOHUB_SECRET'))))throw new RuntimeException('Configure a API HTTPS autenticada do Hub.');
 foreach(['logs','sessions'] as $dir)if(!is_writable(dirname(__DIR__).'/storage/'.$dir))throw new RuntimeException('Storage precisa de diretórios logs/sessions graváveis.');
 $db=$hub?HubDatabase::connect():Database::connect();
 $table=$hub?'rh_catalogo_metadata':'rumo10_migrations';$db->query('SELECT 1 FROM '.$table.' LIMIT 1');
 if($hub){if(!$db->query('SELECT versao FROM rh_catalogo_metadata WHERE id=1')->fetchColumn())throw new RuntimeException('Importe o catálogo antes de publicar.');if(!isset(HubAuth::clients()['rumo10']))throw new RuntimeException('Cadastre o cliente privado rumo10.');}
 else{RumoHub::refresh();}
 echo "Pré-requisitos privados de produção: OK. HTTPS/Apache devem ser testados no domínio.\n";
}catch(Throwable $e){fwrite(STDERR,'Pré-publicação recusada: '.$e::class.'. Verifique configuração, banco, catálogo e permissões sem publicar credenciais.'.PHP_EOL);exit(1);}
