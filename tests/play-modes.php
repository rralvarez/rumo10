<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function modeCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function rejects(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException){return true;}}
foreach(Rules::POSITIONS as $position)foreach(array_keys(PlayMode::PLANS) as $plan) {
    $g=Game::command(Game::legacyCreate('Teste modos','São Paulo','SP',$position),'handicap');
    modeCheck(PlayMode::name($g)==='normal','Legado começa normal');
    modeCheck(rejects(fn()=>Game::command($g,'simulate',['plan'=>$plan])),'Normal não aceita simulação');
    $g=array_replace($g,['play_mode'=>'rapido']);$v=$g['version'];
    modeCheck(rejects(fn()=>Game::command($g,'choice',['option'=>0])),'Não permite contornar estratégia');
    $g=Game::command($g,'simulate',['plan'=>$plan]);
    modeCheck($g['round']===2 && $g['stage']==='treino','Rápido resolve só uma partida');
    modeCheck($g['version']===$v+1 && count($g['pace_report']['matches'])===1,'Uma versão por comando');
    $clearChances=count(array_filter($g['pace_report']['matches'][0]['log'],fn($event)=>str_contains($event['text'],'A sequência abriu a defesa')));
    modeCheck($clearChances<=2 && $g['pace_report']['decisions']>=3 && $g['pace_report']['decisions']<=8+$clearChances,'Oportunidades regulares e até duas chances construídas por partida');
    $played=$g['matches'];$skills=$g['skills'];
    modeCheck(rejects(fn()=>Game::command($g,'mode',['mode'=>'normal'])),'Rápido não pode virar normal');
    modeCheck($g['matches']===$played && $g['skills']===$skills,'Recusa conserva campanha');
    $g=Game::command(Game::legacyCreate('Outra carreira normal','São Paulo','SP',$position),'handicap');
    $g=Game::command($g,'choice',['option'=>0]);
    modeCheck(rejects(fn()=>Game::command($g,'mode',['mode'=>'expresso'])),'Troca bloqueada com decisões pendentes');
    $g=array_replace(Game::command(Game::legacyCreate('Teste expresso','São Paulo','SP',$position),'handicap'),['play_mode'=>'expresso']);
    $g=Game::command($g,'simulate',['plan'=>$plan]);
    modeCheck($g['stage']==='resumo' && $g['season']===1 && count($g['pace_report']['matches'])===14,'Expresso termina antes do contrato');
    modeCheck($g['pace_report']['after']===Game::summary($g),'Estatísticas reais');
    foreach($g['skills'] as $s)modeCheck($s['value']<=100 && $s['xp']>=0,'XP limitado pelo motor');
    $g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'no']);
    modeCheck($g['season']===2 && PlayMode::name($g)==='expresso' && !isset($g['pace_report']),'Contrato manual e modo conservado');
}
foreach(['São Caetano-SP','Flamengo-RJ','Bolívar-BO','River Plate-AR','Nacional Montevideo-UY','Olimpia-PY'] as $club) {
    $g=Game::command(Game::create('Teste oficial','São Paulo','SP','meia',$club),'handicap');
    $g=array_replace($g,['play_mode'=>'expresso']);$start=microtime(true);
    $g=Game::command($g,'simulate',['plan'=>'coletivo']);
    modeCheck($g['stage']==='resumo' && $g['season']===1,'Calendário oficial completo: '.$club);
    modeCheck(count($g['competition_results'])===count($g['season_competitions']),'Todas as competições próprias concluídas');
    modeCheck(count($g['pace_report']['matches'])>0,'Jogos oficiais registrados');
    modeCheck(isset($g['next_memberships'],$g['next_club_strengths']),'Acessos e overall prontos');
    echo $club.': '.count($g['pace_report']['matches']).' jogos em '.round(microtime(true)-$start,2)."s\n";
}
modeCheck(rejects(fn()=>PlayMode::validate('inexistente')),'Modo inválido rejeitado');
$g=Game::create('Teste início','São Paulo','SP','meia');$g['play_mode']='expresso';
$g=Game::command($g,'handicap');$g=Game::command($g,'join',['option'=>0]);
modeCheck($g['stage']==='treino' && PlayMode::name($g)==='expresso','Entrada por roleta e três clubes conserva modo');
$g=array_replace(Game::command(Game::legacyCreate('Teste ausência','São Paulo','SP','zagueiro'),'handicap'),['play_mode'=>'rapido']);
$g['states']['energia']=0;$g['states']['risco_lesao']=100;
$g=Game::command($g,'simulate',['plan'=>'equilibrio']);
modeCheck($g['pace_report']['matches'][0]['stats']['minutes']===0,'Ausência respeitada na simulação');
$g=Game::command(Game::create('Teste rápido completo','São Paulo','SP','lateral','São Caetano-SP'),'handicap');
$g=array_replace($g,['play_mode'=>'rapido']);$played=0;
for($step=0;$step<100 && $g['stage']!=='resumo';$step++) {
    if($g['stage']==='competicao_resumo')$g=Game::command($g,'continue');
    else {$g=Game::command($g,'simulate',['plan'=>'protagonismo']);$played++;modeCheck(count($g['pace_report']['matches'])===1,'Um jogo por estratégia através das fases');}
}
modeCheck($g['stage']==='resumo' && $played>14,'Rápido também conclui mata-mata');
echo "OK: $checks verificações dos três modos.\n";
