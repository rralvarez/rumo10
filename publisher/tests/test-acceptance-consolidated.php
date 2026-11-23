<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
require_once dirname(__DIR__).'/lib/LegacyFence.php';
require_once dirname(__DIR__).'/lib/RuntimeIdentity.php';
require_once dirname(__DIR__).'/lib/DatabaseSchemaValidator.php';

use Rumo\Publisher\{DatabaseSchemaValidator, DrainPublisher, Evidence, GitHub, LegacyFence, MainAutomation, RequestDrain, RuntimeIdentity, UpdatePolicy};
use function Rumo\Publisher\{digest, json, need, record};

$passed = 0;
function ok(bool $cond, string $label): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAIL: {$label}");
    $passed++;
    echo "  [OK] {$label}\n";
}
function mustFail(callable $fn, string $needle, string $label): void {
    try {
        $fn();
    } catch (Throwable $e) {
        if ($needle === '' || str_contains($e->getMessage(), $needle)) {
            ok(true, $label);
            return;
        }
        throw new RuntimeException("FAIL ({$label}): got '{$e->getMessage()}', expected '{$needle}'");
    }
    throw new RuntimeException("FAIL ({$label}): expected exception '{$needle}', got none");
}
function makeTarGz(string $destFile, array $files): void {
    $tar = '';
    foreach ($files as $name => $content) {
        $size = strlen($content);
        $header = str_pad($name, 100, "\0")
            . str_pad('0000644', 7, '0', STR_PAD_LEFT)."\0"
            . str_pad('0000000', 7, '0', STR_PAD_LEFT)."\0"
            . str_pad('0000000', 7, '0', STR_PAD_LEFT)."\0"
            . str_pad(decoct($size), 11, '0', STR_PAD_LEFT)."\0"
            . str_pad(decoct(1700000000), 11, '0', STR_PAD_LEFT)."\0"
            . '        ' . '0' . str_pad('', 100, "\0")
            . "ustar\0" . '00' . str_pad('', 247, "\0");
        $sum = array_sum(unpack('C*', $header));
        $header = substr_replace($header, str_pad(decoct($sum), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);
        $tar .= $header . $content;
        $pad = (512 - ($size % 512)) % 512;
        if ($pad > 0) $tar .= str_repeat("\0", $pad);
    }
    $tar .= str_repeat("\0", 1024);
    $gz = gzopen($destFile, 'wb9');
    need($gz !== false, 'Cannot write tar.gz');
    gzwrite($gz, $tar);
    gzclose($gz);
}
function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($dir);
}

echo "=== RUMO10 | BATERIA DE ACEITACAO CONSOLIDADA (12 TESTES OBRIGATORIOS) ===\n\n";

$ownerId = 35331184;
$repo = 'rralvarez/rumo10';
$c11 = '5d1052f9d85c550e38b6a36e4197cc5592fe85e2'; // PR #11
$c12 = 'eb07334612f4e814bfec6f0a62eb605e7ffadb37'; // PR #12 (app/Retirement.php)
$c14 = 'b73d3c43800ce482e806099b79195de1952efcde'; // PR #14 (app/Game.php + app/MatchParticipation.php)
$pr12Head = 'eed21ca14fbdafca32213adcd2ba42ecb74ce737';
$pr14Head = str_repeat('4', 40);

$root = sys_get_temp_dir() . '/r10-accept-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);

try {
    // ---------------------------------------------------------------------
    // TESTE 1 & 10 & 12: Instalacao limpa, identidade de runtime e recusa de receiver-bootstrap-*
    // ---------------------------------------------------------------------
    echo "--- Testes 1, 10 e 12: Instalacao limpa, identidade ativa e independencia ---\n";
    $pubV9 = $root . '/publisher-v9';
    mkdir($pubV9 . '/lib', 0700, true);
    mkdir($pubV9 . '/bin', 0700, true);
    foreach (glob(dirname(__DIR__) . '/lib/*.php') ?: [] as $f) {
        copy($f, $pubV9 . '/lib/' . basename($f));
    }
    copy(dirname(__DIR__) . '/bin/controlled-pull.php', $pubV9 . '/bin/controlled-pull.php');
    copy(dirname(__DIR__) . '/bin/self-update.php', $pubV9 . '/bin/self-update.php');
    $manifestV9 = RuntimeIdentity::buildManifest($pubV9 . '/lib', RuntimeIdentity::VERSION, 'consolidated-test');
    record($pubV9 . '/lib/version.json', $manifestV9);

    $idV9 = RuntimeIdentity::inspect($pubV9 . '/lib', true);
    ok($idV9['version'] === RuntimeIdentity::VERSION && $idV9['verified_files'] >= 11 && $idV9['legacy_bootstrap_path'] === false,
        'T1: Instalacao limpa valida version.json e todos os arquivos da biblioteca unica');

    $cmdId = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pubV9 . '/bin/controlled-pull.php') . ' --identity';
    $outId = shell_exec($cmdId);
    $parsedId = json((string)$outId);
    ok(($parsedId['ok'] ?? false) === true && ($parsedId['runtime']['version'] ?? null) === RuntimeIdentity::VERSION
        && str_ends_with($parsedId['runtime']['lib_path'] ?? '', '/publisher-v9/lib'),
        'T10: controlled-pull.php --identity comprova versao e caminho fisico real da biblioteca consolidada');

    // Recusa caminho legado receiver-bootstrap-*
    $legacyDir = $root . '/receiver/receiver-bootstrap-deadbeef/lib';
    mkdir($legacyDir, 0700, true);
    foreach (glob($pubV9 . '/lib/*') ?: [] as $f) copy($f, $legacyDir . '/' . basename($f));
    mustFail(
        fn() => RuntimeIdentity::inspect($legacyDir, true),
        'Obsolete receiver-bootstrap library path refused',
        'T12: RuntimeIdentity recusa caminhos obsoletos receiver-bootstrap-*'
    );

    // Self-update atomico + rollback (Requisito 5.4)
    $stagedNew = $root . '/staged-v901';
    mkdir($stagedNew, 0700, true);
    foreach (glob($pubV9 . '/lib/*.php') ?: [] as $f) copy($f, $stagedNew . '/' . basename($f));
    $manifestV901 = RuntimeIdentity::buildManifest($stagedNew, '9.0.1-test', 'next-commit');
    record($stagedNew . '/version.json', $manifestV901);
    $manifestSha = digest($stagedNew . '/version.json');
    $suCfg = $root . '/su-config.json';
    record($suCfg, ['account_mutex' => $root . '/su.mutex']);

    $cmdUp = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pubV9 . '/bin/self-update.php')
        . ' --config=' . escapeshellarg($suCfg)
        . ' --source=' . escapeshellarg($stagedNew)
        . ' --manifest-sha256=' . escapeshellarg($manifestSha);
    $resUp = json((string)shell_exec($cmdUp));
    ok(($resUp['state'] ?? null) === 'updated' && ($resUp['active_runtime']['version'] ?? null) === '9.0.1-test',
        'T12/5.4: self-update.php atualiza biblioteca atomicamente com verificacao SHA-256');

    $cmdRb = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pubV9 . '/bin/self-update.php')
        . ' --config=' . escapeshellarg($suCfg) . ' --rollback';
    $resRb = json((string)shell_exec($cmdRb));
    ok(($resRb['state'] ?? null) === 'rolled_back' && ($resRb['active_runtime']['version'] ?? null) === RuntimeIdentity::VERSION,
        'T12/5.4: self-update.php --rollback reverte atomicamente para a versao anterior');

    // ---------------------------------------------------------------------
    // Sandbox produtivo para Testes 2, 3, 4, 5, 6, 7, 8, 9
    // ---------------------------------------------------------------------
    echo "\n--- Testes 2, 3, 4, 5, 8 e 9: Checkpoint PR #11, PR #12, 2a publicacao, idempotencia, backup e recovery ---\n";
    $prod = $root . '/prod';
    foreach (['private', 'control', 'current/app', 'current/public', 'public'] as $sub) {
        mkdir($prod . '/' . $sub, 0700, true);
    }
    RequestDrain::provision($prod . '/gate');

    $filesV11 = [
        'app/bootstrap.php' => "<?php // boot-v11\n",
        'app/Game.php' => "<?php // game-v11\n",
        'app/PlayerDevelopment.php' => "<?php // dev-v3\n",
        'app/Retirement.php' => "<?php // ret-v11\n",
        'app/Repository.php' => "<?php // repo-v11\n",
        'app/Database.php' => "<?php // db-v11\n",
        'public/index.php' => "<?php // idx-v11\n",
        'public/.htaccess' => "RewriteEngine On\n",
    ];
    foreach ($filesV11 as $rel => $content) {
        file_put_contents($prod . '/current/' . $rel, $content);
    }
    file_put_contents($prod . '/public/index.php', $filesV11['public/index.php']);
    file_put_contents($prod . '/public/.htaccess', $filesV11['public/.htaccess']);
    $hubEntry = $prod . '/rumohub-index.php';
    file_put_contents($hubEntry, "<?php // rumohub\n");
    $tokFile = $prod . '/private/token.txt';
    $stTokFile = $prod . '/private/status-token.txt';
    $envFile = $prod . '/private/app.env';
    file_put_contents($tokFile, 'tok');
    file_put_contents($stTokFile, 'stok');
    file_put_contents($envFile, "APP_ENV=prod\n");

    $hashesV11 = [];
    foreach ($filesV11 as $rel => $content) $hashesV11[$rel] = hash('sha256', $content);
    ksort($hashesV11);
    record($prod . '/current/build-manifest.json', ['commit' => $c11, 'checksums' => $hashesV11]);
    record($prod . '/current/release.json', ['project' => 'rumo10', 'commit' => $c11, 'files' => $hashesV11]);
    record($prod . '/private/installed.json', [
        'release_channel' => 'main',
        'commit' => $c11,
        'functional_commit' => $c11,
        'artifact_id' => 14364443764,
        'run_id' => 100,
        'run_number' => 68,
        'package_sha256' => str_repeat('1', 64),
        'manifest_sha256' => digest($prod . '/current/release.json'),
        'build_manifest_sha256' => digest($prod . '/current/build-manifest.json'),
    ]);

    $fenceScript = $prod . '/private/fence.php';
    file_put_contents($fenceScript, "<?php echo json_encode(['ok'=>true,'legacy_publishers_fenced'=>true]);\n");

    $policy = [
        'schema' => 'rumo-update-policy-v1',
        'protected_contract_paths' => ['app/Database.php', 'app/Repository.php', 'public/.htaccess', 'app/Retirement.php', 'app/RetiredCareer.php'],
        'approved_incremental_migrations' => [],
        'allow_unpinned_migrations' => true,
        'authorized_transitions' => [],
        'authorized_releases' => ['907427dabc363d055ff32ad53853d322639992fb' => ['label' => 'F01']],
    ];
    $reqJobs = ['syntax', 'regression', 'compatibility', 'publisher-safety', 'rumo10-ci-gate', 'artifact'];
    $cfg = [
        'schema' => 'rumo-pull-v1', 'php' => PHP_BINARY, 'project' => 'rumo10',
        'repository' => $repo, 'branch' => 'main', 'release_channel' => 'main',
        'workflow_id' => 398860623, 'required_jobs' => $reqJobs,
        'data_contracts' => ['rumo10-development-v3'], 'baseline_data_contracts' => ['rumo10-development-v3'],
        'pinned_controls' => [
            $prod . '/public/.htaccess' => digest($prod . '/public/.htaccess'),
            $prod . '/public/index.php' => digest($prod . '/public/index.php'),
        ],
        'backup_roots' => ['env' => $envFile],
        'token_file' => $tokFile, 'account_mutex' => $prod . '/private/account.mutex',
        'private' => $prod . '/private', 'control' => $prod . '/control',
        'current' => $prod . '/current', 'public' => $prod . '/public',
        'apply_enabled' => true,
        'homologation' => ['maintenance' => true, 'recovery' => true, 'legacy_publishers_fenced' => true, 'backup' => true],
        'request_gate' => [
            'root' => $prod . '/gate', 'coverage_approved' => true, 'projects' => ['rumo10', 'rumohub'],
            'entrypoints' => [$prod . '/public/index.php' => digest($prod . '/public/index.php'), $hubEntry => digest($hubEntry)],
            'timeout_seconds' => 2,
        ],
        'update_policy' => $policy,
        'main_automation' => [
            'schema' => 'rumo-main-automation-v1', 'owner_id' => $ownerId,
            'status_token_file' => $stTokFile,
            'public_smoke_urls' => ['rumo10' => 'https://rumo10.example.test/', 'rumohub' => 'https://rumohub.example.test/'],
        ],
        'hooks' => ['fence' => ['script' => $fenceScript, 'sha256' => digest($fenceScript), 'args' => []]],
    ];

    $buildStage = static function (string $name, string $commit, int $runId, int $runNumber, int $artId, int $prNum, array $files) use ($prod, $repo, $reqJobs): array {
        $stage = $prod . '/private/stage-' . $name;
        mkdir($stage . '/payload/app', 0700, true);
        mkdir($stage . '/payload/public', 0700, true);
        $hashes = [];
        foreach ($files as $rel => $content) {
            $hashes[$rel] = hash('sha256', $content);
            file_put_contents($stage . '/payload/' . $rel, $content);
        }
        ksort($hashes);
        $files['release.json'] = json_encode([
            'project' => 'rumo10', 'commit' => $commit, 'functional_commit' => $commit,
            'data_contract' => 'rumo10-development-v3', 'data_contracts' => ['rumo10-development-v3'],
            'required_migrations' => [], 'files' => $hashes,
        ], JSON_THROW_ON_ERROR);
        file_put_contents($stage . '/payload/release.json', $files['release.json']);
        $tar = $stage . '/release.tar.gz';
        makeTarGz($tar, $files);
        $pkgSha = digest($tar);
        $run = [
            'id' => $runId, 'run_attempt' => 1, 'run_number' => $runNumber, 'workflow_id' => 398860623,
            'head_sha' => $commit, 'head_branch' => 'main', 'event' => 'push',
            'status' => 'completed', 'conclusion' => 'success',
            'repository' => ['full_name' => $repo], 'merged_pr' => $prNum,
        ];
        $artifact = [
            'id' => $artId, 'name' => 'release-rumo10-' . $commit, 'expired' => false,
            'digest' => 'sha256:' . str_repeat('a', 64), 'workflow_run' => ['id' => $runId, 'head_sha' => $commit],
        ];
        $jobs = array_map(static fn($j) => [
            'name' => $j, 'status' => 'completed', 'conclusion' => 'success',
            'head_sha' => $commit, 'run_id' => $runId, '_requested_attempt' => 1,
        ], $reqJobs);
        $envelope = [
            'schema' => 'rumo-pull-v1', 'project' => 'rumo10', 'repository' => $repo,
            'branch' => 'main', 'release_channel' => 'main', 'commit' => $commit,
            'source_commit' => $commit, 'functional_commit' => $commit,
            'workflow_id' => 398860623, 'run_id' => $runId, 'run_attempt' => 1, 'run_number' => $runNumber,
            'package_sha256' => $pkgSha, 'data_contract' => 'rumo10-development-v3',
            'data_contracts' => ['rumo10-development-v3'], 'required_migrations' => [],
        ];
        record($stage . '/ready.json', compact('run', 'artifact', 'jobs', 'envelope'));
        return [$stage, $pkgSha, $artId];
    };

    $makePub = static function (array $cfg, array $opts = []) use ($repo, $c11, $c12, $pr12Head, $ownerId): DrainPublisher {
        return new class($cfg, $repo, $c11, $c12, $pr12Head, $ownerId, $opts) extends DrainPublisher {
            public array $hooks = [];
            private bool $failedOnce = false;
            public function __construct(
                private array $tCfg, private string $repo, private string $c11, private string $c12,
                private string $pr12Head, private int $ownerId, private array $opts
            ) { parent::__construct($tCfg); }
            protected function assertBackupPrerequisites(bool $classA = false): void {}
            protected function promote(string $a, string $b): bool {
                if (($this->opts['fail_promote_once'] ?? false) && !$this->failedOnce) {
                    $this->failedOnce = true;
                    return false;
                }
                return parent::promote($a, $b);
            }
            protected function source(): GitHub {
                return new class($this->tCfg, $this->repo, $this->c11, $this->c12, $this->pr12Head, $this->ownerId) extends GitHub {
                    public function __construct(array $c, private string $repo, private string $c11, private string $c12, private string $pr12Head, private int $ownerId) { parent::__construct($c); }
                    protected function request(string $url, bool $auth, int $limit, ?string $out = null): array {
                        $path = parse_url($url, PHP_URL_PATH);
                        if (str_contains($path, '/compare/')) {
                            return ['status' => 200, 'body' => json_encode([
                                'status' => 'ahead', 'merge_base_commit' => ['sha' => $this->c11],
                                'files' => [['filename' => 'app/Retirement.php']],
                            ], JSON_THROW_ON_ERROR)];
                        }
                        if (str_ends_with($path, '/pulls')) {
                            return ['status' => 200, 'body' => json_encode([[
                                'number' => 12, 'state' => 'closed', 'merged_at' => '2026-11-20T12:00:00Z',
                                'merge_commit_sha' => $this->c12, 'base' => ['repo' => ['full_name' => $this->repo], 'ref' => 'main'],
                            ]], JSON_THROW_ON_ERROR)];
                        }
                        if (str_contains($path, '/pulls/12')) {
                            return ['status' => 200, 'body' => json_encode([
                                'number' => 12, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-20T12:00:00Z',
                                'merged_by' => ['id' => $this->ownerId], 'base' => ['repo' => ['full_name' => $this->repo], 'ref' => 'main'],
                                'merge_commit_sha' => $this->c12, 'head' => ['sha' => $this->pr12Head],
                            ], JSON_THROW_ON_ERROR)];
                        }
                        if (str_contains($path, '/commits/')) {
                            return ['status' => 200, 'body' => json_encode([
                                'sha' => $this->c12, 'parents' => [['sha' => $this->c11], ['sha' => $this->pr12Head]],
                            ], JSON_THROW_ON_ERROR)];
                        }
                        throw new RuntimeException('Unexpected API: ' . $path);
                    }
                };
            }
            protected function hook(string $name, array $request): array {
                $this->hooks[] = $name;
                if ($name === 'fence') return ['ok' => true, 'legacy_publishers_fenced' => true];
                if ($name === 'preflight') return ['legacy_publishers_fenced' => true, 'source_unchanged' => true];
                if ($name === 'maintenance') return ['http_status' => 503, 'writes_blocked' => true, 'inflight_writers' => 0, 'new_requests_blocked' => true, 'publisher_exclusive' => true];
                if ($name === 'backup') {
                    if ($this->opts['fail_backup'] ?? false) throw new RuntimeException('Simulated mysqldump failure');
                    $dump = $request['backup'] . '/dump.sql';
                    file_put_contents($dump, "-- consistent dump\n");
                    $h = digest($dump);
                    return ['attempt' => $request['attempt'], 'created_at' => time(),
                        'artifact_id' => $this->opts['artifact_id'] ?? 14564933268,
                        'package_sha256' => $this->opts['pkg_sha'] ?? '',
                        'consistent' => true, 'source_uuid' => 'uol-mysql',
                        'dump_sha256' => $h, 'files' => [$dump => $h]];
                }
                if ($name === 'smoke') return ['ok' => true, 'http_status' => 200];
                return ['ok' => true];
            }
        };
    };

    // T2 + T3: Adocao do checkpoint PR #11 (5d1052f9) e publicacao do PR #12 (eb073346 / 14564933268) como Classe B!
    $filesV12 = array_replace($filesV11, ['app/Retirement.php' => "<?php // ret-v12-pr12\n"]);
    [$stage12, $pkg12Sha, $art12Id] = $buildStage('pr12', $c12, 44595050257, 69, 14564933268, 12, $filesV12);
    $pub12 = $makePub($cfg, ['artifact_id' => $art12Id, 'pkg_sha' => $pkg12Sha]);
    $res12 = $pub12->apply($stage12);
    ok(($res12['state'] ?? null) === 'validated' && ($res12['commit'] ?? null) === $c12,
        'T2/T3: Adota checkpoint PR #11 e publica PR #12 (eb073346 / 14564933268) como Classe B sem SHA pin manual');
    ok(in_array('maintenance', $pub12->hooks, true) && in_array('backup', $pub12->hooks, true),
        'T8: PR #12 executou drenagem completa (HTTP 503) e backup obrigatorio');

    // T5: Idempotencia (reexecutar mesmo artefato nao reinstala)
    unset($pub12);
    $pub12Again = $makePub($cfg, ['artifact_id' => $art12Id, 'pkg_sha' => $pkg12Sha]);
    $resAgain = $pub12Again->apply($stage12);
    ok(($resAgain['already_installed'] ?? false) === true && $pub12Again->hooks === [],
        'T5: Idempotencia confirmada — reexecucao do mesmo artefato nao reinstala');

    // T4: Segunda publicacao automatica consecutiva (estilo PR #14 / Classe A) SEM tocar em receiver-config.json!
    unset($pub12Again);
    $filesV14 = array_replace($filesV12, [
        'app/Game.php' => "<?php // game-v14-f04\n",
        'app/MatchParticipation.php' => "<?php // f04-r1\n",
    ]);
    [$stage14, $pkg14Sha, $art14Id] = $buildStage('pr14', $c14, 44946992958, 70, 14600000001, 14, $filesV14);
    $pub14 = $makePub($cfg, ['artifact_id' => $art14Id, 'pkg_sha' => $pkg14Sha]);
    $res14 = $pub14->apply($stage14);
    ok(($res14['state'] ?? null) === 'validated' && ($res14['commit'] ?? null) === $c14,
        'T4: Segunda publicacao consecutiva (PR #14 / Classe A) concluida sem qualquer ajuste manual no cPanel');
    ok(file_get_contents($prod . '/current/app/MatchParticipation.php') === "<?php // f04-r1\n",
        'T4: Novo arquivo de funcionalidade promovido atomicamente');

    // T9: Falha mid-apply + recuperacao segura via recover()
    unset($pub14);
    $filesV15 = array_replace($filesV14, ['app/Game.php' => "<?php // game-v15-recover\n"]);
    [$stage15, $pkg15Sha, $art15Id] = $buildStage('pr15', str_repeat('5', 40), 45000000001, 71, 14600000002, 15, $filesV15);
    $pubFail = $makePub($cfg, ['artifact_id' => $art15Id, 'pkg_sha' => $pkg15Sha, 'fail_promote_once' => true]);
    mustFail(fn() => $pubFail->apply($stage15), 'Promotion rename failed', 'T9: Falha mid-apply detectada e bloqueada');
    $drain = new RequestDrain($prod . '/gate');
    ok($drain->closed(), 'T9: Gate permanece fechado apos falha mid-apply');
    $rec = $pubFail->recover();
    ok(in_array($rec['state'] ?? null, ['recovered', 'validated'], true) && !$drain->closed(),
        'T9: Recuperacao oficial (--recover) conclui com estado estavel e reabre o gate');

    // ---------------------------------------------------------------------
    // TESTE 6, 7 & 11: Seguranca do artefato, mudancas criticas e RumoHub
    // ---------------------------------------------------------------------
    echo "\n--- Testes 6, 7 e 11: Seguranca de artefato, mudancas criticas e RumoHub ---\n";
    // T6: Proveniencia invalida (repo errado)
    mustFail(
        fn() => Evidence::validate($cfg, ['repository' => ['full_name' => 'attacker/rumo10'], 'workflow_id' => 398860623], [], [], []),
        'Wrong repository',
        'T6: Rejeita artefato de repositorio nao autorizado'
    );
    // T7: Mudanca critica (Hard Class C misto com Retirement.php + delecao)
    $oldTest = ['files' => ['app/Retirement.php' => 'a', 'app/Repository.php' => 'b', 'app/Game.php' => 'c']];
    $mixTest = ['files' => ['app/Retirement.php' => 'a2', 'app/Repository.php' => 'b2', 'app/Game.php' => 'c']];
    $delTest = ['files' => ['app/Retirement.php' => 'a2', 'app/Repository.php' => 'b']];
    $jobsOk = [['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success']];
    ok(UpdatePolicy::assess($oldTest, $mixTest, $policy, $jobsOk, $c11, $c12, ['app/Retirement.php'])['class'] === 'C',
        'T7: Rejeita alteracao critica mista (Retirement.php + Repository.php) como Classe C');
    ok(UpdatePolicy::assess($oldTest, $delTest, $policy, $jobsOk, $c11, $c12, ['app/Retirement.php'])['class'] === 'C',
        'T7: Rejeita delecao destrutiva de arquivo como Classe C');

    // T11: Integracao RumoHub (smokeUrls + isolamento de tabelas no banco compartilhado)
    $urls = MainAutomation::smokeUrls($cfg['main_automation']);
    ok(isset($urls['rumo10'], $urls['rumohub']), 'T11: Smoke obrigatorio cobre Rumo10 e RumoHub em hosts distintos');
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $manifestDb = DatabaseSchemaValidator::defaultManifest();
    $expected009 = DatabaseSchemaValidator::resolveExpectedTables($manifestDb, ['006', '006-seed', '007', '008', '009']);
    $db->exec('CREATE TABLE rumo10_migrations (version VARCHAR(40) PRIMARY KEY)');
    foreach (['006', '006-seed', '007', '008', '009'] as $ver) {
        $db->prepare('INSERT INTO rumo10_migrations(version) VALUES(?)')->execute([$ver]);
    }
    foreach ($expected009 as $tbl) {
        if ($tbl !== 'rumo10_migrations') $db->exec("CREATE TABLE `{$tbl}` (id INTEGER PRIMARY KEY)");
    }
    $db->exec('CREATE TABLE rumohub_accounts (id INTEGER PRIMARY KEY)');
    $valDb = DatabaseSchemaValidator::validate($db, $manifestDb, ['009']);
    ok($valDb['ok'] === true && !in_array('rumohub_accounts', $valDb['tables'], true),
        'T11: Validacao estrutural do banco preserva tabelas do RumoHub intactas');

    echo "\n=== TODOS OS {$passed} CHECKS DOS 12 TESTES DE ACEITACAO PASSARAM ===\n";
} finally {
    rrmdir($root);
}
