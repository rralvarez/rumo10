<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

echo "=== TESTE E AUDITORIA: IMPACTO DO OVERALL E ATRIBUTOS (REQUISITO 6) ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [OK] $msg\n";
    } else {
        $failCount++;
        echo "  [FALHA] $msg\n";
    }
}

// Helper para criar mock de universo do jogador
function makeMockPlayer(int $overallTarget, string $position = 'centroavante', int $minutes = 90): array {
    $g = Game::create('Craque Teste', 'São Paulo', 'SP', $position, 'Corinthians-SP');
    foreach (Rules::SKILLS as $s) {
        $g['skills'][$s]['value'] = $overallTarget;
    }
    // Cria match mock
    $g['stage'] = 'partida';
    $g['matches'] = [[
        'round' => 1,
        'home' => $g['club'],
        'away' => 'Flamengo-RJ',
        'home_overall' => 65.0,
        'away_overall' => 65.0,
        'entry' => 90 - $minutes,
        'exit' => 90,
        'status' => 'em_andamento',
        'minute' => 45,
        'hg' => 0,
        'ag' => 0,
        'stats' => ['minutes' => $minutes, 'shots' => 0, 'goals' => 0, 'assists' => 0, 'passes' => 0, 'completed' => 0, 'tackles' => 0, 'cards' => 0, 'rating' => 6.0]
    ]];
    return $g;
}

echo "1. Avaliação da Influência do Overall nas Ações Individuais (ActionResolution):\n";
$overalls = [50, 65, 80, 95];
$chancesChute = [];
$chancesPasse = [];
$chancesClara = [];

foreach ($overalls as $ovr) {
    $g = makeMockPlayer($ovr, 'centroavante');
    $traceChute = ActionResolution::trace($g, ['action' => 'chute', 'shot_kind' => 'normal', 'difficulty' => 65]);
    $tracePasse = ActionResolution::trace($g, ['action' => 'passe', 'difficulty' => 43]);
    $traceClara = ActionResolution::trace($g, ['action' => 'chute', 'clear_chance' => true, 'difficulty' => 45]);

    $chancesChute[$ovr] = $traceChute['chance'];
    $chancesPasse[$ovr] = $tracePasse['chance'];
    $chancesClara[$ovr] = $traceClara['chance'];

    echo "  - Overall $ovr: Chute Normal = {$traceChute['chance']}% | Passe = {$tracePasse['chance']}% | Chance Clara = {$traceClara['chance']}%\n";
}

assertCondition($chancesChute[65] > $chancesChute[50], "Overall 65 tem maior conversão de chute que 50 ({$chancesChute[65]}% vs {$chancesChute[50]}%)");
assertCondition($chancesChute[80] > $chancesChute[65], "Overall 80 tem maior conversão de chute que 65 ({$chancesChute[80]}% vs {$chancesChute[65]}%)");
assertCondition($chancesChute[95] > $chancesChute[80], "Overall 95 tem maior conversão de chute que 80 ({$chancesChute[95]}% vs {$chancesChute[80]}%)");

assertCondition($chancesClara[95] > $chancesClara[65], "Chance clara em Overall 95 é muito superior à de 65 ({$chancesClara[95]}% vs {$chancesClara[65]}%)");
assertCondition($chancesPasse[95] > $chancesPasse[50], "Sucesso no passe cresce com atributos técnicos ({$chancesPasse[95]}% vs {$chancesPasse[50]}%)");

echo "\n2. Avaliação da Influência da Força do Adversário (Opponent Overall):\n";
$adversarios = [
    'Fraco (50)' => 50.0,
    'Médio (68)' => 68.0,
    'Forte (85)' => 85.0
];

$gStar = makeMockPlayer(80, 'centroavante');
$chancesVsAdv = [];

foreach ($adversarios as $rotulo => $oppRating) {
    $gStar['matches'][0]['away_overall'] = $oppRating;
    $trace = ActionResolution::trace($gStar, ['action' => 'chute', 'shot_kind' => 'normal', 'difficulty' => 65]);
    $chancesVsAdv[$rotulo] = $trace['chance'];
    echo "  - Jogador OVR 80 contra Adversário $rotulo: Chance de Chute = {$trace['chance']}%\n";
}

assertCondition($chancesVsAdv['Fraco (50)'] > $chancesVsAdv['Médio (68)'], "Adversário fraco permite maior probabilidade que adversário médio");
assertCondition($chancesVsAdv['Médio (68)'] > $chancesVsAdv['Forte (85)'], "Adversário forte reduz probabilidade de sucesso");

echo "\n3. Avaliação do Impacto do Protagonista na Força Coletiva (MatchBalance):\n";
$contribuicoes = [];
foreach ($overalls as $ovr) {
    $g = makeMockPlayer($ovr, 'centroavante', 90);
    $m = $g['matches'][0];
    MatchBalance::prepare($m, $g);
    $contribuicoes[$ovr] = $m['balance_expected_contribution'];
    $xg = $m['expected_goals']['hg'];
    echo "  - Overall $ovr (Titular 90 min): Fator Contribuição = " . number_format($m['balance_expected_contribution'], 4) . " (xG = " . number_format($xg, 2) . ")\n";
}

assertCondition($contribuicoes[95] > $contribuicoes[80], "Overall 95 eleva xG da equipe mais que 80");
assertCondition($contribuicoes[80] > $contribuicoes[65], "Overall 80 contribui mais que 65");
assertCondition($contribuicoes[50] < 1.0, "Overall 50 (inferior à média da equipe 65) penaliza levemente a equipe");
assertCondition($contribuicoes[95] <= 1.13, "Contribuição máxima respeita o teto configurado de 12% (não quebra o balanceamento coletivo)");

echo "\n4. Comparação Titular vs Reserva (Impacto dos Minutos Jogados):\n";
$gTitular = makeMockPlayer(90, 'centroavante', 90);
$mTitular = $gTitular['matches'][0];
MatchBalance::prepare($mTitular, $gTitular);

$gReserva = makeMockPlayer(90, 'centroavante', 20); // Entra aos 70 min
$mReserva = $gReserva['matches'][0];
MatchBalance::prepare($mReserva, $gReserva);

$boostTitular = $mTitular['balance_expected_contribution'] - 1.0;
$boostReserva = $mReserva['balance_expected_contribution'] - 1.0;

echo "  - Craque OVR 90 Titular (90 min): Bônus = +" . number_format($boostTitular * 100, 2) . "%\n";
echo "  - Craque OVR 90 Reserva (20 min): Bônus = +" . number_format($boostReserva * 100, 2) . "%\n";

assertCondition($boostTitular > $boostReserva, "Titular tem maior impacto na equipe que reserva");
assertCondition($boostReserva > 0, "Reserva de alto nível ainda traz impacto positivo proporcional aos minutos jogados");
assertCondition(abs($boostReserva - ($boostTitular * 20 / 90)) < 0.001, "Impacto do reserva é estritamente proporcional aos minutos jogados (20/90)");

echo "\n5. Quotas de Finalização e Ataque por Posição:\n";
$posicoes = ['centroavante' => 0.60, 'ponta' => 0.55, 'meia' => 0.48, 'lateral' => 0.35, 'zagueiro' => 0.30];
foreach ($posicoes as $pos => $quotaEsperada) {
    $gPos = makeMockPlayer(75, $pos, 90);
    $mPos = $gPos['matches'][0];
    MatchBalance::prepare($mPos, $gPos);
    echo "  - Posição $pos: Quota de ataque = " . ($mPos['player_attack_share'] * 100) . "%\n";
    assertCondition(abs($mPos['player_attack_share'] - $quotaEsperada) < 0.01, "Posição $pos tem quota correta de " . ($quotaEsperada * 100) . "%");
}

echo "\n6. Verificação de Ausência de Dupla Contabilização:\n";
// Verifica se ActionResolution não penaliza duplamente quando o orçamento coletivo já é descontado
$gDouble = makeMockPlayer(80, 'centroavante');
$traceNormal = ActionResolution::trace($gDouble, ['action' => 'chute', 'shot_kind' => 'normal', 'difficulty' => 65]);
$traceClear = ActionResolution::trace($gDouble, ['action' => 'chute', 'clear_chance' => true, 'difficulty' => 45]);

assertCondition(empty($traceClear['extra_sequence_bonus']), "Chance clara não aplica bônus de sequência duplicado");
assertCondition(isset($traceClear['components']), "Componentes de probabilidade são transparentes e auditáveis");

echo "\n=======================================================\n";
echo "TOTAL: Passou: $passCount | Falhou: $failCount\n";
if ($failCount === 0) {
    echo "SUCESSO: Todas as verificações de impacto do overall e motor esportivo foram APROVADAS!\n";
    exit(0);
} else {
    echo "FALHA: Divergências encontradas.\n";
    exit(1);
}
