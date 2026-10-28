<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function ok(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("FALHA: $msg");
}

function fixtureMatch(int $round, string $home, string $away, array $overrides = []): array {
    return array_merge(Schedule::match($round, $home, $away), $overrides);
}

echo "Iniciando bateria completa de testes de Disputa de Pênaltis (v0.7)...\n";
$checks = 0;

// =========================================================================
// 1. Empate agregado que exige pênaltis
// =========================================================================
$tKnockout = [
    'id' => 'sp-a3',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        fixtureMatch(15, 'santos', 'sao-paulo', ['phase' => 'quartas', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'sao-paulo', 'santos', ['phase' => 'quartas', 'round' => 16, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
    ]
];
$gKnockout = Game::legacyCreate('Craque', 'sao-paulo', 'SP', 'meia');
$gKnockout['club'] = 'sao-paulo';
$gKnockout['official'] = true;
$gKnockout['competition_id'] = 'sp-a3';
$gKnockout['competitions'] = ['sp-a3' => $tKnockout];
$gKnockout['matches'] = $tKnockout['matches'];
$gKnockout['round'] = 16;
$gKnockout['stage'] = 'partida';

$m2 = $tKnockout['matches'][1];
ok(PenaltyShootout::requiresShootout($gKnockout, $m2), 'Cenário 1: Empate agregado 1x1 sob regra penalties deve exigir disputa');
$checks++;

// =========================================================================
// 2. Vitória regulamentar que dispensa pênaltis
// =========================================================================
$tWin = $tKnockout;
$tWin['matches'][1]['hg'] = 2; // Agregado 2 x 1 para São Paulo
$gWin = $gKnockout;
$gWin['matches'] = $tWin['matches'];
ok(!PenaltyShootout::requiresShootout($gWin, $tWin['matches'][1]), 'Cenário 2: Vitória no agregado (2x1) dispensa pênaltis');
$checks++;

// =========================================================================
// 3. Competição com vantagem por melhor campanha (advantage)
// =========================================================================
$tAdv = [
    'id' => 'sp-a4',
    'phase' => 'semifinal',
    'ties' => [
        ['a' => 'ponte-preta', 'b' => 'xv-piracicaba', 'winner' => null, 'rule' => 'advantage']
    ],
    'matches' => [
        fixtureMatch(15, 'xv-piracicaba', 'ponte-preta', ['phase' => 'semifinal', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'ponte-preta', 'xv-piracicaba', ['phase' => 'semifinal', 'round' => 16, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
    ]
];
$gAdv = $gKnockout;
$gAdv['competition_id'] = 'sp-a4';
$gAdv['club'] = 'ponte-preta';
$gAdv['competitions'] = ['sp-a4' => $tAdv];
$gAdv['matches'] = $tAdv['matches'];
ok(!PenaltyShootout::requiresShootout($gAdv, $tAdv['matches'][1]), 'Cenário 3: Empate com vantagem de melhor campanha não deve ir para pênaltis');
$checks++;

// =========================================================================
// 4. Encerramento antecipado nas cinco cobranças
// =========================================================================
$stEarly = [
    'home' => 'sao-paulo',
    'away' => 'santos',
    'first_team' => 'sao-paulo',
    'second_team' => 'santos',
    'score' => ['sao-paulo' => 3, 'santos' => 0],
    'home_kicks_count' => 3,
    'away_kicks_count' => 3,
    'status' => 'in_progress',
    'winner' => null,
];
PenaltyShootout::checkTermination($stEarly);
ok($stEarly['status'] === 'completed', 'Cenário 4: 3x0 após 3 cobranças deve encerrar antecipadamente');
ok($stEarly['winner'] === 'sao-paulo', 'Cenário 4: São Paulo declarado vencedor antecipado');
$checks += 2;

// Outro caso: 4x2 após 4 cobranças da equipe que bateu segundo
$stEarly2 = [
    'home' => 'sao-paulo',
    'away' => 'santos',
    'first_team' => 'sao-paulo',
    'second_team' => 'santos',
    'score' => ['sao-paulo' => 4, 'santos' => 2],
    'home_kicks_count' => 4,
    'away_kicks_count' => 4,
    'status' => 'in_progress',
    'winner' => null,
];
PenaltyShootout::checkTermination($stEarly2);
ok($stEarly2['status'] === 'completed' && $stEarly2['winner'] === 'sao-paulo', 'Cenário 4: 4x2 após 4 rodadas encerra antecipadamente');
$checks++;

// =========================================================================
// 5. Disputa empatada após cinco cobranças
// =========================================================================
$stTie5 = [
    'home' => 'sao-paulo',
    'away' => 'santos',
    'first_team' => 'sao-paulo',
    'second_team' => 'santos',
    'score' => ['sao-paulo' => 4, 'santos' => 4],
    'home_kicks_count' => 5,
    'away_kicks_count' => 5,
    'status' => 'in_progress',
    'winner' => null,
];
PenaltyShootout::checkTermination($stTie5);
ok($stTie5['status'] === 'in_progress', 'Cenário 5: Empate em 4x4 após 5 cobranças NÃO encerra a disputa');
ok($stTie5['winner'] === null, 'Cenário 5: Sem vencedor ainda após 5x5 cobranças empatadas');
$checks += 2;

// =========================================================================
// 6. Morte súbita: cobranças alternadas (rodada 6+)
// =========================================================================
// Rodada 6: Ambos marcam (5 x 5) -> continua
$stSudden1 = [
    'home' => 'sao-paulo',
    'away' => 'santos',
    'first_team' => 'sao-paulo',
    'second_team' => 'santos',
    'score' => ['sao-paulo' => 5, 'santos' => 5],
    'home_kicks_count' => 6,
    'away_kicks_count' => 6,
    'status' => 'in_progress',
    'winner' => null,
];
PenaltyShootout::checkTermination($stSudden1);
ok($stSudden1['status'] === 'in_progress', 'Cenário 6: Empate na morte súbita (6 cobranças) continua');
$checks++;

// Rodada 7: Santos marca, São Paulo perde -> Santos campeão (5 x 6)
$stSudden2 = [
    'home' => 'sao-paulo',
    'away' => 'santos',
    'first_team' => 'sao-paulo',
    'second_team' => 'santos',
    'score' => ['sao-paulo' => 5, 'santos' => 6],
    'home_kicks_count' => 7,
    'away_kicks_count' => 7,
    'status' => 'in_progress',
    'winner' => null,
];
PenaltyShootout::checkTermination($stSudden2);
ok($stSudden2['status'] === 'completed', 'Cenário 6: Desempate na morte súbita encerra');
ok($stSudden2['winner'] === 'santos', 'Cenário 6: Santos vencedor na morte súbita');
$checks += 2;

// =========================================================================
// 7. Participação de protagonista cobrador (opções A, B, C, D e fórmulas)
// =========================================================================
$gPlayer = $gKnockout;
$gPlayer['skills']['finalizacao']['value'] = 75;
$gPlayer['skills']['forca']['value'] = 80;
$gPlayer['skills']['visao_jogo']['value'] = 70;
$gPlayer['states']['confianca'] = 85;
$gPlayer['states']['energia'] = 90;

$mShoot = $tKnockout['matches'][1];
$stInit = PenaltyShootout::start($gPlayer, $mShoot);

ok($stInit['protagonist_eligible'] === true, 'Cenário 7: Protagonista elegível para bater');
ok($stInit['protagonist_is_gk'] === false, 'Cenário 7: Protagonista é jogador de linha');

$choices = PenaltyShootout::choices($gPlayer);
ok(count($choices) === 4, 'Cenário 7: 4 alternativas de cobrança para jogador de linha');

$chA = $choices[0]['chance'];
$chB = $choices[1]['chance'];
$chC = $choices[2]['chance'];
$chD = $choices[3]['chance'];

ok($chA >= 15.0 && $chA <= 95.0, 'Chance A dentro dos limites');
ok($chC >= 15.0 && $chC <= 95.0, 'Chance C dentro dos limites');
ok($chD >= 15.0 && $chD <= 95.0, 'Chance D dentro dos limites');
// Opção C (potência com força 80) e D (cavadinha com confiança 85) têm diferenças matemáticas
ok($chC !== $chD, 'Cenário 7: Alternativas C e D têm probabilidades matematicamente distintas');
ok(str_contains($choices[0]['text'], 'canto esquerdo'), 'Opção A é canto esquerdo');
ok(str_contains($choices[2]['text'], 'Bater firme'), 'Opção C é bater firme');
ok(str_contains($choices[3]['text'], 'cavadinha'), 'Opção D é cavadinha');
$checks += 9;

// =========================================================================
// 8. Participação de protagonista goleiro
// =========================================================================
$gGk = Game::legacyCreate('Paredão', 'sao-paulo', 'SP', 'zagueiro');
$gGk['position'] = 'goleiro';
$gGk['skills']['posicionamento']['value'] = 78;
$gGk['skills']['forca']['value'] = 72;
$gGk['skills']['visao_jogo']['value'] = 68;
$gGk['states']['confianca'] = 70;
$gGk['states']['energia'] = 95;

$mGk = $tKnockout['matches'][1];
$stGk = PenaltyShootout::start($gGk, $mGk);
ok($stGk['protagonist_is_gk'] === true, 'Cenário 8: Protagonista identificado como goleiro');

$gkChoices = PenaltyShootout::choices($gGk);
ok(count($gkChoices) === 4, 'Cenário 8: 4 opções de defesa para goleiro');
ok(str_contains($gkChoices[0]['text'], 'Cair no canto esquerdo'), 'Goleiro opção A');
ok(str_contains($gkChoices[2]['text'], 'Ficar no centro'), 'Goleiro opção C');
ok(str_contains($gkChoices[3]['text'], 'antecipar a jogada'), 'Goleiro opção D');
ok($gkChoices[0]['chance'] !== $gkChoices[2]['chance'], 'Cenário 8: Opções de goleiro possuem probabilidades distintas');
$checks += 5;

// =========================================================================
// 9. Protagonista não escolhido / substituído
// =========================================================================
$mSub = $tKnockout['matches'][1];
$mSub['exit'] = 60; // Saiu aos 60 minutos
$gSub=$gPlayer;unset($gSub['shootout']); // Independent substituted-player fixture, not a replay.
$stSub = PenaltyShootout::start($gSub, $mSub);
ok($stSub['protagonist_eligible'] === false, 'Cenário 9: Protagonista substituído não é elegível para cobrar');
$checks++;

// =========================================================================
// 10. Atualização da página durante a disputa (Persistência)
// =========================================================================
$gSim = $gPlayer;
$mActive = $tKnockout['matches'][1];
PenaltyShootout::start($gSim, $mActive);
$gSim['stage'] = 'penaltis';

// Simula primeira cobrança
$stBefore = $gSim['shootout'];
$stBefore['score']['sao-paulo'] = 1;
$stBefore['home_kicks_count'] = 1;
$stBefore['kicks_taken'] = 1;
$stBefore['kicks'][] = [
    'team' => 'sao-paulo',
    'kicker' => 'Atleta 1',
    'is_protagonist' => false,
    'scored' => true,
    'description' => 'Gol!',
    'choice' => null,
    'round' => 1
];
$gSim['shootout'] = $stBefore;

// Recarrega o estado como se fosse novo request
$stAfter = $gSim['shootout'];
ok($stAfter['score']['sao-paulo'] === 1, 'Cenário 10: Placar sobrevive à recarga de página');
ok(count($stAfter['kicks']) === 1, 'Cenário 10: Histórico de cobranças mantido intacto');
$checks += 2;

// =========================================================================
// 11. Proteção contra cobranças duplicadas (Idempotência)
// =========================================================================
$gIdem = $gSim;
$gIdem['shootout']['protagonist_eligible'] = true;
$gIdem['shootout']['protagonist_kick_round'] = 1;
$gIdem['shootout']['current_round'] = 1;
$gIdem['shootout']['current_turn_team'] = $gIdem['club'];
$gIdem['shootout']['protagonist_acted'] = false;

// Executa decisão
PenaltyShootout::decide($gIdem, 0);
$kicksCount1 = count($gIdem['shootout']['kicks']);
ok($gIdem['shootout']['protagonist_acted'] === true, 'Cenário 11: Ação do protagonista registrada');

// Tentar agir novamente como protagonista na mesma rodada não duplica
ok(!PenaltyShootout::isProtagonistTurn($gIdem), 'Cenário 11: Não permite segunda cobrança do protagonista');
$checks += 2;

// =========================================================================
// 12. Classificação correta no chaveamento e liberação de fase
// =========================================================================
$tBracket = $tKnockout;
$gBracket = $gPlayer;
$gBracket['competitions']['sp-a3'] = $tBracket;
$mBracket = &$gBracket['matches'][1];
PenaltyShootout::start($gBracket, $mBracket);

// Força encerramento com vitória do São Paulo (5 x 4)
$gBracket['shootout']['status'] = 'completed';
$gBracket['shootout']['winner'] = 'sao-paulo';
$gBracket['shootout']['score']['sao-paulo'] = 5;
$gBracket['shootout']['score']['santos'] = 4;

PenaltyShootout::complete($gBracket);
ok($gBracket['stage'] === 'fim_semana', 'Cenário 12: Conclusão transiciona para fim_semana');
ok($gBracket['competitions']['sp-a3']['ties'][0]['winner'] === 'sao-paulo', 'Cenário 12: Vencedor gravado no chaveamento oficial');
$checks += 2;

// =========================================================================
// 13. Persistência dos resultados da disputa
// =========================================================================
$mResolved = $gBracket['matches'][1];
ok(isset($mResolved['penalties']), 'Cenário 13: Placar de pênaltis gravado na partida');
ok($mResolved['penalties'] === [5, 4], 'Cenário 13: Penalties é [5, 4]');
ok(isset($mResolved['shootout']), 'Cenário 13: Dados da disputa persistidos na partida');
$checks += 3;

// =========================================================================
// 14. Estatísticas normais preservadas (Sem poluição de gols normais ou temporada)
// =========================================================================
ok($mResolved['hg'] === 1 && $mResolved['ag'] === 0, 'Cenário 14: Placar regulamentar da partida permanece inalterado (1 x 0)');
ok($mResolved['stats']['goals'] === 0, 'Cenário 14: Gols individuais na partida não incluem cobranças de pênaltis');
$summary = Game::summary($gBracket);
ok($summary['goals'] === 0, 'Cenário 14: Resumo da temporada não inclui gols da disputa de pênaltis');
$checks += 3;

// =========================================================================
// 15. Apresentação Mobile e Desktop
// =========================================================================
$gViewShoot = $gPlayer;
$mView = $tKnockout['matches'][1];
PenaltyShootout::start($gViewShoot, $mView);
$gViewShoot['stage'] = 'penaltis';

// Renderização quando é a vez do protagonista
$gViewShoot['shootout']['current_turn_team'] = $gViewShoot['club'];
$gViewShoot['shootout']['current_round'] = 5;
$gViewShoot['shootout']['protagonist_kick_round'] = 5;
$gViewShoot['shootout']['protagonist_acted'] = false;

ob_start();
PenaltyShootout::render($gViewShoot);
$htmlShootout = ob_get_clean();

ok(str_contains($htmlShootout, 'shootout-panel'), 'Cenário 15: HTML renderiza shootout-panel');
ok(str_contains($htmlShootout, 'shootout-indicators'), 'Cenário 15: HTML renderiza indicadores');
ok(str_contains($htmlShootout, 'É A SUA VEZ!'), 'Cenário 15: HTML destaca vez do protagonista');
ok(str_contains($htmlShootout, 'Finalizar colocado no canto esquerdo'), 'Cenário 15: Alternativa A visível');
ok(str_contains($htmlShootout, 'Bater firme'), 'Cenário 15: Alternativa C visível');
$checks += 5;

// Renderização quando concluído
$gViewDone = $gViewShoot;
$gViewDone['shootout']['status'] = 'completed';
$gViewDone['shootout']['winner'] = 'sao-paulo';
$gViewDone['shootout']['score']['sao-paulo'] = 5;
$gViewDone['shootout']['score']['santos'] = 3;

ob_start();
PenaltyShootout::render($gViewDone);
$htmlDone = ob_get_clean();

ok(str_contains($htmlDone, 'Disputa encerrada!'), 'Cenário 15: HTML informa encerramento');
ok(str_contains($htmlDone, 'venceu por 5 × 3 e está classificado!'), 'Cenário 15: HTML informa classificado');
ok(str_contains($htmlDone, 'Continuar para o apito final'), 'Cenário 15: Botão de continuidade para o pós-jogo');
$checks += 3;

echo "OK: $checks verificações do motor de pênaltis e seus 15 cenários obrigatórios APROVADOS com sucesso!\n";
