<?php
declare(strict_types=1);
putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
putenv('RUMO10_WORLD_CALENDAR=1');
putenv('RUMO10_WORLD_FIXTURES=1');
putenv('RUMO10_NEUTRAL_SIMULATION=1');
putenv('RUMO10_ADMIN_TEST_DESIGNATIONS=1');
require dirname(__DIR__).'/app/bootstrap.php';

// Fixture estática, sanitizada e versionável para teste de ordenação e imutabilidade do ledger de candidaturas D.
// Proveniência: coorte sintética derivada de GoianoTrial (seed 872002, 10 UFs + pirâmide nacional),
// sanitizada de metadados desnecessários (logs de lances, escalações detalhadas e agenda) para 619 KB.
// A fixture histórica anterior ('combined-npc-fixture.json') preserva legitimamente a pendência documental do MA e permanece intacta.
$fixturePaths = [
    dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/synthetic-ledger-fixture.json',
    dirname(__DIR__).'/storage/qa/states27/synthetic-ledger-fixture.json',
];
$syntheticPath = null;
foreach ($fixturePaths as $path) {
    if (file_exists($path)) {
        $syntheticPath = $path;
        break;
    }
}
if ($syntheticPath === null) {
    throw new RuntimeException('Fixture estática do ledger ausente. Esperado em Docs/mundializacao-v07/conclusao-27/synthetic-ledger-fixture.json');
}

$g = json_decode(file_get_contents($syntheticPath), true, 512, JSON_THROW_ON_ERROR);
if (empty($g['future_d_admissions']['2027'])) {
    throw new RuntimeException('Requires completed cohort with future_d_admissions; no favorable reroll allowed');
}
$checks = 0;
function orderOk(bool $v, string $m): void {
    global $checks;
    $checks++;
    if (!$v) throw new RuntimeException($m);
}
function ledgerHash(array $r): array {
    unset($r['sha256']);
    $sort = function($v) use (&$sort) {
        if (!is_array($v)) return $v;
        if (!array_is_list($v)) ksort($v, SORT_STRING);
        foreach ($v as &$x) $x = $sort($x);
        return $v;
    };
    $r['sha256'] = hash('sha256', json_encode($sort($r), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    return $r;
}

$old = $g['future_d_admissions']['2027'];
$before = $g;
orderOk(FutureSerieDAdmission::record($g) === $old && $g === $before, 'legacy record preserved byte-for-byte');

$permuted = $g;
$permuted['competitions'] = array_reverse($permuted['competitions'], true);
$before = $permuted;
orderOk(FutureSerieDAdmission::record($permuted) === $old && $permuted === $before, 'map enumeration cannot conflict or rewrite record');

$legacy = $g;
$legacy['future_d_admissions']['2027'] = ledgerHash(array_replace($old, ['ledger' => array_reverse($old['ledger'])]));
$saved = $legacy;
orderOk(FutureSerieDAdmission::record($legacy) === $saved['future_d_admissions']['2027'] && $legacy === $saved, 'synthetic legacy ledger enumeration retained exactly');

foreach (['hash', 'candidate', 'evidence', 'duplicate'] as $kind) {
    $bad = $g;
    $row = $bad['future_d_admissions']['2027'];
    if ($kind === 'hash') $row['sha256'] = str_repeat('0', 64);
    if ($kind === 'candidate') { $row['candidate_count']++; $row = ledgerHash($row); }
    if ($kind === 'evidence') { $row['ledger'][0]['evidence'] = 'wrong'; $row = ledgerHash($row); }
    if ($kind === 'duplicate') { $row['ledger'][] = $row['ledger'][0]; $row = ledgerHash($row); }
    $bad['future_d_admissions']['2027'] = $row;
    $saved = $bad;
    try {
        FutureSerieDAdmission::record($bad);
        throw new LogicException('Invalid ledger accepted ' . $kind);
    } catch (RuntimeException $e) {
        orderOk($bad === $saved, 'conflict rejected atomically ' . $kind);
    }
}

file_put_contents(
    dirname(__DIR__).'/Docs/mundializacao-v07/conclusao-27/ledger-order.json',
    json_encode([
        'checks' => $checks,
        'scope' => 'static sanitized synthetic cohort (GoianoTrial 10 UFs + national pyramid, 619 KB) for ledger ordering/tampering boundaries; no RNG/random_int; combined-npc-fixture preserved with MA pending',
        'results_changed' => false,
        'legacy_records_rewritten' => false,
        'entries' => 0,
        'renewals' => 0
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
echo "OK: {$checks} ledger ordering checks.\n";
