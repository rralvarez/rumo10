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
     * Carrega e mantém em cache estático o mapa positivo de PARES (rótulo de opção => desfechos válidos)
     * realmente produzidos por Game::fieldDecision() (Scenes, NarrativeCatalog, MatchMomentum,
     * fallback de Game::choices e formatos legados reconhecíveis).
     * Nunca valida produto cartesiano global entre rótulos e desfechos de opções distintas.
     *
     * @return array<string,array<string,bool>>
     */
    private static function knownPlayPrefixCatalog(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $pairs = [];
        $addPair = static function (string $label, string $outcome) use (&$pairs): void {
            $l = trim($label);
            $o = trim($outcome);
            if ($l !== '' && $o !== '') {
                $pairs[$l][$o] = true;
            }
        };

        // 1. Formatos legados curtos de ação
        foreach (['Passe', 'Chute', 'Drible', 'Desarme'] as $shortAction) {
            $addPair($shortAction, 'a intenção deu certo.');
            $addPair($shortAction, 'a intenção não teve o efeito esperado.');
            $addPair($shortAction, 'falhou.');
        }

        // 2. Fallback de Game::choices()
        foreach (['Antecipar e desarmar', 'Finalizar no canto', 'Passar para o companheiro', 'Avançar no drible', 'Chutar de longe'] as $fbLabel) {
            $addPair($fbLabel, 'a intenção deu certo.');
            $addPair($fbLabel, 'a intenção não teve o efeito esperado.');
        }

        // 3. Pares exatos de MatchMomentum::situation()
        $momentumPairs = [
            'Combinar um passe curto' => ['A combinação mantém a bola e abre espaço à frente.', 'O passe é interceptado e encerra a posse.'],
            'Avançar com a bola' => ['Você supera a aproximação e ganha espaço para concluir.', 'O marcador tira a bola e encerra a jogada.'],
            'Finalizar agora' => ['Você aproveita o espaço e marca.', 'A finalização não entra.'],
            'Recuar e reorganizar' => ['Você encontra o apoio atrás e encerra esta tentativa de avanço.', 'A devolução é cortada e a bola fica com o adversário.'],
            'Finalizar no canto' => ['Você coloca a bola no canto, fora do alcance do goleiro.', 'O goleiro fecha o canto e impede sua finalização.'],
            'Bater de primeira' => ['Você acerta o chute de primeira e vence o goleiro.', 'Você pega mal na bola e o chute de primeira sai para fora.'],
            'Tocar para o companheiro livre' => ['Seu passe encontra o companheiro livre, que finaliza para o gol.', 'O defensor chega antes do companheiro e corta seu passe.'],
            'Driblar o goleiro' => ['Você passa pelo goleiro e toca para o gol vazio.', 'O goleiro acompanha seu movimento e tira a bola antes do drible.'],
        ];
        foreach ($momentumPairs as $lbl => $outs) {
            foreach ($outs as $out) {
                $addPair($lbl, $out);
            }
        }

        // 4. Catálogo real de Scenes (situations.php, scenes.json e NarrativeCatalog) vinculado por opção
        $failures = [];
        $failuresPath = dirname(__DIR__) . '/database/scene-failures.php';
        if (is_file($failuresPath)) {
            $loaded = require $failuresPath;
            if (is_array($loaded)) {
                $failures = $loaded;
            }
        }

        $defaultSuccessByCode = static function (string $code, string $label): ?string {
            return match ($code) {
                'passe' => $label === 'Pedir a tabela'
                    ? 'Você toca para o companheiro, recebe a devolução e ganha espaço à frente.'
                    : 'Seu passe chega ao companheiro e mantém a jogada em movimento.',
                'chute' => 'Seu chute vence o goleiro.',
                'drible' => 'Você passa pelo marcador e ganha espaço para continuar a jogada.',
                'desarme' => 'Você acerta o tempo do bote e recupera a bola.',
                'tecnica' => 'Você ajusta o toque e mantém o controle da bola durante o exercício.',
                'tatico' => 'Você identifica o ajuste tático e entende como aplicá-lo na movimentação.',
                'posicionamento' => 'Você ocupa o espaço a tempo e mantém a distância necessária.',
                'visao' => 'Você percebe o movimento e identifica a melhor opção antes de decidir.',
                'forca' => 'Você mantém a postura e completa a série de força prevista.',
                'resistencia' => 'Você mantém a intensidade até o fim da atividade.',
                'velocidade' => 'Você coordena as arrancadas e sustenta o ritmo do exercício.',
                'recuperar' => 'Você cuida da recuperação sem forçar o corpo.',
                'apoio' => 'Sua disposição para ajudar é acolhida.',
                'liderar' => 'Você organiza a proposta e deixa claro como pretende ajudar.',
                'disciplina' => 'Você mantém o foco e faz o que combinou.',
                'reclamar' => 'Você é ouvido, mas a reclamação desgasta sua relação com o treinador.',
                'ousar' => 'Tomar a iniciativa aumenta sua confiança.',
                'imprensa' => 'Sua mensagem esclarece sua posição sem ampliar a discussão.',
                'lazer' => 'Você consegue relaxar durante a pausa.',
                'social' => 'Você comunica sua decisão e evita um mal-entendido.',
                default => null,
            };
        };

        $defaultFailureByCode = static function (string $code): ?string {
            return match ($code) {
                'passe' => 'Você erra a direção do passe e a bola não chega ao companheiro.',
                'chute' => 'Você pega mal na bola e o chute sai sem a precisão que precisava.',
                'drible' => 'O marcador acompanha seu movimento e fecha o caminho do drible.',
                'desarme' => 'Você chega atrasado no bote e não consegue tirar a bola do adversário.',
                'tecnica' => 'Você não ajusta o toque na bola e continua errando o movimento.',
                'tatico' => 'Você termina a análise sem entender como aplicar o ajuste na próxima jogada.',
                'posicionamento' => 'Você erra a distância que precisava manter e não consegue ocupar o espaço a tempo.',
                'visao' => 'Você não identifica o detalhe que precisava observar para decidir melhor.',
                'forca' => 'Você perde a postura durante o exercício e não consegue trabalhar a força como planejou.',
                'resistencia' => 'Você se cansa antes de manter a intensidade prevista para a atividade.',
                'velocidade' => 'Você perde o ritmo nas arrancadas e não consegue sustentar a velocidade.',
                'recuperar' => 'Você termina a pausa ainda cansado, sem a sensação de recuperação que buscava.',
                'apoio' => 'Sua tentativa de ajudar não esclarece o que a outra pessoa precisava, e a conversa termina sem resolver isso.',
                'liderar' => 'Você não consegue explicar sua orientação com clareza, e o grupo fica sem saber como seguir.',
                'disciplina' => 'Você se distrai e termina sem organizar o que precisava fazer. Isso abala sua confiança.',
                'imprensa' => 'Sua mensagem dá margem a outra interpretação e não esclarece o que você queria dizer.',
                'lazer' => 'Você continua pensando nos compromissos e não consegue relaxar durante a folga.',
                'ousar' => 'Você se precipita e não consegue executar o que tinha em mente. A tentativa abala sua confiança.',
                'reclamar' => 'A reclamação não muda a situação e a conversa termina mais tensa.',
                'social' => 'Você hesita ao ajustar a movimentação e a marcação fecha o espaço que pretendia usar.',
                default => null,
            };
        };

        if (class_exists('Scenes')) {
            foreach (Scenes::catalog() as $id => $s) {
                if (($s['stage'] ?? '') !== 'partida') {
                    continue;
                }
                if (isset($s['legacy'][2]) && is_array($s['legacy'][2])) {
                    foreach ($s['legacy'][2] as $idx => $o) {
                        $lbl = trim((string)($o[0] ?? ''));
                        if ($lbl === '') {
                            continue;
                        }
                        $addPair($lbl, 'a intenção deu certo.');
                        $fail = $failures[$id][$idx] ?? 'a intenção não teve o efeito esperado.';
                        $addPair($lbl, (string)$fail);
                        if (class_exists('SceneConsequences')) {
                            $action = match ((int)$id) {
                                7 => ['chute', 'passe', 'drible', 'social'][$idx] ?? 'social',
                                9 => ['drible', 'social', 'social', 'desarme'][$idx] ?? 'social',
                                default => 'social',
                            };
                            $enriched = SceneConsequences::option([
                                'text' => $lbl,
                                'effects' => $o[1] ?? [],
                                'failure_text' => $failures[$id][$idx] ?? null,
                                'action' => $action,
                            ], 'partida', (int)$id, (int)$idx);
                            foreach (['success_text', 'failure_text', 'exceptional_text', 'severe_failure_text'] as $k) {
                                if (!empty($enriched[$k])) {
                                    $addPair($lbl, (string)$enriched[$k]);
                                }
                            }
                        }
                    }
                } elseif (isset($s['options']) && is_array($s['options'])) {
                    foreach ($s['options'] as $idx => $o) {
                        $lbl = trim((string)($o['text'] ?? ''));
                        if ($lbl === '') {
                            continue;
                        }
                        $code = (string)($o['code'] ?? '');
                        $succ = isset($o['success_text']) ? (string)$o['success_text'] : $defaultSuccessByCode($code, $lbl);
                        if ($succ !== null) {
                            $addPair($lbl, $succ);
                        }
                        $fail = $o['failure_text'] ?? ($failures[$id][$idx] ?? $defaultFailureByCode($code));
                        if ($fail !== null) {
                            $addPair($lbl, (string)$fail);
                        }
                        foreach (['exceptional_text', 'severe_failure_text'] as $extraKey) {
                            if (!empty($o[$extraKey])) {
                                $addPair($lbl, (string)$o[$extraKey]);
                            }
                        }
                        if ((int)$id < 2000 && class_exists('SceneConsequences')) {
                            $enriched = SceneConsequences::option([
                                'text' => $lbl,
                                'code' => $code,
                                'action' => in_array($code, ['passe', 'chute', 'drible', 'desarme'], true) ? $code : 'social',
                                'effects' => [],
                                'success_text' => $succ,
                                'failure_text' => $fail,
                            ], 'partida', (int)$id, (int)$idx);
                            foreach (['success_text', 'failure_text', 'exceptional_text', 'severe_failure_text'] as $k) {
                                if (!empty($enriched[$k])) {
                                    $addPair($lbl, (string)$enriched[$k]);
                                }
                            }
                        }
                    }
                }
            }
        }

        $cache = $pairs;
        return $cache;
    }

    /**
     * Validação positiva do prefixo anterior a um evento formal em log composto.
     * Exige par exato (opção => desfecho associado) efetivamente emitido por Game::fieldDecision()
     * ou formato histórico comprovado ("75' | Disputa dura."); rejeita introduções arbitrárias
     * ("Aos <min> ...") e combinações cruzadas, mesmo quando source=engine estiver presente.
     */
    private static function isValidCompoundPlayPrefix(string $prefixBody, bool $hasLeadMinute): bool {
        $p = trim($prefixBody);
        if ($p === '' || str_contains($p, '?')) {
            return false;
        }
        $lastChar = substr($p, -1);
        if ($lastChar !== '.' && $lastChar !== '!') {
            return false;
        }

        // Formato histórico legado curto com minuto inicial explícito (ex.: "75' | Disputa dura.")
        if ($hasLeadMinute && $p === 'Disputa dura.') {
            return true;
        }

        $pairs = self::knownPlayPrefixCatalog();

        // Remove complementos finais realmente anexados por Game::fieldDecision()
        $stripEngineFollowups = static function (string $outcome): string {
            $cur = trim($outcome);
            $followups = [
                ' O companheiro marca: assistência!',
                ' Gol do seu jogador!',
                ' A bola não entrou.',
                ' Você chega atrasado e comete falta.',
            ];
            $changed = true;
            while ($changed) {
                $changed = false;
                foreach ($followups as $suffix) {
                    if (str_ends_with($cur, $suffix)) {
                        $cur = trim(substr($cur, 0, -strlen($suffix)));
                        $changed = true;
                    }
                }
                if (preg_match('/^(.+?[.!])\s+\d{1,3}\s*[\'\x{2032}]\s*\|\s*(?:Falta marcada contra você\.|Cartão amarelo! Você foi advertido pela arbitragem\.)$/u', $cur, $fm) === 1) {
                    $cur = trim($fm[1]);
                    $changed = true;
                }
            }
            return $cur;
        };

        $pos = strpos($p, ': ');
        if ($pos === false) {
            return false;
        }
        $label = trim(substr($p, 0, $pos));
        $outcome = trim(substr($p, $pos + 2));
        if ($label === '' || $outcome === '' || !isset($pairs[$label])) {
            return false;
        }
        $baseOutcome = $stripEngineFollowups($outcome);
        return isset($pairs[$label][$baseOutcome]);
    }

    /**
     * Examina uma linha de log completa e identifica TODAS as ocorrências formais candidatas
     * de saída/expulsão do protagonista, distinguindo procedência do motor de relatos arbitrários.
     *
     * @return array{kinds:array<string,bool>,subtypes:array<string,bool>,text_minutes:array<int,bool>,text_minute_conflict:bool,cause_conflict:bool,subtype_conflict:bool}|null
     */
    private static function parseFormalExitLogEntry(array $entry): ?array {
        $t = trim((string)($entry['text'] ?? ''));
        if ($t === '') {
            return null;
        }
        $trustedEngine = (($entry['source'] ?? null) === 'engine');

        $pattern = '/(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?(?:'
            . '(Você é substituído após participar da partida\.)'
            . '|(Cartão vermelho direto! Você foi expulso da partida\.)'
            . '|(Segundo cartão amarelo! Você está expulso da partida\.)'
            . '|(Expulso: segundo cartão\.)'
            . '|(Lesão: você deixa o campo\.)'
            . '|(Substituído por desgaste\.)'
            . ')/u';

        $count = preg_match_all($pattern, $t, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($count === false || $count === 0) {
            return null;
        }

        // Texto adicional após o último evento formal nunca é emitido pelo motor
        $lastMatch = $matches[$count - 1][0];
        $lastEnd = $lastMatch[1] + strlen($lastMatch[0]);
        if (trim(substr($t, $lastEnd)) !== '') {
            return null;
        }

        // Entre múltiplas ocorrências formais na mesma linha só pode haver separação em branco
        for ($i = 1; $i < $count; $i++) {
            $prevEnd = $matches[$i - 1][0][1] + strlen($matches[$i - 1][0][0]);
            $gap = substr($t, $prevEnd, $matches[$i][0][1] - $prevEnd);
            if (trim($gap) !== '') {
                return null;
            }
        }

        $firstOffset = $matches[0][0][1];
        $prefix = trim(substr($t, 0, $firstOffset));
        $leadMin = null;

        if ($prefix !== '') {
            $prefixBody = $prefix;
            if (preg_match('/^(\d{1,3})\s*[\'\x{2032}]\s*\|\s*(.+)$/u', $prefix, $pm) === 1) {
                $leadMin = (int)$pm[1];
                $prefixBody = trim($pm[2]);
            }
            if (!self::isValidCompoundPlayPrefix($prefixBody, $leadMin !== null)) {
                return null;
            }
        }

        $kinds = [];
        $subtypes = [];
        $textMinutes = [];
        if ($leadMin !== null) {
            $textMinutes[$leadMin] = true;
        }

        foreach ($matches as $m) {
            $evMinRaw = $m[1][0] ?? '';
            if ($evMinRaw !== '') {
                $textMinutes[(int)$evMinRaw] = true;
            }
            if (($m[2][0] ?? '') !== '') {
                // Substituição tática (MatchParticipation::sync) nunca é concatenada a relato de jogada
                if ($prefix !== '' && !$trustedEngine) {
                    return null;
                }
                $kinds['tactical'] = true;
            } elseif (($m[3][0] ?? '') !== '') {
                $kinds['sent_off'] = true;
                $subtypes['direct_red'] = true;
            } elseif (($m[4][0] ?? '') !== '' || ($m[5][0] ?? '') !== '') {
                $kinds['sent_off'] = true;
                $subtypes['second_yellow'] = true;
            } elseif (($m[6][0] ?? '') !== '') {
                $kinds['injury'] = true;
            } elseif (($m[7][0] ?? '') !== '') {
                $kinds['fatigue'] = true;
            }
        }

        return [
            'kinds' => $kinds,
            'subtypes' => $subtypes,
            'text_minutes' => $textMinutes,
            'text_minute_conflict' => count($textMinutes) > 1,
            'cause_conflict' => count($kinds) > 1,
            'subtype_conflict' => count($subtypes) > 1,
        ];
    }

    /**
     * Extrai evidência formal de entrada do banco em logs históricos, respeitando cronologia
     * (entradas com minuto posterior ao relógio em partida em andamento ficam pendentes).
     *
     * @return array{found:bool,minute:?int,conflict:bool,pending_future:int}
     */
    private static function extractLogEntryEvent(array $m): array {
        $result = ['found' => false, 'minute' => null, 'conflict' => false, 'pending_future' => 0];
        if (!isset($m['log']) || !is_array($m['log'])) {
            return $result;
        }

        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;
        $minutesSeen = [];

        foreach ($m['log'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $t = trim((string)($entry['text'] ?? ''));
            if (preg_match('/^(?:(\d{1,3})\s*[\'\x{2032}]\s*\|\s*)?Você entra em campo no lugar de um companheiro\.$/u', $t, $matches) !== 1) {
                continue;
            }
            $hasEntryMinKey = array_key_exists('minute', $entry) && $entry['minute'] !== null;
            $entryMin = null;
            if ($hasEntryMinKey) {
                if (!is_numeric($entry['minute'])) {
                    $result['found'] = true;
                    $result['conflict'] = true;
                    continue;
                }
                $entryMin = (int)$entry['minute'];
            }
            $textMin = ($matches[1] ?? '') !== '' ? (int)$matches[1] : null;
            if ($entryMin !== null && $textMin !== null && $entryMin !== $textMin) {
                $result['found'] = true;
                $result['conflict'] = true;
                continue;
            }
            $resolvedMin = $entryMin ?? $textMin;
            if ($resolvedMin !== null && ($resolvedMin < 0 || $resolvedMin > self::MAX_VALID_MINUTE)) {
                $result['found'] = true;
                $result['conflict'] = true;
                continue;
            }
            if ($resolvedMin !== null && $matchMinute !== null && $resolvedMin > $matchMinute) {
                if (!$isFinished) {
                    $result['pending_future']++;
                    continue;
                }
                $result['found'] = true;
                $result['conflict'] = true;
                continue;
            }

            $result['found'] = true;
            if ($resolvedMin !== null) {
                $minutesSeen[$resolvedMin] = true;
            }
        }

        if (count($minutesSeen) > 1) {
            $result['conflict'] = true;
        } elseif (count($minutesSeen) === 1 && !$result['conflict']) {
            $result['minute'] = (int)array_key_first($minutesSeen);
        }

        return $result;
    }

    /**
     * Evidência formal ativa em log de entrada em campo vinda do banco (MatchParticipation::sync).
     */
    private static function hasSubInLog(array $m): bool {
        $ev = self::extractLogEntryEvent($m);
        return $ev['found'] && !$ev['conflict'];
    }

    /**
     * Reconciliação canônica do minuto efetivo de entrada em campo, separando explicitamente
     * janela planejada (entry) de entrada comprovada (transição canônica ou log histórico).
     *
     * @return array{status:string,minute:?int,source:string,pending_future:int}
     */
    public static function resolveEntry(array $m): array {
        $state = (string)($m['participation_state'] ?? '');
        $lineup = (string)($m['lineup'] ?? '');
        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;
        $entry = isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : null;

        $logEntry = self::extractLogEntryEvent($m);

        if (in_array($state, [self::STATE_SUSPENDED, self::STATE_NOT_SELECTED, self::STATE_ON_BENCH, self::STATE_STARTING], true)
            || in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado'], true)
        ) {
            return ['status' => 'none', 'minute' => null, 'source' => 'none', 'pending_future' => $logEntry['pending_future']];
        }

        if ($state === '' && !in_array($lineup, ['titular', 'reserva', 'banco'], true)) {
            return ['status' => 'none', 'minute' => null, 'source' => 'none', 'pending_future' => $logEntry['pending_future']];
        }

        if ($logEntry['conflict']) {
            return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
        }

        $hasSubInRecorded = !empty($m['sub_in_recorded']);
        $hasParticipationFlag = !empty($m['entered_on_field']) || !empty($m['participated_on_field']);

        // Titular com entrada 0
        if ($lineup === 'titular' && $entry === 0) {
            if ($logEntry['found'] && $logEntry['minute'] !== null && $logEntry['minute'] !== 0) {
                return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
            }
            return ['status' => 'valid', 'minute' => 0, 'source' => 'starter', 'pending_future' => $logEntry['pending_future']];
        }

        // Reserva (ou estado canônico com entry > 0): distingue janela planejada (entry) de entrada efetiva.
        // sub_in_recorded comprova transição estruturada no minuto entry; entered_on_field/participated_on_field
        // comprovam atuação em campo sem substituir o minuto de entrada registrado em log formal.
        if ($hasSubInRecorded && $logEntry['found']) {
            if ($entry !== null && $logEntry['minute'] !== null && $entry !== $logEntry['minute']) {
                return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
            }
            $min = $logEntry['minute'] ?? $entry;
            if ($min === null || $min < 0 || $min >= 90) {
                return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
            }
            if (!$isFinished && $matchMinute !== null && $min > $matchMinute) {
                return ['status' => 'none', 'minute' => null, 'source' => 'none', 'pending_future' => $logEntry['pending_future'] + 1];
            }
            return ['status' => 'valid', 'minute' => $min, 'source' => 'both', 'pending_future' => $logEntry['pending_future']];
        }

        if ($logEntry['found']) {
            $min = $logEntry['minute'] ?? $entry;
            if ($min === null || $min < 0 || $min >= 90) {
                return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
            }
            return ['status' => 'valid', 'minute' => $min, 'source' => 'log', 'pending_future' => $logEntry['pending_future']];
        }

        if ($hasSubInRecorded || $hasParticipationFlag) {
            if ($entry === null || $entry < 0 || $entry >= 90) {
                return ['status' => 'conflict', 'minute' => null, 'source' => 'conflict', 'pending_future' => $logEntry['pending_future']];
            }
            if (!$isFinished && $matchMinute !== null && $entry > $matchMinute) {
                return ['status' => 'none', 'minute' => null, 'source' => 'none', 'pending_future' => $logEntry['pending_future'] + 1];
            }
            return ['status' => 'valid', 'minute' => $entry, 'source' => 'structured', 'pending_future' => $logEntry['pending_future']];
        }

        return ['status' => 'none', 'minute' => null, 'source' => 'none', 'pending_future' => $logEntry['pending_future']];
    }

    /**
     * Extrai evento formal e verificável de saída/expulsão do protagonista a partir dos logs históricos.
     * Aplica validação cronológica para todos os minutos (eventos futuros em partida em andamento
     * ficam pendentes sem produzir efeito antecipado) e detecta conflitos de causa, subtipo e horário.
     */
    private static function extractLogExitEvent(array $m): array {
        $result = [
            'found' => false,
            'kind' => null,
            'subtype' => null,
            'minute' => null,
            'conflict' => false,
            'cause_conflict' => false,
            'subtype_conflict' => false,
            'time_conflict' => false,
            'pending_future' => 0,
        ];
        if (!isset($m['log']) || !is_array($m['log'])) {
            return $result;
        }

        $isFinished = (($m['status'] ?? '') === 'finalizada');
        $matchMinute = isset($m['minute']) && is_numeric($m['minute']) ? (int)$m['minute'] : null;
        $kindsSeen = [];
        $subtypesSeen = [];
        $minutesSeen = [];

        foreach ($m['log'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $parsed = self::parseFormalExitLogEntry($entry);
            if ($parsed === null) {
                continue;
            }

            $hasEntryMinKey = array_key_exists('minute', $entry) && $entry['minute'] !== null;
            $entryMin = null;
            $invalidMin = false;
            if ($hasEntryMinKey) {
                if (!is_numeric($entry['minute'])) {
                    $invalidMin = true;
                } else {
                    $entryMin = (int)$entry['minute'];
                    if ($entryMin <= 0 || $entryMin > self::MAX_VALID_MINUTE) {
                        $invalidMin = true;
                    }
                }
            }

            $textMin = null;
            if (!$parsed['text_minute_conflict'] && count($parsed['text_minutes']) === 1) {
                $textMin = (int)array_key_first($parsed['text_minutes']);
                if ($textMin <= 0 || $textMin > self::MAX_VALID_MINUTE) {
                    $invalidMin = true;
                }
            } elseif (count($parsed['text_minutes']) > 1) {
                foreach (array_keys($parsed['text_minutes']) as $tm) {
                    if ($tm <= 0 || $tm > self::MAX_VALID_MINUTE) {
                        $invalidMin = true;
                    }
                }
            }

            $entryTextMismatch = ($entryMin !== null && $textMin !== null && $entryMin !== $textMin);
            $resolvedMin = (!$invalidMin && !$parsed['text_minute_conflict'] && !$entryTextMismatch)
                ? ($entryMin ?? $textMin)
                : null;

            // Validação cronológica geral: evento futuro com partida em andamento não produz efeito antecipado
            if ($resolvedMin !== null && $matchMinute !== null && $resolvedMin > $matchMinute && !$isFinished) {
                $result['pending_future']++;
                continue;
            }

            $result['found'] = true;
            foreach ($parsed['kinds'] as $k => $_) {
                $kindsSeen[$k] = true;
            }
            foreach ($parsed['subtypes'] as $st => $_) {
                $subtypesSeen[$st] = true;
            }
            if ($parsed['cause_conflict']) {
                $result['cause_conflict'] = true;
            }
            if ($parsed['subtype_conflict']) {
                $result['subtype_conflict'] = true;
            }
            if ($invalidMin || $parsed['text_minute_conflict'] || $entryTextMismatch) {
                $result['time_conflict'] = true;
                continue;
            }

            if ($resolvedMin !== null) {
                if ($isFinished && $matchMinute !== null && $resolvedMin > $matchMinute) {
                    $result['time_conflict'] = true;
                    continue;
                }
                $minutesSeen[$resolvedMin] = true;
            }
        }

        if (count($kindsSeen) > 1) {
            $result['cause_conflict'] = true;
        } elseif (count($kindsSeen) === 1 && !$result['cause_conflict']) {
            $result['kind'] = (string)array_key_first($kindsSeen);
        }

        if (count($subtypesSeen) > 1) {
            $result['subtype_conflict'] = true;
        } elseif (count($subtypesSeen) === 1 && !$result['subtype_conflict']) {
            $result['subtype'] = (string)array_key_first($subtypesSeen);
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
     * Reconciliação canônica única de saída de campo, subtipo disciplinar e conflitos
     * entre dados estruturados e logs formais. Fonte única consumida por motor, disciplina,
     * narrativa e interface (sem recursão).
     *
     * @return array{status:string,cause_conflict:bool,time_conflict:bool,subtype_conflict:bool,kind:?string,subtype:?string,minute:?int,pending_future:int}
     */
    public static function resolveExit(array $m): array {
        $state = (string)($m['participation_state'] ?? '');
        $exitReason = (string)($m['exit_reason'] ?? '');
        $lineup = (string)($m['lineup'] ?? '');
        $st = is_array($m['stats'] ?? null) ? $m['stats'] : [];

        $hasDirectRedStruct = (int)($st['direct_red'] ?? 0) > 0;
        $hasSecondYellowStruct = (int)($st['second_yellow'] ?? 0) > 0
            || (int)($st['yellow_cards'] ?? 0) >= 2
            || (!array_key_exists('red_cards', $st) && (int)($st['cards'] ?? 0) >= 2);

        $hasStructuredExpulsion = $state === self::STATE_SENT_OFF
            || $exitReason === 'sent_off'
            || !empty($m['red_card_minute'])
            || (int)($st['red_cards'] ?? 0) > 0
            || $hasDirectRedStruct
            || $hasSecondYellowStruct
            || (int)($st['cards_red'] ?? 0) > 0;

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
                'subtype_conflict' => false,
                'kind' => null,
                'subtype' => null,
                'minute' => null,
                'pending_future' => $logExit['pending_future'],
                'has_structured_expulsion' => false,
                'structured_subtype' => null,
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

        // Reconciliação de subtipo disciplinar (vermelho direto vs segundo amarelo)
        $subtypeConflict = $logExit['subtype_conflict'];
        $subtype = null;
        if (!$causeConflict && $kind === 'sent_off') {
            $subtypes = [];
            if ($hasDirectRedStruct) {
                $subtypes['direct_red'] = true;
            }
            if ($hasSecondYellowStruct) {
                $subtypes['second_yellow'] = true;
            }
            if ($logExit['subtype'] !== null) {
                $subtypes[$logExit['subtype']] = true;
            }
            if (count($subtypes) > 1) {
                $subtypeConflict = true;
            } elseif (count($subtypes) === 1 && !$subtypeConflict) {
                $subtype = (string)array_key_first($subtypes);
            }
        }

        $timeConflict = $logExit['time_conflict'];
        $entryInfo = self::resolveEntry($m);
        $entry = ($entryInfo['status'] === 'valid' && $entryInfo['minute'] !== null)
            ? $entryInfo['minute']
            : (isset($m['entry']) && is_numeric($m['entry']) ? (int)$m['entry'] : null);
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

        $structuredSubtype = null;
        if ($hasDirectRedStruct && !$hasSecondYellowStruct) {
            $structuredSubtype = 'direct_red';
        } elseif ($hasSecondYellowStruct && !$hasDirectRedStruct) {
            $structuredSubtype = 'second_yellow';
        }

        return [
            'status' => ($causeConflict || $timeConflict) ? 'conflict' : 'valid',
            'cause_conflict' => $causeConflict,
            'time_conflict' => $timeConflict,
            'subtype_conflict' => $subtypeConflict,
            'kind' => $causeConflict ? null : $kind,
            'subtype' => ($causeConflict || $subtypeConflict) ? null : $subtype,
            'minute' => ($causeConflict || $timeConflict) ? null : $resolvedMin,
            'pending_future' => $logExit['pending_future'],
            'has_structured_expulsion' => $hasStructuredExpulsion,
            'structured_subtype' => $structuredSubtype,
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

        // Checagem de entrada em campo do banco (consulta resolveEntry antes de promover ON_BENCH -> ON_FIELD)
        if (!$isFinished && $state === self::STATE_ON_BENCH && $validWindow) {
            $mAtMinute = $m;
            $mAtMinute['minute'] = $minute;
            unset($mAtMinute['participation_state']);
            $entryInfo = self::resolveEntry($mAtMinute);

            // Caso C: conflito real entre entradas comprovadas — bloqueio conservador sem promover nem fabricar log
            if ($entryInfo['status'] === 'conflict') {
                $m['participation_state'] = self::STATE_ON_BENCH;
                return;
            }

            // Caso A (antes do minuto efetivo): entrada formal futura pendente — mantém no banco sem novo log nem sub_in_recorded
            if ($entryInfo['pending_future'] > 0 && $entryInfo['status'] !== 'valid') {
                $m['participation_state'] = self::STATE_ON_BENCH;
                return;
            }

            // Caso A (alcançou ou atravessou o minuto efetivo documentado em log formal): promove uma única vez preservando o log original
            if ($entryInfo['status'] === 'valid' && $entryInfo['source'] === 'log' && $entryInfo['minute'] !== null) {
                $effEntry = $entryInfo['minute'];
                if ($minute >= $effEntry && $effEntry < $exit) {
                    $m['participation_state'] = self::STATE_ON_FIELD;
                    $m['entered_on_field'] = true;
                    if ($effEntry === $entry && empty($m['sub_in_recorded'])) {
                        $m['sub_in_recorded'] = true;
                    }
                    $hasSubInSound = false;
                    foreach (($m['sound_events'] ?? []) as $sev) {
                        if (($sev['type'] ?? '') === 'PLAYER_SUBSTITUTION_IN') {
                            $hasSubInSound = true;
                            break;
                        }
                    }
                    if (!$hasSubInSound) {
                        Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_IN', $effEntry);
                    }
                } else {
                    $m['participation_state'] = self::STATE_ON_BENCH;
                }
                return;
            }

            // Caso B: reserva novo programado pelo motor (sem entrada futura nem conflito histórico)
            if ($minute >= $entry && $minute < $exit) {
                $m['participation_state'] = self::STATE_ON_FIELD;
                $m['entered_on_field'] = true;
                if (empty($m['sub_in_recorded'])) {
                    $m['sub_in_recorded'] = true;
                    $m['log'][] = ['minute' => $entry, 'text' => "{$entry}' | Você entra em campo no lugar de um companheiro.", 'source' => 'engine'];
                    Game::emitSoundEvent($m, $g, 'PLAYER_SUBSTITUTION_IN', $entry);
                }
                return;
            }
        }

        // Checagem de saída por substituição (idempotente)
        if ($state === self::STATE_ON_FIELD && $exit !== null && $exit > 0 && $exit < 90 && $minute >= $exit) {
            $m['participation_state'] = self::STATE_SUBSTITUTED_OUT;
            if (empty($m['exit_reason'])) {
                $m['exit_reason'] = 'tactical';
            }
            if (empty($m['sub_out_recorded'])) {
                $m['sub_out_recorded'] = true;
                $m['log'][] = ['minute' => $exit, 'text' => "{$exit}' | Você é substituído após participar da partida.", 'source' => 'engine'];
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

        $entryInfo = self::resolveEntry($m);
        if ($entryInfo['status'] === 'conflict') {
            return 0;
        }
        $effectiveEntry = ($entryInfo['status'] === 'valid' && $entryInfo['minute'] !== null) ? $entryInfo['minute'] : $entry;
        if ($effectiveEntry < 0 || $effectiveEntry >= 90 || $exit < $effectiveEntry) {
            return 0;
        }

        $exitInfo = self::resolveExit($m);

        // Saída confirmada ou conflito de saída impede presumir o horário planejado (90') como horário real.
        // Utiliza exclusivamente a reconciliação canônica (resolveExit): conflito de causa/horário ou minuto ausente retorna 0.
        if ($exitInfo['status'] !== 'none') {
            $entryDemonstrated = ($lineup === 'titular' && $effectiveEntry === 0) || self::hasEntryEvidence($m);
            if (!$entryDemonstrated) {
                return 0;
            }

            if ($exitInfo['status'] !== 'valid' || $exitInfo['minute'] === null) {
                return 0;
            }

            $effectiveExit = $exitInfo['minute'];
            $raw = $effectiveExit - $effectiveEntry;
            if ($raw > 0) {
                return $raw;
            }
            if ($raw < 0) {
                return 0;
            }
            return self::hasEntryEvidence($m) ? 1 : 0;
        }

        $raw = $exit - $effectiveEntry;
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

        $entryInfo = self::resolveEntry($m);
        if ($entryInfo['status'] === 'conflict' || $entryInfo['pending_future'] > 0) {
            return false;
        }

        $lineup = (string)($m['lineup'] ?? '');
        if (in_array($lineup, ['suspenso', 'não relacionado', 'nao_relacionado', 'substituído'], true)) {
            return false;
        }

        if (!isset($m['entry'], $m['exit']) || !is_numeric($m['entry']) || !is_numeric($m['exit'])) {
            return false;
        }

        $entry = ($entryInfo['status'] === 'valid' && $entryInfo['minute'] !== null) ? $entryInfo['minute'] : (int)$m['entry'];
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

        $entryInfo = self::resolveEntry($m);
        if ($entryInfo['status'] === 'conflict' || $entryInfo['pending_future'] > 0) {
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
        $entry = ($entryInfo['status'] === 'valid' && $entryInfo['minute'] !== null) ? $entryInfo['minute'] : (int)$m['entry'];
        $exit = (int)$m['exit'];

        if ($entry < 0 || $entry >= 90 || $exit <= $entry || $exit > self::MAX_VALID_MINUTE) {
            return false;
        }

        // Elegibilidade esportiva estrita: nunca autoriza decisão quando minute < entry efetivo ou minute >= exit
        if ($minute < $entry || $minute >= $exit) {
            return false;
        }

        $state = $m['participation_state'] ?? null;
        $hasCanonicalState = is_string($state) && $state !== '';

        if ($hasCanonicalState) {
            return $state === self::STATE_ON_FIELD;
        }

        // Reconciliação explícita para saves legados sem participation_state:
        // exige escalação explicitamente reconhecida (titular ou reserva com entrada comprovada e já ocorrida).
        if ($lineup === 'titular') {
            return true;
        }
        if (in_array($lineup, ['reserva', 'banco'], true)) {
            return $entryInfo['status'] === 'valid';
        }

        return false;
    }

    public static function isOnField(array $m): bool {
        return self::canActOnField($m);
    }
}

