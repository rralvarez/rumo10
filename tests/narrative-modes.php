<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$choose=new ReflectionMethod(PlayMode::class,'option');$rows=[];$checks=0;$audit=0;
foreach(['equilibrio','protagonismo','coletivo'] as $plan)foreach(Rules::POSITIONS as $position)foreach(['normal','rapido','expresso'] as $mode)for($sample=0;$sample<3;$sample++){
 $g=Game::legacyCreate('Coorte narrativa','São Paulo','SP',$position);$g['story_seed']='cohort-'.$position.'-'.$sample;$g=Game::command($g,'handicap');$g['play_mode']=$mode;
 $commands=0;$trace=[];
 while($g['stage']!=='resumo' && $commands++<1000){
  if($mode!=='normal'){$g=Game::command($g,'simulate',['plan'=>$plan]);foreach($g['pace_report']['attempts'] as $a){if(!isset($a['narrative_selection']))throw new RuntimeException('Seleção automática sem auditoria');$trace[]=$a;$audit++;}}
  else{
   $advance=false;
   if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];$advance=$m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit'];}
   $g=Game::command($g,$advance?'advance':'choice',$advance?[]:['option'=>$choose->invoke(null,$g,$plan)]);
   if(!$advance){if(!isset($g['feedback']['narrative_selection']))throw new RuntimeException('Seleção Normal sem auditoria');$trace[]=$g['feedback'];}
  }
 }
 if($commands>=1000)throw new RuntimeException('Simulação não encerra');
 foreach($g['matches'] as $m)if(in_array($g['club'],[$m['home'],$m['away']],true)){$checks++;$own=$m['home']===$g['club']?$m['hg']:$m['ag'];if($m['stats']['goals']+$m['stats']['assists']>$own)throw new RuntimeException('Gol/assistência sem placar');}
 $growth=0;foreach($g['skills'] as $s)$growth+=$s['xp'];
 $rows[]=['plan'=>$plan,'position'=>$position,'mode'=>$mode,'sample'=>$sample,'summary'=>Game::summary($g),'remaining_xp'=>$growth,'skill_value_sum'=>array_sum(array_column($g['skills'],'value')),'decisions'=>count($trace),'selection_ids'=>array_values(array_unique(array_column($trace,'situation')))];
}
$means=[];foreach(['equilibrio','protagonismo','coletivo'] as $plan)foreach(['normal','rapido','expresso'] as $mode){$r=array_values(array_filter($rows,fn($x)=>$x['mode']===$mode && $x['plan']===$plan));$means[$plan][$mode]=['games'=>array_sum(array_column(array_column($r,'summary'),'games'))/count($r),'goals'=>array_sum(array_column(array_column($r,'summary'),'goals'))/count($r),'assists'=>array_sum(array_column(array_column($r,'summary'),'assists'))/count($r),'remaining_xp'=>array_sum(array_column($r,'remaining_xp'))/count($r),'skill_value_sum'=>array_sum(array_column($r,'skill_value_sum'))/count($r)];}
$events=array_sum(array_column(array_column($rows,'summary'),'goals'))+array_sum(array_column(array_column($rows,'summary'),'assists'));if(!$events)throw new RuntimeException('Coortes não exercitaram gols ou assistências');
file_put_contents(dirname(__DIR__).'/Docs/narrativa-v1/coortes-modos.json',json_encode(['seasons'=>count($rows),'checks'=>$checks,'automatic_audited_choices'=>$audit,'policy'=>'equilibrio/protagonismo/coletivo, método de escolha idêntico por Reflection; resultados random_int não semeados','means'=>$means,'rows'=>$rows,'note'=>'Amostra descritiva, não prova estatística de equivalência. Código usa as mesmas fórmulas; não há multiplicador por modo.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'OK: '.count($rows)." temporadas, $checks partidas reconciliadas, $audit decisões automáticas com seleção auditada.\n";
