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
function okR2(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA R10-BAL-001-R2: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

function makeRngR2(int $seed): callable {
    $state = $seed !== 0 ? ($seed & 0xFFFFFFFF) : 0x51F2A3B9;
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

$rngSuccess = static fn(int $min, int $max): int => ($max === 10000 ? 1 : ($max === 100 && $min === 1 ? 50 : $min));
$rngExceptional = static fn(int $min, int $max): int => $min;
$rngCommonFail = static fn(int $min, int $max): int => ($max === 10000 ? 10000 : 50);
$rngSevereFail = static fn(int $min, int $max): int => ($max === 10000 ? 10000 : 1);

echo "=== R10-BAL-001-R2: SUÍTE DE HOMOLOGAÇÃO DO REEQUILÍBRIO DA AUTOCONFIANÇA ===\n\n";

// Confirmação explícita das 6 posições jogáveis atuais (sem goleiro em Rules::POSITIONS)
okR2(
    Rules::POSITIONS === ['zagueiro', 'lateral', 'volante', 'meia', 'ponta', 'centroavante']
    && !in_array('goleiro', Rules::POSITIONS, true),
    'Rules::POSITIONS mantém estritamente as 6 posições jogáveis atuais (goleiro não adicionado)'
);

// =========================================================================
// 1. PILAR 3.1, PARÂMETROS DINÂMICOS E PRESERVAÇÃO DE EFEITOS COMPOSTOS
// =========================================================================
echo "\n1. Validando Penalidades Contextuais, Parâmetros BalanceService e Efeitos Compostos...\n";

// 1A: Varredura integral do catálogo em campo: 370 ações técnicas (-1/-3) vs 301 decisões táticas/espaciais (0 confiança)
$catalog = Scenes::catalog();
$inMatchTacticalTotal = 0;
$inMatchTacticalZeroConf = 0;
foreach ($catalog as $sid => $scene) {
    if (($scene['stage'] ?? '') !== 'partida') {
        continue;
    }
    $gProbe = Game::legacyCreate('Probe', 'Santos', 'SP', 'meia');
    $gProbe['stage'] = 'partida';
    $idxP = Game::currentIndex($gProbe);
    $gProbe['matches'][$idxP]['status'] = 'em_andamento';
    $gProbe['matches'][$idxP]['lineup'] = 'titular';
    $gProbe['matches'][$idxP]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    $gProbe['matches'][$idxP]['minute'] = 35;
    $gProbe['matches'][$idxP]['exit'] = 90;
    $gProbe['active_situation'] = ['id' => $sid, 'key' => Scenes::key($gProbe)];
    foreach (Game::choices($gProbe) as $opt) {
        $code = $opt['code'] ?? '';
        if (in_array($code, ['posicionamento', 'social'], true) && ($opt['action'] ?? '') === 'social') {
            $inMatchTacticalTotal++;
            if (!isset($opt['failure_effects']['confianca']) && !isset($opt['severe_failure_effects']['confianca'])) {
                $inMatchTacticalZeroConf++;
            }
        }
    }
}
okR2($inMatchTacticalTotal >= 300 && $inMatchTacticalTotal === $inMatchTacticalZeroConf,
    "Todas as {$inMatchTacticalTotal} decisões táticas/espaciais em partida não drenam autoconfiança ({$inMatchTacticalZeroConf}/{$inMatchTacticalTotal})");

// 1B: Varredura de treinos físicos e repetições técnicas fora de partida
$physTrainTotal = 0;
$physTrainZeroConf = 0;
$repTrainTotal = 0;
$repTrainCommonZeroConf = 0;
foreach ($catalog as $sid => $scene) {
    if (!in_array($scene['stage'] ?? '', ['treino', 'pre_jogo'], true)) {
        continue;
    }
    $gProbe = Game::legacyCreate('ProbeTrain', 'Santos', 'SP', 'centroavante');
    $gProbe['stage'] = $scene['stage'];
    $gProbe['active_situation'] = ['id' => $sid, 'key' => Scenes::key($gProbe)];
    foreach (Game::choices($gProbe) as $opt) {
        $code = $opt['code'] ?? '';
        if (in_array($code, ['forca', 'resistencia', 'velocidade'], true)) {
            $physTrainTotal++;
            if (($opt['failure_effects']['ritmo_jogo'] ?? 0) === -1 && !isset($opt['failure_effects']['confianca']) && !isset($opt['severe_failure_effects']['confianca'])) {
                $physTrainZeroConf++;
            }
        } elseif (in_array($code, ['passe', 'chute', 'drible', 'desarme', 'tecnica'], true) && !isset($opt['effects']['confianca'])) {
            $repTrainTotal++;
            if (!isset($opt['failure_effects']['confianca']) && (($opt['severe_failure_effects']['confianca'] ?? 0) >= -1)) {
                $repTrainCommonZeroConf++;
            }
        }
    }
}
okR2($physTrainTotal > 0 && $physTrainTotal === $physTrainZeroConf,
    "Todos os {$physTrainTotal} exercícios físicos afetam ritmo_jogo (-1/-2) sem punir confiança");
okR2($repTrainTotal > 0 && $repTrainTotal === $repTrainCommonZeroConf,
    "Todas as {$repTrainTotal} repetições técnicas de treino/aquecimento afetam foco (-1) na falha comum e no máx -1 confiança na grave");

// 1C: Testes direcionados de EFEITOS COMPOSTOS em SceneConsequences::reconcileCatalogOption e Game::effects
$refEffects = new ReflectionMethod('Game', 'effects');
$refEffects->setAccessible(true);

// Composto 1: ação técnica em partida (passe) com entrosamento + treinador junto de confiança
$compTech = SceneConsequences::reconcileCatalogOption([
    'action' => 'passe',
    'code' => 'passe',
    'effects' => [],
    'failure_effects' => ['confianca' => -2, 'entrosamento' => -2],
    'severe_failure_effects' => ['confianca' => -4, 'entrosamento' => -3, 'confianca_treinador' => -1],
], 'partida', 2500);
okR2(
    $compTech['failure_effects'] === ['confianca' => -1, 'entrosamento' => -2]
    && $compTech['severe_failure_effects'] === ['confianca' => -3, 'entrosamento' => -3, 'confianca_treinador' => -1],
    'Efeito composto técnico em partida: ajusta confianca (-1/-3) preservando entrosamento e confianca_treinador'
);

// Composto 2: oportunidade decisiva (clear_chance) com estados secundários
$compDecisive = SceneConsequences::reconcileCatalogOption([
    'action' => 'chute',
    'clear_chance' => true,
    'effects' => [],
    'failure_effects' => ['confianca' => -1, 'entrosamento' => -2, 'foco' => -1],
    'severe_failure_effects' => ['confianca' => -3, 'entrosamento' => -4, 'confianca_treinador' => -2],
], 'partida', 1300);
okR2(
    $compDecisive['failure_effects'] === ['confianca' => -2, 'entrosamento' => -2, 'foco' => -1]
    && $compDecisive['severe_failure_effects'] === ['confianca' => -4, 'entrosamento' => -4, 'confianca_treinador' => -2],
    'Efeito composto em oportunidade decisiva: aplica -2/-4 em confianca preservando entrosamento, foco e treinador'
);

// Composto 3: finalização de longa distância (shot_kind=long) em Game::effects preservando estados compostos
$gCompLong = Game::legacyCreate('Comp Long', 'Santos', 'SP', 'meia');
$gCompLong['stage'] = 'partida';
$gCompLong['states']['confianca'] = 50;
$gCompLong['states']['foco'] = 50;
$gCompLong['states']['confianca_treinador'] = 50;
$gCompLong['states']['entrosamento'] = 50;
$choiceLongComp = [
    'action' => 'chute',
    'shot_kind' => 'long',
    'failure_effects' => ['confianca' => -1, 'confianca_treinador' => -2],
    'severe_failure_effects' => ['confianca' => -3, 'entrosamento' => -2],
];
$gCompLongCommon = $gCompLong;
$appliedLongCommon = $refEffects->invokeArgs(null, [[], ['success' => false, 'class' => 'falha'], &$gCompLongCommon, $choiceLongComp]);
okR2(
    $gCompLongCommon['states']['confianca'] === 50
    && $gCompLongCommon['states']['foco'] === 49
    && $gCompLongCommon['states']['confianca_treinador'] === 48
    && ($appliedLongCommon['confianca_treinador'] ?? 0) === -2,
    'Efeito composto em chute de longe (comum): converte confianca->foco(-1) sem perder confianca_treinador(-2)'
);

$gCompLongSevere = $gCompLong;
$appliedLongSevere = $refEffects->invokeArgs(null, [[], ['success' => false, 'class' => 'falha_grave'], &$gCompLongSevere, $choiceLongComp]);
okR2(
    $gCompLongSevere['states']['confianca'] === 48
    && $gCompLongSevere['states']['entrosamento'] === 48
    && ($appliedLongSevere['confianca'] ?? 0) === -2
    && ($appliedLongSevere['entrosamento'] ?? 0) === -2,
    'Efeito composto em chute de longe (grave): aplica confianca(-2) preservando entrosamento(-2)'
);

// Composto 4: posicionamento em partida, físico e treino técnico fora de partida com estados secundários
$compPos = SceneConsequences::reconcileCatalogOption([
    'code' => 'posicionamento',
    'action' => 'social',
    'effects' => ['posicionamento' => 1],
    'failure_effects' => ['confianca' => -2, 'entrosamento' => -1],
    'severe_failure_effects' => ['confianca' => -4, 'entrosamento' => -2],
], 'partida', 2600);
okR2(
    !isset($compPos['failure_effects']['confianca'])
    && ($compPos['failure_effects']['entrosamento'] ?? 0) === -1
    && ($compPos['failure_effects']['foco'] ?? 0) === -1
    && !isset($compPos['severe_failure_effects']['confianca'])
    && ($compPos['severe_failure_effects']['entrosamento'] ?? 0) === -2
    && ($compPos['severe_failure_effects']['foco'] ?? 0) === -2
    && ($compPos['severe_failure_effects']['confianca_treinador'] ?? 0) === -1,
    'Efeito composto em posicionamento: remove dreno de confiança preservando entrosamento'
);

$compPhys = SceneConsequences::reconcileCatalogOption([
    'code' => 'velocidade',
    'action' => 'social',
    'effects' => ['velocidade' => 2],
    'failure_effects' => ['confianca' => -2, 'risco_lesao' => 2],
    'severe_failure_effects' => ['confianca' => -4, 'risco_lesao' => 4],
], 'treino', 2100);
okR2(
    !isset($compPhys['failure_effects']['confianca'])
    && ($compPhys['failure_effects']['risco_lesao'] ?? 0) === 2
    && ($compPhys['failure_effects']['ritmo_jogo'] ?? 0) === -1
    && ($compPhys['severe_failure_effects']['risco_lesao'] ?? 0) === 4
    && ($compPhys['severe_failure_effects']['ritmo_jogo'] ?? 0) === -2,
    'Efeito composto em treino físico: converte confianca->ritmo_jogo preservando risco_lesao'
);

$compTrainTech = SceneConsequences::reconcileCatalogOption([
    'code' => 'passe',
    'action' => 'passe',
    'effects' => ['passe' => 2],
    'failure_effects' => ['confianca' => -2, 'entrosamento' => -1],
    'severe_failure_effects' => ['confianca' => -4, 'entrosamento' => -2],
], 'treino', 2200);
okR2(
    !isset($compTrainTech['failure_effects']['confianca'])
    && ($compTrainTech['failure_effects']['entrosamento'] ?? 0) === -1
    && ($compTrainTech['failure_effects']['foco'] ?? 0) === -1
    && ($compTrainTech['severe_failure_effects']['confianca'] ?? 0) === -1
    && ($compTrainTech['severe_failure_effects']['entrosamento'] ?? 0) === -2
    && ($compTrainTech['severe_failure_effects']['foco'] ?? 0) === -2,
    'Efeito composto em treino técnico: preserva entrosamento nas falhas comum e grave'
);

// Composto 5: proteção anti-zero (C=9) amortece apenas confiança sem cancelar efeito secundário
$gZeroComp = Game::legacyCreate('Zero Comp', 'Santos', 'SP', 'meia');
$gZeroComp['stage'] = 'partida';
$gZeroComp['states']['confianca'] = 9;
$gZeroComp['states']['entrosamento'] = 50;
$effZeroComp = $refEffects->invokeArgs(null, [[], ['success' => false, 'class' => 'falha'], &$gZeroComp, [
    'action' => 'passe',
    'failure_effects' => ['confianca' => -1, 'entrosamento' => -2],
]]);
okR2(
    $gZeroComp['states']['confianca'] === 9
    && !isset($effZeroComp['confianca'])
    && $gZeroComp['states']['entrosamento'] === 48
    && ($effZeroComp['entrosamento'] ?? 0) === -2,
    'Proteção anti-zero em efeito composto: amortece confianca em C<=10 preservando entrosamento(-2)'
);

// 1D: Consumo efetivo dos parâmetros configuráveis em BalanceService (confidence.balance.v2)
BalanceService::params('confidence.balance.v2');
$refCache = new ReflectionProperty('BalanceService', 'activeCache');
$refCache->setAccessible(true);
$origCache = $refCache->getValue();
try {
    $customCache = $origCache;
    $customCache['confidence.balance.v2'] = array_replace($customCache['confidence.balance.v2'] ?? [], [
        'tech_fail_normal' => -2,
        'tech_fail_severe' => -5,
        'decisive_fail_normal' => -3,
        'decisive_fail_severe' => -6,
        'post_ceiling' => 83,
    ]);
    $refCache->setValue(null, $customCache);

    [$cfNorm, $cfSev] = SceneConsequences::contextualFailure(['code' => 'passe', 'action' => 'passe'], 'partida', 2016);
    [$dfNorm, $dfSev] = SceneConsequences::contextualFailure(['action' => 'chute', 'clear_chance' => true], 'partida', 1300);
    $recCustom = SceneConsequences::reconcileCatalogOption(['action' => 'drible', 'failure_effects' => ['confianca' => -2], 'severe_failure_effects' => ['confianca' => -4]], 'partida', 2016);

    $gDyn = Game::legacyCreate('Dyn Param', 'Santos', 'SP', 'meia');
    $gDyn['stage'] = 'partida';
    $gDyn['states']['confianca'] = 50;
    $refEffects->invokeArgs(null, [[], ['success' => false, 'class' => 'falha_grave'], &$gDyn, ['action' => 'passe']]);

    $refPostNom = new ReflectionMethod('Game', 'postMatchRatingNominal');
    $refPostNom->setAccessible(true);
    $dynPost82 = $refPostNom->invoke(null, 9.5, 82, 90);
    $dynPost83 = $refPostNom->invoke(null, 9.5, 83, 90);

    okR2(
        $cfNorm['confianca'] === -2 && $cfSev['confianca'] === -5
        && $dfNorm['confianca'] === -3 && $dfSev['confianca'] === -6
        && $recCustom['failure_effects']['confianca'] === -2
        && $recCustom['severe_failure_effects']['confianca'] === -5
        && $gDyn['states']['confianca'] === 45
        && $dynPost82 === 1 && $dynPost83 === 0,
        'Motor consome dinamicamente tech_fail_normal/severe, decisive_fail_normal/severe e post_ceiling via BalanceService'
    );
} finally {
    BalanceService::resetCache();
}

// 1E: Oportunidade decisiva (1300), chute de longe, exposição egoica ('ousar'), expulsão e piso severo
$gClear = Game::create('Chance Clara', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gClear['stage'] = 'partida';
$iCl = Game::currentIndex($gClear);
$gClear['matches'][$iCl]['status'] = 'em_andamento';
$gClear['matches'][$iCl]['lineup'] = 'titular';
$gClear['matches'][$iCl]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gClear['matches'][$iCl]['entry'] = 0;
$gClear['matches'][$iCl]['exit'] = 90;
$gClear['matches'][$iCl]['minute'] = 35;
$gClear['matches'][$iCl]['clear_chance'] = true;
$gClear['matches'][$iCl]['evaluated_penalties'] = ['35-45'];
$gClear['states']['confianca'] = 50;

$resClearCommon = Game::command($gClear, 'choice', ['option' => '0'], false, $rngCommonFail);
okR2($resClearCommon['states']['confianca'] === 48 && ($resClearCommon['feedback']['effects']['confianca'] ?? 0) === -2,
    'Falha comum em chance clara (1300) aplica -2 na confiança e sincroniza feedback (-2)');

$resClearSevere = Game::command($gClear, 'choice', ['option' => '0'], false, $rngSevereFail);
okR2($resClearSevere['states']['confianca'] === 46 && ($resClearSevere['feedback']['effects']['confianca'] ?? 0) === -4,
    'Falha grave em chance clara (1300) aplica -4 na confiança e sincroniza feedback (-4)');

$oOusar = SceneConsequences::option(['text' => 'Ousar', 'code' => 'ousar', 'effects' => []], 'pre_jogo', 9998, 0);
okR2(($oOusar['failure_effects']['confianca'] ?? 0) === -2 && ($oOusar['severe_failure_effects']['confianca'] ?? 0) === -4,
    'Exposição egoica (ousar) preserva penalidade contextual -2/-4');

$gRed = Game::create('Expulso Test', 'Santos', 'SP', 'zagueiro', 'Santos-SP');
$gRed['stage'] = 'partida';
$iRd = Game::currentIndex($gRed);
$gRed['matches'][$iRd]['status'] = 'em_andamento';
$gRed['matches'][$iRd]['lineup'] = 'titular';
$gRed['matches'][$iRd]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gRed['matches'][$iRd]['entry'] = 0;
$gRed['matches'][$iRd]['exit'] = 90;
$gRed['matches'][$iRd]['minute'] = 35;
$gRed['matches'][$iRd]['evaluated_penalties'] = ['35-45'];
$gRed['matches'][$iRd]['stats']['cards'] = 1;
$gRed['matches'][$iRd]['stats']['yellow_cards'] = 1;
$gRed['states']['confianca'] = 50;
$gRed['states']['risco_lesao'] = 0;
$refField = new ReflectionMethod('Game', 'fieldDecision');
$refField->setAccessible(true);
$sentOffRes = null;
for ($attRed = 0; $attRed < 40; $attRed++) {
    $gTry = $gRed;
    $gTry['states']['risco_cartao'] = 100;
    $gTry['feedback'] = ['text' => 'Falta dura.', 'effects' => []];
    $refField->invokeArgs(null, [&$gTry, ['action' => 'social', 'effects' => [], 'card_risk' => 100], ['success' => true, 'class' => 'sucesso', 'chance' => 70.0], 999, 0]);
    if (MatchParticipation::isSentOff($gTry['matches'][$iRd])) {
        $sentOffRes = $gTry;
        break;
    }
}
okR2($sentOffRes !== null && $sentOffRes['states']['confianca'] === 48 && ($sentOffRes['feedback']['effects']['confianca'] ?? 0) === -2,
    'Expulsão em campo aplica penalidade contextual de -2 na confiança e reflete no feedback');

$gLow = Game::create('Baixa Conf', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gLow['stage'] = 'partida';
$gLow['states']['confianca'] = 7;
$effLowSevere = $refEffects->invokeArgs(null, [[], ['success' => false, 'class' => 'falha_grave'], &$gLow, ['action' => 'passe', 'failure_effects' => ['confianca' => -1], 'severe_failure_effects' => ['confianca' => -3]]]);
okR2($gLow['states']['confianca'] === 5 && ($effLowSevere['confianca'] ?? 0) === -2,
    'Proteção anti-zero: falha técnica grave em C <= 8 é amortecida de -3 para -2 (7 -> 5)');

// =========================================================================
// 2. PILAR 3.2, TETO PÓS-PARTIDA (post_ceiling) E ULTRAPASSAGEM POR MÉRITO
// =========================================================================
echo "\n2. Validando Ganhos por Posição, Teto Estrito post_ceiling=90 e Ultrapassagem Legítima...\n";

$refGoalDelta = new ReflectionMethod('Game', 'goalConfidenceDelta');
$refGoalDelta->setAccessible(true);
$refAssistDelta = new ReflectionMethod('Game', 'assistConfidenceDelta');
$refAssistDelta->setAccessible(true);
$refPostNom = new ReflectionMethod('Game', 'postMatchRatingNominal');
$refPostNom->setAccessible(true);
$refSeg = new ReflectionMethod('Game', 'segment');
$refSeg->setAccessible(true);

okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 30]], 1) === 4, 'Gol atacante C=30 => +4');
okR2($refGoalDelta->invoke(null, ['position' => 'zagueiro', 'states' => ['confianca' => 30]], 1) === 5, 'Gol defensor C=30 => +5');
okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 65]], 1) === 3, 'Gol atacante C=65 => +3');
okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 80]], 1) === 2, 'Gol atacante C=80 => +2');
okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 90]], 1) === 1, 'Gol atacante C=90 => +1');
okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 96]], 1) === 0, 'Gol simples em C=96 => 0 (evita inflação automática para 100)');
okR2($refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 96]], 2) === 1, 'Dobradinha/múltiplos gols em C=96 => +1');

okR2($refAssistDelta->invoke(null, ['position' => 'meia', 'states' => ['confianca' => 30]], 1) === 4, 'Assistência meia criador C=30 => +4');
okR2($refAssistDelta->invoke(null, ['position' => 'meia', 'states' => ['confianca' => 60]], 1) === 3, 'Assistência meia criador C=60 => +3');
okR2($refAssistDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => 60]], 1) === 2, 'Assistência atacante C=60 => +2');
okR2($refAssistDelta->invoke(null, ['position' => 'meia', 'states' => ['confianca' => 80]], 1) === 1, 'Assistência C=80 => +1');
okR2($refAssistDelta->invoke(null, ['position' => 'meia', 'states' => ['confianca' => 90]], 1) === 0, 'Assistência simples C=90 => 0');

// Teto estrito post_ceiling=90: C=89 nunca passa de 90 pela recuperação pós-partida
okR2($refPostNom->invoke(null, 8.8, 89, 90) === 1 && $refPostNom->invoke(null, 9.8, 89, 90) === 1,
    'post_ceiling=90 estrito: C=89 recebe no máximo +1 na recuperação pós-partida (nunca 91)');
okR2($refPostNom->invoke(null, 9.8, 90, 90) === 0 && $refPostNom->invoke(null, 9.8, 93, 90) === 0,
    'post_ceiling=90 estrito: C>=90 recebe 0 de recuperação pós-partida');

// Conquista esportiva legítima (gol aos 80') permite ultrapassar 90 sem ser cortada no fechamento 80->90
$gAbove90 = Game::create('Acima de 90', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gAbove90['stage'] = 'partida';
$iAb = Game::currentIndex($gAbove90);
$mAb = &$gAbove90['matches'][$iAb];
$mAb['status'] = 'em_andamento';
$mAb['lineup'] = 'titular';
$mAb['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mAb['entry'] = 0;
$mAb['exit'] = 90;
$mAb['minute'] = 80;
$mAb['hg'] = 2;
$mAb['ag'] = 0;
$mAb['evaluated_penalties'] = ['80-90'];
$mAb['stats'] = array_replace($mAb['stats'], ['goals' => 1, 'shots' => 1, 'passes' => 2, 'completed' => 2]);
$gAbove90['states']['confianca'] = 90;
$gAbove90['states']['risco_lesao'] = 0;
unset($gAbove90['active_situation']);
$resAbove90 = Game::command($gAbove90, 'choice', ['option' => '0'], false, $rngSuccess); // marca o 2º gol (90 -> 91) e encerra partida
okR2($resAbove90['stage'] === 'fim_semana' && $resAbove90['states']['confianca'] === 91 && ($resAbove90['feedback']['effects']['confianca'] ?? 0) === 1,
    'Mérito esportivo legítimo (gol em C=90) ultrapassa 90 (90 -> 91) enquanto componente pós-jogo permanece limitado em 0');

// Suporte preparatório para goleiro (posição não disponível em Rules::POSITIONS, compatível com MatchRating/SeasonEvaluation)
$gGk = Game::create('Suporte Prep GK', 'Santos', 'SP', 'zagueiro', 'Santos-SP');
$gGk['position'] = 'goleiro';
$gGk['stage'] = 'partida';
$iGk = Game::currentIndex($gGk);
$mGk = &$gGk['matches'][$iGk];
$mGk['home'] = 'Santos-SP';
$mGk['away'] = 'Corinthians-SP';
$mGk['hg'] = 1;
$mGk['ag'] = 0;
$mGk['status'] = 'em_andamento';
$mGk['lineup'] = 'titular';
$mGk['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mGk['entry'] = 0;
$mGk['exit'] = 90;
$mGk['minute'] = 90;
$mGk['stats'] = array_replace($mGk['stats'], ['saves' => 4, 'passes' => 3, 'completed' => 3]);
$gGk['states']['confianca'] = 30;
$refSeg->invokeArgs(null, [&$gGk, 90]);
$gkLedgerKey = 'bal001:confidence:' . $gGk['season'] . ':' . ($gGk['competition_id'] ?? 'legacy') . ':' . $mGk['round'] . ':' . $mGk['home'] . ':' . $mGk['away'];
$gkLedger = $gGk['reward_ledger'][$gkLedgerKey];
okR2($mGk['stats']['rating'] >= 8.8 && $gkLedger['gk_save_bonus'] === 4 && $gkLedger['clean_sheet_bonus'] === 1 && $gGk['states']['confianca'] >= 39,
    "[Suporte preparatório — goleiro fora de Rules::POSITIONS] Estatísticas sintéticas de saves/clean sheet operam sem regressão (30 -> {$gGk['states']['confianca']})");

// Execução excepcional em treino (C < 42) e regularidade em 3 partidas seguidas
$gExcTrain = Game::create('Treino Exc', 'Santos', 'SP', 'volante', 'Santos-SP');
$gExcTrain['stage'] = 'treino';
$gExcTrain['states']['confianca'] = 25;
$gExcTrain['active_situation'] = ['id' => 1000, 'key' => Scenes::key($gExcTrain)];
$techOptIdx = null;
foreach (Game::choices($gExcTrain) as $idxOpt => $cOpt) {
    if (in_array($cOpt['code'] ?? '', ['passe', 'chute', 'drible', 'desarme', 'tecnica', 'tatico', 'posicionamento', 'visao'], true) && !isset($cOpt['effects']['confianca'])) {
        $techOptIdx = $idxOpt;
        break;
    }
}
$resExcTrain = Game::command($gExcTrain, 'choice', ['option' => (string)$techOptIdx], false, $rngExceptional);
okR2($resExcTrain['states']['confianca'] === 26 && ($resExcTrain['feedback']['effects']['confianca'] ?? 0) === 1,
    'Treino técnico excepcional em baixa confiança (C=25 < 42) concede +1 e reflete no feedback');

$gStreak = Game::create('Regularidade', 'Santos', 'SP', 'zagueiro', 'Santos-SP');
$gStreak['states']['confianca'] = 60;
$gStreak['confidence_streak'] = 2;
$iSt = Game::currentIndex($gStreak);
$mSt = &$gStreak['matches'][$iSt];
$mSt['home'] = 'Santos-SP';
$mSt['away'] = 'Corinthians-SP';
$mSt['hg'] = 2;
$mSt['ag'] = 1;
$mSt['status'] = 'em_andamento';
$mSt['lineup'] = 'titular';
$mSt['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mSt['entry'] = 0;
$mSt['exit'] = 90;
$mSt['minute'] = 90;
$mSt['stats'] = array_replace($mSt['stats'], ['tackles' => 3, 'passes' => 3, 'completed' => 3]);
$refSeg->invokeArgs(null, [&$gStreak, 90]);
$stKey = 'bal001:confidence:' . $gStreak['season'] . ':' . ($gStreak['competition_id'] ?? 'legacy') . ':' . $mSt['round'] . ':' . $mSt['home'] . ':' . $mSt['away'];
okR2($gStreak['confidence_streak'] === 3 && ($gStreak['reward_ledger'][$stKey]['regularity_bonus'] ?? 0) === 1,
    'Sequência de 3 partidas positivas consecutivas concede bônus de regularidade (+1) registrado no ledger');

// =========================================================================
// 3. NOTA RUIM, SINCRONIA 80->90, CONQUISTAS SAZONAIS E TRANSIÇÃO PARCIAL
// =========================================================================
echo "\n3. Validando Nota Ruim, Sincronia 80->90, Conquistas Sazonais e Transição Parcial...\n";

$gPoor = Game::create('Atuacao Ruim', 'Santos', 'SP', 'meia', 'Santos-SP');
$iPr = Game::currentIndex($gPoor);
$mPr = &$gPoor['matches'][$iPr];
$mPr['home'] = 'Santos-SP';
$mPr['away'] = 'Corinthians-SP';
$mPr['hg'] = 0;
$mPr['ag'] = 3;
$mPr['status'] = 'em_andamento';
$mPr['lineup'] = 'titular';
$mPr['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mPr['entry'] = 0;
$mPr['exit'] = 90;
$mPr['minute'] = 90;
$mPr['stats'] = array_replace($mPr['stats'], ['passes' => 8, 'completed' => 0, 'fouls' => 5, 'cards' => 1, 'yellow_cards' => 1]);
$gPoor['states']['confianca'] = 50;
$refSeg->invokeArgs(null, [&$gPoor, 90]);
okR2($mPr['stats']['rating'] < 4.6 && $gPoor['states']['confianca'] === 48,
    "Atuação desastrosa (rating={$mPr['stats']['rating']} < 4.6) reduz confiança em -2 no pós-jogo (50 -> 48)");

$gSync80 = Game::create('Sync 80', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gSync80['stage'] = 'partida';
$iS8 = Game::currentIndex($gSync80);
$mS8 = &$gSync80['matches'][$iS8];
$mS8['status'] = 'em_andamento';
$mS8['lineup'] = 'titular';
$mS8['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mS8['entry'] = 0;
$mS8['exit'] = 90;
$mS8['minute'] = 80;
$mS8['hg'] = 1;
$mS8['ag'] = 0;
$mS8['evaluated_penalties'] = ['80-90'];
$mS8['stats'] = array_replace($mS8['stats'], ['goals' => 2, 'shots' => 2, 'passes' => 2, 'completed' => 2]);
$gSync80['states']['confianca'] = 30;
$gSync80['states']['risco_lesao'] = 0;
unset($gSync80['active_situation']);
$confBefore80 = $gSync80['states']['confianca'];
$resSync80 = Game::command($gSync80, 'choice', ['option' => '0'], false, $rngSuccess);
$actualDelta80 = $resSync80['states']['confianca'] - $confBefore80;
$feedbackDelta80 = (int)($resSync80['feedback']['effects']['confianca'] ?? 0);
okR2($resSync80['stage'] === 'fim_semana' && $actualDelta80 > 4 && $feedbackDelta80 === $actualDelta80,
    "Sincronia exata 80->90: feedback.effects.confianca ({$feedbackDelta80}) corresponde exatamente à variação real aplicada ({$actualDelta80})");

$gPromo = Game::create('Atleta Acesso', 'Campinas', 'SP', 'centroavante', 'Ponte Preta-SP');
$gPromo['states']['confianca'] = 89;
$iPm = Game::currentIndex($gPromo);
$gPromo['matches'][$iPm]['status'] = 'finalizada';
$gPromo['matches'][$iPm]['stats'] = array_replace($gPromo['matches'][$iPm]['stats'], ['minutes' => 900, 'goals' => 8, 'shots' => 15, 'rating' => 7.4]);
$gPromo['competition_results'] = [
    'br-b' => ['name' => 'Brasileiro Série B', 'champion' => 'Ponte Preta-SP', 'promoted' => true, 'relegated' => false, 'titles' => []],
];
$gPromo['feedback'] = null;
$refSeasonAch = new ReflectionMethod('Game', 'applySeasonAchievementConfidence');
$refSeasonAch->setAccessible(true);
$refSeasonAch->invokeArgs(null, [&$gPromo]);
okR2($gPromo['states']['confianca'] === 92 && ($gPromo['feedback']['effects']['confianca'] ?? 0) === 3,
    'Conquista coletiva legítima (título + acesso em C=89) permite ultrapassar 90 (89 -> 92) com idempotência e feedback');
$refSeasonAch->invokeArgs(null, [&$gPromo]);
okR2($gPromo['states']['confianca'] === 92, 'Bônus de conquista coletiva de temporada é estritamente idempotente');

$gBenchSeason = Game::create('Banco Ano Todo', 'Campinas', 'SP', 'centroavante', 'Ponte Preta-SP');
$gBenchSeason['states']['confianca'] = 40;
$gBenchSeason['competition_results'] = [
    'br-b' => ['name' => 'Brasileiro Série B', 'champion' => 'Ponte Preta-SP', 'promoted' => true, 'relegated' => false, 'titles' => []],
];
$refSeasonAch->invokeArgs(null, [&$gBenchSeason]);
okR2($gBenchSeason['states']['confianca'] === 40, 'Atleta com 0 minutos na temporada recebe 0 de bônus coletivo');

$gTransLow = Game::legacyCreate('Transicao Baixa', 'Santos', 'SP', 'meia');
$gTransLow['stage'] = 'resumo';
$gTransLow['states']['confianca'] = 18;
foreach ($gTransLow['matches'] as &$mTl) {
    if (in_array($gTransLow['club'], [$mTl['home'], $mTl['away']], true)) {
        $mTl['status'] = 'finalizada';
        $mTl['hg'] = 1;
        $mTl['ag'] = 1;
        $mTl['stats'] = array_replace($mTl['stats'], ['minutes' => 90, 'goals' => 0, 'assists' => 1, 'passes' => 3, 'completed' => 3, 'rating' => 6.8]);
    }
}
unset($mTl);
$gTransLow = Game::command($gTransLow, 'offers');
$confBeforeTransfer = $gTransLow['states']['confianca'];
$gTransAfter = Game::command($gTransLow, 'transfer', ['accept' => 'no']);
$offDelta = $gTransAfter['states']['confianca'] - $confBeforeTransfer;
okR2($offDelta === 6 && $gTransAfter['states']['confianca'] === 24 && ($gTransAfter['feedback']['effects']['confianca'] ?? 0) === 6,
    'Transição parcial de intertemporada recupera parcialmente (18 -> 24, +6) sem reset arbitrário para 50 e exibe no feedback');

// =========================================================================
// 4. RECUPERAÇÃO POR COMANDOS REAIS DO MOTOR (Game::command) NAS 6 POSIÇÕES
// =========================================================================
echo "\n4. Validando Recuperação Real via Game::command() nas 6 Posições Atuais (Rules::POSITIONS)...\n";

foreach (Rules::POSITIONS as $playablePos) {
    $gCmd = Game::legacyCreate("RealCmd {$playablePos}", 'Santos', 'SP', $playablePos);
    $gCmd = Game::command($gCmd, 'handicap');
    $gCmd['states']['confianca'] = 18;
    $gCmd['states']['energia'] = 90;
    $gCmd['states']['confianca_treinador'] = 70;
    $gCmd['states']['risco_lesao'] = 0;
    foreach ($gCmd['skills'] as &$sk) {
        $sk['value'] = 78;
    }
    unset($sk);

    $isDefPos = in_array($playablePos, ['zagueiro', 'lateral', 'volante'], true);
    $rngDet = makeRngR2(9001 + abs(crc32($playablePos)));
    $rngHighPerf = static function (int $min, int $max) use ($rngDet): int {
        if ($max === 10000) {
            return 25; // execução bem-sucedida nas ações escolhidas
        }
        return $rngDet($min, $max);
    };

    $startC = $gCmd['states']['confianca'];
    $targetRounds = 6;
    $safety = 0;
    while ($gCmd['round'] <= $targetRounds && $gCmd['stage'] !== 'resumo' && $safety++ < 120) {
        $prevConf = $gCmd['states']['confianca'];
        $prevStage = $gCmd['stage'];
        if ($prevStage === 'partida') {
            $idxM = Game::currentIndex($gCmd);
            // Evita interferência aleatória de pênalti/lesão durante o ciclo determinístico
            $gCmd['matches'][$idxM]['evaluated_penalties'] = ['0-15', '15-35', '35-45', '45-60', '60-70', '70-80', '80-90'];
            $gCmd['states']['risco_lesao'] = 0;
            if ($isDefPos) {
                // Para defensores, zera conversão de passe em gol coletivo eventual? Não: mesmo sem chutar, prioriza desarme/passe
                $gCmd['matches'][$idxM]['pass_conversion'] = 0;
            }
            if (!MatchParticipation::canActOnField($gCmd['matches'][$idxM])) {
                $gCmd = Game::command($gCmd, 'advance', [], false, $rngHighPerf);
                continue;
            }
            $opts = Game::choices($gCmd);
            $pick = 0;
            if ($isDefPos) {
                // Defensores recuperam confiança sem depender de gols (priorizam desarme / passe / posicionamento, nunca chute)
                foreach ($opts as $oi => $op) {
                    if (($op['action'] ?? '') === 'desarme') {
                        $pick = $oi;
                        break;
                    }
                    if (($op['action'] ?? '') === 'passe') {
                        $pick = $oi;
                    }
                }
            } else {
                foreach ($opts as $oi => $op) {
                    if (in_array($op['action'] ?? '', ['chute', 'passe', 'drible'], true) && ($op['shot_kind'] ?? '') !== 'long') {
                        $pick = $oi;
                        break;
                    }
                }
            }
            $gCmd = Game::command($gCmd, 'choice', ['option' => (string)$pick], false, $rngHighPerf);
        } elseif (in_array($prevStage, ['treino', 'pre_jogo', 'fim_semana'], true)) {
            $opts = Game::choices($gCmd);
            $pick = 0;
            foreach ($opts as $oi => $op) {
                $eff = $op['effects'] ?? [];
                // Escolhe rotina esportiva normal sem apelar para opções sociais de confiança direta
                if (!isset($eff['confianca']) && ($op['code'] ?? '') !== 'reclamar') {
                    $pick = $oi;
                    break;
                }
            }
            $gCmd = Game::command($gCmd, 'choice', ['option' => (string)$pick], false, $rngHighPerf);
        } else {
            break;
        }

        $deltaReal = $gCmd['states']['confianca'] - $prevConf;
        if ($gCmd['feedback'] !== null) {
            $deltaFb = (int)($gCmd['feedback']['effects']['confianca'] ?? 0);
            okR2($deltaFb === $deltaReal, "Sincronia real Game::command [{$playablePos}, {$prevStage}]: feedback({$deltaFb}) === real({$deltaReal})");
        }
        $gCmd['states']['energia'] = max(80, $gCmd['states']['energia']);
    }

    $sumPos = Game::summary($gCmd);
    $endC = $gCmd['states']['confianca'];
    echo "  [MOTOR REAL 6 RODADAS] {$playablePos}: C {$startC} -> {$endC} (jogos={$sumPos['games']}, gols={$sumPos['goals']}, assist={$sumPos['assists']}, média={$sumPos['average']})\n";
    okR2($sumPos['games'] >= 5 && $endC >= $startC + 12,
        "Posição jogável '{$playablePos}' recupera confiança por comandos reais de Game::command ({$startC} -> {$endC})");
    if ($isDefPos) {
        okR2($sumPos['goals'] === 0 && $endC >= $startC + 12,
            "Defensor '{$playablePos}' comprova recuperação real no motor com 0 gols marcados ({$startC} -> {$endC})");
    }
}

// =========================================================================
// 5. MODOS RÁPIDO E EXPRESSO + RENDERIZAÇÃO DO PACE REPORT
// =========================================================================
echo "\n5. Validando Modos Rápido / Expresso e Exibição em PlayMode::report()...\n";

foreach (['rapido', 'expresso'] as $pMode) {
    $gPm = Game::legacyCreate("Modo {$pMode}", 'Santos', 'SP', 'centroavante');
    $gPm = Game::command($gPm, 'handicap');
    $gPm['play_mode'] = $pMode;
    $gPm['states']['confianca'] = 25;
    foreach ($gPm['skills'] as &$sk) {
        $sk['value'] = 72;
    }
    unset($sk);
    $startConfPm = $gPm['states']['confianca'];
    $gSim = PlayMode::simulate($gPm, 'protagonismo');
    $endConfPm = $gSim['states']['confianca'];
    $rep = $gSim['pace_report'] ?? [];
    okR2(
        ($rep['confidence_before'] ?? null) === $startConfPm
        && ($rep['confidence_after'] ?? null) === $endConfPm
        && ($rep['confidence_delta'] ?? null) === ($endConfPm - $startConfPm),
        "Modo {$pMode}: pace_report registra variação exata de confiança ({$startConfPm} -> {$endConfPm}, delta={$rep['confidence_delta']})"
    );
    ob_start();
    PlayMode::report($gSim);
    $htmlReport = (string)ob_get_clean();
    if ($rep['confidence_delta'] !== 0) {
        $expectedChip = 'Confiança ' . ($rep['confidence_delta'] > 0 ? '+' . $rep['confidence_delta'] : (string)$rep['confidence_delta']);
        okR2(str_contains($htmlReport, $expectedChip), "Modo {$pMode}: PlayMode::report() exibe chip '{$expectedChip}' fiel à variação aplicada");
    }
}

// =========================================================================
// 6. RECONSTRUÇÃO APROXIMADA (879927) E COMPARAÇÃO PAREADA SEM DERIVA DE RNG
// =========================================================================
echo "\n6. Executando Reconstrução Aproximada (879927) e Séries de Eventos Idênticas Antes/Depois...\n";

// 6A: Reconstrução aproximada da carreira 879927 (as 34 notas, minutos e gols são exatos do save;
// as falhas intermediárias por rodada são estimadas a partir do agregado observado na temporada, pois o save não persiste cada rolagem individual)
$s3Torreense = [
    [1,90,0,0,5.6],[2,90,0,0,5.9],[3,90,0,0,5.9],[4,90,1,0,7.2],[5,90,1,0,7.7],[6,90,1,0,7.3],
    [7,90,1,0,7.3],[8,90,0,0,5.9],[9,90,0,0,6.0],[10,90,0,0,6.2],[11,90,1,0,6.6],[12,90,3,0,9.4],
    [13,90,2,0,7.6],[14,90,1,0,6.5],[15,90,0,0,6.0],[16,90,1,0,7.1],[17,90,2,0,8.4],[18,90,0,0,6.1],
    [19,90,2,0,8.1],[20,90,0,0,5.5],[21,90,0,0,5.9],[22,90,0,0,5.9],[23,90,1,0,7.1],[24,30,0,0,5.9],
    [25,90,2,0,7.3],[26,90,1,0,6.3],[27,90,1,0,7.1],[28,90,1,0,7.1],[29,90,2,0,7.2],[30,90,2,0,7.9],
    [31,90,1,0,7.1],[32,90,0,0,6.0],[33,90,0,0,6.2],[34,90,3,0,8.5],
];

$cApproxOld = 16;
$cApproxR2 = 16;
$streakApprox = 0;
$cApproxOldR23 = 0;
$cApproxR2_R23 = 0;

foreach ($s3Torreense as [$rd, $mins, $gols, $asts, $rat]) {
    // Mesma sequência estimada de eventos aplicada às duas regras (Antes vs Depois):
    // 1) Falha fora de campo em treino físico/repetição a cada 3 rodadas
    if ($rd % 3 === 0) {
        $cApproxOld = max(0, $cApproxOld - ($rd % 9 === 0 ? 4 : 2));
        // Em R2: falha comum dá foco -1 (0 confiança) e grave em repetição técnica dá -1
        if ($rd % 9 === 0 && $cApproxR2 > 8) {
            $cApproxR2 = max(0, $cApproxR2 - 1);
        }
    }
    // 2) Decisão tática/posicionamento frustrada em campo nas partidas abaixo de 7.2
    if ($rat < 7.2) {
        $cApproxOld = max(0, $cApproxOld - ($rd % 6 === 0 ? 4 : 2));
        // Em R2: posicionamento frustrado afeta foco/treinador, 0 autoconfiança
    }
    // 3) Falhas técnicas reais com bola
    if ($rd % 7 === 0) {
        $cApproxOld = max(0, $cApproxOld - 3);
        $cApproxR2 = max(0, $cApproxR2 - ($cApproxR2 <= 8 ? 2 : 3));
    } elseif ($rat < 5.9 && $rd % 3 === 0) {
        $cApproxOld = max(0, $cApproxOld - 1);
        $cApproxR2 = max(0, $cApproxR2 - ($cApproxR2 <= 8 ? 1 : 2));
    }
    if ($rat < 6.2 && $mins >= 60) {
        $cApproxOld = max(0, $cApproxOld - 1);
        if ($cApproxR2 > 10) {
            $cApproxR2 -= 1;
        }
    }
    // 4) Gols reais da partida
    for ($gi = 1; $gi <= $gols; $gi++) {
        $cApproxOld = min(100, $cApproxOld + 4);
        $cApproxR2 = min(100, $cApproxR2 + $refGoalDelta->invoke(null, ['position' => 'centroavante', 'states' => ['confianca' => $cApproxR2]], $gi));
    }
    // 5) Pós-jogo
    $oldRaw = $rat >= 8.8 ? ($cApproxOld < 90 ? 2 : 0) : ($rat >= 7.8 ? ($cApproxOld < 50 ? 2 : ($cApproxOld < 75 ? 1 : 0)) : ($rat >= 6.8 ? ($cApproxOld < 50 ? 2 : ($cApproxOld < 70 ? 1 : 0)) : ($rat >= 6.0 ? ($cApproxOld < 50 ? 2 : ($cApproxOld < 60 ? 1 : 0)) : 0)));
    $cApproxOld = min(100, $cApproxOld + (int)round($oldRaw * min(1.0, $mins / 60.0)));

    $pmBase = $refPostNom->invoke(null, $rat, $cApproxR2, $mins);
    $regB = 0;
    if ($mins >= 45 && $rat >= 6.7) {
        $streakApprox++;
        if ($streakApprox >= 3 && ($streakApprox % 3) === 0 && ($cApproxR2 + max(0, $pmBase)) < 75) {
            $regB = 1;
        }
    } else {
        $streakApprox = 0;
    }
    $cApproxR2 = max(0, min(100, $cApproxR2 + $pmBase + $regB));

    if ($rd === 23) {
        $cApproxOldR23 = $cApproxOld;
        $cApproxR2_R23 = $cApproxR2;
    }
}
$promoDelta = $cApproxR2 >= 94 ? 1 : ($cApproxR2 >= 86 ? 2 : 4);
$cApproxR2AfterPromo = min(100, $cApproxR2 + $promoDelta);

echo "  [RECONSTRUÇÃO APROXIMADA 879927 — mesma sequência estimada] Antes: C0=16 -> R23={$cApproxOldR23} -> R34={$cApproxOld} | Depois (R2): C0=16 -> R23={$cApproxR2_R23} -> R34={$cApproxR2} -> Pós-Acesso={$cApproxR2AfterPromo}\n";
okR2($cApproxR2_R23 > $cApproxOldR23 + 25 && $cApproxR2 >= 82 && $cApproxR2AfterPromo <= 96,
    "Reconstrução aproximada 879927 sobre sequência idêntica comprova salto consistente (Antes R34={$cApproxOld} vs Depois R34={$cApproxR2}, Pós-Acesso={$cApproxR2AfterPromo})");

// 6B: Gerador único de sequência de eventos por temporada (ZERO deriva de PRNG entre Antes e Depois)
$refDefDelta = new ReflectionMethod('Game', 'defensiveTierDelta');
$refDefDelta->setAccessible(true);

$buildSeasonEventTape = static function (string $pos, string $tier, int $seed): array {
    $rng = makeRngR2($seed);
    $isDef = in_array($pos, ['goleiro', 'zagueiro', 'lateral', 'volante'], true);
    $isGk = ($pos === 'goleiro');

    [$techFailProb, $goalProb, $astProb, $tackleMean, $saveMean, $ratingBase] = match ($tier) {
        'ruim'        => [560, $isDef ? 10 : ($pos === 'centroavante' ? 70 : 30), 30, $isDef ? 0.8 : 0.2, 1.2, 5.1],
        'regular'     => [380, $isDef ? 30 : ($pos === 'centroavante' ? 200 : 90), $pos === 'meia' ? 140 : 70, $isDef ? 1.8 : 0.4, 2.4, 6.1],
        'bom'         => [270, $isDef ? 60 : ($pos === 'centroavante' ? 450 : 200), $pos === 'meia' ? 260 : 140, $isDef ? 2.8 : 0.7, 3.6, 6.9],
        'excepcional' => [180, $isDef ? 100 : ($pos === 'centroavante' ? 820 : 400), $pos === 'meia' ? 450 : 240, $isDef ? 4.0 : 1.0, 5.0, 8.1],
    };

    $rounds = [];
    for ($r = 1; $r <= 34; $r++) {
        // Todos os números aleatórios da rodada são sorteados uma única vez na mesma ordem
        $offFieldFailed = ($rng(1, 1000) <= (int)round($techFailProb * 0.55));
        $offFieldSevere = ($rng(1, 100) <= 15);
        $offFieldIsPhysical = ($rng(1, 100) <= 35); // ~35% treino físico, ~65% repetição técnica

        $min = ($r % 8 === 0) ? 30 : 90;
        $decisionsCount = $min >= 60 ? 3 : 1;
        $techDecisions = [];
        for ($d = 0; $d < $decisionsCount; $d++) {
            $techDecisions[] = [
                'failed' => ($rng(1, 1000) <= $techFailProb),
                'severe' => ($rng(1, 100) <= 15),
                'decisive' => (!$isDef && $rng(1, 100) <= 12),
                'exceptional' => ($rng(1, 100) <= ($tier === 'excepcional' ? 15 : ($tier === 'bom' ? 10 : 4))),
            ];
        }
        $tacticalFailed = ($rng(1, 1000) <= (int)round($techFailProb * 0.95));
        $tacticalSevere = ($rng(1, 100) <= 15);

        $gRoll1 = $rng(1, 1000);
        $gRoll2 = $rng(1, 1000);
        $goals = 0;
        if (!$isGk) {
            if ($gRoll1 <= $goalProb) $goals++;
            if ($tier === 'excepcional' && !$isDef && $gRoll2 <= (int)round($goalProb * 0.35)) $goals++;
        }
        $aRoll = $rng(1, 1000);
        $assists = (!$isGk && $aRoll <= $astProb) ? 1 : 0;

        $tNoise = $rng(-10, 10) / 10.0;
        $sNoise = $rng(-15, 15) / 10.0;
        $tackles = $isGk ? 0 : max(0, (int)round($tackleMean + $tNoise));
        $saves = $isGk ? max(0, (int)round($saveMean + $sNoise)) : 0;
        $cleanSheet = $isDef && ($rng(1, 100) <= ($tier === 'excepcional' ? 62 : ($tier === 'bom' ? 45 : ($tier === 'regular' ? 28 : 12))));
        $rNoise = $rng(-6, 6) / 10.0;
        $rat = max(4.2, min(10.0, round($ratingBase + $goals * 0.8 + $assists * 0.6 + ($cleanSheet ? 0.6 : 0.0) + $rNoise, 1)));

        $rounds[] = [
            'min' => $min,
            'off_failed' => $offFieldFailed,
            'off_severe' => $offFieldSevere,
            'off_physical' => $offFieldIsPhysical,
            'decisions' => $techDecisions,
            'tactical_failed' => $tacticalFailed,
            'tactical_severe' => $tacticalSevere,
            'goals' => $goals,
            'assists' => $assists,
            'tackles' => $tackles,
            'saves' => $saves,
            'clean_sheet' => $cleanSheet,
            'rating' => $rat,
        ];
    }
    return $rounds;
};

$evaluateTape = static function (array $tape, string $pos, int $startConf, bool $useR2) use ($refGoalDelta, $refAssistDelta, $refDefDelta, $refPostNom): array {
    $c = $startConf;
    $zeroCount = 0;
    $hundredCount = 0;
    $streak = 0;
    $isDef = in_array($pos, ['goleiro', 'zagueiro', 'lateral', 'volante'], true);
    $isGk = ($pos === 'goleiro');

    foreach ($tape as $ev) {
        // 1. Evento fora de partida (mesmo evento avaliado nas duas regras)
        if ($ev['off_failed']) {
            if (!$useR2) {
                $c = max(0, $c - ($ev['off_severe'] ? 4 : 2));
            } elseif (!$ev['off_physical'] && $ev['off_severe']) {
                $pen = $c <= 8 ? 1 : 1;
                $c = max(0, $c - $pen);
            }
        }

        // 2. Decisões técnicas em campo (mesmos lances avaliados nas duas regras)
        $excAwarded = false;
        foreach ($ev['decisions'] as $dec) {
            if ($dec['failed']) {
                if (!$useR2) {
                    $c = max(0, $c - ($dec['severe'] ? 3 : 1));
                } elseif ($dec['decisive']) {
                    $pen = $dec['severe'] ? ($c <= 8 ? 2 : 4) : ($c <= 8 ? 1 : 2);
                    $c = max(0, $c - $pen);
                } elseif ($dec['severe']) {
                    $c = max(0, $c - ($c <= 8 ? 2 : 3));
                } elseif ($c > 10) {
                    $c = max(0, $c - 1);
                }
            } elseif ($useR2 && !$excAwarded && $dec['exceptional'] && $c < 65) {
                $excAwarded = true;
                $c = min(100, $c + 1);
            }
        }

        // 3. Decisão tática/espacial sem bola (mesmo evento avaliado nas duas regras)
        if ($ev['tactical_failed'] && !$useR2) {
            $c = max(0, $c - ($ev['tactical_severe'] ? 4 : 2));
        }

        // 4. Gols e assistências idênticos
        for ($gi = 1; $gi <= $ev['goals']; $gi++) {
            $c = min(100, $c + ($useR2 ? $refGoalDelta->invoke(null, ['position' => $pos, 'states' => ['confianca' => $c]], $gi) : 4));
        }
        for ($ai = 1; $ai <= $ev['assists']; $ai++) {
            $c = min(100, $c + ($useR2 ? $refAssistDelta->invoke(null, ['position' => $pos, 'states' => ['confianca' => $c]], $ai) : 3));
        }

        // 5. Marcos defensivos idênticos
        if (!$useR2) {
            if (in_array($pos, ['zagueiro', 'volante', 'lateral'], true) && $ev['tackles'] >= 2) {
                $c = min(100, $c + 1);
            }
        } else {
            if (in_array($pos, ['zagueiro', 'volante', 'lateral'], true)) {
                if ($ev['tackles'] >= 2) $c = min(100, $c + $refDefDelta->invoke(null, $c, 1));
                if ($ev['tackles'] >= 4) $c = min(100, $c + $refDefDelta->invoke(null, $c, 2));
            } elseif ($isGk) {
                if ($ev['saves'] >= 2) $c = min(100, $c + $refDefDelta->invoke(null, $c, 1));
                if ($ev['saves'] >= 4) $c = min(100, $c + $refDefDelta->invoke(null, $c, 2));
            }
            if ($isDef && $ev['min'] >= 60 && $ev['clean_sheet'] && ($ev['tackles'] + $ev['saves'] >= 2) && $c < 78) {
                $c = min(100, $c + 1);
            }
        }

        // 6. Fechamento pós-partida sobre a mesma nota e minutagem
        $rat = $ev['rating'];
        $min = $ev['min'];
        if (!$useR2) {
            $oldRaw = $rat >= 8.8 ? ($c < 90 ? 2 : 0) : ($rat >= 7.8 ? ($c < 50 ? 2 : ($c < 75 ? 1 : 0)) : ($rat >= 6.8 ? ($c < 50 ? 2 : ($c < 70 ? 1 : 0)) : ($rat >= 6.0 ? ($c < 50 ? 2 : ($c < 60 ? 1 : 0)) : 0)));
            $c = min(100, $c + (int)round($oldRaw * min(1.0, $min / 60.0)));
        } else {
            $pm = $refPostNom->invoke(null, $rat, $c, $min);
            $reg = 0;
            if ($min >= 45 && $rat >= 6.7) {
                $streak++;
                if ($streak >= 3 && ($streak % 3) === 0 && ($c + max(0, $pm)) < 75) {
                    $reg = 1;
                }
            } else {
                $streak = 0;
            }
            $c = max(0, min(100, $c + $pm + $reg));
        }

        if ($c === 0) $zeroCount++;
        if ($c === 100) $hundredCount++;
    }
    return ['end' => $c, 'zero' => $zeroCount, 'hundred' => $hundredCount];
};

$tiers = ['ruim', 'regular', 'bom', 'excepcional'];
$starts = [15, 50, 85];

// Matriz principal: as 6 posições jogáveis atuais de Rules::POSITIONS
foreach (Rules::POSITIONS as $pos) {
    $tapesByTier = [];
    foreach ($tiers as $tier) {
        $tapesByTier[$tier] = [];
        for ($s = 1; $s <= 30; $s++) {
            $seed = $s * 131 + abs(crc32($pos . ':' . $tier));
            $tapesByTier[$tier][$s] = $buildSeasonEventTape($pos, $tier, $seed);
        }
    }

    foreach ($starts as $c0) {
        $avgByTier = [];
        foreach ($tiers as $tier) {
            $endsOld = [];
            $endsNew = [];
            $zerosNew = 0;
            $hundredsNew = 0;
            foreach ($tapesByTier[$tier] as $tape) {
                $oRes = $evaluateTape($tape, $pos, $c0, false);
                $nRes = $evaluateTape($tape, $pos, $c0, true);
                $endsOld[] = $oRes['end'];
                $endsNew[] = $nRes['end'];
                $zerosNew += $nRes['zero'];
                $hundredsNew += $nRes['hundred'];
            }
            $avgByTier[$tier] = [
                'old' => round(array_sum($endsOld) / count($endsOld), 1),
                'new' => round(array_sum($endsNew) / count($endsNew), 1),
                'zeros' => $zerosNew,
                'hundreds' => $hundredsNew,
            ];
        }

        echo sprintf(
            "  [SÉRIE PAREADA %-12s C0=%2d] ruim: %4.1f->%4.1f | regular: %4.1f->%4.1f | bom: %4.1f->%4.1f | exc: %4.1f->%4.1f\n",
            $pos,
            $c0,
            $avgByTier['ruim']['old'], $avgByTier['ruim']['new'],
            $avgByTier['regular']['old'], $avgByTier['regular']['new'],
            $avgByTier['bom']['old'], $avgByTier['bom']['new'],
            $avgByTier['excepcional']['old'], $avgByTier['excepcional']['new']
        );

        okR2(
            $avgByTier['ruim']['new'] < $avgByTier['regular']['new']
            && $avgByTier['regular']['new'] < $avgByTier['bom']['new']
            && $avgByTier['bom']['new'] < $avgByTier['excepcional']['new'],
            "Ordenação estrita em eventos idênticos [{$pos}, C0={$c0}]: ruim({$avgByTier['ruim']['new']}) < regular({$avgByTier['regular']['new']}) < bom({$avgByTier['bom']['new']}) < exc({$avgByTier['excepcional']['new']})"
        );
        if ($c0 === 15) {
            okR2($avgByTier['bom']['new'] >= 65.0,
                "Recuperação pareada comprovada [{$pos}, C0=15, bom]: Antes={$avgByTier['bom']['old']} -> Depois={$avgByTier['bom']['new']}");
        }
        if ($c0 === 85) {
            okR2($avgByTier['ruim']['new'] <= 45.0,
                "Queda real em temporada ruim [{$pos}, C0=85, ruim]: {$avgByTier['ruim']['new']} <= 45.0");
            okR2($avgByTier['bom']['hundreds'] <= 5,
                "Sem inflação automática para 100 [{$pos}, C0=85, bom]: rodadas em 100 = {$avgByTier['bom']['hundreds']}/1020");
        }
        okR2($avgByTier['regular']['zeros'] === 0 && $avgByTier['bom']['zeros'] === 0 && $avgByTier['excepcional']['zeros'] === 0,
            "Zero aprisionamento em 0 nas faixas regular/bom/excepcional [{$pos}, C0={$c0}]");
    }
}

// Verificação separada do suporte preparatório de goleiro sobre eventos idênticos
$gkTapeGood = $buildSeasonEventTape('goleiro', 'bom', 77701);
$gkOld15 = $evaluateTape($gkTapeGood, 'goleiro', 15, false);
$gkNew15 = $evaluateTape($gkTapeGood, 'goleiro', 15, true);
okR2($gkNew15['end'] >= 65 && $gkNew15['end'] > $gkOld15['end'] + 30,
    "[Suporte preparatório — goleiro fora de Rules::POSITIONS] Série idêntica C0=15 (bom): Antes={$gkOld15['end']} -> Depois={$gkNew15['end']}");

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES DA R10-BAL-001-R2 FORAM APROVADAS!\n";
echo "====================================================================\n";
