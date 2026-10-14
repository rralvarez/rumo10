<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function worldCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$selected=json_decode(file_get_contents(dirname(__DIR__).'/database/world-top100-2026.json'),true);
worldCheck(count($selected['clubs'])===100,'100 clubes pesquisados');$known=Competition::clubs();
foreach($selected['clubs'] as $c)worldCheck(isset($known[$c['club']]),'Top 100 associado: '.$c['source_name']);
$clubs=json_decode(file_get_contents(dirname(__DIR__).'/database/concacaf-clubs-2026.json'),true);$badges=json_decode(file_get_contents(dirname(__DIR__).'/database/club-badges.json'),true);
foreach($clubs as $c){worldCheck(isset($known[$c['name']]),'Clube cadastrado');worldCheck(count($c['colors'])>=2,'Cores');worldCheck(is_file(dirname(__DIR__).'/public'.$badges[$c['name']]['path']),'Escudo local');worldCheck(TeamStrength::initial($c['name'])>=20 && TeamStrength::initial($c['name'])<=95,'Overall');}
$comps=json_decode(file_get_contents(dirname(__DIR__).'/database/concacaf-competitions-2026.json'),true);
foreach($comps as $c){
 $t=Competition::tournament($c['id']);$busy=[];
 foreach($t['matches'] as $m)foreach(['home','away'] as $side){$key=$m['round'].'|'.$m[$side];worldCheck(!isset($busy[$key]),'Sem sobreposição '.$c['id']);$busy[$key]=true;}
 if($c['id']==='us-1')foreach($c['teams'] as $club){$games=array_filter($t['matches'],fn($m)=>in_array($club,[$m['home'],$m['away']],true));worldCheck(count($games)===34,'MLS 34 jogos');worldCheck(count(array_filter($games,fn($m)=>$m['home']===$club))===17,'MLS 17 mandos');}
 if($c['id']==='ca-1')foreach($c['teams'] as $club)worldCheck(count(array_filter($t['matches'],fn($m)=>in_array($club,[$m['home'],$m['away']],true)))===28,'CPL 28 jogos');
 Competition::finish($t);worldCheck($t['done'] && in_array($t['champion'],$c['teams'],true),'Campeão válido');worldCheck(!$t['promoted'] && !$t['relegated'],'Sem inventar acessos');
 foreach($t['matches'] as $m)worldCheck($m['status']==='finalizada','Sem pendência');
 if($c['id']==='us-1')foreach($t['series_wins'] as $wins)worldCheck(max($wins)===2,'Melhor de três: dois triunfos, sem agregado');
 $copy=$t;Competition::finish($t);worldCheck($copy===$t,'Não ressorteia campeonato concluído');
 echo $c['id'].': '.count($t['matches'])." partidas concluídas\n";
}
worldCheck(TransferMarket::country('Inter Miami CF-US')==='US','Miami nos Estados Unidos');worldCheck(TransferMarket::country('Toronto FC-CA')==='CA','Toronto no Canadá');
// Cada série é decidida por vitórias, mesmo quando o adversário fez mais gols no agregado.
$t=Competition::tournament('us-1');foreach($t['matches'] as &$m)Competition::simulate($m);unset($m);Competition::nextPhase($t);
foreach($t['matches'] as &$m)Competition::simulate($m);unset($m);Competition::nextPhase($t);
for($game=1;$game<=3;$game++) {
 foreach($t['matches'] as &$m)if($m['phase']==='serie_'.$game){$m['hg']=$game===2?10:1;$m['ag']=0;$m['status']='finalizada';}unset($m);
 Competition::nextPhase($t);
}
foreach($t['series_pairs'] as $i=>$pair)worldCheck($t['series_winners'][$i]===$pair[0],'Vitórias prevalecem sobre saldo agregado');
$players=ClubSquad::players('Inter Miami CF-US');$messi=array_values(array_filter($players,fn($p)=>$p['name']==='Lionel Messi'));
worldCheck(count($messi)===1 && $messi[0]['origin']==='real','Messi importado sem duplicação');worldCheck($messi[0]['overall_origin']==='published_ea_fc27','Nota publicada de Messi');
// Percorre escolhas em uma carreira descartável, sem alterar a conta do usuário.
$g=Game::command(Game::create('Teste MLS','São Paulo','SP','meia','Inter Miami CF-US'),'handicap');$g=Game::command($g,'mode',['mode'=>'expresso']);$g=Game::command($g,'simulate',['plan'=>'equilibrio']);
worldCheck($g['stage']==='resumo','MLS jogável até o fim');$g=Game::command($g,'offers');$g=Game::command($g,'transfer',['accept'=>'no']);worldCheck($g['season']===2 && $g['stage']==='treino','Renovação e segunda temporada MLS');
Competition::finish($g['competitions']['us-1']);$g['competition_results']['us-1']=Competition::result($g['competitions']['us-1'],$g['club']);$g['matches']=$g['competitions']['us-1']['matches'];$g['id']=0;$g['version']=1;$_SESSION=['csrf'=>'teste'];
ob_start();CompetitionView::recap($g);$html=ob_get_clean();worldCheck(str_contains($html,'Supporters') && str_contains($html,'MLS Cup'),'Troféus renderizados com nomes de clubes');
echo "OK: $checks verificações expansão mundial/Concacaf.\n";
