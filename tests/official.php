<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function verifyOfficial(bool $ok,string $message): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$catalog=Competition::catalog();verifyOfficial(count($catalog)===43,'43 competições');verifyOfficial(count(Competition::clubs())===671,'671 clubes únicos');
foreach(['br-a'=>20,'br-b'=>20,'br-c'=>20,'br-d'=>96,'sp-a1'=>16,'sp-a2'=>16,'sp-a3'=>16,'sp-a4'=>16,'sp-segunda'=>24] as $id=>$count) {
    $c=$catalog[$id];verifyOfficial(count($c['teams'])===$count,'Participantes '.$id);
    $t=Competition::tournament($id);$perTeam=[];
    foreach($t['matches'] as $m) {
        verifyOfficial($m['home']!==$m['away'],'Sem auto-confronto');
        foreach(['home','away'] as $side){$key=$m['round'].'|'.$m[$side];verifyOfficial(!isset($perTeam[$key]),'Sem dois jogos na rodada');$perTeam[$key]=true;}
    }
    foreach($t['teams'] as $team) {
        $own=array_values(array_filter($t['matches'],fn($m)=>in_array($team,[$m['home'],$m['away']],true)));
        $expectedGames=match($id){'br-a','br-b'=>38,'br-c'=>19,'br-d','sp-segunda'=>10,'sp-a1'=>8,default=>15};
        verifyOfficial(count($own)===$expectedGames,'Jogos iniciais por clube '.$id);
        if($id==='sp-a1')verifyOfficial(count(array_filter($own,fn($m)=>$m['home']===$team))===4,'Quatro mandos no Paulistão');
    }
    Competition::finish($t);verifyOfficial($t['done'] && in_array($t['champion'],$t['teams'],true),'Campeão real '.$id);
    $snapshot=$t;Competition::finish($t);verifyOfficial($snapshot===$t,'Resultado final imutável '.$id);
    $expected=match($id){'br-a','sp-a1'=>0,'br-b','br-c'=>4,'br-d'=>6,default=>2};verifyOfficial(count($t['promoted'])===$expected,'Acessos '.$id);
    foreach($t['matches'] as $m)verifyOfficial($m['status']==='finalizada','Calendário concluído '.$id);
    echo $id.': '.count($t['matches']).' jogos; '.count($t['promoted'])." acessos OK\n";
}
foreach(['São Caetano-SP','Água Santa-SP','Corinthians-SP','Nacional-AM'] as $club) {
    $g=Game::create('Teste Oficial','São Paulo','SP','centroavante',$club);$g=Game::command($g,'handicap');$guard=0;
    while($g['stage']!=='resumo' && $guard++<1300) {
        if($g['stage']==='competicao_resumo'){$g=Game::command($g,'continue');continue;}
        if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$action=$m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit']?'advance':'choice';}
        else $action='choice';
        $g=Game::command($g,$action,$action==='choice'?['option'=>'1']:[]);
    }
    verifyOfficial($g['stage']==='resumo','Ano completo '.$club);
    foreach($g['next_memberships'] as $id=>$members)verifyOfficial(count($members)===count(array_unique($members)),'Sem duplicação no acesso');
    verifyOfficial(count($g['next_memberships']['br-c'])===24,'Série C expande para 24');verifyOfficial(count($g['next_memberships']['br-d'])===96,'Série D mantém 96');
    $g=Game::command($g,'offers');foreach($g['offers'] as $o)verifyOfficial(isset(Competition::clubs()[$o['club']]),'Oferta real');
    $g=Game::command($g,'transfer',['accept'=>'yes']);verifyOfficial($g['season']===2 && $g['stage']==='treino','Nova temporada oficial');
    echo $club.': ano, mercado e nova temporada OK ('.$guard." passos)\n";
}
echo "OK: $checks verificações oficiais.\n";
