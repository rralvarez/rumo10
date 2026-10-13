<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
function sampleGame(bool $finished=false): array {
    $g=Game::legacyCreate('Jogador de teste','São Paulo','SP','meia'); $g['id']=0; $g['version']=42; $g['round']=2; $g['stage']=$finished?'fim_semana':'partida';
    foreach($g['matches'] as &$match) if(in_array($g['club'],[$match['home'],$match['away']],true) && $match['round']<=2) {
        $match['status']=$match['round']===1 || $finished?'finalizada':'em_andamento';
        $match['minute']=$match['status']==='finalizada'?90:70; $match['lineup']='titular'; $match['entry']=0; $match['exit']=90;
        $match[$match['home']===$g['club']?'hg':'ag']=2; $match[$match['home']===$g['club']?'ag':'hg']=1;
        $match['stats']['goals']=2; $match['stats']['assists']=0; $match['stats']['shots']=3; $match['stats']['passes']=4; $match['stats']['completed']=3; $match['stats']['minutes']=$match['status']==='finalizada'?90:0; $match['stats']['rating']=8;
        $match['log']=[['minute'=>25,'text'=>'Gol do seu jogador!'],['minute'=>60,'text'=>'Gol do seu jogador!']];
    } unset($match);
    $g['feedback']=['chance'=>62.5,'roll'=>1234,'success'=>true,'class'=>'sucesso','highlight'=>'goal','text'=>'O chute entrou. Gol do seu jogador!','effects'=>['confianca'=>5]]; return $g;
}
function renderSample(bool $finished=false): string {
    $g=sampleGame($finished); $path='/carreira'; $error=null; $_SESSION=['account'=>0,'csrf'=>'preview'];
    if(!function_exists('form')) { function form(string $path,?array $g=null): void { echo '<form method="post" action="'.e($path).'">'; } }
    ob_start(); require dirname(__DIR__).'/app/view.php'; return ob_get_clean();
}
if(defined('RUMO10_PREVIEW')) return;
if(in_array('--render',$argv??[],true)) { echo renderSample(in_array('--finished',$argv,true)); return; }
$g=sampleGame();
if(Game::summary($g)['goals']!==2) throw new RuntimeException('Resumo inclui gol de partida em andamento.');
$html=renderSample();
if(!preg_match('#<nav[^>]*>.*<a class="new-career-link" href="/carreira/nova">Nova carreira</a>.*</nav>#s',$html)) throw new RuntimeException('Nova carreira deve estar no menu durante a partida.');
foreach(['result-success','data-stat="goals">2','Habilidades e evolução','Confiança +5','O adversário se aproxima. O que você faz?'] as $text) if(!str_contains($html,$text)) throw new RuntimeException('Componente ausente: '.$text);
if(str_contains($html,'Rádio do jogo') || str_contains($html,'Ver efeitos') || str_contains($html,'href="/clubes"') || str_contains($html,'SEU DESEMPENHO NESTA PARTIDA')) throw new RuntimeException('Partida contém painel ou menu que deveria ser removido.');
if(str_contains($html,'/10000') || str_contains($html,'oportunidade como')) throw new RuntimeException('Detalhes técnicos ou posição redundante na narrativa.');
preg_match_all('/<button class="choice"[^>]*>(.*?)<\/button>/s',$html,$choices);
foreach($choices[1] as $i=>$choice) {
    $expected=number_format(Game::chance($g,Game::choices($g)[$i]),2,',','');
    if(!str_contains($choice,'Chance de sucesso: '.$expected.'%') || str_contains($choice,'XP ')) throw new RuntimeException('Chance exibida deve corresponder ao motor, sem atributos previstos.');
}
$g['feedback']['success']=false; $g['feedback']['class']='falha'; $g['feedback']['highlight']=null; $g['feedback']['text']='<script>alert(1)</script>';
ob_start(); Presentation::feedback($g); $failure=ob_get_clean();
if(!str_contains($failure,'result-failure') || str_contains($failure,'<script>')) throw new RuntimeException('Falha ou escaping do resultado incorreto.');
$g['feedback']['success']=true; $g['feedback']['highlight']='goal';
ob_start(); Presentation::feedback($g); $goal=ob_get_clean();
if(!str_contains($goal,'GOOOL! Você marcou!')) throw new RuntimeException('Gol sem destaque');
$g['feedback']['highlight']='assist';
ob_start(); Presentation::feedback($g); $assist=ob_get_clean();
if(!str_contains($assist,'Assistência sua!')) throw new RuntimeException('Assistência sem destaque');
$g['feedback']['effects']=['energia'=>-3,'confianca'=>5,'entrosamento'=>0];
ob_start(); Presentation::feedback($g); $effects=ob_get_clean();
if(str_contains($effects,'Entrosamento') || str_contains($effects,'Pular animação') || !str_contains($effects,'Confiança +5') || !str_contains($effects,'Energia -3')) throw new RuntimeException('Efeitos devem aparecer diretamente, sem zero ou botão de animação.');
$finished=renderSample(true);
if(str_contains($finished,'choice-chance') || str_contains($finished,'Chance de sucesso:'))throw new RuntimeException('Fim de semana não deve mostrar chances nas escolhas.');
foreach(['treino','pre_jogo'] as $stage){
    $g=sampleGame();$g['stage']=$stage;$path='/carreira';$error=null;
    ob_start();require dirname(__DIR__).'/app/view.php';$offField=ob_get_clean();
    if(str_contains($offField,'choice-chance') || str_contains($offField,'Chance de sucesso:'))throw new RuntimeException('Chances fora da partida: '.$stage);
}
if(!str_contains($finished,'data-stat="season-goals">4') || !str_contains($finished,'<details open>')) throw new RuntimeException('Histórico pós-jogo incorreto.');
if(!str_contains($finished,'Rádio do jogo')) throw new RuntimeException('Pós-jogo sem rádio');
if(strpos($finished,'Seus gols no campeonato')<strpos($finished,'Ex-treinador')) throw new RuntimeException('Decisão deve preceder o histórico detalhado.');
echo "OK: estatísticas ao vivo, histórico por rodada, totais finalizados, resultado verde/vermelho e escaping.\n";
