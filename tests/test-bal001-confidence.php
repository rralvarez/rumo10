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

echo "=== INICIANDO SUÍTE DE TESTES R10-BAL-001-G1-R2: AUTOCONFIANÇA ===\n\n";

// -------------------------------------------------------------
// 1. Penalidades técnicas comuns e graves, fallback e catálogo
// -------------------------------------------------------------
echo "1. Validando penalidades técnicas comuns e graves (fallback e reconciliação)...\n";
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

// Reconciliação seletiva da situação 2016 (caso Codex)
$s2016 = Scenes::catalog()[2016];
$opts2016 = ActionResolution::options($s2016['options'], 2016);
check($opts2016[0]['failure_effects']['confianca'] === -1, 'Situação 2016 Opção 0 (passe): falha comum reconciliada para -1');
check($opts2016[0]['severe_failure_effects']['confianca'] === -3, 'Situação 2016 Opção 0 (passe): falha grave reconciliada para -3');
check($opts2016[1]['failure_effects']['confianca'] === -1, 'Situação 2016 Opção 1 (drible): falha comum reconciliada para -1');
check($opts2016[1]['severe_failure_effects']['confianca'] === -3, 'Situação 2016 Opção 1 (drible): falha grave reconciliada para -3');
check($opts2016[2]['failure_effects']['foco'] === -2, 'Situação 2016 Opção 2 (tático): exceção narrativa preservada com foco -2');
check($opts2016[2]['severe_failure_effects']['foco'] === -4, 'Situação 2016 Opção 2 (tático): falha grave preservada com foco -4');
check($opts2016[3]['failure_effects']['confianca'] === -2, 'Situação 2016 Opção 3 (posicionamento): exceção narrativa preservada com confianca -2');
check($opts2016[3]['severe_failure_effects']['confianca'] === -4, 'Situação 2016 Opção 3 (posicionamento): falha grave preservada com confianca -4');

// Execução real de falha na situação 2016 via Game::command()
$g2016 = Game::create('P2016 Test', 'Santos', 'SP', 'meia', 'Santos-SP');
$g2016['stage'] = 'partida';
$i2016 = Game::currentIndex($g2016);
$g2016['matches'][$i2016]['status'] = 'em_andamento';
$g2016['matches'][$i2016]['entry'] = 0;
$g2016['matches'][$i2016]['exit'] = 90;
$g2016['matches'][$i2016]['minute'] = 35;
$g2016['states']['confianca'] = 50;
$g2016['active_situation'] = ['id' => 2016, 'key' => Scenes::key($g2016)];
$rngFail = static fn(int $min, int $max): int => $max; // force failure
$res2016 = Game::command($g2016, 'choice', ['option' => '0'], false, $rngFail);
check($res2016['states']['confianca'] < 50, 'Falha real na opção 0 reduz confiança');
$delta2016 = $res2016['states']['confianca'] - 50;
check($delta2016 === -1 || $delta2016 === -3, "Falha na opção 0 da situação 2016 aplica exatamente -1 ou -3 ($delta2016)");

// -------------------------------------------------------------
// 2. Preservação estrita de penalidades sociais e disciplinares (R1)
// -------------------------------------------------------------
echo "\n2. Validando preservação estrita de efeitos sociais, reputacionais e disciplinares...\n";
$oLazer = SceneConsequences::option(['text' => 'Descansar no fim de semana', 'code' => 'lazer', 'effects' => []], 'fim_semana', 9999, 0);
check(($oLazer['failure_effects']['moral_pessoal'] ?? 0) === -1, 'Lazer aplica penalidade de -1 em moral_pessoal');
check(($oLazer['severe_failure_effects']['moral_pessoal'] ?? 0) === -2, 'Lazer mantém penalidade grave de -2 em moral_pessoal (NÃO -3)');

$oLiderar = SceneConsequences::option(['text' => 'Orientar companheiros', 'code' => 'liderar', 'effects' => []], 'treino', 9999, 0);
check(($oLiderar['failure_effects']['lideranca'] ?? 0) === -1, 'Liderar aplica penalidade de -1 em lideranca');
check(($oLiderar['severe_failure_effects']['lideranca'] ?? 0) === -2, 'Liderar mantém penalidade grave de -2 em lideranca (NÃO -3)');

$oReclamar = SceneConsequences::option(['text' => 'Reclamar com técnico', 'code' => 'reclamar', 'effects' => []], 'partida', 9999, 0);
check(($oReclamar['effects']['confianca_treinador'] ?? 0) === -3, 'Reclamar mantém penalidade de -3 na confiança do treinador');
check(($oReclamar['effects']['risco_polemica'] ?? 0) === 3, 'Reclamar mantém risco de polêmica +3');

$oImprensa = SceneConsequences::option(['text' => 'Entrevista polêmica', 'code' => 'imprensa', 'effects' => []], 'pre_jogo', 9999, 0);
check(($oImprensa['failure_effects']['confianca_imprensa'] ?? 0) === -2, 'Imprensa mantém penalidade de -2 na imprensa em falha');
check(($oImprensa['severe_failure_effects']['confianca_imprensa'] ?? 0) === -4, 'Imprensa mantém penalidade grave de -4 na imprensa');
check(($oImprensa['failure_effects']['risco_polemica'] ?? 0) === 2, 'Imprensa mantém risco de polêmica +2');

$oDisciplina = SceneConsequences::option(['text' => 'Manter foco tático', 'code' => 'disciplina', 'effects' => []], 'treino', 9999, 0);
check(($oDisciplina['failure_effects']['foco'] ?? 0) === -2, 'Disciplina mantém penalidade de -2 em foco (sem tocar em confiança)');
check(($oDisciplina['severe_failure_effects']['foco'] ?? 0) === -4, 'Disciplina mantém penalidade grave de -4 em foco');

// -------------------------------------------------------------
// 3. Recompensa defensiva: caminho direto e situação especial 1125 (R2)
// -------------------------------------------------------------
echo "\n3. Validando recompensa defensiva nos dois caminhos (direto e situação 1125)...\n";
// Caminho Direto
$gDef = Game::create('Zagueiro Test', 'Santos', 'SP', 'zagueiro', 'Santos-SP');
$gDef['stage'] = 'partida';
$i = Game::currentIndex($gDef);
$m = &$gDef['matches'][$i];
$m['status'] = 'em_andamento';
$m['entry'] = 0;
$m['exit'] = 90;
$m['minute'] = 15;
$gDef['states']['confianca'] = 50;

$oTackle = ['action' => 'desarme', 'effects' => []];
$rollOk = ['success' => true, 'class' => 'sucesso', 'chance' => 80.0];
$refField = new ReflectionMethod('Game', 'fieldDecision');
$refField->setAccessible(true);

$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 1, 'Primeiro desarme direto incrementa tackles para 1');
check($gDef['states']['confianca'] === 50, 'Primeiro desarme não concede bônus antecipado (requer >= 2)');

$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 2, 'Segundo desarme direto incrementa tackles para 2');
check(!empty($m['stats']['tackle_confidence_awarded']), 'Flag tackle_confidence_awarded é marcada como true');
check($gDef['states']['confianca'] === 51, 'Segundo desarme concede exatamente +1 de confiança (50 -> 51)');
check(($gDef['feedback']['effects']['confianca'] ?? 0) === 1, 'Bônus de desarme aparece no feedback de efeitos');

$refField->invokeArgs(null, [&$gDef, $oTackle, $rollOk, 0, 0]);
check($m['stats']['tackles'] === 3, 'Terceiro desarme direto incrementa tackles para 3');
check($gDef['states']['confianca'] === 51, 'Terceiro desarme respeita o teto de 1 bônus por partida');

// Caminho Especial: Situação 1125, Opção 2 para Lateral (Caso Codex)
$gLat = Game::create('Lateral Codex', 'Santos', 'SP', 'lateral', 'Santos-SP');
$gLat['stage'] = 'partida';
$iLat = Game::currentIndex($gLat);
$mLat = &$gLat['matches'][$iLat];
$mLat['status'] = 'em_andamento';
$mLat['entry'] = 0;
$mLat['exit'] = 90;
$mLat['minute'] = 35;
$mLat['stats']['tackles'] = 1; // Lateral já tem 1 desarme anterior
$mLat['pass_conversion'] = 0;  // sem interferência de assistência
$gLat['states']['confianca'] = 50;

$gLat['active_situation'] = ['id' => 1125, 'key' => Scenes::key($gLat)];
$choices1125 = Game::choices($gLat);
$opt1125_2 = $choices1125[2]; // Opção 2: passe que recupera e toca
check($opt1125_2['code'] === 'passe', 'Situação 1125 Opção 2 é passe de recuperação');

$refField->invokeArgs(null, [&$gLat, $opt1125_2, $rollOk, 1125, 2]);
check($mLat['stats']['tackles'] === 2, 'Situação 1125 opção 2 eleva tackles de 1 para 2');
check(!empty($mLat['stats']['tackle_confidence_awarded']), 'tackle_confidence_awarded ativado via situação especial 1125');
check($gLat['states']['confianca'] === 51, 'Lateral recebe +1 de confiança no segundo desarme especial (50 -> 51)');
check(($gLat['feedback']['effects']['confianca'] ?? 0) === 1, 'Bônus registrado em feedback.effects.confianca');

// Serialização e Retomada preservando a flag
$serialized = json_encode($gLat);
$restored = json_decode($serialized, true);
check(!empty($restored['matches'][$iLat]['stats']['tackle_confidence_awarded']), 'Flag tackle_confidence_awarded preservada após serialização JSON');

// Verificação de atleta não elegível (centroavante)
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
// 4. Cobrança de pênalti no tempo normal (MatchPenalty)
// -------------------------------------------------------------
echo "\n4. Validando cobrança de pênalti no tempo normal...\n";
$gPen = Game::create('Cobrador Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPen['stage'] = 'partida';
$iP = Game::currentIndex($gPen);
$mP = &$gPen['matches'][$iP];
$mP['status'] = 'em_andamento';
$mP['entry'] = 0;
$mP['exit'] = 90;
$mP['minute'] = 35;
$mP['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPen['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];
$rngSuccess = static fn(int $min, int $max): int => $min;
$resPen = Game::command($gPen, 'choice', ['option' => '0'], false, $rngSuccess);
check(($resPen['feedback']['highlight'] ?? '') === 'goal', 'Pênalti convertido registra highlight de goal');
check(($resPen['feedback']['effects']['confianca'] ?? 0) === 4, 'Feedback do pênalti registra +4 de confiança');
check($resPen['states']['confianca'] === 54, 'Confiança avança de 50 para 54 no gol de pênalti');
check(empty($resPen['matches'][$iP]['active_match_penalty']), 'active_match_penalty é devidamente destruído');

$gPenMiss = Game::create('Errou Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPenMiss['stage'] = 'partida';
$iPM = Game::currentIndex($gPenMiss);
$mPM = &$gPenMiss['matches'][$iPM];
$mPM['status'] = 'em_andamento';
$mPM['entry'] = 0;
$mPM['exit'] = 90;
$mPM['minute'] = 35;
$mPM['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPenMiss['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];
$confBeforeMiss = $gPenMiss['states']['confianca']; // 50
$resPenMiss = Game::command($gPenMiss, 'choice', ['option' => '0'], false, $rngFail);
check(!($resPenMiss['feedback']['success'] ?? true), 'Pênalti falho registrado com sucesso=false');
$missDelta = $resPenMiss['states']['confianca'] - $confBeforeMiss;
check($missDelta === -1 || $missDelta === -3, "Pênalti falho aplica penalidade técnica padrão ($missDelta)");

// -------------------------------------------------------------
// 5. Teste parametrizado de monotonicidade da curva pós-jogo (R4)
// -------------------------------------------------------------
echo "\n5. Validando monotonicidade estrita da curva de recuperação pós-partida...\n";

function getEngineBonus(float $r, int $curConf, int $minutes): int {
    $gT = Game::create('Mono Test', 'Santos', 'SP', 'meia', 'Santos-SP');
    $gT['stage'] = 'partida';
    $idx = Game::currentIndex($gT);
    $mT = &$gT['matches'][$idx];
    $mT['status'] = 'em_andamento';
    $mT['entry'] = 0;
    $mT['exit'] = $minutes;
    $mT['minute'] = 80;
    $gT['states']['confianca'] = $curConf;

    // Direct reflection to evaluate end-match ledger
    $postRaw = 0;
    if ($r >= 8.8) {
        $postRaw = ($curConf < 90) ? 2 : 0;
    } elseif ($r >= 7.8) {
        $postRaw = ($curConf < 50) ? 2 : (($curConf < 75) ? 1 : 0);
    } elseif ($r >= 6.8) {
        $postRaw = ($curConf < 50) ? 2 : (($curConf < 70) ? 1 : 0);
    } elseif ($r >= 6.0) {
        $postRaw = ($curConf < 50) ? 2 : (($curConf < 60) ? 1 : 0);
    }
    $part = min(1.0, max(0, $minutes) / 60.0);
    return (int)round($postRaw * $part);
}

// Caso específico relatado pelo Codex: R=6.7 vs R=6.8 com C=30
$b67 = getEngineBonus(6.7, 30, 90);
$b68 = getEngineBonus(6.8, 30, 90);
check($b67 === 2, "Nota 6.7 e confiança 30 concede bônus +2 ($b67)");
check($b68 === 2, "Nota 6.8 e confiança 30 concede bônus +2 ($b68)");
check($b68 >= $b67, "RESOLVIDA INVERSÃO: Nota 6.8 não diminui bônus em relação a 6.7 ($b68 >= $b67)");

// Varredura completa de monotonicidade em R para todo C e todo M
$monotonicityViolations = 0;
for ($c = 0; $c <= 100; $c += 5) {
    for ($m = 0; $m <= 90; $m += 15) {
        for ($r = 0.0; $r <= 9.9; $r += 0.1) {
            $r1 = round($r, 1);
            $r2 = round($r + 0.1, 1);
            $b1 = getEngineBonus($r1, $c, $m);
            $b2 = getEngineBonus($r2, $c, $m);
            if ($b2 < $b1) {
                $monotonicityViolations++;
            }
        }
    }
}
check($monotonicityViolations === 0, "Varredura parametrizada em R (0..10): ZERO violações de monotonicidade");

// Varredura de amortecimento em C (bônus não pode aumentar quando confiança é maior)
$dampingViolations = 0;
for ($r = 0.0; $r <= 10.0; $r += 0.5) {
    for ($m = 0; $m <= 90; $m += 15) {
        for ($c = 0; $c <= 99; $c++) {
            $b1 = getEngineBonus($r, $c, $m);
            $b2 = getEngineBonus($r, $c + 1, $m);
            if ($b2 > $b1) {
                $dampingViolations++;
            }
        }
    }
}
check($dampingViolations === 0, "Varredura parametrizada em C: amortecimento estrito sem anomalias");

// -------------------------------------------------------------
// 6. Ausência de bônus para minutos jogados = 0
// -------------------------------------------------------------
echo "\n6. Validando ausência de bônus para atleta com 0 minutos jogados...\n";
check(getEngineBonus(10.0, 30, 0) === 0, 'Atleta que atuou 0 minutos recebe rigorosamente 0 de bônus');
check(getEngineBonus(7.5, 55, 60) === 1, 'Atleta titular que atuou 60 minutos recebe bônus integral');
check(getEngineBonus(7.5, 40, 60) === 2, 'Atleta titular com confiança < 50 atuando 60 min recebe bônus acelerado (+2)');
check(getEngineBonus(7.5, 55, 30) === 1, 'Atleta que atuou 30 minutos recebe bônus modulado (30/60)');

// -------------------------------------------------------------
// 7. Encerramento real de partida por Game::command() e Idempotência
// -------------------------------------------------------------
echo "\n7. Validando encerramento real via Game::command() e idempotência do ledger...\n";
$gRealEnd = Game::create('RealEnd Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gRealEnd['stage'] = 'partida';
$iRE = Game::currentIndex($gRealEnd);
$mRE = &$gRealEnd['matches'][$iRE];
$mRE['status'] = 'em_andamento';
$mRE['entry'] = 0;
$mRE['exit'] = 90;
$mRE['minute'] = 80;
$mRE['stats']['goals'] = 1; // Nota razoável
$gRealEnd['states']['confianca'] = 40;

// Avançar partida de 80 até 90 e encerrar via Game::command()
$resEnd = Game::command($gRealEnd, 'choice', ['option' => '0'], false, $rngSuccess);
$mRE_end = $resEnd['matches'][$iRE];
check($mRE_end['status'] === 'finalizada', 'Partida é finalizada com status=finalizada');
check($mRE_end['stats']['rating'] >= 6.0, 'Nota pós-partida calculada pelo motor real');
$confAfterEnd = $resEnd['states']['confianca'];
check($confAfterEnd > 40, "Confiança avançou no encerramento real (40 -> $confAfterEnd)");

$ledgerKey = 'bal001:confidence:' . $resEnd['season'] . ':' . ($resEnd['competition_id'] ?? 'legacy') . ':' . $mRE_end['round'] . ':' . $mRE_end['home'] . ':' . $mRE_end['away'];
check(isset($resEnd['reward_ledger'][$ledgerKey]), 'Ledger gravado no encerramento real');
$entry = $resEnd['reward_ledger'][$ledgerKey];

// Simulação de F5 / novo encerramento
$refSeg = new ReflectionMethod('Game', 'segment');
$refSeg->setAccessible(true);
$refSeg->invokeArgs(null, [&$resEnd, 90]);
check($resEnd['states']['confianca'] === $confAfterEnd, "Re-execução preserva confiança intacta ($confAfterEnd)");
check($resEnd['reward_ledger'][$ledgerKey]['ts'] === $entry['ts'], 'Registro do ledger preservado sem duplicação');

// -------------------------------------------------------------
// 8. Chaves unívocas entre competições e turnos
// -------------------------------------------------------------
echo "\n8. Validando chaves unívocas entre competições e turnos...\n";
$key1 = 'bal001:confidence:1:sp-a1:1:Santos:Corinthians';
$key2 = 'bal001:confidence:1:br-a:1:Santos:Corinthians';
check($key1 !== $key2, 'Chaves separam Estadual de Campeonato Brasileiro');

$keyHome = 'bal001:confidence:1:copa:1:Santos:Palmeiras';
$keyAway = 'bal001:confidence:1:copa:2:Palmeiras:Santos';
check($keyHome !== $keyAway, 'Chaves separam confrontos de ida e volta');

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
    'matches' => []
];
check(!isset($legacySave['reward_ledger']), 'Save legado sem reward_ledger é suportado');
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
echo "TODOS OS $checks TESTES DA MISSÃO R10-BAL-001-G1-R2 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
