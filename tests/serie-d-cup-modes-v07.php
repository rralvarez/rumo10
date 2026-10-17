<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');putenv('RUMO10_WORLD_CALENDAR=1');putenv('RUMO10_WORLD_FIXTURES=1');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;$results=[];function check(bool $v,string $why):void{global $checks;$checks++;if(!$v)throw new RuntimeException($why);}
$catalog=Competition::catalog();$clubs=Competition::clubs();
foreach(['meia'] as $i=>$position)foreach(['normal','rapido','expresso'] as $mode){
 $g=NationalTrial::createABCDWithNordeste(815000+$i,$mode);$steps=0;
 while($g['stage']!=='resumo'){
  if(++$steps>3000)throw new RuntimeException('Loop');
  if(in_array($g['stage'],['world_processing','competicao_resumo'],true))$g=Game::command($g,'continue');
  elseif($g['stage']==='treino'&&$mode!=='normal')$g=Game::command($g,'simulate',['plan'=>'equilibrio']);
  elseif($g['stage']==='competicao_resumo')$g=Game::command($g,'continue');
  elseif($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$g=$m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit']?Game::command($g,'advance'):Game::command($g,'choice',['option'=>0]);}
  else $g=Game::command($g,'choice',['option'=>0]);
  $g=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
 }
 check(count($g['world_completed'])===8,'todos os campeonatos encerrados');check(count($g['competition_results'])===4,'liga e copa do jogador concluídas');
 check($g['national_qualification_pending']['al|1']['granted_entries']===[],'sem vagas nacionais fictícias');
 $ledger=$g['reward_ledger']??[];$trophies=WorldSeason::scope($g,fn()=>Trophies::collect($g));
 $next=WorldSeason::scope($g,function()use($g){WorldSeason::next($g);return $g;});check(($next['reward_ledger']??[])===$ledger,'replay não concede XP');check(WorldSeason::scope($next,fn()=>Trophies::collect($next))===$trophies,'títulos sem duplicação');
 check($g['national_pyramid_closures']['1']['state']==='blocked','renovação nacional não finge sucesso');check(count($g['next_memberships']['al-copa'])===12,'copa futura por política simulada explícita');
 check(count($g['next_memberships']['al-a1'])===8&&count($g['next_memberships']['al-entrada'])===6,'tamanhos após acesso e descenso');check(!array_intersect($g['next_memberships']['al-a1'],$g['next_memberships']['al-entrada']),'sem duplicação de divisão');
 $busy=[];foreach($g['world_agenda'] as $e)foreach(['home','away'] as $side){$day=strtotime($e['date'].' UTC');check(!isset($busy[$e[$side]])||$day-$busy[$e[$side]]>=3*86400,'descanso');$busy[$e[$side]]=$day;}
 $bad=$g;$bad['world_reference']['al-a1']['definition']['name']='alterado';try{Game::command($bad,'offers');throw new RuntimeException('Hash aceito');}catch(RuntimeException $e){check($e->getMessage()!=='Hash aceito','edição corrompida bloqueada');}
 check(Competition::catalog()===$catalog&&Competition::clubs()===$clubs,'sem vazamento de escopo');
 $s=Game::summary($g);$results[]=['position'=>$position,'mode'=>$mode,'commands'=>$steps,'games'=>$s['games'],'fixtures'=>count($g['world_agenda']),'snapshot_bytes'=>strlen(json_encode($g))];
}
file_put_contents(dirname(__DIR__).'/Docs/mundializacao-v07/serie-d-renovacao-copas/modes.json',json_encode(['checks'=>$checks,'official_path_released'=>false,'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações, três modos e calendário nacional com regras FAF no piloto isolado.\n";
