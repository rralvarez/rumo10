<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function sampleMatchGame(int $minute, int $hg, int $ag, string $status = 'em_andamento', int $entry = 0, int $exit = 90, string $lineup = 'titular', ?array $feedback = null, bool $clearChance = false): array {
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
        }
    }
    unset($match);
    
    $g['feedback'] = $feedback ?? [
        'success' => true,
        'class' => 'sucesso',
        'highlight' => null,
        'text' => 'A jogada avança pelo meio com boa troca de passes.',
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
    'preview-1t.html' => function() {
        return sampleMatchGame(35, 1, 0, 'em_andamento', 0, 90, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => 'goal',
            'text' => 'Finalização colocada no canto direito! A bola toca a trave e morre no fundo das redes.',
            'effects' => ['confianca' => 5, 'energia' => -3]
        ]);
    },
    'preview-intervalo.html' => function() {
        return sampleMatchGame(45, 1, 1, 'intervalo', 0, 90, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Você protege a posse e segura a bola até o apito do árbitro encerrando o primeiro tempo.',
            'effects' => ['energia' => -2]
        ]);
    },
    'preview-2t.html' => function() {
        return sampleMatchGame(70, 2, 1, 'em_andamento', 0, 90, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'A tabela deu certo e desmontou a defesa adversária: você tem uma chance clara de gol!',
            'effects' => ['confianca' => 4]
        ], true);
    },
    'preview-banco.html' => function() {
        return sampleMatchGame(15, 0, 0, 'em_andamento', 60, 90, 'reserva', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'O treinador observa o posicionamento das linhas adversárias e instrui os suplentes.',
            'effects' => []
        ]);
    },
    'preview-substituido.html' => function() {
        return sampleMatchGame(80, 2, 2, 'em_andamento', 0, 70, 'titular', [
            'success' => true,
            'class' => 'sucesso',
            'highlight' => null,
            'text' => 'Após intenso desgaste físico em campo, você deixa o gramado sob aplausos.',
            'effects' => ['energia' => -4]
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
