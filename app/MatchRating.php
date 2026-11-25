<?php
declare(strict_types=1);

/**
 * Avaliação individual de desempenho por partida (R10-CAR-002).
 * - Escala fechada [0.0, 10.0] com arredondamento a 1 casa decimal.
 * - Utiliza exclusivamente estatísticas confiáveis registradas na partida regulamentar.
 * - Atleta sem minutos jogados permanece sem nota de atuação (0).
 * - Gols de disputas de pênaltis pós-jogo nunca integram as estatísticas nem a nota da partida.
 * - Não altera nem recalcula partidas já encerradas.
 */
final class MatchRating {
    public const VERSION = 2;

    /**
     * Retorna o detalhamento completo dos componentes da nota para auditoria e calibração.
     */
    public static function breakdown(array $m, array $g): array {
        $s = $m['stats'] ?? [];
        $minutes = max(0, (int)($s['minutes'] ?? 0));
        if ($minutes <= 0) {
            return [
                'version' => self::VERSION,
                'participated' => false,
                'minutes' => 0,
                'rating' => 0.0,
                'components' => [],
            ];
        }

        $p = class_exists('BalanceService') ? BalanceService::params('match.rating.v2') : [];
        $pos = strtolower((string)($g['position'] ?? 'meia'));
        $isGk = ($pos === 'goleiro');
        $isCb = ($pos === 'zagueiro');
        $isDm = ($pos === 'volante');
        $isFb = ($pos === 'lateral');
        $isMid = ($pos === 'meia');
        $isDefensive = ($isGk || $isCb || $isDm || $isFb);

        // Estatísticas regulamentares confiáveis (disputa de pênaltis fica em $m['shootout'] / $m['penalties'])
        $goals = max(0, (int)($s['goals'] ?? 0));
        $assists = max(0, (int)($s['assists'] ?? 0));
        $shots = max($goals, (int)($s['shots'] ?? 0));
        $missedShots = max(0, $shots - $goals);
        $penMissed = max(0, (int)($s['penalties_missed'] ?? 0));
        $passes = max(0, (int)($s['passes'] ?? 0));
        $completed = max(0, min($passes, (int)($s['completed'] ?? 0)));
        $passErrors = max(0, $passes - $completed);
        $tackles = max(0, (int)($s['tackles'] ?? 0));
        $saves = ($isGk && isset($s['saves']) && is_numeric($s['saves'])) ? max(0, (int)$s['saves']) : 0;
        $fouls = max(0, (int)($s['fouls'] ?? 0));

        $sentOff = (class_exists('MatchParticipation') && MatchParticipation::isSentOff($m))
            || (int)($s['red_cards'] ?? 0) > 0
            || (int)($s['direct_red'] ?? 0) > 0
            || (int)($s['second_yellow'] ?? 0) > 0;
        $directRed = (int)($s['direct_red'] ?? 0) > 0
            || ($sentOff && (int)($s['second_yellow'] ?? 0) === 0 && (int)($s['yellow_cards'] ?? ($s['cards'] ?? 0)) <= 1);
        $rawCards = max(0, (int)($s['cards'] ?? 0));
        $yellows = array_key_exists('yellow_cards', $s)
            ? max(0, (int)$s['yellow_cards'])
            : ($sentOff ? ($directRed ? 0 : min(2, $rawCards)) : $rawCards);

        $fullRef = max(30.0, (float)($p['full_factor_minutes'] ?? 75.0));
        $minFactor = min(1.0, $minutes / $fullRef);
        $volumePresence = 0.60 + 0.40 * min(1.0, $minutes / 60.0);

        $base = (float)($p['base'] ?? 6.00);

        // 1. Impacto ofensivo decisivo (gols + marcos de hat-trick/poker)
        $wGoal = match (true) {
            $isGk || $isCb => (float)($p['w_goal_def'] ?? 1.20),
            $isDm || $isFb => (float)($p['w_goal_sup'] ?? 1.12),
            $isMid => (float)($p['w_goal_mid'] ?? 1.02),
            default => (float)($p['w_goal_att'] ?? 0.90),
        };
        $goalScore = $goals * $wGoal;
        if ($goals >= 3) {
            $goalScore += (float)($p['hat_trick_bonus'] ?? 0.15);
        }
        if ($goals >= 4) {
            $goalScore += (float)($p['poker_bonus'] ?? 0.15);
        }

        // 2. Criação e assistências
        $wAssist = match (true) {
            $isGk || $isCb => (float)($p['w_assist_def'] ?? 0.80),
            $isMid || $isFb || $pos === 'ponta' => (float)($p['w_assist_creative'] ?? 0.78),
            default => (float)($p['w_assist_other'] ?? 0.70),
        };
        $assistScore = $assists * $wAssist + ($assists >= 2 ? (float)($p['multi_assist_bonus'] ?? 0.15) : 0.0);

        // 3. Eficiência de finalização e construção de passes
        $efficiencyScore = 0.0;
        if ($goals >= 1 && $shots >= 2) {
            $conv = $goals / $shots;
            if ($conv >= 0.999) {
                $efficiencyScore += (float)($p['shot_eff_perfect_bonus'] ?? 0.25);
            } elseif ($conv >= 0.50) {
                $efficiencyScore += (float)($p['shot_eff_good_bonus'] ?? 0.15);
            }
        } elseif ($goals === 1 && $shots === 1) {
            $efficiencyScore += (float)($p['shot_eff_good_bonus'] ?? 0.15);
        }
        // Desperdício de finalizações (tolera 1 chute sem gol quando houve gol marcado)
        $chargeableMisses = $goals > 0 ? max(0, $missedShots - 1) : $missedShots;
        $efficiencyScore -= $chargeableMisses * (float)($p['w_shot_miss'] ?? 0.06);
        if ($goals === 0 && $shots >= 4) {
            $efficiencyScore -= (float)($p['zero_goal_high_shots_pen'] ?? 0.20);
        }

        $wPassOk = ($isMid || $isDm) ? (float)($p['w_pass_ok_mid'] ?? 0.16) : (float)($p['w_pass_ok'] ?? 0.14);
        $passBonus = min((float)($p['pass_ok_cap'] ?? 0.85), $completed * $wPassOk * $volumePresence);
        if ($passes >= 3) {
            $passAcc = $completed / $passes;
            if ($passes >= 4 && $passAcc >= 0.999) {
                $passBonus += (float)($p['pass_acc_perfect_bonus'] ?? 0.34) * $minFactor;
            } elseif ($passAcc >= 0.80) {
                $passBonus += (float)($p['pass_acc_high_bonus'] ?? 0.24) * $minFactor;
            }
        }
        $wPassErr = ($isGk || $isCb) ? (float)($p['w_pass_err_def'] ?? 0.18) : (float)($p['w_pass_err'] ?? 0.14);
        $passErrorPenalty = $passErrors * $wPassErr;

        // 4. Ações defensivas, intervenções de goleiro e solidez coletiva em campo
        $wTackle = match (true) {
            $isCb => (float)($p['w_tackle_cb'] ?? 0.54),
            $isDm => (float)($p['w_tackle_dm'] ?? 0.52),
            $isFb => (float)($p['w_tackle_fb'] ?? 0.48),
            $isGk => (float)($p['w_tackle_gk'] ?? 0.50),
            default => (float)($p['w_tackle_att'] ?? 0.22),
        };
        $defActionScore = $tackles * $wTackle * $volumePresence;
        if ($isDefensive) {
            if ($tackles >= 3) {
                $defActionScore += (float)($p['tackle_tier1_bonus'] ?? 0.30) * $minFactor;
            }
            if ($tackles >= 4) {
                $defActionScore += (float)($p['tackle_tier2_bonus'] ?? 0.25) * $minFactor;
            }
        }
        if ($isGk && $saves > 0) {
            $defActionScore += $saves * (float)($p['w_save_gk'] ?? 0.55) * $volumePresence;
            if ($saves >= 4) {
                $defActionScore += (float)($p['save_tier1_bonus'] ?? 0.35) * $minFactor;
            }
            if ($saves >= 6) {
                $defActionScore += (float)($p['save_tier2_bonus'] ?? 0.25) * $minFactor;
            }
        }

        $solidarityScore = 0.0;
        $teamResultScore = 0.0;
        $club = (string)($g['club'] ?? '');
        $isHome = $club !== '' && ($m['home'] ?? '') === $club;
        $isAway = $club !== '' && ($m['away'] ?? '') === $club;
        if (($isHome || $isAway) && isset($m['hg'], $m['ag']) && is_numeric($m['hg']) && is_numeric($m['ag'])) {
            $gf = $isHome ? (int)$m['hg'] : (int)$m['ag'];
            $ga = $isHome ? (int)$m['ag'] : (int)$m['hg'];

            if ($isDefensive && $minutes >= 30 && !$sentOff) {
                if ($ga === 0) {
                    $csBonus = $isGk
                        ? (float)($p['clean_sheet_gk'] ?? 1.15)
                        : ($isCb ? (float)($p['clean_sheet_cb'] ?? 0.90) : (float)($p['clean_sheet_def'] ?? 0.75));
                    $solidarityScore += $csBonus * $minFactor;
                } elseif ($ga === 1) {
                    $lowBonus = ($isGk || $isCb)
                        ? (float)($p['low_ga_cb_gk'] ?? 0.25)
                        : (float)($p['low_ga_def'] ?? 0.15);
                    $solidarityScore += $lowBonus * $minFactor;
                } elseif ($ga >= 3) {
                    $excessSteps = min(3, $ga - 2);
                    $gaStep = $isGk
                        ? (float)($p['high_ga_gk_step'] ?? 0.30)
                        : ($isCb ? (float)($p['high_ga_cb_step'] ?? 0.22) : (float)($p['high_ga_def_step'] ?? 0.15));
                    $solidarityScore -= $excessSteps * $gaStep * $minFactor;
                }

                if ($minutes >= 60 && !$sentOff && $yellows === 0 && $fouls === 0 && ($tackles + $saves >= 2 || $ga === 0)) {
                    $solidarityScore += (float)($p['clean_def_discipline_bonus'] ?? 0.25);
                }
            }

            if ($gf > $ga) {
                $teamResultScore = (float)($p['win_bonus'] ?? 0.00) * $minFactor;
            } elseif ($gf < $ga) {
                $teamResultScore = -(float)($p['loss_penalty'] ?? 0.00) * $minFactor;
            }
        }

        // 5. Erros graves e indisciplina (sem duplicar amarelo dentro de expulsão por 2º amarelo)
        $disciplinePenalty = $penMissed * (float)($p['w_penalty_missed'] ?? 0.45);
        if ($sentOff) {
            $disciplinePenalty += $directRed
                ? (float)($p['w_direct_red'] ?? 1.65)
                : (float)($p['w_second_yellow_red'] ?? 1.40);
            $unbookedFouls = max(0, $fouls - ($directRed ? 1 : 2));
            $disciplinePenalty += $unbookedFouls * (float)($p['w_foul'] ?? 0.08);
        } else {
            $disciplinePenalty += min(1, $yellows) * (float)($p['w_yellow'] ?? 0.42);
            $unbookedFouls = max(0, $fouls - $yellows);
            $disciplinePenalty += $unbookedFouls * (float)($p['w_foul'] ?? 0.08);
        }

        $raw = $base
            + $goalScore
            + $assistScore
            + $efficiencyScore
            + $passBonus
            - $passErrorPenalty
            + $defActionScore
            + $solidarityScore
            + $teamResultScore
            - $disciplinePenalty;

        $rating = round(max(0.0, min(10.0, $raw)), 1);

        return [
            'version' => self::VERSION,
            'participated' => true,
            'position' => $pos,
            'minutes' => $minutes,
            'rating' => $rating,
            'raw' => round($raw, 4),
            'components' => [
                'base' => round($base, 3),
                'goals' => round($goalScore, 3),
                'assists' => round($assistScore, 3),
                'shot_efficiency' => round($efficiencyScore, 3),
                'passing_net' => round($passBonus - $passErrorPenalty, 3),
                'defensive_actions' => round($defActionScore, 3),
                'defensive_solidity' => round($solidarityScore, 3),
                'team_result' => round($teamResultScore, 3),
                'discipline_and_errors' => round(-$disciplinePenalty, 3),
            ],
        ];
    }

    public static function calculate(array $m, array $g): float {
        return self::breakdown($m, $g)['rating'];
    }
}
