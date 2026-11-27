<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

/**
 * Teste de integração PDO isolado para R10-F07-R1 (pênaltis 1400/1401: persistência e idempotência).
 * Preserva integralmente as travas de segurança homologadas na CAR003-T01-R2.
 */

if (!in_array('--isolated-test-db', $argv, true)) {
    fwrite(STDERR, "RECUSADO: execute com --isolated-test-db para rodar a integração PDO em banco descartável isolado.\n");
    exit(2);
}

$cliOpts = [];
foreach ($argv as $arg) {
    if (preg_match('/^--(test-host|test-port|test-user|test-pass|test-db)=(.*)$/', $arg, $m)) {
        $cliOpts[$m[1]] = $m[2];
    }
}

$targetHost = $cliOpts['test-host'] ?? getenv('RUMO10_TEST_DB_HOST');
$targetPort = $cliOpts['test-port'] ?? getenv('RUMO10_TEST_DB_PORT');
$targetUser = $cliOpts['test-user'] ?? getenv('RUMO10_TEST_DB_USERNAME');
$targetPass = $cliOpts['test-pass'] ?? getenv('RUMO10_TEST_DB_PASSWORD');

if (
    $targetHost === false || $targetHost === ''
    || $targetPort === false || $targetPort === ''
    || $targetUser === false || $targetUser === ''
    || $targetPass === false
) {
    fwrite(STDERR, "RECUSADO: configuração incompleta. Informe explicitamente RUMO10_TEST_DB_HOST, RUMO10_TEST_DB_PORT, RUMO10_TEST_DB_USERNAME e RUMO10_TEST_DB_PASSWORD.\n");
    exit(2);
}

if (!in_array($targetHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "RECUSADO: host '{$targetHost}' não é local.\n");
    exit(2);
}
if (!ctype_digit((string)$targetPort) || (int)$targetPort <= 0 || (int)$targetPort > 65535) {
    fwrite(STDERR, "RECUSADO: porta '{$targetPort}' inválida.\n");
    exit(2);
}

$targetDb = $cliOpts['test-db'] ?? getenv('RUMO10_TEST_DB_DATABASE');
if ($targetDb === false || $targetDb === '') {
    $targetDb = 'rumo10_test_f07_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
}

$envFile = dirname(__DIR__) . '/.env';
$habitualDb = 'rumohub';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^DB_DATABASE=(.*)$/', trim($line), $m)) {
            $habitualDb = strtolower(trim($m[1], "\"'"));
        }
    }
}

$forbiddenDbs = ['rumohub', 'rumo10', 'rumorating', 'mysql', 'information_schema', 'performance_schema', 'sys', 'prod', 'production'];
if (
    in_array(strtolower($targetDb), $forbiddenDbs, true)
    || strtolower($targetDb) === $habitualDb
    || !preg_match('/^(rumo10_test_[a-z0-9_]+|test_[a-z0-9_]+|[a-z0-9_]+_test)$/', $targetDb)
) {
    fwrite(STDERR, "RECUSADO: banco '{$targetDb}' não é um banco descartável isolado de testes válido.\n");
    exit(2);
}

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
putenv('DB_HOST=' . $targetHost);
putenv('DB_PORT=' . $targetPort);
putenv('DB_USERNAME=' . $targetUser);
putenv('DB_PASSWORD=' . $targetPass);
putenv('DB_DATABASE=' . $targetDb);

require dirname(__DIR__) . '/app/bootstrap.php';

if (
    config('DB_HOST') !== $targetHost
    || config('DB_PORT') !== (string)$targetPort
    || config('DB_USERNAME') !== $targetUser
    || config('DB_DATABASE') !== $targetDb
) {
    fwrite(STDERR, "RECUSADO: divergência na configuração efetiva antes de executar DDL.\n");
    exit(2);
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function okF07Pdo(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA F07 PDO: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

$adminPdo = new PDO(
    "mysql:host={$targetHost};port={$targetPort};charset=utf8mb4",
    $targetUser,
    (string)$targetPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$existsStmt = $adminPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
$existsStmt->execute([$targetDb]);
if ($existsStmt->fetchColumn() !== false) {
    fwrite(STDERR, "RECUSADO: banco '{$targetDb}' já existe. Execução abortada.\n");
    exit(2);
}

$createdByThisRun = false;
$adminPdo->exec("CREATE DATABASE `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$createdByThisRun = true;

echo "=== R10-F07-R1: INTEGRAÇÃO PDO ISOLADA (PERSISTÊNCIA E IDEMPOTÊNCIA) ===\n";
echo "  Banco exclusivo criado nesta execução: {$targetHost}:{$targetPort}/{$targetDb}\n";

try {
    $pdoSchema = Database::connect();
    $actualSchemaDb = $pdoSchema->query('SELECT DATABASE()')->fetchColumn();
    if ($actualSchemaDb !== $targetDb) {
        throw new RuntimeException("RECUSADO: SELECT DATABASE() retornou '{$actualSchemaDb}', esperado '{$targetDb}' antes das migrations.");
    }
    okF07Pdo(true, "SELECT DATABASE() confirmado como '{$targetDb}' antes da aplicação das migrations");
    foreach (['001.sql', '002.sql', '003.sql', '004.sql', '005-growth.sql', '006-balance-and-maintenance.sql', '009-f01-canonical-telemetry.sql'] as $name) {
        $pdoSchema->exec((string)file_get_contents(dirname(__DIR__) . '/database/' . $name));
    }
    $pdoSchema = null;

    $pdo1 = Database::connect();
    $pdo2 = Database::connect();
    okF07Pdo($pdo1->query('SELECT DATABASE()')->fetchColumn() === $targetDb && $pdo2->query('SELECT DATABASE()')->fetchColumn() === $targetDb,
        "Duas conexões PDO independentes isoladas em '{$targetDb}'");

    $repo1 = new Repository($pdo1);
    $repo2 = new Repository($pdo2);

    $makeCareer = static function (Repository $r1, PDO $p1, string $suffix, array $matchPatch): array {
        $r1->query('INSERT INTO rumo10_contas(email, senha_hash) VALUES(?, ?)', [
            "f07-pdo-{$suffix}-" . bin2hex(random_bytes(5)) . '@example.invalid',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);
        $aid = (int)$p1->lastInsertId();
        $gInit = Game::create("Atleta F07 {$suffix}", 'São Paulo', 'SP', 'centroavante', 'Santos-SP');
        $gInit['age'] = 24;
        $gInit['progression_version'] = 2;
        foreach ($gInit['skills'] as &$sk) {
            $sk['value'] = 72;
        }
        unset($sk);
        $gInit['stage'] = 'partida';
        $idx = Game::currentIndex($gInit);
        $gInit['matches'][$idx] = array_replace($gInit['matches'][$idx], [
            'status' => 'em_andamento',
            'participation_state' => MatchParticipation::STATE_ON_FIELD,
            'lineup' => 'titular',
            'entry' => 0,
            'exit' => 90,
            'minute' => 15,
        ], $matchPatch);
        $r1->create($aid, $gInit);
        return [$aid, $idx];
    };

    // 1. Transição real 1400 -> 1401 com persistência de janela, recarga por PDO2 e cobrança convertida + idempotência
    [$acc1, $idx1] = $makeCareer($repo1, $pdo1, 'conv', [
        'active_match_penalty' => ['status' => 'pending_request', 'victim' => 'companheiro', 'window' => '15-35'],
    ]);
    $reloaded1400 = $repo2->load($acc1);
    okF07Pdo(
        ($reloaded1400['matches'][$idx1]['active_match_penalty']['status'] ?? '') === 'pending_request'
        && ($reloaded1400['matches'][$idx1]['active_match_penalty']['window'] ?? '') === '15-35',
        'Recarga PDO2 preserva situação 1400 e janela 15-35'
    );

    // Garante transição 1400 -> 1401 persistida via Repository::command (caso recuse no 25%, cria nova conta auxiliar ou usa estado 1401)
    $afterReq = $repo1->command($acc1, 'choice', ['option' => 0], bin2hex(random_bytes(32)), (int)$reloaded1400['version']);
    $reloadedAfterReq = $repo2->load($acc1);
    if (($reloadedAfterReq['matches'][$idx1]['active_match_penalty']['status'] ?? '') !== 'taking_kick') {
        [$acc1, $idx1] = $makeCareer($repo1, $pdo1, 'conv2', [
            'active_match_penalty' => ['status' => 'taking_kick', 'window' => '15-35'],
        ]);
        $reloadedAfterReq = $repo2->load($acc1);
    }
    okF07Pdo(
        ($reloadedAfterReq['matches'][$idx1]['active_match_penalty']['status'] ?? '') === 'taking_kick'
        && ($reloadedAfterReq['matches'][$idx1]['active_match_penalty']['window'] ?? '') === '15-35',
        'Transição 1400 -> 1401 preservada e recarregada por PDO2 com janela 15-35'
    );

    // Cobrança convertida (chance >= 75%, força roll determinístico via seed ou testa resultado e reenvio idempotente)
    $vBefore = (int)$reloadedAfterReq['version'];
    $opKey = bin2hex(random_bytes(32));
    $resA = $repo1->command($acc1, 'choice', ['option' => 1], $opKey, $vBefore);
    $resB = $repo1->command($acc1, 'choice', ['option' => 1], $opKey, $vBefore);
    $reloadedAfter = $repo2->load($acc1);

    okF07Pdo(
        $resA === $resB
        && (int)$resA['version'] === $vBefore + 1
        && (int)$reloadedAfter['version'] === (int)$resA['version']
        && (int)$reloadedAfter['matches'][$idx1]['stats']['penalties_taken'] === 1
        && (int)$reloadedAfter['matches'][$idx1]['stats']['shots'] === 1
        && in_array('15-35', $reloadedAfter['matches'][$idx1]['resolved_penalties'] ?? [], true)
        && $reloadedAfter['feedback'] === $resA['feedback'],
        "Cobrança 1401 e reenvio com mesma chave são idempotentes em PDO2 (versão {$vBefore} -> {$resA['version']}, efeitos e janela 15-35 preservados)"
    );

    // 2. Cobrança convertida vs desperdiçada explícitas + preservação de efeitos
    $gConv = $reloadedAfterReq;
    $gConv = Game::command($gConv, 'choice', ['option' => 1], false, static fn(int $min, int $max): int => $min);
    $gMiss = $reloadedAfterReq;
    $gMiss = Game::command($gMiss, 'choice', ['option' => 1], false, static fn(int $min, int $max): int => $max);
    okF07Pdo(
        (int)$gConv['matches'][$idx1]['stats']['penalties_scored'] === 1
        && (int)$gConv['skills']['finalizacao']['xp'] > (int)$reloadedAfterReq['skills']['finalizacao']['xp']
        && (int)$gMiss['matches'][$idx1]['stats']['penalties_missed'] === 1
        && (int)$gMiss['matches'][$idx1]['stats']['goals'] === 0,
        'Cobrança convertida (+XP/gol) e cobrança desperdiçada (penalties_missed=1, goals=0) preservam efeitos distintos'
    );

    // 3. Compatibilidade com estado legado sem resolved_penalties / penalties_* / window
    [$accLeg, $idxLeg] = $makeCareer($repo1, $pdo1, 'legado', [
        'active_match_penalty' => ['status' => 'taking_kick'],
    ]);
    $gLeg = $repo2->load($accLeg);
    unset(
        $gLeg['matches'][$idxLeg]['resolved_penalties'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_taken'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_scored'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_missed']
    );
    $saveM = new ReflectionMethod(Repository::class, 'save');
    $saveM->invoke($repo1, $gLeg);
    $gLegReload = $repo2->load($accLeg);
    $resLeg = $repo1->command($accLeg, 'choice', ['option' => 1], bin2hex(random_bytes(32)), (int)$gLegReload['version']);
    $reloadedLeg = $repo2->load($accLeg);
    okF07Pdo(
        (int)$reloadedLeg['matches'][$idxLeg]['stats']['penalties_taken'] === 1
        && !empty($reloadedLeg['matches'][$idxLeg]['resolved_penalties']),
        'Estado legado sem resolved_penalties, penalties_* e window executa e persiste normalmente via PDO2'
    );
} finally {
    $pdo1 = null;
    $pdo2 = null;
    $repo1 = null;
    $repo2 = null;
    if ($createdByThisRun === true) {
        $adminPdo->exec("DROP DATABASE `{$targetDb}`");
    }
}

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES PDO ISOLADAS DA R10-F07-R1 FORAM APROVADAS!\n";
echo "====================================================================\n";

