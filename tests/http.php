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
    verify(str_contains($page['body'],'href="/register"') && !str_contains($page['body'],'formaction="/register"'),'Criar conta navega sem validar o login');
    $page=request('/register'); verify($page['status']===200 && str_contains($page['body'],'Crie sua conta'),'Tela própria de cadastro');
    $t=tokens($page['body'],'/register');
    $invalid=request('/register',$t+['email'=>$email,'password'=>'abc']);
    verify($invalid['status']===422 && str_contains($invalid['body'],'action="/register"') && str_contains($invalid['body'],$email),'Erro mantém cadastro e e-mail');
    $page=request('/register',$t+['email'=>$email,'password'=>$password]); verify($page['status']===200,'Registro/login HTTP');
    $page=request('/carreira/criar',tokens($page['body'],'/carreira/criar')+['name'=>'Teste HTTP','city'=>'São Paulo','uf'=>'SP','position'=>'meia']); verify($page['status']===200,'Criação HTTP');
    $t=tokens($page['body'],'/carreira/handicap'); $page=request('/carreira/handicap',$t); verify($page['status']===200,'Handicap HTTP');
    $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['stage']==='clube_inicio' && count($state['initial_candidates'])===3,'Três clubes após roleta');
    $page=request('/carreira/handicap',$t); $replay=json_decode(request('/api/carreira/estado')['body'],true); verify($state===$replay,'Replay HTTP idempotente');
    verify(request('/classificacao')['status']===200,'Tabela antes de escolher clube');
    $page=request('/carreira/clube-inicial',tokens($page['body'],'/carreira/clube-inicial')+['option'=>'0']); verify($page['status']===200,'Escolha do primeiro clube');
    $joined=json_decode(request('/api/carreira/estado')['body'],true);verify($joined['club']===$state['initial_candidates'][0]['club'] && $joined['stage']==='treino','Clube oferecido salvo');
    $profile=request('/jogador');verify($profile['status']===200 && str_contains($profile['body'],'NÍVEL TÉCNICO') && str_contains($profile['body'],'Habilidades e evolução'),'Ficha do jogador autenticada');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$joined,'Abrir ficha não modifica carreira nem sorteia situação');
    $squad=request('/elenco');verify($squad['status']===200 && str_contains($squad['body'],'Companheiros de elenco') && str_contains($squad['body'],$joined['club']),'Elenco do próprio clube autenticado');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$joined,'Abrir elenco não altera estado');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); verify($page['status']===200,'Treino HTTP');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); verify($page['status']===200,'Pré-jogo HTTP');
    verify(str_contains($page['body'],'data-stat="goals">0'),'Contador de gols inicia na partida');
    for($i=0;$i<15;$i++) {
        $state=json_decode(request('/api/carreira/estado')['body'],true); if($state['stage']!=='partida') break;
        $path=str_contains($page['body'],'action="/partida/avancar"')?'/partida/avancar':'/partida/decidir';
        $page=request($path,tokens($page['body'],$path)+($path==='/partida/decidir'?['option'=>'1']:[])); verify($page['status']===200,'Partida HTTP');
    }
    $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['stage']==='fim_semana','Partida finalizada HTTP');
    $match=$state['matches'][Game::currentIndex($state)];
    verify(str_contains($page['body'],'data-stat="goals">'.$match['stats']['goals']), 'Gols mostrados correspondem ao banco');
    verify(str_contains($page['body'],'data-stat="season-goals">'.Game::summary($state)['goals']), 'Histórico mostra total persistido do campeonato');
    $page=request('/decisao/registrar',tokens($page['body'],'/decisao/registrar')+['option'=>'1']); $state=json_decode(request('/api/carreira/estado')['body'],true); verify($state['round']===2 && $state['stage']==='treino','Rodada completa HTTP');
    $page=request('/classificacao'); verify($page['status']===200 && str_contains($page['body'],'Classificação'),'Tabela HTTP');
    $page=request('/carreira'); $t=tokens($page['body'],'/logout'); request('/logout',$t);
    verify(request('/api/carreira/estado')['status']===401,'API exige login');
    $page=request('/login'); $page=request('/login',tokens($page['body'],'/login')+['email'=>$email,'password'=>$password]);
    $reload=json_decode(request('/api/carreira/estado')['body'],true); verify($reload===$state,'Persistência após novo login');
    $invalid=request('/decisao/registrar',['csrf'=>'invalid','option'=>'1']); verify($invalid['status']===403,'CSRF inválido bloqueado');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'CSRF não muda estado');
    verify(!empty($state['official']) && in_array($state['competition_id'],['sp-a3','sp-a4'],true),'Nova conta usa competição oficial');
    $page=request('/carreira/nova'); verify($page['status']===200 && !str_contains($page['body'],'name="club"'),'Criação sem seletor de clubes');
    $cities=json_decode(request('/api/cidades?uf=SE')['body'],true);verify(in_array('Aracaju',$cities,true),'Sugestões de municípios por UF');
    verify(request('/api/cidades?uf=ZZ')['status']===422,'UF inválida rejeitada');
    $invalid=request('/carreira/nova',tokens($page['body'],'/carreira/nova')+['name'=>'Outra Teste HTTP','city'=>'Cidade inventada','uf'=>'SP','position'=>'meia']);
    verify($invalid['status']===422 && str_contains($invalid['body'],'Cidade inventada'),'Município inválido mantém formulário');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'Erro de município conserva carreira atual');
    $page=request('/carreira/nova',tokens($page['body'],'/carreira/nova')+['name'=>'Outra Teste HTTP','city'=>'Santos','uf'=>'SP','position'=>'meia','club'=>'Jabaquara-SP','mode'=>'expresso']);
    $newState=json_decode(request('/api/carreira/estado')['body'],true); verify($page['status']===200 && $newState['id']!==$state['id'],'Nova carreira HTTP');
    verify(PlayMode::name($newState)==='expresso','Modo selecionado no cadastro salvo');
    verify($newState['starting'] && $newState['club']==='Sem clube','Clube enviado no cadastro é ignorado');
    $page=request('/carreira/arquivadas'); verify($page['status']===200 && str_contains($page['body'],'Retomar esta carreira'),'Arquivo HTTP');
    $page=request('/carreira/restaurar',tokens($page['body'],'/carreira/restaurar')+['career'=>$state['id']]);
    verify($page['status']===200 && json_decode(request('/api/carreira/estado')['body'],true)===$state,'Restauração HTTP preserva campanha');
    $page=request('/carreira');
    $page=request('/carreira/modo',tokens($page['body'],'/carreira/modo')+['mode'=>'rapido']);
    $state=json_decode(request('/api/carreira/estado')['body'],true);
    verify($page['status']===200 && PlayMode::name($state)==='rapido' && str_contains($page['body'],'PRÓXIMA PARTIDA'),'Troca para rápido HTTP');
    $before=$state;$t=tokens($page['body'],'/carreira/simular');
    $invalid=request('/carreira/simular',$t+['plan'=>'invalid']);
    verify($invalid['status']===422 && json_decode(request('/api/carreira/estado')['body'],true)===$before,'Estratégia inválida não altera carreira');
    $page=request('/carreira/simular',$t+['plan'=>'coletivo']);
    $state=json_decode(request('/api/carreira/estado')['body'],true);
    verify($page['status']===200 && count($state['pace_report']['matches'])===1 && $state['version']===$before['version']+1,'Jogo rápido persiste resultado');
    request('/carreira/simular',$t+['plan'=>'coletivo']);
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'Replay rápido não simula novamente');
    $invalid=request('/carreira/simular',$t+['plan'=>'equilibrio']);
    verify($invalid['status']===422,'Token reaproveitado com outra estratégia rejeitado');
    if($state['stage']==='competicao_resumo')$page=request('/competicao/continuar',tokens($page['body'],'/competicao/continuar'));
    else $page=request('/carreira');
    $page=request('/carreira/modo',tokens($page['body'],'/carreira/modo')+['mode'=>'expresso']);
    $t=tokens($page['body'],'/carreira/simular');$page=request('/carreira/simular',$t+['plan'=>'evolucao']);
    $state=json_decode(request('/api/carreira/estado')['body'],true);
    verify($page['status']===200 && $state['stage']==='resumo' && $state['season']===1,'Expresso conclui calendário pelo IIS');
    verify(str_contains($page['body'],'Ver minha proposta'),'Contrato permanece escolha explícita');
    request('/carreira/simular',$t+['plan'=>'evolucao']);
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'Replay expresso preserva temporada');
    $page=request('/carreira');$page=request('/transferencia/roleta',tokens($page['body'],'/transferencia/roleta'));
    $page=request('/transferencia/responder',tokens($page['body'],'/transferencia/responder')+['accept'=>'no']);
    $state=json_decode(request('/api/carreira/estado')['body'],true);
    verify($page['status']===200 && $state['season']===2 && PlayMode::name($state)==='expresso' && !isset($state['pace_report']),'Nova temporada conserva modo e limpa resumo');
    $profile=request('/jogador');$careerTotals=CareerStats::collect($state)['totals'];
    verify($profile['status']===200 && str_contains($profile['body'],'data-career-stat="goals">'.$careerTotals['goals']) && str_contains($profile['body'],'data-career-stat="assists">'.$careerTotals['assists']),'Histórico geral exibido após contrato');
    verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'Abrir histórico não altera carreira');
    // Apenas a conta descartável desta execução recebe a idade sintética de teste.
    $owner=$r->query('SELECT c.conta_id FROM rumo10_carreiras c JOIN rumo10_contas a ON a.id=c.conta_id WHERE c.id=? AND a.email=?',[$state['id'],$email])->fetchColumn();
    verify((bool)$owner,'Fixture pertence à conta de teste');
    $r->query('UPDATE rumo10_universos SET estado_json=JSON_SET(estado_json,\'$.age\',35) WHERE carreira_id=?',[$state['id']]);
    $state=json_decode(request('/api/carreira/estado')['body'],true);$totals=CareerStats::collect($state)['totals'];
    $page=request('/carreira/aposentar');verify($page['status']===200 && str_contains($page['body'],'Confirmar aposentadoria'),'Confirmação disponível pelo site aos 35');
    $t=tokens($page['body'],'/carreira/aposentar');$invalid=request('/carreira/aposentar',$t);verify($invalid['status']===422,'Não aposenta sem confirmação');$slotsBefore=CareerSlots::quota($r,(int)$owner)['used'];
    $page=request('/carreira/aposentar',$t+['confirm'=>'retire']);$state=json_decode(request('/api/carreira/estado')['body'],true);
    verify($page['status']===200 && $state['stage']==='aposentado' && $state['retirement']['reason']==='voluntary','Aposentadoria persistida pelo IIS');
    verify(CareerStats::collect($state)['totals']===$totals,'Aposentadoria mantém gols/assistências sem duplicação');
    request('/carreira/aposentar',$t+['confirm'=>'retire']);verify(json_decode(request('/api/carreira/estado')['body'],true)===$state,'Replay da aposentadoria não altera histórico');
    verify(isset($state['retired_summary']) && !isset($state['memberships']) && !$state['matches'],'API aposentada contém somente resumo');
    verify((int)$r->query('SELECT COUNT(*) FROM rumo10_universos WHERE carreira_id=?',[$state['id']])->fetchColumn()===0,'Aposentadoria remove universo detalhado');
    verify(CareerSlots::quota($r,(int)$owner)['used']===$slotsBefore-1,'Aposentadoria libera vaga pelo IIS');
    $summaryPage=request('/carreira/resumo?career='.$state['id']);verify($summaryPage['status']===200 && str_contains($summaryPage['body'],'Resumo permanente') && str_contains($summaryPage['body'],'data-career-stat="goals">'.$totals['goals']),'Histórico compacto acessível pelo site');
    verify(request('/carreira/resumo?career=0')['status']===422,'Resumo de ID não autorizado rejeitado');
    $retiredProfile=request('/jogador');verify($retiredProfile['status']===200 && str_contains($retiredProfile['body'],'Resumo permanente'),'Ficha aposentada não exige atributos apagados');
    $manager=request('/carreiras');verify($manager['status']===200 && str_contains($manager['body'],'Minhas carreiras'),'Gerenciar carreiras pelo site');
    $confirmation=request('/carreira/excluir?career='.$state['id']);verify($confirmation['status']===200 && str_contains($confirmation['body'],'Excluir permanentemente'),'Confirmação explícita de exclusão');
    $deleteTokens=tokens($confirmation['body'],'/carreira/excluir');
    $invalid=request('/carreira/excluir',$deleteTokens+['career'=>$state['id']]);verify($invalid['status']===422 && json_decode(request('/api/carreira/estado')['body'],true)===$state,'Não exclui sem confirmação');
    $invalid=request('/carreira/excluir',array_replace($deleteTokens,['career'=>'0','confirm'=>'delete']));verify($invalid['status']===422,'Não exclui ID não pertencente à conta');
    $deleted=request('/carreira/excluir',$deleteTokens+['career'=>$state['id'],'confirm'=>'delete']);verify($deleted['status']===200 && str_contains($deleted['body'],'Minhas carreiras'),'Exclusão retorna à lista');
    verify((int)$r->query('SELECT COUNT(*) FROM rumo10_universos WHERE carreira_id=?',[$state['id']])->fetchColumn()===0 && json_decode(request('/api/carreira/estado')['body'],true)===null,'Exclusão apaga universo e libera selecionada');
    echo "OK: $checks verificações HTTP em $base — conta, rodada, replay, refresh, tabela, logout/login e CSRF.\n";
} finally {
    // Cleanup estritamente da conta descartável criada nesta execução.
    $account=$r->query('SELECT id FROM rumo10_contas WHERE email=?',[$email])->fetchColumn();
    if($account) {
        $ids=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=? UNION SELECT carreira_id FROM rumo10_carreiras_arquivadas WHERE conta_id=?',[$account,$account])->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id) {
            $r->query('DELETE FROM rumo10_carreiras_arquivadas WHERE carreira_id=? AND conta_id=?',[$id,$account]);
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
