<?php
declare(strict_types=1);

putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
putenv('RUMO10_ACHIEVEMENTS=0');
putenv('RUMO10_WORLD_CALENDAR=1');
putenv('RUMO10_WORLD_FIXTURES=1');
putenv('RUMO10_NEUTRAL_SIMULATION=1');
putenv('RUMO10_ADMIN_TEST_DESIGNATIONS=1');

require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/acre-fixtures.php';

$checks = 0;
function toOk(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function toReject(callable $fn): void {
    global $checks;
    try {
        $fn();
    } catch (RuntimeException|InvalidArgumentException|TypeError $e) {
        $checks++;
        return;
    }
    throw new LogicException('Ação inválida aceita indevidamente.');
}

$start = hrtime(true);

// 1. Definição e validação do contrato revisado de Tocantins (v2) com descenso fundamentado no Art. 13
$c = StateFederationsTrial::reference()['definitions']['br-to-a1'];
toOk($c['edition'] === 2026, 'Edição 2026 de TO');
toOk($c['rules']['relegation'] === 2, 'Descenso declarado como 2 nas regras (Art. 13 do REC FTF)');
toOk($c['state_engine']['closure']['schema'] === 'certified-outcomes-v1', 'Esquema certified-outcomes-v1 em TO');
toOk($c['state_engine']['closure']['title_phase'] === 'final', 'Fase de título é final');
toOk($c['state_engine']['closure']['certified_runner_up'] === true, 'Vice-campeão comprovado declarado');
toOk($c['state_engine']['closure']['relegation'] === 2, 'Descenso declarado como 2 no closure (Art. 13 do REC FTF)');
toOk($c['state_engine']['closure']['relegation_phase'] === 'liga', 'Fase de descenso declarada como liga');
toOk(isset($c['state_engine']['closure']['pending']), 'Pendência documental ativa');
toOk($c['state_engine']['closure']['pending']['reason'] === 'to_final_ranking_campaign_scope_requires_clarification', 'Razão documental da pendência preservada');

ProgrammedStateCompetition::validate($c, $c['teams']);

// 1.1 Rejeições atômicas na especificação de closure e descenso para TO
$badRelegBasis = $c;
$badRelegBasis['state_engine']['closure']['relegation_basis'] = 'final_ranking';
toReject(fn() => ProgrammedStateCompetition::validate($badRelegBasis, $badRelegBasis['teams']));

$badRelegKnockout = $c;
$badRelegKnockout['state_engine']['closure']['relegation_phase'] = 'semifinal';
toReject(fn() => ProgrammedStateCompetition::validate($badRelegKnockout, $badRelegKnockout['teams']));

$badRelegMissing = $c;
$badRelegMissing['state_engine']['closure']['relegation_phase'] = 'fase_inexistente';
toReject(fn() => ProgrammedStateCompetition::validate($badRelegMissing, $badRelegMissing['teams']));

$badRelegNegative = $c;
$badRelegNegative['state_engine']['closure']['relegation'] = -1;
toReject(fn() => ProgrammedStateCompetition::validate($badRelegNegative, $badRelegNegative['teams']));

$badRelegOverflow = $c;
$badRelegOverflow['state_engine']['closure']['relegation'] = 99;
toReject(fn() => ProgrammedStateCompetition::validate($badRelegOverflow, $badRelegOverflow['teams']));

$badNoSchema = $c;
unset($badNoSchema['state_engine']['closure']['schema']);
toReject(fn() => ProgrammedStateCompetition::validate($badNoSchema, $badNoSchema['teams']));

$badInvalidSchema = $c;
$badInvalidSchema['state_engine']['closure']['schema'] = 'invalid-schema-v99';
toReject(fn() => ProgrammedStateCompetition::validate($badInvalidSchema, $badInvalidSchema['teams']));

$badClosure = $c;
$badClosure['state_engine']['closure']['title_phase'] = 'fase_inexistente';
toReject(fn() => ProgrammedStateCompetition::validate($badClosure, $badClosure['teams']));

$badVice = $c;
unset($badVice['state_engine']['closure']['title_phase']);
toReject(fn() => ProgrammedStateCompetition::validate($badVice, $badVice['teams']));

$badViceBool = $c;
$badViceBool['state_engine']['closure']['certified_runner_up'] = false;
toReject(fn() => ProgrammedStateCompetition::validate($badViceBool, $badViceBool['teams']));

// 2. Execução determinística dos 34 jogos com pontuação simulada controlada
Competition::referenceScope([$c['id'] => $c], function() use ($c) {
    $t = Competition::tournament($c['id']);
    $t['lots'] = array_flip($c['teams']);

    // Fase inicial: 28 jogos (7 rodadas de 4 partidas)
    foreach ($t['matches'] as &$m) {
        $m['status'] = 'finalizada';
        $m['hg'] = 1;
        $m['ag'] = 0;
    }
    unset($m);
    ProgrammedStateCompetition::next($t);
    toOk($t['state_stage'] === 1, 'Avançou para semifinais');
    toOk(count($t['matches']) === 32, '28 liga + 4 semifinal = 32 partidas');

    // Semifinais: 4 partidas (2 confrontos de ida e volta)
    foreach ($t['matches'] as &$m) {
        if ($m['status'] !== 'finalizada') {
            $m['status'] = 'finalizada';
            $m['hg'] = 2;
            $m['ag'] = 0;
        }
    }
    unset($m);
    ProgrammedStateCompetition::next($t);
    toOk($t['state_stage'] === 2, 'Avançou para final');
    toOk(count($t['matches']) === 34, '28 liga + 4 semifinal + 2 final = 34 partidas');

    // Final: 2 partidas (ida e volta)
    foreach ($t['matches'] as &$m) {
        if ($m['status'] !== 'finalizada') {
            $m['status'] = 'finalizada';
            $m['hg'] = 2;
            $m['ag'] = 1;
        }
    }
    unset($m);

    // Encerramento desacoplado com certified-outcomes-v1
    ProgrammedStateCompetition::next($t);

    // 2.1 Preservação dos resultados esportivos
    toOk(count($t['matches']) === 34, 'TO executa exatamente 34 partidas');
    toOk(!array_filter($t['matches'], fn($m) => $m['status'] !== 'finalizada'), 'Todas as 34 partidas finalizadas');

    // 2.2 Campeão e vice reconhecidos pela final
    $champion = $t['state_phase_winners']['final'][0];
    $runnerUp = $t['state_phase_losers']['final'][0];
    toOk($t['champion'] !== null && $t['champion'] === $champion, 'Campeão certificado da final');
    toOk($t['runner_up'] !== null && $t['runner_up'] === $runnerUp, 'Vice certificado da final');
    toOk($champion !== $runnerUp, 'Campeão e vice são clubes distintos');
    toOk(in_array($champion, $c['teams'], true), 'Campeão pertence aos participantes de TO');
    toOk(in_array($runnerUp, $c['teams'], true), 'Vice pertence aos participantes de TO');

    // 2.3 Descenso de 2 clubes certificado diretamente pela classificação da 1ª fase (Art. 13 do REC FTF)
    $expectedRelegated = array_slice($t['initial_ranking'], -2);
    toOk(count($t['relegated']) === 2, 'Exatamente 2 clubes rebaixados certificados');
    toOk($t['relegated'] === $expectedRelegated, 'Descenso corresponde aos 7º e 8º da fase inicial (liga)');
    toOk(array_is_list($t['relegated']), 'Relegated é lista estrita');
    toOk(!in_array($champion, $t['relegated'], true), 'Campeão não está entre os rebaixados');
    toOk(!in_array($runnerUp, $t['relegated'], true), 'Vice não está entre os rebaixados');

    // 2.4 Ranking de 3º e 4º lugares bloqueado (sem preenchimento artificial)
    toOk(empty($t['final_ranking']), 'Ranking final NÃO é preenchido artificialmente');

    // 2.5 Competição permanece não homologada
    toOk($t['done'] === false, 'Competição NÃO é marcada como done/homologada');
    toOk(isset($t['administrative_pending']), 'Pendência administrativa ativa');
    toOk($t['administrative_pending']['state'] === 'awaiting_documented_rule', 'Estado awaiting_documented_rule');
    toOk($t['administrative_pending']['reason'] === 'to_final_ranking_campaign_scope_requires_clarification', 'Razão da pendência confere');

    // 2.6 Recibo de certificação parcial
    toOk(isset($t['partial_certification']), 'Recibo partial_certification presente');
    $part = $t['partial_certification'];
    toOk(is_int($part['schema']) && $part['schema'] === 1, 'Schema estrito inteiro 1');
    toOk($part['version'] === 'certified-outcomes-v1', 'Versão certified-outcomes-v1');
    toOk($part['competition'] === 'br-to-a1', 'Competição br-to-a1');
    toOk($part['edition'] === 2026, 'Edição 2026');
    toOk($part['certified_champion'] === $champion, 'Campeão certificado confere');
    toOk($part['certified_runner_up'] === $runnerUp, 'Vice certificado confere');
    toOk($part['certified_relegated'] === $expectedRelegated, 'Descenso certificado confere (2 clubes)');
    toOk(is_array($part['certified_relegated']) && array_is_list($part['certified_relegated']), 'certified_relegated é lista estrita');
    toOk(count($part['certified_relegated']) === 2, 'Exatamente 2 rebaixados no recibo');
    toOk($part['pending_ranking'] === true, 'pending_ranking é true');
    toOk($part['pending_national_admission'] === true, 'pending_national_admission é true');
    toOk($part['homologated'] === false, 'homologated é false');

    // Vínculo com contrato e resultados
    $contractDigest = StateResultProof::digest($c['state_engine']);
    toOk($part['contract_digest'] === $contractDigest, 'Recibo vinculado ao contract_digest');
    toOk(isset($part['results_digest']), 'Recibo vinculado ao results_digest');

    // 2.7 Prova determinística
    toOk(ProgrammedStateCompetition::verify($c, $t), 'Reconstrução determinística da prova com certificação parcial em TO');
    toOk(!StateResultProof::verify($c, $t), 'StateResultProof recusa competição não finalizada (done=false)');

    // 3. Testes negativos e resistência estrita a adulterações
    // 3.1 schema = "1" (string em vez de int)
    $tamperSchemaType = $t;
    $tamperSchemaType['partial_certification']['schema'] = "1";
    toOk(!ProgrammedStateCompetition::verify($c, $tamperSchemaType), 'Recibo com schema="1" (string) rejeitado');

    // 3.2 schema = 2
    $tamperSchemaNum = $t;
    $tamperSchemaNum['partial_certification']['schema'] = 2;
    toOk(!ProgrammedStateCompetition::verify($c, $tamperSchemaNum), 'Recibo com schema=2 rejeitado');

    // 3.3 version adulterada
    $tamperVersion = $t;
    $tamperVersion['partial_certification']['version'] = 'certified-outcomes-v2';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperVersion), 'Recibo com version adulterada rejeitado');

    // 3.4 campeão adulterado no estado
    $tamperChamp = $t;
    $tamperChamp['champion'] = 'Palmas-TO';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperChamp), 'Campeão adulterado no estado rejeitado');

    // 3.5 campeão adulterado no recibo
    $tamperChampReceipt = $t;
    $tamperChampReceipt['partial_certification']['certified_champion'] = 'Palmas-TO';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperChampReceipt), 'Campeão adulterado no recibo rejeitado');

    // 3.6 vice adulterado no estado
    $tamperRunner = $t;
    $tamperRunner['runner_up'] = 'Palmas-TO';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperRunner), 'Vice adulterado no estado rejeitado');

    // 3.7 vice adulterado no recibo
    $tamperRunnerReceipt = $t;
    $tamperRunnerReceipt['partial_certification']['certified_runner_up'] = 'Palmas-TO';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperRunnerReceipt), 'Vice adulterado no recibo rejeitado');

    // 3.8 descenso adulterado no estado (1 rebaixado em vez de 2)
    $tamperReleg = $t;
    $tamperReleg['relegated'] = [$expectedRelegated[0]];
    toOk(!ProgrammedStateCompetition::verify($c, $tamperReleg), 'Descenso adulterado para 1 clube no estado rejeitado');

    // 3.9 descenso adulterado no recibo (1 rebaixado em vez de 2)
    $tamperRelegReceipt = $t;
    $tamperRelegReceipt['partial_certification']['certified_relegated'] = [$expectedRelegated[0]];
    toOk(!ProgrammedStateCompetition::verify($c, $tamperRelegReceipt), 'Descenso adulterado para 1 clube no recibo rejeitado');

    // 3.10 lista de rebaixados invertida com preservação de índices numéricos (array_reverse com preserve_keys)
    $badRelegOrder = $t;
    $badRelegOrder['partial_certification']['certified_relegated'] = array_reverse($expectedRelegated, true);
    toOk(!ProgrammedStateCompetition::verify($c, $badRelegOrder), 'Lista invertida com preservação de índices rejeitada');

    // 3.11 lista de rebaixados com índice inicial diferente de zero
    $badNonZero = $t;
    $badNonZero['partial_certification']['certified_relegated'] = [1 => $expectedRelegated[0], 2 => $expectedRelegated[1]];
    toOk(!ProgrammedStateCompetition::verify($c, $badNonZero), 'Lista com índice inicial 1 rejeitada');

    // 3.12 lista de rebaixados com lacuna de índice
    $badGap = $t;
    $badGap['partial_certification']['certified_relegated'] = [0 => $expectedRelegated[0], 2 => $expectedRelegated[1]];
    toOk(!ProgrammedStateCompetition::verify($c, $badGap), 'Lista com lacuna de índice rejeitada');

    // 3.13 certified_relegated não-lista (objeto associativo)
    $tamperRelegAssoc = $t;
    $tamperRelegAssoc['partial_certification']['certified_relegated'] = ['primeiro' => $expectedRelegated[0], 'segundo' => $expectedRelegated[1]];
    toOk(!ProgrammedStateCompetition::verify($c, $tamperRelegAssoc), 'certified_relegated associativo rejeitado');

    // 3.14 rebaixados duplicados no recibo
    $badDup = $t;
    $badDup['partial_certification']['certified_relegated'] = [$expectedRelegated[0], $expectedRelegated[0]];
    toOk(!ProgrammedStateCompetition::verify($c, $badDup), 'Rebaixados duplicados no recibo rejeitado');

    // 3.15 inclusão do campeão entre os rebaixados
    $badChampInReleg = $t;
    $badChampInReleg['partial_certification']['certified_relegated'] = [$expectedRelegated[0], $champion];
    toOk(!ProgrammedStateCompetition::verify($c, $badChampInReleg), 'Campeão inserido entre rebaixados rejeitado');

    // 3.16 inclusão do vice entre os rebaixados
    $badRunnerInReleg = $t;
    $badRunnerInReleg['partial_certification']['certified_relegated'] = [$expectedRelegated[0], $runnerUp];
    toOk(!ProgrammedStateCompetition::verify($c, $badRunnerInReleg), 'Vice inserido entre rebaixados rejeitado');

    // 3.17 contract_digest adulterado
    $tamperContractDigest = $t;
    $tamperContractDigest['partial_certification']['contract_digest'] = 'tampered_contract_digest';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperContractDigest), 'contract_digest adulterado rejeitado');

    // 3.18 results_digest adulterado
    $tamperResultsDigest = $t;
    $tamperResultsDigest['partial_certification']['results_digest'] = 'tampered_results_digest';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperResultsDigest), 'results_digest adulterado rejeitado');

    // 3.19 digest canônico inconsistente com evidence
    $tamperEvidence = $t;
    $tamperEvidence['administrative_pending']['evidence']['partial_certification'] = 'inconsistent_hash';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperEvidence), 'Digest inconsistente na evidence rejeitado');

    // 3.20 key inconsistente
    $tamperKey = $t;
    $tamperKey['administrative_pending']['key'] = 'inconsistent_key';
    toOk(!ProgrammedStateCompetition::verify($c, $tamperKey), 'Key inconsistente rejeitada');

    // 3.21 homologated=true indevido
    $tamperHomologated = $t;
    $tamperHomologated['partial_certification']['homologated'] = true;
    toOk(!ProgrammedStateCompetition::verify($c, $tamperHomologated), 'homologated=true no recibo rejeitado');

    // 3.22 done=true indevido
    $tamperDone = $t;
    $tamperDone['done'] = true;
    toOk(!ProgrammedStateCompetition::verify($c, $tamperDone), 'done=true no torneio rejeitado');
});

// 4. Validação em modos de jogo (Game runtime): Normal, Rápido e Expresso
foreach (['normal', 'rapido', 'expresso'] as $i => $mode) {
    $g = StateFederationsTrial::isolated(879101 + $i, 'br-to-a1', $mode);
    $steps = 0;
    while ($g['stage'] !== 'world_pending') {
        if (++$steps > 1600) {
            throw new RuntimeException("Loop excessivo no modo {$mode}");
        }
        [$action, $input] = acAction($g);
        $g = Game::command($g, $action, $input);
        $g = json_decode(json_encode($g), true);
    }

    $t = $g['competitions']['br-to-a1'];
    toOk(count($t['matches']) === 34, "{$mode}: exatamente 34 partidas disputadas");
    toOk($t['done'] === false, "{$mode}: done é false");
    toOk($t['champion'] !== null, "{$mode}: campeão certificado reconhecido");
    toOk($t['runner_up'] !== null, "{$mode}: vice certificado reconhecido");
    toOk(count($t['relegated']) === 2, "{$mode}: exatamente 2 clubes rebaixados certificados");
    toOk(is_array($t['relegated']) && array_is_list($t['relegated']), "{$mode}: rebaixados formam lista sequencial");
    toOk(isset($t['partial_certification']), "{$mode}: partial_certification presente");
    toOk(count($t['partial_certification']['certified_relegated']) === 2, "{$mode}: certified_relegated tem 2 clubes");

    // Nenhuma inscrição ou qualificação nacional gerada
    toOk(empty($g['state_qualifications']), "{$mode}: nenhuma qualificação estadual gravada");
    toOk(empty($g['future_d_admissions']), "{$mode}: nenhuma admissão futura na Série D gerada");

    // Nenhuma premiação ou conclusão concedida
    toOk(empty($g['competition_results']), "{$mode}: competition_results vazio (sem concessão de premiação)");
    toOk(empty($g['world_completed']), "{$mode}: world_completed vazio");

    // Mercado permanece bloqueado
    try {
        Game::command($g, 'offers');
        toOk(false, 'Mercado não deve ser aceito');
    } catch (InvalidArgumentException $e) {
        toOk(true, "{$mode}: mercado bloqueado");
    }

    // Renovação de temporada bloqueada
    try {
        WorldSeason::renew($g);
        toOk(false, 'Renovação nacional não deve ser permitida');
    } catch (RuntimeException $e) {
        toOk(str_contains($e->getMessage(), 'Temporada anterior ainda não encerrada'), "{$mode}: renovação bloqueada");
    }
}

// 5. Compatibilidade histórica estrita com TO v1
$v1 = json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-program-2026-v1.json'), true);
$oldTO = $v1['definitions']['br-to-a1'];
toOk($oldTO['state_engine']['version'] === 'configured-state-v9-program', 'TO v1 é versão v9 program');
toOk(!isset($oldTO['state_engine']['closure']['schema']), 'TO v1 não possui schema de certificação parcial');
toOk(!ProgrammedStateCompetition::authorizesPartialCertification($oldTO), 'TO v1 NÃO autoriza certificação parcial');

ProgrammedStateCompetition::validate($oldTO, $oldTO['teams']);

// Injeção de certificação parcial em contrato v1 DEVE ser rejeitada pelo validate
$badTOv1 = $oldTO;
$badTOv1['state_engine']['closure']['schema'] = 'certified-outcomes-v1';
$badTOv1['state_engine']['closure']['title_phase'] = 'final';
$badTOv1['state_engine']['closure']['certified_runner_up'] = true;
$badTOv1['state_engine']['closure']['relegation'] = 2;
toReject(fn() => ProgrammedStateCompetition::validate($badTOv1, $badTOv1['teams']));

// Execução de TO v1 preserva comportamento legado de 28 partidas
Competition::referenceScope([$oldTO['id'] => $oldTO], function() use ($oldTO) {
    $t = ProgrammedStateCompetition::create($oldTO, $oldTO['teams']);
    foreach ($t['matches'] as &$m) {
        $m['status'] = 'finalizada';
        $m['hg'] = 0;
        $m['ag'] = 0;
    }
    unset($m);
    ProgrammedStateCompetition::next($t);
    toOk(count($t['matches']) === 28, 'TO v1 continua gerando exatamente 28 partidas');
    toOk($t['champion'] === null, 'TO v1 preserva champion=null');
    toOk(!array_key_exists('runner_up', $t), 'TO v1 histórico NÃO possui campo runner_up');
    toOk($t['relegated'] === [], 'TO v1 preserva relegated=[]');
    toOk(!array_key_exists('partial_certification', $t), 'TO v1 histórico NÃO possui campo partial_certification');
    toOk($t['done'] === false, 'TO v1 preserva done=false');
    toOk(ProgrammedStateCompetition::verify($oldTO, $t), 'TO v1 verifica com sucesso');

    // Injeções indevidas em TO v1 devem falhar na verificação
    $tamperTO = $t;
    $tamperTO['runner_up'] = null;
    toOk(!ProgrammedStateCompetition::verify($oldTO, $tamperTO), 'Injeção de runner_up em TO v1 rejeitada na verificação');

    $tamperTOPart = $t;
    $tamperTOPart['partial_certification'] = null;
    toOk(!ProgrammedStateCompetition::verify($oldTO, $tamperTOPart), 'Injeção de partial_certification em TO v1 rejeitada na verificação');
});

// 6. Teste de persistência e hidratação MySQL com escopo correto, rollback e tratamento estrito
$pdoAvailable = false;
$pdoStatus = 'omitido';
$pdoExecutionResult = null;

// Teste de conectividade com tratamento restrito a PDOException (sem engolir exceções de teste)
if (config('APP_ENV') !== 'production' && in_array(config('DB_HOST'), ['localhost', '127.0.0.1', '::1'], true)) {
    try {
        $pdo = new PDO(
            'mysql:host='.config('DB_HOST').';port='.config('DB_PORT', '3306').';dbname='.config('DB_DATABASE', 'rumohub').';charset=utf8mb4',
            config('DB_USERNAME'),
            config('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdoAvailable = true;
    } catch (PDOException $e) {
        $pdoStatus = 'omitido: MySQL não disponível no ambiente ('. $e->getMessage() . ')';
    }
} else {
    $pdoStatus = 'omitido: ambiente não local ou de produção';
}

if ($pdoAvailable) {
    $repo = new Repository($pdo);
    $testCareerId = 879901;

    $pdo->beginTransaction();
    try {
        // Garantir que a chave está livre
        $existing = $repo->query('SELECT id FROM rumo10_carreiras WHERE id=?', [$testCareerId])->fetch();
        if ($existing) {
            throw new RuntimeException("Chave {$testCareerId} ocupada em teste descartável");
        }

        // Criar conta isolada de teste
        $email = 'to-partial-'.bin2hex(random_bytes(6)).'@example.invalid';
        $repo->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)', [$email, password_hash('test', PASSWORD_DEFAULT)]);
        $accountId = (int)$pdo->lastInsertId();

        $g = StateFederationsTrial::isolated($testCareerId, 'br-to-a1', 'expresso');
        while ($g['stage'] !== 'world_pending') {
            [$action, $input] = acAction($g);
            $g = Game::command($g, $action, $input);
            $g = json_decode(json_encode($g), true);
        }

        $snapshot = json_encode($g, JSON_THROW_ON_ERROR);
        $repo->query(
            'INSERT INTO rumo10_carreiras(id,conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?,?)',
            [$testCareerId, $accountId, 'Atleta TO', 'Palmas', 'TO', 'meia', 18, 'resumo', $g['club']]
        );
        $repo->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?)', [$testCareerId, $snapshot]);

        // Recarregar via repositório (hidratação MySQL com ordenação de chaves)
        $loaded = $repo->load($accountId);
        $tLoaded = $loaded['competitions']['br-to-a1'];

        // Assertivas estritas: NENHUMA exceção destas assertivas pode ser silenciada
        toOk(count($tLoaded['matches']) === 34, 'MySQL: 34 partidas recuperadas');
        toOk($tLoaded['champion'] !== null, 'MySQL: campeão recuperado');
        toOk($tLoaded['runner_up'] !== null, 'MySQL: vice recuperado');
        toOk(count($tLoaded['relegated']) === 2, 'MySQL: exatamente 2 clubes rebaixados recuperados');
        toOk(is_array($tLoaded['relegated']) && array_is_list($tLoaded['relegated']), 'MySQL: rebaixados formam lista sequencial');
        toOk(is_array($tLoaded['partial_certification']), 'MySQL: recibo recuperado');

        // Verificação no contexto correto de WorldSeason::scope() (Bloqueante 2 da auditoria Codex corrigido)
        $verifiedInScope = WorldSeason::scope(
            $loaded,
            fn() => ProgrammedStateCompetition::verify($c, $tLoaded)
        );
        toOk($verifiedInScope === true, 'MySQL: prova de certificação parcial sobrevive à hidratação sob WorldSeason::scope()');

        // Teste negativo obrigatório: comprovar que falha de assertiva PDO NUNCA é mascarada
        $assertionPropagated = false;
        try {
            $tamperedTournament = $tLoaded;
            $tamperedTournament['champion'] = 'Clube Fake-TO';
            $tamperCheck = WorldSeason::scope(
                $loaded,
                fn() => ProgrammedStateCompetition::verify($c, $tamperedTournament)
            );
            toOk($tamperCheck, 'Assertiva deliberadamente falsa');
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Assertiva deliberadamente falsa')) {
                $assertionPropagated = true;
            }
        }
        toOk($assertionPropagated === true, 'Negativo PDO: falha de assertiva é propagada como exceção e NÃO é mascarada pelo harness');

        $pdoStatus = 'executado: aprovado com rollback';
    } finally {
        $pdo->rollBack();
    }

    toOk(!$repo->query('SELECT id FROM rumo10_carreiras WHERE id=?', [$testCareerId])->fetch(), 'MySQL: rollback integral e limpo confirmado');
}

$elapsedMs = round((hrtime(true) - $start) / 1e6, 2);

$report = [
    'checks' => $checks,
    'scope' => 'Certificação parcial desacoplada - Tocantins (br-to-a1) com descenso comprovado pelo Art. 13',
    'matches' => 34,
    'phases' => [
        'liga' => 28,
        'semifinal' => 4,
        'final' => 2
    ],
    'champion_certified' => true,
    'runner_up_certified' => true,
    'relegation_certified' => true,
    'relegation_count' => 2,
    'relegation_basis' => 'REC art.13: duas associações com pior campanha somando os pontos da 1ª fase rebaixadas para a 2ª divisão de 2026',
    'full_ranking_certified' => false,
    'pending_reason' => 'to_final_ranking_campaign_scope_requires_clarification',
    'pending_source' => 'REC art.11 §1',
    'bracket_conciliation' => 'API FTF oficial 35 (identificada como evidência de conciliação)',
    'national_admission_certified' => false,
    'homologated' => false,
    'done' => false,
    'game_modes_tested' => ['normal', 'rapido', 'expresso'],
    'v1_compatibility_verified' => true,
    'tampering_boundaries_tested' => 22,
    'pdo_status' => $pdoStatus,
    'elapsed_ms' => $elapsedMs,
];

file_put_contents(
    dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/to-partial-certification.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "OK: {$checks} verificações da certificação parcial de Tocantins (com descenso Art. 13 e PDO em escopo) em {$elapsedMs} ms. [PDO: {$pdoStatus}]\n";
