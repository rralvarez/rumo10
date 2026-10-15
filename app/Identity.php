<?php
declare(strict_types=1);
/** Identidade central; a conta local guarda somente vínculo e propriedade das carreiras. */
final class Identity {
 public static function enabled():bool{return RumoHub::enabled() && config('RUMOHUB_IDENTITY','0')==='1';}
 private static function call(string $action,#[SensitiveParameter] array $data):array {
  $r=RumoHub::fetch('/api/v1/identity/'.$action,65536,10,'POST',$data);return json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
 }
 public static function login(Repository $r,string $email,#[SensitiveParameter] string $password,bool $register):int {
  $response=self::call($register?'register':'login',['email'=>$email,'password'=>$password,'origin'=>$_SERVER['REMOTE_ADDR']??'local']);
  $account=self::bind($r,$response['user']);$_SESSION['hub_token']=$response['token'];$_SESSION['hub_user']=$response['user'];return $account;
 }
 public static function bind(Repository $r,array $u):int {
  if(!is_int($u['id']??null) || $u['id']<1 || !filter_var($u['email']??'',FILTER_VALIDATE_EMAIL) || !in_array($u['plan']??'', ['gratuito','pago'],true))throw new RuntimeException('Identidade central inválida.');
  $r->db->beginTransaction();try{
   $id=$r->query('SELECT conta_id FROM rumo10_identidades WHERE hub_usuario_id=? FOR UPDATE',[$u['id']])->fetchColumn();
   if(!$id){
    $id=$r->query('SELECT id FROM rumo10_contas WHERE email=? FOR UPDATE',[$u['email']])->fetchColumn();
    if($id)throw new RuntimeException('Conta anterior sem vínculo central. Conclua a migração antes de entrar.');
    $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',[$u['email'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$id=$r->db->lastInsertId();
    $r->query('INSERT INTO rumo10_identidades VALUES(?,?)',[$id,$u['id']]);
   }
   $r->query('INSERT INTO rumo10_conta_planos(conta_id,plano) VALUES(?,?) ON DUPLICATE KEY UPDATE plano=VALUES(plano)',[$id,$u['plan']]);$r->db->commit();return (int)$id;
  }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
 }
 public static function verify():void {
  if(!self::enabled() || !isset($_SESSION['account']))return;
  if(!isset($_SESSION['hub_token'])){unset($_SESSION['account']);return;}
  try{$u=self::call('session',['token'=>$_SESSION['hub_token']])['user'];$account=self::bind(new Repository(Database::connect()),$u);if($account!==(int)$_SESSION['account'])throw new RuntimeException('Vínculo de identidade divergente.');$_SESSION['hub_user']=$u;}
  catch(IdentityError $e){if($e->status!==401)throw $e;unset($_SESSION['account'],$_SESSION['hub_token'],$_SESSION['hub_user']);}
 }
 public static function logout():void {
  if(self::enabled() && isset($_SESSION['hub_token']))try{self::call('logout',['token'=>$_SESSION['hub_token']]);}catch(Throwable $e){error_log('Saída Hub indisponível: '.$e::class);}
 }
}
