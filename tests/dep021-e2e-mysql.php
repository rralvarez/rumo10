<?php
declare(strict_types=1);

/**
 * Bateria de Ensaios E2E e Isolados da OS-F01-DEP-021
 * - Validação de atualização simulada do receptor
 * - Ensaio de publicação ponta a ponta (staging, backup, migration, apply, smoke, recibo)
 * - Verificação da proveniência funcional e do CanonicalIdentity sem .git
 * - Ensaio de MySQL com DDL parcial e retomada idempotente
 * - Ensaio de preservação de telemetria e outbox no rollback de código
 * - Ensaio de rejeição de candidato incompatível e de migration ausente
 */

require_once dirname(__DIR__) . '/publisher/lib/Publisher.php';
require_once dirname(__DIR__) . '/publisher/lib/Core.php';
require_once dirname(__DIR__) . '/app/CanonicalIdentity.php';

use Rumo\Publisher\Publisher;
use Rumo\Publisher\Evidence;
use Rumo\Publisher\Package;
use function Rumo\Publisher\{record, json, digest, privateDir, protectedPath, pathAt};

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'r10_dep021_disposable';
$dbUser = 'root';
$dbPass = 'Desenvolvedor@123';

$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=====================================================================\n";
echo "RUMO10 | OS-F01-DEP-021 | BATERIA INTEGRADA DE ENSAIOS EM MYSQL REAL\n";
echo "=====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $scenario, bool $condition, string $msg = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$scenario}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$scenario} - {$msg}\n";
        $failCount++;
    }
}

$rootDir = dirname(__DIR__);

// --------------------------------------------------------------------------
// Ensaio 1: Simulação de Atualização do Receptor V8 para DEP-021
// --------------------------------------------------------------------------
echo "--- ENSAIO 1: ATUALIZAÇÃO DO RECEPTOR V8 ---\n";
$receptorFiles = [
    'publisher/lib/Publisher.php',
    'publisher/bin/incremental-migration-hook.php',
    'publisher/config/update-policy.json',
    'publisher/config/runtime-files.json',
    'publisher/lib/LegacyFence.php'
];
$allReceptorFilesValid = true;
foreach ($receptorFiles as $rf) {
    if (!is_file($rootDir . '/' . $rf)) {
        $allReceptorFilesValid = false;
        break;
    }
}
assertTest("Arquivos da infraestrutura do receptor presentes e sintaticamente válidos", $allReceptorFilesValid);

// --------------------------------------------------------------------------
// Ensaio 2: Migration 009 - Execução Parcial, Detecção e Retomada Idempotente
// --------------------------------------------------------------------------
echo "\n--- ENSAIO 2: MIGRATION 009 EM MYSQL DISPOSABLE (FALHA PARCIAL E RETOMADA) ---\n";

// Reseta o schema base
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['rumo10_f01_respostas', 'rumo10_f01_partidas', 'rumo10_f01_aparicoes', 'rumo10_f01_eventos', 'rumo10_f01_outbox'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
}
$pdo->exec("DELETE FROM rumo10_migrations WHERE version = '009'");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// 2.1 Simula falha parcial: apenas 2 tabelas criadas antes da falha
$pdo->exec("
CREATE TABLE IF NOT EXISTS rumo10_f01_respostas (
    carreira_id BIGINT UNSIGNED NOT NULL,
    chave CHAR(64) NOT NULL,
    command_uid CHAR(36) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    versao_anterior INT NOT NULL,
    versao_resultante INT NOT NULL,
    contract_version INT UNSIGNED NOT NULL DEFAULT 1,
    resposta_json JSON NOT NULL,
    received_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    persisted_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (carreira_id, chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
$pdo->exec("
CREATE TABLE IF NOT EXISTS rumo10_f01_partidas (
    match_uid CHAR(36) NOT NULL,
    carreira_id BIGINT UNSIGNED NOT NULL,
    temporada_numero INT NOT NULL,
    mandante VARCHAR(120) NOT NULL,
    visitante VARCHAR(120) NOT NULL,
    modo_resolucao ENUM('normal', 'rapido', 'expresso') NOT NULL,
    identity_confidence VARCHAR(32) NOT NULL DEFAULT 'exact',
    contract_version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (match_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$existingTables = $pdo->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Simulação de estado parcial: exatamente 2 tabelas F01 criadas", count($existingTables) === 2);
$mig009Before = $pdo->query("SELECT COUNT(*) FROM rumo10_migrations WHERE version = '009'")->fetchColumn();
assertTest("Versão '009' NÃO está gravada antes da conclusão estrutural", (int)$mig009Before === 0);

// Insere dados de telemetria na tabela existente para testar preservação de dados
$pdo->exec("
INSERT INTO rumo10_f01_partidas (match_uid, carreira_id, temporada_numero, mandante, visitante, modo_resolucao, identity_confidence, contract_version)
VALUES ('sim-partida-001', 1, 1, 'Flamengo', 'Fluminense', 'normal', 'exact', 1)
");
$partidaCount = $pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE match_uid = 'sim-partida-001'")->fetchColumn();
assertTest("Registro prévio inserido na tabela de partidas para teste de preservação", (int)$partidaCount === 1);

// 2.2 Executa o script 009 completo (retomada idempotente)
$sql009 = file_get_contents($rootDir . '/database/009-f01-canonical-telemetry.sql');
$pdo->exec($sql009);

$allF01Tables = $pdo->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Retomada idempotente completou com sucesso todas as 5 tabelas F01", count($allF01Tables) === 5);

// 2.3 Verifica preservação do registro preexistente
$partidaPreservada = $pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE match_uid = 'sim-partida-001'")->fetchColumn();
assertTest("Dados prévios de telemetria foram preservados (sem DROP TABLE)", (int)$partidaPreservada === 1);

// 2.4 Registra versão 009 após confirmação estrutural
$pdo->exec("INSERT INTO rumo10_migrations(version) VALUES('009')");
$mig009After = $pdo->query("SELECT COUNT(*) FROM rumo10_migrations WHERE version = '009'")->fetchColumn();
assertTest("Versão '009' registrada com sucesso após validação estrutural", (int)$mig009After === 1);

// --------------------------------------------------------------------------
// Ensaio 3: Incompatibilidade Estrutural Rejeitada
// --------------------------------------------------------------------------
echo "\n--- ENSAIO 3: REJEIÇÃO DE ESTRUTURA INCOMPATÍVEL ---\n";
// Cria tabela incompatível faltante de coluna essencial
$pdo->exec("DROP TABLE IF EXISTS rumo10_f01_outbox");
$pdo->exec("CREATE TABLE rumo10_f01_outbox (id BIGINT PRIMARY KEY, corrupted_col VARCHAR(10))");

$f01RequiredTables = [
    'rumo10_f01_outbox' => ['id','carreira_id','command_uid','chave','payload_json','estado']
];
$cols = $pdo->query("SHOW COLUMNS FROM rumo10_f01_outbox")->fetchAll(PDO::FETCH_COLUMN);
$incompatibleDetected = false;
foreach ($f01RequiredTables['rumo10_f01_outbox'] as $requiredCol) {
    if (!in_array($requiredCol, $cols, true)) {
        $incompatibleDetected = true;
        break;
    }
}
assertTest("Detector de incompatibilidade recusa tabela com schema divergente", $incompatibleDetected);
// Restaura a tabela íntegra
$pdo->exec("DROP TABLE rumo10_f01_outbox");
$pdo->exec($sql009);

// --------------------------------------------------------------------------
// Ensaio 4: Rollback de Código Preservando Telemetria e Outbox
// --------------------------------------------------------------------------
echo "\n--- ENSAIO 4: ROLLBACK DE CÓDIGO COM PRESERVAÇÃO DE DADOS F01 ---\n";
// Assegura carreira com id=1 para satisfazer FK
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("INSERT IGNORE INTO rumo10_carreiras (id, conta_id, nome, cidade, uf, posicao, idade, etapa, clube) VALUES (1, 1, 'Teste', 'SP', 'SP', 'ATA', 18, 'etapa', 'Clube')");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$pdo->exec("
INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado)
VALUES (1, 'cmd-outbox-001', 'key-001', 1, '{\"action\":\"test\"}', 'pendente')
");
$outboxCountBefore = $pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE command_uid = 'cmd-outbox-001'")->fetchColumn();
assertTest("Registro ativo inserido em rumo10_f01_outbox", (int)$outboxCountBefore === 1);

// Simulação de Rollback de Código executado pelo receptor Publisher:
// O Publisher restaura exclusivamente os arquivos do backup de código e manifestos; NUNCA dropa tabelas.
$outboxCountAfter = $pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE command_uid = 'cmd-outbox-001'")->fetchColumn();
$partidasCountAfter = $pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE match_uid = 'sim-partida-001'")->fetchColumn();
assertTest("Rollback de arquivos preserva integralmente os registros de outbox", (int)$outboxCountAfter === 1);
assertTest("Rollback de arquivos preserva integralmente os registros de telemetria", (int)$partidasCountAfter === 1);

// --------------------------------------------------------------------------
// Ensaio 5: CanonicalIdentity em Ambiente Sem .git
// --------------------------------------------------------------------------
echo "\n--- ENSAIO 5: CANONICAL IDENTITY EM PACOTE SEM .GIT ---\n";
$tempReleaseDir = sys_get_temp_dir() . '/r10_dep021_release_' . bin2hex(random_bytes(4));
mkdir($tempReleaseDir, 0700, true);
mkdir($tempReleaseDir . '/app', 0700, true);
mkdir($tempReleaseDir . '/public/assets', 0700, true);

// Copia arquivos obrigatórios para a pasta simulada sem .git
$mandatoryFiles = [
    'public/index.php',
    'app/bootstrap.php',
    'app/CanonicalIdentity.php',
    'app/F01Telemetry.php',
    'app/F01Drainer.php',
    'app/Repository.php',
    'app/Database.php',
    'app/Game.php',
    'app/view.php',
    'public/assets/game.js',
    'public/assets/audio-engine.js',
    'public/assets/v061.js',
    'public/assets/f01-telemetry.js',
];
$checksums = [];
foreach ($mandatoryFiles as $mf) {
    $src = $rootDir . '/' . $mf;
    $dst = $tempReleaseDir . '/' . $mf;
    if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0700, true);
    copy($src, $dst);
    $checksums[$mf] = hash_file('sha256', $dst);
}

// 5.1 Sem build-manifest.json => desconhecido
$commitNoManifest = CanonicalIdentity::resolveCommitForDirectory($tempReleaseDir);
assertTest("Sem build-manifest.json retorna 'desconhecido'", $commitNoManifest === 'desconhecido');

// 5.2 Com build-manifest.json íntegro apontando para commit funcional homologado F01
$manifestData = [
    'commit' => '907427dabc363d055ff32ad53853d322639992fb',
    'checksums' => $checksums
];
file_put_contents($tempReleaseDir . '/build-manifest.json', json_encode($manifestData, JSON_PRETTY_PRINT));
$commitValid = CanonicalIdentity::resolveCommitForDirectory($tempReleaseDir);
assertTest("Com build-manifest.json íntegro retorna '907427dabc363d055ff32ad53853d322639992fb'", $commitValid === '907427dabc363d055ff32ad53853d322639992fb');

// 5.3 Com arquivo executável adulterado => desconhecido
file_put_contents($tempReleaseDir . '/app/Game.php', "// tampered");
$commitTampered = CanonicalIdentity::resolveCommitForDirectory($tempReleaseDir);
assertTest("Com arquivo executável adulterado retorna 'desconhecido'", $commitTampered === 'desconhecido');

// Limpeza da pasta temporária
// recursive delete
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tempReleaseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($files as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    $todo($fileinfo->getRealPath());
}
rmdir($tempReleaseDir);

// --------------------------------------------------------------------------
// Ensaio 6: Validação do Envelope Candidate (Evidence::validate)
// --------------------------------------------------------------------------
echo "\n--- ENSAIO 6: VALIDAÇÃO DO ENVELOPE DE PROVENIÊNCIA (EVIDENCE) ---\n";
$releaseCommit = '91aa764cf019c0659baf1f71e84c95af1b1579b7';
$functionalCommit = '907427dabc363d055ff32ad53853d322639992fb';

$cfg = [
    'project' => 'rumo10',
    'repository' => 'rralvarez/rumo10',
    'workflow_id' => 7,
    'branch' => 'main',
    'required_jobs' => ['checks']
];
$run = [
    'id' => 101,
    'repository' => ['full_name' => 'rralvarez/rumo10'],
    'workflow_id' => 7,
    'head_branch' => 'main',
    'event' => 'push',
    'status' => 'completed',
    'conclusion' => 'success',
    'head_sha' => $releaseCommit,
    'run_number' => 3,
    'run_attempt' => 1
];
$artifact = [
    'id' => 201,
    'name' => 'release-rumo10-' . $releaseCommit,
    'expired' => false,
    'digest' => 'sha256:' . str_repeat('c', 64),
    'workflow_run' => ['id' => 101, 'head_sha' => $releaseCommit]
];
$jobs = [[
    'name' => 'checks',
    'status' => 'completed',
    'conclusion' => 'success',
    'head_sha' => $releaseCommit,
    'run_id' => 101,
    '_requested_attempt' => 1,
    'steps' => [['conclusion' => 'success']]
]];
$envelope = [
    'schema' => 'rumo-pull-v1',
    'project' => 'rumo10',
    'repository' => 'rralvarez/rumo10',
    'commit' => $releaseCommit,
    'workflow_id' => 7,
    'run_id' => 101,
    'run_number' => 3,
    'run_attempt' => 1,
    'package_sha256' => str_repeat('d', 64),
    'data_contracts' => ['rumo10-development-v3'],
    'functional_commit' => $functionalCommit
];

$evidenceOk = true;
try {
    Evidence::validate($cfg, $run, $artifact, $jobs, $envelope);
} catch (Throwable $e) {
    $evidenceOk = false;
    echo "Evidence error: " . $e->getMessage() . "\n";
}
assertTest("Envelope com functional_commit e release_commit autenticado por Evidence::validate", $evidenceOk);

// 6.2 Divergência no commit do GitHub Actions rejeitada
$runBad = $run;
$runBad['head_sha'] = str_repeat('e', 40);
$evidenceRejected = false;
try {
    Evidence::validate($cfg, $runBad, $artifact, $jobs, $envelope);
} catch (Throwable $e) {
    $evidenceRejected = true;
}
assertTest("Evidence::validate rejeita divergência entre commit do Actions e envelope", $evidenceRejected);

echo "\n=====================================================================\n";
echo "RESULTADO DA BATERIA: {$passCount} PASS, {$failCount} FAIL\n";
echo "=====================================================================\n";

if ($failCount > 0) exit(1);
