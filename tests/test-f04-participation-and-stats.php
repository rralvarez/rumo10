<?php
declare(strict_types=1);

// Isolamento estrito antes do bootstrap: garante que nenhum teste toque o MySQL ou RumoHub
putenv('DB_USERNAME=');
putenv('DB_PASSWORD=');
putenv('DB_DATABASE=isolated_no_db');
putenv('DB_HOST=invalid.local');
putenv('DB_PORT=65530');
putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');

require dirname(__DIR__) . '/app/bootstrap.php';

$checks = 0;
function f04Check(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA F04-R1: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

echo "=== R10-LOTE02-F04-R1: TESTES DO NÚCLEO CANÔNICO DE PARTICIPAÇÃO ===\n\n";

// 0. Comprovação de isolamento de banco antes de qualquer teste
$dbBlocked = false;
try {
    Database::connect();
} catch (RuntimeException $e) {
    $dbBlocked = true;
}
f04Check($dbBlocked, 'Isolamento estrito comprovado: Database::connect() bloqueado sem tocar o MySQL');

$rngFailSevere = static fn(int $min, int $max): int => $max === 10000 ? 9999 : 1;
$rngMidSuccess = static fn(int $min, int $max): int => $max === 10000 ? 100 : 50;

// =========================================================================
// 1. D1 — Elegibilidade para Disputa de Pênaltis (PenaltyShootout::start)
// =========================================================================
echo "\n1. Validando D1 (Elegibilidade na Disputa de Pênaltis)...\n";

$gPenBase = Game::legacyCreate('Craque D1', 'São Paulo', 'SP', 'meia');
$gPenBase['club'] = 'São Paulo';
$basePenMatch = Schedule::match(16, 'São Paulo', 'Santos');
$basePenMatch['status'] = 'finalizada';
$basePenMatch['minute'] = 90;
$basePenMatch['hg'] = 1;
$basePenMatch['ag'] = 1;

$ineligibleCases = [
    'suspenso_canonico' => ['lineup' => 'suspenso', 'participation_state' => MatchParticipation::STATE_SUSPENDED, 'entry' => 90, 'exit' => 90],
    'suspenso_legado' => ['lineup' => 'suspenso', 'entry' => 90, 'exit' => 90],
    'nao_relacionado_canonico' => ['lineup' => 'não relacionado', 'participation_state' => MatchParticipation::STATE_NOT_SELECTED, 'entry' => 90, 'exit' => 90],
    'nao_relacionado_legado' => ['lineup' => 'não relacionado', 'entry' => 90, 'exit' => 90],
    'reserva_nao_utilizado_canonico' => ['lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_ON_BENCH, 'entry' => 90, 'exit' => 90],
    'reserva_nao_utilizado_legado_90' => ['lineup' => 'reserva', 'entry' => 90, 'exit' => 90],
    'reserva_nao_utilizado_legado_0' => ['lineup' => 'reserva', 'entry' => 0, 'exit' => 0],
    'substituido_60m' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 0, 'exit' => 60, 'exit_reason' => 'fatigue'],
    'substituido_90m' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 0, 'exit' => 90, 'exit_reason' => 'tactical'],
    'saida_lesao' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 0, 'exit' => 70, 'exit_reason' => 'injury'],
    'expulso_segundo_amarelo' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SENT_OFF, 'entry' => 0, 'exit' => 75, 'red_card_minute' => 75, 'exit_reason' => 'sent_off', 'stats' => array_merge(Schedule::stats(), ['yellow_cards' => 2, 'second_yellow' => 1, 'red_cards' => 1, 'cards' => 2])],
    'expulso_vermelho_direto_90m' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SENT_OFF, 'entry' => 0, 'exit' => 90, 'red_card_minute' => 90, 'exit_reason' => 'sent_off', 'stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1])],
    'expulso_legado_cards_red' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['cards_red' => 1])],
    'expulso_legado_cards_2' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['cards' => 2])],
    'titular_sem_evidencia_entry_exit_zero' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 0],
    'starting_nao_entrou' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_STARTING, 'entry' => 0, 'exit' => 90],
    'sem_entry_exit_null' => ['lineup' => 'titular', 'entry' => null, 'exit' => null],
];

foreach ($ineligibleCases as $caseName => $override) {
    $gCase = $gPenBase;
    $mCase = array_merge($basePenMatch, $override);
    $st = PenaltyShootout::start($gCase, $mCase);
    f04Check($st['protagonist_eligible'] === false, "D1: '{$caseName}' impedido de cobrar pênalti");
}

$eligibleCases = [
    'titular_full_time' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 0, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 90])],
    'reserva_entrou_60_full_time' => ['lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 60, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 30])],
    'legado_titular_sem_state' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 90])],
    'legado_reserva_entrou_70' => ['lineup' => 'reserva', 'entry' => 70, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 20])],
    'legado_fixture_schedule_match' => ['lineup' => '', 'entry' => 0, 'exit' => 0, 'status' => 'finalizada', 'minute' => 90],
];

foreach ($eligibleCases as $caseName => $override) {
    $gCase = $gPenBase;
    $mCase = array_merge($basePenMatch, $override);
    $st = PenaltyShootout::start($gCase, $mCase);
    f04Check($st['protagonist_eligible'] === true, "D1: '{$caseName}' permanece elegível para cobrar pênalti");
}

// =========================================================================
// 2. D2 — Reserva que atua e sai no minuto da entrada (mínimo 1 minuto)
// =========================================================================
echo "\n2. Validando D2 (Participação Real e Minutagem Mínima)...\n";

// 2.1 Reserva entra aos 60', atua aos 60' e sai por desgaste aos 60'
$gSubFatigue = Game::legacyCreate('Reserva D2 Desgaste', 'São Paulo', 'SP', 'meia');
$gSubFatigue['stage'] = 'partida';
$gSubFatigue['states']['energia'] = 10;
$gSubFatigue['states']['risco_lesao'] = 0;
$idx2 = Game::currentIndex($gSubFatigue);
$gSubFatigue['matches'][$idx2]['status'] = 'em_andamento';
$gSubFatigue['matches'][$idx2]['minute'] = 60;
$gSubFatigue['matches'][$idx2]['lineup'] = 'reserva';
$gSubFatigue['matches'][$idx2]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gSubFatigue['matches'][$idx2]['entry'] = 60;
$gSubFatigue['matches'][$idx2]['exit'] = 90;
$gSubFatigue = Game::command($gSubFatigue, 'choice', ['option' => 0], false, $rngMidSuccess);
while ($gSubFatigue['stage'] === 'partida') {
    $gSubFatigue = Game::command($gSubFatigue, 'advance');
}
$mSubFatigue = $gSubFatigue['matches'][$idx2];
f04Check($mSubFatigue['entry'] === 60 && $mSubFatigue['exit'] === 60, 'D2 (desgaste): timestamps entry=60 e exit=60 preservados sem adulteração');
f04Check($mSubFatigue['stats']['minutes'] === 1, 'D2 (desgaste): contabiliza mínimo de 1 minuto estatístico após atuação aos 60\'');
f04Check($mSubFatigue['stats']['rating'] > 0, 'D2 (desgaste): recebe nota válida de participação (> 0)');
f04Check(Game::summary($gSubFatigue)['games'] === 1, 'D2 (desgaste): Game::summary contabiliza 1 jogo disputado');
f04Check($mSubFatigue['exit_reason'] === 'fatigue', 'D2 (desgaste): registra exit_reason=fatigue');

// 2.2 Reserva entra aos 70', atua aos 70' e sai lesionado aos 70'
$gSubInjury = Game::legacyCreate('Reserva D2 Lesão', 'São Paulo', 'SP', 'meia');
$gSubInjury['stage'] = 'partida';
$gSubInjury['states']['energia'] = 80;
$gSubInjury['states']['risco_lesao'] = 1000; // força lesão em random_int(1,1000) <= risco_lesao
$idx2b = Game::currentIndex($gSubInjury);
$gSubInjury['matches'][$idx2b]['status'] = 'em_andamento';
$gSubInjury['matches'][$idx2b]['minute'] = 70;
$gSubInjury['matches'][$idx2b]['lineup'] = 'reserva';
$gSubInjury['matches'][$idx2b]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gSubInjury['matches'][$idx2b]['entry'] = 70;
$gSubInjury['matches'][$idx2b]['exit'] = 90;
$gSubInjury = Game::command($gSubInjury, 'choice', ['option' => 0], false, $rngMidSuccess);
while ($gSubInjury['stage'] === 'partida') {
    $gSubInjury = Game::command($gSubInjury, 'advance');
}
$mSubInjury = $gSubInjury['matches'][$idx2b];
f04Check($mSubInjury['entry'] === 70 && $mSubInjury['exit'] === 70, 'D2 (lesão): timestamps entry=70 e exit=70 preservados');
f04Check($mSubInjury['stats']['minutes'] === 1 && Game::summary($gSubInjury)['games'] === 1, 'D2 (lesão): contabiliza 1 minuto e 1 jogo disputado');
f04Check($mSubInjury['exit_reason'] === 'injury', 'D2 (lesão): registra exit_reason=injury');

// 2.3 Reserva entra aos 80', atua aos 80' e é expulso por 2º amarelo aos 80'
$gSubSentOff = Game::legacyCreate('Reserva D2 Expulso', 'São Paulo', 'SP', 'meia');
$gSubSentOff['stage'] = 'partida';
$gSubSentOff['states']['energia'] = 80;
$gSubSentOff['states']['risco_lesao'] = 0;
$idx2c = Game::currentIndex($gSubSentOff);
$gSubSentOff['matches'][$idx2c]['status'] = 'em_andamento';
$gSubSentOff['matches'][$idx2c]['minute'] = 80;
$gSubSentOff['matches'][$idx2c]['hg'] = 1;
$gSubSentOff['matches'][$idx2c]['ag'] = 1;
$gSubSentOff['matches'][$idx2c]['lineup'] = 'reserva';
$gSubSentOff['matches'][$idx2c]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gSubSentOff['matches'][$idx2c]['entry'] = 80;
$gSubSentOff['matches'][$idx2c]['exit'] = 90;
$gSubSentOff['matches'][$idx2c]['stats']['yellow_cards'] = 1;
$gSubSentOff['matches'][$idx2c]['stats']['cards'] = 1;
$gSubSentOff = Game::command($gSubSentOff, 'choice', ['option' => 3], false, $rngFailSevere);
while ($gSubSentOff['stage'] === 'partida') {
    $gSubSentOff = Game::command($gSubSentOff, 'advance');
}
$mSubSentOff = $gSubSentOff['matches'][$idx2c];
f04Check($mSubSentOff['entry'] === 80 && $mSubSentOff['exit'] === 80, 'D2 (expulsão): timestamps entry=80 e exit=80 preservados');
f04Check($mSubSentOff['stats']['minutes'] === 1 && Game::summary($gSubSentOff)['games'] === 1, 'D2 (expulsão): contabiliza 1 minuto e 1 jogo disputado');
f04Check($mSubSentOff['participation_state'] === MatchParticipation::STATE_SENT_OFF && $mSubSentOff['exit_reason'] === 'sent_off', 'D2 (expulsão): preserva estado SENT_OFF e exit_reason=sent_off');

// 2.4 Reserva não utilizado, suspenso e não relacionado permanecem estritamente com 0 minutos e 0 jogos
foreach ([
    'unused_bench' => ['lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_ON_BENCH, 'entry' => 90, 'exit' => 90],
    'suspended' => ['lineup' => 'suspenso', 'participation_state' => MatchParticipation::STATE_SUSPENDED, 'entry' => 90, 'exit' => 90],
    'not_selected' => ['lineup' => 'não relacionado', 'participation_state' => MatchParticipation::STATE_NOT_SELECTED, 'entry' => 90, 'exit' => 90],
] as $absName => $absOv) {
    $gAbs = Game::legacyCreate('Ausente ' . $absName, 'São Paulo', 'SP', 'meia');
    $gAbs['stage'] = 'partida';
    $idxA = Game::currentIndex($gAbs);
    $gAbs['matches'][$idxA] = array_merge($gAbs['matches'][$idxA], ['status' => 'em_andamento', 'minute' => 80], $absOv);
    $gAbs = Game::command($gAbs, 'advance');
    f04Check($gAbs['matches'][$idxA]['stats']['minutes'] === 0 && $gAbs['matches'][$idxA]['stats']['rating'] === 0 && Game::summary($gAbs)['games'] === 0, "D2 ({$absName}): permanece com 0 minutos, nota 0 e 0 jogos");
}

// =========================================================================
// 3. D3 — Contagem Única de Faltas
// =========================================================================
echo "\n3. Validando D3 (Contagem Única de Faltas)...\n";

// 3.1 MatchDiscipline::recordFoul direto: falta sem cartão, amarelo, 2º amarelo, vermelho direto
$gFoul = Game::legacyCreate('Faltas D3', 'São Paulo', 'SP', 'volante');
$mFoul = Schedule::match(1, 'São Paulo', 'Santos');
$mFoul['status'] = 'em_andamento';
$mFoul['minute'] = 20;
$mFoul['participation_state'] = MatchParticipation::STATE_ON_FIELD;

$rMinor = MatchDiscipline::recordFoul($gFoul, $mFoul, 'minor');
f04Check($mFoul['stats']['fouls'] === 1 && $rMinor['card'] === null && $mFoul['stats']['cards'] === 0, 'D3: falta sem cartão (minor) contabiliza exatamente 1 falta');

$rYellow1 = MatchDiscipline::recordFoul($gFoul, $mFoul, 'late_tackle');
f04Check($mFoul['stats']['fouls'] === 2 && $rYellow1['card'] === 'yellow' && $mFoul['stats']['yellow_cards'] === 1, 'D3: falta com 1º amarelo contabiliza +1 falta (total 2)');

$rYellow2 = MatchDiscipline::recordFoul($gFoul, $mFoul, 'late_tackle');
f04Check($mFoul['stats']['fouls'] === 3 && $rYellow2['card'] === 'second_yellow' && $mFoul['stats']['red_cards'] === 1, 'D3: falta com 2º amarelo contabiliza +1 falta (total 3) e expulsa');

$rAfterSentOff = MatchDiscipline::recordFoul($gFoul, $mFoul, 'argument');
f04Check($mFoul['stats']['fouls'] === 3 && $rAfterSentOff['card'] === null, 'D3: atleta já expulso não acumula falta ou cartão adicional');

$mDirectRed = Schedule::match(2, 'São Paulo', 'Santos');
$mDirectRed['status'] = 'em_andamento';
$mDirectRed['minute'] = 30;
$mDirectRed['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$rDirect = MatchDiscipline::recordFoul($gFoul, $mDirectRed, 'violent_conduct', true);
f04Check($mDirectRed['stats']['fouls'] === 1 && $rDirect['card'] === 'direct_red' && $mDirectRed['stats']['red_cards'] === 1 && $mDirectRed['stats']['cards'] === 1, 'D3: vermelho direto contabiliza exatamente 1 falta e 1 cartão');

// 3.2 S7 (simulação, option=3) via Game::command
$gSimul = Game::legacyCreate('Simulação D3', 'São Paulo', 'SP', 'meia');
$gSimul['stage'] = 'partida';
$gSimul['states']['energia'] = 90;
$gSimul['states']['risco_lesao'] = 0;
$idxS7 = Game::currentIndex($gSimul);
$gSimul['matches'][$idxS7]['status'] = 'em_andamento';
$gSimul['matches'][$idxS7]['minute'] = 80;
$gSimul['matches'][$idxS7]['hg'] = 1;
$gSimul['matches'][$idxS7]['ag'] = 1;
$gSimul['matches'][$idxS7]['entry'] = 0;
$gSimul['matches'][$idxS7]['exit'] = 90;
$gSimul['matches'][$idxS7]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gSimul = Game::command($gSimul, 'choice', ['option' => 3], false, $rngFailSevere);
f04Check($gSimul['matches'][$idxS7]['stats']['fouls'] === 1 && ($gSimul['matches'][$idxS7]['stats']['yellow_cards'] ?? 0) === 1, 'D3: S7 simulação com cartão contabiliza exatamente 1 falta (não 2)');

// 3.3 S8 (reclamação, option=0) — testar múltiplas execuções cobrindo com cartão e sem cartão
$seenDissentWithCard = false;
$seenDissentNoCard = false;
for ($iter = 0; $iter < 40; $iter++) {
    $gS8 = Game::legacyCreate('Reclamação D3', 'São Paulo', 'SP', 'meia');
    $gS8['stage'] = 'partida';
    $gS8['states']['energia'] = 90;
    $gS8['states']['risco_lesao'] = 0;
    $idxS8 = Game::currentIndex($gS8);
    $gS8['matches'][$idxS8]['status'] = 'em_andamento';
    $gS8['matches'][$idxS8]['minute'] = 35;
    $gS8['matches'][$idxS8]['entry'] = 0;
    $gS8['matches'][$idxS8]['exit'] = 90;
    $gS8['matches'][$idxS8]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    $gS8 = Game::command($gS8, 'choice', ['option' => 0], false, $rngMidSuccess);
    $fCount = $gS8['matches'][$idxS8]['stats']['fouls'];
    $cCount = $gS8['matches'][$idxS8]['stats']['cards'];
    if ($fCount !== 1) {
        throw new RuntimeException("FALHA D3 S8: esperado fouls=1, obtido {$fCount} (cards={$cCount})");
    }
    if ($cCount > 0) $seenDissentWithCard = true;
    else $seenDissentNoCard = true;
    if ($seenDissentWithCard && $seenDissentNoCard) break;
}
f04Check($seenDissentWithCard && $seenDissentNoCard, 'D3: S8 reclamação contabiliza exatamente 1 falta tanto com cartão quanto sem cartão');

// 3.4 Desarme atrasado (falha_grave em desarme) — cobrindo com cartão e sem cartão
$seenTackleWithCard = false;
$seenTackleNoCard = false;
for ($iter = 0; $iter < 60; $iter++) {
    $gTac = Game::legacyCreate('Desarme D3', 'São Paulo', 'SP', 'zagueiro');
    $gTac['stage'] = 'partida';
    $gTac['states']['energia'] = 90;
    $gTac['states']['risco_lesao'] = 0;
    $idxTac = Game::currentIndex($gTac);
    $gTac['matches'][$idxTac]['status'] = 'em_andamento';
    $gTac['matches'][$idxTac]['minute'] = 15;
    $gTac['matches'][$idxTac]['entry'] = 0;
    $gTac['matches'][$idxTac]['exit'] = 90;
    $gTac['matches'][$idxTac]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    // Garante situação padrão de partida (opção 0 = desarme para zagueiro)
    unset($gTac['active_situation']);
    $gTac = Game::command($gTac, 'choice', ['option' => 0], false, $rngFailSevere);
    $fCount = $gTac['matches'][$idxTac]['stats']['fouls'];
    $cCount = $gTac['matches'][$idxTac]['stats']['cards'];
    if ($fCount !== 1) {
        throw new RuntimeException("FALHA D3 Desarme: esperado fouls=1, obtido {$fCount} (cards={$cCount})");
    }
    if ($cCount > 0) $seenTackleWithCard = true;
    else $seenTackleNoCard = true;
    if ($seenTackleWithCard && $seenTackleNoCard) break;
}
f04Check($seenTackleWithCard && $seenTackleNoCard, 'D3: desarme atrasado contabiliza exatamente 1 falta tanto com cartão quanto sem cartão');

// =========================================================================
// 4. D5 — Máquina Canônica de Participação (Idempotência, Terminais, FULL_TIME)
// =========================================================================
echo "\n4. Validando D5 (Máquina Canônica de Participação)...\n";

$gSync = Game::legacyCreate('Sync D5', 'São Paulo', 'SP', 'meia');

// 4.1 Idempotência de reserva não utilizado no fim da partida
$mUnused = Schedule::match(1, 'São Paulo', 'Santos');
$mUnused['lineup'] = 'reserva';
$mUnused['participation_state'] = MatchParticipation::STATE_ON_BENCH;
$mUnused['entry'] = 90;
$mUnused['exit'] = 90;
$mUnused['status'] = 'finalizada';
MatchParticipation::sync($mUnused, $gSync, 90);
MatchParticipation::sync($mUnused, $gSync, 90);
MatchParticipation::sync($mUnused, $gSync, 90);
f04Check(count($mUnused['log']) === 1, 'D5: log de reserva não utilizado gravado exatamente 1 vez após 3 chamadas de sync()');

// 4.2 Idempotência de entrada do banco e saída por substituição
$mSubIn = Schedule::match(2, 'São Paulo', 'Santos');
$mSubIn['lineup'] = 'reserva';
$mSubIn['participation_state'] = MatchParticipation::STATE_ON_BENCH;
$mSubIn['entry'] = 60;
$mSubIn['exit'] = 70;
$mSubIn['status'] = 'em_andamento';
MatchParticipation::sync($mSubIn, $gSync, 60);
MatchParticipation::sync($mSubIn, $gSync, 60);
$subInSounds = array_filter($mSubIn['sound_events'] ?? [], fn($e) => ($e['type'] ?? '') === 'PLAYER_SUBSTITUTION_IN');
f04Check($mSubIn['participation_state'] === MatchParticipation::STATE_ON_FIELD && count($subInSounds) === 1, 'D5: entrada do banco idempotente (1 evento sonoro e estado ON_FIELD)');

MatchParticipation::sync($mSubIn, $gSync, 70);
MatchParticipation::sync($mSubIn, $gSync, 70);
MatchParticipation::sync($mSubIn, $gSync, 65); // tentativa de regressão de minuto não reverte SUBSTITUTED_OUT
$subOutSounds = array_filter($mSubIn['sound_events'] ?? [], fn($e) => ($e['type'] ?? '') === 'PLAYER_SUBSTITUTION_OUT');
f04Check($mSubIn['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT && count($subOutSounds) === 1, 'D5: SUBSTITUTED_OUT é estado terminal imutável com 1 evento sonoro');

// 4.3 Expulso não retorna a campo nem recebe decisão
$mSentOff = Schedule::match(3, 'São Paulo', 'Santos');
$mSentOff['lineup'] = 'titular';
$mSentOff['participation_state'] = MatchParticipation::STATE_SENT_OFF;
$mSentOff['entry'] = 0;
$mSentOff['exit'] = 40;
$mSentOff['status'] = 'em_andamento';
MatchParticipation::sync($mSentOff, $gSync, 30);
MatchParticipation::sync($mSentOff, $gSync, 90);
f04Check($mSentOff['participation_state'] === MatchParticipation::STATE_SENT_OFF, 'D5: SENT_OFF permanece imutável em chamadas subsequentes de sync()');

$gBlockedChoice = Game::legacyCreate('Bloqueio D5', 'São Paulo', 'SP', 'meia');
$gBlockedChoice['stage'] = 'partida';
$idxBlk = Game::currentIndex($gBlockedChoice);
$gBlockedChoice['matches'][$idxBlk]['status'] = 'em_andamento';
$gBlockedChoice['matches'][$idxBlk]['minute'] = 70;
$gBlockedChoice['matches'][$idxBlk]['entry'] = 0;
$gBlockedChoice['matches'][$idxBlk]['exit'] = 90;
$gBlockedChoice['matches'][$idxBlk]['participation_state'] = MatchParticipation::STATE_SENT_OFF;
$choiceBlocked = false;
try {
    Game::command($gBlockedChoice, 'choice', ['option' => 0]);
} catch (InvalidArgumentException $e) {
    $choiceBlocked = true;
}
f04Check($choiceBlocked, 'D5: Game::command(choice) rejeitado para atleta com STATE_SENT_OFF');

// 4.4 FULL_TIME somente quando status === 'finalizada' (compatibilidade com acréscimos 93')
$mStoppage = Schedule::match(4, 'São Paulo', 'Santos');
$mStoppage['lineup'] = 'titular';
$mStoppage['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mStoppage['entry'] = 0;
$mStoppage['exit'] = 90;
$mStoppage['status'] = 'em_andamento';
$mStoppage['minute'] = 93;
MatchParticipation::sync($mStoppage, $gSync, 93);
f04Check($mStoppage['participation_state'] === MatchParticipation::STATE_ON_FIELD, 'D5: status=em_andamento aos 93\' mantém ON_FIELD (não antecipa FULL_TIME)');

$mStoppage['status'] = 'finalizada';
MatchParticipation::sync($mStoppage, $gSync, 93);
f04Check($mStoppage['participation_state'] === MatchParticipation::STATE_FULL_TIME, 'D5: FULL_TIME atribuído apenas após status=finalizada');

// 4.5 Motor de segmentos não retrocede relógio de 93' para 90'
$gNoRollback = Game::legacyCreate('Sem Retrocesso D5', 'São Paulo', 'SP', 'meia');
$gNoRollback['stage'] = 'partida';
$idxNR = Game::currentIndex($gNoRollback);
$gNoRollback['matches'][$idxNR]['status'] = 'em_andamento';
$gNoRollback['matches'][$idxNR]['minute'] = 93;
$gNoRollback['matches'][$idxNR]['entry'] = 0;
$gNoRollback['matches'][$idxNR]['exit'] = 90;
$gNoRollback['matches'][$idxNR]['participation_state'] = MatchParticipation::STATE_SUBSTITUTED_OUT;
$gNoRollback = Game::command($gNoRollback, 'advance');
f04Check($gNoRollback['matches'][$idxNR]['minute'] === 93 && $gNoRollback['matches'][$idxNR]['status'] === 'finalizada', 'D5: avanço a partir de 93\' encerra sem retroceder o relógio para 90\'');

// =========================================================================
// 5. D6 — Expulsões, Lesões, Desgaste e Contagem Oficial de Vermelhos
// =========================================================================
echo "\n5. Validando D6 (Causa Estruturada de Saída, Vermelho Direto e Áudio Único)...\n";

// 5.1 Segundo amarelo não duplica texto, registra exit_reason=sent_off e preserva NarrativeContext
$g2ndYellow = Game::legacyCreate('Segundo Amarelo D6', 'São Paulo', 'SP', 'meia');
$g2ndYellow['stage'] = 'partida';
$g2ndYellow['states']['energia'] = 10; // energia baixa NÃO pode virar segunda causa 'fatigue'
$g2ndYellow['states']['risco_lesao'] = 1000; // risco alto NÃO pode virar segunda causa 'injury'
$idxY2 = Game::currentIndex($g2ndYellow);
$g2ndYellow['matches'][$idxY2]['status'] = 'em_andamento';
$g2ndYellow['matches'][$idxY2]['minute'] = 80;
$g2ndYellow['matches'][$idxY2]['hg'] = 1;
$g2ndYellow['matches'][$idxY2]['ag'] = 1;
$g2ndYellow['matches'][$idxY2]['entry'] = 0;
$g2ndYellow['matches'][$idxY2]['exit'] = 90;
$g2ndYellow['matches'][$idxY2]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$g2ndYellow['matches'][$idxY2]['stats']['yellow_cards'] = 1;
$g2ndYellow['matches'][$idxY2]['stats']['cards'] = 1;
$g2ndYellow = Game::command($g2ndYellow, 'choice', ['option' => 3], false, $rngFailSevere);
$mY2 = $g2ndYellow['matches'][$idxY2];
$fbText = $g2ndYellow['feedback']['text'] ?? '';
f04Check(str_contains($fbText, 'Segundo cartão amarelo! Você está expulso da partida.'), 'D6: texto de expulsão por 2º amarelo presente');
f04Check(!str_contains($fbText, 'Expulso: segundo cartão.'), 'D6: sem mensagem duplicada "Expulso: segundo cartão."');
f04Check(!str_contains($fbText, 'Lesão:') && !str_contains($fbText, 'Substituído por desgaste.'), 'D6: atleta expulso não recebe segunda causa de saída (lesão/desgaste) na mesma jogada');
f04Check($mY2['participation_state'] === MatchParticipation::STATE_SENT_OFF && $mY2['exit_reason'] === 'sent_off', 'D6: expulsão define STATE_SENT_OFF e exit_reason=sent_off');
f04Check((NarrativeContext::values($g2ndYellow)['exit_reason'] ?? null) === 'red', 'D6: NarrativeContext mapeia exit_reason=sent_off para red');
$redSoundsY2 = array_filter($mY2['sound_events'] ?? [], fn($e) => ($e['type'] ?? '') === 'CARD_RED');
f04Check(count($redSoundsY2) === 1, 'D6: CARD_RED emitido exatamente 1 vez');

// 5.2 Vermelho direto reconhecido com cards=1 e contabilizado corretamente em home_red/away_red oficial
$gDirectOfficial = Game::create('Vermelho Direto Oficial', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gDirectOfficial = Game::command($gDirectOfficial, 'handicap');
$gDirectOfficial['stage'] = 'partida';
$idxDO = Game::currentIndex($gDirectOfficial);
$gDirectOfficial['matches'][$idxDO]['status'] = 'em_andamento';
$gDirectOfficial['matches'][$idxDO]['minute'] = 80;
$gDirectOfficial['matches'][$idxDO]['entry'] = 0;
$gDirectOfficial['matches'][$idxDO]['exit'] = 90;
$gDirectOfficial['matches'][$idxDO]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
MatchDiscipline::recordFoul($gDirectOfficial, $gDirectOfficial['matches'][$idxDO], 'violent_conduct', true);
f04Check($gDirectOfficial['matches'][$idxDO]['participation_state'] === MatchParticipation::STATE_SENT_OFF && $gDirectOfficial['matches'][$idxDO]['exit_reason'] === 'sent_off', 'D6: vermelho direto atualiza imediatamente STATE_SENT_OFF e exit_reason=sent_off');
// Tentar aplicar lesão/desgaste após vermelho direto não altera causa
MatchParticipation::recordExit($gDirectOfficial['matches'][$idxDO], $gDirectOfficial, 80, 'injury');
f04Check($gDirectOfficial['matches'][$idxDO]['participation_state'] === MatchParticipation::STATE_SENT_OFF && $gDirectOfficial['matches'][$idxDO]['exit_reason'] === 'sent_off', 'D6: vermelho direto (cards=1) impede sobrescrita por lesão/desgaste');
$gDirectOfficial = Game::command($gDirectOfficial, 'advance');
$mDO = $gDirectOfficial['matches'][$idxDO];
$ownSide = $mDO['home'] === $gDirectOfficial['club'] ? 'home' : 'away';
f04Check(($mDO[$ownSide . '_red'] ?? 0) === 1, 'D6: estatística oficial da competição contabiliza vermelho direto (home_red/away_red = 1 mesmo com cards=1)');

// 5.3 Lesão e desgaste não geram log genérico duplicado nem segundo PLAYER_SUBSTITUTION_OUT
$gInjLog = Game::legacyCreate('Lesão Log Único D6', 'São Paulo', 'SP', 'meia');
$gInjLog['stage'] = 'partida';
$gInjLog['states']['energia'] = 90;
$gInjLog['states']['risco_lesao'] = 1000;
$idxIL = Game::currentIndex($gInjLog);
$gInjLog['matches'][$idxIL]['status'] = 'em_andamento';
$gInjLog['matches'][$idxIL]['minute'] = 35;
$gInjLog['matches'][$idxIL]['entry'] = 0;
$gInjLog['matches'][$idxIL]['exit'] = 90;
$gInjLog['matches'][$idxIL]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gInjLog = Game::command($gInjLog, 'choice', ['option' => 0], false, $rngMidSuccess);
while ($gInjLog['stage'] === 'partida') {
    $gInjLog = Game::command($gInjLog, 'advance');
}
$mIL = $gInjLog['matches'][$idxIL];
$genericSubLogs = array_filter($mIL['log'], fn($l) => str_contains($l['text'], 'Você é substituído após participar da partida.'));
$subOutInjSounds = array_filter($mIL['sound_events'] ?? [], fn($e) => ($e['type'] ?? '') === 'PLAYER_SUBSTITUTION_OUT');
f04Check($mIL['exit_reason'] === 'injury' && $mIL['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT, 'D6: lesão encerra imediatamente com STATE_SUBSTITUTED_OUT e exit_reason=injury');
f04Check(count($genericSubLogs) === 0, 'D6: lesão não gera log genérico duplicado de substituição no sync seguinte');
f04Check(count($subOutInjSounds) === 1, 'D6: PLAYER_SUBSTITUTION_OUT emitido exatamente 1 vez na saída por lesão');

// =========================================================================
// 6. D7 — Ritmo de Jogo (+3 somente quando há participação efetiva, idempotente)
// =========================================================================
echo "\n6. Validando D7 (Ritmo de Jogo Condicionado à Participação Efetiva)...\n";

// 6.1 Titular que atuou recebe +3
$gRitStarter = Game::legacyCreate('Ritmo Titular', 'São Paulo', 'SP', 'meia');
$gRitStarter['stage'] = 'partida';
$gRitStarter['states']['ritmo_jogo'] = 50;
$gRitStarter['states']['energia'] = 90;
$gRitStarter['states']['risco_lesao'] = 0;
$idxRS = Game::currentIndex($gRitStarter);
$gRitStarter['matches'][$idxRS]['status'] = 'em_andamento';
$gRitStarter['matches'][$idxRS]['minute'] = 80;
$gRitStarter['matches'][$idxRS]['lineup'] = 'titular';
$gRitStarter['matches'][$idxRS]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gRitStarter['matches'][$idxRS]['entry'] = 0;
$gRitStarter['matches'][$idxRS]['exit'] = 90;
$gRitStarter['matches'][$idxRS]['evaluated_penalties'] = ['80-90'];
$gRitStarter = Game::command($gRitStarter, 'choice', ['option' => 0], false, $rngMidSuccess);
while ($gRitStarter['stage'] === 'partida') {
    $mCur = $gRitStarter['matches'][$idxRS];
    $gRitStarter = MatchParticipation::isOnField($mCur)
        ? Game::command($gRitStarter, 'choice', ['option' => 0], false, $rngMidSuccess)
        : Game::command($gRitStarter, 'advance');
}
f04Check($gRitStarter['states']['ritmo_jogo'] === 53 && $gRitStarter['matches'][$idxRS]['stats']['minutes'] === 90, 'D7: titular com 90\' recebe +3 em ritmo_jogo (50 -> 53)');

// 6.2 Reserva utilizado recebe +3
$gRitSub = Game::legacyCreate('Ritmo Reserva', 'São Paulo', 'SP', 'meia');
$gRitSub['stage'] = 'partida';
$gRitSub['states']['ritmo_jogo'] = 50;
$gRitSub['states']['energia'] = 90;
$gRitSub['states']['risco_lesao'] = 0;
$idxRSub = Game::currentIndex($gRitSub);
$gRitSub['matches'][$idxRSub]['status'] = 'em_andamento';
$gRitSub['matches'][$idxRSub]['minute'] = 80;
$gRitSub['matches'][$idxRSub]['lineup'] = 'reserva';
$gRitSub['matches'][$idxRSub]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$gRitSub['matches'][$idxRSub]['entry'] = 60;
$gRitSub['matches'][$idxRSub]['exit'] = 90;
$gRitSub['matches'][$idxRSub]['evaluated_penalties'] = ['80-90'];
$gRitSub = Game::command($gRitSub, 'choice', ['option' => 0], false, $rngMidSuccess);
while ($gRitSub['stage'] === 'partida') {
    $mCur = $gRitSub['matches'][$idxRSub];
    $gRitSub = MatchParticipation::isOnField($mCur)
        ? Game::command($gRitSub, 'choice', ['option' => 0], false, $rngMidSuccess)
        : Game::command($gRitSub, 'advance');
}
f04Check($gRitSub['states']['ritmo_jogo'] === 53 && $gRitSub['matches'][$idxRSub]['stats']['minutes'] === 30, 'D7: reserva utilizado (30\') recebe +3 em ritmo_jogo (50 -> 53)');

// 6.3 Reserva não utilizado, suspenso e não relacionado NÃO recebem +3
foreach ([
    'reserva_nao_utilizado' => ['lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_ON_BENCH, 'entry' => 90, 'exit' => 90],
    'suspenso' => ['lineup' => 'suspenso', 'participation_state' => MatchParticipation::STATE_SUSPENDED, 'entry' => 90, 'exit' => 90],
    'nao_relacionado' => ['lineup' => 'não relacionado', 'participation_state' => MatchParticipation::STATE_NOT_SELECTED, 'entry' => 90, 'exit' => 90],
] as $label => $ov) {
    $gRitAbs = Game::legacyCreate('Ritmo ' . $label, 'São Paulo', 'SP', 'meia');
    $gRitAbs['stage'] = 'partida';
    $gRitAbs['states']['ritmo_jogo'] = 50;
    $idxRA = Game::currentIndex($gRitAbs);
    $gRitAbs['matches'][$idxRA] = array_merge($gRitAbs['matches'][$idxRA], ['status' => 'em_andamento', 'minute' => 80], $ov);
    $gRitAbs = Game::command($gRitAbs, 'advance');
    f04Check($gRitAbs['states']['ritmo_jogo'] === 50 && $gRitAbs['matches'][$idxRA]['stats']['minutes'] === 0, "D7 ({$label}): 0 minutos não concede bônus em ritmo_jogo (mantém 50)");
}

// 6.4 Idempotência de ritmo_jogo via reward_ledger
$mRhythm = $gRitStarter['matches'][$idxRS];
$rhythmKey = 'f04:rhythm:' . $gRitStarter['season'] . ':legacy:' . $mRhythm['round'] . ':' . $mRhythm['home'] . ':' . $mRhythm['away'];
f04Check(isset($gRitStarter['reward_ledger'][$rhythmKey]), 'D7: chave idempotente f04:rhythm gravada no reward_ledger');
$gRitReplay = $gRitStarter;
$gRitReplay['stage'] = 'partida';
$gRitReplay['matches'][$idxRS]['status'] = 'em_andamento';
$gRitReplay['matches'][$idxRS]['minute'] = 80;
$gRitReplay['matches'][$idxRS]['participation_state'] = MatchParticipation::STATE_SUBSTITUTED_OUT;
$gRitReplay = Game::command($gRitReplay, 'advance');
f04Check($gRitReplay['states']['ritmo_jogo'] === 53, 'D7: reexecução de encerramento não duplica +3 em ritmo_jogo (mantém 53)');

echo "\n=======================================================\n";
echo "TODOS OS {$checks} TESTES DA MISSÃO R10-LOTE02-F04-R1 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
