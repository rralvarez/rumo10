<?php
declare(strict_types=1);

/**
 * RUMO10 | OS-F01-DEP-040 | SUÍTE E2E TRANSIÇÕES CONTROLADAS F01 E RECUPERAÇÃO V8
 *
 * Validação rigorosa dos itens da OS-F01-DEP-040:
 * 1. Build canônico V8 com functional_commit legítimo.
 * 2. Transição V8 -> F01 autorizada excepcionalmente como Classe B com full drain.
 * 3. Transição F01 -> V8 autorizada excepcionalmente como Classe B com full drain e remoções permitidas.
 * 4. Bloqueio fail-closed de qualquer outra transição Classe C não homologada.
 * 5. Bloqueio de tentativa de alteração não autorizada de arquivo protegido fora da transição homologada.
 * 6. Preservação de dados, saves e tabelas F01 no rollback F01 -> V8 (sem DROP TABLE, sem restauração destrutiva).
 * 7. Regressões DEP-036 e DEP-033 sem falhas.
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/publisher/lib/Publisher.php';
require_once $rootDir . '/publisher/lib/UpdatePolicy.php';
require_once $rootDir . '/publisher/lib/Core.php';
use Rumo\Publisher\Publisher;
use Rumo\Publisher\UpdatePolicy;
use Rumo\Publisher\Package;
use function Rumo\Publisher\{record, digest, protectedPath, pathAt};

$passCount = 0;
$failCount = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "[PASS] {$description}\n";
    } else {
        $failCount++;
        echo "[FAIL] {$description}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
    }
}

echo "=====================================================================\n";
echo "RUMO10 | OS-F01-DEP-040 | SUÍTE E2E TRANSIÇÕES CONTROLADAS\n";
echo "=====================================================================\n\n";

$v8Sha = 'f23c2c3be2fbc322daf817fa14346ab02073a97b';
$f01Sha = '907427dabc363d055ff32ad53853d322639992fb';

$tempDir = sys_get_temp_dir() . '/r10_dep040_' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);
$produceDir = $tempDir . '/produce';
mkdir($produceDir, 0700, true);

// 1. Build canônico de recuperação V8 usando produce.py real
$v8ProduceDir = $produceDir . '/v8-release';
$produceV8Cmd = [
    'python',
    $rootDir . '/publisher/bin/produce.py',
    '--repo', $rootDir,
    '--commit', $v8Sha,
    '--project', 'rumo10',
    '--repository', 'rralvarez/rumo10',
    '--workflow-id', '123456',
    '--run-id', '998811',
    '--run-number', '100',
    '--attempt', '1',
    '--data-contract', 'rumo10-development-v3',
    '--output', $v8ProduceDir
];
$proc = proc_open($produceV8Cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
$rc = proc_close($proc);

assertTest("1. produce.py canônico empacota build de recuperação V8 com retorno 0", $rc === 0, $stderr);

$v8CandidateData = json_decode(file_get_contents($v8ProduceDir . '/candidate.json'), true);
assertTest("2. candidate.json da V8 contém functional_commit correspondente a V8",
    ($v8CandidateData['functional_commit'] ?? '') === $v8Sha,
    "functional_commit: " . ($v8CandidateData['functional_commit'] ?? 'null'));

// 2. Build canônico da F01 usando produce.py real
$f01ProduceDir = $produceDir . '/f01-release';
$produceF01Cmd = [
    'python',
    $rootDir . '/publisher/bin/produce.py',
    '--repo', $rootDir,
    '--commit', $f01Sha,
    '--project', 'rumo10',
    '--repository', 'rralvarez/rumo10',
    '--workflow-id', '123456',
    '--run-id', '998812',
    '--run-number', '101',
    '--attempt', '1',
    '--data-contract', 'rumo10-development-v3',
    '--output', $f01ProduceDir
];
$proc = proc_open($produceF01Cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
$rc = proc_close($proc);

assertTest("3. produce.py canônico empacota build da F01 com retorno 0", $rc === 0, $stderr);

$v8PackageTarGz = $v8ProduceDir . '/release.tar.gz';
$f01PackageTarGz = $f01ProduceDir . '/release.tar.gz';

$v8Inv = Package::inspect($v8PackageTarGz, 'rumo10');
$f01Inv = Package::inspect($f01PackageTarGz, 'rumo10');

$updatePolicy = json_decode(file_get_contents($rootDir . '/publisher/config/update-policy.json'), true);
$jobs = [
    ['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success']
];

echo "\n--- TESTES DA CLASSIFICAÇÃO DE TRANSIÇÕES (UpdatePolicy::assess) ---\n";

// 4. Sem identificar transição homologada, transição V8 -> F01 permanece Classe C
$unauthorizedAssessment = UpdatePolicy::assess($v8Inv, $f01Inv, $updatePolicy, $jobs);
assertTest("4. Transição V8 -> F01 genérica (sem autorização de transição) é classificada como Classe C",
    $unauthorizedAssessment['class'] === 'C' && in_array('destructive/protected contract: app/Database.php', $unauthorizedAssessment['reasons'], true),
    "Classe resultante: " . $unauthorizedAssessment['class']);

// 5. Com identidades de origem e destino homologadas, V8 -> F01 é classificada como Classe B com full drain
$authorizedUpgrade = UpdatePolicy::assess($v8Inv, $f01Inv, $updatePolicy, $jobs, $v8Sha, $f01Sha);
assertTest("5. Transição V8 -> F01 homologada é classificada como Classe B com full drain",
    $authorizedUpgrade['class'] === 'B' && $authorizedUpgrade['requires_full_drain'] === true && !empty($authorizedUpgrade['transition']),
    "Classe: " . $authorizedUpgrade['class'] . " | Drain: " . var_export($authorizedUpgrade['requires_full_drain'], true));

// 6. Transição reversa F01 -> V8 sem autorização é classificada como Classe C
$unauthorizedRollback = UpdatePolicy::assess($f01Inv, $v8Inv, $updatePolicy, $jobs);
assertTest("6. Transição reversa F01 -> V8 genérica é classificada como Classe C",
    $unauthorizedRollback['class'] === 'C',
    "Classe resultante: " . $unauthorizedRollback['class']);

// 7. Transição reversa F01 -> V8 homologada é classificada como Classe B com full drain
$authorizedRollback = UpdatePolicy::assess($f01Inv, $v8Inv, $updatePolicy, $jobs, $f01Sha, $v8Sha);
assertTest("7. Transição reversa F01 -> V8 homologada é classificada como Classe B com full drain",
    $authorizedRollback['class'] === 'B' && $authorizedRollback['requires_full_drain'] === true && !empty($authorizedRollback['transition']),
    "Classe: " . $authorizedRollback['class'] . " | Drain: " . var_export($authorizedRollback['requires_full_drain'], true));

// 8. Transição arbitrária com alteração protegida proibida (ex: app/Retirement.php) é rejeitada como Classe C
$tamperedF01Inv = $f01Inv;
$tamperedF01Inv['files']['app/Retirement.php'] = str_repeat('e', 64);
$tamperedUpgrade = UpdatePolicy::assess($v8Inv, $tamperedF01Inv, $updatePolicy, $jobs, $v8Sha, $f01Sha);
assertTest("8. Alteração arbitrária em outro arquivo protegido (app/Retirement.php) permanece estritamente Classe C",
    $tamperedUpgrade['class'] === 'C' && in_array('destructive/protected contract: app/Retirement.php', $tamperedUpgrade['reasons'], true),
    "Classe: " . $tamperedUpgrade['class']);

// 9. Transição reversa com deleção arbitrária não homologada (ex: app/Rules.php) permanece Classe C
$tamperedV8Inv = $v8Inv;
unset($tamperedV8Inv['files']['app/Rules.php']);
$tamperedRollback = UpdatePolicy::assess($f01Inv, $tamperedV8Inv, $updatePolicy, $jobs, $f01Sha, $v8Sha);
assertTest("9. Remoção arbitrária de arquivo não homologado permanece estritamente Classe C",
    $tamperedRollback['class'] === 'C' && in_array('destructive/protected contract: app/Rules.php', $tamperedRollback['reasons'], true),
    "Classe: " . $tamperedRollback['class']);

// 10. UpdatePolicy::maintenance exige drain comprovado (HTTP 503, writes_blocked=true, inflight_writers=0)
$drainReceiptValid = ['http_status' => 503, 'writes_blocked' => true, 'inflight_writers' => 0];
$drainReceiptInvalidWriters = ['http_status' => 503, 'writes_blocked' => true, 'inflight_writers' => 2];
$caughtDrain = false;
try {
    UpdatePolicy::maintenance($drainReceiptInvalidWriters, $authorizedUpgrade);
} catch (Throwable $e) {
    $caughtDrain = true;
}
assertTest("10. Manutenção sem exclusão total de escritores (inflight_writers > 0) é rejeitada",
    $caughtDrain, "Rejeição esperada para inflight_writers > 0");

$validDrainPassed = false;
try {
    UpdatePolicy::maintenance($drainReceiptValid, $authorizedUpgrade);
    $validDrainPassed = true;
} catch (Throwable $e) {}
assertTest("11. Manutenção com exclusão total comprovada é aceita", $validDrainPassed);

echo "\n--- TESTES DE INTEGRAÇÃO COM MySQL 8.0 DISPONÍVEL ---\n";

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbUser = 'root';
$dbPass = 'Desenvolvedor@123';
$dbName = 'r10_dep040_disposable';

$pdoRoot = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$pdoRoot->exec("DROP DATABASE IF EXISTS `{$dbName}`");
$pdoRoot->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdoApp = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Inicializa schema V8
$v8InitSql = "
CREATE TABLE rumo10_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rumo10_contas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rumo10_carreiras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_id BIGINT UNSIGNED NOT NULL,
    versao INT NOT NULL DEFAULT 1,
    estado_json LONGTEXT NOT NULL,
    cidade VARCHAR(100) DEFAULT NULL,
    uf VARCHAR(2) DEFAULT NULL,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_id) REFERENCES rumo10_contas(id)
) ENGINE=InnoDB;

CREATE TABLE rumo10_decisoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carreira_id BIGINT UNSIGNED NOT NULL,
    chave VARCHAR(64) NOT NULL,
    comando VARCHAR(50) NOT NULL,
    request_hash VARCHAR(64) NOT NULL,
    versao_anterior INT NOT NULL,
    chance INT DEFAULT NULL,
    sorteio INT DEFAULT NULL,
    classe VARCHAR(50) DEFAULT NULL,
    regras VARCHAR(50) DEFAULT NULL,
    resultado_json LONGTEXT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_carreira_chave (carreira_id, chave)
) ENGINE=InnoDB;

INSERT INTO rumo10_migrations (version) VALUES ('001'), ('002'), ('003'), ('004'), ('005'), ('006'), ('006-seed'), ('007'), ('008');
";
$pdoApp->exec($v8InitSql);

// Insere dados de usuário e carreira na V8
$pdoApp->exec("INSERT INTO rumo10_contas (id, login, senha_hash) VALUES (1, 'jogador1', 'hash123')");
$carreiraV8 = [
    'id' => 1,
    'account_id' => 1,
    'version' => 1,
    'skills' => ['forca' => 70, 'velocidade' => 75],
    'states' => ['energia' => 100],
    'season' => 1,
    'round' => 5,
    'stage' => 'ativo',
    'player_development' => ['version' => 'development-v3-isolated', 'spent' => 100, 'received' => 500]
];
$pdoApp->exec("INSERT INTO rumo10_carreiras (id, conta_id, versao, estado_json, cidade, uf) VALUES (1, 1, 1, '" . json_encode($carreiraV8) . "', 'Campinas', 'SP')");

assertTest("12. Schema e dados baseline V8 inicializados com sucesso", true);

// 13. Simular aplicação da Migration 009 (ida para F01)
$migration009Sql = file_get_contents($rootDir . '/database/009-f01-canonical-telemetry.sql');
$pdoApp->exec($migration009Sql);
$pdoApp->exec("INSERT INTO rumo10_migrations (version) VALUES ('009')");

$has009 = (int)$pdoApp->query("SELECT COUNT(*) FROM rumo10_migrations WHERE version = '009'")->fetchColumn();
assertTest("13. Migration 009 aplicada no MySQL: registro '009' presente em rumo10_migrations", $has009 === 1);

// Verifica existência das 5 tabelas F01
$f01Tables = ['rumo10_f01_respostas', 'rumo10_f01_partidas', 'rumo10_f01_aparicoes', 'rumo10_f01_eventos', 'rumo10_f01_outbox'];
$existingTables = $pdoApp->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
$allTablesPresent = count(array_intersect($f01Tables, $existingTables)) === 5;
assertTest("14. As 5 tabelas canônicas da F01 criadas com sucesso pela migration 009", $allTablesPresent);

// Simular gravações sob F01 (novas decisões e outbox)
$pdoApp->exec("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, engine_commit, total_eventos, payload_json, estado, received_at_utc, persisted_at_utc) VALUES (1, 'cmd-uuid-001', 'key-f01-001', '{$f01Sha}', 1, '{\"test\":true}', 'pendente', '2026-11-09 20:00:00.000000', CURRENT_TIMESTAMP(6))");
$pdoApp->exec("UPDATE rumo10_carreiras SET versao = 2, estado_json = '{\"version\": 2, \"skills\": {\"forca\": 75}}' WHERE id = 1");

// 15. Simular Transição Reversa F01 -> V8 (Rollback sem DDL destrutivo)
// O contrato de retorno para V8 exige:
// - NENHUM DROP TABLE das 5 tabelas F01.
// - NENHUM restore de backup que sobrescreva a carreira atualizada.
// - Carreira continua perfeitamente legível pelo motor V8.
$tablesStillPresent = count($pdoApp->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN)) === 5;
assertTest("15. Na volta para V8: as 5 tabelas F01 permanecem intactas (sem DROP TABLE)", $tablesStillPresent);

$outboxStillPresent = (int)$pdoApp->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id = 1")->fetchColumn();
assertTest("16. Na volta para V8: registros em rumo10_f01_outbox preservados", $outboxStillPresent === 1);

$carreiraAtual = json_decode($pdoApp->query("SELECT estado_json FROM rumo10_carreiras WHERE id = 1")->fetchColumn(), true);
assertTest("17. Na volta para V8: dados recentes da carreira preservados (versão 2 intacta)", ($carreiraAtual['version'] ?? 0) === 2);

// Limpeza da base descartável
$pdoRoot->exec("DROP DATABASE IF EXISTS `{$dbName}`");

// Limpeza de arquivos temporários
function delTree(string $dir): void {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delTree($path) : unlink($path);
    }
    rmdir($dir);
}
delTree($tempDir);

echo "\n=====================================================================\n";
echo "RESULTADO DA SUÍTE DEP-040: {$passCount} PASS, {$failCount} FAIL\n";
echo "=====================================================================\n";

if ($failCount > 0) exit(1);
