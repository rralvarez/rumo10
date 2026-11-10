<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function ok(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("FALHA: $msg");
}

function fixtureMatch(int $round, string $home, string $away, array $overrides = []): array {
    return array_merge(Schedule::match($round, $home, $away), $overrides);
}

echo "Iniciando bateria de testes do placar agregado (v0.7)...\n";

$checks = 0;

// =========================================================================
// 1. Jogo de Ida (1ª partida): não inventa agregado nem resultado anterior
// =========================================================================
$t1 = [
    'id' => 'sp-a1',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        fixtureMatch(15, 'santos', 'sao-paulo', ['phase' => 'quartas', 'round' => 15, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 60, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'sao-paulo', 'santos', ['phase' => 'quartas', 'round' => 16, 'tie' => 0, 'status' => 'agendada', 'hg' => 0, 'ag' => 0]),
    ]
];

$mLeg1 = $t1['matches'][0];
$agg1 = Competition::tieContext($t1, $mLeg1);
ok($agg1 === null, 'Jogo de ida não deve retornar contexto de agregado');
$checks++;

// =========================================================================
// 2. Jogo de Volta antes do início (pre_jogo): Ida finalizada 1x2
// =========================================================================
$t2 = [
    'id' => 'sp-a1',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        fixtureMatch(15, 'santos', 'sao-paulo', ['phase' => 'quartas', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 2]),
        fixtureMatch(16, 'sao-paulo', 'santos', ['phase' => 'quartas', 'round' => 16, 'tie' => 0, 'status' => 'agendada', 'minute' => 0, 'hg' => 0, 'ag' => 0]),
    ]
];

$mLeg2Pre = $t2['matches'][1];
$aggPre = Competition::tieContext($t2, $mLeg2Pre);
ok($aggPre !== null, 'Jogo de volta com ida finalizada deve retornar contexto de agregado');
ok($aggPre['is_second_leg'] === true, 'Identificado como segundo jogo');
ok($aggPre['home_id'] === 'sao-paulo' && $aggPre['away_id'] === 'santos', 'IDs reais dos clubes preservados');
ok($aggPre['leg1']['home'] === 'santos' && $aggPre['leg1']['away'] === 'sao-paulo', 'Mandante da ida preservado');
ok($aggPre['leg1']['hg'] === 1 && $aggPre['leg1']['ag'] === 2, 'Gols da ida corretos (1 x 2)');
ok($aggPre['home_leg1_goals'] === 2, 'São Paulo marcou 2 na ida');
ok($aggPre['away_leg1_goals'] === 1, 'Santos marcou 1 na ida');
ok($aggPre['home_aggregate'] === 2 && $aggPre['away_aggregate'] === 1, 'Agregado inicial é 2 x 1');
ok($aggPre['is_tied'] === false, 'Agregado inicial não está empatado');
ok($aggPre['qualifying_id'] === 'sao-paulo', 'São Paulo está se classificando antes de começar');
ok($aggPre['rule'] === 'penalties', 'Regra da competição é pênaltis');
$checks += 11;

// =========================================================================
// 3. Jogo de Volta em andamento: Atualização em tempo real lance a lance
// =========================================================================
// 3.1: Santos abre o placar (0 x 1) -> Agregado empata em 2 x 2
$mLeg2Live1 = $t2['matches'][1];
$mLeg2Live1['status'] = 'em_andamento';
$mLeg2Live1['minute'] = 35;
$mLeg2Live1['hg'] = 0;
$mLeg2Live1['ag'] = 1;

$aggLive1 = Competition::tieContext($t2, $mLeg2Live1);
ok($aggLive1['home_aggregate'] === 2, 'São Paulo tem 2 no agregado (2 na ida + 0 na volta)');
ok($aggLive1['away_aggregate'] === 2, 'Santos tem 2 no agregado (1 na ida + 1 na volta)');
ok($aggLive1['is_tied'] === true, 'Empate no agregado detectado em tempo real');
ok($aggLive1['qualifying_id'] === null, 'Sob regra de pênaltis, empate não dá vaga antecipada');
$checks += 4;

// 3.2: Santos amplia para 0 x 2 -> Santos vira o agregado para 2 x 3
$mLeg2Live2 = $mLeg2Live1;
$mLeg2Live2['minute'] = 65;
$mLeg2Live2['ag'] = 2;

$aggLive2 = Competition::tieContext($t2, $mLeg2Live2);
ok($aggLive2['home_aggregate'] === 2, 'São Paulo 2 no agregado');
ok($aggLive2['away_aggregate'] === 3, 'Santos 3 no agregado');
ok($aggLive2['is_tied'] === false, 'Não está empatado');
ok($aggLive2['qualifying_id'] === 'santos', 'Santos virou e está se classificando no momento');
$checks += 4;

// 3.3: São Paulo desconta para 1 x 2 -> Agregado empata em 3 x 3
$mLeg2Live3 = $mLeg2Live2;
$mLeg2Live3['minute'] = 75;
$mLeg2Live3['hg'] = 1;

$aggLive3 = Competition::tieContext($t2, $mLeg2Live3);
ok($aggLive3['home_aggregate'] === 3, 'São Paulo 3 no agregado (2 + 1)');
ok($aggLive3['away_aggregate'] === 3, 'Santos 3 no agregado (1 + 2)');
ok($aggLive3['is_tied'] === true, 'Re-empate no agregado');
$checks += 3;

// 3.4: São Paulo empata o jogo em 2 x 2 -> São Paulo retoma a liderança do agregado (4 x 3)
$mLeg2Live4 = $mLeg2Live3;
$mLeg2Live4['minute'] = 88;
$mLeg2Live4['hg'] = 2;

$aggLive4 = Competition::tieContext($t2, $mLeg2Live4);
ok($aggLive4['home_aggregate'] === 4, 'São Paulo 4 no agregado (2 + 2)');
ok($aggLive4['away_aggregate'] === 3, 'Santos 3 no agregado (1 + 2)');
ok($aggLive4['is_tied'] === false, 'Desempatou');
ok($aggLive4['qualifying_id'] === 'sao-paulo', 'São Paulo volta a estar se classificando');
$checks += 4;

// =========================================================================
// 4. Critério de Vantagem do Empate (Advantage rule)
// =========================================================================
$tAdv = [
    'id' => 'sp-a2',
    'phase' => 'semifinal',
    'ties' => [
        ['a' => 'ponte-preta', 'b' => 'xv-piracicaba', 'winner' => null, 'rule' => 'advantage']
    ],
    'matches' => [
        fixtureMatch(15, 'xv-piracicaba', 'ponte-preta', ['phase' => 'semifinal', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'ponte-preta', 'xv-piracicaba', ['phase' => 'semifinal', 'round' => 16, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 80, 'hg' => 1, 'ag' => 0]),
    ]
];

$mAdv = $tAdv['matches'][1];
$aggAdv = Competition::tieContext($tAdv, $mAdv);
ok($aggAdv['is_tied'] === true, 'Agregado empatado em 1 x 1');
ok($aggAdv['rule'] === 'advantage', 'Regra é advantage');
ok($aggAdv['advantage_id'] === 'ponte-preta', 'Ponte Preta possui vantagem do empate');
ok($aggAdv['qualifying_id'] === 'ponte-preta', 'Com vantagem, Ponte Preta se classifica no empate');
$checks += 4;

// =========================================================================
// 5. Encerramento (Finalizada): Decisão no agregado, pênaltis e vantagem
// =========================================================================
// 5.1: Vitória simples no agregado
$mFin1 = $mLeg2Live4;
$mFin1['status'] = 'finalizada';
$mFin1['minute'] = 90;
$aggFin1 = Competition::tieContext($t2, $mFin1);
ok($aggFin1['finalized'] === true, 'Partida finalizada');
ok($aggFin1['advanced_id'] === 'sao-paulo', 'São Paulo avançou no agregado (4 x 3)');
ok($aggFin1['by_penalties'] === false, 'Não foi por pênaltis');
$checks += 3;

// 5.2: Empate e decisão nos pênaltis
$mFinPens = $mLeg2Live3; // 1 x 2 na volta, agregado 3 x 3
$mFinPens['status'] = 'finalizada';
$mFinPens['minute'] = 90;
$mFinPens['penalties'] = [5, 4]; // São Paulo 5 x 4 Santos nos pênaltis
$aggFinPens = Competition::tieContext($t2, $mFinPens);
ok($aggFinPens['finalized'] === true, 'Finalizada com pênaltis');
ok($aggFinPens['by_penalties'] === true, 'Identificado avanço por pênaltis');
ok($aggFinPens['advanced_id'] === 'sao-paulo', 'São Paulo avançou nos pênaltis (5 x 4)');
$checks += 3;

// 5.3: Empate e classificação por vantagem
$mFinAdv = $tAdv['matches'][1];
$mFinAdv['status'] = 'finalizada';
$mFinAdv['minute'] = 90;
$aggFinAdv = Competition::tieContext($tAdv, $mFinAdv);
ok($aggFinAdv['finalized'] === true, 'Finalizada por vantagem');
ok($aggFinAdv['by_penalties'] === false, 'Sem pênaltis');
ok($aggFinAdv['advanced_id'] === 'ponte-preta', 'Ponte Preta avançou pela vantagem');
$checks += 3;

// =========================================================================
// 6. Inversão de Mandos: Ordem inversa de mandante na ida/volta
// =========================================================================
$tInv = [
    'id' => 'br-d',
    'phase' => 'oitavas',
    'ties' => [
        ['a' => 'clube-alpha', 'b' => 'clube-beta', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        // Alpha foi mandante no jogo 1 e venceu por 3 x 1
        fixtureMatch(10, 'clube-alpha', 'clube-beta', ['phase' => 'oitavas', 'round' => 10, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 3, 'ag' => 1]),
        // Beta é mandante no jogo 2 e vence por 1 x 0
        fixtureMatch(11, 'clube-beta', 'clube-alpha', ['phase' => 'oitavas', 'round' => 11, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 60, 'hg' => 1, 'ag' => 0]),
    ]
];

$mInv = $tInv['matches'][1];
$aggInv = Competition::tieContext($tInv, $mInv);
ok($aggInv['home_id'] === 'clube-beta', 'Mandante do jogo 2 é Beta');
ok($aggInv['away_id'] === 'clube-alpha', 'Visitante do jogo 2 é Alpha');
ok($aggInv['home_leg1_goals'] === 1, 'Beta marcou 1 no jogo 1 (como visitante)');
ok($aggInv['away_leg1_goals'] === 3, 'Alpha marcou 3 no jogo 1 (como mandante)');
ok($aggInv['home_aggregate'] === 2, 'Beta tem 2 no agregado (1 + 1)');
ok($aggInv['away_aggregate'] === 3, 'Alpha tem 3 no agregado (3 + 0)');
ok($aggInv['qualifying_id'] === 'clube-alpha', 'Alpha está se classificando (3 x 2)');
$checks += 7;

// =========================================================================
// 7. Renderização HTML no componente Presentation::score e Presentation::renderAggregate
// =========================================================================
ob_start();
Presentation::renderAggregate($aggLive1);
$htmlLive = ob_get_clean();

ok(str_contains($htmlLive, 'class="aggregate-strip"'), 'HTML contém aggregate-strip');
ok(str_contains($htmlLive, 'Ida:'), 'HTML exibe resultado da ida');
ok(str_contains($htmlLive, 'Agregado:'), 'HTML exibe placar agregado');
ok(str_contains($htmlLive, '2 <small>×</small> 2'), 'HTML exibe números do agregado');
ok(str_contains($htmlLive, 'Decisão por pênaltis'), 'HTML informa critério de pênaltis');
$checks += 5;

ob_start();
Presentation::renderAggregate($aggLive2);
$htmlLive2 = ob_get_clean();
ok(str_contains($htmlLive2, 'Classificando: <b>'.ClubBadge::name($aggLive2['qualifying_id']).'</b>'), 'HTML informa time se classificando em tempo real');
ok(str_contains($htmlLive2, '2 <small>×</small> 3'), 'HTML exibe placar agregado atualizado');
$checks += 2;

ob_start();
Presentation::renderAggregate($aggFinPens);
$htmlFinPens = ob_get_clean();
ok(str_contains($htmlFinPens, 'Classificado nos pênaltis: <b>'.ClubBadge::name($aggFinPens['advanced_id']).'</b> (5 × 4)'), 'HTML pós-jogo informa campeão nos pênaltis');
$checks += 1;

ob_start();
Presentation::renderAggregate($aggFinAdv);
$htmlFinAdv = ob_get_clean();
ok(str_contains($htmlFinAdv, 'Classificado por vantagem: <b>'.ClubBadge::name($aggFinAdv['advanced_id']).'</b> (melhor campanha)'), 'HTML pós-jogo informa classificação por vantagem');
$checks += 1;

// =========================================================================
// 8. Integração completa com o Game array em Game::currentIndex e view.php
// =========================================================================
$gSample = Game::legacyCreate('Jogador de teste','São Paulo','SP','meia');
$gSample['official'] = true;
$gSample['competition_id'] = 'sp-a1';
$gSample['club'] = 'sao-paulo';
$gSample['competitions'] = ['sp-a1' => $t2];
$gSample['matches'] = $t2['matches'];
$gSample['matches'][1] = $mLeg2Live1;
$gSample['round'] = 16;
$gSample['stage'] = 'partida';

ob_start();
Presentation::score($gSample, $gSample['matches'][1]);
$htmlScore = ob_get_clean();

ok(str_contains($htmlScore, 'class="aggregate-strip"'), 'Presentation::score renderiza aggregate-strip');
ok(str_contains($htmlScore, 'Ida:'), 'Presentation::score exibe resultado da ida');
ok(str_contains($htmlScore, 'Agregado: <strong>2 <small>×</small> 2</strong>'), 'Presentation::score exibe agregado');
$checks += 3;

// 8.1 Teste de renderização no view.php para pre_jogo e fim_semana
if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void { echo '<form method="post" action="'.e($path).'">'; }
}

$path = '/carreira';
$error = null;
$_SESSION = ['account' => 0, 'csrf' => 'preview'];

// pre_jogo
$gPre = $gSample;
$gPre['stage'] = 'pre_jogo';
$gPre['matches'][1] = $mLeg2Pre;
$g = $gPre;
ob_start();
require dirname(__DIR__).'/app/view.php';
$htmlViewPre = ob_get_clean();
ok(str_contains($htmlViewPre, 'class="aggregate-strip"'), 'view.php renderiza aggregate-strip no pre_jogo');
ok(str_contains($htmlViewPre, 'PRÓXIMA PARTIDA · RODADA 16'), 'view.php exibe banner de pre_jogo');
$checks += 2;

// fim_semana
$gPost = $gSample;
$gPost['stage'] = 'fim_semana';
$gPost['matches'][1] = $mFinPens;
$g = $gPost;
ob_start();
require dirname(__DIR__).'/app/view.php';
$htmlViewPost = ob_get_clean();
ok(str_contains($htmlViewPost, 'class="aggregate-strip"'), 'view.php renderiza aggregate-strip no fim_semana');
ok(str_contains($htmlViewPost, 'Classificado nos pênaltis:'), 'view.php pós-jogo exibe avanço nos pênaltis');
$checks += 2;

echo "OK: $checks verificações do placar agregado aprovadas com sucesso!\n";
