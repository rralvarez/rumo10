<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (!function_exists('form')) {
    function form(string $action, ?array $g = null): void {
        echo '<form method="post" action="' . e($action) . '">';
    }
}

$passed = 0;
$failed = 0;

function check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
}

mt_srand(42);
$path = '/carreira';
$error = null;
$_SESSION = ['account' => 1];

$g = Game::create('Rodrigo Silva', 'Erechim', 'RS', 'meia');
$g['id'] = 1;
$g = Game::command($g, 'handicap', [], true);
if ($g['stage'] === 'clube_inicio') {
    $g = Game::command($g, 'join', ['option' => 0], true);
}
while ($g['stage'] !== 'partida') {
    $g = Game::command($g, 'choice', ['option' => 0], true);
}

for ($step = 0; $step < 20; $step++) {
    $m = $g['matches'][Game::currentIndex($g)];
    if ($m['status'] !== 'em_andamento' || $m['minute'] < $m['entry'] || $m['minute'] >= $m['exit']) {
        $g = Game::command($g, 'advance', [], true);
    } else {
        $g = Game::command($g, 'choice', ['option' => 0], true);
        $m = $g['matches'][Game::currentIndex($g)] ?? null;
        if ($g['stage'] === 'partida' && $m && $m['status'] === 'em_andamento' && $m['minute'] >= $m['entry'] && $m['minute'] < $m['exit'] && !empty($g['feedback'])) {
            break;
        }
    }
}

$_GET = [];
ob_start();
include __DIR__ . '/../app/view.php';
$htmlPartida = ob_get_clean();

check(str_contains($htmlPartida, 'class="playing in-match"'), 'In-match body has class "playing in-match"');
check(str_contains($htmlPartida, 'compact-score'), 'In-match renders .compact-score block');
check(str_contains($htmlPartida, 'action-result'), 'In-match renders .action-result feedback block');
check(str_contains($htmlPartida, 'action="/partida/decidir"'), 'In-match renders current play choices form');

// Advance until fim_semana
while ($g['stage'] === 'partida') {
    $m = $g['matches'][Game::currentIndex($g)];
    if ($m['status'] !== 'em_andamento' || $m['minute'] < $m['entry'] || $m['minute'] >= $m['exit']) {
        $g = Game::command($g, 'advance', [], true);
    } else {
        $g = Game::command($g, 'choice', ['option' => 0], true);
    }
}

check($g['stage'] === 'fim_semana', 'Match concludes to stage fim_semana');

// Render Post-Match Step 1 (default /carreira)
$_GET = [];
ob_start();
include __DIR__ . '/../app/view.php';
$htmlFimStep1 = ob_get_clean();

check(str_contains($htmlFimStep1, 'post-match-summary'), 'Post-match Step 1 body has class "post-match-summary"');
check(!str_contains($htmlFimStep1, 'post-match-decision'), 'Post-match Step 1 body does not have class "post-match-decision"');
check(str_contains($htmlFimStep1, 'data-action="continue-post-match"'), 'Post-match Step 1 renders "Continuar ->" continuation button');
check(str_contains($htmlFimStep1, 'href="/carreira?etapa=pos_jogo"'), 'Continuation button has fallback href="/carreira?etapa=pos_jogo"');
check(str_contains($htmlFimStep1, 'weekend-decision-panel'), 'Weekend decision panel has class "weekend-decision-panel" for step separation');

// Render Post-Match Step 2 (/carreira?etapa=pos_jogo)
$_GET = ['etapa' => 'pos_jogo'];
ob_start();
include __DIR__ . '/../app/view.php';
$htmlFimStep2 = ob_get_clean();

check(str_contains($htmlFimStep2, 'post-match-decision'), 'Post-match Step 2 body has class "post-match-decision"');
check(!str_contains($htmlFimStep2, 'post-match-summary'), 'Post-match Step 2 body does not have class "post-match-summary"');

// Verify CSS rules in visual.css and JS handler in game.js
$css = file_get_contents(__DIR__ . '/../public/assets/visual.css');
check(str_contains($css, 'body.post-match-summary .weekend-decision-panel'), 'CSS hides .weekend-decision-panel during post-match-summary step');
check(str_contains($css, 'body.post-match-decision .post-match'), 'CSS hides .post-match during post-match-decision step');
check(str_contains($css, '.in-match .compact-score') && str_contains($css, 'order: 2'), 'CSS sets mobile order: 2 on .in-match .compact-score');
check(str_contains($css, '.in-match .action-result') && str_contains($css, 'order: 3'), 'CSS sets mobile order: 3 on .in-match .action-result');

$js = file_get_contents(__DIR__ . '/../public/assets/game.js');
check(str_contains($js, 'continue-post-match') && str_contains($js, 'post-match-decision'), 'game.js handles [data-action="continue-post-match"] transition to post-match-decision');

echo "\nTotal: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
