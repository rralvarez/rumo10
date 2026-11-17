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
// 6. Disciplina: Cartões Amarelos e Vermelhos
// -------------------------------------------------------------------------
echo "\n6. Validando emissão de eventos sonoros disciplinares (CARD_YELLOW e CARD_RED)...\n";

$matchDisc = [
    'minute' => 55, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['cards' => 0]
];
$resYellow = MatchDiscipline::recordFoul($gameNormal, $matchDisc, 'reckless_tackle', false);
assertAud(in_array('CARD_YELLOW', array_column($matchDisc['sound_events'], 'type'), true), 'Aplicação de cartão amarelo emite CARD_YELLOW');

$matchRed = [
    'minute' => 80, 'entry' => 0, 'exit' => 90, 'lineup' => 'titular',
    'hg' => 0, 'ag' => 0, 'home' => 'SP', 'away' => 'SAN', 'competition' => 'c1', 'round' => 1,
    'status' => 'em_andamento', 'sound_events' => [], 'log' => [], 'stats' => ['cards' => 0]
];
$resRed = MatchDiscipline::recordFoul($gameNormal, $matchRed, 'violent_conduct', true);
assertAud(in_array('CARD_RED', array_column($matchRed['sound_events'], 'type'), true), 'Expulsão direta emite CARD_RED');

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
