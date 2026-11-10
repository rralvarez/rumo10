<?php
declare(strict_types=1);

final class F01Telemetry {
    public const PROTOCOL_VERSION = 1;
    public const MAX_EVENTS_LIMIT = 500; // Calibrado para temporada inteira no Expresso

    /**
     * Extrai envelope canônico de partidas e eventos a partir das mutações de uma ação do jogo.
     * Gera e reutiliza UUID v4 persistidos no estado/envelope da ocorrência.
     */
    public static function extractEnvelope(array $before, array &$after, string $action, array $input, string $commandUid, string $key, ?string $receivedAtUtc = null): ?array {
        $matches = [];
        $careerId = (int)$after['id'];
        $season = (int)($after['season'] ?? 1);
        $totalEventsCount = 0;
        $engineCommit = CanonicalIdentity::currentEngineCommit();
        $receivedAt = $receivedAtUtc ?? CanonicalIdentity::utcMicroTimestamp();

        // 1. MODO RÁPIDO OU EXPRESSO (Ação: simulate)
        if ($action === 'simulate' && isset($after['pace_report']['matches'])) {
            $simMatches = &$after['pace_report']['matches'];
            $mode = $after['pace_report']['mode'] ?? PlayMode::name($after);
            
            // Reutilizar ou persistir batch_uid no pace_report
            if (empty($after['pace_report']['canonical_batch_uid'])) {
                $after['pace_report']['canonical_batch_uid'] = CanonicalIdentity::uuidV4();
            }
            $batchUid = (string)$after['pace_report']['canonical_batch_uid'];

            foreach ($simMatches as $idx => &$m) {
                $mandante = (string)($m['home'] ?? '');
                $visitante = (string)($m['away'] ?? '');
                $competition = isset($m['competition']) && $m['competition'] !== '' ? (string)$m['competition'] : null;
                $seqBatch = $idx + 1;
                // Preservar rodada original se existir; não converter ausência em '1'
                $rodadaFase = isset($m['round']) && $m['round'] !== '' && $m['round'] !== null ? (string)$m['round'] : null;

                // UUID v4 canônico exclusivo e persistente para a partida
                if (empty($m['canonical_match_uid'])) {
                    $m['canonical_match_uid'] = CanonicalIdentity::uuidV4();
                }
                $matchUid = (string)$m['canonical_match_uid'];

                $stats = $m['stats'] ?? [];
                // Preservar valores desconhecidos sem fabricar premissas
                $minutes = array_key_exists('minutes', $stats) && $stats['minutes'] !== null ? (int)$stats['minutes'] : null;
                $rating = array_key_exists('rating', $stats) && $stats['rating'] !== null ? (float)$stats['rating'] : null;

                // Não inferir titularidade nem fabricar aparições se o protagonista não participou
                $hasExplicitLineup = !empty($m['lineup']);
                $participated = ($minutes !== null && $minutes > 0);
                $hasAppearance = $hasExplicitLineup || $participated;
                $appearances = [];

                if ($hasAppearance) {
                    if (empty($m['canonical_appearance_uid'])) {
                        $m['canonical_appearance_uid'] = CanonicalIdentity::uuidV4();
                    }
                    $appUid = (string)$m['canonical_appearance_uid'];
                    
                    // Condição de escalação real: preservar NULL se a fonte não informar escalação (elimina inferência de titularidade)
                    $condicao = !empty($m['lineup']) ? (string)$m['lineup'] : null;
                    
                    $minutosJogados = $minutes;
                    $minutoEntrada = isset($m['entry']) && $m['entry'] !== null ? (int)$m['entry'] : null;
                    $minutoSaida = isset($m['exit']) && $m['exit'] !== null ? (int)$m['exit'] : null;
                    $estadoPart = (string)($m['participation_state'] ?? ($participated ? 'participou' : 'banco'));

                    $events = [];
                    $log = &$m['log'];
                    if (is_array($log)) {
                        foreach ($log as $seq => &$l) {
                            $totalEventsCount++;
                            $seqPartida = $seq + 1;
                            if (empty($l['canonical_event_uid'])) {
                                $l['canonical_event_uid'] = CanonicalIdentity::uuidV4();
                            }
                            $evUid = (string)$l['canonical_event_uid'];
                            $minutoJogo = isset($l['minute']) && $l['minute'] !== null ? (int)$l['minute'] : null;
                            $tipoEvento = self::inferEventType($l['text'] ?? '');

                            $events[] = [
                                'event_uid' => $evUid,
                                'tipo_evento' => $tipoEvento,
                                'sequencia_partida' => $seqPartida,
                                'minuto_jogo' => $minutoJogo,
                                'dados_evento_json' => $l,
                            ];
                        }
                        unset($l);
                    }

                    // Eventos de som nativos da V8
                    if (!empty($m['sound_events']) && is_array($m['sound_events'])) {
                        $soundEvents = &$m['sound_events'];
                        $logCount = is_array($log) ? count($log) : 0;
                        foreach ($soundEvents as $sndIdx => &$snd) {
                            $totalEventsCount++;
                            $seqPartida = $logCount + $sndIdx + 1;
                            if (empty($snd['canonical_event_uid'])) {
                                $snd['canonical_event_uid'] = CanonicalIdentity::uuidV4();
                            }
                            $evUid = (string)$snd['canonical_event_uid'];
                            $events[] = [
                                'event_uid' => $evUid,
                                'tipo_evento' => 'audio_' . strtolower($snd['type'] ?? 'efeito'),
                                'sequencia_partida' => $seqPartida,
                                'minuto_jogo' => isset($snd['minute']) && $snd['minute'] !== null ? (int)$snd['minute'] : null,
                                'dados_evento_json' => $snd,
                            ];
                        }
                        unset($snd);
                    }

                    if ($totalEventsCount > self::MAX_EVENTS_LIMIT) {
                        throw new RuntimeException("Limite de telemetria excedido no modo $mode ($totalEventsCount > " . self::MAX_EVENTS_LIMIT . "). Operação abortada sem persistência.");
                    }

                    $appearances[] = [
                        'appearance_uid' => $appUid,
                        'carreira_id' => $careerId,
                        'condicao_escalacao' => $condicao,
                        'minuto_entrada' => $minutoEntrada,
                        'minuto_saida' => $minutoSaida,
                        'minutos_jogados' => $minutosJogados,
                        'estado_participacao' => $estadoPart,
                        'nota_atuacao' => $rating,
                        'events' => $events,
                    ];
                }

                $matches[] = [
                    'match_uid' => $matchUid,
                    'temporada_numero' => $season,
                    'competicao_codigo' => $competition,
                    'rodada_fase' => $rodadaFase,
                    'mandante' => $mandante,
                    'visitante' => $visitante,
                    'gols_mandante' => isset($m['hg']) && $m['hg'] !== null ? (int)$m['hg'] : null,
                    'gols_visitante' => isset($m['ag']) && $m['ag'] !== null ? (int)$m['ag'] : null,
                    'modo_resolucao' => $mode,
                    'batch_uid' => $batchUid,
                    'sequencia_no_batch' => $seqBatch,
                    'engine_commit' => $engineCommit,
                    'appearances' => $appearances,
                ];
            }
            unset($m);
        }
        // 2. MODO NORMAL (Ação: advance ou choice que conclui partida)
        elseif (in_array($action, ['advance', 'choice'], true) && ($after['stage'] === 'fim_semana' || $after['stage'] === 'competicao_resumo' || ($before['stage'] === 'partida' && $after['stage'] !== 'partida'))) {
            $mIdx = Game::currentIndex($after);
            if (isset($after['matches'][$mIdx])) {
                $m = &$after['matches'][$mIdx];
                if (in_array($m['status'] ?? '', ['encerrada', 'finalizada'], true)) {
                    $mandante = (string)($m['home'] ?? '');
                    $visitante = (string)($m['away'] ?? '');
                    $rodadaFase = isset($m['round']) && $m['round'] !== '' && $m['round'] !== null ? (string)$m['round'] : null;
                    $competition = Competition::label($after);

                    if (empty($m['canonical_match_uid'])) {
                        $m['canonical_match_uid'] = CanonicalIdentity::uuidV4();
                    }
                    $matchUid = (string)$m['canonical_match_uid'];

                    $stats = $m['stats'] ?? [];
                    $minutes = array_key_exists('minutes', $stats) && $stats['minutes'] !== null ? (int)$stats['minutes'] : null;
                    $rating = array_key_exists('rating', $stats) && $stats['rating'] !== null ? (float)$stats['rating'] : null;

                    $hasExplicitLineup = !empty($m['lineup']);
                    $participated = ($minutes !== null && $minutes > 0);
                    $hasAppearance = $hasExplicitLineup || $participated;
                    $appearances = [];

                    if ($hasAppearance) {
                        if (empty($m['canonical_appearance_uid'])) {
                            $m['canonical_appearance_uid'] = CanonicalIdentity::uuidV4();
                        }
                        $appUid = (string)$m['canonical_appearance_uid'];
                        // Preservar NULL se a fonte não informar escalação (elimina inferência de titularidade)
                        $condicao = !empty($m['lineup']) ? (string)$m['lineup'] : null;
                        $entry = isset($m['entry']) && $m['entry'] !== null ? (int)$m['entry'] : null;
                        $exit = isset($m['exit']) && $m['exit'] !== null ? (int)$m['exit'] : null;
                        $minutosJogados = $minutes;
                        $estadoPart = (string)($m['participation_state'] ?? ($participated ? 'participou' : 'banco'));

                        $events = [];
                        $log = &$m['log'];
                        if (is_array($log)) {
                            foreach ($log as $seq => &$l) {
                                $totalEventsCount++;
                                $seqPartida = $seq + 1;
                                if (empty($l['canonical_event_uid'])) {
                                    $l['canonical_event_uid'] = CanonicalIdentity::uuidV4();
                                }
                                $evUid = (string)$l['canonical_event_uid'];
                                $minutoJogo = isset($l['minute']) && $l['minute'] !== null ? (int)$l['minute'] : null;
                                $tipoEvento = self::inferEventType($l['text'] ?? '');

                                $events[] = [
                                    'event_uid' => $evUid,
                                    'tipo_evento' => $tipoEvento,
                                    'sequencia_partida' => $seqPartida,
                                    'minuto_jogo' => $minutoJogo,
                                    'dados_evento_json' => $l,
                                ];
                            }
                            unset($l);
                        }

                        // Eventos de som nativos
                        if (!empty($m['sound_events']) && is_array($m['sound_events'])) {
                            $soundEvents = &$m['sound_events'];
                            $logCount = is_array($log) ? count($log) : 0;
                            foreach ($soundEvents as $sndIdx => &$snd) {
                                $totalEventsCount++;
                                $seqPartida = $logCount + $sndIdx + 1;
                                if (empty($snd['canonical_event_uid'])) {
                                    $snd['canonical_event_uid'] = CanonicalIdentity::uuidV4();
                                }
                                $evUid = (string)$snd['canonical_event_uid'];
                                $events[] = [
                                    'event_uid' => $evUid,
                                    'tipo_evento' => 'audio_' . strtolower($snd['type'] ?? 'efeito'),
                                    'sequencia_partida' => $seqPartida,
                                    'minuto_jogo' => isset($snd['minute']) && $snd['minute'] !== null ? (int)$snd['minute'] : null,
                                    'dados_evento_json' => $snd,
                                ];
                            }
                            unset($snd);
                        }

                        if ($totalEventsCount > self::MAX_EVENTS_LIMIT) {
                            throw new RuntimeException("Limite de telemetria excedido no modo normal ($totalEventsCount > " . self::MAX_EVENTS_LIMIT . ").");
                        }

                        $appearances[] = [
                            'appearance_uid' => $appUid,
                            'carreira_id' => $careerId,
                            'condicao_escalacao' => $condicao,
                            'minuto_entrada' => $entry,
                            'minuto_saida' => $exit,
                            'minutos_jogados' => $minutosJogados,
                            'estado_participacao' => $estadoPart,
                            'nota_atuacao' => $rating,
                            'events' => $events,
                        ];
                    }

                    $matches[] = [
                        'match_uid' => $matchUid,
                        'temporada_numero' => $season,
                        'competicao_codigo' => $competition,
                        'rodada_fase' => $rodadaFase,
                        'mandante' => $mandante,
                        'visitante' => $visitante,
                        'gols_mandante' => isset($m['hg']) && $m['hg'] !== null ? (int)$m['hg'] : null,
                        'gols_visitante' => isset($m['ag']) && $m['ag'] !== null ? (int)$m['ag'] : null,
                        'modo_resolucao' => 'normal',
                        'batch_uid' => null,
                        'sequencia_no_batch' => null,
                        'engine_commit' => $engineCommit,
                        'appearances' => $appearances,
                    ];
                }
                unset($m);
            }
        }

        if (empty($matches)) {
            return null;
        }

        return [
            'contract_version' => self::PROTOCOL_VERSION,
            'carreira_id' => $careerId,
            'command_uid' => $commandUid,
            'chave' => $key,
            'received_at_utc' => $receivedAt,
            'engine_commit' => $engineCommit,
            'total_eventos' => $totalEventsCount,
            'matches' => $matches,
        ];
    }

    private static function inferEventType(string $text): string {
        $t = mb_strtolower($text);
        if (str_contains($t, 'gol')) return 'gol';
        if (str_contains($t, 'pênalti') || str_contains($t, 'penalti')) return 'penalti';
        if (str_contains($t, 'cartão') || str_contains($t, 'cartao')) return 'cartao';
        if (str_contains($t, 'falta')) return 'falta';
        if (str_contains($t, 'finalização') || str_contains($t, 'chute')) return 'finalizacao';
        if (str_contains($t, 'passe')) return 'passe';
        if (str_contains($t, 'desarme')) return 'desarme';
        return 'lance';
    }
}
