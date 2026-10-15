<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require 'C:/inetpub/wwwroot/rumohub/app/bootstrap.php';
if(config('APP_ENV','local')!=='local')throw new RuntimeException('Fixture somente local.');$db=Database::connect();if($db->query('SELECT DATABASE()')->fetchColumn()!=='rumohub')throw new RuntimeException('Banco local inesperado.');
$file=dirname(__DIR__).'/storage/private/package-mobile.json';$r=new Repository($db);
if(in_array('--clean',$argv,true)){
 $fixture=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);if(!preg_match('/^qa-mobile-[a-f0-9]{16}@example.invalid$/',$fixture['email']))throw new RuntimeException('Fixture não reconhecida.');
 $stored=$r->query('SELECT id FROM rumo10_contas WHERE id=? AND email=?',[$fixture['account'],$fixture['email']])->fetchColumn();if((int)$stored!==$fixture['account'])throw new RuntimeException('Conta divergente.');
 foreach(CareerSlots::listing($r,$fixture['account']) as $c)CareerSlots::delete($r,$fixture['account'],(int)$c['id'],'delete');$r->query('DELETE FROM rumo10_contas WHERE id=?',[$fixture['account']]);$r->query('DELETE FROM rh_primeiras_carreiras WHERE usuario_id=?',[$fixture['user']]);$r->query('DELETE FROM rh_convites WHERE usuario_id=?',[$fixture['user']]);$r->query('DELETE FROM rh_usuarios WHERE id=? AND email=?',[$fixture['user'],$fixture['email']]);unlink($file);echo "Fixture mobile local removida.\n";exit;
}
if(is_file($file))throw new RuntimeException('Limpe a fixture anterior antes de recriar.');
$email='qa-mobile-'.bin2hex(random_bytes(8)).'@example.invalid';$password=bin2hex(random_bytes(20));$u=HubUsers::register($db,$email,$password,'qa-mobile');$token=HubUsers::issue($db,$u,'rumo10')['token'];
$_SESSION=['hub_user'=>$u,'hub_token'=>$token];$account=Identity::bind($r,$u);$_SESSION['account']=$account;
$g=EntryCountries::create('Jogador de interface','São Paulo','SP','meia','BR','gratuito');$g['play_mode']='normal';$g=Game::command($g,'handicap');$g=Game::command($g,'join',['option'=>0]);$g=Game::command($g,'choice',['option'=>0]);$g=Game::command($g,'choice',['option'=>0]);$g=Game::command($g,'advance');$r->create($account,$g);
file_put_contents($file,json_encode(['email'=>$email,'password'=>$password,'account'=>$account,'user'=>$u['id']],JSON_THROW_ON_ERROR));echo "Fixture mobile local preparada em arquivo privado, sem credenciais na saída.\n";
