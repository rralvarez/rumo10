<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function ok(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA R10-CAR-002: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="' . e($path) . '"><input type="hidden" name="csrf" value="fixture-csrf">';
        if ($g) {
            echo '<input type="hidden" name="version" value="' . e($g['version']) . '"><input type="hidden" name="key" value="fixture-idempotency-key">';
        }
    }
}

/** Gerador determinístico (xorshift32) para simulações reproduzíveis com semente controlada. */
function makeSeededRng(int $seed): callable {
    $state = $seed !== 0 ? ($seed & 0xFFFFFFFF) : 0x6D2B79F5;
    return static function (int $min, int $max) use (&$state): int {
        if ($min >= $max) {
            return $min;
        }
        $state ^= ($state << 13) & 0xFFFFFFFF;
        $state ^= ($state >> 17) & 0xFFFFFFFF;
        $state ^= ($state << 5) & 0xFFFFFFFF;
        $u = ($state & 0x7FFFFFFF) / 0x80000000;
        return $min + (int)floor($u * ($max - $min + 1));
    };
}

/** Fórmula legada v1 (base 6.0, w_goal 0.70, etc.) para comparação direta antes/depois. */
function legacyRatingV1(array $m, array $g): float {
    $s = $m['stats'] ?? [];
    if (((int)($s['minutes'] ?? 0)) === 0) {
        return 0.0;
    }
    $def = in_array($g['position'], ['zagueiro', 'volante', 'lateral'], true);
    $wTackle = $def ? 0.35 : 0.15;
    $raw = 6.0
        + ((int)$s['goals']) * 0.70
        + ((int)$s['assists']) * 0.50
        + ((int)$s['tackles']) * $wTackle
        + ((int)$s['completed']) * 0.10
        - (((int)$s['passes']) - ((int)$s['completed'])) * 0.15
        - ((int)$s['cards']) * 0.60;
    return round(max(0.0, min(10.0, $raw)), 1);
}

/** Roleta legada v1 (7 cenários com 0, 1 e 2 propostas + ponderação 70/20/10) para comparação empírica. */
function legacyWheelOffersCount(float $merit, callable $rng): array {
    $weights = [
        0 => max(3, (int)round(65 - 35 * $merit)), // Sem interesse (0 ofertas)
        1 => 30 + (int)round(10 * $merit),        // 1 proposta
        2 => 10 + (int)round(18 * $merit),        // 2 propostas
        3 => 5 + (int)round(25 * $merit),         // 3 propostas (nacional)
        4 => $merit >= 0.20 ? (2 + (int)round(15 * $merit)) : 0,
        5 => $merit >= 0.45 ? (1 + (int)round(8 * $merit)) : 0,
        6 => $merit >= 0.85 ? (1 + (int)round(5 * $merit)) : 0,
    ];
    $items = [];
    foreach ($weights as $id => $w) {
        if ($w > 0) {
            $items[] = ['id' => $id, 'peso' => $w];
        }
    }
    $picked = Probability::weighted($items, $rng);
    $offersCount = match ($picked['id']) {
        0 => 0,
        1 => 1,
        2 => 2,
        default => 3,
    };
    return ['scenario' => $picked['id'], 'offers' => $offersCount];
}

echo "=== R10-CAR-002: SUÍTE DE HOMOLOGAÇÃO E SIMULAÇÕES COM SEMENTES CONTROLADAS ===\n\n";

// =========================================================================
// 1. NOTAS INDIVIDUAIS: TODAS AS POSIÇÕES, 4 GOLS, DEFESA/GOLEIRO, CURTA E 0 MIN
// =========================================================================
echo "1. Validando Fórmula de Avaliação Individual (MatchRating v2)...\n";

$baseMatch = static function (array $stats, int $hg = 2, int $ag = 0, string $partState = 'FULL_TIME', int $entry = 0, int $exit = 90): array {
    return [
        'round' => 1,
        'home' => 'Santos-SP',
        'away' => 'Corinthians-SP',
        'hg' => $hg,
        'ag' => $ag,
        'minute' => 90,
        'status' => 'finalizada',
        'lineup' => $entry === 0 ? 'titular' : 'reserva',
        'entry' => $entry,
        'exit' => $exit,
        'participation_state' => $partState,
        'stats' => array_replace([
            'minutes' => 90, 'goals' => 0, 'assists' => 0, 'shots' => 0,
            'passes' => 0, 'completed' => 0, 'tackles' => 0, 'fouls' => 0, 'cards' => 0, 'rating' => 0,
        ], $stats),
        'log' => [],
    ];
};

$ratingComparison = [];

// 1A: Atacante com 4 gols (poker) em diversas variações realistas sem erros graves -> [9.5, 10.0]
$pokerCases = [
    'poker_4_em_4_perfeito' => $baseMatch(['goals' => 4, 'shots' => 4, 'passes' => 2, 'completed' => 2], 4, 0),
    'poker_4_em_6_normal' => $baseMatch(['goals' => 4, 'shots' => 6, 'passes' => 2, 'completed' => 1, 'fouls' => 1], 4, 1),
    'poker_4_em_7_com_amarelo' => $baseMatch(['goals' => 4, 'shots' => 7, 'passes' => 2, 'completed' => 1, 'fouls' => 1, 'cards' => 1, 'yellow_cards' => 1], 4, 2),
];
foreach ($pokerCases as $label => $mCase) {
    $gAtt = ['club' => 'Santos-SP', 'position' => 'centroavante'];
    $v1 = legacyRatingV1($mCase, $gAtt);
    $v2 = MatchRating::calculate($mCase, $gAtt);
    $ratingComparison[$label] = ['position' => 'centroavante', 'v1' => $v1, 'v2' => $v2];
    ok($v2 >= 9.5 && $v2 <= 10.0, "Atacante 4 gols ({$label}): v2={$v2} dentro de [9.5, 10.0] (v1 era {$v1})");
}

// 1B: Atacante com 4 gols MAS erros gravíssimos (vermelho direto + pênalti perdido + passes errados) -> cai abaixo de 9.5
$mPokerGrave = $baseMatch([
    'minutes' => 75, 'goals' => 4, 'shots' => 8, 'passes' => 4, 'completed' => 0,
    'penalties_missed' => 1, 'fouls' => 3, 'cards' => 1, 'red_cards' => 1, 'direct_red' => 1,
], 4, 3, 'SENT_OFF', 0, 75);
$rPokerGrave = MatchRating::calculate($mPokerGrave, ['club' => 'Santos-SP', 'position' => 'centroavante']);
ok($rPokerGrave < 8.5, "Atacante 4 gols com expulsão direta e pênalti perdido recebe punição real (nota {$rPokerGrave})");

// 1C: Notas excelentes sem gols para Goleiro, Zagueiro, Lateral, Volante e Meia garçom
$defRoles = [
    'goleiro_muralha_saves' => [['position' => 'goleiro', 'club' => 'Santos-SP'], $baseMatch(['saves' => 5, 'tackles' => 1, 'passes' => 3, 'completed' => 3], 1, 0)],
    'goleiro_seguro_sem_saves_key' => [['position' => 'goleiro', 'club' => 'Santos-SP'], $baseMatch(['tackles' => 3, 'passes' => 2, 'completed' => 2], 1, 0)],
    'zagueiro_dominante_clean_sheet' => [['position' => 'zagueiro', 'club' => 'Santos-SP'], $baseMatch(['tackles' => 4, 'passes' => 3, 'completed' => 3], 1, 0)],
    'zagueiro_dominante_1_gol_sofrido' => [['position' => 'zagueiro', 'club' => 'Santos-SP'], $baseMatch(['tackles' => 4, 'passes' => 3, 'completed' => 3], 2, 1)],
    'volante_cão_de_guarda' => [['position' => 'volante', 'club' => 'Santos-SP'], $baseMatch(['tackles' => 4, 'passes' => 4, 'completed' => 4], 1, 0)],
    'lateral_completo_sem_gol' => [['position' => 'lateral', 'club' => 'Santos-SP'], $baseMatch(['tackles' => 3, 'assists' => 1, 'passes' => 3, 'completed' => 3], 2, 0)],
    'meia_tres_assistencias' => [['position' => 'meia', 'club' => 'Santos-SP'], $baseMatch(['assists' => 3, 'passes' => 4, 'completed' => 4, 'tackles' => 1], 3, 1)],
    'ponta_dois_gols_uma_assist' => [['position' => 'ponta', 'club' => 'Santos-SP'], $baseMatch(['goals' => 2, 'shots' => 3, 'assists' => 1, 'passes' => 2, 'completed' => 2], 3, 0)],
];
foreach ($defRoles as $key => [$gRole, $mRole]) {
    $v1 = legacyRatingV1($mRole, $gRole);
    $v2 = MatchRating::calculate($mRole, $gRole);
    $ratingComparison[$key] = ['position' => $gRole['position'], 'v1' => $v1, 'v2' => $v2];
    ok($v2 >= 9.0 && $v2 <= 10.0, "Atuação excepcional {$key} ({$gRole['position']}): v2={$v2} >= 9.0 (v1 era {$v1})");
}

// 1D: Participação curta (15 min) vs Atleta não utilizado (0 min)
$mUnused = $baseMatch(['minutes' => 0, 'goals' => 0], 2, 0, 'ON_BENCH', 90, 90);
ok(MatchRating::calculate($mUnused, ['club' => 'Santos-SP', 'position' => 'zagueiro']) === 0.0, 'Atleta não utilizado (0 min) não recebe nota de atuação (0.0)');

$mShortNeutral = $baseMatch(['minutes' => 15, 'passes' => 1, 'completed' => 1], 0, 0, 'FULL_TIME', 75, 90);
$rShortDef = MatchRating::calculate($mShortNeutral, ['club' => 'Santos-SP', 'position' => 'zagueiro']);
ok($rShortDef >= 5.8 && $rShortDef <= 6.5, "Participação curta neutra (15 min) fica próxima à base sem inflar clean sheet ({$rShortDef})");

$mShortGoal = $baseMatch(['minutes' => 15, 'goals' => 1, 'shots' => 1], 1, 0, 'FULL_TIME', 75, 90);
$rShortGoal = MatchRating::calculate($mShortGoal, ['club' => 'Santos-SP', 'position' => 'centroavante']);
ok($rShortGoal >= 7.0 && $rShortGoal <= 7.8, "Reserva que entra aos 75' e faz o gol da vitória recebe nota coerente ({$rShortGoal})");

// 1E: Disputa de pênaltis pós-jogo NÃO altera gols da partida nem recalcula nota encerrada
$mWithShootout = $baseMatch(['minutes' => 90, 'goals' => 0, 'shots' => 1, 'passes' => 2, 'completed' => 2], 1, 1);
$rBeforeShootout = MatchRating::calculate($mWithShootout, ['club' => 'Santos-SP', 'position' => 'centroavante']);
$mWithShootout['penalties'] = [5, 4];
$mWithShootout['shootout'] = ['status' => 'completed', 'winner' => 'Santos-SP', 'kicks' => [['team' => 'Santos-SP', 'protagonist' => true, 'scored' => true]]];
$rAfterShootout = MatchRating::calculate($mWithShootout, ['club' => 'Santos-SP', 'position' => 'centroavante']);
ok($rBeforeShootout === $rAfterShootout, 'Gols em disputa de pênaltis pós-jogo não alteram a nota da partida');

// 1F: Não recalcular retrospectivamente notas de partidas já encerradas em saves antigos
$gRetro = Game::legacyCreate('Save Antigo', 'Santos', 'SP', 'centroavante');
$idxRetro = Game::currentIndex($gRetro);
$gRetro['matches'][$idxRetro]['status'] = 'finalizada';
$gRetro['matches'][$idxRetro]['minute'] = 90;
$gRetro['matches'][$idxRetro]['stats'] = array_replace($gRetro['matches'][$idxRetro]['stats'], [
    'minutes' => 90, 'goals' => 4, 'shots' => 4, 'rating' => 8.0, // nota histórica salva antes da CAR-002
]);
$summaryRetro = Game::summary($gRetro);
$evalRetro = SeasonEvaluation::current($gRetro);
ok($summaryRetro['average'] === 8.0 && $evalRetro['average'] === 8.0 && $gRetro['matches'][$idxRetro]['stats']['rating'] === 8.0,
    'Notas históricas de partidas encerradas permanecem preservadas sem recálculo retrospectivo');

// 1G: Preservação integral da correção de autoconfiança BAL-001 (sem bônus duplicado)
$gBal = Game::legacyCreate('Teste BAL001', 'São Paulo', 'SP', 'centroavante');
$gBal['stage'] = 'partida';
$gBal['states']['confianca'] = 88;
$iBal = Game::currentIndex($gBal);
$gBal['matches'][$iBal]['status'] = 'em_andamento';
$gBal['matches'][$iBal]['minute'] = 80;
$gBal['matches'][$iBal]['entry'] = 0;
$gBal['matches'][$iBal]['exit'] = 90;
$gBal['matches'][$iBal]['participation_state'] = 'ON_FIELD';
$gBal['matches'][$iBal]['participated_on_field'] = true;
$gBal['matches'][$iBal]['entered_on_field'] = true;
$gBal['matches'][$iBal]['stats'] = array_replace($gBal['matches'][$iBal]['stats'], ['goals' => 4, 'shots' => 4, 'passes' => 2, 'completed' => 2]);
// Avança do 80' para 90' encerrando a partida
$gBal['matches'][$iBal]['exit'] = 80;
$gBal['matches'][$iBal]['participation_state'] = 'SUBSTITUTED_OUT';
$gBal = Game::command($gBal, 'advance');
$balKeys = array_keys(array_filter($gBal['reward_ledger'] ?? [], static fn($k) => str_starts_with($k, 'bal001:confidence:'), ARRAY_FILTER_USE_KEY));
ok(count($balKeys) === 1 && $gBal['states']['confianca'] === 90 && $gBal['matches'][$iBal]['stats']['rating'] >= 9.5,
    'BAL-001 preservada: ledger único por partida e teto/incremento exato de confiança sem duplicação');

// =========================================================================
// 2. AVALIAÇÃO EXCLUSIVA DA ÚLTIMA TEMPORADA (EXCLUSÃO DO HISTÓRICO DA ROLETA)
// =========================================================================
echo "\n2. Validando Avaliação da Última Temporada e Exclusão de Temporadas Anteriores...\n";

$makeOfficialCareer = static function (string $position, float $skillVal, int $season, string $club = 'Ponte Preta-SP'): array {
    $g = Game::create('Atleta Simulado', 'Campinas', 'SP', $position, $club);
    $g['id'] = 777;
    $g['season'] = $season;
    $g['age'] = 17 + $season;
    foreach ($g['skills'] as &$sk) {
        $sk['value'] = (int)round($skillVal);
    }
    unset($sk);
    $g['season_skill_baseline'] = $skillVal - 1.5;
    $g['stage'] = 'resumo';
    return $g;
};

$populateSeasonMatches = static function (array $g, int $gamesPlayed, int $minutesEach, float $matchRating, int $totalGoals, int $totalAssists, int $totalTackles, bool $wonTitle = false): array {
    $cid = $g['competition_id'];
    $teams = $g['memberships'][$cid];
    $oppList = array_values(array_filter($teams, static fn($t) => $t !== $g['club']));
    $matches = [];
    $gLeft = $totalGoals;
    $aLeft = $totalAssists;
    $tLeft = $totalTackles;
    for ($r = 1; $r <= max($gamesPlayed, 14); $r++) {
        $opp = $oppList[($r - 1) % count($oppList)];
        $played = $r <= $gamesPlayed ? $minutesEach : 0;
        $gThis = ($played > 0 && $gLeft > 0) ? (int)ceil($gLeft / max(1, $gamesPlayed - $r + 1)) : 0;
        $aThis = ($played > 0 && $aLeft > 0) ? (int)ceil($aLeft / max(1, $gamesPlayed - $r + 1)) : 0;
        $tThis = ($played > 0 && $tLeft > 0) ? (int)ceil($tLeft / max(1, $gamesPlayed - $r + 1)) : 0;
        $gLeft -= $gThis;
        $aLeft -= $aThis;
        $tLeft -= $tThis;
        $matches[] = [
            'round' => $r,
            'competition' => $cid,
            'home' => $r % 2 === 1 ? $g['club'] : $opp,
            'away' => $r % 2 === 1 ? $opp : $g['club'],
            'hg' => $r % 2 === 1 ? max(1, $gThis) : ($wonTitle ? 0 : 1),
            'ag' => $r % 2 === 1 ? ($wonTitle ? 0 : 1) : max(1, $gThis),
            'home_overall' => 64.0,
            'away_overall' => 66.0,
            'minute' => 90,
            'status' => 'finalizada',
            'lineup' => $played > 0 ? 'titular' : 'reserva',
            'entry' => $played > 0 ? 0 : 90,
            'exit' => 90,
            'participation_state' => $played > 0 ? 'FULL_TIME' : 'ON_BENCH',
            'stats' => [
                'minutes' => $played,
                'goals' => $gThis,
                'assists' => $aThis,
                'shots' => $gThis + ($played > 0 ? 1 : 0),
                'passes' => $played > 0 ? 3 : 0,
                'completed' => $played > 0 ? 3 : 0,
                'tackles' => $tThis,
                'fouls' => 0,
                'cards' => 0,
                'rating' => $played > 0 ? $matchRating : 0,
            ],
            'log' => [],
        ];
    }
    $g['matches'] = $matches;
    $g['competitions'][$cid]['matches'] = $matches;
    $g['competitions'][$cid]['done'] = true;
    $g['competition_results'] = [
        $cid => [
            'name' => Competition::catalog()[$cid]['name'],
            'champion' => $wonTitle ? $g['club'] : $oppList[0],
            'position' => $wonTitle ? 1 : 6,
        ],
    ];
    return $g;
};

$gHistPoorPrior = $populateSeasonMatches($makeOfficialCareer('meia', 72, 4, 'Santos-SP'), 20, 85, 7.6, 8, 9, 15, true);
$gHistPoorPrior['history'] = [
    ['season' => 3, 'club' => 'Santos-SP', 'goals' => 0, 'average' => 4.8, 'evaluation' => ['score' => 0.05]],
    ['season' => 2, 'club' => 'Santos-SP', 'goals' => 0, 'average' => 4.9, 'evaluation' => ['score' => 0.05]],
];
$gHistLegendPrior = $gHistPoorPrior;
$gHistLegendPrior['history'] = [
    ['season' => 3, 'club' => 'Santos-SP', 'goals' => 35, 'average' => 9.6, 'evaluation' => ['score' => 1.48]],
    ['season' => 2, 'club' => 'Santos-SP', 'goals' => 30, 'average' => 9.4, 'evaluation' => ['score' => 1.45]],
];

$evalA = SeasonEvaluation::evaluate($gHistPoorPrior);
$evalB = SeasonEvaluation::evaluate($gHistLegendPrior);
$candsAB = TransferMarket::pool($gHistPoorPrior, false, makeSeededRng(101));
$tiersA = TransferInterest::scenarios($gHistPoorPrior, $candsAB, $evalA);
$tiersB = TransferInterest::scenarios($gHistLegendPrior, $candsAB, $evalB);

ok($evalA['score'] === $evalB['score'] && $evalA['weighted_score'] === $evalB['weighted_score'],
    'Avaliação anual produz exatamente o mesmo score independentemente das temporadas anteriores');
ok(array_column($tiersA, 'percent') === array_column($tiersB, 'percent'),
    'Probabilidades das 5 faixas na roleta são 100% idênticas independentemente do histórico anterior');
ok(count($gHistPoorPrior['history']) === 2 && $evalA['history_available'] === 2,
    'Histórico das temporadas anteriores permanece integralmente preservado no save');

// =========================================================================
// 3. SIMULAÇÕES CONTROLADAS (5 PERFIS DE TEMPORADA × 1.000 SORTEIOS) & COMPARAÇÃO V1 vs V2
// =========================================================================
echo "\n3. Executando Simulações com Sementes Controladas (Ruim, Regular, Boa, Excelente, Excepcional)...\n";

$profilesSpec = [
    'ruim'        => ['games' => 8,  'min' => 55, 'rating' => 5.3, 'goals' => 0,  'assists' => 0,  'tackles' => 2,  'title' => false, 'ovr' => 70],
    'regular'     => ['games' => 16, 'min' => 75, 'rating' => 6.3, 'goals' => 3,  'assists' => 2,  'tackles' => 10, 'title' => false, 'ovr' => 70],
    'boa'         => ['games' => 22, 'min' => 85, 'rating' => 7.1, 'goals' => 9,  'assists' => 6,  'tackles' => 16, 'title' => false, 'ovr' => 72],
    'excelente'   => ['games' => 26, 'min' => 88, 'rating' => 7.9, 'goals' => 16, 'assists' => 10, 'tackles' => 20, 'title' => true,  'ovr' => 74],
    'excepcional' => ['games' => 30, 'min' => 90, 'rating' => 8.8, 'goals' => 26, 'assists' => 14, 'tackles' => 24, 'title' => true,  'ovr' => 78],
];

$simulationReport = [];
$concreteExamples = [];

foreach ($profilesSpec as $profileKey => $spec) {
    $gSim = $populateSeasonMatches(
        $makeOfficialCareer('meia', $spec['ovr'], 4, 'Red Bull Bragantino-SP'),
        $spec['games'], $spec['min'], $spec['rating'], $spec['goals'], $spec['assists'], $spec['tackles'], $spec['title']
    );
    $evalSim = SeasonEvaluation::evaluate($gSim);
    $rngPool = makeSeededRng(5000 + crc32($profileKey));
    $candsSim = TransferMarket::pool($gSim, false, $rngPool);
    $tiersSim = TransferInterest::scenarios($gSim, $candsSim, $evalSim);

    $pctSum = round(array_sum(array_column($tiersSim, 'percent')), 1);
    ok($pctSum === 100.0, "Perfil '{$profileKey}': probabilidades das faixas somam exatamente 100.0% (soma={$pctSum})");

    // 300 sorteios completos de janela em v2 + 1.000 sorteios comparativos v1 vs v2
    $N = 250;
    $tierHits = array_fill(0, 5, 0);
    $v2ThreeOffersCount = 0;
    $v1ZeroOffersCount = 0;
    $v1UnderThreeCount = 0;
    $geoTotals = ['country' => 0, 'region' => 0, 'world' => 0];
    $confedsObserved = [];

    for ($trial = 1; $trial <= $N; $trial++) {
        $rngV2 = makeSeededRng(100000 + $trial * 37 + crc32($profileKey));
        $draw = TransferInterest::draw($gSim, $rngV2);
        $tierHits[$draw['quality_tier']]++;

        $offers = $draw['offers'];
        $offerClubs = array_column($offers, 'club');
        if (count($offers) === 3) {
            $v2ThreeOffersCount++;
        }
        if (count(array_unique($offerClubs)) !== count($offers)) {
            throw new RuntimeException("Clube duplicado detectado na simulação '{$profileKey}' trial {$trial}");
        }
        if (in_array($gSim['club'], $offerClubs, true)) {
            throw new RuntimeException("Clube atual incluído indevidamente como proposta externa");
        }
        foreach ($offers as $of) {
            $b = $of['region'] ?? 'country';
            $geoTotals[$b] = ($geoTotals[$b] ?? 0) + 1;
            $cf = $of['confederation'] ?? 'CONMEBOL';
            $confedsObserved[$cf] = ($confedsObserved[$cf] ?? 0) + 1;
        }
        if ($trial === 1) {
            $concreteExamples[$profileKey] = [
                'season_score' => $evalSim['score'],
                'score_100' => $evalSim['score_100'],
                'band' => $evalSim['band_label'],
                'drawn_tier' => $draw['quality_tier_label'],
                'stay' => ['club' => $draw['stay']['club'], 'overall' => $draw['stay']['overall']],
                'offers' => array_map(static fn($o) => [
                    'club' => $o['club'],
                    'country' => $o['country'],
                    'confederation' => $o['confederation'] ?? null,
                    'scope' => $o['region'],
                    'competition' => $o['competition'] ?? null,
                    'overall' => $o['overall'],
                    'overall_delta' => $o['overall_delta'],
                    'tier' => $o['quality_tier_label'],
                    'role' => $o['forecast']['role'] ?? null,
                ], $offers),
            ];
        }

        $rngV1 = makeSeededRng(200000 + $trial * 37 + crc32($profileKey));
        $v1Res = legacyWheelOffersCount($evalSim['score'], $rngV1);
        if ($v1Res['offers'] === 0) {
            $v1ZeroOffersCount++;
        }
        if ($v1Res['offers'] < 3) {
            $v1UnderThreeCount++;
        }
    }

    ok($v2ThreeOffersCount === $N, "Perfil '{$profileKey}': 100% dos {$N} sorteios entregaram 3 propostas válidas e distintas (v1 teve {$v1ZeroOffersCount} janelas com ZERO propostas e {$v1UnderThreeCount} com <3)");

    $simulationReport[$profileKey] = [
        'evaluation_score' => $evalSim['score'],
        'score_100' => $evalSim['score_100'],
        'band' => $evalSim['band'],
        'theoretical_probabilities_pct' => array_combine(array_column($tiersSim, 'nome'), array_column($tiersSim, 'percent')),
        'empirical_tier_pct_N250' => [
            'Inferior' => round(100 * $tierHits[0] / $N, 1),
            'Equivalente' => round(100 * $tierHits[1] / $N, 1),
            'Superior' => round(100 * $tierHits[2] / $N, 1),
            'Destaque' => round(100 * $tierHits[3] / $N, 1),
            'Elite' => round(100 * $tierHits[4] / $N, 1),
        ],
        'v1_comparison_N250' => [
            'v1_zero_offers_pct' => round(100 * $v1ZeroOffersCount / $N, 1),
            'v1_under_3_offers_pct' => round(100 * $v1UnderThreeCount / $N, 1),
            'v2_exact_3_offers_pct' => round(100 * $v2ThreeOffersCount / $N, 1),
        ],
        'geographic_offer_share_pct' => [
            'nacional' => round(100 * $geoTotals['country'] / ($N * 3), 1),
            'continental' => round(100 * $geoTotals['region'] / ($N * 3), 1),
            'intercontinental' => round(100 * $geoTotals['world'] / ($N * 3), 1),
        ],
        'confederations_sampled' => $confedsObserved,
    ];
}

// Verifica progressão monotônica entre temporada ruim e temporada excelente/excepcional
$pRuim = $simulationReport['ruim']['theoretical_probabilities_pct'];
$pBoa = $simulationReport['boa']['theoretical_probabilities_pct'];
$pExc = $simulationReport['excelente']['theoretical_probabilities_pct'];
$pExcep = $simulationReport['excepcional']['theoretical_probabilities_pct'];

ok($pRuim['Inferior'] > $pBoa['Inferior'] && $pBoa['Inferior'] > $pExc['Inferior'],
    'Temporada ruim favorece faixa Inferior; melhora de desempenho reduz Inferior progressivamente');
ok(($pExc['Destaque'] + $pExc['Elite']) > ($pRuim['Destaque'] + $pRuim['Elite']) + 35.0,
    'Temporada excelente desloca fortemente a probabilidade para Destaque/Elite');
ok($pExcep['Elite'] > $pExc['Elite'] && $pExc['Elite'] > $pBoa['Elite'],
    'Faixa Elite cresce monotonicamente de Boa -> Excelente -> Excepcional');

// =========================================================================
// 4. DIVERSIDADE GEOGRÁFICA GLOBAL (NÃO RESTRITA À EUROPA) & COMPATIBILIDADE DE OVERALL
// =========================================================================
echo "\n4. Validando Diversidade Geográfica Intercontinental e Bloqueio de Incompatibilidade...\n";

$allObservedConfeds = $simulationReport['excelente']['confederations_sampled'] + $simulationReport['boa']['confederations_sampled'];
$nonUefaWorldConfeds = array_intersect(array_keys($allObservedConfeds), ['CONCACAF', 'AFC', 'CAF', 'OFC']);
ok(isset($allObservedConfeds['CONMEBOL'], $allObservedConfeds['UEFA']) && count($nonUefaWorldConfeds) >= 1,
    'Mercado intercontinental abrange confederações além da UEFA: ' . implode(', ', array_keys($allObservedConfeds)));

// Jogador iniciante (overall 48) não pode receber oferta de clube Elite (overall >= 76)
$gRookie = $populateSeasonMatches($makeOfficialCareer('centroavante', 48, 1, 'Marília-SP'), 10, 80, 7.2, 5, 1, 2, false);
$evalRookie = SeasonEvaluation::evaluate($gRookie);
$candsRookie = TransferMarket::pool($gRookie, false, makeSeededRng(77));
$tiersRookie = TransferInterest::scenarios($gRookie, $candsRookie, $evalRookie);
ok($tiersRookie[4]['eligible'] === false && $tiersRookie[4]['percent'] === 0.0,
    'Atleta overall 48 tem faixa Elite bloqueada (0,0%) por incompatibilidade esportiva');
$drawRookie = TransferInterest::draw($gRookie, makeSeededRng(88));
ok(count($drawRookie['offers']) === 3 && max(array_column($drawRookie['offers'], 'overall')) <= 68.0,
    'Atleta overall 48 ainda recebe 3 propostas válidas compatíveis com seu patamar (máx overall <= 68)');

// Catálogo sintético com apenas 2 clubes elegíveis no universo inteiro -> shortfall explícito sem inventar clubes
$miniCatalog = [
    'mini-1' => ['id' => 'mini-1', 'name' => 'Liga Mini', 'family' => 'br', 'tier' => 1, 'format' => 'league', 'teams' => ['Clube Alfa', 'Clube Beta', 'Clube Gama'], 'rules' => []],
];
$miniClubs = [
    'Clube Alfa' => ['name' => 'Clube Alfa', 'country' => 'BR', 'competitions' => ['mini-1']],
    'Clube Beta' => ['name' => 'Clube Beta', 'country' => 'BR', 'competitions' => ['mini-1']],
    'Clube Gama' => ['name' => 'Clube Gama', 'country' => 'BR', 'competitions' => ['mini-1']],
];
Competition::referenceScope($miniCatalog, static function () use ($makeOfficialCareer): void {
    $gMini = Game::legacyCreate('Teste Catálogo Curto', 'São Paulo', 'SP', 'meia');
    $gMini['official'] = true;
    $gMini['club'] = 'Clube Alfa';
    $gMini['competition_id'] = 'mini-1';
    $gMini['season_competitions'] = ['mini-1'];
    $gMini['memberships'] = ['mini-1' => ['Clube Alfa', 'Clube Beta']]; // Só 1 clube externo válido!
    $gMini['competitions'] = ['mini-1' => ['id' => 'mini-1', 'matches' => [], 'done' => true]];
    $gMini['matches'] = [];
    $drawMini = TransferInterest::draw($gMini, makeSeededRng(99));
    ok(count($drawMini['offers']) === 1
        && $drawMini['offers'][0]['club'] === 'Clube Beta'
        && $drawMini['offer_guarantee']['exceptional_shortfall'] === true
        && is_string($drawMini['offer_guarantee']['cause']) && $drawMini['offer_guarantee']['cause'] !== '',
        'Catálogo restrito com <3 clubes registra condição excepcional explícita sem inventar nem duplicar destinos');
}, $miniClubs);

// =========================================================================
// 5. PERSISTÊNCIA APÓS REFRESH, PERMANÊNCIA, TRANSFERÊNCIA, APOSENTADORIA E 3 MODOS
// =========================================================================
echo "\n5. Validando Persistência, Refresh, Permanência, Transferência, Aposentadoria e 3 Modos...\n";

foreach (['normal', 'rapido', 'expresso'] as $mode) {
    $gMode = Game::legacyCreate('Modo ' . $mode, 'São Paulo', 'SP', 'centroavante');
    $gMode['play_mode'] = $mode;
    $gMode = Game::command($gMode, 'handicap');
    if ($mode === 'expresso') {
        $gMode = Game::command($gMode, 'simulate', ['plan' => 'protagonismo']);
        ok($gMode['stage'] === 'resumo', "Modo Expresso conclui temporada completa em resumo");
    } elseif ($mode === 'rapido') {
        $gMode = Game::command($gMode, 'simulate', ['plan' => 'protagonismo']);
        ok($gMode['round'] === 2 && $gMode['matches'][0]['status'] === 'finalizada', "Modo Rápido simula partida completa com MatchRating v2");
        ok(($gMode['matches'][0]['stats']['rating_version'] ?? 0) === MatchRating::VERSION, "Modo Rápido grava rating_version=2");
        // Completa as demais rodadas para testar a janela anual
        while ($gMode['stage'] !== 'resumo') {
            $gMode = Game::command($gMode, 'simulate', ['plan' => 'protagonismo']);
        }
    } else {
        // Modo normal: joga 1ª partida e avança temporada
        while ($gMode['stage'] !== 'resumo') {
            if ($gMode['stage'] === 'partida') {
                $mCur = $gMode['matches'][Game::currentIndex($gMode)];
                $act = ($mCur['status'] === 'em_andamento' && MatchParticipation::canActOnField($mCur)) ? 'choice' : 'advance';
                $gMode = Game::command($gMode, $act, $act === 'choice' ? ['option' => 0] : []);
            } else {
                $gMode = Game::command($gMode, 'choice', ['option' => 0]);
            }
        }
        ok(($gMode['matches'][0]['stats']['rating_version'] ?? 0) === MatchRating::VERSION, "Modo Normal grava rating_version=2");
    }

    // Abre janela de propostas uma única vez
    $gWindow = Game::command($gMode, 'offers');
    ok($gWindow['stage'] === 'oferta' && count($gWindow['offers']) === 3 && ($gWindow['market_draw']['schema'] ?? 0) === 3,
        "Modo {$mode}: janela abre com 3 propostas distintas e schema 3");

    // Tentativa de reenvio / segundo sorteio na mesma janela deve ser bloqueada
    $blockedRedraw = false;
    try {
        Game::command($gWindow, 'offers');
    } catch (InvalidArgumentException) {
        $blockedRedraw = true;
    }
    ok($blockedRedraw, "Modo {$mode}: reenvio de sorteio na mesma janela é bloqueado no servidor");

    // Refresh de página (dupla renderização HTML) preserva ID, faixa sorteada e clubes sem mutação
    $snapBeforeRender = serialize($gWindow);
    ob_start(); TransferMarket::render($gWindow); $htmlRender1 = ob_get_clean();
    ob_start(); TransferMarket::render($gWindow); $htmlRender2 = ob_get_clean();
    ok(serialize($gWindow) === $snapBeforeRender, "Modo {$mode}: renderização HTML não altera estado persistido");
    // Remove chaves aleatórias de CSRF/idempotência para comparar conteúdo funcional determinístico
    $clean1 = preg_replace('/name="key" value="[a-f0-9]+"/', 'name="key" value="K"', (string)$htmlRender1);
    $clean2 = preg_replace('/name="key" value="[a-f0-9]+"/', 'name="key" value="K"', (string)$htmlRender2);
    ok($clean1 === $clean2 && str_contains((string)$htmlRender1, 'Clubes Interessados (3)') && str_contains((string)$htmlRender1, 'Permanecer em '),
        "Modo {$mode}: refresh mantém exatamente as mesmas 3 propostas, probabilidades, avaliação e opção de permanência");

    // Testa permanência (accept=no) e transferência (accept=yes, option=2)
    $origClub = $gWindow['club'];
    $gStayed = Game::command($gWindow, 'transfer', ['accept' => 'no']);
    ok($gStayed['club'] === $origClub && $gStayed['season'] === 2 && $gStayed['stage'] === 'treino',
        "Modo {$mode}: opção de permanência renova vínculo com o clube atual");

    $chosenClub = $gWindow['offers'][2]['club'];
    $gMoved = Game::command($gWindow, 'transfer', ['accept' => 'yes', 'option' => 2]);
    ok($gMoved['club'] === $chosenClub && $gMoved['season'] === 2 && $gMoved['stage'] === 'treino',
        "Modo {$mode}: aceite da 3ª proposta transfere corretamente para {$chosenClub}");
}

// Aposentadoria obrigatória preservada (39 anos em resumo não abre ofertas; exige confirmação explícita)
$gVet39 = $makeOfficialCareer('zagueiro', 75, 24, 'Santos-SP');
$gVet39['age'] = 39;
$vetBlocked = false;
try {
    Game::command($gVet39, 'offers');
} catch (InvalidArgumentException) {
    $vetBlocked = true;
}
ok($vetBlocked, 'Aposentadoria obrigatória aos 39 anos bloqueia abertura de nova janela de transferências');
$gVetRetired = Game::command($gVet39, 'retire', ['confirm' => 'retire']);
ok($gVetRetired['stage'] === 'aposentado' && $gVetRetired['retirement']['reason'] === 'mandatory',
    'Aposentadoria obrigatória conclui encerramento com confirmação explícita');

// =========================================================================
// 6. VALIDAÇÃO DIRIGIDA PR #17: INVENTÁRIO, TARGET_OFFERS >= 3, TETO DE OVERALL E CLASSIFICAÇÃO EFETIVA
// =========================================================================
echo "\n6. Validando Pendências Dirigidas do PR #17 (Inventário, target_offers >= 3, Teto Estrito e Faixa Efetiva)...\n";

// 6A: Inventário produtivo publisher/config/runtime-files.json registra app/MatchRating.php sem duplicatas
$runtimeInventory = json_decode((string)file_get_contents(dirname(__DIR__) . '/publisher/config/runtime-files.json'), true, 512, JSON_THROW_ON_ERROR);
ok(is_array($runtimeInventory)
    && in_array('app/MatchRating.php', $runtimeInventory, true)
    && count($runtimeInventory) === count(array_unique($runtimeInventory)),
    'publisher/config/runtime-files.json registra app/MatchRating.php sem duplicidades');

// 6B: target_offers < 3 configurado em parâmetros nunca reduz o mínimo contratual de 3 propostas
$refBalanceCache = new ReflectionProperty('BalanceService', 'activeCache');
$refBalanceCache->setAccessible(true);
BalanceService::params('market.quality.tiers.v2'); // garante carregamento inicial
$origBalanceCache = $refBalanceCache->getValue();
try {
    foreach ([0, 1, 2, -5] as $badTarget) {
        $patched = $origBalanceCache;
        $patched['market.quality.tiers.v2']['target_offers'] = $badTarget;
        $refBalanceCache->setValue(null, $patched);
        $gTarget = $populateSeasonMatches($makeOfficialCareer('meia', 72, 3, 'Santos-SP'), 18, 85, 7.2, 6, 5, 10, false);
        $drawTarget = TransferInterest::draw($gTarget, makeSeededRng(900 + $badTarget));
        ok($drawTarget['offer_guarantee']['target'] === 3 && count($drawTarget['offers']) === 3,
            "target_offers={$badTarget} é travado em >= 3 (entregues=" . count($drawTarget['offers']) . ")");
    }
} finally {
    $refBalanceCache->setValue(null, $origBalanceCache);
}

// 6C: Em catálogo reduzido, clubes acima do teto de overall compatível NUNCA entram por fallback apenas para fechar 3 ofertas
$strictCeilingCatalog = [
    'liga-curta' => [
        'id' => 'liga-curta',
        'name' => 'Liga Curta',
        'family' => 'br',
        'tier' => 1,
        'format' => 'league',
        'teams' => ['Base Modesta', 'Vizinho Compatível', 'Superpotência 92', 'Superpotência 94'],
        'rules' => [],
    ],
];
$strictCeilingClubs = [
    'Base Modesta'       => ['name' => 'Base Modesta', 'country' => 'BR', 'competitions' => ['liga-curta'], 'reference_overall' => 50.0],
    'Vizinho Compatível' => ['name' => 'Vizinho Compatível', 'country' => 'BR', 'competitions' => ['liga-curta'], 'reference_overall' => 55.0],
    'Superpotência 92'   => ['name' => 'Superpotência 92', 'country' => 'ES', 'competitions' => ['liga-curta'], 'reference_overall' => 92.0],
    'Superpotência 94'   => ['name' => 'Superpotência 94', 'country' => 'EN', 'competitions' => ['liga-curta'], 'reference_overall' => 94.0],
];
Competition::referenceScope($strictCeilingCatalog, static function (): void {
    $gCap = Game::legacyCreate('Rookie Teto Estrito', 'São Paulo', 'SP', 'centroavante');
    $gCap['official'] = true;
    $gCap['season'] = 1;
    $gCap['age'] = 18;
    foreach ($gCap['skills'] as &$sk) {
        $sk['value'] = 48; // compatibleOverallCeiling ~ 60.5 -> bloqueia 92 e 94 obrigatoriamente!
    }
    unset($sk);
    $gCap['season_skill_baseline'] = 48.0;
    $gCap['club'] = 'Base Modesta';
    $gCap['competition_id'] = 'liga-curta';
    $gCap['season_competitions'] = ['liga-curta'];
    $gCap['memberships'] = ['liga-curta' => ['Base Modesta', 'Vizinho Compatível', 'Superpotência 92', 'Superpotência 94']];
    $gCap['competitions'] = ['liga-curta' => ['id' => 'liga-curta', 'matches' => [], 'done' => true]];
    $gCap['matches'] = [];

    $poolCap = TransferMarket::pool($gCap, false, makeSeededRng(301));
    $drawCap = TransferInterest::draw($gCap, makeSeededRng(302));
    $offeredClubs = array_column($drawCap['offers'], 'club');

    ok(count($poolCap) === 1 && $offeredClubs === ['Vizinho Compatível'],
        'Teto obrigatório de overall preservado: clubes incompatíveis (OVR 92/94) não entram por fallback em catálogo reduzido');
    ok($drawCap['offer_guarantee']['target'] === 3
        && $drawCap['offer_guarantee']['delivered'] === 1
        && $drawCap['offer_guarantee']['exceptional_shortfall'] === true,
        'Escassez por bloqueio de incompatibilidade registra shortfall explícito (1/3) sem gerar ofertas incompatíveis');
}, $strictCeilingClubs);

// 6D: Preferências geográficas (continental/world na T1) relaxam legitimamente dentro do teto de overall quando o país tem < 6 clubes
$prefRelaxCatalog = [
    'liga-mista' => [
        'id' => 'liga-mista',
        'name' => 'Liga Mista Compatível',
        'family' => 'br',
        'tier' => 1,
        'format' => 'league',
        'teams' => ['Origem BR', 'Nacional BR', 'Vizinho AR', ' Asiático JP'],
        'rules' => [],
    ],
];
$prefRelaxClubs = [
    'Origem BR'   => ['name' => 'Origem BR', 'country' => 'BR', 'competitions' => ['liga-mista'], 'reference_overall' => 52.0],
    'Nacional BR' => ['name' => 'Nacional BR', 'country' => 'BR', 'competitions' => ['liga-mista'], 'reference_overall' => 53.0],
    'Vizinho AR'  => ['name' => 'Vizinho AR', 'country' => 'AR', 'competitions' => ['liga-mista'], 'reference_overall' => 54.0],
    'Asiático JP' => ['name' => 'Asiático JP', 'country' => 'JP', 'competitions' => ['liga-mista'], 'reference_overall' => 55.0],
];
$prefRelaxCatalog['liga-mista']['teams'] = array_keys($prefRelaxClubs);
Competition::referenceScope($prefRelaxCatalog, static function (): void {
    $gPref = Game::legacyCreate('Rookie Alcance', 'São Paulo', 'SP', 'meia');
    $gPref['official'] = true;
    $gPref['season'] = 1; // T1 normalmente restringe world_open por preferência
    $gPref['age'] = 18;
    foreach ($gPref['skills'] as &$sk) {
        $sk['value'] = 50; // compatibleOverallCeiling ~ 62.5 -> todos os 3 externos (53, 54, 55) são compatíveis!
    }
    unset($sk);
    $gPref['season_skill_baseline'] = 50.0;
    $gPref['club'] = 'Origem BR';
    $gPref['competition_id'] = 'liga-mista';
    $gPref['season_competitions'] = ['liga-mista'];
    $gPref['memberships'] = ['liga-mista' => array_keys([
        'Origem BR' => 1, 'Nacional BR' => 1, 'Vizinho AR' => 1, 'Asiático JP' => 1,
    ])];
    $gPref['competitions'] = ['liga-mista' => ['id' => 'liga-mista', 'matches' => [], 'done' => true]];
    $gPref['matches'] = [];

    $drawPref = TransferInterest::draw($gPref, makeSeededRng(401));
    ok(count($drawPref['offers']) === 3 && $drawPref['offer_guarantee']['exceptional_shortfall'] === false,
        'Preferência geográfica relaxa dentro do teto de overall para entregar 3 propostas compatíveis');
}, $prefRelaxClubs);

// 6E: Expansão de busca / fallback mantém a classificação esportiva EFETIVA de cada clube (nunca rotula com a faixa sorteada)
$effectiveTierCatalog = [
    'faixas-1' => [
        'id' => 'faixas-1',
        'name' => 'Liga Multi-Patamar',
        'family' => 'br',
        'tier' => 1,
        'format' => 'league',
        'teams' => ['Clube Base 72', 'Potência Elite 84', 'Clube Paridade 72', 'Clube Acesso 62'],
        'rules' => [],
    ],
];
$effectiveTierClubs = [
    'Clube Base 72'     => ['name' => 'Clube Base 72', 'country' => 'BR', 'competitions' => ['faixas-1'], 'reference_overall' => 72.0],
    'Potência Elite 84' => ['name' => 'Potência Elite 84', 'country' => 'ES', 'competitions' => ['faixas-1'], 'reference_overall' => 84.0],
    'Clube Paridade 72' => ['name' => 'Clube Paridade 72', 'country' => 'BR', 'competitions' => ['faixas-1'], 'reference_overall' => 72.0],
    'Clube Acesso 62'   => ['name' => 'Clube Acesso 62', 'country' => 'BR', 'competitions' => ['faixas-1'], 'reference_overall' => 62.0],
];
Competition::referenceScope($effectiveTierCatalog, static function (): void {
    $gEff = Game::legacyCreate('Craque Expansão', 'São Paulo', 'SP', 'meia');
    $gEff['official'] = true;
    $gEff['season'] = 4;
    $gEff['age'] = 22;
    foreach ($gEff['skills'] as &$sk) {
        $sk['value'] = 76; // habilita Elite (ovr >= 68, ceiling >= 86)
    }
    unset($sk);
    $gEff['season_skill_baseline'] = 74.0;
    $gEff['club'] = 'Clube Base 72';
    $gEff['next_club_strengths'] = [
        'Clube Base 72' => 72.0,
        'Potência Elite 84' => 84.0,
        'Clube Paridade 72' => 72.0,
        'Clube Acesso 62' => 62.0,
    ];
    $gEff['competition_id'] = 'faixas-1';
    $gEff['season_competitions'] = ['faixas-1'];
    $gEff['memberships'] = ['faixas-1' => ['Clube Base 72', 'Potência Elite 84', 'Clube Paridade 72', 'Clube Acesso 62']];
    $gEff['competitions'] = ['faixas-1' => ['id' => 'faixas-1', 'matches' => [], 'done' => true]];
    $gEff['matches'] = [];

    // Força o sorteio do maior peso acumulado na roleta (último segmento elegível = Elite, id=4)
    $rngMax = static fn(int $min, int $max): int => $max;
    $drawEff = TransferInterest::draw($gEff, $rngMax);
    $byClub = array_column($drawEff['offers'], null, 'club');

    ok($drawEff['quality_tier'] === 4 && $drawEff['quality_tier_label'] === 'Elite' && $drawEff['expanded_search'] === true,
        'Roleta sorteia Elite e aciona busca expandida por existir apenas 1 clube Elite no catálogo reduzido');
    ok(($byClub['Potência Elite 84']['quality_tier_label'] ?? '') === 'Elite'
        && ($byClub['Clube Paridade 72']['quality_tier_label'] ?? '') === 'Equivalente'
        && ($byClub['Clube Acesso 62']['quality_tier_label'] ?? '') === 'Inferior',
        'Cada proposta preserva sua classificação esportiva efetiva (Elite, Equivalente, Inferior) sem herdar o rótulo sorteado');

    $gEff['stage'] = 'oferta';
    $gEff['market_draw'] = $drawEff;
    $gEff['offers'] = $drawEff['offers'];
    ob_start();
    TransferMarket::render($gEff);
    $htmlEff = (string)ob_get_clean();
    ok(str_contains($htmlEff, 'PATAMAR ELITE')
        && str_contains($htmlEff, 'PATAMAR EQUIVALENTE')
        && str_contains($htmlEff, 'PATAMAR INFERIOR'),
        'UI renderiza a faixa esportiva efetiva individual em cada card após expansão/fallback');
}, $effectiveTierClubs);

// Salva artefato JSON com os resultados das simulações e exemplos concretos para documentação
$reportData = [
    'generated_at' => gmdate('c'),
    'checks_passed' => $checks,
    'match_rating_comparisons' => $ratingComparison,
    'season_tier_simulations' => $simulationReport,
    'concrete_offer_examples' => $concreteExamples,
];
$outPath = sys_get_temp_dir() . '/r10-car002-simulation-report.json';
file_put_contents($outPath, json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n===================================================================\n";
echo "SUCESSO: TODOS OS {$checks} CHECKS DA R10-CAR-002 FORAM APROVADOS!\n";
echo "Relatório de simulação gravado em: {$outPath}\n";
echo "===================================================================\n";

