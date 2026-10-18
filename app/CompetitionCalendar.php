<?php
declare(strict_types=1);
/** Dates are explicitly simulated, never an official federation schedule.
 * WorldSeason uses this planner; its ledger is persisted in the save transaction.
 */
final class CompetitionCalendar {
 public const VERSION='simulated-rest-v1';
 public static function build(int $save,int $season,array $tournaments,string $start,int $restDays=3,array $existing=[]):array {
  if($save<1||$season<1||$restDays<2||$restDays>7)throw new InvalidArgumentException('Parâmetros inválidos de calendário.');
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$start,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d')!==$start)throw new InvalidArgumentException('Data inicial inválida.');
  $jobs=[];$known=[];$roundClubs=[];foreach($tournaments as $id=>$t){if(($t['id']??'')!==$id)throw new InvalidArgumentException('Identidade divergente de competição.');
   foreach($t['matches']??[] as $m){if(empty($m['home'])||empty($m['away'])||$m['home']===$m['away']||empty($m['round']))throw new InvalidArgumentException('Partida inválida.');
    foreach(['home','away'] as $side){$roundKey=$id.'|'.$m['round'].'|'.$m[$side];if(isset($roundClubs[$roundKey]))throw new InvalidArgumentException('Clube repetido na mesma rodada da competição.');$roundClubs[$roundKey]=true;}
    $key=hash('sha256',json_encode([$save,$season,$id,$m['phase']??'liga',$m['round'],$m['home'],$m['away']],JSON_THROW_ON_ERROR));if(isset($known[$key]))throw new InvalidArgumentException('Partida duplicada.');$known[$key]=['competition'=>$id,'round'=>(int)$m['round'],'phase'=>$m['phase']??'liga','home'=>$m['home'],'away'=>$m['away']];
    $jobs[]=['key'=>$key,'competition'=>$id,'round'=>(int)$m['round'],'phase'=>$m['phase']??'liga','home'=>$m['home'],'away'=>$m['away']];
    if(isset($m['simultaneous_group'])){if(!is_string($m['simultaneous_group'])||$m['simultaneous_group']!==$id.'|'.($m['phase']??'liga').'|'.$m['round']||!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/',$m['kickoff']??''))throw new InvalidArgumentException('Grupo simultâneo inválido.');$meta=['simultaneous_group'=>$m['simultaneous_group'],'kickoff'=>$m['kickoff']];$jobs[array_key_last($jobs)]+=$meta;$known[$key]+=$meta;}
   }
  }
  usort($jobs,fn($a,$b)=>[$a['round'],$a['competition'],$a['key']]<=>[$b['round'],$b['competition'],$b['key']]);
  $ledger=[];$busy=[];$latestRound=[];
  foreach($existing as $entry){if(!isset($known[$entry['key']??'']))throw new InvalidArgumentException('Agenda anterior contém partida ausente; não apagar histórico.');if(isset($ledger[$entry['key']]))throw new InvalidArgumentException('Chave persistida duplicada.');foreach($known[$entry['key']] as $field=>$value)if(($entry[$field]??null)!==$value)throw new InvalidArgumentException('Agenda persistida diverge da partida.');$check=DateTimeImmutable::createFromFormat('!Y-m-d',$entry['date']??'',new DateTimeZone('UTC'));if(!$check||$check->format('Y-m-d')!==$entry['date'])throw new InvalidArgumentException('Data persistida inválida.');$ledger[$entry['key']]=$entry;}
  $simultaneous=[];foreach($ledger as $entry)if(isset($entry['simultaneous_group']))$simultaneous[$entry['simultaneous_group']][]=$entry;foreach($simultaneous as $key=>$entries){$expected=count(array_filter($jobs,fn($job)=>($job['simultaneous_group']??null)===$key));if(count($entries)!==$expected||count(array_unique(array_column($entries,'date')))!==1||count(array_unique(array_column($entries,'kickoff')))!==1)throw new InvalidArgumentException('Rodada simultânea persistida divergente.');}
  // Reserve every persisted date first: additions must never move earlier entries.
  foreach($ledger as $entry){$day=(new DateTimeImmutable($entry['date'],new DateTimeZone('UTC')))->getTimestamp();foreach(['home','away'] as $side)$busy[$entry[$side]][]=$day;}
  foreach($jobs as $job)if(!isset($ledger[$job['key']]))foreach($ledger as $entry)if($entry['competition']===$job['competition']&&$entry['round']>$job['round'])throw new InvalidArgumentException('Não inserir rodada anterior à agenda persistida.');
  foreach($busy as $dates){sort($dates);for($i=1;$i<count($dates);$i++)if($dates[$i]-$dates[$i-1]<$restDays*86400)throw new InvalidArgumentException('Agenda anterior viola descanso; revisão necessária.');}
  foreach($jobs as $job){$id=$job['competition'];$round=$job['round'];if(isset($ledger[$job['key']])){$latestRound[$id][$round]=max($latestRound[$id][$round]??0,strtotime($ledger[$job['key']]['date'].' UTC'));continue;}
   if(isset($job['simultaneous_group'])){
    $group=array_values(array_filter($jobs,fn($other)=>($other['simultaneous_group']??null)===$job['simultaneous_group']));$day=$date->getTimestamp();foreach($latestRound[$id]??[] as $r=>$last)if($r<$round)$day=max($day,$last+$restDays*86400);
    foreach($group as $member)if(isset($ledger[$member['key']]))throw new InvalidArgumentException('Grupo simultâneo parcialmente persistido.');
    while(true){$next=$day;foreach($group as $member)foreach(['home','away'] as $side)foreach($busy[$member[$side]]??[] as $booked)if(abs($booked-$day)<$restDays*86400)$next=max($next,$booked+$restDays*86400);if($next===$day)break;$day=$next;}
    if($day>$date->modify('+1 year -1 day')->getTimestamp())throw new RuntimeException('Rodada simultânea sem janela viável.');
    foreach($group as $member){$ledger[$member['key']]=$member+['date'=>gmdate('Y-m-d',$day),'date_basis'=>'simulation_adaptation','calendar_version'=>self::VERSION];foreach(['home','away'] as $side)$busy[$member[$side]][]=$day;}$latestRound[$id][$round]=$day;continue;
   }
   $day=$date->getTimestamp();foreach($latestRound[$id]??[] as $r=>$last)if($r<$round)$day=max($day,$last+$restDays*86400);
   while(true){$next=$day;foreach(['home','away'] as $side)foreach($busy[$job[$side]]??[] as $booked)if(abs($booked-$day)<$restDays*86400)$next=max($next,$booked+$restDays*86400);if($next===$day)break;$day=$next;}
   if($day>$date->modify('+1 year -1 day')->getTimestamp())throw new RuntimeException('Agenda excede o limite seguro; competição não pode ser ativada.');
   $entry=$job+['date'=>gmdate('Y-m-d',$day),'date_basis'=>'simulation_adaptation','calendar_version'=>self::VERSION];$ledger[$job['key']]=$entry;
   foreach(['home','away'] as $side)$busy[$job[$side]][]=$day;$latestRound[$id][$round]=max($latestRound[$id][$round]??0,$day);
  }
  $ledger=array_values($ledger);usort($ledger,fn($a,$b)=>[$a['date'],$a['competition'],$a['key']]<=>[$b['date'],$b['competition'],$b['key']]);return $ledger;
 }
}
