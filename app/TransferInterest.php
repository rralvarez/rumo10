<?php
declare(strict_types=1);
final class TransferInterest {
 public const LABELS=['Sem interesse','Uma proposta','Duas propostas','Mercado nacional','Mercado continental','Mercado internacional','Destaque internacional'];
 public static function division(string $id):array {
  if(preg_match('/^br-([abcd])$/',$id,$m))return ['br',strpos('abcd',$m[1])+1];
  if(preg_match('/^sp-(a[1-4]|segunda)$/',$id,$m))return ['sp',$m[1]==='segunda'?5:(int)substr($m[1],1)];
  if($id==='am-a1'||$id==='am-entrada')return ['am',$id==='am-a1'?1:2];
  if(preg_match('/^([a-z]{2})-([12])$/',$id,$m))return [$m[1],(int)$m[2]];
  return [$id,null];
 }
 public static function scenarios(array $g,array $candidates,array $evaluation):array {
  $country=TransferMarket::country($g['club']);$region=FootballGeography::region($country);$members=$g['next_memberships']??$g['memberships']??[];$current=[];
  $clubDivisions=[];foreach($members as $id=>$teams){foreach($teams as $club)$clubDivisions[$club][]=$id;if(in_array($g['club'],$teams,true))$current[]=self::division($id);}
  $sets=['same'=>[],'up'=>[],'down'=>[],'continental'=>[],'continental_up'=>[],'europe'=>[]];
  foreach($candidates as $c){$club=$c['club'];$cc=$c['country']??$country;$division=self::division($c['competition']??'');
   if($cc===$country){foreach($clubDivisions[$club]??[] as $candidateId){$division=self::division($candidateId);foreach($current as [$pyramid,$level]){if($level===null||$division[0]!==$pyramid||$division[1]===null)continue;$key=match($division[1]-$level){0=>'same',-1=>'up',1=>'down',default=>null};if($key){$context=$c;$context['competition']=$candidateId;$context['tier']=Competition::catalog()[$candidateId]['tier'];$sets[$key][$club]=$context;}}}}
   elseif(FootballGeography::region($cc)===$region){if(abs($c['overall_delta']??0)<=10)$sets['continental'][$club]=$c;if(($c['overall_delta']??0)>0)$sets['continental_up'][$club]=$c;}
   if($cc!==$country&&in_array($cc,TransferMarket::EUROPE,true))$sets['europe'][$club]=$c;
  }
  $merit=max(0,min(1.5,(float)$evaluation['weighted_score']));$technical=array_sum(array_column($g['skills'],'value'))/max(1,count($g['skills']));$confidence=$evaluation['confidence'];
  $recipes=[[],[$candidates],[$candidates,$candidates],[$sets['same'],$sets['up'],$sets['down']],[$sets['same'],$sets['up'],$sets['continental']],[$sets['up'],$sets['continental_up'],$sets['europe']],[$sets['up'],$sets['continental_up'],$sets['europe']]];
  $mp=class_exists('BalanceService')?BalanceService::params('market.interest.scenarios'):[];
  $weights=[
   max(3,(int)round((int)($mp['weight_no_offer_base']??65)-(int)($mp['weight_no_offer_slope']??35)*$merit)),
   (int)($mp['weight_one_base']??30)+(int)round((int)($mp['weight_one_slope']??10)*$merit),
   (int)($mp['weight_two_base']??10)+(int)round((int)($mp['weight_two_slope']??18)*$merit),
   (int)($mp['weight_national_base']??5)+(int)round((int)($mp['weight_national_slope']??25)*$merit),
   (int)($mp['weight_continental_base']??2)+(int)round((int)($mp['weight_continental_slope']??15)*$merit),
   (int)($mp['weight_international_base']??1)+(int)round((int)($mp['weight_international_slope']??8)*$merit),
   (int)($mp['weight_star_base']??1)+(int)round((int)($mp['weight_star_slope']??5)*$merit)
  ];$rows=[];
  foreach(self::LABELS as $id=>$label){$eligible=$id===0||count(array_filter($recipes[$id]))>0;$reason=null;
   if($id===4&&(!$sets['continental']||$confidence<.20||$merit<.20)){$eligible=false;$reason='Sem alcance continental comprovado ou destinos compatíveis';}
   if($id>=5&&((!$sets['europe']&&!$sets['continental_up'])||$confidence<.40||$merit<.45)){$eligible=false;$reason='Sem alcance internacional comprovado ou destinos compatíveis';}
   if($id===6){$recipes[$id]=array_map(fn($set)=>array_filter($set,fn($c)=>($c['overall_delta']??0)>=8&&($c['overall']??0)>=70),$recipes[$id]);if($technical<75||$confidence<.60||$merit<.85||(!$recipes[$id][1]&&!$recipes[$id][2])){$eligible=false;$reason='Elite exige overall, amostra e evolução de patamar sustentados';}}
   $rows[]=['id'=>$id,'nome'=>$label,'peso'=>$eligible?$weights[$id]:0,'eligible'=>$eligible,'reason'=>$reason,'recipe'=>$recipes[$id]];
  }
  $total=array_sum(array_column($rows,'peso'));foreach($rows as &$r)$r['percent']=round(100*$r['peso']/$total,3);unset($r);return $rows;
 }
 public static function draw(array $g,?callable $rng=null):array {
  $evaluation=SeasonEvaluation::evaluate($g);
  if(!empty($g['official']))$candidates=TransferMarket::pool($g,false);
  else{$candidates=[];foreach(Rules::TEAMS as $club)if($club!==$g['club']){$ovr=TeamStrength::initial($club);$candidates[]=['club'=>$club,'tier'=>$g['tier'],'peso'=>30,'country'=>'BR','overall'=>$ovr,'overall_delta'=>round($ovr-TeamStrength::rating($g['club'],$g),1)];}}
  $scenarios=self::scenarios($g,$candidates,$evaluation);$chosen=Probability::weighted(array_values(array_filter($scenarios,fn($s)=>$s['peso']>0)),$rng);$offers=[];$seen=[];
  foreach($chosen['recipe'] as $set){$available=array_values(array_filter($set,fn($c)=>!isset($seen[$c['club']])));if(!$available)continue;$offer=Probability::weighted($available,$rng);$seen[$offer['club']]=true;$offer['forecast']=OpportunityForecast::estimate($g,$offer['club']);$offer['selected']=false;$offers[]=$offer;}
  foreach($scenarios as &$row)unset($row['recipe']);unset($row);
  return ['schema'=>2,'id'=>bin2hex(random_bytes(16)),'season'=>$g['season'],'scenario'=>$chosen['id'],'scenarios'=>$scenarios,'evaluation'=>$evaluation,'offers'=>$offers,'stay'=>['club'=>$g['club'],'overall'=>$g['next_club_strengths'][$g['club']]??TeamStrength::rating($g['club'],$g),'forecast'=>OpportunityForecast::estimate($g,$g['club'])],'candidate_count'=>count($candidates)];
 }
 public static function render(array $g):void {
  $draw=$g['market_draw'];$items=array_values(array_filter($draw['scenarios'],fn($s)=>$s['peso']>0));
  echo '<p>O mercado considera sua temporada, posição e tempo em campo. Você decide se aceita uma proposta ou permanece.</p><ul>';foreach($items as $s)echo '<li>'.e($s['nome']).' · '.e($s['percent']).'%</li>';echo '</ul>';
  Wheel::render($items,self::LABELS[$draw['scenario']],'market:'.$draw['id']);
  if(!$g['offers'])echo '<p>Nenhuma proposta se concretizou nesta janela.</p>';
  foreach($g['offers'] as $i=>$offer){echo '<article class="panel"><h3>'.e(ClubBadge::name($offer['club'])).'</h3>';TransferMarket::describe($offer);OpportunityForecast::render($offer['forecast']);form('/transferencia/responder',$g);echo '<input type="hidden" name="option" value="'.e($i).'"><button class="btn btn-primary" name="accept" value="yes">Aceitar proposta</button></form></article>';}
  echo '<article class="panel"><h3>Permanecer em '.e(ClubBadge::name($g['club'])).'</h3><p>Overall '.e($draw['stay']['overall']).'</p>';OpportunityForecast::render($draw['stay']['forecast']);form('/transferencia/responder',$g);echo '<button class="btn btn-outline-light" name="accept" value="no">Permanecer no meu clube</button></form></article>';
 }
}
