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

echo "=== INICIANDO SUÍTE DE TESTES R10-CAR-001-R1.1: FIDELIDADE DOS RESULTADOS ===\n\n";

// Helper para capturar saída HTML de CompetitionView::recap($g)
function renderRecap(array $g): string {
    $g['matches'] = $g['matches'] ?? [];
    ob_start();
    CompetitionView::recap($g);
    return ob_get_clean() ?: '';
}

// -------------------------------------------------------------
// 1. Cenário A: Clube campeão comprovado
// -------------------------------------------------------------
echo "1. Validando Cenário A: Clube campeão comprovado...\n";
$gChampion = [
    'id' => 101,
    'name' => 'Craque Campeão',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 5,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1', 'br-a'],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => 'Santos-SP',
            'promoted' => false,
            'relegated' => false,
            'position' => 1,
            'group' => null,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => [
        'sp-a1' => [
            'id' => 'sp-a1',
            'phase' => 'final',
            'done' => true,
            'champion' => 'Santos-SP',
            'ties' => [],
            'groups' => [['Santos-SP', 'Palmeiras-SP']],
            'matches' => []
        ]
    ]
];

$htmlA = renderRecap($gChampion);
check(str_contains($htmlA, 'TÍTULO CONQUISTADO'), 'Cenário A exibe tag com destaque de título conquistado');
check(str_contains($htmlA, 'É campeão! Santos-SP conquista o título!'), 'Cenário A exibe título celebrativo com nome do clube');
check(str_contains($htmlA, 'Seu clube levantou a taça de campeão de <b>Paulistão A1</b>'), 'Cenário A confirma que o clube levantou a taça da competição');
check(str_contains($htmlA, 'action="/competicao/continuar"'), 'Cenário A preserva rota POST /competicao/continuar');

// -------------------------------------------------------------
// 2. Cenário B: Clube não campeão (posição na primeira fase sem mata-mata fictício)
// -------------------------------------------------------------
echo "\n2. Validando Cenário B: Clube não campeão (posição na primeira fase sem classificação final fictícia)...\n";
$gRunnerUp = [
    'id' => 102,
    'name' => 'Craque Vice',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 7,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1', 'br-a'],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => 'Palmeiras-SP',
            'promoted' => false,
            'relegated' => false,
            'position' => 2,
            'group' => null,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => [
        'sp-a1' => [
            'id' => 'sp-a1',
            'phase' => 'final',
            'done' => true,
            'champion' => 'Palmeiras-SP',
            'ties' => [],
            'groups' => [['Palmeiras-SP', 'Santos-SP']],
            'matches' => []
        ]
    ]
];

$htmlB = renderRecap($gRunnerUp);
check(str_contains($htmlB, 'COMPETIÇÃO ENCERRADA'), 'Cenário B exibe tag padrão de competição encerrada');
check(str_contains($htmlB, 'O campeonato terminou · Paulistão A1'), 'Cenário B exibe cabeçalho factual com nome da competição');
check(str_contains($htmlB, 'Campeão da edição: <b>Palmeiras-SP</b>'), 'Cenário B exibe o campeão efetivo da edição');
check(str_contains($htmlB, 'Seu clube terminou em <b>2º lugar</b> na primeira fase.'), 'Cenário B exibe posição na primeira fase com semântica conservadora');
check(!str_contains($htmlB, 'classificação final'), 'Cenário B NÃO afirma classificação final quando dado deriva da primeira fase');
check(!str_contains($htmlB, 'vice-campeão'), 'Cenário B NÃO inventa vice-campeonato sem dados');
check(!str_contains($htmlB, 'É campeão! Santos-SP'), 'Cenário B não atribui título ao clube não campeão');

// -------------------------------------------------------------
// 3. Cenário C: Acesso e Rebaixamento (com ressalvas regulamentares)
// -------------------------------------------------------------
echo "\n3. Validando Cenário C: Acesso e rebaixamento com ressalvas...\n";
$gPromoted = [
    'id' => 103,
    'name' => 'Craque do Acesso',
    'club' => 'São Caetano-SP',
    'season' => 1,
    'version' => 10,
    'competition_id' => 'sp-a2',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a2'],
    'competition_results' => [
        'sp-a2' => [
            'name' => 'Paulistão A2',
            'champion' => 'Ferroviária-SP',
            'promoted' => true,
            'relegated' => false,
            'position' => 2,
            'group' => null,
            'access_pending' => true,
            'continuity' => ['valid' => true, 'simulation_ceiling' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => [
        'sp-a2' => [
            'id' => 'sp-a2',
            'phase' => 'final',
            'done' => true,
            'champion' => 'Ferroviária-SP',
            'ties' => [],
            'groups' => [['Ferroviária-SP', 'São Caetano-SP']],
            'matches' => []
        ]
    ]
];

$htmlC1 = renderRecap($gPromoted);
check(str_contains($htmlC1, 'Acesso conquistado!'), 'Cenário C1 exibe destaque positivo de acesso');
check(str_contains($htmlC1, 'São Caetano-SP') && str_contains($htmlC1, 'garantiu vaga na divisão superior'), 'Cenário C1 cita clube promovido');
check(str_contains($htmlC1, 'próxima temporada repetirá a divisão cadastrada'), 'Cenário C1 preserva ressalva de access_pending');
check(str_contains($htmlC1, 'A divisão superior ainda não está disponível no jogo'), 'Cenário C1 preserva ressalva de simulation_ceiling');

$gRelegated = [
    'id' => 104,
    'name' => 'Craque em Crise',
    'club' => 'Água Santa-SP',
    'season' => 1,
    'version' => 12,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1'],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => 'Corinthians-SP',
            'promoted' => false,
            'relegated' => true,
            'position' => 16,
            'group' => null,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => true,
            'titles' => []
        ]
    ],
    'competitions' => [
        'sp-a1' => [
            'id' => 'sp-a1',
            'phase' => 'liga',
            'done' => true,
            'champion' => 'Corinthians-SP',
            'ties' => [],
            'groups' => [['Corinthians-SP', 'Água Santa-SP']],
            'matches' => []
        ]
    ]
];

$htmlC2 = renderRecap($gRelegated);
check(str_contains($htmlC2, 'Rebaixamento:'), 'Cenário C2 exibe status de rebaixamento');
check(str_contains($htmlC2, 'Água Santa-SP') && str_contains($htmlC2, 'terminou na zona de descenso'), 'Cenário C2 cita clube rebaixado');
check(str_contains($htmlC2, 'A terceira divisão ainda não está disponível; permanecer no clube repete a divisão cadastrada'), 'Cenário C2 preserva ressalva de relegation_pending');

// -------------------------------------------------------------
// 4. Cenário D: Próxima competição na temporada
// -------------------------------------------------------------
echo "\n4. Validando Cenário D: Próxima competição na temporada...\n";
$gNextComp = $gChampion;
$htmlD = renderRecap($gNextComp);
check(str_contains($htmlD, 'Próximo desafio:'), 'Cenário D destaca bloco de próximo desafio');
check(str_contains($htmlD, 'Agora vem o <b>Brasileirão Série A</b>'), 'Cenário D informa explicitamente o próximo campeonato');
check(str_contains($htmlD, 'Começar próximo campeonato: Brasileirão Série A →'), 'Cenário D exibe botão direcionado com nome do próximo campeonato');

// -------------------------------------------------------------
// 5. Cenário E: Última competição da temporada
// -------------------------------------------------------------
echo "\n5. Validando Cenário E: Última competição da temporada...\n";
$gLastComp = [
    'id' => 105,
    'name' => 'Craque Fim de Ano',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 25,
    'competition_id' => 'br-a',
    'competition_cursor' => 1,
    'season_competitions' => ['sp-a1', 'br-a'],
    'competition_results' => [
        'br-a' => [
            'name' => 'Brasileirão Série A',
            'champion' => 'Flamengo-RJ',
            'promoted' => false,
            'relegated' => false,
            'position' => 3,
            'group' => null,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => [
        'br-a' => [
            'id' => 'br-a',
            'phase' => 'liga',
            'done' => true,
            'champion' => 'Flamengo-RJ',
            'ties' => [],
            'groups' => [['Flamengo-RJ', 'Santos-SP']],
            'matches' => []
        ]
    ]
];

$htmlE = renderRecap($gLastComp);
check(str_contains($htmlE, 'Calendário encerrado:'), 'Cenário E informa encerramento do calendário de competições do ano');
check(str_contains($htmlE, 'Concluir temporada e ver retrospectiva anual →'), 'Cenário E exibe botão de conclusão de temporada e retrospectiva anual');
check(!str_contains($htmlE, 'Começar próximo campeonato:'), 'Cenário E não exibe botão de próximo campeonato');

// -------------------------------------------------------------
// 6. Eliminação Antecipada em Mata-Mata e Competições com Grupos
// -------------------------------------------------------------
echo "\n6. Validando eliminação antecipada e torneios com grupos...\n";
$gEliminatedGroup = [
    'id' => 106,
    'name' => 'Craque Eliminado',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 18,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1', 'br-a'],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => 'São Paulo-SP',
            'promoted' => false,
            'relegated' => false,
            'position' => 3,
            'group' => 2,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => [
        'sp-a1' => [
            'id' => 'sp-a1',
            'phase' => 'final',
            'done' => true,
            'champion' => 'São Paulo-SP',
            'ties' => [],
            'groups' => [['São Paulo-SP'], ['Santos-SP']],
            'matches' => []
        ]
    ]
];

$htmlElim = renderRecap($gEliminatedGroup);
check(str_contains($htmlElim, 'Campeão da edição: <b>São Paulo-SP</b>'), 'Eliminação antecipada exibe campeão efetivo do torneio');
check(str_contains($htmlElim, 'Seu clube terminou em <b>3º lugar</b> no Grupo 2.'), 'Torneio com grupos exibe colocação específica no grupo');
check(!str_contains($htmlElim, 'classificação final'), 'Torneio com grupos NÃO afirma classificação final');
check(!str_contains($htmlElim, 'vice-campeão'), 'Eliminação não afirma vice-campeonato fictício');
check(!str_contains($htmlElim, 'semifinal'), 'Eliminação não afirma fase inventada sem comprovação');

// -------------------------------------------------------------
// 7. Correção R1.1 A: Resultado Ausente (Sem position => 1 inventada)
// -------------------------------------------------------------
echo "\n7. Validando Correção R1.1 A: Resultado ausente sem primeira colocação fictícia...\n";
$gMissingResult = [
    'id' => 108,
    'name' => 'Craque Sem Resultado',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 3,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 0,
    'season_competitions' => ['sp-a1', 'br-a'],
    'competition_results' => [], // NENHUM resultado consolidado para sp-a1
    'competitions' => []
];

$htmlMissing = renderRecap($gMissingResult);
check(str_contains($htmlMissing, 'COMPETIÇÃO ENCERRADA'), 'Resultado ausente exibe tag neutra de competição encerrada');
check(str_contains($htmlMissing, 'O campeonato terminou · Paulistão A1'), 'Resultado ausente exibe nome da competição a partir do catálogo');
check(str_contains($htmlMissing, 'O resultado detalhado desta competição não está disponível nos dados da carreira.'), 'Resultado ausente exibe mensagem neutra sem invenções');
check(!str_contains($htmlMissing, '1º lugar'), 'Resultado ausente NÃO inventa 1º lugar');
check(!str_contains($htmlMissing, 'Campeão da edição:'), 'Resultado ausente NÃO inventa campeão da edição');
check(!str_contains($htmlMissing, 'É campeão!'), 'Resultado ausente NÃO atribui título');
check(!str_contains($htmlMissing, 'Acesso conquistado'), 'Resultado ausente NÃO inventa acesso');
check(!str_contains($htmlMissing, 'Rebaixamento:'), 'Resultado ausente NÃO inventa rebaixamento');
check(str_contains($htmlMissing, 'Começar próximo campeonato: Brasileirão Série A →'), 'Resultado ausente preserva continuidade quando há próximo campeonato');

// -------------------------------------------------------------
// 8. Correção R1.1 B: Campeão Desconhecido (Sem campeão inventado)
// -------------------------------------------------------------
echo "\n8. Validando Correção R1.1 B: Campeão vazio/desconhecido sem atribuição inventada...\n";
$gUnknownChampion = [
    'id' => 109,
    'name' => 'Craque Campeão Oculto',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 4,
    'competition_id' => 'sp-a1',
    'competition_cursor' => 1,
    'season_competitions' => ['sp-a1'],
    'competition_results' => [
        'sp-a1' => [
            'name' => 'Paulistão A1',
            'champion' => '', // Campeão não identificado
            'promoted' => false,
            'relegated' => false,
            'position' => 4,
            'group' => null,
            'access_pending' => false,
            'continuity' => ['valid' => true],
            'relegation_pending' => false,
            'titles' => []
        ]
    ],
    'competitions' => []
];

$htmlUnknown = renderRecap($gUnknownChampion);
check(!str_contains($htmlUnknown, 'Campeão da edição:'), 'Campeão desconhecido não renderiza linha de campeão inventado');
check(str_contains($htmlUnknown, 'Seu clube terminou em <b>4º lugar</b> na primeira fase.'), 'Campeão desconhecido preserva posição comprovada na primeira fase');
check(str_contains($htmlUnknown, 'Concluir temporada e ver retrospectiva anual →'), 'Campeão desconhecido preserva botão para última competição');

// -------------------------------------------------------------
// 9. Retomada e Renderização sem mutação de estado (Idempotência)
// -------------------------------------------------------------
echo "\n9. Validando que a renderização não altera o estado da carreira (idempotência)...\n";
$gStateBefore = $gChampion;
$snapshotBefore = serialize($gStateBefore);

$htmlFirst = renderRecap($gChampion);
$snapshotAfter1 = serialize($gChampion);
check($snapshotBefore === $snapshotAfter1, 'Primeira renderização não altera o estado da carreira');

$htmlSecond = renderRecap($gChampion);
$snapshotAfter2 = serialize($gChampion);
check($snapshotBefore === $snapshotAfter2, 'Segunda renderização (simulando F5) preserva estado intacto');
check(strlen($htmlFirst) > 500 && strlen($htmlSecond) > 500, 'Renderizações consecutivas geram HTML completo');

// -------------------------------------------------------------
// 10. Compatibilidade com saves legados
// -------------------------------------------------------------
echo "\n10. Validando compatibilidade com saves legados...\n";
$gLegacy = [
    'id' => 110,
    'name' => 'Craque Legado',
    'club' => 'Santos-SP',
    'season' => 1,
    'version' => 1,
    'competition_id' => 'legacy',
    'competition_cursor' => 0,
    'season_competitions' => ['legacy'],
    'competitions' => []
];

$htmlLegacy = renderRecap($gLegacy);
check(str_contains($htmlLegacy, 'COMPETIÇÃO ENCERRADA'), 'Save legado renderiza painel de encerramento sem fatal error');
check(str_contains($htmlLegacy, 'O resultado detalhado desta competição não está disponível nos dados da carreira.'), 'Save legado sem resultado estruturado exibe aviso neutro em vez de 1º lugar');
check(str_contains($htmlLegacy, 'action="/competicao/continuar"'), 'Save legado possui formulário funcional com rota /competicao/continuar');
check(str_contains($htmlLegacy, 'Concluir temporada e ver retrospectiva anual →'), 'Save legado com uma competição única aponta para encerramento de temporada');

echo "\n=======================================================\n";
echo "TODOS OS $checks TESTES DA MISSÃO R10-CAR-001-R1.1 PASSARAM COM SUCESSO!\n";
echo "=======================================================\n";
