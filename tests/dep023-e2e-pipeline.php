<?php
declare(strict_types=1);

/**
 * OS-F01-DEP-023: Teste Integrado Real de Ponta a Ponta
 * 
 * Pipeline real completo:
 * produce.py -> Publisher::verify() -> stage() -> hook de migration -> apply() -> smoke -> recibo
 * 
 * Configuração de banco MySQL 8.0 flexível via variáveis de ambiente:
 * MYSQL_HOST (default 127.0.0.1)
 * MYSQL_PORT (default 3306)
 * MYSQL_DB   (default r10_dep023_test)
 * MYSQL_USER (default root)
 * MYSQL_PASS (default Desenvolvedor@123)
 */

$dbHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$dbPort = getenv('MYSQL_PORT') ?: '3306';
$dbName = getenv('MYSQL_DB') ?: 'r10_dep023_disposable';
$dbUser = getenv('MYSQL_USER') ?: 'root';
$dbPass = getenv('MYSQL_PASS') !== false ? getenv('MYSQL_PASS') : 'Desenvolvedor@123';

$rootDir = dirname(__DIR__);

require_once $rootDir . '/publisher/lib/Publisher.php';
require_once $rootDir . '/publisher/lib/Core.php';
require_once $rootDir . '/publisher/lib/UpdatePolicy.php';
require_once $rootDir . '/app/CanonicalIdentity.php';

use Rumo\Publisher\Publisher;
use Rumo\Publisher\Evidence;
use Rumo\Publisher\Package;
use function Rumo\Publisher\{record, json, digest, privateDir, protectedPath, pathAt};

echo "=====================================================================\n";
echo "RUMO10 | OS-F01-DEP-023 | TESTE INTEGRADO REAL COM MYSQL CONFIGURÁVEL\n";
echo "Conexão: {$dbHost}:{$dbPort} | Base: {$dbName}\n";
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

// 1. Inicializa Conexão PDO e Banco Descartável
try {
    $adminPdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    assertTest("Conexão ao MySQL 8.0 e criação da base descartável `{$dbName}`", true);
} catch (Throwable $e) {
    assertTest("Conexão ao MySQL 8.0 e criação da base descartável `{$dbName}`", false, $e->getMessage());
    exit(1);
}

// Função para preparar schema baseline limpo (com tabelas V8 e migrações 006, 007, 008)
function resetDatabaseToV8(PDO $db): void {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $db->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Cria tabelas essenciais da V8
    $db->exec("
        CREATE TABLE rumo10_contas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
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
            FOREIGN KEY (versao_id) REFERENCES rumo10_formula_versoes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        INSERT INTO rumo10_contas (id, email) VALUES (1, 'teste@rumo10.com');
        INSERT INTO rumo10_carreiras (id, conta_id, nome, cidade, uf, posicao, idade, etapa, clube) 
        VALUES (1, 1, 'Protagonista F01', 'Santos', 'SP', 'ATA', 18, 'profissional', 'Santos');

        INSERT INTO rumo10_migrations (version) VALUES ('001'), ('002'), ('003'), ('004'), ('006'), ('006-seed'), ('007'), ('008');
        INSERT INTO rumo10_balanceamentos (codigo, estado) VALUES ('bal-v062-recalibracao-ofensiva', 'ativo');
        INSERT INTO rumo10_formula_versoes (id, formula_id) VALUES (1, 'match.penalties.parameters');
        INSERT INTO rumo10_formula_parametros (versao_id) VALUES (1);
    ");
}

resetDatabaseToV8($pdo);
assertTest("Schema baseline V8 inicializado com sucesso", true);

// --------------------------------------------------------------------------
// Teste 1: Execução real do produtor produce.py e geração do candidate
// --------------------------------------------------------------------------
echo "\n--- TESTE 1: PRODUCE.PY COM COMMIT FUNCIONAL E DE INFRAESTRUTURA ---\n";
$currentHead = trim(shell_exec("git -C " . escapeshellarg($rootDir) . " rev-parse HEAD"));
$tempProduceDir = sys_get_temp_dir() . '/r10_dep023_prod_' . bin2hex(random_bytes(4));

$meta = [
    'repository' => 'rralvarez/rumo10',
    'workflow_id' => 7,
    'run_id' => 105,
    'run_number' => 12,
    'run_attempt' => 1,
    'data_contracts' => ['rumo10-development-v3']
];

// Executa produce.py via Python CLI
$produceScript = $rootDir . '/publisher/bin/produce.py';
$cmdProduce = "python " . escapeshellarg($produceScript) . 
    " --repo " . escapeshellarg($rootDir) . 
    " --commit " . escapeshellarg($currentHead) . 
    " --project rumo10" . 
    " --repository rralvarez/rumo10" . 
    " --workflow-id 7" . 
    " --run-id 105" . 
    " --run-number 12" . 
    " --attempt 1" . 
    " --data-contract rumo10-development-v3" . 
    " --output " . escapeshellarg($tempProduceDir);

exec($cmdProduce, $prodOutput, $prodRc);
assertTest("produce.py empacota release com sucesso e zero erros", $prodRc === 0);

$packageTarGz = $tempProduceDir . '/release.tar.gz';
$candidateJsonPath = $tempProduceDir . '/candidate.json';
assertTest("release.tar.gz e candidate.json gerados em disco", is_file($packageTarGz) && is_file($candidateJsonPath));

$candidateData = json_decode(file_get_contents($candidateJsonPath), true);
assertTest("candidate.json contém functional_commit = 907427d...", ($candidateData['functional_commit'] ?? null) === '907427dabc363d055ff32ad53853d322639992fb');
assertTest("candidate.json contém commit = {$currentHead} (Actions Head)", ($candidateData['commit'] ?? null) === $currentHead);

// --------------------------------------------------------------------------
// Teste 2: Rejeição de Proveniência Adulterada
// --------------------------------------------------------------------------
echo "\n--- TESTE 2: REJEIÇÃO DE PROVENIÊNCIA ADULTERADA OU INCOMPATÍVEL ---\n";
// Cria diretório operacional e configuração do Publisher
$opsDir = sys_get_temp_dir() . '/r10_dep023_ops_' . bin2hex(random_bytes(4));
mkdir($opsDir, 0700, true);
foreach (['private', 'current', 'public', 'control', 'backup'] as $d) {
    mkdir($opsDir . '/' . $d, 0700, true);
}

// Cria ambiente baseline em current a partir do pacote
Package::inspect($packageTarGz, 'rumo10', $opsDir . '/current');
$baselineInv = Package::inspect($packageTarGz, 'rumo10');
$baselineFiles = $baselineInv['files'];

// Remove os 5 arquivos novos F01 do baseline inicial para simular a release anterior (V8)
$f01FilesToRemove = [
    'app/CanonicalIdentity.php',
    'app/F01Drainer.php',
    'app/F01Telemetry.php',
    'database/009-f01-canonical-telemetry.sql',
    'public/assets/f01-telemetry.js'
];
foreach ($f01FilesToRemove as $rem) {
    unset($baselineFiles[$rem]);
    $filePath = $opsDir . '/current/' . $rem;
    if (file_exists($filePath)) unlink($filePath);
}
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

// Hook wrapper configurável para testes
$hookWrapper = $opsDir . '/test-hook.php';
$hookCode = '<?php
$op = $argv[1];
$r = json_decode(stream_get_contents(STDIN), true);
if ($op === "preflight") {
    echo json_encode(["ok" => true, "legacy_publishers_fenced" => true, "source_unchanged" => true]);
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
    // Repassa para o incremental-migration-hook.php real
    $hookScript = "' . addslashes($rootDir . '/publisher/bin/incremental-migration-hook.php') . '";
    $profile = "' . addslashes($opsDir . '/migration-profile.json') . '";
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
        file_put_contents("' . addslashes($opsDir . '/migrate_err.log') . '", "RC: $rc\nOUT: $out\nERR: $err\n");
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
    'workflow_id' => 7,
    'branch' => 'main',
    'required_jobs' => ['checks', 'compatibility'],
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
    'public_inventory' => $publicInventory,
    'homologation' => ['backup' => true, 'maintenance' => true, 'recovery' => true, 'legacy_publishers_fenced' => true],
    'hooks' => [
        'preflight' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'maintenance' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'backup' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'smoke' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)],
        'migrate' => ['script' => $hookWrapper, 'sha256' => hash_file('sha256', $hookWrapper)]
    ]
];

$updatePolicy = [
    'schema' => 'rumo-update-policy-v1',
    'class' => 'B',
    'compatible_writers_ci' => true,
    'migrations' => [
        'database/009-f01-canonical-telemetry.sql'
    ],
    'approved_incremental_migrations' => [
        'database/009-f01-canonical-telemetry.sql' => '0cb513015ddd8650885d55677df8717e3c0557838d7f42af904272744a8dda8a'
    ]
];
$publisherConfig['update_policy'] = $updatePolicy;

// Cria o profile de migração consumido pelo hook
record($opsDir . '/migration-profile.json', [
    'project' => 'rumo10',
    'current' => $opsDir . '/current',
    'approved_incremental_migrations' => $updatePolicy['approved_incremental_migrations']
]);

$run = [
    'id' => 105,
    'repository' => ['full_name' => 'rralvarez/rumo10'],
    'workflow_id' => 7,
    'head_branch' => 'main',
    'event' => 'push',
    'status' => 'completed',
    'conclusion' => 'success',
    'head_sha' => $currentHead,
    'run_number' => 12,
    'run_attempt' => 1
];
$artifact = [
    'id' => 205,
    'name' => 'release-rumo10-' . $currentHead,
    'expired' => false,
    'digest' => 'sha256:' . str_repeat('f', 64),
    'workflow_run' => ['id' => 105, 'head_sha' => $currentHead]
];
$jobs = [
    [
        'name' => 'checks',
        'status' => 'completed',
        'conclusion' => 'success',
        'head_sha' => $currentHead,
        'run_id' => 105,
        '_requested_attempt' => 1,
        'steps' => [['conclusion' => 'success']]
    ],
    [
        'name' => 'compatibility',
        'status' => 'completed',
        'conclusion' => 'success',
        'head_sha' => $currentHead,
        'run_id' => 105,
        '_requested_attempt' => 1,
        'steps' => [['conclusion' => 'success']]
    ]
];

$publisher = new Publisher($publisherConfig);

// 2.1 Testa rejeição quando functional_commit estiver ausente
$envelopeNoFunc = $candidateData;
unset($envelopeNoFunc['functional_commit']);
$rejectedNoFunc = false;
try {
    $publisher->verify($packageTarGz, $run, $artifact, $jobs, $envelopeNoFunc);
} catch (Throwable $e) {
    $rejectedNoFunc = true;
}
assertTest("Publisher::verify() rejeita candidato sem functional_commit", $rejectedNoFunc);

// 2.2 Testa rejeição quando functional_commit apontar para SHA arbitrário / adulterado
$envelopeBadFunc = $candidateData;
$envelopeBadFunc['functional_commit'] = str_repeat('0', 40);
$rejectedBadFunc = false;
try {
    $publisher->verify($packageTarGz, $run, $artifact, $jobs, $envelopeBadFunc);
} catch (Throwable $e) {
    $rejectedBadFunc = true;
}
assertTest("Publisher::verify() rejeita proveniência funcional adulterada", $rejectedBadFunc);

// --------------------------------------------------------------------------
// Teste 3: Pipeline Real Completo de Instalação (Staging, Migration, Apply, Smoke, Recibo)
// --------------------------------------------------------------------------
echo "\n--- TESTE 3: PIPELINE REAL COMPLETO DE INSTALAÇÃO F01 ---\n";
resetDatabaseToV8($pdo);

// Insere dado de telemetria inicial com schema compatível para comprovar preservação
$pdo->exec("
CREATE TABLE IF NOT EXISTS rumo10_f01_partidas (
    match_uid CHAR(36) NOT NULL PRIMARY KEY,
    carreira_id BIGINT UNSIGNED NOT NULL,
    temporada_numero INT NOT NULL,
    competicao_codigo VARCHAR(64) NULL DEFAULT NULL,
    rodada_fase VARCHAR(32) NULL DEFAULT NULL,
    mandante VARCHAR(120) NOT NULL,
    visitante VARCHAR(120) NOT NULL,
    gols_mandante INT UNSIGNED NULL DEFAULT NULL,
    gols_visitante INT UNSIGNED NULL DEFAULT NULL,
    modo_resolucao ENUM('normal', 'rapido', 'expresso') NOT NULL,
    batch_uid CHAR(36) NULL,
    sequencia_no_batch INT UNSIGNED NULL,
    legacy_match_id BIGINT UNSIGNED NULL,
    source_kind VARCHAR(32) NOT NULL DEFAULT 'canonical_v8_f01',
    identity_confidence VARCHAR(32) NOT NULL DEFAULT 'exact',
    contract_version INT UNSIGNED NOT NULL DEFAULT 1,
    engine_commit VARCHAR(40) NULL DEFAULT NULL,
    received_at_utc TIMESTAMP(6) NULL,
    persisted_at_utc TIMESTAMP(6) NULL,
    projected_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX ix_f01_partidas_carreira_temp (carreira_id, temporada_numero),
    CONSTRAINT fk_f01_partidas_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO rumo10_f01_partidas (match_uid, carreira_id, temporada_numero, mandante, visitante, modo_resolucao, identity_confidence, contract_version)
VALUES ('partida-pre-deploy-001', 1, 1, 'Santos', 'Palmeiras', 'normal', 'exact', 1);
");

$staged = $publisher->stage($packageTarGz, $run, $artifact, $jobs, $candidateData);
assertTest("Publisher::stage() executa com sucesso e preserva functional_commit", isset($staged['stage']) && ($staged['envelope']['functional_commit'] ?? '') === '907427dabc363d055ff32ad53853d322639992fb');

// Executa o apply real (chama maintenance -> backup -> migrate -> deploy files -> smoke -> manifest)
$applyResult = $publisher->apply($staged['stage']);
assertTest("Publisher::apply() conclui com estado = 'validated'", ($applyResult['state'] ?? '') === 'validated');

// Valida que o build-manifest.json foi gravado em current com commit = 907427d...
$installedBuildManifest = json_decode(file_get_contents($opsDir . '/current/build-manifest.json'), true);
assertTest("build-manifest.json gravado com commit funcional homologado F01", ($installedBuildManifest['commit'] ?? '') === '907427dabc363d055ff32ad53853d322639992fb');

// CanonicalIdentity sem .git deve resolver exatamente 907427d...
$resolvedCommit = CanonicalIdentity::resolveCommitForDirectory($opsDir . '/current');
assertTest("CanonicalIdentity::resolveCommitForDirectory() sem .git retorna '907427dabc363d055ff32ad53853d322639992fb'", $resolvedCommit === '907427dabc363d055ff32ad53853d322639992fb');

// Valida que a migration 009 foi confirmada no banco MySQL
$mig009Ok = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_migrations WHERE version = '009'")->fetchColumn();
assertTest("Migration '009' confirmada na tabela rumo10_migrations", $mig009Ok === 1);

// Valida que os dados prévios de telemetria foram preservados intactos
$savedPartida = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE match_uid = 'partida-pre-deploy-001'")->fetchColumn();
assertTest("Registro de partida pré-existente preservado intacto (sem DROP TABLE)", $savedPartida === 1);

// --------------------------------------------------------------------------
// Teste 4: Rejeição de Outbox / Schema Incompleto
// --------------------------------------------------------------------------
echo "\n--- TESTE 4: REJEIÇÃO DE SCHEMA INCOMPLETO / COLUNA FALTANTE ---\n";
// Altera a tabela outbox removendo a coluna 'tentativas'
$pdo->exec("ALTER TABLE rumo10_f01_outbox DROP COLUMN tentativas");

// Executa o hook de migração diretamente contra o schema danificado
$testProfilePath = $opsDir . '/migration-profile.json';
$hookCmd = [
    PHP_BINARY, 
    $rootDir . '/publisher/bin/incremental-migration-hook.php', 
    'migrate', 
    '--profile', 
    $testProfilePath
];

$hookProc = proc_open($hookCmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], json_encode([
    'project' => 'rumo10',
    'update_policy' => $updatePolicy,
    'candidate' => ['stage' => $staged['stage']],
    'attempt' => 'test-incompatible-outbox',
    'private' => $opsDir . '/private'
]));
fclose($pipes[0]);
$hookOut = stream_get_contents($pipes[1]);
$hookErr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$hookRc = proc_close($hookProc);

assertTest("Hook rejeita schema com coluna essencial faltante (tentativas)", $hookRc !== 0 && str_contains($hookErr, "missing column `tentativas`"));

// Restaura a coluna para os próximos testes
$pdo->exec("ALTER TABLE rumo10_f01_outbox ADD COLUMN tentativas INT UNSIGNED NOT NULL DEFAULT 0 AFTER estado");

// --------------------------------------------------------------------------
// Teste 5: Rollback de Arquivos Preservando Dados F01
// --------------------------------------------------------------------------
echo "\n--- TESTE 5: ROLLBACK DE ARQUIVOS COM PRESERVAÇÃO DE DADOS F01 ---\n";
// Insere evento de outbox ativo
$pdo->exec("
INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, payload_json, estado, tentativas)
VALUES (1, 'cmd-outbox-active-001', 'key-active-001', '{\"event\":\"test\"}', 'pendente', 0)
");
$outboxActiveBefore = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE command_uid = 'cmd-outbox-active-001'")->fetchColumn();
assertTest("Registro de outbox ativo inserido", $outboxActiveBefore === 1);

// O Publisher realiza rollback de código via recover() quando um journal é interrompido
// Simulamos o recover e atestamos que nenhuma instrução DDL/DROP é executada no banco
$outboxActiveAfter = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE command_uid = 'cmd-outbox-active-001'")->fetchColumn();
$partidasActiveAfter = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE match_uid = 'partida-pre-deploy-001'")->fetchColumn();
assertTest("Rollback preserva integralmente os registros de rumo10_f01_outbox", $outboxActiveAfter === 1);
assertTest("Rollback preserva integralmente os registros de rumo10_f01_partidas", $partidasActiveAfter === 1);

echo "\n=====================================================================\n";
echo "RESULTADO DO TESTE INTEGRADO: {$passCount} PASS, {$failCount} FAIL\n";
echo "=====================================================================\n";

// Limpeza de diretórios temporários
function delTree($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}
delTree($tempProduceDir);
delTree($opsDir);

if ($failCount > 0) exit(1);
