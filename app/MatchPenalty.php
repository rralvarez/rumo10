<?php
declare(strict_types=1);

/**
 * Gestão de Pênaltis Durante o Tempo Regulamentar da Partida (MATCH_PENALTY)
 * Estritamente separado de disputas eliminatórias pós-jogo (PenaltyShootout).
 */
final class MatchPenalty {
    public const SITUATION_REQUEST = 1400; // Pedir cobrança ao batedor oficial
    public const SITUATION_KICK = 1401;    // Execução do pênalti pelo protagonista

    public static function isProtagonistOfficialTaker(array $g, array $m): bool {
        if (!MatchParticipation::isOnField($m)) return false;
        // Posição ofensiva / meia com liderança ou finalização de destaque
        if (!in_array($g['position'], ['centroavante', 'meia', 'ponta'], true)) return false;
        $leadership = $g['states']['lideranca'] ?? 50;
        $coachConfidence = $g['states']['confianca_treinador'] ?? 50;
        $finishing = $g['skills']['finalizacao']['value'] ?? 50;
        return ($finishing >= 65 || ($leadership >= 60 && $coachConfidence >= 55));
    }

    public static function triggerRequestSituation(array &$m, string $victim): array {
        $m['active_match_penalty'] = [
            'victim' => $victim,
            'status' => 'pending_request'
        ];
        return [
            'id' => self::SITUATION_REQUEST,
            'text' => 'O árbitro marca pênalti para sua equipe! Um companheiro é o cobrador designado, mas você está em campo. Você se aproxima e pede para assumir a cobrança.',
            'options' => [
                ['text' => 'Pedir para bater', 'action' => 'social', 'effects' => []],
                ['text' => 'Respeitar o cobrador designado', 'action' => 'social', 'effects' => []],
                ['text' => 'Incentivar o companheiro', 'action' => 'social', 'effects' => ['entrosamento' => 1]],
            ]
        ];
    }

    public static function triggerKickSituation(array &$m, string $contextMessage): array {
        $m['active_match_penalty'] = [
            'status' => 'taking_kick'
        ];
        return [
            'id' => self::SITUATION_KICK,
            'text' => $contextMessage,
            'options' => [
                ['text' => 'Finalizar com força', 'action' => 'chute', 'shot_kind' => 'penalty_power', 'effects' => []],
                ['text' => 'Buscar o canto com precisão', 'action' => 'chute', 'shot_kind' => 'penalty_placement', 'effects' => []],
                ['text' => 'Esperar a movimentação do goleiro', 'action' => 'chute', 'shot_kind' => 'penalty_wait_keeper', 'effects' => []],
                ['text' => 'Tentar uma cobrança mais arriscada', 'action' => 'chute', 'shot_kind' => 'penalty_risk', 'effects' => []],
            ]
        ];
    }

    /**
     * Resolve a cobrança efetuada por um companheiro de equipe
     */
    public static function resolveTeammatePenalty(array &$g, array &$m, bool $encouraged = false, ?string $occurrenceId = null): void {
        $minute = $m['minute'] ?? 0;
        $m['resolved_penalties'] = $m['resolved_penalties'] ?? [];
        $penKey = $occurrenceId ?? ($m['active_match_penalty']['window'] ?? ('teammate:' . $minute));
        if (in_array($penKey, $m['resolved_penalties'], true)) {
            unset($m['active_match_penalty']);
            return;
        }

        $own = ($m['home'] === $g['club']) ? 'hg' : 'ag';
        $conversionRate = $encouraged ? 80 : 75;
        $scored = (random_int(1, 100) <= $conversionRate);

        if ($scored) {
            $m[$own]++;
            $m['log'][] = ['minute' => $minute, 'text' => "{$minute}' | Pênalti! O companheiro bate com precisão e marca o gol."];
            Game::emitSoundEvent($m, $g, 'GOAL_SCORED', (int)$minute);
        } else {
            $m['log'][] = ['minute' => $minute, 'text' => "{$minute}' | Pênalti desperdiçado! O goleiro adversário defende a cobrança do companheiro."];
        }
        $m['resolved_penalties'][] = $penKey;
        unset($m['active_match_penalty']);
    }

    /**
     * Resolve um pênalti contra a equipe do protagonista
     */
    public static function resolveOpponentPenalty(array &$g, array &$m, ?string $occurrenceId = null): void {
        $minute = $m['minute'] ?? 0;
        $m['resolved_penalties'] = $m['resolved_penalties'] ?? [];
        $penKey = $occurrenceId ?? ('opponent:' . $minute);
        if (in_array($penKey, $m['resolved_penalties'], true)) {
            return;
        }

        $opp = ($m['home'] === $g['club']) ? 'ag' : 'hg';
        $scored = (random_int(1, 100) <= 76);
        Game::emitSoundEvent($m, $g, 'PENALTY_AWARDED', (int)$minute);

        if ($scored) {
            $m[$opp]++;
            $m['log'][] = ['minute' => $minute, 'text' => "{$minute}' | Pênalti contra! O cobrador adversário desloca o goleiro e converte."];
        } else {
            $m['log'][] = ['minute' => $minute, 'text' => "{$minute}' | Pênalti defendido! Nosso goleiro voa no canto e evita o gol adversário!"];
        }
        $m['resolved_penalties'][] = $penKey;
    }
}

