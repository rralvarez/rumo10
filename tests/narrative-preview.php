<?php
declare(strict_types=1);
// Router visual exclusivo do PHP CLI Server em loopback. Nunca é empacotado para produção.
$render=PHP_SAPI==='cli' && ($argv[1]??'')==='--render';
if(!$render && (PHP_SAPI!=='cli-server' || ($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1')){http_response_code(404);exit;}
if($render){$_GET['scene']=$argv[2]??2000;if(isset($argv[3]))$_POST['option']=$argv[3];$_SERVER['REQUEST_URI']='/';}
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(str_starts_with($uri,'/assets/') || $uri==='/favicon.svg' || $uri==='/favicon.ico')return false;
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$id=(int)($_GET['scene']??$_POST['scene']??2000);$s=NarrativeCatalog::all()[$id]??null;if(!$s){http_response_code(404);exit;}
$_SESSION=['account'=>999,'csrf'=>'narrative-local-preview'];
function form(string $path,?array $g=null):void{global $id;echo '<form method="post" action="/?scene='.$id.'"><input type="hidden" name="scene" value="'.$id.'"><input type="hidden" name="csrf" value="narrative-local-preview">';if($g)echo '<input type="hidden" name="version" value="'.$g['version'].'"><input type="hidden" name="key" value="'.str_repeat('a',64).'">';}
$g=Game::legacyCreate('Jogador fictício de revisão','São Paulo','SP',($s['gating']['positions_allow'][0]??'meia'));$g['id']=999;$g['stage']=$s['stage'];$g['story_seed']='visual-local';$g['states']['risco_lesao']=0;
$i=Game::currentIndex($g);$m=&$g['matches'][$i];$m['status']=$g['stage']==='fim_semana'?'finalizada':'em_andamento';$m['minute']=$g['stage']==='fim_semana'?90:70;$m['entry']=0;$m['exit']=90;$m['lineup']='titular';$m['stats']['minutes']=70;
foreach($s['gating'] as $k=>$v){if($k==='bench' && $v){$g['round']=4;$m['round']=4;$g['states']['energia']=35;$m['lineup']='reserva';}if($k==='score_state'){$m['hg']=$v==='empatando'?0:1;$m['ag']=0;if(($m['home']===$g['club'])!==($v==='ganhando') && $v!=='empatando'){$m['hg']=0;$m['ag']=1;}}if($k==='own_goals_min'){$m['stats']['goals']=$v;$m['hg']=$m['ag']=$v;}if($k==='own_cards_min')$m['stats']['cards']=$v;if($k==='injury_risk_min')$g['states']['risco_lesao']=$v;}
unset($m);$g['active_situation']=['id'=>$id,'key'=>Scenes::key($g),'snapshot'=>$s];
if(isset($_POST['option'])){$o=$s['options'][(int)$_POST['option']];$g['feedback']=['success'=>false,'class'=>'falha','roll'=>9000,'chance'=>50,'text'=>$o['text'].': '.$o['failure_text'],'effects'=>$o['failure_effects']];}
$path='/carreira';$error=null;$careerPlan='gratuito';require dirname(__DIR__).'/app/view.php';
