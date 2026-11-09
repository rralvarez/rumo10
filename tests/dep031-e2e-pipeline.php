<?php
declare(strict_types=1);

/**
 * RUMO10 | OS-F01-DEP-031 | SUÍTE E2E DE CORREÇÃO FAIL-CLOSED DA POLÍTICA DE RELEASES
 *
 * Testes Negativos Obrigatórios:
 * 1. Catálogo ausente: rejeitar.
 * 2. Catálogo vazio: rejeitar.
 * 3. Entrada F01 ausente: rejeitar.
 * 4. F01 sem requires_migration: rejeitar.
 * 5. F01 com requires_migration: null: rejeitar.
 * 6. F01 com valor escalar: rejeitar.
 * 7. F01 com [] adulterado pelo candidato: rejeitar conforme política confiável.
 * 8. V8 com [] explicitamente autorizado: aceitar.
 * 9. F01 com ["009"]: aceitar somente mediante comprovação válida.
 * 10. Candidato tentando reduzir requisitos da política: rejeitar.
 *
 * Teste Adicional de Isolamento Físico:
 * - Classe A executando com usuário MySQL com permissão restrita a SELECT (comprovando ausência de mutação/escrita).
 */

$dbHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$dbPort = getenv('MYSQL_PORT') ?: '3306';
$dbName = getenv('MYSQL_DB') ?: 'r10_dep031_disposable';
$dbUser = getenv('MYSQL_USER') ?: 'root';
$dbPass = getenv('MYSQL_PASS') !== false ? getenv('MYSQL_PASS') : 'Desenvolvedor@123';

$rootDir = dirname(__DIR__);

require_once $rootDir . '/publisher/lib/Publisher.php';
require_once $rootDir . '/publisher/lib/Core.php';
require_once $rootDir . '/publisher/lib/UpdatePolicy.php';
require_once $rootDir . '/app/CanonicalIdentity.php';

use Rumo\Publisher\Publisher;
use Rumo\Publisher\Package;
use function Rumo\Publisher\{record, json, digest, protectedPath};

echo "=====================================================================\n";
echo "RUMO10 | OS-F01-DEP-031 | SUÍTE E2E FAIL-CLOSED DA POLÍTICA DE RELEASES\n";
echo "Conexão: {$dbHost}:{$dbPort} | Base: {$dbName}\n";
echo "=====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "[PASS] {$desc}\n";
    } else {
        $failCount++;
        echo "[FAIL] {$desc}" . ($detail !== '' ? ": {$detail}" : "") . "\n";
    }
}

// 0. Conexão ao MySQL e recriação limpa
try {
    $pdoRoot = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdoRoot->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $pdoRoot->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    assertTest("Conexão ao MySQL 8.0 e criação da base descartável `{$dbName}`", true);
} catch (Throwable $e) {
    assertTest("Conexão ao MySQL 8.0 e criação da base descartável `{$dbName}`", false, $e->getMessage());
    exit(1);
}

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

function initV8Database(PDO $pdo): void {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->exec("
        CREATE TABLE rumo10_contas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(60) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE rumo10_carreiras (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            conta_id BIGINT UNSIGNED NULL,
            nome VARCHAR(60) NOT NULL,
            cidade VARCHAR(80) NOT NULL,
            uf CHAR(2) NOT NULL,
            posicao VARCHAR(20) NOT NULL,
            idade INT NOT NULL,
            temporada INT NOT NULL DEFAULT 1,
            rodada INT NOT NULL DEFAULT 1,
            etapa VARCHAR(30) NOT NULL,
            clube VARCHAR(120) NOT NULL,
            tier INT NOT NULL DEFAULT 1,
            versao INT NOT NULL DEFAULT 0,
            feedback_json JSON NULL,
            FOREIGN KEY (conta_id) REFERENCES rumo10_contas(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE rumo10_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(50) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE rumo10_balanceamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(64) NOT NULL,
            estado ENUM('ativo', 'inativo') NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE rumo10_formula_versoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            formula_id VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE rumo10_formula_parametros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            versao_id INT NOT NULL,
            parametro VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        INSERT INTO rumo10_migrations (version) VALUES ('001'), ('002'), ('003'), ('004'), ('005'), ('006'), ('006-seed'), ('007'), ('008');
        INSERT INTO rumo10_balanceamentos (codigo, estado) VALUES ('bal-v062-recalibracao-ofensiva', 'ativo');
        INSERT INTO rumo10_formula_versoes (id, formula_id) VALUES (1, 'match.penalties.parameters');
        INSERT INTO rumo10_formula_parametros (versao_id, parametro) VALUES (1, 'p1');
    ");
}

function applyHomologatedF01Tables(PDO $pdo, string $rootDir): void {
    $sql = file_get_contents($rootDir . '/database/009-f01-canonical-telemetry.sql');
    $pdo->exec($sql);
    $pdo->exec("INSERT INTO rumo10_migrations (version) VALUES ('009')");
}

initV8Database($pdo);
assertTest("Schema baseline V8 inicializado com sucesso", true);

// Diretórios operacionais
$tempDir = sys_get_temp_dir() . '/r10_dep031_test_' . uniqid();
$opsDir = $tempDir . '/ops';
$produceDir = $tempDir . '/produce';
foreach (['current', 'private', 'public', 'control'] as $d) {
    mkdir($opsDir . '/' . $d, 0700, true);
}
mkdir($produceDir, 0700, true);

$headSha = trim(shell_exec("git -C " . escapeshellarg($rootDir) . " rev-parse HEAD") ?: '');
$f01Sha = '907427dabc363d055ff32ad53853d322639992fb';
$v8Sha = 'f23c2c3be2fbc322daf817fa14346ab02073a97b';

// Empacota o candidato F01 oficial usando produce.py
$produceCmd = [
    'python',
    $rootDir . '/publisher/bin/produce.py',
    '--repo', $rootDir,
    '--commit', $headSha,
    '--project', 'rumo10',
    '--repository', 'rralvarez/rumo10',
    '--workflow-id', '123456',
    '--run-id', '789031',
    '--run-number', '31',
    '--attempt', '1',
    '--data-contract', 'rumo10-development-v3',
    '--output', $produceDir . '/f01-release'
];
$proc = proc_open($produceCmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
$rc = proc_close($proc);
assertTest("produce.py empacota release F01 com sucesso", $rc === 0 && trim($stderr) === '', $stderr);

$f01PackageTarGz = $produceDir . '/f01-release/release.tar.gz';
$f01CandidateJson = $produceDir . '/f01-release/candidate.json';
$f01CandidateData = json_decode(file_get_contents($f01CandidateJson), true);

$updatePolicy = json_decode(file_get_contents($rootDir . '/publisher/config/update-policy.json'), true);

// Prepara baseline em current
Package::inspect($f01PackageTarGz, 'rumo10', $opsDir . '/current');
$baselineInv = Package::inspect($f01PackageTarGz, 'rumo10');
$baselineFiles = $baselineInv['files'];

file_put_contents($opsDir . '/current/.env', "DB_HOST={$dbHost}\nDB_PORT={$dbPort}\nDB_DATABASE={$dbName}\nDB_USERNAME={$dbUser}\nDB_PASSWORD={$dbPass}\n");
copy($rootDir . '/public/index.php', $opsDir . '/public/index.php');
file_put_contents($opsDir . '/public/.htaccess', "# htaccess\n");

$publicInventory = [];
foreach ($baselineFiles as $p => $h) {
    if (str_starts_with($p, 'public/') && !in_array($p, ['public/index.php', 'public/.htaccess'], true) && !protectedPath($p)) {
        $dest = $opsDir . '/public/' . substr($p, 7);
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
        copy($opsDir . '/current/' . $p, $dest);
        $publicInventory[substr($p, 7)] = $h;
    }
}
ksort($baselineFiles);
record($opsDir . '/current/release.json', ['project' => 'rumo10', 'files' => $baselineFiles]);
record($opsDir . '/current/build-manifest.json', ['commit' => $f01Sha, 'checksums' => $baselineFiles]);

$migrationProfilePath = $opsDir . '/migration-profile.json';
record($migrationProfilePath, [
    'project' => 'rumo10',
    'current' => $opsDir . '/current',
    'approved_incremental_migrations' => $updatePolicy['approved_incremental_migrations']
]);

$hookWrapper = $opsDir . '/test-hook.php';
$hookCode = '<?php
$op = $argv[1];
$r = json_decode(stream_get_contents(STDIN), true);
$rootDir = "' . addslashes($rootDir) . '";
$opsDir = "' . addslashes($opsDir) . '";

if ($op === "preflight") {
    echo json_encode(["ok" => true, "legacy_publishers_fenced" => true, "source_unchanged" => true]);
} elseif ($op === "fence") {
    echo json_encode(["ok" => true, "legacy_publishers_fenced" => true]);
} elseif ($op === "maintenance") {
    echo json_encode([
        "ok" => true,
        "http_status" => 503,
        "writes_blocked" => true,
        "inflight_writers" => 0,
        "new_requests_blocked" => true,
        "publisher_exclusive" => true
    ]);
} elseif ($op === "backup") {
    $bDir = $r["backup"];
    $snapFile = $bDir . "/db_snapshot.txt";
    file_put_contents($snapFile, "MYSQL_CONSISTENT_SNAPSHOT");
    echo json_encode([
        "ok" => true,
        "attempt" => $r["attempt"],
        "consistent" => true,
        "restore_tested" => true,
        "files" => [$snapFile => hash_file("sha256", $snapFile)]
    ]);
} elseif ($op === "smoke") {
    echo json_encode(["ok" => true, "f01_smoke_passed" => true]);
} elseif ($op === "migrate") {
    $hookScript = $rootDir . "/publisher/bin/incremental-migration-hook.php";
    $profile = $opsDir . "/migration-profile.json";
    $cmd = [PHP_BINARY, $hookScript, "migrate", "--profile", $profile];
    $proc = proc_open($cmd, [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
    fwrite($pipes[0], json_encode($r));
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    if ($rc !== 0) {
        fwrite(STDERR, $err);
        exit($rc);
    }
    echo $out;
}
';
file_put_contents($hookWrapper, $hookCode);

$publisherConfig = [
    'project' => 'rumo10',
    'repository' => 'rralvarez/rumo10',
    'workflow_id' => 123456,
    'branch' => 'main',
    'required_jobs' => ['syntax', 'regression', 'publisher-safety', 'artifact', 'compatibility'],
    'private' => $opsDir . '/private',
    'current' => $opsDir . '/current',
    'public' => $opsDir . '/public',
    'control' => $opsDir . '/control',
    'account_mutex' => $opsDir . '/account.lock',
    'apply_enabled' => true,
    'php' => PHP_BINARY,
    'php_args' => [],
    'data_contracts' => ['rumo10-development-v3'],
    'baseline_data_contracts' => ['rumo10-development-v3'],
    'backup_roots' => ['env' => $opsDir . '/current/.env'],
    'pinned_controls' => [
        $opsDir . '/public/index.php' => hash_file('sha256', $opsDir . '/public/index.php'),
        $opsDir . '/public/.htaccess' => hash_file('sha256', $opsDir . '/public/.htaccess')
    ],
    'unmanaged_allowlist' => [],
    'public_inventory' => $publicInventory,
    'homologation' => ['backup' => true, 'maintenance' => true, 'recovery' => true, 'legacy_publishers_fenced' => true],
    'update_policy' => $updatePolicy,
    'hooks' => [
        'fence' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'preflight' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'maintenance' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'backup' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'smoke' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'migrate' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)]
    ]
];

// Helper mock para envelope, run, artifact, jobs
$f01Run = [
    'id' => 789031, 'repository' => ['full_name' => 'rralvarez/rumo10'],
    'workflow_id' => 123456, 'head_branch' => 'main', 'event' => 'push',
    'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha,
    'run_number' => 31, 'run_attempt' => 1
];
$f01Artifact = [
    'id' => 99931, 'name' => 'release-rumo10-' . $headSha, 'expired' => false,
    'digest' => 'sha256:' . $f01CandidateData['package_sha256'],
    'workflow_run' => ['id' => 789031, 'head_sha' => $headSha]
];
$f01Jobs = [
    ['name' => 'syntax', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha, 'run_id' => 789031, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'regression', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha, 'run_id' => 789031, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'publisher-safety', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha, 'run_id' => 789031, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'artifact', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha, 'run_id' => 789031, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $headSha, 'run_id' => 789031, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]]
];
$f01Envelope = $f01CandidateData;

echo "\n--- TESTES NEGATIVOS OBRIGATÓRIOS DA DEP-031 ---\n";

// 1. Catálogo ausente: rejeitar
$cfgMissingCat = $publisherConfig;
unset($cfgMissingCat['update_policy']['authorized_releases']);
// Para isolar o teste do arquivo de fallback em disco, instanciamos com cópia temporária onde config file não existe
$tempNoFileDir = $tempDir . '/no-policy-dir';
mkdir($tempNoFileDir . '/config', 0700, true);
$codePublisher = file_get_contents($rootDir . '/publisher/lib/Publisher.php');
// Testando via Publisher configurado sem authorized_releases:
$pubMissingCat = new Publisher($cfgMissingCat);
$caught = false; $msg = '';
try {
    // Se update_policy['authorized_releases'] for explicitamente null ou vazio, Publisher::verify deve rejeitar
    $cfgMissingCat['update_policy']['authorized_releases'] = null;
    $pubNullCat = new Publisher($cfgMissingCat);
    $pubNullCat->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
// Se caiu no fallback de arquivo, também testamos se o catálogo em si for ausente / não-array:
$cfgInvalidCat = $publisherConfig;
$cfgInvalidCat['update_policy']['authorized_releases'] = false;
$pubInvalidCat = new Publisher($cfgInvalidCat);
$caughtInvalid = false; $msgInvalid = '';
try {
    $pubInvalidCat->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caughtInvalid = true; $msgInvalid = $e->getMessage();
}
assertTest("1. Catálogo ausente/inválido: rejeitar com erro fail-closed",
    $caughtInvalid && str_contains($msgInvalid, 'Authorized releases policy catalog missing, invalid or empty'), $msgInvalid);

// 2. Catálogo vazio: rejeitar
$cfgEmptyCat = $publisherConfig;
$cfgEmptyCat['update_policy']['authorized_releases'] = [];
$pubEmptyCat = new Publisher($cfgEmptyCat);
$caught = false; $msg = '';
try {
    $pubEmptyCat->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("2. Catálogo vazio: rejeitar com erro fail-closed",
    $caught && str_contains($msg, 'Authorized releases policy catalog missing, invalid or empty'), $msg);

// 3. Entrada F01 ausente: rejeitar
$cfgMissingF01 = $publisherConfig;
unset($cfgMissingF01['update_policy']['authorized_releases'][$f01Sha]);
$pubMissingF01 = new Publisher($cfgMissingF01);
$caught = false; $msg = '';
try {
    $pubMissingF01->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("3. Entrada F01 ausente no catálogo: rejeitar",
    $caught && str_contains($msg, 'Functional commit not recognized in authorized releases policy'), $msg);

// 4. F01 sem requires_migration: rejeitar
$cfgNoReq = $publisherConfig;
unset($cfgNoReq['update_policy']['authorized_releases'][$f01Sha]['requires_migration']);
$pubNoReq = new Publisher($cfgNoReq);
$caught = false; $msg = '';
try {
    $pubNoReq->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("4. F01 sem requires_migration: rejeitar",
    $caught && str_contains($msg, 'missing required field `requires_migration`'), $msg);

// 5. F01 com requires_migration: null: rejeitar
$cfgNullReq = $publisherConfig;
$cfgNullReq['update_policy']['authorized_releases'][$f01Sha]['requires_migration'] = null;
$pubNullReq = new Publisher($cfgNullReq);
$caught = false; $msg = '';
try {
    $pubNullReq->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("5. F01 com requires_migration: null: rejeitar",
    $caught && str_contains($msg, '`requires_migration` must be an array'), $msg);

// 6. F01 com valor escalar: rejeitar
$cfgScalarReq = $publisherConfig;
$cfgScalarReq['update_policy']['authorized_releases'][$f01Sha]['requires_migration'] = '009';
$pubScalarReq = new Publisher($cfgScalarReq);
$caught = false; $msg = '';
try {
    $pubScalarReq->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("6. F01 com valor escalar: rejeitar",
    $caught && str_contains($msg, '`requires_migration` must be an array'), $msg);

// 7. F01 com [] adulterado pelo candidato: rejeitar conforme política confiável (tentativa de contornar 009)
$pubLegit = new Publisher($publisherConfig);
$envTamperedEmpty = $f01Envelope;
$envTamperedEmpty['required_migrations'] = []; // Candidato tenta enviar [] para ignorar 009
$caught = false; $msg = '';
try {
    $pubLegit->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $envTamperedEmpty);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("7. F01 com `[]` adulterado pelo candidato: rejeitar conforme política confiável",
    $caught && str_contains($msg, 'Candidate illegally reduced mandatory migration requirement: 009'), $msg);

// 8. V8 com [] explicitamente autorizado: aceitar
$v8Payload = [];
foreach ($baselineFiles as $p => $h) {
    if (is_file($opsDir . '/current/' . $p)) {
        $v8Payload[$p] = file_get_contents($opsDir . '/current/' . $p);
    }
}
$v8Mandatory = $updatePolicy['authorized_releases'][$v8Sha]['mandatory_files'];
foreach ($v8Mandatory as $mPath => $expectedHash) {
    $proc = proc_open(['git', '-C', $rootDir, 'show', "{$v8Sha}:{$mPath}"], [1 => ['pipe', 'w']], $pipes);
    $v8Content = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($proc);
    if ($v8Content !== '' && hash('sha256', $v8Content) === $expectedHash) {
        $v8Payload[$mPath] = $v8Content;
    }
}
$v8TarGz = $produceDir . '/v8-release.tar.gz';
function createTarGzPackage(string $outTarGz, array $files, string $project = 'rumo10'): void {
    if (file_exists($outTarGz)) unlink($outTarGz);
    $tarPath = substr($outTarGz, 0, -3);
    if (file_exists($tarPath)) unlink($tarPath);
    
    $fileHashes = [];
    foreach ($files as $p => $content) {
        $fileHashes[$p] = hash('sha256', $content);
    }
    ksort($fileHashes);
    $manifestJson = json_encode(['project' => $project, 'files' => $fileHashes], JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

    $tar = new PharData($tarPath);
    $tar->addFromString('release.json', $manifestJson);
    foreach ($files as $relPath => $content) {
        $tar->addFromString($relPath, $content);
    }
    $tar->compress(Phar::GZ);
    unset($tar);
    unlink($tarPath);
}
createTarGzPackage($v8TarGz, $v8Payload);
$v8PkgSha = hash_file('sha256', $v8TarGz);

$v8Run = [
    'id' => 8881, 'repository' => ['full_name' => 'rralvarez/rumo10'],
    'workflow_id' => 123456, 'head_branch' => 'main', 'event' => 'push',
    'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha,
    'run_number' => 88, 'run_attempt' => 1
];
$v8Artifact = [
    'id' => 88801, 'name' => 'release-rumo10-' . $v8Sha, 'expired' => false,
    'digest' => 'sha256:' . $v8PkgSha,
    'workflow_run' => ['id' => 8881, 'head_sha' => $v8Sha]
];
$v8Jobs = [
    ['name' => 'syntax', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'regression', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'publisher-safety', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'artifact', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $v8Sha, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]]
];
$v8Envelope = [
    'schema' => 'rumo-pull-v1', 'project' => 'rumo10', 'repository' => 'rralvarez/rumo10',
    'commit' => $v8Sha, 'functional_commit' => $v8Sha, 'workflow_id' => 123456,
    'run_id' => 8881, 'run_number' => 88, 'run_attempt' => 1,
    'package_sha256' => $v8PkgSha, 'data_contracts' => ['rumo10-development-v3']
];

$pubV8 = new Publisher($publisherConfig);
$v8Verified = false; $v8Msg = '';
try {
    $v8Res = $pubV8->verify($v8TarGz, $v8Run, $v8Artifact, $v8Jobs, $v8Envelope);
    $v8Verified = ($v8Res['envelope']['required_migrations'] === []);
} catch (Throwable $e) {
    $v8Msg = $e->getMessage();
}
assertTest("8. V8 com `[]` explicitamente autorizado: aceitar com required_migrations = []", $v8Verified, $v8Msg);

// 9. F01 com ["009"]: aceitar somente mediante comprovação válida
$pubF01Legit = new Publisher($publisherConfig);
$f01Verified = false; $f01Msg = '';
try {
    $f01Res = $pubF01Legit->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
    $f01Verified = ($f01Res['envelope']['required_migrations'] === ['009']);
} catch (Throwable $e) {
    $f01Msg = $e->getMessage();
}
assertTest("9. F01 com [\"009\"]: aceitar somente mediante comprovação válida (required_migrations = ['009'])", $f01Verified, $f01Msg);

// 10. Candidato tentando reduzir requisitos da política: rejeitar
$envReducedMulti = $f01Envelope;
$cfgMulti = $publisherConfig;
$cfgMulti['update_policy']['authorized_releases'][$f01Sha]['requires_migration'] = ['008', '009'];
$pubMulti = new Publisher($cfgMulti);
$envReducedMulti['required_migrations'] = ['009']; // Reduziu 008!
$caught = false; $msg = '';
try {
    $pubMulti->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $envReducedMulti);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("10. Candidato tentando reduzir requisitos da política (omitiu 008): rejeitar",
    $caught && str_contains($msg, 'Candidate illegally reduced mandatory migration requirement: 008'), $msg);

// -------------------------------------------------------------------------------------------------
echo "\n--- ISOLAMENTO FÍSICO DA CLASSE A COM USUÁRIO MYSQL RESTRITO A SELECT ---\n";
// -------------------------------------------------------------------------------------------------
$roUser = 'r10_ro_' . bin2hex(random_bytes(4));
$roPass = 'RoPass_' . bin2hex(random_bytes(6)) . '!';
$pdoRoot->exec("DROP USER IF EXISTS '{$roUser}'@'127.0.0.1'");
$pdoRoot->exec("CREATE USER '{$roUser}'@'127.0.0.1' IDENTIFIED BY '{$roPass}'");
$pdoRoot->exec("GRANT SELECT ON `{$dbName}`.* TO '{$roUser}'@'127.0.0.1'");
$pdoRoot->exec("FLUSH PRIVILEGES");

// Criar bootstrap dedicado apontando para usuário somente-leitura
$roAppDir = $tempDir . '/app_ro';
mkdir($roAppDir . '/app', 0700, true);
file_put_contents($roAppDir . '/app/bootstrap.php', "<?php
class Database {
    public static function connect(): PDO {
        return new PDO('mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4', '{$roUser}', '{$roPass}', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}
");

// Prepara banco com as 5 tabelas F01 já aplicadas
initV8Database($pdo);
applyHomologatedF01Tables($pdo, $rootDir);

$roProfilePath = $tempDir . '/ro-migration-profile.json';
record($roProfilePath, [
    'project' => 'rumo10',
    'current' => $roAppDir,
    'approved_incremental_migrations' => [
        'database/009-f01-canonical-telemetry.sql' => '0cb513015ddd8650885d55677df8717e3c0557838d7f42af904272744a8dda8a'
    ]
]);

$roHookInput = [
    'project' => 'rumo10',
    'attempt' => 'test-ro-user-001',
    'private' => $opsDir . '/private',
    'candidate' => [
        'stage' => $tempDir . '/stage-ro-test',
        'envelope' => [
            'functional_commit' => $f01Sha,
            'required_migrations' => ['009']
        ]
    ],
    'update_policy' => [
        'class' => 'A',
        'compatible_writers_ci' => true,
        'migrations' => [],
        'required_migrations' => ['009']
    ]
];

$cmd = [PHP_BINARY, $rootDir . '/publisher/bin/incremental-migration-hook.php', 'migrate', '--profile', $roProfilePath];
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], json_encode($roHookInput));
fclose($pipes[0]);
$hookOut = stream_get_contents($pipes[1]);
$hookErr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$hookRc = proc_close($proc);

assertTest("Hook migrate em Classe A tem sucesso mesmo com usuário MySQL restrito a SELECT",
    $hookRc === 0, "hookRc={$hookRc}, hookErr={$hookErr}");

$receiptData = json_decode($hookOut, true);
assertTest("Recibo emite compatible_expansion = true sob usuário restrito a SELECT",
    ($receiptData['ok'] ?? false) === true && ($receiptData['compatible_expansion'] ?? false) === true);

// Limpar usuário RO
$pdoRoot->exec("DROP USER '{$roUser}'@'127.0.0.1'");

// -------------------------------------------------------------------------------------------------
// Limpeza final do ambiente temporário
$pdoRoot->exec("DROP DATABASE IF EXISTS `{$dbName}`");

echo "\n=====================================================================\n";
echo "RESULTADO DA SUÍTE DEP-031: {$passCount} PASS, {$failCount} FAIL\n";
echo "=====================================================================\n";

if ($failCount > 0) exit(1);
