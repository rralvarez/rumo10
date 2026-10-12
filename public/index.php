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
function wheel(array $items): void {
    $sum=array_sum(array_column($items,'peso')); $angle=-M_PI/2;
    echo '<svg class="offer-wheel" viewBox="0 0 200 200" role="img" aria-label="Fatias proporcionais aos pesos das propostas">';
    foreach($items as $i=>$item) {
        $span=2*M_PI*$item['peso']/$sum; $end=$angle+$span;
        $x1=100+90*cos($angle); $y1=100+90*sin($angle); $x2=100+90*cos($end); $y2=100+90*sin($end);
        $color=isset($item['tier']) && $item['tier']>1?'#d2f36b':['#67baa6','#858bc0','#cfaa6a','#85a6b8'][$i%4];
        $d=sprintf('M100 100 L%.3f %.3f A90 90 0 %d 1 %.3f %.3f Z',$x1,$y1,$span>M_PI?1:0,$x2,$y2);
        echo '<path d="'.e($d).'" fill="'.$color.'" stroke="#0e171b" stroke-width="2"><title>'.e(($item['club']??$item['nome']).' · '.round($item['peso']/$sum*100,1).'%').'</title></path>';
        $angle=$end;
    }
    echo '<circle cx="100" cy="100" r="25" fill="#0e171b"/><text x="100" y="108" text-anchor="middle" fill="#edf1e9" font-size="24">10</text></svg>';
}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$method=$_SERVER['REQUEST_METHOD'];
$routes=['/'=>['GET'],'/login'=>['GET','POST'],'/register'=>['GET','POST'],'/logout'=>['POST'],'/carreira'=>['GET'],'/carreira/criar'=>['POST'],'/carreira/handicap'=>['POST'],'/semana'=>['GET'],'/decisao/registrar'=>['POST'],'/partida'=>['GET'],'/partida/avancar'=>['POST'],'/partida/decidir'=>['POST'],'/classificacao'=>['GET'],'/temporada/resumo'=>['GET'],'/transferencia/roleta'=>['POST'],'/transferencia/responder'=>['POST'],'/api/carreira/estado'=>['GET']];
$status=200; $error=null; $g=null; $clubs=[];
try {
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
    if(!$public) {
        $r=new Repository(Database::connect()); $g=$r->load($_SESSION['account']);
        if($method==='POST') {
            if($path==='/carreira/criar') {
                $r->create($_SESSION['account'],Game::create(trim((string)($_POST['name']??'')),trim((string)($_POST['city']??'')),strtoupper((string)($_POST['uf']??'')),(string)($_POST['position']??'')));
            } else {
                $action=match($path) {'/carreira/handicap'=>'handicap','/decisao/registrar','/partida/decidir'=>'choice','/partida/avancar'=>'advance','/transferencia/roleta'=>'offers','/transferencia/responder'=>'transfer',default=>throw new InvalidArgumentException('Ação inválida.')};
                $input=$action==='choice'?['option'=>(string)($_POST['option']??'')]:($action==='transfer'?['accept'=>(string)($_POST['accept']??'')]:[]);
                $r->command($_SESSION['account'],$action,$input,(string)($_POST['key']??''),(int)($_POST['version']??-1));
            }
            redirect('/carreira');
        }
    }
    if($path==='/api/carreira/estado') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); exit; }

} catch(InvalidArgumentException $ex) { if($status===200) $status=422; $error=$ex->getMessage(); }
catch(Throwable $ex) { $status=503; $error='Aplicação indisponível. Verifique a instalação e a conexão local com o banco.'; error_log('rumo10: '.$ex::class.' code='.$ex->getCode()); }
http_response_code($status);
if(str_starts_with($path,'/api/')) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error]); exit; }
require dirname(__DIR__).'/app/view.php';
