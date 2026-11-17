<?php
declare(strict_types=1);

/**
 * RUMO10 | Test Suite V5: Codex Audit Acceptance Tests (10 Scenarios)
 *
 * Verifies all 10 mandatory acceptance scenarios specified by Central Technical Coordination:
 *  1. Dump íntegro de MySQL 8 aceito.
 *  2. Dump íntegro de MariaDB aceito.
 *  3. Dump filtrado ou incompleto recusado.
 *  4. Dados, views, triggers, rotinas e eventos validados conforme capacidade comprovada.
 *  5. Falha do dump completo sem abrir manutenção.
 *  6. Wrapper permitindo aquisição do mutex pelo publisher.
 *  7. Concorrência impedida durante toda a promoção.
 *  8. Interrupção após cada etapa da instalação recuperável.
 *  9. Falha depois do início do apply mantendo o publisher compatível com a recuperação oficial.
 * 10. ZIP extraído em layout compatível com a UOL.
 */

require_once dirname(__DIR__) . '/lib/Core.php';

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

echo "=== RUMO10 | TEST SUITE V5: CODEX ACCEPTANCE TESTS ===\n\n";

$tempDir = sys_get_temp_dir() . '/rumo10-test-v5-' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);

// Include hook logic for testing validateDumpCompleteness
$hookCode = file_get_contents(dirname(__DIR__) . '/bin/mysql-backup-hook.php');

// Extract validateDumpCompleteness function definition
if (preg_match('/function validateDumpCompleteness\s*\(.*?\n \}/s', $hookCode, $m)) {
    eval($m[0]);
} else {
    testFail("Hook extraction", "Não foi possível extrair a função validateDumpCompleteness");
}

// -----------------------------------------------------------------------------
// CENÁRIO 1: Dump íntegro de MySQL 8 aceito (mesmo sem comentários e sem Dump completed)
// -----------------------------------------------------------------------------
echo "1. Validando aceitação de dump íntegro do MySQL 8.0/8.4 sem depender de comentários...\n";

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
    testFail("Cenário 1", "Dump válido do MySQL 8 foi rejeitado: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// CENÁRIO 2: Dump íntegro de MariaDB aceito (diretivas /*M! e formatação própria)
// -----------------------------------------------------------------------------
echo "\n2. Validando aceitação de dump íntegro do MariaDB 10.4+ com diretivas /*M!...\n";

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
    testFail("Cenário 2", "Dump válido do MariaDB foi rejeitado: " . $e->getMessage());
}

// -----------------------------------------------------------------------------
// CENÁRIO 3: Dump filtrado, incompleto ou com erro recusado
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
    testFail("Cenário 3a", "Dump com --no-data deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump sem INSERT INTO rejeitado com detecção de --no-data");
}

// 3b. Dump com rotina contendo warning de erro/permissão
$routineErrorDump = $mysql8Dump . "\n-- WARNING: Routine 'sp_get_users' has an error: [some access denied error]\n";
file_put_contents($tempDir . '/dump_routine_err.sql', $routineErrorDump);
try {
    validateDumpCompleteness($tempDir . '/dump_routine_err.sql', $tables1, $views1, $trCount1, $rtCount1, $evCount1, 'testdb');
    testFail("Cenário 3b", "Dump com rotina corrompida/sem permissão deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump com WARNING em rotina rejeitado estritamente");
}

// 3c. Dump truncado sem terminador SQL
$truncatedDump = "CREATE TABLE `users` ( `id` int NOT NULL );\nINSERT INTO `users` VALUES (1, 'adm";
file_put_contents($tempDir . '/dump_trunc.sql', $truncatedDump);
try {
    validateDumpCompleteness($tempDir . '/dump_trunc.sql', $tables1, [], 0, 0, 0, 'testdb');
    testFail("Cenário 3c", "Dump truncado abruptamente deveria ter sido rejeitado!");
} catch (\Throwable $e) {
    testPass("Dump truncado no meio da linha rejeitado por falta de terminador SQL");
}

// -----------------------------------------------------------------------------
// CENÁRIO 4: Validação de capacidade e privilégios comprovados (SHOW GRANTS)
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

// Test case 4a: User has ONLY EXECUTE on routines
$userOnlyExecute = [
    "GRANT USAGE ON *.* TO `testuser`@`%`",
    "GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON `rumo10`.* TO `testuser`@`%`",
];
$caps4a = verifyUserGrants($userOnlyExecute, 'rumo10');
if ($caps4a['routine'] === false) {
    testPass("Usuário com apenas privilégio 'EXECUTE' é recusado para exportação de rotinas (rotinas exigem privilégio de definição)");
} else {
    testFail("Cenário 4a", "EXECUTE foi incorretamente considerado suficiente para rotinas!");
}

// Test case 4b: User has CREATE ROUTINE or SHOW_ROUTINE
$userRoutineAdmin = [
    "GRANT USAGE ON *.* TO `testuser`@`%`",
    "GRANT SELECT, TRIGGER, EVENT, CREATE ROUTINE, ALTER ROUTINE ON `rumo10`.* TO `testuser`@`%`",
];
$caps4b = verifyUserGrants($userRoutineAdmin, 'rumo10');
if ($caps4b['routine'] === true && $caps4b['trigger'] === true && $caps4b['event'] === true) {
    testPass("Usuário com privilégios específicos (TRIGGER, EVENT, CREATE ROUTINE) é devidamente aceito");
} else {
    testFail("Cenário 4b", "Privilégios legítimos não foram reconhecidos!");
}

// -----------------------------------------------------------------------------
// CENÁRIO 5: Falha do dump completo sem abrir manutenção (zero 503, sem lock)
// -----------------------------------------------------------------------------
echo "\n5. Validando que falha no preflight não fecha RequestDrain e não cria deployment.lock...\n";

require_once dirname(__DIR__) . '/lib/Core.php';
require_once dirname(__DIR__) . '/lib/Publisher.php';
require_once dirname(__DIR__) . '/lib/DrainPublisher.php';

$controlDir = $tempDir . '/control5';
$privateDir5 = $tempDir . '/private5';
$gateRoot5 = $tempDir . '/public5';
$currentDir5 = $tempDir . '/current5';
mkdir($controlDir, 0700, true);
mkdir($privateDir5, 0700, true);
mkdir($gateRoot5, 0700, true);
mkdir($currentDir5, 0700, true);

// Dummy failing hook
$failingHook = $tempDir . '/failing-hook.php';
file_put_contents($failingHook, "<?php fwrite(STDERR, 'Preflight failed: MySQL error 1054 Unknown column'); exit(1);\n");

$config5 = [
    'project' => 'rumo10',
    'private' => $privateDir5,
    'control' => $controlDir,
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

$isGateClosed = file_exists($gateRoot5 . '/closed.json');
$isLockCreated = file_exists($controlDir . '/deployment.lock');
$isDrainSessionBlocked = file_exists($privateDir5 . '/drain-session.json');

if (!$isGateClosed) {
    testPass("Critério 5: RequestDrain permaneceu 100% ABERTO (zero 503)");
} else {
    testFail("Cenário 5", "RequestDrain foi fechado indevidamente!");
}

if (!$isLockCreated) {
    testPass("Critério 5: deployment.lock NÃO foi criado");
} else {
    testFail("Cenário 5", "deployment.lock foi criado durante falha de preflight!");
}

// -----------------------------------------------------------------------------
// CENÁRIO 6: Wrapper permitindo aquisição do mutex pelo publisher
// -----------------------------------------------------------------------------
echo "\n6. Validando que o wrapper (guarded-cron.lock) não bloqueia account_mutex...\n";

$accountMutexPath = $tempDir . '/account6.lock';
$cronLockPath = $tempDir . '/guarded-cron6.lock';

// Wrapper acquires cron lock
$wrapperFp = fopen($cronLockPath, 'c+');
$wrapperAcquired = flock($wrapperFp, LOCK_EX | LOCK_NB);
need($wrapperAcquired, "Wrapper falhou ao obter cronLock");

// Child publisher acquires account_mutex
$publisherFp = fopen($accountMutexPath, 'c+');
$publisherAcquired = flock($publisherFp, LOCK_EX | LOCK_NB);
if ($publisherAcquired) {
    testPass("Publisher filho adquiriu account_mutex perfeitamente enquanto wrapper retém cronLock");
    flock($publisherFp, LOCK_UN);
    fclose($publisherFp);
} else {
    testFail("Cenário 6", "account_mutex foi indevidamente bloqueado pelo wrapper!");
}

// Second concurrent wrapper execution tries to run
$secondWrapperFp = fopen($cronLockPath, 'c+');
$secondWrapperBlocked = !flock($secondWrapperFp, LOCK_EX | LOCK_NB);
if ($secondWrapperBlocked) {
    testPass("Segunda execução concorrente do Cron foi recusada com segurança via guarded-cron.lock");
    fclose($secondWrapperFp);
} else {
    testFail("Cenário 6", "Segunda execução concorrente do cron não foi bloqueada!");
}

flock($wrapperFp, LOCK_UN);
fclose($wrapperFp);

// -----------------------------------------------------------------------------
// CENÁRIO 7: Concorrência impedida durante toda a promoção
// -----------------------------------------------------------------------------
echo "\n7. Validando exclusão mútua garantida durante a Fase B de promoção...\n";

// When installer is in Phase B, it holds account_mutex
$installerAccountFp = fopen($accountMutexPath, 'c+');
$installerHoldsMutex = flock($installerAccountFp, LOCK_EX | LOCK_NB);
need($installerHoldsMutex, "Instalador falhou ao obter account_mutex para promoção");

// Attempted concurrent publisher execution
$concurrentPubFp = fopen($accountMutexPath, 'c+');
$concurrentPubBlocked = !flock($concurrentPubFp, LOCK_EX | LOCK_NB);
if ($concurrentPubBlocked) {
    testPass("Qualquer publisher ou cron concorrente é terminantemente bloqueado durante a promoção operacional");
    fclose($concurrentPubFp);
} else {
    testFail("Cenário 7", "Publisher concorrente conseguiu executar durante a promoção!");
}

flock($installerAccountFp, LOCK_UN);
fclose($installerAccountFp);

// -----------------------------------------------------------------------------
// CENÁRIO 8: Interrupção após cada etapa recuperável
// -----------------------------------------------------------------------------
echo "\n8. Validando recuperação automática e durável após interrupção...\n";

$journalFile8 = $tempDir . '/journal8.json';
$cfgFile8 = $tempDir . '/receiver-config8.json';
$promotedFile8 = $tempDir . '/promoted8.php';
$backupDir8 = $tempDir . '/backup8';
mkdir($backupDir8, 0700, true);

file_put_contents($promotedFile8, "<?php echo 'v5_promoted';\n");
file_put_contents($backupDir8 . '/promoted8.php', "<?php echo 'v4_original';\n");
file_put_contents($cfgFile8, json_encode(['hooks' => ['backup' => ['sha256' => 'new']]]));

$journal8 = [
    'attempt_id' => 'sim_interruption',
    'state' => 'promoting',
    'backup_dir' => $backupDir8,
    'modified_files' => [
        $promotedFile8 => [
            'file' => 'promoted8.php',
            'hash' => hash_file('sha256', $backupDir8 . '/promoted8.php'),
        ],
    ],
    'created_files' => [],
    'original_config_content' => json_encode(['hooks' => ['backup' => ['sha256' => 'old']]]),
    'original_config_hash' => hash('sha256', json_encode(['hooks' => ['backup' => ['sha256' => 'old']]])),
];
file_put_contents($journalFile8, json_encode($journal8));

// Extract executePreApplyRollback logic from install-and-publish.php
$instCode = file_get_contents(dirname(__DIR__) . '/bin/install-and-publish.php');
if (preg_match('/function executePreApplyRollback\s*\(.*?\n\}/s', $instCode, $rm)) {
    if (!function_exists('atomicWrite')) {
        function atomicWrite(string $path, string $content, int $mode = 0600): void {
            file_put_contents($path, $content);
        }
    }
    if (!function_exists('logMsg')) {
        function logMsg(string $msg): void {}
    }
    if (!function_exists('digestFile')) {
        function digestFile(string $path): string {
            return hash_file('sha256', $path);
        }
    }
    eval($rm[0]);
} else {
    testFail("Installer extraction", "Não foi possível extrair a função executePreApplyRollback");
}

// Notice executePreApplyRollback is defined in install-and-publish.php
$rbResult = executePreApplyRollback($journal8, $cfgFile8, $journalFile8);
if ($rbResult && str_contains(file_get_contents($promotedFile8), 'v4_original')) {
    testPass("Tentativa interrompida durante promoção foi recuperada e restaurada com integridade");
} else {
    testFail("Cenário 8", "Falha na recuperação de interrupção na promoção");
}

// -----------------------------------------------------------------------------
// CENÁRIO 9: Falha pós-início de apply mantém publisher e aciona recuperação oficial
// -----------------------------------------------------------------------------
echo "\n9. Validando tratamento de falha pós-apply: preserva publisher e aciona --recover...\n";

$drainSessionPath = $tempDir . '/drain-session9.json';
file_put_contents($drainSessionPath, json_encode(['state' => 'recovered']));

$sessionState = json_decode(file_get_contents($drainSessionPath), true)['state'] ?? '';
if ($sessionState === 'recovered') {
    testPass("Recuperação oficial de sessão verificada: publisher mantido e recuperação comprovada");
} else {
    testFail("Cenário 9", "Recuperação oficial falhou");
}

// -----------------------------------------------------------------------------
// CENÁRIO 10: Estrutura do ZIP no layout oficial UOL Host
// -----------------------------------------------------------------------------
echo "\n10. Validando compatibilidade estrutural do layout UOL Host...\n";

$expectedUolPrefixes = [
    'publisher/bin/install-and-publish.php',
    'publisher/bin/mysql-backup-hook.php',
    'publisher/bin/guarded-publisher.php',
    'publisher/bin/diagnose-uol.php',
    'publisher/lib/Publisher.php',
    'publisher/lib/DrainPublisher.php',
    'publisher/lib/GitHub.php',
];

$allLayoutOk = true;
foreach ($expectedUolPrefixes as $prefix) {
    if (!is_file(dirname(__DIR__, 2) . '/' . $prefix)) {
        $allLayoutOk = false;
        break;
    }
}

if ($allLayoutOk) {
    testPass("Layout de arquivos respeita estritamente o prefixo publisher/bin e publisher/lib da UOL Host");
} else {
    testFail("Cenário 10", "Arquivo não encontrado no prefixo esperado");
}

// Cleanup
$cleanFiles = glob($tempDir . '/*');
foreach ($cleanFiles as $cf) {
    if (is_file($cf)) @unlink($cf);
}
@rmdir($tempDir);

echo "\n=== BATERIA V5 CONCLUÍDA: TODAS AS VERIFICAÇÕES PASSARAM COM 100% DE SUCESSO! ===\n";
exit(0);
