<?php
declare(strict_types=1);
/** Local-only integration pilot. No auto conversion of legacy or real careers. */
final class WorldSeason {
 public const MAX_PILOT_MATCHES=128;
 public const MAX_SYNC_EVENTS=128;
 public static function view(array $variables):void {
  $state=$variables['g']??[];
  if(isset($state['world_runtime']))try{self::scope($state,static fn()=>null);}catch(RuntimeException $e){http_response_code(503);$variables['g']=null;$variables['error']='O piloto local de calendário está indisponível nesta configuração. Seu save foi preservado.';$state=[];}
  self::scope($state,static function()use($variables){extract($variables,EXTR_SKIP);require dirname(__DIR__).'/app/view.php';});
 }
 public static function renderAgenda(array $g):void {
  echo '<details class="panel world-agenda"><summary>Agenda consolidada da temporada</summary><p class="subtle">Datas simuladas. Exibimos apenas jogos já definidos; avanços no mata-mata podem acrescentar partidas.</p><ol>';
  foreach($g['world_agenda']??[] as $entry){if(!in_array($g['club'],[$entry['home'],$entry['away']],true))continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$entry['date'],new DateTimeZone('UTC'));$name=$g['world_reference'][$entry['competition']]['definition']['name'];echo '<li><strong>'.e($date?$date->format('d/m/Y'):$entry['date']).'</strong> · '.e($name).'<br>'.e($entry['home']).' × '.e($entry['away']).'</li>';}
  echo '</ol></details>';
  $rows=array_filter($g['external_qualifications']??[],fn($r)=>$r['event']['club']===$g['club']);if($rows){echo '<details class="panel"><summary>Classificações externas registradas</summary><p>Uma classificação pendente ainda não é uma inscrição nem acrescenta jogos ou recompensas.</p><ul>';foreach($rows as $r){$e=$r['event'];$name=['br-copa'=>'Copa do Brasil','br-a'=>'Brasileirão Série A','br-c'=>'Brasileirão Série C'][$e['target']]??$e['target'];echo '<li>'.e($name).' · temporada '.e($e['target_edition']).' · '.e($r['state']==='consumed'?'Inscrição verificada no destino':'Pendente: destino ainda não executável').'</li>';}echo '</ul></details>';}
 }
 public static function enabled():bool{return config('APP_ENV')!=='production'&&config('RUMO10_WORLD_CALENDAR','0')==='1';}
 public static function scope(array $g,callable $operation):mixed {
  if(!isset($g['world_runtime']))return $operation();
  if(!self::enabled())throw new RuntimeException('Esta carreira de teste exige a flag local de calendário; nenhum save foi convertido.');
  if(($g['world_runtime']['schema']??0)!==1||empty($g['world_runtime']['fixture']))throw new RuntimeException('O piloto aceita apenas fixtures isoladas; inscrições oficiais permanecem bloqueadas.');
  $definitions=[];foreach($g['world_reference']??[] as $id=>$edition){if($id!==($edition['definition_id']??null)||!CompetitionReference::verify($edition))throw new RuntimeException('Contrato da edição ausente ou corrompido.');if(empty($edition['definition']['test_fixture']))throw new RuntimeException('Competição oficial ainda não habilitada no piloto.');$definitions[$id]=$edition['definition'];}
  foreach($g['memberships'] as $id=>$teams)if(!isset($definitions[$id]))throw new RuntimeException('Inscrição sem contrato de edição.');
  $clubs=[];foreach($definitions as $definition)foreach($definition['reference_clubs']??[] as $name=>$club){if(isset($clubs[$name])&&$clubs[$name]!==$club)throw new RuntimeException('Identidade local divergente.');$clubs[$name]=$club;}
  return Competition::referenceScope($definitions,$operation,$clubs);
 }
 /** Called only by isolated fixture builders, never by a player route/import. */
 public static function attachFixture(array $g,array $definitions,array $members,array $processing=[]):array {
  if(PHP_SAPI!=='cli'||!self::enabled()||config('RUMO10_WORLD_FIXTURES','0')!=='1')throw new RuntimeException('Fixtures esportivas só podem ser preparadas pelo teste CLI local.');
  if(empty($g['id']))throw new InvalidArgumentException('Identificador estável de carreira obrigatório.');
  $g['world_reference']=[];foreach($definitions as $id=>$c){if(empty($c['test_fixture'])||$id!==$c['id'])throw new InvalidArgumentException('Definição não identificada como fixture.');$g['world_reference'][$id]=CompetitionReference::pin($c,'fixture-2026-v1','simulation_adaptation');}
  $g['world_runtime']=['schema'=>1,'fixture'=>true,'start_month'=>1,'start_day'=>1,'rest_days'=>3];$g['memberships']=$members;$g['official']=true;$g['competition_history']=[];$g['story_seed']??='world-test-'.(string)$g['id'];$g['play_mode']??='normal';
  if($processing){$max=$processing['max_matches']??128;$budget=$processing['budget']??16;$ceiling=1152;if(($processing['sized_contract']??null)==='abcd-2026-v1'&&($g['national_trial']['schema']??null)==='abcd-closure-v1'&&array_keys($definitions)===['al-a1','al-entrada','al-copa','br-b','br-a','br-c','br-d']&&count($members['br-d'])===96)$ceiling=1673;if(($processing['sized_contract']??null)==='abcd-ne-2026-v1'&&($g['national_trial']['schema']??null)==='abcd-closure-v1'&&array_keys($definitions)===['al-a1','al-entrada','al-copa','br-b','br-a','br-c','br-d','br-nordeste']&&count($members['br-d'])===96&&count($members['br-nordeste'])===20)$ceiling=1733;if(($processing['sized_contract']??null)==='abcd-ne-cdb-2026-v1'&&($g['national_trial']['schema']??null)==='abcd-closure-v1'&&array_keys($definitions)===['al-a1','al-entrada','al-copa','br-b','br-a','br-c','br-d','br-nordeste','br-copa']&&count($members['br-d'])===96&&count($members['br-nordeste'])===20&&count($members['br-copa'])===126)$ceiling=1888;if(($processing['sized_contract']??null)==='abcd-ne-cdb-states-2026-v1'&&StateBatchTrial::validatesBudget($g,$definitions,$members))$ceiling=1970;if(($processing['sized_contract']??null)==='abcd-ne-cdb-state-expansion-2026-v1'&&StateExpansionTrial::validatesBudget($g,$definitions,$members))$ceiling=StateExpansionTrial::MAX_MATCHES;if(($processing['sized_contract']??null)==='abcd-ne-cdb-capixaba-2026-v1'&&CapixabaTrial::validatesBudget($g,$definitions,$members))$ceiling=CapixabaTrial::MAX_MATCHES;if(($processing['sized_contract']??null)==='abcd-ne-cdb-pe-rn-2026-v1'&&PernambucoPotiguarTrial::validatesBudget($g,$definitions,$members))$ceiling=PernambucoPotiguarTrial::MAX_MATCHES;if(($processing['sized_contract']??null)==='abcd-ne-cdb-df-2026-v1'&&CandangaoTrial::validatesBudget($g,$definitions,$members))$ceiling=CandangaoTrial::MAX_MATCHES;if(($processing['sized_contract']??null)==='abcd-ne-cdb-pr-2026-v1'&&ParanaenseTrial::validatesBudget($g,$definitions,$members))$ceiling=ParanaenseTrial::MAX_MATCHES;if(!is_int($max)||$max<128||$max>$ceiling||!is_int($budget)||$budget<1||$budget>32)throw new InvalidArgumentException('Orçamento incremental inválido.');$g['world_runtime']+=['max_matches'=>$max,'processing_budget'=>$budget];}
  return self::scope($g,function()use($g){self::start($g);return $g;});
 }
 private static function start(array &$g):void {
  unset($g['world_simultaneous_windows'],$g['world_pending']);
  if(isset($g['alagoas_trial'])){foreach($g['memberships']['al-entrada'] as $club)$g['squad_registrations'][$g['season']][$club]=SubTwentyThree::prepare(ClubSquad::players($club),2025+$g['season'],$g['season'],$club,$club===$g['club']?(int)$g['age']:null);}
  if(isset($g['national_trial']))NationalTrial::registerSquads($g);
  $g['competitions']=[];$g['competition_results']=[];$g['season_competitions']=[];$g['world_completed']=[];$g['world_recaps']=[];$g['world_agenda']=[];$g['world_cursor_date']=null;$g['world_current_key']=null;
  foreach(Competition::referencePairs() as [$up,$down]){if(!isset($g['memberships'][$up],$g['memberships'][$down])||array_intersect($g['memberships'][$up],$g['memberships'][$down]))throw new RuntimeException('Divisões da fixture sem par ou com clube duplicado.');if((Competition::catalog()[$up]['rules']['relegation']??0)!==(Competition::catalog()[$down]['rules']['access']??0))throw new RuntimeException('Slots de acesso e descenso incompatíveis.');}
  foreach($g['memberships'] as $id=>$teams){$c=Competition::catalog()[$id];if(count($teams)<2||count($teams)!==count(array_unique($teams)))throw new RuntimeException('Inscrição inviável.');foreach($teams as $club)if(!isset(Competition::clubs()[$club]))throw new RuntimeException('Clube inexistente no catálogo de referência.');
   $g['competitions'][$id]=Competition::tournament($id,$teams);if(in_array($g['club'],$teams,true))$g['season_competitions'][]=$id;
  }
  if(array_sum(array_map(fn($t)=>count($t['matches']),$g['competitions']))>($g['world_runtime']['max_matches']??self::MAX_PILOT_MATCHES))throw new RuntimeException('O lote excede o orçamento síncrono do piloto; nenhuma temporada foi ativada.');
  if(!$g['season_competitions'])throw new RuntimeException('Clube sem inscrição na edição.');self::next($g);
  if(isset($g['alagoas_trial']))foreach($g['season_competitions'] as $id)$g['competition_availability'][$id]=ExternalQualification::availability(true,true,true,false,isset($g['national_trial'])?['full_mandatory_calendar_not_yet_verified']:[]);
 }
 private static function key(array $g,string $id,array $m):string{return hash('sha256',json_encode([$g['id'],$g['season'],$id,$m['phase']??'liga',$m['round'],$m['home'],$m['away']],JSON_THROW_ON_ERROR));}
 private static function agenda(array &$g):array {
  $start=sprintf('%04d-%02d-%02d',2025+$g['season'],$g['world_runtime']['start_month'],$g['world_runtime']['start_day']);
  $planned=CompetitionCalendar::build((int)$g['id'],(int)$g['season'],$g['competitions'],$start,$g['world_runtime']['rest_days'],$g['world_agenda']);if(count($planned)>($g['world_runtime']['max_matches']??self::MAX_PILOT_MATCHES))throw new RuntimeException('Agenda excede o orçamento do piloto; ativação ampliada permanece bloqueada.');$g['world_agenda']=$planned;$map=[];
  foreach($g['competitions'] as $id=>$t)foreach($t['matches'] as $i=>$m)$map[self::key($g,$id,$m)]=[$id,$i];return $map;
 }
 private static function advance(array &$g,string $id):void {
  $t=&$g['competitions'][$id];if(!$t['done']&&!array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))Competition::nextPhase($t);
  if(!$t['done']||isset($g['world_completed'][$id]))return;
  $drop=(int)(Competition::catalog()[$id]['rules']['relegation']??0);if($drop)$t['relegated']=array_slice($t['initial_ranking'],-$drop);
  $g['world_completed'][$id]=true;
  if(in_array($id,$g['season_competitions'],true)){$g['competition_results'][$id]=Competition::result($t,$g['club']);Progression::competition($g,$id,$g['competition_results'][$id]);$g['world_recaps'][]=$id;}
  if(isset($g['national_trial'])&&$id==='br-b')NationalTrial::finish($g);
 }
 private static function pending(array &$g):bool {
  foreach($g['competitions'] as $id=>$t)if(isset($t['administrative_pending'])){$g['world_pending']=['competition'=>$id]+$t['administrative_pending'];$g['stage']='world_pending';return true;}unset($g['world_pending']);return false;
 }
 public static function next(array &$g):void {
  $processed=0;$map=null;
  for($guard=0;$guard<self::MAX_SYNC_EVENTS;$guard++){
   if(self::pending($g))return;
   if($g['world_recaps']){$id=array_shift($g['world_recaps']);self::activate($g,$id,null);$g['stage']='competicao_resumo';return;}
   if($map===null)$map=self::agenda($g);$pending=null;
   foreach($g['world_agenda'] as $entry){[$id,$index]=$map[$entry['key']];if($g['competitions'][$id]['matches'][$index]['status']!=='finalizada'){$pending=[$entry,$id,$index];break;}}
   if(!$pending){foreach($g['competitions'] as $id=>$_)self::advance($g,$id);if(self::pending($g))return;if($g['world_recaps'])continue;
    if(array_filter($g['competitions'],fn($t)=>!$t['done'])){$map=null;continue;}
    $g['next_memberships']=$g['memberships'];
    foreach(Competition::referencePairs() as [$up,$down]){$promoted=$g['competitions'][$down]['promoted'];$slots=Competition::catalog()[$up]['rules']['relegation']??0;if(count($promoted)!==$slots)throw new RuntimeException('Vagas incompatíveis entre divisões da fixture.');$relegated=$slots?array_slice($g['competitions'][$up]['initial_ranking'],-$slots):[];$g['competitions'][$up]['relegated']=$relegated;$g['next_memberships'][$up]=array_values(array_merge(array_diff($g['memberships'][$up],$relegated),$promoted));$g['next_memberships'][$down]=array_values(array_merge(array_diff($g['memberships'][$down],$promoted),$relegated));}
    if(isset($g['alagoas_trial']))AlagoasTrial::prepareNext($g);
    foreach($g['season_competitions'] as $id)$g['competition_results'][$id]=Competition::result($g['competitions'][$id],$g['club']);
    if(isset($g['national_trial']))NationalTrial::finish($g);
    if(($g['national_trial']['schema']??null)==='abc-closure-v1')NationalRenewal::close($g);
    if(($g['national_trial']['schema']??null)==='abcd-closure-v1')NationalPyramid::close($g);
    TeamStrength::nextSeason($g);if(isset($g['national_trial'])){foreach($g['competitions'] as &$closed)Competition::compactResults($closed,$g['club']);unset($closed);$g['matches']=$g['competitions'][$g['competition_id']]['matches'];}$g['stage']='resumo';$g['world_season_closed']=$g['season'];return;
   }
   [$entry,$id,$index]=$pending;
   if(isset($entry['simultaneous_group'])&&!isset($g['world_simultaneous_windows'][$entry['simultaneous_group']])){
    foreach($g['world_agenda'] as $parallel)if(($parallel['simultaneous_group']??null)===$entry['simultaneous_group']){[$parallelId,$parallelIndex]=$map[$parallel['key']];TeamStrength::snapshot($g['competitions'][$parallelId]['matches'][$parallelIndex],$g);}
    $g['world_simultaneous_windows'][$entry['simultaneous_group']]=['date'=>$entry['date'],'kickoff'=>$entry['kickoff'],'strengths_frozen'=>true];
   }
   $g['world_cursor_date']=$entry['date'];$m=&$g['competitions'][$id]['matches'][$index];
   if(in_array($g['club'],[$m['home'],$m['away']],true)){unset($m);self::activate($g,$id,$index);$g['world_current_key']=$entry['key'];$g['stage']='treino';return;}
   if(isset($g['world_runtime']['processing_budget'])&&$processed>=$g['world_runtime']['processing_budget']){$g['stage']='world_processing';$g['world_processing_batches']=($g['world_processing_batches']??0)+1;return;}
   Competition::simulate($m,$g);unset($m);$processed++;$count=count($g['competitions'][$id]['matches']);self::advance($g,$id);if(count($g['competitions'][$id]['matches'])!==$count)$map=null;
  }
  throw new RuntimeException('Limite de processamento do calendário; transação deve ser revertida.');
 }
 private static function activate(array &$g,string $id,?int $index):void {$t=$g['competitions'][$id];$g['competition_id']=$id;$g['competition_cursor']=array_search($id,$g['season_competitions'],true);$g['matches']=$t['matches'];$g['teams']=$t['teams'];$g['tier']=Competition::catalog()[$id]['tier'];if($index!==null)$g['round']=$t['matches'][$index]['round'];}
 public static function afterWeekend(array &$g):void {
  $id=$g['competition_id'];$i=Game::currentIndex($g);$m=$g['matches'][$i];if($m['status']!=='finalizada'||self::key($g,$id,$m)!==$g['world_current_key'])throw new RuntimeException('Partida fora da agenda persistida.');
  $g['competitions'][$id]['matches']=$g['matches'];self::advance($g,$id);self::next($g);
 }
 public static function renew(array &$g):void {
  if(isset($g['national_trial']))throw new RuntimeException('Continuidade nacional ainda indisponível; resultados preservados.');
  if(($g['world_season_closed']??null)!==$g['season']-1||array_filter($g['competitions'],fn($t)=>!$t['done']))throw new RuntimeException('Temporada anterior ainda não encerrada.');
  $g['competition_history'][]=['season'=>$g['season']-1,'results'=>$g['competition_results']];$g['memberships']=$g['next_memberships'];unset($g['next_memberships']);$g['club_strengths']=$g['next_club_strengths']??$g['club_strengths']??[];unset($g['next_club_strengths']);if(isset($g['alagoas_trial']))AlagoasTrial::renewReference($g);self::scope($g,static function()use(&$g){self::start($g);});
 }
}
