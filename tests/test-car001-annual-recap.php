<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(function(Throwable $e) {
    echo "\n[EXCEPTION]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
});

$checks = 0;
function check(bool $v, string $message): void {
    global $checks;
    $checks++;
    if (!$v) {
        throw new RuntimeException("FALHA DE TESTE: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== INICIANDO SUÍTE DE TESTES R10-CAR-001-R2: RETROSPECTIVA ANUAL COMPLETA ===\n\n";

// Helper para capturar saída HTML de Presentation::annualRecap($g)
if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="' . e($path) . '"><input type="hidden" name="csrf" value="fixture-csrf">';
        if ($g) {
            echo '<input type="hidden" name="version" value="' . e($g['version']) . '"><input type="hidden" name="key" value="fixture-idempotency-key">';
        }
    }
}

function renderAnnualRecap(array $g): string {
    $g['matches'] = $g['matches'] ?? [];
    ob_start();
    Presentation::annualRecap($g);
    return ob_get_clean() ?: '';
}

// -------------------------------------------------------------
// 1. Cenário: Identificação da Temporada e Calendário Concluído
// -------------------------------------------------------------
echo "1. Validando Identificação da Temporada e Mensagem de Conclusão...\n";
$gBase = [
    'id' => 201,
    'name' => 'Craque da Silva',
    'club' => 'Santos-SP',
    'position' => 'meia',
    'season' => 2,
    'version' => 15,
    'stage' => 'resumo',
    'official' => true,
    'competition_id' => 'br-a',
    'season_competitions' => ['br-a'],
    'competitions' => [
        'br-a' => [
            'id' => 'br-a',
            'done' => true,
            'phase' => 'liga',
            'matches' => []
        ]
    ],
    'skills' => [
        'finalizacao' => ['value' => 70, 'xp' => 0],
        'passe' => ['value' => 74, 'xp' => 0],
        'drible' => ['value' => 72, 'xp' => 0]
    ],
    'season_skill_baseline' => 70.0,
    'states' => ['confianca' => 60, 'energia' => 85],
    'matches' => [],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => 'Santos-SP',
            'promoted' => false,
            'relegated' => false,
            'position' => 1,
            'group' => null
        ],
        'br-a' => [
            'name' => 'Brasileirão Série A',
            'champion' => 'Palmeiras-SP',
            'promoted' => false,
            'relegated' => false,
            'position' => 3,
            'group' => null
        ]
    ]
];

$html1 = renderAnnualRecap($gBase);
check(str_contains($html1, 'TEMPORADA CONCLUÍDA · BALANÇO ANUAL'), 'Identificação exibe tag de encerramento anual');
check(str_contains($html1, 'Temporada 2 · Craque da Silva'), 'Identificação exibe número da temporada e nome do jogador');
check(str_contains($html1, 'Santos-SP</b> · Calendário competitivo encerrado'), 'Identificação exibe clube atual e encerramento do calendário');

// -------------------------------------------------------------
// 2. Cenário: Desempenho Individual da Temporada
// -------------------------------------------------------------
echo "\n2. Validando Desempenho Individual da Temporada...\n";
$gWithMatches = $gBase;
$gWithMatches['matches'] = [
    [
        'round' => 1, 'home' => 'Santos-SP', 'away' => 'Corinthians-SP', 'hg' => 2, 'ag' => 1,
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
        'stats' => ['goals' => 2, 'assists' => 1, 'minutes' => 90, 'rating' => 8.5, 'shots' => 3, 'tackles' => 1]
    ],
    [
        'round' => 2, 'home' => 'Palmeiras-SP', 'away' => 'Santos-SP', 'hg' => 0, 'ag' => 1,
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
        'stats' => ['goals' => 1, 'assists' => 0, 'minutes' => 90, 'rating' => 7.5, 'shots' => 2, 'tackles' => 2]
    ]
];

$html2 = renderAnnualRecap($gWithMatches);
check(str_contains($html2, 'Desempenho individual na temporada'), 'Seção de desempenho individual presente');
check(str_contains($html2, 'data-stat="recap-games">2</strong>'), 'Desempenho exibe 2 partidas disputadas na temporada');
check(str_contains($html2, 'data-stat="recap-goals">3</strong>'), 'Desempenho exibe 3 gols marcados na temporada');
check(str_contains($html2, 'data-stat="recap-assists">1</strong>'), 'Desempenho exibe 1 assistência na temporada');
check(str_contains($html2, 'data-stat="recap-minutes">180′</strong>'), 'Desempenho exibe 180 minutos jogados na temporada');
check(str_contains($html2, 'data-stat="recap-rating">8,00</strong>'), 'Desempenho exibe nota média correta de 8,00');

// -------------------------------------------------------------
// 3. Cenário: Múltiplas Competições Disputadas
// -------------------------------------------------------------
echo "\n3. Validando Resultados com Múltiplas Competições...\n";
check(str_contains($html1, 'Paulistão A1'), 'Retrospectiva lista Paulistão A1');
check(str_contains($html1, 'Título conquistado!'), 'Paulistão A1 reconhece título do Santos-SP');
check(str_contains($html1, 'Brasileirão Série A'), 'Retrospectiva lista Brasileirão Série A');
check(str_contains($html1, 'Campeão: <b>Palmeiras-SP</b>'), 'Brasileirão Série A exibe campeão correto');
check(str_contains($html1, 'Terminou em <b>3º lugar</b> na primeira fase.'), 'Brasileirão Série A exibe colocação fiel sem classificar final fictícia');

// -------------------------------------------------------------
// 4. Cenário: Competição Única (Modo Legado / Liga Regional)
// -------------------------------------------------------------
echo "\n4. Validando Competição Única e Modo Legado...\n";
$gLegacy = [
    'id' => 202,
    'name' => 'Craque Regional',
    'club' => 'Santos-SP',
    'position' => 'atacante',
    'season' => 1,
    'version' => 14,
    'stage' => 'resumo',
    'official' => false, // Liga regional legada
    'teams' => ['Santos-SP', 'São Paulo-SP'],
    'skills' => ['finalizacao' => ['value' => 60, 'xp' => 0]],
    'matches' => [
        [
            'round' => 1, 'home' => 'Santos-SP', 'away' => 'São Paulo-SP', 'hg' => 3, 'ag' => 0,
            'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
            'stats' => ['goals' => 2, 'assists' => 1, 'minutes' => 90, 'rating' => 8.0, 'shots' => 4, 'tackles' => 0]
        ],
        [
            'round' => 2, 'home' => 'São Paulo-SP', 'away' => 'Santos-SP', 'hg' => 0, 'ag' => 1,
            'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
            'stats' => ['goals' => 1, 'assists' => 0, 'minutes' => 90, 'rating' => 7.5, 'shots' => 2, 'tackles' => 0]
        ]
    ]
];

$htmlLegacy = renderAnnualRecap($gLegacy);
check(str_contains($htmlLegacy, 'Liga regional'), 'Modo legado exibe Liga regional');
check(str_contains($htmlLegacy, 'Terminou em <b>1º lugar</b> com 6 pontos.') || str_contains($htmlLegacy, 'Título conquistado!'), 'Modo legado calcula e exibe tabela do Schedule::table');
check(str_contains($htmlLegacy, 'Ver minha proposta →'), 'Modo legado preserva botão de janela de transferências');

// Ausência de classificação comprovada no modo legado (P2 Codex)
$gLegacyEmpty = $gLegacy;
$gLegacyEmpty['matches'] = [];
$gLegacyEmpty['teams'] = [];
$htmlLegacyEmpty = renderAnnualRecap($gLegacyEmpty);
check(str_contains($htmlLegacyEmpty, 'Não há classificação detalhada disponível para esta temporada.'), 'Sem tabela válida, exibe mensagem neutra de classificação indisponível');
check(!str_contains($htmlLegacyEmpty, '1º lugar') && !str_contains($htmlLegacyEmpty, '0 pontos') && !str_contains($htmlLegacyEmpty, 'Título conquistado!'), 'Sem tabela válida, não inventa 1º lugar, 0 pontos ou título');

// Resultado legítimo com 0 pontos quando há tabela válida e clube identificado (P2 Codex)
$gLegacyZeroPts = $gLegacy;
$gLegacyZeroPts['matches'] = [
    [
        'round' => 1, 'home' => 'São Paulo-SP', 'away' => 'Santos-SP', 'hg' => 2, 'ag' => 0,
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
        'stats' => ['goals' => 0, 'assists' => 0, 'minutes' => 90, 'rating' => 6.0, 'shots' => 1, 'tackles' => 0]
    ],
    [
        'round' => 2, 'home' => 'Santos-SP', 'away' => 'São Paulo-SP', 'hg' => 0, 'ag' => 1,
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
        'stats' => ['goals' => 0, 'assists' => 0, 'minutes' => 90, 'rating' => 6.0, 'shots' => 1, 'tackles' => 0]
    ]
];
$htmlLegacyZeroPts = renderAnnualRecap($gLegacyZeroPts);
check(str_contains($htmlLegacyZeroPts, 'com 0 pontos.') && !str_contains($htmlLegacyZeroPts, '1º lugar'), 'Preserva resultado legítimo com 0 pontos quando há tabela válida e clube identificado');

// -------------------------------------------------------------
// 5. Cenário: Temporada com Títulos e Sala de Troféus (sem duplicação)
// -------------------------------------------------------------
echo "\n5. Validando Conquistas e Troféus da Temporada...\n";
$gWithTrophies = $gBase;
$gWithTrophies['history'] = [
    [
        'season' => 1,
        'club' => 'Santos-SP',
        'competitions' => [
            'copa' => ['name' => 'Campeão da Copa Antiga', 'champion' => 'Santos-SP']
        ]
    ]
];

$htmlTrophies = renderAnnualRecap($gWithTrophies);
check(str_contains($htmlTrophies, 'Paulistão A1'), 'Troféu da temporada atual é exibido');
check(!str_contains($htmlTrophies, 'Campeão da Copa Antiga'), 'Troféu de temporada anterior NÃO é exibido na retrospectiva da temporada 2');

// -------------------------------------------------------------
// 6. Cenário: Temporada sem Títulos
// -------------------------------------------------------------
echo "\n6. Validando Temporada Sem Títulos...\n";
$gNoTrophies = $gBase;
$gNoTrophies['competition_results']['sp-a1']['champion'] = 'Palmeiras-SP';
$gNoTrophies['competition_results']['sp-a1']['position'] = 4;
$gNoTrophies['trophies'] = [];

$htmlNoTrophies = renderAnnualRecap($gNoTrophies);
check(str_contains($htmlNoTrophies, 'Nenhum título conquistado nesta temporada.'), 'Temporada sem títulos informa ausência de forma sóbria');

// -------------------------------------------------------------
// 7. Cenário: Jogador com Zero Gols ou Sem Partidas
// -------------------------------------------------------------
echo "\n7. Validando Jogador com Zero Gols e Sem Partidas (Zero-safe)...\n";
$gZero = $gBase;
$gZero['matches'] = [];

$htmlZero = renderAnnualRecap($gZero);
check(str_contains($htmlZero, 'data-stat="recap-games">0</strong>'), 'Zero partidas renderizado com segurança');
check(str_contains($htmlZero, 'data-stat="recap-goals">0</strong>'), 'Zero gols renderizado com segurança');
check(str_contains($htmlZero, 'data-stat="recap-assists">0</strong>'), 'Zero assistências renderizado com segurança');
check(str_contains($htmlZero, 'data-stat="recap-minutes">0′</strong>'), 'Zero minutos renderizado com segurança');
check(str_contains($htmlZero, 'data-stat="recap-rating">0,00</strong>'), 'Nota média padrão (0,00) exibida sem divisão por zero');

// -------------------------------------------------------------
// 8. Cenário: Evolução Técnica Fiel
// -------------------------------------------------------------
echo "\n8. Validando Evolução Técnica Fiel...\n";
// Baseline = 70.0, Atual = (70+74+72)/3 = 72.0 (+2.0)
check(str_contains($html1, 'Média técnica: <b>72,0</b> <span class="text-success">(+2,0 na temporada)</span>'), 'Evolução técnica positiva exibe variação correta');

$gStable = $gBase;
$gStable['season_skill_baseline'] = 72.0;
$htmlStable = renderAnnualRecap($gStable);
check(str_contains($htmlStable, 'estável na temporada'), 'Evolução estável identificada');

$gNoBaseline = $gBase;
unset($gNoBaseline['season_skill_baseline']);
$htmlNoBaseline = renderAnnualRecap($gNoBaseline);
check(str_contains($htmlNoBaseline, 'Média técnica: <b>72,0</b>'), 'Sem baseline histórica, exibe apenas média técnica atual sem ganhos inventados');

// -------------------------------------------------------------
// 9. Cenário: Próximo Passo e Preservação de Rota e Tokens
// -------------------------------------------------------------
echo "\n9. Validando Próximo Passo, Rota e Formulário...\n";
check(str_contains($html1, 'action="/transferencia/roleta"'), 'Formulário preserva ação POST /transferencia/roleta');
check(str_contains($html1, 'Ver minha proposta →'), 'Botão preserva texto oficial "Ver minha proposta →"');
check(str_contains($html1, 'name="csrf"'), 'Campo oculto CSRF presente');
check(str_contains($html1, 'name="version" value="15"'), 'Campo oculto version preservado');
check(str_contains($html1, 'name="key"'), 'Campo oculto de idempotência presente');

// -------------------------------------------------------------
// 10. Cenário: Idempotência e Imutabilidade do Estado
// -------------------------------------------------------------
echo "\n10. Validando Imutabilidade do Estado (Idempotência / F5)...\n";
$snapshotBefore = serialize($gBase);
$render1 = renderAnnualRecap($gBase);
$snapshotAfter1 = serialize($gBase);
check($snapshotBefore === $snapshotAfter1, 'Renderização da retrospectiva não altera estado');

$render2 = renderAnnualRecap($gBase);
$snapshotAfter2 = serialize($gBase);
check($snapshotBefore === $snapshotAfter2, 'Segunda renderização (simulando F5) mantém estado idêntico');
check($render1 === $render2, 'Saída HTML é 100% idêntica e determinística');

// -------------------------------------------------------------
// 11. Cenário: Compatibilidade com o Modo Expresso
// -------------------------------------------------------------
echo "\n11. Validando Compatibilidade com o Modo Expresso...\n";
$gExpresso = $gBase;
$gExpresso['play_mode'] = 'expresso';
$gExpresso['pace_report'] = [
    'mode' => 'expresso',
    'plan' => 'evolucao',
    'season' => 2,
    'growth' => ['finalizacao' => 2, 'passe' => 1],
    'matches' => []
];

$htmlExpresso = renderAnnualRecap($gExpresso);
check(str_contains($htmlExpresso, 'Paulistão A1') && str_contains($htmlExpresso, 'Brasileirão Série A'), 'Modo Expresso recupera todas as competições concluídas');
check(str_contains($htmlExpresso, 'Finalização +2') && str_contains($htmlExpresso, 'Passe +1'), 'Modo Expresso integra ganhos de habilidades registrados pelo pace report');

// -------------------------------------------------------------
// 12. Casos Obrigatórios R10-CAR-001-R5.3 (TC-D4-01 a TC-D4-10 e Imutabilidade)
// -------------------------------------------------------------
echo "\n12. Validando Casos Obrigatórios R10-CAR-001-R5.3 (TC-D4-01 a TC-D4-10)...\n";

$neutralMsg = 'Não há classificação detalhada disponível para esta temporada.';

// TC-D4-01: ida finalizada, volta agendada
$tc01 = $gLegacy;
$tc01['teams'] = ['Santos-SP', 'São Paulo-SP'];
$tc01['matches'] = [
    [
        'round' => 1, 'home' => 'Santos-SP', 'away' => 'São Paulo-SP', 'hg' => 1, 'ag' => 0,
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
        'stats' => Schedule::stats(), 'log' => []
    ],
    [
        'round' => 2, 'home' => 'São Paulo-SP', 'away' => 'Santos-SP', 'hg' => 0, 'ag' => 0,
        'status' => 'agendada', 'minute' => 0, 'lineup' => '', 'entry' => 0, 'exit' => 0,
        'stats' => Schedule::stats(), 'log' => []
    ]
];
$htmlTc01 = renderAnnualRecap($tc01);
check(str_contains($htmlTc01, $neutralMsg), 'TC-D4-01: Ida finalizada e volta agendada exibe mensagem neutra');
check(!str_contains($htmlTc01, 'Título conquistado!') && !str_contains($htmlTc01, 'terminou na liderança') && !str_contains($htmlTc01, 'º lugar'), 'TC-D4-01: Não declara título, liderança provisória nem colocação definitiva');

// TC-D4-02: campeonato integralmente encerrado
$tc02 = $tc01;
$tc02['matches'][1]['status'] = 'finalizada';
$tc02['matches'][1]['hg'] = 1;
$tc02['matches'][1]['ag'] = 1;
$htmlTc02 = renderAnnualRecap($tc02);
check(str_contains($htmlTc02, 'Título conquistado!') && str_contains($htmlTc02, 'terminou na liderança com 4 pontos.') && !str_contains($htmlTc02, $neutralMsg), 'TC-D4-02: Campeonato integralmente encerrado exibe título e pontuação real (4 pontos)');

// TC-D4-03: zero pontos legítimos
$tc03 = $tc01;
$tc03['matches'][0]['hg'] = 0;
$tc03['matches'][0]['ag'] = 2;
$tc03['matches'][1]['status'] = 'finalizada';
$tc03['matches'][1]['hg'] = 3;
$tc03['matches'][1]['ag'] = 1;
$htmlTc03 = renderAnnualRecap($tc03);
check(str_contains($htmlTc03, 'Campeão: <b>São Paulo-SP</b>') && str_contains($htmlTc03, 'Terminou em <b>2º lugar</b> com 0 pontos.') && !str_contains($htmlTc03, 'Título conquistado!'), 'TC-D4-03: Zero pontos legítimos apresenta 2º lugar com 0 pontos e campeão real');

// TC-D4-04: calendário totalmente pendente
$tc04 = $tc01;
$tc04['matches'][0]['status'] = 'agendada';
$tc04['matches'][1]['status'] = 'agendada';
$htmlTc04 = renderAnnualRecap($tc04);
check(str_contains($htmlTc04, $neutralMsg) && !str_contains($htmlTc04, 'Campeão:') && !str_contains($htmlTc04, 'Título conquistado!') && !str_contains($htmlTc04, 'º lugar'), 'TC-D4-04: Calendário totalmente pendente exibe mensagem neutra sem campeão ou posição');

// TC-D4-05: calendário vazio
$tc05 = $tc01;
$tc05['matches'] = [];
$htmlTc05 = renderAnnualRecap($tc05);
check(str_contains($htmlTc05, $neutralMsg) && !str_contains($htmlTc05, 'º lugar') && !str_contains($htmlTc05, 'Título conquistado!'), 'TC-D4-05: Calendário vazio exibe mensagem neutra sem erro fatal');

// TC-D4-06: clube ausente da tabela
$tc06 = $tc02;
$tc06['teams'] = ['São Paulo-SP', 'Palmeiras-SP'];
$tc06['matches'][0]['home'] = 'São Paulo-SP';
$tc06['matches'][0]['away'] = 'Palmeiras-SP';
$tc06['matches'][1]['home'] = 'Palmeiras-SP';
$tc06['matches'][1]['away'] = 'São Paulo-SP';
$htmlTc06 = renderAnnualRecap($tc06);
check(str_contains($htmlTc06, $neutralMsg) && !str_contains($htmlTc06, 'Título conquistado!') && !str_contains($htmlTc06, 'º lugar'), 'TC-D4-06: Clube ausente da tabela exibe mensagem neutra sem posição inventada');

// TC-D4-07: calendário truncado (apenas jogo de ida presente, volta ausente do array)
$tc07 = $tc01;
$tc07['matches'] = [$tc01['matches'][0]];
$htmlTc07 = renderAnnualRecap($tc07);
check(str_contains($htmlTc07, $neutralMsg) && !str_contains($htmlTc07, 'Título conquistado!') && !str_contains($htmlTc07, 'Campeão:') && !str_contains($htmlTc07, 'º lugar'), 'TC-D4-07: Calendário truncado não declara campeão nem colocação definitiva');

// TC-D4-08: calendário inconsistente (equipes duplicadas / confrontos duplicados)
$tc08a = $tc02;
$tc08a['teams'] = ['Santos-SP', 'Santos-SP'];
$htmlTc08a = renderAnnualRecap($tc08a);
check(str_contains($htmlTc08a, $neutralMsg) && !str_contains($htmlTc08a, 'Título conquistado!') && !str_contains($htmlTc08a, 'º lugar'), 'TC-D4-08a: Equipes duplicadas exibe mensagem neutra sem erro fatal');

$tc08b = $tc02;
$tc08b['matches'][1] = $tc02['matches'][0]; // Dois jogos de ida idênticos, faltando o jogo de volta
$htmlTc08b = renderAnnualRecap($tc08b);
check(str_contains($htmlTc08b, $neutralMsg) && !str_contains($htmlTc08b, 'Título conquistado!') && !str_contains($htmlTc08b, 'º lugar'), 'TC-D4-08b: Confrontos duplicados sem o jogo de volta exibe mensagem neutra');

// TC-D4-09: calendário completo com quatro equipes gerado por Schedule::generate()
$teams4 = ['Santos-SP', 'São Paulo-SP', 'Palmeiras-SP', 'Corinthians-SP'];
$matches4 = Schedule::generate($teams4);
foreach ($matches4 as &$m4) {
    $m4['status'] = 'finalizada';
    $m4['minute'] = 90;
    if ($m4['home'] === 'Palmeiras-SP') {
        $m4['hg'] = 2; $m4['ag'] = 0;
    } elseif ($m4['away'] === 'Palmeiras-SP') {
        $m4['hg'] = 0; $m4['ag'] = 2;
    } elseif ($m4['home'] === 'Santos-SP') {
        $m4['hg'] = 1; $m4['ag'] = 0;
    } elseif ($m4['away'] === 'Santos-SP') {
        $m4['hg'] = 0; $m4['ag'] = 1;
    } else {
        $m4['hg'] = 1; $m4['ag'] = 1;
    }
}
unset($m4);
$tc09 = $gLegacy;
$tc09['teams'] = $teams4;
$tc09['matches'] = $matches4;
$expectedTable4 = Schedule::table($matches4, $teams4);
$htmlTc09 = renderAnnualRecap($tc09);
check($expectedTable4[0]['team'] === 'Palmeiras-SP' && $expectedTable4[1]['team'] === 'Santos-SP' && $expectedTable4[1]['points'] === 12, 'TC-D4-09: Tabela oficial de 4 equipes calculada corretamente');
check(str_contains($htmlTc09, 'Campeão: <b>Palmeiras-SP</b>') && str_contains($htmlTc09, 'Terminou em <b>2º lugar</b> com 12 pontos.'), 'TC-D4-09: Calendário completo com 4 equipes exibe campeão e colocação coerentes com Schedule::table()');

// TC-D4-10: competição oficial com resultado persistido
$tc10 = $gBase;
$htmlTc10 = renderAnnualRecap($tc10);
check(str_contains($htmlTc10, 'Paulistão A1') && str_contains($htmlTc10, 'Título conquistado!') && str_contains($htmlTc10, 'Brasileirão Série A') && str_contains($htmlTc10, 'Terminou em <b>3º lugar</b> na primeira fase.'), 'TC-D4-10: Competição oficial com resultado persistido continua funcionando inalterada');

// Verificações explícitas de imutabilidade em múltiplos estados (Seção 8)
foreach (['tc01' => $tc01, 'tc02' => $tc02, 'tc07' => $tc07, 'tc09' => $tc09, 'tc10' => $tc10] as $label => $stateFixture) {
    $stateFixture['competition_results'] = $stateFixture['competition_results'] ?? [];
    $stateFixture['history'] = $stateFixture['history'] ?? [];
    $stateFixture['market_draw'] = $stateFixture['market_draw'] ?? null;
    $snapBefore = serialize($stateFixture);
    $fieldsBefore = [
        'matches' => $stateFixture['matches'],
        'teams' => $stateFixture['teams'] ?? [],
        'stage' => $stateFixture['stage'],
        'season' => $stateFixture['season'],
        'version' => $stateFixture['version'],
        'competition_results' => $stateFixture['competition_results'],
        'history' => $stateFixture['history'],
        'market_draw' => $stateFixture['market_draw'],
    ];
    ob_start(); Presentation::annualRecap($stateFixture); ob_end_clean();
    check(serialize($stateFixture) === $snapBefore, "Imutabilidade ($label): 1ª renderização não altera estado");
    ob_start(); Presentation::annualRecap($stateFixture); ob_end_clean();
    check(serialize($stateFixture) === $snapBefore, "Imutabilidade ($label): 2ª renderização mantém estado rigorosamente igual");
    foreach ($fieldsBefore as $fieldKey => $fieldVal) {
        check(($stateFixture[$fieldKey] ?? ($fieldKey === 'teams' ? [] : null)) === $fieldVal, "Imutabilidade ($label): campo '$fieldKey' intacto");
    }
}

echo "\n=======================================================\n";
echo "TODOS OS $checks TESTES DA MISSÃO R10-CAR-001-R5.3 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
