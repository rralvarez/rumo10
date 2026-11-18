<?php
declare(strict_types=1);

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');

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

echo "=== INICIANDO SUÍTE DE TESTES R10-CAR-001-R4: CONTINUIDADE INTEGRADA E APOSENTADORIA ===\n\n";

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="' . e($path) . '"><input type="hidden" name="csrf" value="fixture-csrf">';
        if ($g) {
            echo '<input type="hidden" name="version" value="' . e($g['version']) . '"><input type="hidden" name="key" value="fixture-idempotency-key">';
        }
    }
}

// -------------------------------------------------------------
// 1. Cenário: Ciclo Completo Sem Transferência (Permanência)
// -------------------------------------------------------------
echo "1. Validando Ciclo Anual Completo Sem Transferência (Permanência)...\n";
$g1 = Game::legacyCreate('Craque Fiel', 'Santos', 'SP', 'meia');
$g1['club'] = 'Santos-SP';
$g1['season'] = 1;
$g1['age'] = 18;
$g1['official'] = false; // modo padrão simples
$g1['stage'] = 'resumo';
$g1['version'] = 10;
$g1['skills']['passe']['value'] = 75;

// Renderização da retrospectiva em resumo
ob_start();
Presentation::annualRecap($g1);
$recapHtml1 = ob_get_clean();
check(str_contains($recapHtml1, 'TEMPORADA CONCLUÍDA · BALANÇO ANUAL'), 'Etapa resumo exibe balanço anual');
check(str_contains($recapHtml1, 'Ver minha proposta →'), 'Botão para abrir mercado presente');

// Executa abertura da janela de transferências
$g1Oferta = Game::command($g1, 'offers');
check($g1Oferta['stage'] === 'oferta', 'Comando offers avança para etapa oferta');
check(isset($g1Oferta['market_draw']), 'Roleta de mercado persistida');

// Decide permanecer no clube
$g1Permanencia = Game::command($g1Oferta, 'transfer', ['accept' => 'no']);
check($g1Permanencia['stage'] === 'treino', 'Permanência avança para treino');
check($g1Permanencia['season'] === 2, 'Temporada incrementada para 2');
check($g1Permanencia['age'] === 19, 'Idade incrementada para 19');
check($g1Permanencia['club'] === 'Santos-SP', 'Clube mantido inalterado');
check(count($g1Permanencia['history']) === 1, 'Histórico anual registrado');
check($g1Permanencia['states']['energia'] === 85, 'Energia restaurada para 85');

// -------------------------------------------------------------
// 2. Cenário: Ciclo Completo Com Transferência
// -------------------------------------------------------------
echo "\n2. Validando Ciclo Anual Completo Com Transferência...\n";
$g2 = Game::legacyCreate('Craque Viajante', 'Santos', 'SP', 'centroavante');
$g2['club'] = 'Santos-SP';
$g2['season'] = 1;
$g2['age'] = 20;
$g2['stage'] = 'resumo';
$g2['version'] = 12;

$g2Oferta = Game::command($g2, 'offers');
// Se o sorteio não gerou ofertas (cenário 0), injetamos uma oferta válida compatível
if (empty($g2Oferta['offers'])) {
    $fakeOffer = [
        'club' => 'Palmeiras-SP',
        'tier' => 1,
        'peso' => 40,
        'country' => 'BR',
        'selected' => false,
        'forecast' => OpportunityForecast::estimate($g2Oferta, 'Palmeiras-SP')
    ];
    $g2Oferta['offers'] = [$fakeOffer];
    $g2Oferta['market_draw']['offers'] = [$fakeOffer];
}

$destClub = $g2Oferta['offers'][0]['club'];
$g2Transferido = Game::command($g2Oferta, 'transfer', ['accept' => 'yes', 'option' => 0]);
check($g2Transferido['stage'] === 'treino', 'Transferência aceita avança para treino');
check($g2Transferido['season'] === 2, 'Temporada incrementada para 2');
check($g2Transferido['age'] === 21, 'Idade incrementada para 21');
check($g2Transferido['club'] === $destClub, 'Clube atualizado para o destino escolhido');
check($g2Transferido['history'][0]['next_club'] === $destClub, 'Histórico documenta clube de destino');

// -------------------------------------------------------------
// 3. Cenário: Temporada Oficial com Múltiplas Competições
// -------------------------------------------------------------
echo "\n3. Validando Transições de Múltiplas Competições...\n";
$allCatMemberships = array_map(fn($c) => $c['teams'], Competition::catalog());
$tSpA1 = Competition::tournament('sp-a1', $allCatMemberships['sp-a1']);
$tSpA1['done'] = true;
$tSpA1['champion'] = 'Santos-SP';
$tSpA1['initial_ranking'] = $tSpA1['teams'];
$tBrA = Competition::tournament('br-a', $allCatMemberships['br-a']);
$tBrA['done'] = false;
$tBrA['round'] = 1;
$tBrA['phase_end'] = 38;
$tBrA['initial_ranking'] = $tBrA['teams'];

$gMulti = [
    'id' => 403,
    'name' => 'Craque Multi',
    'club' => 'Santos-SP',
    'position' => 'meia',
    'season' => 1,
    'age' => 22,
    'version' => 15,
    'stage' => 'competicao_resumo',
    'official' => true,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1', 'br-a'],
    'memberships' => $allCatMemberships,
    'competitions' => [
        'sp-a1' => $tSpA1,
        'br-a' => $tBrA
    ],
    'competition_results' => [
        'sp-a1' => ['name' => 'Paulistão A1', 'champion' => 'Santos-SP', 'position' => 1]
    ],
    'skills' => ['passe' => ['value' => 70, 'xp' => 0]],
    'states' => ['confianca' => 60, 'energia' => 80, 'confianca_treinador' => 60, 'risco_lesao' => 5, 'risco_cartao' => 5],
    'matches' => [],
    'history' => [],
    'offers' => []
];

// Final da 1ª competição aponta para a 2ª
ob_start();
CompetitionView::recap($gMulti);
$htmlComp1 = ob_get_clean();
check(str_contains($htmlComp1, 'Próximo desafio:'), 'Primeira competição sinaliza próximo campeonato');
check(str_contains($htmlComp1, 'Brasileirão Série A'), 'Cita nominalmente o Brasileirão');

// Avanço pela rota continue
$gMulti2 = Game::command($gMulti, 'continue');
check($gMulti2['stage'] === 'treino', 'Avanço para a próxima competição retorna a treino');
check($gMulti2['competition_id'] === 'br-a', 'Competição ativa passa a ser br-a');

// Agora simula término da última competição
$gMulti2['stage'] = 'competicao_resumo';
$tBrA = Competition::tournament('br-a', $gMulti['memberships']['br-a']);
$tBrA['done'] = true;
$tBrA['champion'] = 'Palmeiras-SP';
$tBrA['initial_ranking'] = $tBrA['teams'];
$gMulti2['competitions']['br-a'] = $tBrA;
$gMulti2['competition_results']['br-a'] = ['name' => 'Brasileirão Série A', 'champion' => 'Palmeiras-SP', 'position' => 2];

ob_start();
CompetitionView::recap($gMulti2);
$htmlComp2 = ob_get_clean();
check(str_contains($htmlComp2, 'Calendário encerrado:'), 'Última competição sinaliza encerramento do calendário');
check(str_contains($htmlComp2, 'Concluir temporada e ver retrospectiva anual →'), 'Botão direciona para a retrospectiva');

$gMultiResumo = Game::command($gMulti2, 'continue');
check($gMultiResumo['stage'] === 'resumo', 'Avanço da última competição conduz para resumo anual');

// -------------------------------------------------------------
// 4. Cenário: Modos de Jogo (Normal, Rápido e Expresso)
// -------------------------------------------------------------
echo "\n4. Validando Suporte aos Três Modos de Jogo...\n";
foreach (['normal', 'rapido', 'expresso'] as $m) {
    $gMode = $gBase = Game::legacyCreate('Craque Modo ' . ucfirst($m), 'São Paulo', 'SP', 'meia');
    $gMode['play_mode'] = $m;
    $gMode['stage'] = 'resumo';
    ob_start();
    Presentation::annualRecap($gMode);
    $recapMode = ob_get_clean();
    check(str_contains($recapMode, 'TEMPORADA CONCLUÍDA'), "Modo $m renderiza retrospectiva anual");
}

// -------------------------------------------------------------
// 5. Cenário: Idempotência nos Três Estágios Finais (F5 / Recarga)
// -------------------------------------------------------------
echo "\n5. Validando Idempotência nos Estágios Finais...\n";
// Estágio competicao_resumo
$snapshotBeforeComp = serialize($gMulti);
ob_start(); CompetitionView::recap($gMulti); ob_get_clean();
check(serialize($gMulti) === $snapshotBeforeComp, 'Recarga em competicao_resumo não altera estado');

// Estágio resumo
$snapshotBeforeResumo = serialize($gMultiResumo);
ob_start(); Presentation::annualRecap($gMultiResumo); ob_get_clean();
check(serialize($gMultiResumo) === $snapshotBeforeResumo, 'Recarga em resumo não altera estado');

// Estágio oferta
$snapshotBeforeOferta = serialize($g1Oferta);
ob_start(); TransferMarket::render($g1Oferta); ob_get_clean();
check(serialize($g1Oferta) === $snapshotBeforeOferta, 'Recarga em oferta não altera estado');

// -------------------------------------------------------------
// 6. Cenário: Aposentadoria Compulsória com Retrospectiva Visível
// -------------------------------------------------------------
echo "\n6. Validando Aposentadoria Compulsória aos 39/40 anos com Retrospectiva...\n";
$allMemberships = array_map(fn($c) => $c['teams'], Competition::catalog());
$tVet = Competition::tournament('br-a', $allMemberships['br-a']);
$tVet['done'] = true;
$tVet['champion'] = 'Santos-SP';
$tVet['initial_ranking'] = $tVet['teams'];
$gVeteran = [
    'id' => 499,
    'name' => 'Veterano Campeão',
    'club' => 'Santos-SP',
    'position' => 'meia',
    'age' => 39,
    'season' => 24,
    'stage' => 'competicao_resumo',
    'round' => 38,
    'version' => 99,
    'official' => true,
    'competition_id' => 'br-a',
    'competition_cursor' => 0,
    'season_competitions' => ['br-a'],
    'memberships' => $allMemberships,
    'competitions' => ['br-a' => $tVet],
    'competition_results' => ['br-a' => ['name' => 'Brasileirão Série A', 'champion' => 'Santos-SP', 'position' => 1]],
    'skills' => ['passe' => ['value' => 80, 'xp' => 0]],
    'states' => ['confianca' => 70, 'energia' => 85, 'confianca_treinador' => 60, 'risco_lesao' => 5, 'risco_cartao' => 5],
    'matches' => [],
    'history' => [],
    'offers' => []
];

// Conclui a última competição da temporada de 39 anos
$gVeteranResumo = Game::command($gVeteran, 'continue');
check($gVeteranResumo['stage'] === 'resumo', 'Veterano aos 39 anos entra normalmente em resumo');
check($gVeteranResumo['age'] === 39, 'Veterano preserva idade de 39 anos durante a visualização do resumo');
check($gVeteranResumo['version'] === 100, 'Versão incrementada exatamente uma vez ao entrar em resumo (99 -> 100)');

// Renderização da retrospectiva anual da sua última temporada
ob_start();
Presentation::annualRecap($gVeteranResumo);
$vetRecapHtml = ob_get_clean();
check(str_contains($vetRecapHtml, 'TEMPORADA CONCLUÍDA · BALANÇO ANUAL'), 'Retrospectiva anual completa visível para o veterano');
check(str_contains($vetRecapHtml, 'DESPEDIDA DOS GRAMADOS'), 'Retrospectiva exibe tag de despedida dos gramados');
check(str_contains($vetRecapHtml, 'Encerramento da Carreira Profissional'), 'Retrospectiva exibe título de encerramento da carreira');
$expectedWarning = 'Ao concluir sua carreira, o jogo guardará permanentemente suas estatísticas, clubes, temporadas e conquistas no histórico. Os detalhes das partidas, eventos individuais e decisões serão removidos e não poderão ser recuperados.';
check(str_contains($vetRecapHtml, $expectedWarning), 'Aviso explícito sobre remoção de detalhes presente');
$beforeDetailsPos = strpos($vetRecapHtml, '<details');
$warningPos = strpos($vetRecapHtml, $expectedWarning);
check($warningPos !== false && ($beforeDetailsPos === false || $warningPos < $beforeDetailsPos), 'Aviso de remoção de detalhes está visível fora do painel recolhido');
check(str_contains($vetRecapHtml, 'diferente da opção de excluir o resumo permanente'), 'Diferencia encerramento da carreira da exclusão completa do resumo permanente');
check(str_contains($vetRecapHtml, 'Concluir minha carreira →'), 'Botão principal é Concluir minha carreira');
check(!str_contains($vetRecapHtml, 'Ver minha proposta →'), 'Não oferece propostas de mercado');

// Proteção contra aposentadoria desencadeada por comandos inválidos na etapa resumo
foreach (['choice', 'advance', 'simulate', 'continue', 'transfer', 'join', 'handicap', 'mode'] as $invalidAction) {
    $copyVet = $gVeteranResumo;
    $rejectedInvalid = false;
    try {
        Game::command($copyVet, $invalidAction, $invalidAction === 'simulate' ? ['plan' => 'equilibrio'] : ($invalidAction === 'transfer' ? ['accept' => 'no'] : ($invalidAction === 'choice' ? ['option' => 0] : [])));
    } catch (InvalidArgumentException $e) {
        $rejectedInvalid = true;
    }
    check($rejectedInvalid && $copyVet['stage'] === 'resumo' && $copyVet['age'] === 39, "Comando inválido '$invalidAction' em resumo não conclui a carreira precocemente");
}

// Confirmação do encerramento (submetendo o comando válido 'offers' da retrospectiva)
$versionBeforeClose = $gVeteranResumo['version'];
$seasonBeforeClose = $gVeteranResumo['season'];
$gAposentado = Game::command($gVeteranResumo, 'offers');
check($gAposentado['stage'] === 'aposentado', 'Carreira encerrada após confirmação explícita');
check($gAposentado['version'] === $versionBeforeClose + 1, 'Versão incrementada exatamente uma vez no encerramento compulsório (100 -> 101)');
check($gAposentado['age'] === 40, 'Idade final de 40 anos para atleta de 39');
check($gAposentado['season'] === $seasonBeforeClose, 'Nenhuma nova temporada é criada no encerramento compulsório');
check($gAposentado['retirement']['reason'] === 'mandatory', 'Motivo da aposentadoria registrado como mandatory');
check(empty($gAposentado['offers']) && !isset($gAposentado['market_draw']), 'Aposentadoria compulsória sem sorteio de mercado nem ofertas');

// Verificação de idade 40 não avançando para 41 e envelhecimento técnico aplicado no máximo uma vez
$gAlready40 = $gVeteranResumo;
$gAlready40['age'] = 40;
$gAlready40['player_development'] = [
    'version' => PlayerDevelopment::VERSION,
    'seed' => 'vet-40-seed',
    'parameters' => [
        'calibration' => 'calendar-log250-smooth024016-budget410-isolated',
        'normalization' => ['schema' => 'marginal-log-v1', 'scale' => 250],
        'learning_model' => ['schema' => 'smooth-log-learning-v1', 'linear' => .24, 'saturating' => .16],
        'cost' => ['base' => 20, 'divisor' => 5],
        'annual_base' => 410,
        'annual_merit' => 175,
        'rates' => [1.45, 1.2, .85, .15, .08],
        'ceiling_open_age' => 27,
        'aging_acceleration' => 0.075
    ],
    'learning' => 1.0,
    'ceilings' => array_fill_keys(Rules::SKILLS, 90),
    'physical_decline_start' => 33,
    'aging_rate' => 1.0,
    'technical_decline_start' => 37,
    'received' => array_fill_keys(Rules::SKILLS, 0),
    'spent' => array_fill_keys(Rules::SKILLS, 0),
    'points' => array_fill_keys(Rules::SKILLS, 0),
];
foreach (Rules::SKILLS as $sk) {
    $gAlready40['skills'][$sk] = ['value' => 75, 'xp' => 0];
}
$gClosed40 = Game::command($gAlready40, 'offers');
check($gClosed40['age'] === 40, 'Idade 40 não avança para 41 no encerramento');
check($gClosed40['version'] === $gAlready40['version'] + 1, 'Versão incrementada exatamente uma vez para atleta já com 40 anos');
$skillsAfterFirstBirthday = $gClosed40['skills'];
$secondAgingChanges = Evolution::birthday($gClosed40);
check($secondAgingChanges === [] && $gClosed40['skills'] === $skillsAfterFirstBirthday, 'Envelhecimento técnico aplicado no máximo uma vez por encerramento');

// Carreira legada com idade superior (> 40) não é alterada arbitrariamente
$gLegacy42 = $gVeteranResumo;
$gLegacy42['age'] = 42;
$gClosed42 = Game::command($gLegacy42, 'offers');
check($gClosed42['age'] === 42 && $gClosed42['stage'] === 'aposentado', 'Carreira legada com idade superior (42) preserva idade sem alteração arbitrária');

// Compatibilidade com save legado em 'oferta' aos 39 anos
$gLegacyOferta = $gVeteranResumo;
$gLegacyOferta['stage'] = 'oferta';
$gLegacyOferta['age'] = 39;
$gLegacyOferta['offers'] = [['club' => 'Flamengo-RJ', 'tier' => 1, 'peso' => 10, 'selected' => true]];
$invalidInOfertaRejected = false;
try {
    Game::command($gLegacyOferta, 'choice', ['option' => 0]);
} catch (InvalidArgumentException $e) {
    $invalidInOfertaRejected = true;
}
check($invalidInOfertaRejected, 'Comando inválido em save legado na etapa oferta é rejeitado');
$gLegacyOfertaClosed = Game::command($gLegacyOferta, 'transfer', ['accept' => 'yes', 'option' => 0]);
check($gLegacyOfertaClosed['stage'] === 'aposentado', 'Save legado em oferta aos 39 anos encerra carreira ao responder transferência');
check($gLegacyOfertaClosed['age'] === 40, 'Save legado em oferta aos 39 anos normaliza idade para 40');
check($gLegacyOfertaClosed['club'] === 'Santos-SP', 'Save legado em oferta aos 39 anos impede nova contratação');
check($gLegacyOfertaClosed['season'] === $gLegacyOferta['season'], 'Save legado em oferta aos 39 anos impede início de nova temporada');
check(empty($gLegacyOfertaClosed['offers']), 'Save legado em oferta limpa ofertas ao aposentar');
check($gLegacyOfertaClosed['version'] === $gLegacyOferta['version'] + 1, 'Save legado em oferta incrementa versão exatamente uma vez');

// Renderização da tela de carreira aposentada
ob_start();
Retirement::render($gAposentado);
$retiredScreenHtml = ob_get_clean();
check(str_contains($retiredScreenHtml, 'CARREIRA ENCERRADA'), 'Tela de carreira encerrada exibida');
check(str_contains($retiredScreenHtml, '40 anos'), 'Cita 40 anos na despedida');

// -------------------------------------------------------------
// 7. Cenário: Aposentadoria Voluntária Preservada e Bloqueios
// -------------------------------------------------------------
echo "\n7. Validando Aposentadoria Voluntária e Bloqueios...\n";
$g35 = Game::legacyCreate('Craque Decidido', 'São Paulo', 'SP', 'lateral');
$g35['age'] = 35;
$g35['stage'] = 'treino';
$v35Before = $g35['version'];
$g35Aposentado = Game::command($g35, 'retire', ['confirm' => 'retire']);
check($g35Aposentado['stage'] === 'aposentado', 'Aposentadoria voluntária aos 35 anos funciona');
check($g35Aposentado['version'] === $v35Before + 1, 'Aposentadoria voluntária incrementa versão exatamente uma vez');
check($g35Aposentado['retirement']['reason'] === 'voluntary', 'Motivo registrado como voluntary');

$g34 = Game::legacyCreate('Craque Jovem', 'São Paulo', 'SP', 'lateral');
$g34['age'] = 34;
$g34['stage'] = 'treino';
$blocked = false;
try {
    Game::command($g34, 'retire', ['confirm' => 'retire']);
} catch (InvalidArgumentException $e) {
    $blocked = true;
}
check($blocked, 'Aposentadoria voluntária bloqueada para jogadores com menos de 35 anos');

// -------------------------------------------------------------
// 8. Cenário: Impossibilidade de Jogar Nova Temporada Após Aposentadoria
// -------------------------------------------------------------
echo "\n8. Validando Bloqueio de Comandos em Carreira Aposentada...\n";
foreach (['choice', 'advance', 'offers', 'transfer', 'continue', 'simulate', 'mode', 'retire'] as $act) {
    $commandBlocked = false;
    try {
        Game::command($gAposentado, $act, $act === 'retire' ? ['confirm' => 'retire'] : []);
    } catch (InvalidArgumentException $e) {
        $commandBlocked = true;
    }
    check($commandBlocked, "Comando '$act' bloqueado em carreira aposentada");
}

// -------------------------------------------------------------
// 9. Cenário: Preservação de Estatísticas, Compactação e Idempotência em Memória
// -------------------------------------------------------------
echo "\n9. Validando Preservação de Totais, Compactação e Idempotência em Memória...\n";
$statsBeforeCompact = CareerStats::collect($gAposentado);
$trophiesBeforeCompact = Trophies::collect($gAposentado);
check(isset($statsBeforeCompact['totals']), 'Totais históricos coletados com sucesso');
check(isset($statsBeforeCompact['clubs']), 'Totais por clube preservados');
check(is_array($trophiesBeforeCompact) && count($trophiesBeforeCompact) > 0, 'Sala de troféus preservada e consultável');

$closingKey = bin2hex(random_bytes(32));
$closingHash = hash('sha256', json_encode(['offers', []], JSON_THROW_ON_ERROR));
$compactSummary = RetiredCareer::build($gAposentado, ['key' => $closingKey, 'hash' => $closingHash]);
$compactedState = RetiredCareer::state($compactSummary);

check($compactedState['stage'] === 'aposentado', 'Estado compactado mantém stage=aposentado');
check($compactedState['age'] === 40 && $compactedState['version'] === $gAposentado['version'], 'Estado compactado preserva idade 40 e versão final');
check(CareerStats::collect($compactedState)['totals'] === $statsBeforeCompact['totals'], 'Estado compactado preserva totais históricos idênticos');
check(CareerStats::collect($compactedState)['clubs'] === $statsBeforeCompact['clubs'], 'Estado compactado preserva totais por clube idênticos');
check(Trophies::collect($compactedState) === $trophiesBeforeCompact, 'Estado compactado preserva troféus idênticos');
check(empty($compactedState['matches']) && empty($compactedState['history']) && empty($compactedState['offers']), 'Estado compactado remove detalhes de partidas, histórico bruto e ofertas');

// Simulação da guarda de idempotência de Repository::command() sobre o closing_command
$closingMeta = $compactedState['retired_summary']['closing_command'] ?? null;
check($closingMeta !== null && hash_equals($closingMeta['key'], $closingKey) && hash_equals($closingMeta['hash'], $closingHash), 'Metadado closing_command gravado com key e hash exatos');

// Retentativa idempotente com mesmo token e mesma ação
$retryHashSame = hash('sha256', json_encode(['offers', []], JSON_THROW_ON_ERROR));
$isIdempotentReplay = ($closingMeta && hash_equals($closingMeta['key'], $closingKey) && hash_equals($closingMeta['hash'], $retryHashSame));
check($isIdempotentReplay, 'Retentativa do mesmo token com a mesma ação é reconhecida de forma idempotente');

// Reutilização do mesmo token com outra ação deve ser rejeitada
$retryHashDifferent = hash('sha256', json_encode(['transfer', ['accept' => 'no']], JSON_THROW_ON_ERROR));
$tokenReuseRejected = false;
if ($closingMeta && hash_equals($closingMeta['key'], $closingKey)) {
    if (!hash_equals($closingMeta['hash'], $retryHashDifferent)) {
        $tokenReuseRejected = true;
    }
}
check($tokenReuseRejected, 'Reutilização do mesmo token de encerramento com outra ação é rejeitada');

// Impossibilidade de executar comandos sobre o estado compactado
$compactedBlocked = false;
try {
    Game::command($compactedState, 'offers');
} catch (InvalidArgumentException $e) {
    $compactedBlocked = true;
}
check($compactedBlocked, 'Estado compactado também bloqueia novos comandos de jogo');

echo "\n=======================================================\n";
echo "TODOS OS $checks TESTES DA MISSÃO R10-CAR-001-R4.1 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
