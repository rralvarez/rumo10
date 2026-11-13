<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function sampleMatchGame(
    int $minute,
    int $hg,
    int $ag,
    string $status = 'em_andamento',
    int $entry = 0,
    int $exit = 90,
    string $lineup = 'titular',
    ?array $feedback = null,
    bool $clearChance = false,
    array $extraMatch = []
): array {
    $g = Game::legacyCreate('Rodrigo Rodrigues Alvarez', 'São Paulo', 'SP', 'centroavante');
    $g['id'] = 999;
    $g['version'] = 42;
    $g['round'] = 2;
    $g['stage'] = 'partida';
    $g['states']['energia'] = 82;
    $g['states']['confianca'] = 75;
    $g['states']['ritmo_jogo'] = 70;
    
    foreach ($g['matches'] as &$match) {
        if (in_array($g['club'], [$match['home'], $match['away']], true) && $match['round'] === 2) {
            $match['status'] = $status;
            $match['minute'] = $minute;
            $match['entry'] = $entry;
            $match['exit'] = $exit;
            $match['lineup'] = $lineup;
            $own = ($match['home'] === $g['club']) ? 'hg' : 'ag';
            $opp = ($own === 'hg') ? 'ag' : 'hg';
            $match[$own] = $hg;
            $match[$opp] = $ag;
            $match['stats']['goals'] = min($hg, 2);
            $match['stats']['assists'] = 0;
            $match['stats']['shots'] = 3;
            $match['stats']['passes'] = 5;
            $match['stats']['completed'] = 4;
            $match['stats']['minutes'] = ($minute >= $entry) ? min($minute, $exit) - $entry : 0;
            $match['stats']['rating'] = 7.5;
            if ($clearChance) {
                $match['clear_chance'] = true;
            }
            foreach ($extraMatch as $k => $v) {
                $match[$k] = $v;
            }
        }
    }
    unset($match);
    
    $g['feedback'] = $feedback ?? [
        'success' => true,
        'class' => 'sucesso',
        'highlight' => null,
        'text' => 'A jogada avança com boa troca de passes.',
        'effects' => ['confianca' => 3, 'energia' => -2]
    ];
    return $g;
}

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="'.e($path).'">';
    }
}

$scenarios = [
    // 1. Minuto 30' - Titular em campo (~33% barra)
    'preview-30m.html' => function() {
        return sampleMatchGame(30, 1, 0, 'em_andamento', 0, 90, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'A equipe constrói a jogada no campo de ataque aos 30 minutos.',
            'effects' => ['confianca' => 2]
        ], false, ['participation_state' => 'ON_FIELD']);
    },
    // 2. Minuto 45' - Intervalo (50% barra)
    'preview-45m.html' => function() {
        return sampleMatchGame(45, 1, 1, 'intervalo', 0, 90, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Apito do árbitro: fim da primeira etapa.',
            'effects' => []
        ], false, ['participation_state' => 'ON_FIELD']);
    },
    // 3. Minuto 60' - Reserva que entrou (~67% barra)
    'preview-60m.html' => function() {
        return sampleMatchGame(60, 1, 1, 'em_andamento', 60, 90, 'reserva', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Você entra em campo no segundo tempo pronto para fazer a diferença.',
            'effects' => ['energia' => -1]
        ], false, ['participation_state' => 'ON_FIELD']);
    },
    // 4. Minuto 90' - Reserva que NÃO entra (permaneceu no banco, 100% barra)
    'preview-90m.html' => function() {
        return sampleMatchGame(90, 2, 1, 'finalizada', 90, 90, 'reserva', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'O árbitro finaliza a partida com vitória da sua equipe.',
            'effects' => []
        ], false, ['participation_state' => 'ON_BENCH']);
    },
    // 5. Expulso aos 68'
    'preview-expulso.html' => function() {
        $g = sampleMatchGame(70, 0, 1, 'em_andamento', 0, 68, 'titular', [
            'success' => false,
            'class' => 'falha',
            'highlight' => null,
            'text' => 'Falta dura na intermediária: o árbitro apresenta o cartão vermelho direto!',
            'effects' => ['confianca' => -10]
        ], false, [
            'participation_state' => 'SENT_OFF',
            'red_card_minute' => 68,
            'stats' => ['goals' => 0, 'assists' => 0, 'shots' => 1, 'passes' => 3, 'completed' => 2, 'minutes' => 68, 'rating' => 4.5, 'red_cards' => 1]
        ]);
        return $g;
    },
    // 6. Suspenso
    'preview-suspenso.html' => function() {
        return sampleMatchGame(0, 0, 0, 'em_andamento', 90, 90, 'suspenso', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Você acompanha o início da partida das tribunas cumprindo suspensão.',
            'effects' => []
        ], false, ['participation_state' => 'SUSPENDED']);
    },
    // 7. Substituído
    'preview-substituido.html' => function() {
        return sampleMatchGame(80, 2, 2, 'em_andamento', 0, 70, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Após intenso desgaste físico em campo, você deixa o gramado sob aplausos.',
            'effects' => ['energia' => -4]
        ], false, ['participation_state' => 'SUBSTITUTED_OUT']);
    },
    // 8. Legado - Reserva no banco sem participation_state
    'preview-legado-banco.html' => function() {
        return sampleMatchGame(15, 0, 0, 'em_andamento', 60, 90, 'reserva', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'No banco de reservas aguardando a oportunidade da comissão técnica.',
            'effects' => []
        ]);
    }
];

$publicDir = dirname(__DIR__).'/public';
foreach ($scenarios as $filename => $builder) {
    $g = $builder();
    $path = '/carreira';
    $error = null;
    $_SESSION = ['account' => 1, 'csrf' => 'token_preview'];
    
    ob_start();
    include dirname(__DIR__).'/app/view.php';
    $html = ob_get_clean();
    
    file_put_contents($publicDir . '/' . $filename, $html);
    echo "Gerado: $filename\n";
}
echo "Previews gerados com sucesso!\n";
