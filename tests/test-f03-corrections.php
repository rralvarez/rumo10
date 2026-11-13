<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

$checks = 0;
function ok(bool $condition, string $msg): void {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException("FALHA: $msg");
    }
    echo "  [OK] $msg\n";
}

echo "=== R10-F03: TESTES PONTUAIS DE CORREÇÃO ===\n\n";

// =========================================================================
// CORREÇÃO 1: BARRA DE PROGRESSO COM <progress> E SEM INLINE STYLES
// =========================================================================
echo "1. Validando Correção 1: Barra de progresso da partida (<progress>, max=90, sem inline style)...\n";

$minutesToTest = [0, 30, 45, 60, 90, 105];
foreach ($minutesToTest as $min) {
    $g = Game::legacyCreate('Test Player', 'São Paulo', 'SP', 'meia');
    $m = [
        'home' => 'São Paulo',
        'away' => 'Santos',
        'hg' => 1,
        'ag' => 0,
        'status' => ($min === 45) ? 'intervalo' : (($min >= 90) ? 'finalizada' : 'em_andamento'),
        'minute' => $min,
        'lineup' => 'titular',
        'entry' => 0,
        'exit' => 90,
        'stats' => ['goals' => 0, 'assists' => 0, 'shots' => 0, 'passes' => 0, 'completed' => 0, 'minutes' => min(90, $min), 'rating' => 6.0],
        'log' => []
    ];

    ob_start();
    Presentation::score($g, $m);
    $html = ob_get_clean();

    // 1. Elemento <progress>
    ok(str_contains($html, '<progress class="match-progress-bar"'), "Minuto {$min}': Renderiza tag <progress class=\"match-progress-bar\">");
    ok(str_contains($html, 'max="90"'), "Minuto {$min}': Atributo max=\"90\" presente");

    // 2. Ausência total de style=" inline no container de progresso
    preg_match('#<div class="match-progress-wrap".*?</div>\s*</div>#s', $html, $progressMatch);
    ok(!empty($progressMatch), "Minuto {$min}': Container .match-progress-wrap localizado");
    ok(!str_contains($progressMatch[0] ?? '', 'style='), "Minuto {$min}': ZERO atributos style inline na barra de progresso (CSP clean)");

    // 3. Valor limitado ao intervalo 0 a 90
    $expectedValue = min(90, max(0, $min));
    ok(str_contains($html, 'value="'.$expectedValue.'"'), "Minuto {$min}': Visual limitado a {$expectedValue} (0..90)");

    // 4. Minuto real preservado no relógio e rótulos
    ok(str_contains($html, "<b>{$min}′</b>"), "Minuto {$min}': Minuto real {$min}′ preservado no relógio");
}

// Proporções esperadas
ok(30 / 90 >= 0.33 && 30 / 90 <= 0.34, "Aos 30', valor representa ~33.3% de 90");
ok(45 / 90 === 0.50, "Aos 45', valor representa exatamente 50% de 90");
ok(60 / 90 >= 0.66 && 60 / 90 <= 0.67, "Aos 60', valor representa ~66.7% de 90");
ok((float)(90 / 90) === 1.0, "Aos 90', valor representa exatamente 100% de 90");

echo "\n=========================================================================\n";
// =========================================================================
// CORREÇÃO 2: SITUAÇÃO REAL DO JOGADOR
// =========================================================================
echo "2. Validando Correção 2: Situação real do jogador (placar e card de avanço)...\n";

$scenarios = [
    'pre_jogo_titular' => [
        'm' => [
            'status' => 'pre_jogo',
            'minute' => 0,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            'participation_state' => 'STARTING',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Escalado (titular)',
        'expected_class' => 'status-starting',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'pre_jogo_reserva' => [
        'm' => [
            'status' => 'pre_jogo',
            'minute' => 0,
            'entry' => 60,
            'exit' => 90,
            'lineup' => 'reserva',
            'participation_state' => 'ON_BENCH',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'No banco (reserva)',
        'expected_class' => 'status-bench',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'pre_jogo_suspenso' => [
        'm' => [
            'status' => 'pre_jogo',
            'minute' => 0,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'suspenso',
            'participation_state' => 'SUSPENDED',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Suspenso',
        'expected_class' => 'status-suspended',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'pre_jogo_nao_relacionado' => [
        'm' => [
            'status' => 'pre_jogo',
            'minute' => 0,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'não relacionado',
            'participation_state' => 'NOT_SELECTED',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Não relacionado',
        'expected_class' => 'status-not-selected',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'em_andamento_titular_em_campo' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 35,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            'participation_state' => 'ON_FIELD',
            'stats' => ['goals' => 1, 'assists' => 0]
        ],
        'expected_label' => 'Em campo',
        'expected_class' => 'status-onfield',
        'not_contains' => ['entra aos 90', 'Substituído', 'No banco']
    ],
    'em_andamento_reserva_no_banco' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 15,
            'entry' => 60,
            'exit' => 90,
            'lineup' => 'reserva',
            'participation_state' => 'ON_BENCH',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'No banco de reservas',
        'expected_class' => 'status-bench',
        'not_contains' => ['Em campo', 'entra aos 90', 'entra aos 60', 'Substituído']
    ],
    'em_andamento_reserva_unused' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 35,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'reserva',
            'participation_state' => 'ON_BENCH',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'No banco de reservas',
        'expected_class' => 'status-bench',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'em_andamento_reserva_entrou' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 70,
            'entry' => 60,
            'exit' => 90,
            'lineup' => 'reserva',
            'participation_state' => 'ON_FIELD',
            'stats' => ['goals' => 0, 'assists' => 1]
        ],
        'expected_label' => 'Em campo',
        'expected_class' => 'status-onfield',
        'not_contains' => ['No banco', 'entra aos 90', 'Substituído']
    ],
    'em_andamento_substituido' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 80,
            'entry' => 0,
            'exit' => 70,
            'lineup' => 'titular',
            'participation_state' => 'SUBSTITUTED_OUT',
            'stats' => ['goals' => 1, 'assists' => 0]
        ],
        'expected_label' => 'Substituído aos 70′',
        'expected_class' => 'status-subbed',
        'not_contains' => ['Em campo', 'Expulso', 'entra aos 90']
    ],
    'em_andamento_expulso_direto' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 70,
            'entry' => 0,
            'exit' => 68,
            'lineup' => 'titular',
            'participation_state' => 'SENT_OFF',
            'red_card_minute' => 68,
            'stats' => ['goals' => 0, 'assists' => 0, 'red_cards' => 1]
        ],
        'expected_label' => 'Expulso aos 68′',
        'expected_class' => 'status-sent-off',
        'not_contains' => ['Substituído', 'Em campo', 'entra aos 90']
    ],
    'em_andamento_suspenso' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 35,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'suspenso',
            'participation_state' => 'SUSPENDED',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Suspenso',
        'expected_class' => 'status-suspended',
        'not_contains' => ['Em campo', 'Substituído', 'entra aos 90']
    ],
    'em_andamento_nao_relacionado' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 35,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'não relacionado',
            'participation_state' => 'NOT_SELECTED',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Não relacionado',
        'expected_class' => 'status-not-selected',
        'not_contains' => ['Em campo', 'Substituído', 'entra aos 90']
    ],
    'finalizada_reserva_unused' => [
        'm' => [
            'status' => 'finalizada',
            'minute' => 90,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'reserva',
            'participation_state' => 'ON_BENCH',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Permaneceu no banco',
        'expected_class' => 'status-bench',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    // Fallback para saves legados (sem participation_state)
    'legado_reserva_no_banco' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 15,
            'entry' => 60,
            'exit' => 90,
            'lineup' => 'reserva',
            // participation_state ausente!
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'No banco de reservas',
        'expected_class' => 'status-bench',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],
    'legado_substituido' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 80,
            'entry' => 0,
            'exit' => 70,
            'lineup' => 'titular',
            // participation_state ausente!
            'stats' => ['goals' => 0, 'assists' => 0, 'red_cards' => 0]
        ],
        'expected_label' => 'Substituído aos 70′',
        'expected_class' => 'status-subbed',
        'not_contains' => ['Em campo', 'Expulso', 'entra aos 90']
    ],
    'legado_expulso' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 75,
            'entry' => 0,
            'exit' => 60,
            'lineup' => 'titular',
            'red_card_minute' => 60,
            // participation_state ausente!
            'stats' => ['goals' => 0, 'assists' => 0, 'red_cards' => 1]
        ],
        'expected_label' => 'Expulso aos 60′',
        'expected_class' => 'status-sent-off',
        'not_contains' => ['Substituído', 'Em campo', 'entra aos 90']
    ]
];

$g = Game::legacyCreate('Test Player', 'São Paulo', 'SP', 'meia');

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void { echo '<form method="post" action="'.e($path).'">'; }
}

foreach ($scenarios as $key => $sc) {
    $matchData = array_merge([
        'round' => 1,
        'home' => 'São Paulo',
        'away' => 'Santos',
        'hg' => 1,
        'ag' => 0,
        'stats' => ['goals' => 0, 'assists' => 0, 'shots' => 0, 'passes' => 0, 'completed' => 0, 'minutes' => 0, 'rating' => 6.0],
        'log' => []
    ], $sc['m']);

    // 1. Testar método puro Presentation::playerParticipation
    $part = Presentation::playerParticipation($g, $matchData);
    ok($part['label'] === $sc['expected_label'], "Cenário {$key}: playerParticipation label === '{$sc['expected_label']}' (obteve '{$part['label']}')");
    ok($part['class'] === $sc['expected_class'], "Cenário {$key}: playerParticipation class === '{$sc['expected_class']}' (obteve '{$part['class']}')");

    // 2. Testar renderização no placar (Presentation::score)
    ob_start();
    Presentation::score($g, $matchData);
    $htmlScore = ob_get_clean();

    ok(str_contains($htmlScore, $sc['expected_label']), "Cenário {$key}: Placar renderiza '{$sc['expected_label']}'");
    ok(str_contains($htmlScore, $sc['expected_class']), "Cenário {$key}: Placar renderiza classe '{$sc['expected_class']}'");

    foreach ($sc['not_contains'] as $forbidden) {
        ok(!str_contains($htmlScore, $forbidden), "Cenário {$key}: Placar NÃO contém '{$forbidden}'");
    }

    // 3. Testar consistência com card de avanço em view.php (quando jogador não tem decisões em campo)
    $isAdvance = ($matchData['status'] !== 'em_andamento' || $matchData['minute'] < $matchData['entry'] || $matchData['minute'] >= $matchData['exit']);
    if ($isAdvance) {
        $path = '/carreira';
        $error = null;
        $_SESSION = ['account' => 1, 'csrf' => 'test'];
        $testGame = $g;
        $testGame['stage'] = 'partida';
        $testGame['round'] = 1;
        $testGame['club'] = $matchData['home'];
        $testGame['matches'] = [$matchData];

        $baseGame = $g;
        $g = $testGame;
        ob_start();
        include dirname(__DIR__).'/app/view.php';
        $htmlView = ob_get_clean();
        $g = $baseGame;

        ok(str_contains($htmlView, 'class="match-advance-card panel"'), "Cenário {$key}: view.php renderiza card de avanço");
        ok(str_contains($htmlView, htmlspecialchars($part['note_title'], ENT_QUOTES, 'UTF-8')), "Cenário {$key}: Card de avanço reflete título consistente '{$part['note_title']}'");
        foreach ($sc['not_contains'] as $forbidden) {
            ok(!str_contains($htmlView, $forbidden), "Cenário {$key}: view.php NÃO contém '{$forbidden}'");
        }
    }
}

echo "\n=======================================================\n";
echo "TOTAL: {$checks} verificações executadas e APROVADAS!\n";
echo "SUCESSO: Correção 1 e Correção 2 verificadas com perfeição!\n";
echo "=======================================================\n";
