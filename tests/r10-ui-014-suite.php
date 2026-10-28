<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function ok(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("FALHA: $msg");
}

echo "========================================================================\n";
echo "Executando bateria de testes R10-UI-014...\n";
echo "========================================================================\n";
$checks = 0;

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="'.e($path).'">';
    }
}

// =========================================================================
// TESTE 1: Tarefa C - Redesenho das Estatísticas
// =========================================================================
echo "\n[Tarefa C] Testando estatísticas redesenhadas em MatchExperience...\n";

// Caso 1: 90 minutos, nota 6.9, 1 gol, 0 assistências
$mCase1 = [
    'stats' => [
        'minutes' => 90,
        'rating' => 6.9,
        'goals' => 1,
        'assists' => 0,
        'shots' => 3,
        'passes' => 12,
        'completed' => 9,
        'tackles' => 1,
        'cards' => 0,
    ],
    'experience' => [
        'complete' => true,
        'earned' => ['finalizacao' => 8, 'posicionamento' => 6],
        'before' => array_fill_keys(Rules::SKILLS, ['value' => 50, 'xp' => 10]),
        'after' => array_fill_keys(Rules::SKILLS, ['value' => 50, 'xp' => 10]),
        'development_raw_xp' => 14,
        'bonus' => array_fill_keys(Rules::SKILLS, 0),
    ]
];

ob_start();
MatchExperience::render($mCase1);
$htmlStats1 = ob_get_clean();

ok(str_contains($htmlStats1, 'class="match-protagonist-strip"'), 'C1: Strip de estatísticas renderizado');
ok(str_contains($htmlStats1, 'stat-chip-minutes') && str_contains($htmlStats1, '>90<') && str_contains($htmlStats1, '>Minutos<'), 'C1: Chip de minutos renderiza 90');
ok(str_contains($htmlStats1, 'stat-chip-rating') && str_contains($htmlStats1, '>6,9<') && str_contains($htmlStats1, '>Nota<'), 'C1: Chip de nota formata com vírgula 6,9');
ok(str_contains($htmlStats1, 'stat-chip-goals') && str_contains($htmlStats1, '>1<') && str_contains($htmlStats1, '>Gol<'), 'C1: Chip de gol no singular (1 Gol)');
ok(str_contains($htmlStats1, 'stat-chip-assists') && str_contains($htmlStats1, '>0<') && str_contains($htmlStats1, '>Assistências<'), 'C1: Chip de assistências no plural com zero (0 Assistências)');
ok(str_contains($htmlStats1, 'xp-badge') && str_contains($htmlStats1, '+14 XP') && str_contains($htmlStats1, 'Aplicados ao desenvolvimento'), 'C1: Bloco de XP independente com +14 XP');
$checks += 6;

// Caso 2: Jogador com múltiplas assistências (plural)
$mCase2 = $mCase1;
$mCase2['stats']['goals'] = 0;
$mCase2['stats']['assists'] = 2;
ob_start();
MatchExperience::render($mCase2);
$htmlStats2 = ob_get_clean();
ok(str_contains($htmlStats2, '>0<') && str_contains($htmlStats2, '>Gols<'), 'C2: Zero gols no plural (0 Gols)');
ok(str_contains($htmlStats2, '>2<') && str_contains($htmlStats2, '>Assistências<'), 'C2: Múltiplas assistências no plural (2 Assistências)');
$checks += 2;

// Caso 3: Jogador com exatamente 1 assistência (singular)
$mCase3 = $mCase1;
$mCase3['stats']['assists'] = 1;
ob_start();
MatchExperience::render($mCase3);
$htmlStats3 = ob_get_clean();
ok(str_contains($htmlStats3, '>1<') && str_contains($htmlStats3, '>Assistência<'), 'C3: Uma assistência no singular (1 Assistência)');
$checks++;

// Caso 4: Jogador substituído (ex: 60 minutos)
$mCase4 = $mCase1;
$mCase4['stats']['minutes'] = 60;
ob_start();
MatchExperience::render($mCase4);
$htmlStats4 = ob_get_clean();
ok(str_contains($htmlStats4, '>60<') && str_contains($htmlStats4, '>Minutos<'), 'C4: Minutos substituído respeitados (60 Minutos)');
$checks++;

// Caso 5: Estatísticas ausentes ou vazias (resiliência)
$mCase5 = ['stats' => []];
ob_start();
MatchExperience::render($mCase5);
$htmlStats5 = ob_get_clean();
ok(str_contains($htmlStats5, '>0<') && str_contains($htmlStats5, '>0,0<'), 'C5: Resiliência a array de estatísticas vazio');
$checks++;

// =========================================================================
// TESTE 2: Tarefa B - Fluxo de Pênaltis
// =========================================================================
echo "\n[Tarefa B] Testando continuidade do fluxo de disputa de pênaltis...\n";

// Caso A: Empate agregado com regra de pênaltis (Quartas Paulistão A3)
$tKnockout = [
    'id' => 'sp-a3',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'EC São Bernardo-SP', 'b' => 'XV de Jaú-SP', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        [
            'round' => 16,
            'phase' => 'quartas',
            'tie' => 0,
            'home' => 'XV de Jaú-SP',
            'away' => 'EC São Bernardo-SP',
            'hg' => 0,
            'ag' => 0,
            'status' => 'finalizada',
            'minute' => 90,
            'lineup' => 'titular',
            'entry' => 0,
            'exit' => 90,
            'stats' => ['minutes' => 90, 'goals' => 0, 'rating' => 6.0, 'assists' => 0, 'shots' => 1, 'passes' => 5, 'completed' => 4, 'tackles' => 0, 'cards' => 0],
            'log' => []
        ],
        [
            'round' => 17,
            'phase' => 'quartas',
            'tie' => 0,
            'home' => 'EC São Bernardo-SP',
            'away' => 'XV de Jaú-SP',
            'hg' => 0,
            'ag' => 0,
            'status' => 'finalizada',
            'minute' => 90,
            'lineup' => 'titular',
            'entry' => 0,
            'exit' => 90,
            'stats' => ['minutes' => 90, 'goals' => 0, 'rating' => 6.0, 'assists' => 0, 'shots' => 1, 'passes' => 5, 'completed' => 4, 'tackles' => 0, 'cards' => 0],
            'log' => []
        ],
    ]
];

$gTie = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'EC São Bernardo-SP', 'SP', 'centroavante');
$gTie['age'] = 16;
$gTie['club'] = 'EC São Bernardo-SP';
$gTie['competition_id'] = 'sp-a3';
$gTie['competitions'] = ['sp-a3' => $tKnockout];
$gTie['matches'] = $tKnockout['matches'];
$gTie['round'] = 17;
$gTie['stage'] = 'fim_semana';

$mTie = $gTie['matches'][1];
ok(PenaltyShootout::requiresShootout($gTie, $mTie), 'B1: Empate agregado 0x0 exige pênaltis');
$checks++;

// Verificação de que Game::choices($g) bloqueia escolhas de fim_semana concorrentes
$choicesTie = Game::choices($gTie);
ok(count($choicesTie) === 0, 'B1: Game::choices bloqueia decisões de fim de semana enquanto houver pênaltis pendentes');
$checks++;

// Renderização da view no fim_semana com pênalti pendente
$path = '/carreira';
$error = null;
$_SESSION = ['account' => 1, 'csrf' => 'token123'];
ob_start();
$g = $gTie;
$m = $mTie;
include dirname(__DIR__).'/app/view.php';
$htmlViewTie = ob_get_clean();

ok(str_contains($htmlViewTie, 'shootout-inline-cta'), 'B1: CTA de pênaltis renderizado dentro do post-match');
ok(str_contains($htmlViewTie, 'Começar as cobranças de pênaltis'), 'B1: Botão de ação direta presente');
ok(str_contains($htmlViewTie, 'action="/penaltis/iniciar"'), 'B1: Form aponta para /penaltis/iniciar');
ok(!str_contains($htmlViewTie, 'O que você faz?'), 'B1: Cena narrativa de fim de semana NÃO é renderizada enquanto houver pênalti');
$checks += 4;

// Caso B: Iniciar disputa e testar transição para stage 'penaltis'
$gStarted = Game::command($gTie, 'shootout_start');
ok($gStarted['stage'] === 'penaltis', 'B2: Transição confirmada para stage penaltis');
ok(isset($gStarted['shootout']) && $gStarted['shootout']['status'] === 'in_progress', 'B2: Objeto shootout inicializado in_progress');
$checks += 2;

// Renderização na etapa 'penaltis'
ob_start();
$g = $gStarted;
$m = $mTie;
include dirname(__DIR__).'/app/view.php';
$htmlViewShootout = ob_get_clean();
ok(str_contains($htmlViewShootout, 'shootout-panel'), 'B2: Painel de pênaltis exibido durante stage penaltis');
ok(str_contains($htmlViewShootout, 'shootout-indicators'), 'B2: Indicadores visuais de cobranças presentes');
$checks += 2;

// Caso C: Vitória regulamentar no agregado sem pênaltis (ex: 2x0)
$tWin = $tKnockout;
$tWin['matches'][1]['hg'] = 2; // EC São Bernardo venceu 2x0 (agregado 2x0)
$gWin = $gTie;
$gWin['matches'] = $tWin['matches'];
$gWin['competitions']['sp-a3'] = $tWin;
$mWin = $gWin['matches'][1];

ok(!PenaltyShootout::requiresShootout($gWin, $mWin), 'B3: Vitória no agregado (2x0) não requer pênaltis');
$choicesWin = Game::choices($gWin);
ok(count($choicesWin) > 0, 'B3: Escolhas normais de fim de semana são liberadas quando não há disputa pendente');
$checks += 2;

ob_start();
$g = $gWin;
$m = $mWin;
include dirname(__DIR__).'/app/view.php';
$htmlViewWin = ob_get_clean();
ok(!str_contains($htmlViewWin, 'shootout-inline-cta'), 'B3: CTA de pênaltis NÃO aparece quando há vencedor no tempo normal');
ok(str_contains($htmlViewWin, 'Classificado'), 'B3: Classificação direta informada no agregado');
$checks += 2;

// Caso D: Derrota no agregado sem pênaltis (ex: 0x1)
$tLoss = $tKnockout;
$tLoss['matches'][1]['ag'] = 1; // XV de Jaú venceu 1x0 (agregado 1x0)
$gLoss = $gTie;
$gLoss['matches'] = $tLoss['matches'];
$gLoss['competitions']['sp-a3'] = $tLoss;
$mLoss = $gLoss['matches'][1];

ok(!PenaltyShootout::requiresShootout($gLoss, $mLoss), 'B4: Derrota no agregado (0x1) não requer pênaltis');
ob_start();
$g = $gLoss;
$m = $mLoss;
include dirname(__DIR__).'/app/view.php';
$htmlViewLoss = ob_get_clean();
ok(!str_contains($htmlViewLoss, 'shootout-inline-cta'), 'B4: CTA de pênaltis NÃO aparece na derrota');
$checks += 2;

// Caso E: Conclusão da disputa e continuidade da carreira
$gDone = $gStarted;
$gDone['shootout']['status'] = 'completed';
$gDone['shootout']['winner'] = 'EC São Bernardo-SP';
$gDone['shootout']['score']['EC São Bernardo-SP'] = 5;
$gDone['shootout']['score']['XV de Jaú-SP'] = 4;

$gFinished = Game::command($gDone, 'shootout_finish');
ok($gFinished['stage'] === 'fim_semana', 'B5: shootout_finish transiciona para fim_semana');
ok($gFinished['competitions']['sp-a3']['ties'][0]['winner'] === 'EC São Bernardo-SP', 'B5: Vencedor gravado no torneio');
ok(!PenaltyShootout::requiresShootout($gFinished, $gFinished['matches'][1]), 'B5: Partida com pênaltis concluídos não requer nova disputa');
$checks += 3;

echo "\n========================================================================\n";
ok($gStarted['version']===$gTie['version']+1,'Comando de início incrementa versão');
ok($gFinished['version']===$gDone['version']+1,'Comando de conclusão incrementa versão');
$checks+=2;
echo "TODOS OS $checks TESTES DA BATERIA R10-UI-014 FORAM APROVADOS COM SUCESSO!\n";
echo "========================================================================\n";

