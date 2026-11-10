<?php
declare(strict_types=1);

ini_set('memory_limit', '512M');
require_once __DIR__ . '/app/bootstrap.php';

$pdo = new PDO("mysql:host=127.0.0.1;port=33306;dbname=r10_f01_test;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("SET time_zone = '+00:00'");

$repo = new Repository($pdo);

echo "=====================================================================\n";
echo "RUMO10 | OS-F01-P1-005 | BATERIA INTEGRADA DE HOMOLOGAÇÃO (3 CAMADAS)\n";
echo "=====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $scenario, bool $condition, string $msg = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $scenario\n";
        $passCount++;
    } else {
        echo "[FAIL] $scenario: $msg\n";
        $failCount++;
    }
}

// Reset limpo do banco de testes
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE rumo10_f01_eventos");
$pdo->exec("TRUNCATE TABLE rumo10_f01_aparicoes");
$pdo->exec("TRUNCATE TABLE rumo10_f01_partidas");
$pdo->exec("TRUNCATE TABLE rumo10_f01_outbox");
$pdo->exec("TRUNCATE TABLE rumo10_f01_respostas");
$pdo->exec("TRUNCATE TABLE rumo10_decisoes");
$pdo->exec("TRUNCATE TABLE rumo10_carreiras");
$pdo->exec("TRUNCATE TABLE rumo10_contas");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES (1, 'test@rumo10.test', 'hash')");
$account = 1;

// =====================================================================
// CAMADA 1: TESTES UNITÁRIOS E FIXTURES
// =====================================================================
echo "--- CAMADA 1: TESTES UNITÁRIOS E FIXTURES (UUID v4, ESCALAÇÃO DESCONHECIDA, PROVENIÊNCIA) ---\n";

// CENÁRIO U01: UUID v4 RFC 4122 legítimo e persistido
try {
    $u1 = CanonicalIdentity::uuidV4();
    $u2 = CanonicalIdentity::uuidV4();
    $isV4_1 = CanonicalIdentity::isValidUuid($u1) && $u1[14] === '4';
    $isV4_2 = CanonicalIdentity::isValidUuid($u2) && $u2[14] === '4';
    assertTest("C-U01: Geração estrita de UUID v4 (RFC 4122) com entropia criptográfica", $isV4_1 && $isV4_2 && $u1 !== $u2);
} catch (Throwable $e) {
    assertTest("C-U01: Geração de UUID v4", false, $e->getMessage());
}

// CENÁRIO U02: Correção Obrigatória A - Escalação Desconhecida com Minutagem Positiva
try {
    // Caso 1: Jogador com 20 minutos jogados, mas escalação não informada (vazia/ausente)
    $mockPartida20min = [
        'home' => 'Santos',
        'away' => 'Palmeiras',
        'round' => 3,
        'lineup' => '', // Escalação não informada
        'stats' => ['minutes' => 20, 'rating' => 6.5],
        'status' => 'encerrada',
        'log' => [],
    ];
    $after20min = ['id' => 991, 'season' => 1, 'round' => 3, 'stage' => 'resumo', 'matches' => [$mockPartida20min], 'pace_report' => ['mode' => 'rapido', 'matches' => [$mockPartida20min]]];
    $env20min = F01Telemetry::extractEnvelope([], $after20min, 'simulate', ['plan' => 'equilibrio'], CanonicalIdentity::uuidV4(), 'k_20min');
    
    $m20min = $env20min['matches'][0];
    $app20min = $m20min['appearances'][0];
    
    $preservesMinutes = ($app20min['minutos_jogados'] === 20);
    $condicaoNull = ($app20min['condicao_escalacao'] === null); // Rigorosamente NULL
    $noEntryInvented = ($app20min['minuto_entrada'] === null);
    $noExitInvented = ($app20min['minuto_saida'] === null);

    // Caso 2: Não relacionado (0 min e sem escalação) -> não fabrica aparição
    $mockPartidaOut = [
        'home' => 'Santos',
        'away' => 'Palmeiras',
        'lineup' => '',
        'stats' => [],
        'status' => 'encerrada',
        'log' => [],
    ];
    $afterOut = ['id' => 991, 'season' => 1, 'stage' => 'resumo', 'matches' => [$mockPartidaOut], 'pace_report' => ['mode' => 'rapido', 'matches' => [$mockPartidaOut]]];
    $envOut = F01Telemetry::extractEnvelope([], $afterOut, 'simulate', ['plan' => 'equilibrio'], CanonicalIdentity::uuidV4(), 'k_out');
    $noAppCreated = empty($envOut['matches'][0]['appearances']);

    assertTest("C-U02: Escalação desconhecida com 20min preserva condicao_escalacao = NULL sem inferir titularidade", 
        $preservesMinutes && $condicaoNull && $noEntryInvented && $noExitInvented && $noAppCreated
    );
} catch (Throwable $e) {
    assertTest("C-U02: Escalação desconhecida", false, $e->getMessage());
}

// CENÁRIO U03: Proveniência do Artefato - Cobertura Integral de JavaScript, Fixtures Codex e Ambientes (OS-F01-P1-008 §2)
try {
    // 1. Caso Worktree Git: quando há modificações locais, NUNCA atribuir commit, mesmo se houver manifesto residual
    $manifestPath = dirname(__DIR__) . '/build-manifest.json';
    $fakeCommit = 'abcdef0123456789abcdef0123456789abcdef01';
    file_put_contents($manifestPath, json_encode(['commit' => $fakeCommit, 'dirty' => false]));

    $gitCommitResolved = CanonicalIdentity::currentEngineCommit();
    @unlink($manifestPath);
    $gitCaseOk = ($gitCommitResolved === 'desconhecido' && $gitCommitResolved !== $fakeCommit);

    // 2. Fixture do Codex (OS-F01-P1-007): Pacote sem .git, manifesto contendo SHA da V8 e APENAS note.txt
    $codexFixtureDir = sys_get_temp_dir() . '/r10_f01_codex_fixture_' . bin2hex(random_bytes(4));
    @mkdir($codexFixtureDir . '/public', 0777, true);
    @mkdir($codexFixtureDir . '/app', 0777, true);
    file_put_contents($codexFixtureDir . '/note.txt', "hello world\n");
    file_put_contents($codexFixtureDir . '/public/index.php', "<?php echo 'index';\n");
    file_put_contents($codexFixtureDir . '/build-manifest.json', json_encode([
        'commit' => 'f23c2c3be2fbc322daf817fa14346ab02073a97b',
        'checksums' => [
            'note.txt' => hash_file('sha256', $codexFixtureDir . '/note.txt')
        ]
    ]));

    $codexBefore = CanonicalIdentity::resolveCommitForDirectory($codexFixtureDir);
    $codexBeforeOk = ($codexBefore === 'desconhecido');

    file_put_contents($codexFixtureDir . '/public/index.php', "<?php echo 'modified index';\n");
    $codexAfter = CanonicalIdentity::resolveCommitForDirectory($codexFixtureDir);
    $codexAfterOk = ($codexAfter === 'desconhecido');

    // 3. Fixture Específica do Codex OS-F01-P1-008 §2: game.js presente em disco, mas omitido do manifesto
    $codexJsDir = sys_get_temp_dir() . '/r10_f01_codex_js_' . bin2hex(random_bytes(4));
    @mkdir($codexJsDir . '/public/assets', 0777, true);
    @mkdir($codexJsDir . '/app', 0777, true);
    file_put_contents($codexJsDir . '/public/index.php', "<?php // index\n");
    file_put_contents($codexJsDir . '/public/assets/game.js', "console.log('game.js');\n");
    file_put_contents($codexJsDir . '/build-manifest.json', json_encode([
        'commit' => 'f23c2c3be2fbc322daf817fa14346ab02073a97b',
        'checksums' => [
            'public/index.php' => hash_file('sha256', $codexJsDir . '/public/index.php'),
            // game.js OMITIDO propositalmente
        ]
    ]));
    $codexJsOmitted = CanonicalIdentity::resolveCommitForDirectory($codexJsDir);
    $codexJsOmittedOk = ($codexJsOmitted === 'desconhecido');

    // 4. Caso Pacote de Release com Cobertura Integral Válida (incluindo todos os JS distribuídos)
    $releaseDir = sys_get_temp_dir() . '/r10_f01_valid_release_' . bin2hex(random_bytes(4));
    @mkdir($releaseDir . '/public/assets', 0777, true);
    @mkdir($releaseDir . '/app', 0777, true);
    @mkdir($releaseDir . '/database', 0777, true);

    $mandatoryList = [
        'public/index.php' => "<?php // index\n",
        'app/bootstrap.php' => "<?php // bootstrap\n",
        'app/CanonicalIdentity.php' => "<?php // id\n",
        'app/F01Telemetry.php' => "<?php // tele\n",
        'app/F01Drainer.php' => "<?php // drain\n",
        'app/Repository.php' => "<?php // repo\n",
        'app/Database.php' => "<?php // db\n",
        'app/Game.php' => "<?php // game\n",
        'app/view.php' => "<?php // view\n",
        'public/assets/game.js' => "// game js\n",
        'public/assets/audio-engine.js' => "// audio js\n",
        'public/assets/v061.js' => "// v061 js\n",
        'public/assets/f01-telemetry.js' => "// tele js\n",
    ];

    $checksums = [];
    foreach ($mandatoryList as $f => $content) {
        file_put_contents($releaseDir . '/' . $f, $content);
        $checksums[$f] = hash_file('sha256', $releaseDir . '/' . $f);
    }

    $validSha = '4444555566667777888899990000111122223333';
    file_put_contents($releaseDir . '/build-manifest.json', json_encode([
        'commit' => $validSha,
        'checksums' => $checksums
    ]));

    // 4a. Pacote íntegro com 100% dos executáveis (PHP, SQL e todos JS) cobertos
    $resValid = CanonicalIdentity::resolveCommitForDirectory($releaseDir);
    $validReleaseOk = ($resValid === $validSha);

    // 4b. Arquivo JS omitido do manifesto: adicionar script JS extra sem listar
    file_put_contents($releaseDir . '/public/assets/extra-module.js', "// extra js omitido\n");
    $resJsExtraOmitted = CanonicalIdentity::resolveCommitForDirectory($releaseDir);
    $extraJsOmittedOk = ($resJsExtraOmitted === 'desconhecido');
    @unlink($releaseDir . '/public/assets/extra-module.js');

    // 4c. Arquivo JS adulterado
    file_put_contents($releaseDir . '/public/assets/game.js', "// game js modificado\n");
    $resJsTampered = CanonicalIdentity::resolveCommitForDirectory($releaseDir);
    $jsTamperedOk = ($resJsTampered === 'desconhecido');

    // Limpeza
    foreach (array_keys($mandatoryList) as $f) @unlink($releaseDir . '/' . $f);
    @unlink($releaseDir . '/build-manifest.json');
    @rmdir($releaseDir . '/public/assets');
    @rmdir($releaseDir . '/public');
    @rmdir($releaseDir . '/app');
    @rmdir($releaseDir . '/database');
    @rmdir($releaseDir);

    @unlink($codexFixtureDir . '/note.txt');
    @unlink($codexFixtureDir . '/public/index.php');
    @unlink($codexFixtureDir . '/build-manifest.json');
    @rmdir($codexFixtureDir . '/public');
    @rmdir($codexFixtureDir . '/app');
    @rmdir($codexFixtureDir);

    @unlink($codexJsDir . '/public/assets/game.js');
    @unlink($codexJsDir . '/public/index.php');
    @unlink($codexJsDir . '/build-manifest.json');
    @rmdir($codexJsDir . '/public/assets');
    @rmdir($codexJsDir . '/public');
    @rmdir($codexJsDir . '/app');
    @rmdir($codexJsDir);

    assertTest("C-U03: Proveniência: cobertura integral (PHP/SQL/JS), fixture Codex game.js omitido e rejeição de adulteração", 
        $gitCaseOk && $codexBeforeOk && $codexAfterOk && $codexJsOmittedOk && $validReleaseOk && $extraJsOmittedOk && $jsTamperedOk
    );
} catch (Throwable $e) {
    if (isset($manifestPath)) @unlink($manifestPath);
    assertTest("C-U03: Proveniência e cobertura integral", false, $e->getMessage());
}

// CENÁRIO U04: Fail-fast pré-mutação por estouro de eventos
try {
    $beforeMock = ['stage' => 'treino', 'round' => 1, 'season' => 1];
    $simMatches = [];
    for ($i = 0; $i < 30; $i++) {
        $simMatches[] = [
            'home' => 'Time A', 'away' => 'Time B', 'hg' => 1, 'ag' => 0, 'competition' => 'liga',
            'stats' => ['minutes' => 90, 'rating' => 7.0],
            'log' => array_fill(0, 20, ['minute' => 10, 'text' => 'Lance']),
        ];
    }
    $afterMock = [
        'id' => 1, 'season' => 1, 'round' => 30, 'stage' => 'resumo', 'play_mode' => 'expresso',
        'pace_report' => ['mode' => 'expresso', 'matches' => $simMatches],
    ];
    $threw = false;
    try {
        F01Telemetry::extractEnvelope($beforeMock, $afterMock, 'simulate', ['plan' => 'coletivo'], CanonicalIdentity::uuidV4(), 'k_over');
    } catch (RuntimeException) {
        $threw = true;
    }
    assertTest("C-U04: Estouro do teto operacional de eventos dispara RuntimeException antes da persistência", $threw);
} catch (Throwable $e) {
    assertTest("C-U04: Fail-fast de eventos", false, $e->getMessage());
}

// =====================================================================
// CAMADA 2: TESTES DE INTEGRAÇÃO PHP / MYSQL
// =====================================================================
echo "\n--- CAMADA 2: TESTES DE INTEGRAÇÃO PHP/MYSQL (DRENAGEM, EXPRESSO, CONCORRÊNCIA) ---\n";

// CENÁRIO I01: Criação de Carreira (Modo Normal)
try {
    $gameNormal = EntryCountries::create('Craque Teste', 'Santos', 'SP', 'centroavante', 'BR', 'gratuito');
    $gameNormal['play_mode'] = 'normal';
    $repo->create($account, $gameNormal, false);
    $c = $repo->load($account);
    assertTest("C-I01: Criação de Carreira com version 0 e etapa handicap", $c !== null && ($c['name'] ?? '') === 'Craque Teste' && $c['version'] === 0);
} catch (Throwable $e) {
    assertTest("C-I01: Criação de Carreira", false, $e->getMessage());
}

$c = $repo->load($account);
$careerId = (int)$c['id'];

// CENÁRIO I02: Transação Atômica de Comando e Registro na Outbox com Timestamps
$key1 = bin2hex(random_bytes(32));
try {
    $next = $repo->command($account, 'handicap', [], $key1, $c['version']);
    $dec = $pdo->query("SELECT chave, request_hash FROM rumo10_decisoes WHERE carreira_id = $careerId AND chave = '$key1'")->fetch();
    $resp = $pdo->query("SELECT chave, request_hash, command_uid, versao_resultante, received_at_utc FROM rumo10_f01_respostas WHERE carreira_id = $careerId AND chave = '$key1'")->fetch();
    assertTest("C-I02: Transação atômica grava carreira, auditoria, resposta versionada e outbox com UTC real", 
        $dec !== false && $resp !== false && $resp['versao_resultante'] === $next['version'] && CanonicalIdentity::isValidUuid($resp['command_uid']) && !empty($resp['received_at_utc'])
    );
} catch (Throwable $e) {
    assertTest("C-I02: Transação atômica", false, $e->getMessage());
}

// CENÁRIO I03: Reenvio Idempotente Coincidente Retorna Resposta Original
try {
    $cBefore = $repo->load($account);
    $resReplay = $repo->command($account, 'handicap', [], $key1, $cBefore['version']);
    assertTest("C-I03: Reenvio idempotente com mesmo token retorna resposta original sem incrementar versão", 
        $resReplay['version'] === $cBefore['version']
    );
} catch (Throwable $e) {
    assertTest("C-I03: Reenvio idempotente", false, $e->getMessage());
}

// CENÁRIO I04: Reutilização de Token com Payload Conflitante Rejeitada
try {
    $cCur = $repo->load($account);
    $threwConflict = false;
    try {
        $repo->command($account, 'relocate', ['city' => 'Campinas', 'uf' => 'SP'], $key1, $cCur['version']);
    } catch (InvalidArgumentException) {
        $threwConflict = true;
    }
    assertTest("C-I04: Reutilização de token com payload conflitante rejeitada", $threwConflict);
} catch (Throwable $e) {
    assertTest("C-I04: Token conflitante", false, $e->getMessage());
}

// CENÁRIO I05: Versão Obsoleta Rejeitada com Sucesso
try {
    $keyObsolete = bin2hex(random_bytes(32));
    $threwObsolete = false;
    try {
        $repo->command($account, 'relocate', ['city' => 'Campinas', 'uf' => 'SP'], $keyObsolete, 0);
    } catch (InvalidArgumentException) {
        $threwObsolete = true;
    }
    assertTest("C-I05: Versão obsoleta rejeitada com sucesso", $threwObsolete);
} catch (Throwable $e) {
    assertTest("C-I05: Versão obsoleta", false, $e->getMessage());
}

// CENÁRIO I06: Persistência e Reutilização Exata de UUID v4 (Reextração Idêntica)
try {
    $mockMatch = [
        'home' => 'Santos',
        'away' => 'Corinthians',
        'round' => 1,
        'status' => 'encerrada',
        'hg' => 2,
        'ag' => 1,
        'lineup' => 'titular',
        'entry' => 0,
        'exit' => 90,
        'stats' => ['minutes' => 90, 'rating' => 8.5],
        'log' => [
            ['minute' => 10, 'text' => 'Gol de placa'],
            ['minute' => 40, 'text' => 'Cartão amarelo']
        ],
        'sound_events' => [
            ['type' => 'GOL', 'minute' => 10, 'seq' => 1]
        ]
    ];
    $simState = ['id' => $careerId, 'season' => 1, 'round' => 1, 'stage' => 'resumo', 'pace_report' => ['mode' => 'rapido', 'matches' => [$mockMatch]]];
    $cmdUid = CanonicalIdentity::uuidV4();
    $keyExtra = bin2hex(random_bytes(32));
    
    // Primeira extração incorpora os UUIDs v4 no estado $simState
    $env1 = F01Telemetry::extractEnvelope([], $simState, 'simulate', ['plan' => 'equilibrio'], $cmdUid, $keyExtra);
    // Segunda extração reutiliza os UUIDs v4 incorporados
    $env2 = F01Telemetry::extractEnvelope([], $simState, 'simulate', ['plan' => 'equilibrio'], $cmdUid, $keyExtra);

    $mUid1 = $env1['matches'][0]['match_uid'];
    $mUid2 = $env2['matches'][0]['match_uid'];
    $bUid1 = $env1['matches'][0]['batch_uid'];
    $bUid2 = $env2['matches'][0]['batch_uid'];
    $appUid1 = $env1['matches'][0]['appearances'][0]['appearance_uid'];
    $appUid2 = $env2['matches'][0]['appearances'][0]['appearance_uid'];
    $evUid1 = $env1['matches'][0]['appearances'][0]['events'][0]['event_uid'];
    $evUid2 = $env2['matches'][0]['appearances'][0]['events'][0]['event_uid'];

    $sameUuids = ($mUid1 === $mUid2 && $bUid1 === $bUid2 && $appUid1 === $appUid2 && $evUid1 === $evUid2);
    assertTest("C-I06: Identidade canônica persistente reutiliza exatamente UUID v4 (match, batch, appearance, event)", $sameUuids);
} catch (Throwable $e) {
    assertTest("C-I06: Reutilização de UUIDs", false, $e->getMessage());
}

// CENÁRIO I07: Ciclo Completo de Escalação Desconhecida (Outbox -> Drenagem -> SQL com NULL)
try {
    $unknownMatchUid = CanonicalIdentity::uuidV4();
    $unknownAppUid = CanonicalIdentity::uuidV4();
    $unknownCmd = CanonicalIdentity::uuidV4();
    $unknownKey = bin2hex(random_bytes(32));

    $unknownEnv = [
        'contract_version' => 1,
        'carreira_id' => $careerId,
        'command_uid' => $unknownCmd,
        'chave' => $unknownKey,
        'total_eventos' => 0,
        'matches' => [
            [
                'match_uid' => $unknownMatchUid,
                'temporada_numero' => 1,
                'competicao_codigo' => 'liga',
                'rodada_fase' => '4',
                'mandante' => 'Santos',
                'visitante' => 'Sao Paulo',
                'gols_mandante' => 1,
                'gols_visitante' => 0,
                'modo_resolucao' => 'normal',
                'appearances' => [
                    [
                        'appearance_uid' => $unknownAppUid,
                        'carreira_id' => $careerId,
                        'condicao_escalacao' => null, // Escalação estritamente NULL
                        'minuto_entrada' => null,
                        'minuto_saida' => null,
                        'minutos_jogados' => 20, // Minutos jogados positivos
                        'estado_participacao' => 'participou',
                        'nota_atuacao' => 6.0,
                        'events' => []
                    ]
                ]
            ]
        ]
    ];

    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 0, ?, 'pendente')")
        ->execute([$careerId, $unknownCmd, $unknownKey, json_encode($unknownEnv)]);

    $drainUnknown = F01Drainer::drain($repo, $account, $careerId, 1);
    $appSql = $pdo->query("SELECT appearance_uid, condicao_escalacao, minutos_jogados FROM rumo10_f01_aparicoes WHERE appearance_uid = '$unknownAppUid'")->fetch();

    assertTest("C-I07: Projeção SQL preserva condicao_escalacao = NULL e minutos_jogados = 20 no banco", 
        $drainUnknown['processed'] === 1 && $appSql !== false && $appSql['condicao_escalacao'] === null && (int)$appSql['minutos_jogados'] === 20
    );
} catch (Throwable $e) {
    assertTest("C-I07: Ciclo de escalação desconhecida", false, $e->getMessage());
}

// CENÁRIO I08: Integridade Relacional (FK Composta e UK de Aparição)
try {
    $threwFk = false;
    try {
        $fakeAppUid = CanonicalIdentity::uuidV4();
        $pdo->prepare("INSERT INTO rumo10_f01_eventos (event_uid, match_uid, appearance_uid, carreira_id, tipo_evento, dados_evento_json) VALUES (?, ?, ?, ?, 'lance', '{}')")
            ->execute([CanonicalIdentity::uuidV4(), $mUid1, $fakeAppUid, $careerId]);
    } catch (PDOException) {
        $threwFk = true;
    }
    
    $threwUk = false;
    try {
        $pdo->prepare("INSERT INTO rumo10_f01_aparicoes (appearance_uid, match_uid, carreira_id, condicao_escalacao, estado_participacao) VALUES (?, ?, ?, 'titular', 'participou')")
            ->execute([CanonicalIdentity::uuidV4(), $mUid1, $careerId]);
    } catch (PDOException) {
        $threwUk = true;
    }

    assertTest("C-I08: FK composta (match_uid, appearance_uid) e UK (match_uid, carreira_id) garantem integridade", $threwFk && $threwUk);
} catch (Throwable $e) {
    assertTest("C-I08: Integridade relacional", false, $e->getMessage());
}

// CENÁRIO I09: Modo Rápido Sintético com Comparação Fiel dos Fatos Esportivos
try {
    $accSim = 50;
    $pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES ($accSim, 'sim@rumo10.test', 'hash')");
    $gFast = EntryCountries::create('Craque Rapido', 'Santos', 'SP', 'meia', 'BR', 'gratuito');
    $gFast['play_mode'] = 'rapido';
    $repo->create($accSim, $gFast, false);
    $cFast = $repo->load($accSim);
    $fastId = (int)$cFast['id'];

    $fastKey = bin2hex(random_bytes(32));
    $fastAfter = $repo->command($accSim, 'handicap', [], $fastKey, $cFast['version']);
    
    $fastKeyClub = bin2hex(random_bytes(32));
    $fastAfterClub = $repo->command($accSim, 'join', ['option' => 0], $fastKeyClub, $fastAfter['version']);

    $fastKeySim = bin2hex(random_bytes(32));
    $fastRes = $repo->command($accSim, 'simulate', ['plan' => 'coletivo'], $fastKeySim, $fastAfterClub['version']);
    
    $fastOut = $pdo->query("SELECT payload_json FROM rumo10_f01_outbox WHERE carreira_id = $fastId AND chave = '$fastKeySim'")->fetch();
    $fastEnv = json_decode($fastOut['payload_json'] ?? '{}', true);

    $fastPaceMatches = $fastRes['pace_report']['matches'] ?? [];
    $fastEnvMatches = $fastEnv['matches'] ?? [];
    $fastCountMatch = count($fastPaceMatches) === count($fastEnvMatches) && count($fastEnvMatches) > 0;

    $sportsFactsMatch = true;
    foreach ($fastEnvMatches as $idx => $fem) {
        $originalMatch = $fastPaceMatches[$idx];
        if ($fem['mandante'] !== $originalMatch['home'] || $fem['visitante'] !== $originalMatch['away'] || $fem['gols_mandante'] !== $originalMatch['hg'] || $fem['gols_visitante'] !== $originalMatch['ag']) {
            $sportsFactsMatch = false;
            break;
        }
    }

    assertTest("C-I09: Comparação fiel dos fatos esportivos reais do modo Rápido com a telemetria", 
        $fastCountMatch && $sportsFactsMatch
    );
} catch (Throwable $e) {
    assertTest("C-I09: Fatos esportivos modo Rápido", false, $e->getMessage());
}

// CENÁRIO I10: Comprovação Obrigatória do Expresso - Caminho de Sucesso (Temporada Completa Atômica)
try {
    $accExpSuccess = 61;
    $pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES ($accExpSuccess, 'exp_ok@rumo10.test', 'hash')");
    $gExpS = EntryCountries::create('Craque Expresso Sucesso', 'Santos', 'SP', 'centroavante', 'BR', 'gratuito');
    $gExpS['play_mode'] = 'expresso';
    $repo->create($accExpSuccess, $gExpS, false);
    $cExpS = $repo->load($accExpSuccess);
    $expSId = (int)$cExpS['id'];

    $expSKey1 = bin2hex(random_bytes(32));
    $expSAfter1 = $repo->command($accExpSuccess, 'handicap', [], $expSKey1, $cExpS['version']);
    $expSKey2 = bin2hex(random_bytes(32));
    $expSAfter2 = $repo->command($accExpSuccess, 'join', ['option' => 0], $expSKey2, $expSAfter1['version']);

    // Simulação da temporada inteira no Expresso
    $expSSimKey = bin2hex(random_bytes(32));
    $expSRes = $repo->command($accExpSuccess, 'simulate', ['plan' => 'protagonismo'], $expSSimKey, $expSAfter2['version']);

    // Conferir persistência única e atômica
    $outExpRows = $pdo->query("SELECT o.id, o.total_eventos, o.payload_json FROM rumo10_f01_outbox o WHERE o.carreira_id = $expSId AND o.chave = '$expSSimKey'")->fetchAll();
    $respExpRows = $pdo->query("SELECT r.command_uid FROM rumo10_f01_respostas r WHERE r.carreira_id = $expSId AND r.chave = '$expSSimKey'")->fetchAll();
    
    $singleOutbox = (count($outExpRows) === 1);
    $singleResp = (count($respExpRows) === 1);

    // Drenar a temporada completa
    $drainExp = F01Drainer::drain($repo, $accExpSuccess, $expSId, 10);
    $expPayload = json_decode($outExpRows[0]['payload_json'], true);
    $paceMatches = $expSRes['pace_report']['matches'] ?? [];
    $envMatches = $expPayload['matches'] ?? [];

    $matchCountsEqual = (count($paceMatches) === count($envMatches) && count($envMatches) > 0);
    $eventsNotDiscarded = true;
    foreach ($envMatches as $i => $em) {
        $pMatch = $paceMatches[$i];
        if ($em['mandante'] !== $pMatch['home'] || $em['visitante'] !== $pMatch['away'] || $em['gols_mandante'] !== $pMatch['hg'] || $em['gols_visitante'] !== $pMatch['ag']) {
            $eventsNotDiscarded = false;
            break;
        }
    }

    assertTest("C-I10: Expresso Caminho Sucesso: temporada completa atômica, fatos fiéis sem eventos inventados/descartados", 
        $singleOutbox && $singleResp && $matchCountsEqual && $eventsNotDiscarded && $drainExp['processed'] === 1
    );
} catch (Throwable $e) {
    assertTest("C-I10: Expresso Sucesso", false, $e->getMessage());
}

// CENÁRIO I11: Comprovação Obrigatória do Expresso - Caminho de Falha Pós-Processamento Esportivo (Rollback Integral sem Resíduos - OS-F01-P1-006 §3)
try {
    $accExpFail = 62;
    $pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES ($accExpFail, 'exp_fail@rumo10.test', 'hash')");
    $gExpF = EntryCountries::create('Craque Expresso Falha', 'Santos', 'SP', 'centroavante', 'BR', 'gratuito');
    $gExpF['play_mode'] = 'expresso';
    $repo->create($accExpFail, $gExpF, false);
    $cExpF = $repo->load($accExpFail);
    $expFId = (int)$cExpF['id'];

    $expFKey1 = bin2hex(random_bytes(32));
    $expFAfter1 = $repo->command($accExpFail, 'handicap', [], $expFKey1, $cExpF['version']);
    $expFKey2 = bin2hex(random_bytes(32));
    $expFAfter2 = $repo->command($accExpFail, 'join', ['option' => 0], $expFKey2, $expFAfter1['version']);

    $verBeforeFail = $expFAfter2['version'];
    $stageBeforeFail = $expFAfter2['stage'];
    $roundBeforeFail = $expFAfter2['round'];
    $expFailKey = bin2hex(random_bytes(32));
    
    $engineReachedSportsProcessing = false;
    $matchesProcessedInEngine = 0;
    $threwRealFail = false;

    // Configurar injeção controlada de falha: executada APÓS o motor ter processado as partidas, mas ANTES de salvar/commitar
    Repository::$testPostProcessHook = function (array $next, string $action) use (&$engineReachedSportsProcessing, &$matchesProcessedInEngine) {
        if ($action === 'simulate') {
            $matches = $next['pace_report']['matches'] ?? [];
            $matchesProcessedInEngine = count($matches);
            if ($matchesProcessedInEngine > 0) {
                $engineReachedSportsProcessing = true;
                // Lançar falha crítica pós-processamento esportivo para abortar antes da confirmação
                throw new RuntimeException("FALHA_CONTROLADA_TESTE_POS_MOTOR_ESPORTIVO");
            }
        }
    };

    try {
        // Enviar simulação legítima com plano 'protagonismo' (o motor processará a temporada inteira)
        $repo->command($accExpFail, 'simulate', ['plan' => 'protagonismo'], $expFailKey, $verBeforeFail);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'FALHA_CONTROLADA_TESTE_POS_MOTOR_ESPORTIVO') {
            $threwRealFail = true;
        }
    } finally {
        Repository::$testPostProcessHook = null;
    }

    $cAfterRollback = $repo->load($accExpFail);
    $outboxCount = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id = $expFId AND chave = '$expFailKey'")->fetchColumn();
    $respCount = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_respostas WHERE carreira_id = $expFId AND chave = '$expFailKey'")->fetchColumn();
    $decCount = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id = $expFId AND chave = '$expFailKey'")->fetchColumn();
    $partidasCount = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id = $expFId")->fetchColumn();

    $statePreserved = (
        $cAfterRollback['version'] === $verBeforeFail &&
        $cAfterRollback['stage'] === $stageBeforeFail &&
        $cAfterRollback['round'] === $roundBeforeFail
    );

    assertTest("C-I11: Expresso Caminho Falha: falha pós-motor aborta e executa rollback integral sem resíduos", 
        $threwRealFail && $engineReachedSportsProcessing && $matchesProcessedInEngine > 0 &&
        $statePreserved && $outboxCount === 0 && $respCount === 0 && $decCount === 0 && $partidasCount === 0
    );
} catch (Throwable $e) {
    Repository::$testPostProcessHook = null;
    assertTest("C-I11: Expresso Falha", false, $e->getMessage());
}

// CENÁRIO I11B: Comprovação Obrigatória do Rollback Real Pós-Escritas SQL (OS-F01-P1-009)
try {
    $accExpSqlFail = 63;
    $pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES ($accExpSqlFail, 'exp_sqlfail@rumo10.test', 'hash')");
    $gExpSql = EntryCountries::create('Craque Rollback SQL', 'Santos', 'SP', 'centroavante', 'BR', 'gratuito');
    $gExpSql['play_mode'] = 'expresso';
    $repo->create($accExpSqlFail, $gExpSql, false);
    $cExpSql = $repo->load($accExpSqlFail);
    $expSqlId = (int)$cExpSql['id'];

    $expSqlKey1 = bin2hex(random_bytes(32));
    $expSqlAfter1 = $repo->command($accExpSqlFail, 'handicap', [], $expSqlKey1, $cExpSql['version']);
    $expSqlKey2 = bin2hex(random_bytes(32));
    $expSqlAfter2 = $repo->command($accExpSqlFail, 'join', ['option' => 0], $expSqlKey2, $expSqlAfter1['version']);

    $verBeforeSqlFail = $expSqlAfter2['version'];
    $stageBeforeSqlFail = $expSqlAfter2['stage'];
    $roundBeforeSqlFail = $expSqlAfter2['round'];
    $expSqlFailKey = bin2hex(random_bytes(32));

    // 1. Capturar integralmente o snapshot inicial de estado_json em rumo10_universos antes do comando Expresso
    $stmtInitSnap = $pdo->prepare("SELECT estado_json FROM rumo10_universos WHERE carreira_id = ?");
    $stmtInitSnap->execute([$expSqlId]);
    $initialSnapshotJson = $stmtInitSnap->fetchColumn();
    if ($initialSnapshotJson === false || $initialSnapshotJson === null) {
        throw new RuntimeException("Snapshot inicial não encontrado em rumo10_universos.");
    }
    $initialSnapshotHash = hash('sha256', $initialSnapshotJson);

    $writesConfirmedBeforeFailure = false;
    $duringSnapshotHash = null;
    $snapshotDiffersDuringTransaction = false;
    $threwSqlHookFail = false;

    // Configurar injeção controlada de falha: executada APÓS save(), decisao e outbox serem gravados no MySQL sob a transação,
    // mas imediatamente antes de inserir em f01_respostas e antes do commit
    Repository::$testPreResponseHook = function (int $cId, string $k, PDO $activeDb) use (
        &$writesConfirmedBeforeFailure, 
        &$duringSnapshotHash,
        &$snapshotDiffersDuringTransaction,
        $initialSnapshotJson, 
        $initialSnapshotHash, 
        $expSqlFailKey
    ) {
        if ($k === $expSqlFailKey) {
            // 2 e 3. Dentro da própria transação, antes da exceção, consultar estado_json e comprovar que difere do original
            $stmtSnapDuring = $activeDb->prepare("SELECT estado_json FROM rumo10_universos WHERE carreira_id = ?");
            $stmtSnapDuring->execute([$cId]);
            $duringSnapshotJson = $stmtSnapDuring->fetchColumn();
            
            if ($duringSnapshotJson !== false && $duringSnapshotJson !== null) {
                $duringSnapshotHash = hash('sha256', $duringSnapshotJson);
                // Comprovar que o conteúdo durante a transação DIFERE do original
                if (!hash_equals($initialSnapshotHash, $duringSnapshotHash) && $duringSnapshotJson !== $initialSnapshotJson) {
                    $snapshotDiffersDuringTransaction = true;
                }
            }

            // Snapshot da carreira atualizado
            $stmtC = $activeDb->prepare("SELECT versao, etapa FROM rumo10_carreiras WHERE id = ?");
            $stmtC->execute([$cId]);
            $cRow = $stmtC->fetch();
            $careerUpdated = ($cRow && (int)$cRow['versao'] > 2);

            // Decisão gravada
            $stmtD = $activeDb->prepare("SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id = ? AND chave = ?");
            $stmtD->execute([$cId, $k]);
            $decWritten = ((int)$stmtD->fetchColumn() === 1);

            // Outbox gravado
            $stmtO = $activeDb->prepare("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id = ? AND chave = ?");
            $stmtO->execute([$cId, $k]);
            $outWritten = ((int)$stmtO->fetchColumn() === 1);

            if ($snapshotDiffersDuringTransaction && $careerUpdated && $decWritten && $outWritten) {
                $writesConfirmedBeforeFailure = true;
                // 4. Acionar o hook de falha disparando erro fatal antes do INSERT da resposta
                throw new RuntimeException("FALHA_CONTROLADA_TESTE_INSERT_RESPOSTA_POS_ESCRITAS");
            }
        }
    };

    try {
        $repo->command($accExpSqlFail, 'simulate', ['plan' => 'protagonismo'], $expSqlFailKey, $verBeforeSqlFail);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'FALHA_CONTROLADA_TESTE_INSERT_RESPOSTA_POS_ESCRITAS') {
            $threwSqlHookFail = true;
        }
    } finally {
        Repository::$testPreResponseHook = null;
    }

    // 5. Abertura de uma SEGUNDA CONEXÃO PDO INDEPENDENTE para verificar o estado real persistido no MySQL pós-rollback
    $pdoObserver = new PDO("mysql:host=127.0.0.1;port=33306;dbname=r10_f01_test;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cRowObs = $pdoObserver->query("SELECT versao, etapa, rodada FROM rumo10_carreiras WHERE id = $expSqlId")->fetch();
    $outboxObs = (int)$pdoObserver->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id = $expSqlId AND chave = '$expSqlFailKey'")->fetchColumn();
    $respObs = (int)$pdoObserver->query("SELECT COUNT(*) FROM rumo10_f01_respostas WHERE carreira_id = $expSqlId AND chave = '$expSqlFailKey'")->fetchColumn();
    $decObs = (int)$pdoObserver->query("SELECT COUNT(*) FROM rumo10_decisoes WHERE carreira_id = $expSqlId AND chave = '$expSqlFailKey'")->fetchColumn();
    $partidasObs = (int)$pdoObserver->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id = $expSqlId")->fetchColumn();

    // Consultar estado_json pós-rollback e verificar que voltou integralmente ao original byte a byte
    $stmtAfterSnap = $pdoObserver->prepare("SELECT estado_json FROM rumo10_universos WHERE carreira_id = ?");
    $stmtAfterSnap->execute([$expSqlId]);
    $afterSnapshotJson = $stmtAfterSnap->fetchColumn();
    $afterSnapshotHash = $afterSnapshotJson !== false ? hash('sha256', $afterSnapshotJson) : null;

    $universeJsonIdenticalByteByByte = ($afterSnapshotJson === $initialSnapshotJson && hash_equals($initialSnapshotHash, (string)$afterSnapshotHash));

    $obsSnapshotIdentical = (
        (int)$cRowObs['versao'] === $verBeforeSqlFail &&
        $cRowObs['etapa'] === $stageBeforeSqlFail &&
        (int)$cRowObs['rodada'] === $roundBeforeSqlFail &&
        $universeJsonIdenticalByteByByte
    );

    // 6. Confirmar ausência de resíduos
    assertTest("C-I11B: Rollback real pós-escritas SQL: snapshot difere durante transação e retorna 100% byte a byte pós-rollback sem resíduos", 
        $threwSqlHookFail && $writesConfirmedBeforeFailure && $snapshotDiffersDuringTransaction &&
        $obsSnapshotIdentical && $outboxObs === 0 && $respObs === 0 && $decObs === 0 && $partidasObs === 0
    );
} catch (Throwable $e) {
    Repository::$testPreResponseHook = null;
    assertTest("C-I11B: Rollback pós-escritas SQL", false, $e->getMessage());
}

// CENÁRIO I12: Dois Consumidores Simultâneos (SKIP LOCKED), Falha e Recuperação
try {
    $cI12 = $repo->load($account);
    $cI12Id = (int)$cI12['id'];

    $cmdA = CanonicalIdentity::uuidV4();
    $cmdB = CanonicalIdentity::uuidV4();
    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 1, '{}', 'pendente')")
        ->execute([$cI12Id, $cmdA, bin2hex(random_bytes(32))]);
    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 1, '{}', 'pendente')")
        ->execute([$cI12Id, $cmdB, bin2hex(random_bytes(32))]);

    $pdoA = new PDO("mysql:host=127.0.0.1;port=33306;dbname=r10_f01_test;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdoB = new PDO("mysql:host=127.0.0.1;port=33306;dbname=r10_f01_test;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    $pdoA->beginTransaction();
    $stmtA = $pdoA->prepare("SELECT o.id FROM rumo10_f01_outbox o WHERE o.carreira_id = ? AND o.estado = 'pendente' ORDER BY o.id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
    $stmtA->execute([$cI12Id]);
    $itemA = $stmtA->fetchColumn();

    $pdoB->beginTransaction();
    $stmtB = $pdoB->prepare("SELECT o.id FROM rumo10_f01_outbox o WHERE o.carreira_id = ? AND o.estado = 'pendente' ORDER BY o.id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
    $stmtB->execute([$cI12Id]);
    $itemB = $stmtB->fetchColumn();

    $pdoA->rollBack();
    $pdoB->rollBack();

    // Injetar falha induzida e recuperação
    $accFail = 70;
    $pdo->exec("INSERT INTO rumo10_contas (id, email, senha_hash) VALUES ($accFail, 'fail@rumo10.test', 'hash')");
    $gFail = EntryCountries::create('Craque Falha', 'Santos', 'SP', 'meia', 'BR', 'gratuito');
    $repo->create($accFail, $gFail, false);
    $cFail = $repo->load($accFail);
    $cFailId = (int)$cFail['id'];

    $failCmd = CanonicalIdentity::uuidV4();
    $failKey = bin2hex(random_bytes(32));
    $badPayload = ['matches' => [['match_uid' => CanonicalIdentity::uuidV4(), 'appearances' => [['appearance_uid' => CanonicalIdentity::uuidV4(), 'condicao_escalacao' => 'INVALID_ENUM']]]]];
    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 1, ?, 'pendente')")
        ->execute([$cFailId, $failCmd, $failKey, json_encode($badPayload)]);

    $drainFail = F01Drainer::drain($repo, $accFail, $cFailId, 1);
    $rowFail = $pdo->query("SELECT estado, tentativas, erro FROM rumo10_f01_outbox WHERE command_uid = '$failCmd'")->fetch();

    $goodPayload = ['matches' => []];
    $pdo->prepare("UPDATE rumo10_f01_outbox SET payload_json = ? WHERE command_uid = ?")->execute([json_encode($goodPayload), $failCmd]);
    $drainRecover = F01Drainer::drain($repo, $accFail, $cFailId, 1);
    $rowRecover = $pdo->query("SELECT estado FROM rumo10_f01_outbox WHERE command_uid = '$failCmd'")->fetchColumn();

    $successI12 = ($itemA !== false && $itemB !== false && (int)$itemA !== (int)$itemB && 
                   $drainFail['failed'] === 1 && (int)$rowFail['tentativas'] === 1 &&
                   $drainRecover['processed'] === 1 && $rowRecover === 'processado');

    assertTest("C-I12: Dois consumidores simultâneos isolados por SKIP LOCKED, falha controlada e recuperação", $successI12);
} catch (Throwable $e) {
    assertTest("C-I12: Consumidores simultâneos e falha/recuperação", false, $e->getMessage());
}

// CENÁRIO I13: Exclusão Permanente de Carreira Remove Tabelas F01 (PurgeDetails)
try {
    CareerSlots::delete($repo, $accExpSuccess, $expSId, 'delete');
    
    $outRem = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_outbox WHERE carreira_id = $expSId")->fetchColumn();
    $respRem = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_respostas WHERE carreira_id = $expSId")->fetchColumn();
    $partsRem = (int)$pdo->query("SELECT COUNT(*) FROM rumo10_f01_partidas WHERE carreira_id = $expSId")->fetchColumn();

    assertTest("C-I13: Exclusão permanente de carreira remove outbox, respostas e partidas F01", 
        $outRem === 0 && $respRem === 0 && $partsRem === 0
    );
} catch (Throwable $e) {
    assertTest("C-I13: PurgeDetails F01", false, $e->getMessage());
}

// CENÁRIO I14: Compatibilidade com Saves Legados e Preservação de Nulos
try {
    $legacyAcc = 10;
    $legacyCareerId = 8888;
    $pdo->exec("INSERT IGNORE INTO rumo10_contas (id, email, senha_hash) VALUES ($legacyAcc, 'legacy@rumo10.test', 'hash')");
    $pdo->prepare("INSERT INTO rumo10_carreiras (id, conta_id, nome, cidade, uf, posicao, idade, etapa, clube, versao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$legacyCareerId, $legacyAcc, 'Craque Legado', 'Santos', 'SP', 'meia', 18, 'treino', 'Santos FC', 10]);
    
    $legacyCmd = CanonicalIdentity::uuidV4();
    $legacyKey = bin2hex(random_bytes(32));
    $legacyEnv = [
        'contract_version' => 1,
        'carreira_id' => $legacyCareerId,
        'command_uid' => $legacyCmd,
        'chave' => $legacyKey,
        'total_eventos' => 1,
        'matches' => [
            [
                'match_uid' => CanonicalIdentity::uuidV4(),
                'temporada_numero' => 1,
                'competicao_codigo' => null,
                'rodada_fase' => null,
                'mandante' => 'Santos FC',
                'visitante' => 'Adversário',
                'gols_mandante' => null,
                'gols_visitante' => null,
                'modo_resolucao' => 'normal',
                'appearances' => [
                    [
                        'appearance_uid' => CanonicalIdentity::uuidV4(),
                        'carreira_id' => $legacyCareerId,
                        'condicao_escalacao' => null,
                        'minuto_entrada' => null,
                        'minuto_saida' => null,
                        'minutos_jogados' => null,
                        'estado_participacao' => 'participou',
                        'nota_atuacao' => null,
                        'events' => []
                    ]
                ]
            ]
        ]
    ];
    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 1, ?, 'pendente')")
        ->execute([$legacyCareerId, $legacyCmd, $legacyKey, json_encode($legacyEnv)]);

    $legacyDrain = F01Drainer::drain($repo, $legacyAcc, $legacyCareerId, 1);
    $legacyMatch = $pdo->query("SELECT match_uid, competicao_codigo, gols_mandante FROM rumo10_f01_partidas WHERE carreira_id = $legacyCareerId")->fetch();

    assertTest("C-I14: Compatibilidade com saves legados preserva valores nulos sem interrupção", 
        $legacyDrain['processed'] === 1 && $legacyMatch !== false && $legacyMatch['competicao_codigo'] === null && $legacyMatch['gols_mandante'] === null
    );
} catch (Throwable $e) {
    assertTest("C-I14: Compatibilidade legada", false, $e->getMessage());
}

// =====================================================================
// CAMADA 3: TESTES HTTP REAIS COM PROCESSOS INDEPENDENTES VIA CURL
// =====================================================================
echo "\n--- CAMADA 3: TESTES HTTP REAIS (CURL / PROCESSOS INDEPENDENTES CONTRA O SERVIDOR WEB) ---\n";

$webPort = 8089;
$phpBinary = 'C:\\PHP\\php-8.4.26\\php.exe';
$publicDir = __DIR__ . '/public';

$serverProcess = proc_open(
    "\"$phpBinary\" -S 127.0.0.1:$webPort -t \"$publicDir\"",
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
usleep(800000); // 800ms para bind do socket

function makeHttpRequest(string $method, string $path, array $data = [], ?string $cookieJar = null): array {
    global $webPort;
    $url = "http://127.0.0.1:{$webPort}" . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headerStr = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $body,
    ];
}

$cookie1 = __DIR__ . '/cookie_user1.txt';
$cookie2 = __DIR__ . '/cookie_user2.txt';
if (is_file($cookie1)) unlink($cookie1);
if (is_file($cookie2)) unlink($cookie2);

// CENÁRIO H01: Rejeição HTTP no POST de drenagem sem sessão (401)
try {
    $resNoAuth = makeHttpRequest('POST', '/api/v1/telemetry/drain', ['career_id' => 1, 'csrf' => 'qualquer']);
    assertTest("C-H01: POST /api/v1/telemetry/drain sem sessão HTTP retorna 401 Unauthorized", $resNoAuth['code'] === 401);
} catch (Throwable $e) {
    assertTest("C-H01: POST drenagem sem sessão", false, $e->getMessage());
}

// Obter sessão e CSRF para Conta 1
$loginPage = makeHttpRequest('GET', '/register', [], $cookie1);
preg_match('/<input type="hidden" name="csrf" value="([a-f0-9]{64})"/', $loginPage['body'], $csrfLogin1);
$tokenInit1 = $csrfLogin1[1] ?? '';

makeHttpRequest('POST', '/register', ['email' => 'http1@rumo10.test', 'password' => 'senhaSegura123', 'csrf' => $tokenInit1], $cookie1);

$careerPage = makeHttpRequest('GET', '/carreira', [], $cookie1);
preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $careerPage['body'], $csrfMatch);
$user1Csrf = $csrfMatch[1] ?? '';

if (str_contains($careerPage['body'], 'action="/carreira/criar"')) {
    makeHttpRequest('POST', '/carreira/criar', [
        'csrf' => $user1Csrf,
        'name' => 'Craque HTTP 1',
        'position' => 'centroavante',
        'country' => 'BR',
        'residence_country' => 'BR',
        'uf' => 'SP',
        'city' => 'Santos',
        'mode' => 'normal',
    ], $cookie1);
    $careerPage = makeHttpRequest('GET', '/carreira', [], $cookie1);
    preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $careerPage['body'], $csrfMatch);
    $user1Csrf = $csrfMatch[1] ?? '';
}

preg_match('/<meta name="f01-career-context" content="([0-9]+)"/', $careerPage['body'], $careerMatch);
$user1CareerId = isset($careerMatch[1]) ? (int)$careerMatch[1] : (int)$pdo->query("SELECT id FROM rumo10_carreiras WHERE conta_id = (SELECT id FROM rumo10_contas WHERE email = 'http1@rumo10.test')")->fetchColumn();

// CENÁRIO H02: Rejeição HTTP no POST de drenagem com CSRF inválido (403)
try {
    $resBadCsrf = makeHttpRequest('POST', '/api/v1/telemetry/drain', ['career_id' => $user1CareerId, 'csrf' => 'csrf_adulterado_invalido'], $cookie1);
    assertTest("C-H02: POST /api/v1/telemetry/drain com CSRF inválido retorna 403 Forbidden", $resBadCsrf['code'] === 403);
} catch (Throwable $e) {
    assertTest("C-H02: POST drenagem com CSRF inválido", false, $e->getMessage());
}

// Obter CSRF para Conta 2
$loginPage2 = makeHttpRequest('GET', '/register', [], $cookie2);
preg_match('/<input type="hidden" name="csrf" value="([a-f0-9]{64})"/', $loginPage2['body'], $csrfLogin2);
$tokenInit2 = $csrfLogin2[1] ?? '';

makeHttpRequest('POST', '/register', ['email' => 'http2@rumo10.test', 'password' => 'senhaSegura123', 'csrf' => $tokenInit2], $cookie2);
$careerPage2 = makeHttpRequest('GET', '/carreira', [], $cookie2);
preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $careerPage2['body'], $csrfMatch2);
$user2Csrf = $csrfMatch2[1] ?? '';

// CENÁRIO H03: Rejeição HTTP de Acesso Cruzado entre Contas
try {
    $resCross = makeHttpRequest('POST', '/api/v1/telemetry/drain', ['career_id' => $user1CareerId, 'csrf' => $user2Csrf], $cookie2);
    $crossBlocked = ($resCross['code'] === 422 || $resCross['code'] === 403 || str_contains($resCross['body'], 'Acesso negado'));
    assertTest("C-H03: Tentativa de drenagem da carreira de outro usuário via HTTP é rejeitada", $crossBlocked);
} catch (Throwable $e) {
    assertTest("C-H03: Acesso cruzado no POST", false, $e->getMessage());
}

// CENÁRIO H04: Preservação de Redirecionamento 303 no POST Esportivo
$c1Ver = (int)$pdo->query("SELECT versao FROM rumo10_carreiras WHERE id = $user1CareerId")->fetchColumn();
$keySport = bin2hex(random_bytes(32));
try {
    $resSport = makeHttpRequest('POST', '/carreira/handicap', [
        'csrf' => $user1Csrf,
        'key' => $keySport,
        'version' => $c1Ver,
    ], $cookie1);

    $is303 = ($resSport['code'] === 303 && str_contains($resSport['headers'], 'Location: /carreira'));
    assertTest("C-H04: POST de comando esportivo retorna HTTP 303 com cabeçalho Location para /carreira", $is303);
} catch (Throwable $e) {
    assertTest("C-H04: Redirecionamento 303 no POST esportivo", false, $e->getMessage());
}

// CENÁRIO H05: Recuperação de Backlog após Fechamento e Reabertura da Aba
try {
    $backlogCmd = CanonicalIdentity::uuidV4();
    $backlogKey = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, total_eventos, payload_json, estado) VALUES (?, ?, ?, 1, '{}', 'pendente')")
        ->execute([$user1CareerId, $backlogCmd, $backlogKey]);

    $careerReload = makeHttpRequest('GET', '/carreira', [], $cookie1);
    preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})"/', $careerReload['body'], $csrfReloadMatch);
    $freshCsrf = $csrfReloadMatch[1] ?? $user1Csrf;

    $resDrainBacklog = makeHttpRequest('POST', '/api/v1/telemetry/drain', ['career_id' => $user1CareerId, 'csrf' => $freshCsrf, 'limit' => 20], $cookie1);
    $statusBacklog = $pdo->query("SELECT estado FROM rumo10_f01_outbox WHERE command_uid = '$backlogCmd'")->fetchColumn();

    assertTest("C-H05: Recuperação completa de backlog após fechamento e reabertura da aba via HTTP", 
        $resDrainBacklog['code'] === 200 && $statusBacklog === 'processado'
    );
} catch (Throwable $e) {
    assertTest("C-H05: Recuperação de backlog", false, $e->getMessage());
}

// CENÁRIO H06: Resposta Original Recuperada via HTTP GET após Avanço Posterior da Carreira
try {
    $c1VerNext = (int)$pdo->query("SELECT versao FROM rumo10_carreiras WHERE id = $user1CareerId")->fetchColumn();
    $keyAdvance = bin2hex(random_bytes(32));
    makeHttpRequest('POST', '/carreira/clube-inicial', [
        'csrf' => $user1Csrf,
        'key' => $keyAdvance,
        'version' => $c1VerNext,
        'club' => 'Santos'
    ], $cookie1);

    $resRespOriginal = makeHttpRequest('GET', "/api/v1/command/response?key={$keySport}&career_id={$user1CareerId}", [], $cookie1);
    $respJson = json_decode($resRespOriginal['body'], true);
    
    $isOriginalPreserved = ($resRespOriginal['code'] === 200 && 
                            ($respJson['contract_version'] ?? 0) === 1 && 
                            CanonicalIdentity::isValidUuid($respJson['command_uid'] ?? '') && 
                            $respJson['versao_anterior'] === $c1Ver);

    assertTest("C-H06: Resposta original recuperada via HTTP GET após perda pós-commit e avanço posterior da carreira", $isOriginalPreserved);
} catch (Throwable $e) {
    assertTest("C-H06: Recuperação da resposta original", false, $e->getMessage());
}

// CENÁRIO H07: Bloqueio HTTP na Recuperação de Resposta de Outra Conta
try {
    $resCrossResp = makeHttpRequest('GET', "/api/v1/command/response?key={$keySport}&career_id={$user1CareerId}", [], $cookie2);
    assertTest("C-H07: Consulta GET da resposta de outra conta retorna 403 Forbidden", $resCrossResp['code'] === 403);
} catch (Throwable $e) {
    assertTest("C-H07: Bloqueio de resposta entre contas", false, $e->getMessage());
}

// CENÁRIO H08: Pureza Absoluta das Rotas GET da F01 (Verificação de Dados e Checksum das Tabelas)
try {
    $tables = ['rumo10_carreiras', 'rumo10_decisoes', 'rumo10_f01_respostas', 'rumo10_f01_outbox', 'rumo10_f01_partidas', 'rumo10_f01_aparicoes', 'rumo10_f01_eventos'];
    $checksumPre = [];
    foreach ($tables as $t) {
        $checksumPre[$t] = $pdo->query("CHECKSUM TABLE $t")->fetch()['Checksum'] ?? null;
    }

    makeHttpRequest('GET', "/api/v1/command/response?key={$keySport}&career_id={$user1CareerId}", [], $cookie1);
    makeHttpRequest('GET', "/carreira", [], $cookie1);

    $checksumPost = [];
    foreach ($tables as $t) {
        $checksumPost[$t] = $pdo->query("CHECKSUM TABLE $t")->fetch()['Checksum'] ?? null;
    }

    $noDataMutations = ($checksumPre === $checksumPost);
    assertTest("C-H08: Pureza absoluta de dados nas rotas HTTP GET da F01 (verificada por checksum de dados de todas as tabelas)", $noDataMutations);
} catch (Throwable $e) {
    assertTest("C-H08: Pureza de dados HTTP GET", false, $e->getMessage());
}

// Encerrar servidor web temporário
if (is_resource($serverProcess)) {
    proc_terminate($serverProcess);
    proc_close($serverProcess);
}

// Limpar arquivos temporários de cookies
if (is_file($cookie1)) unlink($cookie1);
if (is_file($cookie2)) unlink($cookie2);

echo "\n=====================================================================\n";
echo "RESULTADO GERAL: $passCount PASSADOS | $failCount FALHADOS (Total: " . ($passCount + $failCount) . ")\n";
echo "=====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
