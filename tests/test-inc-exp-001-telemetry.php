<?php
declare(strict_types=1);
ini_set('memory_limit', '512M');

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');

require dirname(__DIR__) . '/app/bootstrap.php';

if (getenv('RUMO10_INC_EXP_TEST_MYSQL') !== '1'
    || getenv('DB_HOST') !== '127.0.0.1'
    || !ctype_digit((string)getenv('DB_PORT'))
    || getenv('DB_USERNAME') === false
    || getenv('DB_PASSWORD') === false) {
    fwrite(STDERR, "Use uma instância MySQL isolada local e configure suas credenciais explicitamente.\n");
    exit(1);
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, "\n[EXCEPTION] " . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
});

$checks = 0;
function okExp(bool $cond, string $msg): void {
    global $checks;
    $checks++;
    if (!$cond) {
        throw new RuntimeException("FALHA R10-INC-EXP-001: {$msg}");
    }
    echo "  [PASS] {$msg}\n";
}

function makeKey(string $seed): string {
    return hash('sha256', 'r10-inc-exp-001:' . $seed);
}

function countExpectedTelemetry(array $paceMatches): array {
    $matches = count($paceMatches);
    $appearances = 0;
    $events = 0;
    $matchUids = [];
    $appUids = [];
    $eventUids = [];
    foreach ($paceMatches as $m) {
        if (!empty($m['canonical_match_uid'])) {
            $matchUids[] = $m['canonical_match_uid'];
        }
        $stats = $m['stats'] ?? [];
        $mins = array_key_exists('minutes', $stats) && $stats['minutes'] !== null ? (int)$stats['minutes'] : null;
        $hasApp = !empty($m['lineup']) || ($mins !== null && $mins > 0);
        if ($hasApp) {
            $appearances++;
            if (!empty($m['canonical_appearance_uid'])) {
                $appUids[] = $m['canonical_appearance_uid'];
            }
            foreach (($m['log'] ?? []) as $l) {
                $events++;
                if (!empty($l['canonical_event_uid'])) {
                    $eventUids[] = $l['canonical_event_uid'];
                }
            }
            foreach (($m['sound_events'] ?? []) as $s) {
                $events++;
                if (!empty($s['canonical_event_uid'])) {
                    $eventUids[] = $s['canonical_event_uid'];
                }
            }
        }
    }
    return [
        'matches' => $matches,
        'appearances' => $appearances,
        'events' => $events,
        'match_uids' => $matchUids,
        'app_uids' => $appUids,
        'event_uids' => $eventUids,
    ];
}

echo "=== R10-INC-EXP-001: HOMOLOGAÇÃO COMPLETA DA TELEMETRIA NO EXPRESSO (MYSQL ISOLADO) ===\n\n";

// =========================================================================
// 0. PROVISIONAMENTO DE BANCO MYSQL TEMPORÁRIO E ESTRITAMENTE ISOLADO
// =========================================================================
$host = config('DB_HOST', '127.0.0.1');
$port = config('DB_PORT', '3306');
$user = config('DB_USERNAME');
$pass = config('DB_PASSWORD');
$isoDb = 'rumo10_iso_incexp001_' . getmypid() . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

$adminPdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$adminPdo->exec("CREATE DATABASE `{$isoDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

register_shutdown_function(static function () use ($adminPdo, $isoDb): void {
    Repository::$testPostProcessHook = null;
    Repository::$testPreResponseHook = null;
    F01Drainer::$testChunkHook = null;
    try {
        $adminPdo->exec("DROP DATABASE IF EXISTS `{$isoDb}`");
    } catch (Throwable) {
    }
});

putenv("DB_DATABASE={$isoDb}");
$_ENV['DB_DATABASE'] = $isoDb;
$_SERVER['DB_DATABASE'] = $isoDb;
BalanceService::resetCache();

$db = Database::connect();
$activeDbName = (string)$db->query('SELECT DATABASE()')->fetchColumn();
okExp($activeDbName === $isoDb, "Conectado exclusivamente ao schema MySQL isolado '{$isoDb}'");

$rootDir = dirname(__DIR__);
// Mesma ordem de provisionamento do job oficial de compatibilidade (publisher/tests/compatible-writers.php)
foreach (['001.sql', '002.sql', '003.sql', '004.sql', '005-growth.sql', '006-balance-and-maintenance.sql'] as $migFile) {
    $db->exec((string)file_get_contents($rootDir . '/database/' . $migFile));
}
require_once $rootDir . '/database/seed_balance.php';
$db->beginTransaction();
seed_balance_data($db);
$db->exec((string)file_get_contents($rootDir . '/database/007-recalibracao-ofensiva.sql'));
$db->exec((string)file_get_contents($rootDir . '/database/008-penalties-balance.sql'));
$db->commit();
$db->exec((string)file_get_contents($rootDir . '/database/009-f01-canonical-telemetry.sql'));
BalanceService::resetCache();

$repo = new Repository($db);

$createAccount = static function (string $tag) use ($repo): int {
    static $seq = 0;
    $seq++;
    $repo->query(
        'INSERT INTO rumo10_contas(email, senha_hash) VALUES(?, ?)',
        ["{$tag}.{$seq}@isolated.invalid", password_hash('isolated-test-only', PASSWORD_DEFAULT)]
    );
    return (int)$repo->db->lastInsertId();
};

// =========================================================================
// 1. CENÁRIO EXATO DE REPRODUÇÃO: CD Malacateco-GT (gt-1, T13, 28 anos, >500 eventos)
// =========================================================================
echo "\n1. Cenário de Reprodução Oficial (CD Malacateco-GT, gt-1, T13, 28 anos, protagonismo)...\n";

$accGt = $createAccount('malacateco');
mt_srand(20261126);
$gGt = Game::create('Atleta Sintetico GT', 'Sao Paulo', 'SP', 'centroavante', 'CD Malacateco-GT');
$gGt = Game::command($gGt, 'handicap');
$gGt['play_mode'] = 'expresso';
$gGt['age'] = 28;
$gGt['season'] = 13;
for ($histS = 1; $histS <= 12; $histS++) {
    $gGt['history'][] = [
        'season' => $histS,
        'club' => 'CD Malacateco-GT',
        'next_club' => 'CD Malacateco-GT',
        'games' => 36,
        'minutes' => 2980,
        'goals' => 14,
        'assists' => 4,
        'average' => 7.12,
    ];
}
$repo->create($accGt, $gGt, false, makeKey('create-gt'));
$loadedGtBefore = $repo->load($accGt);
$careerGtId = (int)$loadedGtBefore['id'];
$verGtBefore = (int)$loadedGtBefore['version'];

mt_srand(20261126);
$memBeforeGt = memory_get_usage(false);
memory_reset_peak_usage();
$t0Gt = microtime(true);
$keyGtSim = makeKey('sim-gt-t13');
$savedGt = $repo->command($accGt, 'simulate', ['plan' => 'protagonismo'], $keyGtSim, $verGtBefore);
$msGtCmd = round((microtime(true) - $t0Gt) * 1000, 2);
$peakMbGt = round((memory_get_peak_usage(false) - $memBeforeGt) / 1048576, 2);

$expGt = countExpectedTelemetry($savedGt['pace_report']['matches'] ?? []);
echo "  [MÉTRICAS GT-1] Partidas={$expGt['matches']} | Aparições={$expGt['appearances']} | Eventos={$expGt['events']} | Command={$msGtCmd}ms | Pico adicional PHP={$peakMbGt}MiB\n";

okExp($savedGt['stage'] === 'resumo', "Temporada 13 em gt-1 concluída integralmente em 'resumo'");
okExp((int)$savedGt['version'] === $verGtBefore + 1, "Avanço único de versão ({$verGtBefore} -> {$savedGt['version']})");
okExp($expGt['matches'] >= 40 && $expGt['events'] > 500, "Temporada ultrapassou 500 eventos sem rejeição ({$expGt['matches']} jogos, {$expGt['events']} eventos)");
okExp(count($savedGt['history']) === 12, 'Histórico preexistente das 12 temporadas preservado integralmente');
okExp(
    count(array_unique($expGt['match_uids'])) === $expGt['matches']
    && count(array_unique($expGt['app_uids'])) === $expGt['appearances']
    && count(array_unique($expGt['event_uids'])) === $expGt['events'],
    'Todos os match_uid, appearance_uid e event_uid foram gerados de forma única e persistidos no estado salvo'
);

// Verificar consistência atômica entre rumo10_decisoes, rumo10_f01_respostas e rumo10_f01_outbox
$decRowGt = $repo->query('SELECT COUNT(*) AS cnt FROM rumo10_decisoes WHERE carreira_id=? AND chave=?', [$careerGtId, $keyGtSim])->fetch();
$respRowGt = $repo->query('SELECT command_uid, versao_anterior, versao_resultante FROM rumo10_f01_respostas WHERE carreira_id=? AND chave=?', [$careerGtId, $keyGtSim])->fetch();
$outRowGt = $repo->query('SELECT id, command_uid, total_eventos, estado, payload_json FROM rumo10_f01_outbox WHERE carreira_id=? AND chave=?', [$careerGtId, $keyGtSim])->fetch();

$payloadGt = json_decode($outRowGt['payload_json'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
$chunkCountGt = count(F01Telemetry::partitionMatchesIntoChunks($payloadGt['matches'] ?? []));
okExp(
    (int)$decRowGt['cnt'] === 1
    && $respRowGt !== false
    && $outRowGt !== false
    && $respRowGt['command_uid'] === $outRowGt['command_uid']
    && (int)$respRowGt['versao_anterior'] === $verGtBefore
    && (int)$respRowGt['versao_resultante'] === $verGtBefore + 1
    && (int)$outRowGt['total_eventos'] === $expGt['events']
    && $outRowGt['estado'] === 'pendente'
    && $chunkCountGt >= 3
    && ($payloadGt['contract_version'] ?? null) === 1
    && !array_key_exists('batching', $payloadGt),
    "Consistência exata entre rumo10_decisoes, rumo10_f01_respostas e rumo10_f01_outbox (1 registro único, command_uid idêntico, {$chunkCountGt} lotes)"
);

// Reenvio idempotente do mesmo comando (mesma chave) ANTES e DEPOIS da drenagem
$retryGt1 = $repo->command($accGt, 'simulate', ['plan' => 'protagonismo'], $keyGtSim, $verGtBefore);
okExp(
    (int)$retryGt1['version'] === (int)$savedGt['version']
    && ($retryGt1['pace_report']['canonical_batch_uid'] ?? '') === ($savedGt['pace_report']['canonical_batch_uid'] ?? '')
    && (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id=?', [$careerGtId])->fetchColumn() === 1
    && (int)$repo->query('SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id=?', [$careerGtId])->fetchColumn() === 1,
    'Reenvio idempotente do mesmo comando retorna exatamente o estado confirmado sem duplicar versão, decisão ou outbox'
);

$wrongPayloadCaught = false;
try {
    $repo->command($accGt, 'simulate', ['plan' => 'coletivo'], $keyGtSim, $verGtBefore);
} catch (InvalidArgumentException $e) {
    $wrongPayloadCaught = str_contains($e->getMessage(), 'Token já usado');
}
okExp($wrongPayloadCaught, 'Reuso da mesma chave com estratégia distinta é rejeitado determinísticamente');

// Drenagem em lotes para as tabelas canônicas da migração 009
$t0DrainGt = microtime(true);
$drainResGt = F01Drainer::drain($repo, $accGt, $careerGtId);
$msDrainGt = round((microtime(true) - $t0DrainGt) * 1000, 2);
echo "  [DRENAGEM GT-1] Processados={$drainResGt['processed']} | Falhas={$drainResGt['failed']} | Tempo={$msDrainGt}ms\n";

$cntPartidasGt = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=?', [$careerGtId])->fetchColumn();
$cntAparicoesGt = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_aparicoes WHERE carreira_id=?', [$careerGtId])->fetchColumn();
$cntEventosGt = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [$careerGtId])->fetchColumn();
$outStateGtAfter = $repo->query('SELECT estado, erro, projected_at_utc FROM rumo10_f01_outbox WHERE id=?', [(int)$outRowGt['id']])->fetch();

okExp(
    $drainResGt['processed'] === 1
    && $drainResGt['failed'] === 0
    && $outStateGtAfter['estado'] === 'processado'
    && $outStateGtAfter['erro'] === null
    && $outStateGtAfter['projected_at_utc'] !== null,
    'F01Drainer concluiu projeção e marcou outbox como processado sem erros'
);
okExp(
    $cntPartidasGt === $expGt['matches']
    && $cntAparicoesGt === $expGt['appearances']
    && $cntEventosGt === $expGt['events'],
    "Persistência canônica 100% íntegra sem perda nem truncamento: partidas={$cntPartidasGt}, aparições={$cntAparicoesGt}, eventos={$cntEventosGt}"
);

foreach ([
    ['partidas', 'match_uid', 'match_uids'],
    ['aparicoes', 'appearance_uid', 'app_uids'],
    ['eventos', 'event_uid', 'event_uids'],
] as [$table, $column, $expectedKey]) {
    $projected = $repo->query(
        "SELECT {$column} FROM rumo10_f01_{$table} WHERE carreira_id=?",
        [$careerGtId]
    )->fetchAll(PDO::FETCH_COLUMN);
    $expected = $expGt[$expectedKey];
    sort($projected);
    sort($expected);
    okExp($projected === $expected, "IDs canônicos de {$table} preservados da resposta até a projeção");
}

// Reprocessamento forçado da outbox (idempotência ON DUPLICATE KEY UPDATE sem duplicar eventos)
$repo->query("UPDATE rumo10_f01_outbox SET estado='pendente', projected_at_utc=NULL WHERE id=?", [(int)$outRowGt['id']]);
$reDrainGt = F01Drainer::drain($repo, $accGt, $careerGtId);
$cntPartidasRe = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=?', [$careerGtId])->fetchColumn();
$cntAparicoesRe = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_aparicoes WHERE carreira_id=?', [$careerGtId])->fetchColumn();
$cntEventosRe = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [$careerGtId])->fetchColumn();
okExp(
    $reDrainGt['processed'] === 1
    && $cntPartidasRe === $expGt['matches']
    && $cntAparicoesRe === $expGt['appearances']
    && $cntEventosRe === $expGt['events'],
    "Reprocessamento completo da outbox é estritamente idempotente (0 duplicatas, {$cntEventosRe} eventos intactos)"
);

// Continuidade da carreira após o Expresso (roleta + renovação -> temporada 14 gravada no histórico)
$afterOffersGt = $repo->command($accGt, 'offers', [], makeKey('offers-gt-t13'), (int)$savedGt['version']);
$afterTransferGt = $repo->command($accGt, 'transfer', ['accept' => 'no'], makeKey('transfer-gt-t13'), (int)$afterOffersGt['version']);
okExp(
    (int)$afterTransferGt['season'] === 14
    && count($afterTransferGt['history']) === 13
    && (int)$afterTransferGt['history'][12]['season'] === 13,
    'Continuidade da temporada 13 -> 14 preserva estatísticas, avaliação e histórico anual'
);

// =========================================================================
// 2. FAIXAS DE VOLUME (<500, EXATAMENTE 500, 501 E CALENDÁRIO EXTENSO + 4 ESTRATÉGIAS)
// =========================================================================
echo "\n2. Validando Fronteiras de Volume (<500, =500 exato, =501 e Calendário Extenso) e 4 Estratégias...\n";

// 2A: < 500 eventos (liga curta + 4 estratégias diferentes)
$plans = ['protagonismo', 'coletivo', 'evolucao', 'equilibrio'];
foreach ($plans as $planIdx => $planName) {
    $accShort = $createAccount("short-{$planName}");
    $gShort = Game::legacyCreate("Curta {$planName}", 'Santos', 'SP', 'meia');
    $gShort = Game::command($gShort, 'handicap');
    $gShort['play_mode'] = 'expresso';
    $repo->create($accShort, $gShort, false, makeKey("create-short-{$planName}"));
    $loadedShort = $repo->load($accShort);
    $resShort = $repo->command($accShort, 'simulate', ['plan' => $planName], makeKey("sim-short-{$planName}"), (int)$loadedShort['version']);
    $expShort = countExpectedTelemetry($resShort['pace_report']['matches'] ?? []);
    $drShort = F01Drainer::drain($repo, $accShort, (int)$resShort['id']);
    $dbEvShort = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [(int)$resShort['id']])->fetchColumn();
    okExp(
        $expShort['events'] > 0 && $expShort['events'] < 500
        && $drShort['processed'] === 1 && $dbEvShort === $expShort['events'],
        "Temporada < 500 eventos com estratégia '{$planName}': {$expShort['matches']} jogos e {$dbEvShort} eventos persistidos/drenados"
    );
}

// 2B: Exatamente 500 eventos e exatamente 501 eventos via Repository::command() real
foreach ([500, 501] as $exactTarget) {
    $accExact = $createAccount("exact-{$exactTarget}");
    $gExact = Game::legacyCreate("Exato {$exactTarget}", 'Santos', 'SP', 'centroavante');
    $gExact = Game::command($gExact, 'handicap');
    $gExact['play_mode'] = 'expresso';
    foreach ($gExact['skills'] as &$sk) {
        $sk['value'] = 75;
    }
    unset($sk);
    $repo->create($accExact, $gExact, false, makeKey("create-exact-{$exactTarget}"));
    $loadedEx = $repo->load($accExact);

    Repository::$testPostProcessHook = static function (array &$next, string $action) use ($exactTarget): void {
        if ($action !== 'simulate') {
            return;
        }
        // Ajusta os logs sintéticos apenas para atingir a fronteira exata (500 ou 501) distribuída entre as 14 partidas (~35 eventos/jogo < 500)
        $matches = &$next['pace_report']['matches'];
        $current = 0;
        foreach ($matches as $idx => &$m) {
            $m['stats']['minutes'] = max(15, (int)($m['stats']['minutes'] ?? 90));
            $m['sound_events'] = [];
            $m['log'] = [['minute' => 90, 'text' => 'Placar final consolidado.']];
            $current += 1;
        }
        unset($m);
        $remaining = $exactTarget - $current;
        $mCount = count($matches);
        for ($i = 0; $i < $remaining; $i++) {
            $targetIdx = $i % $mCount;
            $matches[$targetIdx]['log'][] = ['minute' => 10 + ($i % 75), 'text' => "Lance auditável #{$i} na partida."];
        }
    };
    try {
        $savedEx = $repo->command($accExact, 'simulate', ['plan' => 'protagonismo'], makeKey("sim-exact-{$exactTarget}"), (int)$loadedEx['version']);
    } finally {
        Repository::$testPostProcessHook = null;
    }

    $outExTotal = (int)$repo->query('SELECT total_eventos FROM rumo10_f01_outbox WHERE carreira_id=?', [(int)$savedEx['id']])->fetchColumn();
    $drEx = F01Drainer::drain($repo, $accExact, (int)$savedEx['id']);
    $dbEvEx = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [(int)$savedEx['id']])->fetchColumn();
    okExp(
        $outExTotal === $exactTarget && $drEx['processed'] === 1 && $dbEvEx === $exactTarget,
        "Fronteira exata de {$exactTarget} eventos via Repository::command() + F01Drainer: outbox={$outExTotal}, eventos_canônicos={$dbEvEx}"
    );
}

// 2C: Calendário extenso (Estadual A1 + Brasileiro Série A, carreira avançada T15, estadual e liga nacional)
$accExt = $createAccount('calendario-extenso');
mt_srand(20261101);
$gExt = Game::create('Veterano Extenso', 'Sao Paulo', 'SP', 'meia', 'Palmeiras-SP');
$gExt = Game::command($gExt, 'handicap');
$gExt['play_mode'] = 'expresso';
$gExt['age'] = 30;
$gExt['season'] = 15;
foreach ($gExt['skills'] as &$sk) {
    $sk['value'] = 82;
}
unset($sk);
$repo->create($accExt, $gExt, false, makeKey('create-ext'));
$loadedExt = $repo->load($accExt);
$memBeforeExt = memory_get_usage(false);
memory_reset_peak_usage();
$t0Ext = microtime(true);
$savedExt = $repo->command($accExt, 'simulate', ['plan' => 'coletivo'], makeKey('sim-ext'), (int)$loadedExt['version']);
$msExt = round((microtime(true) - $t0Ext) * 1000, 2);
$peakMbExt = round((memory_get_peak_usage(false) - $memBeforeExt) / 1048576, 2);
$expExt = countExpectedTelemetry($savedExt['pace_report']['matches'] ?? []);
$drExt = F01Drainer::drain($repo, $accExt, (int)$savedExt['id']);
$dbEvExt = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [(int)$savedExt['id']])->fetchColumn();
$dbMatchExt = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=?', [(int)$savedExt['id']])->fetchColumn();
$extOutboxError = $repo->query('SELECT erro FROM rumo10_f01_outbox WHERE carreira_id=?', [(int)$savedExt['id']])->fetchColumn();
printf("  [PROJEÇÃO PALMEIRAS-SP] Processados=%d | Falhas=%d | Partidas=%d | Eventos=%d | Erro=%s\n",
    $drExt['processed'], $drExt['failed'], $dbMatchExt, $dbEvExt, $extOutboxError ?? '-');
echo "  [CALENDÁRIO EXTENSO PALMEIRAS-SP] Partidas={$expExt['matches']} | Eventos={$expExt['events']} | Tempo={$msExt}ms | Pico adicional PHP={$peakMbExt}MiB\n";
okExp(
    $expExt['matches'] >= 38 && count(array_unique(array_column($savedExt['pace_report']['matches'], 'competition'))) >= 2
    && $expExt['events'] > 550
    && $drExt['processed'] === 1 && $dbMatchExt === $expExt['matches'] && $dbEvExt === $expExt['events'],
    "Calendário extenso ({$expExt['matches']} partidas, {$expExt['events']} eventos) concluído e drenado integralmente em {$msExt}ms"
);

// =========================================================================
// 3. MODOS NORMAL E RÁPIDO PELO FLUXO COMPLETO DE Repository::command()
// =========================================================================
echo "\n3. Validando Modos Normal e Rápido via Repository::command() + F01Drainer...\n";

// Modo Rápido (1 partida por comando simulate)
$accRap = $createAccount('modo-rapido');
$gRap = Game::legacyCreate('Atleta Rapido', 'Santos', 'SP', 'ponta');
$gRap = Game::command($gRap, 'handicap');
$gRap['play_mode'] = 'rapido';
$repo->create($accRap, $gRap, false, makeKey('create-rap'));
$loadRap = $repo->load($accRap);
$resRap = $repo->command($accRap, 'simulate', ['plan' => 'protagonismo'], makeKey('sim-rap-1'), (int)$loadRap['version']);
$drRap = F01Drainer::drain($repo, $accRap, (int)$resRap['id']);
$cntPartRap = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=? AND modo_resolucao="rapido"', [(int)$resRap['id']])->fetchColumn();
okExp($drRap['processed'] === 1 && $cntPartRap === 1, 'Modo Rápido registra e projeta partida canônica com modo_resolucao=rapido');

// Modo Normal (ciclo real até conclusão da 1ª partida via Repository::command)
$accNorm = $createAccount('modo-normal');
$gNorm = Game::legacyCreate('Atleta Normal', 'Santos', 'SP', 'zagueiro');
$gNorm = Game::command($gNorm, 'handicap');
$gNorm['play_mode'] = 'normal';
$gNorm['states']['risco_lesao'] = 0;
$repo->create($accNorm, $gNorm, false, makeKey('create-norm'));
$curNorm = $repo->load($accNorm);
$stepNorm = 0;
while ($curNorm['stage'] !== 'fim_semana' && $stepNorm < 25) {
    $stepNorm++;
    if ($curNorm['stage'] === 'partida') {
        $mN = $curNorm['matches'][Game::currentIndex($curNorm)];
        $actN = MatchParticipation::canActOnField($mN) ? 'choice' : 'advance';
        $inpN = $actN === 'choice' ? ['option' => '0'] : [];
    } else {
        $actN = 'choice';
        $inpN = ['option' => '0'];
    }
    $curNorm = $repo->command($accNorm, $actN, $inpN, makeKey("norm-step-{$stepNorm}"), (int)$curNorm['version']);
}
$drNorm = F01Drainer::drain($repo, $accNorm, (int)$curNorm['id']);
$cntPartNorm = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=? AND modo_resolucao="normal"', [(int)$curNorm['id']])->fetchColumn();
okExp($curNorm['stage'] === 'fim_semana' && $drNorm['processed'] === 1 && $cntPartNorm === 1, 'Modo Normal registra e projeta partida canônica com modo_resolucao=normal');

// =========================================================================
// 4. ATOMICIDADE, ROLLBACK ANTES DO COMMIT E FALHA PARCIAL NA OUTBOX
// =========================================================================
echo "\n4. Validando Atomicidade Transacional (Rollback Integral) e Recuperação de Outbox...\n";

$accAtom = $createAccount('atomicidade');
mt_srand(20261126);
$gAtom = Game::create('Atleta Atomico', 'Sao Paulo', 'SP', 'centroavante', 'CD Malacateco-GT');
$gAtom = Game::command($gAtom, 'handicap');
$gAtom['play_mode'] = 'expresso';
$gAtom['age'] = 28;
$gAtom['season'] = 13;
$repo->create($accAtom, $gAtom, false, makeKey('create-atom'));
$beforeAtom = $repo->load($accAtom);
$atomId = (int)$beforeAtom['id'];
$atomVer = (int)$beforeAtom['version'];
$snapJsonBefore = (string)$repo->query('SELECT estado_json FROM rumo10_universos WHERE carreira_id=?', [$atomId])->fetchColumn();

// 4A: Falha controlada APÓS gravação de rumo10_universos, rumo10_decisoes e rumo10_f01_outbox, ANTES do commit final
$reachedPreResponse = false;
Repository::$testPreResponseHook = static function (int $cid, string $k, PDO $pdo) use (&$reachedPreResponse): void {
    $rowsWritten = $pdo->prepare('SELECT COUNT(*) FROM rumo10_decisoes d JOIN rumo10_f01_outbox o
        ON o.carreira_id=d.carreira_id AND o.chave=d.chave WHERE d.carreira_id=? AND d.chave=?');
    $rowsWritten->execute([$cid, $k]);
    $reachedPreResponse = $pdo->inTransaction() && (int)$rowsWritten->fetchColumn() === 1;
    throw new RuntimeException('Falha controlada pre-resposta antes do commit.');
};
$preRespRolledBack = false;
try {
    $repo->command($accAtom, 'simulate', ['plan' => 'protagonismo'], makeKey('atom-fail-preresp'), $atomVer);
} catch (RuntimeException $e) {
    $preRespRolledBack = $e->getMessage() === 'Falha controlada pre-resposta antes do commit.';
} finally {
    Repository::$testPreResponseHook = null;
}

$afterRollback = $repo->load($accAtom);
$snapJsonAfter = (string)$repo->query('SELECT estado_json FROM rumo10_universos WHERE carreira_id=?', [$atomId])->fetchColumn();
$cntDecAtom = (int)$repo->query('SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id=?', [$atomId])->fetchColumn();
$cntOutAtom = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id=?', [$atomId])->fetchColumn();
$cntRespAtom = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_respostas WHERE carreira_id=?', [$atomId])->fetchColumn();

okExp(
    $preRespRolledBack && $reachedPreResponse
    && (int)$afterRollback['version'] === $atomVer
    && $afterRollback['stage'] === 'treino'
    && $snapJsonAfter === $snapJsonBefore
    && $cntDecAtom === 0 && $cntOutAtom === 0 && $cntRespAtom === 0,
    'Rollback integral comprovado: falha antes do commit desfaz carreira, universo, decisão, outbox e resposta'
);

// 4B: Confirma o comando e injeta falha controlada no MEIO da drenagem em lotes (após o lote 0 já ter executado INSERTs)
mt_srand(20261126);
$confirmedAtom = $repo->command($accAtom, 'simulate', ['plan' => 'protagonismo'], makeKey('atom-ok-after-rb'), $atomVer);
$expAtom = countExpectedTelemetry($confirmedAtom['pace_report']['matches'] ?? []);

F01Drainer::$testChunkHook = static function (int $chunkIdx, int $totalChunks): void {
    if ($chunkIdx === 0 && $totalChunks > 1) {
        throw new RuntimeException('Falha controlada entre lotes da outbox com dado sensível user@secret.com SELECT * FROM rumo10_contas');
    }
};
try {
    $drainFailRes = F01Drainer::drain($repo, $accAtom, $atomId);
} finally {
    F01Drainer::$testChunkHook = null;
}

$outAfterChunkFail = $repo->query('SELECT estado, tentativas, erro FROM rumo10_f01_outbox WHERE carreira_id=?', [$atomId])->fetch();
$partAfterChunkFail = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id=?', [$atomId])->fetchColumn();
$evAfterChunkFail = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [$atomId])->fetchColumn();

okExp(
    $drainFailRes['processed'] === 0 && $drainFailRes['failed'] === 1
    && $outAfterChunkFail['estado'] === 'pendente'
    && (int)$outAfterChunkFail['tentativas'] === 1
    && $partAfterChunkFail === 0 && $evAfterChunkFail === 0,
    'Falha entre lotes da outbox faz rollback transacional completo das tabelas canônicas (0 partidas/eventos parciais)'
);
okExp(
    str_starts_with((string)$outAfterChunkFail['erro'], 'rumo10_diag ')
    && !str_contains((string)$outAfterChunkFail['erro'], 'user@secret.com')
    && !str_contains((string)$outAfterChunkFail['erro'], 'rumo10_contas'),
    'Erro registrado na outbox utiliza diagnóstico técnico sanitizado sem vazar e-mail ou SQL'
);

// Reprocessa após falha parcial -> projeta 100% dos eventos
$drainRecovered = F01Drainer::drain($repo, $accAtom, $atomId);
$evAfterRecover = (int)$repo->query('SELECT COUNT(*) FROM rumo10_f01_eventos WHERE carreira_id=?', [$atomId])->fetchColumn();
okExp(
    $drainRecovered['processed'] === 1 && $drainRecovered['failed'] === 0 && $evAfterRecover === $expAtom['events'],
    "Outbox recupera da falha anterior e projeta todos os {$evAfterRecover} eventos sem perda nem duplicação"
);

// 4C: Proteção estrutural contra partida individual corrompida (> MAX_EVENTS_PER_MATCH = 500)
$accAnom = $createAccount('anomalia-partida');
$gAnom = Game::legacyCreate('Teste Anomalia', 'Santos', 'SP', 'centroavante');
$gAnom = Game::command($gAnom, 'handicap');
$gAnom['play_mode'] = 'rapido';
$repo->create($accAnom, $gAnom, false, makeKey('create-anom'));
$loadAnom = $repo->load($accAnom);

Repository::$testPostProcessHook = static function (array &$next): void {
    for ($i = 0; $i < 501; $i++) {
        $next['pace_report']['matches'][0]['log'][] = ['minute' => 45, 'text' => "Loop sintético #{$i}"];
    }
};
$anomBlocked = false;
try {
    $repo->command($accAnom, 'simulate', ['plan' => 'protagonismo'], makeKey('sim-anom'), (int)$loadAnom['version']);
} catch (RuntimeException) {
    $anomBlocked = true;
} finally {
    Repository::$testPostProcessHook = null;
}
$diagAnom = Repository::$lastFailureDiagnostic;
okExp(
    $anomBlocked
    && ($diagAnom['category'] ?? '') === 'f01_telemetry_volume'
    && ($diagAnom['reason_code'] ?? '') === 'MATCH_EVENTS'
    && (int)$repo->load($accAnom)['version'] === (int)$loadAnom['version'],
    'Anomalia real por partida (>500 eventos em 1 jogo) continua bloqueada com rollback e reason_code=MATCH_EVENTS'
);

// =========================================================================
// 5. AUDITORIA DE SANITIZAÇÃO DE DIAGNÓSTICO DE EXCEÇÕES (SEÇÃO 6)
// =========================================================================
echo "\n5. Validando Sanitização Estrita dos Diagnósticos de Exceções...\n";

$fakePdoEx = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'cpf=123.456.789-00|senha=SuperSecreta123|email=dono@clube.com' for key 'uk'");
$fakePdoEx->errorInfo = ['23000', 1062, "Duplicate entry 'secret'"];
$logPdo = F01Telemetry::formatDiagnosticLog($fakePdoEx, [
    'route' => '/carreira/simular',
    'method' => 'POST',
    'action' => 'simulate',
    'mode' => 'expresso',
    'career_id' => 879927,
    'email' => 'dono@clube.com',
    'password' => 'SuperSecreta123',
    'csrf' => str_repeat('f', 64),
    'key' => str_repeat('9', 64),
    'save_json' => '{"name":"Segredo"}',
]);
okExp(
    str_contains($logPdo, 'category=pdo_database')
    && str_contains($logPdo, 'sqlstate=23000')
    && str_contains($logPdo, 'driver_code=1062')
    && str_contains($logPdo, 'route=/carreira/simular')
    && str_contains($logPdo, 'mode=expresso')
    && !str_contains($logPdo, 'SuperSecreta123')
    && !str_contains($logPdo, 'dono@clube.com')
    && !str_contains($logPdo, '123.456.789-00')
    && !str_contains($logPdo, 'Segredo')
    && !str_contains($logPdo, str_repeat('f', 64)),
    'Diagnóstico sanitizado registra metadados técnicos úteis e elimina 100% de PII, credenciais, SQL, tokens e save'
);

okExp(
    max($peakMbGt, $peakMbExt) < 256.0,
    "Pico adicional por comando Expresso menor que 256 MiB (GT-1: {$peakMbGt}; Palmeiras: {$peakMbExt})"
);

echo "\n====================================================================\n";
echo "SUCESSO: TODAS AS {$checks} VERIFICAÇÕES DA R10-INC-EXP-001 FORAM APROVADAS!\n";
echo "====================================================================\n";
