<?php
declare(strict_types=1);

/**
 * Gestão de Participação do Protagonista na Partida
 * Estados canônicos: STARTING, ON_BENCH, ON_FIELD, SUBSTITUTED_OUT, SENT_OFF, FULL_TIME, NOT_SELECTED, SUSPENDED.
 */
final class MatchParticipation {
    public const STATE_STARTING = 'STARTING';
    public const STATE_ON_BENCH = 'ON_BENCH';
    public const STATE_ON_FIELD = 'ON_FIELD';
    public const STATE_SUBSTITUTED_OUT = 'SUBSTITUTED_OUT';
    public const STATE_SENT_OFF = 'SENT_OFF';
    public const STATE_FULL_TIME = 'FULL_TIME';
    public const STATE_NOT_SELECTED = 'NOT_SELECTED';
    public const STATE_SUSPENDED = 'SUSPENDED';

    /**
     * Determina a condição inicial no pré-jogo
     */
    public static function prepare(array &$m, array &$g, array $s): void {
        $cid = $m['competition'] ?? ($g['competition_id'] ?? 'legacy');
        $suspended = class_exists('MatchDiscipline') && MatchDiscipline::isSuspended($g, $cid);

        if ($suspended) {
            $m['lineup'] = 'suspenso';
            $m['participation_state'] = self::STATE_SUSPENDED;
            $m['entry'] = 90;
            $m['exit'] = 90;
            $m['suspension_reason'] = MatchDiscipline::suspensionReason($g, $cid);
            $m['log'][] = ['minute' => 0, 'text' => 'Você está suspenso nesta competição e não poderá atuar nesta partida.'];
            return;
        }

        $absent = ($g['states']['energia'] < 15 || $g['states']['risco_lesao'] > 85);
        if ($absent) {
            $m['lineup'] = 'não relacionado';
            $m['participation_state'] = self::STATE_NOT_SELECTED;
            $m['entry'] = 90;
            $m['exit'] = 90;
            $m['log'][] = ['minute' => 0, 'text' => 'Você não foi relacionado para esta partida.'];
            return;
        }

        $bench = ($s['bench'] ?? ($s['id'] === 6));
        if ($bench) {
            $m['lineup'] = 'reserva';
            $m['participation_state'] = self::STATE_ON_BENCH;
            // Taxa de entrada do banco alinhada com OpportunityForecast (~80%)
            $enters = (random_int(1, 100) <= 80);
            if ($enters) {
                // Entrada no 2º tempo (60' ou 70')
                $m['entry'] = (random_int(1, 100) <= 70) ? 60 : 70;
                $m['exit'] = 90;
            } else {
                // Permanece no banco o jogo todo (unused sub)
                $m['entry'] = 90;
                $m['exit'] = 90;
            }
        } else {
            $m['lineup'] = 'titular';
            $m['participation_state'] = self::STATE_STARTING;
            $m['entry'] = 0;
            $m['exit'] = 90;
        }
    }

    /**
     * Registra imediatamente uma saída de campo (expulsão, lesão, desgaste ou tática)
     * preservando a causa estruturada e evitando múltiplas causas na mesma jogada.
     */
    public static function recordExit(array &$m, array &$g, int $minute, string $reason): void {
        $state = $m['participation_state'] ?? null;
        if ($reason === 'sent_off') {
            $m['exit'] = $minute;
            $m['participation_state'] = self::STATE_SENT_OFF;
            $m['exit_reason'] = 'sent_off';
            $m['team_reduced'] = true;
            $m['red_card_minute'] = $minute;
            return;
        }

        // Não sobrescrever um estado terminal já consolidado (ex.: expulso ou já substituído)
        if (in_array($state, [self::STATE_SENT_OFF, self::STATE_SUBSTITUTED_OUT, self::STATE_FULL_TIME, self::STATE_NOT_SELECTED, self::STATE_SUSPENDED], true)) {
            return;
        }

        $m['exit'] = $minute;
        $m['participation_state'] = self::STATE_SUBSTITUTED_OUT;
        $m['exit_reason'] = $reason;
        if (empty($m['sub_out_recorded'])) {
            $m['sub_out_recorded'] = true;
            Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_OUT', $minute);
        }
    }

    /**
     * Atualiza o estado de participação ao transitar minutos/segmentos (idempotente)
     */
    public static function sync(array &$m, array &$g, int $minute): void {
        $state = $m['participation_state'] ?? null;
        if (!$state) {
            // Reconciliação legada
            $st = $m['stats'] ?? [];
            $isRed = !empty($m['red_card_minute'])
                || (int)($st['red_cards'] ?? 0) > 0
                || (int)($st['direct_red'] ?? 0) > 0
                || (int)($st['second_yellow'] ?? 0) > 0;
            if ($isRed) $state = self::STATE_SENT_OFF;
            elseif (($m['lineup'] ?? '') === 'não relacionado') $state = self::STATE_NOT_SELECTED;
            elseif (($m['lineup'] ?? '') === 'suspenso') $state = self::STATE_SUSPENDED;
            elseif (isset($m['exit']) && is_numeric($m['exit']) && (int)$m['exit'] > 0 && (int)$m['exit'] < 90 && $minute >= (int)$m['exit']) $state = self::STATE_SUBSTITUTED_OUT;
            elseif (($m['lineup'] ?? '') === 'reserva') $state = ($minute >= ($m['entry'] ?? 90) && $minute < ($m['exit'] ?? 90)) ? self::STATE_ON_FIELD : self::STATE_ON_BENCH;
            else $state = ($minute >= ($m['entry'] ?? 0) && $minute < ($m['exit'] ?? 90)) ? self::STATE_ON_FIELD : self::STATE_STARTING;
        }

        // Estados terminais imutáveis: expulsos, substituídos, ausentes e encerrados não retornam a campo
        if (in_array($state, [self::STATE_NOT_SELECTED, self::STATE_SUSPENDED, self::STATE_SENT_OFF, self::STATE_SUBSTITUTED_OUT, self::STATE_FULL_TIME], true)) {
            $m['participation_state'] = $state;
            return;
        }

        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $entry = (int)($m['entry'] ?? 0);
        $exit = (int)($m['exit'] ?? 90);

        // Checagem de entrada em campo do banco (apenas com partida em curso)
        if (!$isFinished && $state === self::STATE_ON_BENCH && $entry < 90 && $minute >= $entry && $minute < $exit) {
            $m['participation_state'] = self::STATE_ON_FIELD;
            if (empty($m['sub_in_recorded'])) {
                $m['sub_in_recorded'] = true;
                $m['log'][] = ['minute' => $entry, 'text' => "{$entry}' | Você entra em campo no lugar de um companheiro."];
                Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_IN', $entry);
            }
            return;
        }

        // Checagem de saída por substituição (idempotente)
        if ($state === self::STATE_ON_FIELD && $exit < 90 && $minute >= $exit) {
            $m['participation_state'] = self::STATE_SUBSTITUTED_OUT;
            if (empty($m['exit_reason'])) {
                $m['exit_reason'] = 'tactical';
            }
            if (empty($m['sub_out_recorded'])) {
                $m['sub_out_recorded'] = true;
                $m['log'][] = ['minute' => $exit, 'text' => "{$exit}' | Você é substituído após participar da partida."];
                Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_OUT', $exit);
            }
            return;
        }

        // Encerramento efetivo da partida: FULL_TIME vinculado ao status 'finalizada'
        if ($isFinished) {
            if ($state === self::STATE_ON_FIELD || ($state === self::STATE_STARTING && $entry < 90 && $exit >= 90)) {
                $m['participation_state'] = self::STATE_FULL_TIME;
            } elseif ($state === self::STATE_ON_BENCH && $entry >= 90) {
                $m['participation_state'] = self::STATE_ON_BENCH;
                if (empty($m['unused_bench_logged'])) {
                    $m['unused_bench_logged'] = true;
                    $m['log'][] = ['minute' => max(90, $minute), 'text' => 'Você permaneceu no banco e não entrou em campo.'];
                }
            }
            return;
        }

        if ($state === self::STATE_STARTING && $minute >= $entry && $minute < $exit) {
            $m['participation_state'] = self::STATE_ON_FIELD;
        }
    }

    /**
     * Calcula minutos estatísticos efetivamente disputados sem adulterar entry/exit.
     * Admite mínimo de 1 minuto quando houve ação válida em campo no mesmo minuto da entrada.
     */
    public static function playedMinutes(array $m): int {
        $entry = (int)($m['entry'] ?? 0);
        $exit = (int)($m['exit'] ?? 0);
        $state = $m['participation_state'] ?? null;
        $lineup = (string)($m['lineup'] ?? '');

        if (in_array($state, [self::STATE_SUSPENDED, self::STATE_NOT_SELECTED], true)
            || in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado'], true)
            || $entry >= 90
        ) {
            return 0;
        }

        $raw = max(0, $exit - $entry);
        if ($raw > 0) {
            return $raw;
        }

        // Somente contabiliza mínimo de 1 minuto quando houver evidência real de atuação em campo
        // no mesmo minuto da entrada (participated_on_field gravado pelo motor ou reserva que entrou
        // em entry > 0 e saiu por expulsão/substituição no mesmo minuto).
        $actedInField = !empty($m['participated_on_field'])
            || ($entry > 0 && $entry < 90 && $exit === $entry && in_array($state, [self::STATE_SENT_OFF, self::STATE_SUBSTITUTED_OUT], true));

        return $actedInField ? 1 : 0;
    }

    /**
     * Verifica se o atleta efetivamente permaneceu em campo até o término da partida
     * (elegibilidade para cobrança em disputa de pênaltis).
     */
    public static function finishedOnField(array $m): bool {
        $state = $m['participation_state'] ?? null;
        $lineup = (string)($m['lineup'] ?? '');
        $st = $m['stats'] ?? [];
        $exitReason = (string)($m['exit_reason'] ?? '');

        if ($state === self::STATE_SENT_OFF
            || $exitReason === 'sent_off'
            || !empty($m['red_card_minute'])
            || (int)($st['red_cards'] ?? 0) > 0
            || (int)($st['direct_red'] ?? 0) > 0
            || (int)($st['second_yellow'] ?? 0) > 0
            || (int)($st['cards_red'] ?? 0) > 0
            || (!array_key_exists('red_cards', $st) && (int)($st['cards'] ?? 0) >= 2)
        ) {
            return false;
        }

        if (in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado', 'substituído'], true)
            || in_array($exitReason, ['injury', 'fatigue', 'tactical', 'sent_off'], true)
            || !empty($m['substituido'])
        ) {
            return false;
        }

        $entry = isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : null;
        $exit = isset($m['exit']) && is_numeric($m['exit']) ? (int)$m['exit'] : null;

        if ($entry !== null && $entry >= 90) {
            return false;
        }
        if ($exit !== null && $exit > 0 && $exit < 90) {
            return false;
        }

        if (is_string($state) && $state !== '') {
            if (!in_array($state, [self::STATE_FULL_TIME, self::STATE_ON_FIELD], true)) {
                return false;
            }
            return $entry !== null && $entry < 90 && $exit !== null && $exit >= 90;
        }

        if ($entry === null || $exit === null) {
            return false;
        }
        if ($exit >= 90) {
            return $entry >= 0 && $entry < 90;
        }
        // Compatibilidade com saves/fixtures legados sem participation_state nem lineup
        if ($entry === 0 && $exit === 0 && $lineup === '' && ($m['status'] ?? '') === 'finalizada' && (int)($m['minute'] ?? 0) >= 90) {
            return true;
        }
        return false;
    }

    public static function isOnField(array $m): bool {
        return (($m['status'] ?? '') === 'em_andamento' &&
                $m['minute'] >= $m['entry'] &&
                $m['minute'] < $m['exit'] &&
                ($m['participation_state'] ?? self::STATE_ON_FIELD) === self::STATE_ON_FIELD);
    }
}

