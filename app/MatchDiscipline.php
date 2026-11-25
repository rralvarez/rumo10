<?php
declare(strict_types=1);

/**
 * Gestão do Sistema Disciplinar (Faltas, Cartões e Suspensões)
 * Respeita estritamente o regulamento das competições e previne suspensões universais arbitrárias.
 * Diferencia expulsão em campo (partida atual) de suspensão disciplinar futura.
 */
final class MatchDiscipline {
    /**
     * Verifica se o atleta possui suspensão ativa para a partida da competição informada.
     */
    public static function isSuspended(array $g, string $competitionId): bool {
        return (int)($g['disciplinary'][$competitionId]['pending_suspension'] ?? 0) > 0;
    }

    /**
     * Retorna a motivação regulamentar da suspensão
     */
    public static function suspensionReason(array $g, string $competitionId): string {
        return (string)($g['disciplinary'][$competitionId]['suspension_reason'] ?? '');
    }

    /**
     * Consulta os parâmetros regulamentares da competição específica
     */
    public static function competitionRules(string $competitionId): array {
        $catalog = class_exists('Competition') ? Competition::catalog() : [];
        return $catalog[$competitionId]['disciplinary'] 
            ?? $catalog[$competitionId]['rules']['disciplinary'] 
            ?? [];
    }

    /**
     * Aplica falta e avalia cartão no protagonista com distinção entre falta tática e infrações disciplinares.
     */
    public static function recordFoul(array &$g, array &$m, string $context, bool $severe = false): array {
        if (MatchParticipation::resolveExit($m)['status'] !== 'none') {
            return ['card' => null, 'text' => ''];
        }

        $st = &$m['stats'];
        $st['yellow_cards'] = $st['yellow_cards'] ?? 0;
        $st['red_cards'] = $st['red_cards'] ?? 0;
        $st['direct_red'] = $st['direct_red'] ?? 0;
        $st['second_yellow'] = $st['second_yellow'] ?? 0;

        $st['fouls'] = ($st['fouls'] ?? 0) + 1;

        $minute = $m['minute'];
        $result = ['card' => null, 'text' => ''];

        // Falta tática simples ou desarme sem cartão
        if ($context === 'tactical' || $context === 'minor') {
            $result['text'] = "{$minute}' | Falta marcada contra você.";
            return $result;
        }

        if ($severe && $context === 'violent_conduct') {
            // Cartão vermelho direto
            $st['direct_red'] = 1;
            $st['red_cards'] = 1;
            $st['cards'] = $st['yellow_cards'] + 1;
            MatchParticipation::recordExit($m, $g, (int)$minute, 'sent_off');
            $result['card'] = 'direct_red';
            $result['text'] = "{$minute}' | Cartão vermelho direto! Você foi expulso da partida.";
            $result['sound_event_id'] = Game::emitSoundEvent($m, $g, 'CARD_RED', (int)$minute);
            return $result;
        }

        // Primeiro amarelo
        if ($st['yellow_cards'] === 0) {
            $st['yellow_cards'] = 1;
            $st['cards'] = 1;
            $result['card'] = 'yellow';
            $result['text'] = "{$minute}' | Cartão amarelo! Você foi advertido pela arbitragem.";
            $result['sound_event_id'] = Game::emitSoundEvent($m, $g, 'CARD_YELLOW', (int)$minute);
            return $result;
        }

        // Segundo amarelo -> Expulsão
        if ($st['yellow_cards'] === 1) {
            $st['yellow_cards'] = 2;
            $st['second_yellow'] = 1;
            $st['red_cards'] = 1;
            $st['cards'] = 2;
            MatchParticipation::recordExit($m, $g, (int)$minute, 'sent_off');
            $result['card'] = 'second_yellow';
            $result['text'] = "{$minute}' | Segundo cartão amarelo! Você está expulso da partida.";
            $result['sound_event_id'] = Game::emitSoundEvent($m, $g, 'CARD_RED', (int)$minute);
            return $result;
        }

        return $result;
    }

    /**
     * Classificação disciplinar canônica da partida, unificada com MatchParticipation::resolveExit().
     * Distingue: none, direct_red, second_yellow, sent_off_unspecified e conflict.
     * Em conflito temporal sem prova estruturada independente (ou conflito causal/subtipo),
     * sinaliza pending_reconciliation=true para impedir punição definitiva precipitada.
     *
     * @return array{outcome:string,minute:?int,pending_reconciliation:bool}
     */
    public static function classifyExpulsion(array $m): array {
        $exitInfo = MatchParticipation::resolveExit($m);
        if ($exitInfo['cause_conflict'] || !empty($exitInfo['subtype_conflict'])) {
            return ['outcome' => 'conflict', 'minute' => null, 'pending_reconciliation' => true];
        }
        if ($exitInfo['kind'] !== 'sent_off') {
            return ['outcome' => 'none', 'minute' => null, 'pending_reconciliation' => false];
        }
        if (!empty($exitInfo['time_conflict'])) {
            // Caso B: prova estruturada independente confirma infração e subtipo (regra não depende do minuto)
            if (!empty($exitInfo['has_structured_expulsion']) && !empty($exitInfo['structured_subtype'])) {
                return ['outcome' => $exitInfo['structured_subtype'], 'minute' => null, 'pending_reconciliation' => false];
            }
            // Caso A: registro internamente contraditório sem prova estruturada independente -> pendente de reconciliação
            return ['outcome' => 'conflict', 'minute' => null, 'pending_reconciliation' => true];
        }
        if ($exitInfo['subtype'] === 'direct_red') {
            return ['outcome' => 'direct_red', 'minute' => $exitInfo['minute'], 'pending_reconciliation' => false];
        }
        if ($exitInfo['subtype'] === 'second_yellow') {
            return ['outcome' => 'second_yellow', 'minute' => $exitInfo['minute'], 'pending_reconciliation' => false];
        }
        return ['outcome' => 'sent_off_unspecified', 'minute' => $exitInfo['minute'], 'pending_reconciliation' => false];
    }

    /**
     * Reconcilia disciplina ao fim da partida na competição correspondente.
     * Consome exclusivamente a classificação canônica de expulsão (MatchDiscipline::classifyExpulsion /
     * MatchParticipation::resolveExit), garantindo paridade entre log formal e campos estruturados
     * e impedindo punição silenciosa em conflitos causais ou disciplinares irreconciliáveis.
     */
    public static function processMatchEnd(array &$g, array &$m): void {
        if (!empty($m['disciplinary_processed'])) {
            return;
        }

        $cid = $m['competition'] ?? ($g['competition_id'] ?? 'legacy');
        $g['disciplinary'] = $g['disciplinary'] ?? [];
        $g['disciplinary'][$cid] = $g['disciplinary'][$cid] ?? [
            'yellow_accumulated' => 0,
            'pending_suspension' => 0,
            'suspension_reason' => '',
            'suspensions_served' => 0,
            'regulatory_status' => 'regular' // regular | suspended | pending_tribunal | no_rule_recorded
        ];
        $disp = &$g['disciplinary'][$cid];

        // Se estava cumprindo suspensão nesta competição, decrementa
        if (($m['participation_state'] ?? '') === MatchParticipation::STATE_SUSPENDED) {
            if ($disp['pending_suspension'] > 0) {
                $disp['pending_suspension']--;
                $disp['suspensions_served']++;
                if ($disp['pending_suspension'] === 0) {
                    $disp['suspension_reason'] = '';
                    $disp['regulatory_status'] = 'regular';
                }
            }
            $m['disciplinary_processed'] = true;
            return;
        }

        $rules = self::competitionRules($cid);
        $st = $m['stats'] ?? [];
        $yellows = (int)($st['yellow_cards'] ?? 0);
        $classification = self::classifyExpulsion($m);

        // Evidência pendente (conflito causal, de subtipo ou temporal sem prova estruturada independente):
        // preserva ledger sem punição e NÃO grava disciplinary_processed=true para permitir reconciliação posterior.
        if ($classification['outcome'] === 'conflict' || !empty($classification['pending_reconciliation'])) {
            return;
        }

        // 1. Vermelho direto comprovado (estrutura, log formal ou ambos concordantes)
        if ($classification['outcome'] === 'direct_red') {
            if (isset($rules['direct_red_suspension'])) {
                $games = (int)$rules['direct_red_suspension'];
                if ($games > 0) {
                    $disp['pending_suspension'] = max($disp['pending_suspension'], $games);
                    $disp['suspension_reason'] = 'Cartão vermelho direto (' . $games . ' partida' . ($games > 1 ? 's' : '') . ')';
                    $disp['regulatory_status'] = 'suspended';
                } else {
                    $disp['regulatory_status'] = 'regular';
                }
            } elseif (!empty($rules['has_disciplinary_code'])) {
                // Competição possui tribunal desportivo, aguarda julgamento
                $disp['regulatory_status'] = 'pending_tribunal';
                $disp['suspension_reason'] = 'Aguardando julgamento desportivo';
            } else {
                // Ausência de regra documentada: não inventar suspensão automática cega
                $disp['regulatory_status'] = 'no_rule_recorded';
                $disp['suspension_reason'] = 'Sem regra disciplinar específica documentada';
            }
            $m['disciplinary_processed'] = true;
            return;
        }

        // 2. Segundo amarelo comprovado (estrutura, log formal ou ambos concordantes)
        if ($classification['outcome'] === 'second_yellow') {
            if (isset($rules['second_yellow_suspension'])) {
                $games = (int)$rules['second_yellow_suspension'];
                if ($games > 0) {
                    $disp['pending_suspension'] = max($disp['pending_suspension'], $games);
                    $disp['suspension_reason'] = 'Expulsão por segundo cartão amarelo (' . $games . ' partida' . ($games > 1 ? 's' : '') . ')';
                    $disp['regulatory_status'] = 'suspended';
                } else {
                    $disp['regulatory_status'] = 'regular';
                }
            } else {
                // Sem regra de suspensão por segundo amarelo para esta competição
                $disp['regulatory_status'] = 'no_rule_recorded';
                $disp['suspension_reason'] = 'Sem regra de suspensão por segundo amarelo';
            }
            $m['disciplinary_processed'] = true;
            return;
        }

        // 3. Expulsão comprovada sem subtipo confiável: não atribui subtipo nem infere pena pelo mínimo de regras
        if ($classification['outcome'] === 'sent_off_unspecified') {
            if (!empty($rules['has_disciplinary_code'])) {
                $disp['regulatory_status'] = 'pending_tribunal';
                $disp['suspension_reason'] = 'Expulsão sem subtipo especificado — aguardando julgamento desportivo';
            } else {
                $disp['regulatory_status'] = 'no_rule_recorded';
                $disp['suspension_reason'] = 'Expulsão sem subtipo disciplinar especificado e sem regra documentada';
            }
            $m['disciplinary_processed'] = true;
            return;
        }

        // 4. Acúmulo de cartões amarelos (sem expulsão)
        if ($yellows === 1) {
            $threshold = $rules['yellow_suspension_threshold'] ?? null;
            if ($threshold !== null && $threshold > 0) {
                $disp['yellow_accumulated']++;
                if ($disp['yellow_accumulated'] >= $threshold) {
                    $games = (int)($rules['yellow_accumulation_suspension'] ?? 1);
                    $disp['pending_suspension'] = max($disp['pending_suspension'], $games);
                    $disp['suspension_reason'] = 'Acúmulo de cartões amarelos (' . $threshold . ' amarelos)';
                    $disp['regulatory_status'] = 'suspended';
                    $disp['yellow_accumulated'] = 0; // zera ciclo
                }
            } else {
                // Competição sem acúmulo de amarelos
                $disp['yellow_accumulated']++;
            }
        }
        $m['disciplinary_processed'] = true;
    }
}
