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
     * Interpreta uma linha de log segundo a gramática estrita dos eventos formais de saída
     * efetivamente emitidos pelo motor (MatchParticipation, MatchDiscipline e Game).
     * Rejeita perguntas, hipóteses, comentários narrativos, referências a terceiros e fragmentos parciais.
     *
     * @return array{kind:string,text_minute:?int,text_minute_conflict:bool,cause_conflict:bool}|null
     */
    private static function parseFormalExitLogText(string $text, bool $allowCompound = true): ?array {
        $t = trim($text);
        if ($t === '') {
            return null;
        }

        // 1. Substituição tática formal (MatchParticipation::sync) — sempre evento isolado
        if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?Você é substituído após participar da partida\.$/u', $t, $m) === 1) {
            return [
                'kind' => 'tactical',
                'text_minute' => ($m[1] ?? '') !== '' ? (int)$m[1] : null,
                'text_minute_conflict' => false,
                'cause_conflict' => false,
            ];
        }

        // 2. Cartão vermelho direto formal (MatchDiscipline::recordFoul) — isolado ou concatenado em Game::fieldDecision
        if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?Cartão vermelho direto! Você foi expulso da partida\.$/u', $t, $m) === 1) {
            return [
                'kind' => 'sent_off',
                'text_minute' => ($m[1] ?? '') !== '' ? (int)$m[1] : null,
                'text_minute_conflict' => false,
                'cause_conflict' => false,
            ];
        }
        if ($allowCompound && preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?(.+?[.!])\s+(\d{1,3})\s*[\'\x{2032}]\s*\|\s*Cartão vermelho direto! Você foi expulso da partida\.$/u', $t, $m) === 1) {
            return self::buildCompoundExitResult('sent_off', $m[1], $m[3], $m[2]);
        }

        // 3. Segundo cartão amarelo formal (MatchDiscipline::recordFoul) — isolado ou concatenado em Game::fieldDecision
        if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?Segundo cartão amarelo! Você está expulso da partida\.$/u', $t, $m) === 1) {
            return [
                'kind' => 'sent_off',
                'text_minute' => ($m[1] ?? '') !== '' ? (int)$m[1] : null,
                'text_minute_conflict' => false,
                'cause_conflict' => false,
            ];
        }
        if ($allowCompound && preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?(.+?[.!])\s+(\d{1,3})\s*[\'\x{2032}]\s*\|\s*Segundo cartão amarelo! Você está expulso da partida\.$/u', $t, $m) === 1) {
            return self::buildCompoundExitResult('sent_off', $m[1], $m[3], $m[2]);
        }

        // 4. Formatos formais de sufixo em Game::fieldDecision (lesão, desgaste, segundo cartão legado)
        $suffixPatterns = [
            'sent_off' => 'Expulso: segundo cartão\.',
            'injury' => 'Lesão: você deixa o campo\.',
            'fatigue' => 'Substituído por desgaste\.',
        ];
        foreach ($suffixPatterns as $kind => $literalPattern) {
            if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?' . $literalPattern . '$/u', $t, $m) === 1) {
                return [
                    'kind' => $kind,
                    'text_minute' => ($m[1] ?? '') !== '' ? (int)$m[1] : null,
                    'text_minute_conflict' => false,
                    'cause_conflict' => false,
                ];
            }
            if ($allowCompound && preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?(.+?[.!])\s+(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?' . $literalPattern . '$/u', $t, $m) === 1) {
                return self::buildCompoundExitResult($kind, $m[1], $m[3] ?? '', $m[2]);
            }
        }

        return null;
    }

    /**
     * Consolida minuto e eventuais conflitos em registro composto (relato da jogada + evento formal de saída).
     */
    private static function buildCompoundExitResult(string $kind, string $leadMinRaw, string $eventMinRaw, string $prefix): array {
        $leadMin = $leadMinRaw !== '' ? (int)$leadMinRaw : null;
        $eventMin = $eventMinRaw !== '' ? (int)$eventMinRaw : null;
        $minConflict = ($leadMin !== null && $eventMin !== null && $leadMin !== $eventMin);
        $resolvedMin = $minConflict ? null : ($eventMin ?? $leadMin);
        $causeConflict = false;

        // Detecta duplo evento formal incompatível concatenado na mesma linha de log (sem recursão profunda)
        $prefixEvent = self::parseFormalExitLogText($prefix, false);
        if ($prefixEvent !== null) {
            if ($prefixEvent['kind'] !== $kind) {
                $causeConflict = true;
            }
            if ($prefixEvent['text_minute'] !== null && $resolvedMin !== null && $prefixEvent['text_minute'] !== $resolvedMin) {
                $minConflict = true;
                $resolvedMin = null;
            }
        }

        return [
            'kind' => $kind,
            'text_minute' => $resolvedMin,
            'text_minute_conflict' => $minConflict,
            'cause_conflict' => $causeConflict,
        ];
    }

    /**
     * Evidência formal em log de entrada em campo vinda do banco (MatchParticipation::sync).
     * Rejeita menções hipotéticas, perguntas ou fragmentos fora do formato oficial.
     */
    private static function hasSubInLog(array $m): bool {
        foreach (($m['log'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $t = trim((string)($entry['text'] ?? ''));
            if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?Você entra em campo no lugar de um companheiro\.$/u', $t, $matches) !== 1) {
                continue;
            }
            $hasEntryMin = array_key_exists('minute', $entry) && $entry['minute'] !== null && is_numeric($entry['minute']);
            $entryMin = $hasEntryMin ? (int)$entry['minute'] : null;
            $textMin = ($matches[1] ?? '') !== '' ? (int)$matches[1] : null;
            if ($entryMin !== null && $textMin !== null && $entryMin !== $textMin) {
                continue;
            }
            $resolvedMin = $entryMin ?? $textMin;
            if ($resolvedMin !== null && ($resolvedMin < 0 || $resolvedMin > self::MAX_VALID_MINUTE)) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Extrai evento formal e verificável de saída/expulsão do protagonista a partir dos logs históricos.
     * Política conservadora: aceita apenas formatos formais completos emitidos pelo motor,
     * valida limites temporais, trata duplicatas coerentes de forma idempotente e sinaliza
     * explicitamente conflitos de causa ou horário.
     */
    private static function extractLogExitEvent(array $m): array {
        $result = [
            'found' => false,
            'kind' => null,
            'minute' => null,
            'conflict' => false,
            'cause_conflict' => false,
            'time_conflict' => false,
        ];
        if (!isset($m['log']) || !is_array($m['log'])) {
            return $result;
        }

        $kindsSeen = [];
        $minutesSeen = [];

        foreach ($m['log'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $parsed = self::parseFormalExitLogText((string)($entry['text'] ?? ''));
            if ($parsed === null) {
                continue;
            }

            $result['found'] = true;
            $kindsSeen[$parsed['kind']] = true;
            if ($parsed['cause_conflict']) {
                $result['cause_conflict'] = true;
            }
            if ($parsed['text_minute_conflict']) {
                $result['time_conflict'] = true;
            }

            $hasEntryMinKey = array_key_exists('minute', $entry) && $entry['minute'] !== null;
            $entryMin = null;
            if ($hasEntryMinKey) {
                if (!is_numeric($entry['minute'])) {
                    $result['time_conflict'] = true;
                    continue;
                }
                $entryMin = (int)$entry['minute'];
                if ($entryMin <= 0 || $entryMin > self::MAX_VALID_MINUTE) {
                    $result['time_conflict'] = true;
                    continue;
                }
            }

            $textMin = $parsed['text_minute'];
            if ($textMin !== null && ($textMin <= 0 || $textMin > self::MAX_VALID_MINUTE)) {
                $result['time_conflict'] = true;
                continue;
            }

            if ($entryMin !== null && $textMin !== null && $entryMin !== $textMin) {
                $result['time_conflict'] = true;
                continue;
            }

            $resolvedMin = $entryMin ?? $textMin;
            if ($resolvedMin !== null) {
                $minutesSeen[$resolvedMin] = true;
            }
        }

        if (count($kindsSeen) > 1) {
            $result['cause_conflict'] = true;
        } elseif (count($kindsSeen) === 1 && !$result['cause_conflict']) {
            $result['kind'] = (string)array_key_first($kindsSeen);
        }

        if (count($minutesSeen) > 1) {
            $result['time_conflict'] = true;
        } elseif (count($minutesSeen) === 1 && !$result['time_conflict']) {
            $result['minute'] = (int)array_key_first($minutesSeen);
        }

        $result['conflict'] = $result['cause_conflict'] || $result['time_conflict'];
        return $result;
    }

    /**
     * Reconciliação canônica única de saída de campo e conflitos entre dados estruturados e logs formais.
     * Fonte única de verdade consumida por motor, disciplina, narrativa e interface (sem recursão).
     *
     * @return array{status:string,cause_conflict:bool,time_conflict:bool,kind:?string,minute:?int}
     */
    public static function resolveExit(array $m): array {
        $state = (string)($m['participation_state'] ?? '');
        $exitReason = (string)($m['exit_reason'] ?? '');
        $lineup = (string)($m['lineup'] ?? '');
        $st = is_array($m['stats'] ?? null) ? $m['stats'] : [];

        $hasStructuredExpulsion = $state === self::STATE_SENT_OFF
            || $exitReason === 'sent_off'
            || !empty($m['red_card_minute'])
            || (int)($st['red_cards'] ?? 0) > 0
            || (int)($st['direct_red'] ?? 0) > 0
            || (int)($st['second_yellow'] ?? 0) > 0
            || (int)($st['cards_red'] ?? 0) > 0
            || (int)($st['yellow_cards'] ?? 0) >= 2
            || (!array_key_exists('red_cards', $st) && (int)($st['cards'] ?? 0) >= 2);

        $structuredSubKind = in_array($exitReason, ['injury', 'fatigue', 'tactical'], true) ? $exitReason : null;
        $hasStructuredSub = $state === self::STATE_SUBSTITUTED_OUT
            || $structuredSubKind !== null
            || !empty($m['substituido'])
            || !empty($m['sub_out_recorded'])
            || $lineup === 'substituído';

        $logExit = self::extractLogExitEvent($m);

        if (!$hasStructuredExpulsion && !$hasStructuredSub && !$logExit['found']) {
            return [
                'status' => 'none',
                'cause_conflict' => false,
                'time_conflict' => false,
                'kind' => null,
                'minute' => null,
            ];
        }

        $causeConflict = $logExit['cause_conflict']
            || ($hasStructuredExpulsion && $hasStructuredSub)
            || ($hasStructuredExpulsion && $logExit['found'] && $logExit['kind'] !== 'sent_off')
            || ($hasStructuredSub && $logExit['found'] && $logExit['kind'] === 'sent_off')
            || ($structuredSubKind !== null && $logExit['found'] && $logExit['kind'] !== null && $logExit['kind'] !== $structuredSubKind);

        $kind = null;
        if (!$causeConflict) {
            if ($hasStructuredExpulsion || $logExit['kind'] === 'sent_off') {
                $kind = 'sent_off';
            } elseif ($structuredSubKind !== null) {
                $kind = $structuredSubKind;
            } elseif ($logExit['kind'] !== null) {
                $kind = $logExit['kind'];
            }
        }

        $timeConflict = $logExit['time_conflict'];
        $entry = isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : null;
        $exit = isset($m['exit']) && is_numeric($m['exit']) ? (int)$m['exit'] : null;
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;
        $logMin = $logExit['minute'];

        if ($logMin !== null) {
            if ($entry !== null && $logMin < $entry) {
                $timeConflict = true;
            } elseif ($logMin > 90 && ($matchMinute === null || $matchMinute < $logMin)) {
                $timeConflict = true;
            }
        }

        $structuredExit = null;
        if (!$causeConflict) {
            if ($kind === 'sent_off') {
                $hasRedMinuteKey = array_key_exists('red_card_minute', $m) && $m['red_card_minute'] !== null;
                if ($hasRedMinuteKey) {
                    if (!is_numeric($m['red_card_minute'])) {
                        $timeConflict = true;
                    } else {
                        $redMin = (int)$m['red_card_minute'];
                        if ($redMin <= 0 || $redMin > self::MAX_VALID_MINUTE || ($entry !== null && $redMin < $entry)) {
                            $timeConflict = true;
                        } elseif ($redMin > 90 && ($matchMinute === null || $matchMinute < $redMin)) {
                            $timeConflict = true;
                        } elseif ($exit !== null && $exit > 0 && $exit < 90 && $exit !== $redMin) {
                            $timeConflict = true;
                        } else {
                            $structuredExit = $redMin;
                        }
                    }
                } elseif ($exit !== null) {
                    if ($exit < 0 || $exit > self::MAX_VALID_MINUTE || ($entry !== null && $exit < $entry)) {
                        $timeConflict = true;
                    } elseif ($exit > 0 && $exit < 90) {
                        $structuredExit = $exit;
                    }
                }
            } else {
                if ($exit !== null) {
                    if ($exit < 0 || $exit > self::MAX_VALID_MINUTE || ($entry !== null && $exit < $entry)) {
                        $timeConflict = true;
                    } elseif ($exit > 0 && $exit < 90) {
                        $structuredExit = $exit;
                    } elseif ($logMin === null
                        && !$timeConflict
                        && $state === self::STATE_SUBSTITUTED_OUT
                        && !empty($m['sub_out_recorded'])
                        && self::hasEntryEvidence($m)
                        && $matchMinute !== null
                        && $matchMinute >= $exit
                    ) {
                        $structuredExit = $exit;
                    }
                }
            }
        }

        $resolvedMin = null;
        if (!$causeConflict && !$timeConflict) {
            if ($structuredExit !== null && $logMin !== null) {
                if ($structuredExit !== $logMin) {
                    $timeConflict = true;
                } else {
                    $resolvedMin = $structuredExit;
                }
            } elseif ($structuredExit !== null) {
                $resolvedMin = $structuredExit;
            } elseif ($logMin !== null) {
                $resolvedMin = $logMin;
            }
        }

        return [
            'status' => ($causeConflict || $timeConflict) ? 'conflict' : 'valid',
            'cause_conflict' => $causeConflict,
            'time_conflict' => $timeConflict,
            'kind' => $causeConflict ? null : $kind,
            'minute' => ($causeConflict || $timeConflict) ? null : $resolvedMin,
        ];
    }

    /**
     * Verifica marcadores canônicos e legados de expulsão do protagonista de forma consistente
     * com a reconciliação canônica (retorna false em conflito de causa irreconciliável).
     */
    public static function isSentOff(array $m): bool {
        $resolved = self::resolveExit($m);
        return !$resolved['cause_conflict'] && $resolved['kind'] === 'sent_off';
    }

    /**
     * Evidência formal em log de saída/expulsão do protagonista.
     */
    private static function hasExitLog(array $m): bool {
        return self::extractLogExitEvent($m)['found'];
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
     * Verifica marcadores estruturados ou legados de saída de campo (substituição, lesão, desgaste, expulsão ou conflito de saída).
     */
    public static function hasExitMarker(array $m): bool {
        return self::resolveExit($m)['status'] !== 'none';
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

        $exitInfo = self::resolveExit($m);
        // Conflito de causa irreconciliável: preserva dados originais sem inventar estado nem sobrescrever causa
        if ($exitInfo['cause_conflict']) {
            return;
        }

        if (!$hadCanonicalState) {
            // Reconciliação legada estrita: exige marcadores explícitos ou escalação reconhecida.
            // Evidências de entrada comprovada são avaliadas antes de classificar reserva como não utilizado.
            if ($exitInfo['kind'] === 'sent_off') {
                $state = self::STATE_SENT_OFF;
            } elseif (in_array($lineup, ['não relacionado', 'nao_relacionado'], true)) {
                $state = self::STATE_NOT_SELECTED;
            } elseif ($lineup === 'suspenso') {
                $state = self::STATE_SUSPENDED;
            } elseif ($exitInfo['status'] !== 'none' || ($exit !== null && $exit > 0 && $exit < 90 && $minute >= $exit && in_array($lineup, ['titular', 'reserva', 'banco'], true))) {
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
        } elseif ($exitInfo['status'] !== 'none' && in_array($state, [self::STATE_ON_FIELD, self::STATE_STARTING, self::STATE_ON_BENCH], true)) {
            $state = ($exitInfo['kind'] === 'sent_off') ? self::STATE_SENT_OFF : self::STATE_SUBSTITUTED_OUT;
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

        $exitInfo = self::resolveExit($m);

        // Saída confirmada ou conflito de saída impede presumir o horário planejado (90') como horário real.
        // Utiliza exclusivamente a reconciliação canônica (resolveExit): conflito de causa/horário ou minuto ausente retorna 0.
        if ($exitInfo['status'] !== 'none') {
            $entryDemonstrated = ($lineup === 'titular' && $entry === 0) || self::hasEntryEvidence($m);
            if (!$entryDemonstrated) {
                return 0;
            }

            if ($exitInfo['status'] !== 'valid' || $exitInfo['minute'] === null) {
                return 0;
            }

            $effectiveExit = $exitInfo['minute'];
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

