<?php
declare(strict_types=1);

/**
 * Roleta Anual de Qualidade de Mercado e Seleção de Propostas (R10-CAR-002).
 * - Schema 3: roleta de 5 faixas de patamar esportivo (Inferior, Equivalente, Superior, Destaque, Elite).
 * - Probabilidades normalizadas em 100%, determinadas exclusivamente pela última temporada concluída.
 * - Garantia de 3 propostas externas válidas e distintas + opção de permanência no clube atual.
 * - Busca progressiva preservando coerência esportiva e diversidade geográfica (nacional, continental e intercontinental global).
 * - Preserva compatibilidade total de renderização com saves persistidos em Schema 2 (CAR-001).
 */
final class TransferInterest {
    public const SCHEMA = 3;

    public const TIERS = [
        0 => 'Inferior',
        1 => 'Equivalente',
        2 => 'Superior',
        3 => 'Destaque',
        4 => 'Elite',
    ];

    public const LABELS = self::TIERS;

    public const LEGACY_LABELS = [
        'Sem interesse',
        'Uma proposta',
        'Duas propostas',
        'Mercado nacional',
        'Mercado continental',
        'Mercado internacional',
        'Destaque internacional',
    ];

    private const TIER_DESCRIPTIONS = [
        0 => 'A janela concentrou oportunidades em clubes de patamar inferior ou divisões de acesso, onde a concorrência tende a ser menor e há amplo espaço para recuperar minutagem e protagonismo.',
        1 => 'O mercado abriu propostas de patamar equivalente ao do seu clube atual, preservando competitividade semelhante em novos contextos nacionais ou internacionais.',
        2 => 'Seu desempenho na última temporada abriu portas em clubes de patamar superior, oferecendo avanço esportivo consistente e elencos mais qualificados.',
        3 => 'Temporada de forte impacto projetou seu nome para clubes de destaque nacional, continental e internacional, com ambição direta por títulos.',
        4 => 'Desempenho de alto nível aliado ao seu overall habilitou propostas na faixa de elite, reunindo potências do cenário continental e mundial.',
    ];

    public static function division(string $id): array {
        if (preg_match('/^br-([abcd])$/', $id, $m)) {
            return ['br', strpos('abcd', $m[1]) + 1];
        }
        if (preg_match('/^sp-(a[1-4]|segunda)$/', $id, $m)) {
            return ['sp', $m[1] === 'segunda' ? 5 : (int)substr($m[1], 1)];
        }
        if ($id === 'am-a1' || $id === 'am-entrada') {
            return ['am', $id === 'am-a1' ? 1 : 2];
        }
        if (preg_match('/^([a-z]{2})-([12])$/', $id, $m)) {
            return [$m[1], (int)$m[2]];
        }
        return [$id, null];
    }

    /**
     * Resolve o contexto de pirâmides/divisões e overalls de referência para classificação esportiva.
     */
    private static function classificationContext(array $g): array {
        $skills = $g['skills'] ?? [];
        $playerOvr = $skills ? array_sum(array_column($skills, 'value')) / max(1, count($skills)) : 50.0;
        $currentOvr = (float)($g['next_club_strengths'][$g['club']] ?? TeamStrength::rating($g['club'], $g));
        $originCountry = !empty($g['official']) ? TransferMarket::country((string)$g['club']) : 'BR';
        $members = $g['next_memberships'] ?? $g['memberships'] ?? [];

        $currentDivs = [];
        $clubDivs = [];
        foreach ($members as $id => $teams) {
            foreach ($teams as $club) {
                $clubDivs[$club][] = $id;
            }
            if (in_array($g['club'], $teams, true)) {
                $currentDivs[] = self::division($id);
            }
        }

        $originLeague = null;
        if (!empty($g['official'])) {
            $catalog = Competition::catalog();
            $originIds = $clubDivs[$g['club']] ?? (!empty($g['competition_id']) ? [$g['competition_id']] : []);
            $originCompId = LeagueStrength::primaryCompetition($originIds, $catalog);
            if ($originCompId !== null) {
                $originLeague = LeagueStrength::resolveCompetition($originCompId, $g, $catalog);
            }
        }

        return [$playerOvr, $currentOvr, $originCountry, $currentDivs, $clubDivs, $originLeague];
    }

    /**
     * Determina a classificação esportiva efetiva (0..4) de um clube candidato pelas suas próprias características,
     * combinando overall do clube, força da liga e divisão, nunca herdando artificialmente a faixa sorteada na roleta.
     */
    public static function effectiveTier(array $g, array $c, ?array $ctx = null): int {
        return LeagueStrength::assess($g, $c, $ctx)['tier'];
    }

    /**
     * Classifica todos os clubes candidatos em sua faixa esportiva efetiva (partição exata em 0..4).
     */
    private static function classifyCandidates(array $g, array $candidates): array {
        $ctx = self::classificationContext($g);
        [$playerOvr, $currentOvr] = $ctx;
        $buckets = [0 => [], 1 => [], 2 => [], 3 => [], 4 => []];
        $classifiedAll = [];

        foreach ($candidates as $c) {
            $club = (string)$c['club'];
            $assessment = LeagueStrength::assess($g, $c, $ctx);
            $tier = $assessment['tier'];
            $item = $c;
            $item['quality_tier'] = $tier;
            $item['quality_tier_label'] = self::TIERS[$tier];
            $item['league_strength'] = $assessment['target_league_strength'];
            $item['league_strength_status'] = $assessment['target_league_status'];
            $item['league_delta'] = $assessment['league_delta'];
            $item['composite_delta'] = $assessment['composite_delta'];
            $item['origin_competition'] = $assessment['origin_competition'];
            $item['origin_league_strength'] = $assessment['origin_league_strength'];
            $item['reason'] = $assessment['reason'];
            $item['sporting_assessment'] = $assessment;
            $buckets[$tier][$club] = $item;
            $classifiedAll[] = $item;
        }

        return [$buckets, $playerOvr, $currentOvr, $classifiedAll, $ctx];
    }

    /**
     * Normaliza os percentuais das faixas elegíveis para somarem exatamente 100.000% (e 100,0% em 1 casa).
     */
    private static function normalizePercentages(array &$rows): void {
        $total = array_sum(array_column($rows, 'peso'));
        if ($total <= 0) {
            foreach ($rows as &$r) {
                $r['percent'] = 0.0;
            }
            unset($r);
            return;
        }

        // Arredondamento por maiores restos em décimos de ponto percentual (soma exata 100.0)
        $tenthsTotal = 1000;
        $allocated = 0;
        $remainders = [];
        foreach ($rows as $idx => &$r) {
            if (($r['peso'] ?? 0) <= 0) {
                $r['percent'] = 0.0;
                continue;
            }
            $exactTenths = ($r['peso'] / $total) * $tenthsTotal;
            $floorTenths = (int)floor($exactTenths);
            $r['_tenths'] = $floorTenths;
            $allocated += $floorTenths;
            $remainders[$idx] = $exactTenths - $floorTenths;
        }
        unset($r);

        arsort($remainders);
        $left = $tenthsTotal - $allocated;
        foreach (array_keys($remainders) as $idx) {
            if ($left <= 0) {
                break;
            }
            $rows[$idx]['_tenths']++;
            $left--;
        }
        foreach ($rows as &$r) {
            if (isset($r['_tenths'])) {
                $r['percent'] = round($r['_tenths'] / 10.0, 1);
                unset($r['_tenths']);
            }
        }
        unset($r);
    }

    /**
     * Calcula as 5 faixas de qualidade e suas probabilidades normalizadas a partir da última temporada.
     */
    public static function scenarios(array $g, array $candidates, array $evaluation): array {
        $mp = class_exists('BalanceService') ? BalanceService::params('market.quality.tiers.v2') : [];
        [$buckets, $playerOvr, $currentOvr] = self::classifyCandidates($g, $candidates);

        // Usa exclusivamente o score da última temporada recém-concluída
        $seasonScore = max(0.0, min(1.35, (float)($evaluation['score'] ?? $evaluation['weighted_score'] ?? 0.35)));
        $sigma = max(0.12, (float)($mp['sigma'] ?? 0.28));
        $scale = max(20, (int)($mp['weight_scale'] ?? 100));
        $minWeight = max(1, (int)($mp['min_weight'] ?? 2));
        $destMinPlayer = (float)($mp['destaque_min_player_ovr'] ?? 54.0);
        $eliteMinPlayer = (float)($mp['elite_min_player_ovr'] ?? 68.0);

        $centers = [
            0 => (float)($mp['mu_inferior'] ?? 0.08),
            1 => (float)($mp['mu_equivalente'] ?? 0.40),
            2 => (float)($mp['mu_superior'] ?? 0.70),
            3 => (float)($mp['mu_destaque'] ?? 0.96),
            4 => (float)($mp['mu_elite'] ?? 1.22),
        ];

        $hasAnyCandidate = count($candidates) > 0;
        $rows = [];

        foreach (self::TIERS as $id => $label) {
            $eligible = true;
            $reason = null;

            if (!$hasAnyCandidate) {
                $eligible = ($id === 1);
                $reason = $eligible ? null : 'Catálogo sem clubes externos elegíveis nesta janela';
            } elseif ($id === 4) {
                if ($playerOvr < $eliteMinPlayer) {
                    $eligible = false;
                    $reason = 'Exige overall mínimo do atleta (' . (int)$eliteMinPlayer . '+) compatível com clubes de elite';
                } elseif (empty($buckets[4])) {
                    $eligible = false;
                    $reason = 'Nenhum destino de elite esportivamente compatível disponível nesta janela';
                }
            } elseif ($id === 3) {
                if ($playerOvr < $destMinPlayer) {
                    $eligible = false;
                    $reason = 'Exige overall mínimo do atleta (' . (int)$destMinPlayer . '+) compatível com clubes de destaque';
                } elseif (empty($buckets[3])) {
                    $eligible = false;
                    $reason = 'Nenhum destino de destaque compatível disponível nesta janela';
                }
            } elseif ($id === 2 && empty($buckets[2]) && empty($buckets[3])) {
                $eligible = false;
                $reason = $currentOvr >= 88.0
                    ? 'Você já atua no teto de força disponível no catálogo atual'
                    : 'Exige evolução de overall ou melhor desempenho para alcançar clubes acima do patamar atual';
            } elseif ($id === 0 && empty($buckets[0]) && empty($buckets[1])) {
                $eligible = false;
                $reason = 'Não há clubes abaixo do patamar atual no catálogo elegível';
            }

            $weight = 0;
            if ($eligible) {
                $z = ($seasonScore - $centers[$id]) / $sigma;
                $affinity = exp(-0.5 * $z * $z);
                $weight = max($minWeight, (int)round($minWeight + $scale * $affinity));
            }

            $rows[] = [
                'id' => $id,
                'nome' => $label,
                'tier' => $id + 1,
                'peso' => $weight,
                'eligible' => $eligible,
                'reason' => $reason,
                'pool' => $buckets[$id],
            ];
        }

        // Garantia: pelo menos uma faixa elegível caso todos os filtros acima tenham zerado pesos
        if (array_sum(array_column($rows, 'peso')) <= 0) {
            $rows[1]['eligible'] = true;
            $rows[1]['peso'] = 100;
            $rows[1]['reason'] = null;
        }

        self::normalizePercentages($rows);
        return $rows;
    }

    /**
     * Seleciona até 3 propostas distintas e válidas com busca progressiva por faixa e diversidade geográfica.
     */
    private static function selectOffers(array $g, int $drawnTier, array $scenarios, array $allCandidates, ?callable $rng): array {
        $mp = class_exists('BalanceService') ? BalanceService::params('market.quality.tiers.v2') : [];
        // Garantia contratual mínima de 3 propostas externas distintas; parâmetros < 3 nunca reduzem a meta.
        $targetCount = max(3, (int)($mp['target_offers'] ?? 3));
        $ctx = self::classificationContext($g);

        $poolsByTier = [];
        foreach ($scenarios as $s) {
            $poolsByTier[$s['id']] = $s['pool'] ?? [];
        }

        // Ordem progressiva de expansão preservando coerência esportiva (sem saltar de Inferior direto a Elite)
        $expansionOrder = match ($drawnTier) {
            0 => [[0], [1], [2], [3, 4]],
            1 => [[1], [2, 0], [3], [4]],
            2 => [[2], [1, 3], [0], [4]],
            3 => [[3], [2, 4], [1], [0]],
            default => [[4], [3], [2], [1, 0]],
        };

        $targetDelta = match ($drawnTier) {
            0 => -4.5,
            1 => 0.0,
            2 => 4.5,
            3 => 8.0,
            default => 11.0,
        };

        $passes = [];
        foreach ($expansionOrder as $stepIdx => $tierIds) {
            $merged = [];
            foreach ($tierIds as $tid) {
                foreach ($poolsByTier[$tid] ?? [] as $club => $cand) {
                    $merged[$club] ??= $cand;
                }
            }
            if ($merged !== []) {
                $passes[] = [
                    'kind' => $stepIdx === 0 ? 'exact' : ('adjacent_' . $stepIdx),
                    'items' => array_values($merged),
                ];
            }
        }

        // Passo final: todos os demais candidatos compatíveis ordenados por proximidade esportiva, mantendo classificação própria
        $fallbackItems = [];
        foreach ($allCandidates as $cand) {
            if (isset($cand['quality_tier'], $cand['sporting_assessment']) && isset(self::TIERS[(int)$cand['quality_tier']])) {
                $effTier = (int)$cand['quality_tier'];
            } else {
                $assessment = LeagueStrength::assess($g, $cand, $ctx);
                $effTier = $assessment['tier'];
                $cand['quality_tier'] = $effTier;
                $cand['quality_tier_label'] = self::TIERS[$effTier];
                $cand['league_strength'] = $assessment['target_league_strength'];
                $cand['league_strength_status'] = $assessment['target_league_status'];
                $cand['league_delta'] = $assessment['league_delta'];
                $cand['composite_delta'] = $assessment['composite_delta'];
                $cand['reason'] = $assessment['reason'];
                $cand['sporting_assessment'] = $assessment;
            }
            $fallbackItems[] = $cand;
        }
        usort($fallbackItems, static fn($a, $b) => (abs(($a['composite_delta'] ?? $a['overall_delta'] ?? 0) - $targetDelta) <=> abs(($b['composite_delta'] ?? $b['overall_delta'] ?? 0) - $targetDelta)) ?: strcmp($a['club'], $b['club']));
        if ($fallbackItems !== []) {
            $passes[] = ['kind' => 'compatible_fallback', 'items' => $fallbackItems];
        }

        $season = (int)($g['season'] ?? 1);
        // Referência geográfica (6/2 nas T1..T3; 3/3/2 nas T4+, com abertura mundial progressiva nas faixas altas)
        $geoBaseBoost = $season <= 3
            ? ['country' => 1.35, 'region' => 1.15, 'world' => ($drawnTier >= 3 ? 1.10 : 0.85)]
            : ['country' => 1.05, 'region' => 1.20, 'world' => ($drawnTier >= 3 ? 1.35 : 1.15)];

        $offers = [];
        $seenClubs = [$g['club'] => true];
        $bucketCounts = ['country' => 0, 'region' => 0, 'world' => 0];
        $countryCounts = [];
        $confedCounts = [];
        $expandedUsed = false;

        foreach ($passes as $pass) {
            while (count($offers) < $targetCount) {
                $available = array_values(array_filter($pass['items'], static fn($c) => !isset($seenClubs[$c['club']])));
                if ($available === []) {
                    break;
                }

                // Verifica se existe mais de um horizonte geográfico disponível neste passe
                $availableBuckets = array_unique(array_map(static fn($c) => $c['region'] ?? 'country', $available));
                $hasAlternativeBucket = count($availableBuckets) > 1;

                $weighted = [];
                foreach ($available as $cand) {
                    $bucket = $cand['region'] ?? 'country';
                    $country = $cand['country'] ?? 'BR';
                    $confed = $cand['confederation'] ?? FootballGeography::region($country);
                    $deltaDist = abs(((float)($cand['overall_delta'] ?? 0.0)) - $targetDelta);

                    $w = max(4.0, (float)($cand['peso'] ?? 20) + max(0.0, 14.0 - $deltaDist * 1.4));
                    $w *= ($geoBaseBoost[$bucket] ?? 1.0);

                    // Diversidade geográfica ativa: evita concentração excessiva em um único bloco/país/confederação
                    if ($hasAlternativeBucket) {
                        if (($bucketCounts[$bucket] ?? 0) >= 2) {
                            $w *= 0.22;
                        } elseif (($bucketCounts[$bucket] ?? 0) === 0 && count($offers) >= 1) {
                            $w *= 1.80;
                        }
                    }
                    if ($bucket !== 'country' && ($countryCounts[$country] ?? 0) >= 1) {
                        $w *= 0.20;
                    }
                    if ($bucket === 'world' && ($confedCounts[$confed] ?? 0) >= 1) {
                        $w *= 0.45;
                    }

                    $cand['peso'] = max(1, (int)round($w));
                    $weighted[] = $cand;
                }

                $picked = Probability::weighted($weighted, $rng);
                $club = $picked['club'];
                $seenClubs[$club] = true;

                $b = $picked['region'] ?? 'country';
                $cCountry = $picked['country'] ?? 'BR';
                $cConfed = $picked['confederation'] ?? FootballGeography::region($cCountry);
                $bucketCounts[$b] = ($bucketCounts[$b] ?? 0) + 1;
                $countryCounts[$cCountry] = ($countryCounts[$cCountry] ?? 0) + 1;
                $confedCounts[$cConfed] = ($confedCounts[$cConfed] ?? 0) + 1;

                if ($pass['kind'] !== 'exact') {
                    $expandedUsed = true;
                }

                // Classificação esportiva efetiva do clube ofertado (nunca herda $drawnTier por fallback)
                if (isset($picked['quality_tier'], $picked['sporting_assessment']) && isset(self::TIERS[(int)$picked['quality_tier']])) {
                    $offerTier = (int)$picked['quality_tier'];
                } else {
                    $assessment = LeagueStrength::assess($g, $picked, $ctx);
                    $offerTier = $assessment['tier'];
                    $picked['quality_tier'] = $offerTier;
                    $picked['quality_tier_label'] = self::TIERS[$offerTier];
                    $picked['league_strength'] = $assessment['target_league_strength'];
                    $picked['league_strength_status'] = $assessment['target_league_status'];
                    $picked['league_delta'] = $assessment['league_delta'];
                    $picked['composite_delta'] = $assessment['composite_delta'];
                    $picked['reason'] = $assessment['reason'];
                    $picked['sporting_assessment'] = $assessment;
                }
                if ($offerTier !== $drawnTier) {
                    $expandedUsed = true;
                }
                $picked['tier_match'] = ($offerTier === $drawnTier && $pass['kind'] === 'exact') ? 'exact' : $pass['kind'];
                $picked['forecast'] = OpportunityForecast::estimate($g, $club);
                $picked['selected'] = false;
                $offers[] = $picked;
            }

            if (count($offers) >= $targetCount) {
                break;
            }
        }

        $shortfall = count($offers) < $targetCount;
        $shortfallCause = null;
        if ($shortfall) {
            $shortfallCause = count($allCandidates) === 0
                ? 'Nenhum clube externo com continuidade válida e overall esportivamente compatível está disponível nesta janela.'
                : sprintf(
                    'O catálogo elegível comporta apenas %d destino(s) válido(s) e esportivamente compatível(is) distinto(s) do seu clube atual.',
                    count($offers)
                );
        }

        return [
            'offers' => $offers,
            'expanded_search' => $expandedUsed,
            'guarantee' => [
                'target' => $targetCount,
                'delivered' => count($offers),
                'fulfilled' => !$shortfall,
                'exceptional_shortfall' => $shortfall,
                'cause' => $shortfallCause,
            ],
            'geography' => [
                'buckets' => $bucketCounts,
                'countries' => $countryCounts,
                'confederations' => $confedCounts,
            ],
        ];
    }

    public static function draw(array $g, ?callable $rng = null): array {
        $evaluation = SeasonEvaluation::evaluate($g);
        $currentOvr = (float)($g['next_club_strengths'][$g['club']] ?? TeamStrength::rating($g['club'], $g));

        if (!empty($g['official'])) {
            $candidates = TransferMarket::pool($g, false, $rng);
        } else {
            $candidates = [];
            foreach ($g['teams'] ?? Rules::TEAMS as $club) {
                if ($club === $g['club']) {
                    continue;
                }
                $ovr = (float)TeamStrength::rating($club, $g);
                $candidates[] = [
                    'club' => $club,
                    'tier' => $g['tier'] ?? 1,
                    'peso' => 30,
                    'country' => 'BR',
                    'confederation' => 'CONMEBOL',
                    'region' => 'country',
                    'international' => false,
                    'overall' => round($ovr, 1),
                    'overall_delta' => round($ovr - $currentOvr, 1),
                    'reason' => 'A proposta considera o desempenho da última temporada e o equilíbrio da liga.',
                ];
            }
        }

        $scenarios = self::scenarios($g, $candidates, $evaluation);
        $eligibleScenarios = array_values(array_filter($scenarios, static fn($s) => ($s['peso'] ?? 0) > 0));
        $chosen = Probability::weighted($eligibleScenarios, $rng);
        $drawnTier = (int)$chosen['id'];

        $selection = self::selectOffers($g, $drawnTier, $scenarios, $candidates, $rng);
        $distribution = TransferMarket::distribution($g, $selection['offers']);

        foreach ($scenarios as &$row) {
            unset($row['pool']);
        }
        unset($row);

        return [
            'schema' => self::SCHEMA,
            'id' => bin2hex(random_bytes(16)),
            'season' => (int)$g['season'],
            'scenario' => $drawnTier,
            'quality_tier' => $drawnTier,
            'quality_tier_label' => self::TIERS[$drawnTier],
            'scenarios' => $scenarios,
            'evaluation' => $evaluation,
            'offers' => $selection['offers'],
            'expanded_search' => $selection['expanded_search'],
            'offer_guarantee' => $selection['guarantee'],
            'geography' => $selection['geography'],
            'distribution' => $distribution,
            'stay' => [
                'club' => $g['club'],
                'overall' => round($currentOvr, 1),
                'forecast' => OpportunityForecast::estimate($g, $g['club']),
            ],
            'candidate_count' => count($candidates),
        ];
    }

    public static function render(array $g): void {
        $draw = $g['market_draw'] ?? [];
        // Preserva renderização fiel de saves antigos já abertos no Schema 2 (CAR-001)
        if (($draw['schema'] ?? 2) === 2 && !isset($draw['quality_tier'])) {
            self::renderSchema2($g, $draw);
            return;
        }

        $scenarios = $draw['scenarios'] ?? [];
        $wheelItems = array_values(array_filter($scenarios, static fn($s) => ($s['peso'] ?? 0) > 0));
        $tierId = (int)($draw['quality_tier'] ?? $draw['scenario'] ?? 1);
        $tierLabel = $draw['quality_tier_label'] ?? (self::TIERS[$tierId] ?? 'Equivalente');
        $club = (string)($g['club'] ?? '');
        $clubName = ClubBadge::name($club);
        $eval = $draw['evaluation'] ?? SeasonEvaluation::evaluate($g);

        // 1. Orientação inicial
        echo '<p class="transfer-intro-text">A roleta anual define o <b>patamar esportivo</b> das propostas com base exclusivamente na sua última temporada concluída. A nova temporada começará somente após sua decisão contratual: aceitar uma das propostas oficiais ou permanecer no <b>' . e($clubName) . '</b>.</p>';

        // 2. Painel de Avaliação da Última Temporada
        $evalGames = (int)($eval['games'] ?? 0);
        $evalMinutes = (int)($eval['totals']['minutes'] ?? 0);
        $evalAvg = number_format((float)($eval['average'] ?? 6.0), 2, ',', '.');
        $evalGoals = (int)($eval['totals']['goals'] ?? 0);
        $evalAssists = (int)($eval['totals']['assists'] ?? 0);
        $evalTackles = (int)($eval['totals']['tackles'] ?? 0);
        $evalTitles = (int)($eval['titles_count'] ?? 0);
        $evalBandLabel = (string)($eval['band_label'] ?? 'Temporada avaliada');
        $evalScore100 = (int)($eval['score_100'] ?? round(min(100, max(0, ((float)($eval['score'] ?? 0.4)) / 1.25 * 100))));

        echo '<section class="season-eval-market-panel" aria-label="Avaliação da última temporada">';
        echo '<div class="season-eval-header">';
        echo '<div><span class="tag">AVALIAÇÃO DA TEMPORADA ' . (int)($eval['season'] ?? $g['season'] ?? 1) . '</span>';
        echo '<h3>' . e($evalBandLabel) . '</h3></div>';
        echo '<div class="season-eval-badge" title="Índice de desempenho da temporada recém-concluída"><strong>' . $evalScore100 . '</strong><span>/100</span></div>';
        echo '</div>';
        echo '<div class="season-eval-metrics">';
        echo '<div><strong>' . $evalAvg . '</strong><span>Nota média</span></div>';
        echo '<div><strong>' . $evalGames . '</strong><span>' . ($evalGames === 1 ? 'Partida' : 'Partidas') . '</span></div>';
        echo '<div><strong>' . $evalMinutes . '′</strong><span>Minutos</span></div>';
        echo '<div><strong>' . $evalGoals . '</strong><span>' . ($evalGoals === 1 ? 'Gol' : 'Gols') . '</span></div>';
        echo '<div><strong>' . $evalAssists . '</strong><span>' . ($evalAssists === 1 ? 'Assistência' : 'Assistências') . '</span></div>';
        if (!empty($eval['is_defensive']) || $evalTackles > 0) {
            echo '<div><strong>' . $evalTackles . '</strong><span>' . ($evalTackles === 1 ? 'Desarme' : 'Desarmes') . '</span></div>';
        }
        echo '<div><strong>' . $evalTitles . '</strong><span>' . ($evalTitles === 1 ? 'Título no ano' : 'Títulos no ano') . '</span></div>';
        echo '</div>';
        echo '<p class="subtle season-eval-footnote">As probabilidades abaixo consideram apenas a temporada recém-concluída. Temporadas anteriores permanecem preservadas no seu histórico de carreira.</p>';
        echo '</section>';

        // 3. Roleta de Qualidade (Wheel oficial)
        if ($wheelItems !== []) {
            Wheel::render($wheelItems, $tierLabel, 'market:' . ($draw['id'] ?? 'default'));
        }

        // 4. Resultado da Roleta
        $description = self::TIER_DESCRIPTIONS[$tierId] ?? 'O mercado apresentou propostas correspondentes ao seu patamar esportivo.';
        echo '<div class="scenario-outcome-panel">';
        echo '<span class="tag">RESULTADO DA ROLETA · PATAMAR DE QUALIDADE</span>';
        echo '<h3>Faixa sorteada: ' . e($tierLabel) . '</h3>';
        echo '<p>' . e($description) . '</p>';
        if (!empty($draw['expanded_search'])) {
            echo '<p class="subtle" style="margin-top:8px;">Para completar as oportunidades válidas com coerência esportiva, a seleção incluiu clubes compatíveis de faixa adjacente.</p>';
        }
        echo '</div>';

        // 5. Probabilidades de cada uma das 5 faixas
        echo '<div class="market-probabilities-panel">';
        echo '<h3>Probabilidades por Faixa de Qualidade</h3>';
        echo '<p class="subtle">Distribuição normalizada em 100% segundo o desempenho da última temporada e a compatibilidade esportiva de overall.</p>';
        echo '<div class="market-scenarios-grid">';
        foreach ($scenarios as $s) {
            $isEligible = !empty($s['eligible']) && ($s['peso'] ?? 0) > 0;
            $isWinner = $isEligible && ((int)($s['id'] ?? -1) === $tierId || ($s['nome'] ?? '') === $tierLabel);
            echo '<div class="market-scenario-card' . ($isWinner ? ' scenario-drawn' : '') . (!$isEligible ? ' scenario-ineligible' : '') . '">';
            echo '<div class="scenario-card-header">';
            echo '<span class="scenario-name">' . e($s['nome']) . '</span>';
            if ($isWinner) {
                echo '<span class="drawn-badge">Sorteado</span>';
            } elseif (!$isEligible) {
                echo '<span class="ineligible-badge">Bloqueado</span>';
            }
            echo '</div>';
            echo '<strong class="scenario-percent">' . e(number_format((float)($s['percent'] ?? 0), 1, ',', '.')) . '%</strong>';
            if (!$isEligible && !empty($s['reason'])) {
                echo '<small class="scenario-reason">' . e($s['reason']) . '</small>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // 6. Propostas Externas + Opção de Permanência
        $offers = $g['offers'] ?? ($draw['offers'] ?? []);
        $guarantee = $draw['offer_guarantee'] ?? ['exceptional_shortfall' => false, 'cause' => null];
        $stayClub = (string)($draw['stay']['club'] ?? $club);
        $stayName = ClubBadge::name($stayClub);
        $stayOverall = $draw['stay']['overall'] ?? (class_exists('TeamStrength') ? TeamStrength::rating($stayClub, $g) : 60);
        $stayForecast = $draw['stay']['forecast'] ?? (class_exists('OpportunityForecast') ? OpportunityForecast::estimate($g, $stayClub) : []);

        if (!empty($guarantee['exceptional_shortfall']) && !empty($guarantee['cause'])) {
            echo '<div class="no-offers-panel">';
            echo '<p class="no-offers-status">ℹ️ Condição excepcional de catálogo registrada</p>';
            echo '<p class="no-offers-message">' . e($guarantee['cause']) . '</p>';
            echo '</div>';
        }

        echo '<div class="transfer-offers-section">';
        if ($offers !== []) {
            echo '<h3 class="transfer-offers-title">Clubes Interessados (' . count($offers) . ')</h3>';
            echo '<p class="subtle">Avalie o overall, a competição, o horizonte geográfico e o espaço projetado em cada clube, ou opte por permanecer na sua equipe atual.</p>';

            foreach ($offers as $i => $offer) {
                $offerClub = (string)($offer['club'] ?? '');
                $offerName = ClubBadge::name($offerClub);
                $offerTierLabel = $offer['quality_tier_label'] ?? $tierLabel;
                $forecast = $offer['forecast'] ?? (class_exists('OpportunityForecast') ? OpportunityForecast::estimate($g, $offerClub) : []);

                echo '<article class="panel transfer-offer-card">';
                echo '<div class="offer-header">';
                ClubBadge::render($offerClub);
                echo '<div>';
                echo '<span class="tag">PROPOSTA ' . sprintf('%02d', $i + 1) . ' · PATAMAR ' . e(mb_strtoupper($offerTierLabel, 'UTF-8')) . '</span>';
                echo '<h3>' . e($offerName) . '</h3>';
                echo '</div>';
                echo '</div>';

                TransferMarket::describe($offer, false);

                if ($forecast) {
                    OpportunityForecast::render($forecast);
                }

                echo '<div class="transfer-actions">';
                self::openResponseForm($g);
                echo '<input type="hidden" name="option" value="' . e($i) . '">';
                echo '<button class="btn btn-primary btn-lg" name="accept" value="yes">Aceitar proposta</button>';
                echo '</form>';
                echo '</div>';
                echo '</article>';
            }
        }

        echo '<article class="panel transfer-stay-card' . ($offers === [] ? ' stay-card-highlighted' : '') . '">';
        echo '<div class="offer-header">';
        ClubBadge::render($stayClub);
        echo '<div>';
        echo '<span class="tag">SEU CLUBE ATUAL · CONTINUIDADE</span>';
        echo '<h3>Permanecer em ' . e($stayName) . '</h3>';
        echo '</div>';
        echo '</div>';
        echo '<p class="offer-overall-line">Overall <b>' . e($stayOverall) . '</b> · Contrato de renovação/permanência garantido</p>';
        if ($stayForecast) {
            OpportunityForecast::render($stayForecast);
        }
        echo '<div class="transfer-actions">';
        self::openResponseForm($g);
        echo '<button class="btn ' . ($offers === [] ? 'btn-primary btn-stay-priority' : 'btn-outline-light') . ' btn-lg" name="accept" value="no">Permanecer no meu clube</button>';
        echo '</form>';
        echo '</div>';
        echo '</article>';
        echo '</div>';
    }

    private static function openResponseForm(array $g): void {
        if (function_exists('form')) {
            form('/transferencia/responder', $g);
        } else {
            echo '<form method="post" action="/transferencia/responder">';
            echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
            echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
            echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
        }
    }

    /**
     * Renderizador preservado para saves antigos já persistidos na etapa 'oferta' sob o Schema 2 (CAR-001).
     */
    private static function renderSchema2(array $g, array $draw): void {
        $scenarios = $draw['scenarios'] ?? [];
        $items = array_values(array_filter($scenarios, static fn($s) => ($s['peso'] ?? 0) > 0));
        $scenarioId = (int)($draw['scenario'] ?? 0);
        $scenarioLabel = self::LEGACY_LABELS[$scenarioId] ?? 'Sem interesse';
        $club = (string)($g['club'] ?? '');
        $clubName = ClubBadge::name($club);

        echo '<p class="transfer-intro-text">O mercado considera sua temporada, minutagem e posição em campo. A nova temporada começará somente após sua decisão contratual: aceitar uma proposta oficial ou permanecer no <b>' . e($clubName) . '</b>.</p>';
        Wheel::render($items, $scenarioLabel, 'market:' . ($draw['id'] ?? 'default'));

        $scenarioDescriptions = [
            0 => 'Nesta janela de transferências, o mercado esteve retraído e não houve ofertas concretas de novos clubes. Permanecer no seu clube atual é o caminho legítimo e seguro para manter sequência de jogos, aprimorar seus atributos e consolidar sua evolução.',
            1 => 'Seu desempenho no ano atraiu a atenção do mercado e gerou 1 proposta oficial para seu contrato. Avalie as perspectivas esportivas antes de decidir.',
            2 => 'Sua regularidade e minutagem geraram 2 propostas oficiais. Compare a concorrência e o nível das equipes para definir seu futuro.',
            3 => 'Destaque no futebol brasileiro abriu portas em divisões nacionais, proporcionando oportunidades em diferentes níveis competitivos.',
            4 => 'Boa temporada com alcance continental no futebol sul-americano gerou propostas internacionais de clubes da região.',
            5 => 'Desempenho expressivo com projeção internacional abriu portas no exterior e mercados europeus ou de alto nível.',
            6 => 'Temporada de elite no patamar mais elevado atraiu as melhores oportunidades do mercado global.',
        ];
        $description = $scenarioDescriptions[$scenarioId] ?? 'O mercado apresentou propostas correspondentes ao seu perfil e momento esportivo.';

        echo '<div class="scenario-outcome-panel">';
        echo '<span class="tag">RESULTADO DA ROLETA</span>';
        echo '<h3>Cenário sorteado: ' . e($scenarioLabel) . '</h3>';
        echo '<p>' . e($description) . '</p>';
        echo '</div>';

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
            echo '<div><span class="tag">SEU CLUBE ATUAL · CONTINUIDADE</span><h3>Permanecer em ' . e($stayName) . '</h3></div>';
            echo '</div>';
            echo '<p class="offer-overall-line">Overall <b>' . e($stayOverall) . '</b> · Próxima temporada com contrato garantido</p>';
            if ($stayForecast) {
                OpportunityForecast::render($stayForecast);
            }
            echo '<div class="transfer-actions">';
            self::openResponseForm($g);
            echo '<button class="btn btn-primary btn-lg btn-stay-priority" name="accept" value="no">Permanecer no meu clube</button></form></div></article>';
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
                echo '<div><span class="tag">OPÇÃO ' . sprintf('%02d', $i + 1) . '</span><h3>' . e($offerName) . '</h3></div>';
                echo '</div>';
                TransferMarket::describe($offer, false);
                if ($forecast) {
                    OpportunityForecast::render($forecast);
                }
                echo '<div class="transfer-actions">';
                self::openResponseForm($g);
                echo '<input type="hidden" name="option" value="' . e($i) . '">';
                echo '<button class="btn btn-primary btn-lg" name="accept" value="yes">Aceitar proposta</button></form></div></article>';
            }

            echo '<article class="panel transfer-stay-card">';
            echo '<div class="offer-header">';
            ClubBadge::render($stayClub);
            echo '<div><span class="tag">SEU CLUBE ATUAL</span><h3>Permanecer em ' . e($stayName) . '</h3></div>';
            echo '</div>';
            echo '<p class="offer-overall-line">Overall <b>' . e($stayOverall) . '</b> · Opção de renovação/continuidade</p>';
            if ($stayForecast) {
                OpportunityForecast::render($stayForecast);
            }
            echo '<div class="transfer-actions">';
            self::openResponseForm($g);
            echo '<button class="btn btn-outline-light btn-lg" name="accept" value="no">Permanecer no meu clube</button></form></div></article>';
            echo '</div>';
        }
    }
}
