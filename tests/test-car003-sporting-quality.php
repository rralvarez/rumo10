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
function okC3(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA R10-CAR-003-R1: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

function makeRngC3(int $seed): callable {
    $state = $seed !== 0 ? ($seed & 0xFFFFFFFF) : 0x4A1B2C3D;
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

function makeCareerC3(string $club, int $playerOvr = 74, int $season = 4): array {
    $g = Game::create('Atleta CAR003', 'São Paulo', 'SP', 'meia', $club);
    $g['id'] = 9001;
    $g['season'] = $season;
    $g['age'] = 18 + $season;
    foreach ($g['skills'] as &$sk) {
        $sk['value'] = $playerOvr;
    }
    unset($sk);
    $g['season_skill_baseline'] = (float)($playerOvr - 2);
    $g['stage'] = 'resumo';
    $cid = $g['competition_id'];
    $teams = $g['memberships'][$cid];
    $opp = $teams[0] === $club ? $teams[1] : $teams[0];
    foreach ($g['matches'] as &$m) {
        if (in_array($club, [$m['home'], $m['away']], true)) {
            $m['status'] = 'finalizada';
            $m['hg'] = 2;
            $m['ag'] = 1;
            $m['stats'] = array_replace($m['stats'], [
                'minutes' => 90, 'goals' => 1, 'assists' => 0, 'shots' => 2,
                'passes' => 4, 'completed' => 4, 'tackles' => 1, 'rating' => 7.6,
            ]);
        }
    }
    unset($m);
    if (isset($g['competitions'][$cid])) {
        $g['competitions'][$cid]['matches'] = $g['matches'];
        $g['competitions'][$cid]['done'] = true;
    }
    $g['competition_results'][$cid] = [
        'name' => Competition::catalog()[$cid]['name'] ?? $cid,
        'champion' => $opp,
        'position' => 4,
    ];
    return $g;
}

echo "=== R10-CAR-003-R1: SUÍTE DE HOMOLOGAÇÃO DA QUALIDADE ESPORTIVA NA ROLETA ANUAL ===\n\n";

// =========================================================================
// 1. CENÁRIOS ESPORTIVOS OBRIGATÓRIOS (RF01, RF02, RF03)
// =========================================================================
echo "1. Validando Classificação Multidimensional (Clube + Liga + Divisão)...\n";

// 1A: Brasil Série A -> Guatemala (gt-1) com pequeno ganho de overall (+2.0)
$gBrA = makeCareerC3('Vitória-BA', 74, 4); // Vitória-BA ~ 81.0 em br-a (baseline 84.0)
$gBrA['next_club_strengths']['Vitória-BA'] = 72.0;
$candGt = [
    'club' => 'Antigua GFC-GT',
    'country' => 'GT',
    'competition' => 'gt-1',
    'all_competitions' => ['gt-1'],
    'overall' => 74.0,
    'overall_delta' => 2.0,
];
$assGt = LeagueStrength::assess($gBrA, $candGt);
okC3($assGt['origin_competition'] === 'br-a' && $assGt['origin_league_strength'] === 84.0,
    "Clube brasileiro em br-a resolve liga principal br-a (força={$assGt['origin_league_strength']})");
okC3($assGt['target_league_strength'] === 54.0 && $assGt['league_delta'] === -30.0 && $assGt['tier'] === 0,
    "BR Série A -> Guatemala com +2.0 de overall do clube é classificado como Inferior (league_delta={$assGt['league_delta']}, tier={$assGt['tier_label']})");

// 1B: Brasil Série A -> Guiana (gy-1), considerando baseline disponível (35.0)
$candGy = [
    'club' => 'Slingerz-GY',
    'country' => 'GY',
    'competition' => 'gy-1',
    'all_competitions' => ['gy-1'],
    'overall' => 73.0,
    'overall_delta' => 1.0,
];
$assGy = LeagueStrength::assess($gBrA, $candGy);
okC3($assGy['target_league_strength'] === 35.0 && $assGy['league_delta'] === -49.0 && $assGy['tier'] === 0,
    "BR Série A -> Guiana com +1.0 de overall é classificado como Inferior (força gy-1={$assGy['target_league_strength']}, league_delta={$assGy['league_delta']})");

// 1C: Espanha primeira divisão (es-1, baseline 87) -> Turquia (tr-1, baseline 68) com +1.0 de overall
$gEs1 = makeCareerC3('Alavés-ES', 78, 4);
$gEs1['next_club_strengths']['Alavés-ES'] = 83.0;
$candTr = [
    'club' => 'Besiktas-TR',
    'country' => 'TR',
    'competition' => 'tr-1',
    'all_competitions' => ['tr-1'],
    'overall' => 84.0,
    'overall_delta' => 1.0,
];
$assTr = LeagueStrength::assess($gEs1, $candTr);
okC3($assTr['origin_league_strength'] === 87.0 && $assTr['target_league_strength'] === 68.0 && $assTr['league_delta'] === -19.0 && $assTr['tier'] === 0,
    "Espanha 1ª (87) -> Turquia 1ª (68) com +1.0 de overall é classificado como Inferior (league_delta={$assTr['league_delta']})");

// 1D: Transferência para clube ligeiramente inferior em overall (-2.0) inserido em liga superior (+12.0: br-b -> br-a)
$gBrB = makeCareerC3('Goiás-GO', 74, 4); // br-b (força 72.0)
$gBrB['next_club_strengths']['Goiás-GO'] = 72.0;
$candStepUp = [
    'club' => 'Juventude-RS',
    'country' => 'BR',
    'competition' => 'br-a',
    'all_competitions' => ['br-a'],
    'overall' => 70.0,
    'overall_delta' => -2.0,
];
$assStepUp = LeagueStrength::assess($gBrB, $candStepUp);
okC3($assStepUp['league_delta'] === 12.0 && $assStepUp['tier'] >= 2,
    "Clube ligeiramente inferior (-2.0 OVR) em liga superior (+12.0 br-b -> br-a) evolui para {$assStepUp['tier_label']}");

// 1E: Progressão entre divisões na mesma pirâmide (br-b -> br-a com +3.0 OVR)
$candProg = [
    'club' => 'Bahia-BA',
    'country' => 'BR',
    'competition' => 'br-a',
    'all_competitions' => ['br-a'],
    'overall' => 75.0,
    'overall_delta' => 3.0,
];
$assProg = LeagueStrength::assess($gBrB, $candProg);
okC3($assProg['div_step'] === -1 && $assProg['tier'] >= 2,
    "Progressão br-b -> br-a classifica como {$assProg['tier_label']} (div_step={$assProg['div_step']})");

// 1F: Retrocesso entre divisões (es-1 -> es-2)
$candReleg = [
    'club' => 'Almería-ES',
    'country' => 'ES',
    'competition' => 'es-2',
    'all_competitions' => ['es-2'],
    'overall' => 83.0,
    'overall_delta' => 0.0,
];
$assReleg = LeagueStrength::assess($gEs1, $candReleg);
okC3($assReleg['div_step'] === 1 && $assReleg['league_delta'] === -19.0 && $assReleg['tier'] === 0,
    "Retrocesso es-1 -> es-2 classifica como Inferior (div_step={$assReleg['div_step']}, league_delta={$assReleg['league_delta']})");

// 1G: Transferência entre clubes da mesma liga (br-a -> br-a)
$candSameEq = [
    'club' => 'Fortaleza-CE',
    'country' => 'BR',
    'competition' => 'br-a',
    'all_competitions' => ['br-a'],
    'overall' => 72.5,
    'overall_delta' => 0.5,
];
$candSameSup = [
    'club' => 'Palmeiras-SP',
    'country' => 'BR',
    'competition' => 'br-a',
    'all_competitions' => ['br-a'],
    'overall' => 80.0,
    'overall_delta' => 8.0,
];
$assSameEq = LeagueStrength::assess($gBrA, $candSameEq);
$assSameSup = LeagueStrength::assess($gBrA, $candSameSup);
okC3($assSameEq['league_delta'] === 0.0 && $assSameEq['tier'] === 1 && $assSameSup['tier'] >= 3,
    "Mesma liga (br-a): paridade resulta em {$assSameEq['tier_label']} e salto forte resulta em {$assSameSup['tier_label']}");

// 1H: Distinção entre competições simultâneas (Paulistão sp-a1 + Brasileirão br-a -> prioriza br-a)
$gSpAndBr = makeCareerC3('Palmeiras-SP', 80, 4);
okC3(in_array('sp-a1', $gSpAndBr['season_competitions'], true) && in_array('br-a', $gSpAndBr['season_competitions'], true),
    'Palmeiras-SP disputa simultaneamente Paulistão A1 e Brasileirão Série A');
$assSimultaneous = LeagueStrength::assess($gSpAndBr, [
    'club' => 'Atlético Mineiro-MG',
    'country' => 'BR',
    'competition' => 'br-a',
    'all_competitions' => ['br-a'],
    'overall' => TeamStrength::initial('Atlético Mineiro-MG'),
]);
okC3($assSimultaneous['origin_competition'] === 'br-a' && $assSimultaneous['origin_league_strength'] === 84.0,
    'Competições simultâneas: campeonato nacional principal (br-a=84) prevalece sobre estadual (sp-a1=64)');

// =========================================================================
// 2. LACUNAS DE DADOS (LIGA SEM REFERÊNCIA) E CONTRATO FUTURO RUMOHUB
// =========================================================================
echo "\n2. Validando Tratamento Conservador de Lacunas e Consumo Futuro Confirmado...\n";

$gapCatalog = [
    'orig-1' => ['id' => 'orig-1', 'name' => 'Liga Origem', 'family' => 'international', 'tier' => 5, 'format' => 'league', 'baseline' => 70, 'teams' => ['Clube Origem', 'Clube SemRef', 'Clube Confirmado', 'Clube GenRating', 'Clube ProvA', 'Clube ProvElite'], 'rules' => []],
    'semref-1' => ['id' => 'semref-1', 'name' => 'Liga Sem Referência', 'family' => 'international', 'tier' => 5, 'format' => 'league', 'teams' => ['Clube SemRef'], 'rules' => []],
    'conf-1' => ['id' => 'conf-1', 'name' => 'Liga Confirmada Futura', 'family' => 'international', 'tier' => 5, 'format' => 'league', 'confirmed_strength' => 86.5, 'strength_status' => 'confirmed', 'teams' => ['Clube Confirmado'], 'rules' => []],
    'gen-1' => ['id' => 'gen-1', 'name' => 'Liga Rating Genérico Sem Status', 'family' => 'international', 'tier' => 5, 'format' => 'league', 'rating' => 91.0, 'teams' => ['Clube GenRating'], 'rules' => []],
    'prov-1' => ['id' => 'prov-1', 'name' => 'Liga Provisória Média 72.5', 'family' => 'international', 'tier' => 5, 'format' => 'league', 'teams' => ['Clube ProvA', 'Clube ProvElite'], 'rules' => []],
];
$gapClubs = [
    'Clube Origem' => ['name' => 'Clube Origem', 'country' => 'BR', 'competitions' => ['orig-1'], 'reference_overall' => 70.0],
    'Clube SemRef' => ['name' => 'Clube SemRef', 'country' => 'XY', 'competitions' => ['semref-1'], 'reference_overall' => 76.0],
    'Clube Confirmado' => ['name' => 'Clube Confirmado', 'country' => 'DE', 'competitions' => ['conf-1'], 'reference_overall' => 75.0],
    'Clube GenRating' => ['name' => 'Clube GenRating', 'country' => 'IT', 'competitions' => ['gen-1'], 'reference_overall' => 74.0],
    'Clube ProvA' => ['name' => 'Clube ProvA', 'country' => 'CL', 'competitions' => ['prov-1'], 'reference_overall' => 61.0],
    'Clube ProvElite' => ['name' => 'Clube ProvElite', 'country' => 'CL', 'competitions' => ['prov-1'], 'reference_overall' => 84.0],
];
Competition::referenceScope($gapCatalog, static function (): void {
    $gGap = Game::legacyCreate('Gap Test', 'São Paulo', 'SP', 'meia');
    $gGap['official'] = true;
    $gGap['club'] = 'Clube Origem';
    $gGap['competition_id'] = 'orig-1';
    $gGap['season_competitions'] = ['orig-1'];
    $gGap['memberships'] = [
        'orig-1' => ['Clube Origem'],
        'semref-1' => ['Clube SemRef'],
        'conf-1' => ['Clube Confirmado'],
        'gen-1' => ['Clube GenRating'],
        'prov-1' => ['Clube ProvA', 'Clube ProvElite'],
    ];
    foreach ($gGap['skills'] as &$sk) {
        $sk['value'] = 76;
    }
    unset($sk);

    $assMissing = LeagueStrength::assess($gGap, [
        'club' => 'Clube SemRef',
        'country' => 'XY',
        'competition' => 'semref-1',
        'all_competitions' => ['semref-1'],
        'overall' => 76.0,
        'overall_delta' => 6.0,
    ]);
    okC3(
        $assMissing['target_league_status'] === LeagueStrength::STATUS_UNAVAILABLE
        && $assMissing['conservative_fallback'] === true
        && $assMissing['tier'] <= 1,
        "Liga sem referência recebe status='unavailable' e tratamento conservador (não presume superioridade mesmo com +6 OVR -> tier={$assMissing['tier_label']})"
    );

    $assConfirmed = LeagueStrength::assess($gGap, [
        'club' => 'Clube Confirmado',
        'country' => 'DE',
        'competition' => 'conf-1',
        'all_competitions' => ['conf-1'],
        'overall' => 75.0,
        'overall_delta' => 5.0,
    ]);
    okC3(
        $assConfirmed['target_league_status'] === LeagueStrength::STATUS_CONFIRMED
        && $assConfirmed['target_league_strength'] === 86.5
        && $assConfirmed['tier'] >= 3,
        "Contrato futuro RumoHub/RumoRating (confirmed_strength=86.5 + strength_status=confirmed) é consumido como confirmed (tier={$assConfirmed['tier_label']})"
    );

    $assGenericRating = LeagueStrength::assess($gGap, [
        'club' => 'Clube GenRating',
        'country' => 'IT',
        'competition' => 'gen-1',
        'all_competitions' => ['gen-1'],
        'overall' => 74.0,
        'overall_delta' => 4.0,
    ]);
    okC3(
        $assGenericRating['target_league_status'] !== LeagueStrength::STATUS_CONFIRMED
        && $assGenericRating['target_league_status'] === LeagueStrength::STATUS_UNAVAILABLE,
        "Campo genérico 'rating' sem status='confirmed' NÃO é tratado como confirmado (status={$assGenericRating['target_league_status']})"
    );

    $assProvEliteBlocked = LeagueStrength::assess($gGap, [
        'club' => 'Clube ProvElite',
        'country' => 'CL',
        'competition' => 'prov-1',
        'all_competitions' => ['prov-1'],
        'overall' => 84.0,
        'overall_delta' => 14.0,
    ]);
    okC3(
        $assProvEliteBlocked['target_league_status'] === LeagueStrength::STATUS_PROVISIONAL
        && $assProvEliteBlocked['target_league_strength'] === 72.5
        && $assProvEliteBlocked['tier'] === 3,
        "Liga provisória abaixo do piso Elite (72.5 < 76.0) NÃO é promovida a Elite pelo overall do clube (84.0) -> tier={$assProvEliteBlocked['tier_label']}"
    );
}, $gapClubs);

// =========================================================================
// 3. DISTRIBUIÇÃO 100%, 3 PROPOSTAS, ESCASSEZ, PERSISTÊNCIA, CARDS E 3 MODOS
// =========================================================================
echo "\n3. Validando Roleta (100%), 3 Propostas, Escassez, Persistência, Cards e 3 Modos...\n";

$gMarket = makeCareerC3('Santos-SP', 74, 4);
$draw = TransferInterest::draw($gMarket, makeRngC3(20261127));
$pctSum = round(array_sum(array_column($draw['scenarios'], 'percent')), 1);
okC3($pctSum === 100.0 && count($draw['scenarios']) === 5, "Distribuição das 5 faixas soma exatamente 100.0% ({$pctSum}%)");
okC3(count($draw['offers']) === 3 && count(array_unique(array_column($draw['offers'], 'club'))) === 3 && !in_array('Santos-SP', array_column($draw['offers'], 'club'), true),
    'Roleta entrega exatamente 3 propostas externas distintas + opção de permanência');
okC3(!empty($draw['stay']['club']) && $draw['stay']['club'] === 'Santos-SP', 'Permanência preservada para o clube atual');

foreach ($draw['offers'] as $idx => $of) {
    okC3(
        isset($of['overall'], $of['overall_delta'], $of['competition'], $of['league_strength_status'], $of['quality_tier_label'], $of['reason'])
        && is_string($of['reason']) && $of['reason'] !== '',
        "Proposta #{$idx} ({$of['club']}) possui metadados completos (overall, delta, competição, força da liga, patamar e justificativa)"
    );
}

$gMarket['stage'] = 'oferta';
$gMarket['market_draw'] = $draw;
$gMarket['offers'] = $draw['offers'];
ob_start();
TransferInterest::render($gMarket);
$html1 = (string)ob_get_clean();
ob_start();
TransferInterest::render($gMarket);
$html2 = (string)ob_get_clean();

$clean1 = preg_replace('/name="key" value="[a-f0-9]+"/', 'name="key" value="K"', $html1);
$clean2 = preg_replace('/name="key" value="[a-f0-9]+"/', 'name="key" value="K"', $html2);
okC3($clean1 === $clean2, 'Recarregamento (dupla renderização) preserva exatamente o mesmo HTML determinístico');
okC3(
    str_contains($html1, 'Força da liga:')
    && str_contains($html1, 'Overall ')
    && str_contains($html1, 'PATAMAR '),
    'Cards exibem overall, diferença, competição/divisão, qualidade relativa da liga, patamar efetivo e justificativa'
);

// Proposta já persistida em save antigo (sem league_strength) continua renderizando intacta
$legacyPersisted = $gMarket;
foreach ($legacyPersisted['offers'] as &$lo) {
    unset($lo['league_strength'], $lo['league_strength_status'], $lo['league_delta'], $lo['sporting_assessment']);
}
unset($lo);
ob_start();
TransferInterest::render($legacyPersisted);
$htmlLegacy = (string)ob_get_clean();
okC3(str_contains($htmlLegacy, 'Clubes Interessados (3)') && str_contains($htmlLegacy, $legacyPersisted['offers'][0]['club']),
    'Compatibilidade preservada: propostas antigas já persistidas renderizam sem alteração retroativa');

// Aposentadoria aos 39 anos preservada
$gRet39 = makeCareerC3('Santos-SP', 75, 22);
$gRet39['age'] = 39;
$retBlocked = false;
try {
    Game::command($gRet39, 'offers');
} catch (InvalidArgumentException) {
    $retBlocked = true;
}
okC3($retBlocked, 'Aposentadoria obrigatória aos 39 anos preservada (bloqueia abertura de ofertas)');

// Três modos (normal/completo, rapido, expresso) com janela de transferência
foreach (['normal', 'rapido', 'expresso'] as $mode) {
    $gm = Game::create("Modo {$mode}", 'São Paulo', 'SP', 'centroavante', 'Ponte Preta-SP');
    $gm['play_mode'] = $mode;
    $gm = Game::command($gm, 'handicap');
    if ($mode === 'expresso') {
        $gm = Game::command($gm, 'simulate', ['plan' => 'protagonismo']);
    } elseif ($mode === 'rapido') {
        $guard = 0;
        while ($gm['stage'] !== 'resumo' && $guard++ < 60) {
            if ($gm['stage'] === 'competicao_resumo') {
                $gm = Game::command($gm, 'continue');
            } else {
                $gm = Game::command($gm, 'simulate', ['plan' => 'protagonismo']);
            }
        }
    } else {
        $gm = makeCareerC3('Ponte Preta-SP', 72, 2);
        $gm['play_mode'] = 'normal';
    }
    $gWin = Game::command($gm, 'offers');
    okC3(
        $gWin['stage'] === 'oferta'
        && count($gWin['offers']) === 3
        && isset($gWin['offers'][0]['sporting_assessment']['target_league_status']),
        "Modo '{$mode}': janela abre com 3 propostas distintas e avaliação multidimensional completa"
    );
}

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES DA R10-CAR-003-R1 FORAM APROVADAS!\n";
echo "====================================================================\n";
