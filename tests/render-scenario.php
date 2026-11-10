<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

$scenario = $argv[1] ?? 'pos_jogo_penaltis';

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="'.e($path).'">';
    }
}

$path = '/carreira';
$error = null;
$_SESSION = ['account' => 1, 'csrf' => 'token_preview'];

if ($scenario === 'pos_jogo_penaltis') {
    $t = [
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
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'EC São Bernardo-SP', 'SP', 'centroavante');
    $g['age'] = 16;
    $g['club'] = 'EC São Bernardo-SP';
    $g['competition_id'] = 'sp-a3';
    $g['competitions'] = ['sp-a3' => $t];
    $g['matches'] = $t['matches'];
    $g['round'] = 17;
    $g['stage'] = 'fim_semana';
    $g['feedback'] = [
        'class' => 'falha',
        'text' => 'Identificar qual passagem está realmente aberta: Você olha apenas a chegada e ignora o bloqueio no trajeto.',
        'effects' => ['foco' => -2, 'energia' => -3]
    ];
    $m = $g['matches'][1];
} elseif ($scenario === 'estatisticas_redesenhadas') {
    // Cenário padrão com 90 minutos, nota 6.9, 1 gol, 0 assistências, +14 XP
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'EC São Bernardo-SP', 'SP', 'centroavante');
    $g['age'] = 16;
    $g['club'] = 'EC São Bernardo-SP';
    $g['stage'] = 'fim_semana';
    $g['matches'][0] = [
        'round' => 1,
        'phase' => 'liga',
        'home' => 'EC São Bernardo-SP',
        'away' => 'União Suzano-SP',
        'hg' => 2,
        'ag' => 1,
        'status' => 'finalizada',
        'minute' => 90,
        'lineup' => 'titular',
        'entry' => 0,
        'exit' => 90,
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
            'before' => array_merge(array_fill_keys(Rules::SKILLS, ['value' => 56, 'xp' => 10])),
            'after' => array_merge(array_fill_keys(Rules::SKILLS, ['value' => 56, 'xp' => 10]), ['finalizacao' => ['value' => 57, 'xp' => 2]]),
            'development_raw_xp' => 14,
            'bonus' => array_fill_keys(Rules::SKILLS, 0),
        ],
        'log' => []
    ];
    $g['round'] = 1;
    $g['feedback'] = [
        'class' => 'sucesso',
        'text' => 'Finalização colocada: Você encontra o canto e marca um belo gol nos acréscimos.',
        'effects' => ['confianca' => 5]
    ];
    $m = $g['matches'][0];
} elseif ($scenario === 'disputa_penaltis_em_andamento') {
    $t = [
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
                'stats' => ['minutes' => 90, 'goals' => 0, 'rating' => 6.0, 'assists' => 0],
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
                'stats' => ['minutes' => 90, 'goals' => 0, 'rating' => 6.0, 'assists' => 0],
                'log' => []
            ],
        ]
    ];
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'EC São Bernardo-SP', 'SP', 'centroavante');
    $g['club'] = 'EC São Bernardo-SP';
    $g['competition_id'] = 'sp-a3';
    $g['competitions'] = ['sp-a3' => $t];
    $g['matches'] = $t['matches'];
    $g['round'] = 17;
    $m = $g['matches'][1];
    PenaltyShootout::start($g, $m);
    $g['stage'] = 'penaltis';
    // Protagonist kick round 5
    $g['shootout']['current_turn_team'] = $g['club'];
    $g['shootout']['current_round'] = 5;
    $g['shootout']['protagonist_kick_round'] = 5;
    $g['shootout']['protagonist_acted'] = false;
    $g['shootout']['score']['EC São Bernardo-SP'] = 4;
    $g['shootout']['score']['XV de Jaú-SP'] = 4;
    $g['shootout']['kicks'] = [
        ['team' => 'XV de Jaú-SP', 'scored' => true],
        ['team' => 'EC São Bernardo-SP', 'scored' => true],
        ['team' => 'XV de Jaú-SP', 'scored' => true],
        ['team' => 'EC São Bernardo-SP', 'scored' => true],
        ['team' => 'XV de Jaú-SP', 'scored' => true],
        ['team' => 'EC São Bernardo-SP', 'scored' => true],
        ['team' => 'XV de Jaú-SP', 'scored' => true],
        ['team' => 'EC São Bernardo-SP', 'scored' => true],
    ];
} elseif ($scenario === 'jogada_continuacao_gol') {
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'Portuguesa Santista-SP', 'SP', 'centroavante');
    $g['stage'] = 'partida';
    $i = Game::currentIndex($g);
    $g['matches'][$i]['status'] = 'em_andamento';
    $g['matches'][$i]['minute'] = 68;
    $g['matches'][$i]['entry'] = 0;
    $g['matches'][$i]['exit'] = 90;
    $g['matches'][$i]['hg'] = 1;
    $g['matches'][$i]['ag'] = 1;
    $g['matches'][$i]['attack_sequence'] = 1;
    $g['matches'][$i]['possession'] = ['minute' => 68, 'score' => [1, 1], 'actions' => 1];
    $g['states']['energia'] = 82;
    $g['states']['confianca'] = 65;
    $g['states']['ritmo_jogo'] = 70;
    $m = $g['matches'][$i];
} elseif ($scenario === 'jogada_chance_clara') {
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'Portuguesa Santista-SP', 'SP', 'centroavante');
    $g['stage'] = 'partida';
    $i = Game::currentIndex($g);
    $g['matches'][$i]['status'] = 'em_andamento';
    $g['matches'][$i]['minute'] = 75;
    $g['matches'][$i]['entry'] = 0;
    $g['matches'][$i]['exit'] = 90;
    $g['matches'][$i]['hg'] = 1;
    $g['matches'][$i]['ag'] = 1;
    $g['matches'][$i]['clear_chance'] = true;
    $g['matches'][$i]['attack_sequence'] = 2;
    $g['matches'][$i]['possession'] = ['minute' => 75, 'score' => [1, 1], 'actions' => 2];
    $g['states']['energia'] = 78;
    $g['states']['confianca'] = 70;
    $g['states']['ritmo_jogo'] = 75;
    $m = $g['matches'][$i];
}

ob_start();
include dirname(__DIR__).'/app/view.php';
echo ob_get_clean();

