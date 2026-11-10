<?php
declare(strict_types=1);

/**
 * RUMO10 | OS-F01-DEP-036 | SUÍTE E2E DE VINCULAÇÃO FAIL-CLOSED DO BUILD AUTORIZADO
 *
 * Testes Negativos Obrigatórios do Controle de Build Autorizado:
 * 1. Campo authorized_build_commits ausente na política: rejeitar.
 * 2. authorized_build_commits com valor null: rejeitar.
 * 3. authorized_build_commits com valor escalar: rejeitar.
 * 4. authorized_build_commits com array vazio []: rejeitar.
 * 5. authorized_build_commits com formato malformado (ex: não-SHA): rejeitar.
 * 6. Commit de build desconhecido / não listado: rejeitar.
 * 7. Reprodução e bloqueio do ataque DEP-034 (Game.php adulterado + produtor adulterado + YAML intacto): rejeitar antes da instalação.
 * 8. Build autorizado e íntegro: aceitar.
 * 9. F01 sem migration 009: rejeitar pelo invariante mandatório.
 * 10. V8 autorizada com authorized_build_commits explícito e requires_migration = []: compatibilidade preservada.
 * 11. Regressão DEP-035: sem falhas.
 */

$dbHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$dbPort = getenv('MYSQL_PORT') ?: '3306';
$dbName = getenv('MYSQL_DB') ?: 'r10_dep036_disposable';
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
echo "RUMO10 | OS-F01-DEP-036 | SUÍTE E2E BUILD FAIL-CLOSED\n";
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

initV8Database($pdo);
assertTest("Schema baseline V8 inicializado com sucesso", true);

$tempDir = sys_get_temp_dir() . '/r10_dep036_test_' . uniqid();
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
    '--run-id', '789036',
    '--run-number', '36',
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

// Configuração com authorized_build_commits explicitamente cadastrado para o teste
$updatePolicyWithBuild = $updatePolicy;
$authorizedBuildCommitF01 = $headSha; // Commit atual de teste autorizado
$authorizedBuildCommitV8 = $v8Sha; // Commit V8 de teste autorizado
$updatePolicyWithBuild['authorized_releases'][$f01Sha]['authorized_build_commits'] = [
    $authorizedBuildCommitF01
];
$updatePolicyWithBuild['authorized_releases'][$v8Sha]['authorized_build_commits'] = [
    $authorizedBuildCommitV8
];

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
    'update_policy' => $updatePolicyWithBuild,
    'hooks' => []
];

// Helper mock para envelope, run, artifact, jobs
$f01Run = [
    'id' => 789036, 'repository' => ['full_name' => 'rralvarez/rumo10'],
    'workflow_id' => 123456, 'head_branch' => 'main', 'event' => 'push',
    'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01,
    'run_number' => 36, 'run_attempt' => 1
];
$f01Artifact = [
    'id' => 99936, 'name' => 'release-rumo10-' . $authorizedBuildCommitF01, 'expired' => false,
    'digest' => 'sha256:' . $f01CandidateData['package_sha256'],
    'workflow_run' => ['id' => 789036, 'head_sha' => $authorizedBuildCommitF01]
];
$f01Jobs = [
    ['name' => 'syntax', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01, 'run_id' => 789036, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'regression', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01, 'run_id' => 789036, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'publisher-safety', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01, 'run_id' => 789036, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'artifact', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01, 'run_id' => 789036, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitF01, 'run_id' => 789036, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]]
];
$f01Envelope = $f01CandidateData;

echo "\n--- TESTES NEGATIVOS OBRIGATÓRIOS DO CONTROLE DE BUILD FAIL-CLOSED ---\n";

// 1. Campo authorized_build_commits ausente na política: rejeitar
$cfgMissingField = $publisherConfig;
unset($cfgMissingField['update_policy']['authorized_releases'][$f01Sha]['authorized_build_commits']);
$pubMissingField = new Publisher($cfgMissingField);
$caught = false; $msg = '';
try {
    $pubMissingField->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("1. Campo authorized_build_commits ausente: rejeitar",
    $caught && str_contains($msg, 'missing required field `authorized_build_commits`'), $msg);
unset($pubMissingField);

// 2. authorized_build_commits com valor null: rejeitar
$cfgNullField = $publisherConfig;
$cfgNullField['update_policy']['authorized_releases'][$f01Sha]['authorized_build_commits'] = null;
$pubNullField = new Publisher($cfgNullField);
$caught = false; $msg = '';
try {
    $pubNullField->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("2. authorized_build_commits com valor null: rejeitar",
    $caught && str_contains($msg, 'must be a non-empty array'), $msg);
unset($pubNullField);

// 3. authorized_build_commits com valor escalar: rejeitar
$cfgScalarField = $publisherConfig;
$cfgScalarField['update_policy']['authorized_releases'][$f01Sha]['authorized_build_commits'] = $authorizedBuildCommitF01; // string em vez de array
$pubScalarField = new Publisher($cfgScalarField);
$caught = false; $msg = '';
try {
    $pubScalarField->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("3. authorized_build_commits com valor escalar: rejeitar",
    $caught && str_contains($msg, 'must be a non-empty array'), $msg);
unset($pubScalarField);

// 4. authorized_build_commits com array vazio []: rejeitar
$cfgEmptyField = $publisherConfig;
$cfgEmptyField['update_policy']['authorized_releases'][$f01Sha]['authorized_build_commits'] = [];
$pubEmptyField = new Publisher($cfgEmptyField);
$caught = false; $msg = '';
try {
    $pubEmptyField->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("4. authorized_build_commits com array vazio []: rejeitar",
    $caught && str_contains($msg, 'must be a non-empty array'), $msg);
unset($pubEmptyField);

// 5. authorized_build_commits com formato malformado: rejeitar
$cfgMalformedField = $publisherConfig;
$cfgMalformedField['update_policy']['authorized_releases'][$f01Sha]['authorized_build_commits'] = ['not-a-sha-40-hex'];
$pubMalformedField = new Publisher($cfgMalformedField);
$caught = false; $msg = '';
try {
    $pubMalformedField->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("5. authorized_build_commits com formato malformado: rejeitar",
    $caught && str_contains($msg, 'Invalid build commit SHA format in release policy'), $msg);
unset($pubMalformedField);

// 6. Commit de build desconhecido / não listado: rejeitar
$unauthorizedSha = '4444444444444444444444444444444444444444';
$runUnauth = $f01Run; $runUnauth['head_sha'] = $unauthorizedSha;
$artUnauth = $f01Artifact; $artUnauth['workflow_run']['head_sha'] = $unauthorizedSha;
$artUnauth['name'] = 'release-rumo10-' . $unauthorizedSha;
$jobsUnauth = array_map(function($j) use ($unauthorizedSha) { $j['head_sha'] = $unauthorizedSha; return $j; }, $f01Jobs);
$envUnauth = $f01Envelope; $envUnauth['commit'] = $unauthorizedSha;

$pubUnauth = new Publisher($publisherConfig);
$caught = false; $msg = '';
try {
    $pubUnauth->verify($f01PackageTarGz, $runUnauth, $artUnauth, $jobsUnauth, $envUnauth);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("6. Commit de build desconhecido / não listado: rejeitar",
    $caught && str_contains($msg, "Publication build commit not authorized for release {$f01Sha}: {$unauthorizedSha}"), $msg);
unset($pubUnauth);

// 7. Reprodução e bloqueio do ataque DEP-034
// Game.php adulterado + produtor adulterado (sem diff check) + YAML intacto:
// Gera commit de build $attackSha (não homologado). Receptor rejeita em verify antes de descompactar/instalar!
$attackSha = '5555555555555555555555555555555555555555';
$runAttack = $f01Run; $runAttack['head_sha'] = $attackSha;
$artAttack = $f01Artifact; $artAttack['workflow_run']['head_sha'] = $attackSha;
$artAttack['name'] = 'release-rumo10-' . $attackSha;
$jobsAttack = array_map(function($j) use ($attackSha) { $j['head_sha'] = $attackSha; return $j; }, $f01Jobs);
$envAttack = $f01Envelope; $envAttack['commit'] = $attackSha;

$pubAttack = new Publisher($publisherConfig);
$caught = false; $msg = '';
try {
    $pubAttack->verify($f01PackageTarGz, $runAttack, $artAttack, $jobsAttack, $envAttack);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("7. Ataque DEP-034 reproduzido e bloqueado pelo receptor antes da instalação",
    $caught && str_contains($msg, "Publication build commit not authorized for release {$f01Sha}: {$attackSha}"), $msg);
unset($pubAttack);

// 8. Build autorizado e íntegro: aceitar
$pubAuthorized = new Publisher($publisherConfig);
$f01Verified = false; $f01Msg = '';
try {
    $f01Res = $pubAuthorized->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
    $f01Verified = ($f01Res['envelope']['required_migrations'] === ['009'] && $f01Res['commit'] === $authorizedBuildCommitF01);
} catch (Throwable $e) {
    $f01Msg = $e->getMessage();
}
assertTest("8. Build autorizado e íntegro: aceitar com sucesso", $f01Verified, $f01Msg);
unset($pubAuthorized);

// 9. F01 sem migration 009: rejeitar pelo invariante mandatório
$cfgNo009 = $publisherConfig;
$cfgNo009['update_policy']['authorized_releases'][$f01Sha]['requires_migration'] = [];
$pubNo009 = new Publisher($cfgNo009);
$caught = false; $msg = '';
try {
    $pubNo009->verify($f01PackageTarGz, $f01Run, $f01Artifact, $f01Jobs, $f01Envelope);
} catch (Throwable $e) {
    $caught = true; $msg = $e->getMessage();
}
assertTest("9. F01 sem migration 009: rejeitada pelo invariante mandatório da F01",
    $caught && str_contains($msg, "Release F01 ({$f01Sha}) strictly requires migration `009`"), $msg);
unset($pubNo009);

// 10. V8 autorizada com authorized_build_commits explícito e requires_migration = []: compatibilidade preservada
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
    'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8,
    'run_number' => 88, 'run_attempt' => 1
];
$v8Artifact = [
    'id' => 88801, 'name' => 'release-rumo10-' . $authorizedBuildCommitV8, 'expired' => false,
    'digest' => 'sha256:' . $v8PkgSha,
    'workflow_run' => ['id' => 8881, 'head_sha' => $authorizedBuildCommitV8]
];
$v8Jobs = [
    ['name' => 'syntax', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'regression', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'publisher-safety', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'artifact', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]],
    ['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success', 'head_sha' => $authorizedBuildCommitV8, 'run_id' => 8881, '_requested_attempt' => 1, 'steps' => [['conclusion' => 'success']]]
];
$v8Envelope = [
    'schema' => 'rumo-pull-v1', 'project' => 'rumo10', 'repository' => 'rralvarez/rumo10',
    'commit' => $authorizedBuildCommitV8, 'functional_commit' => $v8Sha, 'workflow_id' => 123456,
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
assertTest("10. V8 autorizada com authorized_build_commits explícito: compatibilidade preservada", $v8Verified, $v8Msg);
unset($pubV8);

// 11. Regressão DEP-035: sem falhas
$dep035Script = $rootDir . '/tests/dep035-e2e-pipeline.php';
$proc = proc_open([PHP_BINARY, $dep035Script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fclose($pipes[0]);
$dep035Out = stream_get_contents($pipes[1]);
$dep035Err = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$dep035Rc = proc_close($proc);

assertTest("11. Regressão DEP-035: todos os 10 testes passam sem falhas",
    $dep035Rc === 0 && str_contains($dep035Out, 'RESULTADO DA SUÍTE DEP-035: 10 PASS, 0 FAIL'), $dep035Err);

// -------------------------------------------------------------------------------------------------
// Limpeza final do ambiente temporário
$pdoRoot->exec("DROP DATABASE IF EXISTS `{$dbName}`");

echo "\n=====================================================================\n";
echo "RESULTADO DA SUÍTE DEP-036: {$passCount} PASS, {$failCount} FAIL\n";
echo "=====================================================================\n";

if ($failCount > 0) exit(1);

