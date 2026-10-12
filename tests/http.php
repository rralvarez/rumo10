<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$base=rtrim($argv[1]??'http://rumo10.local','/');
if(!in_array(parse_url($base,PHP_URL_HOST),['rumo10.local','127.0.0.1','localhost'],true)) throw new RuntimeException('Teste de cadastro permitido apenas localmente.');
$cookie=tempnam(sys_get_temp_dir(),'rumo10-http-'); $email='http-'.bin2hex(random_bytes(8)).'@example.invalid'; $password=bin2hex(random_bytes(16));
$checks=0;
function verify(bool $v,string $message): void { global $checks; $checks++; if(!$v) throw new RuntimeException($message); }
function request(string $path,?array $body=null): array {
    global $base,$cookie;
    $c=curl_init($base.$path); $headers='';
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>20,CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$headers.=$line;return strlen($line);}]);
    if($body!==null) curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($body)]);
    $text=curl_exec($c); if($text===false) throw new RuntimeException('Requisição local falhou.'); $code=curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c);
    return ['status'=>$code,'body'=>$text,'headers'=>$headers];
}
function tokens(string $html,string $path): array {
    if(!preg_match('#<form method="post" action="'.preg_quote($path,'#').'">(.*?)</form>#s',$html,$m)) throw new RuntimeException('Formulário não encontrado: '.$path);
    $out=[]; preg_match_all('/<input type="hidden" name="([a-z]+)" value="([^"]*)">/',$m[1],$matches,PREG_SET_ORDER);
    foreach($matches as $i) $out[$i[1]]=html_entity_decode($i[2],ENT_QUOTES,'UTF-8'); return $out;
}
$r=new Repository(Database::connect());
try {
    $page=request('/login'); verify($page['status']===200,'Tela login');
    verify(str_contains(strtolower($page['headers']),'httponly') && str_contains(strtolower($page['headers']),'samesite=lax'),'Flags de cookie');
    $t=tokens($page['body'],'/login');
    $page=request('/register',$t+['email'=>$email,'password'=>$password]); verify($page['status']===200,'Registro/login HTTP');
    $page=request('/carreira/criar',tokens($page['body'],'/carreira/criar')+['name'=>'Teste HTTP','city'=>'São Paulo','uf'=>'SP','position'=>'meia']); verify($page['status']===200,'Criação HTTP');
    $t=tokens($page['body'],'/carreira/handicap'); $page=request('/carreira/handicap',$t); verify($page['status']===200,'Handicap HTTP');
    $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['stage']==='treino','Etapa treino');
    $page=request('/carreira/handicap',$t); $replay=json_decode(request('/api/carreira/estado')['body'],true); verify($state===$replay,'Replay HTTP idempotente');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); verify($page['status']===200,'Treino HTTP');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); verify($page['status']===200,'Pré-jogo HTTP');
    for($i=0;$i<15;$i++) {
        $state=json_decode(request('/api/carreira/estado')['body'],true); if($state['stage']!=='partida') break;
        $path=str_contains($page['body'],'action="/partida/avancar"')?'/partida/avancar':'/partida/decidir';
        $page=request($path,tokens($page['body'],$path)+($path==='/partida/decidir'?['option'=>'1']:[])); verify($page['status']===200,'Partida HTTP');
    }
    $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['stage']==='fim_semana','Partida finalizada HTTP');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['round']===2 && $state['stage']==='treino','Rodada completa HTTP');
    $page=request('/classificacao'); verify($page['status']===200 && str_contains($page['body'],'Classificação'),'Tabela HTTP');
    $page=request('/carreira'); $t=tokens($page['body'],'/logout'); request('/logout',$t);
    verify(request('/api/carreira/estado')['status']===401,'API exige login');
    $page=request('/login'); $page=request('/login',tokens($page['body'],'/login')+['email'=>$email,'password'=>$password]);
    $reload=json_decode(request('/api/carreira/estado')['body'],true); verify($reload===$state,'Persistência após novo login');
    $invalid=request('/decisao/registrar',['csrf'=>'invalid','option'=>'1']); verify($invalid['status']===403,'CSRF inválido bloqueado');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'CSRF não muda estado');
    echo "OK: $checks verificações HTTP em $base — conta, rodada, replay, refresh, tabela, logout/login e CSRF.\n";
} finally {
    // Cleanup estritamente da conta descartável criada nesta execução.
    $account=$r->query('SELECT id FROM rumo10_contas WHERE email=?',[$email])->fetchColumn();
    if($account) {
        $id=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=?',[$account])->fetchColumn();
        if($id) {
            foreach(['rumo10_eventos_partida','rumo10_estatisticas'] as $table) $r->query("DELETE x FROM $table x JOIN rumo10_partidas p ON p.id=x.partida_id JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?",[$id]);
            $r->query('DELETE p FROM rumo10_partidas p JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?',[$id]);
            foreach(['rumo10_times','rumo10_ofertas'] as $table) $r->query("DELETE x FROM $table x JOIN rumo10_temporadas t ON t.id=x.temporada_id WHERE t.carreira_id=?",[$id]);
            foreach(['rumo10_temporadas','rumo10_decisoes','rumo10_atributos','rumo10_estados','rumo10_transferencias'] as $table) $r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
            $r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
        }
        $r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);
    }
    $r->query('DELETE FROM rumo10_login_limits WHERE fingerprint=?',[hash('sha256',gethostbyname('rumo10.local').'|'.$email)]);
    if(is_file($cookie)) unlink($cookie);
}
