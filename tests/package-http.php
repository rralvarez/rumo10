<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require 'C:/inetpub/wwwroot/rumohub/app/bootstrap.php';
if(config('APP_ENV','local')!=='local')throw new RuntimeException('Fixture restrita ao IIS local.');
$db=Database::connect();if($db->query('SELECT DATABASE()')->fetchColumn()!=='rumohub')throw new RuntimeException('Banco inesperado.');
$tag='qa-http-growth-'.bin2hex(random_bytes(6));$pass=bin2hex(random_bytes(18));$clients=[];$accounts=[];$users=[];$checks=0;$admin=0;
function httpCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function sqlh(string $sql,array $args=[]):PDOStatement{global $db;return HubBenefits::q($db,$sql,$args);}
final class PackageClient {
 public string $jar;public string $body='';public int $status=0;
 public function __construct(public string $base='http://rumo10.local'){$this->jar=tempnam(dirname(__DIR__).'/storage/qa-package','cookie-');}
 public function handle(string $path,array $post=[],bool $follow=true):CurlHandle {
  $c=curl_init($this->base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>$follow,CURLOPT_COOKIEFILE=>$this->jar,CURLOPT_COOKIEJAR=>$this->jar,CURLOPT_TIMEOUT=>30]);if($post)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);return $c;
 }
 public function request(string $path,array $post=[]):self {$c=$this->handle($path,$post);$this->body=(string)curl_exec($c);$this->status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return $this;}
 public function field(string $name):string {if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$this->body,$m))throw new RuntimeException('Campo de formulário ausente: '.$name.'; HTTP '.$this->status);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
 public function csrf():string{return $this->field('csrf');}
}
function client():PackageClient{global $clients;$c=new PackageClient();$clients[]=$c;return $c;}
function registerFixture(PackageClient $c,string $suffix,?string $invite=null):array {
 global $tag,$pass,$accounts,$users;
 if($invite)$c->request('/convite?code='.$invite);else$c->request('/register');$csrf=$c->csrf();$email=$tag.'-'.$suffix.'@example.invalid';$c->request('/register',['csrf'=>$csrf,'email'=>$email,'password'=>$pass]);httpCheck($c->status===200,'Cadastro HTTP funciona com identidade Hub');
 $a=(int)sqlh('SELECT id FROM rumo10_contas WHERE email=?',[$email])->fetchColumn();$u=(int)sqlh('SELECT id FROM rh_usuarios WHERE email=?',[$email])->fetchColumn();httpCheck($a>0 && $u>0,'Identidades locais e centrais vinculadas');$accounts[]=$a;$users[]=$u;return [$a,$u];
}
function creation(PackageClient $c,string $mode='normal'):array {$c->request('/carreira/nova');httpCheck($c->status===200,'Formulário de carreira disponível');return ['csrf'=>$c->csrf(),'creation_key'=>$c->field('creation_key'),'name'=>'Jogador de fixture','city'=>'São Paulo','uf'=>'SP','position'=>'meia','country'=>'BR','residence_country'=>'BR','mode'=>$mode];}
try{
 $owner=client();[$aid,$uid]=registerFixture($owner,'owner');
 foreach(['normal','rapido'] as $mode){$input=creation($owner,$mode);$owner->request('/carreira/nova',$input);httpCheck($owner->status===200,'Criação de vaga-base persistida');}
 $owner->request('/carreira/nova',creation($owner));httpCheck($owner->status===422,'Terceira vaga gratuita exige indicação');
 $owner->request('/convites');preg_match('/id="invite-link"[^>]*value="[^"]*code=([a-f0-9]{64})/',$owner->body,$m);$invite=$m[1]??'';httpCheck(strlen($invite)===64,'Link de compartilhamento disponível');
 $friend=client();[$friendAccount,$friendUid]=registerFixture($friend,'friend-1',$invite);
 httpCheck((int)sqlh('SELECT COUNT(*) FROM rh_creditos_indicacao WHERE usuario_id=?',[$uid])->fetchColumn()===0,'Cadastro HTTP isolado não credita');
 $friend->request('/carreira/nova',creation($friend,'expresso'));httpCheck($friend->status===200,'Primeira carreira do amigo confirmada');
 httpCheck((int)sqlh("SELECT COUNT(*) FROM rh_creditos_indicacao WHERE usuario_id=? AND estado='disponivel'",[$uid])->fetchColumn()===1,'Comprovação HTTP gera um crédito');
 // Duas sessões independentes da mesma conta: disputa real pelo último crédito/vaga.
 $other=client();$other->request('/login');$other->request('/login',['csrf'=>$other->csrf(),'email'=>$tag.'-owner@example.invalid','password'=>$pass]);httpCheck($other->status===200,'Segunda sessão independente');
 $one=creation($owner);$two=creation($other);$h1=$owner->handle('/carreira/nova',$one,false);$h2=$other->handle('/carreira/nova',$two,false);$multi=curl_multi_init();curl_multi_add_handle($multi,$h1);curl_multi_add_handle($multi,$h2);
 do{$state=curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,.2);}while($running && $state===CURLM_OK);
 $s1=curl_getinfo($h1,CURLINFO_RESPONSE_CODE);$s2=curl_getinfo($h2,CURLINFO_RESPONSE_CODE);curl_multi_remove_handle($multi,$h1);curl_multi_remove_handle($multi,$h2);curl_multi_close($multi);curl_close($h1);curl_close($h2);
 httpCheck(($s1===303 && $s2===422)||($s1===422 && $s2===303),'Concorrência cria uma carreira e rejeita a segunda');
 $winner=$s1===303?$owner:$other;$payload=$s1===303?$one:$two;$winner->request('/carreira/nova',$payload);httpCheck($winner->status===200,'Replay da criação retorna sem duplicar');
 httpCheck((int)sqlh('SELECT COUNT(*) FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id WHERE c.conta_id=? OR a.conta_id=?',[$aid,$aid])->fetchColumn()===3,'Somente três carreiras após corrida e replay');
 $extra=(int)sqlh("SELECT o.carreira_id FROM rumo10_origem_vagas o JOIN rumo10_carreiras c ON c.id=o.carreira_id WHERE o.tipo='INDICACAO' AND c.conta_id=?",[$aid])->fetchColumn();httpCheck($extra>0,'Origem da vaga vinculada ao crédito');
 $winner->request('/carreira/excluir?career='.$extra);$winner->request('/carreira/excluir',['csrf'=>$winner->csrf(),'career'=>$extra,'confirm'=>'delete']);httpCheck($winner->status===200,'Exclusão autorizada da fixture');
 httpCheck((int)sqlh("SELECT COUNT(*) FROM rh_creditos_indicacao WHERE usuario_id=? AND estado='consumido'",[$uid])->fetchColumn()===1,'Exclusão não devolve crédito usado');
 $winner->request('/carreira/nova',creation($winner));httpCheck($winner->status===422,'Nova extra após excluir precisa de novo amigo');
 for($n=2;$n<=5;$n++){$f=client();registerFixture($f,'friend-'.$n,$invite);$f->request('/carreira/nova',creation($f));httpCheck($f->status===200,'Novo amigo iniciou carreira');if($n<5){$winner->request('/carreira/nova',creation($winner));httpCheck($winner->status===200,'Novo crédito abre uma carreira extra');}}
 $winner->request('/carreira/nova',creation($winner));httpCheck($winner->status===422,'Teto gratuito de cinco respeitado mesmo com saldo');
 httpCheck((int)sqlh("SELECT COUNT(*) FROM rh_creditos_indicacao WHERE usuario_id=? AND estado='disponivel'",[$uid])->fetchColumn()===1,'Saldo não é consumido quando o limite impede criação');
 $adminEmail=$tag.'-admin@example.invalid';sqlh('INSERT INTO rh_admins(email,senha_hash) VALUES(?,?)',[$adminEmail,password_hash($pass,PASSWORD_DEFAULT)]);$admin=(int)$db->lastInsertId();sqlh('INSERT INTO rh_usuarios(email,senha_hash,admin_hub) VALUES(?,?,1)',[$adminEmail,password_hash($pass,PASSWORD_DEFAULT)]);$users[]=(int)$db->lastInsertId();
 $hub=new PackageClient('http://rumohub.local');$clients[]=$hub;$hub->request('/admin/login');$hub->request('/admin/login',['csrf'=>$hub->csrf(),'email'=>$adminEmail,'password'=>$pass]);$hub->request('/admin/users?user='.$uid);httpCheck($hub->status===200 && str_contains($hub->body,'Conta selecionada'),'Painel de conta verificada funciona no IIS');
 $grant=['csrf'=>$hub->csrf(),'user'=>$uid,'action'=>'grant','key'=>$hub->field('key'),'games'=>['rumo10'],'reason'=>'Teste de concessão por jogo','confirm_user'=>'yes'];$hub->request('/admin/benefit',$grant);httpCheck($hub->status===200,'Cortesia via painel autenticado');
 $winner->request('/carreira/nova',creation($winner));httpCheck($winner->status===200,'Cortesia permite carreira além das cinco gratuitas');
 httpCheck((int)sqlh("SELECT COUNT(*) FROM rh_creditos_indicacao WHERE usuario_id=? AND estado='disponivel'",[$uid])->fetchColumn()===1,'Cortesia não gasta saldo de indicações');
 $benefit=(int)sqlh("SELECT id FROM rh_beneficios_jogo WHERE usuario_id=? AND tipo='cortesia'",[$uid])->fetchColumn();
 putenv('HUB_BENEFITS_ENABLED=1');HubBenefits::revoke($db,$admin,$benefit,'Finalização da fixture HTTP',bin2hex(random_bytes(32)),'revogar');
 $winner->request('/carreira/nova',creation($winner));httpCheck($winner->status===422,'Revogação bloqueia criação e preserva excedentes');
 httpCheck((int)sqlh('SELECT COUNT(*) FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id WHERE c.conta_id=? OR a.conta_id=?',[$aid,$aid])->fetchColumn()===6,'Nenhuma carreira removida pela revogação');
 $winner->request('/classificacao');httpCheck($winner->status===200 && str_contains($winner->body,'Voltar à carreira'),'CTA na tabela em início de carreira');$winner->request('/carreira');httpCheck($winner->status===200,'Retorno em GET restaura a carreira');
 echo "OK: $checks verificações HTTP reais no IIS, concorrência entre duas sessões, créditos, limites, cortesia e revogação.\n";
}finally{
 if($db->inTransaction())$db->rollBack();
 foreach($accounts as $id){$r=new Repository($db);foreach(sqlh('SELECT c.id FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id WHERE c.conta_id=? OR a.conta_id=?',[$id,$id])->fetchAll(PDO::FETCH_COLUMN) as $cid){CareerSlots::purgeDetails($r,(int)$cid);sqlh('DELETE FROM rumo10_carreiras WHERE id=?',[$cid]);}sqlh('DELETE FROM rumo10_contas WHERE id=?',[$id]);}
 foreach($users as $id){sqlh('DELETE a FROM rh_beneficios_audit a JOIN rh_beneficios_jogo b ON b.id=a.beneficio_id WHERE b.usuario_id=?',[$id]);sqlh('DELETE FROM rh_beneficios_jogo WHERE usuario_id=?',[$id]);sqlh('DELETE FROM rh_indicacoes_ledger WHERE usuario_id=?',[$id]);sqlh('DELETE FROM rh_creditos_indicacao WHERE usuario_id=?',[$id]);}
 foreach($users as $id){sqlh('DELETE FROM rh_indicacoes WHERE convidante_id=? OR convidado_id=?',[$id,$id]);sqlh('DELETE FROM rh_convites WHERE usuario_id=?',[$id]);sqlh('DELETE FROM rh_primeiras_carreiras WHERE usuario_id=?',[$id]);sqlh('DELETE FROM rh_perfis_publicos WHERE usuario_id=?',[$id]);sqlh('DELETE FROM rh_usuarios WHERE id=?',[$id]);}
 if($admin)sqlh('DELETE FROM rh_admins WHERE id=?',[$admin]);foreach($clients as $c)if(is_file($c->jar))unlink($c->jar);
}
