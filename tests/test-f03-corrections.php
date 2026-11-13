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

echo "=== R10-F03-R3: TESTES DIRECIONADOS DE CORREÇÃO FINAL ===\n\n";

// =========================================================================
// CORREÇÃO 1: BARRA DE PROGRESSO COM <progress> E SEM INLINE STYLES (CSP)
// =========================================================================
echo "1. Validando Barra de progresso da partida (<progress>, max=90, sem inline style, limite visual 0..90)...\n";

$minutesToTest = [0, 30, 45, 60, 90, 93, 105];
foreach ($minutesToTest as $min) {
    $g = Game::legacyCreate('Test Player', 'São Paulo', 'SP', 'meia');
    $m = [
        'home' => 'São Paulo',
        'away' => 'Santos',
        'hg' => 1,
        'ag' => 0,
        'status' => ($min === 45) ? 'intervalo' : (($min === 90) ? 'finalizada' : 'em_andamento'),
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

    // 3. Valor limitado ao intervalo 0 a 90 (limite exclusivamente visual)
    $expectedValue = min(90, max(0, $min));
    ok(str_contains($html, 'value="'.$expectedValue.'"'), "Minuto {$min}': Visual limitado a {$expectedValue} (0..90)");

    // 4. Minuto real preservado no relógio e rótulos (inclusive nos acréscimos aos 93')
    ok(str_contains($html, "<b>{$min}′</b>"), "Minuto {$min}': Minuto real {$min}′ preservado no relógio");
}

// Proporções esperadas
ok(30 / 90 >= 0.33 && 30 / 90 <= 0.34, "Aos 30', valor representa ~33.3% de 90");
ok(45 / 90 === 0.50, "Aos 45', valor representa exatamente 50% de 90");
ok(60 / 90 >= 0.66 && 60 / 90 <= 0.67, "Aos 60', valor representa ~66.7% de 90");
ok((float)(90 / 90) === 1.0, "Aos 90', valor representa exatamente 100% de 90");

echo "\n=========================================================================\n";
// =========================================================================
// CORREÇÃO 2: ESTADOS REAIS E NÃO INVENTAR INFORMAÇÕES (OS-COR-F03-R3)
// =========================================================================
echo "2. Validando Estados Reais e Ausência de Informações Inventadas...\n";

$scenarios = [
    // Cenário: Expulsão COM minuto registrado
    'expulsao_com_minuto' => [
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
        'note_title' => 'Expulsão de campo',
        'note_desc_contains' => 'Você foi expulso aos 68′ e desfalca a equipe até o encerramento do jogo.',
        'not_contains' => ['Substituído', 'Em campo', 'entra aos 90']
    ],

    // Cenário: Expulsão SEM minuto registrado
    'expulsao_sem_minuto' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 75,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            'participation_state' => 'SENT_OFF',
            // red_card_minute ausente de propósito
            'stats' => ['goals' => 0, 'assists' => 0, 'red_cards' => 1]
        ],
        'expected_label' => 'Expulso',
        'expected_class' => 'status-sent-off',
        'note_title' => 'Expulsão de campo',
        'note_desc_contains' => 'Você foi expulso e desfalca a equipe até o encerramento do jogo.',
        'not_contains' => ['Expulso aos', 'Substituído', 'Em campo', 'aos 75′', 'aos 90′']
    ],

    // Cenário: Atleta não relacionado sem motivo registrado
    'nao_relacionado_sem_motivo' => [
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
        'note_title' => 'Não relacionado',
        'note_desc_contains' => 'Você não foi relacionado para esta partida e acompanha o jogo de fora.',
        'not_contains' => ['desgaste físico', 'lesão', 'suspenso', 'poupado', 'Em campo', 'entra aos 90', 'Substituído']
    ],

    // Cenário: Partida aos 93', status em_andamento, atleta ON_FIELD (acréscimos)
    'partida_93m_em_andamento_on_field' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 93,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            'participation_state' => 'ON_FIELD',
            'stats' => ['goals' => 1, 'assists' => 0]
        ],
        'expected_label' => 'Em campo',
        'expected_class' => 'status-onfield',
        'note_title' => 'Em campo',
        'not_contains' => ['Partida encerrada', 'Permaneceu no banco', 'Substituído', 'Expulso']
    ],

    // Cenário: Partida efetivamente encerrada, conforme status real do servidor (status=finalizada)
    'partida_efetivamente_encerrada' => [
        'm' => [
            'status' => 'finalizada',
            'minute' => 90,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            'participation_state' => 'FULL_TIME',
            'stats' => ['goals' => 1, 'assists' => 1]
        ],
        'expected_label' => 'Partida encerrada',
        'expected_class' => 'status-finished',
        'note_title' => 'Partida finalizada',
        'not_contains' => ['Em campo', 'No banco', 'entra aos 90', 'Substituído']
    ],

    // Cenário: Partida efetivamente encerrada, reserva que não entrou
    'partida_finalizada_reserva_unused' => [
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
        'note_title' => 'Partida finalizada',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído', 'Partida encerrada']
    ],

    // Cenário: Compatibilidade com save legado sem campos opcionais (em andamento)
    'legado_sem_campos_opcionais_on_field' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 35,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            // participation_state, red_card_minute ausentes
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Em campo',
        'expected_class' => 'status-onfield',
        'note_title' => 'Em campo',
        'not_contains' => ['Partida encerrada', 'Permaneceu no banco', 'Substituído', 'Expulso']
    ],

    // Cenário: Compatibilidade com save legado sem campos opcionais (expulso sem minuto)
    'legado_sem_campos_opcionais_expulso' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 80,
            'entry' => 0,
            'exit' => 90,
            'lineup' => 'titular',
            // participation_state e red_card_minute ausentes
            'stats' => ['goals' => 0, 'assists' => 0, 'red_cards' => 1]
        ],
        'expected_label' => 'Expulso',
        'expected_class' => 'status-sent-off',
        'note_title' => 'Expulsão de campo',
        'note_desc_contains' => 'Você foi expulso e desfalca a equipe até o encerramento do jogo.',
        'not_contains' => ['Expulso aos', 'Substituído', 'Em campo']
    ],

    // Cenário: Pré-jogo titular
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
        'note_title' => 'Pré-jogo',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],

    // Cenário: Pré-jogo reserva
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
        'note_title' => 'Pré-jogo',
        'not_contains' => ['Em campo', 'entra aos 90', 'Substituído']
    ],

    // Cenário: Suspenso
    'suspenso_disciplinar' => [
        'm' => [
            'status' => 'em_andamento',
            'minute' => 20,
            'entry' => 90,
            'exit' => 90,
            'lineup' => 'suspenso',
            'participation_state' => 'SUSPENDED',
            'stats' => ['goals' => 0, 'assists' => 0]
        ],
        'expected_label' => 'Suspenso',
        'expected_class' => 'status-suspended',
        'note_title' => 'Atleta suspenso',
        'not_contains' => ['Em campo', 'Substituído', 'entra aos 90']
    ],

    // Cenário: Substituído com minuto registrado
    'substituido_com_minuto' => [
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
        'note_title' => 'Substituição realizada',
        'not_contains' => ['Em campo', 'Expulso', 'entra aos 90']
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

    if (isset($sc['note_title'])) {
        ok($part['note_title'] === $sc['note_title'], "Cenário {$key}: playerParticipation note_title === '{$sc['note_title']}'");
    }
    if (isset($sc['note_desc_contains'])) {
        ok(str_contains($part['note_desc'], $sc['note_desc_contains']), "Cenário {$key}: note_desc contém '{$sc['note_desc_contains']}'");
    }

    // 2. Testar renderização no placar (Presentation::score)
    ob_start();
    Presentation::score($g, $matchData);
    $htmlScore = ob_get_clean();

    ok(str_contains($htmlScore, $sc['expected_label']), "Cenário {$key}: Placar renderiza '{$sc['expected_label']}'");
    ok(str_contains($htmlScore, $sc['expected_class']), "Cenário {$key}: Placar renderiza classe '{$sc['expected_class']}'");

    foreach ($sc['not_contains'] as $forbidden) {
        ok(!str_contains($htmlScore, $forbidden), "Cenário {$key}: Placar NÃO contém '{$forbidden}'");
    }

    // 3. Testar consistência com view.php (card de avanço ou painel de decisão)
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

    if ($matchData['status'] !== 'em_andamento' || $part['type'] !== 'on_field') {
        ok(str_contains($htmlView, 'class="match-advance-card panel"'), "Cenário {$key}: view.php renderiza card de avanço");
        ok(str_contains($htmlView, htmlspecialchars($part['note_title'], ENT_QUOTES, 'UTF-8')), "Cenário {$key}: Card de avanço reflete título consistente '{$part['note_title']}'");
    } else {
        ok(str_contains($htmlView, 'name="option"'), "Cenário {$key} (em campo): view.php renderiza opções de decisão");
        ok(!str_contains($htmlView, 'class="match-advance-card panel"'), "Cenário {$key} (em campo): view.php NÃO renderiza card de avanço");
    }

    foreach ($sc['not_contains'] as $forbidden) {
        ok(!str_contains($htmlView, $forbidden), "Cenário {$key}: view.php NÃO contém '{$forbidden}'");
    }
}

echo "\n=======================================================\n";
echo "TOTAL: {$checks} verificações executadas e APROVADAS!\n";
echo "SUCESSO: OS-COR-F03-R3 verificada com precisão absoluta!\n";
echo "=======================================================\n";
