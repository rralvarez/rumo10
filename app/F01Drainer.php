<?php
declare(strict_types=1);

final class F01Drainer {
    public const DEFAULT_BATCH_SIZE = 20;
    public const MAX_BATCH_SIZE = 20; // Limitado a no máximo 20 envelopes por execução conforme OS-F01-P1-002 e OS-F01-P1-003

    /**
     * Drena até $batchSize registros pendentes da rumo10_f01_outbox para as tabelas canônicas.
     * Exige titularidade da carreira vinculada à seleção transacional da fila,
     * eliminando a janela de concorrência entre autorização e processamento.
     */
    public static function drain(Repository $r, int $account, int $careerId, int $batchSize = self::DEFAULT_BATCH_SIZE): array {
        $batchSize = max(1, min($batchSize, self::MAX_BATCH_SIZE));
        $processed = 0;
        $failed = 0;

        // Loop de processamento: seleção e autorização sob FOR UPDATE SKIP LOCKED unificadas na mesma consulta
        for ($i = 0; $i < $batchSize; $i++) {
            $r->db->beginTransaction();
            $outboxId = null;
            try {
                $r->query("SET time_zone = '+00:00'");

                // Seleção transacional vinculada estritamente à titularidade da conta e carreira via JOIN com lock FOR UPDATE SKIP LOCKED
                $stmt = $r->query(
                    "SELECT o.id, o.carreira_id, o.command_uid, o.chave, o.payload_json, o.tentativas, o.engine_commit, o.received_at_utc 
                     FROM rumo10_f01_outbox o
                     WHERE o.carreira_id = ? 
                       AND o.estado = 'pendente'
                       AND EXISTS (
                           SELECT 1 FROM rumo10_carreiras c
                           WHERE c.id = o.carreira_id
                             AND (c.conta_id = ? OR (c.conta_id IS NULL AND EXISTS (
                                 SELECT 1 FROM rumo10_carreiras_arquivadas a WHERE a.carreira_id = c.id AND a.conta_id = ?
                             )))
                       )
                     ORDER BY o.id ASC 
                     LIMIT 1 
                     FOR UPDATE SKIP LOCKED",
                    [$careerId, $account, $account]
                );
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    // Verificar se a carreira sequer pertence à conta (para fornecer erro de autorização determinístico se aplicável)
                    $ownsCareer = $r->query(
                        "SELECT id FROM rumo10_carreiras 
                         WHERE id = ? AND (conta_id = ? OR (conta_id IS NULL AND EXISTS (
                             SELECT 1 FROM rumo10_carreiras_arquivadas a WHERE a.carreira_id = ? AND a.conta_id = ?
                         )))",
                        [$careerId, $account, $careerId, $account]
                    )->fetch();

                    $r->db->rollBack();
                    if (!$ownsCareer) {
                        throw new InvalidArgumentException('Acesso negado: a carreira informada não pertence à conta autenticada.');
                    }
                    break; // Carreira pertence à conta, mas não há mais itens pendentes
                }

                $outboxId = (int)$row['id'];
                $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR);

                // Projetar tabelas canônicas
                self::processEnvelope($r, (int)$row['carreira_id'], (string)$row['command_uid'], $payload, $row['engine_commit']);

                // Atualizar estado para processado sob a mesma transação
                $r->query(
                    "UPDATE rumo10_f01_outbox 
                     SET estado = 'processado', erro = NULL, projected_at_utc = CURRENT_TIMESTAMP(6) 
                     WHERE id = ?",
                    [$outboxId]
                );

                $r->db->commit();
                $processed++;
            } catch (InvalidArgumentException $ia) {
                if ($r->db->inTransaction()) {
                    $r->db->rollBack();
                }
                throw $ia;
            } catch (Throwable $e) {
                if ($r->db->inTransaction()) {
                    $r->db->rollBack();
                }
                $failed++;

                // Tratamento seguro de falha: nova transação isolada com verificação transacional sob lock FOR UPDATE
                if ($outboxId !== null) {
                    try {
                        $r->db->beginTransaction();
                        // Re-verificar estado atual sob lock para não sobrescrever item que possa ter sido processado concorrentemente
                        $checkStmt = $r->query(
                            "SELECT id, estado, tentativas FROM rumo10_f01_outbox WHERE id = ? FOR UPDATE",
                            [$outboxId]
                        );
                        $cur = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($cur && $cur['estado'] === 'pendente') {
                            $tentativas = ((int)$cur['tentativas']) + 1;
                            $status = $tentativas >= 5 ? 'falha' : 'pendente';
                            $sanitizedMsg = mb_substr(preg_replace('/[\r\n\t]+/', ' ', $e->getMessage()), 0, 500);
                            $r->query(
                                "UPDATE rumo10_f01_outbox 
                                 SET estado = ?, tentativas = ?, erro = ? 
                                 WHERE id = ?",
                                [$status, $tentativas, $sanitizedMsg, $outboxId]
                            );
                        }
                        $r->db->commit();
                    } catch (Throwable) {
                        if ($r->db->inTransaction()) {
                            $r->db->rollBack();
                        }
                    }
                }
                break; // Interrompe o lote para manter a ordem e evitar loops de exceção
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    private static function processEnvelope(Repository $r, int $carreiraId, string $commandUid, array $envelope, ?string $envelopeCommit): void {
        $matches = $envelope['matches'] ?? [];
        foreach ($matches as $match) {
            $matchUid = (string)$match['match_uid'];
            $temporadaNumero = (int)($match['temporada_numero'] ?? 1);
            $competicaoCodigo = isset($match['competicao_codigo']) && $match['competicao_codigo'] !== '' ? (string)$match['competicao_codigo'] : null;
            $rodadaFase = isset($match['rodada_fase']) && $match['rodada_fase'] !== '' ? (string)$match['rodada_fase'] : null;
            $mandante = (string)($match['mandante'] ?? '');
            $visitante = (string)($match['visitante'] ?? '');
            $golsMandante = isset($match['gols_mandante']) && $match['gols_mandante'] !== null ? (int)$match['gols_mandante'] : null;
            $golsVisitante = isset($match['gols_visitante']) && $match['gols_visitante'] !== null ? (int)$match['gols_visitante'] : null;
            $modoResolucao = (string)($match['modo_resolucao'] ?? 'normal');
            $batchUid = $match['batch_uid'] ?? null;
            $seqBatch = isset($match['sequencia_no_batch']) && $match['sequencia_no_batch'] !== null ? (int)$match['sequencia_no_batch'] : null;
            $engineCommit = $match['engine_commit'] ?? $envelopeCommit ?? CanonicalIdentity::currentEngineCommit();

            // Inserir partida canônica
            $r->query(
                "INSERT INTO rumo10_f01_partidas 
                 (match_uid, carreira_id, temporada_numero, competicao_codigo, rodada_fase, mandante, visitante, gols_mandante, gols_visitante, modo_resolucao, batch_uid, sequencia_no_batch, engine_commit, projected_at_utc)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE 
                    gols_mandante = VALUES(gols_mandante),
                    gols_visitante = VALUES(gols_visitante),
                    engine_commit = VALUES(engine_commit),
                    projected_at_utc = CURRENT_TIMESTAMP(6)",
                [$matchUid, $carreiraId, $temporadaNumero, $competicaoCodigo, $rodadaFase, $mandante, $visitante, $golsMandante, $golsVisitante, $modoResolucao, $batchUid, $seqBatch, $engineCommit]
            );

            // Inserir aparições reais
            $appearances = $match['appearances'] ?? [];
            foreach ($appearances as $app) {
                $appearanceUid = (string)$app['appearance_uid'];
                $appCarreiraId = (int)($app['carreira_id'] ?? $carreiraId);
                $condicao = isset($app['condicao_escalacao']) && $app['condicao_escalacao'] !== null && $app['condicao_escalacao'] !== '' ? (string)$app['condicao_escalacao'] : null;
                $minutoEntrada = isset($app['minuto_entrada']) && $app['minuto_entrada'] !== null ? (int)$app['minuto_entrada'] : null;
                $minutoSaida = isset($app['minuto_saida']) && $app['minuto_saida'] !== null ? (int)$app['minuto_saida'] : null;
                $minutosJogados = isset($app['minutos_jogados']) && $app['minutos_jogados'] !== null ? (int)$app['minutos_jogados'] : null;
                $estadoParticipacao = (string)($app['estado_participacao'] ?? 'participou');
                $notaAtuacao = array_key_exists('nota_atuacao', $app) && $app['nota_atuacao'] !== null ? (float)$app['nota_atuacao'] : null;

                $r->query(
                    "INSERT INTO rumo10_f01_aparicoes
                     (appearance_uid, match_uid, carreira_id, condicao_escalacao, minuto_entrada, minuto_saida, minutos_jogados, estado_participacao, nota_atuacao, projected_at_utc)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))
                     ON DUPLICATE KEY UPDATE
                        minuto_saida = VALUES(minuto_saida),
                        minutos_jogados = VALUES(minutos_jogados),
                        nota_atuacao = VALUES(nota_atuacao),
                        estado_participacao = VALUES(estado_participacao),
                        projected_at_utc = CURRENT_TIMESTAMP(6)",
                    [$appearanceUid, $matchUid, $appCarreiraId, $condicao, $minutoEntrada, $minutoSaida, $minutosJogados, $estadoParticipacao, $notaAtuacao]
                );

                // Inserir eventos associados
                $events = $app['events'] ?? [];
                foreach ($events as $ev) {
                    $eventUid = (string)$ev['event_uid'];
                    $tipoEvento = (string)($ev['tipo_evento'] ?? 'lance');
                    $seqPartida = isset($ev['sequencia_partida']) ? (int)$ev['sequencia_partida'] : null;
                    $minutoJogo = isset($ev['minuto_jogo']) ? (int)$ev['minuto_jogo'] : null;
                    $dadosJson = json_encode($ev['dados_evento_json'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

                    $r->query(
                        "INSERT INTO rumo10_f01_eventos
                         (event_uid, match_uid, appearance_uid, carreira_id, command_uid, tipo_evento, sequencia_partida, minuto_jogo, dados_evento_json, projected_at_utc)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))
                         ON DUPLICATE KEY UPDATE
                            minuto_jogo = VALUES(minuto_jogo),
                            dados_evento_json = VALUES(dados_evento_json),
                            projected_at_utc = CURRENT_TIMESTAMP(6)",
                        [$eventUid, $matchUid, $appearanceUid, $appCarreiraId, $commandUid, $tipoEvento, $seqPartida, $minutoJogo, $dadosJson]
                    );
                }
            }
        }
    }
}
