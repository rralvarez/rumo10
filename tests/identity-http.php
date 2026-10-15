<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';if(config('APP_ENV','local')!=='local' || !Identity::enabled())throw new RuntimeException('Teste requer identidades no ambiente local.');
$email='shared-'.bin2hex(random_bytes(10)).'@example.invalid';$password=bin2hex(random_bytes(12));$cookies=['hub'=>tempnam(sys_get_temp_dir(),'rh-'),'game'=>tempnam(sys_get_temp_dir(),'r10-')];$db=Database::connect();$uid=null;$account=null;$checks=0;
function sharedCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function sharedRequest(string $site,string $path,?array $body=null):array{global $cookies;$base=$site==='hub'?'http://rumohub.local':'http://rumo10.local';$c=curl_init($base.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookies[$site],CURLOPT_COOKIEJAR=>$cookies[$site],CURLOPT_TIMEOUT=>15]);if($body!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($body)]);$text=curl_exec($c);if($text===false)throw new RuntimeException('HTTP indisponível.');return ['status'=>curl_getinfo($c,CURLINFO_RESPONSE_CODE),'body'=>$text];}
function sharedCsrf(array $r):string{if(!preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m))throw new RuntimeException('CSRF ausente.');return $m[1];}
try{
 sharedCheck(sharedRequest('hub','/conta')['status']===303,'Minha conta redireciona antes de enviar HTML');
 $page=sharedRequest('hub','/conta/cadastro');$r=sharedRequest('hub','/conta/cadastro',['csrf'=>sharedCsrf($page),'email'=>$email,'password'=>$password,'admin'=>1,'plan'=>'pago']);sharedCheck($r['status']===303,'Cadastro pessoal no Hub');
 $q=$db->prepare('SELECT id,admin_hub,plano FROM rh_usuarios WHERE email=?');$q->execute([$email]);$u=$q->fetch();$uid=$u['id'];sharedCheck(!$u['admin_hub'] && $u['plano']==='gratuito','Formulário forjado não concede permissão ou plano');
 $page=sharedRequest('hub','/conta');sharedCheck($page['status']===200 && str_contains($page['body'],$email),'Conta Hub autenticada');sharedCheck(sharedRequest('hub','/admin')['status']===303,'Conta comum não entra no painel');
 $page=sharedRequest('game','/login');$r=sharedRequest('game','/login',['csrf'=>sharedCsrf($page),'email'=>$email,'password'=>$password]);sharedCheck($r['status']===303,'Mesmo e-mail e senha entram no jogo');
 $q=$db->prepare('SELECT conta_id FROM rumo10_identidades WHERE hub_usuario_id=?');$q->execute([$uid]);$account=$q->fetchColumn();sharedCheck((bool)$account,'Vínculo local usa identidade central');
 $page=sharedRequest('game','/carreira');sharedCheck($page['status']===200 && str_contains($page['body'],'Crie seu jogador'),'Acesso ao jogo após login central');
 sharedCheck(sharedRequest('game','/logout',['csrf'=>sharedCsrf($page)])['status']===303,'Saída do jogo');sharedCheck(sharedRequest('hub','/conta')['status']===200,'Sessões dos sites permanecem isoladas');
 $page=sharedRequest('game','/register');$r=sharedRequest('game','/register',['csrf'=>sharedCsrf($page),'email'=>$email,'password'=>$password]);sharedCheck($r['status']===422,'Não duplica usuário entre sites');
 echo "OK: $checks verificações HTTP de conta compartilhada entre Hub e Rumo10.\n";
}finally{
 if($account)$db->prepare('DELETE FROM rumo10_contas WHERE id=? AND email=?')->execute([$account,$email]);if($uid)$db->prepare('DELETE FROM rh_usuarios WHERE id=? AND email=?')->execute([$uid,$email]);foreach($cookies as $cookie)if(is_file($cookie))unlink($cookie);
}
