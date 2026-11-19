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
     * Evidência em log de entrada em campo vinda do banco.
     */
    private static function hasSubInLog(array $m): bool {
        foreach (($m['log'] ?? []) as $entry) {
            if (str_contains((string)($entry['text'] ?? ''), 'Você entra em campo')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evidência em log de saída/expulsão do protagonista.
     */
    private static function hasExitLog(array $m): bool {
        foreach (($m['log'] ?? []) as $entry) {
            $t = (string)($entry['text'] ?? '');
            if (str_contains($t, 'Lesão: você deixa o campo.')
                || str_contains($t, 'Substituído por desgaste.')
                || str_contains($t, 'Você é substituído')
                || str_contains($t, 'Expulso: segundo cartão.')
                || str_contains($t, 'Você foi expulso da partida')
                || str_contains($t, 'Você está expulso da partida')
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evidência explícita de entrada efetiva em campo produzida por transição legítima
     * ou por ação já autorizada (nunca por estatística isolada ou janela planejada).
     */
    private static function hasEntryEvidence(array $m): bool {
        return !empty($m['entered_on_field'])
            || !empty($m['sub_in_recorded'])
            || !empty($m['participated_on_field'])
            || self::hasSubInLog($m);
    }

    /**
     * Verifica marcadores estruturados ou legados de saída de campo (substituição, lesão, desgaste ou expulsão).
     */
    private static function hasExitMarker(array $m): bool {
        $exitReason = (string)($m['exit_reason'] ?? '');
        $lineup = (string)($m['lineup'] ?? '');
        return in_array($exitReason, ['injury', 'fatigue', 'tactical', 'sent_off'], true)
            || !empty($m['substituido'])
            || !empty($m['sub_out_recorded'])
            || $lineup === 'substituído'
            || self::hasExitLog($m);
    }

    /**
     * Registra imediatamente uma saída de campo (expulsão, lesão, desgaste ou tática)
     * de forma estritamente idempotente, preservando a primeira saída/expulsão confirmada
     * (inclusive red_card_minute = 0 explicitamente persistido, sem inferir a partir de exit).
     */
    public static function recordExit(array &$m, array &$g, int $minute, string $reason): void {
        $state = $m['participation_state'] ?? null;
        $hasRedMinuteKey = array_key_exists('red_card_minute', $m) && $m['red_card_minute'] !== null;
        $hasPositiveRedMinute = $hasRedMinuteKey && is_numeric($m['red_card_minute']) && (int)$m['red_card_minute'] > 0;

        // Expulsão já registrada é imutável: preserva minuto (inclusive 0), motivo e estado originais
        if ($state === self::STATE_SENT_OFF || ($m['exit_reason'] ?? '') === 'sent_off' || $hasPositiveRedMinute) {
            $m['participation_state'] = self::STATE_SENT_OFF;
            $m['exit_reason'] = 'sent_off';
            $m['team_reduced'] = true;
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
            if (!$hasRedMinuteKey) {
                $m['red_card_minute'] = $minute;
            }
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
     * Atualiza o estado de participação ao transitar minutos/segmentos (idempotente).
     * Não utiliza fallback permissivo para escalações desconhecidas e nunca fabrica
     * entered_on_field=true durante o encerramento a partir de janela temporal.
     */
    public static function sync(array &$m, array &$g, int $minute): void {
        $state = $m['participation_state'] ?? null;
        $hadCanonicalState = is_string($state) && $state !== '';
        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $lineup = (string)($m['lineup'] ?? '');
        $entry = isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : null;
        $exit = isset($m['exit']) && is_numeric($m['exit']) ? (int)$m['exit'] : null;
        $validWindow = ($entry !== null && $exit !== null && $entry >= 0 && $entry < 90 && $exit > $entry && $exit <= self::MAX_VALID_MINUTE);

        if (!$hadCanonicalState) {
            // Reconciliação legada estrita: exige marcadores explícitos ou escalação reconhecida.
            // Evidências de entrada comprovada são avaliadas antes de classificar reserva como não utilizado.
            if (self::isSentOff($m)) {
                $state = self::STATE_SENT_OFF;
            } elseif (in_array($lineup, ['não relacionado', 'nao_relacionado'], true)) {
                $state = self::STATE_NOT_SELECTED;
            } elseif ($lineup === 'suspenso') {
                $state = self::STATE_SUSPENDED;
            } elseif (self::hasExitMarker($m) || ($exit !== null && $exit > 0 && $exit < 90 && $minute >= $exit && in_array($lineup, ['titular', 'reserva', 'banco'], true))) {
                $state = self::STATE_SUBSTITUTED_OUT;
            } elseif (in_array($lineup, ['reserva', 'banco'], true)) {
                if (self::hasEntryEvidence($m) && $validWindow) {
                    $state = ($minute >= $entry && ($minute < $exit || ($isFinished && $exit >= 90)))
                        ? self::STATE_ON_FIELD
                        : self::STATE_ON_BENCH;
                } else {
                    $state = self::STATE_ON_BENCH;
                }
            } elseif ($lineup === 'titular') {
                if ($validWindow && $minute >= $entry && $minute < $exit) {
                    $state = self::STATE_ON_FIELD;
                } elseif (self::hasEntryEvidence($m) && $validWindow && $isFinished && $exit >= 90) {
                    $state = self::STATE_ON_FIELD;
                } else {
                    $state = self::STATE_STARTING;
                }
            } else {
                // Escalação desconhecida/ausente sem estado canônico: preserva dados sem fabricar participação
                return;
            }
        }

        // Estados terminais imutáveis: expulsos, substituídos, ausentes e encerrados não retornam a campo
        if (in_array($state, [self::STATE_NOT_SELECTED, self::STATE_SUSPENDED, self::STATE_SENT_OFF, self::STATE_SUBSTITUTED_OUT, self::STATE_FULL_TIME], true)) {
            $m['participation_state'] = $state;
            return;
        }

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
        if ($state === self::STATE_ON_FIELD && $exit !== null && $exit > 0 && $exit < 90 && $minute >= $exit) {
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

        // Encerramento efetivo da partida: FULL_TIME vinculado ao status 'finalizada' apenas para quem já estava ON_FIELD
        // (nunca fabrica entered_on_field=true retroativamente no encerramento)
        if ($isFinished) {
            if ($state === self::STATE_ON_FIELD) {
                $m['participation_state'] = self::STATE_FULL_TIME;
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
     * Distingue escalação planejada, entrada efetiva, tempo disputado e encerramento.
     * Rejeita escalações desconhecidas sem estado canônico e intervalos inválidos.
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

        // Save legado sem estado canônico: rejeita escalação desconhecida e exige entrada efetiva para reserva
        if (!$hasCanonicalState) {
            if (!in_array($lineup, ['titular', 'reserva', 'banco'], true)) {
                return 0;
            }
            if (in_array($lineup, ['reserva', 'banco'], true) && !self::hasEntryEvidence($m)) {
                return 0;
            }
        }

        $isExpelled = self::isSentOff($m) || $state === self::STATE_SENT_OFF;
        $isSubstitutedOrInjured = !$isExpelled && ($state === self::STATE_SUBSTITUTED_OUT || self::hasExitMarker($m));

        // Saída confirmada impede presumir automaticamente o horário planejado (90') como horário real de saída
        if ($isExpelled || $isSubstitutedOrInjured) {
            // Entrada precisa estar demonstrada (titular com entry=0 ou evidência explícita de entrada)
            $entryDemonstrated = ($lineup === 'titular' && $entry === 0) || self::hasEntryEvidence($m);
            if (!$entryDemonstrated) {
                return 0;
            }

            if ($isExpelled) {
                $hasRedMinuteKey = array_key_exists('red_card_minute', $m) && $m['red_card_minute'] !== null;
                if ($hasRedMinuteKey) {
                    if (!is_numeric($m['red_card_minute'])) {
                        return 0;
                    }
                    $redMin = (int)$m['red_card_minute'];
                    if ($redMin <= 0 || $redMin < $entry || $redMin > self::MAX_VALID_MINUTE) {
                        return 0;
                    }
                    if ($redMin > 90 && ($matchMinute === null || $matchMinute < $redMin)) {
                        return 0;
                    }
                    // Contradição entre minuto de saída anterior (< 90) e minuto de expulsão distinto
                    if ($exit < 90 && $exit !== $redMin) {
                        return 0;
                    }
                    $effectiveExit = $redMin;
                } elseif ($exit > 0 && $exit < 90) {
                    $effectiveExit = $exit;
                } else {
                    // Expulsão sem minuto confiável e exit planejado (>= 90): não presume 90 minutos
                    return 0;
                }
            } else {
                if ($exit > 0 && $exit < 90) {
                    $effectiveExit = $exit;
                } elseif ($state === self::STATE_SUBSTITUTED_OUT
                    && !empty($m['sub_out_recorded'])
                    && self::hasEntryEvidence($m)
                    && $matchMinute !== null
                    && $matchMinute >= $exit
                ) {
                    // Substituição canônica comprovadamente registrada no próprio minuto >= 90
                    $effectiveExit = $exit;
                } else {
                    // Saída (ex.: lesão/desgaste) com horário planejado (>= 90) sem registro confiável de saída
                    return 0;
                }
            }

            $raw = $effectiveExit - $entry;
            if ($raw > 0) {
                return $raw;
            }
            return self::hasEntryEvidence($m) ? 1 : 0;
        }

        $raw = $exit - $entry;
        if ($raw > 0) {
            return $raw;
        }

        // Piso estatístico de 1 minuto exclusivamente quando há evidência explícita de entrada/atuação real
        // no mesmo minuto da entrada (estados terminais isolados sem evidência retornam 0).
        return self::hasEntryEvidence($m) ? 1 : 0;
    }

    /**
     * Verifica se o atleta efetivamente permaneceu em campo até o término da partida
     * (elegibilidade para cobrança em disputa de pênaltis).
     * Exige entrada comprovada e permanência até o encerramento; nunca aceita estatística isolada
     * ou apenas stats.minutes > 0 como prova de permanência final.
     */
    public static function finishedOnField(array $m): bool {
        if (($m['status'] ?? '') !== 'finalizada') {
            return false;
        }

        if (self::isSentOff($m) || self::hasExitMarker($m)) {
            return false;
        }

        $lineup = (string)($m['lineup'] ?? '');
        if (in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado', 'substituído'], true)) {
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

        $st = is_array($m['stats'] ?? null) ? $m['stats'] : [];
        $recordedMinutes = (int)($st['minutes'] ?? 0);
        $expectedMinutes = $exit - $entry;
        $state = $m['participation_state'] ?? null;

        if (is_string($state) && $state !== '') {
            // Em partida finalizada com estado canônico, apenas FULL_TIME comprova permanência no apito final
            if ($state !== self::STATE_FULL_TIME) {
                return false;
            }
            if (self::hasEntryEvidence($m)) {
                return true;
            }
            // Compatibilidade conservadora com registros FULL_TIME sem flag de entrada, exigindo escalação e minutagem + nota coerentes
            return in_array($lineup, ['titular', 'reserva'], true)
                && ($lineup === 'titular' ? $entry === 0 : $entry > 0)
                && $recordedMinutes === $expectedMinutes
                && (float)($st['rating'] ?? 0) > 0;
        }

        // Save legado sem participation_state: comportamento conservador, nunca aceita escalação desconhecida
        // nem estatística isolada (ex.: fouls=1) ou stats.minutes > 0 isolado.
        if ($lineup === 'reserva') {
            return self::hasEntryEvidence($m)
                && $entry > 0
                && $recordedMinutes === $expectedMinutes;
        }

        if ($lineup === 'titular') {
            return $entry === 0
                && $recordedMinutes === $expectedMinutes
                && (self::hasEntryEvidence($m) || (float)($st['rating'] ?? 0) > 0);
        }

        return false;
    }

    /**
     * Decisão canônica única que autoriza atuação esportiva do protagonista em campo.
     * Considera conjuntamente status, estado canônico, escalação reconhecida, evidência de entrada,
     * ausência de saída/expulsão e compatibilidade temporal (incluindo acréscimos em STATE_ON_FIELD).
     * Nunca utiliza fallback permissivo para escalações desconhecidas nem promove ON_BENCH para ON_FIELD.
     */
    public static function canActOnField(array $m): bool {
        if (($m['status'] ?? '') !== 'em_andamento') {
            return false;
        }

        if (self::isSentOff($m) || self::hasExitMarker($m)) {
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

        // Elegibilidade esportiva estrita: nunca autoriza decisão quando minute >= exit (inclusive acréscimos)
        if ($minute < $entry || $minute >= $exit) {
            return false;
        }

        $state = $m['participation_state'] ?? null;
        $hasCanonicalState = is_string($state) && $state !== '';

        if ($hasCanonicalState) {
            return $state === self::STATE_ON_FIELD;
        }

        // Reconciliação explícita para saves legados sem participation_state:
        // exige escalação explicitamente reconhecida (titular ou reserva com entrada comprovada).
        if ($lineup === 'titular') {
            return true;
        }
        if (in_array($lineup, ['reserva', 'banco'], true)) {
            return self::hasEntryEvidence($m);
        }

        return false;
    }

    public static function isOnField(array $m): bool {
        return self::canActOnField($m);
    }
}

