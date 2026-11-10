<?php
declare(strict_types=1);
if(!in_array('--local-fixtures',$argv,true))exit("Use --local-fixtures, somente loopback.\n");
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_IDENTITY=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_ACHIEVEMENTS=1');putenv('RUMO10_RECONCILIATION=1');putenv('RUMO10_FEEDBACK=1');require dirname(__DIR__).'/app/bootstrap.php';
if(config('APP_ENV','local')==='production'||!in_array(config('DB_HOST'),['localhost','127.0.0.1','::1'],true)||config('DB_DATABASE')!=='rumohub')throw new RuntimeException('Somente rumohub local.');
$root=dirname(__DIR__);$dir=$root.'/storage/qa/v061';if(!is_dir($dir))mkdir($dir,0700,true);
$db=Database::connect();$r=new Repository($db);$hub=random_int(1000000000000,7000000000000);$email='http-v061-'.bin2hex(random_bytes(8)).'@example.invalid';$password=bin2hex(random_bytes(20));$account=0;$career=0;$server=null;$cookies=[];$checks=0;
function ok(bool $x,string $why):void{global $checks;$checks++;if(!$x)throw new RuntimeException($why);}
function request(string $path,?array $post=null):array {
 global $cookies;$headers=['Connection: close'];if($cookies)$headers[]='Cookie: '.implode('; ',array_map(fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies));
 $opt=['method'=>$post===null?'GET':'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>10,'header'=>implode("\r\n",$headers)];if($post!==null){$opt['content']=http_build_query($post);$opt['header'].="\r\nContent-Type: application/x-www-form-urlencoded";}
 $body=file_get_contents('http://127.0.0.1:18061'.$path,false,stream_context_create(['http'=>$opt]));$response=$http_response_header??[];preg_match('/HTTP\/\S+ (\d+)/',$response[0]??'',$m);
 foreach($response as $h)if(preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i',$h,$cookie))$cookies[$cookie[1]]=$cookie[2];return ['status'=>(int)($m[1]??0),'body'=>$body?:''];
}
function hidden(string $html,string $name):string {preg_match('/name="'.preg_quote($name,'/').'"[^>]*value="([^"]*)"/',$html,$m);return html_entity_decode($m[1]??'',ENT_QUOTES,'UTF-8');}
try{
 $probe=@fsockopen('127.0.0.1',18061,$errno,$errstr,.1);if($probe){fclose($probe);throw new RuntimeException('Porta de teste ocupada; recusado.');}
 $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$account=(int)$db->lastInsertId();$r->query('INSERT INTO rumo10_identidades VALUES(?,?)',[$account,$hub]);
 $r->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?)',[$account,'Fixture','São Paulo','SP','meia',18,'treino','Fixture']);$career=(int)$db->lastInsertId();
 $g=Game::legacyCreate('Fixture','São Paulo','SP','meia');$g['id']=$career;$g['official']=true;$g['competition_id']='br-a';$g['season_competitions']=['br-a'];$g['stage']='treino';$m=$g['matches'][Game::currentIndex($g)];$m['status']='finalizada';$m['entry']=0;$m['exit']=90;$m['stats']=array_replace($m['stats'],['minutes'=>90,'goals'=>3,'assists'=>1,'tackles'=>2]);$m[$m['home']===$g['club']?'hg':'ag']=4;$g['matches']=[$m];$r->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)',[$career,json_encode($g,JSON_THROW_ON_ERROR)]);
 $r->query('INSERT INTO rumo10_conquistas(hub_usuario_id,conquista_id,carreira_id,pontos_g,evento_chave) VALUES(?,?,?,?,?)',[$hub+1,'DES10',999999999,70,str_repeat('e',64)]);
 $server=proc_open([PHP_BINARY,'-n','-d','extension_dir=C:/PHP/php-8.4.26/ext','-d','extension=mbstring','-d','extension=pdo_mysql','-S','127.0.0.1:18061','-t',$root.'/public',$root.'/tests/router-v061.php'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server-error.log','a']],$pipes,$root);if(!is_resource($server))throw new RuntimeException('Servidor de teste não iniciou.');fclose($pipes[0]);
 for($i=0;$i<30;$i++){usleep(100000);$socket=@fsockopen('127.0.0.1',18061,$errno,$errstr,.1);if($socket){fclose($socket);break;}}
 if(!proc_get_status($server)['running'])throw new RuntimeException('Servidor isolado encerrou; teste interrompido.');
 ok(request('/feedback')['status']===303,'anonymous feedback redirects');ok(request('/feedback',['rating'=>5])['status']===403,'anonymous CSRF denied');
 $login=request('/login');$csrf=hidden($login['body'],'csrf');ok(request('/login',['csrf'=>$csrf,'email'=>$email,'password'=>$password])['status']===303,'fixture login');
 $page=request('/feedback');ok($page['status']===200,'authenticated feedback form');$csrf=hidden($page['body'],'csrf');$key=hidden($page['body'],'key');$post=['csrf'=>$csrf,'key'=>$key,'rating'=>4,'category'=>'sugestao','comment'=>'<script>alert(1)</script>'];
 ok(request('/feedback',array_replace($post,['csrf'=>'wrong']))['status']===403,'CSRF server enforced');ok(request('/feedback',$post+['user_id'=>$hub+1])['status']===422,'client cannot select user');ok(request('/feedback',$post)['status']===303,'valid feedback saved');ok(request('/feedback',$post)['status']===303,'HTTP replay safe');
 ok((int)$r->query('SELECT COUNT(*) FROM rumo10_feedback WHERE hub_usuario_id=?',[$hub])->fetchColumn()===1,'one persisted feedback');$received=request('/feedback');ok(str_contains($received['body'],'Avaliação recebida.'),'received confirmation');ok(!str_contains($received['body'],'<script>alert(1)</script>'),'no stored XSS reflected');
 $room=request('/conquistas?user_id='.($hub+1));ok($room['status']===200,'authenticated historical reconciliation');ok(substr_count($room['body'],'achievement is-unlocked')===7,'only own seven unlocks');ok(str_contains($room['body'],'7 conquistas históricas reconhecidas'),'single consolidated historical notice');
 $again=request('/conquistas');ok(!str_contains($again['body'],'7 conquistas históricas reconhecidas'),'historical notice no reload replay');ok((int)$r->query('SELECT SUM(pontos_g) FROM rumo10_conquistas WHERE hub_usuario_id=?',[$hub])->fetchColumn()===75,'own 75 G not other 70 G');
 ok(request('/admin/feedback')['status']===404,'no unsafe admin route');request('/logout',['csrf'=>$csrf]);
}finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 foreach(['rumo10_feedback','rumo10_conquistas_fontes','rumo10_conquistas_reconciliacoes','rumo10_conquistas_eventos','rumo10_conquistas_carreiras','rumo10_conquistas'] as $table)$r->query('DELETE FROM '.$table.' WHERE hub_usuario_id IN (?,?)',[$hub,$hub+1]);
 if($career){$db->beginTransaction();CareerSlots::purgeDetails($r,$career);$r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$career]);$db->commit();}
 if($account){$r->query('DELETE FROM rumo10_identidades WHERE conta_id=?',[$account]);$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}$r->query('DELETE FROM rumo10_login_limits WHERE fingerprint=?',[hash('sha256','127.0.0.1|'.$email)]);
}
file_put_contents($root.'/Docs/pos-jogo-v061/http.json',json_encode(['checks'=>$checks,'environment'=>'Isolated loopback PHP HTTP process, local login fallback; NOT authenticated IIS/Hub login','fixtures_cleaned'=>true,'historical_unlocked'=>7,'rh_writes'=>false,'result'=>'passed'],JSON_PRETTY_PRINT));echo "OK: $checks verificações HTTP autenticadas com fixtures descartáveis; sem Hub/produção.\n";
