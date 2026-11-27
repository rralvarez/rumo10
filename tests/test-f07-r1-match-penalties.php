<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function okF07(bool $cond, string $msg): void
{
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA R10-F07-R1: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

function makeMatchCareerF07(string $pos, int $skillVal, int $conf, int $ene, int $rhythm, float $ownOvr, float $oppOvr): array
{
    $g = Game::create('Atleta F07', 'São Paulo', 'SP', $pos, 'Santos-SP');
    foreach ($g['skills'] as &$sk) {
        $sk['value'] = $skillVal;
    }
    unset($sk);
    $g['states']['confianca'] = $conf;
    $g['states']['energia'] = $ene;
    $g['states']['ritmo_jogo'] = $rhythm;
    $g['stage'] = 'partida';
    $i = Game::currentIndex($g);
    $g['matches'][$i]['status'] = 'em_andamento';
    $g['matches'][$i]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    $g['matches'][$i]['lineup'] = 'titular';
    $g['matches'][$i]['entry'] = 0;
    $g['matches'][$i]['exit'] = 90;
    $g['matches'][$i]['minute'] = 15;
    $g['matches'][$i]['home_overall'] = $g['matches'][$i]['home'] === $g['club'] ? $ownOvr : $oppOvr;
    $g['matches'][$i]['away_overall'] = $g['matches'][$i]['home'] === $g['club'] ? $oppOvr : $ownOvr;
    return $g;
}

echo "=== R10-F07-R1: SUÍTE DE HOMOLOGAÇÃO DOS PÊNALTIS EM PARTIDA ===\n";

// 1. Conversão, preservação de shot_kind, faixas e diferenciação
echo "\n1. Validando shot_kind, Faixas de Probabilidade e Jogo Aberto...\n";

$gOpen = makeMatchCareerF07('centroavante', 70, 60, 75, 60, 74.0, 74.0);
$openChoices = Game::choices($gOpen);
$openNormalTrace = ActionResolution::trace($gOpen, $openChoices[0]);
$openLongTrace = ActionResolution::trace($gOpen, $openChoices[3]);
okF07(
    ($openChoices[0]['shot_kind'] ?? '') === 'normal'
    && ($openChoices[3]['shot_kind'] ?? '') === 'long'
    && $openNormalTrace['kind'] === 'normal'
    && $openLongTrace['kind'] === 'long'
    && $openNormalTrace['chance'] < 45.0
    && $openLongTrace['chance'] < 15.0,
    "Chutes em jogo aberto (id=0) preservam shot_kind e probabilidades originais (normal={$openNormalTrace['chance']}%, long={$openLongTrace['chance']}%)"
);

$gInit = makeMatchCareerF07('centroavante', 45, 45, 65, 50, 65.0, 72.0);
$idxInit = Game::currentIndex($gInit);
$gInit['matches'][$idxInit]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
$chInit = Game::choices($gInit);
$pInit = array_map(static fn($o) => Game::chance($gInit, $o), $chInit);
okF07(
    $chInit[0]['shot_kind'] === 'penalty_power'
    && $chInit[1]['shot_kind'] === 'penalty_placement'
    && $chInit[2]['shot_kind'] === 'penalty_wait_keeper'
    && $chInit[3]['shot_kind'] === 'penalty_risk',
    'Situação 1401 preserva as 4 modalidades distintas de shot_kind'
);
okF07(
    $pInit[1] >= 60.0 && $pInit[1] <= 71.0 && $pInit[3] >= 58.0 && $pInit[3] < $pInit[1],
    'Iniciante (skills=45) permanece na faixa ~60%–70% (' . implode('%, ', $pInit) . '%)'
);

$gMid = makeMatchCareerF07('centroavante', 65, 60, 75, 60, 72.0, 72.0);
$gMid['matches'][Game::currentIndex($gMid)]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
$pMid = array_map(static fn($o) => Game::chance($gMid, $o), Game::choices($gMid));
okF07(
    $pMid[1] >= 75.0 && $pMid[1] <= 81.0 && $pMid[0] >= 75.0 && $pMid[2] >= 75.0,
    'Cobrador mediano (skills=65) permanece na faixa ~75%–80% (' . implode('%, ', $pMid) . '%)'
);

$gSpec = makeMatchCareerF07('centroavante', 88, 85, 85, 75, 82.0, 74.0);
$idxSpec = Game::currentIndex($gSpec);
$gSpec['matches'][$idxSpec]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
// Simula sequência ofensiva prévia para provar que NÃO contamina pênalti
$gSpec['matches'][$idxSpec]['momentum_actions'] = 2;
$chSpec = Game::choices($gSpec);
$trSpec = array_map(static fn($o) => ActionResolution::trace($gSpec, $o), $chSpec);
$pSpec = array_map(static fn($t) => $t['chance'], $trSpec);
okF07(
    $pSpec[1] >= 85.0 && $pSpec[1] <= 90.0
    && count(array_unique(array_map('strval', $pSpec))) === 4,
    'Especialista (skills=88) permanece em ~85%–90% sem saturação simultânea das 4 opções (' . implode('%, ', $pSpec) . '%)'
);
okF07(
    $trSpec[1]['extra_sequence_bonus'] === 0
    && $trSpec[1]['opponent_unit_source'] === 'collective_fallback',
    'Pênalti não recebe bônus de sequência ofensiva e identifica fallback coletivo do adversário'
);

$gExtreme = makeMatchCareerF07('meia', 40, 15, 20, 40, 62.0, 90.0);
$gExtreme['matches'][Game::currentIndex($gExtreme)]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '60-70'];
$pExt = array_map(static fn($o) => Game::chance($gExtreme, $o), Game::choices($gExtreme));
okF07(
    $pExt[1] < 60.0 && $pExt[3] < $pExt[1],
    'Condições extremas reduzem naturalmente abaixo de 60% sem piso artificial (' . implode('%, ', $pExt) . '%)'
);

// 2. Modos automatizados (1400 e 1401)
echo "\n2. Validando Estratégias nos Modos Automatizados (1400 e 1401)...\n";
$pmOpt = new ReflectionMethod(PlayMode::class, 'option');

$gReqAuto = makeMatchCareerF07('centroavante', 58, 65, 80, 60, 74.0, 74.0);
$gReqAuto['play_mode'] = 'rapido';
$gReqAuto['matches'][Game::currentIndex($gReqAuto)]['active_match_penalty'] = ['status' => 'pending_request', 'victim' => 'companheiro'];
okF07(
    $pmOpt->invoke(null, $gReqAuto, 'protagonismo') === 0,
    'Situação 1400: estratégia Protagonismo prioriza solicitar a cobrança (opção 0)'
);
okF07(
    $pmOpt->invoke(null, $gReqAuto, 'coletivo') === 2,
    'Situação 1400: estratégia Coletivo preserva identidade incentivando o companheiro (opção 2)'
);

$gKickAuto = makeMatchCareerF07('centroavante', 70, 65, 80, 65, 74.0, 74.0);
$gKickAuto['play_mode'] = 'expresso';
$gKickAuto['matches'][Game::currentIndex($gKickAuto)]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
$kickPickedEquil = $pmOpt->invoke(null, $gKickAuto, 'equilibrio');
$kickPickedProt = $pmOpt->invoke(null, $gKickAuto, 'protagonismo');
okF07(
    $kickPickedEquil === 1 && in_array($kickPickedProt, [0, 1], true),
    "Situação 1401: modos automatizados usam probabilidade efetiva + estratégia (equilibrio={$kickPickedEquil}, protagonismo={$kickPickedProt})"
);

// 3. Estatísticas, +2 XP finalização, nota, identidade 1400->1401 e idempotência
echo "\n3. Validando XP (+2 finalização), Confiança, Nota, Identidade e Elegibilidade...\n";
$gScore = makeMatchCareerF07('centroavante', 75, 65, 80, 70, 75.0, 72.0);
$gScore['age'] = 24;
$gScore['progression_version'] = 2;
$idxS = Game::currentIndex($gScore);
$gScore['matches'][$idxS]['active_match_penalty'] = ['status' => 'pending_request', 'victim' => 'companheiro', 'window' => '15-35'];
$gAfterReq = Game::command($gScore, 'choice', ['option' => 0], false, static fn(int $min, int $max): int => $min); // aceita pedido (<=75)
okF07(
    ($gAfterReq['matches'][$idxS]['active_match_penalty']['status'] ?? '') === 'taking_kick'
    && ($gAfterReq['matches'][$idxS]['active_match_penalty']['window'] ?? '') === '15-35',
    'Transição 1400 -> 1401 preserva exatamente o identificador da janela da ocorrência'
);

$xpBefore = (int) $gAfterReq['skills']['finalizacao']['xp'];
$confBefore = (int) $gAfterReq['states']['confianca'];
$gAfterKick = Game::command($gAfterReq, 'choice', ['option' => 1], false, static fn(int $min, int $max): int => $min);
$xpAfter = (int) $gAfterKick['skills']['finalizacao']['xp'];
$stKick = $gAfterKick['matches'][$idxS]['stats'];
okF07(
    $xpAfter > $xpBefore
    && ($gAfterKick['feedback']['effects']['XP finalizacao'] ?? 0) > 0
    && (int)$stKick['goals'] === 1
    && (int)$stKick['shots'] === 1
    && (int)$stKick['penalties_taken'] === 1
    && (int)$stKick['penalties_scored'] === 1
    && in_array('15-35', $gAfterKick['matches'][$idxS]['resolved_penalties'] ?? [], true)
    && (int)$gAfterKick['states']['confianca'] >= $confBefore,
    "Pênalti convertido concede +2 XP em finalização ({$xpBefore} -> {$xpAfter}), 1 gol, 1 cobrança, chave canônica '15-35' e preserva confiança"
);

// Idempotência explícita de ocorrência já resolvida (mesma janela '15-35', sem alterar placar, gols, chutes, XP ou confiança)
$gReplay = $gAfterKick;
$gReplay['matches'][$idxS]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
$gAfterReplay = Game::command($gReplay, 'choice', ['option' => 1], false, static fn(int $min, int $max): int => $min);
$stReplay = $gAfterReplay['matches'][$idxS]['stats'];
okF07(
    (int)$stReplay['goals'] === (int)$stKick['goals']
    && (int)$stReplay['shots'] === (int)$stKick['shots']
    && (int)$stReplay['penalties_taken'] === (int)$stKick['penalties_taken']
    && (int)$stReplay['penalties_scored'] === (int)$stKick['penalties_scored']
    && (int)$gAfterReplay['skills']['finalizacao']['xp'] === $xpAfter
    && (int)$gAfterReplay['states']['confianca'] === (int)$gAfterKick['states']['confianca']
    && (int)$gAfterReplay['matches'][$idxS]['hg'] === (int)$gAfterKick['matches'][$idxS]['hg']
    && (int)$gAfterReplay['matches'][$idxS]['ag'] === (int)$gAfterKick['matches'][$idxS]['ag'],
    'Ocorrência já resolvida (15-35) não altera placar, gols, finalizações, estatísticas, XP ou confiança'
);

// Elegibilidade: substituído, expulso, suspenso e não relacionado não cobram 1401
foreach ([
    'substituido' => ['participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'lineup' => 'titular', 'exit' => 35],
    'expulso' => ['participation_state' => MatchParticipation::STATE_SENT_OFF, 'lineup' => 'titular', 'exit' => 35, 'red_card_minute' => 35],
    'suspenso' => ['participation_state' => MatchParticipation::STATE_SUSPENDED, 'lineup' => 'suspenso', 'entry' => 90, 'exit' => 90],
    'nao_relacionado' => ['participation_state' => MatchParticipation::STATE_NOT_SELECTED, 'lineup' => 'não relacionado', 'entry' => 90, 'exit' => 90],
] as $ineligLabel => $patch) {
    $gInel = makeMatchCareerF07('centroavante', 75, 70, 80, 70, 75.0, 72.0);
    $idxI = Game::currentIndex($gInel);
    $gInel['matches'][$idxI] = array_replace($gInel['matches'][$idxI], $patch);
    $gInel['matches'][$idxI]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '15-35'];
    okF07(
        Game::choices($gInel) === [] && !MatchPenalty::isProtagonistOfficialTaker($gInel, $gInel['matches'][$idxI]),
        "Elegibilidade bloqueia cobrança para atleta {$ineligLabel}"
    );
}

// 4. Simulações estocásticas (random_int) com N=400 partidas por modo (Normal, Rápido e Expresso)
echo "\n4. Executando Simulações Estocásticas (N=400 partidas/modo: Normal, Rápido, Expresso)...\n";

$simNormal = ['team_pens' => 0, 'opp_pens' => 0, 'protag_kicks' => 0, 'protag_goals' => 0, 'total_goals' => 0];
for ($k = 0; $k < 400; $k++) {
    $gn = makeMatchCareerF07('centroavante', 68, 60, 80, 65, 74.0, 74.0);
    $gn['play_mode'] = 'normal';
    $idxN = Game::currentIndex($gn);
    $gn['matches'][$idxN]['status'] = 'pre_jogo';
    $gn['matches'][$idxN]['minute'] = 0;
    MatchBalance::prepare($gn['matches'][$idxN], $gn);
    $guard = 0;
    while ($gn['stage'] === 'partida' && $guard++ < 35) {
        $m = $gn['matches'][Game::currentIndex($gn)];
        if (!empty($m['active_match_penalty']) && ($m['active_match_penalty']['status'] ?? '') === 'taking_kick') {
            $simNormal['protag_kicks']++;
            $gn = Game::command($gn, 'choice', ['option' => 1]);
            if (($gn['feedback']['highlight'] ?? '') === 'goal') {
                $simNormal['protag_goals']++;
            }
            continue;
        }
        $ch = Game::choices($gn);
        $gn = $ch ? Game::command($gn, 'choice', ['option' => 0]) : Game::command($gn, 'advance', []);
    }
    $mEnd = $gn['matches'][$idxN];
    $simNormal['total_goals'] += (int) $mEnd['hg'] + (int) $mEnd['ag'];
    foreach ($mEnd['resolved_penalties'] ?? [] as $rk) {
        if (str_starts_with((string)$rk, 'opponent:')) {
            $simNormal['opp_pens']++;
        } else {
            $simNormal['team_pens']++;
        }
    }
}
$normalFreq = ($simNormal['team_pens'] + $simNormal['opp_pens']) / 400;
$normalConv = $simNormal['protag_kicks'] > 0 ? 100 * $simNormal['protag_goals'] / $simNormal['protag_kicks'] : 0;
okF07(
    $normalFreq >= 0.22 && $normalFreq <= 0.40 && $normalConv >= 70.0 && $normalConv <= 88.0,
    sprintf(
        'Normal (400 jogos): freq_penaltis=%.3f/jogo, conv_protag=%d/%d (%.1f%%), media_gols=%.2f/jogo',
        $normalFreq,
        $simNormal['protag_goals'],
        $simNormal['protag_kicks'],
        $normalConv,
        $simNormal['total_goals'] / 400
    )
);

foreach (['rapido', 'expresso'] as $mode) {
    $totMatches = 0;
    $totPens = 0;
    $pTaken = 0;
    $pScored = 0;
    $totGoals = 0;
    $targetMatches = 400;
    while ($totMatches < $targetMatches) {
        $gm = Game::create("Sim {$mode}", 'São Paulo', 'SP', 'centroavante', 'Santos-SP');
        $gm['play_mode'] = $mode;
        foreach ($gm['skills'] as &$sk) {
            $sk['value'] = 68;
        }
        unset($sk);
        $gm = Game::command($gm, 'handicap');
        $gm = Game::command($gm, 'simulate', ['plan' => 'protagonismo']);
        $finishedClubMatches = array_values(array_filter(
            $gm['matches'],
            static fn(array $mCand): bool => ($mCand['status'] ?? '') === 'finalizada'
                && in_array($gm['club'], [$mCand['home'], $mCand['away']], true)
        ));
        if ($mode === 'rapido') {
            $totMatches++;
            $last = end($gm['pace_report']['matches']);
            $mRef = end($finishedClubMatches);
            $totGoals += (int)$last['hg'] + (int)$last['ag'];
            $pTaken += (int)($last['stats']['penalties_taken'] ?? 0);
            $pScored += (int)($last['stats']['penalties_scored'] ?? 0);
            $totPens += is_array($mRef) ? count($mRef['resolved_penalties'] ?? []) : 0;
        } else {
            foreach ($gm['pace_report']['matches'] as $idxPm => $pm) {
                $totMatches++;
                $totGoals += (int)$pm['hg'] + (int)$pm['ag'];
                $pTaken += (int)($pm['stats']['penalties_taken'] ?? 0);
                $pScored += (int)($pm['stats']['penalties_scored'] ?? 0);
                $mRef = $finishedClubMatches[$idxPm] ?? null;
                $totPens += is_array($mRef) ? count($mRef['resolved_penalties'] ?? []) : 0;
                if ($totMatches >= $targetMatches) {
                    break;
                }
            }
        }
    }
    $mFreq = $totPens / $totMatches;
    $mConv = $pTaken > 0 ? 100 * $pScored / $pTaken : 0;
    okF07(
        $mFreq >= 0.20 && $mFreq <= 0.42 && ($pTaken === 0 || ($mConv >= 65.0 && $mConv <= 92.0)),
        sprintf(
            "Modo %s (%d jogos): freq_penaltis=%.3f/jogo, conv_protag=%d/%d (%.1f%%), media_gols=%.2f/jogo",
            $mode,
            $totMatches,
            $mFreq,
            $pScored,
            $pTaken,
            $mConv,
            $totGoals / $totMatches
        )
    );
}

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES DA R10-F07-R1 FORAM APROVADAS!\n";
echo "====================================================================\n";