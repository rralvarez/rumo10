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
    public const MAX_VALID_MINUTE = 105;

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
     * Verifica marcadores canônicos e legados de expulsão do protagonista.
     */
    public static function isSentOff(array $m): bool {
        $state = (string)($m['participation_state'] ?? '');
        $exitReason = (string)($m['exit_reason'] ?? '');
        $st = is_array($m['stats'] ?? null) ? $m['stats'] : [];

        return $state === self::STATE_SENT_OFF
            || $exitReason === 'sent_off'
            || !empty($m['red_card_minute'])
            || (int)($st['red_cards'] ?? 0) > 0
            || (int)($st['direct_red'] ?? 0) > 0
            || (int)($st['second_yellow'] ?? 0) > 0
            || (int)($st['cards_red'] ?? 0) > 0
            || (int)($st['yellow_cards'] ?? 0) >= 2
            || (!array_key_exists('red_cards', $st) && (int)($st['cards'] ?? 0) >= 2);
    }

    /**
     * Evidência positiva de participação efetiva (flags do motor ou estatísticas registradas).
     */
    private static function hasPositiveEvidence(array $m): bool {
        if (!empty($m['participated_on_field']) || !empty($m['entered_on_field']) || !empty($m['sub_in_recorded'])) {
            return true;
        }
        $st = is_array($m['stats'] ?? null) ? $m['stats'] : [];
        return (int)($st['minutes'] ?? 0) > 0
            || (int)($st['goals'] ?? 0) > 0
            || (int)($st['assists'] ?? 0) > 0
            || (int)($st['passes'] ?? 0) > 0
            || (int)($st['shots'] ?? 0) > 0
            || (int)($st['tackles'] ?? 0) > 0
            || (int)($st['fouls'] ?? 0) > 0;
    }

    /**
     * Registra imediatamente uma saída de campo (expulsão, lesão, desgaste ou tática)
     * de forma estritamente idempotente, preservando a primeira saída/expulsão confirmada.
     */
    public static function recordExit(array &$m, array &$g, int $minute, string $reason): void {
        $state = $m['participation_state'] ?? null;

        // Expulsão já registrada é imutável: preserva minuto, motivo e estado originais
        if ($state === self::STATE_SENT_OFF || ($m['exit_reason'] ?? '') === 'sent_off' || !empty($m['red_card_minute'])) {
            $m['participation_state'] = self::STATE_SENT_OFF;
            $m['exit_reason'] = 'sent_off';
            $m['team_reduced'] = true;
            if (empty($m['red_card_minute']) && isset($m['exit']) && is_numeric($m['exit']) && (int)$m['exit'] > 0) {
                $m['red_card_minute'] = (int)$m['exit'];
            }
            if ((!isset($m['exit']) || !is_numeric($m['exit']) || (int)$m['exit'] <= 0) && !empty($m['red_card_minute'])) {
                $m['exit'] = (int)$m['red_card_minute'];
            }
            return;
        }

        // Não sobrescrever partida encerrada ou outro estado terminal já consolidado
        if (($m['status'] ?? '') === 'finalizada'
            || in_array($state, [self::STATE_SUBSTITUTED_OUT, self::STATE_FULL_TIME, self::STATE_NOT_SELECTED, self::STATE_SUSPENDED], true)
        ) {
            return;
        }

        if ($reason === 'sent_off') {
            $m['exit'] = $minute;
            $m['participation_state'] = self::STATE_SENT_OFF;
            $m['exit_reason'] = 'sent_off';
            $m['team_reduced'] = true;
            $m['red_card_minute'] = $minute;
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
        $hadCanonicalState = is_string($state) && $state !== '';
        if (!$hadCanonicalState) {
            // Reconciliação legada
            if (self::isSentOff($m)) $state = self::STATE_SENT_OFF;
            elseif (($m['lineup'] ?? '') === 'não relacionado' || ($m['lineup'] ?? '') === 'nao_relacionado') $state = self::STATE_NOT_SELECTED;
            elseif (($m['lineup'] ?? '') === 'suspenso') $state = self::STATE_SUSPENDED;
            elseif (isset($m['exit']) && is_numeric($m['exit']) && (int)$m['exit'] > 0 && (int)$m['exit'] < 90 && $minute >= (int)$m['exit']) $state = self::STATE_SUBSTITUTED_OUT;
            elseif (in_array($m['lineup'] ?? '', ['reserva', 'banco'], true)) {
                $entered = !empty($m['sub_in_recorded']) || !empty($m['entered_on_field']) || !empty($m['participated_on_field']);
                $state = ($entered && $minute >= ($m['entry'] ?? 90) && $minute < ($m['exit'] ?? 90)) ? self::STATE_ON_FIELD : self::STATE_ON_BENCH;
            }
            else $state = ($minute >= ($m['entry'] ?? 0) && $minute < ($m['exit'] ?? 90)) ? self::STATE_ON_FIELD : self::STATE_STARTING;
        }

        // Estados terminais imutáveis: expulsos, substituídos, ausentes e encerrados não retornam a campo
        if (in_array($state, [self::STATE_NOT_SELECTED, self::STATE_SUSPENDED, self::STATE_SENT_OFF, self::STATE_SUBSTITUTED_OUT, self::STATE_FULL_TIME], true)) {
            $m['participation_state'] = $state;
            return;
        }

        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $entry = isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : 0;
        $exit = isset($m['exit']) && is_numeric($m['exit']) ? (int)$m['exit'] : 90;
        $validWindow = ($entry >= 0 && $entry < 90 && $exit > $entry && $exit <= self::MAX_VALID_MINUTE);

        // Checagem de entrada em campo do banco (apenas com partida em curso e janela válida)
        if (!$isFinished && $state === self::STATE_ON_BENCH && $validWindow && $minute >= $entry && $minute < $exit) {
            $m['participation_state'] = self::STATE_ON_FIELD;
            $m['entered_on_field'] = true;
            if (empty($m['sub_in_recorded'])) {
                $m['sub_in_recorded'] = true;
                $m['log'][] = ['minute' => $entry, 'text' => "{$entry}' | Você entra em campo no lugar de um companheiro."];
                Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_IN', $entry);
            }
            return;
        }

        // Checagem de saída por substituição (idempotente)
        if ($state === self::STATE_ON_FIELD && $exit > 0 && $exit < 90 && $minute >= $exit) {
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
            if ($state === self::STATE_ON_FIELD) {
                $m['participation_state'] = self::STATE_FULL_TIME;
                $m['entered_on_field'] = true;
            } elseif ($state === self::STATE_STARTING && !$hadCanonicalState && $validWindow && $exit >= 90) {
                // Reconciliação legada para saves/fixtures sem participation_state que encerram com janela titular válida
                $m['participation_state'] = self::STATE_FULL_TIME;
                $m['entered_on_field'] = true;
            } elseif ($state === self::STATE_ON_BENCH) {
                $m['participation_state'] = self::STATE_ON_BENCH;
                if (empty($m['unused_bench_logged'])) {
                    $m['unused_bench_logged'] = true;
                    $m['log'][] = ['minute' => max(90, $minute), 'text' => 'Você permaneceu no banco e não entrou em campo.'];
                }
            }
            return;
        }

        if ($state === self::STATE_STARTING && $validWindow && $minute >= $entry && $minute < $exit) {
            $m['participation_state'] = self::STATE_ON_FIELD;
            $m['entered_on_field'] = true;
        }
    }

    /**
     * Calcula minutos estatísticos efetivamente disputados sem adulterar entry/exit.
     * Distingue escalação planejada de participação efetiva, valida limites temporais
     * e exige evidência positiva para o piso estatístico de 1 minuto em saídas imediatas.
     */
    public static function playedMinutes(array $m): int {
        if (in_array($m['status'] ?? '', ['agendada', 'pre_jogo'], true)) {
            return 0;
        }

        $state = $m['participation_state'] ?? null;
        $lineup = (string)($m['lineup'] ?? '');

        // Estados e escalações sem entrada efetiva em campo nunca geram minutagem
        if (in_array($state, [self::STATE_SUSPENDED, self::STATE_NOT_SELECTED, self::STATE_ON_BENCH, self::STATE_STARTING], true)
            || in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado'], true)
        ) {
            return 0;
        }

        $hasCanonicalState = is_string($state) && $state !== '';
        if ($hasCanonicalState
            && !in_array($state, [self::STATE_ON_FIELD, self::STATE_FULL_TIME, self::STATE_SUBSTITUTED_OUT, self::STATE_SENT_OFF], true)
        ) {
            return 0;
        }

        if (!isset($m['entry'], $m['exit']) || !is_numeric($m['entry']) || !is_numeric($m['exit'])) {
            return 0;
        }

        $entry = (int)$m['entry'];
        $exit = (int)$m['exit'];
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;

        // Validação estrita de limites temporais: não fabrica minutos em intervalos inválidos
        if ($entry < 0 || $entry >= 90 || $exit < $entry || $exit > self::MAX_VALID_MINUTE) {
            return 0;
        }
        if ($exit > 90 && ($matchMinute === null || $matchMinute < $exit)) {
            return 0;
        }

        // Save legado de reserva sem estado canônico: janela planejada sem evidência de entrada não gera minutos
        if (!$hasCanonicalState && in_array($lineup, ['reserva', 'banco'], true) && !self::hasPositiveEvidence($m)) {
            return 0;
        }

        $raw = $exit - $entry;
        if ($raw > 0) {
            // Estado terminal isolado sem qualquer evidência coerente de participação não fabrica minutos
            if ($hasCanonicalState
                && in_array($state, [self::STATE_SUBSTITUTED_OUT, self::STATE_SENT_OFF], true)
                && in_array($lineup, ['reserva', 'banco'], true)
                && !self::hasPositiveEvidence($m)
                && empty($m['sub_out_recorded'])
                && empty($m['exit_reason'])
                && empty($m['red_card_minute'])
            ) {
                return 0;
            }
            return $raw;
        }

        // Piso estatístico de 1 minuto exclusivamente quando há evidência positiva de entrada/atuação real
        // no mesmo minuto da entrada (estados terminais isolados sem evidência retornam 0).
        $actedInField = !empty($m['participated_on_field'])
            || !empty($m['entered_on_field'])
            || !empty($m['sub_in_recorded']);

        return $actedInField ? 1 : 0;
    }

    /**
     * Verifica se o atleta efetivamente permaneceu em campo até o término da partida
     * (elegibilidade para cobrança em disputa de pênaltis).
     * Exige evidência positiva e consistente de participação até o apito final.
     */
    public static function finishedOnField(array $m): bool {
        if (($m['status'] ?? '') !== 'finalizada') {
            return false;
        }

        if (self::isSentOff($m)) {
            return false;
        }

        $lineup = (string)($m['lineup'] ?? '');
        $exitReason = (string)($m['exit_reason'] ?? '');

        if (in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado', 'substituído'], true)
            || in_array($exitReason, ['injury', 'fatigue', 'tactical', 'sent_off'], true)
            || !empty($m['substituido'])
        ) {
            return false;
        }

        if (!isset($m['entry'], $m['exit']) || !is_numeric($m['entry']) || !is_numeric($m['exit'])) {
            return false;
        }

        $entry = (int)$m['entry'];
        $exit = (int)$m['exit'];
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;

        if ($entry < 0 || $entry >= 90 || $exit < 90 || $exit > self::MAX_VALID_MINUTE) {
            return false;
        }
        if ($exit > 90 && ($matchMinute === null || $matchMinute < $exit)) {
            return false;
        }

        $state = $m['participation_state'] ?? null;
        if (is_string($state) && $state !== '') {
            if (!in_array($state, [self::STATE_FULL_TIME, self::STATE_ON_FIELD], true)) {
                return false;
            }
            return self::hasPositiveEvidence($m);
        }

        // Save legado sem participation_state: exige escalação válida (titular/reserva) e evidência positiva
        if (!in_array($lineup, ['titular', 'reserva'], true)) {
            return false;
        }

        return self::hasPositiveEvidence($m);
    }

    /**
     * Decisão canônica única que autoriza atuação esportiva do protagonista em campo.
     * Considera conjuntamente status, estado canônico, evidência de entrada, ausência de saída/expulsão
     * e compatibilidade temporal. Nunca promove automaticamente ON_BENCH para ON_FIELD.
     */
    public static function canActOnField(array $m): bool {
        if (($m['status'] ?? '') !== 'em_andamento') {
            return false;
        }

        if (self::isSentOff($m)) {
            return false;
        }

        $exitReason = (string)($m['exit_reason'] ?? '');
        if (in_array($exitReason, ['injury', 'fatigue', 'tactical', 'sent_off'], true) || !empty($m['substituido'])) {
            return false;
        }

        $lineup = (string)($m['lineup'] ?? '');
        if (in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado', 'substituído'], true)) {
            return false;
        }

        if (!isset($m['minute'], $m['entry'], $m['exit'])
            || !is_numeric($m['minute'])
            || !is_numeric($m['entry'])
            || !is_numeric($m['exit'])
        ) {
            return false;
        }

        $minute = (int)$m['minute'];
        $entry = (int)$m['entry'];
        $exit = (int)$m['exit'];

        if ($entry < 0 || $entry >= 90 || $exit <= $entry || $exit > self::MAX_VALID_MINUTE) {
            return false;
        }

        if ($minute < $entry || $minute >= $exit) {
            return false;
        }

        $state = $m['participation_state'] ?? null;
        if (is_string($state) && $state !== '') {
            return $state === self::STATE_ON_FIELD;
        }

        // Reconciliação explícita para saves legados sem participation_state:
        // reserva precisa de evidência explícita de entrada; titular/legado sem estado usa janela válida.
        if (in_array($lineup, ['reserva', 'banco'], true)) {
            return !empty($m['sub_in_recorded'])
                || !empty($m['entered_on_field'])
                || !empty($m['participated_on_field']);
        }

        return true;
    }

    public static function isOnField(array $m): bool {
        return self::canActOnField($m);
    }
}

