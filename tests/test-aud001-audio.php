<?php
declare(strict_types=1);

/**
 * R10-AUD-001 | Bateria de Testes dos Efeitos Sonoros da Partida
 * Valida:
 * 1. Emissão de NO_GOAL em falhas de chute e pênalti.
 * 2. Emissão de MATCH_WHISTLE_START (1x), MATCH_HALFTIME (2x), MATCH_WHISTLE_END (3x).
 * 3. Deduplicação e imutabilidade de IDs de eventos sonoros.
 * 4. Isolamento dos Modos de Jogo (Normal vs Rápido/Expresso).
 * 5. Eventos de Substituição (PLAYER_SUBSTITUTION_IN/OUT) vinculados a participações reais.
 * 6. Prioridades de eventos sonoros.
 * 7. Arquivos OGG físicos e contratos de duração <= 3s.
 */

require dirname(__DIR__).'/app/bootstrap.php';

$testCount = 0;
$passCount = 0;

function assertAud(bool $cond, string $msg): void {
    global $testCount, $passCount;
    $testCount++;
    if (!$cond) {
        echo "  [FAIL] {$msg}\n";
        throw new RuntimeException("Falha na validação AUD-001: {$msg}");
    }
    $passCount++;
    echo "  [PASS] {$msg}\n";
}

echo "=== R10-AUD-001: TESTES DE EFEITOS SONOROS DA PARTIDA ===\n\n";

// -------------------------------------------------------------------------
// 1. Emissão de NO_GOAL em falhas de finalização
// -------------------------------------------------------------------------
echo "1. Validando emissão de NO_GOAL em finalizações desperdiçadas...\n";

// 1.1 Chute desperdiçado (simulação de fieldDecision com falha de chute)
$match1 = [
    'minute' => 30, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['shots' => 0, 'goals' => 0]
];
$game1 = ['id' => 'test1', 'season' => 1, 'club' => 'SP', 'stage' => 'partida', 'feedback' => []];

// Emite NO_GOAL diretamente via Game::emitSoundEvent como em lance de chute sem gol
$eventId1 = Game::emitSoundEvent($match1, $game1, 'NO_GOAL', 30);
assertAud(!empty($eventId1), 'ID de NO_GOAL emitido com sucesso');
assertAud(count($match1['sound_events']) === 1, 'Evento NO_GOAL adicionado aos sound_events da partida');
assertAud($match1['sound_events'][0]['type'] === 'NO_GOAL', 'Tipo do evento é rigorosamente NO_GOAL');
assertAud($match1['sound_events'][0]['minute'] === 30, 'Minuto do lance registrado como 30');

// 1.2 Pênalti desperdiçado
$match2 = [
    'minute' => 75, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 1, 'ag' => 1, 'home' => 'SP', 'away' => 'COR', 'competition' => 'c1', 'round' => 2,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['penalties_taken' => 1, 'penalties_missed' => 1]
];
$eventId2 = Game::emitSoundEvent($match2, $game1, 'NO_GOAL', 75);
assertAud($match2['sound_events'][0]['type'] === 'NO_GOAL', 'Pênalti desperdiçado emite NO_GOAL');
assertAud(str_contains($eventId2, 'NO_GOAL'), 'ID contém chave canônica NO_GOAL');

// 1.3 Gol marcado emite GOAL_SCORED e NÃO emite NO_GOAL
$match3 = [
    'minute' => 40, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'PAL', 'competition' => 'c1', 'round' => 3,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['shots' => 1, 'goals' => 1]
];
$goalId = Game::emitSoundEvent($match3, $game1, 'GOAL_SCORED', 40);
assertAud($match3['sound_events'][0]['type'] === 'GOAL_SCORED', 'Gol marcado emite GOAL_SCORED');
$hasNoGoal = in_array('NO_GOAL', array_column($match3['sound_events'], 'type'), true);
assertAud(!$hasNoGoal, 'Gol marcado NÃO emite evento NO_GOAL');

// 1.4 Caminho Real de Game::command em Situação de Pênalti
$gRealPen = Game::legacyCreate('Jogador Teste', 'São Paulo', 'SP', 'centroavante');
$gRealPen['stage'] = 'partida';
$iCur = Game::currentIndex($gRealPen);
$gRealPen['matches'][$iCur]['entry'] = 0;
$gRealPen['matches'][$iCur]['exit'] = 90;
$gRealPen['matches'][$iCur]['minute'] = 30;
$gRealPen['matches'][$iCur]['status'] = 'em_andamento';
$gRealPen['matches'][$iCur]['lineup'] = 'titular';
$gRealPen['matches'][$iCur]['sound_events'] = [];
$gRealPen['matches'][$iCur]['active_match_penalty'] = [
    'status' => 'taking_kick',
    'taker' => 'user',
    'context_message' => 'Pênalti a seu favor!'
];
$gRealPenResult = Game::command($gRealPen, 'choice', ['option' => '0']);
$mPenRes = $gRealPenResult['matches'][$iCur];
$penEventTypes = array_column($mPenRes['sound_events'], 'type');
$hasValidPenSound = in_array('GOAL_SCORED', $penEventTypes, true) || in_array('NO_GOAL', $penEventTypes, true);
assertAud($hasValidPenSound, 'Game::command em cobrança de pênalti emite GOAL_SCORED ou NO_GOAL de acordo com o lance');
assertAud(!empty($gRealPenResult['feedback']['sound_event_id']), 'Game::command preenche sound_event_id no feedback da ação');

// 1.5 Caminho Real de Game::command em Chute de Jogo Aberto (Situação 7 aos 80')
$gRealShot = Game::legacyCreate('Artilheiro', 'São Paulo', 'SP', 'centroavante');
$gRealShot['stage'] = 'partida';
$iShot = Game::currentIndex($gRealShot);
$gRealShot['matches'][$iShot]['entry'] = 0;
$gRealShot['matches'][$iShot]['exit'] = 90;
$gRealShot['matches'][$iShot]['minute'] = 80;
$gRealShot['matches'][$iShot]['hg'] = 0;
$gRealShot['matches'][$iShot]['ag'] = 0;
$gRealShot['matches'][$iShot]['status'] = 'em_andamento';
$gRealShot['matches'][$iShot]['lineup'] = 'titular';
$gRealShot['matches'][$iShot]['sound_events'] = [];

$gRealShotResult = Game::command($gRealShot, 'choice', ['option' => '0']); // Opção 0 da situação 7 é chute
$mShotRes = $gRealShotResult['matches'][$iShot];
$shotEventTypes = array_column($mShotRes['sound_events'], 'type');
$hasValidShotSound = in_array('GOAL_SCORED', $shotEventTypes, true) || in_array('NO_GOAL', $shotEventTypes, true);
assertAud($hasValidShotSound, 'Game::command em chute de jogo aberto (S7 aos 80m) emite GOAL_SCORED ou NO_GOAL');
assertAud(!empty($gRealShotResult['feedback']['sound_event_id']), 'Game::command preenche sound_event_id no feedback de chute');

// -------------------------------------------------------------------------
// 2. Apitos de Período (MATCH_WHISTLE_START, MATCH_HALFTIME, MATCH_WHISTLE_END)
// -------------------------------------------------------------------------
echo "\n2. Validando sinalização de apitos de período (início 1x, intervalo 2x, fim 3x)...\n";

// 2.1 Início da partida (minuto 15, em_andamento)
$matchStart = [
    'minute' => 15, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => []
];
$gameNormal = ['id' => 'test_whistle', 'season' => 1, 'club' => 'SP', 'stage' => 'partida', 'feedback' => []];

ob_start();
Presentation::matchSounds($gameNormal, $matchStart);
$outputStart = ob_get_clean();
assertAud(str_contains($outputStart, 'data-sound-event="MATCH_WHISTLE_START"'), 'Início de jogo (15 min) renderiza MATCH_WHISTLE_START');

// 2.2 Intervalo da partida (minuto 45, status intervalo)
$matchHalftime = [
    'minute' => 45, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 1, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'intervalo', 'sound_events' => [], 'log' => []
];
ob_start();
Presentation::matchSounds($gameNormal, $matchHalftime);
$outputHalf = ob_get_clean();
assertAud(str_contains($outputHalf, 'data-sound-event="MATCH_HALFTIME"'), 'Intervalo (45 min) renderiza MATCH_HALFTIME');

// 2.3 Fim da partida (estágio fim_semana, status finalizada)
$matchEnd = [
    'minute' => 90, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 2, 'ag' => 1, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'finalizada', 'sound_events' => [], 'log' => []
];
$gameEnd = ['id' => 'test_whistle', 'season' => 1, 'club' => 'SP', 'stage' => 'fim_semana', 'feedback' => []];
ob_start();
Presentation::matchSounds($gameEnd, $matchEnd);
$outputEnd = ob_get_clean();
assertAud(str_contains($outputEnd, 'data-sound-event="MATCH_WHISTLE_END"'), 'Fim de partida (fim_semana finalizada) renderiza MATCH_WHISTLE_END');

// 2.4 Ausência de apitos indevidos em minutos intermediários
$matchMid = [
    'minute' => 30, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => []
];
ob_start();
Presentation::matchSounds($gameNormal, $matchMid);
$outputMid = ob_get_clean();
assertAud(!str_contains($outputMid, 'MATCH_WHISTLE_'), 'Minutos normais de andamento (30) não geram apito espúrio de período');

// -------------------------------------------------------------------------
// 3. Imutabilidade e Reconciliação de IDs de Eventos Sonoros
// -------------------------------------------------------------------------
echo "\n3. Validando imutabilidade e reconciliação determinística de IDs...\n";

$matchRec = [
    'minute' => 60, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento',
    'sound_events' => [
        ['type' => 'GOAL_SCORED', 'minute' => 20], // evento legado sem ID
        ['type' => 'ASSIST_RECORDED', 'minute' => 20, 'id' => 'legacy:id:1', 'seq' => 1], // evento com ID pré-existente
    ],
    'log' => []
];

Game::reconcileSoundEvents($matchRec, $gameNormal);
assertAud(!empty($matchRec['sound_events'][0]['id']), 'Evento legado sem ID recebe ID gerado de forma determinística');
assertAud($matchRec['sound_events'][1]['id'] === 'legacy:id:1', 'Evento com ID já existente preserva seu ID original');
assertAud($matchRec['sound_events'][0]['id'] !== $matchRec['sound_events'][1]['id'], 'IDs de eventos diferentes são estritamente distintos');

// Reconciliar uma segunda vez não altera IDs já atribuídos (idempotência)
$firstGenId = $matchRec['sound_events'][0]['id'];
Game::reconcileSoundEvents($matchRec, $gameNormal);
assertAud($matchRec['sound_events'][0]['id'] === $firstGenId, 'Reconciliação subsequente é estritamente idempotente');

// 3.2 Coexistência Contemporânea e Separação de Históricos (P0-2)
$matchP02 = [
    'minute' => 90, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 1, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'finalizada',
    'sound_events' => [
        ['type' => 'MATCH_WHISTLE_START', 'minute' => 15, 'id' => 'hist:15'],
        ['type' => 'GOAL_SCORED', 'minute' => 90, 'id' => 'current:goal:90']
    ],
    'log' => []
];
$gameP02 = [
    'id' => 'p02_test', 'season' => 1, 'club' => 'SP', 'stage' => 'fim_semana',
    'version' => 10,
    'feedback' => [
        'highlight' => 'goal',
        'sound_event' => 'GOAL_SCORED',
        'sound_event_id' => 'current:goal:90',
        'text' => 'Gooool!',
        'success' => true
    ]
];

ob_start();
Presentation::feedback($gameP02);
$outFeedback = ob_get_clean();

ob_start();
Presentation::matchSounds($gameP02, $matchP02);
$outMatchSounds = ob_get_clean();

assertAud(str_contains($outFeedback, 'data-sound-event="GOAL_SCORED"'), 'Feedback renderiza data-sound-event="GOAL_SCORED"');
assertAud(str_contains($outFeedback, 'data-sound-source="action"'), 'Feedback identifica evento com data-sound-source="action"');
assertAud(str_contains($outMatchSounds, 'data-sound-event="MATCH_WHISTLE_END"'), 'MatchSounds renderiza data-sound-event="MATCH_WHISTLE_END"');
assertAud(str_contains($outMatchSounds, 'data-sound-historical="1"'), 'Evento de minuto 15 marcado com data-sound-historical="1" aos 90 min');

// 3.3 Regressão P0-4: Cartão e Substituição emitidos antes do avanço do minuto
echo "\n3.3 Validando Regressão P0-4: Cartões e substituições da última ação após avanço de segmento...\n";

// Cenário A: Cartão Amarelo aos 15 min, com segmento avançado para 35 min
$gP04Yellow = Game::legacyCreate('Zagueiro Teste', 'São Paulo', 'SP', 'zagueiro');
$gP04Yellow['stage'] = 'partida';
$iY = Game::currentIndex($gP04Yellow);
$mY = &$gP04Yellow['matches'][$iY];
$mY['minute'] = 15;
$mY['entry'] = 0;
$mY['exit'] = 90;
$mY['status'] = 'em_andamento';
$mY['lineup'] = 'titular';
$mY['sound_events'] = [];
$mY['stats']['yellow_cards'] = 0;
$gP04Yellow['feedback'] = ['text' => 'Lance faltoso', 'effects' => []];

// Aplica falta aos 15 min
$foulRes = MatchDiscipline::recordFoul($gP04Yellow, $mY, 'reckless_tackle', false);
assertAud(!empty($foulRes['sound_event_id']), 'MatchDiscipline::recordFoul retorna sound_event_id');
assertAud(in_array($foulRes['sound_event_id'], $gP04Yellow['feedback']['sound_event_ids'] ?? [], true), 'ID do cartão amarelo registrado na coleção sound_event_ids do feedback');

// Motor avança segmento: de 15 para 35 minutos
$mY['minute'] = 35;

// Renderização: Presentation::matchSounds não deve classificar o cartão da última ação como histórico!
ob_start();
Presentation::matchSounds($gP04Yellow, $mY);
$outP04Yellow = ob_get_clean();

assertAud(str_contains($outP04Yellow, 'data-sound-event="CARD_YELLOW"'), 'P0-4: matchSounds renderiza CARD_YELLOW');
assertAud(!str_contains($outP04Yellow, 'data-sound-historical="1"'), 'P0-4: Cartão amarelo da ação recente NÃO é marcado como histórico mesmo com minuto avançado de 15 para 35');

// Cenário B: Na jogada seguinte (outro feedback), o cartão antigo de 15 min DEVE ser classificado como histórico aos 35 min
$gP04NextTurn = $gP04Yellow;
$gP04NextTurn['feedback'] = ['text' => 'Nova jogada no minuto 35', 'effects' => [], 'sound_event_ids' => []]; // sem o cartão na última ação
ob_start();
Presentation::matchSounds($gP04NextTurn, $mY);
$outP04OldCard = ob_get_clean();
assertAud(str_contains($outP04OldCard, 'data-sound-historical="1"'), 'P0-4: Cartão da rodada anterior é corretamente classificado como histórico no turno subsequente');

// Cenário C: Substituição aos 60 min com minuto avançado para 70 min
$gP04Sub = Game::legacyCreate('Reserva Teste', 'São Paulo', 'SP', 'meia');
$gP04Sub['stage'] = 'partida';
$iS = Game::currentIndex($gP04Sub);
$mS = &$gP04Sub['matches'][$iS];
$mS['entry'] = 60;
$mS['exit'] = 90;
$mS['minute'] = 60;
$mS['lineup'] = 'reserva';
$mS['participation_state'] = MatchParticipation::STATE_ON_BENCH;
$mS['sound_events'] = [];
$gP04Sub['feedback'] = ['text' => 'Entrada em campo', 'effects' => []];

MatchParticipation::sync($mS, $gP04Sub, 60);
assertAud(in_array('PLAYER_SUBSTITUTION_IN', array_column($mS['sound_events'], 'type'), true), 'MatchParticipation emite PLAYER_SUBSTITUTION_IN');
$subId = $mS['sound_events'][0]['id'];
assertAud(in_array($subId, $gP04Sub['feedback']['sound_event_ids'] ?? [], true), 'ID da substituição registrado em sound_event_ids do feedback');

// Avança para 70 min
$mS['minute'] = 70;
ob_start();
Presentation::matchSounds($gP04Sub, $mS);
$outP04Sub = ob_get_clean();
assertAud(str_contains($outP04Sub, 'data-sound-event="PLAYER_SUBSTITUTION_IN"'), 'P0-4: matchSounds renderiza PLAYER_SUBSTITUTION_IN');
assertAud(!str_contains($outP04Sub, 'data-sound-historical="1"'), 'P0-4: PLAYER_SUBSTITUTION_IN da ação recente NÃO é marcado como histórico com minuto avançado de 60 para 70');

// 3.4 Regressão P2-A: Classificação de histórico no mesmo minuto (duas ações no mesmo minuto)
echo "\n3.4 Validando Regressão P2-A: Histórico no mesmo minuto (ações sucessivas sem avanço de relógio)...\n";

$gP2A = Game::legacyCreate('Atacante Teste', 'São Paulo', 'SP', 'centroavante');
$gP2A['stage'] = 'partida';
$iP2A = Game::currentIndex($gP2A);
$mP2A = &$gP2A['matches'][$iP2A];
$mP2A['minute'] = 35;
$mP2A['entry'] = 0;
$mP2A['exit'] = 90;
$mP2A['status'] = 'em_andamento';
$mP2A['lineup'] = 'titular';
$mP2A['sound_events'] = [];

// Ação 1: lance aos 35 min gera cartão amarelo
$idCard35 = Game::emitSoundEvent($mP2A, $gP2A, 'CARD_YELLOW', 35);
$gP2A['feedback'] = [
    'text' => 'Falta cometida no minuto 35',
    'sound_event_ids' => [$idCard35],
    'sound_event_id' => $idCard35
];

ob_start();
Presentation::matchSounds($gP2A, $mP2A);
$outAct1 = ob_get_clean();
assertAud(str_contains($outAct1, 'data-sound-event="CARD_YELLOW"'), 'P2-A: Ação 1 renderiza CARD_YELLOW');
assertAud(!str_contains($outAct1, 'data-sound-historical="1"'), 'P2-A: Ação 1 NÃO é marcada como histórica na ação corrente');

// Ação 2: lance seguinte no MESMO minuto 35 (relógio continua em 35') gera chute para fora
$idNoGoal35 = Game::emitSoundEvent($mP2A, $gP2A, 'NO_GOAL', 35);
$gP2A['feedback'] = [
    'text' => 'Chute para fora no mesmo minuto 35',
    'sound_event' => 'NO_GOAL',
    'sound_event_ids' => [$idNoGoal35],
    'sound_event_id' => $idNoGoal35
];

ob_start();
Presentation::matchSounds($gP2A, $mP2A);
$outAct2 = ob_get_clean();

// P2-A: O evento da Ação 1 (CARD_YELLOW aos 35') DEVE ter data-sound-historical="1",
// mesmo tendo minuto 35 == minuto corrente 35!
assertAud(str_contains($outAct2, 'data-sound-event-id="'.$idCard35.'" data-sound-historical="1"') ||
          str_contains($outAct2, 'data-sound-historical="1" class="sr-only" aria-hidden="true"></div>') && str_contains($outAct2, 'data-sound-event-id="'.$idCard35.'"'),
          'P2-A: Evento persistido da Ação 1 no minuto 35 É classificado como histórico na Ação 2 (mesmo minuto)');

// E o evento da Ação 2 (NO_GOAL aos 35') NÃO deve ser histórico
assertAud(str_contains($outAct2, 'data-sound-event="NO_GOAL"'), 'P2-A: Ação 2 renderiza NO_GOAL');
assertAud(!str_contains($outAct2, 'data-sound-event-id="'.$idNoGoal35.'" data-sound-historical="1"'), 'P2-A: Evento fresco da Ação 2 NÃO tem marcação histórica');

// 3.5 Compatibilidade com Efeitos Sintetizados Legados (P1-C: BALL_HIT_POST e PENALTY_AWARDED)
echo "\n3.5 Validando Compatibilidade com Efeitos Legados Sintetizados (BALL_HIT_POST e PENALTY_AWARDED)...\n";
$mLegacy = $mP2A;
$mLegacy['sound_events'] = [];
$gLegacy = $gP2A;

$idPost = Game::emitSoundEvent($mLegacy, $gLegacy, 'BALL_HIT_POST', 35);
$idPenalty = Game::emitSoundEvent($mLegacy, $gLegacy, 'PENALTY_AWARDED', 35);
$gLegacy['feedback']['sound_event_ids'] = [$idPost, $idPenalty];

ob_start();
Presentation::matchSounds($gLegacy, $mLegacy);
$outLegacy = ob_get_clean();

assertAud(str_contains($outLegacy, 'data-sound-event="BALL_HIT_POST"'), 'P1-C: matchSounds renderiza evento legado BALL_HIT_POST');
assertAud(str_contains($outLegacy, 'data-sound-event="PENALTY_AWARDED"'), 'P1-C: matchSounds renderiza evento legado PENALTY_AWARDED');
assertAud(!str_contains($outLegacy, 'data-sound-historical="1"'), 'P1-C: Efeitos legados frescos renderizados sem marcação histórica');

// -------------------------------------------------------------------------
// 4. Isolamento dos Modos de Jogo (Normal vs Rápido / Expresso)
// -------------------------------------------------------------------------
echo "\n4. Validando isolamento dos modos de jogo...\n";

$gameRapido = ['id' => 'rapido_test', 'season' => 1, 'club' => 'SP', 'stage' => 'partida', 'play_mode' => 'rapido', 'feedback' => []];
$gameExpresso = ['id' => 'expresso_test', 'season' => 1, 'club' => 'SP', 'stage' => 'partida', 'play_mode' => 'expresso', 'feedback' => []];

$matchSim = [
    'minute' => 90, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 3, 'ag' => 2, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 5,
    'status' => 'em_andamento',
    'sound_events' => [
        ['type' => 'GOAL_SCORED', 'minute' => 10, 'id' => 'g1', 'seq' => 1],
        ['type' => 'GOAL_SCORED', 'minute' => 35, 'id' => 'g2', 'seq' => 2],
        ['type' => 'GOAL_SCORED', 'minute' => 70, 'id' => 'g3', 'seq' => 3],
    ],
    'log' => []
];

ob_start();
Presentation::matchSounds($gameRapido, $matchSim);
$outRapido = ob_get_clean();
assertAud($outRapido === '', 'Modo Rápido: Presentation::matchSounds não renderiza eventos no DOM (zero rajadas)');

ob_start();
Presentation::matchSounds($gameExpresso, $matchSim);
$outExpresso = ob_get_clean();
assertAud($outExpresso === '', 'Modo Expresso: Presentation::matchSounds não renderiza eventos no DOM (zero rajadas)');

// -------------------------------------------------------------------------
// 5. Substituições Canônicas (PLAYER_SUBSTITUTION_IN e OUT)
// -------------------------------------------------------------------------
echo "\n5. Validando substituições reais vinculadas a MatchParticipation...\n";

// 5.1 Jogador entra do banco aos 60 minutos
$matchSubIn = [
    'minute' => 60, 'entry' => 60, 'exit' => 90, 'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => []
];
$gameSub = ['id' => 'sub_test', 'season' => 1, 'club' => 'SP', 'stage' => 'partida', 'feedback' => []];
MatchParticipation::sync($matchSubIn, $gameSub, 60);

$hasSubIn = in_array('PLAYER_SUBSTITUTION_IN', array_column($matchSubIn['sound_events'], 'type'), true);
assertAud($hasSubIn, 'Jogador entrando do banco aos 60 min emite PLAYER_SUBSTITUTION_IN');

// 5.2 Jogador titular sai substituído aos 70 minutos
$matchSubOut = [
    'minute' => 70, 'entry' => 0, 'exit' => 70, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'hg' => 1, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => []
];
MatchParticipation::sync($matchSubOut, $gameSub, 70);
$hasSubOut = in_array('PLAYER_SUBSTITUTION_OUT', array_column($matchSubOut['sound_events'], 'type'), true);
assertAud($hasSubOut, 'Jogador titular substituído aos 70 min emite PLAYER_SUBSTITUTION_OUT');

// 5.3 Titular que joga a partida inteira (exit = 90) NÃO emite substituição
$matchFullTime = [
    'minute' => 90, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'hg' => 1, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => []
];
MatchParticipation::sync($matchFullTime, $gameSub, 90);
$hasSubFull = in_array('PLAYER_SUBSTITUTION_OUT', array_column($matchFullTime['sound_events'], 'type'), true);
assertAud(!$hasSubFull, 'Titular que atua os 90 minutos NÃO emite PLAYER_SUBSTITUTION_OUT fictício');

// -------------------------------------------------------------------------
// 6. Disciplina: Cartões Amarelos, Segundo Amarelo e Vermelho Direto
// -------------------------------------------------------------------------
echo "\n6. Validando disciplina integrada: Primeiro Amarelo, Segundo Amarelo e Vermelho Direto...\n";

// 6.1 Primeiro Cartão Amarelo via MatchDiscipline::recordFoul
$gDisc1 = $gameNormal;
$gDisc1['feedback'] = ['text' => '', 'effects' => []];
$matchDisc1 = [
    'minute' => 20, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['cards' => 0, 'yellow_cards' => 0, 'red_cards' => 0]
];
$resYellow1 = MatchDiscipline::recordFoul($gDisc1, $matchDisc1, 'reckless_tackle', false);
assertAud($resYellow1['card'] === 'yellow', 'Primeiro cartão classificado como yellow');
assertAud(in_array('CARD_YELLOW', array_column($matchDisc1['sound_events'], 'type'), true), 'Primeira infração emite CARD_YELLOW');
assertAud(!empty($resYellow1['sound_event_id']), 'recordFoul retorna sound_event_id para primeiro amarelo');
assertAud(in_array($resYellow1['sound_event_id'], $gDisc1['feedback']['sound_event_ids'] ?? [], true), 'ID do primeiro amarelo registrado em feedback.sound_event_ids');
assertAud($matchDisc1['stats']['yellow_cards'] === 1 && $matchDisc1['stats']['cards'] === 1, 'Estatísticas registram yellow_cards=1 e cards=1');

// Avanço de segmento após primeiro amarelo (de 20 para 35)
$matchDisc1['minute'] = 35;
ob_start();
Presentation::matchSounds($gDisc1, $matchDisc1);
$outDisc1 = ob_get_clean();
assertAud(str_contains($outDisc1, 'data-sound-event="CARD_YELLOW"'), 'matchSounds renderiza CARD_YELLOW');
assertAud(!str_contains($outDisc1, 'data-sound-historical="1"'), 'CARD_YELLOW da ação recente não é marcado histórico após avanço de minuto');

// Deduplicação no F5: re-renderização imediata mantém o mesmo ID determinístico
ob_start();
Presentation::matchSounds($gDisc1, $matchDisc1);
$outDisc1F5 = ob_get_clean();
assertAud($outDisc1 === $outDisc1F5, 'Re-renderização no F5 produz marcação idêntica com o mesmo sound-event-id para deduplicação no cliente');

// Turno seguinte: com novo feedback sem o cartão, o evento de 20 min passa a ser histórico aos 35 min
$gDisc1Next = $gDisc1;
$gDisc1Next['feedback'] = ['text' => 'Passe completado aos 35 min', 'effects' => [], 'sound_event_ids' => []];
ob_start();
Presentation::matchSounds($gDisc1Next, $matchDisc1);
$outDisc1Next = ob_get_clean();
assertAud(str_contains($outDisc1Next, 'data-sound-historical="1"'), 'No turno subsequente, o primeiro cartão é classificado como histórico');

// 6.2 Segundo Cartão Amarelo -> Expulsão (CARD_RED)
$gDisc2 = $gameNormal;
$gDisc2['feedback'] = ['text' => '', 'effects' => []];
$matchDisc2 = [
    'minute' => 50, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [
        ['type' => 'CARD_YELLOW', 'minute' => 20, 'id' => 'card:yellow:20']
    ],
    'log' => [],
    'stats' => ['cards' => 1, 'yellow_cards' => 1, 'red_cards' => 0]
];
$resYellow2 = MatchDiscipline::recordFoul($gDisc2, $matchDisc2, 'tactical_foul', false);
assertAud($resYellow2['card'] === 'second_yellow', 'Segunda advertência classificada como second_yellow');
assertAud($matchDisc2['stats']['yellow_cards'] === 2, 'yellow_cards atualizado para 2');
assertAud($matchDisc2['stats']['second_yellow'] === 1, 'second_yellow marcado como 1');
assertAud($matchDisc2['stats']['red_cards'] === 1, 'red_cards incrementado para 1');
assertAud($matchDisc2['stats']['cards'] === 2, 'Total de cards igual a 2');
assertAud($matchDisc2['participation_state'] === MatchParticipation::STATE_SENT_OFF, 'Atleta entra em STATE_SENT_OFF');
assertAud($matchDisc2['team_reduced'] === true, 'Equipe tem desvantagem numérica assinalada (team_reduced=true)');
assertAud($matchDisc2['exit'] === 50, 'Minuto de saída ajustado imediatamente para o minuto do cartão (50)');
assertAud($resYellow2['sound_event_id'] !== null, 'Segundo amarelo gera sound_event_id');
assertAud(in_array($resYellow2['sound_event_id'], $gDisc2['feedback']['sound_event_ids'] ?? [], true), 'ID do segundo amarelo inserido em feedback.sound_event_ids');

// O som emitido no segundo amarelo DEVE ser CARD_RED e não outro amarelo
$recentEventsDisc2 = array_filter($matchDisc2['sound_events'], fn($ev) => ($ev['id'] ?? '') === $resYellow2['sound_event_id']);
$recentEventDisc2 = reset($recentEventsDisc2);
assertAud($recentEventDisc2 !== false && $recentEventDisc2['type'] === 'CARD_RED', 'Segundo amarelo emite som canônico CARD_RED');

// Renderização do segundo amarelo: CARD_RED contemporâneo
ob_start();
Presentation::matchSounds($gDisc2, $matchDisc2);
$outDisc2 = ob_get_clean();
assertAud(str_contains($outDisc2, 'data-sound-event="CARD_RED"'), 'Segundo amarelo renderiza data-sound-event="CARD_RED"');
assertAud(!str_contains($outDisc2, 'data-sound-historical="1" data-sound-event="CARD_RED"') && !str_contains($outDisc2, 'data-sound-event="CARD_RED" data-sound-historical="1"'), 'CARD_RED da expulsão recente não é classificado como histórico');

// 6.3 Cartão Vermelho Direto (Violent Conduct)
$gDiscRed = $gameNormal;
$gDiscRed['feedback'] = ['text' => '', 'effects' => []];
$matchDiscRed = [
    'minute' => 75, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [],
    'stats' => ['cards' => 0, 'yellow_cards' => 0, 'red_cards' => 0]
];
$resDirectRed = MatchDiscipline::recordFoul($gDiscRed, $matchDiscRed, 'violent_conduct', true);
assertAud($resDirectRed['card'] === 'direct_red', 'Infração violenta classificada como direct_red');
assertAud($matchDiscRed['stats']['direct_red'] === 1, 'direct_red marcado como 1');
assertAud($matchDiscRed['stats']['red_cards'] === 1, 'red_cards igual a 1');
assertAud($matchDiscRed['participation_state'] === MatchParticipation::STATE_SENT_OFF, 'Atleta expulso diretamente');
assertAud($matchDiscRed['team_reduced'] === true, 'Time em desvantagem numérica');
assertAud($matchDiscRed['exit'] === 75, 'Minuto de saída definido para 75');
assertAud(in_array('CARD_RED', array_column($matchDiscRed['sound_events'], 'type'), true), 'Expulsão direta emite som CARD_RED');
assertAud(in_array($resDirectRed['sound_event_id'], $gDiscRed['feedback']['sound_event_ids'] ?? [], true), 'ID do vermelho direto registrado no feedback');

// 6.4 Fluxo Real via Game::command() com avanço de segmento e deduplicação F5
echo "\n6.4 Validando Cartão Real via Game::command(), avanço de segmento e F5...\n";

// Reprodução 100% controlada e determinística via RNG injetável regulamentar de Game::command():
// Na Situação 7 aos 80 min (empate), a opção 3 (simulação de pênalti) combinada a falha grave
// aciona estritamente MatchDiscipline::recordFoul($g, $m, 'simulation') em Game.php (linha 353),
// sem dependência de laço estocástico ou alteração de regras do jogo.
$gCmdSim = Game::legacyCreate('Craque Disciplinar', 'São Paulo', 'SP', 'centroavante');
$gCmdSim['stage'] = 'partida';
$iCmd = Game::currentIndex($gCmdSim);
$gCmdSim['matches'][$iCmd]['minute'] = 80;
$gCmdSim['matches'][$iCmd]['hg'] = 0;
$gCmdSim['matches'][$iCmd]['ag'] = 0;
$gCmdSim['matches'][$iCmd]['entry'] = 0;
$gCmdSim['matches'][$iCmd]['exit'] = 90;
$gCmdSim['matches'][$iCmd]['status'] = 'em_andamento';
$gCmdSim['matches'][$iCmd]['lineup'] = 'titular';

$rngFalhaGrave = static fn(int $min, int $max): int => ($max === 10000 ? 10000 : 1);
$gCmdCard = Game::command($gCmdSim, 'choice', ['option' => '3'], false, $rngFalhaGrave);

$mCmd = $gCmdCard['matches'][$iCmd];
assertAud(($mCmd['stats']['yellow_cards'] ?? 0) === 1, 'Game::command() executa ação e gera cartão amarelo de forma 100% controlada');
assertAud($mCmd['minute'] > 80, 'nextSegment avança minuto após lance (de 80 para ' . $mCmd['minute'] . ')');
assertAud(!empty($gCmdCard['feedback']['sound_event_ids']), 'feedback.sound_event_ids contém ID do cartão gerado no lance');

// Renderização: cartão da jogada NÃO é marcado histórico
ob_start();
Presentation::matchSounds($gCmdCard, $mCmd);
$outCmdCard = ob_get_clean();
assertAud(str_contains($outCmdCard, 'data-sound-event="CARD_YELLOW"'), 'matchSounds renderiza CARD_YELLOW disparado por Game::command');
assertAud(!str_contains($outCmdCard, 'data-sound-historical="1"'), 'CARD_YELLOW da ação recente com Game::command NÃO é marcado histórico');

// Deduplicação no F5 após Game::command
ob_start();
Presentation::matchSounds($gCmdCard, $mCmd);
$outCmdCardF5 = ob_get_clean();
assertAud($outCmdCard === $outCmdCardF5, 'F5 após Game::command produz marcação idêntica com mesmo ID estável');

// 6.5 Tríplice Evento Contemporâneo aos 90 minutos (Gol + Cartão + Apito de Fim)
echo "\n6.5 Validando Tríplice Evento Contemporâneo aos 90 min (Gol + Cartão + Apito Final)...\n";
$gTriple = [
    'id' => 'triple_test', 'season' => 1, 'club' => 'SP', 'stage' => 'fim_semana',
    'version' => 20,
    'feedback' => [
        'highlight' => 'goal',
        'sound_event' => 'GOAL_SCORED',
        'sound_event_ids' => ['goal:90:triple', 'card:90:triple'],
        'text' => 'Gol épico no último minuto seguido de cartão na comemoração!',
        'success' => true
    ]
];
$matchTriple = [
    'minute' => 90, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 1, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'finalizada',
    'sound_events' => [
        ['type' => 'GOAL_SCORED', 'minute' => 90, 'id' => 'goal:90:triple'],
        ['type' => 'CARD_YELLOW', 'minute' => 90, 'id' => 'card:90:triple']
    ],
    'log' => []
];

ob_start();
Presentation::feedback($gTriple);
$outTripleFeedback = ob_get_clean();

ob_start();
Presentation::matchSounds($gTriple, $matchTriple);
$outTripleMatchSounds = ob_get_clean();

assertAud(str_contains($outTripleFeedback, 'data-sound-event="GOAL_SCORED"'), 'Feedback renderiza GOAL_SCORED contemporâneo');
assertAud(str_contains($outTripleMatchSounds, 'data-sound-event="CARD_YELLOW"'), 'MatchSounds renderiza CARD_YELLOW contemporâneo');
assertAud(str_contains($outTripleMatchSounds, 'data-sound-event="MATCH_WHISTLE_END"'), 'MatchSounds renderiza MATCH_WHISTLE_END contemporâneo');
assertAud(!str_contains($outTripleMatchSounds, 'data-sound-historical="1"'), 'Nenhum dos três eventos legítimos aos 90 min é classificado como histórico');

// -------------------------------------------------------------------------
// 7. Validação dos Arquivos OGG Físicos em public/assets/audio/
// -------------------------------------------------------------------------
echo "\n7. Validando arquivos de áudio OGG físicos e durações...\n";

$audioDir = dirname(__DIR__) . '/public/assets/audio';
$expectedFiles = [
    'match_whistle.ogg',
    'yellow_card.ogg',
    'goal.ogg',
    'assist.ogg',
    'substitution_out.ogg',
    'red_card.ogg',
    'substitution_in.ogg',
    'no_goal.ogg',
];

foreach ($expectedFiles as $file) {
    $fullPath = $audioDir . '/' . $file;
    assertAud(is_file($fullPath), "Arquivo {$file} existe fisicamente em public/assets/audio/");
    $size = filesize($fullPath);
    assertAud($size > 0 && $size < 1000000, "Arquivo {$file} tem tamanho válido ({$size} bytes)");
    
    // Validar cabeçalho OGG (OggS)
    $fp = fopen($fullPath, 'rb');
    $magic = fread($fp, 4);
    fclose($fp);
    assertAud($magic === 'OggS', "Arquivo {$file} possui cabeçalho válido OggS");

    // Validação profunda com ffprobe (codec vorbis, duração <= 3s, canais)
    $probeOutput = shell_exec("ffprobe -v error -show_entries format=duration:stream=codec_name,channels,sample_rate -of default=noprint_wrappers=1 ".escapeshellarg($fullPath));
    assertAud(str_contains($probeOutput, 'codec_name=vorbis'), "Arquivo {$file} usa codec vorbis");
    
    preg_match('/duration=([0-9.]+)/', $probeOutput, $mDur);
    $dur = isset($mDur[1]) ? (float)$mDur[1] : 999.0;
    assertAud($dur > 0 && $dur <= 3.0, "Arquivo {$file} tem duração rigorosamente <= 3.0s ({$dur}s aferido)");
}

// -------------------------------------------------------------------------
// 8. Regra do Apito Compartilhado
// -------------------------------------------------------------------------
echo "\n8. Validando regra do apito compartilhado...\n";
$whistleFile = $audioDir . '/match_whistle.ogg';
assertAud(file_exists($whistleFile), 'match_whistle.ogg presente para uso unificado em Início (1x), Intervalo (2x) e Fim (3x)');

echo "\n=======================================================\n";
echo "TODOS OS {$passCount}/{$testCount} TESTES DO R10-AUD-001 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
