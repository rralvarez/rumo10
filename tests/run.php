<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function check(bool $v,string $message): void { global $checks; $checks++; if(!$v) throw new RuntimeException($message); }
function nextCommand(array $g): array {
    if($g['stage']==='handicap') return ['handicap',[]];
    if($g['stage']==='resumo') return ['offers',[]];
    if($g['stage']==='oferta') return ['transfer',['accept'=>'no']];
    if($g['stage']==='partida') { $m=$g['matches'][Game::currentIndex($g)]; if($m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit']) return ['advance',[]]; }
    return ['choice',['option'=>'1']];
}
check(Probability::chance(50,50,50,50,50)===50.0,'Centro da fórmula');
check(Probability::chance(100,100,100,100,0,0)===95.0,'Teto');
check(Probability::chance(0,0,0,0,100,100)===5.0,'Piso');
for($x=0;$x<100;$x++) foreach(range(0,5) as $factor) {
    $a=array_fill(0,6,50); $a[$factor]=$x; $b=$a; $b[$factor]++;
    check($factor<4?Probability::chance(...$b)>=Probability::chance(...$a):Probability::chance(...$b)<=Probability::chance(...$a),'Monotonicidade');
}
$success=0; for($i=0;$i<20000;$i++) { $r=Probability::roll(50); $success+=(int)$r['success']; check($r['roll']>=1 && $r['roll']<=10000,'Faixa sorteio'); }
check(abs($success/20000-.5)<.025,'Distribuição empírica 50% (tolerância 2,5%)');
$schedule=Schedule::generate(Rules::TEAMS); check(count($schedule)===56,'56 jogos');
$pairs=[]; foreach($schedule as $m) { $key=$m['home'].'|'.$m['away']; check(!isset($pairs[$key]),'Mando único por par'); $pairs[$key]=1; }
foreach(range(1,14) as $round) { $teams=[]; foreach($schedule as $m) if($m['round']===$round) $teams=array_merge($teams,[$m['home'],$m['away']]); check(count($teams)===8 && count(array_unique($teams))===8,'Todos jogam uma vez por rodada'); }
$all=require dirname(__DIR__).'/database/situations.php'; check(count($all)===12,'12 situações'); foreach($all as $s) check(count($s[2])===4,'4 opções');
$clubs=json_decode(file_get_contents(dirname(__DIR__).'/database/clubs.json'),true); check(count($clubs)===21,'21 clubes');
foreach($clubs as $c) foreach(['cor_primaria_hex','cor_secundaria_hex'] as $k) check((bool)preg_match('/^#[0-9A-F]{6}$/',$c[$k]),'HEX válido');
foreach(Rules::POSITIONS as $position) {
    for($trial=0;$trial<5;$trial++) {
        $g=Game::create('Jogador Teste','São Paulo','SP',$position); $g=Game::command($g,'handicap'); $profile=$g['handicap'];
        try { Game::command($g,'handicap'); check(false,'Roleta repetida proibida'); } catch(InvalidArgumentException) { check(true,'Roleta protegida'); }
        $steps=0;
        while($g['stage']!=='resumo' && $steps++<300) { [$cmd,$input]=nextCommand($g); $g=Game::command($g,$cmd,$input); }
        check($g['stage']==='resumo' && $g['round']===14,'Conclusão 14 rodadas'); check($g['handicap']===$profile,'Handicap preservado');
        $table=Schedule::table($g['matches'],$g['teams']); check(count($table)===8,'Tabela oito times');
        check(array_sum(array_column($table,'gf'))===array_sum(array_column($table,'ga')),'Gols pró/contra');
        foreach($table as $row) check($row['played']===14 && $row['points']===$row['wins']*3+$row['draws'],'Classificação consistente');
        foreach($g['matches'] as $m) { check($m['status']==='finalizada' && $m['minute']===90,'Partida finalizada'); check($m['stats']['completed']<=$m['stats']['passes'],'Passes consistentes'); check($m['stats']['goals']<=($m['home']===$g['club']?$m['hg']:$m['ag']),'Gols do jogador no placar'); if($m['stats']['minutes']===0) check($m['stats']['goals']===0 && $m['stats']['shots']===0,'Sem estatística fora de campo'); }
        foreach($g['states'] as $v) check($v>=0 && $v<=100,'Estado na faixa');
        $g=Game::command($g,'offers'); $offers=$g['offers']; check(count(array_filter($offers,fn($o)=>$o['selected']))===1,'Uma proposta sorteada');
        try { Game::command($g,'offers'); check(false,'Proposta repetida proibida'); } catch(InvalidArgumentException) { check(true,'Proposta persistida'); }
        $club=$g['club']; $g=Game::command($g,'transfer',['accept'=>$trial%2?'yes':'no']); check($g['season']===2 && $g['stage']==='treino' && count($g['history'])===1,'Continuação da carreira'); if($trial%2===0) check($g['club']===$club,'Recusa mantém clube');
    }
}
echo "OK: $checks verificações; 30 temporadas completas em seis posições.\n";
$highlights=['goal'=>0,'assist'=>0];
for($i=0;$i<200;$i++) {
    $sample=Game::create('Teste de Lance','São Paulo','SP','centroavante'); $sample['stage']='partida';
    $index=Game::currentIndex($sample); $sample['matches'][$index]['status']='em_andamento'; $sample['matches'][$index]['minute']=15; $sample['matches'][$index]['exit']=90; $sample['matches'][$index]['lineup']='titular';
    $sample=Game::command($sample,'choice',['option'=>($i%2?'1':'0')]); $stats=$sample['matches'][$index]['stats']; $highlight=$sample['feedback']['highlight']??null;
    check(($stats['goals']===1)===($highlight==='goal'),'Destaque corresponde a gol do personagem');
    check(($stats['assists']===1)===($highlight==='assist'),'Destaque corresponde a assistência do personagem');
    check(($sample['feedback']['effects']['energia']??0)===-3,'Custo de campo visível no resultado');
    if($highlight) $highlights[$highlight]++;
}
check($highlights['goal']>0 && $highlights['assist']>0,'Eventos pessoais observados no teste');
echo "OK: destaques pessoais correspondem às estatísticas e não aos gols coletivos.\n";
if(in_array('--mysql',$argv,true)) {
    $r=new Repository(Database::connect()); $account=null; $account2=null;
    try {
        $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['teste-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]); $account=(int)$r->db->lastInsertId();
        $r->create($account,Game::create('Integração Teste','São Paulo','SP','meia')); $g=$r->load($account); $key=bin2hex(random_bytes(32));
        $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['teste-isolamento-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]); $account2=(int)$r->db->lastInsertId();
        $r->create($account2,Game::create('Outra Carreira','Santos','SP','zagueiro')); $untouched=$r->load($account2);
        $g=$r->command($account,'handicap',[],$key,0); $again=$r->command($account,'handicap',[],$key,0); check($g===$again,'Replay idempotente no banco');
        try { $r->command($account,'choice',['option'=>'1'],$key,0); check(false,'Payload diferente com mesma chave'); } catch(InvalidArgumentException) { check(true,'Hash de payload protegido'); }
        try { $r->command($account,'choice',['option'=>'1'],bin2hex(random_bytes(32)),0); check(false,'Versão obsoleta'); } catch(InvalidArgumentException) { check(true,'Versão protegida'); }
        $g=$again; $steps=0;
        while($g['stage']!=='resumo' && $steps++<300) { [$cmd,$input]=nextCommand($g); $g=$r->command($account,$cmd,$input,bin2hex(random_bytes(32)),$g['version']); $reload=$r->load($account); check($g===$reload,'Persistência/refresh exata'); $g=$reload; }
        check($g['stage']==='resumo','Temporada persistida concluída');
        $g=$r->command($account,'offers',[],bin2hex(random_bytes(32)),$g['version']); $g=$r->command($account,'transfer',['accept'=>'yes'],bin2hex(random_bytes(32)),$g['version']); check($r->load($account)['season']===2,'Transferência persistida');
        check($r->load(0)===null,'Isolamento de conta');
        check($r->load($account2)===$untouched,'Outra conta não sofreu mudanças');
        echo "OK: integração MySQL, replay, refresh, 14 rodadas e transferência.\n";
    } finally {
        foreach([$account,$account2] as $testAccount) if($testAccount) {
            $id=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=?',[$testAccount])->fetchColumn();
            if($id) {
                foreach(['rumo10_eventos_partida','rumo10_estatisticas'] as $table) $r->query("DELETE x FROM $table x JOIN rumo10_partidas p ON p.id=x.partida_id JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?",[$id]);
                $r->query('DELETE p FROM rumo10_partidas p JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?',[$id]);
                foreach(['rumo10_times','rumo10_ofertas'] as $table) $r->query("DELETE x FROM $table x JOIN rumo10_temporadas t ON t.id=x.temporada_id WHERE t.carreira_id=?",[$id]);
                foreach(['rumo10_temporadas','rumo10_decisoes','rumo10_atributos','rumo10_estados','rumo10_transferencias'] as $table) $r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
                $r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
            }
            $r->query('DELETE FROM rumo10_contas WHERE id=?',[$testAccount]);
        }
    }
}
