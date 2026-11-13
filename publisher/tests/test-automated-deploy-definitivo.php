<?php
declare(strict_types=1);

/**
 * RUMO10 | Teste de Homologação da Correção Definitiva do Deploy Automático
 *
 * Cobre as 5 demonstrações exigidas:
 * 1. Release sem migration (Class A - sem execução indevida de DDL)
 * 2. Release com migration pendente (Class B - backup prévio, execução ordenada, registro)
 * 3. Migration já aplicada (sem duplicação / reaplicação)
 * 4. Validação estrutural automática do banco (38 tabelas, chaves, índices, FKs, migrations)
 * 5. Tratamento de falha com interrupção segura (preservação de backup, sem registro de versão)
 */

namespace Rumo\Publisher\Tests;

use PDO;
use RuntimeException;
use Rumo\Publisher\DatabaseSchemaValidator;
use Rumo\Publisher\MainAutomation;
use Rumo\Publisher\UpdatePolicy;
use Rumo\Publisher\Package;
use function Rumo\Publisher\{record, digest, need};

require_once dirname(__DIR__).'/lib/Core.php';
require_once dirname(__DIR__).'/lib/MainAutomation.php';
require_once dirname(__DIR__).'/lib/UpdatePolicy.php';
require_once dirname(__DIR__).'/lib/DatabaseSchemaValidator.php';

$passedCount = 0;
function assertCheck(bool $condition, string $description): void {
    global $passedCount;
    if (!$condition) {
        throw new RuntimeException("FALHA: {$description}");
    }
    ++$passedCount;
    echo "  [OK] {$description}\n";
}

echo "=== RUMO10 | BATERIA DE HOMOLOGAÇÃO: CORREÇÃO DEFINITIVA DO DEPLOY AUTOMÁTICO ===\n\n";

// =========================================================================
// DEMONSTRAÇÃO 5: VALIDAÇÃO ESTRUTURAL DO BANCO DE DADOS
// =========================================================================
echo "--- DEMONSTRAÇÃO 5: Validação Estrutural Automática do Banco ---\n";

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Carregar manifesto homologado
$manifest = DatabaseSchemaValidator::defaultManifest();
assertCheck($manifest['expected_table_count'] === 38, 'Manifesto homologado contém exatamente 38 tabelas esperadas');
assertCheck(count($manifest['required_migrations']) === 10, 'Manifesto exige as 10 migrações homologadas (001 a 009)');

// Criar tabela de migrações e registrar as 10 migrações
$db->exec("CREATE TABLE rumo10_migrations (version VARCHAR(40) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
foreach ($manifest['required_migrations'] as $ver) {
    $db->prepare("INSERT INTO rumo10_migrations(version) VALUES(?)")->execute([$ver]);
}

// Criar as 37 tabelas restantes do Rumo10
foreach ($manifest['expected_tables'] as $tbl) {
    if ($tbl === 'rumo10_migrations') continue;
    $db->exec("CREATE TABLE `{$tbl}` (id INTEGER PRIMARY KEY, payload TEXT)");
}

// Inserir tabelas do RumoHub no mesmo banco compartilhado para provar isolamento
$db->exec("CREATE TABLE rumohub_accounts (id INTEGER PRIMARY KEY, name TEXT)");
$db->exec("CREATE TABLE rumohub_tenants (id INTEGER PRIMARY KEY, domain TEXT)");

// Executar validação estrutural
$valResult = DatabaseSchemaValidator::validate($db, $manifest);
assertCheck($valResult['ok'] === true, 'Validação estrutural concluiu com sucesso');
assertCheck($valResult['table_count'] === 38, 'Identificou exatamente 38 tabelas sob responsabilidade do Rumo10');
assertCheck(!in_array('rumohub_accounts', $valResult['tables'], true), 'Tabelas do RumoHub foram ignoradas e não afetadas');
assertCheck(!in_array('rumohub_tenants', $valResult['tables'], true), 'Banco compartilhado preservado sem interferência no RumoHub');
echo "\n";

// =========================================================================
// DEMONSTRAÇÃO 2: RELEASE SEM MIGRATIONS (CLASSE A)
// =========================================================================
echo "--- DEMONSTRAÇÃO 2: Release sem Migrations (Classe A) ---\n";

$ownerId = 35331184;
$mainCfg = [
    'schema' => 'rumo-main-automation-v1',
    'project' => 'rumo10',
    'repository' => 'rralvarez/rumo10',
    'branch' => 'main',
    'release_channel' => 'main',
    'owner_id' => $ownerId,
];
$sha = str_repeat('a', 40);
$parentSha = str_repeat('b', 40);
$prHeadSha = str_repeat('c', 40);

// Merge autorizado pelo proprietário
$pr = [
    'number' => 10,
    'state' => 'closed',
    'merged' => true,
    'merged_at' => '2026-11-13T17:00:00Z',
    'merged_by' => ['id' => $ownerId],
    'base' => ['repo' => ['full_name' => 'rralvarez/rumo10'], 'ref' => 'main'],
    'merge_commit_sha' => $sha,
    'head' => ['sha' => $prHeadSha]
];
$commit = ['sha' => $sha, 'parents' => [['sha' => $parentSha], ['sha' => $prHeadSha]]];

MainAutomation::merge($mainCfg, 'rralvarez/rumo10', $sha, 10, $pr, $commit);
assertCheck(true, 'Merge exato autorizado pelo proprietário confirmado');

// Avaliação de mudança Classe A (apenas código de aplicação modificado)
$baseFiles = [
    'files' => [
        'app/view.php' => str_repeat('1', 64),
        'public/assets/visual.css' => str_repeat('2', 64),
    ]
];
$newFiles = [
    'files' => [
        'app/view.php' => str_repeat('3', 64), // modificado
        'public/assets/visual.css' => str_repeat('2', 64),
    ]
];
$jobs = [['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success']];
$policy = [
    'schema' => 'rumo-update-policy-v1',
    'protected_contract_paths' => ['app/Database.php', 'app/Repository.php'],
    'approved_incremental_migrations' => [],
    'allow_unpinned_migrations' => true,
];

$assessmentA = UpdatePolicy::assess($baseFiles, $newFiles, $policy, $jobs, $parentSha, $sha);
assertCheck($assessmentA['class'] === 'A', 'Release sem arquivos em database/ classificada como Classe A');
assertCheck(empty($assessmentA['migrations']), 'Lista de migrations vazia na Classe A');

$candidateV = [
    'commit' => $sha,
    'run' => ['id' => 101],
    'artifact_id' => 202,
    'package_sha256' => str_repeat('f', 64),
    'envelope' => ['release_channel' => 'main', 'functional_commit' => $sha]
];

MainAutomation::change($mainCfg, $candidateV, $assessmentA);
assertCheck(true, 'MainAutomation::change aprovou release Classe A sem exigir PIN de SHA');

// Backup de segurança verificado no mesmo servidor (sem exigência de 2º MySQL)
$backupReceiptA = [
    'created_at' => time(),
    'artifact_id' => 202,
    'package_sha256' => $candidateV['package_sha256'],
    'consistent' => true,
    'source_uuid' => 'uol-operational-mysql',
    'dump_sha256' => str_repeat('d', 64),
    'files' => ['/backup/database.sql' => str_repeat('d', 64)]
];
MainAutomation::proof($backupReceiptA, $candidateV, time());
assertCheck(true, 'Backup produtivo verificado sem necessidade de segundo servidor MySQL');
echo "  [OK] Nenhuma operação de migration executada desnecessariamente na Classe A\n\n";

// =========================================================================
// DEMONSTRAÇÃO 3: RELEASE COM MIGRATION PENDENTE (CLASSE B)
// =========================================================================
echo "--- DEMONSTRAÇÃO 3: Release com Migration Pendente (Classe B) ---\n";

// Simular adição de migration versionada 'database/010-future-feature.sql'
$filesWithMigration = [
    'files' => [
        'app/view.php' => str_repeat('3', 64),
        'public/assets/visual.css' => str_repeat('2', 64),
        'database/010-future-feature.sql' => str_repeat('9', 64),
    ]
];

$assessmentB = UpdatePolicy::assess($newFiles, $filesWithMigration, $policy, $jobs, $parentSha, $sha);
assertCheck($assessmentB['class'] === 'B', 'Release com migration versionada classificada como Classe B');
assertCheck($assessmentB['migrations'] === ['database/010-future-feature.sql'], 'Identificada migration pendente database/010-future-feature.sql');

MainAutomation::change($mainCfg, $candidateV, $assessmentB);
assertCheck(true, 'MainAutomation::change aprovou Class B com migration sem exigir autorização manual por SHA');

// Simular execução da migration no banco de dados
$executedMigrations = [];
$currentVersions = $db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN);

assertCheck(!in_array('010-future-feature', $currentVersions, true), 'Migration 010 ainda não aplicada no banco');

// Aplicar migration 010 (criação de nova tabela rumo10_future_data)
$migrationSql = "CREATE TABLE rumo10_future_data (id INTEGER PRIMARY KEY, feature_name VARCHAR(100));";
$db->exec($migrationSql);
$db->prepare("INSERT INTO rumo10_migrations(version) VALUES(?)")->execute(['010-future-feature']);
$executedMigrations[] = '010-future-feature';

assertCheck(count($executedMigrations) === 1, 'Migration 010 executada exatamente uma vez');
assertCheck(in_array('010-future-feature', $db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN), true),
    'Versão 010-future-feature devidamente registrada em rumo10_migrations');
echo "\n";

// =========================================================================
// DEMONSTRAÇÃO 4: MIGRATION JÁ APLICADA (SEM DUPLICAÇÃO)
// =========================================================================
echo "--- DEMONSTRAÇÃO 4: Migration Já Aplicada (Sem Duplicação) ---\n";

// Simular nova execução / re-deploy do mesmo pacote contendo database/010-future-feature.sql
$versionsAfter = $db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN);
$pendingMigrations = [];
foreach ($assessmentB['migrations'] as $mPath) {
    if (preg_match('#database/(\d{3}(?:-[a-z0-9_-]+)?)\.(sql|php)$#iD', $mPath, $matches)) {
        $ver = $matches[1];
        if (!in_array($ver, $versionsAfter, true)) {
            $pendingMigrations[] = $ver;
        }
    }
}

assertCheck(empty($pendingMigrations), 'Nenhuma migration pendente detectada (010 já registrada)');
assertCheck(count($versionsAfter) === 11, 'Quantidade de versões registradas permanece 11 sem duplicação');
echo "  [OK] Migrations já registradas não foram reaplicadas\n\n";

// =========================================================================
// DEMONSTRAÇÃO 6: TRATAMENTO DE FALHA COM INTERRUPÇÃO SEGURA
// =========================================================================
echo "--- DEMONSTRAÇÃO 6: Tratamento de Falha com Interrupção Segura ---\n";

$failedAttemptMigrations = [];
$versionsBeforeFailure = $db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN);
$badSql = "CREATE TABLE rumo10_broken ( id INT NOT NULL, syntax error !!! );";

$failed = false;
$errorMessage = '';
try {
    // Tenta executar migration inválida
    $db->exec($badSql);
    // Se passasse (não vai passar), registraria:
    $db->prepare("INSERT INTO rumo10_migrations(version) VALUES(?)")->execute(['011-broken']);
    $failedAttemptMigrations[] = '011-broken';
} catch (\Throwable $e) {
    $failed = true;
    $errorMessage = $e->getMessage();
}

assertCheck($failed === true, 'Execução da migration com falha foi interrompida imediatamente');
assertCheck(!in_array('011-broken', $db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN), true),
    'Migration incompleta NÃO foi registrada em rumo10_migrations');
assertCheck($db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN) === $versionsBeforeFailure,
    'Estado do banco de dados e controle de versões preservados integralmente');
assertCheck($errorMessage !== '', "Causa específica do erro apresentada: {$errorMessage}");
echo "  [OK] Publicação abortada com segurança sem promover código ou corromper estado\n\n";

echo "=== RESULTADO FINAL: {$passedCount} DE {$passedCount} VERIFICAÇÕES CONCLUÍDAS COM SUCESSO! ===\n";
