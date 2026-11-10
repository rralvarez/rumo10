<?php
declare(strict_types=1);
if(is_file(dirname(__DIR__).'/storage/database-migration.lock')){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');header('Retry-After: 60');exit('Estamos atualizando o banco do jogo. Recarregue em instantes; sua carreira será preservada.');}
require dirname(__DIR__).'/app/bootstrap.php';
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('zend.exception_ignore_args','1');
ini_set('error_log',dirname(__DIR__).'/storage/logs/php.log');
session_save_path(dirname(__DIR__).'/storage/sessions');
$production=config('APP_ENV','local')==='production';
$https=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
if($production && !$https) { http_response_code(503); exit('HTTPS deve ser configurado no servidor antes de acessar a produção.'); }
header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin'); header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store'); if($production) header('Strict-Transport-Security: max-age=31536000');
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
session_name('rumo10_session'); session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']); session_start();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
function redirect(string $path): never { RequestMetrics::headers();header('Location: '.$path,true,303); exit; }
function form(string $path,?array $g=null): void {
    echo '<form method="post" action="'.e($path).'"><input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
    if($g) echo '<input type="hidden" name="version" value="'.e($g['version']).'"><input type="hidden" name="key" value="'.bin2hex(random_bytes(32)).'">';
}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$method=$_SERVER['REQUEST_METHOD'];

if(str_starts_with($path, '/admin')) {
    $action = match($path) {
        '/admin/login' => 'login',
        '/admin/logout' => 'logout',
        '/admin/manutencao' => 'maintenance_toggle',
        '/admin/balance_save' => 'balance_save',
        '/admin/balance_activate' => 'balance_activate',
        '/admin/balance_rollback' => 'balance_rollback',
        '/admin/simulacao' => 'simulate',
        default => 'dashboard',
    };
    AdminController::handle($action);
    exit;
}

Maintenance::check();

$routes=['/api/v1/telemetry/drain'=>['POST'],'/api/v1/command/response'=>['GET'],'/feedback'=>['GET','POST'],'/conquistas/reconciliar'=>['POST'],'/conquistas'=>['GET'],'/jogador/localizacao'=>['GET','POST'],'/convite'=>['GET'],'/convites'=>['GET'],'/carreira/trofeus'=>['GET'],'/carreira/resumo'=>['GET'],'/carreira/compactar'=>['GET','POST'],'/carreiras'=>['GET'],'/carreira/excluir'=>['GET','POST'],'/carreira/aposentar'=>['GET','POST'],'/elenco'=>['GET'],'/carreira/simular'=>['POST'],'/jogador'=>['GET'],'/'=>['GET'],'/login'=>['GET','POST'],'/register'=>['GET','POST'],'/logout'=>['POST'],'/carreira'=>['GET'],'/carreira/nova'=>['GET','POST'],'/carreira/arquivadas'=>['GET'],'/carreira/restaurar'=>['POST'],'/competicao/continuar'=>['POST'],'/carreira/criar'=>['POST'],'/carreira/clube-inicial'=>['POST'],'/carreira/handicap'=>['POST'],'/semana'=>['GET'],'/decisao/registrar'=>['POST'],'/partida'=>['GET'],'/partida/avancar'=>['POST'],'/partida/decidir'=>['POST'],'/penaltis/iniciar'=>['POST'],'/penaltis/decidir'=>['POST'],'/penaltis/avancar'=>['POST'],'/penaltis/concluir'=>['POST'],'/classificacao'=>['GET'],'/temporada/resumo'=>['GET'],'/transferencia/roleta'=>['POST'],'/transferencia/responder'=>['POST'],'/api/carreira/estado'=>['GET'],'/api/cidades'=>['GET']];
$status=200; $error=null; $g=null; $clubs=[];
try {
    Identity::verify();
    if($path==='/escudo' && $method==='GET' && RumoHub::enabled()) {
        if(!isset($_SESSION['account'])){http_response_code(401);exit;}
        $club=$_GET['club']??'';if(!is_string($club) || strlen($club)>400){http_response_code(422);exit;}
        session_write_close();try{RumoHub::badge($club);}catch(Throwable $badgeError){http_response_code(503);error_log('Escudo Hub indisponível: '.$badgeError::class);exit;}
    }
    if(!isset($routes[$path])) { $status=404; throw new InvalidArgumentException('Página não encontrada.'); }
    if(!in_array($method,$routes[$path],true)) { $status=405; header('Allow: '.implode(', ',$routes[$path])); throw new InvalidArgumentException('Método não permitido.'); }
    $public=in_array($path,['/','/login','/register','/convite'],true);
    if(!$public && !isset($_SESSION['account'])) { 
        if(str_starts_with($path,'/api/')) { 
            http_response_code(401); 
            header('Content-Type: application/json; charset=utf-8'); 
            echo json_encode(['error' => 'Entre na sua conta.'], JSON_UNESCAPED_UNICODE); 
            exit; 
        } 
        redirect('/login'); 
    }
    if($method==='POST' && (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf']??'',$_POST['csrf']))) { 
        http_response_code(403); 
        if(str_starts_with($path,'/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Sessão de formulário inválida. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $status=403; throw new InvalidArgumentException('Sessão de formulário inválida. Recarregue a página.'); 
    }
    if($method==='POST' && in_array($path,['/login','/register'],true)) {
        $r=new Repository(Database::connect());
        $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<8 || strlen($password)>72) throw new InvalidArgumentException('Use um e-mail válido e senha entre 8 e 72 caracteres.');
        if(Identity::enabled()){$account=Identity::login($r,$email,$password,$path==='/register');session_regenerate_id(true);$_SESSION['account']=$account;$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('/carreira');}
        $fp=hash('sha256',($_SERVER['REMOTE_ADDR']??'local').'|'.$email); $now=time();
        $r->query('INSERT INTO rumo10_login_limits VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start<?,1,attempts+1),window_start=IF(window_start<?,?,window_start)',[$fp,$now,$now-900,$now-900,$now]);
        if((int)$r->query('SELECT attempts FROM rumo10_login_limits WHERE fingerprint=?',[$fp])->fetchColumn()>10) { $status=429; throw new InvalidArgumentException('Muitas tentativas. Aguarde 15 minutos.'); }
        if($path==='/register') {
            try { $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]); } catch(PDOException $ex) { if($ex->getCode()==='23000') throw new InvalidArgumentException('Não foi possível registrar esse e-mail.'); throw $ex; }
        }
        $a=$r->query('SELECT id,senha_hash FROM rumo10_contas WHERE email=?',[$email])->fetch();
        if(!$a || !password_verify($password,$a['senha_hash'])) throw new InvalidArgumentException('E-mail ou senha incorretos.');
        session_regenerate_id(true); $_SESSION['account']=(int)$a['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('/carreira');
    }
    if($path==='/logout') { Identity::logout(); $_SESSION=[]; session_destroy(); redirect('/'); }
    if($path==='/api/cidades') {
        $uf=strtoupper((string)($_GET['uf']??'SP'));$cities=array_values(array_column(array_filter(Onboarding::municipalities(),fn($m)=>$m['uf']===$uf),'name'));
        if(!$cities)throw new InvalidArgumentException('UF inválida.');
        header('Content-Type: application/json; charset=utf-8');echo json_encode($cities,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    if(!$public) {
        $r=new Repository(Database::connect());
        if(!in_array($path,['/feedback','/conquistas','/conquistas/reconciliar','/api/v1/command/response'],true)&&CareerBenefits::enabled())CareerBenefits::flush($r,$_SESSION['account']);
        $careerPlan=in_array($path,['/feedback','/conquistas','/conquistas/reconciliar','/api/v1/command/response'],true)?'gratuito':CareerSlots::quota($r,$_SESSION['account'])['plan'];
        if(!in_array($path,['/feedback','/conquistas/reconciliar','/conquistas','/carreiras','/carreira/excluir','/carreira/resumo','/carreira/compactar','/api/v1/command/response'],true))$g=$r->load($_SESSION['account']);
        if($path==='/conquistas'){$reconciliation=AchievementReconciliation::run($r,$_SESSION['account']);if($reconciliation['recognized'])$_SESSION['historical_achievements']=(int)($_SESSION['historical_achievements']??0)+count($reconciliation['recognized']);}
        if($method==='POST') {
            if($path==='/api/v1/telemetry/drain') {
                if(!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf']??'', (string)$_POST['csrf'])) {
                    http_response_code(403);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error'=>'CSRF inválido ou ausente.'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $careerId = isset($_POST['career_id']) ? (int)$_POST['career_id'] : ($g['id'] ?? 0);
                if($careerId <= 0) {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error'=>'career_id obrigatório e inválido.'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $limit = isset($_POST['limit']) ? min((int)$_POST['limit'], F01Drainer::MAX_BATCH_SIZE) : F01Drainer::DEFAULT_BATCH_SIZE;
                $res = F01Drainer::drain($r, (int)$_SESSION['account'], $careerId, $limit);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status'=>'ok','career_id'=>$careerId,'processed'=>$res['processed'],'failed'=>$res['failed']], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                exit;
            }
            if($path==='/feedback'){GameFeedback::submit($r,$_SESSION['account'],$_POST);$_SESSION['feedback_received']=true;redirect('/feedback');}
            if($path==='/conquistas/reconciliar'){$result=AchievementReconciliation::run($r,$_SESSION['account']);if($result['recognized'])$_SESSION['historical_achievements']=(int)($_SESSION['historical_achievements']??0)+count($result['recognized']);redirect('/conquistas');}
            if($path==='/carreira/compactar'){RetiredCareer::compactExisting($r,$_SESSION['account'],(int)($_POST['career']??0),(string)($_POST['confirm']??''));redirect('/carreira/resumo?career='.(int)$_POST['career']);}
            if($path==='/carreira/excluir'){CareerSlots::delete($r,$_SESSION['account'],(int)($_POST['career']??0),(string)($_POST['confirm']??''));redirect('/carreiras');}
            if(in_array($path,['/carreira/criar','/carreira/nova'],true)) {
                $newGame=EntryCountries::create(trim((string)($_POST['name']??'')),trim((string)($_POST['city']??'')),strtoupper((string)($_POST['uf']??'')),(string)($_POST['position']??''),(string)($_POST['country']??'BR'),$careerPlan,(string)($_POST['residence_country']??'BR'),trim((string)($_POST['residence_region']??'')),(string)($_POST['entry_uf']??'SP'));
                $newGame['play_mode']=PlayMode::validate((string)($_POST['mode']??'normal'));
                $r->create($_SESSION['account'],$newGame,$path==='/carreira/nova',CareerBenefits::enabled()?(string)($_POST['creation_key']??''):null);
            } elseif($path==='/carreira/restaurar') {
                $r->restore($_SESSION['account'],(int)($_POST['career']??0));
            } else {
                $action=match($path) {'/jogador/localizacao'=>'relocate','/carreira/aposentar'=>'retire','/carreira/simular'=>'simulate','/carreira/clube-inicial'=>'join','/competicao/continuar'=>'continue','/carreira/handicap'=>'handicap','/penaltis/iniciar'=>'shootout_start','/penaltis/concluir'=>'shootout_finish','/penaltis/avancar'=>'shootout_advance','/decisao/registrar','/partida/decidir','/penaltis/decidir'=>'choice','/partida/avancar'=>'advance','/transferencia/roleta'=>'offers','/transferencia/responder'=>'transfer',default=>throw new InvalidArgumentException('Ação inválida.')};
                $input=in_array($action,['choice','join'],true)?['option'=>(string)($_POST['option']??'')]:($action==='transfer'?['accept'=>(string)($_POST['accept']??'')]:($action==='shootout_advance'?['mode'=>(string)($_POST['mode']??'one')]:[]));
                if($action==='transfer'&&isset($_POST['option']))$input['option']=(string)$_POST['option'];
                if($action==='relocate')$input=array_intersect_key($_POST,array_flip(['city','uf','residence_country','residence_region']));
                if($action==='retire')$input=['confirm'=>(string)($_POST['confirm']??'')];
                if($action==='mode')$input=['mode'=>(string)($_POST['mode']??'')];
                if($action==='simulate')$input=['plan'=>(string)($_POST['plan']??'')];
                $g=null; // O repositório carrega a carreira sob lock; evite manter outro universo completo na memória.
                $saved=$r->command($_SESSION['account'],$action,$input,(string)($_POST['key']??''),(int)($_POST['version']??-1));
                $savedId = isset($saved['id']) ? (int)$saved['id'] : 0;
            }
            if($savedId > 0 && function_exists('fastcgi_finish_request')) {
                // Em ambiente FastCGI, envia cabeçalhos e corpo do redirecionamento 303 antes de acionar a drenagem
                header('Location: /carreira', true, 303);
                header('Content-Length: 0');
                header('Connection: close');
                session_write_close();
                fastcgi_finish_request();
                try {
                    F01Drainer::drain($r, (int)$_SESSION['account'], $savedId, F01Drainer::DEFAULT_BATCH_SIZE);
                } catch (Throwable) {}
                exit;
            }
            redirect('/carreira');
        }
    }
    if($path==='/carreira/trofeus' && isset($_GET['career']))$g=RetiredCareer::state(RetiredCareer::owned($r,$_SESSION['account'],(int)$_GET['career']));
    if($path==='/carreira/resumo')$retiredRecord=RetiredCareer::owned($r,$_SESSION['account'],(int)($_GET['career']??0));
    if($path==='/carreira/compactar'){$compactId=(int)($_GET['career']??0);CareerSlots::owned($r,$_SESSION['account'],$compactId);if($r->query('SELECT etapa FROM rumo10_carreiras WHERE id=?',[$compactId])->fetchColumn()!=='aposentado')throw new InvalidArgumentException('A carreira ainda não está aposentada.');}
    if($path==='/carreira/excluir')$careerToDelete=CareerSlots::owned($r,$_SESSION['account'],(int)($_GET['career']??0));
    if($path==='/carreira/arquivadas')$archives=$r->archives($_SESSION['account']);
    if($path==='/carreira/nova'){$existingCareer=$g;$g=null;}
    if($path==='/api/carreira/estado') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); exit; }
    if($path==='/api/v1/command/response') {
        $key = (string)($_GET['key'] ?? '');
        $careerId = isset($_GET['career_id']) ? (int)$_GET['career_id'] : ($g['id'] ?? 0);
        if(!preg_match('/^[a-f0-9]{64}$/', $key) || $careerId <= 0) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'Parâmetros key ou career_id inválidos.'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Autorização: a carreira deve pertencer à conta
        $owned = $r->query('SELECT id FROM rumo10_carreiras WHERE id=? AND (conta_id=? OR (conta_id IS NULL AND EXISTS(SELECT 1 FROM rumo10_carreiras_arquivadas a WHERE a.carreira_id=? AND a.conta_id=?)))', [$careerId, $_SESSION['account'], $careerId, $_SESSION['account']])->fetch();
        if(!$owned) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'Acesso negado à carreira.'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            exit;
        }
        $row = $r->query('SELECT contract_version, command_uid, request_hash, versao_anterior, versao_resultante, resposta_json, received_at_utc, persisted_at_utc FROM rumo10_f01_respostas WHERE carreira_id=? AND chave=?', [$careerId, $key])->fetch();
        if(!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'Resposta original não encontrada para esta chave.'], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            exit;
        }
        $respData = json_decode((string)$row['resposta_json'], true, 512, JSON_THROW_ON_ERROR);
        // Expor estritamente os metadados de auditoria e conformidade da resposta canônica autorizados pelo contrato
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'contract_version' => (int)$row['contract_version'],
            'command_uid' => $row['command_uid'],
            'request_hash' => $row['request_hash'],
            'carreira_id' => $careerId,
            'versao_anterior' => (int)$row['versao_anterior'],
            'versao_resultante' => (int)$row['versao_resultante'],
            'received_at_utc' => $row['received_at_utc'],
            'persisted_at_utc' => $row['persisted_at_utc'],
            'command_summary' => [
                'feedback' => $respData['game_state']['feedback'] ?? null,
                'stage' => $respData['game_state']['stage'] ?? null,
                'version' => $respData['game_state']['version'] ?? null,
            ]
        ], JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch(IdentityError $ex) { $status=$ex->status;$error=$ex->getMessage(); }
catch(InvalidArgumentException $ex) { if($status===200) $status=422; $error=$ex->getMessage();if(in_array($path,['/carreira/criar','/carreira/nova'],true) && $status===422){$existingCareer=$g;$g=null;$path='/carreira/nova';} }
catch(Throwable $ex) { $status=503; $error='Aplicação indisponível. Verifique a instalação e a conexão local com o banco.'; error_log('rumo10: '.$ex::class.' code='.$ex->getCode()); }
http_response_code($status);
if(str_starts_with($path,'/api/')) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error]); exit; }
if(config('RUMO10_TIMING')==='1'){$renderStart=RequestMetrics::start();ob_start();require dirname(__DIR__).'/app/view.php';$html=ob_get_clean();RequestMetrics::end('render',$renderStart);RequestMetrics::headers();echo $html;}
else require dirname(__DIR__).'/app/view.php';
