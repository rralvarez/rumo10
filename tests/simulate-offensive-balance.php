<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function runSeasonSimulation(string $position, string $club, string $uf, int $numSeasons = 20): array {
    $stats = [
        'seasons' => $numSeasons,
        'total_matches' => 0,
        'total_goals' => 0,
        'total_assists' => 0,
        'total_shots' => 0,
        'total_clear_chances' => 0,
        'total_decisions' => 0,
        'total_minutes' => 0,
        'seasons_with_zero_goals' => 0,
        'shot_conversions' => [],
        'clear_conversions' => []
    ];

    for ($s = 0; $s < $numSeasons; $s++) {
        try {
            $g = Game::legacyCreate("Sim $s", $club, $uf, $position);
            // Stage handicap
            if ($g['stage'] === 'handicap') {
                $g = Game::command($g, 'handicap');
            }

            $seasonGoals = 0;
            $seasonMatches = 0;

            // Run until round 14 or 15 or season end
            $maxLoops = 3000;
            $loop = 0;
            while ($g['stage'] !== 'resumo' && $g['stage'] !== 'oferta' && $g['stage'] !== 'aposentado' && $loop++ < $maxLoops) {
                $stage = $g['stage'];

                if ($stage === 'treino') {
                    $g = Game::command($g, 'choice', ['option' => 0]);
                } elseif ($stage === 'pre_jogo') {
                    $g = Game::command($g, 'choice', ['option' => 0]);
                } elseif ($stage === 'partida') {
                    $m = $g['matches'][Game::currentIndex($g)];
                    if ($m['status'] === 'pre_jogo' || $m['status'] === 'intervalo' || $m['minute'] < $m['entry'] || $m['minute'] >= $m['exit']) {
                        $g = Game::command($g, 'advance');
                        continue;
                    }

                    $choices = Game::choices($g);
                    if (empty($choices)) {
                        $g = Game::command($g, 'advance');
                        continue;
                    }

                    $stats['total_decisions']++;
                    $sit = Game::situation($g);
                    $isClear = !empty($sit['clear_chance']);
                    if ($isClear) {
                        $stats['total_clear_chances']++;
                    }

                    // Choose action strategy based on position:
                    // Forward: wants to shoot when clear chance or continuation, or pass to build
                    // Midfielder: passes to assist or build
                    $chosenIdx = 0;
                    $chuteIdx = null;
                    $passeIdx = null;
                    $dribleIdx = null;

                    foreach ($choices as $idx => $c) {
                        if ($c['action'] === 'chute') $chuteIdx = $idx;
                        if ($c['action'] === 'passe' && empty($c['end_sequence'])) $passeIdx = $idx;
                        if ($c['action'] === 'drible' && empty($c['end_sequence'])) $dribleIdx = $idx;
                    }

                    if ($isClear) {
                        if ($position === 'centroavante' || $position === 'ponta') {
                            $chosenIdx = $chuteIdx ?? 0;
                        } elseif ($position === 'meia') {
                            $chosenIdx = $passeIdx ?? $chuteIdx ?? 0;
                        } else {
                            $chosenIdx = $chuteIdx ?? 0;
                        }
                    } elseif (($sit['id'] ?? 0) === 1301) {
                        if ($position === 'centroavante') {
                            $chosenIdx = (random_int(1, 100) <= 50 && $chuteIdx !== null) ? $chuteIdx : ($passeIdx ?? $dribleIdx ?? 0);
                        } else {
                            $chosenIdx = $passeIdx ?? $dribleIdx ?? 0;
                        }
                    } else {
                        if ($chuteIdx !== null && random_int(1, 100) <= 40) {
                            $chosenIdx = $chuteIdx;
                        } elseif ($passeIdx !== null) {
                            $chosenIdx = $passeIdx;
                        } elseif ($dribleIdx !== null) {
                            $chosenIdx = $dribleIdx;
                        } else {
                            $chosenIdx = 0;
                        }
                    }

                    $cAction = $choices[$chosenIdx]['action'] ?? '';
                    if ($cAction === 'chute') {
                        $stats['total_shots']++;
                    }

                    $g = Game::command($g, 'choice', ['option' => $chosenIdx]);
                } elseif ($stage === 'penaltis') {
                    $g = Game::command($g, 'choice', ['option' => 0]);
                } elseif ($stage === 'fim_semana') {
                    $curM = $g['matches'][Game::currentIndex($g)];
                    $seasonMatches++;
                    $seasonGoals += $curM['stats']['goals'];
                    $stats['total_matches']++;
                    $stats['total_goals'] += $curM['stats']['goals'];
                    $stats['total_assists'] += $curM['stats']['assists'];
                    $stats['total_minutes'] += $curM['stats']['minutes'];

                    if (class_exists('PenaltyShootout') && PenaltyShootout::requiresShootout($g, $curM)) {
                        $g = Game::command($g, 'shootout_start');
                        continue;
                    }

                    $g = Game::command($g, 'choice', ['option' => 0]);
                } else {
                    break;
                }

                if ($seasonMatches >= 15) {
                    break;
                }
            }

            if ($seasonGoals === 0) {
                $stats['seasons_with_zero_goals']++;
            }
        } catch (Throwable $e) {
            echo "Error during season $s, stage " . ($g['stage'] ?? 'unknown') . ": " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
            break;
        }
    }

    $avgMatches = $stats['total_matches'] / max(1, $numSeasons);
    return [
        'position' => $position,
        'club' => $club,
        'seasons' => $numSeasons,
        'avg_matches_per_season' => round($avgMatches, 1),
        'avg_minutes_per_match' => round($stats['total_minutes'] / max(1, $stats['total_matches']), 1),
        'avg_goals_per_season' => round($stats['total_goals'] / max(1, $numSeasons), 2),
        'avg_assists_per_season' => round($stats['total_assists'] / max(1, $numSeasons), 2),
        'avg_shots_per_season' => round($stats['total_shots'] / max(1, $numSeasons), 2),
        'avg_clear_chances_per_season' => round($stats['total_clear_chances'] / max(1, $numSeasons), 2),
        'avg_decisions_per_match' => round($stats['total_decisions'] / max(1, $stats['total_matches']), 2),
        'zero_goal_season_pct' => round(100 * $stats['seasons_with_zero_goals'] / max(1, $numSeasons), 1) . '%',
        'shot_goal_conversion_pct' => round(100 * $stats['total_goals'] / max(1, $stats['total_shots']), 1) . '%'
    ];
}

echo "=== SIMULAÇÃO ATUAL DO MOTOR (20 TEMPORADAS) ===\n\n";

$resCA_Weak = runSeasonSimulation('centroavante', 'Portuguesa Santista-SP', 'SP', 25);
echo "1. Centroavante em Clube Fraco/Médio (Portuguesa Santista):\n";
print_r($resCA_Weak);

$resCA_Strong = runSeasonSimulation('centroavante', 'São Paulo', 'SP', 20);
echo "\n2. Centroavante em Clube Forte (São Paulo):\n";
print_r($resCA_Strong);

$resMeia = runSeasonSimulation('meia', 'Portuguesa Santista-SP', 'SP', 20);
echo "\n3. Meia em Clube Fraco/Médio (Portuguesa Santista):\n";
print_r($resMeia);

$resZag = runSeasonSimulation('zagueiro', 'Portuguesa Santista-SP', 'SP', 15);
echo "\n4. Zagueiro em Clube Fraco/Médio (Portuguesa Santista):\n";
print_r($resZag);
