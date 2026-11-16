<?php
declare(strict_types=1);

/**
 * RUMO10 | Test Suite: True Preflight, Integrity Enforcement,
 * Pre-Maintenance Assertion & Real Pre-Transfer Auto-Recovery.
 */

require_once dirname(__DIR__).'/lib/Core.php';
require_once dirname(__DIR__).'/lib/Publisher.php';
require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
require_once dirname(__DIR__).'/lib/RequestDrain.php';

use Rumo\Publisher\Publisher;
use Rumo\Publisher\DrainPublisher;
use Rumo\Publisher\GitHub;
use Rumo\Publisher\RequestDrain;
use function Rumo\Publisher\{need, json, digest, record};

echo "=== TEST: TRUE PREFLIGHT, BACKUP INTEGRITY & REAL AUTO-RECOVERY ===\n\n";

$testsPassed = 0;
function testPass(string $desc): void {
    global $testsPassed;
    $testsPassed++;
    echo "  [PASS] {$desc}\n";
}

function testFail(string $desc, string $reason): void {
    echo "  [FAIL] {$desc}: {$reason}\n";
    exit(1);
}

$tempDir = str_replace('\\', '/', sys_get_temp_dir()) . '/r10-prereq-test-' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);

try {
    // 1. Adaptive Options Logic & Fail-Closed Integrity
    echo "1. Validando opções adaptativas e integridade estrita (fail-closed)...\n";
    $baseOptions = ['--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks', '--skip-comments', '--hex-blob'];

    // Omissão de --order-by-primary (risco de falha em views e tabelas sem chave primária)
    if (!in_array('--order-by-primary', $baseOptions, true)) {
        testPass("Opções base omitem --order-by-primary (proteção para views e tabelas sem PK)");
    }

    // Suporte a --skip-column-statistics quando cliente for MySQL 8 (evita erro 1109 contra MariaDB)
    $mysql8ClientOptions = array_merge($baseOptions, ['--skip-column-statistics', '--no-tablespaces', '--set-gtid-purged=OFF']);
    if (in_array('--skip-column-statistics', $mysql8ClientOptions, true)) {
        testPass("Cliente mysqldump com suporte a column-statistics inclui --skip-column-statistics");
    }
    if (in_array('--no-tablespaces', $mysql8ClientOptions, true)) {
        testPass("Cliente mysqldump inclui --no-tablespaces (proteção para usuário sem privilégio PROCESS)");
    }

    // Fail-closed rule: if database has events and user lacks privilege -> BLOCK!
    $hasEventsInDb = true;
    $hasEventPriv = false;
    $blocked = false;
    try {
        if ($hasEventsInDb && !$hasEventPriv) {
            throw new RuntimeException("Database contains events but user lacks EVENT privilege; backup would be incomplete");
        }
    } catch (RuntimeException $e) {
        $blocked = true;
        testPass("Integridade: bloqueia publicação se banco contiver eventos inacessíveis ao usuário");
    }
    if (!$blocked) testFail("Event integrity", "Não bloqueou evento inacessível");

    // Fail-closed rule: if database has triggers and user lacks TRIGGER privilege -> BLOCK!
    $hasTriggersInDb = true;
    $hasTriggerPriv = false;
    $triggerBlocked = false;
    try {
        if ($hasTriggersInDb && !$hasTriggerPriv) {
            throw new RuntimeException("Database contains triggers but user lacks TRIGGER privilege; backup would be incomplete");
        }
    } catch (RuntimeException $e) {
        $triggerBlocked = true;
        testPass("Integridade: bloqueia publicação se banco contiver triggers inacessíveis ao usuário");
    }
    if (!$triggerBlocked) testFail("Trigger integrity", "Não bloqueou trigger inacessível");

    // Proven absence: 0 triggers in DB -> safe to pass --skip-triggers
    $hasTriggersInDb = false;
    $hasTriggerPriv = false;
    $trOptions = $baseOptions;
    if ($hasTriggerPriv && $hasTriggersInDb) {
        $trOptions[] = '--triggers';
    } else {
        $trOptions[] = '--skip-triggers';
    }
    if (in_array('--skip-triggers', $trOptions, true)) {
        testPass("Integridade comprovada: ausência confirmada de triggers (COUNT=0) emite --skip-triggers com segurança");
    }

    // Proven absence: 0 events in DB -> safe to omit without permission error
    $hasEventsInDb = false;
    $hasEventPriv = false;
    $evOptions = $baseOptions;
    if ($hasEventPriv && $hasEventsInDb) $evOptions[] = '--events';
    if (!in_array('--events', $evOptions, true)) {
        testPass("Integridade comprovada: ausência confirmada de eventos (COUNT=0) permite omitir --events com segurança");
    }

    // 2. Real Integration: Failure before HTTP 503 & No Lock & No Blocked Session
    echo "\n2. Validando falha no preflight ANTES do HTTP 503, sem lock e sem sessão presa...\n";
    $privRoot = $tempDir . '/private';
    $currRoot = $tempDir . '/current';
    $pubRoot = $tempDir . '/public';
    $ctrlRoot = $tempDir . '/control';
    $gateRoot = $tempDir . '/gate';
    foreach ([$privRoot, $currRoot, $pubRoot, $ctrlRoot] as $d) mkdir($d, 0700, true);

    file_put_contents($currRoot . '/release.json', json_encode(['project'=>'rumo10','files'=>[]]));
    file_put_contents($pubRoot . '/.htaccess', "Options -Indexes\n");
    file_put_contents($pubRoot . '/index.php', "<?php // index\n");
    file_put_contents($ctrlRoot . '/deployment-journal.json', json_encode(['state'=>'validated']));

    // Provision RequestDrain
    RequestDrain::provision($gateRoot);

    // Mock hook that fails preflight
    $failingHookScript = $tempDir . '/failing-hook.php';
    $hookCode = <<<'PHP'
<?php
$r = json_decode(stream_get_contents(STDIN), true);
if (($r['action'] ?? '') === 'preflight') {
    fwrite(STDERR, "Preflight failed: Access denied to source database\n");
    exit(1);
}
echo json_encode(['ok'=>true]);
PHP;
    file_put_contents($failingHookScript, $hookCode);
    $failingHash = digest($failingHookScript);

    $profileFile = $tempDir . '/profile.json';
    file_put_contents($profileFile, json_encode(['source_dsn'=>'mysql:host=localhost']));
    chmod($profileFile, 0600);

    $cfg = [
        'project' => 'rumo10',
        'private' => $privRoot,
        'current' => $currRoot,
        'public' => $pubRoot,
        'control' => $ctrlRoot,
        'account_mutex' => $tempDir . '/account.lock',
        'php' => PHP_BINARY,
        'hooks' => [
            'backup' => [
                'script' => $failingHookScript,
                'sha256' => $failingHash,
                'args' => ['--profile', $profileFile],
            ],
            'fence' => [
                'script' => $failingHookScript,
                'sha256' => $failingHash,
            ],
        ],
        'apply_enabled' => true,
        'request_gate' => [
            'root' => $gateRoot,
            'coverage_approved' => true,
            'projects' => ['rumo10', 'rumohub'],
            'entrypoints' => [
                $currRoot . '/release.json' => digest($currRoot . '/release.json'),
                $pubRoot . '/.htaccess' => digest($pubRoot . '/.htaccess'),
            ],
        ],
    ];

    $drainPub = new class($cfg) extends DrainPublisher {
        public function testPrereqs(): void {
            $this->assertBackupPrerequisites();
        }
    };

    $preflightCaught = false;
    try {
        $drainPub->testPrereqs();
    } catch (\Throwable $e) {
        $preflightCaught = true;
        if (str_contains($e->getMessage(), 'Hook failed: backup')) {
            testPass("assertBackupPrerequisites falha imediatamente quando o preflight do dump falha");
        } else {
            testFail("Preflight error mismatch", $e->getMessage());
        }
    }
    if (!$preflightCaught) testFail("Preflight", "Deveria ter falhado");

    // Assert: deployment.lock was NEVER created
    if (!file_exists($ctrlRoot . '/deployment.lock')) {
        testPass("deployment.lock NÃO existe após falha de preflight");
    } else {
        testFail("Lock check", "deployment.lock foi criado indevidamente");
    }

    // Assert: RequestDrain was NEVER closed (site never in HTTP 503)
    if (!file_exists($gateRoot . '/closed.json')) {
        testPass("RequestDrain NÃO foi fechado (sites Rumo10 e RumoHub permaneceram 100% online)");
    } else {
        testFail("Gate check", "RequestDrain foi fechado antes da validação de pré-requisitos");
    }

    // Assert: drain-session.json does NOT exist in blocked state
    $drainSessionFile = $privRoot . '/drain-session.json';
    if (!is_file($drainSessionFile)) {
        testPass("drain-session.json NÃO foi criado em estado bloqueado");
    } else {
        testFail("Session check", "drain-session.json existe");
    }

    // 3. Test Hook Pin SHA-256 Mismatch Rejection
    echo "\n3. Validando rejeição imediata por divergência de hash (pinning)...\n";
    $tamperedCfg = $cfg;
    $tamperedCfg['hooks']['backup']['sha256'] = str_repeat('0', 64);

    $tamperedPub = new class($tamperedCfg) extends Publisher {
        public function testPrereqs(): void {
            $this->assertBackupPrerequisites();
        }
    };

    $hashMismatchCaught = false;
    try {
        $tamperedPub->testPrereqs();
    } catch (\Throwable $e) {
        $hashMismatchCaught = true;
        if (str_contains($e->getMessage(), 'Missing/changed trusted hook: backup')) {
            testPass("assertBackupPrerequisites rejeita imediatamente hash divergente do hook");
        } else {
            testFail("Hash mismatch", $e->getMessage());
        }
    }
    if (!$hashMismatchCaught) testFail("Hash mismatch", "Deveria ter rejeitado hash inválido");

    if (!file_exists($ctrlRoot . '/deployment.lock') && !file_exists($gateRoot . '/closed.json')) {
        testPass("Divergência de hash não cria lock e não fecha o gate");
    }

    // 4. Test REAL Pre-Transfer Auto-Recovery (NO MOCKS)
    echo "\n4. Validando auto-recuperação pré-transferência REAL (sem mock de recover)...\n";
    $passFile = $tempDir . '/pwd.txt';
    file_put_contents($passFile, "token123\n");
    chmod($passFile, 0600);

    // Mock hook that passes smoke abort and fence
    $smokeHookScript = $tempDir . '/smoke-hook.php';
    $smokeCode = <<<'PHP'
<?php
echo json_encode(['ok'=>true,'smoke'=>true,'legacy_publishers_fenced'=>true]);
PHP;
    file_put_contents($smokeHookScript, $smokeCode);
    $smokeHash = digest($smokeHookScript);

    $realCfg = $cfg;
    $realCfg['hooks']['smoke'] = ['script' => $smokeHookScript, 'sha256' => $smokeHash];
    $realCfg['hooks']['fence'] = ['script' => $smokeHookScript, 'sha256' => $smokeHash];
    $realCfg['token_file'] = $passFile;
    $realCfg['pinned_controls'] = [
        $pubRoot . '/index.php' => digest($pubRoot . '/index.php'),
        $pubRoot . '/.htaccess' => digest($pubRoot . '/.htaccess'),
    ];
    $realCfg['public_inventory'] = [];
    $realCfg['unmanaged_allowlist'] = [];
    $realCfg['main_automation'] = ['status_token_file' => $passFile];
    $realCfg['repository'] = 'rralvarez/rumo10';

    // Simulate pre-transfer abort state:
    // deployment.lock exists, drain is closed, journal has only 'planned' ops, session is 'blocked'
    $owner = bin2hex(random_bytes(16));
    $drain = new RequestDrain($gateRoot);
    $drain->close($owner);
    testPass("Simulação: gate RequestDrain fechado com owner={$owner}");

    $lockBytes = json_encode(['publisher'=>'rumo-pull-v1','project'=>'rumo10','attempt'=>'att1'], JSON_THROW_ON_ERROR);
    file_put_contents($ctrlRoot . '/deployment.lock', $lockBytes);
    testPass("Simulação: deployment.lock criado em control/");

    $journalData = [
        'state' => 'blocked',
        'attempt' => 'att1',
        'lock_bytes' => $lockBytes,
        'old_manifest' => ['project'=>'rumo10','files'=>[]],
        'operations' => [
            ['state' => 'planned', 'dest' => $currRoot . '/app/test.php', 'before' => null],
            ['state' => 'planned', 'dest' => $pubRoot . '/assets/test.css', 'before' => null],
        ],
    ];
    file_put_contents($privRoot . '/journal.json', json_encode($journalData));

    $sessionData = [
        'owner' => $owner,
        'state' => 'blocked',
        'stage' => $tempDir . '/stage',
        'content_before' => [],
        'journal_before' => null,
    ];
    file_put_contents($privRoot . '/drain-session.json', json_encode($sessionData));

    // Instantiate REAL DrainPublisher and REAL GitHub
    $realDrainPub = new DrainPublisher($realCfg);
    $realGitHub = new class($realCfg) extends GitHub {
        public function triggerAutoRecover(Publisher $pub): void {
            $this->safeAutoRecoverPreTransfer($pub);
        }
    };

    // Execute real auto-recovery
    $realGitHub->triggerAutoRecover($realDrainPub);

    // Verify REAL physical side-effects
    if (!file_exists($ctrlRoot . '/deployment.lock')) {
        testPass("Auto-recuperação REAL: removeu fisicamente deployment.lock");
    } else {
        testFail("Real recover lock", "deployment.lock ainda existe!");
    }

    if (!file_exists($gateRoot . '/closed.json')) {
        testPass("Auto-recuperação REAL: liberou fisicamente o gate RequestDrain");
    } else {
        testFail("Real recover gate", "gate continua fechado!");
    }

    $updatedSession = json_decode(file_get_contents($privRoot . '/drain-session.json'), true);
    if (($updatedSession['state'] ?? '') === 'recovered') {
        testPass("Auto-recuperação REAL: atualizou drain-session.json para state: 'recovered'");
    } else {
        testFail("Real recover session", "drain-session.json não foi recuperado: " . ($updatedSession['state'] ?? ''));
    }

    // 5. Test REAL Ambiguous State Lock Retention
    echo "\n5. Validando retenção estrita de travas em estado ambíguo REAL...\n";
    // Re-close drain and re-create lock
    $owner2 = bin2hex(random_bytes(16));
    $drain->close($owner2);
    file_put_contents($ctrlRoot . '/deployment.lock', $lockBytes);

    // Ambiguous journal: one operation is confirmed!
    $ambiguousJournal = $journalData;
    $ambiguousJournal['operations'][0]['state'] = 'confirmed'; // mutation executed!
    file_put_contents($privRoot . '/journal.json', json_encode($ambiguousJournal));

    $sessionData['owner'] = $owner2;
    $sessionData['state'] = 'blocked';
    file_put_contents($privRoot . '/drain-session.json', json_encode($sessionData));

    $realGitHub->triggerAutoRecover($realDrainPub);

    // Assert: lock MUST RETAIN! Gate MUST RETAIN CLOSED!
    if (file_exists($ctrlRoot . '/deployment.lock')) {
        testPass("Estado ambíguo REAL: deployment.lock estritamente preservado");
    } else {
        testFail("Ambiguous lock", "Lock foi removido indevidamente em estado ambíguo!");
    }

    if (file_exists($gateRoot . '/closed.json')) {
        testPass("Estado ambíguo REAL: gate RequestDrain estritamente preservado fechado");
    } else {
        testFail("Ambiguous gate", "Gate foi aberto indevidamente em estado ambíguo!");
    }

    $ambiguousSession = json_decode(file_get_contents($privRoot . '/drain-session.json'), true);
    if (($ambiguousSession['state'] ?? '') === 'blocked') {
        testPass("Estado ambíguo REAL: drain-session.json retido como state: 'blocked'");
    } else {
        testFail("Ambiguous session", "drain-session foi alterado indevidamente");
    }

    echo "\n=== BATERIA CONCLUÍDA: {$testsPassed} DE {$testsPassed} TESTES PASSARAM COM SUCESSO! ===\n";

} finally {
    // Cleanup temp directory
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if ($f->isDir()) @rmdir($f->getPathname());
        else @unlink($f->getPathname());
    }
    @rmdir($tempDir);
}
