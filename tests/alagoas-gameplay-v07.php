<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$g=AlagoasTrial::create(780001);$steps=0;$results=[];
for($year=1;$year<=5;$year++){$start=hrtime(true);$commands=0;
 while($g['stage']!=='resumo'){
  if(++$steps>10000)throw new RuntimeException('Loop');
  $g=WorldSeason::scope($g,function()use($g){if($g['stage']==='competicao_resumo')return Game::command($g,'continue');if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];return $m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']?Game::command($g,'advance'):Game::command($g,'choice',['option'=>0]);}return Game::command($g,'choice',['option'=>0]);});$commands++;
 }
 $stats=Game::summary($g);$trophies=WorldSeason::scope($g,fn()=>Trophies::collect($g));$results[]=['season'=>$g['season'],'club'=>$g['club'],'games'=>$stats['games'],'goals'=>$stats['goals'],'assists'=>$stats['assists'],'competitions'=>array_keys($g['competition_results']),'fixtures'=>count($g['world_agenda']),'commands'=>$commands,'elapsed_ms'=>round((hrtime(true)-$start)/1e6,2),'snapshot_bytes'=>strlen(json_encode($g)),'titles'=>count($trophies),'promotions'=>array_map(fn($t)=>$t['promoted'],$g['competitions']),'relegations'=>array_map(fn($t)=>$t['relegated'],$g['competitions'])];
 foreach($g['competitions'] as $t)if(!$t['done'])throw new RuntimeException('Premature season close');
 $seen=[];foreach($g['world_agenda'] as $e)foreach(['home','away'] as $side){$d=strtotime($e['date'].' UTC');if(isset($seen[$e[$side]])&&$d-$seen[$e[$side]]<3*86400)throw new RuntimeException('Rest violation');$seen[$e[$side]]=$d;}
 if($year<5){$g=Game::command($g,'offers');$choice=$year===2&&!empty($g['offers'])?['accept'=>'yes','option'=>0]:['accept'=>'no'];$g=Game::command($g,'transfer',$choice);$results[count($results)-1]['market_choice']=$choice['accept'];}
}
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/alagoas-runtime/gameplay-five-seasons.json',json_encode(['real_participants'=>true,'faf_2026_rules_adapter'=>true,'official_path_released'=>false,'national_qualification_pending'=>$g['national_qualification_pending'],'real_Game_command'=>true,'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo 'OK: five full gameplay seasons; '.array_sum(array_column($results,'games')).' player appearances. FAF rules tested; national continuity blocked, not released.'.PHP_EOL;
