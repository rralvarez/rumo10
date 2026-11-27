<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

/**
 * Teste de integração PDO isolado para R10-F07-R1 (pênaltis 1400/1401: persistência e idempotência).
 * Preserva integralmente as travas de segurança homologadas na CAR003-T01-R2.
 */

if (!in_array('--isolated-test-db', $argv, true)) {
    fwrite(STDERR, "RECUSADO: execute com --isolated-test-db para rodar a integração PDO em banco descartável isolado.\n");
    exit(2);
}

$cliOpts = [];
foreach ($argv as $arg) {
    if (preg_match('/^--(test-host|test-port|test-user|test-pass|test-db)=(.*)$/', $arg, $m)) {
        $cliOpts[$m[1]] = $m[2];
    }
}

$targetHost = $cliOpts['test-host'] ?? getenv('RUMO10_TEST_DB_HOST');
$targetPort = $cliOpts['test-port'] ?? getenv('RUMO10_TEST_DB_PORT');
$targetUser = $cliOpts['test-user'] ?? getenv('RUMO10_TEST_DB_USERNAME');
$targetPass = $cliOpts['test-pass'] ?? getenv('RUMO10_TEST_DB_PASSWORD');

if (
    $targetHost === false || $targetHost === ''
    || $targetPort === false || $targetPort === ''
    || $targetUser === false || $targetUser === ''
    || $targetPass === false
) {
    fwrite(STDERR, "RECUSADO: configuração incompleta. Informe explicitamente RUMO10_TEST_DB_HOST, RUMO10_TEST_DB_PORT, RUMO10_TEST_DB_USERNAME e RUMO10_TEST_DB_PASSWORD.\n");
    exit(2);
}

if (!in_array($targetHost, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "RECUSADO: host '{$targetHost}' não é local.\n");
    exit(2);
}
if (!ctype_digit((string)$targetPort) || (int)$targetPort <= 0 || (int)$targetPort > 65535) {
    fwrite(STDERR, "RECUSADO: porta '{$targetPort}' inválida.\n");
    exit(2);
}

$targetDb = $cliOpts['test-db'] ?? getenv('RUMO10_TEST_DB_DATABASE');
if ($targetDb === false || $targetDb === '') {
    $targetDb = 'rumo10_test_f07_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
}

$envFile = dirname(__DIR__) . '/.env';
$habitualDb = 'rumohub';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^DB_DATABASE=(.*)$/', trim($line), $m)) {
            $habitualDb = strtolower(trim($m[1], "\"'"));
        }
    }
}

$forbiddenDbs = ['rumohub', 'rumo10', 'rumorating', 'mysql', 'information_schema', 'performance_schema', 'sys', 'prod', 'production'];
if (
    in_array(strtolower($targetDb), $forbiddenDbs, true)
    || strtolower($targetDb) === $habitualDb
    || !preg_match('/^(rumo10_test_[a-z0-9_]+|test_[a-z0-9_]+|[a-z0-9_]+_test)$/', $targetDb)
) {
    fwrite(STDERR, "RECUSADO: banco '{$targetDb}' não é um banco descartável isolado de testes válido.\n");
    exit(2);
}

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
putenv('DB_HOST=' . $targetHost);
putenv('DB_PORT=' . $targetPort);
putenv('DB_USERNAME=' . $targetUser);
putenv('DB_PASSWORD=' . $targetPass);
putenv('DB_DATABASE=' . $targetDb);

require dirname(__DIR__) . '/app/bootstrap.php';

if (
    config('DB_HOST') !== $targetHost
    || config('DB_PORT') !== (string)$targetPort
    || config('DB_USERNAME') !== $targetUser
    || config('DB_DATABASE') !== $targetDb
) {
    fwrite(STDERR, "RECUSADO: divergência na configuração efetiva antes de executar DDL.\n");
    exit(2);
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function okF07Pdo(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA F07 PDO: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

$adminPdo = new PDO(
    "mysql:host={$targetHost};port={$targetPort};charset=utf8mb4",
    $targetUser,
    (string)$targetPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$existsStmt = $adminPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
$existsStmt->execute([$targetDb]);
if ($existsStmt->fetchColumn() !== false) {
    fwrite(STDERR, "RECUSADO: banco '{$targetDb}' já existe. Execução abortada.\n");
    exit(2);
}

$createdByThisRun = false;
$adminPdo->exec("CREATE DATABASE `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$createdByThisRun = true;

echo "=== R10-F07-R1: INTEGRAÇÃO PDO ISOLADA (PERSISTÊNCIA E IDEMPOTÊNCIA) ===\n";
echo "  Banco exclusivo criado nesta execução: {$targetHost}:{$targetPort}/{$targetDb}\n";

try {
    $pdoSchema = Database::connect();
    $actualSchemaDb = $pdoSchema->query('SELECT DATABASE()')->fetchColumn();
    if ($actualSchemaDb !== $targetDb) {
        throw new RuntimeException("RECUSADO: SELECT DATABASE() retornou '{$actualSchemaDb}', esperado '{$targetDb}' antes das migrations.");
    }
    okF07Pdo(true, "SELECT DATABASE() confirmado como '{$targetDb}' antes da aplicação das migrations");
    foreach (['001.sql', '002.sql', '003.sql', '004.sql', '005-growth.sql', '006-balance-and-maintenance.sql', '009-f01-canonical-telemetry.sql'] as $name) {
        $pdoSchema->exec((string)file_get_contents(dirname(__DIR__) . '/database/' . $name));
    }
    $pdoSchema = null;

    $pdo1 = Database::connect();
    $pdo2 = Database::connect();
    okF07Pdo($pdo1->query('SELECT DATABASE()')->fetchColumn() === $targetDb && $pdo2->query('SELECT DATABASE()')->fetchColumn() === $targetDb,
        "Duas conexões PDO independentes isoladas em '{$targetDb}'");

    $repo1 = new Repository($pdo1);
    $repo2 = new Repository($pdo2);

    $makeCareer = static function (Repository $r1, PDO $p1, string $suffix, int $skillVal, int $conf, int $ene, array $matchPatch): array {
        $r1->query('INSERT INTO rumo10_contas(email, senha_hash) VALUES(?, ?)', [
            "f07-pdo-{$suffix}-" . bin2hex(random_bytes(5)) . '@example.invalid',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);
        $aid = (int)$p1->lastInsertId();
        $gInit = Game::create("Atleta F07 {$suffix}", 'São Paulo', 'SP', 'centroavante', 'Santos-SP');
        $gInit['age'] = 24;
        $gInit['progression_version'] = 2;
        foreach ($gInit['skills'] as &$sk) {
            $sk['value'] = $skillVal;
        }
        unset($sk);
        $gInit['states']['confianca'] = $conf;
        $gInit['states']['energia'] = $ene;
        $gInit['stage'] = 'partida';
        $idx = Game::currentIndex($gInit);
        $gInit['matches'][$idx] = array_replace($gInit['matches'][$idx], [
            'status' => 'em_andamento',
            'participation_state' => MatchParticipation::STATE_ON_FIELD,
            'lineup' => 'titular',
            'entry' => 0,
            'exit' => 90,
            'minute' => 15,
        ], $matchPatch);
        $r1->create($aid, $gInit);
        return [$aid, $idx];
    };

    // 1. Transição real 1400 -> 1401 na mesma carreira (com tentativas contabilizadas) + conversão persistida e reenvio idempotente
    $convAcc = null;
    $convIdx = 0;
    $convReloaded1401 = null;
    $convKickRes = null;
    $convKickResend = null;
    $convVersionBefore = 0;
    $convAttempts = 0;
    $maxConvAttempts = 16;

    for ($attempt = 1; $attempt <= $maxConvAttempts; $attempt++) {
        $convAttempts = $attempt;
        [$aid, $idx] = $makeCareer($repo1, $pdo1, "conv{$attempt}", 82, 80, 85, [
            'active_match_penalty' => ['status' => 'pending_request', 'victim' => 'companheiro', 'window' => '15-35'],
        ]);
        $r1400 = $repo2->load($aid);
        $afterReq = $repo1->command($aid, 'choice', ['option' => 0], bin2hex(random_bytes(32)), (int)$r1400['version']);
        $r1401 = $repo2->load($aid);
        if (($r1401['matches'][$idx]['active_match_penalty']['status'] ?? '') !== 'taking_kick') {
            continue;
        }
        $vBefore = (int)$r1401['version'];
        $opKey = bin2hex(random_bytes(32));
        $resA = $repo1->command($aid, 'choice', ['option' => 1], $opKey, $vBefore);
        $resB = $repo1->command($aid, 'choice', ['option' => 1], $opKey, $vBefore);
        $rAfter = $repo2->load($aid);
        if ((int)($rAfter['matches'][$idx]['stats']['penalties_scored'] ?? 0) === 1) {
            $convAcc = $aid;
            $convIdx = $idx;
            $convReloaded1401 = $r1401;
            $convKickRes = $resA;
            $convKickResend = $resB;
            $convVersionBefore = $vBefore;
            break;
        }
    }
    $convAfterReload = $convAcc !== null ? $repo2->load($convAcc) : null;
    okF07Pdo(
        $convAcc !== null
        && ($convReloaded1401['matches'][$convIdx]['active_match_penalty']['window'] ?? '') === '15-35'
        && $convKickRes === $convKickResend
        && (int)$convKickRes['version'] === $convVersionBefore + 1
        && (int)$convAfterReload['version'] === (int)$convKickRes['version']
        && (int)$convAfterReload['matches'][$convIdx]['stats']['penalties_taken'] === 1
        && (int)$convAfterReload['matches'][$convIdx]['stats']['penalties_scored'] === 1
        && (int)$convAfterReload['matches'][$convIdx]['stats']['shots'] === 1
        && (int)$convAfterReload['matches'][$convIdx]['stats']['goals'] === 1
        && (int)$convAfterReload['skills']['finalizacao']['xp'] > (int)$convReloaded1401['skills']['finalizacao']['xp']
        && in_array('15-35', $convAfterReload['matches'][$convIdx]['resolved_penalties'] ?? [], true)
        && $convAfterReload['feedback'] === $convKickRes['feedback'],
        "Transição real 1400->1401 + cobrança convertida persistida via Repository e reenvio idempotente (tentativas={$convAttempts}/{$maxConvAttempts}, versão {$convVersionBefore}->{$convKickRes['version']})"
    );

    // 2. Cobrança desperdiçada efetivamente persistida pelo Repository + reenvio idempotente (tentativas limitadas e contabilizadas)
    $missAcc = null;
    $missIdx = 0;
    $missKickRes = null;
    $missKickResend = null;
    $missVersionBefore = 0;
    $missAttempts = 0;
    $maxMissAttempts = 20;

    for ($attempt = 1; $attempt <= $maxMissAttempts; $attempt++) {
        $missAttempts = $attempt;
        [$aid, $idx] = $makeCareer($repo1, $pdo1, "miss{$attempt}", 35, 15, 20, [
            'home_overall' => 60.0,
            'away_overall' => 92.0,
            'active_match_penalty' => ['status' => 'taking_kick', 'window' => '60-70'],
        ]);
        $r1401 = $repo2->load($aid);
        $vBefore = (int)$r1401['version'];
        $opKey = bin2hex(random_bytes(32));
        $resA = $repo1->command($aid, 'choice', ['option' => 3], $opKey, $vBefore);
        $resB = $repo1->command($aid, 'choice', ['option' => 3], $opKey, $vBefore);
        $rAfter = $repo2->load($aid);
        if ((int)($rAfter['matches'][$idx]['stats']['penalties_missed'] ?? 0) === 1) {
            $missAcc = $aid;
            $missIdx = $idx;
            $missKickRes = $resA;
            $missKickResend = $resB;
            $missVersionBefore = $vBefore;
            break;
        }
    }
    $missAfterReload = $missAcc !== null ? $repo2->load($missAcc) : null;
    okF07Pdo(
        $missAcc !== null
        && $missKickRes === $missKickResend
        && (int)$missKickRes['version'] === $missVersionBefore + 1
        && (int)$missAfterReload['version'] === (int)$missKickRes['version']
        && (int)$missAfterReload['matches'][$missIdx]['stats']['penalties_taken'] === 1
        && (int)$missAfterReload['matches'][$missIdx]['stats']['penalties_missed'] === 1
        && (int)$missAfterReload['matches'][$missIdx]['stats']['goals'] === 0
        && in_array('60-70', $missAfterReload['matches'][$missIdx]['resolved_penalties'] ?? [], true)
        && $missAfterReload['feedback'] === $missKickRes['feedback'],
        "Cobrança desperdiçada persistida via Repository e reenvio idempotente (tentativas={$missAttempts}/{$maxMissAttempts}, versão {$missVersionBefore}->{$missKickRes['version']})"
    );

    // 3. Reenvio com NOVA chave HTTP sobre ocorrência já resolvida (sem window e com chaves legadas) + preservação de nova cobrança legítima
    $gReplayHttp = $convAfterReload;
    $gReplayHttp['matches'][$convIdx]['minute'] = 15;
    $gReplayHttp['matches'][$convIdx]['active_match_penalty'] = ['status' => 'taking_kick']; // sem window, minuto 15 -> reconcilia com '15-35'
    $saveM = new ReflectionMethod(Repository::class, 'save');
    $saveM->invoke($repo1, $gReplayHttp);
    $reloadedBeforeNewKey = $repo2->load($convAcc);
    $achBeforeNewKey = $reloadedBeforeNewKey['achievement_events'] ?? [];
    $newHttpKey = bin2hex(random_bytes(32));
    $resNewKey = $repo1->command($convAcc, 'choice', ['option' => 1], $newHttpKey, (int)$reloadedBeforeNewKey['version']);
    $reloadedAfterNewKey = $repo2->load($convAcc);
    okF07Pdo(
        empty($reloadedAfterNewKey['matches'][$convIdx]['active_match_penalty'])
        && (int)$reloadedAfterNewKey['matches'][$convIdx]['stats']['penalties_taken'] === 1
        && (int)$reloadedAfterNewKey['matches'][$convIdx]['stats']['penalties_scored'] === 1
        && (int)$reloadedAfterNewKey['matches'][$convIdx]['stats']['goals'] === 1
        && (int)$reloadedAfterNewKey['skills']['finalizacao']['xp'] === (int)$reloadedBeforeNewKey['skills']['finalizacao']['xp']
        && ($reloadedAfterNewKey['achievement_events'] ?? []) === $achBeforeNewKey,
        'Reenvio com NOVA chave HTTP sobre ocorrência já resolvida (sem window) não duplica gols/XP/estatísticas nem gera conquistas'
    );

    // 4. Preservação de uma nova cobrança legítima (janela 60-70 após 15-35 resolvida) em PDO
    $gNewLegit = $reloadedAfterNewKey;
    $gNewLegit['matches'][$convIdx]['minute'] = 60;
    $gNewLegit['matches'][$convIdx]['active_match_penalty'] = ['status' => 'taking_kick', 'window' => '60-70'];
    $saveM->invoke($repo1, $gNewLegit);
    $rBeforeLegit = $repo2->load($convAcc);
    $repo1->command($convAcc, 'choice', ['option' => 1], bin2hex(random_bytes(32)), (int)$rBeforeLegit['version']);
    $rAfterLegit = $repo2->load($convAcc);
    okF07Pdo(
        (int)$rAfterLegit['matches'][$convIdx]['stats']['penalties_taken'] === 2
        && in_array('60-70', $rAfterLegit['matches'][$convIdx]['resolved_penalties'] ?? [], true),
        'Nova cobrança legítima (janela 60-70) após 15-35 é processada e persistida normalmente via PDO2'
    );

    // 5. Compatibilidade com estado legado sem resolved_penalties / penalties_* / window (primeiro processamento legítimo + replay)
    [$accLeg, $idxLeg] = $makeCareer($repo1, $pdo1, 'legado', 72, 60, 75, [
        'active_match_penalty' => ['status' => 'taking_kick'],
    ]);
    $gLeg = $repo2->load($accLeg);
    unset(
        $gLeg['matches'][$idxLeg]['resolved_penalties'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_taken'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_scored'],
        $gLeg['matches'][$idxLeg]['stats']['penalties_missed']
    );
    $saveM->invoke($repo1, $gLeg);
    $gLegReload = $repo2->load($accLeg);
    $resLeg = $repo1->command($accLeg, 'choice', ['option' => 1], bin2hex(random_bytes(32)), (int)$gLegReload['version']);
    $reloadedLeg = $repo2->load($accLeg);
    okF07Pdo(
        (int)$reloadedLeg['matches'][$idxLeg]['stats']['penalties_taken'] === 1
        && !empty($reloadedLeg['matches'][$idxLeg]['resolved_penalties']),
        'Save antigo sem resolved_penalties, penalties_* e window executa primeiro processamento legítimo e persiste via PDO2'
    );

    // 6. Cenário do Codex em PDO: save legado titular sem participation_state recebe pênalti 0-15 pelo motor e retoma em 15'
    $legEngineAcc = null;
    $legEngineIdx = 0;
    $legEngineAttempts = 0;
    $maxLegEngineAttempts = 250;
    for ($att = 1; $att <= $maxLegEngineAttempts; $att++) {
        $legEngineAttempts = $att;
        [$aidE, $idxE] = $makeCareer($repo1, $pdo1, "legeng{$att}", 74, 65, 80, [
            'status' => 'pre_jogo',
            'lineup' => 'titular',
            'entry' => 0,
            'exit' => 90,
            'minute' => 0,
        ]);
        $gE = $repo2->load($aidE);
        unset($gE['matches'][$idxE]['participation_state']);
        TeamStrength::snapshot($gE['matches'][$idxE], $gE);
        MatchBalance::prepare($gE['matches'][$idxE], $gE);
        $saveM->invoke($repo1, $gE);
        $gEReload = $repo2->load($aidE);
        $afterAdv = $repo1->command($aidE, 'advance', [], bin2hex(random_bytes(32)), (int)$gEReload['version']);
        $afterAdvReload = $repo2->load($aidE);
        if (($afterAdvReload['matches'][$idxE]['active_match_penalty']['window'] ?? '') === '0-15') {
            $legEngineAcc = $aidE;
            $legEngineIdx = $idxE;
            break;
        }
    }
    if ($legEngineAcc === null) {
        throw new RuntimeException("Esgotadas {$maxLegEngineAttempts} tentativas sem concessão de pênalti em 0-15 no cenário legado PDO.");
    }
    $legReloaded015 = $repo2->load($legEngineAcc);
    $repo1->command($legEngineAcc, 'choice', ['option' => 1], bin2hex(random_bytes(32)), (int)$legReloaded015['version']);
    $legAfter015 = $repo2->load($legEngineAcc);
    okF07Pdo(
        (int)$legAfter015['matches'][$legEngineIdx]['minute'] === 15
        && $legAfter015['matches'][$legEngineIdx]['status'] === 'em_andamento'
        && ($legAfter015['matches'][$legEngineIdx]['evaluated_penalties'] ?? []) === ['0-15'],
        "Cenário legado sem participation_state em PDO (tentativas={$legEngineAttempts}/{$maxLegEngineAttempts}): pênalti 0-15 concedido pelo motor e retomado em 15'"
    );
} finally {
    $pdo1 = null;
    $pdo2 = null;
    $repo1 = null;
    $repo2 = null;
    if ($createdByThisRun === true) {
        $adminPdo->exec("DROP DATABASE `{$targetDb}`");
    }
}

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES PDO ISOLADAS DA R10-F07-R1 FORAM APROVADAS!\n";
echo "====================================================================\n";

