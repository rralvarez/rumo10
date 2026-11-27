<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

/**
 * Teste de integração PDO isolado para R10-CAR-003-R2 (CAR003-D02 / CAR003-T01).
 *
 * Exige autorização explícita (--isolated-test-db) e banco descartável local
 * explicitamente identificado como ambiente de testes, recusando qualquer execução
 * contra o banco habitual do Rumo10, RumoHub ou produção antes de qualquer gravação.
 */

if (!in_array('--isolated-test-db', $argv, true)) {
    fwrite(STDERR, "RECUSADO: execute com --isolated-test-db para rodar a integração PDO em banco descartável isolado.\n");
    exit(2);
}

$envFile = dirname(__DIR__) . '/.env';
$envVars = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', trim($line), $m)) {
            $envVars[$m[1]] = trim($m[2], "\"'");
        }
    }
}

$targetHost = getenv('RUMO10_TEST_DB_HOST') ?: ($envVars['DB_HOST'] ?? '127.0.0.1');
if (!in_array($targetHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "RECUSADO: host '{$targetHost}' não é local. Integração PDO permitida apenas em instância local descartável.\n");
    exit(2);
}

$targetDb = 'rumo10_test_car003';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--test-db=')) {
        $targetDb = substr($arg, strlen('--test-db='));
    }
}
$envTestDb = getenv('RUMO10_TEST_DB_DATABASE');
if ($envTestDb !== false && $envTestDb !== '') {
    $targetDb = $envTestDb;
}

$forbiddenDbs = ['rumohub', 'rumo10', 'rumorating', 'mysql', 'information_schema', 'performance_schema', 'sys', 'prod', 'production'];
$habitualDb = strtolower((string)($envVars['DB_DATABASE'] ?? 'rumohub'));
if (
    in_array(strtolower($targetDb), $forbiddenDbs, true)
    || strtolower($targetDb) === $habitualDb
    || !preg_match('/^(rumo10_test_[a-z0-9_]+|test_[a-z0-9_]+|[a-z0-9_]+_test)$/', $targetDb)
) {
    fwrite(STDERR, "RECUSADO: banco '{$targetDb}' não é um banco descartável isolado de testes válido (ex.: rumo10_test_car003).\n");
    exit(2);
}

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
putenv('DB_HOST=' . $targetHost);
putenv('DB_DATABASE=' . $targetDb);

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function okD02(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA CAR003-D02: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

echo "=== R10-CAR-003-R2: INTEGRAÇÃO PDO ISOLADA (CAR003-D02 / CAR003-T01) ===\n";
echo "  Banco descartável autorizado: {$targetHost}/{$targetDb}\n";

$port = config('DB_PORT', '3306');
$user = config('DB_USERNAME');
$pass = config('DB_PASSWORD');
if ($user === '') {
    throw new RuntimeException('Credenciais locais ausentes para criar banco descartável.');
}

$adminPdo = new PDO(
    "mysql:host={$targetHost};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$adminPdo->exec("DROP DATABASE IF EXISTS `{$targetDb}`");
$adminPdo->exec("CREATE DATABASE `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdoSchema = Database::connect();
    okD02($pdoSchema->query('SELECT DATABASE()')->fetchColumn() === $targetDb,
        "Conexão PDO isolada aponta exclusivamente para '{$targetDb}'");

    $schemaFiles = [
        '001.sql',
        '002.sql',
        '003.sql',
        '004.sql',
        '005-growth.sql',
        '006-balance-and-maintenance.sql',
        '009-f01-canonical-telemetry.sql',
    ];
    foreach ($schemaFiles as $name) {
        $sql = (string)file_get_contents(dirname(__DIR__) . '/database/' . $name);
        $pdoSchema->exec($sql);
    }
    $pdoSchema = null;

    $pdo1 = Database::connect();
    $pdo2 = Database::connect();
    okD02(
        $pdo1->query('SELECT DATABASE()')->fetchColumn() === $targetDb
        && $pdo2->query('SELECT DATABASE()')->fetchColumn() === $targetDb,
        "Duas conexões PDO independentes isoladas em '{$targetDb}'"
    );

    $repo1 = new Repository($pdo1);
    $repo2 = new Repository($pdo2);

    $repo1->query('INSERT INTO rumo10_contas(email, senha_hash) VALUES(?, ?)', [
        'car003-t01-' . bin2hex(random_bytes(6)) . '@example.invalid',
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $accountId = (int)$pdo1->lastInsertId();

    $gInit = Game::create('Atleta CAR003-T01', 'São Paulo', 'SP', 'meia', 'Santos-SP');
    $gInit['season'] = 4;
    $gInit['age'] = 22;
    foreach ($gInit['skills'] as &$sk) {
        $sk['value'] = 74;
    }
    unset($sk);
    $gInit['season_skill_baseline'] = 72.0;

    $repo1->create($accountId, $gInit);
    $gLoaded = $repo1->load($accountId);
    $gLoaded['stage'] = 'resumo';
    $saveMethod = new ReflectionMethod(Repository::class, 'save');
    $saveMethod->invoke($repo1, $gLoaded);

    $versionBefore = (int)$gLoaded['version'];
    $opKey = bin2hex(random_bytes(32));

    $winA = $repo1->command($accountId, 'offers', [], $opKey, $versionBefore);
    $winB = $repo1->command($accountId, 'offers', [], $opKey, $versionBefore);

    okD02(
        (int)$winA['version'] === $versionBefore + 1 && (int)$winB['version'] === (int)$winA['version'],
        "Abertura e repetição com mesma chave produzem um único avanço de versão ({$versionBefore} -> {$winA['version']})"
    );
    okD02(
        $winA === $winB && count($winA['offers']) === 3,
        'Repetição idempotente retorna exatamente o mesmo estado e 3 propostas'
    );

    // Recarga real por segunda conexão PDO independente
    $reloaded = $repo2->load($accountId);
    okD02(
        is_array($reloaded)
        && (int)$reloaded['version'] === (int)$winA['version']
        && $reloaded['offers'] === $winA['offers'],
        'Recarga via segunda conexão PDO preserva integralmente todas as propostas persistidas e a versão'
    );

    $targetOffer = $reloaded['offers'][0];
    $removedCompId = (string)$targetOffer['competition'];
    $origCompName = (string)$targetOffer['competition_name'];
    $origScope = (string)$targetOffer['division_scope'];
    $origReason = (string)$targetOffer['reason'];

    $fullCat = Competition::catalog();
    $removedCat = $fullCat;
    unset($removedCat[$removedCompId]);

    Competition::referenceScope($removedCat, static function () use ($reloaded, $origCompName, $origScope, $origReason): void {
        ob_start();
        TransferInterest::render($reloaded);
        $htmlRemoved = (string)ob_get_clean();
        okD02(
            str_contains($htmlRemoved, $origCompName)
            && str_contains($htmlRemoved, $origScope)
            && str_contains($htmlRemoved, $origReason)
            && str_contains($htmlRemoved, 'Força da liga:'),
            "Competição removida do catálogo ({$origCompName}) mantém dados persistidos na renderização após recarga PDO"
        );
    });

    $renamedCat = $fullCat;
    if (isset($renamedCat[$removedCompId])) {
        $renamedCat[$removedCompId]['name'] = 'LIGA RENOMEADA INDEVIDAMENTE';
    }
    Competition::referenceScope($renamedCat, static function () use ($reloaded, $origCompName): void {
        ob_start();
        TransferInterest::render($reloaded);
        $htmlRenamed = (string)ob_get_clean();
        okD02(
            str_contains($htmlRenamed, $origCompName)
            && !str_contains($htmlRenamed, 'LIGA RENOMEADA INDEVIDAMENTE'),
            'Competição renomeada no catálogo não sobrescreve nome persistido após recarga PDO'
        );
    });
} finally {
    $pdo1 = null;
    $pdo2 = null;
    $repo1 = null;
    $repo2 = null;
    $adminPdo->exec("DROP DATABASE IF EXISTS `{$targetDb}`");
}

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES DE INTEGRAÇÃO PDO ISOLADA FORAM APROVADAS!\n";
echo "====================================================================\n";
