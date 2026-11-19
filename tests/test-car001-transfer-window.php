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

echo "=== INICIANDO SUÍTE DE TESTES R10-CAR-001-R3: JANELA DE TRANSFERÊNCIAS E ROLETA ===\n\n";

// Helper para capturar a saída HTML de TransferMarket::render($g)
if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form method="post" action="' . e($path) . '"><input type="hidden" name="csrf" value="fixture-csrf">';
        if ($g) {
            echo '<input type="hidden" name="version" value="' . e($g['version']) . '"><input type="hidden" name="key" value="fixture-idempotency-key">';
        }
    }
}

function renderTransferWindow(array $g): string {
    ob_start();
    TransferMarket::render($g);
    return ob_get_clean() ?: '';
}

// Fixture base com schema 2
$baseScenarios = [
    ['id' => 0, 'nome' => 'Sem interesse', 'peso' => 20, 'percent' => 20.0, 'eligible' => true],
    ['id' => 1, 'nome' => 'Uma proposta', 'peso' => 35, 'percent' => 35.0, 'eligible' => true],
    ['id' => 2, 'nome' => 'Duas propostas', 'peso' => 25, 'percent' => 25.0, 'eligible' => true],
    ['id' => 3, 'nome' => 'Mercado nacional', 'peso' => 20, 'percent' => 20.0, 'eligible' => true],
    ['id' => 4, 'nome' => 'Mercado continental', 'peso' => 0, 'percent' => 0.0, 'eligible' => false],
    ['id' => 5, 'nome' => 'Mercado internacional', 'peso' => 0, 'percent' => 0.0, 'eligible' => false],
    ['id' => 6, 'nome' => 'Destaque internacional', 'peso' => 0, 'percent' => 0.0, 'eligible' => false]
];

$gBase = [
    'id' => 301,
    'name' => 'Menino da Vila',
    'club' => 'Santos-SP',
    'position' => 'meia',
    'season' => 2,
    'version' => 20,
    'stage' => 'oferta',
    'official' => true,
    'skills' => [
        'finalizacao' => ['value' => 70, 'xp' => 0],
        'passe' => ['value' => 76, 'xp' => 0],
        'drible' => ['value' => 74, 'xp' => 0]
    ],
    'states' => ['confianca' => 65, 'energia' => 85],
    'market_draw' => [
        'schema' => 2,
        'id' => 'market-draw-test-uuid-1234',
        'season' => 2,
        'scenario' => 1,
        'scenarios' => $baseScenarios,
        'evaluation' => ['weighted_score' => 0.8, 'confidence' => 0.7],
        'offers' => [],
        'stay' => [
            'club' => 'Santos-SP',
            'overall' => 72.5,
            'forecast' => [
                'scheduled_games' => 38,
                'expected_games' => 30,
                'minutes_percent' => 78.5,
                'expected_minutes' => 2685,
                'role' => 'Titular provável',
                'short_calendar' => false,
                'basis' => 'Base Santos-SP'
            ]
        ],
        'candidate_count' => 12
    ],
    'offers' => []
];

// -------------------------------------------------------------
// 1. Cenário sem propostas (scenario === 0 ou offers vazias)
// -------------------------------------------------------------
echo "1. Validando Cenário Sem Propostas...\n";
$gNoOffers = $gBase;
$gNoOffers['market_draw']['scenario'] = 0;
$gNoOffers['market_draw']['offers'] = [];
$gNoOffers['offers'] = [];

$htmlNoOffers = renderTransferWindow($gNoOffers);
check(str_contains($htmlNoOffers, 'Nenhuma proposta se concretizou nesta janela.'), 'Informa ausência de propostas de forma explícita');
check(str_contains($htmlNoOffers, 'Cenário sorteado: Sem interesse'), 'Identifica cenário sorteado como Sem interesse');
check(str_contains($htmlNoOffers, 'seu vínculo com o <b>Santos-SP</b> permanece garantido'), 'Mensagem positiva sem conotação de fracasso');
check(str_contains($htmlNoOffers, 'Permanecer no meu clube'), 'Opção de permanência disponível no cenário sem propostas');
check(str_contains($htmlNoOffers, 'btn-stay-priority') && str_contains($htmlNoOffers, 'btn-primary'), 'Botão de permanência recebe destaque prioritário quando não há ofertas');
check(str_contains($htmlNoOffers, 'name="accept" value="no"'), 'Ação de permanência submete accept=no');
check(!str_contains($htmlNoOffers, 'Aceitar proposta'), 'Não oferece aceite de proposta inexistente');

// -------------------------------------------------------------
// 2. Cenário com uma proposta
// -------------------------------------------------------------
echo "\n2. Validando Cenário com Uma Proposta...\n";
$offer1 = [
    'club' => 'Palmeiras-SP',
    'country' => 'BR',
    'city' => 'São Paulo',
    'competition' => 'br-a',
    'overall' => 78.0,
    'overall_delta' => 5.5,
    'selected' => false,
    'forecast' => [
        'scheduled_games' => 42,
        'expected_games' => 25,
        'minutes_percent' => 55.0,
        'expected_minutes' => 2079,
        'role' => 'Disputa por titularidade',
        'short_calendar' => false,
        'basis' => 'Base Palmeiras'
    ]
];
$gOne = $gBase;
$gOne['market_draw']['scenario'] = 1;
$gOne['market_draw']['offers'] = [$offer1];
$gOne['offers'] = [$offer1];

$htmlOne = renderTransferWindow($gOne);
check(str_contains($htmlOne, 'Cenário sorteado: Uma proposta'), 'Identifica cenário sorteado como Uma proposta');
check(str_contains($htmlOne, 'Propostas Oficiais Disponíveis (1)'), 'Contador exibe exatamente 1 proposta disponível');
check(str_contains($htmlOne, 'Palmeiras-SP'), 'Exibe nome do clube da proposta');
check(str_contains($htmlOne, 'São Paulo'), 'Exibe cidade do clube da proposta');
check(str_contains($htmlOne, 'Brasileirão Série A'), 'Exibe competição do clube da proposta');
check(str_contains($htmlOne, 'Overall 78'), 'Exibe overall do clube da proposta');
check(str_contains($htmlOne, 'Disputa por titularidade'), 'Exibe papel projetado por OpportunityForecast');
check(str_contains($htmlOne, 'Aceitar proposta'), 'Botão de aceite presente');
check(str_contains($htmlOne, 'name="option" value="0"'), 'Formulário contém input de opção com índice 0');
check(str_contains($htmlOne, 'Permanecer no meu clube'), 'Botão de permanência também continua disponível');

// -------------------------------------------------------------
// 3. Cenário com duas propostas
// -------------------------------------------------------------
echo "\n3. Validando Cenário com Duas Propostas...\n";
$offer2 = [
    'club' => 'Grêmio-RS',
    'country' => 'BR',
    'city' => 'Porto Alegre',
    'competition' => 'br-a',
    'overall' => 74.0,
    'overall_delta' => 1.5,
    'selected' => false,
    'forecast' => [
        'scheduled_games' => 38,
        'expected_games' => 32,
        'minutes_percent' => 80.0,
        'expected_minutes' => 2736,
        'role' => 'Titular provável',
        'short_calendar' => false,
        'basis' => 'Base Grêmio'
    ]
];
$gTwo = $gBase;
$gTwo['market_draw']['scenario'] = 2;
$gTwo['market_draw']['offers'] = [$offer1, $offer2];
$gTwo['offers'] = [$offer1, $offer2];

$htmlTwo = renderTransferWindow($gTwo);
check(str_contains($htmlTwo, 'Cenário sorteado: Duas propostas'), 'Identifica cenário sorteado como Duas propostas');
check(str_contains($htmlTwo, 'Propostas Oficiais Disponíveis (2)'), 'Contador exibe exatamente 2 propostas');
check(str_contains($htmlTwo, 'OPÇÃO 01') && str_contains($htmlTwo, 'OPÇÃO 02'), 'Cards numerados para as duas opções');
check(str_contains($htmlTwo, 'name="option" value="0"') && str_contains($htmlTwo, 'name="option" value="1"'), 'Formulários possuem índices 0 e 1 distintos');
check(substr_count($htmlTwo, 'Aceitar proposta') === 2, 'Dois botões de aceite (um para cada oferta)');
check(substr_count($htmlTwo, 'Permanecer no meu clube') === 1, 'Exatamente um botão de permanência');

// -------------------------------------------------------------
// 4. Cenário com três propostas
// -------------------------------------------------------------
echo "\n4. Validando Cenário com Três Propostas...\n";
$offer3 = [
    'club' => 'Cruzeiro-MG',
    'country' => 'BR',
    'city' => 'Belo Horizonte',
    'competition' => 'br-a',
    'overall' => 73.0,
    'overall_delta' => 0.5,
    'selected' => false,
    'forecast' => [
        'scheduled_games' => 38,
        'expected_games' => 28,
        'minutes_percent' => 70.0,
        'expected_minutes' => 2394,
        'role' => 'Titular provável',
        'short_calendar' => false,
        'basis' => 'Base Cruzeiro'
    ]
];
$gThree = $gBase;
$gThree['market_draw']['scenario'] = 3;
$gThree['market_draw']['offers'] = [$offer1, $offer2, $offer3];
$gThree['offers'] = [$offer1, $offer2, $offer3];

$htmlThree = renderTransferWindow($gThree);
check(str_contains($htmlThree, 'Propostas Oficiais Disponíveis (3)'), 'Contador exibe exatamente 3 propostas');
check(str_contains($htmlThree, 'OPÇÃO 03'), 'Opção 03 renderizada');
check(str_contains($htmlThree, 'Cruzeiro-MG'), 'Cruzeiro presente');
check(str_contains($htmlThree, 'name="option" value="2"'), 'Índice 2 configurado no terceiro card');
check(substr_count($htmlThree, 'Aceitar proposta') === 3, 'Três botões de aceite renderizados');

// -------------------------------------------------------------
// 5. Propostas de diferentes países (Internacionais)
// -------------------------------------------------------------
echo "\n5. Validando Propostas Internacionais e Nacionais...\n";
$offerForeign = [
    'club' => 'Sporting-PT',
    'country' => 'PT',
    'city' => 'Lisboa',
    'competition' => 'pt-1',
    'overall' => 80.0,
    'overall_delta' => 7.5,
    'selected' => false,
    'forecast' => [
        'scheduled_games' => 34,
        'expected_games' => 20,
        'minutes_percent' => 50.0,
        'expected_minutes' => 1530,
        'role' => 'Disputa por titularidade',
        'short_calendar' => false,
        'basis' => 'Base Sporting'
    ]
];
$gInternational = $gBase;
$intlScenarios = $baseScenarios;
$intlScenarios[5]['peso'] = 20;
$intlScenarios[5]['percent'] = 20.0;
$intlScenarios[5]['eligible'] = true;
$gInternational['market_draw']['scenarios'] = $intlScenarios;
$gInternational['market_draw']['scenario'] = 5;
$gInternational['market_draw']['offers'] = [$offerForeign];
$gInternational['offers'] = [$offerForeign];

$htmlIntl = renderTransferWindow($gInternational);
check(str_contains($htmlIntl, 'Portugal'), 'País internacional Portugal renderizado via EntryCountries');
check(str_contains($htmlIntl, 'Lisboa'), 'Cidade Lisboa renderizada');
check(str_contains($htmlIntl, 'Sporting-PT'), 'Nome do clube internacional correto');
check(str_contains($htmlIntl, 'Liga Portugal'), 'Nome da liga de Portugal obtido do catálogo');

// -------------------------------------------------------------
// 6. Permanência no clube como decisão legítima
// -------------------------------------------------------------
echo "\n6. Validando Permanência no Clube...\n";
check(str_contains($htmlOne, 'Permanecer em Santos-SP'), 'Card de permanência cita clube atual');
check(str_contains($htmlOne, 'Overall <b>72.5</b>') || str_contains($htmlOne, 'Overall <b>72,5</b>'), 'Card de permanência cita overall do clube atual');
check(str_contains($htmlOne, 'Titular provável'), 'Projeção de minutos no clube atual visível');
check(str_contains($htmlOne, 'name="accept" value="no"'), 'Submissão de permanência envia accept=no');

// -------------------------------------------------------------
// 7. Estrutura correta do formulário de aceite e rotas
// -------------------------------------------------------------
echo "\n7. Validando Estrutura de Formulários e Rotas...\n";
check(str_contains($htmlOne, 'action="/transferencia/responder"'), 'Ação POST aponta para /transferencia/responder');
check(str_contains($htmlOne, 'name="csrf"'), 'Campo CSRF presente');
check(str_contains($htmlOne, 'name="version" value="20"'), 'Campo de versão preservado');
check(str_contains($htmlOne, 'name="key"'), 'Campo de idempotência key presente');
check(str_contains($htmlOne, 'name="accept" value="yes"'), 'Botão de aceite envia accept=yes');

// -------------------------------------------------------------
// 8. Integridade dos percentuais apresentados
// -------------------------------------------------------------
echo "\n8. Validando Integridade dos Percentuais e Explicação...\n";
check(str_contains($htmlOne, 'Probabilidades da Roleta de Mercado'), 'Painel de probabilidades presente');
check(str_contains($htmlOne, 'Estes percentuais representam os cenários elegíveis do sorteio na roleta de mercado'), 'Aviso explicativo obrigatório presente');
check(str_contains($htmlOne, 'Sem interesse') && str_contains($htmlOne, '20,0%'), 'Percentual de Sem interesse (20,0%)');
check(str_contains($htmlOne, 'Uma proposta') && str_contains($htmlOne, '35,0%'), 'Percentual de Uma proposta (35,0%)');
check(str_contains($htmlOne, 'Duas propostas') && str_contains($htmlOne, '25,0%'), 'Percentual de Duas propostas (25,0%)');
check(str_contains($htmlOne, 'Mercado nacional') && str_contains($htmlOne, '20,0%'), 'Percentual de Mercado nacional (20,0%)');
check(str_contains($htmlOne, 'drawn-badge') || str_contains($htmlOne, 'Sorteado'), 'Destaque visual do cenário sorteado');

// -------------------------------------------------------------
// 9. Roleta persistida após F5 (sem re-sorteio)
// -------------------------------------------------------------
echo "\n9. Validando Roleta Persistida e Ausência de Re-sorteio...\n";
$htmlFirst = renderTransferWindow($gOne);
$htmlSecond = renderTransferWindow($gOne);
check(str_contains($htmlFirst, 'data-wheel-id="market:market-draw-test-uuid-1234"'), 'Wheel utiliza ID original do sorteio');
check(str_contains($htmlSecond, 'data-wheel-id="market:market-draw-test-uuid-1234"'), 'Wheel mantém mesmo ID após segundo render (F5)');
check(str_contains($htmlFirst, 'Cenário sorteado: Uma proposta') && str_contains($htmlSecond, 'Cenário sorteado: Uma proposta'), 'Cenário permanece rigorosamente o mesmo');

// -------------------------------------------------------------
// 10. Renderização sem mutação de estado (Idempotência)
// -------------------------------------------------------------
echo "\n10. Validando Imutabilidade de Estado na Renderização...\n";
$snapshotBefore = serialize($gOne);
$rendered = renderTransferWindow($gOne);
$snapshotAfter = serialize($gOne);
check($snapshotBefore === $snapshotAfter, 'Renderizar a janela de transferências não altera o estado da carreira');

// -------------------------------------------------------------
// 11. Compatibilidade com Esquema Legado (Schema 1)
// -------------------------------------------------------------
echo "\n11. Validando Compatibilidade com Esquema Legado (Schema 1)...\n";
$gLegacy = [
    'id' => 302,
    'name' => 'Craque Legado',
    'club' => 'Santos-SP',
    'position' => 'atacante',
    'season' => 1,
    'version' => 10,
    'stage' => 'oferta',
    'market_draw' => [
        'schema' => 1,
        'id' => 'legacy-draw-99',
        'distribution' => ['limited' => false]
    ],
    'offers' => [
        ['club' => 'Palmeiras-SP', 'tier' => 1, 'peso' => 50, 'selected' => true, 'country' => 'BR', 'overall' => 77]
    ]
];

$htmlLegacy = renderTransferWindow($gLegacy);
check(str_contains($htmlLegacy, 'Aceitar proposta'), 'Schema legado mantém botão de aceite');
check(str_contains($htmlLegacy, 'Permanecer no meu clube'), 'Schema legado mantém botão de permanência');
check(str_contains($htmlLegacy, 'Palmeiras-SP'), 'Schema legado renderiza clube sorteado');

// Schema legado sem ofertas
$gLegacyEmpty = $gLegacy;
$gLegacyEmpty['offers'] = [];
$htmlLegacyEmpty = renderTransferWindow($gLegacyEmpty);
check(str_contains($htmlLegacyEmpty, 'Nenhuma proposta se concretizou'), 'Schema legado sem ofertas exibe mensagem');
check(str_contains($htmlLegacyEmpty, 'Permanecer no meu clube'), 'Schema legado sem ofertas permite permanecer');

// -------------------------------------------------------------
// 12. Layout responsivo e texto sobre início de nova temporada
// -------------------------------------------------------------
echo "\n12. Validando Diretrizes de Contexto e Nova Temporada...\n";
check(str_contains($htmlOne, 'A nova temporada começará somente após sua decisão contratual'), 'Mensagem esclarece que a nova temporada só inicia após a decisão');
check(str_contains($htmlOne, 'transfer-intro-text'), 'Classe de introdução presente');
check(str_contains($htmlOne, 'market-scenarios-grid'), 'Grid responsivo de cenários presente');
check(str_contains($htmlOne, 'transfer-offer-card'), 'Cards individuais presentes');

echo "\n=======================================================\n";
echo "TODOS OS $checks TESTES DA MISSÃO R10-CAR-001-R3 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
