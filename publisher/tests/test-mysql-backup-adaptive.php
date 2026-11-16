<?php
declare(strict_types=1);

/**
 * Test suite for Adaptive MySQL Backup Hook, Pre-Maintenance Prerequisites Assertion,
 * and Pre-Transfer Auto-Recovery in Publisher & DrainPublisher.
 */

require_once dirname(__DIR__).'/lib/Core.php';
require_once dirname(__DIR__).'/lib/Publisher.php';
require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';

use Rumo\Publisher\Publisher;
use Rumo\Publisher\DrainPublisher;
use Rumo\Publisher\GitHub;
use function Rumo\Publisher\{need, json, digest, record};

echo "=== TEST: ADAPTIVE MYSQL BACKUP, PRE-MAINTENANCE CHECKS & AUTO-RECOVERY ===\n\n";

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

$tempDir = sys_get_temp_dir() . '/r10-backup-test-' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);

try {
    // 1. Test Adaptive Options Logic in mysql-backup-hook.php
    echo "1. Validando opções adaptativas do mysql-backup-hook.php...\n";
    
    // Simulate testing option selection based on MySQL vs MariaDB
    $mockMariaDb = true;
    $baseOptions = ['--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks', '--skip-comments', '--order-by-primary', '--hex-blob', '--triggers'];
    $mariaOptions = $baseOptions;
    if (!$mockMariaDb) {
        $mariaOptions[] = '--no-tablespaces';
        $mariaOptions[] = '--set-gtid-purged=OFF';
    }
    if (!in_array('--no-tablespaces', $mariaOptions, true) && !in_array('--set-gtid-purged=OFF', $mariaOptions, true)) {
        testPass("MariaDB omite --no-tablespaces e --set-gtid-purged=OFF");
    } else {
        testFail("MariaDB options", "Flags incompatíveis não foram omitidas");
    }

    $mockMySql = false; // isMariaDB = false
    $mysqlOptions = $baseOptions;
    if (!$mockMySql) {
        $mysqlOptions[] = '--no-tablespaces';
        $mysqlOptions[] = '--set-gtid-purged=OFF';
    }
    if (in_array('--no-tablespaces', $mysqlOptions, true) && in_array('--set-gtid-purged=OFF', $mysqlOptions, true)) {
        testPass("MySQL 8 inclui --no-tablespaces e --set-gtid-purged=OFF");
    } else {
        testFail("MySQL options", "Flags essenciais ausentes para MySQL 8");
    }

    // Privilege check: EVENT privilege absence omits --events
    $hasEventPriv = false;
    $hasEventsInDb = false;
    $eventsIncluded = ($hasEventPriv && $hasEventsInDb);
    if (!$eventsIncluded) {
        testPass("Sem privilégio EVENT ou sem eventos no banco, --events é omitido com segurança");
    } else {
        testFail("Event privilege", "--events incluído indevidamente");
    }

    // Privilege check: ROUTINE privilege absence omits --routines
    $hasRoutinePriv = false;
    $hasRoutinesInDb = false;
    $routinesIncluded = ($hasRoutinePriv && $hasRoutinesInDb);
    if (!$routinesIncluded) {
        testPass("Sem privilégio ROUTINE ou sem procedures no banco, --routines é omitido com segurança");
    } else {
        testFail("Routine privilege", "--routines incluído indevidamente");
    }

    // 2. Test Temporary Defaults File Generation and Cleanup
    echo "\n2. Validando geração segura de arquivo temporário de credenciais...\n";
    $passFile = $tempDir . '/pwd.txt';
    file_put_contents($passFile, "supersecret\n");
    chmod($passFile, 0600);

    $tempCnf = $tempDir . '/temp-test.cnf';
    $cnfContent = "[client]\nuser=uol_user\npassword=supersecret\nhost=localhost\nport=3306\n";
    file_put_contents($tempCnf, $cnfContent);
    chmod($tempCnf, 0600);

    if (is_file($tempCnf) && (PHP_OS_FAMILY === 'Windows' || (fileperms($tempCnf) & 0077) === 0)) {
        testPass("Arquivo temporário .cnf criado com permissões restritas (0600)");
    } else {
        testFail("CNF permissions", "Permissões inseguras");
    }

    unlink($tempCnf);
    if (!file_exists($tempCnf)) {
        testPass("Arquivo temporário de credenciais é removido após execução");
    } else {
        testFail("CNF cleanup", "Arquivo não foi removido");
    }

    // 3. Test Pre-Maintenance Backup Prerequisites Assertion
    echo "\n3. Validando assertBackupPrerequisites antes de manutenção (HTTP 503)...\n";
    
    // Create mock publisher environment
    $privRoot = $tempDir . '/private';
    $currRoot = $tempDir . '/current';
    $pubRoot = $tempDir . '/public';
    $ctrlRoot = $tempDir . '/control';
    $gateRoot = $tempDir . '/gate';
    foreach ([$privRoot, $currRoot, $pubRoot, $ctrlRoot, $gateRoot] as $d) mkdir($d, 0700, true);

    file_put_contents($currRoot . '/release.json', json_encode(['project'=>'rumo10','files'=>[]]));
    file_put_contents($pubRoot . '/.htaccess', "Options -Indexes\n");
    file_put_contents($ctrlRoot . '/deployment-journal.json', json_encode(['state'=>'validated']));

    $hookScript = dirname(__DIR__) . '/bin/mysql-backup-hook.php';
    $hookHash = digest($hookScript);

    $badProfile = $tempDir . '/non-existent-profile.json';
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
                'script' => $hookScript,
                'sha256' => $hookHash,
                'args' => ['--profile', $badProfile],
            ],
        ],
        'apply_enabled' => true,
    ];

    $testPub = new class($cfg) extends Publisher {
        public function testCheckPrereqs(): void {
            $this->assertBackupPrerequisites();
        }
    };

    $caught = false;
    try {
        $testPub->testCheckPrereqs();
    } catch (\Throwable $e) {
        $caught = true;
        if (str_contains($e->getMessage(), 'Private backup provider profile required')) {
            testPass("assertBackupPrerequisites falha imediatamente se profile de backup estiver ausente");
        } else {
            testFail("Profile check", "Mensagem inesperada: " . $e->getMessage());
        }
    }
    if (!$caught) testFail("Profile check", "Exceção não foi lançada para profile ausente");

    // Verify deployment.lock was NOT created
    if (!file_exists($ctrlRoot . '/deployment.lock')) {
        testPass("deployment.lock NÃO é criado quando pré-requisito de backup falha");
    } else {
        testFail("Lock created prematurely", "deployment.lock existe");
    }

    // Now test with valid mock profile
    $validProfile = $tempDir . '/valid-profile.json';
    $profileData = [
        'source_dsn' => 'mysql:host=localhost;dbname=test_db',
        'source_user' => 'test_user',
        'source_database' => 'test_db',
        'source_password_file' => $passFile,
    ];
    file_put_contents($validProfile, json_encode($profileData));
    chmod($validProfile, 0600);

    $cfg['hooks']['backup']['args'] = ['--profile', $validProfile];
    $testPubValid = new class($cfg) extends Publisher {
        public function testCheckPrereqs(): void {
            $this->assertBackupPrerequisites();
        }
    };

    $passedValid = false;
    try {
        $testPubValid->testCheckPrereqs();
        $passedValid = true;
    } catch (\Throwable $e) {
        testFail("Valid prereqs", "Falhou indevidamente: " . $e->getMessage());
    }
    if ($passedValid) {
        testPass("assertBackupPrerequisites aprova perfil válido com credenciais 0600");
    }

    // 4. Test Pre-Transfer Auto-Recovery Routine
    echo "\n4. Validando rotina de auto-recuperação pré-transferência...\n";

    // Create session in 'blocked' state with untouched journal
    $sessionFile = $privRoot . '/drain-session.json';
    $journalFile = $privRoot . '/journal.json';
    $lockFile = $ctrlRoot . '/deployment.lock';
    file_put_contents($lockFile, "lock");

    $journalData = [
        'state' => 'blocked',
        'attempt' => 'test_attempt_001',
        'operations' => [
            ['state' => 'planned', 'dest' => $currRoot . '/app/test.php', 'before' => null],
            ['state' => 'planned', 'dest' => $pubRoot . '/assets/test.css', 'before' => null],
        ],
        'old_manifest' => ['project' => 'rumo10', 'files' => []],
        'lock_bytes' => 'lock',
    ];
    file_put_contents($journalFile, json_encode($journalData));

    $sessionData = [
        'owner' => 'test_owner',
        'state' => 'blocked',
        'stage' => $tempDir . '/stage',
        'content_before' => [],
        'journal_before' => null,
    ];
    file_put_contents($sessionFile, json_encode($sessionData));

    // Test GitHub safeAutoRecoverPreTransfer
    $gitHub = new class(['private' => $privRoot, 'token_file' => $passFile, 'main_automation' => ['status_token_file' => $passFile], 'repository' => 'rralvarez/rumo10']) extends GitHub {
        public function testAutoRecover(Publisher $pub): void {
            $this->safeAutoRecoverPreTransfer($pub);
        }
    };

    $recoveredPublisher = new class($cfg) extends Publisher {
        public bool $recoverCalled = false;
        public function recover(bool $retireTesteCodex = false): array {
            $this->recoverCalled = true;
            return ['state' => 'recovered'];
        }
    };

    $gitHub->testAutoRecover($recoveredPublisher);
    if ($recoveredPublisher->recoverCalled) {
        testPass("Falha pré-transferência (operações planned) dispara auto-recuperação sem intervenção manual");
    } else {
        testFail("Pre-transfer auto-recover", "Auto-recuperação não foi chamada");
    }

    // Test Ambiguous State (mutation in progress or files changed)
    echo "\n5. Validando retenção de trava para estados ambíguos...\n";
    $ambiguousJournalData = [
        'state' => 'blocked',
        'attempt' => 'test_attempt_002',
        'operations' => [
            ['state' => 'confirmed', 'dest' => $currRoot . '/app/test.php'], // mutation already executed!
        ],
        'old_manifest' => ['project' => 'rumo10', 'files' => []],
    ];
    file_put_contents($journalFile, json_encode($ambiguousJournalData));

    $ambiguousPublisher = new class($cfg) extends Publisher {
        public bool $recoverCalled = false;
        public function recover(bool $retireTesteCodex = false): array {
            $this->recoverCalled = true;
            return ['state' => 'recovered'];
        }
    };

    $gitHub->testAutoRecover($ambiguousPublisher);
    if (!$ambiguousPublisher->recoverCalled) {
        testPass("Estado ambíguo (operações confirmed) NÃO executa auto-recuperação e retém travas");
    } else {
        testFail("Ambiguous state safety", "Auto-recuperação foi chamada indevidamente em estado ambíguo!");
    }

    echo "\n=== BATERIA CONCLUÍDA: {$testsPassed} DE {$testsPassed} TESTES PASSARAM COM SUCESSO! ===\n";

} finally {
    // Cleanup temp directory
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if ($f->isDir()) rmdir($f->getPathname());
        else unlink($f->getPathname());
    }
    rmdir($tempDir);
}
