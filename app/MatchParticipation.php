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
     * Atualiza o estado de participação ao transitar minutos/segmentos
     */
    public static function sync(array &$m, array &$g, int $minute): void {
        $state = $m['participation_state'] ?? null;
        if (!$state) {
            // Reconciliação legada
            if (($m['lineup'] ?? '') === 'não relacionado') $state = self::STATE_NOT_SELECTED;
            elseif (($m['lineup'] ?? '') === 'suspenso') $state = self::STATE_SUSPENDED;
            elseif (($m['lineup'] ?? '') === 'reserva') $state = ($minute >= $m['entry'] && $minute < $m['exit']) ? self::STATE_ON_FIELD : self::STATE_ON_BENCH;
            else $state = ($minute >= $m['entry'] && $minute < $m['exit']) ? self::STATE_ON_FIELD : self::STATE_STARTING;
        }

        if (in_array($state, [self::STATE_NOT_SELECTED, self::STATE_SUSPENDED, self::STATE_SENT_OFF], true)) {
            $m['participation_state'] = $state;
            return;
        }

        // Checagem de entrada em campo do banco
        if ($state === self::STATE_ON_BENCH && $m['entry'] < 90 && $minute >= $m['entry'] && $minute < $m['exit']) {
            $m['participation_state'] = self::STATE_ON_FIELD;
            $m['log'][] = ['minute' => $m['entry'], 'text' => "{$m['entry']}' | Você entra em campo no lugar de um companheiro."];
            Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_IN', (int)$m['entry']);
            return;
        }

        // Checagem de saída por substituição
        if ($state === self::STATE_ON_FIELD && $minute >= $m['exit'] && $m['exit'] < 90) {
            $m['participation_state'] = self::STATE_SUBSTITUTED_OUT;
            $m['log'][] = ['minute' => $m['exit'], 'text' => "{$m['exit']}' | Você é substituído após participar da partida."];
            Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_OUT', (int)$m['exit']);
            return;
        }

        // Checagem de término de partida
        if ($minute >= 90) {
            if ($state === self::STATE_ON_FIELD) {
                $m['participation_state'] = self::STATE_FULL_TIME;
            } elseif ($state === self::STATE_ON_BENCH && $m['entry'] >= 90) {
                $m['log'][] = ['minute' => 90, 'text' => 'Você permaneceu no banco e não entrou em campo.'];
            }
            return;
        }

        if ($minute >= $m['entry'] && $minute < $m['exit']) {
            $m['participation_state'] = self::STATE_ON_FIELD;
        }
    }

    public static function isOnField(array $m): bool {
        return (($m['status'] ?? '') === 'em_andamento' &&
                $m['minute'] >= $m['entry'] &&
                $m['minute'] < $m['exit'] &&
                ($m['participation_state'] ?? self::STATE_ON_FIELD) === self::STATE_ON_FIELD);
    }
}

