<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');putenv('RUMOHUB_BENEFITS=0');require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;function continuityCheck(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$catalog=Competition::catalog();continuityCheck(count($catalog['am-a1']['teams'])===8,'Oito participantes oficiais');continuityCheck(count(array_unique($catalog['am-a1']['teams']))===8,'Sem duplicatas');
foreach($catalog['am-a1']['teams'] as $club)continuityCheck(isset(Competition::clubs()[$club]),'Participante conhecido '.$club);
$report=[];foreach($catalog as $id=>$c){$s=CompetitionContinuity::status($id);$report[]=['id'=>$id,'name'=>$c['name'],'status'=>$s];if(($c['rules']['access']??0)>0 && !CompetitionContinuity::upper($id))continuityCheck(!$s['valid'],'Acesso órfão não é novo destino '.$id);}
$previous=count(json_decode(file_get_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/competicoes-inventario.json'),true)['rows']);continuityCheck(count($catalog)===$previous+1,'Somente o campeonato indispensável acrescentado');
$members=array_map(fn($c)=>$c['teams'],$catalog);$champions=[];
for($year=1;$year<=5;$year++){
 $lower=Competition::tournament('am-entrada',$members['am-entrada']);Competition::finish($lower);$upper=Competition::tournament('am-a1',$members['am-a1']);Competition::finish($upper);
 $champions[]=['season'=>$year,'upper_champion'=>$upper['champion'],'promoted'=>$lower['promoted'],'relegated'=>$upper['relegated']];
 continuityCheck(count($upper['relegated'])===1,'Uma queda com proteção do campeão de turno');continuityCheck(!array_intersect($upper['am_winners'],$upper['relegated']),'Campeão de turno não rebaixado');
 $played=[];$calendar=[];foreach($upper['matches'] as $m){continuityCheck($m['status']==='finalizada','Partida concluída');foreach(['home','away'] as $side){$club=$m[$side];continuityCheck(empty($calendar[$m['round']][$club]),'Uma partida por rodada');$calendar[$m['round']][$club]=true;if($m['phase']==='liga')$played[$club]=($played[$club]??0)+1;}}
 continuityCheck(array_unique(array_values($played))===[7],'Cada clube joga sete partidas classificatórias');
 $up=$lower['promoted'];$down=$upper['relegated'];$members['am-a1']=array_values(array_merge(array_diff($members['am-a1'],$down),$up));$members['am-entrada']=array_values(array_merge(array_diff($members['am-entrada'],$up),$down));
 continuityCheck(count($members['am-a1'])===8&&count($members['am-entrada'])===7,'Pirâmide conserva tamanho');continuityCheck(in_array($lower['champion'],$members['am-a1'],true),'Campeão sobe de verdade');continuityCheck(CompetitionContinuity::status('am-entrada',$members)['valid'],'Continuidade da próxima edição simulada');
}
$legacyLower=Competition::tournament('am-entrada');Competition::finish($legacyLower);
$legacy=['official'=>true,'club'=>$legacyLower['champion'],'memberships'=>['am-entrada'=>$legacyLower['teams']],'next_memberships'=>['am-entrada'=>$legacyLower['teams']],'competitions'=>['am-entrada'=>$legacyLower],'history'=>[['season'=>1,'text'=>'Registro intocável']],'active_situation'=>['id'=>2000,'snapshot'=>['fixture'=>'não reescrever']]];
$oldLower=$legacy['competitions']['am-entrada'];$oldHistory=$legacy['history'];$oldScene=$legacy['active_situation'];CompetitionContinuity::reconcileAmazonas($legacy);continuityCheck(in_array($legacyLower['champion'],$legacy['next_memberships']['am-a1'],true),'Janela antiga conserva acesso confirmado');continuityCheck($legacy['competitions']['am-entrada']===$oldLower&&$legacy['history']===$oldHistory&&$legacy['active_situation']===$oldScene,'Reconciliação não reescreve B, histórico ou snapshot');$once=$legacy;CompetitionContinuity::reconcileAmazonas($legacy);continuityCheck($once===$legacy,'Reconciliação idempotente');
file_put_contents(dirname(__DIR__).'/Docs/estabilizacao-v04/continuidade.json',json_encode(['checks'=>$checks,'catalog'=>$report,'amazonas_five_seasons'=>$champions,'scope'=>'Catálogo efetivo do jogo com complemento local; sem edição do Hub, edições futuras simuladas pelo regulamento 2026.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo "OK: $checks verificações, cinco temporadas amazonenses e varredura de ".count($catalog)." competições.\n";
