<?php
declare(strict_types=1);

/**
 * Avaliação consolidada da temporada recém-concluída (R10-CAR-002).
 * - Considera exclusivamente a temporada atual para definir o mérito e as proporções da roleta anual.
 * - Preserva o histórico de temporadas anteriores sem permitir que afete a distribuição de probabilidades.
 * - Evita dupla contagem excessiva separando média de atuação, produção complementar por posição,
 *   regularidade, volume (minutos + partidas), desempenho frente aos adversários e títulos do ano.
 */
final class SeasonEvaluation {
    public const SAMPLE_MINUTES = 720;
    public const SCHEMA = 2;

    public static function current(array $g): array {
        $p = class_exists('BalanceService') ? BalanceService::params('season.evaluation.v2') : [];
        $totals = array_fill_keys(['goals', 'assists', 'minutes', 'shots', 'passes', 'completed', 'tackles', 'saves', 'fouls', 'cards'], 0);
        $ratingWeightedSum = 0.0;
        $games = 0;
        $scheduledGames = 0;
        $matchRatings = [];
        $positiveGames = 0;
        $poorGames = 0;
        $cleanSheets = 0;
        $redCardGames = 0;
        $contextSum = 0.0;
        $contextMinutes = 0;
        $vsStrongDeltaSum = 0.0;
        $vsStrongWeight = 0.0;

        $club = (string)($g['club'] ?? '');
        $pos = strtolower((string)($g['position'] ?? 'meia'));
        $isDefensive = in_array($pos, ['goleiro', 'zagueiro', 'lateral', 'volante'], true);

        foreach (Competition::playerMatches($g) as $m) {
            if (!in_array($club, [$m['home'] ?? '', $m['away'] ?? ''], true)) {
                continue;
            }
            if (($m['status'] ?? '') !== 'finalizada') {
                continue;
            }
            $scheduledGames++;
            $s = $m['stats'] ?? [];
            $minutes = max(0, (int)($s['minutes'] ?? 0));
            if ($minutes <= 0) {
                continue;
            }
            $games++;
            foreach ($totals as $key => $_) {
                if (isset($s[$key]) && is_numeric($s[$key])) {
                    $totals[$key] += max(0, (int)$s[$key]);
                }
            }
            $r = isset($s['rating']) && is_numeric($s['rating']) && (float)$s['rating'] > 0
                ? (float)$s['rating']
                : 6.0;
            $ratingWeightedSum += $r * $minutes;
            $matchRatings[] = ['rating' => $r, 'minutes' => $minutes];
            if ($r >= 6.5) {
                $positiveGames++;
            } elseif ($r < 5.5) {
                $poorGames++;
            }

            $isHome = ($m['home'] === $club);
            $gf = $isHome ? (int)($m['hg'] ?? 0) : (int)($m['ag'] ?? 0);
            $ga = $isHome ? (int)($m['ag'] ?? 0) : (int)($m['hg'] ?? 0);
            if ($minutes >= 45 && $ga === 0) {
                $cleanSheets++;
            }
            if ((class_exists('MatchParticipation') && MatchParticipation::isSentOff($m)) || (int)($s['red_cards'] ?? 0) > 0) {
                $redCardGames++;
            }

            if (isset($m['home_overall'], $m['away_overall']) && is_numeric($m['home_overall']) && is_numeric($m['away_overall'])) {
                $ownOvr = (float)($isHome ? $m['home_overall'] : $m['away_overall']);
                $oppOvr = (float)($isHome ? $m['away_overall'] : $m['home_overall']);
                $diff = $oppOvr - $ownOvr;
                $factor = max(0.86, min(1.16, 1.0 + $diff / 180.0));
                $contextSum += $factor * $minutes;
                $contextMinutes += $minutes;
                if ($diff >= -2.0) {
                    $w = ($diff >= 4.0 ? 1.35 : 1.0) * ($minutes / 90.0);
                    $resBonus = $gf > $ga ? 0.25 : ($gf === $ga && $diff >= 3.0 ? 0.10 : ($gf < $ga ? -0.10 : 0.0));
                    $vsStrongDeltaSum += (($r - 6.0) / 3.0 + $resBonus) * $w;
                    $vsStrongWeight += $w;
                }
            }
        }

        $minutes = $totals['minutes'];
        $rates = [];
        foreach ($totals as $key => $value) {
            if ($key !== 'minutes') {
                $rates[$key] = $minutes > 0 ? round(90.0 * $value / $minutes, 4) : 0.0;
            }
        }

        $average = $minutes > 0 ? ($ratingWeightedSum / $minutes) : 6.0;
        $sampleMinutesRef = max(90.0, (float)($p['sample_minutes'] ?? self::SAMPLE_MINUTES));
        $sampleGamesRef = max(2.0, (float)($p['sample_games'] ?? 10.0));
        $wMinConf = (float)($p['w_minutes_conf'] ?? 0.65);
        $wGamesConf = (float)($p['w_games_conf'] ?? 0.35);
        $minConf = $minutes / ($minutes + $sampleMinutesRef);
        $gamesConf = $games / ($games + $sampleGamesRef);
        $confidence = $minutes > 0 ? ($wMinConf * $minConf + $wGamesConf * $gamesConf) : 0.0;

        // 1. Componente principal: média de atuação na temporada
        $ratingDiv = max(1.0, (float)($p['rating_divisor'] ?? 2.55));
        $ratingNorm = max(-0.45, min(1.35, ($average - 6.0) / $ratingDiv));
        $ratingComponent = (float)($p['w_rating'] ?? 0.52) * $ratingNorm;

        // 2. Produção complementar específica da posição (saturada para não duplicar o peso já presente na nota)
        $passAcc = $totals['passes'] > 0 ? ($totals['completed'] / $totals['passes']) : 0.0;
        $shotConv = $totals['shots'] > 0 ? ($totals['goals'] / $totals['shots']) : null;
        $cleanSheetRate = $games > 0 ? ($cleanSheets / $games) : 0.0;
        $gkInterventions90 = max($rates['saves'], $rates['tackles']);

        $rawRoleRate = match ($pos) {
            'goleiro' => 0.45 * min(1.5, $gkInterventions90 / 2.4) + 0.45 * $cleanSheetRate + 0.10 * $passAcc,
            'zagueiro' => 0.52 * min(1.5, $rates['tackles'] / 2.2) + 0.28 * $cleanSheetRate + 0.12 * $rates['assists'] + 0.08 * $rates['goals'],
            'volante' => 0.46 * min(1.5, $rates['tackles'] / 2.2) + 0.18 * $cleanSheetRate + 0.24 * $rates['assists'] + 0.12 * $rates['goals'],
            'lateral' => 0.34 * min(1.5, $rates['tackles'] / 2.0) + 0.12 * $cleanSheetRate + 0.36 * $rates['assists'] + 0.18 * $rates['goals'],
            'meia' => 0.34 * $rates['goals'] + 0.50 * $rates['assists'] + 0.16 * $passAcc,
            'ponta' => 0.52 * $rates['goals'] + 0.38 * $rates['assists'] + 0.10 * $passAcc,
            default => 0.68 * $rates['goals'] + 0.26 * $rates['assists'] + 0.06 * ($shotConv ?? 0.0),
        };
        $saturatedRole = $rawRoleRate / (1.0 + 0.55 * max(0.0, $rawRoleRate));
        $disciplineDrag = min(0.18, $redCardGames * 0.05 + max(0.0, $rates['cards'] - 0.35) * 0.08);
        $productionComponent = (float)($p['w_production'] ?? 0.22) * max(-0.15, min(1.25, $saturatedRole - $disciplineDrag));

        // 3. Regularidade entre as partidas disputadas
        $stdDev = 0.0;
        if (count($matchRatings) >= 2) {
            $varSum = 0.0;
            foreach ($matchRatings as $mr) {
                $d = $mr['rating'] - $average;
                $varSum += $d * $d;
            }
            $stdDev = sqrt($varSum / count($matchRatings));
        }
        $posShare = $games > 0 ? ($positiveGames / $games) : 0.0;
        $poorShare = $games > 0 ? ($poorGames / $games) : 0.0;
        $stabilityAdj = $stdDev > 1.35 ? -min(0.25, ($stdDev - 1.35) * 0.25) : ($games >= 5 && $average >= 6.5 && $stdDev <= 0.75 ? 0.15 : 0.0);
        $regularityIndex = $games > 0 ? max(-0.80, min(1.00, ($posShare - 1.15 * $poorShare) + $stabilityAdj)) : 0.0;
        $regularityComponent = (float)($p['w_regularity'] ?? 0.10) * $regularityIndex;

        // 4. Desempenho diante dos adversários
        $contextFactor = $contextMinutes > 0 ? ($contextSum / $contextMinutes) : 1.0;
        $vsStrongPerf = $vsStrongWeight > 0 ? ($vsStrongDeltaSum / $vsStrongWeight) : (($average - 6.0) / 3.5);
        $opponentNorm = max(-0.50, min(1.00, ($contextFactor - 1.0) * 3.0 + 0.55 * $vsStrongPerf));
        $opponentComponent = (float)($p['w_opponent'] ?? 0.06) * $opponentNorm;

        // 5. Títulos conquistados exclusivamente na temporada atual (escalados pela participação real)
        $seasonNum = (int)($g['season'] ?? 1);
        $seasonTitles = [];
        if (class_exists('Trophies')) {
            foreach (Trophies::collect($g) as $t) {
                if ((int)($t['season'] ?? 0) === $seasonNum && ($t['club'] ?? '') === $club) {
                    $seasonTitles[] = $t;
                }
            }
        }
        // Modo legado regional sem Trophies oficial: reconhece liderança apenas quando o calendário está 100% encerrado
        if (empty($g['official']) && !$seasonTitles && !empty($g['teams']) && !empty($g['matches'])) {
            $teams = array_values($g['teams']);
            $expected = count($teams) >= 2 && count($teams) % 2 === 0 ? count($teams) * (count($teams) - 1) : 0;
            $doneCount = count(array_filter($g['matches'], static fn($m) => ($m['status'] ?? '') === 'finalizada'));
            if ($expected > 0 && $doneCount === $expected) {
                $tbl = Schedule::table($g['matches'], $teams);
                if (($tbl[0]['team'] ?? '') === $club) {
                    $seasonTitles[] = ['season' => $seasonNum, 'club' => $club, 'title' => 'Liga regional', 'kind' => 'champion'];
                }
            }
        }
        $majorCount = 0;
        $stageCount = 0;
        foreach ($seasonTitles as $st) {
            if (($st['kind'] ?? 'champion') === 'stage_title') {
                $stageCount++;
            } else {
                $majorCount++;
            }
        }
        $rawTitleBonus = ($majorCount > 0 ? (float)($p['title_major_first'] ?? 0.12) + max(0, $majorCount - 1) * (float)($p['title_major_extra'] ?? 0.06) : 0.0)
            + $stageCount * (float)($p['title_stage'] ?? 0.04);
        $titlePresence = min(1.0, $minutes / 360.0);
        $titlesComponent = min((float)($p['title_cap'] ?? 0.20), $rawTitleBonus) * $titlePresence;

        // Combinação calibrada modulada pela amostra (minutos e partidas) da temporada concluída
        $baseQuality = 0.34 + $ratingComponent + $productionComponent + $regularityComponent + $opponentComponent;
        $rawScore = $minutes > 0
            ? max(0.02, min(1.50, $baseQuality * (0.35 + 0.65 * $confidence) + $titlesComponent))
            : 0.08;
        $score = round($rawScore, 4);
        $score100 = (int)round(max(0.0, min(100.0, ($score / 1.25) * 100.0)));

        $band = match (true) {
            $score >= (float)($p['band_exceptional_min'] ?? 1.05) => 'excepcional',
            $score >= (float)($p['band_excellent_min'] ?? 0.80) => 'excelente',
            $score >= (float)($p['band_good_min'] ?? 0.55) => 'boa',
            $score >= (float)($p['band_regular_min'] ?? 0.30) => 'regular',
            default => 'ruim',
        };
        $bandLabel = [
            'ruim' => 'Temporada abaixo do esperado',
            'regular' => 'Temporada regular',
            'boa' => 'Boa temporada',
            'excelente' => 'Temporada excelente',
            'excepcional' => 'Temporada excepcional',
        ][$band];

        $skills = $g['skills'] ?? [];
        $overall = $skills ? array_sum(array_column($skills, 'value')) / max(1, count($skills)) : 50.0;

        return [
            'schema' => self::SCHEMA,
            'season' => $seasonNum,
            'position' => $pos,
            'totals' => $totals,
            'per90' => $rates,
            'games' => $games,
            'scheduled_games' => $scheduledGames,
            'clean_sheets' => $cleanSheets,
            'average' => round($average, 3),
            'confidence' => round($confidence, 4),
            'regularity' => [
                'index' => round($regularityIndex, 4),
                'positive_games' => $positiveGames,
                'poor_games' => $poorGames,
                'std_dev' => round($stdDev, 3),
            ],
            'titles_count' => count($seasonTitles),
            'season_titles' => array_values(array_map(static fn($t) => $t['title'] ?? 'Campeão', $seasonTitles)),
            'opponent_context_factor' => round($contextFactor, 4),
            'context_minutes' => $contextMinutes,
            'sample_minutes_reference' => (int)$sampleMinutesRef,
            'conversion' => $shotConv !== null ? round($shotConv, 4) : null,
            'overall' => round($overall, 2),
            'components' => [
                'rating' => round($ratingComponent, 4),
                'role_production' => round($productionComponent, 4),
                'regularity' => round($regularityComponent, 4),
                'opponent_context' => round($opponentComponent, 4),
                'titles' => round($titlesComponent, 4),
                'availability_confidence' => round($confidence, 4),
            ],
            'is_defensive' => $isDefensive,
            'band' => $band,
            'band_label' => $bandLabel,
            'score_100' => $score100,
            'unavailable_metrics' => ['passes_decisivos', 'interceptacoes', 'duelos', 'gols_evitados'],
            'score' => $score,
        ];
    }

    /**
     * Avalia a temporada para a janela anual: 100% determinada pela última temporada concluída.
     * O histórico anterior permanece preservado apenas como registro informativo da carreira.
     */
    public static function evaluate(array $g): array {
        $current = self::current($g);
        $seasonNum = (int)($g['season'] ?? 1);
        $preservedHistory = [];
        $history = $g['history'] ?? [];
        usort($history, static fn($a, $b) => ($b['season'] ?? 0) <=> ($a['season'] ?? 0));
        $seen = [$seasonNum => true];
        foreach ($history as $h) {
            $s = (int)($h['season'] ?? 0);
            if ($s <= 0 || $s >= $seasonNum || isset($seen[$s])) {
                continue;
            }
            $seen[$s] = true;
            $preservedHistory[] = [
                'season' => $s,
                'club' => $h['club'] ?? null,
                'score' => isset($h['evaluation']['score']) ? (float)$h['evaluation']['score'] : null,
            ];
        }

        $parts = [['season' => $seasonNum, 'weight' => 1.0, 'score' => $current['score']]];
        return $current + [
            'history_weights' => $parts,
            'weighted_score' => $current['score'],
            'wheel_uses_history' => false,
            'history_available' => count($preservedHistory),
            'preserved_history' => $preservedHistory,
        ];
    }
}
