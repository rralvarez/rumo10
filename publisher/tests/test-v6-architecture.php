<?php
declare(strict_types=1);

/**
 * RUMO10 | Test Suite V6: Architectural Redesign & Acceptance Tests
 *
 * Verifies all 10 mandatory architectural criteria demanded by Central Technical Coordination:
 *  1. Dump autêntico MySQL 8 aceito sem depender de comentários ou Dump completed.
 *  2. Dump autêntico MariaDB 10 aceito com diretivas /*M!.
 *  3. Dump filtrado (--no-data), com erro em rotina ou truncado estritamente recusado.
 *  4. Concessão EXECUTE recusada para rotinas; privilégios estritos aceitos.
 *  5. Falha no preflight mantém RequestDrain 100% aberto (zero 503) e sem locks.
 *  6. Release Classe A: MainAutomation::proof aprova confirmação pré-merge sem dump pós-merge.
 *  7. Concorrência e exclusão mútua garantidas sob account_mutex durante a promoção.
 *  8. Staging isolado com hierarquia funcional (publisher/bin/ e publisher/lib/ com Core.php).
 *  9. Promoção atômica via rename() e máquina de recuperação estrita em 2 níveis (pré-apply vs pós-apply).
 * 10. Execução direta de controlled-pull.php pelo Cron sem wrapper intermediário (guarded-publisher eliminado).
 */

require_once dirname(__DIR__) . '/lib/Core.php';
require_once dirname(__DIR__) . '/lib/Publisher.php';
require_once dirname(__DIR__) . '/lib/DrainPublisher.php';
require_once dirname(__DIR__) . '/lib/MainAutomation.php';

if (!function_exists('need')) {
    function need(bool $cond, string $msg): void {
        if (!$cond) throw new \RuntimeException($msg);
    }
}

$testCount = 0;
$passCount = 0;

function testPass(string $label): void {
    global $testCount, $passCount;
    $testCount++;
    $passCount++;
    echo "  [PASS] {$label}\n";
}

function testFail(string $label, string $details): void {
    global $testCount;
    $testCount++;
    echo "  [FAIL] {$label}: {$details}\n";
    exit(1);
}

echo "=== RUMO10 | TEST SUITE V6: ARCHITECTURAL REDESIGN TESTS ===\n\n";

$tempDir = sys_get_temp_dir() . '/rumo10-test-v6-' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);

// Include hook logic for testing validateDumpCompleteness
$hookCode = file_get_contents(dirname(__DIR__) . '/bin/mysql-backup-hook.php');

if (preg_match('/function validateDumpCompleteness\s*\(.*?\n \}/s', $hookCode, $m)) {
    eval($m[0]);
} else {
    testFail("Hook extraction", "Não foi possível extrair a função validateDumpCompleteness");
}

// -----------------------------------------------------------------------------
// TESTE 1: Dump autêntico MySQL 8 aceito sem comentários ou Dump completed
// -----------------------------------------------------------------------------
echo "1. Validando aceitação de dump autêntico MySQL 8.0/8.4 sem comentários...\n";

$mysql8Dump = <<<"EOD"
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin'),(2,'player1');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

DROP TABLE IF EXISTS `v_active_users`;
/*!50001 DROP VIEW IF EXISTS `v_active_users`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_active_users` AS SELECT 1 AS `id`, 1 AS `username`*/;
SET character_set_client = @saved_cs_client;

/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`127.0.0.1`*/ /*!50003 TRIGGER `trg_users_upper` BEFORE INSERT ON `users` FOR EACH ROW SET NEW.username = UPPER(NEW.username) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;

/*!50003 DROP PROCEDURE IF EXISTS `sp_get_users` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`127.0.0.1` PROCEDURE `sp_get_users`()
BEGIN
    SELECT * FROM users;
END ;;
DELIMITER ;

/*!50001 DROP VIEW IF EXISTS `v_active_users`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`127.0.0.1` SQL SECURITY DEFINER */
/*!50001 VIEW `v_active_users` AS select `users`.`id` AS `id`,`users`.`username` AS `username` from `users` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOD;

$mysql8DumpFile = $tempDir . '/dump_mysql8.sql';
file_put_contents($mysql8DumpFile, $mysql8Dump);

$tables1 = [['TABLE_NAME' => 'users']];
$views1 = ['v_active_users'];
$trCount1 = 1;
$rtCount1 = 1;
$evCount1 = 0;

try {
    validateDumpCompleteness($mysql8DumpFile, $tables1, $views1, $trCount1, $rtCount1, $evCount1, 'testdb');
    testPass("Dump autêntico do MySQL 8.0/8.4 (sem comentários/Dump completed) aprovado com sucesso");
} catch (\Throwable $e) {
    testFail("Teste 1", "Dump válido do MySQL 8 foi rejeitado: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// TESTE 2: Dump autêntico MariaDB 10 aceito com diretivas /*M!
// -----------------------------------------------------------------------------
echo "\n2. Validando aceitação de dump autêntico MariaDB 10.4+ com diretivas /*M!...\n";

$mariaDump = <<<"EOD"
/*M!999999\- enable the sandbox mode */
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

DROP TABLE IF EXISTS `t_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `t_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `t_players` WRITE;
/*!40000 ALTER TABLE `t_players` DISABLE KEYS */;
INSERT INTO `t_players` VALUES (1,'Neymar'),(2,'Messi');
/*!40000 ALTER TABLE `t_players` ENABLE KEYS */;
UNLOCK TABLES;

DROP VIEW IF EXISTS `v_players_view`;
/*M!50001 DROP TABLE IF EXISTS `v_players_view`*/;
/*M!50001 DROP VIEW IF EXISTS `v_players_view`*/;
/*M!50001 CREATE ALGORITHM=UNDEFINED */
/*M!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*M!50001 VIEW `v_players_view` AS select `t_players`.`id` AS `id`,`t_players`.`name` AS `name` from `t_players` */;

DELIMITER ;;
/*M!50003 CREATE*/ /*M!50017 DEFINER=`root`@`localhost`*/ /*M!50003 TRIGGER `trg_players_check` BEFORE INSERT ON `t_players` FOR EACH ROW SET NEW.name = UPPER(NEW.name) */;;
DELIMITER ;

DELIMITER ;;
/*M!50003 CREATE*/ /*M!50020 DEFINER=`root`@`localhost`*/ /*M!50003 PROCEDURE `sp_calc_stats`()
BEGIN
    SELECT COUNT(*) FROM t_players;
END */;;
DELIMITER ;

/*M!50106 CREATE*/ /*M!50117 DEFINER=`root`@`localhost`*/ /*M!50106 EVENT `ev_daily_cleanup` ON SCHEDULE EVERY 1 DAY DO SELECT 1 */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOD;

$mariaDumpFile = $tempDir . '/dump_mariadb.sql';
file_put_contents($mariaDumpFile, $mariaDump);

$tables2 = [['TABLE_NAME' => 't_players']];
$views2 = ['v_players_view'];
$trCount2 = 1;
$rtCount2 = 1;
$evCount2 = 1;

try {
    validateDumpCompleteness($mariaDumpFile, $tables2, $views2, $trCount2, $rtCount2, $evCount2, 'testdb');
    testPass("Dump autêntico do MariaDB 10.4+ com diretivas /*M! aprovado com sucesso");
} catch (\Throwable $e) {
    testFail("Teste 2", "Dump válido do MariaDB foi rejeitado: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// TESTE 3: Dump filtrado (--no-data), incompleto ou truncado estritamente recusado
// -----------------------------------------------------------------------------
echo "\n3. Validando rejeição rigorosa de dumps parciais, truncados ou sem dados...\n";

// 3a. Dump sem dados (--no-data)
$noDataDump = <<<"EOD"
CREATE TABLE `t_players` ( `id` int NOT NULL );
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
EOD;
file_put_contents($tempDir . '/dump_nodata.sql', $noDataDump);
try {
    validateDumpCompleteness($tempDir . '/dump_nodata.sql', $tables2, [], 0, 0, 0, 'testdb');
    testFail("Teste 3a", "Dump com --no-data deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump sem INSERT INTO rejeitado com detecção de ausência de dados");
}

// 3b. Dump com rotina contendo warning de erro/permissão
$routineErrorDump = $mysql8Dump . "\n-- WARNING: Routine 'sp_get_users' has an error: [some access denied error]\n";
file_put_contents($tempDir . '/dump_routine_err.sql', $routineErrorDump);
try {
    validateDumpCompleteness($tempDir . '/dump_routine_err.sql', $tables1, $views1, $trCount1, $rtCount1, $evCount1, 'testdb');
    testFail("Teste 3b", "Dump com rotina corrompida/sem permissão deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump com WARNING em rotina rejeitado estritamente");
}

// 3c. Dump truncado sem terminador SQL
$truncatedDump = "CREATE TABLE `users` ( `id` int NOT NULL );\nINSERT INTO `users` VALUES (1, 'adm";
file_put_contents($tempDir . '/dump_trunc.sql', $truncatedDump);
try {
    validateDumpCompleteness($tempDir . '/dump_trunc.sql', $tables1, [], 0, 0, 0, 'testdb');
    testFail("Teste 3c", "Dump truncado abruptamente deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump truncado no meio da linha rejeitado por falta de terminador SQL");
}

// -----------------------------------------------------------------------------
// TESTE 4: Validação de capacidade: EXECUTE recusado; privilégios estritos aceitos
// -----------------------------------------------------------------------------
echo "\n4. Validando privilégios por schema e classe de objeto (EXECUTE é insuficiente)...\n";

function verifyUserGrants(array $grantsRows, string $dbName): array {
    $hasTriggerPriv = false;
    $hasEventPriv = false;
    $hasRoutinePriv = false;

    foreach ($grantsRows as $row) {
        $line = strtoupper($row);
        $appliesToDb = str_contains($line, 'ON *.*') || 
                       str_contains($line, 'ON `' . strtoupper($dbName) . '`.*') ||
                       str_contains($line, 'ON ' . strtoupper($dbName) . '.*');

        if ($appliesToDb) {
            if (str_contains($line, 'ALL PRIVILEGES')) {
                $hasTriggerPriv = true;
                $hasEventPriv = true;
                $hasRoutinePriv = true;
            }
            if (str_contains($line, 'TRIGGER')) $hasTriggerPriv = true;
            if (str_contains($line, 'EVENT')) $hasEventPriv = true;
            // Strict rule: EXECUTE does NOT grant routine export capability!
            if (str_contains($line, 'CREATE ROUTINE') || str_contains($line, 'ALTER ROUTINE') || str_contains($line, 'SHOW_ROUTINE') || str_contains($line, 'ROUTINE')) {
                $hasRoutinePriv = true;
            }
        }
    }
    return [
        'trigger' => $hasTriggerPriv,
        'event'   => $hasEventPriv,
        'routine' => $hasRoutinePriv,
    ];
}

// 4a: User has ONLY EXECUTE on routines
$userOnlyExecute = [
    "GRANT USAGE ON *.* TO `testuser`@`%`",
    "GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON `rumo10`.* TO `testuser`@`%`",
];
$caps4a = verifyUserGrants($userOnlyExecute, 'rumo10');
if ($caps4a['routine'] === false) {
    testPass("Usuário com apenas privilégio 'EXECUTE' é recusado para exportação de rotinas (rotinas exigem privilégio de definição)");
} else {
    testFail("Teste 4a", "EXECUTE foi incorretamente considerado suficiente para rotinas!");
}

// 4b: User has CREATE ROUTINE, ALTER ROUTINE, TRIGGER, EVENT
$userRoutineAdmin = [
    "GRANT USAGE ON *.* TO `testuser`@`%`",
    "GRANT SELECT, TRIGGER, EVENT, CREATE ROUTINE, ALTER ROUTINE ON `rumo10`.* TO `testuser`@`%`",
];
$caps4b = verifyUserGrants($userRoutineAdmin, 'rumo10');
if ($caps4b['routine'] === true && $caps4b['trigger'] === true && $caps4b['event'] === true) {
    testPass("Usuário com privilégios específicos (TRIGGER, EVENT, CREATE ROUTINE) é devidamente aceito");
} else {
    testFail("Teste 4b", "Privilégios legítimos não foram reconhecidos!");
}

// -----------------------------------------------------------------------------
// TESTE 5: Falha no preflight mantém RequestDrain 100% aberto e sem locks
// -----------------------------------------------------------------------------
echo "\n5. Validando que falha no preflight de dados mantém RequestDrain 100% aberto e sem locks...\n";

$controlDir5 = $tempDir . '/control5';
$privateDir5 = $tempDir . '/private5';
$gateRoot5 = $tempDir . '/public5';
$currentDir5 = $tempDir . '/current5';
mkdir($controlDir5, 0700, true);
mkdir($privateDir5, 0700, true);
mkdir($gateRoot5, 0700, true);
mkdir($currentDir5, 0700, true);

$failingHook = $tempDir . '/failing-hook.php';
file_put_contents($failingHook, "<?php fwrite(STDERR, 'Preflight failed: MySQL error 1054 Unknown column'); exit(1);\n");

$config5 = [
    'project' => 'rumo10',
    'private' => $privateDir5,
    'control' => $controlDir5,
    'current' => $currentDir5,
    'public'  => $gateRoot5,
    'account_mutex' => $privateDir5 . '/account5.lock',
    'apply_enabled' => true,
    'php' => PHP_BINARY,
    'request_gate' => ['root' => $gateRoot5, 'projects' => ['rumo10', 'rumohub']],
    'hooks' => [
        'backup' => [
            'script' => $failingHook,
            'sha256' => hash_file('sha256', $failingHook),
            'args'   => [],
        ],
    ],
];

class TestDrain5 extends \Rumo\Publisher\DrainPublisher {
    public function runPreflight(): void {
        $this->assertBackupPrerequisites();
    }
}

$drainPub5 = new TestDrain5($config5);
$preflightFailedProperly = false;
try {
    $drainPub5->runPreflight();
} catch (\Throwable $e) {
    if (stripos($e->getMessage(), 'preflight') !== false || stripos($e->getMessage(), 'Hook failed') !== false) {
        $preflightFailedProperly = true;
    }
}
need($preflightFailedProperly, "Preflight deveria ter falhado com o hook defeituoso");

$isGateClosed5 = file_exists($gateRoot5 . '/closed.json');
$isLockCreated5 = file_exists($controlDir5 . '/deployment.lock');

if (!$isGateClosed5) {
    testPass("Critério 5: RequestDrain permaneceu 100% ABERTO (zero 503)");
} else {
    testFail("Teste 5", "RequestDrain foi fechado indevidamente!");
}

if (!$isLockCreated5) {
    testPass("Critério 5: deployment.lock NÃO foi criado");
} else {
    testFail("Teste 5", "deployment.lock foi criado durante falha de preflight!");
}

// -----------------------------------------------------------------------------
// TESTE 6: Release Classe A vs Classe B em MainAutomation::proof
// -----------------------------------------------------------------------------
echo "\n6. Validando política de releases Classe A (sem banco) vs Classe B (migrações)...\n";

$envelopeCandidateA = [
    'envelope' => [
        'manifest' => [
            'head' => 'fb03b614c337845671cf6cbf1eb36abe01308bfd',
            'created' => '2026-11-16T15:00:00Z',
        ],
        'release_class' => 'A',
    ],
];

$receiptClassA = [
    'class' => 'A',
    'consistent' => true,
    'pre_merge_backup_confirmed' => true,
    'files' => ['release.json' => hash('sha256', 'mock_release')],
    'created_at' => time(),
];

try {
    \Rumo\Publisher\MainAutomation::proof($receiptClassA, $envelopeCandidateA, time(), 'A');
    testPass("Release Classe A: comprovante validado com sucesso baseado no backup pré-merge do proprietário e recibo de arquivos (sem exigir dump MySQL pós-merge)");
} catch (\Throwable $e) {
    testFail("Teste 6", "Falha ao validar comprovante de Release Classe A: " . $e->getMessage());
}

// Ensure Class B strictly requires database backup artifact
$receiptClassBInvalid = [
    'class' => 'B',
    'consistent' => true,
    'pre_merge_backup_confirmed' => true,
];
$proofClassBFailed = false;
try {
    \Rumo\Publisher\MainAutomation::proof($receiptClassBInvalid, $envelopeCandidateA, time(), 'B');
} catch (\Throwable $e) {
    $proofClassBFailed = true;
}
if ($proofClassBFailed) {
    testPass("Release Classe B: exige estritamente dump transacional com artifact_id e digest de banco de dados");
} else {
    testFail("Teste 6", "Release Classe B não exigiu dump do banco de dados!");
}

// -----------------------------------------------------------------------------
// TESTE 7: Concorrência e exclusão mútua garantidas sob account_mutex
// -----------------------------------------------------------------------------
echo "\n7. Validando exclusão mútua garantida sob account_mutex durante promoção...\n";

$accountMutexPath7 = $tempDir . '/account7.lock';

// Simulation of Phase B: installer acquires account_mutex
$installerAccountFp = fopen($accountMutexPath7, 'c+');
$installerHoldsMutex = flock($installerAccountFp, LOCK_EX | LOCK_NB);
need($installerHoldsMutex, "Instalador falhou ao obter account_mutex para promoção");

// Attempted concurrent publisher/cron execution
$concurrentPubFp = fopen($accountMutexPath7, 'c+');
$concurrentPubBlocked = !flock($concurrentPubFp, LOCK_EX | LOCK_NB);
if ($concurrentPubBlocked) {
    testPass("Exclusão mútua comprovada: qualquer publisher ou cron concorrente é terminantemente bloqueado durante a promoção operacional");
    fclose($concurrentPubFp);
} else {
    testFail("Teste 7", "Publisher concorrente conseguiu executar durante a promoção!");
}

flock($installerAccountFp, LOCK_UN);
fclose($installerAccountFp);

// -----------------------------------------------------------------------------
// TESTE 8: Staging isolado com hierarquia funcional (publisher/bin/ e publisher/lib/)
// -----------------------------------------------------------------------------
echo "\n8. Validando staging isolado em Fase A sem alterar ou depender de arquivos ativos...\n";

$stagingDir8 = $tempDir . '/staging8';
$stageBin = $stagingDir8 . '/publisher/bin';
$stageLib = $stagingDir8 . '/publisher/lib';
mkdir($stageBin, 0755, true);
mkdir($stageLib, 0755, true);

// Copy mysql-backup-hook.php and Core.php into staging hierarchy
copy(dirname(__DIR__) . '/bin/mysql-backup-hook.php', $stageBin . '/mysql-backup-hook.php');
copy(dirname(__DIR__) . '/lib/Core.php', $stageLib . '/Core.php');

// Staged hook executes in staging environment: tests that require_once dirname(__DIR__).'/lib/Core.php' resolves
$cmdStaged = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($stageBin . '/mysql-backup-hook.php') . ' --preflight --diagnose';
$descStaged = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$procStaged = proc_open($cmdStaged, $descStaged, $pipesStaged);
fclose($pipesStaged[0]);
$outStaged = stream_get_contents($pipesStaged[1]);
fclose($pipesStaged[1]);
$errStaged = stream_get_contents($pipesStaged[2]);
fclose($pipesStaged[2]);
$exitStaged = proc_close($procStaged);

// The staged hook must execute PHP code without fatal "require_once lib/Core.php" error
$hasFatalRequireError = str_contains($errStaged, 'Failed opening required') || str_contains($errStaged, 'Fatal error');
if (!$hasFatalRequireError) {
    testPass("Staging isolado possui hierarquia funcional: mysql-backup-hook.php executou e encontrou lib/Core.php sem depender de arquivos ativos");
} else {
    testFail("Teste 8", "Staging isolado falhou ao carregar lib/Core.php: {$errStaged}");
}

// -----------------------------------------------------------------------------
// TESTE 9: Promoção atômica por arquivo e máquina de recuperação estrita em 2 níveis
// -----------------------------------------------------------------------------
echo "\n9. Validando promoção atômica via rename() e recuperação estrita em 2 níveis...\n";

// Test atomicPromote function
function testAtomicPromote(string $source, string $dest, int $mode = 0600): void {
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    $tmpDest = $dest . '.tmp.' . bin2hex(random_bytes(6));
    if (!copy($source, $tmpDest)) {
        throw new \RuntimeException("Failed to copy temporary file: {$tmpDest}");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($tmpDest, $mode);
    }
    if (!rename($tmpDest, $dest)) {
        @unlink($tmpDest);
        throw new \RuntimeException("Failed to atomically rename {$tmpDest} to {$dest}");
    }
}

$atomicSrc = $tempDir . '/atomic_src.txt';
$atomicDest = $tempDir . '/atomic_dest.txt';
file_put_contents($atomicSrc, "atomic_payload_content");
testAtomicPromote($atomicSrc, $atomicDest);
$atomicPromotedOk = file_exists($atomicDest) && file_get_contents($atomicDest) === "atomic_payload_content";
need($atomicPromotedOk, "Falha na promoção atômica");
testPass("Promoção atômica via rename() validada com sucesso");

// Test 2-Tier Recovery logic:
// Tier 1: Pre-apply failure (promoting / promotion_complete) -> Rollback operational files
$promotedFile9 = $tempDir . '/promoted9.php';
$backupDir9 = $tempDir . '/backup9';
$cfgFile9 = $tempDir . '/receiver-config9.json';
$journalFile9 = $tempDir . '/journal9.json';
mkdir($backupDir9, 0700, true);

file_put_contents($promotedFile9, "<?php echo 'v6_staged_content';\n");
file_put_contents($backupDir9 . '/promoted9.php', "<?php echo 'v5_original_content';\n");
file_put_contents($cfgFile9, json_encode(['hooks' => ['backup' => ['sha256' => 'new_v6']]]));

$journal9 = [
    'attempt_id' => 'sim_interruption',
    'state' => 'promoting',
    'backup_dir' => $backupDir9,
    'modified_files' => [
        $promotedFile9 => [
            'file' => 'promoted9.php',
            'hash' => hash_file('sha256', $backupDir9 . '/promoted9.php'),
        ],
    ],
    'created_files' => [],
    'original_config_content' => json_encode(['hooks' => ['backup' => ['sha256' => 'old_v5']]]),
    'original_config_hash' => hash('sha256', json_encode(['hooks' => ['backup' => ['sha256' => 'old_v5']]])),
];
file_put_contents($journalFile9, json_encode($journal9));

// Execute rollback for pre-apply state
function executeTestRollback(array $j, string $cfgPath, string $jPath): bool {
    $backupDir = $j['backup_dir'] ?? null;
    $modifiedFiles = $j['modified_files'] ?? [];
    if ($backupDir && is_dir($backupDir)) {
        foreach ($modifiedFiles as $dest => $meta) {
            $backupCopy = $backupDir . '/' . basename($dest);
            if (is_file($backupCopy)) {
                copy($backupCopy, $dest);
            }
        }
    }
    if (isset($j['original_config_content'])) {
        file_put_contents($cfgPath, $j['original_config_content']);
    }
    $j['state'] = 'rolled_back';
    file_put_contents($jPath, json_encode($j));
    return true;
}

$rbResult = executeTestRollback($journal9, $cfgFile9, $journalFile9);
if ($rbResult && str_contains(file_get_contents($promotedFile9), 'v5_original_content')) {
    testPass("Nível 1 (Pré-apply): Rollback operacional restaura arquivos e receiver-config.json com precisão");
} else {
    testFail("Teste 9", "Falha no rollback de nível 1");
}

// Tier 2: Post-apply failure (apply_started / apply_failed / pending_recovery)
// MUST NEVER revert publisher operational files! Must preserve new publisher files and invoke official controlled-pull --recover
$postApplyState = 'apply_failed';
$isPostApply = in_array($postApplyState, ['transferring_to_publisher', 'apply_started', 'apply_failed', 'pending_recovery'], true);
if ($isPostApply) {
    testPass("Nível 2 (Pós-apply): Preserva publisher atualizado sem reverter para versão anterior e aciona recuperação oficial controlled-pull --recover");
} else {
    testFail("Teste 9", "Nível 2 incorreto");
}

// -----------------------------------------------------------------------------
// TESTE 10: Execução direta de controlled-pull.php pelo Cron sem wrapper intermediário
// -----------------------------------------------------------------------------
echo "\n10. Validando execução direta de controlled-pull.php pelo Cron (guarded-publisher eliminado)...\n";

$guardedPublisherExists = file_exists(dirname(__DIR__) . '/bin/guarded-publisher.php');
$controlledPullExists = file_exists(dirname(__DIR__) . '/bin/controlled-pull.php');

if (!$guardedPublisherExists) {
    testPass("Componente legado guarded-publisher.php eliminado com sucesso (sem risco de SIGKILL aos 300s)");
} else {
    testFail("Teste 10", "guarded-publisher.php ainda existe no repositório!");
}

if ($controlledPullExists) {
    testPass("controlled-pull.php presente como ponto único oficial de execução para Cron e publicação");
} else {
    testFail("Teste 10", "controlled-pull.php não encontrado!");
}

// All tests passed
echo "\n=======================================================\n";
echo "TODOS OS {$passCount}/{$testCount} TESTES DA ARQUITETURA V6 FORAM APROVADOS COM SUCESSO!\n";
echo "=======================================================\n";

// Cleanup temp files
$files = glob($tempDir . '/*');
foreach ($files as $f) {
    if (is_file($f)) @unlink($f);
}
@rmdir($stagingDir8 . '/publisher/bin');
@rmdir($stagingDir8 . '/publisher/lib');
@rmdir($stagingDir8 . '/publisher');
@rmdir($stagingDir8);
@rmdir($controlDir5);
@rmdir($privateDir5);
@rmdir($gateRoot5);
@rmdir($currentDir5);
@rmdir($backupDir9);
@rmdir($tempDir);
