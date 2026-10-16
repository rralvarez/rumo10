<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);putenv('RUMOHUB_ENABLED=0');putenv('RUMO10_ACHIEVEMENTS=1');putenv('RUMO10_FEEDBACK=1');require dirname(__DIR__).'/app/bootstrap.php';
class PreviewStatement extends PDOStatement {private array $params=[];public function __construct(private string $sql){}public function execute(?array $params=null):bool{$this->params=$params??[];return true;}public function fetchColumn(int $column=0):mixed{return str_contains($this->sql,'information_schema')?count($this->params):999;}public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return [];}}
class PreviewConnection extends PDO {public function __construct(){}public function beginTransaction():bool{return true;}public function commit():bool{return true;}public function inTransaction():bool{return false;}public function prepare(string $query,array $options=[]):PDOStatement|false{return new PreviewStatement($query);}}
$r=new Repository(new PreviewConnection());$error=null;$g=null;$mode=$argv[1]??'room';$path=$mode==='home'?'/':($mode==='feedback'?'/feedback':'/conquistas');$_GET['filtro']=$argv[2]??'todas';$_SESSION=['account'=>999,'csrf'=>'fixture-only'];
if(($argv[3]??'')==='received')$_SESSION['feedback_received']=true;
function form(string $path,?array $g=null):void{echo '<form method="post" action="'.e($path).'"><input name="csrf" type="hidden" value="fixture-only">';}
ob_start();require dirname(__DIR__).'/app/view.php';$html=ob_get_clean();
if($mode==='toast')$html=str_replace('<main class="container"','<div data-achievement-queue class="achievement-notifications"><div class="achievement-announcement" role="status" aria-live="polite" aria-atomic="true"></div><div hidden data-achievement-notice data-title="Primeiros Passos" data-description="Criar a primeira carreira válida." data-g="5"></div><div hidden data-achievement-notice data-title="Primeiro Apito" data-description="Completar sua primeira partida." data-g="10"></div></div><main class="container"',$html);
echo $html;
