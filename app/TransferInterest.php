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
 public static function render(array $g): void {
      $draw = $g['market_draw'] ?? [];
      $scenarios = $draw['scenarios'] ?? [];
      $items = array_values(array_filter($scenarios, fn($s) => ($s['peso'] ?? 0) > 0));
      $scenarioId = (int)($draw['scenario'] ?? 0);
      $scenarioLabel = self::LABELS[$scenarioId] ?? 'Sem interesse';

      $season = (int)($g['season'] ?? 1);
      $club = (string)($g['club'] ?? '');
      $clubName = ClubBadge::name($club);

      // 1. Contexto e orientação sobre a janela
      echo '<p class="transfer-intro-text">O mercado considera sua temporada, minutagem e posição em campo. A nova temporada começará somente após sua decisão contratual: aceitar uma proposta oficial ou permanecer no <b>' . e($clubName) . '</b>.</p>';

      // 2. Roleta de Mercado (Wheel oficial)
      Wheel::render($items, $scenarioLabel, 'market:' . ($draw['id'] ?? 'default'));

      // 3. Painel do Cenário Sorteado (Significado do resultado)
      $scenarioDescriptions = [
          0 => 'Nesta janela de transferências, o mercado esteve retraído e não houve ofertas concretas de novos clubes. Permanecer no seu clube atual é o caminho legítimo e seguro para manter sequência de jogos, aprimorar seus atributos e consolidar sua evolução.',
          1 => 'Seu desempenho no ano atraiu a atenção do mercado e gerou 1 proposta oficial para seu contrato. Avalie as perspectivas esportivas antes de decidir.',
          2 => 'Sua regularidade e minutagem geraram 2 propostas oficiais. Compare a concorrência e o nível das equipes para definir seu futuro.',
          3 => 'Destaque no futebol brasileiro abriu portas em divisões nacionais, proporcionando oportunidades em diferentes níveis competitivos.',
          4 => 'Boa temporada com alcance continental no futebol sul-americano gerou propostas internacionais de clubes da região.',
          5 => 'Desempenho expressivo com projeção internacional abriu portas no exterior e mercados europeus ou de alto nível.',
          6 => 'Temporada de elite no patamar mais elevado atraiu as melhores oportunidades do mercado global.'
      ];
      $description = $scenarioDescriptions[$scenarioId] ?? 'O mercado apresentou propostas correspondentes ao seu perfil e momento esportivo.';

      echo '<div class="scenario-outcome-panel">';
      echo '<span class="tag">RESULTADO DA ROLETA</span>';
      echo '<h3>Cenário sorteado: ' . e($scenarioLabel) . '</h3>';
      echo '<p>' . e($description) . '</p>';
      echo '</div>';

      // 4. Painel de Probabilidades da Roleta
      echo '<div class="market-probabilities-panel">';
      echo '<h3>Probabilidades da Roleta de Mercado</h3>';
      echo '<p class="subtle">Estes percentuais representam os cenários elegíveis do sorteio na roleta de mercado com base na sua temporada e tempo de jogo, e não a probabilidade individual de contratação por cada clube.</p>';
      echo '<div class="market-scenarios-grid">';
      foreach ($items as $s) {
          $isWinner = ($s['id'] ?? null) === $scenarioId || ($s['nome'] ?? '') === $scenarioLabel;
          echo '<div class="market-scenario-card' . ($isWinner ? ' scenario-drawn' : '') . '">';
          echo '<div class="scenario-card-header">';
          echo '<span class="scenario-name">' . e($s['nome']) . '</span>';
          if ($isWinner) {
              echo '<span class="drawn-badge">Sorteado</span>';
          }
          echo '</div>';
          echo '<strong class="scenario-percent">' . e(number_format((float)($s['percent'] ?? 0), 1, ',', '.')) . '%</strong>';
          echo '</div>';
      }
      echo '</div>';
      echo '</div>';

      // 5. Ofertas ou Ausência de Propostas
      $offers = $g['offers'] ?? [];
      $stayClub = (string)($draw['stay']['club'] ?? $club);
      $stayName = ClubBadge::name($stayClub);
      $stayOverall = $draw['stay']['overall'] ?? (class_exists('TeamStrength') ? TeamStrength::rating($stayClub, $g) : 60);
      $stayForecast = $draw['stay']['forecast'] ?? (class_exists('OpportunityForecast') ? OpportunityForecast::estimate($g, $stayClub) : []);

      if (empty($offers) || $scenarioId === 0) {
          echo '<div class="no-offers-panel">';
          echo '<p class="no-offers-status">ℹ️ Nenhuma proposta se concretizou nesta janela.</p>';
          echo '<p class="no-offers-message">Mesmo sem novas propostas de transferência, seu vínculo com o <b>' . e($stayName) . '</b> permanece garantido e plenamente ativo. Continuar no clube é a decisão legítima para assegurar estabilidade, manter a confiança da comissão técnica e dar início à próxima temporada.</p>';
          echo '</div>';

          echo '<article class="panel transfer-stay-card stay-card-highlighted">';
          echo '<div class="offer-header">';
          ClubBadge::render($stayClub);
          echo '<div>';
          echo '<span class="tag">SEU CLUBE ATUAL · CONTINUIDADE</span>';
          echo '<h3>Permanecer em ' . e($stayName) . '</h3>';
          echo '</div>';
          echo '</div>';
          echo '<p class="offer-overall-line">Overall <b>' . e($stayOverall) . '</b> · Próxima temporada com contrato garantido</p>';
          if ($stayForecast) {
              OpportunityForecast::render($stayForecast);
          }
          echo '<div class="transfer-actions">';
          if (function_exists('form')) {
              form('/transferencia/responder', $g);
          } else {
              echo '<form method="post" action="/transferencia/responder">';
              echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
              echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
              echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
          }
          echo '<button class="btn btn-primary btn-lg btn-stay-priority" name="accept" value="no">Permanecer no meu clube</button>';
          echo '</form>';
          echo '</div>';
          echo '</article>';
      } else {
          echo '<div class="transfer-offers-section">';
          echo '<h3 class="transfer-offers-title">Propostas Oficiais Disponíveis (' . count($offers) . ')</h3>';
          echo '<p class="subtle">Avalie cada destino com atenção. Você pode aceitar uma das propostas abaixo ou decidir permanecer no seu clube atual.</p>';

          foreach ($offers as $i => $offer) {
              $offerClub = (string)($offer['club'] ?? '');
              $offerName = ClubBadge::name($offerClub);
              $forecast = $offer['forecast'] ?? (class_exists('OpportunityForecast') ? OpportunityForecast::estimate($g, $offerClub) : []);

              echo '<article class="panel transfer-offer-card">';
              echo '<div class="offer-header">';
              ClubBadge::render($offerClub);
              echo '<div>';
              echo '<span class="tag">OPÇÃO ' . sprintf('%02d', $i + 1) . '</span>';
              echo '<h3>' . e($offerName) . '</h3>';
              echo '</div>';
              echo '</div>';

              TransferMarket::describe($offer);

              if ($forecast) {
                  OpportunityForecast::render($forecast);
              }

              echo '<div class="transfer-actions">';
              if (function_exists('form')) {
                  form('/transferencia/responder', $g);
              } else {
                  echo '<form method="post" action="/transferencia/responder">';
                  echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
                  echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
                  echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
              }
              echo '<input type="hidden" name="option" value="' . e($i) . '">';
              echo '<button class="btn btn-primary btn-lg" name="accept" value="yes">Aceitar proposta</button>';
              echo '</form>';
              echo '</div>';
              echo '</article>';
          }

          echo '<article class="panel transfer-stay-card">';
          echo '<div class="offer-header">';
          ClubBadge::render($stayClub);
          echo '<div>';
          echo '<span class="tag">SEU CLUBE ATUAL</span>';
          echo '<h3>Permanecer em ' . e($stayName) . '</h3>';
          echo '</div>';
          echo '</div>';
          echo '<p class="offer-overall-line">Overall <b>' . e($stayOverall) . '</b> · Opção de renovação/continuidade</p>';
          if ($stayForecast) {
              OpportunityForecast::render($stayForecast);
          }
          echo '<div class="transfer-actions">';
          if (function_exists('form')) {
              form('/transferencia/responder', $g);
          } else {
              echo '<form method="post" action="/transferencia/responder">';
              echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
              echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
              echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
          }
          echo '<button class="btn btn-outline-light btn-lg" name="accept" value="no">Permanecer no meu clube</button>';
          echo '</form>';
          echo '</div>';
          echo '</article>';
          echo '</div>';
      }
 }
}
