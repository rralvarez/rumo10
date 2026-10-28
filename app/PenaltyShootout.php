<?php
declare(strict_types=1);

/**
 * Motor centralizado de Disputa de Pênaltis do Rumo10.
 * Suporta 5 cobranças alternadas, encerramento antecipado, morte súbita,
 * participação do protagonista (linha e goleiro) e simulação orientada por força.
 */
final class PenaltyShootout {

    /**
     * Verifica se uma partida (ou agregado) terminada em empate exige disputa de pênaltis.
     */
    public static function requiresShootout(array $g, array $m): bool {
        if (($m['status'] ?? '') !== 'finalizada') return false;

        // Se for jogo de volta de confronto eliminatório
        $agg = Competition::aggregateContext($g, $m);
        if ($agg !== null) {
            return !empty($agg['is_tied']) && ($agg['rule'] ?? '') === 'penalties' && empty($m['penalties']);
        }

        // Se for jogo único eliminatório (fase de mata-mata com 1 perna)
        $phase = $m['phase'] ?? '';
        if ($phase && $phase !== 'liga' && $phase !== 'quadrangular' && (int)($m['hg'] ?? 0) === (int)($m['ag'] ?? 0)) {
            $cid = $m['competition'] ?? ($g['competition_id'] ?? null);
            $t = $cid && isset($g['competitions'][$cid]) ? $g['competitions'][$cid] : [];
            $legs=array_filter($g['matches']??[],static fn($x)=>($x['phase']??'')===$phase && (($x['home']===$m['home']&&$x['away']===$m['away'])||($x['home']===$m['away']&&$x['away']===$m['home'])));
            if(count($legs)>1)return false; // First leg cannot decide a two-legged tie.
            $tieDef = null;
            if (isset($m['tie']) && isset($t['ties'][$m['tie']])) $tieDef = $t['ties'][$m['tie']];
            elseif (!empty($t['ties'])) {
                foreach ($t['ties'] as $cand) {
                    if (($cand['a'] === $m['home'] && $cand['b'] === $m['away']) || ($cand['a'] === $m['away'] && $cand['b'] === $m['home'])) {
                        $tieDef = $cand; break;
                    }
                }
            }
            $rule = $tieDef['rule'] ?? 'penalties';
            return $rule === 'penalties' && empty($m['penalties']);
        }

        return false;
    }

    /**
     * Inicializa o estado persistente da disputa de pênaltis.
     */
    public static function start(array &$g, array &$m): array {
        $fixtureId=($m['round']??1).':'.$m['home'].':'.$m['away'];
        if(isset($g['shootout'])&&($g['shootout']['id']??'')===$fixtureId)return $g['shootout'];
        if(($g['shootout']['status']??'')==='in_progress')throw new LogicException('Outra disputa ainda pendente.');
        $home = $m['home'];
        $away = $m['away'];
        $playerClub = $g['club'] ?? '';

        // Sorteio de quem bate primeiro (50% de chance)
        $firstTeam = random_int(0, 1) === 0 ? $home : $away;
        $secondTeam = $firstTeam === $home ? $away : $home;

        // Participação do protagonista
        $onPitch = (($m['entry'] ?? 0) <= 90) && (($m['exit'] ?? 0) === 0 || ($m['exit'] ?? 0) >= 90) && empty($m['stats']['cards_red']);
        $isGk = ($g['position'] ?? '') === 'goleiro';

        // Jogador de linha bate na 5ª cobrança (decisiva) se estiver em campo
        $protagonistRound = 5;
        $protagonistEligible = $onPitch;

        $shootout = [
            'id' => ($m['round'] ?? 1).':'.$home.':'.$away,
            'match_round' => $m['round'] ?? 1,
            'home' => $home,
            'away' => $away,
            'player_club' => $playerClub,
            'first_team' => $firstTeam,
            'second_team' => $secondTeam,
            'current_round' => 1,
            'current_turn_team' => $firstTeam,
            'kicks_taken' => 0,
            'home_kicks_count' => 0,
            'away_kicks_count' => 0,
            'score' => [
                $home => 0,
                $away => 0,
            ],
            'kicks' => [],
            'status' => 'in_progress', // 'in_progress' | 'completed'
            'winner' => null,
            'protagonist_eligible' => $protagonistEligible,
            'protagonist_is_gk' => $isGk,
            'protagonist_kick_round' => $protagonistRound,
            'protagonist_acted' => false,
            'last_kick_feedback' => null,
            'balance_version' => class_exists('BalanceService') ? (BalanceService::activeBalance()['versao'] ?? 1) : 1,
        ];

        $g['shootout'] = $shootout;
        $m['shootout'] = $shootout;
        return $shootout;
    }

    /**
     * Verifica se a cobrança atual requer ação direta do protagonista.
     */
    public static function isProtagonistTurn(array $g): bool {
        $st = $g['shootout'] ?? null;
        if (!$st || ($st['status'] ?? '') !== 'in_progress') return false;
        if (empty($st['protagonist_eligible'])) return false;

        $currentTeam = $st['current_turn_team'];
        $playerClub = $st['player_club'];
        $isGk = !empty($st['protagonist_is_gk']);
        $round = (int)$st['current_round'];

        if ($isGk) {
            // Goleiro participa em todas as cobranças do adversário
            return $currentTeam !== $playerClub;
        }

        // Jogador de linha bate na sua rodada prevista (5ª rodada, ou na morte súbita se aplicável)
        if ($currentTeam === $playerClub && $round === (int)$st['protagonist_kick_round'] && empty($st['protagonist_acted'])) {
            return true;
        }

        return false;
    }

    /**
     * Retorna as 4 opções de decisão do protagonista com cálculo de probabilidades distintas.
     */
    public static function choices(array $g): array {
        $st = $g['shootout'] ?? [];
        $isGk = !empty($st['protagonist_is_gk']);
        $params = class_exists('BalanceService') ? BalanceService::params('match.penalties.parameters') : [];

        $base = (float)($params['base_conversion'] ?? 0.75);
        $wFin = (float)($params['w_finishing'] ?? 0.0035);
        $wStr = (float)($params['w_strength'] ?? 0.0020);
        $wGkPos = (float)($params['w_gk_pos'] ?? 0.0030);
        $wConf = (float)($params['w_confidence'] ?? 0.0020);
        $pPressure = (float)($params['pressure_penalty'] ?? 0.05);
        $pFatigue = (float)($params['fatigue_penalty'] ?? 0.0010);

        $fin = (float)($g['skills']['finalizacao']['value'] ?? 50);
        $str = (float)($g['skills']['forca']['value'] ?? 50);
        $vis = (float)($g['skills']['visao_jogo']['value'] ?? 50);
        $pos = (float)($g['skills']['posicionamento']['value'] ?? 50);
        $conf = (float)($g['states']['confianca'] ?? 50);
        $energy = (float)($g['states']['energia'] ?? 100);

        $fatigueMod = -max(0.0, (100.0 - $energy) * $pFatigue);
        $confMod = ($conf - 50.0) * $wConf;
        $isDecisive = ((int)($st['current_round'] ?? 1) >= 5);
        $pressureMod = $isDecisive ? -$pPressure : 0.0;

        if ($isGk) {
            // Opções defensivas para protagonista Goleiro
            $baseSave = 1.0 - $base; // ~0.25 base
            $pA = self::clampChance(($baseSave + ($str - 50) * 0.003 + ($pos - 50) * $wGkPos + $confMod + $fatigueMod + 0.06) * 100.0);
            $pB = self::clampChance(($baseSave + ($str - 50) * 0.003 + ($pos - 50) * $wGkPos + $confMod + $fatigueMod + 0.06) * 100.0);
            $pC = self::clampChance(($baseSave + ($vis - 50) * 0.004 + $confMod + 0.03) * 100.0);
            $pD = self::clampChance(($baseSave + ($pos - 50) * 0.005 + ($conf - 50) * 0.004 + $pressureMod + 0.08) * 100.0);

            return [
                [
                    'text' => 'Cair no canto esquerdo com impulsão máxima',
                    'action' => 'save_left',
                    'chance' => $pA,
                    'description' => 'Aposta firme no canto esquerdo com explosão física.',
                ],
                [
                    'text' => 'Cair no canto direito com impulsão máxima',
                    'action' => 'save_right',
                    'chance' => $pB,
                    'description' => 'Aposta firme no canto direito com explosão física.',
                ],
                [
                    'text' => 'Ficar no centro e esperar até o último instante',
                    'action' => 'save_center',
                    'chance' => $pC,
                    'description' => 'Evita o salto precoce, cobrindo chutes no meio e cavadinhas.',
                ],
                [
                    'text' => 'Tentar antecipar a jogada pela postura do cobrador',
                    'action' => 'anticipate',
                    'chance' => $pD,
                    'description' => 'Leitura tática da corrida; se acertar a leitura, defesa quase certa.',
                ],
            ];
        }

        // Opções ofensivas para jogador de linha
        $pA = self::clampChance(($base + ($fin - 50) * $wFin + ($vis - 50) * 0.0015 + $confMod + $fatigueMod + $pressureMod + 0.02) * 100.0);
        $pB = self::clampChance(($base + ($fin - 50) * $wFin + ($vis - 50) * 0.0015 + $confMod + $fatigueMod + $pressureMod + 0.02) * 100.0);
        $pC = self::clampChance(($base + ($str - 50) * $wStr + ($fin - 50) * 0.0020 + $fatigueMod + $pressureMod + 0.03) * 100.0);
        $pD = self::clampChance((($base - 0.10) + ($conf - 50) * 0.0070 + ($fin - 50) * 0.0025 + $fatigueMod + ($pressureMod * 1.5) + 0.05) * 100.0);

        return [
            [
                'text' => 'Finalizar colocado no canto esquerdo',
                'action' => 'left',
                'chance' => $pA,
                'description' => 'Chute com curva e precisão, longe do alcance do goleiro.',
            ],
            [
                'text' => 'Finalizar colocado no canto direito',
                'action' => 'right',
                'chance' => $pB,
                'description' => 'Chute com chapa de pé mirando a bochecha da rede.',
            ],
            [
                'text' => 'Bater firme, priorizando potência',
                'action' => 'power',
                'chance' => $pC,
                'description' => 'Pancada no meio-alto; mesmo se o goleiro tocar, a bola entra pela força.',
            ],
            [
                'text' => 'Tentar uma cobrança de maior risco (cavadinha)',
                'action' => 'risk',
                'chance' => $pD,
                'description' => 'Toque sutil pelo centro; desmoralizante se entrar, arriscada sob pressão.',
            ],
        ];
    }

    /**
     * Executa a decisão do protagonista.
     */
    public static function decide(array &$g, int $choiceIndex): array {
        if (!isset($g['shootout']) || $g['shootout']['status'] !== 'in_progress') {
            throw new RuntimeException('Nenhuma disputa de pênaltis ativa.');
        }

        $choices = self::choices($g);
        if (!isset($choices[$choiceIndex])) {
            throw new InvalidArgumentException('Opção de pênalti inválida.');
        }

        if (!self::isProtagonistTurn($g)) throw new InvalidArgumentException('Aguarde a vez do protagonista.');
        $choice = $choices[$choiceIndex];
        $st = &$g['shootout'];
        $isGk = !empty($st['protagonist_is_gk']);
        $currentTeam = $st['current_turn_team'];
        $roll = (random_int(1, 10000) / 100.0);
        $success = ($roll <= $choice['chance']);

        if ($isGk) {
            // Protagonista goleiro: sucesso significa DEFESA!
            $scored = !$success;
            $kickerName = 'Cobrador do '.ClubBadge::name($currentTeam);
            $desc = $success
                ? 'DEFESA ESPETACULAR! Você acertou a escolha e espalmou a cobrança!'
                : 'Gol do adversário. A bola foi indefensável no canto.';
        } else {
            // Protagonista cobrador: sucesso significa GOL!
            $scored = $success;
            $kickerName = $g['name'];
            $desc = $success
                ? 'GOL! Você bateu com categoria e converteu sua cobrança!'
                : 'NA TRAVE / DEFENDEU! A cobrança não entrou.';
            $st['protagonist_acted'] = true;
        }

        self::recordKick($st, $currentTeam, $kickerName, true, $scored, $desc, $choice['action']);

        // Efeitos imediatos no protagonista
        if (!$isGk) {
            if ($scored) {
                $g['states']['confianca'] = min(100, ($g['states']['confianca'] ?? 50) + 5);
                $g['states']['moral'] = min(100, ($g['states']['moral'] ?? 50) + 3);
            } else {
                $g['states']['confianca'] = max(0, ($g['states']['confianca'] ?? 50) - 5);
                $g['states']['foco'] = max(0, ($g['states']['foco'] ?? 50) - 3);
            }
        } else {
            if ($success) {
                $g['states']['confianca'] = min(100, ($g['states']['confianca'] ?? 50) + 6);
                $g['states']['moral'] = min(100, ($g['states']['moral'] ?? 50) + 4);
            }
        }

        self::checkTermination($st);
        return $st;
    }

    /**
     * Avança exatamente uma cobrança de atleta virtual.
     */
    public static function advanceOneKick(array &$g): array {
        if (!isset($g['shootout']) || $g['shootout']['status'] !== 'in_progress') {
            return $g['shootout'] ?? [];
        }
        if (self::isProtagonistTurn($g)) {
            return $g['shootout'];
        }
        self::simulateNextKick($g);
        self::checkTermination($g['shootout']);
        return $g['shootout'];
    }

    /**
     * Avança cobranças de outros atletas até a vez do protagonista ou conclusão.
     */
    public static function advanceUntilProtagonistOrEnd(array &$g): array {
        if (!isset($g['shootout']) || $g['shootout']['status'] !== 'in_progress') {
            return $g['shootout'] ?? [];
        }

        $guard = 0;
        while ($g['shootout']['status'] === 'in_progress' && !self::isProtagonistTurn($g) && $guard++ < 24) {
            self::simulateNextKick($g);
            self::checkTermination($g['shootout']);
        }

        return $g['shootout'];
    }

    /**
     * Simula uma cobrança individual de um atleta virtual.
     */
    private static function simulateNextKick(array &$g): void {
        $st = &$g['shootout'];
        $team = $st['current_turn_team'];
        $params = class_exists('BalanceService') ? BalanceService::params('match.penalties.parameters') : [];
        $base = (float)($params['base_conversion'] ?? 0.75);
        $wStrength = (float)($params['virtual_kicker_strength_weight'] ?? 0.0025);

        $teamRating = class_exists('TeamStrength') ? TeamStrength::rating($team, $g) : 65.0;
        $chance = self::clampChance(($base + ($teamRating - 65.0) * $wStrength) * 100.0);

        $roll = random_int(1, 10000) / 100.0;
        $scored = ($roll <= $chance);

        $kickerNum = (count($st['kicks']) % 11) + 1;
        $kickerName = 'Jogador '.$kickerNum.' ('.ClubBadge::name($team).')';
        $desc = $scored
            ? 'Gol de '.ClubBadge::name($team).': bola de um lado, goleiro do outro.'
            : 'Perdeu! Cobrança defendida pelo goleiro!';

        self::recordKick($st, $team, $kickerName, false, $scored, $desc, null);
    }

    /**
     * Registra o resultado de uma cobrança no histórico e avança o turno.
     */
    private static function recordKick(array &$st, string $team, string $kickerName, bool $isProtagonist, bool $scored, string $desc, ?string $choice): void {
        if ($scored) {
            $st['score'][$team]++;
        }

        if ($team === $st['home']) {
            $st['home_kicks_count']++;
        } else {
            $st['away_kicks_count']++;
        }

        $st['kicks_taken']++;
        $st['kicks'][] = [
            'team' => $team,
            'kicker' => $kickerName,
            'is_protagonist' => $isProtagonist,
            'scored' => $scored,
            'description' => $desc,
            'choice' => $choice,
            'round' => $st['current_round'],
        ];

        $st['last_kick_feedback'] = [
            'team' => $team,
            'kicker' => $kickerName,
            'scored' => $scored,
            'text' => $desc,
        ];

        // Alternância de turno
        if ($team === $st['first_team']) {
            $st['current_turn_team'] = $st['second_team'];
        } else {
            // Concluiu uma rodada completa de cobranças
            $st['current_turn_team'] = $st['first_team'];
            $st['current_round']++;
        }
    }

    /**
     * Aplica o regulamento esportivo oficial (IFAB):
     * - Encerramento antecipado quando uma equipe não pode mais alcançar.
     * - Morte súbita quando empatado após 5 cobranças de cada equipe.
     */
    public static function checkTermination(array &$st): void {
        $first = $st['first_team'];
        $second = $st['second_team'];
        $s1 = $st['score'][$first];
        $s2 = $st['score'][$second];
        $c1 = ($first === $st['home']) ? $st['home_kicks_count'] : $st['away_kicks_count'];
        $c2 = ($second === $st['home']) ? $st['home_kicks_count'] : $st['away_kicks_count'];

        // Fase inicial de 5 cobranças
        if ($c1 <= 5 && $c2 <= 5) {
            $rem1 = 5 - $c1;
            $rem2 = 5 - $c2;

            if ($s1 > $s2 + $rem2) {
                $st['status'] = 'completed';
                $st['winner'] = $first;
                return;
            }
            if ($s2 > $s1 + $rem1) {
                $st['status'] = 'completed';
                $st['winner'] = $second;
                return;
            }

            // Se ambos completaram 5 cobranças
            if ($c1 === 5 && $c2 === 5) {
                if ($s1 !== $s2) {
                    $st['status'] = 'completed';
                    $st['winner'] = ($s1 > $s2) ? $first : $second;
                    return;
                }
                // Se empatou em 5 cobranças, continua para morte súbita
            }
            return;
        }

        // Morte súbita: cobranças alternadas (após cada time cobrar igual número de vezes >= 6)
        if ($c1 === $c2 && $c1 >= 6) {
            if ($s1 !== $s2) {
                $st['status'] = 'completed';
                $st['winner'] = ($s1 > $s2) ? $first : $second;
                return;
            }
        }
    }

    /**
     * Conclui a disputa, persiste placar próprio e libera avanço de fase.
     */
    public static function complete(array &$g): void {
        $st = &$g['shootout'];
        if (($st['status'] ?? '') !== 'completed' || !in_array($st['winner'] ?? null, [$st['home'], $st['away']], true)) throw new LogicException('Disputa ainda nao concluida.');
        if (!empty($st['applied'])) { $g['stage']='fim_semana'; return; }
        $winner = $st['winner'];
        $homeScore = $st['score'][$st['home']];
        $awayScore = $st['score'][$st['away']];

        // Atualiza a partida atual
        $i = null;
        try {
            $i = Game::currentIndex($g);
        } catch (\Throwable) {
            foreach ($g['matches'] ?? [] as $idx => $cand) {
                if (($cand['home'] ?? '') === $st['home'] && ($cand['away'] ?? '') === $st['away']) {
                    $i = $idx;
                    break;
                }
            }
        }

        if ($i !== null && isset($g['matches'][$i])) {
            $m = &$g['matches'][$i];
            $m['penalties'] = [$homeScore, $awayScore];
            $m['shootout'] = $st;
            $m['log'][] = [
                'minute' => 90,
                'text' => 'Disputa de pênaltis: '.$m['home'].' '.$homeScore.' × '.$awayScore.' '.$m['away'].'. Vencedor: '.ClubBadge::name($winner),
            ];

            // Atualiza o confronto no torneio
            $cid = $g['competition_id'] ?? null;
            if ($cid && isset($g['competitions'][$cid])) {
                $t = &$g['competitions'][$cid];
                $tieIndex = $m['tie'] ?? null;
                if ($tieIndex !== null && isset($t['ties'][$tieIndex])) {
                    $t['ties'][$tieIndex]['winner'] = $winner;
                } elseif (!empty($t['ties'])) {
                    foreach ($t['ties'] as &$cand) {
                        if (($cand['a'] === $m['home'] && $cand['b'] === $m['away']) || ($cand['a'] === $m['away'] && $cand['b'] === $m['home'])) {
                            $cand['winner'] = $winner;
                            break;
                        }
                    }
                    unset($cand);
                }
                unset($t);
            }
        }

        $st['applied'] = true;
        // Transição definitiva para o fim_semana
        $g['stage'] = 'fim_semana';
    }

    /**
     * Simula disputa completa para partidas sem o protagonista (confrontos do chaveamento).
     */
    public static function simulateTie(array &$tie, array $matches, string $phase): array {
        $teamA = $tie['a'];
        $teamB = $tie['b'];

        $first = random_int(0, 1) === 0 ? $teamA : $teamB;
        $second = $first === $teamA ? $teamB : $teamA;

        $st = [
            'home' => $teamA,
            'away' => $teamB,
            'first_team' => $first,
            'second_team' => $second,
            'score' => [$teamA => 0, $teamB => 0],
            'home_kicks_count' => 0,
            'away_kicks_count' => 0,
            'status' => 'in_progress',
            'winner' => null,
            'current_round' => 1,
            'current_turn_team' => $first,
            'kicks_taken' => 0,
            'kicks' => [],
        ];

        $npc=['matches'=>$matches,'shootout'=>$st];
        for ($k=0; $k<1000 && $npc['shootout']['status']==='in_progress'; $k++) {
            self::simulateNextKick($npc);
            self::checkTermination($npc['shootout']);
        }
        $st=$npc['shootout'];
        if (!$st['winner']) throw new RuntimeException('Disputa NPC excedeu limite seguro, sem vencedor artificial.');

        return $st;
    }

    /**
     * Limita a probabilidade entre 15% e 95%.
     */
    private static function clampChance(float $val): float {
        return round(max(15.0, min(95.0, $val)), 2);
    }

    /**
     * Renderização da interface mobile-first da disputa de pênaltis.
     */
    public static function render(array $g): void {
        $st = $g['shootout'] ?? null;
        if (!$st) return;

        $home = $st['home'];
        $away = $st['away'];
        $homeScore = (int)($st['score'][$home] ?? 0);
        $awayScore = (int)($st['score'][$away] ?? 0);
        $round = (int)($st['current_round'] ?? 1);
        $status = $st['status'] ?? 'in_progress';
        $winner = $st['winner'] ?? null;
        $isProtagonistTurn = self::isProtagonistTurn($g);
        $isGk = !empty($st['protagonist_is_gk']);
        $lastFeedback = $st['last_kick_feedback'] ?? null;

        $displayRounds = max(5, $round);

        echo '<article class="panel match compact-score shootout-panel" aria-label="Disputa de pênaltis">';
        echo '<div class="match-time">DISPUTA DE PÊNALTIS · '.e(Competition::phaseLabel($g)).'</div>';
        echo '<div class="score-row shootout-score-row">';
        echo '<span class="score-team">'; ClubBadge::render($home); echo '<span>'.e(ClubBadge::name($home)).'</span></span>';
        echo '<strong class="shootout-score">'.e($homeScore).' <small>×</small> '.e($awayScore).'</strong>';
        echo '<span class="score-team">'; ClubBadge::render($away); echo '<span>'.e(ClubBadge::name($away)).'</span></span>';
        echo '</div>';

        // Indicadores das cobranças (pills verdes/vermelhas)
        echo '<div class="shootout-indicators">';
        foreach ([$home, $away] as $team) {
            echo '<div class="team-indicators">';
            echo '<span class="team-lbl">'.e(ClubBadge::name($team)).'</span>';
            echo '<div class="shootout-pills">';
            $teamKicks = array_values(array_filter($st['kicks'], fn($k) => $k['team'] === $team));
            for ($r = 0; $r < $displayRounds; $r++) {
                if (isset($teamKicks[$r])) {
                    $class = $teamKicks[$r]['scored'] ? 'scored' : 'missed';
                    $title = $teamKicks[$r]['scored'] ? 'Gol' : 'Perdeu';
                    echo '<span class="shootout-pill '.e($class).'" title="'.e($title).'"></span>';
                } elseif ($status === 'in_progress' && $r === count($teamKicks) && $team === $st['current_turn_team']) {
                    echo '<span class="shootout-pill current" title="Cobrança atual"></span>';
                } else {
                    echo '<span class="shootout-pill pending" title="Pendente"></span>';
                }
            }
            echo '</div></div>';
        }
        echo '</div>';

        if ($status === 'completed') {
            echo '<div class="shootout-conclusion">';
            echo '<h3>Disputa encerrada!</h3>';
            $winnerName = ClubBadge::name($winner ?? '');
            echo '<p class="tag" style="background:#27ae6030;color:#5af08e;border:1px solid #27ae6060;display:inline-block;padding:6px 12px;font-size:14px;">🏆 <b>'.e($winnerName).'</b> venceu por '.e($homeScore).' × '.e($awayScore).' e está classificado!</p>';
            if (!empty($st['protagonist_eligible'])) {
                $myKicks = array_values(array_filter($st['kicks'], fn($k) => !empty($k['is_protagonist'])));
                if ($myKicks) {
                    $k = $myKicks[0];
                    if ($isGk) {
                        echo '<p class="subtle">Sua participação no gol: '.e($k['description']).'</p>';
                    } else {
                        echo '<p class="subtle">Sua cobrança: <b>'.($k['scored'] ? 'Convertida ⚽' : 'Desperdiçada ❌').'</b> ('.e($k['description']).')</p>';
                    }
                }
            }
            if (function_exists('form')) form('/penaltis/avancar', $g);
            else echo '<form method="post" action="/penaltis/avancar">';
            echo '<button class="btn btn-primary btn-lg" style="margin-top:10px;">Continuar para o apito final →</button></form>';
            echo '</div></article>';
            return;
        }

        if ($lastFeedback) {
            echo '<div class="last-kick-alert"><b>Última cobrança:</b> '.e($lastFeedback['text']).'</div>';
        }

        if ($isProtagonistTurn) {
            $choices = self::choices($g);
            echo '<div class="shootout-decision">';
            if ($isGk) {
                echo '<h2>DEFESA DECISIVA! ADVERSÁRIO NA MARCA DO PÊNALTI</h2>';
                echo '<p class="chance-caption">Escolha sua postura no gol. Posicionamento, impulsão e leitura influenciam a chance de defesa.</p>';
            } else {
                echo '<h2>É A SUA VEZ! COBRANÇA Nº '.e($round).'</h2>';
                echo '<p class="chance-caption">Escolha onde e como finalizar. Atributos técnicos, potência, frieza e energia definem sua chance.</p>';
            }

            if (function_exists('form')) form('/penaltis/decidir', $g);
            else echo '<form method="post" action="/penaltis/decidir">';

            foreach ($choices as $i => $o) {
                $percent = number_format((float)$o['chance'], 2, ',', '');
                $outcome = $isGk ? 'defesa' : 'gol';
                echo '<button class="choice" name="option" value="'.e((string)$i).'">';
                echo '<b>'.e(chr(65 + $i)).'</b>';
                echo '<span class="choice-text">'.e($o['text']).'</span>';
                echo '<span class="choice-chance chance-'.($o['chance'] >= 65 ? 'high' : ($o['chance'] >= 40 ? 'medium' : 'low')).'" aria-label="Chance de '.e($outcome).': '.e($percent).'%">';
                echo '<strong>'.e($percent).'%</strong><small>'.e($outcome).'</small></span>';
                echo '</button>';
            }
            echo '</form></div>';
        } else {
            echo '<div class="shootout-advance">';
            $currTeamName = ClubBadge::name($st['current_turn_team']);
            echo '<p>Cobrança da rodada <b>'.e($round).'</b> · Vez de <b>'.e($currTeamName).'</b></p>';
            if (function_exists('form')) form('/penaltis/avancar', $g);
            else echo '<form method="post" action="/penaltis/avancar">';
            echo '<button class="btn btn-primary btn-lg" name="mode" value="one">Cobrar / Ver cobrança →</button>';
            if (!empty($st['protagonist_eligible']) && empty($st['protagonist_acted'])) {
                echo ' <button class="btn btn-secondary" name="mode" value="to_protagonist" style="margin-left:8px;">Avançar até minha vez ⏭</button>';
            }
            echo '</form>';
            echo '</div>';
        }

        echo '</article>';
    }
}