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

    $repo1->query('INSERT INTO rumo10_contas(email, senha_hash) VALUES(?, ?)', [
        'f07-pdo-' . bin2hex(random_bytes(6)) . '@example.invalid',
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $accountId = (int)$pdo1->lastInsertId();

    $gInit = Game::create('Atleta F07 PDO', 'São Paulo', 'SP', 'centroavante', 'Santos-SP');
    $gInit['age'] = 24;
    $gInit['progression_version'] = 2;
    foreach ($gInit['skills'] as &$sk) {
        $sk['value'] = 72;
    }
    unset($sk);
    $gInit['stage'] = 'partida';
    $idx = Game::currentIndex($gInit);
    $gInit['matches'][$idx]['status'] = 'em_andamento';
    $gInit['matches'][$idx]['participation_state'] = MatchParticipation::STATE_ON_FIELD;
    $gInit['matches'][$idx]['lineup'] = 'titular';
    $gInit['matches'][$idx]['entry'] = 0;
    $gInit['matches'][$idx]['exit'] = 90;
    $gInit['matches'][$idx]['minute'] = 15;
    $gInit['matches'][$idx]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '0-15'];

    $repo1->create($accountId, $gInit);
    $loaded1 = $repo2->load($accountId);
    okF07Pdo(($loaded1['matches'][$idx]['active_match_penalty']['status'] ?? '') === 'taking_kick',
        'Recarga via segunda conexão PDO preserva estado pendente da situação 1401');

    $vBefore = (int)$loaded1['version'];
    $opKey = bin2hex(random_bytes(32));
    $resA = $repo1->command($accountId, 'choice', ['option' => 1], $opKey, $vBefore);
    $resB = $repo1->command($accountId, 'choice', ['option' => 1], $opKey, $vBefore);
    $reloadedAfter = $repo2->load($accountId);

    okF07Pdo(
        $resA === $resB
        && (int)$resA['version'] === $vBefore + 1
        && (int)$reloadedAfter['version'] === (int)$resA['version']
        && (int)$reloadedAfter['matches'][$idx]['stats']['penalties_taken'] === 1
        && (int)$reloadedAfter['matches'][$idx]['stats']['shots'] === 1,
        "Cobrança 1401 e reenvio com mesma chave são idempotentes (versão {$vBefore} -> {$resA['version']}, penalties_taken=1 sem duplicação)"
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

