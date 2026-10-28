<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function test_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FALHA NA ASSERÇÃO: $message");
    }
    echo "  [OK] $message\n";
}

echo "=== Rumo10 - Entrega 61: Testes de Isolamento, Invalidação por Referência e Equivalência ===\n\n";

// -------------------------------------------------------------
// 1. Cache desligado fora do fechamento
// -------------------------------------------------------------
echo "1. Testando cache desligado fora do fechamento...\n";
test_assert(!TeamStrength::isRatingCacheActive(), "Cache deve estar desligado inicialmente");
$metrics = TeamStrength::ratingCacheMetrics();
test_assert(!$metrics['active'] && $metrics['hits'] === 0 && $metrics['misses'] === 0, "Métricas fora do cache devem ser zero");

$gInitial = [
    'season' => 2026,
    'club' => 'Flamengo',
    'club_strengths' => ['Flamengo' => 52.0],
    'matches' => [],
];
$rNormal1 = TeamStrength::rating('Flamengo', $gInitial);
$rNormal2 = TeamStrength::rating('Flamengo', $gInitial);
test_assert($rNormal1 === $rNormal2, "Ratings sem cache devem ser consistentes");
test_assert(!TeamStrength::isRatingCacheActive(), "Cache deve continuar desligado após chamadas padrão");

// -------------------------------------------------------------
// 2. Cache ativado no contexto correto
// -------------------------------------------------------------
echo "\n2. Testando ativação no contexto correto...\n";
TeamStrength::openRatingCache($gInitial);
test_assert(TeamStrength::isRatingCacheActive(), "Cache deve estar ativo após openRatingCache");
$metrics = TeamStrength::ratingCacheMetrics();
test_assert($metrics['active'] && $metrics['hits'] === 0 && $metrics['misses'] === 0, "Métricas devem iniciar zeradas no topo da pilha");

// -------------------------------------------------------------
// 3. Repetição de rating em estado imutável (Reutilização correta)
// -------------------------------------------------------------
echo "\n3. Testando repetição de rating em estado imutável...\n";
$rCached1 = TeamStrength::rating('Flamengo', $gInitial);
test_assert($rCached1 === $rNormal1, "Primeiro rating com cache deve ser idêntico ao sem cache");
$mAfter1 = TeamStrength::ratingCacheMetrics();
test_assert($mAfter1['misses'] === 1 && $mAfter1['hits'] === 0, "Primeira chamada deve ser cache miss");

$rCached2 = TeamStrength::rating('Flamengo', $gInitial);
test_assert($rCached2 === $rCached1, "Segunda chamada deve retornar exatamente o mesmo valor");
$mAfter2 = TeamStrength::ratingCacheMetrics();
test_assert($mAfter2['misses'] === 1 && $mAfter2['hits'] === 1, "Segunda chamada deve ser cache hit");

// -------------------------------------------------------------
// 4. Mudança legítima de força base
// -------------------------------------------------------------
echo "\n4. Testando mudança legítima de força base...\n";
$gModified = $gInitial;
$gModified['club_strengths']['Flamengo'] = 48.0; // dentro de [anchor-4, anchor+4] para anchor=50
$rStrengthChanged = TeamStrength::rating('Flamengo', $gModified);
$rExpectedStrength = TeamStrength::calculateRawRating('Flamengo', $gModified);
test_assert($rStrengthChanged === $rExpectedStrength, "Rating com força alterada deve refletir nova força");
test_assert($rStrengthChanged !== $rCached1, "Rating deve ser diferente do valor anterior com outra força");
$mAfterStrength = TeamStrength::ratingCacheMetrics();
test_assert($mAfterStrength['misses'] === 2, "Mudança de força deve forçar novo cálculo (miss)");
TeamStrength::closeRatingCache();

// -------------------------------------------------------------
// 5. Testes Obrigatórios de Referências Internas PHP (Codex)
// -------------------------------------------------------------
echo "\n5. Testando invalidação e reversão por referências internas PHP...\n";
$mkMatch = fn($h, $a, $hg, $ag, $s = 'finalizada') => ['home' => $h, 'away' => $a, 'hg' => $hg, 'ag' => $ag, 'status' => $s];

// 5a. Mutação do primeiro jogo por referência
$gRef1 = ['matches' => [
    $mkMatch('Audit61 A', 'B', 1, 0),
    $mkMatch('C', 'D', 0, 0)
]];
$aliasFirst =& $gRef1['matches'][0];
TeamStrength::openRatingCache($gRef1);
try {
    $rInit = TeamStrength::rating('Audit61 A', $gRef1);
    test_assert($rInit === 53.0, "Rating inicial com vitória 1-0 é 53.0");
    
    // Mutação por referência do primeiro jogo
    $aliasFirst['hg'] = 0;
    $aliasFirst['ag'] = 1;
    $rMutatedFirst = TeamStrength::rating('Audit61 A', $gRef1);
    $rawMutatedFirst = TeamStrength::calculateRawRating('Audit61 A', $gRef1);
    test_assert($rMutatedFirst === 47.0 && $rMutatedFirst === $rawMutatedFirst, "Mutação por referência do 1º jogo invalida e retorna 47.0");

    // 5b. Reversão do placar original pela mesma referência
    $aliasFirst['hg'] = 1;
    $aliasFirst['ag'] = 0;
    $rRevertedFirst = TeamStrength::rating('Audit61 A', $gRef1);
    $rawRevertedFirst = TeamStrength::calculateRawRating('Audit61 A', $gRef1);
    test_assert($rRevertedFirst === 53.0 && $rRevertedFirst === $rawRevertedFirst, "Reversão pela mesma referência restaura e retorna 53.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 5c. Mutação por referência em jogo intermediário
$gRefInter = ['matches' => [
    $mkMatch('X', 'Y', 0, 0),
    $mkMatch('Audit61 A', 'B', 1, 0),
    $mkMatch('C', 'D', 0, 0)
]];
$aliasInter =& $gRefInter['matches'][1];
TeamStrength::openRatingCache($gRefInter);
try {
    $rInitInter = TeamStrength::rating('Audit61 A', $gRefInter);
    test_assert($rInitInter === 53.0, "Rating inicial intermediário é 53.0");

    $aliasInter['hg'] = 0;
    $aliasInter['ag'] = 2;
    $rMutatedInter = TeamStrength::rating('Audit61 A', $gRefInter);
    $rawMutatedInter = TeamStrength::calculateRawRating('Audit61 A', $gRefInter);
    test_assert($rMutatedInter === 47.0 && $rMutatedInter === $rawMutatedInter, "Mutação por referência em jogo intermediário retorna 47.0");

    // Reversão intermediária
    $aliasInter['hg'] = 1;
    $aliasInter['ag'] = 0;
    $rRevertInter = TeamStrength::rating('Audit61 A', $gRefInter);
    $rawRevertInter = TeamStrength::calculateRawRating('Audit61 A', $gRefInter);
    test_assert($rRevertInter === 53.0 && $rRevertInter === $rawRevertInter, "Reversão em jogo intermediário retorna 53.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 5d. Mudança de status por referência
$gRefStatus = ['matches' => [
    $mkMatch('Audit61 A', 'B', 1, 0),
    $mkMatch('C', 'D', 0, 0)
]];
$aliasStatus =& $gRefStatus['matches'][0]['status'];
TeamStrength::openRatingCache($gRefStatus);
try {
    $rBeforeStatus = TeamStrength::rating('Audit61 A', $gRefStatus);
    $aliasStatus = 'agendada';
    $rAfterStatus = TeamStrength::rating('Audit61 A', $gRefStatus);
    $rawStatus = TeamStrength::calculateRawRating('Audit61 A', $gRefStatus);
    test_assert($rAfterStatus === 50.0 && $rAfterStatus === $rawStatus, "Mudança de status por referência retorna 50.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 5e. Mudança de participante por referência
$gRefClub = ['matches' => [
    $mkMatch('Audit61 A', 'B', 1, 0),
    $mkMatch('C', 'D', 0, 0)
]];
$aliasClub =& $gRefClub['matches'][0]['home'];
TeamStrength::openRatingCache($gRefClub);
try {
    $rBeforeClub = TeamStrength::rating('Audit61 A', $gRefClub);
    $aliasClub = 'Outro Clube';
    $rAfterClub = TeamStrength::rating('Audit61 A', $gRefClub);
    $rawClub = TeamStrength::calculateRawRating('Audit61 A', $gRefClub);
    test_assert($rAfterClub === 50.0 && $rAfterClub === $rawClub, "Mudança de participante por referência retorna 50.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 5f. Reordenação de partidas relevante
$gSix = ['matches' => [
    $mkMatch('Audit61 A', 'B', 1, 0),
    $mkMatch('Audit61 A', 'B', 0, 1),
    $mkMatch('Audit61 A', 'B', 0, 1),
    $mkMatch('Audit61 A', 'B', 0, 1),
    $mkMatch('Audit61 A', 'B', 0, 1),
    $mkMatch('Audit61 A', 'B', 0, 1),
    $mkMatch('C', 'D', 0, 0),
]];
TeamStrength::openRatingCache($gSix);
try {
    $rOrderBefore = TeamStrength::rating('Audit61 A', $gSix);
    test_assert($rOrderBefore === 47.0, "Rating com 5 derrotas nos últimos cinco jogos é 47.0");

    // Inverte o primeiro jogo (vitória) com o sexto jogo (derrota), fazendo a vitória entrar no slice(-5)
    $gSixReordered = $gSix;
    [$gSixReordered['matches'][0], $gSixReordered['matches'][5]] = [$gSixReordered['matches'][5], $gSixReordered['matches'][0]];
    $rOrderAfter = TeamStrength::rating('Audit61 A', $gSixReordered);
    $rawOrder = TeamStrength::calculateRawRating('Audit61 A', $gSixReordered);
    test_assert($rOrderAfter === 48.2 && $rOrderAfter === $rawOrder, "Reordenação de partidas invalida e retorna 48.2 (era 47.0)");
} finally {
    TeamStrength::closeRatingCache();
}

// -------------------------------------------------------------
// 6. Testes do Clube Inicialmente Ausente do Histórico (Auditoria Conclusiva Codex)
// -------------------------------------------------------------
echo "\n6. Testando transição de clube ausente -> presente -> ausente (com e sem referências)...\n";

// 6a. Atribuição normal (sem referência)
$gAbsentNormal = ['matches' => [
    ['home' => 'ClubeB', 'away' => 'ClubeC', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0]
]];
TeamStrength::openRatingCache($gAbsentNormal);
try {
    // 1. Clube ausente com rating inicial 50
    $rInitAbsent = TeamStrength::rating('Audit61 A', $gAbsentNormal);
    $rawInitAbsent = TeamStrength::calculateRawRating('Audit61 A', $gAbsentNormal);
    test_assert($rInitAbsent === 50.0 && $rInitAbsent === $rawInitAbsent, "Clube ausente com rating inicial 50.0");

    // 2. Inclusão do clube em partida existente, resultado esperado 53
    $gAbsentNormal['matches'][0]['home'] = 'Audit61 A';
    $rInc = TeamStrength::rating('Audit61 A', $gAbsentNormal);
    $rawInc = TeamStrength::calculateRawRating('Audit61 A', $gAbsentNormal);
    test_assert($rInc === 53.0 && $rInc === $rawInc, "Inclusão de clube ausente em partida existente retorna 53.0");

    // 3. Derrota posterior, resultado esperado 47
    $gAbsentNormal['matches'][0]['hg'] = 0;
    $gAbsentNormal['matches'][0]['ag'] = 1;
    $rLoss = TeamStrength::rating('Audit61 A', $gAbsentNormal);
    $rawLoss = TeamStrength::calculateRawRating('Audit61 A', $gAbsentNormal);
    test_assert($rLoss === 47.0 && $rLoss === $rawLoss, "Derrota posterior de clube introduzido retorna 47.0");

    // 4. Remoção do clube do histórico (reversão ao estado original)
    $gAbsentNormal['matches'][0]['home'] = 'ClubeB';
    $gAbsentNormal['matches'][0]['hg'] = 1;
    $gAbsentNormal['matches'][0]['ag'] = 0;
    $rRevertAbsent = TeamStrength::rating('Audit61 A', $gAbsentNormal);
    $rawRevertAbsent = TeamStrength::calculateRawRating('Audit61 A', $gAbsentNormal);
    test_assert($rRevertAbsent === 50.0 && $rRevertAbsent === $rawRevertAbsent, "Remoção do clube e reversão ao estado original retorna 50.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 6b. Mesmos casos com referências PHP
$gAbsentRef = ['matches' => [
    ['home' => 'ClubeB', 'away' => 'ClubeC', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0],
    ['home' => 'ClubeD', 'away' => 'ClubeE', 'status' => 'finalizada', 'hg' => 0, 'ag' => 0],
]];
$aliasHome =& $gAbsentRef['matches'][0]['home'];
$aliasHg   =& $gAbsentRef['matches'][0]['hg'];
$aliasAg   =& $gAbsentRef['matches'][0]['ag'];

TeamStrength::openRatingCache($gAbsentRef);
try {
    $rInitRef = TeamStrength::rating('Audit61 A', $gAbsentRef);
    $rawInitRef = TeamStrength::calculateRawRating('Audit61 A', $gAbsentRef);
    test_assert($rInitRef === 50.0 && $rInitRef === $rawInitRef, "Ref: Clube ausente inicial com 50.0");

    // Inclusão por referência
    $aliasHome = 'Audit61 A';
    $rIncRef = TeamStrength::rating('Audit61 A', $gAbsentRef);
    $rawIncRef = TeamStrength::calculateRawRating('Audit61 A', $gAbsentRef);
    test_assert($rIncRef === 53.0 && $rIncRef === $rawIncRef, "Ref: Inclusão de clube ausente por referência retorna 53.0");

    // Derrota por referência
    $aliasHg = 0;
    $aliasAg = 1;
    $rLossRef = TeamStrength::rating('Audit61 A', $gAbsentRef);
    $rawLossRef = TeamStrength::calculateRawRating('Audit61 A', $gAbsentRef);
    test_assert($rLossRef === 47.0 && $rLossRef === $rawLossRef, "Ref: Derrota por referência retorna 47.0");

    // Remoção por referência (reversão)
    $aliasHome = 'ClubeB';
    $aliasHg = 1;
    $aliasAg = 0;
    $rRevRef = TeamStrength::rating('Audit61 A', $gAbsentRef);
    $rawRevRef = TeamStrength::calculateRawRating('Audit61 A', $gAbsentRef);
    test_assert($rRevRef === 50.0 && $rRevRef === $rawRevRef, "Ref: Remoção por referência e retorno a 50.0");
} finally {
    TeamStrength::closeRatingCache();
}

// 6c. Ordem de consulta: A antes de B vs B antes de A
$gOrderTest1 = ['matches' => [
    ['home' => 'ClubeB', 'away' => 'ClubeC', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0]
]];
TeamStrength::openRatingCache($gOrderTest1);
try {
    TeamStrength::rating('Audit61 A', $gOrderTest1); // A ausente
    $gOrderTest1['matches'][0]['home'] = 'Audit61 A';
    // Ordem: A antes de B
    $rA1 = TeamStrength::rating('Audit61 A', $gOrderTest1);
    $rB1 = TeamStrength::rating('ClubeB', $gOrderTest1);
    test_assert($rA1 === 53.0 && $rB1 === 50.0, "Ordem A antes de B: A=53.0, B=50.0");
    test_assert($rA1 === TeamStrength::calculateRawRating('Audit61 A', $gOrderTest1), "A igual ao raw na ordem A->B");
} finally {
    TeamStrength::closeRatingCache();
}

$gOrderTest2 = ['matches' => [
    ['home' => 'ClubeB', 'away' => 'ClubeC', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0]
]];
TeamStrength::openRatingCache($gOrderTest2);
try {
    TeamStrength::rating('Audit61 A', $gOrderTest2); // A ausente
    $gOrderTest2['matches'][0]['home'] = 'Audit61 A';
    // Ordem: B antes de A
    $rB2 = TeamStrength::rating('ClubeB', $gOrderTest2);
    $rA2 = TeamStrength::rating('Audit61 A', $gOrderTest2);
    test_assert($rB2 === 50.0 && $rA2 === 53.0, "Ordem B antes de A: B=50.0, A=53.0");
    test_assert($rA2 === TeamStrength::calculateRawRating('Audit61 A', $gOrderTest2), "A igual ao raw na ordem B->A");
} finally {
    TeamStrength::closeRatingCache();
}

// 6d. Alterações sucessivas mantendo a quantidade de partidas
$gSuccessive = ['matches' => [
    ['home' => 'X', 'away' => 'Y', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0]
]];
TeamStrength::openRatingCache($gSuccessive);
try {
    // Consulta inicial
    $r0 = TeamStrength::rating('Audit61 A', $gSuccessive);
    $raw0 = TeamStrength::calculateRawRating('Audit61 A', $gSuccessive);
    test_assert($r0 === 50.0 && $r0 === $raw0, "Sucessivo: A ausente inicial");

    // 1ª alteração: A joga e vence
    $gSuccessive['matches'][0] = ['home' => 'Audit61 A', 'away' => 'Y', 'status' => 'finalizada', 'hg' => 2, 'ag' => 0];
    $r1 = TeamStrength::rating('Audit61 A', $gSuccessive);
    $raw1 = TeamStrength::calculateRawRating('Audit61 A', $gSuccessive);
    test_assert($r1 === 53.0 && $r1 === $raw1, "Sucessivo 1: A entra e vence -> 53.0");

    // 2ª alteração: empate
    $gSuccessive['matches'][0]['ag'] = 2;
    $r2 = TeamStrength::rating('Audit61 A', $gSuccessive);
    $raw2 = TeamStrength::calculateRawRating('Audit61 A', $gSuccessive);
    test_assert($r2 === 49.0 && $r2 === $raw2, "Sucessivo 2: Empate -> 49.0");

    // 3ª alteração: derrota
    $gSuccessive['matches'][0]['ag'] = 3;
    $r3 = TeamStrength::rating('Audit61 A', $gSuccessive);
    $raw3 = TeamStrength::calculateRawRating('Audit61 A', $gSuccessive);
    test_assert($r3 === 47.0 && $r3 === $raw3, "Sucessivo 3: Derrota -> 47.0");

    // 4ª alteração: A substituído por Z
    $gSuccessive['matches'][0]['home'] = 'Z';
    $r4 = TeamStrength::rating('Audit61 A', $gSuccessive);
    $raw4 = TeamStrength::calculateRawRating('Audit61 A', $gSuccessive);
    test_assert($r4 === 50.0 && $r4 === $raw4, "Sucessivo 4: A sai -> 50.0");
} finally {
    TeamStrength::closeRatingCache();
}

// -------------------------------------------------------------
// 7. Mudança da Âncora 80 para 20 no Mesmo Escopo
// -------------------------------------------------------------
echo "\n7. Testando mudança da âncora 80 para 20 no mesmo escopo...\n";
TeamStrength::openRatingCache([]);
try {
    $rRef80 = Competition::referenceScope([], fn() => TeamStrength::rating('Audit61 A', []), ['Audit61 A' => ['reference_overall' => 80]]);
    test_assert($rRef80 === 80.0, "Primeira consulta com reference_overall=80 retorna 80.0");

    $rRef20 = Competition::referenceScope([], fn() => TeamStrength::rating('Audit61 A', []), ['Audit61 A' => ['reference_overall' => 20]]);
    $rawRef20 = Competition::referenceScope([], fn() => TeamStrength::calculateRawRating('Audit61 A', []), ['Audit61 A' => ['reference_overall' => 20]]);
    test_assert($rRef20 === 20.0 && $rRef20 === $rawRef20, "Mudança de âncora 80 para 20 no mesmo escopo retorna 20.0 (era 80)");
} finally {
    TeamStrength::closeRatingCache();
}

// -------------------------------------------------------------
// 7. Encerramento normal e por exceção em finally
// -------------------------------------------------------------
echo "\n7. Testando encerramento normal e por exceção em finally...\n";
TeamStrength::openRatingCache($gInitial);
test_assert(TeamStrength::isRatingCacheActive(), "Cache aberto");
TeamStrength::closeRatingCache();
test_assert(!TeamStrength::isRatingCacheActive(), "Cache fechado normalmente");

$exceptionCaught = false;
try {
    TeamStrength::withRatingCache(function() use ($gInitial) {
        test_assert(TeamStrength::isRatingCacheActive(), "Cache ativo dentro de withRatingCache");
        TeamStrength::rating('Flamengo', $gInitial);
        throw new LogicException("Exceção simulada");
    }, $gInitial);
} catch (LogicException $e) {
    $exceptionCaught = true;
}
test_assert($exceptionCaught, "Exceção simulada capturada");
test_assert(!TeamStrength::isRatingCacheActive(), "Cache descartado no finally após exceção");

// Exceção real no completeWorld
$actualExceptionCaught = false;
$invalidState = ['club' => 'Audit61 A', 'memberships' => ['does-not-exist' => ['A', 'B']], 'competitions' => []];
try {
    @Competition::completeWorld($invalidState);
} catch (Throwable) {
    $actualExceptionCaught = true;
}
test_assert($actualExceptionCaught, "Exceção no completeWorld real disparada");
test_assert(!TeamStrength::isRatingCacheActive(), "Cache inativo após exceção no completeWorld");

// -------------------------------------------------------------
// 8. Contextos aninhados (Reentrância)
// -------------------------------------------------------------
echo "\n8. Testando contextos aninhados...\n";
TeamStrength::openRatingCache($gInitial);
test_assert(TeamStrength::isRatingCacheActive(), "Nível 1 ativo");
TeamStrength::rating('Palmeiras', $gInitial);
$mLevel1Before = TeamStrength::ratingCacheMetrics();
test_assert($mLevel1Before['depth'] === 1 && $mLevel1Before['size'] === 1, "Nível 1 com 1 clube");

// Nível 2 aninhado
TeamStrength::openRatingCache($gInitial);
$mLevel2 = TeamStrength::ratingCacheMetrics();
test_assert($mLevel2['depth'] === 2 && $mLevel2['size'] === 0, "Nível 2 aninhado criado vazio");
TeamStrength::rating('Santos', $gInitial);
TeamStrength::rating('Santos', $gInitial);
$mLevel2After = TeamStrength::ratingCacheMetrics();
test_assert($mLevel2After['hits'] === 1 && $mLevel2After['misses'] === 1, "Nível 2 operando com isolamento");

TeamStrength::closeRatingCache();
$mLevel1After = TeamStrength::ratingCacheMetrics();
test_assert($mLevel1After['depth'] === 1 && $mLevel1After['size'] === 1, "Nível 1 restaurado intacto");
TeamStrength::closeRatingCache();
test_assert(!TeamStrength::isRatingCacheActive(), "Pilha desempilhada completamente");

// -------------------------------------------------------------
// 9. Duas carreiras sequenciais
// -------------------------------------------------------------
echo "\n9. Testando isolamento entre duas carreiras sequenciais...\n";
$career1_g = ['season' => 2026, 'club' => 'Corinthians', 'club_strengths' => ['Corinthians' => 54.0], 'matches' => []];
$career2_g = ['season' => 2026, 'club' => 'Corinthians', 'club_strengths' => ['Corinthians' => 47.0], 'matches' => []];

$rCareer1 = TeamStrength::withRatingCache(fn() => TeamStrength::rating('Corinthians', $career1_g), $career1_g);
$rCareer2 = TeamStrength::withRatingCache(fn() => TeamStrength::rating('Corinthians', $career2_g), $career2_g);

test_assert($rCareer1 !== $rCareer2, "Carreiras com forças distintas produzem ratings distintos");
test_assert($rCareer1 === TeamStrength::calculateRawRating('Corinthians', $career1_g), "Carreira 1 com rating idêntico ao cálculo bruto");
test_assert($rCareer2 === TeamStrength::calculateRawRating('Corinthians', $career2_g), "Carreira 2 com rating idêntico ao cálculo bruto");
test_assert(!TeamStrength::isRatingCacheActive(), "Cache desligado após ambas as carreiras");

// -------------------------------------------------------------
// 10. Invalidação explícita
// -------------------------------------------------------------
echo "\n10. Testando métodos de invalidação explícita...\n";
TeamStrength::openRatingCache($gInitial);
TeamStrength::rating('Flamengo', $gInitial);
TeamStrength::rating('Palmeiras', $gInitial);
test_assert(TeamStrength::ratingCacheMetrics()['size'] === 2, "2 clubes em cache");

TeamStrength::invalidateRating('Flamengo');
test_assert(TeamStrength::ratingCacheMetrics()['size'] === 1, "Apenas Palmeiras permanece após invalidar Flamengo");

TeamStrength::clearRatingCache();
test_assert(TeamStrength::ratingCacheMetrics()['size'] === 0, "Cache totalmente limpo por clearRatingCache");
TeamStrength::closeRatingCache();

// -------------------------------------------------------------
// 11. Equivalência matemática para todos os 2.164 clubes do catálogo
// -------------------------------------------------------------
echo "\n11. Testando equivalência matemática para todos os 2.164 clubes...\n";
$allClubs = array_keys(Competition::clubs());
$testG = [
    'season' => 2026,
    'club' => 'Mauá-SP',
    'club_strengths' => [
        'Mauá-SP' => 45.0,
        'Flamengo' => 53.0,
        'Palmeiras' => 52.5,
    ],
    'matches' => [
        ['home' => 'Mauá-SP', 'away' => 'Flamengo', 'status' => 'finalizada', 'hg' => 1, 'ag' => 0],
    ],
];

$rawRatings = [];
foreach ($allClubs as $club) {
    $rawRatings[$club] = TeamStrength::calculateRawRating($club, $testG);
}

$cachedRatings = [];
TeamStrength::withRatingCache(function() use ($allClubs, $testG, &$cachedRatings) {
    foreach ($allClubs as $club) {
        $cachedRatings[$club] = TeamStrength::rating($club, $testG);
    }
}, $testG);

$divergences = 0;
foreach ($allClubs as $club) {
    if ($rawRatings[$club] !== $cachedRatings[$club]) {
        $divergences++;
    }
}
test_assert($divergences === 0, "Identidade perfeita para todos os " . count($allClubs) . " clubes (0 divergências)");

// -------------------------------------------------------------
// 12. Fechamento NPC completo (Competition::completeWorld)
// -------------------------------------------------------------
echo "\n12. Testando fechamento completo via Competition::completeWorld...\n";
$gSeason = Game::create('Teste Entrega61 V3', 'São Paulo', 'SP', 'meia', 'Mauá-SP');
$gSeason = Game::command($gSeason, 'handicap');
$gSeason['play_mode'] = 'expresso';

$count = 0;
while ($gSeason['stage'] !== 'resumo' && ++$count < 100) {
    if ($gSeason['stage'] === 'competicao_resumo') {
        test_assert(!TeamStrength::isRatingCacheActive(), "Cache desligado antes de continue");
        $gSeason = Game::command($gSeason, 'continue');
        test_assert(!TeamStrength::isRatingCacheActive(), "Cache desligado após completeWorld");
    } else {
        $gSeason = Game::command($gSeason, 'simulate', ['plan' => 'evolucao']);
    }
}

test_assert($gSeason['stage'] === 'resumo', "Temporada avançou para resumo após completeWorld");
test_assert(!TeamStrength::isRatingCacheActive(), "Cache garantidamente desligado após o encerramento");
test_assert(!empty($gSeason['competitions']['br-a']['done']), "Competições NPC foram finalizadas com sucesso");

echo "\n>>> TODOS OS TESTES OBRIGATÓRIOS DA ENTREGA 61 PASSARAM COM SUCESSO! <<<\n";
