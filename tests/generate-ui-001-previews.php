<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!function_exists('form')) {
    function form(string $action, ?array $g = null): void {
        echo '<form method="post" action="' . e($action) . '">';
    }
}

function sampleGame(string $stage = 'fim_semana', array $get = []): string {
    $_GET = $get;
    $_SESSION = ['account' => 1, 'csrf' => 'preview-csrf'];
    $error = null;
    $path = '/carreira';

    $g = Game::legacyCreate('Lucas Silva', 'Santos', 'SP', 'meia');
    $g['id'] = 101;
    $g['version'] = 10;
    $g['club'] = 'Santos';
    $g['round'] = 2;
    $g['season'] = 1;
    $g['play_mode'] = 'normal';
    $g['stage'] = $stage;

    // Rodada 1 finalizada
    $g['matches'][0]['round'] = 1;
    $g['matches'][0]['home'] = 'Santos';
    $g['matches'][0]['away'] = 'Palmeiras';
    $g['matches'][0]['status'] = 'finalizada';
    $g['matches'][0]['minute'] = 90;
    $g['matches'][0]['entry'] = 0;
    $g['matches'][0]['exit'] = 90;
    $g['matches'][0]['hg'] = 2;
    $g['matches'][0]['ag'] = 1;
    $g['matches'][0]['lineup'] = 'titular';
    $g['matches'][0]['stats'] = ['goals' => 1, 'assists' => 1, 'shots' => 3, 'passes' => 10, 'completed' => 8, 'tackles' => 2, 'cards' => 0, 'minutes' => 90, 'rating' => 8.2];
    $g['matches'][0]['log'] = [['minute' => 34, 'text' => 'Gol de Lucas Silva!']];

    // Rodada 2
    $g['matches'][1]['round'] = 2;
    $g['matches'][1]['home'] = 'Corinthians';
    $g['matches'][1]['away'] = 'Santos';
    if ($stage === 'fim_semana') {
        $g['matches'][1]['status'] = 'finalizada';
        $g['matches'][1]['minute'] = 90;
        $g['matches'][1]['entry'] = 0;
        $g['matches'][1]['exit'] = 90;
        $g['matches'][1]['hg'] = 0;
        $g['matches'][1]['ag'] = 2;
        $g['matches'][1]['lineup'] = 'titular';
        $g['matches'][1]['participation_state'] = 'FULL_TIME';
        $g['matches'][1]['stats'] = ['goals' => 2, 'assists' => 0, 'shots' => 4, 'passes' => 12, 'completed' => 10, 'tackles' => 1, 'cards' => 0, 'minutes' => 90, 'rating' => 8.8];
        $g['matches'][1]['experience'] = [
            'schema' => 1,
            'complete' => true,
            'earned' => array_fill_keys(Rules::SKILLS, 10),
            'bonus' => array_fill_keys(Rules::SKILLS, 0),
            'before' => $g['skills'],
            'after' => $g['skills']
        ];
    } else {
        $g['matches'][1]['status'] = 'em_andamento';
        $g['matches'][1]['minute'] = 60;
        $g['matches'][1]['entry'] = 0;
        $g['matches'][1]['exit'] = 90;
        $g['matches'][1]['hg'] = 0;
        $g['matches'][1]['ag'] = 1;
        $g['matches'][1]['lineup'] = 'titular';
        $g['matches'][1]['participation_state'] = 'ON_FIELD';
        $g['matches'][1]['stats'] = ['goals' => 1, 'assists' => 0, 'shots' => 2, 'passes' => 7, 'completed' => 6, 'tackles' => 1, 'cards' => 0, 'minutes' => 60, 'rating' => 7.5];
    }

    ob_start();
    include __DIR__ . '/../app/view.php';
    return ob_get_clean();
}

$root = dirname(__DIR__) . '/public';

// 1. Etapa A: Fim de Jogo (post-match-summary)
file_put_contents($root . '/preview-ui001-etapa-a.html', sampleGame('fim_semana', []));

// 2. Etapa C: Decisões (post-match-decision)
file_put_contents($root . '/preview-ui001-etapa-c.html', sampleGame('fim_semana', ['etapa' => 'pos_jogo']));

// 3. Partida em andamento com identificação da competição
file_put_contents($root . '/preview-ui001-partida.html', sampleGame('partida', []));

echo "Previews HTML gerados com sucesso!\n";
