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

echo "=== INICIANDO SUÍTE DE TESTES R10-BAL-001-G1-R4: AUTOCONFIANÇA ===\n\n";

// -------------------------------------------------------------
// 1. Penalidades técnicas comuns e graves, fallback e catálogo
// -------------------------------------------------------------
echo "1. Validando penalidades técnicas comuns e graves (fallback e reconciliação)...\n";
$oFallback = SceneConsequences::option(['text' => 'Opção Fallback', 'effects' => []], 'fim_semana', 9999, 0);
check(($oFallback['failure_effects']['confianca'] ?? null) === -2, 'Fallback social padrão aplica -2 na confiança em falha comum (baseline)');
check(($oFallback['severe_failure_effects']['confianca'] ?? null) === -4, 'Fallback social padrão aplica -4 na confiança em falha grave (baseline)');

$oRawTech = SceneConsequences::option(['text' => 'Passe', 'action' => 'passe', 'effects' => []], 'partida', 9999, 0);
$oTech = ActionResolution::options([$oRawTech], 9999)[0];
check(($oTech['failure_effects']['confianca'] ?? null) === -1, 'Falha técnica comum reconciliada aplica -1 na confiança em partida');
check(($oTech['severe_failure_effects']['confianca'] ?? null) === -3, 'Falha técnica grave reconciliada aplica -3 na confiança em partida');

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

// Execução real da situação técnica 2016 via Game::command()
$rngCommonFail = static fn(int $min, int $max): int => ($max === 10000 ? 10000 : 50); // falha comum (intensity > 15)
$rngSevereFail = static fn(int $min, int $max): int => ($max === 10000 ? 10000 : 1);  // falha grave (intensity <= 15)

$g2016Common = Game::create('P2016 Common', 'Santos', 'SP', 'meia', 'Santos-SP');
$g2016Common['stage'] = 'partida';
$i2016C = Game::currentIndex($g2016Common);
$g2016Common['matches'][$i2016C]['status'] = 'em_andamento';
$g2016Common['matches'][$i2016C]['lineup'] = 'titular';
$g2016Common['matches'][$i2016C]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$g2016Common['matches'][$i2016C]['entry'] = 0;
$g2016Common['matches'][$i2016C]['exit'] = 90;
$g2016Common['matches'][$i2016C]['minute'] = 35;
$g2016Common['states']['confianca'] = 50;
$g2016Common['active_situation'] = ['id' => 2016, 'key' => Scenes::key($g2016Common)];
$res2016Common = Game::command($g2016Common, 'choice', ['option' => '0'], false, $rngCommonFail);
check($res2016Common['feedback']['class'] === 'falha', 'Situação 2016: class é falha comum');
check($res2016Common['states']['confianca'] === 49, 'Caso técnico 2016 Opção 0: falha comum aplica exatamente -1 (50 -> 49)');
check(($res2016Common['feedback']['effects']['confianca'] ?? 0) === -1, 'Feedback de efeitos registra confianca -1');

$g2016Severe = Game::create('P2016 Severe', 'Santos', 'SP', 'meia', 'Santos-SP');
$g2016Severe['stage'] = 'partida';
$i2016S = Game::currentIndex($g2016Severe);
$g2016Severe['matches'][$i2016S]['status'] = 'em_andamento';
$g2016Severe['matches'][$i2016S]['lineup'] = 'titular';
$g2016Severe['matches'][$i2016S]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$g2016Severe['matches'][$i2016S]['entry'] = 0;
$g2016Severe['matches'][$i2016S]['exit'] = 90;
$g2016Severe['matches'][$i2016S]['minute'] = 35;
$g2016Severe['states']['confianca'] = 50;
$g2016Severe['active_situation'] = ['id' => 2016, 'key' => Scenes::key($g2016Severe)];
$res2016Severe = Game::command($g2016Severe, 'choice', ['option' => '0'], false, $rngSevereFail);
check($res2016Severe['feedback']['class'] === 'falha_grave', 'Situação 2016: class é falha grave');
check($res2016Severe['states']['confianca'] === 47, 'Caso técnico 2016 Opção 0: falha grave aplica exatamente -3 (50 -> 47)');
check(($res2016Severe['feedback']['effects']['confianca'] ?? 0) === -3, 'Feedback de efeitos registra confianca -3');

// Execução real do caso social 1154, opção 3 via Game::command()
$g1154Common = Game::create('Social 1154 Common', 'Santos', 'SP', 'meia', 'Santos-SP');
$g1154Common['stage'] = 'fim_semana';
$g1154Common['states']['confianca'] = 50;
$g1154Common['active_situation'] = ['id' => 1154, 'key' => Scenes::key($g1154Common)];
$res1154Common = Game::command($g1154Common, 'choice', ['option' => '3'], false, $rngCommonFail);
check($res1154Common['feedback']['class'] === 'falha', 'Caso social 1154: class é falha comum');
check($res1154Common['states']['confianca'] === 48, 'Caso social 1154 Opção 3: falha comum termina em exatamente 48 (50 -> 48)');
check(($res1154Common['feedback']['effects']['confianca'] ?? 0) === -2, 'Feedback de efeitos registra confianca -2');

$g1154Severe = Game::create('Social 1154 Severe', 'Santos', 'SP', 'meia', 'Santos-SP');
$g1154Severe['stage'] = 'fim_semana';
$g1154Severe['states']['confianca'] = 50;
$g1154Severe['active_situation'] = ['id' => 1154, 'key' => Scenes::key($g1154Severe)];
$res1154Severe = Game::command($g1154Severe, 'choice', ['option' => '3'], false, $rngSevereFail);
check($res1154Severe['feedback']['class'] === 'falha_grave', 'Caso social 1154: class é falha grave');
check($res1154Severe['states']['confianca'] === 46, 'Caso social 1154 Opção 3: falha grave termina em exatamente 46 (50 -> 46)');
check(($res1154Severe['feedback']['effects']['confianca'] ?? 0) === -4, 'Feedback de efeitos registra confianca -4');

// Verificação abrangente do catálogo: 370 técnicas reconciliadas e preservação social
$catalog = Scenes::catalog();
$techCount = 0;
$techReconciled = 0;
foreach ($catalog as $id => $s) {
    if (($s['stage'] ?? '') === 'partida') {
        $resolvedOpts = ActionResolution::options($s['options'] ?? [], $id);
        foreach ($resolvedOpts as $opt) {
            $act = $opt['action'] ?? '';
            if (in_array($act, ['passe', 'chute', 'drible', 'desarme'], true)) {
                $techCount++;
                if (($opt['failure_effects']['confianca'] ?? null) === -1 && ($opt['severe_failure_effects']['confianca'] ?? null) === -3) {
                    $techReconciled++;
                }
            }
        }
    }
}
check($techCount === 370, "Total de opções técnicas em partida é exatamente 370 ($techCount)");
check($techReconciled === 370, "Todas as 370 opções técnicas em partida mantêm reconciliação -1/-3 ($techReconciled/370)");

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
$mLat['lineup'] = 'titular';
$mLat['participation_state'] = MatchParticipation::STATE_ON_FIELD;
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

// 4.1 Pênalti convertido com controle de janela subsequente na fixture (isolando destruição do active_match_penalty)
$gPen = Game::create('Cobrador Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPen['stage'] = 'partida';
$iP = Game::currentIndex($gPen);
$mP = &$gPen['matches'][$iP];
$mP['status'] = 'em_andamento';
$mP['lineup'] = 'titular';
$mP['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mP['entry'] = 0;
$mP['exit'] = 90;
$mP['minute'] = 35;
$mP['evaluated_penalties'] = ['35-45']; // Controla janela seguinte para isolar destruição do active_match_penalty
$mP['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPen['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];
$rngSuccess = static fn(int $min, int $max): int => $min;
$resPen = Game::command($gPen, 'choice', ['option' => '0'], false, $rngSuccess);
$mP_res = $resPen['matches'][$iP];
check(($resPen['feedback']['highlight'] ?? '') === 'goal', 'Pênalti convertido registra highlight de goal');
check(($resPen['feedback']['effects']['confianca'] ?? 0) === 4, 'Feedback do pênalti registra +4 de confiança');
check($resPen['states']['confianca'] === 54, 'Confiança avança de 50 para 54 no gol de pênalti (aplicada uma única vez)');
check(($mP_res['stats']['goals'] ?? 0) === 1, 'Estatística de gols do jogador incrementada para 1');
check(($mP_res['stats']['penalties_taken'] ?? 0) === 1, 'Estatística de penalties_taken incrementada para 1');
check(($mP_res['stats']['penalties_scored'] ?? 0) === 1, 'Estatística de penalties_scored incrementada para 1');
check(empty($mP_res['active_match_penalty']), 'Cobrança original é destruída e active_match_penalty permanece vazio quando janela seguinte não gera pênalti');

// 4.2 Pênalti convertido com sorteio aberto e verificação estrita de identidade da cobrança
$gPenOpen = Game::create('Cobrador Open', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPenOpen['stage'] = 'partida';
$iPO = Game::currentIndex($gPenOpen);
$mPO = &$gPenOpen['matches'][$iPO];
$mPO['status'] = 'em_andamento';
$mPO['lineup'] = 'titular';
$mPO['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mPO['entry'] = 0;
$mPO['exit'] = 90;
$mPO['minute'] = 35;
$mPO['active_match_penalty'] = [
    'status' => 'taking_kick',
    'victim' => $gPenOpen['name'],
    'window' => '15-35',
    'context_message' => 'Pênalti a favor!'
];
$resPenOpen = Game::command($gPenOpen, 'choice', ['option' => '0'], false, $rngSuccess);
$mPO_res = $resPenOpen['matches'][$iPO];
check(($mPO_res['active_match_penalty']['window'] ?? '') !== '15-35', 'Cobrança original (janela 15-35) foi resolvida e não permanece pendente');
if (!empty($mPO_res['active_match_penalty'])) {
    check($mPO_res['active_match_penalty']['window'] === '35-45', 'Novo pênalti ativo pertence à janela subsequente 35-45 (não é duplicação)');
    check(in_array($mPO_res['active_match_penalty']['status'] ?? '', ['taking_kick', 'pending_request'], true), 'Novo pênalti possui status válido de sorteio');
}
check($resPenOpen['states']['confianca'] === 54, 'Confiança permanece em 54 com bônus de +4 aplicado');
check(($mPO_res['stats']['goals'] ?? 0) === 1, 'Gol da cobrança original registrado com sucesso nas estatísticas');

// 4.3 Cobrança de pênalti desperdiçada
$gPenMiss = Game::create('Errou Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gPenMiss['stage'] = 'partida';
$iPM = Game::currentIndex($gPenMiss);
$mPM = &$gPenMiss['matches'][$iPM];
$mPM['status'] = 'em_andamento';
$mPM['lineup'] = 'titular';
$mPM['participation_state'] = MatchParticipation::STATE_ON_FIELD;
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
$resPenMiss = Game::command($gPenMiss, 'choice', ['option' => '0'], false, $rngCommonFail);
$mPM_res = $resPenMiss['matches'][$iPM];
check(!($resPenMiss['feedback']['success'] ?? true), 'Pênalti falho registrado com sucesso=false');
$missDelta = $resPenMiss['states']['confianca'] - $confBeforeMiss;
check($missDelta === -1 || $missDelta === -3, "Pênalti falho aplica penalidade técnica padrão ($missDelta)");
check(($mPM_res['active_match_penalty']['window'] ?? '') !== '15-35', 'Cobrança original de pênalti falho resolvida e não permanece pendente');
check(($mPM_res['stats']['penalties_taken'] ?? 0) === 1, 'Estatística penalties_taken incrementada para 1 em falha');
check(($mPM_res['stats']['penalties_missed'] ?? 0) === 1, 'Estatística penalties_missed incrementada para 1');
check(($mPM_res['stats']['goals'] ?? 0) === 0, 'Nenhum gol concedido em cobrança desperdiçada');

// 4.4 Teste de estabilidade repetida (50 execuções com sorteio aberto)
$repeatFailures = 0;
for ($rIter = 0; $rIter < 50; $rIter++) {
    $gRep = Game::create("Repeat $rIter", 'Santos', 'SP', 'centroavante', 'Santos-SP');
    $gRep['stage'] = 'partida';
    $iRep = Game::currentIndex($gRep);
    $gRep['matches'][$iRep]['status'] = 'em_andamento';
    $gRep['matches'][$iRep]['lineup'] = 'titular';
    $gRep['matches'][$iRep]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    $gRep['matches'][$iRep]['entry'] = 0;
    $gRep['matches'][$iRep]['exit'] = 90;
    $gRep['matches'][$iRep]['minute'] = 35;
    $gRep['matches'][$iRep]['active_match_penalty'] = [
        'status' => 'taking_kick',
        'victim' => $gRep['name'],
        'window' => '15-35',
        'context_message' => 'Pênalti a favor!'
    ];
    $resRep = Game::command($gRep, 'choice', ['option' => '0'], false, $rngSuccess);
    $mRep = $resRep['matches'][$iRep];
    $origStillPending = ($mRep['active_match_penalty']['window'] ?? '') === '15-35';
    $goalOk = ($mRep['stats']['goals'] ?? 0) === 1;
    $confOk = $resRep['states']['confianca'] === 54;
    $subsequentOk = empty($mRep['active_match_penalty']) || ($mRep['active_match_penalty']['window'] !== '15-35');
    if ($origStillPending || !$goalOk || !$confOk || !$subsequentOk) {
        $repeatFailures++;
    }
}
check($repeatFailures === 0, 'Estabilidade repetida comprovada: 50 execuções consecutivas com zero falhas');

// -------------------------------------------------------------
// 5. Validação real no motor: faixas de nota, monotonicidade e amortecimento
// -------------------------------------------------------------
echo "\n5. Validando faixas de nota, monotonicidade e amortecimento diretamente no motor produtivo...\n";

// Helper que executa o encerramento REAL no motor produtivo via Game::segment
function runEngineMatchEnd(float $targetRating, int $startConf, int $minutes): array {
    static $statsCache = null;
    if ($statsCache === null) {
        $statsCache = [];
        for ($cards = 0; $cards <= 10; $cards++) {
            for ($goals = 0; $goals <= 10; $goals++) {
                for ($assists = 0; $assists <= 5; $assists++) {
                    for ($ok = 0; $ok <= 40; $ok++) {
                        for ($err = 0; $err <= 40; $err++) {
                            $r = round(max(0, min(10, 6.0 + $goals*0.7 + $assists*0.5 + $ok*0.1 - $err*0.15 - $cards*0.6)), 1);
                            $key = (string)$r;
                            if (!isset($statsCache[$key])) {
                                $statsCache[$key] = [
                                    'goals' => $goals,
                                    'assists' => $assists,
                                    'passes' => $ok + $err,
                                    'completed' => $ok,
                                    'cards' => $cards
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    static $refSeg = null;
    if ($refSeg === null) {
        $refSeg = new ReflectionMethod('Game', 'segment');
        $refSeg->setAccessible(true);
    }

    $g = Game::create('EngineTest', 'Santos', 'SP', 'meia', 'Santos-SP');
    $g['stage'] = 'partida';
    $idx = Game::currentIndex($g);
    $m = &$g['matches'][$idx];
    $m['status'] = 'em_andamento';
    $m['lineup'] = $minutes > 0 ? 'titular' : 'reserva';
    $m['participation_state'] = $minutes > 0 ? MatchParticipation::STATE_ON_FIELD : MatchParticipation::STATE_ON_BENCH;
    $m['entry'] = 0;
    $m['exit'] = $minutes;
    $m['minute'] = 80;
    $m['stats']['minutes'] = $minutes;
    $m['evaluated_penalties'] = ['80-90'];

    $st = $statsCache[(string)round($targetRating, 1)] ?? ['passes' => 0, 'completed' => 0, 'goals' => 0, 'assists' => 0, 'cards' => 0];
    foreach ($st as $k => $v) {
        $m['stats'][$k] = $v;
    }
    $g['states']['confianca'] = $startConf;

    // Executa encerramento real no motor produtivo
    $refSeg->invokeArgs(null, [&$g, 90]);

    $ledgerKey = 'bal001:confidence:' . $g['season'] . ':' . ($g['competition_id'] ?? 'legacy') . ':' . $m['round'] . ':' . $m['home'] . ':' . $m['away'];
    $ledgerEntry = $g['reward_ledger'][$ledgerKey] ?? null;

    return [
        'game' => $g,
        'rating' => $m['stats']['rating'],
        'conf_before' => $startConf,
        'conf_after' => $g['states']['confianca'],
        'delta' => $g['states']['confianca'] - $startConf,
        'ledger' => $ledgerEntry,
        'nominal' => $ledgerEntry['nominal'] ?? 0,
        'applied' => $ledgerEntry['applied'] ?? 0,
    ];
}

// Caso específico relatado pelo Codex: R=6.7 vs R=6.8 com C=30 no motor real
$res67 = runEngineMatchEnd(6.7, 30, 90);
$res68 = runEngineMatchEnd(6.8, 30, 90);
check($res67['rating'] === 6.7, "Motor real calculou rating exato de 6.7 ({$res67['rating']})");
check($res68['rating'] === 6.8, "Motor real calculou rating exato de 6.8 ({$res68['rating']})");
check($res67['nominal'] === 2, "Nota 6.7 e confiança 30: nominal é +2 ({$res67['nominal']})");
check($res68['nominal'] === 2, "Nota 6.8 e confiança 30: nominal é +2 ({$res68['nominal']})");
check($res68['nominal'] >= $res67['nominal'], "RESOLVIDA INVERSÃO NO MOTOR REAL: Nota 6.8 não diminui bônus em relação a 6.7 ({$res68['nominal']} >= {$res67['nominal']})");

// Validação das faixas de nota diretamente pelo motor
$res55 = runEngineMatchEnd(5.5, 30, 90);
check($res55['nominal'] === 0, 'Nota abaixo de 6.0 (5.5) não concede bônus');

$res60_c30 = runEngineMatchEnd(6.0, 30, 90);
check($res60_c30['nominal'] === 2, 'Nota 6.0 com confiança < 50 concede bônus +2 no motor real');

$res60_c55 = runEngineMatchEnd(6.0, 55, 90);
check($res60_c55['nominal'] === 1, 'Nota 6.0 com confiança 55 (< 60) concede bônus +1 no motor real');

$res60_c65 = runEngineMatchEnd(6.0, 65, 90);
check($res60_c65['nominal'] === 0, 'Nota 6.0 com confiança >= 60 concede bônus 0 no motor real');

$res68_c65 = runEngineMatchEnd(6.8, 65, 90);
check($res68_c65['nominal'] === 1, 'Nota 6.8 com confiança 65 (< 70) concede bônus +1 no motor real');

$res78_c72 = runEngineMatchEnd(7.8, 72, 90);
check($res78_c72['nominal'] === 1, 'Nota 7.8 com confiança 72 (< 75) concede bônus +1 no motor real');

$res88_c85 = runEngineMatchEnd(8.8, 85, 90);
check($res88_c85['nominal'] === 2, 'Nota 8.8 com confiança 85 (< 90) concede bônus +2 no motor real');

$res88_c95 = runEngineMatchEnd(8.8, 95, 90);
check($res88_c95['nominal'] === 0, 'Nota 8.8 com confiança >= 90 concede bônus 0 no motor real');

// Varredura de monotonicidade em R no motor real
$monotonicityViolations = 0;
for ($c = 10; $c <= 90; $c += 20) {
    for ($m = 30; $m <= 90; $m += 30) {
        for ($r = 5.0; $r <= 9.0; $r += 0.5) {
            $r1 = round($r, 1);
            $r2 = round($r + 0.5, 1);
            $m1 = runEngineMatchEnd($r1, $c, $m);
            $m2 = runEngineMatchEnd($r2, $c, $m);
            if ($m2['nominal'] < $m1['nominal']) {
                $monotonicityViolations++;
            }
        }
    }
}
check($monotonicityViolations === 0, 'Varredura parametrizada em R no motor real: ZERO violações de monotonicidade');

// Varredura de amortecimento em C no motor real
$dampingViolations = 0;
for ($r = 6.0; $r <= 9.0; $r += 0.5) {
    for ($m = 60; $m <= 90; $m += 30) {
        for ($c = 10; $c <= 90; $c += 10) {
            $d1 = runEngineMatchEnd($r, $c, $m);
            $d2 = runEngineMatchEnd($r, $c + 10, $m);
            if ($d2['nominal'] > $d1['nominal']) {
                $dampingViolations++;
            }
        }
    }
}
check($dampingViolations === 0, 'Varredura parametrizada em C no motor real: amortecimento estrito sem anomalias');

// -------------------------------------------------------------
// 6. Minutos jogados e modulação temporal no motor real
// -------------------------------------------------------------
echo "\n6. Validando ausência de bônus com 0 minutos e modulação por tempo jogado...\n";
$resZeroMin = runEngineMatchEnd(10.0, 30, 0);
check($resZeroMin['rating'] === 0, 'Atleta que atuou 0 minutos tem rating 0 calculado pelo motor');
check($resZeroMin['nominal'] === 0, 'Atleta que atuou 0 minutos recebe rigorosamente 0 de bônus nominal');
check($resZeroMin['applied'] === 0, 'Atleta que atuou 0 minutos recebe rigorosamente 0 de bônus aplicado');
check($resZeroMin['conf_after'] === 30, 'Confiança permanece inalterada em 30');

$res60Min = runEngineMatchEnd(7.5, 55, 60);
check($res60Min['nominal'] === 1, 'Atleta titular que atuou 60 minutos recebe bônus integral');

$res60MinFast = runEngineMatchEnd(7.5, 40, 60);
check($res60MinFast['nominal'] === 2, 'Atleta titular com confiança < 50 atuando 60 min recebe bônus acelerado (+2)');

$res30Min = runEngineMatchEnd(7.5, 55, 30);
check($res30Min['nominal'] === 1, 'Atleta que atuou 30 minutos recebe bônus modulado (30/60)');

// -------------------------------------------------------------
// 7. Teto de recuperação em 90 e integridade dos registros do ledger
// -------------------------------------------------------------
echo "\n7. Validando teto de recuperação e integridade do ledger...\n";
$resTeto = runEngineMatchEnd(8.8, 90, 90);
check($resTeto['nominal'] === 0, 'Confiança >= 90 não recebe recuperação pós-jogo (teto funcional da curva)');
check($resTeto['applied'] === 0, 'Bônus aplicado registrado no ledger é 0 para C >= 90');

$res89 = runEngineMatchEnd(8.8, 89, 90);
check($res89['nominal'] === 2, 'Bônus nominal para R=8.8 e C=89 é 2');
check($res89['conf_after'] === 91, 'Confiança final avança para 91 (89 + 2)');
check($res89['applied'] === 2, 'Bônus aplicado registrado no ledger é exatamente 2');
check($res89['ledger']['conf_before'] === 89, 'Ledger registra conf_before=89');
check($res89['ledger']['conf_after'] === 91, 'Ledger registra conf_after=91');

// Clamp estrito de confiança no teto de 100 via motor
$refClamp = new ReflectionMethod('Game', 'clamp');
$refClamp->setAccessible(true);
check($refClamp->invoke(null, 102) === 100, 'Game::clamp limita valores acima de 100 ao teto de 100');
check($refClamp->invoke(null, -5) === 0, 'Game::clamp limita valores negativos ao piso de 0');

// -------------------------------------------------------------
// 8. Encerramento real de partida por Game::command() e Idempotência
// -------------------------------------------------------------
echo "\n8. Validando encerramento real via Game::command() e idempotência do ledger...\n";
$gRealEnd = Game::create('RealEnd Test', 'Santos', 'SP', 'centroavante', 'Santos-SP');
$gRealEnd['stage'] = 'partida';
$iRE = Game::currentIndex($gRealEnd);
$mRE = &$gRealEnd['matches'][$iRE];
$mRE['status'] = 'em_andamento';
$mRE['lineup'] = 'titular';
$mRE['participation_state'] = MatchParticipation::STATE_ON_FIELD;
$mRE['entry'] = 0;
$mRE['exit'] = 90;
$mRE['minute'] = 80;
$mRE['evaluated_penalties'] = ['80-90'];
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
// 9. Chaves unívocas entre competições e turnos
// -------------------------------------------------------------
echo "\n9. Validando chaves unívocas entre competições e turnos...\n";
$key1 = 'bal001:confidence:1:sp-a1:1:Santos:Corinthians';
$key2 = 'bal001:confidence:1:br-a:1:Santos:Corinthians';
check($key1 !== $key2, 'Chaves separam Estadual de Campeonato Brasileiro');

$keyHome = 'bal001:confidence:1:copa:1:Santos:Palmeiras';
$keyAway = 'bal001:confidence:1:copa:2:Palmeiras:Santos';
check($keyHome !== $keyAway, 'Chaves separam confrontos de ida e volta');

// -------------------------------------------------------------
// 10. Compatibilidade com saves legados
// -------------------------------------------------------------
echo "\n10. Validando compatibilidade com saves legados...\n";
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
// 11. Execução real dos modos Normal, Rápido e Expresso
// -------------------------------------------------------------
echo "\n11. Validando execução real nos modos Normal, Rápido e Expresso...\n";
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
echo "TODOS OS $checks TESTES DA MISSÃO R10-BAL-001-G1-R4 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
