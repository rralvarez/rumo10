<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function stateCheck(bool $ok,string $message):void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$catalog=Competition::catalog();$locations=json_decode(file_get_contents(dirname(__DIR__).'/database/starting-clubs.json'),true,512,JSON_THROW_ON_ERROR);
foreach($catalog as $id=>$c)if($c['format']==='state') {
    $origin=array_values(array_filter(Onboarding::municipalities(),fn($m)=>$m['uf']===$c['uf']))[0];
    $g=Game::create('Teste estadual',$origin['name'],$origin['uf'],'centroavante');$g['id']=0;
    stateCheck($g['starting'] && !$g['matches'],'Personagem antes do clube '.$id);
    $g=Game::command($g,'handicap');
    stateCheck($g['entry_division']===$id,'Entrada na divisão da UF '.$id);
    $candidates=$g['initial_candidates'];stateCheck(count($candidates)===min(3,count($c['teams'])),'Quantidade de ofertas '.$id);
    $all=[];foreach($c['teams'] as $club)$all[$club]=round(Onboarding::distance($g['origin'],$locations[$club]),1);
    $expected=array_keys($all);usort($expected,fn($a,$b)=>($all[$a]<=>$all[$b])?:strcmp($a,$b));
    stateCheck(array_column($candidates,'club')===array_slice($expected,0,3),'Mais próximos da cidade '.$id);
    foreach($candidates as $i=>$candidate) {
        $joined=Game::command($g,'join',['option'=>(string)$i]);
        stateCheck($joined['club']===$candidate['club'] && $joined['competition_id']===$id,'Escolha inicia campeonato '.$id);
        Game::currentIndex($joined);
    }
    $career=Game::command($g,'join',['option'=>'0']);$guard=0;
    while($career['stage']!=='resumo' && $guard++<1500) {
        $action=$career['stage']==='competicao_resumo'?'continue':'choice';
        if($career['stage']==='partida') {
            $match=$career['matches'][Game::currentIndex($career)];
            if($match['status']!=='em_andamento' || $match['minute']<$match['entry'] || $match['minute']>=$match['exit'])$action='advance';
        }
        $career=Game::command($career,$action,$action==='choice'?['option'=>'1']:[]);
    }
    stateCheck($career['stage']==='resumo','Carreira jogável até o fim do ano '.$id);
    $career=Game::command($career,'offers');$career=Game::command($career,'transfer',['accept'=>'no']);
    stateCheck($career['season']===2 && $career['stage']==='treino','Progressão e próxima temporada '.$id);
    for($run=0;$run<3;$run++) {
        $t=Competition::tournament($id);$own=array_fill_keys($c['teams'],0);$calendar=[];
        foreach($t['matches'] as $m) {
            foreach(['home','away'] as $side) {stateCheck(!isset($calendar[$m['round']][$m[$side]]),'Uma partida por clube/rodada');$calendar[$m['round']][$m[$side]]=true;$own[$m[$side]]++;}
        }
        if(!isset($c['rules']['group_returns']))stateCheck(count(array_unique($own))===1 || count($c['groups'])===3,'Calendário regular '.$id);
        Competition::finish($t);
        stateCheck($t['done'] && in_array($t['champion'],$c['teams'],true),'Campeonato completo '.$id);
        stateCheck(count($t['promoted'])===$c['rules']['access'],'Quantidade de acessos '.$id);
        stateCheck(count(array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))===0,'Todos os jogos concluídos '.$id);
        $saved=$t;Competition::finish($t);stateCheck($saved===$t,'Não sortear novamente resultados '.$id);
        foreach($c['teams'] as $club)stateCheck(Competition::result($t,$club)['position']>0,'Resumo de todos os clubes '.$id);
    }
    echo $id." OK\n";
}
// Uma carreira salva antes da ampliação conserva seu universo, sem novos clubes invasivos.
$g=Game::create('Universo antigo','São Paulo','SP','meia','São Caetano-SP');
$original=array_intersect_key($g['memberships'],array_flip(['br-a','br-b','br-c','br-d','sp-a1','sp-a2','sp-a3','sp-a4','sp-segunda']));$g['memberships']=$original;
$international=array_filter($catalog,fn($c)=>$c['family']==='international');$expected=array_keys($original+$international);sort($expected);
Competition::completeWorld($g);$actual=array_keys($g['competitions']);sort($actual);stateCheck($actual===$expected,'Universo antigo recebe todos os mercados internacionais na janela seguinte');
foreach($original as $id=>$teams)stateCheck($g['memberships'][$id]===$teams,'Participantes antigos preservados: '.$id);
stateCheck(Onboarding::division('SE','Talento de rua')==='se-entrada','Sergipe usa A2 provisória autorizada');
echo "OK: $checks verificações estaduais e geográficas.\n";
