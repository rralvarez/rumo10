<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function check(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}
$base=Competition::catalog();$clubs=Competition::clubs();$g=AlagoasTrial::create(790001);
WorldSeason::scope($g,function()use($g){
 $bad=Competition::catalog()['al-a1'];$bad['rules']['adapter_version']='unknown';try{AlagoasCompetition::create($bad,$bad['teams']);throw new RuntimeException('versão aceita');}catch(RuntimeException $e){check($e->getMessage()!=='versão aceita','versão não suportada não migra silenciosamente');}
 check(count(Competition::catalog()['al-a1']['teams'])===8,'A oito reais');check(count(Competition::catalog()['al-entrada']['teams'])===6,'B seis reais');check(count(Competition::catalog()['al-copa']['teams'])===11,'copa exclui Aliança');
 check(!in_array('Desportivo Aliança-AL',Competition::catalog()['al-copa']['teams'],true),'sanção apenas edição inicial');
 check(count($g['competitions']['al-a1']['matches'])===28,'A turno único');check(count($g['competitions']['al-entrada']['matches'])===15,'B turno único');check(count($g['competitions']['al-copa']['matches'])===25,'grupos efetivos cinco e seis');
 foreach(['al-a1','al-entrada','al-copa'] as $id){$t=$g['competitions'][$id];foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=0;$m['ag']=0;}unset($m);Competition::nextPhase($t);check(count($t['ties'])===2,'semifinais duas');$semis=array_slice($t['matches'],-($id==='al-copa'?2:4));check(count($semis)===($id==='al-copa'?2:4),'pernas das semifinais');
  $ranks=array_map(fn($group)=>array_column(AlagoasCompetition::standings($t,$group,'liga'),'team'),$t['initial_groups']);
  if($id==='al-copa'){check($t['ties'][0]['a']===$ranks[0][0]&&$t['ties'][0]['b']===$ranks[1][1],'cruzamento A1 B2');check($t['ties'][1]['a']===$ranks[1][0]&&$t['ties'][1]['b']===$ranks[0][1],'cruzamento B1 A2');}
  foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=0;$m['ag']=0;}unset($m);Competition::nextPhase($t);check($t['phase']==='final','avança final');$finals=array_values(array_filter($t['matches'],fn($m)=>$m['phase']==='final'));check(count($finals)===($id==='al-copa'?1:2),'pernas da final');
  $order=array_column(AlagoasCompetition::standings($t,[$t['ties'][0]['a'],$t['ties'][0]['b']],null,true),'team');check(($id==='al-copa'?$finals[0]['home']:$finals[1]['home'])===$order[0],'melhor campanha manda final/volta');
  foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=0;$m['ag']=0;}unset($m);Competition::nextPhase($t);check($t['done']&&count(array_unique($t['final_ranking']))===count($t['teams']),'finalização e ranking íntegro');check($t['final_ranking'][0]===$t['champion'],'campeão primeiro');check(count($t['promoted'])===($id==='al-entrada'?1:0),'copa não promove');
  check(count($t['relegated'])===($id==='al-a1'?1:0),'somente A rebaixa');check(count(array_filter($t['matches'],fn($m)=>isset($m['penalties'])))>=2,'empates mata-mata resolvidos sem gol extra');
  $winner=$g;$winner['club']=$t['champion'];$winner['competition_results']=[$id=>Competition::result($t,$winner['club'])];$winner['competition_history']=[];$winner['history']=[];$titles=Trophies::collect($winner);check(count($titles)===1&&$titles[0]['kind']==='champion','resultado final gera um título principal');$winner['competition_history'][]=['season'=>1,'results'=>$winner['competition_results']];check(Trophies::collect($winner)===$titles,'mesmo título no histórico e resultado não duplica');
 }
});
$t=['teams'=>['A','B','C','D'],'lots'=>['A'=>3,'B'=>0,'C'=>1,'D'=>2],'matches'=>[]];
foreach([['A','B'],['C','A'],['B','D']] as [$a,$b]){$m=Schedule::match(1,$a,$b);$m['phase']='liga';$m['status']='finalizada';$m['hg']=1;$m['ag']=0;$m['home_red']=$a==='A'?5:0;$t['matches'][]=$m;}
$r=array_column(AlagoasCompetition::standings($t),'team');check(array_search('A',$r,true)<array_search('B',$r,true),'confronto direto antecede cartões entre dois empatados');
$m=Schedule::match(2,'D','C');$m+=['phase'=>'liga'];$m['status']='finalizada';$m['hg']=1;$m['ag']=0;$t['matches'][]=$m;$r=AlagoasCompetition::standings($t);check($r[0]['team']==='B','três ou mais empatados não aplicam confronto direto');
check(Competition::catalog()===$base&&Competition::clubs()===$clubs,'contexto não vaza novas identidades');
$f=WorldSeason::scope($g,fn()=>OpportunityForecast::estimate($g,$g['club']));check($f['guaranteed_games']===10&&$f['potential_extra_games']===6,'B+copa: mínimo separado do potencial');
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/alagoas-runtime/rules-tests.json',json_encode(['checks'=>$checks,'national_path_released'=>false,'base_edition'=>2026],JSON_PRETTY_PRINT));echo "OK: $checks verificações do adaptador FAF.\n";
