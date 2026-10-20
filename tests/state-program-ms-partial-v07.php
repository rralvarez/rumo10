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
function msOk(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function msReject(callable $fn): void {
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

// 1. Definição e validação do contrato MS revisado (v2)
$c = StateFederationsTrial::reference()['definitions']['br-ms-a1'];
msOk($c['edition'] === 2026, 'Edição 2026 de MS');
msOk($c['state_engine']['closure']['schema'] === 'certified-outcomes-v1', 'Esquema certified-outcomes-v1');
msOk($c['state_engine']['closure']['title_phase'] === 'final', 'Fase de título é final');
msOk($c['state_engine']['closure']['certified_runner_up'] === true, 'Vice-campeão comprovado declarado');
msOk($c['state_engine']['closure']['relegation'] === 2, 'Descenso declarado como 2');
msOk($c['state_engine']['closure']['relegation_phase'] === 'liga', 'Fase de descenso declarada como liga');
msOk(isset($c['state_engine']['closure']['pending']), 'Pendência documental ativa');

ProgrammedStateCompetition::validate($c, $c['teams']);

// 1.1 Reprodução e validação do Defeito 2: Rejeições atômicas na especificação de closure e descenso
$badRelegBasis = $c;
$badRelegBasis['state_engine']['closure']['relegation_basis'] = 'final_ranking';
msReject(fn() => ProgrammedStateCompetition::validate($badRelegBasis, $badRelegBasis['teams']));

$badRelegKnockout = $c;
$badRelegKnockout['state_engine']['closure']['relegation_phase'] = 'quartas';
msReject(fn() => ProgrammedStateCompetition::validate($badRelegKnockout, $badRelegKnockout['teams']));

$badRelegMissing = $c;
$badRelegMissing['state_engine']['closure']['relegation_phase'] = 'fase_inexistente';
msReject(fn() => ProgrammedStateCompetition::validate($badRelegMissing, $badRelegMissing['teams']));

$badRelegNegative = $c;
$badRelegNegative['state_engine']['closure']['relegation'] = -1;
msReject(fn() => ProgrammedStateCompetition::validate($badRelegNegative, $badRelegNegative['teams']));

$badRelegOverflow = $c;
$badRelegOverflow['state_engine']['closure']['relegation'] = 99;
msReject(fn() => ProgrammedStateCompetition::validate($badRelegOverflow, $badRelegOverflow['teams']));

$badNoSchema = $c;
unset($badNoSchema['state_engine']['closure']['schema']);
msReject(fn() => ProgrammedStateCompetition::validate($badNoSchema, $badNoSchema['teams']));

$badInvalidSchema = $c;
$badInvalidSchema['state_engine']['closure']['schema'] = 'invalid-schema-v99';
msReject(fn() => ProgrammedStateCompetition::validate($badInvalidSchema, $badInvalidSchema['teams']));

$badClosure = $c;
$badClosure['state_engine']['closure']['title_phase'] = 'fase_inexistente';
msReject(fn() => ProgrammedStateCompetition::validate($badClosure, $badClosure['teams']));

$badVice = $c;
unset($badVice['state_engine']['closure']['title_phase']);
msReject(fn() => ProgrammedStateCompetition::validate($badVice, $badVice['teams']));

$badViceBool = $c;
$badViceBool['state_engine']['closure']['certified_runner_up'] = false;
msReject(fn() => ProgrammedStateCompetition::validate($badViceBool, $badViceBool['teams']));

// 2. Execução determinística dos 55 jogos com pontuação simulada controlada
Competition::referenceScope([$c['id'] => $c], function() use ($c) {
    $t = Competition::tournament($c['id']);
    $t['lots'] = array_flip($c['teams']);

    // Fase 1: liga inicial (45 jogos, 9 rodadas)
    for ($stage = 0; $stage < 3; $stage++) {
        foreach ($t['matches'] as &$m) {
            if ($m['status'] !== 'finalizada') {
                $m['status'] = 'finalizada';
                $m['hg'] = 1;
                $m['ag'] = 0;
                // Assegurar confrontos com vencedor nas fases eliminatórias
                if ($m['phase'] === 'quartas' && $m['round'] === 11) {
                    $m['hg'] = 2;
                }
                if ($m['phase'] === 'semifinal' && $m['round'] === 13) {
                    $m['hg'] = 2;
                }
            }
        }
        unset($m);
        ProgrammedStateCompetition::next($t);
    }

    // Fase final (2 jogos)
    foreach ($t['matches'] as &$m) {
        if ($m['status'] !== 'finalizada') {
            $m['status'] = 'finalizada';
            $m['hg'] = 2;
            $m['ag'] = 1;
        }
    }
    unset($m);

    // Executar closure desacoplado
    ProgrammedStateCompetition::next($t);

    // 2.1 Preservação dos resultados
    msOk(count($t['matches']) === 55, 'MS executa exatamente 55 partidas');
    msOk(!array_filter($t['matches'], fn($m) => $m['status'] !== 'finalizada'), 'Todas as partidas finalizadas');

    // 2.2 Campeão e vice reconhecidos independentemente do ranking completo
    $champion = $t['state_phase_winners']['final'][0];
    $runnerUp = $t['state_phase_losers']['final'][0];
    msOk($t['champion'] !== null && $t['champion'] === $champion, 'Campeão identificado e comprovado');
    msOk($t['runner_up'] !== null && $t['runner_up'] === $runnerUp, 'Vice-campeão identificado e comprovado');
    msOk($champion !== $runnerUp, 'Campeão e vice são clubes distintos');

    // 2.3 Descenso certificado da primeira fase
    $expectedRelegated = array_slice($t['initial_ranking'], -2);
    msOk(count($t['relegated']) === 2, 'Exatamente 2 clubes rebaixados');
    msOk($t['relegated'] === $expectedRelegated, 'Descenso corresponde aos 9º e 10º da fase inicial');
    msOk(!in_array($champion, $t['relegated'], true), 'Campeão não está entre rebaixados');
    msOk(!in_array($runnerUp, $t['relegated'], true), 'Vice não está entre rebaixados');

    // 2.4 Ranking desconhecido (3º ao 8º) não preenchido artificialmente
    msOk(empty($t['final_ranking']), 'Ranking final integral NÃO é preenchido artificialmente');

    // 2.5 A competição permanece não homologada integralmente
    msOk($t['done'] === false, 'Competição NÃO é marcada como integralmente done/homologada');
    msOk(isset($t['administrative_pending']), 'Pendência administrativa documental ativa');
    msOk($t['administrative_pending']['state'] === 'awaiting_documented_rule', 'Estado da pendência é awaiting_documented_rule');

    // 2.6 Certificação parcial explicitamente auditável, versionada e estruturada
    msOk(isset($t['partial_certification']), 'Registro estruturado de certificação parcial presente');
    $part = $t['partial_certification'];
    msOk(is_int($part['schema']) && $part['schema'] === 1, 'Schema estrito inteiro 1 de certificação parcial');
    msOk($part['version'] === 'certified-outcomes-v1', 'Versão certified-outcomes-v1');
    msOk($part['competition'] === 'br-ms-a1', 'Identificador de competição correto');
    msOk($part['edition'] === 2026, 'Edição 2026');
    msOk($part['certified_champion'] === $champion, 'Campeão certificado confere');
    msOk($part['certified_runner_up'] === $runnerUp, 'Vice certificado confere');
    msOk($part['certified_relegated'] === $expectedRelegated, 'Descenso certificado confere');
    msOk($part['pending_ranking'] === true, 'Ranking pendente declarado');
    msOk($part['pending_national_admission'] === true, 'Admissão nacional pendente declarada');
    msOk($part['homologated'] === false, 'Não homologado declarado na certificação parcial');

    // Vínculo com contrato e resultados esportivos
    $contractDigest = StateResultProof::digest($c['state_engine']);
    msOk($part['contract_digest'] === $contractDigest, 'Recibo vinculado ao digest do contrato versionado');
    msOk(isset($part['results_digest']), 'Recibo vinculado ao digest dos resultados esportivos');

    // 2.7 Provas e integridade
    msOk(ProgrammedStateCompetition::verify($c, $t), 'Reconstrução determinística da prova com certificação parcial');
    msOk(!StateResultProof::verify($c, $t), 'StateResultProof recusa competição não finalizada (done=false)');

    // 3. Reprodução e validação do Defeito 3: Integridade estrita do recibo parcial
    // 3.1 schema = "1" (string em vez de int) altera tipo e DEVE ser rejeitado
    $tamperSchemaType = $t;
    $tamperSchemaType['partial_certification']['schema'] = "1";
    msOk(!ProgrammedStateCompetition::verify($c, $tamperSchemaType), 'Recibo com schema="1" (string) é rejeitado com comparação estrita');

    // 3.2 schema = 2
    $tamperSchemaNum = $t;
    $tamperSchemaNum['partial_certification']['schema'] = 2;
    msOk(!ProgrammedStateCompetition::verify($c, $tamperSchemaNum), 'Recibo com schema=2 é rejeitado');

    // 3.3 version adulterada
    $tamperVersion = $t;
    $tamperVersion['partial_certification']['version'] = 'certified-outcomes-v2';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperVersion), 'Recibo com version adulterada é rejeitado');

    // 3.4 campeão adulterado
    $tamperChamp = $t;
    $tamperChamp['champion'] = 'Clube Inventado-MS';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperChamp), 'Adulteração de campeão no estado rejeitada');

    $tamperChampReceipt = $t;
    $tamperChampReceipt['partial_certification']['certified_champion'] = 'Clube Inventado-MS';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperChampReceipt), 'Adulteração de campeão no recibo rejeitada');

    // 3.5 vice adulterado
    $tamperRunner = $t;
    $tamperRunner['runner_up'] = 'Outro Clube-MS';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperRunner), 'Adulteração de vice no estado rejeitada');

    $tamperRunnerReceipt = $t;
    $tamperRunnerReceipt['partial_certification']['certified_runner_up'] = 'Outro Clube-MS';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperRunnerReceipt), 'Adulteração de vice no recibo rejeitada');

    // 3.6 descenso adulterado
    $tamperReleg = $t;
    $tamperReleg['relegated'] = ['Operário-MS'];
    msOk(!ProgrammedStateCompetition::verify($c, $tamperReleg), 'Adulteração de descenso no estado rejeitada');

    $tamperRelegReceipt = $t;
    $tamperRelegReceipt['partial_certification']['certified_relegated'] = ['Operário-MS'];
    msOk(!ProgrammedStateCompetition::verify($c, $tamperRelegReceipt), 'Adulteração de descenso no recibo rejeitada');

    // 3.7 digest canônico inconsistente com a evidência de administrative_pending
    $tamperEvidence = $t;
    $tamperEvidence['administrative_pending']['evidence']['partial_certification'] = 'inconsistent_hash_abc';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperEvidence), 'Digest inconsistente na evidência rejeitado');

    // 3.8 key inconsistente
    $tamperKey = $t;
    $tamperKey['administrative_pending']['key'] = 'inconsistent_key_123';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperKey), 'Key de administrative_pending inconsistente rejeitada');

    // 3.9 contract_digest adulterado
    $tamperContractDigest = $t;
    $tamperContractDigest['partial_certification']['contract_digest'] = 'tampered_contract_digest';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperContractDigest), 'Recibo com contract_digest adulterado rejeitado');

    // 3.10 results_digest adulterado
    $tamperResultsDigest = $t;
    $tamperResultsDigest['partial_certification']['results_digest'] = 'tampered_results_digest';
    msOk(!ProgrammedStateCompetition::verify($c, $tamperResultsDigest), 'Recibo com results_digest adulterado rejeitado');

    // 3.11 homologated=true indevido
    $tamperPart = $t;
    $tamperPart['partial_certification']['homologated'] = true;
    msOk(!ProgrammedStateCompetition::verify($c, $tamperPart), 'Adulteração de homologated=true no recibo rejeitada');

    $tamperDone = $t;
    $tamperDone['done'] = true;
    msOk(!ProgrammedStateCompetition::verify($c, $tamperDone), 'Adulteração para done=true rejeitada na prova');

    // 3.12 Casos negativos estritos de tipagem/estrutura de lista em certified_relegated (Auditoria Codex)
    // 3.12.1 Lista sequencial válida com dois rebaixados
    msOk(is_array($t['partial_certification']['certified_relegated']) && array_is_list($t['partial_certification']['certified_relegated']), 'certified_relegated é estritamente uma lista sequencial');
    msOk(count($t['partial_certification']['certified_relegated']) === 2, 'certified_relegated contém exatamente 2 rebaixados');

    // 3.12.2 Lista invertida com preservação de índices (Codex exact tamper)
    $badCodex = $t;
    $badCodex['partial_certification']['certified_relegated'] = array_reverse(
        $badCodex['partial_certification']['certified_relegated'],
        true
    );
    msOk(!ProgrammedStateCompetition::verify($c, $badCodex), 'Lista invertida com preservação de índices (Codex) rejeitada');

    // 3.12.3 Lista com índice inicial diferente de zero
    $badNonZero = $t;
    $badNonZero['partial_certification']['certified_relegated'] = [
        1 => $t['partial_certification']['certified_relegated'][0],
        2 => $t['partial_certification']['certified_relegated'][1],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badNonZero), 'Lista com índice inicial diferente de zero rejeitada');

    // 3.12.4 Lista com lacuna entre índices
    $badGap = $t;
    $badGap['partial_certification']['certified_relegated'] = [
        0 => $t['partial_certification']['certified_relegated'][0],
        2 => $t['partial_certification']['certified_relegated'][1],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badGap), 'Lista com lacuna entre índices rejeitada');

    // 3.12.5 Lista com chave numérica adicional
    $badExtraKey = $t;
    $badExtraKey['partial_certification']['certified_relegated'] = [
        0 => $t['partial_certification']['certified_relegated'][0],
        1 => $t['partial_certification']['certified_relegated'][1],
        2 => 'Aquidauanense-MS',
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badExtraKey), 'Lista com chave numérica adicional rejeitada');

    // 3.12.6 Lista com chave textual
    $badTextKey = $t;
    $badTextKey['partial_certification']['certified_relegated'] = [
        'primeiro' => $t['partial_certification']['certified_relegated'][0],
        'segundo' => $t['partial_certification']['certified_relegated'][1],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badTextKey), 'Lista com chave textual rejeitada');

    // 3.12.7 Rebaixados duplicados
    $badDup = $t;
    $badDup['partial_certification']['certified_relegated'] = [
        $t['partial_certification']['certified_relegated'][0],
        $t['partial_certification']['certified_relegated'][0],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badDup), 'Rebaixados duplicados no recibo rejeitado');

    // 3.12.8 Rebaixado inexistente
    $badInexistent = $t;
    $badInexistent['partial_certification']['certified_relegated'] = [
        $t['partial_certification']['certified_relegated'][0],
        'Clube-Inexistente-MS',
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badInexistent), 'Rebaixado inexistente na competição rejeitado');

    // 3.12.9 Quantidade divergente
    $badQty = $t;
    $badQty['partial_certification']['certified_relegated'] = [
        $t['partial_certification']['certified_relegated'][0],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badQty), 'Quantidade divergente de rebaixados rejeitada');

    // 3.12.10 Ordem dos clubes alterada
    $badOrder = $t;
    $badOrder['partial_certification']['certified_relegated'] = array_values(array_reverse(
        $t['partial_certification']['certified_relegated']
    ));
    msOk(!ProgrammedStateCompetition::verify($c, $badOrder), 'Ordem dos clubes rebaixados alterada rejeitada');

    // 3.12.11 Conversão JSON de lista para objeto
    $badObj = $t;
    $badObj['partial_certification']['certified_relegated'] = (object)[
        '0' => $t['partial_certification']['certified_relegated'][0],
        '1' => $t['partial_certification']['certified_relegated'][1],
    ];
    msOk(!ProgrammedStateCompetition::verify($c, $badObj), 'Conversão de lista para objeto rejeitada');

    // 3.12.12 Serialização e hidratação da estrutura adulterada
    $wireBadCodex = json_decode(json_encode($badCodex), true);
    msOk(!ProgrammedStateCompetition::verify($c, $wireBadCodex), 'Estrutura adulterada serializada e hidratada rejeitada');

    // 3.12.13 Preservação de reordenação legítima de chaves associativas do recibo (MySQL JSON)
    $permutedReceipt = $t;
    $permutedReceipt['partial_certification'] = array_reverse($t['partial_certification'], true);
    msOk(ProgrammedStateCompetition::verify($c, $permutedReceipt), 'Reordenação legítima de chaves associativas do recibo aceita');
    $wirePermuted = json_decode(json_encode($permutedReceipt), true);
    msOk(ProgrammedStateCompetition::verify($c, $wirePermuted), 'Reordenação de chaves associativas sobrevive após hidratação JSON');

    // 3.13 Idempotência e serialização
    $saved = $t;
    ProgrammedStateCompetition::next($t);
    msOk($saved === $t, 'Próxima chamada quando pendente é estritamente idempotente');

    $wire = json_decode(json_encode($t), true);
    msOk(ProgrammedStateCompetition::verify($c, $wire), 'Serialização JSON preserva verificação da prova exata');
});

// 4. Validação em modos de jogo (Game runtime): ausência de avanço, XP de título e vagas nacionais
foreach (['normal', 'rapido', 'expresso'] as $i => $mode) {
    $g = StateFederationsTrial::isolated(878001 + $i, 'br-ms-a1', $mode);
    $steps = 0;
    while ($g['stage'] !== 'world_pending') {
        if (++$steps > 1600) {
            throw new RuntimeException("Loop excessivo no modo {$mode}");
        }
        [$action, $input] = acAction($g);
        $g = Game::command($g, $action, $input);
        $g = json_decode(json_encode($g), true);
    }

    $t = $g['competitions']['br-ms-a1'];
    msOk(count($t['matches']) === 55, "{$mode}: 55 partidas disputadas");
    msOk($t['done'] === false, "{$mode}: done é false");
    msOk($t['champion'] !== null, "{$mode}: campeão reconhecido");
    msOk($t['runner_up'] !== null, "{$mode}: vice reconhecido");
    msOk(count($t['relegated']) === 2, "{$mode}: descenso reconhecido");
    msOk(isset($t['partial_certification']), "{$mode}: partial_certification presente");

    // Inscrição nacional não realizada
    msOk(empty($g['state_qualifications']), "{$mode}: nenhuma qualificação estadual gravada");
    msOk(empty($g['future_d_admissions']), "{$mode}: nenhuma admissão futura na Série D gerada");

    // Ausência de premiações duplicadas ou de título
    msOk(empty($g['competition_results']), "{$mode}: competition_results vazio (sem concessão de título)");
    msOk(empty($g['world_completed']), "{$mode}: world_completed vazio (competição não concluída no calendário)");

    // Mercado permanece bloqueado
    try {
        Game::command($g, 'offers');
        msOk(false, 'Mercado não deve ser aceito');
    } catch (InvalidArgumentException $e) {
        msOk(true, "{$mode}: mercado bloqueado");
    }

    // Renovação nacional bloqueada
    try {
        WorldSeason::renew($g);
        msOk(false, 'Renovação nacional não deve ser permitida');
    } catch (RuntimeException $e) {
        msOk(str_contains($e->getMessage(), 'Temporada anterior ainda não encerrada'), "{$mode}: renovação bloqueada");
    }

    // Verificação da prova pós-Game
    msOk(WorldSeason::scope($g, fn() => ProgrammedStateCompetition::verify($c, $t)), "{$mode}: prova pós-Game verificada com sucesso");
}

// 5. Reprodução e validação do Defeito 1: Compatibilidade histórica estrita com RJ, SC, CE, PA e TO v1
$v1 = json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-program-2026-v1.json'), true);

// 5.1 TO v1 (pendente histórico sem certificação parcial)
$oldTO = $v1['definitions']['br-to-a1'];
Competition::referenceScope([$oldTO['id'] => $oldTO], function() use ($oldTO) {
    $t = ProgrammedStateCompetition::create($oldTO, $oldTO['teams']);
    foreach ($t['matches'] as &$m) {
        $m['status'] = 'finalizada';
        $m['hg'] = 0;
        $m['ag'] = 0;
    }
    unset($m);
    ProgrammedStateCompetition::next($t);
    msOk(count($t['matches']) === 28, 'TO v1 continua gerando 28 partidas');
    msOk($t['champion'] === null, 'TO v1 preserva champion=null');
    msOk(!array_key_exists('runner_up', $t), 'TO v1 histórico NÃO possui campo runner_up');
    msOk($t['relegated'] === [], 'TO v1 preserva relegated=[]');
    msOk(!array_key_exists('partial_certification', $t), 'TO v1 histórico NÃO possui campo partial_certification');
    msOk(ProgrammedStateCompetition::verify($oldTO, $t), 'TO v1 sem partial_certification verifica com sucesso');

    // Injeção retroativa de campos em TO v1 deve falhar na verificação
    $tamperTO = $t;
    $tamperTO['runner_up'] = null;
    msOk(!ProgrammedStateCompetition::verify($oldTO, $tamperTO), 'Injeção de runner_up em TO v1 rejeitada na verificação');

    $tamperTOPart = $t;
    $tamperTOPart['partial_certification'] = null;
    msOk(!ProgrammedStateCompetition::verify($oldTO, $tamperTOPart), 'Injeção de partial_certification em TO v1 rejeitada na verificação');
});

// 5.2 Snapshots históricos concluídos de RJ, SC, CE e PA (SEM runner_up e SEM partial_certification)
$historicalUFs = ['br-rj-a1', 'br-sc-a1', 'br-ce-a1', 'br-pa-a1'];
foreach ($historicalUFs as $ufId) {
    $def = $v1['definitions'][$ufId];
    Competition::referenceScope([$def['id'] => $def], function() use ($def, $ufId) {
        // Criar torneio no formato do contrato legado
        $t = ProgrammedStateCompetition::create($def, $def['teams']);
        msOk(!array_key_exists('runner_up', $t), "{$ufId}: contrato legado NÃO gera runner_up na criação");
        msOk(!array_key_exists('partial_certification', $t), "{$ufId}: contrato legado NÃO gera partial_certification na criação");

        while (!$t['done']) {
            if (isset($t['administrative_pending'])) {
                $p = $t['administrative_pending'];
                if ($p['state'] === 'awaiting_club_home_choice') {
                    $d = ['key' => $p['key'], 'reference' => 'Synthetic test choice', 'home_clubs' => array_column($p['request']['pairs'], 0)];
                    StateProgramAdministration::designate($t, $d);
                }
            }
            foreach ($t['matches'] as &$m) {
                if ($m['status'] !== 'finalizada') {
                    $m['status'] = 'finalizada';
                    $m['hg'] = 1;
                    $m['ag'] = 0;
                }
            }
            unset($m);
            ProgrammedStateCompetition::next($t);
        }

        // Simular o snapshot histórico real: remover explicitamente quaisquer campos novos
        unset($t['runner_up'], $t['partial_certification']);

        msOk($t['done'] === true, "{$ufId}: torneio concluído");
        msOk($t['champion'] !== null, "{$ufId}: campeão definido");
        msOk(!array_key_exists('runner_up', $t), "{$ufId}: snapshot histórico purificado sem runner_up");
        msOk(!array_key_exists('partial_certification', $t), "{$ufId}: snapshot histórico purificado sem partial_certification");

        // 5.2.1 Verificação do snapshot histórico legítimo DEVE passar
        msOk(ProgrammedStateCompetition::verify($def, $t), "{$ufId}: snapshot histórico sem novos campos é aceito na verificação");

        // 5.2.2 Injeção indevida de runner_up=null no snapshot histórico DEVE ser rejeitada
        $badAddNull = $t;
        $badAddNull['runner_up'] = null;
        msOk(!ProgrammedStateCompetition::verify($def, $badAddNull), "{$ufId}: injeção de runner_up=null em snapshot histórico é rejeitada");

        // 5.2.3 Injeção indevida de partial_certification=null no snapshot histórico DEVE ser rejeitada
        $badAddPartNull = $t;
        $badAddPartNull['partial_certification'] = null;
        msOk(!ProgrammedStateCompetition::verify($def, $badAddPartNull), "{$ufId}: injeção de partial_certification=null em snapshot histórico é rejeitada");

        // 5.2.4 Rejeição de alterações nos resultados esportivos de snapshots históricos
        $badScore = $t;
        $badScore['matches'][0]['hg'] += 10;
        msOk(!ProgrammedStateCompetition::verify($def, $badScore), "{$ufId}: adulteração de placar em snapshot histórico é rejeitada");

        // 5.2.5 Rejeição de adulterações no ranking do snapshot histórico
        $badRanking = $t;
        $badRanking['final_ranking'] = array_reverse($badRanking['final_ranking']);
        msOk(!ProgrammedStateCompetition::verify($def, $badRanking), "{$ufId}: adulteração de ranking final é rejeitada");

        // 5.2.6 Rejeição de adulterações no contrato fixado
        $badDef = $def;
        $badDef['teams'][0] = 'Clube Inexistente-BR';
        msOk(!ProgrammedStateCompetition::verify($badDef, $t), "{$ufId}: adulteração no contrato fixado é rejeitada");
    });
}

$elapsedMs = round((hrtime(true) - $start) / 1e6, 2);

$report = [
    'checks' => $checks,
    'scope' => 'Piloto de certificações parciais desacopladas corrigido - Mato Grosso do Sul (br-ms-a1)',
    'defects_addressed' => [
        'defeito_1_compatibilidade_historica' => 'runner_up e partial_certification condicionados estritamente ao schema certified-outcomes-v1; snapshots históricos de RJ, SC, CE, PA e TO verificados sem campos novos',
        'defeito_2_validacao_descenso' => 'relegation_basis=final_ranking rejeitado para certificação parcial; fase de descenso restrita a league; validação estrita de contagem, unicidade e ausência de conflitos',
        'defeito_3_integridade_recibo' => 'schema validado estritamente como inteiro 1 (rejeitando "1"); digest canônico comparado estritamente; recibo vinculado a contract_digest e results_digest',
        'falha_bloqueante_auditoria_2_recibo' => 'certified_relegated validado com array_is_list, comparação estrita contra a lista reconstruída, e rejeição de objetos numéricos, índices não sequenciais, inversão de ordem e hidratação JSON adulterada',
    ],
    'matches' => 55,
    'champion_certified' => true,
    'runner_up_certified' => true,
    'relegation_certified' => true,
    'full_ranking_certified' => false,
    'national_admission_certified' => false,
    'homologated' => false,
    'done' => false,
    'historical_snapshots_tested' => $historicalUFs,
    'game_modes_tested' => ['normal', 'rapido', 'expresso'],
    'elapsed_ms' => $elapsedMs,
];

file_put_contents(
    dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/ms-partial-certification-pilot.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "OK: {$checks} verificações do piloto de certificação parcial MS (com correções da auditoria) em {$elapsedMs} ms.\n";
