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
    'save_ambiguo_sem_escalacao_nem_evidencia' => ['lineup' => '', 'entry' => 0, 'exit' => 0, 'status' => 'finalizada', 'minute' => 90],
    'save_ambiguo_janela_padrao_sem_evidencia' => ['lineup' => '', 'entry' => 0, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90],
    'reserva_janela_planejada_sem_entrada' => ['lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90],
    'full_time_sem_evidencia_positiva' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 0, 'exit' => 90],
    'reserva_apenas_stats_fouls_1' => ['lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 0, 'fouls' => 1])],
    'reserva_apenas_minutes_sem_entrada' => ['lineup' => 'reserva', 'entry' => 70, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 20])],
    'reserva_stats_sem_permanencia_final' => ['lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 15, 'goals' => 1, 'passes' => 5, 'shots' => 2, 'tackles' => 1, 'fouls' => 1])],
    'reserva_sub_in_mas_lesionado_exit_90' => ['lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'status' => 'finalizada', 'minute' => 90, 'sub_in_recorded' => true, 'exit_reason' => 'injury', 'stats' => array_merge(Schedule::stats(), ['minutes' => 30])],
];

foreach ($ineligibleCases as $caseName => $override) {
    $gCase = $gPenBase;
    $mCase = array_merge($basePenMatch, $override);
    $st = PenaltyShootout::start($gCase, $mCase);
    f04Check($st['protagonist_eligible'] === false, "D1: '{$caseName}' impedido de cobrar pênalti");
}

$eligibleCases = [
    'titular_full_time' => ['lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 0, 'exit' => 90, 'entered_on_field' => true, 'stats' => array_merge(Schedule::stats(), ['minutes' => 90, 'rating' => 6.8])],
    'reserva_entrou_60_full_time' => ['lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true, 'stats' => array_merge(Schedule::stats(), ['minutes' => 30, 'rating' => 6.5])],
    'legado_titular_sem_state' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 90, 'stats' => array_merge(Schedule::stats(), ['minutes' => 90, 'rating' => 6.7])],
    'legado_reserva_entrou_70' => ['lineup' => 'reserva', 'entry' => 70, 'exit' => 90, 'log' => [['minute' => 70, 'text' => "70' | Você entra em campo no lugar de um companheiro."]], 'stats' => array_merge(Schedule::stats(), ['minutes' => 20, 'rating' => 6.4])],
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

// =========================================================================
// 7. MATRIZ ADVERSARIAL R1-R2 (P0-01, P0-02, P1-01, P1-02, P2 e 3 Modos)
// =========================================================================
echo "\n7. Validando Matriz Adversarial R1-R2 (Correções Codex)...\n";

// 7.1 (P0-01) Reserva em ON_BENCH dentro da janela prevista (entry=60, exit=90, minute=70): ação rejeitada sem autopromoção
$gBenchBlock = Game::legacyCreate('Banco Bloqueado', 'São Paulo', 'SP', 'meia');
$gBenchBlock['stage'] = 'partida';
$idxBB = Game::currentIndex($gBenchBlock);
$gBenchBlock['matches'][$idxBB] = array_merge($gBenchBlock['matches'][$idxBB], [
    'status' => 'em_andamento',
    'minute' => 70,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 60,
    'exit' => 90,
    'evaluated_penalties' => ['70-80'],
]);
$benchChoiceBlocked = false;
try {
    Game::command($gBenchBlock, 'choice', ['option' => 0], false, $rngMidSuccess);
} catch (InvalidArgumentException $e) {
    $benchChoiceBlocked = true;
}
f04Check($benchChoiceBlocked, 'R1-R2 [1]: reserva em ON_BENCH aos 70\' (janela 60-90) tem Game::command(choice) rejeitado');
f04Check($gBenchBlock['matches'][$idxBB]['participation_state'] === MatchParticipation::STATE_ON_BENCH && empty($gBenchBlock['matches'][$idxBB]['participated_on_field']), 'R1-R2 [1]: tentativa de ação em ON_BENCH não promove atleta para ON_FIELD nem grava participated_on_field');
f04Check(MatchParticipation::canActOnField($gBenchBlock['matches'][$idxBB]) === false, 'R1-R2 [1]: canActOnField() retorna false para ON_BENCH mesmo dentro da janela entry/exit');

// Detecção de pênalti durante segmento em ON_BENCH com janela 60-90 nunca abre active_match_penalty para o protagonista
$refSegBenchPen = new ReflectionMethod('Game', 'segment');
$refSegBenchPen->setAccessible(true);
$benchPenaltyTriggeredForProtagonist = false;
for ($bpIter = 0; $bpIter < 120; $bpIter++) {
    $gBP = Game::legacyCreate('Banco Penalti', 'São Paulo', 'SP', 'centroavante');
    $gBP['stage'] = 'partida';
    $gBP['skills']['finalizacao']['value'] = 90;
    $idxBP = Game::currentIndex($gBP);
    $gBP['matches'][$idxBP] = array_merge($gBP['matches'][$idxBP], [
        'status' => 'em_andamento',
        'minute' => 70,
        'lineup' => 'reserva',
        'participation_state' => MatchParticipation::STATE_ON_BENCH,
        'entry' => 60,
        'exit' => 90,
        'evaluated_penalties' => [],
    ]);
    $refSegBenchPen->invokeArgs(null, [&$gBP, 80]);
    if (!empty($gBP['matches'][$idxBP]['active_match_penalty'])) {
        $benchPenaltyTriggeredForProtagonist = true;
        break;
    }
}
f04Check(!$benchPenaltyTriggeredForProtagonist, 'R1-R2 [1]: detecção de pênalti no segmento nunca atribui cobrança ativa (active_match_penalty) a atleta em ON_BENCH');

// Contradição deliberada: TODOS os estados não atuantes dentro da janela 0-90 aos 70' devem ser rejeitados
foreach ([
    MatchParticipation::STATE_ON_BENCH,
    MatchParticipation::STATE_STARTING,
    MatchParticipation::STATE_NOT_SELECTED,
    MatchParticipation::STATE_SUSPENDED,
    MatchParticipation::STATE_SUBSTITUTED_OUT,
    MatchParticipation::STATE_SENT_OFF,
    MatchParticipation::STATE_FULL_TIME,
] as $blockedSt) {
    $gContradict = Game::legacyCreate('Contradicao ' . $blockedSt, 'São Paulo', 'SP', 'meia');
    $gContradict['stage'] = 'partida';
    $idxC = Game::currentIndex($gContradict);
    $gContradict['matches'][$idxC] = array_merge($gContradict['matches'][$idxC], [
        'status' => 'em_andamento',
        'minute' => 70,
        'lineup' => 'titular',
        'participation_state' => $blockedSt,
        'entry' => 0,
        'exit' => 90,
    ]);
    $wasRejected = false;
    try {
        Game::command($gContradict, 'choice', ['option' => 0], false, $rngMidSuccess);
    } catch (InvalidArgumentException $e) {
        $wasRejected = true;
    }
    f04Check($wasRejected && !MatchParticipation::canActOnField($gContradict['matches'][$idxC]), "R1-R2 [1]: estado '{$blockedSt}' contradizendo janela 0-90 aos 70' é rejeitado");
}

// 7.2 (P0-01) Reserva que efetivamente entrou pelo fluxo legítimo (intervalo -> 60' via advance): ação permitida
$gLegitSub = Game::legacyCreate('Reserva Legitimo', 'São Paulo', 'SP', 'meia');
$gLegitSub['stage'] = 'partida';
$gLegitSub['states']['energia'] = 85;
$gLegitSub['states']['risco_lesao'] = 0;
$idxLS = Game::currentIndex($gLegitSub);
$gLegitSub['matches'][$idxLS] = array_merge($gLegitSub['matches'][$idxLS], [
    'status' => 'intervalo',
    'minute' => 45,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 60,
    'exit' => 90,
    'evaluated_penalties' => ['45-60', '60-70'],
]);
$gLegitSub = Game::command($gLegitSub, 'advance');
f04Check(
    $gLegitSub['matches'][$idxLS]['participation_state'] === MatchParticipation::STATE_ON_FIELD
    && !empty($gLegitSub['matches'][$idxLS]['sub_in_recorded'])
    && !empty($gLegitSub['matches'][$idxLS]['entered_on_field'])
    && MatchParticipation::canActOnField($gLegitSub['matches'][$idxLS]),
    'R1-R2 [2]: reserva que entra legitimamente aos 60\' via advance transita para ON_FIELD e habilita canActOnField()'
);
$gLegitSubAfter = Game::command($gLegitSub, 'choice', ['option' => 0], false, $rngMidSuccess);
f04Check(!empty($gLegitSubAfter['matches'][$idxLS]['participated_on_field']), 'R1-R2 [2]: reserva após entrada legítima executa decisão esportiva com sucesso');

// 7.3 (P0-01) Reserva legado sem entrada confirmada (sem participation_state, sem sub_in/entered/participated): nenhuma participação presumida
$gLegacyBench = Game::legacyCreate('Reserva Legado Sem Entrada', 'São Paulo', 'SP', 'meia');
$gLegacyBench['stage'] = 'partida';
$idxLB = Game::currentIndex($gLegacyBench);
$gLegacyBench['matches'][$idxLB] = array_merge($gLegacyBench['matches'][$idxLB], [
    'status' => 'em_andamento',
    'minute' => 70,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
]);
unset($gLegacyBench['matches'][$idxLB]['participation_state']);
$legacyBenchBlocked = false;
try {
    Game::command($gLegacyBench, 'choice', ['option' => 0], false, $rngMidSuccess);
} catch (InvalidArgumentException $e) {
    $legacyBenchBlocked = true;
}
f04Check($legacyBenchBlocked && !MatchParticipation::canActOnField($gLegacyBench['matches'][$idxLB]), 'R1-R2 [3]: reserva legado sem entrada confirmada não pode executar decisão');
$mLegacyBenchEnd = array_merge($gLegacyBench['matches'][$idxLB], ['status' => 'finalizada', 'minute' => 90]);
f04Check(MatchParticipation::playedMinutes($mLegacyBenchEnd) === 0 && !MatchParticipation::finishedOnField($mLegacyBenchEnd), 'R1-R2 [3]: reserva legado sem entrada confirmada não recebe minutos nem elegibilidade em pênaltis');

// 7.4 (P0-02) Fluxo completo de disputa de pênaltis com save ambíguo (entry=0, exit=0, sem escalação/estado/stats)
$tAmb = [
    'id' => 'sp-a3',
    'phase' => 'quartas',
    'ties' => [['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']],
    'matches' => [
        array_merge(Schedule::match(15, 'santos', 'sao-paulo'), ['phase' => 'quartas', 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        array_merge(Schedule::match(16, 'sao-paulo', 'santos'), ['phase' => 'quartas', 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
    ],
];
$gAmbPen = Game::legacyCreate('Save Ambiguo Penaltis', 'São Paulo', 'SP', 'meia');
$gAmbPen['club'] = 'sao-paulo';
$gAmbPen['official'] = true;
$gAmbPen['competition_id'] = 'sp-a3';
$gAmbPen['competitions'] = ['sp-a3' => $tAmb];
$gAmbPen['matches'] = $tAmb['matches'];
$gAmbPen['round'] = 16;
$gAmbPen['stage'] = 'fim_semana';
$gAmbPen = Game::command($gAmbPen, 'shootout_start');
f04Check($gAmbPen['shootout']['protagonist_eligible'] === false, 'R1-R2 [4]: shootout_start nega elegibilidade para save ambíguo sem evidência de atuação');
$gAmbPen['shootout']['current_round'] = 5;
$gAmbPen['shootout']['current_turn_team'] = 'sao-paulo';
f04Check(PenaltyShootout::isProtagonistTurn($gAmbPen) === false, 'R1-R2 [4]: isProtagonistTurn() retorna false na 5ª cobrança para save ambíguo');
// Reset para rodada 1 e validar que a disputa da equipe conclui normalmente sem turno do protagonista
$gAmbPen['shootout']['current_round'] = 1;
$gAmbPen['shootout']['current_turn_team'] = $gAmbPen['shootout']['first_team'];
for ($kStep = 0; $kStep < 60 && $gAmbPen['shootout']['status'] === 'in_progress'; $kStep++) {
    f04Check(!PenaltyShootout::isProtagonistTurn($gAmbPen), 'R1-R2 [4]: nenhum turno do protagonista é concedido durante simulação da disputa');
    $gAmbPen = Game::command($gAmbPen, 'shootout_advance');
    if ($kStep === 0) break; // checagem por loop completo abaixo sem inflar contador de assertions
}
for ($kStep = 0; $kStep < 60 && $gAmbPen['shootout']['status'] === 'in_progress'; $kStep++) {
    if (PenaltyShootout::isProtagonistTurn($gAmbPen)) {
        throw new RuntimeException('FALHA R1-R2 [4]: save ambíguo recebeu turno na disputa de pênaltis');
    }
    $gAmbPen = Game::command($gAmbPen, 'shootout_advance');
}
f04Check($gAmbPen['shootout']['status'] === 'completed' && in_array($gAmbPen['shootout']['winner'], ['sao-paulo', 'santos'], true), 'R1-R2 [4]: disputa com protagonista inelegível conclui normalmente pelas cobranças da equipe');

// 7.5 e 7.6 (P0-02) Titular consistente e reserva utilizado recebem turno legítimo na 5ª cobrança
foreach (['titular_consistente' => ['lineup' => 'titular', 'entry' => 0, 'exit' => 90, 'minutes' => 90], 'reserva_utilizado' => ['lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'minutes' => 30]] as $lblPen => $cfgPen) {
    $gOkPen = $gAmbPen;
    unset($gOkPen['shootout']);
    $gOkPen['stage'] = 'fim_semana';
    $gOkPen['matches'][1]['lineup'] = $cfgPen['lineup'];
    $gOkPen['matches'][1]['participation_state'] = MatchParticipation::STATE_FULL_TIME;
    $gOkPen['matches'][1]['entry'] = $cfgPen['entry'];
    $gOkPen['matches'][1]['exit'] = $cfgPen['exit'];
    $gOkPen['matches'][1]['entered_on_field'] = true;
    $gOkPen['matches'][1]['stats']['minutes'] = $cfgPen['minutes'];
    $gOkPen = Game::command($gOkPen, 'shootout_start');
    $gOkPen['shootout']['current_round'] = 5;
    $gOkPen['shootout']['current_turn_team'] = 'sao-paulo';
    f04Check($gOkPen['shootout']['protagonist_eligible'] === true && PenaltyShootout::isProtagonistTurn($gOkPen) === true, "R1-R2 [5/6]: {$lblPen} com evidência consistente é elegível e recebe turno na 5ª cobrança");
}

// 7.7 (P1-01) Atleta ON_BENCH com janela planejada 60-90 no encerramento real: 0 minutos e 0 bônus
$gBenchEnd = Game::legacyCreate('Banco 60-90 Encerramento', 'São Paulo', 'SP', 'meia');
$gBenchEnd['stage'] = 'partida';
$gBenchEnd['states']['ritmo_jogo'] = 50;
$gBenchEnd['states']['confianca'] = 40;
$idxBE = Game::currentIndex($gBenchEnd);
$gBenchEnd['matches'][$idxBE] = array_merge($gBenchEnd['matches'][$idxBE], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 60,
    'exit' => 90,
    'evaluated_penalties' => ['80-90'],
]);
$refSegment = new ReflectionMethod('Game', 'segment');
$refSegment->setAccessible(true);
$refSegment->invokeArgs(null, [&$gBenchEnd, 90]);
$mBE = $gBenchEnd['matches'][$idxBE];
$rhythmKeyBE = 'f04:rhythm:' . $gBenchEnd['season'] . ':legacy:' . $mBE['round'] . ':' . $mBE['home'] . ':' . $mBE['away'];
f04Check($mBE['participation_state'] === MatchParticipation::STATE_ON_BENCH, 'R1-R2 [7]: encerramento de atleta em ON_BENCH com janela 60-90 preserva estado ON_BENCH');
f04Check($mBE['stats']['minutes'] === 0 && $mBE['stats']['rating'] === 0 && Game::summary($gBenchEnd)['games'] === 0, 'R1-R2 [7]: ON_BENCH com janela planejada 60-90 registra estritamente 0 minutos, nota 0 e 0 jogos');
f04Check($gBenchEnd['states']['ritmo_jogo'] === 50 && $gBenchEnd['states']['confianca'] === 40 && !isset($gBenchEnd['reward_ledger'][$rhythmKeyBE]), 'R1-R2 [7]: ON_BENCH com janela planejada 60-90 não recebe bônus de ritmo nem de confiança');

// 7.8 (P1-01) Intervalos temporais inválidos: nenhum minuto fabricado
$invalidIntervals = [
    'negativo_extrapolado_-20_130' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => -20, 'exit' => 130, 'participated_on_field' => true],
    'invertido_70_40' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 70, 'exit' => 40, 'participated_on_field' => true],
    'exit_acima_limite_0_110' => ['status' => 'finalizada', 'minute' => 110, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 0, 'exit' => 110, 'participated_on_field' => true],
    'exit_maior_que_minute_0_98' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 0, 'exit' => 98, 'participated_on_field' => true],
    'entry_90_exit_95' => ['status' => 'finalizada', 'minute' => 95, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => 90, 'exit' => 95, 'participated_on_field' => true],
    'entry_nulo' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'entry' => null, 'exit' => 90, 'participated_on_field' => true],
];
foreach ($invalidIntervals as $invName => $invMatch) {
    f04Check(MatchParticipation::playedMinutes($invMatch) === 0, "R1-R2 [8]: intervalo inválido '{$invName}' retorna estritamente 0 minutos");
}

// 7.9 (P1-01) Estados terminais isolados sem evidência adicional: nenhuma participação presumida
$isolatedTerminals = [
    'sent_off_60_60_isolado' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SENT_OFF, 'entry' => 60, 'exit' => 60],
    'substituted_out_70_70_isolado' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 70, 'exit' => 70],
    'substituted_out_reserva_60_90_sem_evidencia' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 60, 'exit' => 90],
    'starting_0_90_sem_entrada' => ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_STARTING, 'entry' => 0, 'exit' => 90],
];
foreach ($isolatedTerminals as $isoName => $isoMatch) {
    f04Check(MatchParticipation::playedMinutes($isoMatch) === 0, "R1-R2 [9]: estado isolado '{$isoName}' sem evidência adicional retorna 0 minutos");
}

// 7.10 (P1-01) Atuação comprovada com entrada e saída no mesmo minuto retorna 1 minuto estatístico
$mProvenImmediate = [
    'status' => 'finalizada',
    'minute' => 90,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_SENT_OFF,
    'entry' => 60,
    'exit' => 60,
    'exit_reason' => 'sent_off',
    'red_card_minute' => 60,
    'sub_in_recorded' => true,
    'entered_on_field' => true,
    'participated_on_field' => true,
];
f04Check(MatchParticipation::playedMinutes($mProvenImmediate) === 1, 'R1-R2 [10]: atuação comprovada com entry=60 e exit=60 contabiliza exatamente 1 minuto estatístico');

// 7.11 (P1-02) recordExit() repetido preserva minuto, motivo e estado originais sem áudio duplicado
$gIdemExit = Game::legacyCreate('Idempotencia Exit', 'São Paulo', 'SP', 'meia');
$mIdemRed = Schedule::match(1, 'São Paulo', 'Santos');
$mIdemRed['status'] = 'em_andamento';
$mIdemRed['participation_state'] = MatchParticipation::STATE_ON_FIELD;
MatchParticipation::recordExit($mIdemRed, $gIdemExit, 40, 'sent_off');
MatchParticipation::recordExit($mIdemRed, $gIdemExit, 80, 'sent_off');
MatchParticipation::recordExit($mIdemRed, $gIdemExit, 85, 'injury');
f04Check(
    $mIdemRed['exit'] === 40
    && $mIdemRed['red_card_minute'] === 40
    && $mIdemRed['exit_reason'] === 'sent_off'
    && $mIdemRed['participation_state'] === MatchParticipation::STATE_SENT_OFF,
    'R1-R2 [11]: recordExit() repetido preserva minuto (40\'), motivo (sent_off) e estado originais da expulsão'
);
$mIdemSub = Schedule::match(2, 'São Paulo', 'Santos');
$mIdemSub['status'] = 'em_andamento';
$mIdemSub['participation_state'] = MatchParticipation::STATE_ON_FIELD;
MatchParticipation::recordExit($mIdemSub, $gIdemExit, 62, 'injury');
MatchParticipation::recordExit($mIdemSub, $gIdemExit, 78, 'fatigue');
MatchParticipation::recordExit($mIdemSub, $gIdemExit, 82, 'sent_off');
$subOutCountIdem = count(array_filter($mIdemSub['sound_events'] ?? [], fn($e) => ($e['type'] ?? '') === 'PLAYER_SUBSTITUTION_OUT'));
f04Check(
    $mIdemSub['exit'] === 62
    && $mIdemSub['exit_reason'] === 'injury'
    && $mIdemSub['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $subOutCountIdem === 1,
    'R1-R2 [11]: recordExit() repetido após substituição preserva minuto (62\'), motivo (injury) e emite 1 evento sonoro'
);

// 7.12 (P1-02) Encerramento repetido via Game::segment(90) é completamente inócuo para logs, stats e recompensas
$gIdemClose = Game::create('Idempotencia Close', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gIdemClose = Game::command($gIdemClose, 'handicap');
$gIdemClose['stage'] = 'partida';
$gIdemClose['states']['ritmo_jogo'] = 50;
$gIdemClose['states']['confianca'] = 45;
$idxIC = Game::currentIndex($gIdemClose);
$gIdemClose['matches'][$idxIC] = array_merge($gIdemClose['matches'][$idxIC], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 0,
    'exit' => 90,
    'entered_on_field' => true,
    'participated_on_field' => true,
    'evaluated_penalties' => ['80-90'],
]);
$refSegment->invokeArgs(null, [&$gIdemClose, 90]);
$snapshotMatch = $gIdemClose['matches'][$idxIC];
$snapshotStates = $gIdemClose['states'];
$snapshotLedger = $gIdemClose['reward_ledger'] ?? [];
$snapshotSkills = $gIdemClose['skills'];
$refSegment->invokeArgs(null, [&$gIdemClose, 90]);
$refSegment->invokeArgs(null, [&$gIdemClose, 93]);
f04Check(
    $gIdemClose['matches'][$idxIC] === $snapshotMatch
    && $gIdemClose['states'] === $snapshotStates
    && ($gIdemClose['reward_ledger'] ?? []) === $snapshotLedger
    && $gIdemClose['skills'] === $snapshotSkills
    && $gIdemClose['stage'] === 'fim_semana',
    'R1-R2 [12]: chamadas repetidas de Game::segment(90/93) após partida finalizada são totalmente inócuas (logs, stats, cartões, ledger e recompensas intactos)'
);

// 7.13 (P2) Marcadores legados de expulsão (direct_red, second_yellow, cards_red, cards=2 sem red_cards) impedem nova falta
$gLegacyDisc = Game::legacyCreate('Disciplina Legada', 'São Paulo', 'SP', 'volante');
foreach ([
    'direct_red_sem_red_cards' => ['stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'fouls' => 1])],
    'second_yellow_sem_red_cards' => ['stats' => array_merge(Schedule::stats(), ['second_yellow' => 1, 'fouls' => 2])],
    'cards_red_legado' => ['stats' => array_merge(Schedule::stats(), ['cards_red' => 1, 'fouls' => 1])],
    'cards_2_sem_red_cards' => ['stats' => array_merge(Schedule::stats(), ['cards' => 2, 'fouls' => 2])],
    'red_card_minute_legado' => ['red_card_minute' => 55, 'stats' => array_merge(Schedule::stats(), ['fouls' => 1])],
] as $discLbl => $discOv) {
    $mLD = array_merge(Schedule::match(3, 'São Paulo', 'Santos'), ['status' => 'em_andamento', 'minute' => 60], $discOv);
    $foulsBeforeLD = $mLD['stats']['fouls'];
    $resLD = MatchDiscipline::recordFoul($gLegacyDisc, $mLD, 'minor');
    f04Check(
        $mLD['stats']['fouls'] === $foulsBeforeLD && $resLD['card'] === null && $resLD['text'] === '',
        "R1-R2 [13]: marcador legado '{$discLbl}' impede acúmulo de nova falta ou cartão"
    );
}

// 7.14 (P2) Reconhecimento narrativo legado de mensagens de expulsão e precedência de exit_reason
$gNarr = Game::legacyCreate('Narrativa Legada', 'São Paulo', 'SP', 'meia');
$gNarr['stage'] = 'fim_semana';
$idxN = Game::currentIndex($gNarr);
$gNarr['matches'][$idxN]['status'] = 'finalizada';
$gNarr['matches'][$idxN]['minute'] = 90;

$gNarrDirect = $gNarr;
$gNarrDirect['matches'][$idxN]['log'] = [['minute' => 55, 'text' => "55' | Cartão vermelho direto! Você foi expulso da partida."]];
f04Check((NarrativeContext::values($gNarrDirect)['exit_reason'] ?? null) === 'red', 'R1-R2 [14]: fallback narrativo reconhece texto legado de vermelho direto ("Você foi expulso da partida")');

$gNarrSecond = $gNarr;
$gNarrSecond['matches'][$idxN]['log'] = [['minute' => 70, 'text' => "70' | Segundo cartão amarelo! Você está expulso da partida."]];
f04Check((NarrativeContext::values($gNarrSecond)['exit_reason'] ?? null) === 'red', 'R1-R2 [14]: fallback narrativo reconhece texto legado de 2º amarelo ("Você está expulso da partida")');

$gNarrOpp = $gNarr;
$gNarrOpp['matches'][$idxN]['log'] = [['minute' => 65, 'text' => "65' | Zagueiro adversário expulso após falta dura."]];
f04Check(!isset(NarrativeContext::values($gNarrOpp)['exit_reason']), 'R1-R2 [14]: texto de expulsão de adversário não gera falso exit_reason para o protagonista');

$gNarrPrec = $gNarrOpp;
$gNarrPrec['matches'][$idxN]['exit_reason'] = 'injury';
f04Check((NarrativeContext::values($gNarrPrec)['exit_reason'] ?? null) === 'injury', 'R1-R2 [14]: exit_reason estruturado prevalece quando não há evento formal contraditório no log');

// 7.15 (P0-01 + Modos) Simulação nos três modos (Normal, Rápido, Expresso): nenhuma decisão fora de campo
// 7.15a Modo Normal: atleta suspenso percorre partida inteira sem nenhuma decisão em campo, 0 min e sem bônus de ritmo
$gModeNorm = Game::create('Normal Suspenso', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gModeNorm = Game::command($gModeNorm, 'handicap');
$gModeNorm['play_mode'] = 'normal';
$cidMN = $gModeNorm['competition_id'];
$gModeNorm['disciplinary'][$cidMN] = ['yellow_accumulated' => 0, 'pending_suspension' => 1, 'suspension_reason' => 'Teste suspensão', 'suspensions_served' => 0, 'regulatory_status' => 'suspended'];
$gModeNorm = Game::command($gModeNorm, 'choice', ['option' => 0]); // treino -> pre_jogo
$gModeNorm = Game::command($gModeNorm, 'choice', ['option' => 0]); // pre_jogo -> partida (SUSPENDED)
$gModeNorm['states']['ritmo_jogo'] = 50; // fixa baseline no início da partida (após efeitos de treino/pré-jogo)
$idxMN = Game::currentIndex($gModeNorm);
$mInitMN = $gModeNorm['matches'][$idxMN];
$rhythmKeyMN = 'f04:rhythm:' . $gModeNorm['season'] . ':' . ($gModeNorm['competition_id'] ?? 'legacy') . ':' . $mInitMN['round'] . ':' . $mInitMN['home'] . ':' . $mInitMN['away'];
$normDecisions = 0;
for ($stepMN = 0; $stepMN < 20 && $gModeNorm['stage'] === 'partida'; $stepMN++) {
    $mCurMN = $gModeNorm['matches'][$idxMN];
    if (MatchParticipation::canActOnField($mCurMN)) {
        $normDecisions++;
        $gModeNorm = Game::command($gModeNorm, 'choice', ['option' => 0]);
    } else {
        $gModeNorm = Game::command($gModeNorm, 'advance');
    }
}
f04Check(
    $gModeNorm['stage'] === 'fim_semana'
    && $normDecisions === 0
    && $gModeNorm['matches'][$idxMN]['stats']['minutes'] === 0
    && $gModeNorm['states']['ritmo_jogo'] === 50
    && !isset($gModeNorm['reward_ledger'][$rhythmKeyMN]),
    'R1-R2 [15]: modo Normal com atleta suspenso executa 0 decisões em campo, 0 minutos e 0 bônus de ritmo'
);

// 7.15b Modo Rápido: atleta suspenso em PlayMode::simulate() executa 0 decisões em stage=partida, sem loop e 0 minutos
$gModeRap = Game::create('Rapido Suspenso', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gModeRap = Game::command($gModeRap, 'handicap');
$gModeRap['play_mode'] = 'rapido';
$cidMR = $gModeRap['competition_id'];
$idxMR = Game::currentIndex($gModeRap);
$mInitMR = $gModeRap['matches'][$idxMR];
$rhythmKeyMR = 'f04:rhythm:' . $gModeRap['season'] . ':' . ($cidMR ?? 'legacy') . ':' . $mInitMR['round'] . ':' . $mInitMR['home'] . ':' . $mInitMR['away'];
$gModeRap['disciplinary'][$cidMR] = ['yellow_accumulated' => 0, 'pending_suspension' => 1, 'suspension_reason' => 'Teste suspensão', 'suspensions_served' => 0, 'regulatory_status' => 'suspended'];
$gModeRap = PlayMode::simulate($gModeRap, 'equilibrio');
$rapPartidaAttempts = array_filter($gModeRap['pace_report']['attempts'] ?? [], fn($a) => ($a['stage'] ?? '') === 'partida');
$rapMatch0 = $gModeRap['pace_report']['matches'][0] ?? null;
f04Check(
    count($rapPartidaAttempts) === 0
    && $rapMatch0 !== null
    && (int)$rapMatch0['stats']['minutes'] === 0
    && !isset($gModeRap['reward_ledger'][$rhythmKeyMR]),
    'R1-R2 [15]: modo Rápido com atleta suspenso executa 0 decisões em partida, 0 minutos e 0 bônus de ritmo'
);

// 7.15c Modo Expresso: temporada simulada completa sem decisões fora de campo e com minutagem coerente
$gModeExp = Game::create('Expresso Coerencia', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gModeExp = Game::command($gModeExp, 'handicap');
$gModeExp['play_mode'] = 'expresso';
$gModeExp = PlayMode::simulate($gModeExp, 'equilibrio');
$expMatches = $gModeExp['pace_report']['matches'] ?? [];
$expAttemptsByRound = [];
foreach ($gModeExp['pace_report']['attempts'] ?? [] as $att) {
    if (($att['stage'] ?? '') === 'partida') {
        $kAtt = ($att['competition'] ?? '') . ':' . ($att['round'] ?? 0);
        $expAttemptsByRound[$kAtt] = ($expAttemptsByRound[$kAtt] ?? 0) + 1;
    }
}
$zeroMinWithDecision = 0;
foreach (Competition::playerMatches($gModeExp) as $pm) {
    if (($pm['status'] ?? '') !== 'finalizada') continue;
    if (!in_array($gModeExp['club'], [$pm['home'], $pm['away']], true)) continue;
    $kPm = ($pm['competition'] ?? ($gModeExp['competition_id'] ?? 'legacy')) . ':' . ($pm['round'] ?? 0);
    if ((int)($pm['stats']['minutes'] ?? 0) === 0 && !empty($expAttemptsByRound[$kPm])) {
        $zeroMinWithDecision++;
    }
}
f04Check(count($expMatches) > 0 && $zeroMinWithDecision === 0, 'R1-R2 [15]: modo Expresso completa simulação sem nenhuma decisão em partida de 0 minutos');

// =========================================================================
// 8. MATRIZ ADVERSARIAL R1-R3 (P0-01, P0-02, P1 Unificação, P2 Minuto Zero)
// =========================================================================
echo "\n8. Validando Matriz Adversarial R1-R3 (Segunda Reauditoria Codex)...\n";

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="' . e($path) . '">';
    }
}
$renderCareerMatchView = static function (array $gState): string {
    $path = '/carreira';
    $error = null;
    $_SESSION = ['account' => 1, 'csrf' => 'f04-r3-token'];
    $g = $gState;
    ob_start();
    include dirname(__DIR__) . '/app/view.php';
    return (string)ob_get_clean();
};

// 8.1-8.3 (P0-01) Escalação desconhecida (lineup="", sem participation_state, entry=0, exit=90) aos 80' e encerramento completo
$gUnknown80 = Game::create('Ambiguo 80 R3', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gUnknown80 = Game::command($gUnknown80, 'handicap');
$gUnknown80['stage'] = 'partida';
$gUnknown80['states']['ritmo_jogo'] = 50;
$gUnknown80['states']['confianca'] = 45;
$xpBeforeUnk = array_sum(array_column($gUnknown80['skills'], 'xp'));
$idxU80 = Game::currentIndex($gUnknown80);
$gUnknown80['matches'][$idxU80] = array_merge($gUnknown80['matches'][$idxU80], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => '',
    'entry' => 0,
    'exit' => 90,
    'evaluated_penalties' => ['80-90'],
]);
unset($gUnknown80['matches'][$idxU80]['participation_state']);
$unkChoiceBlocked = false;
try {
    Game::command($gUnknown80, 'choice', ['option' => 0], false, $rngMidSuccess);
} catch (InvalidArgumentException $e) {
    $unkChoiceBlocked = true;
}
f04Check($unkChoiceBlocked, 'R1-R3 [1]: escalação desconhecida (lineup="", entry=0, exit=90 aos 80\') rejeita Game::command(choice)');
f04Check(
    !MatchParticipation::canActOnField($gUnknown80['matches'][$idxU80])
    && NarrativeContext::values($gUnknown80)['on_field'] === false
    && Scenes::eligible($gUnknown80) === []
    && Game::choices($gUnknown80) === []
    && Game::situation($gUnknown80) === null,
    'R1-R3 [1]: escalação desconhecida bloqueada uniformemente em motor, NarrativeContext, Scenes e Game::choices'
);
$htmlUnk80 = $renderCareerMatchView($gUnknown80);
f04Check(
    str_contains($htmlUnk80, 'class="match-advance-card panel"')
    && !str_contains($htmlUnk80, 'action="/partida/decidir"')
    && !str_contains($htmlUnk80, 'name="option"'),
    'R1-R3 [1]: app/view.php não oferece escolha para save ambíguo e exibe card de avanço'
);

// Encerramento passivo completo do save ambíguo
$gUnknownEnd = Game::command($gUnknown80, 'advance');
$mU80End = $gUnknownEnd['matches'][$idxU80];
$rhythmKeyU80 = 'f04:rhythm:' . $gUnknownEnd['season'] . ':' . ($gUnknownEnd['competition_id'] ?? 'legacy') . ':' . $mU80End['round'] . ':' . $mU80End['home'] . ':' . $mU80End['away'];
$xpAfterUnk = array_sum(array_column($gUnknownEnd['skills'], 'xp'));
f04Check(
    $gUnknownEnd['stage'] === 'fim_semana'
    && $mU80End['status'] === 'finalizada'
    && !isset($mU80End['participation_state'])
    && empty($mU80End['entered_on_field'])
    && empty($mU80End['participated_on_field']),
    'R1-R3 [2]: encerramento de save ambíguo conclui passivamente sem fabricar FULL_TIME nem entered_on_field'
);
f04Check(
    $mU80End['stats']['minutes'] === 0
    && $mU80End['stats']['rating'] === 0
    && Game::summary($gUnknownEnd)['games'] === 0
    && $gUnknownEnd['states']['ritmo_jogo'] === 50
    && $gUnknownEnd['states']['confianca'] === 45
    && $xpAfterUnk === $xpBeforeUnk
    && !isset($gUnknownEnd['reward_ledger'][$rhythmKeyU80])
    && !MatchParticipation::finishedOnField($mU80End),
    'R1-R3 [3]: save ambíguo encerrado mantém 0 minutos, 0 jogos, 0 XP, confiança/ritmo inalterados e sem elegibilidade para pênaltis'
);

// 8.4-8.5 (P0-02) Reserva com apenas stats.fouls=1 ou estatísticas sem prova de permanência final
$mFoulsOnly = array_merge($basePenMatch, [
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'stats' => array_merge(Schedule::stats(), ['minutes' => 0, 'fouls' => 1]),
]);
$gPenFouls = $gPenBase;
$stPenFouls = PenaltyShootout::start($gPenFouls, $mFoulsOnly);
f04Check(!MatchParticipation::finishedOnField($mFoulsOnly) && $stPenFouls['protagonist_eligible'] === false, 'R1-R3 [4]: reserva com apenas stats.fouls=1 e 0 minutos é inelegível para pênaltis');

$mStatsNoFinal = array_merge($basePenMatch, [
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'stats' => array_merge(Schedule::stats(), ['minutes' => 0, 'goals' => 1, 'assists' => 1, 'passes' => 8, 'shots' => 2, 'tackles' => 2, 'fouls' => 1]),
]);
$gPenStats = $gPenBase;
$stPenStats = PenaltyShootout::start($gPenStats, $mStatsNoFinal);
f04Check(!MatchParticipation::finishedOnField($mStatsNoFinal) && $stPenStats['protagonist_eligible'] === false, 'R1-R3 [5]: estatísticas históricas isoladas (gol/assist/passes/desarmes/faltas) sem prova de entrada e permanência não habilitam pênaltis');

// 8.6 (P0-02) Titular legítimo de ponta a ponta (evidências geradas pelo motor real)
$gE2EStarter = Game::legacyCreate('Titular E2E R3', 'São Paulo', 'SP', 'meia');
$gE2EStarter['stage'] = 'partida';
$gE2EStarter['states']['energia'] = 90;
$gE2EStarter['states']['risco_lesao'] = 0;
$idxES = Game::currentIndex($gE2EStarter);
$gE2EStarter['matches'][$idxES] = array_merge($gE2EStarter['matches'][$idxES], [
    'status' => 'pre_jogo',
    'minute' => 0,
    'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_STARTING,
    'entry' => 0,
    'exit' => 90,
    'evaluated_penalties' => ['0-15', '15-35', '35-45', '45-60', '60-70', '70-80', '80-90'],
]);
for ($stepES = 0; $stepES < 25 && $gE2EStarter['stage'] === 'partida'; $stepES++) {
    $mCurES = $gE2EStarter['matches'][$idxES];
    $gE2EStarter = MatchParticipation::canActOnField($mCurES)
        ? Game::command($gE2EStarter, 'choice', ['option' => 0], false, $rngMidSuccess)
        : Game::command($gE2EStarter, 'advance');
}
$mESEnd = $gE2EStarter['matches'][$idxES];
$gPenES = $gE2EStarter;
$stPenES = PenaltyShootout::start($gPenES, $mESEnd);
f04Check(
    $mESEnd['status'] === 'finalizada'
    && $mESEnd['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && !empty($mESEnd['entered_on_field'])
    && !empty($mESEnd['participated_on_field'])
    && $mESEnd['stats']['minutes'] === 90
    && $mESEnd['stats']['rating'] > 0
    && MatchParticipation::finishedOnField($mESEnd)
    && $stPenES['protagonist_eligible'] === true,
    'R1-R3 [6]: titular legítimo ponta a ponta gera evidências reais (FULL_TIME, entered_on_field, participated_on_field, 90\', rating>0) e elegibilidade para pênaltis'
);

// 8.7 (P0-02) Reserva legítimo ponta a ponta (intervalo -> 60' -> atua -> 90')
$gE2ESub = Game::legacyCreate('Reserva E2E R3', 'São Paulo', 'SP', 'meia');
$gE2ESub['stage'] = 'partida';
$gE2ESub['states']['energia'] = 90;
$gE2ESub['states']['risco_lesao'] = 0;
$idxER = Game::currentIndex($gE2ESub);
$gE2ESub['matches'][$idxER] = array_merge($gE2ESub['matches'][$idxER], [
    'status' => 'intervalo',
    'minute' => 45,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 60,
    'exit' => 90,
    'evaluated_penalties' => ['45-60', '60-70', '70-80', '80-90'],
]);
for ($stepER = 0; $stepER < 20 && $gE2ESub['stage'] === 'partida'; $stepER++) {
    $mCurER = $gE2ESub['matches'][$idxER];
    $gE2ESub = MatchParticipation::canActOnField($mCurER)
        ? Game::command($gE2ESub, 'choice', ['option' => 0], false, $rngMidSuccess)
        : Game::command($gE2ESub, 'advance');
}
$mEREnd = $gE2ESub['matches'][$idxER];
$gPenER = $gE2ESub;
$stPenER = PenaltyShootout::start($gPenER, $mEREnd);
f04Check(
    $mEREnd['status'] === 'finalizada'
    && $mEREnd['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && !empty($mEREnd['sub_in_recorded'])
    && !empty($mEREnd['entered_on_field'])
    && !empty($mEREnd['participated_on_field'])
    && $mEREnd['stats']['minutes'] === 30
    && MatchParticipation::finishedOnField($mEREnd)
    && $stPenER['protagonist_eligible'] === true,
    'R1-R3 [7]: reserva legítimo ponta a ponta entra aos 60\', gera evidências completas (sub_in, entered, participated, FULL_TIME, 30\') e elegibilidade para pênaltis'
);

// 8.8 (P1 Cenário A) ON_BENCH aos 70' com janela 60-90: motor, narrativa e interface 100% unificados
$gBenchUnif = Game::legacyCreate('Banco Unificado R3', 'São Paulo', 'SP', 'meia');
$gBenchUnif['stage'] = 'partida';
$idxBU = Game::currentIndex($gBenchUnif);
$gBenchUnif['matches'][$idxBU] = array_merge($gBenchUnif['matches'][$idxBU], [
    'status' => 'em_andamento',
    'minute' => 70,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 60,
    'exit' => 90,
]);
$partBU = Presentation::playerParticipation($gBenchUnif, $gBenchUnif['matches'][$idxBU]);
$htmlBU = $renderCareerMatchView($gBenchUnif);
f04Check(
    !MatchParticipation::canActOnField($gBenchUnif['matches'][$idxBU])
    && NarrativeContext::values($gBenchUnif)['on_field'] === false
    && Scenes::eligible($gBenchUnif) === []
    && Game::choices($gBenchUnif) === []
    && $partBU['type'] === 'bench'
    && str_contains($htmlBU, 'class="match-advance-card panel"')
    && !str_contains($htmlBU, 'action="/partida/decidir"'),
    'R1-R3 [8]: ON_BENCH aos 70\' (janela 60-90) unificado em motor, NarrativeContext, Scenes, Presentation e app/view.php'
);

// 8.9 (P1 Cenário B) Atleta lesionado com marcadores legados contraditórios (exit_reason=injury, sub_in_recorded=true, exit=90)
$gInjLegacy = Game::legacyCreate('Lesao Legada R3', 'São Paulo', 'SP', 'meia');
$gInjLegacy['stage'] = 'partida';
$idxIL3 = Game::currentIndex($gInjLegacy);
$gInjLegacy['matches'][$idxIL3] = array_merge($gInjLegacy['matches'][$idxIL3], [
    'status' => 'em_andamento',
    'minute' => 70,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'sub_in_recorded' => true,
    'exit_reason' => 'injury',
]);
unset($gInjLegacy['matches'][$idxIL3]['participation_state']);
$partIL3 = Presentation::playerParticipation($gInjLegacy, $gInjLegacy['matches'][$idxIL3]);
$htmlIL3 = $renderCareerMatchView($gInjLegacy);
$injChoiceBlocked = false;
try {
    Game::command($gInjLegacy, 'choice', ['option' => 0], false, $rngMidSuccess);
} catch (InvalidArgumentException $e) {
    $injChoiceBlocked = true;
}
f04Check(
    $injChoiceBlocked
    && !MatchParticipation::canActOnField($gInjLegacy['matches'][$idxIL3])
    && NarrativeContext::values($gInjLegacy)['on_field'] === false
    && Scenes::eligible($gInjLegacy) === []
    && Game::choices($gInjLegacy) === []
    && $partIL3['type'] === 'substituted'
    && $partIL3['label'] === 'Substituído'
    && str_contains($htmlIL3, 'class="match-advance-card panel"')
    && !str_contains($htmlIL3, 'action="/partida/decidir"'),
    'R1-R3 [9]: save legado lesionado (exit_reason=injury, sub_in_recorded=true, exit=90) bloqueado em motor, narrativa, Presentation e view.php'
);

// 8.10 (P1) Atleta substituído que ainda possuía active_situation no mesmo minuto não recebe cena nem opções
$gSubScene = Game::legacyCreate('Substituido Cena R3', 'São Paulo', 'SP', 'meia');
$gSubScene['stage'] = 'partida';
$idxSS = Game::currentIndex($gSubScene);
$gSubScene['matches'][$idxSS] = array_merge($gSubScene['matches'][$idxSS], [
    'status' => 'em_andamento',
    'minute' => 70,
    'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
    'entry' => 0,
    'exit' => 70,
    'exit_reason' => 'tactical',
]);
$gSubScene['active_situation'] = ['id' => 2016, 'key' => Scenes::key($gSubScene)];
f04Check(
    Scenes::current($gSubScene) === null
    && Game::situation($gSubScene) === null
    && Game::choices($gSubScene) === [],
    'R1-R3 [10]: atleta substituído com active_situation prévia no mesmo minuto não recebe situação nem opções jogáveis'
);
Scenes::prepare($gSubScene);
f04Check(!isset($gSubScene['active_situation']), 'R1-R3 [10]: Scenes::prepare() remove active_situation residual quando atleta está fora de campo');

// 8.11-8.12 (P2) Expulsão com red_card_minute=0, ausente ou inconsistente preservada em recordExit() repetido
$gP2Zero = Game::legacyCreate('Expulsao Minuto Zero R3', 'São Paulo', 'SP', 'meia');
$mZero = Schedule::match(1, 'São Paulo', 'Santos');
$mZero['status'] = 'em_andamento';
$mZero['participation_state'] = MatchParticipation::STATE_SENT_OFF;
$mZero['red_card_minute'] = 0;
$mZero['exit'] = 60;
MatchParticipation::recordExit($mZero, $gP2Zero, 80, 'sent_off');
MatchParticipation::recordExit($mZero, $gP2Zero, 85, 'injury');
f04Check(
    array_key_exists('red_card_minute', $mZero)
    && $mZero['red_card_minute'] === 0
    && $mZero['exit'] === 60
    && $mZero['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $mZero['exit_reason'] === 'sent_off',
    'R1-R3 [11/12]: recordExit() repetido preserva red_card_minute=0 explícito sem inferir 60 a partir de exit'
);

$mAbsentRed = Schedule::match(2, 'São Paulo', 'Santos');
$mAbsentRed['status'] = 'em_andamento';
$mAbsentRed['participation_state'] = MatchParticipation::STATE_SENT_OFF;
$mAbsentRed['exit'] = 60;
MatchParticipation::recordExit($mAbsentRed, $gP2Zero, 80, 'sent_off');
f04Check(
    !array_key_exists('red_card_minute', $mAbsentRed)
    && $mAbsentRed['exit'] === 60
    && $mAbsentRed['participation_state'] === MatchParticipation::STATE_SENT_OFF,
    'R1-R3 [12]: recordExit() repetido com red_card_minute ausente não infere minuto a partir de exit nem sobrescreve exit'
);

// 8.13 (P0-01) Avanço passivo de save ambíguo desde 15' até fim_semana sem travamento
$gPassive = Game::legacyCreate('Avanco Passivo R3', 'São Paulo', 'SP', 'meia');
$gPassive['stage'] = 'partida';
$gPassive['states']['ritmo_jogo'] = 50;
$idxPas = Game::currentIndex($gPassive);
$gPassive['matches'][$idxPas] = array_merge($gPassive['matches'][$idxPas], [
    'status' => 'em_andamento',
    'minute' => 15,
    'lineup' => '',
    'entry' => 0,
    'exit' => 90,
    'evaluated_penalties' => ['15-35', '35-45', '45-60', '60-70', '70-80', '80-90'],
]);
unset($gPassive['matches'][$idxPas]['participation_state']);
$pasSteps = 0;
while ($gPassive['stage'] === 'partida' && $pasSteps < 20) {
    $gPassive = Game::command($gPassive, 'advance');
    $pasSteps++;
}
$mPasEnd = $gPassive['matches'][$idxPas];
f04Check(
    $gPassive['stage'] === 'fim_semana'
    && $pasSteps < 20
    && $mPasEnd['status'] === 'finalizada'
    && $mPasEnd['stats']['minutes'] === 0
    && $gPassive['states']['ritmo_jogo'] === 50
    && !MatchParticipation::finishedOnField($mPasEnd),
    'R1-R3 [13]: save ambíguo avança passivamente de 15\' até fim_semana sem travamento, com 0 minutos, 0 bônus e sem elegibilidade para pênaltis'
);

// 8.15 (P1-01 + F03) Distinção entre estado visual F03 e elegibilidade esportiva aos 93' (exit=90)
$gF03Stop = Game::legacyCreate('F03 Acrescimos 93 R4', 'São Paulo', 'SP', 'centroavante');
$gF03Stop['stage'] = 'partida';
$gF03Stop['states']['ritmo_jogo'] = 50;
$idxF93 = Game::currentIndex($gF03Stop);
$gF03Stop['matches'][$idxF93] = array_merge($gF03Stop['matches'][$idxF93], [
    'status' => 'em_andamento',
    'minute' => 93,
    'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 0,
    'exit' => 90,
    'entered_on_field' => true,
]);
$partF93 = Presentation::playerParticipation($gF03Stop, $gF03Stop['matches'][$idxF93]);
$htmlF93 = $renderCareerMatchView($gF03Stop);
$f93ChoiceBlocked = false;
try {
    Game::command($gF03Stop, 'choice', ['option' => 0], false, static fn(int $min, int $max): int => $min);
} catch (InvalidArgumentException $e) {
    $f93ChoiceBlocked = true;
}
f04Check(
    $f93ChoiceBlocked
    && MatchParticipation::canActOnField($gF03Stop['matches'][$idxF93]) === false
    && NarrativeContext::values($gF03Stop)['on_field'] === false
    && Scenes::eligible($gF03Stop) === []
    && Game::situation($gF03Stop) === null
    && Game::choices($gF03Stop) === [],
    'R1-R4 [P1-01]: aos 93\' com janela encerrada aos 90\', decisão esportiva é rejeitada em motor, NarrativeContext, Scenes e Game::choices'
);
f04Check(
    $partF93['type'] === 'on_field'
    && $partF93['label'] === 'Em campo'
    && $partF93['class'] === 'status-onfield'
    && str_contains($htmlF93, '93′')
    && str_contains($htmlF93, 'Em campo')
    && str_contains($htmlF93, 'class="match-advance-card panel"')
    && !str_contains($htmlF93, 'name="option"')
    && !str_contains($htmlF93, 'action="/partida/decidir"'),
    'R1-R4 [P1-01]: apresentação visual F03 (93′ e "Em campo") preservada, mas app/view.php oferece apenas avanço passivo sem escolhas esportivas'
);
$gF03StopEnd = Game::command($gF03Stop, 'advance');
$mF93End = $gF03StopEnd['matches'][$idxF93];
$snapF93Match = $mF93End;
$snapF93States = $gF03StopEnd['states'];
$refSegment->invokeArgs(null, [&$gF03StopEnd, 93]);
f04Check(
    $gF03StopEnd['stage'] === 'fim_semana'
    && $mF93End['status'] === 'finalizada'
    && $mF93End['minute'] === 93
    && $mF93End['stats']['goals'] === 0
    && $mF93End['stats']['shots'] === 0
    && $gF03StopEnd['matches'][$idxF93] === $snapF93Match
    && $gF03StopEnd['states'] === $snapF93States,
    'R1-R4 [P1-01]: avanço passivo aos 93\' encerra sem retroceder relógio para 90\', sem gol indevido e com encerramento idempotente'
);

// =========================================================================
// 9. MATRIZ ADVERSARIAL R1-R4 (P1-02 Reserva Legado e P1-03 Saída Contraditória)
// =========================================================================
echo "\n9. Validando Matriz Adversarial R1-R4 (Reserva Legado e Saída com Horário Contraditório)...\n";

$rngAlwaysSuccess = static fn(int $min, int $max): int => $min;

// 9.1 (P1-02 [1]) Reserva legado com entrada comprovada (sub_in_recorded + log 60') e sem saída
$gLegSubOk = Game::create('Reserva Legado OK R4', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gLegSubOk = Game::command($gLegSubOk, 'handicap');
$gLegSubOk['stage'] = 'partida';
$gLegSubOk['states']['ritmo_jogo'] = 50;
$gLegSubOk['states']['energia'] = 90;
$gLegSubOk['states']['risco_lesao'] = 0;
$idxLSO = Game::currentIndex($gLegSubOk);
$gLegSubOk['matches'][$idxLSO] = array_merge($gLegSubOk['matches'][$idxLSO], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'sub_in_recorded' => true,
    'log' => [['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."]],
    'evaluated_penalties' => ['80-90'],
]);
unset($gLegSubOk['matches'][$idxLSO]['participation_state']);
$gLegSubOk = Game::command($gLegSubOk, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gLegSubOk['stage'] === 'partida') {
    $gLegSubOk = MatchParticipation::canActOnField($gLegSubOk['matches'][$idxLSO])
        ? Game::command($gLegSubOk, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gLegSubOk, 'advance');
}
$mLSO = $gLegSubOk['matches'][$idxLSO];
$rhythmKeyLSO = 'f04:rhythm:' . $gLegSubOk['season'] . ':' . ($gLegSubOk['competition_id'] ?? 'legacy') . ':' . $mLSO['round'] . ':' . $mLSO['home'] . ':' . $mLSO['away'];
$gPenLSO = $gLegSubOk;
$stPenLSO = PenaltyShootout::start($gPenLSO, $mLSO);
f04Check(
    $mLSO['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $mLSO['stats']['minutes'] === 30
    && $mLSO['stats']['rating'] > 0
    && Game::summary($gLegSubOk)['games'] === 1
    && ($gLegSubOk['reward_ledger'][$rhythmKeyLSO]['delta'] ?? 0) === 3
    && ($gLegSubOk['reward_ledger'][$rhythmKeyLSO]['minutes'] ?? 0) === 30
    && $gLegSubOk['states']['ritmo_jogo'] >= 53
    && MatchParticipation::finishedOnField($mLSO)
    && $stPenLSO['protagonist_eligible'] === true,
    'R1-R4 [P1-02.1]: reserva legado com entrada comprovada aos 60\' preserva FULL_TIME, 30\', 1 jogo, bônus de ritmo (+3 no ledger) e elegibilidade para pênaltis'
);
// Reexecução idempotente do encerramento no reserva legado
$snapLSO = $gLegSubOk['matches'][$idxLSO];
$snapLSOStates = $gLegSubOk['states'];
$snapLSOLedger = $gLegSubOk['reward_ledger'] ?? [];
$refSegment->invokeArgs(null, [&$gLegSubOk, 90]);
f04Check(
    $gLegSubOk['matches'][$idxLSO] === $snapLSO
    && $gLegSubOk['states'] === $snapLSOStates
    && ($gLegSubOk['reward_ledger'] ?? []) === $snapLSOLedger,
    'R1-R4 [P1-02.7]: reexecução do encerramento no reserva legado não altera logs, stats, ritmo ou ledger'
);

// 9.2 (P1-02 [2]) Reserva legado com entrada comprovada e saída registrada (60' -> 75')
$gLegSubOut = Game::legacyCreate('Reserva Legado Saiu R4', 'São Paulo', 'SP', 'meia');
$gLegSubOut['stage'] = 'partida';
$gLegSubOut['states']['ritmo_jogo'] = 50;
$idxLSO2 = Game::currentIndex($gLegSubOut);
$gLegSubOut['matches'][$idxLSO2] = array_merge($gLegSubOut['matches'][$idxLSO2], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 75,
    'sub_in_recorded' => true,
    'exit_reason' => 'fatigue',
    'evaluated_penalties' => ['80-90'],
]);
unset($gLegSubOut['matches'][$idxLSO2]['participation_state']);
$gLegSubOut = Game::command($gLegSubOut, 'advance');
$mLSO2 = $gLegSubOut['matches'][$idxLSO2];
f04Check(
    $mLSO2['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mLSO2['stats']['minutes'] === 15
    && Game::summary($gLegSubOut)['games'] === 1
    && $gLegSubOut['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($mLSO2),
    'R1-R4 [P1-02.2]: reserva legado com entrada comprovada e saída aos 75\' registra 15\', 1 jogo, ritmo +3 e fica inelegível para pênaltis'
);

// 9.3 (P1-02 [3]) Reserva legado sem evidência de entrada (60-90 planejado apenas)
$gLegSubNoEv = Game::legacyCreate('Reserva Legado Sem Ev R4', 'São Paulo', 'SP', 'meia');
$gLegSubNoEv['stage'] = 'partida';
$gLegSubNoEv['states']['ritmo_jogo'] = 50;
$idxLSN = Game::currentIndex($gLegSubNoEv);
$gLegSubNoEv['matches'][$idxLSN] = array_merge($gLegSubNoEv['matches'][$idxLSN], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'evaluated_penalties' => ['80-90'],
]);
unset($gLegSubNoEv['matches'][$idxLSN]['participation_state']);
$gLegSubNoEv = Game::command($gLegSubNoEv, 'advance');
$mLSN = $gLegSubNoEv['matches'][$idxLSN];
f04Check(
    $mLSN['participation_state'] === MatchParticipation::STATE_ON_BENCH
    && $mLSN['stats']['minutes'] === 0
    && Game::summary($gLegSubNoEv)['games'] === 0
    && $gLegSubNoEv['states']['ritmo_jogo'] === 50
    && !MatchParticipation::finishedOnField($mLSN),
    'R1-R4 [P1-02.3]: reserva legado sem evidência de entrada (apenas janela 60-90) permanece ON_BENCH com 0\', 0 jogos e ritmo inalterado'
);

// 9.4 (P1-02 [5]) Reserva novo com entrada aos 70' (fluxo real intervalo -> 60' -> 70' -> 90')
$gNewSub70 = Game::legacyCreate('Reserva Novo 70 R4', 'São Paulo', 'SP', 'meia');
$gNewSub70['stage'] = 'partida';
$gNewSub70['states']['energia'] = 90;
$gNewSub70['states']['risco_lesao'] = 0;
$gNewSub70['states']['ritmo_jogo'] = 50;
$idxNS70 = Game::currentIndex($gNewSub70);
$gNewSub70['matches'][$idxNS70] = array_merge($gNewSub70['matches'][$idxNS70], [
    'status' => 'intervalo',
    'minute' => 45,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_BENCH,
    'entry' => 70,
    'exit' => 90,
    'evaluated_penalties' => ['45-60', '60-70', '70-80', '80-90'],
]);
for ($st70 = 0; $st70 < 20 && $gNewSub70['stage'] === 'partida'; $st70++) {
    $gNewSub70 = MatchParticipation::canActOnField($gNewSub70['matches'][$idxNS70])
        ? Game::command($gNewSub70, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gNewSub70, 'advance');
}
$mNS70 = $gNewSub70['matches'][$idxNS70];
$rhythmKeyNS70 = 'f04:rhythm:' . $gNewSub70['season'] . ':legacy:' . $mNS70['round'] . ':' . $mNS70['home'] . ':' . $mNS70['away'];
f04Check(
    $mNS70['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && !empty($mNS70['sub_in_recorded'])
    && $mNS70['stats']['minutes'] === 20
    && ($gNewSub70['reward_ledger'][$rhythmKeyNS70]['delta'] ?? 0) === 3
    && ($gNewSub70['reward_ledger'][$rhythmKeyNS70]['minutes'] ?? 0) === 20
    && $gNewSub70['states']['ritmo_jogo'] >= 53
    && MatchParticipation::finishedOnField($mNS70),
    'R1-R4 [P1-02.5]: reserva novo com entrada aos 70\' conclui com FULL_TIME, 20\', bônus de ritmo (+3 no ledger) e elegibilidade para pênaltis'
);

// 9.5 (P1-03 Cenário A) Lesão legada aos 80' com exit=90 (horário contraditório, sem estado canônico)
$gInj90 = Game::create('Lesao Contraditoria R4', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gInj90 = Game::command($gInj90, 'handicap');
$gInj90['stage'] = 'partida';
$gInj90['states']['ritmo_jogo'] = 50;
$gInj90['states']['confianca'] = 45;
$xpBeforeInj90 = array_sum(array_column($gInj90['skills'], 'xp'));
$idxI90 = Game::currentIndex($gInj90);
$gInj90['matches'][$idxI90] = array_merge($gInj90['matches'][$idxI90], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'titular',
    'entry' => 0,
    'exit' => 90,
    'exit_reason' => 'injury',
    'evaluated_penalties' => ['80-90'],
]);
unset($gInj90['matches'][$idxI90]['participation_state']);
$inj90Blocked = false;
try { Game::command($gInj90, 'choice', ['option' => 0], false, $rngAlwaysSuccess); } catch (InvalidArgumentException $e) { $inj90Blocked = true; }
$gInj90End = Game::command($gInj90, 'advance');
$mI90End = $gInj90End['matches'][$idxI90];
$xpAfterInj90 = array_sum(array_column($gInj90End['skills'], 'xp'));
$rhythmKeyI90 = 'f04:rhythm:' . $gInj90End['season'] . ':' . ($gInj90End['competition_id'] ?? 'legacy') . ':' . $mI90End['round'] . ':' . $mI90End['home'] . ':' . $mI90End['away'];
f04Check(
    $inj90Blocked
    && $mI90End['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mI90End['stats']['minutes'] === 0
    && $mI90End['stats']['rating'] === 0
    && Game::summary($gInj90End)['games'] === 0
    && $gInj90End['states']['ritmo_jogo'] === 50
    && $gInj90End['states']['confianca'] === 45
    && $xpAfterInj90 === $xpBeforeInj90
    && !isset($gInj90End['reward_ledger'][$rhythmKeyI90])
    && !MatchParticipation::finishedOnField($mI90End),
    'R1-R4 [P1-03A]: lesão legada com exit=90 contraditório não fabrica 90\', mantém 0 min, 0 XP, confiança/ritmo intactos e sem pênaltis'
);

// 9.6 (P1-03 Cenário B) Expulsão legada direct_red=1 aos 80' com exit=90 (sem red_card_minute)
$gRed90 = Game::create('Red Contraditorio R4', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gRed90 = Game::command($gRed90, 'handicap');
$gRed90['stage'] = 'partida';
$gRed90['states']['ritmo_jogo'] = 50;
$gRed90['states']['confianca'] = 45;
$xpBeforeRed90 = array_sum(array_column($gRed90['skills'], 'xp'));
$idxR90 = Game::currentIndex($gRed90);
$gRed90['matches'][$idxR90] = array_merge($gRed90['matches'][$idxR90], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'titular',
    'entry' => 0,
    'exit' => 90,
    'evaluated_penalties' => ['80-90'],
]);
$gRed90['matches'][$idxR90]['stats']['direct_red'] = 1;
unset($gRed90['matches'][$idxR90]['participation_state']);
$red90Blocked = false;
try { Game::command($gRed90, 'choice', ['option' => 0], false, $rngAlwaysSuccess); } catch (InvalidArgumentException $e) { $red90Blocked = true; }
$gRed90End = Game::command($gRed90, 'advance');
$mR90End = $gRed90End['matches'][$idxR90];
$xpAfterRed90 = array_sum(array_column($gRed90End['skills'], 'xp'));
$rhythmKeyR90 = 'f04:rhythm:' . $gRed90End['season'] . ':' . ($gRed90End['competition_id'] ?? 'legacy') . ':' . $mR90End['round'] . ':' . $mR90End['home'] . ':' . $mR90End['away'];
f04Check(
    $red90Blocked
    && $mR90End['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $mR90End['stats']['minutes'] === 0
    && $mR90End['stats']['rating'] === 0
    && Game::summary($gRed90End)['games'] === 0
    && $gRed90End['states']['ritmo_jogo'] === 50
    && $gRed90End['states']['confianca'] === 45
    && $xpAfterRed90 === $xpBeforeRed90
    && !isset($gRed90End['reward_ledger'][$rhythmKeyR90])
    && !MatchParticipation::finishedOnField($mR90End),
    'R1-R4 [P1-03B]: direct_red=1 legado com exit=90 contraditório não fabrica 90\', mantém 0 min, 0 XP, confiança/ritmo intactos e sem pênaltis'
);
// Reexecução do encerramento sobre P1-03B permanece inócua
$snapR90 = $gRed90End['matches'][$idxR90];
$snapR90States = $gRed90End['states'];
$refSegment->invokeArgs(null, [&$gRed90End, 90]);
f04Check($gRed90End['matches'][$idxR90] === $snapR90 && $gRed90End['states'] === $snapR90States, 'R1-R4 [P1-03]: repetição do encerramento em saída contraditória permanece inócua');

// 9.7 (P1-03 Positivos com horário real conhecido + contraditórios adicionais)
// Lesão legada com horário real conhecido (0 -> 35) preserva 35 minutos
$gInj35 = Game::legacyCreate('Lesao 35 Conhecida R4', 'São Paulo', 'SP', 'meia');
$gInj35['stage'] = 'partida';
$gInj35['states']['ritmo_jogo'] = 50;
$idxI35 = Game::currentIndex($gInj35);
$gInj35['matches'][$idxI35] = array_merge($gInj35['matches'][$idxI35], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'titular',
    'entry' => 0,
    'exit' => 35,
    'exit_reason' => 'injury',
    'evaluated_penalties' => ['80-90'],
]);
unset($gInj35['matches'][$idxI35]['participation_state']);
$gInj35End = Game::command($gInj35, 'advance');
f04Check(
    $gInj35End['matches'][$idxI35]['stats']['minutes'] === 35
    && $gInj35End['matches'][$idxI35]['stats']['rating'] > 0
    && $gInj35End['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($gInj35End['matches'][$idxI35]),
    'R1-R4 [P1-03]: lesão legada com horário real conhecido (exit=35) preserva 35\', nota > 0, ritmo +3 e bloqueia pênaltis'
);

// Expulsão com red_card_minute=40 conhecido (mesmo com exit=90 legado) preserva 40 minutos (não 90!)
$gRed40 = Game::legacyCreate('Expulsao 40 Conhecida R4', 'São Paulo', 'SP', 'meia');
$gRed40['stage'] = 'partida';
$gRed40['states']['ritmo_jogo'] = 50;
$idxR40 = Game::currentIndex($gRed40);
$gRed40['matches'][$idxR40] = array_merge($gRed40['matches'][$idxR40], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'titular',
    'entry' => 0,
    'exit' => 90,
    'red_card_minute' => 40,
    'evaluated_penalties' => ['80-90'],
]);
$gRed40['matches'][$idxR40]['stats']['direct_red'] = 1;
unset($gRed40['matches'][$idxR40]['participation_state']);
$gRed40End = Game::command($gRed40, 'advance');
f04Check(
    $gRed40End['matches'][$idxR40]['stats']['minutes'] === 40
    && $gRed40End['matches'][$idxR40]['stats']['rating'] > 0
    && $gRed40End['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($gRed40End['matches'][$idxR40]),
    'R1-R4 [P1-03]: expulsão com red_card_minute=40 (e exit=90 legado) calcula 40\' reais em vez de 90\', concede ritmo +3 e bloqueia pênaltis'
);

// Expulsão contraditória (red_card_minute=70 vs exit=40, ou red_card_minute=0) e reserva lesionado sem entrada comprovada -> 0 min
f04Check(
    MatchParticipation::playedMinutes(['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SENT_OFF, 'entry' => 0, 'exit' => 40, 'red_card_minute' => 70]) === 0,
    'R1-R4 [P1-03]: expulsão com red_card_minute=70 contradizendo exit=40 retorna 0 minutos'
);
f04Check(
    MatchParticipation::playedMinutes(['status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_SENT_OFF, 'entry' => 0, 'exit' => 60, 'red_card_minute' => 0]) === 0,
    'R1-R4 [P1-03]: expulsão com red_card_minute=0 não confiável retorna 0 minutos'
);
f04Check(
    MatchParticipation::playedMinutes(['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 60, 'exit' => 75, 'exit_reason' => 'injury']) === 0,
    'R1-R4 [P1-03]: lesão canônica em reserva sem entrada demonstrada retorna 0 minutos'
);
f04Check(
    MatchParticipation::playedMinutes(['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'entry' => 60, 'exit' => 75, 'exit_reason' => 'injury', 'sub_in_recorded' => true, 'entered_on_field' => true]) === 15,
    'R1-R4 [P1-03]: lesão canônica em reserva com entrada demonstrada e exit=75 preserva 15 minutos'
);

// =========================================================================
// 10. MATRIZ ADVERSARIAL R1-R5 (P1-01 Segmento Span Zero e P1-02 Logs Históricos)
// =========================================================================
echo "\n10. Validando Matriz Adversarial R1-R5 (Duração Zero e Reconciliação por Logs)...\n";

// 10.1 (P1-01) Segmento de duração zero (93', 92', 90') SEM evaluated_penalties pré-marcado nunca gera eventos esportivos
$zeroSpanViolations = 0;
for ($zsIter = 0; $zsIter < 120; $zsIter++) {
    $gZS = Game::legacyCreate('SpanZero93 R5', 'São Paulo', 'SP', 'meia');
    $gZS['stage'] = 'partida';
    $idxZS = Game::currentIndex($gZS);
    $gZS['matches'][$idxZS] = array_merge($gZS['matches'][$idxZS], [
        'status' => 'em_andamento',
        'minute' => 93,
        'hg' => 0,
        'ag' => 0,
        'lineup' => 'titular',
        'participation_state' => MatchParticipation::STATE_ON_FIELD,
        'entry' => 0,
        'exit' => 90,
        'entered_on_field' => true,
        'evaluated_penalties' => [],
        'sound_events' => [],
    ]);
    $gZSEnd = Game::command($gZS, 'advance');
    $mZSEnd = $gZSEnd['matches'][$idxZS];
    $hasZeroWindow = in_array('93-93', $mZSEnd['evaluated_penalties'] ?? [], true);
    if ($mZSEnd['hg'] !== 0
        || $mZSEnd['ag'] !== 0
        || $mZSEnd['minute'] !== 93
        || $mZSEnd['status'] !== 'finalizada'
        || !empty($mZSEnd['sound_events'])
        || !empty($mZSEnd['active_match_penalty'])
        || $hasZeroWindow
    ) {
        $zeroSpanViolations++;
        break;
    }
}
f04Check($zeroSpanViolations === 0, 'R1-R5 [P1-01]: 120 execuções de avanço aos 93\' (span=0, sem evaluated_penalties) preservam placar 0-0, 0 áudios esportivos e nenhuma janela 93-93 avaliada');

foreach ([90, 92, 93] as $closeMin) {
    $gZMin = Game::legacyCreate('SpanZero' . $closeMin, 'São Paulo', 'SP', 'meia');
    $gZMin['stage'] = 'partida';
    $idxZM = Game::currentIndex($gZMin);
    $gZMin['matches'][$idxZM] = array_merge($gZMin['matches'][$idxZM], [
        'status' => 'em_andamento',
        'minute' => $closeMin,
        'hg' => 2,
        'ag' => 1,
        'lineup' => 'titular',
        'participation_state' => MatchParticipation::STATE_ON_FIELD,
        'entry' => 0,
        'exit' => 90,
        'entered_on_field' => true,
        'evaluated_penalties' => [],
        'sound_events' => [],
    ]);
    $gZMinEnd = Game::command($gZMin, 'advance');
    $mZMEnd = $gZMinEnd['matches'][$idxZM];
    $snapZM = $mZMEnd;
    $snapZMStates = $gZMinEnd['states'];
    $refSegment->invokeArgs(null, [&$gZMinEnd, $closeMin]);
    f04Check(
        $gZMinEnd['stage'] === 'fim_semana'
        && $mZMEnd['status'] === 'finalizada'
        && $mZMEnd['minute'] === $closeMin
        && $mZMEnd['hg'] === 2 && $mZMEnd['ag'] === 1
        && empty($mZMEnd['sound_events'])
        && $gZMinEnd['matches'][$idxZM] === $snapZM
        && $gZMinEnd['states'] === $snapZMStates,
        "R1-R5 [P1-01]: encerramento com span=0 aos {$closeMin}' preserva placar (2x1), relógio, 0 áudios e idempotência na repetição"
    );
}

// Segmento com duração positiva (span > 0) continua gerando eventos esportivos normalmente
$posSpanEvents = 0;
for ($psIter = 0; $psIter < 120; $psIter++) {
    $gPS = Game::legacyCreate('SpanPositivo R5', 'São Paulo', 'SP', 'meia');
    $gPS['stage'] = 'partida';
    $idxPS = Game::currentIndex($gPS);
    $gPS['matches'][$idxPS] = array_merge($gPS['matches'][$idxPS], [
        'status' => 'em_andamento',
        'minute' => 60,
        'hg' => 0,
        'ag' => 0,
        'lineup' => 'reserva',
        'participation_state' => MatchParticipation::STATE_ON_BENCH,
        'entry' => 90,
        'exit' => 90,
        'evaluated_penalties' => [],
    ]);
    $refSegment->invokeArgs(null, [&$gPS, 70]);
    $mPSEnd = $gPS['matches'][$idxPS];
    if ($mPSEnd['hg'] > 0 || $mPSEnd['ag'] > 0 || !empty($mPSEnd['sound_events'])) {
        $posSpanEvents++;
    }
}
f04Check($posSpanEvents > 0, 'R1-R5 [P1-01]: segmento com duração positiva (60->70) preserva geração legítima de gols/eventos esportivos');

// Partida eliminatória empatada no agregado que encerra via span=0 aos 93' preserva exigência de disputa de pênaltis
$tKnock93 = [
    'id' => 'sp-a3',
    'phase' => 'quartas',
    'ties' => [['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']],
    'matches' => [
        array_merge(Schedule::match(15, 'santos', 'sao-paulo'), ['phase' => 'quartas', 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        array_merge(Schedule::match(16, 'sao-paulo', 'santos'), [
            'phase' => 'quartas', 'tie' => 0, 'status' => 'em_andamento', 'minute' => 93, 'hg' => 1, 'ag' => 0,
            'lineup' => 'titular', 'participation_state' => MatchParticipation::STATE_ON_FIELD,
            'entry' => 0, 'exit' => 90, 'entered_on_field' => true, 'evaluated_penalties' => [],
        ]),
    ],
];
$gK93 = Game::legacyCreate('MataMata93 R5', 'São Paulo', 'SP', 'meia');
$gK93['club'] = 'sao-paulo';
$gK93['official'] = true;
$gK93['competition_id'] = 'sp-a3';
$gK93['competitions'] = ['sp-a3' => $tKnock93];
$gK93['matches'] = $tKnock93['matches'];
$gK93['round'] = 16;
$gK93['stage'] = 'partida';
$gK93 = Game::command($gK93, 'advance');
$mK93End = $gK93['matches'][1];
f04Check(
    $gK93['stage'] === 'fim_semana'
    && $mK93End['hg'] === 1 && $mK93End['ag'] === 0
    && PenaltyShootout::requiresShootout($gK93, $mK93End) === true,
    'R1-R5 [P1-01]: eliminatória empatada no agregado encerrada via span=0 aos 93\' mantém placar e exige disputa de pênaltis'
);
$gK93Pen = Game::command($gK93, 'shootout_start');
f04Check($gK93Pen['stage'] === 'penaltis' && $gK93Pen['shootout']['protagonist_eligible'] === true, 'R1-R5 [P1-01]: disputa de pênaltis inicia corretamente após encerramento aos 93\' com protagonista elegível');

// 10.2 (P1-02 Variantes A, B, C e Matriz Completa de 15 Cenários de Log Histórico)
// [1] Variante A: Reserva entrou aos 60', exit=90 planejado, log formal de lesão aos 75' -> 15 min, SUBSTITUTED_OUT, recompensas sobre 15', inelegível
$gVarA = Game::create('VarA Lesao 75 R5', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gVarA = Game::command($gVarA, 'handicap');
$gVarA['stage'] = 'partida';
$gVarA['states']['ritmo_jogo'] = 50;
$gVarA['states']['confianca'] = 40;
$xpBeforeVarA = array_sum(array_column($gVarA['skills'], 'xp'));
$idxVA = Game::currentIndex($gVarA);
$gVarA['matches'][$idxVA] = array_merge($gVarA['matches'][$idxVA], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'sub_in_recorded' => true,
    'entered_on_field' => true,
    'exit_reason' => 'injury',
    'balance_version' => MatchBalance::VERSION,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Disputa dura. Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVarA['matches'][$idxVA]['participation_state']);
$gVarA = Game::command($gVarA, 'advance');
$mVA = $gVarA['matches'][$idxVA];
$xpAfterVarA = array_sum(array_column($gVarA['skills'], 'xp'));
$gPenVA = $gVarA;
$stPenVA = PenaltyShootout::start($gPenVA, $mVA);
f04Check(
    $mVA['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mVA['exit'] === 90 // não sobrescreve dado original do save
    && $mVA['stats']['minutes'] === 15
    && $mVA['stats']['rating'] > 0
    && Game::summary($gVarA)['games'] === 1
    && $gVarA['states']['ritmo_jogo'] === 53
    && $xpAfterVarA > $xpBeforeVarA
    && !MatchParticipation::finishedOnField($mVA)
    && $stPenVA['protagonist_eligible'] === false,
    'R1-R5 [P1-02.1/A]: reserva entrou aos 60\' e lesionado aos 75\' (log formal com exit=90 planejado) contabiliza 15\', 1 jogo, nota/XP/ritmo e bloqueia pênaltis'
);
// [14] Encerramento repetido na Variante A é inócuo
$snapVA = $gVarA['matches'][$idxVA];
$snapVAStates = $gVarA['states'];
$snapVALedger = $gVarA['reward_ledger'] ?? [];
$refSegment->invokeArgs(null, [&$gVarA, 90]);
f04Check($gVarA['matches'][$idxVA] === $snapVA && $gVarA['states'] === $snapVAStates && ($gVarA['reward_ledger'] ?? []) === $snapVALedger, 'R1-R5 [P1-02.14]: encerramento repetido em saída reconciliada por log permanece totalmente inócuo');

// [2] Variante B: Reserva entrou aos 60', exit=90 planejado, log formal de expulsão aos 75' -> 15 min, SENT_OFF, inelegível
$gVarB = Game::create('VarB Expulso 75 R5', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gVarB = Game::command($gVarB, 'handicap');
$gVarB['stage'] = 'partida';
$gVarB['states']['ritmo_jogo'] = 50;
$idxVB = Game::currentIndex($gVarB);
$gVarB['matches'][$idxVB] = array_merge($gVarB['matches'][$idxVB], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'entry' => 60,
    'exit' => 90,
    'sub_in_recorded' => true,
    'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVarB['matches'][$idxVB]['participation_state']);
$gVarB = Game::command($gVarB, 'advance');
$mVB = $gVarB['matches'][$idxVB];
$gPenVB = $gVarB;
$stPenVB = PenaltyShootout::start($gPenVB, $mVB);
f04Check(
    $mVB['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $mVB['stats']['minutes'] === 15
    && $mVB['stats']['rating'] > 0
    && Game::summary($gVarB)['games'] === 1
    && $gVarB['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($mVB)
    && $stPenVB['protagonist_eligible'] === false,
    'R1-R5 [P1-02.2/B]: reserva entrou aos 60\' e expulso aos 75\' (log formal com exit=90 planejado) define SENT_OFF, 15\', 1 jogo, ritmo +3 e bloqueia pênaltis'
);

// [3] Variante C: Reserva entrou aos 60', sub_out_recorded=true, exit=90 planejado, log formal substituição aos 75' -> 15 min (NÃO 30!)
$gVarC = Game::create('VarC Sub 75 R5', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gVarC = Game::command($gVarC, 'handicap');
$gVarC['stage'] = 'partida';
$gVarC['states']['ritmo_jogo'] = 50;
$idxVC = Game::currentIndex($gVarC);
$gVarC['matches'][$idxVC] = array_merge($gVarC['matches'][$idxVC], [
    'status' => 'em_andamento',
    'minute' => 80,
    'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
    'entry' => 60,
    'exit' => 90,
    'sub_in_recorded' => true,
    'entered_on_field' => true,
    'sub_out_recorded' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Você é substituído após participar da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
$gVarC = Game::command($gVarC, 'advance');
$mVC = $gVarC['matches'][$idxVC];
$gPenVC = $gVarC;
$stPenVC = PenaltyShootout::start($gPenVC, $mVC);
f04Check(
    $mVC['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mVC['stats']['minutes'] === 15
    && $mVC['stats']['rating'] > 0
    && Game::summary($gVarC)['games'] === 1
    && $gVarC['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($mVC)
    && $stPenVC['protagonist_eligible'] === false,
    'R1-R5 [P1-02.3/C]: reserva com sub_out_recorded=true, exit=90 planejado e log de substituição aos 75\' registra 15\' (não 30\') e bloqueia pênaltis'
);

// [4] Entrada comprovada sem saída antecipada -> 30', FULL_TIME, elegível para pênaltis
$gVar4 = Game::legacyCreate('Var4 SemSaida R5', 'São Paulo', 'SP', 'meia');
$gVar4['stage'] = 'partida';
$gVar4['states']['ritmo_jogo'] = 50;
$idxV4 = Game::currentIndex($gVar4);
$gVar4['matches'][$idxV4] = array_merge($gVar4['matches'][$idxV4], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."]],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar4['matches'][$idxV4]['participation_state']);
$gVar4 = Game::command($gVar4, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gVar4['stage'] === 'partida') {
    $gVar4 = MatchParticipation::canActOnField($gVar4['matches'][$idxV4])
        ? Game::command($gVar4, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gVar4, 'advance');
}
f04Check(
    $gVar4['matches'][$idxV4]['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $gVar4['matches'][$idxV4]['stats']['minutes'] === 30
    && MatchParticipation::finishedOnField($gVar4['matches'][$idxV4]),
    'R1-R5 [P1-02.4]: entrada comprovada aos 60\' sem saída antecipada preserva 30\', FULL_TIME e elegibilidade para pênaltis'
);

// [5] Saída somente em campo estruturado (sem log) e [6] somente em log (sem exit_reason nem participation_state)
f04Check(
    MatchParticipation::playedMinutes(['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 75, 'sub_in_recorded' => true, 'exit_reason' => 'fatigue', 'log' => []]) === 15,
    'R1-R5 [P1-02.5]: saída registrada somente em campo estruturado (exit=75) retorna 15\''
);
$gVar6 = Game::legacyCreate('Var6 SomenteLog R5', 'São Paulo', 'SP', 'meia');
$gVar6['stage'] = 'partida';
$idxV6 = Game::currentIndex($gVar6);
$gVar6['matches'][$idxV6] = array_merge($gVar6['matches'][$idxV6], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'sub_in_recorded' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Substituído por desgaste."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar6['matches'][$idxV6]['participation_state']);
$gVar6 = Game::command($gVar6, 'advance');
f04Check(
    $gVar6['matches'][$idxV6]['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $gVar6['matches'][$idxV6]['stats']['minutes'] === 15
    && !MatchParticipation::finishedOnField($gVar6['matches'][$idxV6]),
    'R1-R5 [P1-02.6]: saída registrada somente em log histórico confiável (desgaste aos 75\') contabiliza 15\' e SUBSTITUTED_OUT'
);

// [7] Planejado 90 + log real 75 (2º amarelo)
$gVar7 = Game::legacyCreate('Var7 2AmareloLog R5', 'São Paulo', 'SP', 'meia');
$gVar7['stage'] = 'partida';
$idxV7 = Game::currentIndex($gVar7);
$gVar7['matches'][$idxV7] = array_merge($gVar7['matches'][$idxV7], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'sub_in_recorded' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Segundo cartão amarelo! Você está expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar7['matches'][$idxV7]['participation_state']);
$gVar7 = Game::command($gVar7, 'advance');
f04Check(
    $gVar7['matches'][$idxV7]['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $gVar7['matches'][$idxV7]['stats']['minutes'] === 15
    && !MatchParticipation::finishedOnField($gVar7['matches'][$idxV7]),
    'R1-R5 [P1-02.7]: exit=90 planejado + log formal de 2º amarelo aos 75\' contabiliza 15\', SENT_OFF e bloqueia pênaltis'
);

// [8] Registros estruturados e logs com horários contraditórios -> conservador 0 min e 0 bônus
$gVar8 = Game::legacyCreate('Var8 Conflito R5', 'São Paulo', 'SP', 'meia');
$gVar8['stage'] = 'partida';
$gVar8['states']['ritmo_jogo'] = 50;
$idxV8 = Game::currentIndex($gVar8);
$gVar8['matches'][$idxV8] = array_merge($gVar8['matches'][$idxV8], [
    'status' => 'em_andamento', 'minute' => 85, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 70,
    'sub_in_recorded' => true, 'exit_reason' => 'injury',
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 82, 'text' => "82' | Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['85-90'],
]);
unset($gVar8['matches'][$idxV8]['participation_state']);
$gVar8 = Game::command($gVar8, 'advance');
f04Check(
    $gVar8['matches'][$idxV8]['stats']['minutes'] === 0
    && $gVar8['states']['ritmo_jogo'] === 50
    && !MatchParticipation::finishedOnField($gVar8['matches'][$idxV8]),
    'R1-R5 [P1-02.8]: conflito irreconciliável entre exit estruturado (70\') e log formal (82\') retorna 0 minutos e 0 bônus'
);
f04Check(
    MatchParticipation::playedMinutes([
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
        'sub_in_recorded' => true,
        'log' => [
            ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
            ['minute' => 70, 'text' => "70' | Lesão: você deixa o campo."],
            ['minute' => 82, 'text' => "82' | Você é substituído após participar da partida."],
        ],
    ]) === 0,
    'R1-R5 [P1-02.8]: dois logs formais de saída em minutos distintos (70\' vs 82\') retornam 0 minutos'
);

// [9] Log de expulsão de adversário e [10] texto narrativo genérico NÃO são tratados como saída do protagonista
$gVar9 = Game::legacyCreate('Var9 Adversario R5', 'São Paulo', 'SP', 'meia');
$gVar9['stage'] = 'partida';
$idxV9 = Game::currentIndex($gVar9);
$gVar9['matches'][$idxV9] = array_merge($gVar9['matches'][$idxV9], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Zagueiro adversário expulso após entrada dura."],
        ['minute' => 78, 'text' => "78' | Você sente o cansaço, mas permanece em campo lutando pela bola."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar9['matches'][$idxV9]['participation_state']);
$gVar9 = Game::command($gVar9, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gVar9['stage'] === 'partida') {
    $gVar9 = MatchParticipation::canActOnField($gVar9['matches'][$idxV9])
        ? Game::command($gVar9, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gVar9, 'advance');
}
f04Check(
    $gVar9['matches'][$idxV9]['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $gVar9['matches'][$idxV9]['stats']['minutes'] === 30
    && MatchParticipation::finishedOnField($gVar9['matches'][$idxV9]),
    'R1-R5 [P1-02.9/10]: expulsão de adversário e texto narrativo genérico no log não são confundidos com saída do protagonista (mantém 30\' e FULL_TIME)'
);

// [11] Entrada não comprovada com log de lesão aos 75' -> 0 min, 0 bônus
$gVar11 = Game::legacyCreate('Var11 SemEntrada R5', 'São Paulo', 'SP', 'meia');
$gVar11['stage'] = 'partida';
$gVar11['states']['ritmo_jogo'] = 50;
$idxV11 = Game::currentIndex($gVar11);
$gVar11['matches'][$idxV11] = array_merge($gVar11['matches'][$idxV11], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'log' => [['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."]],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar11['matches'][$idxV11]['participation_state']);
$gVar11 = Game::command($gVar11, 'advance');
f04Check(
    $gVar11['matches'][$idxV11]['stats']['minutes'] === 0
    && $gVar11['states']['ritmo_jogo'] === 50
    && !MatchParticipation::finishedOnField($gVar11['matches'][$idxV11]),
    'R1-R5 [P1-02.11]: reserva sem evidência de entrada com log de saída aos 75\' mantém 0\' e 0 bônus'
);

// [12] Saída legítima aos 90' e [13] saída legítima durante acréscimos (92')
f04Check(
    MatchParticipation::playedMinutes([
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva',
        'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
        'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'sub_out_recorded' => true,
        'log' => [
            ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
            ['minute' => 90, 'text' => "90' | Você é substituído após participar da partida."],
        ],
    ]) === 30,
    'R1-R5 [P1-02.12]: saída legítima registrada aos 90\' contabiliza 30\' (60->90)'
);
f04Check(
    MatchParticipation::playedMinutes([
        'status' => 'finalizada', 'minute' => 93, 'lineup' => 'reserva',
        'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
        'entry' => 60, 'exit' => 92, 'sub_in_recorded' => true, 'sub_out_recorded' => true,
        'log' => [
            ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
            ['minute' => 92, 'text' => "92' | Você é substituído após participar da partida."],
        ],
    ]) === 32,
    'R1-R5 [P1-02.13]: saída legítima durante acréscimos aos 92\' contabiliza 32\' (60->92)'
);

// [15] Save legado puro sem participation_state nem flag sub_in_recorded (apenas logs históricos 60' entrada e 75' lesão)
$gVar15 = Game::legacyCreate('Var15 LegadoPuro R5', 'São Paulo', 'SP', 'meia');
$gVar15['stage'] = 'partida';
$gVar15['states']['ritmo_jogo'] = 50;
$idxV15 = Game::currentIndex($gVar15);
$gVar15['matches'][$idxV15] = array_merge($gVar15['matches'][$idxV15], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gVar15['matches'][$idxV15]['participation_state']);
$gVar15 = Game::command($gVar15, 'advance');
$mV15 = $gVar15['matches'][$idxV15];
f04Check(
    $mV15['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mV15['stats']['minutes'] === 15
    && $mV15['stats']['rating'] > 0
    && Game::summary($gVar15)['games'] === 1
    && $gVar15['states']['ritmo_jogo'] === 53
    && !MatchParticipation::finishedOnField($mV15),
    'R1-R5 [P1-02.15]: save legado sem estado canônico nem flags (apenas logs 60\' entrada + 75\' lesão) reconcilia 15\', 1 jogo, nota/ritmo e bloqueia pênaltis'
);

echo "\n// =========================================================================\n";
echo "// 11. MATRIZ ADVERSARIAL R1-R6 (Falsos Positivos, Formatos Formais, Conflitos e Integração)\n";
echo "// =========================================================================\n";

// --- GRUPO A: FALSOS POSITIVOS (1-7) ---
// [A.1] Frase hipotética de substituição (reprodução exata do Codex)
$gR6A1 = Game::create('R6 A1 Hipotetica Sub', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6A1 = Game::command($gR6A1, 'handicap');
$gR6A1['stage'] = 'partida';
$gR6A1['states']['energia'] = 90;
$gR6A1['states']['risco_lesao'] = 0;
$gR6A1['states']['ritmo_jogo'] = 50;
$idxA1 = Game::currentIndex($gR6A1);
$hypSubText = 'Se Você é substituído na próxima partida, o técnico muda a estratégia.';
$gR6A1['matches'][$idxA1] = array_merge($gR6A1['matches'][$idxA1], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => $hypSubText],
    ],
    'evaluated_penalties' => ['80-90'],
]);
$mA1Before = $gR6A1['matches'][$idxA1];
$presA1 = Presentation::playerParticipation($gR6A1, $mA1Before);
$narrA1 = NarrativeContext::values($gR6A1);
f04Check(
    MatchParticipation::canActOnField($mA1Before) === true
    && MatchParticipation::hasExitMarker($mA1Before) === false
    && !isset($narrA1['exit_reason'])
    && $narrA1['on_field'] === true
    && $presA1['type'] === 'on_field'
    && $presA1['label'] === 'Em campo',
    'R1-R6 [A.1]: frase hipotética de substituição preserva canActOnField=true, sem exit_reason e apresentação Em campo'
);
$gR6A1 = Game::command($gR6A1, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gR6A1['stage'] === 'partida') {
    $gR6A1 = MatchParticipation::canActOnField($gR6A1['matches'][$idxA1])
        ? Game::command($gR6A1, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gR6A1, 'advance');
}
$mA1End = $gR6A1['matches'][$idxA1];
$rhythmKeyA1 = 'f04:rhythm:' . $gR6A1['season'] . ':' . ($gR6A1['competition_id'] ?? 'legacy') . ':' . $mA1End['round'] . ':' . $mA1End['home'] . ':' . $mA1End['away'];
$gPenA1 = $gR6A1;
$stPenA1 = PenaltyShootout::start($gPenA1, $mA1End);
$a1PreservedLog = !empty(array_filter($mA1End['log'], fn($l) => ($l['text'] ?? '') === $hypSubText));
f04Check(
    $mA1End['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $mA1End['stats']['minutes'] === 30
    && $mA1End['stats']['rating'] > 0
    && Game::summary($gR6A1)['games'] === 1
    && ($gR6A1['reward_ledger'][$rhythmKeyA1]['delta'] ?? 0) === 3
    && MatchParticipation::finishedOnField($mA1End) === true
    && $stPenA1['protagonist_eligible'] === true
    && $a1PreservedLog,
    'R1-R6 [A.1]: frase hipotética permite escolha esportiva válida, conclui FULL_TIME com 30\', elegível para pênaltis e preserva texto histórico'
);

// [A.2] Pergunta sobre expulsão anterior (reprodução exata do Codex)
$gR6A2 = Game::create('R6 A2 Pergunta Expulsao', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6A2 = Game::command($gR6A2, 'handicap');
$gR6A2['stage'] = 'partida';
$gR6A2['states']['energia'] = 90;
$gR6A2['states']['risco_lesao'] = 0;
$idxA2 = Game::currentIndex($gR6A2);
$hypRedText = 'O treinador pergunta: Você foi expulso da partida anterior?';
$gR6A2['matches'][$idxA2] = array_merge($gR6A2['matches'][$idxA2], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => $hypRedText],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6A2['matches'][$idxA2]['participation_state']);
$mA2Before = $gR6A2['matches'][$idxA2];
$presA2 = Presentation::playerParticipation($gR6A2, $mA2Before);
$narrA2 = NarrativeContext::values($gR6A2);
f04Check(
    MatchParticipation::isSentOff($mA2Before) === false
    && MatchParticipation::canActOnField($mA2Before) === true
    && !isset($narrA2['exit_reason'])
    && $presA2['type'] === 'on_field',
    'R1-R6 [A.2]: pergunta sobre expulsão anterior não classifica SENT_OFF nem bloqueia atuação'
);
$gR6A2 = Game::command($gR6A2, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gR6A2['stage'] === 'partida') {
    $gR6A2 = MatchParticipation::canActOnField($gR6A2['matches'][$idxA2])
        ? Game::command($gR6A2, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gR6A2, 'advance');
}
$mA2End = $gR6A2['matches'][$idxA2];
$a2PreservedLog = !empty(array_filter($mA2End['log'], fn($l) => ($l['text'] ?? '') === $hypRedText));
f04Check(
    $mA2End['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $mA2End['stats']['minutes'] === 30
    && MatchParticipation::finishedOnField($mA2End) === true
    && $a2PreservedLog,
    'R1-R6 [A.2]: pergunta sobre expulsão anterior conclui FULL_TIME com 30\', elegível para pênaltis e texto preservado'
);

// [A.3 - A.7] Outros falsos positivos: adversário, companheiro, comentário narrativo, lesão futura, fragmentos parciais
$falsePositiveCases = [
    'A.3_adversario' => "75' | Cartão vermelho direto! O zagueiro adversário foi expulso da partida.",
    'A.4_companheiro' => "75' | O ponta-direita é substituído após participar da partida.",
    'A.5_comentario_sub' => "O técnico comentou: se cair o rendimento, Você é substituído após participar da partida?",
    'A.5_comentario_red' => "Na entrevista, citaram: Cartão vermelho direto! Você foi expulso da partida?",
    'A.6_lesao_futura' => "75' | O departamento médico alertou sobre risco futuro de Lesão: você deixa o campo na próxima rodada.",
    'A.6_lesao_pergunta' => "Se sentir dor, Lesão: você deixa o campo?",
    'A.7_fragmento_sub' => "75' | Você é substituído.",
    'A.7_fragmento_red1' => "75' | Você foi expulso da partida.",
    'A.7_fragmento_red2' => "75' | Você está expulso da partida.",
    'A.7_fragmento_desgaste' => "75' | O adversário foi Substituído por desgaste na etapa inicial.",
];
foreach ($falsePositiveCases as $fpKey => $fpText) {
    $gFP = Game::legacyCreate('R6 ' . $fpKey, 'São Paulo', 'SP', 'meia');
    $gFP['stage'] = 'partida';
    $idxFP = Game::currentIndex($gFP);
    $gFP['matches'][$idxFP] = array_merge($gFP['matches'][$idxFP], [
        'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
        'participation_state' => MatchParticipation::STATE_ON_FIELD,
        'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
        'log' => [
            ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
            ['minute' => 75, 'text' => $fpText],
        ],
        'evaluated_penalties' => ['80-90'],
    ]);
    $mFP = $gFP['matches'][$idxFP];
    $presFP = Presentation::playerParticipation($gFP, $mFP);
    $narrFP = NarrativeContext::values($gFP);
    $gFP['states']['energia'] = 90;
    $gFP['states']['risco_lesao'] = 0;
    $gFPEnd = $gFP;
    while ($gFPEnd['stage'] === 'partida') {
        $gFPEnd = MatchParticipation::canActOnField($gFPEnd['matches'][$idxFP])
            ? Game::command($gFPEnd, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
            : Game::command($gFPEnd, 'advance');
    }
    $mFPEnd = $gFPEnd['matches'][$idxFP];
    f04Check(
        MatchParticipation::canActOnField($mFP) === true
        && MatchParticipation::isSentOff($mFP) === false
        && MatchParticipation::hasExitMarker($mFP) === false
        && !isset($narrFP['exit_reason'])
        && $presFP['type'] === 'on_field'
        && $mFPEnd['participation_state'] === MatchParticipation::STATE_FULL_TIME
        && $mFPEnd['stats']['minutes'] === 30
        && MatchParticipation::finishedOnField($mFPEnd) === true,
        "R1-R6 [{$fpKey}]: falso positivo rejeitado (mantém ON_FIELD, 30', FULL_TIME e elegibilidade para pênaltis)"
    );
}

// --- GRUPO B: EVENTOS FORMAIS VÁLIDOS (8-15) ---
$validFormalCases = [
    'B.8_sub_isolada' => [
        'log' => [['minute' => 75, 'text' => "75' | Você é substituído após participar da partida."]],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 15, 'narr' => 'tactical', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 75′',
    ],
    'B.9_lesao_isolada' => [
        'log' => [['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."]],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 15, 'narr' => 'injury', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 75′',
    ],
    'B.10_vermelho_direto_isolado' => [
        'log' => [['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida."]],
        'state' => MatchParticipation::STATE_SENT_OFF, 'min' => 15, 'narr' => 'red', 'presType' => 'sent_off', 'presLabel' => 'Expulso aos 75′',
    ],
    'B.11_segundo_amarelo_isolado' => [
        'log' => [['minute' => 75, 'text' => "75' | Segundo cartão amarelo! Você está expulso da partida."]],
        'state' => MatchParticipation::STATE_SENT_OFF, 'min' => 15, 'narr' => 'red', 'presType' => 'sent_off', 'presLabel' => 'Expulso aos 75′',
    ],
    'B.11_segundo_amarelo_legado' => [
        'log' => [['minute' => 75, 'text' => "75' | Expulso: segundo cartão."]],
        'state' => MatchParticipation::STATE_SENT_OFF, 'min' => 15, 'narr' => 'red', 'presType' => 'sent_off', 'presLabel' => 'Expulso aos 75′',
    ],
    'B.12_concat_red_direto' => [
        'log' => [['minute' => 75, 'text' => "Desarme: a intenção não teve o efeito esperado. Você chega atrasado e comete falta. 75' | Cartão vermelho direto! Você foi expulso da partida."]],
        'state' => MatchParticipation::STATE_SENT_OFF, 'min' => 15, 'narr' => 'red', 'presType' => 'sent_off', 'presLabel' => 'Expulso aos 75′',
    ],
    'B.12_concat_2amarelo_com_min_anterior' => [
        'log' => [['minute' => 75, 'text' => "Aos 50' houve advertência. Desarme: falhou. 75' | Segundo cartão amarelo! Você está expulso da partida."]],
        'state' => MatchParticipation::STATE_SENT_OFF, 'min' => 15, 'narr' => 'red', 'presType' => 'sent_off', 'presLabel' => 'Expulso aos 75′',
    ],
    'B.12_concat_lesao' => [
        'log' => [['minute' => 75, 'text' => "Passe: a intenção deu certo. Lesão: você deixa o campo."]],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 15, 'narr' => 'injury', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 75′',
    ],
    'B.12_concat_desgaste' => [
        'log' => [['minute' => 75, 'text' => "Drible: a intenção não teve o efeito esperado. Substituído por desgaste."]],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 15, 'narr' => 'fatigue', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 75′',
    ],
    'B.13_duplicado_mesmo_minuto' => [
        'log' => [
            ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."],
            ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."],
        ],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 15, 'narr' => 'injury', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 75′',
    ],
    'B.14_saida_90m' => [
        'minute' => 90,
        'log' => [['minute' => 90, 'text' => "90' | Você é substituído após participar da partida."]],
        'state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'min' => 30, 'narr' => 'tactical', 'presType' => 'substituted', 'presLabel' => 'Substituído aos 90′',
    ],
];
foreach ($validFormalCases as $vfKey => $vfCfg) {
    $gVF = Game::legacyCreate('R6 ' . $vfKey, 'São Paulo', 'SP', 'meia');
    $gVF['stage'] = 'partida';
    $gVF['states']['ritmo_jogo'] = 50;
    $idxVF = Game::currentIndex($gVF);
    $gVF['matches'][$idxVF] = array_merge($gVF['matches'][$idxVF], [
        'status' => 'em_andamento', 'minute' => $vfCfg['minute'] ?? 80, 'lineup' => 'reserva',
        'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
        'log' => array_merge(
            [['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."]],
            $vfCfg['log']
        ),
        'evaluated_penalties' => ['80-90'],
    ]);
    unset($gVF['matches'][$idxVF]['participation_state']);
    $gVFEnd = Game::command($gVF, 'advance');
    $mVFEnd = $gVFEnd['matches'][$idxVF];
    $presVF = Presentation::playerParticipation($gVFEnd, $mVFEnd);
    $narrVF = NarrativeContext::values($gVFEnd);
    f04Check(
        $mVFEnd['participation_state'] === $vfCfg['state']
        && $mVFEnd['stats']['minutes'] === $vfCfg['min']
        && $mVFEnd['stats']['rating'] > 0
        && $gVFEnd['states']['ritmo_jogo'] === 53
        && ($narrVF['exit_reason'] ?? null) === $vfCfg['narr']
        && $presVF['type'] === $vfCfg['presType']
        && $presVF['label'] === $vfCfg['presLabel']
        && !MatchParticipation::finishedOnField($mVFEnd),
        "R1-R6 [{$vfKey}]: evento formal válido reconciliado ({$vfCfg['min']}', {$vfCfg['state']}, {$vfCfg['presLabel']}, narr={$vfCfg['narr']})"
    );
}

// [B.15] Saída formal aos 92 minutos nos acréscimos
$gR6B15 = Game::legacyCreate('R6 B15 Acrescimos 92', 'São Paulo', 'SP', 'meia');
$gR6B15['stage'] = 'partida';
$gR6B15['states']['ritmo_jogo'] = 50;
$idxB15 = Game::currentIndex($gR6B15);
$gR6B15['matches'][$idxB15] = array_merge($gR6B15['matches'][$idxB15], [
    'status' => 'em_andamento', 'minute' => 93, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 92, 'text' => "92' | Você é substituído após participar da partida."],
    ],
    'evaluated_penalties' => [],
]);
unset($gR6B15['matches'][$idxB15]['participation_state']);
$gR6B15End = Game::command($gR6B15, 'advance');
$mB15 = $gR6B15End['matches'][$idxB15];
$presB15 = Presentation::playerParticipation($gR6B15End, $mB15);
f04Check(
    $mB15['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mB15['minute'] === 93
    && $mB15['stats']['minutes'] === 32
    && $presB15['label'] === 'Substituído aos 92′'
    && !MatchParticipation::finishedOnField($mB15),
    'R1-R6 [B.15]: saída formal aos 92\' durante acréscimos contabiliza 32\', exibe Substituído aos 92′ e bloqueia pênaltis'
);

// --- GRUPO C: CONFLITOS (16-24) ---
// [C.16] minute=75 vs texto formal 82' isolado
$gR6C16 = Game::create('R6 C16 Tempo Isolado', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6C16 = Game::command($gR6C16, 'handicap');
$gR6C16['stage'] = 'partida';
$gR6C16['states']['ritmo_jogo'] = 50;
$gR6C16['states']['confianca'] = 45;
$xpC16Before = array_sum(array_column($gR6C16['skills'], 'xp'));
$idxC16 = Game::currentIndex($gR6C16);
$gR6C16['matches'][$idxC16] = array_merge($gR6C16['matches'][$idxC16], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "82' | Cartão vermelho direto! Você foi expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6C16['matches'][$idxC16]['participation_state']);
$gR6C16End = Game::command($gR6C16, 'advance');
$mC16 = $gR6C16End['matches'][$idxC16];
$presC16 = Presentation::playerParticipation($gR6C16End, $mC16);
f04Check(
    $mC16['stats']['minutes'] === 0
    && $mC16['stats']['rating'] === 0
    && Game::summary($gR6C16End)['games'] === 0
    && $gR6C16End['states']['ritmo_jogo'] === 50
    && $gR6C16End['states']['confianca'] === 45
    && array_sum(array_column($gR6C16End['skills'], 'xp')) === $xpC16Before
    && $presC16['type'] === 'sent_off'
    && $presC16['label'] === 'Expulso'
    && !MatchParticipation::finishedOnField($mC16),
    'R1-R6 [C.16]: minute=75 vs texto formal 82\' isolado detecta conflito temporal, credita 0\'/0 recompensas e exibe Expulso sem minuto arbitrário'
);

// [C.17] Mesmo conflito com prefixo narrativo (reprodução exata do Codex P1-02)
$gR6C17 = Game::create('R6 C17 Tempo Prefixo', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6C17 = Game::command($gR6C17, 'handicap');
$gR6C17['stage'] = 'partida';
$gR6C17['states']['ritmo_jogo'] = 50;
$gR6C17['states']['confianca'] = 45;
$xpC17Before = array_sum(array_column($gR6C17['skills'], 'xp'));
$idxC17 = Game::currentIndex($gR6C17);
$gR6C17['matches'][$idxC17] = array_merge($gR6C17['matches'][$idxC17], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "Desarme: falhou. 82' | Cartão vermelho direto! Você foi expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6C17['matches'][$idxC17]['participation_state']);
f04Check(MatchParticipation::canActOnField($gR6C17['matches'][$idxC17]) === false, 'R1-R6 [C.17]: conflito temporal com prefixo narrativo bloqueia atuação em campo');
$gR6C17End = Game::command($gR6C17, 'advance');
$mC17 = $gR6C17End['matches'][$idxC17];
$presC17 = Presentation::playerParticipation($gR6C17End, $mC17);
f04Check(
    $mC17['stats']['minutes'] === 0
    && $mC17['stats']['rating'] === 0
    && Game::summary($gR6C17End)['games'] === 0
    && $gR6C17End['states']['ritmo_jogo'] === 50
    && $gR6C17End['states']['confianca'] === 45
    && array_sum(array_column($gR6C17End['skills'], 'xp')) === $xpC17Before
    && $presC17['type'] === 'sent_off'
    && $presC17['label'] === 'Expulso'
    && !MatchParticipation::finishedOnField($mC17),
    'R1-R6 [C.17]: conflito temporal ocultado por prefixo narrativo (75 vs 82) é detectado e credita 0\'/0 nota/0 XP/0 ritmo'
);

// [C.18] Dois logs formais com minutos distintos
$gR6C18 = Game::legacyCreate('R6 C18 Dois Logs', 'São Paulo', 'SP', 'meia');
$gR6C18['stage'] = 'partida';
$gR6C18['states']['ritmo_jogo'] = 50;
$idxC18 = Game::currentIndex($gR6C18);
$gR6C18['matches'][$idxC18] = array_merge($gR6C18['matches'][$idxC18], [
    'status' => 'em_andamento', 'minute' => 85, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 70, 'text' => "70' | Lesão: você deixa o campo."],
        ['minute' => 82, 'text' => "82' | Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['85-90'],
]);
unset($gR6C18['matches'][$idxC18]['participation_state']);
$gR6C18End = Game::command($gR6C18, 'advance');
$mC18 = $gR6C18End['matches'][$idxC18];
$presC18 = Presentation::playerParticipation($gR6C18End, $mC18);
f04Check(
    $mC18['stats']['minutes'] === 0
    && $gR6C18End['states']['ritmo_jogo'] === 50
    && $presC18['type'] === 'substituted'
    && $presC18['label'] === 'Substituído'
    && !MatchParticipation::finishedOnField($mC18),
    'R1-R6 [C.18]: dois logs formais com minutos distintos (70\' vs 82\') retornam 0\', ritmo intacto e apresentação sem minuto arbitrário'
);

// [C.19] Lesão estruturada vs expulsão formal no mesmo minuto (reprodução exata do Codex P1-02)
$gR6C19 = Game::create('R6 C19 Causa Lesao vs Expulsao', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6C19 = Game::command($gR6C19, 'handicap');
$gR6C19['stage'] = 'partida';
$gR6C19['states']['ritmo_jogo'] = 50;
$gR6C19['states']['confianca'] = 45;
$xpC19Before = array_sum(array_column($gR6C19['skills'], 'xp'));
$idxC19 = Game::currentIndex($gR6C19);
$gR6C19['matches'][$idxC19] = array_merge($gR6C19['matches'][$idxC19], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'exit_reason' => 'injury',
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
$c19ChoiceBlocked = false;
try { Game::command($gR6C19, 'choice', ['option' => 0], false, $rngAlwaysSuccess); } catch (InvalidArgumentException $e) { $c19ChoiceBlocked = true; }
$gR6C19End = Game::command($gR6C19, 'advance');
$mC19 = $gR6C19End['matches'][$idxC19];
$presC19 = Presentation::playerParticipation($gR6C19End, $mC19);
$narrC19 = NarrativeContext::values($gR6C19End);
$gPenC19 = $gR6C19End;
$stPenC19 = PenaltyShootout::start($gPenC19, $mC19);
$foulC19 = MatchDiscipline::recordFoul($gR6C19End, $mC19, 'minor');
f04Check(
    $c19ChoiceBlocked
    && $mC19['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT // preserva dado original sem inventar estado
    && $mC19['exit_reason'] === 'injury' // preserva dado original
    && $mC19['stats']['minutes'] === 0
    && $mC19['stats']['rating'] === 0
    && Game::summary($gR6C19End)['games'] === 0
    && $gR6C19End['states']['ritmo_jogo'] === 50
    && $gR6C19End['states']['confianca'] === 45
    && array_sum(array_column($gR6C19End['skills'], 'xp')) === $xpC19Before
    && !isset($narrC19['exit_reason'])
    && $presC19['type'] === 'off_field'
    && $presC19['label'] === 'Fora de campo'
    && !MatchParticipation::canActOnField($mC19)
    && !MatchParticipation::finishedOnField($mC19)
    && $stPenC19['protagonist_eligible'] === false
    && $foulC19['card'] === null && $foulC19['text'] === '',
    'R1-R6 [C.19]: conflito de causa (exit_reason=injury vs expulsão formal) preserva dados, zera minutagem/recompensas, omite exit_reason narrativo e usa apresentação neutra Fora de campo'
);

// [C.20] Substituição estruturada vs expulsão formal
$gR6C20 = Game::legacyCreate('R6 C20 Causa Sub vs Expulsao', 'São Paulo', 'SP', 'meia');
$gR6C20['stage'] = 'partida';
$gR6C20['states']['ritmo_jogo'] = 50;
$idxC20 = Game::currentIndex($gR6C20);
$gR6C20['matches'][$idxC20] = array_merge($gR6C20['matches'][$idxC20], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
    'entry' => 60, 'exit' => 75, 'sub_in_recorded' => true, 'sub_out_recorded' => true,
    'exit_reason' => 'tactical',
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Segundo cartão amarelo! Você está expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
$gR6C20End = Game::command($gR6C20, 'advance');
$mC20 = $gR6C20End['matches'][$idxC20];
$presC20 = Presentation::playerParticipation($gR6C20End, $mC20);
$narrC20 = NarrativeContext::values($gR6C20End);
f04Check(
    $mC20['stats']['minutes'] === 0
    && $gR6C20End['states']['ritmo_jogo'] === 50
    && !isset($narrC20['exit_reason'])
    && $presC20['type'] === 'off_field'
    && $presC20['label'] === 'Fora de campo'
    && !MatchParticipation::finishedOnField($mC20),
    'R1-R6 [C.20]: conflito de causa (substituição tática estruturada vs 2º amarelo formal) credita 0\', sem exit_reason e apresentação neutra Fora de campo'
);

// [C.21] Minuto de saída anterior à entrada (entry=60, saída formal aos 55')
$gR6C21 = Game::legacyCreate('R6 C21 Saida Antes Entrada', 'São Paulo', 'SP', 'meia');
$gR6C21['stage'] = 'partida';
$gR6C21['states']['ritmo_jogo'] = 50;
$idxC21 = Game::currentIndex($gR6C21);
$gR6C21['matches'][$idxC21] = array_merge($gR6C21['matches'][$idxC21], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 55, 'text' => "55' | Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6C21['matches'][$idxC21]['participation_state']);
$gR6C21End = Game::command($gR6C21, 'advance');
$mC21 = $gR6C21End['matches'][$idxC21];
$presC21 = Presentation::playerParticipation($gR6C21End, $mC21);
f04Check(
    $mC21['stats']['minutes'] === 0
    && $gR6C21End['states']['ritmo_jogo'] === 50
    && $presC21['label'] === 'Substituído'
    && !MatchParticipation::finishedOnField($mC21),
    'R1-R6 [C.21]: minuto de saída anterior à entrada (55\' < 60\') retorna 0\', sem recompensas e sem exibir minuto inválido'
);

// [C.22] Minuto fora dos limites válidos (120', -5', 0')
foreach ([
    'acima_limite_120' => ['minute' => 120, 'text' => "120' | Lesão: você deixa o campo."],
    'negativo_-5' => ['minute' => -5, 'text' => 'Lesão: você deixa o campo.'],
    'zero_0' => ['minute' => 0, 'text' => 'Lesão: você deixa o campo.'],
    'texto_130' => ['text' => "130' | Cartão vermelho direto! Você foi expulso da partida."],
] as $oobKey => $oobEntry) {
    $mOob = [
        'status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva',
        'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
        'log' => [
            ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
            $oobEntry,
        ],
    ];
    f04Check(
        MatchParticipation::playedMinutes($mOob) === 0
        && MatchParticipation::finishedOnField($mOob) === false,
        "R1-R6 [C.22:{$oobKey}]: minuto de saída fora dos limites válidos retorna 0' e bloqueia pênaltis"
    );
}

// [C.23] Minuto ausente no texto e presente no registro
$mC23 = [
    'status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => 'Lesão: você deixa o campo.'],
    ],
];
f04Check(
    MatchParticipation::playedMinutes($mC23) === 15
    && MatchParticipation::finishedOnField($mC23) === false,
    'R1-R6 [C.23]: minuto ausente no texto e presente no registro (minute=75) reconcilia 15\' corretamente'
);

// [C.24] Minuto presente no texto e ausente no registro
$mC24 = [
    'status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['text' => "75' | Cartão vermelho direto! Você foi expulso da partida."],
    ],
];
f04Check(
    MatchParticipation::playedMinutes($mC24) === 15
    && MatchParticipation::isSentOff($mC24) === true
    && MatchParticipation::finishedOnField($mC24) === false,
    'R1-R6 [C.24]: minuto presente no texto (75\') e ausente no registro reconcilia 15\' e expulsão corretamente'
);

// --- GRUPO D: INTEGRAÇÃO (25-33) ---
// [D.25] Entrada legítima aos 60' e saída formal aos 75' com validação completa de estatísticas, recompensas, placar, logs, áudio e apresentação
$gR6D25 = Game::create('R6 D25 Integracao 60-75', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6D25 = Game::command($gR6D25, 'handicap');
$gR6D25['stage'] = 'partida';
$gR6D25['states']['ritmo_jogo'] = 50;
$gR6D25['states']['confianca'] = 40;
$xpD25Before = array_sum(array_column($gR6D25['skills'], 'xp'));
$idxD25 = Game::currentIndex($gR6D25);
$gR6D25['matches'][$idxD25] = array_merge($gR6D25['matches'][$idxD25], [
    'status' => 'em_andamento', 'minute' => 80, 'hg' => 1, 'ag' => 0, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "75' | Disputa dura. Lesão: você deixa o campo."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6D25['matches'][$idxD25]['participation_state']);
TeamStrength::snapshot($gR6D25['matches'][$idxD25], $gR6D25);
MatchBalance::prepare($gR6D25['matches'][$idxD25], $gR6D25);
$gR6D25['progression_version'] = 2;
$gR6D25 = Game::command($gR6D25, 'advance');
$mD25 = $gR6D25['matches'][$idxD25];
$presD25 = Presentation::playerParticipation($gR6D25, $mD25);
$narrD25 = NarrativeContext::values($gR6D25);
$gPenD25 = $gR6D25;
$stPenD25 = PenaltyShootout::start($gPenD25, $mD25);
f04Check(
    $mD25['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $mD25['stats']['minutes'] === 15
    && $mD25['stats']['rating'] > 0
    && Game::summary($gR6D25)['games'] === 1
    && array_sum(array_column($gR6D25['skills'], 'xp')) > $xpD25Before
    && $gR6D25['states']['ritmo_jogo'] === 53
    && $presD25['label'] === 'Substituído aos 75′'
    && ($narrD25['exit_reason'] ?? null) === 'injury'
    && $stPenD25['protagonist_eligible'] === false,
    'R1-R6 [D.25]: integração entrada 60\' + lesão 75\' valida minutos, jogos, nota, XP, ritmo, apresentação, narrativa e inelegibilidade'
);

// [D.26] Titular permanecendo até o final mesmo com frase hipotética no log
$gR6D26 = Game::legacyCreate('R6 D26 Titular Integral', 'São Paulo', 'SP', 'meia');
$gR6D26['stage'] = 'partida';
$gR6D26['states']['energia'] = 90;
$gR6D26['states']['risco_lesao'] = 0;
$idxD26 = Game::currentIndex($gR6D26);
$gR6D26['matches'][$idxD26] = array_merge($gR6D26['matches'][$idxD26], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 0, 'exit' => 90, 'entered_on_field' => true,
    'log' => [['minute' => 70, 'text' => 'Se Você é substituído na próxima partida, o técnico muda a estratégia.']],
    'evaluated_penalties' => ['80-90'],
]);
$gR6D26 = Game::command($gR6D26, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
while ($gR6D26['stage'] === 'partida') {
    $gR6D26 = MatchParticipation::canActOnField($gR6D26['matches'][$idxD26])
        ? Game::command($gR6D26, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gR6D26, 'advance');
}
f04Check(
    $gR6D26['matches'][$idxD26]['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $gR6D26['matches'][$idxD26]['stats']['minutes'] === 90
    && MatchParticipation::finishedOnField($gR6D26['matches'][$idxD26]) === true,
    'R1-R6 [D.26]: titular integral com menção hipotética no log conclui FULL_TIME com 90\' e elegível para pênaltis'
);

// [D.27] Reserva que não entrou (mesmo com menção hipotética de entrada no log)
$gR6D27 = Game::legacyCreate('R6 D27 Reserva Nao Entrou', 'São Paulo', 'SP', 'meia');
$gR6D27['stage'] = 'partida';
$gR6D27['states']['ritmo_jogo'] = 50;
$idxD27 = Game::currentIndex($gR6D27);
$gR6D27['matches'][$idxD27] = array_merge($gR6D27['matches'][$idxD27], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'entry' => 60, 'exit' => 90,
    'log' => [['minute' => 65, 'text' => 'O técnico cogita: se Você entra em campo na próxima rodada, ajuda na marcação.']],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6D27['matches'][$idxD27]['participation_state']);
$gR6D27 = Game::command($gR6D27, 'advance');
f04Check(
    $gR6D27['matches'][$idxD27]['participation_state'] === MatchParticipation::STATE_ON_BENCH
    && $gR6D27['matches'][$idxD27]['stats']['minutes'] === 0
    && $gR6D27['states']['ritmo_jogo'] === 50
    && MatchParticipation::finishedOnField($gR6D27['matches'][$idxD27]) === false,
    'R1-R6 [D.27]: reserva não utilizado com menção hipotética de entrada permanece ON_BENCH com 0\', ritmo intacto e inelegível'
);

// [D.28] Save legado sem estado canônico com entrada formal e saída concatenada
$gR6D28 = Game::legacyCreate('R6 D28 Legado Concat', 'São Paulo', 'SP', 'meia');
$gR6D28['stage'] = 'partida';
$idxD28 = Game::currentIndex($gR6D28);
$gR6D28['matches'][$idxD28] = array_merge($gR6D28['matches'][$idxD28], [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'log' => [
        ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."],
        ['minute' => 75, 'text' => "Desarme: falhou. 75' | Segundo cartão amarelo! Você está expulso da partida."],
    ],
    'evaluated_penalties' => ['80-90'],
]);
unset($gR6D28['matches'][$idxD28]['participation_state']);
$gR6D28 = Game::command($gR6D28, 'advance');
$mD28 = $gR6D28['matches'][$idxD28];
f04Check(
    $mD28['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $mD28['stats']['minutes'] === 15
    && (NarrativeContext::values($gR6D28)['exit_reason'] ?? null) === 'red'
    && Presentation::playerParticipation($gR6D28, $mD28)['label'] === 'Expulso aos 75′',
    'R1-R6 [D.28]: save legado sem estado canônico reconcilia entrada formal e expulsão concatenada aos 75\' (15\', SENT_OFF, Expulso aos 75′)'
);

// [D.29] Expulsão real impede nova falta; pergunta hipotética NÃO impede nova falta
$gR6D29 = Game::legacyCreate('R6 D29 Disciplina', 'São Paulo', 'SP', 'volante');
$mD29Real = array_merge(Schedule::match(1, 'São Paulo', 'Santos'), [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
    'log' => [['minute' => 75, 'text' => "Desarme: falhou. 75' | Cartão vermelho direto! Você foi expulso da partida."]],
]);
$foulD29Real = MatchDiscipline::recordFoul($gR6D29, $mD29Real, 'minor');
$mD29Hyp = array_merge(Schedule::match(2, 'São Paulo', 'Santos'), [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
    'log' => [['minute' => 75, 'text' => 'O treinador pergunta: Você foi expulso da partida anterior?']],
]);
$foulD29Hyp = MatchDiscipline::recordFoul($gR6D29, $mD29Hyp, 'minor');
f04Check(
    $foulD29Real['text'] === '' && ($mD29Real['stats']['fouls'] ?? 0) === 0
    && $foulD29Hyp['text'] !== '' && ($mD29Hyp['stats']['fouls'] ?? 0) === 1,
    'R1-R6 [D.29]: expulsão formal real bloqueia nova falta enquanto pergunta hipotética permite falta normalmente'
);

// [D.30-D.31] Interface (Presentation + app/view.php) e narrativa coerentes em todas as causas e em conflito de causa
$htmlC19 = $renderCareerMatchView($gR6C19);
f04Check(
    str_contains($htmlC19, 'Fora de campo')
    && !str_contains($htmlC19, 'Expulso')
    && str_contains($htmlC19, 'class="match-advance-card panel"')
    && !str_contains($htmlC19, 'action="/partida/decidir"'),
    'R1-R6 [D.31]: app/view.php exibe estado neutro Fora de campo sem Expulso e sem botões de decisão em conflito de causa'
);

// [D.32] Reexecução idempotente do encerramento em conflito temporal e conflito de causa
$snapC17M = $gR6C17End['matches'][$idxC17];
$snapC17S = $gR6C17End['states'];
$snapC17L = $gR6C17End['reward_ledger'] ?? [];
$refSegment->invokeArgs(null, [&$gR6C17End, 90]);
$snapC19M = $gR6C19End['matches'][$idxC19];
$snapC19S = $gR6C19End['states'];
$snapC19L = $gR6C19End['reward_ledger'] ?? [];
$refSegment->invokeArgs(null, [&$gR6C19End, 90]);
f04Check(
    $gR6C17End['matches'][$idxC17] === $snapC17M
    && $gR6C17End['states'] === $snapC17S
    && ($gR6C17End['reward_ledger'] ?? []) === $snapC17L
    && $gR6C19End['matches'][$idxC19] === $snapC19M
    && $gR6C19End['states'] === $snapC19S
    && ($gR6C19End['reward_ledger'] ?? []) === $snapC19L,
    'R1-R6 [D.32]: reexecução do encerramento em conflito temporal e conflito de causa é estritamente idempotente'
);

// [D.33] Modos Normal, Rápido e Expresso preservam integridade de participação após R6
$gR6Norm = Game::create('R6 D33 Normal', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6Norm = Game::command($gR6Norm, 'handicap');
$gR6Norm['play_mode'] = 'normal';
$gR6Norm = Game::command($gR6Norm, 'choice', ['option' => 0]);
$gR6Norm = Game::command($gR6Norm, 'choice', ['option' => 0]);
$idxRN = Game::currentIndex($gR6Norm);
$normStepsR6 = 0;
while ($gR6Norm['stage'] === 'partida' && $normStepsR6 < 25) {
    $gR6Norm = MatchParticipation::canActOnField($gR6Norm['matches'][$idxRN])
        ? Game::command($gR6Norm, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
        : Game::command($gR6Norm, 'advance');
    $normStepsR6++;
}
$gR6Rap = Game::create('R6 D33 Rapido', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6Rap = Game::command($gR6Rap, 'handicap');
$gR6Rap['play_mode'] = 'rapido';
$gR6Rap = PlayMode::simulate($gR6Rap, 'equilibrio');
$gR6Exp = Game::create('R6 D33 Expresso', 'São Paulo', 'SP', 'meia', 'Santos-SP');
$gR6Exp = Game::command($gR6Exp, 'handicap');
$gR6Exp['play_mode'] = 'expresso';
$gR6Exp = PlayMode::simulate($gR6Exp, 'equilibrio');
f04Check(
    $gR6Norm['stage'] === 'fim_semana'
    && $gR6Norm['matches'][$idxRN]['status'] === 'finalizada'
    && !empty($gR6Rap['pace_report']['matches'])
    && !empty($gR6Exp['pace_report']['matches']),
    'R1-R6 [D.33]: modos Normal, Rápido e Expresso concluem integralmente preservando participação e estatísticas coerentes'
);

// =========================================================================
// 12. MATRIZ ADVERSARIAL R1-R7 (Procedência, Cronologia e Fechamento Disciplinar)
// =========================================================================
echo "\n12. Validando Matriz Adversarial R1-R7 (Procedência, Cronologia e Disciplina)...\n";

$r7RunToEnd = static function (array $g, int $idx) use ($rngAlwaysSuccess): array {
    for ($steps = 0; $steps < 30 && $g['stage'] === 'partida'; $steps++) {
        $g = MatchParticipation::canActOnField($g['matches'][$idx])
            ? Game::command($g, 'choice', ['option' => 0], false, $rngAlwaysSuccess)
            : Game::command($g, 'advance');
    }
    return $g;
};
$r7Game = static function (string $name, array $matchOverride, bool $keepState = false): array {
    $g = Game::legacyCreate($name, 'São Paulo', 'SP', 'meia');
    $g['stage'] = 'partida';
    $g['states']['energia'] = 90;
    $g['states']['risco_lesao'] = 0;
    $g['states']['risco_cartao'] = 0;
    $g['states']['ritmo_jogo'] = 50;
    $idx = Game::currentIndex($g);
    $g['matches'][$idx] = array_merge($g['matches'][$idx], $matchOverride);
    if (!$keepState && !array_key_exists('participation_state', $matchOverride)) {
        unset($g['matches'][$idx]['participation_state']);
    }
    return [$g, $idx];
};
$r7RhythmKey = static fn(array $g, array $m): string => 'f04:rhythm:' . $g['season'] . ':' . ($g['competition_id'] ?? 'legacy') . ':' . $m['round'] . ':' . $m['home'] . ':' . $m['away'];
$r7EntryLog = ['minute' => 60, 'text' => "60' | Você entra em campo no lugar de um companheiro."];
$r7OnFieldReserve = [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'entered_on_field' => true,
    'evaluated_penalties' => ['80-90'],
];

// Verificação integrada: todas as camadas devem concordar com a interpretação canônica do acontecimento
$r7Coherent = static function (array $g, int $idx, string $label): void {
    $m = $g['matches'][$idx];
    $r = MatchParticipation::resolveExit($m);
    $e = MatchParticipation::resolveEntry($m);
    $p = Presentation::playerParticipation($g, $m);
    $n = NarrativeContext::values($g);
    $c = MatchDiscipline::classifyExpulsion($m);
    $minutes = (int)($m['stats']['minutes'] ?? 0);
    $ok = true;
    if ($r['cause_conflict']) {
        $ok = $ok && $p['type'] === 'off_field' && !isset($n['exit_reason']) && $c['outcome'] === 'conflict'
            && $minutes === 0 && !MatchParticipation::isSentOff($m);
    } elseif ($r['kind'] === 'sent_off') {
        $ok = $ok && $p['type'] === 'sent_off' && ($n['exit_reason'] ?? null) === 'red' && MatchParticipation::isSentOff($m)
            && in_array($c['outcome'], ['direct_red', 'second_yellow', 'sent_off_unspecified', 'conflict'], true);
    } elseif ($r['status'] !== 'none') {
        $ok = $ok && $p['type'] === 'substituted' && ($n['exit_reason'] ?? null) === $r['kind'] && $c['outcome'] === 'none';
    } else {
        $ok = $ok && !isset($n['exit_reason']) && $c['outcome'] === 'none';
    }
    if ($r['status'] !== 'none') {
        $ok = $ok && !MatchParticipation::finishedOnField($m) && !MatchParticipation::canActOnField($m);
    }
    if ($r['status'] === 'conflict' || $e['status'] === 'conflict') {
        $ok = $ok && $minutes === 0 && (float)($m['stats']['rating'] ?? 0) === 0.0;
    }
    if (($m['status'] ?? '') === 'finalizada' && $r['status'] === 'valid' && $r['minute'] !== null && $e['status'] === 'valid') {
        $ok = $ok && $minutes === max(1, $r['minute'] - $e['minute']);
    }
    f04Check($ok, "R1-R7 [INTEGRADO] {$label}: estado, minutagem, disciplina, narrativa, apresentação e elegibilidade coerentes com a resolução canônica");
};

// ---------------- P1-01: PROCEDÊNCIA E MULTIPLICIDADE ----------------
// [P1-01.1-7] Prefixos sem procedência comprovada (hipótese, simulação, futuro, passado, pergunta, adversário, companheiro)
$r7Prefixed = [
    '1_hipotetico_codex' => "O treinador mostrou um exemplo hipotético. 75' | Cartão vermelho direto! Você foi expulso da partida.",
    '2_simulacao' => "Simulação de treino: o auxiliar encena o lance. 75' | Lesão: você deixa o campo.",
    '3_partida_futura' => "Na próxima partida, se repetir a entrada: 75' | Cartão vermelho direto! Você foi expulso da partida.",
    '4_partida_passada' => "Na partida anterior aconteceu isto: 75' | Segundo cartão amarelo! Você está expulso da partida.",
    '5_pergunta' => "Você lembra do que houve? 75' | Substituído por desgaste.",
    '6_adversario' => "O atacante adversário recebe o aviso do médico: Lesão: você deixa o campo.",
    '7_companheiro' => "O ponta companheiro ouve do técnico: 75' | Você é substituído após participar da partida.",
];
foreach ($r7Prefixed as $pfKey => $pfText) {
    [$gPf, $idxPf] = $r7Game('R7 Prefixo ' . $pfKey, $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 75, 'text' => $pfText]]], true);
    $mPf = $gPf['matches'][$idxPf];
    $rPf = MatchParticipation::resolveExit($mPf);
    $presPf = Presentation::playerParticipation($gPf, $mPf);
    $copyPf = $mPf;
    $foulPf = MatchDiscipline::recordFoul($gPf, $copyPf, 'minor');
    f04Check(
        $rPf['status'] === 'none'
        && MatchParticipation::canActOnField($mPf)
        && !MatchParticipation::isSentOff($mPf)
        && !isset(NarrativeContext::values($gPf)['exit_reason'])
        && $presPf['type'] === 'on_field'
        && $foulPf['text'] !== '',
        "R1-R7 [P1-01.{$pfKey}]: prefixo sem procedência não promove evento formal (permite escolha, sem expulsão, lesão ou bloqueio disciplinar)"
    );
    $gPfEnd = $r7RunToEnd($gPf, $idxPf);
    $mPfEnd = $gPfEnd['matches'][$idxPf];
    $gPenPf = $gPfEnd;
    $stPenPf = PenaltyShootout::start($gPenPf, $mPfEnd);
    f04Check(
        $mPfEnd['participation_state'] === MatchParticipation::STATE_FULL_TIME
        && $mPfEnd['stats']['minutes'] === 30
        && $mPfEnd['stats']['rating'] > 0
        && ($gPfEnd['reward_ledger'][$r7RhythmKey($gPfEnd, $mPfEnd)]['delta'] ?? 0) === 3
        && MatchParticipation::finishedOnField($mPfEnd)
        && $stPenPf['protagonist_eligible'] === true
        && ($gPfEnd['disciplinary']['legacy']['regulatory_status'] ?? 'regular') === 'regular',
        "R1-R7 [P1-01.{$pfKey}]: encerramento preserva FULL_TIME, 30', nota, ritmo, disciplina regular e elegibilidade para pênaltis"
    );
}

// [P1-01.8/14] Prefixo legítimo produzido pelo motor real (Game::command choice) com saída por desgaste
[$gReal, $idxReal] = $r7Game('R7 Composto Real', [
    'status' => 'em_andamento', 'minute' => 70, 'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 0, 'exit' => 90, 'entered_on_field' => true, 'evaluated_penalties' => ['70-80', '80-90'],
], true);
$gReal['states']['energia'] = 10;
$gReal = Game::command($gReal, 'choice', ['option' => 0], false, $rngAlwaysSuccess);
$mReal = $gReal['matches'][$idxReal];
$realLine = '';
foreach ($mReal['log'] as $lr) {
    if (str_ends_with((string)($lr['text'] ?? ''), 'Substituído por desgaste.')) { $realLine = (string)$lr['text']; }
}
$rReal = MatchParticipation::resolveExit($mReal);
f04Check(
    $realLine !== '' && $realLine !== 'Substituído por desgaste.'
    && $rReal['status'] === 'valid' && $rReal['kind'] === 'fatigue' && $rReal['minute'] === 70
    && $mReal['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && !MatchParticipation::canActOnField($mReal),
    'R1-R7 [P1-01.8/14]: mensagem composta realmente emitida por Game::command(choice) é reconhecida (desgaste aos 70\', SUBSTITUTED_OUT)'
);
$gRealEnd = $r7RunToEnd($gReal, $idxReal);
f04Check($gRealEnd['matches'][$idxReal]['stats']['minutes'] === 70, 'R1-R7 [P1-01.8/14]: composto real encerra com 70\' contabilizados');
$r7Coherent($gRealEnd, $idxReal, 'composto real por desgaste');

// Mesma linha real em save legado (sem estado canônico), mantendo apenas o horário estruturado gravado pelo motor-base (exit=70)
[$gRealLegacy, $idxRL] = $r7Game('R7 Composto Real Legado', [
    'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular', 'entry' => 0, 'exit' => 70,
    'log' => [['minute' => 70, 'text' => $realLine]],
]);
$rRL = MatchParticipation::resolveExit($gRealLegacy['matches'][$idxRL]);
f04Check(
    $rRL['status'] === 'valid' && $rRL['kind'] === 'fatigue' && $rRL['minute'] === 70
    && MatchParticipation::playedMinutes($gRealLegacy['matches'][$idxRL]) === 70,
    'R1-R7 [P1-01.8]: linha composta legada corroborada pelo horário estruturado do motor (exit=70) reconhece desgaste aos 70\''
);
// Texto arbitrário semelhante ao formato oficial sem procedência nem gramática de jogada: não promove saída
[$gRealOrphan, $idxRO] = $r7Game('R7 Composto Arbitrario', [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'titular', 'entry' => 0, 'exit' => 90,
    'log' => [['minute' => 70, 'text' => 'Relato da comissão sobre o cansaço do elenco. Substituído por desgaste.']], 'evaluated_penalties' => ['80-90'],
]);
f04Check(
    MatchParticipation::resolveExit($gRealOrphan['matches'][$idxRO])['status'] === 'none'
    && MatchParticipation::canActOnField($gRealOrphan['matches'][$idxRO]),
    'R1-R7 [P1-01.8]: texto arbitrário semelhante ao formato oficial sem procedência é tratado como relato (não bloqueia)'
);

// [P1-01.9] Duas saídas distintas na mesma linha (reprodução Codex B) e variante com prefixo real do motor
foreach ([
    'codex_literal' => "Desarme: falhou. 75' | Cartão vermelho direto! Você foi expulso da partida. Lesão: você deixa o campo.",
    'prefixo_real' => rtrim(substr($realLine, 0, (int)strrpos($realLine, ' Substituído por desgaste.'))) . " 75' | Cartão vermelho direto! Você foi expulso da partida. Lesão: você deixa o campo.",
    'sem_prefixo' => "75' | Lesão: você deixa o campo. Substituído por desgaste.",
] as $dkKey => $dkText) {
    [$gDk, $idxDk] = $r7Game('R7 Duas Causas ' . $dkKey, $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 75, 'text' => $dkText]]]);
    $rDk = MatchParticipation::resolveExit($gDk['matches'][$idxDk]);
    $choiceDk = false;
    try { Game::command($gDk, 'choice', ['option' => 0], false, $rngAlwaysSuccess); } catch (InvalidArgumentException $e) { $choiceDk = true; }
    $gDkEnd = $r7RunToEnd($gDk, $idxDk);
    $mDkEnd = $gDkEnd['matches'][$idxDk];
    f04Check(
        $rDk['status'] === 'conflict' && $rDk['cause_conflict'] && $rDk['kind'] === null
        && $choiceDk
        && $mDkEnd['stats']['minutes'] === 0
        && $gDkEnd['states']['ritmo_jogo'] === 50
        && Presentation::playerParticipation($gDkEnd, $mDkEnd)['type'] === 'off_field'
        && ($gDkEnd['disciplinary']['legacy']['regulatory_status'] ?? 'regular') === 'regular'
        && (int)($gDkEnd['disciplinary']['legacy']['pending_suspension'] ?? 0) === 0,
        "R1-R7 [P1-01.9:{$dkKey}]: duas causas incompatíveis na mesma linha sinalizam conflito sem escolher causa (0', sem punição, apresentação neutra)"
    );
    $r7Coherent($gDkEnd, $idxDk, "duas causas {$dkKey}");
}

// [P1-01.10] Duas saídas idênticas na mesma linha são consolidadas
[$gSame, $idxSame] = $r7Game('R7 Duplicada Linha', $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo. 75' | Lesão: você deixa o campo."]]]);
$gSameEnd = $r7RunToEnd($gSame, $idxSame);
f04Check(
    MatchParticipation::resolveExit($gSameEnd['matches'][$idxSame])['kind'] === 'injury'
    && $gSameEnd['matches'][$idxSame]['stats']['minutes'] === 15
    && $gSameEnd['states']['ritmo_jogo'] === 53,
    'R1-R7 [P1-01.10]: duplicação idêntica na mesma linha é consolidada (lesão aos 75\', 15\', ritmo +3)'
);
$r7Coherent($gSameEnd, $idxSame, 'duplicata consolidada');

// [P1-01.11] Saídas com horários diferentes na mesma linha
[$gTwoMin, $idxTM] = $r7Game('R7 Dois Minutos Linha', $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 70, 'text' => "70' | Lesão: você deixa o campo. 82' | Lesão: você deixa o campo."]]]);
$gTwoMinEnd = $r7RunToEnd($gTwoMin, $idxTM);
$rTM = MatchParticipation::resolveExit($gTwoMinEnd['matches'][$idxTM]);
f04Check(
    $rTM['status'] === 'conflict' && $rTM['time_conflict'] && $rTM['kind'] === 'injury'
    && $gTwoMinEnd['matches'][$idxTM]['stats']['minutes'] === 0
    && Presentation::playerParticipation($gTwoMinEnd, $gTwoMinEnd['matches'][$idxTM])['label'] === 'Substituído',
    'R1-R7 [P1-01.11]: horários incompatíveis na mesma linha sinalizam conflito temporal (0\' e sem minuto arbitrário)'
);
$r7Coherent($gTwoMinEnd, $idxTM, 'dois horários na mesma linha');

// [P1-01.12] Texto adicional depois do evento formal (formato nunca emitido pelo motor)
[$gTail, $idxTail] = $r7Game('R7 Texto Posterior', $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida. Mas era apenas um ensaio."]]], true);
$gTailEnd = $r7RunToEnd($gTail, $idxTail);
f04Check(
    $gTailEnd['matches'][$idxTail]['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $gTailEnd['matches'][$idxTail]['stats']['minutes'] === 30
    && MatchParticipation::finishedOnField($gTailEnd['matches'][$idxTail]),
    'R1-R7 [P1-01.12]: texto adicional após a sentença oficial não promove evento formal (FULL_TIME, 30\')'
);

// [P1-01.13] Mensagem formal isolada continua reconhecida
[$gIso, $idxIso] = $r7Game('R7 Isolada', $r7OnFieldReserve + ['log' => [$r7EntryLog, ['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida."]]]);
$gIsoEnd = $r7RunToEnd($gIso, $idxIso);
f04Check(
    $gIsoEnd['matches'][$idxIso]['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $gIsoEnd['matches'][$idxIso]['stats']['minutes'] === 15
    && MatchDiscipline::classifyExpulsion($gIsoEnd['matches'][$idxIso])['outcome'] === 'direct_red',
    'R1-R7 [P1-01.13]: mensagem formal isolada de vermelho direto continua reconhecida (SENT_OFF, 15\', subtipo direct_red)'
);
$r7Coherent($gIsoEnd, $idxIso, 'vermelho isolado');

// ---------------- P1-02: CRONOLOGIA E ENTRADA EFETIVA ----------------
// [P1-02.1] Relógio 70, saída registrada para 75: não retira o protagonista antecipadamente
[$gFut, $idxFut] = $r7Game('R7 Saida Futura', array_merge($r7OnFieldReserve, ['minute' => 70, 'evaluated_penalties' => ['70-80', '80-90'], 'log' => [$r7EntryLog, ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."]]]), true);
$mFut = $gFut['matches'][$idxFut];
$rFut = MatchParticipation::resolveExit($mFut);
f04Check(
    $rFut['status'] === 'none' && $rFut['pending_future'] === 1
    && MatchParticipation::canActOnField($mFut)
    && Presentation::playerParticipation($gFut, $mFut)['type'] === 'on_field'
    && !isset(NarrativeContext::values($gFut)['exit_reason']),
    'R1-R7 [P1-02.1]: saída registrada para 75\' com relógio em 70\' não produz efeito antecipado (pendente, atuação permitida)'
);
$gFutEnd = $r7RunToEnd($gFut, $idxFut);
f04Check(
    $gFutEnd['matches'][$idxFut]['participation_state'] === MatchParticipation::STATE_SUBSTITUTED_OUT
    && $gFutEnd['matches'][$idxFut]['stats']['minutes'] === 15,
    'R1-R7 [P1-02.1]: após o relógio alcançar o minuto informado, a saída passa a valer (SUBSTITUTED_OUT, 15\')'
);
$r7Coherent($gFutEnd, $idxFut, 'saída futura aplicada no minuto correto');

// [P1-02.2/3] Relógio 75 e 80 com saída aos 75
foreach ([75, 80] as $clk) {
    [$gNow, $idxNow] = $r7Game('R7 Relogio ' . $clk, array_merge($r7OnFieldReserve, ['minute' => $clk, 'log' => [$r7EntryLog, ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."]]]), true);
    $rNow = MatchParticipation::resolveExit($gNow['matches'][$idxNow]);
    f04Check(
        $rNow['status'] === 'valid' && $rNow['kind'] === 'injury' && $rNow['minute'] === 75
        && !MatchParticipation::canActOnField($gNow['matches'][$idxNow]),
        "R1-R7 [P1-02." . ($clk === 75 ? '2' : '3') . "]: relógio {$clk}' com saída aos 75' aplica a saída e bloqueia atuação"
    );
}

// [P1-02.4/9] Janela planejada 60-90 e única entrada formal aos 75 (save legado), encerramento aos 90
[$gEnt75, $idxE75] = $r7Game('R7 Entrada 75', [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'log' => [['minute' => 75, 'text' => "75' | Você entra em campo no lugar de um companheiro."]], 'evaluated_penalties' => ['80-90'],
]);
$eE75 = MatchParticipation::resolveEntry($gEnt75['matches'][$idxE75]);
$gEnt75End = $r7RunToEnd($gEnt75, $idxE75);
$mE75 = $gEnt75End['matches'][$idxE75];
$gPenE75 = $gEnt75End;
$stPenE75 = PenaltyShootout::start($gPenE75, $mE75);
f04Check(
    $eE75['status'] === 'valid' && $eE75['minute'] === 75
    && $mE75['participation_state'] === MatchParticipation::STATE_FULL_TIME
    && $mE75['stats']['minutes'] === 15
    && $mE75['stats']['rating'] > 0
    && ($gEnt75End['reward_ledger'][$r7RhythmKey($gEnt75End, $mE75)]['minutes'] ?? 0) === 15
    && MatchParticipation::finishedOnField($mE75)
    && $stPenE75['protagonist_eligible'] === true,
    'R1-R7 [P1-02.4/9]: entrada real aos 75\' (janela planejada 60-90) contabiliza 15\' e não 30\', com FULL_TIME e elegibilidade'
);
$r7Coherent($gEnt75End, $idxE75, 'entrada efetiva aos 75');
// Entrada aos 75 ainda futura com relógio em 70: não habilita atuação antes de ocorrer
[$gEntFut, $idxEF] = $r7Game('R7 Entrada Futura', [
    'status' => 'em_andamento', 'minute' => 70, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'log' => [['minute' => 75, 'text' => "75' | Você entra em campo no lugar de um companheiro."]],
]);
f04Check(
    MatchParticipation::resolveEntry($gEntFut['matches'][$idxEF])['status'] === 'none'
    && !MatchParticipation::canActOnField($gEntFut['matches'][$idxEF]),
    'R1-R7 [P1-02.4]: entrada registrada para 75\' com relógio em 70\' não habilita atuação antecipada'
);

// [P1-02.5] Entrada planejada e confirmada aos 60 (estrutura + log concordantes)
$mE60 = ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'sub_in_recorded' => true, 'participation_state' => MatchParticipation::STATE_FULL_TIME, 'log' => [$r7EntryLog]];
f04Check(MatchParticipation::resolveEntry($mE60)['minute'] === 60 && MatchParticipation::playedMinutes($mE60) === 30, 'R1-R7 [P1-02.5]: entrada confirmada aos 60\' por estrutura e log concordantes contabiliza 30\'');

// [P1-02.6] Entrada estruturada aos 60 versus log formal aos 75: conflito explícito, sem escolher a maior minutagem
[$gEntC, $idxEC] = $r7Game('R7 Entrada Conflito', [
    'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90,
    'participation_state' => MatchParticipation::STATE_ON_FIELD, 'sub_in_recorded' => true,
    'log' => [['minute' => 75, 'text' => "75' | Você entra em campo no lugar de um companheiro."]], 'evaluated_penalties' => ['80-90'],
], true);
$eEC = MatchParticipation::resolveEntry($gEntC['matches'][$idxEC]);
$gEntCEnd = $r7RunToEnd($gEntC, $idxEC);
f04Check(
    $eEC['status'] === 'conflict'
    && !MatchParticipation::canActOnField($gEntC['matches'][$idxEC])
    && $gEntCEnd['matches'][$idxEC]['stats']['minutes'] === 0
    && $gEntCEnd['states']['ritmo_jogo'] === 50
    && !MatchParticipation::finishedOnField($gEntCEnd['matches'][$idxEC]),
    'R1-R7 [P1-02.6]: entrada estruturada 60\' vs log 75\' gera conflito explícito (0\', sem ritmo, sem pênaltis, sem atuação)'
);
$r7Coherent($gEntCEnd, $idxEC, 'conflito de entrada');

// [P1-02.7] Entrada posterior à saída
$mEntAfter = ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'log' => [
    ['minute' => 80, 'text' => "80' | Você entra em campo no lugar de um companheiro."],
    ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."],
]];
$rEA = MatchParticipation::resolveExit($mEntAfter);
f04Check($rEA['status'] === 'conflict' && $rEA['time_conflict'] && MatchParticipation::playedMinutes($mEntAfter) === 0, 'R1-R7 [P1-02.7]: saída (75\') anterior à entrada efetiva (80\') é inválida e retorna 0\'');

// [P1-02.8] Entrada e saída no mesmo minuto
$mSameMin = ['status' => 'finalizada', 'minute' => 90, 'lineup' => 'reserva', 'entry' => 60, 'exit' => 90, 'log' => [
    ['minute' => 75, 'text' => "75' | Você entra em campo no lugar de um companheiro."],
    ['minute' => 75, 'text' => "75' | Lesão: você deixa o campo."],
]];
f04Check(MatchParticipation::playedMinutes($mSameMin) === 1, 'R1-R7 [P1-02.8]: entrada e saída comprovadas no mesmo minuto registram o piso de 1\'');

// [P1-02.10/11] Saída aos 90, 92 e 93
foreach ([90 => 30, 92 => 32, 93 => 33] as $exMin => $expMin) {
    $mEx = ['status' => 'finalizada', 'minute' => 93, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT,
        'entry' => 60, 'exit' => $exMin, 'sub_in_recorded' => true, 'sub_out_recorded' => true,
        'log' => [$r7EntryLog, ['minute' => $exMin, 'text' => "{$exMin}' | Você é substituído após participar da partida."]]];
    f04Check(MatchParticipation::playedMinutes($mEx) === $expMin, "R1-R7 [P1-02.10/11]: saída formal aos {$exMin}' contabiliza {$expMin}'");
}
// Saída aos 92 registrada enquanto o relógio está em 90 (em andamento) permanece pendente
$mEx92Fut = ['status' => 'em_andamento', 'minute' => 90, 'lineup' => 'reserva', 'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 60, 'exit' => 93, 'sub_in_recorded' => true, 'log' => [$r7EntryLog, ['minute' => 92, 'text' => "92' | Você é substituído após participar da partida."]]];
f04Check(MatchParticipation::resolveExit($mEx92Fut)['status'] === 'none' && MatchParticipation::canActOnField($mEx92Fut), 'R1-R7 [P1-02.11]: saída aos 92\' com relógio em 90\' não produz efeito antecipado');

// [P1-02.12] Save sem escalação canônica (lineup desconhecido) com log de entrada não fabrica atuação
$mNoLineup = ['status' => 'finalizada', 'minute' => 90, 'lineup' => '', 'entry' => 60, 'exit' => 90, 'log' => [['minute' => 75, 'text' => "75' | Você entra em campo no lugar de um companheiro."]]];
f04Check(MatchParticipation::playedMinutes($mNoLineup) === 0 && !MatchParticipation::finishedOnField($mNoLineup), 'R1-R7 [P1-02.12]: escalação desconhecida com log de entrada não fabrica minutagem nem elegibilidade');

// [P1-02.13] Titulares e reservas criados pelo fluxo real do motor (pré-jogo -> partida -> encerramento)
$realFlowOk = true;
$realFlowSeen = [];
for ($rf = 0; $rf < 8; $rf++) {
    $gRF = Game::create('R7 Fluxo Real ' . $rf, 'São Paulo', 'SP', 'meia', 'Santos-SP');
    $gRF = Game::command($gRF, 'handicap');
    if ($rf % 2 === 1) { $gRF['round'] = 4; }
    $gRF = Game::command($gRF, 'choice', ['option' => 0]);
    $gRF['states']['energia'] = 90;
    $gRF['states']['risco_lesao'] = 0;
    $gRF = Game::command($gRF, 'choice', ['option' => 0]);
    if ($gRF['stage'] !== 'partida') { continue; }
    $idxRF = Game::currentIndex($gRF);
    $gRF = $r7RunToEnd($gRF, $idxRF);
    $mRF = $gRF['matches'][$idxRF];
    $realFlowSeen[$mRF['participation_state'] ?? 'null'] = true;
    $eRF = MatchParticipation::resolveEntry($mRF);
    $rRF = MatchParticipation::resolveExit($mRF);
    $expRF = match ($mRF['participation_state'] ?? '') {
        MatchParticipation::STATE_FULL_TIME => (int)$mRF['exit'] - (int)$eRF['minute'],
        MatchParticipation::STATE_SUBSTITUTED_OUT, MatchParticipation::STATE_SENT_OFF => max(1, (int)$rRF['minute'] - (int)$eRF['minute']),
        default => 0,
    };
    if ($mRF['stats']['minutes'] !== $expRF || $eRF['status'] === 'conflict' || $rRF['status'] === 'conflict') { $realFlowOk = false; }
    $r7Coherent($gRF, $idxRF, 'fluxo real ' . $rf . ' (' . ($mRF['participation_state'] ?? 'null') . ')');
}
f04Check($realFlowOk && count($realFlowSeen) >= 1, 'R1-R7 [P1-02.13]: titulares e reservas do fluxo real do motor mantêm minutagem igual ao intervalo comprovado (sem conflitos espúrios)');

// ---------------- P1-03: FECHAMENTO DISCIPLINAR (sp-a3) ----------------
$r7DiscMatch = static function (array $over): array {
    return array_merge(Schedule::match(5, 'São Paulo', 'Santos'), [
        'competition' => 'sp-a3', 'status' => 'finalizada', 'minute' => 90, 'lineup' => 'titular',
        'entry' => 0, 'exit' => 90, 'entered_on_field' => true,
    ], $over);
};
$r7DiscRun = static function (array $m): array {
    $g = ['disciplinary' => [], 'competition_id' => 'sp-a3'];
    MatchDiscipline::processMatchEnd($g, $m);
    $first = $g['disciplinary']['sp-a3'];
    $mFirst = $m;
    MatchDiscipline::processMatchEnd($g, $m);
    MatchDiscipline::processMatchEnd($g, $m);
    return ['disc' => $first, 'idempotent' => $g['disciplinary']['sp-a3'] === $first && $m === $mFirst, 'class' => MatchDiscipline::classifyExpulsion($m)];
};
$redLog = ['minute' => 75, 'text' => "75' | Cartão vermelho direto! Você foi expulso da partida."];
$secondLog = ['minute' => 75, 'text' => "75' | Segundo cartão amarelo! Você está expulso da partida."];
$redStruct = ['participation_state' => MatchParticipation::STATE_SENT_OFF, 'exit_reason' => 'sent_off', 'exit' => 75, 'red_card_minute' => 75, 'stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1])];
$secondStruct = ['participation_state' => MatchParticipation::STATE_SENT_OFF, 'exit_reason' => 'sent_off', 'exit' => 75, 'red_card_minute' => 75, 'stats' => array_merge(Schedule::stats(), ['yellow_cards' => 2, 'second_yellow' => 1, 'red_cards' => 1, 'cards' => 2])];
$discCases = [
    '1_direto_estruturado' => [$redStruct, 'direct_red'],
    '2_direto_log' => [['log' => [$redLog]], 'direct_red'],
    '3_direto_ambos' => [$redStruct + ['log' => [$redLog]], 'direct_red'],
    '4_segundo_estruturado' => [$secondStruct, 'second_yellow'],
    '5_segundo_log' => [['log' => [$secondLog]], 'second_yellow'],
    '6_sem_subtipo' => [['participation_state' => MatchParticipation::STATE_SENT_OFF, 'exit' => 75, 'red_card_minute' => 75], 'sent_off_unspecified'],
    '7_conflito_lesao_vermelho' => [['participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'exit_reason' => 'injury', 'exit' => 75, 'red_card_minute' => 75, 'stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1])], 'conflict'],
    '8_conflito_direto_segundo' => [['stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1]), 'red_card_minute' => 75, 'exit' => 75, 'log' => [$secondLog]], 'conflict'],
    '9_adversario' => [['log' => [['minute' => 75, 'text' => "75' | Cartão vermelho direto! O zagueiro adversário foi expulso."]]], 'none'],
    '10_amarelo_isolado' => [['stats' => array_merge(Schedule::stats(), ['yellow_cards' => 1, 'cards' => 1])], 'none'],
];
$discResults = [];
foreach ($discCases as $dcKey => [$dcOver, $dcExpected]) {
    $dcRes = $r7DiscRun($r7DiscMatch($dcOver));
    $discResults[$dcKey] = $dcRes;
    $statusOk = match ($dcExpected) {
        'direct_red', 'second_yellow', 'sent_off_unspecified' => $dcRes['disc']['regulatory_status'] === 'no_rule_recorded' && (int)$dcRes['disc']['pending_suspension'] === 0,
        'conflict' => $dcRes['disc']['regulatory_status'] === 'regular' && (int)$dcRes['disc']['pending_suspension'] === 0 && (int)$dcRes['disc']['yellow_accumulated'] === 0,
        default => $dcRes['disc']['regulatory_status'] === 'regular' && (int)$dcRes['disc']['pending_suspension'] === 0,
    };
    f04Check(
        $dcRes['class']['outcome'] === $dcExpected && $statusOk && $dcRes['idempotent'],
        "R1-R7 [P1-03.{$dcKey}]: sp-a3 classifica '{$dcExpected}', aplica fechamento coerente sem inventar suspensão e é idempotente em encerramentos repetidos"
    );
}
f04Check(
    $discResults['1_direto_estruturado']['disc'] === $discResults['2_direto_log']['disc']
    && $discResults['2_direto_log']['disc'] === $discResults['3_direto_ambos']['disc']
    && $discResults['4_segundo_estruturado']['disc'] === $discResults['5_segundo_log']['disc'],
    'R1-R7 [P1-03]: o mesmo vermelho direto (ou 2º amarelo) produz o mesmo resultado disciplinar em log, campos estruturados ou ambos'
);
f04Check(
    (int)$discResults['10_amarelo_isolado']['disc']['yellow_accumulated'] === 1,
    'R1-R7 [P1-03.10]: cartão amarelo isolado segue o acúmulo regulamentar sem expulsão'
);

// [P1-03.11] Competição com regra disciplinar documentada (catálogo de referência isolado, sem alterar regulamentos reais)
$discRuled = Competition::referenceScope(
    ['sp-a3' => ['disciplinary' => ['direct_red_suspension' => 2, 'second_yellow_suspension' => 1]]],
    static function () use ($r7DiscRun, $r7DiscMatch, $redStruct, $redLog, $secondLog): array {
        return [
            'struct' => $r7DiscRun($r7DiscMatch($redStruct)),
            'log' => $r7DiscRun($r7DiscMatch(['log' => [$redLog]])),
            'second' => $r7DiscRun($r7DiscMatch(['log' => [$secondLog]])),
            'unspec' => $r7DiscRun($r7DiscMatch(['participation_state' => MatchParticipation::STATE_SENT_OFF, 'exit' => 75, 'red_card_minute' => 75])),
            'conflict' => $r7DiscRun($r7DiscMatch(['participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'exit_reason' => 'injury', 'exit' => 75, 'log' => [$redLog]])),
        ];
    }
);
f04Check(
    $discRuled['struct']['disc'] === $discRuled['log']['disc']
    && $discRuled['log']['disc']['regulatory_status'] === 'suspended' && (int)$discRuled['log']['disc']['pending_suspension'] === 2
    && (int)$discRuled['second']['disc']['pending_suspension'] === 1
    && (int)$discRuled['unspec']['disc']['pending_suspension'] === 1
    && $discRuled['conflict']['disc']['regulatory_status'] === 'regular' && (int)$discRuled['conflict']['disc']['pending_suspension'] === 0
    && MatchDiscipline::isSuspended(['disciplinary' => ['sp-a3' => $discRuled['log']['disc']]], 'sp-a3'),
    'R1-R7 [P1-03.11]: com regra documentada, vermelho direto (log ou estrutura) suspende 2 jogos, 2º amarelo 1, sem subtipo aplica o mínimo regulamentar comum e conflito não pune'
);

// [P1-03] Reprodução Codex por fluxo real em sp-a3: vermelho apenas em log versus apenas estruturado
$r7DiscFlow = static function (array $over) use ($r7Game): array {
    [$g, $idx] = $r7Game('R7 Disc Fluxo', array_merge([
        'competition' => 'sp-a3', 'status' => 'em_andamento', 'minute' => 80, 'lineup' => 'titular',
        'entry' => 0, 'exit' => 90, 'entered_on_field' => true, 'evaluated_penalties' => ['80-90'],
    ], $over));
    $g['competition_id'] = 'sp-a3';
    $g = Game::command($g, 'advance');
    return [$g, $idx];
};
[$gDfLog, $idxDfL] = $r7DiscFlow(['log' => [$redLog]]);
[$gDfStr, $idxDfS] = $r7DiscFlow(['red_card_minute' => 75, 'stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1])]);
[$gDfConf, $idxDfC] = $r7DiscFlow(['participation_state' => MatchParticipation::STATE_SUBSTITUTED_OUT, 'exit_reason' => 'injury', 'exit' => 75, 'red_card_minute' => 75, 'stats' => array_merge(Schedule::stats(), ['direct_red' => 1, 'red_cards' => 1, 'cards' => 1])]);
f04Check(
    $gDfLog['matches'][$idxDfL]['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $gDfStr['matches'][$idxDfS]['participation_state'] === MatchParticipation::STATE_SENT_OFF
    && $gDfLog['matches'][$idxDfL]['stats']['minutes'] === 75
    && $gDfStr['matches'][$idxDfS]['stats']['minutes'] === 75
    && $gDfLog['disciplinary']['sp-a3'] === $gDfStr['disciplinary']['sp-a3']
    && $gDfLog['disciplinary']['sp-a3']['regulatory_status'] === 'no_rule_recorded'
    && $gDfConf['disciplinary']['sp-a3']['regulatory_status'] === 'regular'
    && (int)$gDfConf['disciplinary']['sp-a3']['pending_suspension'] === 0,
    'R1-R7 [P1-03 Codex]: em sp-a3, vermelho apenas em log e apenas estruturado produzem o mesmo fechamento; conflito lesão x vermelho não pune'
);
$r7Coherent($gDfLog, $idxDfL, 'sp-a3 vermelho em log');
$r7Coherent($gDfStr, $idxDfS, 'sp-a3 vermelho estruturado');
$r7Coherent($gDfConf, $idxDfC, 'sp-a3 conflito lesão x vermelho');

echo "\n=======================================================\n";
echo "TODOS OS {$checks} TESTES DA MISSÃO R10-LOTE02-F04-R1-R7 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
