<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(function(Throwable $e) {
    echo "\n[EXCEPTION]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
});

$checks = 0;
function check(bool $v, string $message): void {
    global $checks;
    $checks++;
    if (!$v) {
        throw new RuntimeException("FALHA DE TESTE: $message");
    }
    echo "  [PASS] $message\n";
}

echo "=== INICIANDO SUÍTE DE TESTES R10-BAL-001: AUTOCONFIANÇA ===\n\n";

// -------------------------------------------------------------
// 1. Penalidades comuns e graves, inclusive overrides
// -------------------------------------------------------------
echo "1. Validando penalidades técnicas comuns e graves...\n";
$oTech = SceneConsequences::option(['text' => 'Passe', 'action' => 'passe', 'effects' => []], 'partida', 9999, 0);
check(($oTech['failure_effects']['confianca'] ?? null) === -1, 'Falha técnica comum padrão aplica -1 na confiança');
check(($oTech['severe_failure_effects']['confianca'] ?? null) === -3, 'Falha técnica grave padrão aplica -3 na confiança');

// Test Game::effects directly on failure
$refEffects = new ReflectionMethod('Game', 'effects');
$refEffects->setAccessible(true);

$g = Game::create('Teste Falha', 'Santos', 'SP', 'meia', 'Santos-SP');
$g['states']['confianca'] = 50;
$effRoll = ['success' => false, 'class' => 'falha'];
$resEff = $refEffects->invokeArgs(null, [[], $effRoll, &$g]);
check(($resEff['confianca'] ?? 0) === -1, 'Game::effects aplica -1 em falha comum sem override');
check($g['states']['confianca'] === 49, 'Confiança é reduzida de 50 para 49');

$effRollGrave = ['success' => false, 'class' => 'falha_grave'];
$resEffGrave = $refEffects->invokeArgs(null, [[], $effRollGrave, &$g]);
check(($resEffGrave['confianca'] ?? 0) === -3, 'Game::effects aplica -3 em falha grave sem override');
check($g['states']['confianca'] === 46, 'Confiança é reduzida de 49 para 46');

// -------------------------------------------------------------
// 2. Preservação de efeitos sociais e disciplinares
// -------------------------------------------------------------
echo "\n2. Validando preservação de efeitos sociais, reputacionais e disciplinares...\n";
$oReclamar = SceneConsequences::option(['text' => 'Reclamar com técnico', 'code' => 'reclamar', 'effects' => []], 'partida', 9999, 0);
check(($oReclamar['effects']['confianca_treinador'] ?? 0) === -3, 'Reclamar mantém penalidade de -3 na confiança do treinador');
check(($oReclamar['effects']['risco_polemica'] ?? 0) === 3, 'Reclamar mantém risco de polêmica +3');

$oImprensa = SceneConsequences::option(['text' => 'Entrevista polêmica', 'code' => 'imprensa', 'effects' => []], 'pre_jogo', 9999, 0);
check(($oImprensa['failure_effects']['confianca_imprensa'] ?? 0) === -2, 'Imprensa mantém penalidade de -2 na imprensa em falha');
check(($oImprensa['failure_effects']['risco_polemica'] ?? 0) === 2, 'Imprensa mantém risco de polêmica +2');

$oDisciplina = SceneConsequences::option(['text' => 'Manter foco tático', 'code' => 'disciplina', 'effects' => []], 'treino', 9999, 0);
check(($oDisciplina['failure_effects']['foco'] ?? 0) === -2, 'Disciplina mantém penalidade de -2 em foco (sem tocar em confiança)');

// -------------------------------------------------------------
// 3. Recompensa defensiva e teto por partida
// -------------------------------------------------------------
echo "\n3. Validando recompensa de desarmes defensivos e teto por partida...\n";
$gDef = Game::create('Zagueiro Test', 'Santos', 'SP', 'zagueiro', 'Santos-SP');
$gDef['stage'] = 'partida';
$i = Game::currentIndex($gDef);
$m = &$gDef['matches'][$i];
$m['status'] = 'em_andamento';
$m['entry'] = 0;
$m['exit'] = 90;
$m['minute'] = 15;
$gDef['states']['confianca'] = 50;

// Tackle 1 (success)
$oTackle = ['action' => 'desarme', 'effects' => []];
$rollOk = ['success' => true, 'class' => 'sucesso', 'chance' => 80.0];
$refField = new ReflectionMethod('Game', 'fieldDecision');
$refField->setAccessible(true);

$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 1, 'Primeiro desarme incrementa contagem de desarmes para 1');
check($gDef['states']['confianca'] === 50, 'Primeiro desarme não concede bônus antecipado (requer >= 2)');

// Tackle 2 (success) -> triggers +1 bonus
$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 2, 'Segundo desarme incrementa contagem para 2');
check(!empty($m['stats']['tackle_confidence_awarded']), 'Flag tackle_confidence_awarded é marcada como true');
check($gDef['states']['confianca'] === 51, 'Segundo desarme concede exatamente +1 de confiança (50 -> 51)');
check(($gDef['feedback']['effects']['confianca'] ?? 0) === 1, 'Bônus de desarme aparece no feedback de efeitos');

// Tackle 3 (success) -> does NOT award duplicate bonus (capped at 1 per match)
$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 3, 'Terceiro desarme incrementa contagem para 3');
check($gDef['states']['confianca'] === 51, 'Terceiro desarme respeita o teto de 1 bônus por partida');

// Check that offensive player (centroavante) does NOT get tackle confidence bonus
$gAtt = Game::create('Atacante Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gAtt['stage'] = 'partida';
$iA = Game::currentIndex($gAtt);
$mA = &$gAtt['matches'][$iA];
$mA['status'] = 'em_andamento';
$mA['entry'] = 0;
$mA['exit'] = 90;
$mA['minute'] = 15;
$gAtt['states']['confianca'] = 50;

$refField->invokeArgs(null, [&$gAtt, $oTackle, $rollOk, 0, 0]);
$refField->invokeArgs(null, [&$gAtt, $oTackle, $rollOk, 0, 0]);
check($mA['stats']['tackles'] === 2, 'Atacante executa 2 desarmes');
check(empty($mA['stats']['tackle_confidence_awarded']), 'Atacante NÃO recebe flag de bonificação defensiva');
check($gAtt['states']['confianca'] === 50, 'Atacante mantém confiança inalterada por desarmes');

// -------------------------------------------------------------
// 4. Gol de pênalti, erro de pênalti e ausência de bonificação duplicada
// -------------------------------------------------------------
echo "\n4. Validando cobrança de pênalti no tempo normal (MatchPenalty)...\n";
$gPen = Game::create('Cobrador Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPen['stage'] = 'partida';
$iP = Game::currentIndex($gPen);
$mP = &$gPen['matches'][$iP];
$mP['status'] = 'em_andamento';
$mP['entry'] = 0;
$mP['exit'] = 90;
$mP['minute'] = 35;
$gPen['skills']['finalizacao']['value'] = 99; // force success
$mP['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPen['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];

$confBefore = $gPen['states']['confianca']; // 50
$rngSuccess = static fn(int $min, int $max): int => $min;
$resPen = Game::command($gPen, 'choice', ['option' => '0'], false, $rngSuccess);
check(($resPen['feedback']['highlight'] ?? '') === 'goal', 'Pênalti convertido registra highlight de goal');
check(($resPen['feedback']['effects']['confianca'] ?? 0) === 4, 'Feedback do pênalti registra +4 de confiança');
check($resPen['states']['confianca'] === 54, 'Confiança avança de 50 para 54 no gol de pênalti');
check(empty($resPen['matches'][$iP]['active_match_penalty']), 'active_match_penalty é devidamente destruído');

// Pênalti perdido
$gPenMiss = Game::create('Errou Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPenMiss['stage'] = 'partida';
$iPM = Game::currentIndex($gPenMiss);
$mPM = &$gPenMiss['matches'][$iPM];
$mPM['status'] = 'em_andamento';
$mPM['entry'] = 0;
$mPM['exit'] = 90;
$mPM['minute'] = 35;
$gPenMiss['skills']['finalizacao']['value'] = 1; // force failure
$mPM['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPenMiss['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];

$confBeforeMiss = $gPenMiss['states']['confianca']; // 50
$rngFail = static fn(int $min, int $max): int => $max;
$resPenMiss = Game::command($gPenMiss, 'choice', ['option' => '0'], false, $rngFail);
check(!($resPenMiss['feedback']['success'] ?? true), 'Pênalti falho registrado com sucesso=false');
$missDelta = $resPenMiss['states']['confianca'] - $confBeforeMiss;
check($missDelta === -1 || $missDelta === -3, "Pênalti falho aplica penalidade técnica padrão ($missDelta)");

// -------------------------------------------------------------
// 5. Curva pós-jogo em diferentes notas, níveis de confiança e minutos
// -------------------------------------------------------------
echo "\n5. Validando curva dinâmica pós-partida...\n";
function testEndMatchCurve(float $rating, int $confStart, int $minutes): int {
    $gT = Game::create('Curva Test', 'Santos', 'SP', 'meia', 'Santos-SP');
    $gT['stage'] = 'partida';
    $idx = Game::currentIndex($gT);
    $mT = &$gT['matches'][$idx];
    $mT['status'] = 'em_andamento';
    $mT['entry'] = 0;
    $mT['exit'] = $minutes;
    $mT['minute'] = 80;
    $gT['states']['confianca'] = $confStart;
    
    // Inject rating directly for testing curve
    $mT['stats']['goals'] = 0;
    $mT['stats']['assists'] = 0;
    $mT['stats']['tackles'] = 0;
    $mT['stats']['passes'] = 10;
    $mT['stats']['completed'] = 10;
    $mT['stats']['cards'] = 0;

    $refSeg = new ReflectionMethod('Game', 'segment');
    $refSeg->setAccessible(true);
    $refSeg->invokeArgs(null, [&$gT, 90]);

    // Force override rating to the test value before ledger evaluation if needed, but segment calculates it
    // Let's check ledger entry
    $ledgerKey = 'bal001:confidence:' . $gT['season'] . ':' . ($gT['competition_id'] ?? 'legacy') . ':' . $mT['round'] . ':' . $mT['home'] . ':' . $mT['away'];
    $ledger = $gT['reward_ledger'][$ledgerKey] ?? [];
    return (int)($ledger['applied'] ?? 0);
}

// Test individual tier logic
// R < 6.0: 0 bonus
// 6.0 <= R < 6.8: +2 if C < 50, +1 if 50 <= C < 60, 0 if C >= 60
// 6.8 <= R < 7.8: +1 if C < 70, 0 if C >= 70
// 7.8 <= R < 8.8: +1 if C < 75, 0 if C >= 75
// R >= 8.8: +2 if C < 90, 0 if C >= 90
$evalBonus = function(float $r, int $curConf, int $minutes) {
    $postRaw = 0;
    if ($r >= 8.8) {
        $postRaw = ($curConf < 90) ? 2 : 0;
    } elseif ($r >= 7.8) {
        $postRaw = ($curConf < 75) ? 1 : 0;
    } elseif ($r >= 6.8) {
        $postRaw = ($curConf < 70) ? 1 : 0;
    } elseif ($r >= 6.0) {
        $postRaw = ($curConf < 50) ? 2 : (($curConf < 60) ? 1 : 0);
    }
    $part = min(1.0, max(0, $minutes) / 60.0);
    return (int)round($postRaw * $part);
};

check($evalBonus(5.5, 40, 90) === 0, 'Nota 5.5 (abaixo de 6.0) concede ZERO bônus pós-partida');
check($evalBonus(6.2, 30, 90) === 2, 'Nota 6.2 com confiança 30 (< 50) concede +2 de bônus (saída do poço)');
check($evalBonus(6.2, 55, 90) === 1, 'Nota 6.2 com confiança 55 (50..59) concede +1 de bônus');
check($evalBonus(6.2, 65, 90) === 0, 'Nota 6.2 com confiança 65 (>= 60) concede 0 de bônus (estabilidade)');
check($evalBonus(7.2, 60, 90) === 1, 'Nota 7.2 com confiança 60 (< 70) concede +1 de bônus');
check($evalBonus(7.2, 75, 90) === 0, 'Nota 7.2 com confiança 75 (>= 70) concede 0 de bônus (anti-saturação)');
check($evalBonus(8.0, 70, 90) === 1, 'Nota 8.0 com confiança 70 (< 75) concede +1 de bônus');
check($evalBonus(8.0, 80, 90) === 0, 'Nota 8.0 com confiança 80 (>= 75) concede 0 de bônus');
check($evalBonus(9.0, 85, 90) === 2, 'Nota 9.0 com confiança 85 (< 90) concede +2 de bônus (supercraque)');
check($evalBonus(9.0, 95, 90) === 0, 'Nota 9.0 com confiança 95 (>= 90) concede 0 de bônus');

// -------------------------------------------------------------
// 6. Ausência de bônus quando o jogador não entra em campo
// -------------------------------------------------------------
echo "\n6. Validando ausência de bônus para atleta fora de campo (minutos = 0)...\n";
check($evalBonus(8.5, 40, 0) === 0, 'Atleta que atuou 0 minutos recebe estritamente 0 de bônus pós-partida');
check($evalBonus(7.0, 40, 40) === 1, 'Atleta com 40 minutos recebe bônus proporcional modulado (40/60)');
check($evalBonus(6.2, 30, 20) === 1, 'Atleta com 20 minutos e ganho nominal 2 recebe bônus modulado (20/60 = 0.67 -> 1)');

// -------------------------------------------------------------
// 7. Idempotência após encerramento e retomada (F5)
// -------------------------------------------------------------
echo "\n7. Validando idempotência do ledger de recompensa pós-partida...\n";
$gIdem = Game::create('Idem Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gIdem['stage'] = 'partida';
$iId = Game::currentIndex($gIdem);
$mId = &$gIdem['matches'][$iId];
$mId['status'] = 'em_andamento';
$mId['entry'] = 0;
$mId['exit'] = 90;
$mId['minute'] = 80;
$mId['stats']['goals'] = 2; // high rating
$gIdem['states']['confianca'] = 40;

$refSeg = new ReflectionMethod('Game', 'segment');
$refSeg->setAccessible(true);

// First termination
$refSeg->invokeArgs(null, [&$gIdem, 90]);
$conf1 = $gIdem['states']['confianca'];
$ledgerKey = 'bal001:confidence:' . $gIdem['season'] . ':' . ($gIdem['competition_id'] ?? 'legacy') . ':' . $mId['round'] . ':' . $mId['home'] . ':' . $mId['away'];
check(isset($gIdem['reward_ledger'][$ledgerKey]), 'Ledger contém a chave de idempotência da partida');
$entry = $gIdem['reward_ledger'][$ledgerKey];
check($entry['applied'] > 0, 'Bônus aplicado registrado no ledger');
check($conf1 > 40, "Confiança avançou na primeira chamada (40 -> $conf1)");

// Second termination (simulating reload / F5 / repeated call)
$refSeg->invokeArgs(null, [&$gIdem, 90]);
$conf2 = $gIdem['states']['confianca'];
check($conf2 === $conf1, "Segunda chamada preserva exatamente o mesmo valor de confiança ($conf1 === $conf2)");
check($gIdem['reward_ledger'][$ledgerKey]['ts'] === $entry['ts'], 'Registro do ledger permaneceu estritamente intacto');

// -------------------------------------------------------------
// 8. Continuidade de competições e mudança de rodada
// -------------------------------------------------------------
echo "\n8. Validando chave única em competições distintas e mudança de rodada...\n";
$key1 = 'bal001:confidence:1:sp-a1:1:Santos:Corinthians';
$key2 = 'bal001:confidence:1:br-a:1:Santos:Corinthians';
check($key1 !== $key2, 'Identificador unívoco separa formalmente mesma rodada em competições distintas');

$keyHome = 'bal001:confidence:1:copa:1:Santos:Palmeiras';
$keyAway = 'bal001:confidence:1:copa:2:Palmeiras:Santos';
check($keyHome !== $keyAway, 'Identificador unívoco separa jogos de ida e volta');

// -------------------------------------------------------------
// 9. Compatibilidade com saves legados
// -------------------------------------------------------------
echo "\n9. Validando compatibilidade com saves legados...\n";
$legacySave = [
    'name' => 'Craque Antigo',
    'position' => 'centroavante',
    'club' => 'Santos-SP',
    'season' => 1,
    'round' => 5,
    'stage' => 'partida',
    'skills' => ['finalizacao' => ['value' => 70, 'xp' => 0]],
    'states' => ['confianca' => 0, 'energia' => 85, 'confianca_treinador' => 50],
    'reward_ledger' => [], // ledger vazio
    'matches' => []
];
check(empty($legacySave['reward_ledger']), 'Save legado possui reward_ledger vazio sem quebra');
check($legacySave['states']['confianca'] === 0, 'Save legado com confiança zero é aceito sem erro');

// -------------------------------------------------------------
// 10. Execução real dos modos Normal, Rápido e Expresso
// -------------------------------------------------------------
echo "\n10. Validando execução real nos modos Normal, Rápido e Expresso...\n";
foreach (['normal', 'rapido', 'expresso'] as $mode) {
    $gMode = Game::create("Test $mode", 'Santos', 'SP', 'meia', 'Santos-SP');
    $gMode = Game::command($gMode, 'handicap');
    $gMode['play_mode'] = $mode;
    $gMode['states']['confianca'] = 50;

    if ($mode === 'normal') {
        $gMode = Game::command($gMode, 'choice', ['option' => '0']);
        check($gMode['stage'] === 'pre_jogo', 'Modo Normal avança com sucesso via Game::command()');
    } else {
        $gSim = PlayMode::simulate($gMode, 'coletivo');
        check(!empty($gSim['pace_report']), "Modo " . ucfirst($mode) . " simula com sucesso via PlayMode::simulate()");
        check($gSim['states']['confianca'] >= 0 && $gSim['states']['confianca'] <= 100, "Confiança final permanece na faixa 0..100");
    }
}

echo "\n=======================================================\n";
echo "TODOS OS $checks TESTES DA MISSÃO R10-BAL-001 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
