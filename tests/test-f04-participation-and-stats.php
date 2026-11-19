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

$gNarrPrec = $gNarrDirect;
$gNarrPrec['matches'][$idxN]['exit_reason'] = 'injury';
f04Check((NarrativeContext::values($gNarrPrec)['exit_reason'] ?? null) === 'injury', 'R1-R2 [14]: exit_reason estruturado mantém precedência sobre fallback de log');

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

// 8.15 (P1 + F03) Compatibilidade com F03: acréscimos aos 93' em ON_FIELD permanecem coerentes entre motor, narrativa e view.php
$gF03Stop = Game::legacyCreate('F03 Acrescimos 93 R3', 'São Paulo', 'SP', 'meia');
$gF03Stop['stage'] = 'partida';
$idxF93 = Game::currentIndex($gF03Stop);
$gF03Stop['matches'][$idxF93] = array_merge($gF03Stop['matches'][$idxF93], [
    'status' => 'em_andamento',
    'minute' => 93,
    'lineup' => 'titular',
    'participation_state' => MatchParticipation::STATE_ON_FIELD,
    'entry' => 0,
    'exit' => 90,
]);
$partF93 = Presentation::playerParticipation($gF03Stop, $gF03Stop['matches'][$idxF93]);
$htmlF93 = $renderCareerMatchView($gF03Stop);
f04Check(
    MatchParticipation::canActOnField($gF03Stop['matches'][$idxF93]) === true
    && NarrativeContext::values($gF03Stop)['on_field'] === true
    && $partF93['type'] === 'on_field'
    && $partF93['label'] === 'Em campo'
    && str_contains($htmlF93, 'name="option"')
    && !str_contains($htmlF93, 'class="match-advance-card panel"'),
    'R1-R3 [15]: compatibilidade F03 preservada aos 93\' em andamento com STATE_ON_FIELD (motor, narrativa, Presentation e view.php coerentes)'
);

echo "\n=======================================================\n";
echo "TODOS OS {$checks} TESTES DA MISSÃO R10-LOTE02-F04-R1-R3 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
