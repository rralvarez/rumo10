<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
function sampleGame(bool $finished=false): array {
    $g=Game::create('Jogador de teste','São Paulo','SP','meia'); $g['id']=0; $g['version']=42; $g['round']=2; $g['stage']=$finished?'fim_semana':'partida';
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
foreach(['result-success','data-stat="goals">2','Habilidades e evolução','Confiança +5','O adversário se aproxima. O que você faz?'] as $text) if(!str_contains($html,$text)) throw new RuntimeException('Componente ausente: '.$text);
if(str_contains($html,'Rádio do jogo') || str_contains($html,'Ver efeitos') || str_contains($html,'href="/clubes"') || str_contains($html,'SEU DESEMPENHO NESTA PARTIDA')) throw new RuntimeException('Partida contém painel ou menu que deveria ser removido.');
if(str_contains($html,'Chance') || str_contains($html,'/10000') || str_contains($html,'oportunidade como')) throw new RuntimeException('Detalhes técnicos ou posição redundante na narrativa.');
preg_match_all('/<button class="choice"[^>]*>(.*?)<\/button>/s',$html,$choices);
foreach($choices[1] as $choice) if(str_contains($choice,'<small>') || str_contains($choice,'XP ')) throw new RuntimeException('Escolha mostra atributos previstos.');
$g['feedback']['success']=false; $g['feedback']['class']='falha'; $g['feedback']['highlight']=null; $g['feedback']['text']='<script>alert(1)</script>';
ob_start(); Presentation::feedback($g); $failure=ob_get_clean();
if(!str_contains($failure,'result-failure') || str_contains($failure,'<script>')) throw new RuntimeException('Falha ou escaping do resultado incorreto.');
$g['feedback']['success']=true; $g['feedback']['highlight']='goal';
ob_start(); Presentation::feedback($g); $goal=ob_get_clean();
if(!str_contains($goal,'GOOOL! Você marcou!')) throw new RuntimeException('Gol sem destaque');
$g['feedback']['highlight']='assist';
ob_start(); Presentation::feedback($g); $assist=ob_get_clean();
if(!str_contains($assist,'Assistência sua!')) throw new RuntimeException('Assistência sem destaque');
$finished=renderSample(true);
if(!str_contains($finished,'data-stat="season-goals">4') || !str_contains($finished,'<details open>')) throw new RuntimeException('Histórico pós-jogo incorreto.');
if(!str_contains($finished,'Rádio do jogo')) throw new RuntimeException('Pós-jogo sem rádio');
if(strpos($finished,'Seus gols no campeonato')>strpos($finished,'Ex-treinador')) throw new RuntimeException('Histórico não precede decisão de fim de semana.');
echo "OK: estatísticas ao vivo, histórico por rodada, totais finalizados, resultado verde/vermelho e escaping.\n";
