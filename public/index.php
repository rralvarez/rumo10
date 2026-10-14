<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
ini_set('display_errors','0');
ini_set('log_errors','1');
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
function redirect(string $path): never { header('Location: '.$path,true,303); exit; }
function form(string $path,?array $g=null): void {
    echo '<form method="post" action="'.e($path).'"><input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
    if($g) echo '<input type="hidden" name="version" value="'.e($g['version']).'"><input type="hidden" name="key" value="'.bin2hex(random_bytes(32)).'">';
}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$method=$_SERVER['REQUEST_METHOD'];
$routes=['/carreira/resumo'=>['GET'],'/carreira/compactar'=>['GET','POST'],'/carreiras'=>['GET'],'/carreira/excluir'=>['GET','POST'],'/carreira/aposentar'=>['GET','POST'],'/elenco'=>['GET'],'/carreira/modo'=>['POST'],'/carreira/simular'=>['POST'],'/jogador'=>['GET'],'/'=>['GET'],'/login'=>['GET','POST'],'/register'=>['GET','POST'],'/logout'=>['POST'],'/carreira'=>['GET'],'/carreira/nova'=>['GET','POST'],'/carreira/arquivadas'=>['GET'],'/carreira/restaurar'=>['POST'],'/competicao/continuar'=>['POST'],'/carreira/criar'=>['POST'],'/carreira/clube-inicial'=>['POST'],'/carreira/handicap'=>['POST'],'/semana'=>['GET'],'/decisao/registrar'=>['POST'],'/partida'=>['GET'],'/partida/avancar'=>['POST'],'/partida/decidir'=>['POST'],'/classificacao'=>['GET'],'/temporada/resumo'=>['GET'],'/transferencia/roleta'=>['POST'],'/transferencia/responder'=>['POST'],'/api/carreira/estado'=>['GET'],'/api/cidades'=>['GET']];
$status=200; $error=null; $g=null; $clubs=[];
try {
    if($path==='/escudo' && $method==='GET' && RumoHub::enabled()) {
        if(!isset($_SESSION['account'])){http_response_code(401);exit;}
        $club=$_GET['club']??'';if(!is_string($club) || strlen($club)>400){http_response_code(422);exit;}
        session_write_close();try{RumoHub::badge($club);}catch(Throwable $badgeError){http_response_code(503);error_log('Escudo Hub indisponível: '.$badgeError::class);exit;}
    }
    if(!isset($routes[$path])) { $status=404; throw new InvalidArgumentException('Página não encontrada.'); }
    if(!in_array($method,$routes[$path],true)) { $status=405; header('Allow: '.implode(', ',$routes[$path])); throw new InvalidArgumentException('Método não permitido.'); }
    if($method==='POST' && (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf']))) { $status=403; throw new InvalidArgumentException('Sessão de formulário inválida. Recarregue a página.'); }
    $public=in_array($path,['/','/login','/register'],true);
    if(!$public && !isset($_SESSION['account'])) { if(str_starts_with($path,'/api/')) { $status=401; throw new InvalidArgumentException('Entre na sua conta.'); } redirect('/login'); }
    if($method==='POST' && in_array($path,['/login','/register'],true)) {
        $r=new Repository(Database::connect());
        $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<8 || strlen($password)>72) throw new InvalidArgumentException('Use um e-mail válido e senha entre 8 e 72 caracteres.');
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
    if($path==='/logout') { $_SESSION=[]; session_destroy(); redirect('/'); }
    if($path==='/api/cidades') {
        $uf=strtoupper((string)($_GET['uf']??'SP'));$cities=array_values(array_column(array_filter(Onboarding::municipalities(),fn($m)=>$m['uf']===$uf),'name'));
        if(!$cities)throw new InvalidArgumentException('UF inválida.');
        header('Content-Type: application/json; charset=utf-8');echo json_encode($cities,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    if(!$public) {
        $r=new Repository(Database::connect()); if(!in_array($path,['/carreiras','/carreira/excluir','/carreira/resumo','/carreira/compactar'],true))$g=$r->load($_SESSION['account']);
        if($method==='POST') {
            if($path==='/carreira/compactar'){RetiredCareer::compactExisting($r,$_SESSION['account'],(int)($_POST['career']??0),(string)($_POST['confirm']??''));redirect('/carreira/resumo?career='.(int)$_POST['career']);}
            if($path==='/carreira/excluir'){CareerSlots::delete($r,$_SESSION['account'],(int)($_POST['career']??0),(string)($_POST['confirm']??''));redirect('/carreiras');}
            if(in_array($path,['/carreira/criar','/carreira/nova'],true)) {
                $newGame=Game::create(trim((string)($_POST['name']??'')),trim((string)($_POST['city']??'')),strtoupper((string)($_POST['uf']??'')),(string)($_POST['position']??''));
                $newGame['play_mode']=PlayMode::validate((string)($_POST['mode']??'normal'));
                $r->create($_SESSION['account'],$newGame,$path==='/carreira/nova');
            } elseif($path==='/carreira/restaurar') {
                $r->restore($_SESSION['account'],(int)($_POST['career']??0));
            } else {
                $action=match($path) {'/carreira/aposentar'=>'retire','/carreira/modo'=>'mode','/carreira/simular'=>'simulate','/carreira/clube-inicial'=>'join','/competicao/continuar'=>'continue','/carreira/handicap'=>'handicap','/decisao/registrar','/partida/decidir'=>'choice','/partida/avancar'=>'advance','/transferencia/roleta'=>'offers','/transferencia/responder'=>'transfer',default=>throw new InvalidArgumentException('Ação inválida.')};
                $input=in_array($action,['choice','join'],true)?['option'=>(string)($_POST['option']??'')]:($action==='transfer'?['accept'=>(string)($_POST['accept']??'')]:[]);
                if($action==='retire')$input=['confirm'=>(string)($_POST['confirm']??'')];
                if($action==='mode')$input=['mode'=>(string)($_POST['mode']??'')];
                if($action==='simulate')$input=['plan'=>(string)($_POST['plan']??'')];
                $g=null; // O repositório carrega a carreira sob lock; evite manter outro universo completo na memória.
                $r->command($_SESSION['account'],$action,$input,(string)($_POST['key']??''),(int)($_POST['version']??-1));
            }
            redirect('/carreira');
        }
    }
    if($path==='/carreira/resumo')$retiredRecord=RetiredCareer::owned($r,$_SESSION['account'],(int)($_GET['career']??0));
    if($path==='/carreira/compactar'){$compactId=(int)($_GET['career']??0);CareerSlots::owned($r,$_SESSION['account'],$compactId);if($r->query('SELECT etapa FROM rumo10_carreiras WHERE id=?',[$compactId])->fetchColumn()!=='aposentado')throw new InvalidArgumentException('A carreira ainda não está aposentada.');}
    if($path==='/carreira/excluir')$careerToDelete=CareerSlots::owned($r,$_SESSION['account'],(int)($_GET['career']??0));
    if($path==='/carreira/arquivadas')$archives=$r->archives($_SESSION['account']);
    if($path==='/carreira/nova'){$existingCareer=$g;$g=null;}
    if($path==='/api/carreira/estado') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); exit; }

} catch(InvalidArgumentException $ex) { if($status===200) $status=422; $error=$ex->getMessage();if(in_array($path,['/carreira/criar','/carreira/nova'],true) && $status===422){$existingCareer=$g;$g=null;$path='/carreira/nova';} }
catch(Throwable $ex) { $status=503; $error='Aplicação indisponível. Verifique a instalação e a conexão local com o banco.'; error_log('rumo10: '.$ex::class.' code='.$ex->getCode()); }
http_response_code($status);
if(str_starts_with($path,'/api/')) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error]); exit; }
require dirname(__DIR__).'/app/view.php';
