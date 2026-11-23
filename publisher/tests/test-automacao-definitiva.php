<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
require_once dirname(__DIR__).'/lib/LegacyFence.php';

use Rumo\Publisher\{DrainPublisher, GitHub, LegacyFence, MainAutomation, RequestDrain, UpdatePolicy};
use function Rumo\Publisher\{digest, need, record};

$passed = 0;
function ok(bool $cond, string $label): void {
    global $passed;
    if (!$cond) {
        throw new RuntimeException("FAIL: {$label}");
    }
    $passed++;
    echo "  [OK] {$label}\n";
}

function mustFail(callable $fn, string $expectedSubstr, string $label): void {
    try {
        $fn();
    } catch (Throwable $e) {
        if ($expectedSubstr === '' || str_contains($e->getMessage(), $expectedSubstr)) {
            ok(true, $label);
            return;
        }
        throw new RuntimeException("FAIL ({$label}): unexpected message '{$e->getMessage()}' (expected '{$expectedSubstr}')");
    }
    throw new RuntimeException("FAIL ({$label}): expected exception containing '{$expectedSubstr}', got none");
}

/** Creates a minimal valid tar.gz containing the provided relative path => contents map. */
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
            . '        '
            . '0'
            . str_pad('', 100, "\0")
            . "ustar\0" . '00'
            . str_pad('', 32, "\0")
            . str_pad('', 32, "\0")
            . str_pad('', 8, "\0")
            . str_pad('', 8, "\0")
            . str_pad('', 155, "\0")
            . str_pad('', 12, "\0");
        $sum = array_sum(unpack('C*', $header));
        $header = substr_replace($header, str_pad(decoct($sum), 6, '0', STR_PAD_LEFT)."\0 ", 148, 8);
        $tar .= $header . $content;
        $pad = (512 - ($size % 512)) % 512;
        if ($pad > 0) $tar .= str_repeat("\0", $pad);
    }
    $tar .= str_repeat("\0", 1024);
    $gz = gzopen($destFile, 'wb9');
    need($gz !== false, 'Cannot write synthetic tar.gz');
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

echo "=== R10-PUBLISHER-AUTOMACAO-DEFINITIVA-01 | SUITE DE TESTES ===\n\n";

$ownerId = 35331184;
$repo = 'rralvarez/rumo10';
$fromSha = '5d1052f9d85c550e38b6a36e4197cc5592fe85e2';
$toSha   = 'eb07334612f4e814bfec6f0a62eb605e7ffadb37';
$prHead  = str_repeat('c', 40);
$jobsOk  = [['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success']];
$policy  = [
    'schema' => 'rumo-update-policy-v1',
    'protected_contract_paths' => [
        'app/Database.php',
        'app/Repository.php',
        'public/.htaccess',
        'app/Retirement.php',
        'app/RetiredCareer.php',
    ],
    'approved_incremental_migrations' => [],
    'allow_unpinned_migrations' => true,
    'authorized_transitions' => [],
    'authorized_releases' => [
        '907427dabc363d055ff32ad53853d322639992fb' => ['label' => 'F01 Historical Baseline'],
    ],
];
$mainCfg = [
    'schema' => 'rumo-main-automation-v1',
    'owner_id' => $ownerId,
];

// -------------------------------------------------------------------------
// 1. Classe A ordinaria (estilo PR #14 / F04-R1)
// -------------------------------------------------------------------------
echo "--- 1. Release Classe A ordinaria ---\n";
$oldFiles = [
    'files' => [
        'app/Game.php' => hash('sha256', 'game-v1'),
        'app/Retirement.php' => hash('sha256', 'ret-v1'),
        'app/Database.php' => hash('sha256', 'db-v1'),
        'app/Repository.php' => hash('sha256', 'repo-v1'),
        'public/index.php' => hash('sha256', 'idx-v1'),
        'public/.htaccess' => hash('sha256', 'hta-v1'),
    ],
];
$classANew = ['files' => array_replace($oldFiles['files'], [
    'app/Game.php' => hash('sha256', 'game-v2'),
    'app/MatchParticipation.php' => hash('sha256', 'f04-v1'),
])];
$assessA = UpdatePolicy::assess($oldFiles, $classANew, $policy, $jobsOk, $fromSha, $toSha);
ok($assessA['class'] === 'A', 'Classe A ordinaria sem SHA pin manual');
$vA = [
    'commit' => $toSha,
    'run' => ['id' => 1001, 'merged_pr' => 14],
    'artifact_id' => 2001,
    'package_sha256' => str_repeat('a', 64),
    'envelope' => ['release_channel' => 'main', 'functional_commit' => $toSha],
];
MainAutomation::change($mainCfg, $vA, $assessA);
ok(true, 'MainAutomation::change aprova Classe A ordinaria automaticamente');

// -------------------------------------------------------------------------
// 2. Classe B autorizada via delegacao segura + bloqueio de Hard Class C
// -------------------------------------------------------------------------
echo "\n--- 2. Politica Classe B delegavel vs Hard Class C ---\n";
$retNew = ['files' => array_replace($oldFiles['files'], [
    'app/Retirement.php' => hash('sha256', 'ret-v2'),
])];
$assessNoOwner = UpdatePolicy::assess($oldFiles, $retNew, $policy, $jobsOk, $fromSha, $toSha);
ok($assessNoOwner['class'] === 'C', 'Sem prova de owner-merge, app/Retirement.php permanece Classe C');

$delegable = MainAutomation::delegableClassBPaths($mainCfg);
ok($delegable === ['app/Retirement.php', 'app/RetiredCareer.php'], 'Default delegavel restrito a app/Retirement.php e app/RetiredCareer.php');

$assessOwnerB = UpdatePolicy::assess($oldFiles, $retNew, $policy, $jobsOk, $fromSha, $toSha, $delegable);
ok($assessOwnerB['class'] === 'B' && $assessOwnerB['requires_full_drain'] === true,
    'Com prova de owner-merge, app/Retirement.php promove para Classe B com full drain');
ok($assessOwnerB['owner_authorized_protected_paths'] === ['app/Retirement.php'],
    'Assessment registra explicitamente o caminho protegido autorizado pelo owner');
MainAutomation::change($mainCfg, $vA, $assessOwnerB);
ok(true, 'MainAutomation::change aprova Classe B delegavel sem par de SHAs manual');

// Defesa em profundidade: Hard Class C NUNCA pode ser delegado!
foreach ([
    'app/Database.php',
    'app/Repository.php',
    'public/index.php',
    'public/.htaccess',
    'publisher/lib/Publisher.php',
    '.env',
    'app/bootstrap.php',
] as $hardPath) {
    mustFail(
        fn() => MainAutomation::delegableClassBPaths(['owner_id' => $ownerId, 'owner_authorized_class_b_paths' => [$hardPath]]),
        'Hard Class C path cannot be delegated',
        "Config privada recusa delegar Hard Class C: {$hardPath}"
    );
}

// Mesmo tentando injetar direto no parametro de UpdatePolicy::assess()
$dbNew = ['files' => array_replace($oldFiles['files'], ['app/Database.php' => hash('sha256', 'db-v2')])];
$assessForgedDb = UpdatePolicy::assess($oldFiles, $dbNew, $policy, $jobsOk, $fromSha, $toSha, ['app/Database.php']);
ok($assessForgedDb['class'] === 'C', 'UpdatePolicy::assess ignora tentativa de delegar app/Database.php (permanece Classe C)');

$idxNew = ['files' => array_replace($oldFiles['files'], ['public/index.php' => hash('sha256', 'idx-v2')])];
$assessForgedIdx = UpdatePolicy::assess($oldFiles, $idxNew, $policy, $jobsOk, $fromSha, $toSha, ['public/index.php']);
ok($assessForgedIdx['class'] === 'C', 'UpdatePolicy::assess ignora tentativa de delegar public/index.php (permanece Classe C)');

// Mistura de app/Retirement.php + delecao de arquivo -> permanece Classe C!
$retPlusDel = ['files' => array_diff_key($retNew['files'], ['app/Game.php' => true])];
$assessDel = UpdatePolicy::assess($oldFiles, $retPlusDel, $policy, $jobsOk, $fromSha, $toSha, $delegable);
ok($assessDel['class'] === 'C', 'Delecao de arquivo combinada com app/Retirement.php permanece Classe C');

// -------------------------------------------------------------------------
// 3. LegacyFence: protecao estrita preservada (sem flexibilizacao por regex)
// -------------------------------------------------------------------------
echo "\n--- 3. LegacyFence estrito preservado ---\n";
$fenceProfile = [
    'manual_channels_fenced' => true,
    'repositories' => [
        'rralvarez/rumo10' => ['branch' => 'main', 'approved_active' => []],
        'rralvarez/rumohub' => ['branch' => 'main', 'approved_active' => []],
    ],
];
$makeFenceFetcher = static function (?string $extraState = null, bool $legacyEnabled = false): callable {
    return static function (string $r, string $path) use ($extraState, $legacyEnabled): array {
        if (str_contains($path, '/actions/workflows?')) {
            $w = [
                ['id' => 1, 'path' => '.github/workflows/production.yml', 'state' => $legacyEnabled ? 'active' : 'disabled_manually'],
                ['id' => 2, 'path' => '.github/workflows/production-ftps.yml', 'state' => 'disabled_manually'],
            ];
            if ($extraState !== null) {
                $w[] = ['id' => 424312740, 'path' => '.github/workflows/audit-escudos-20261119.yml', 'state' => $extraState];
            }
            return ['total_count' => count($w), 'workflows' => $w];
        }
        return ['total_count' => 0, 'workflow_runs' => []];
    };
};

$fenceDisabledOk = LegacyFence::verify($fenceProfile, $makeFenceFetcher('disabled_manually'));
ok(($fenceDisabledOk['ok'] ?? false) === true, 'LegacyFence aprova quando workflows auxiliares indevidos estao em disabled_manually');

mustFail(
    fn() => LegacyFence::verify($fenceProfile, $makeFenceFetcher('active')),
    'Unapproved active workflow',
    'LegacyFence estrito bloqueia qualquer workflow ativo nao-pinado (zero bypass por regex)'
);
mustFail(
    fn() => LegacyFence::verify($fenceProfile, $makeFenceFetcher('disabled_manually', true)),
    'Legacy publication workflow must be explicitly disabled',
    'LegacyFence estrito bloqueia production.yml ativo'
);

// -------------------------------------------------------------------------
// 4. Fluxo E2E de DrainPublisher: Classe B owner-merged, backup fail-closed,
//    tentativa nao autorizada e recuperacao (--recover)
// -------------------------------------------------------------------------
echo "\n--- 4. Fluxo E2E DrainPublisher (Classe B, fail-closed e --recover) ---\n";

$makeSandbox = static function (array $opts = []) use ($ownerId, $repo, $fromSha, $toSha, $prHead, $policy): array {
    $root = sys_get_temp_dir() . '/r10-auto-def-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    foreach (['private', 'control', 'current/app', 'current/public', 'public'] as $sub) {
        mkdir($root . '/' . $sub, 0700, true);
    }
    RequestDrain::provision($root . '/gate');

    // Baseline instalado
    $curApp = $root . '/current/app';
    file_put_contents($curApp . '/bootstrap.php', "<?php // boot-v1\n");
    file_put_contents($curApp . '/Game.php', "<?php // v1\n");
    file_put_contents($curApp . '/PlayerDevelopment.php', "<?php // v3\n");
    file_put_contents($curApp . '/Retirement.php', "<?php // ret-v1\n");
    file_put_contents($curApp . '/Database.php', "<?php // db-v1\n");
    file_put_contents($root . '/current/public/index.php', "<?php // idx-v1\n");
    file_put_contents($root . '/current/public/.htaccess', "RewriteEngine On\n");
    file_put_contents($root . '/public/index.php', "<?php // idx-v1\n");
    file_put_contents($root . '/public/.htaccess', "RewriteEngine On\n");
    $rumohubEntry = $root . '/rumohub-index.php';
    file_put_contents($rumohubEntry, "<?php // hub\n");

    $tokenFile = $root . '/private/token.txt';
    $statusTokenFile = $root . '/private/status-token.txt';
    file_put_contents($tokenFile, 'synthetic-token');
    file_put_contents($statusTokenFile, 'synthetic-status-token');

    $manifest = [
        'commit' => $fromSha,
        'checksums' => [
            'app/bootstrap.php' => digest($curApp . '/bootstrap.php'),
            'app/Game.php' => digest($curApp . '/Game.php'),
            'app/PlayerDevelopment.php' => digest($curApp . '/PlayerDevelopment.php'),
            'app/Retirement.php' => digest($curApp . '/Retirement.php'),
            'app/Database.php' => digest($curApp . '/Database.php'),
            'public/index.php' => digest($root . '/public/index.php'),
            'public/.htaccess' => digest($root . '/public/.htaccess'),
        ],
    ];
    record($root . '/current/build-manifest.json', $manifest);
    record($root . '/current/release.json', [
        'project' => 'rumo10',
        'commit' => $fromSha,
        'files' => $manifest['checksums'],
    ]);
    record($root . '/private/installed.json', [
        'release_channel' => 'main',
        'commit' => $fromSha,
        'functional_commit' => $fromSha,
        'artifact_id' => 100,
        'run_id' => 10,
        'run_number' => 10,
        'package_sha256' => str_repeat('0', 64),
        'manifest_sha256' => digest($root . '/current/release.json'),
        'build_manifest_sha256' => digest($root . '/current/build-manifest.json'),
    ]);

    // Monta pacote candidato (por padrao altera app/Retirement.php — cenario PR #12!)
    $pkgFiles = [
        'app/bootstrap.php' => "<?php // boot-v1\n",
        'app/Game.php' => "<?php // v1\n",
        'app/PlayerDevelopment.php' => "<?php // v3\n",
        'app/Retirement.php' => $opts['retirement_content'] ?? "<?php // ret-v2-owner-approved\n",
        'app/Database.php' => $opts['database_content'] ?? "<?php // db-v1\n",
        'public/index.php' => "<?php // idx-v1\n",
        'public/.htaccess' => "RewriteEngine On\n",
    ];
    $fileHashes = [];
    foreach ($pkgFiles as $rel => $content) {
        $fileHashes[$rel] = hash('sha256', $content);
    }
    ksort($fileHashes);
    $releaseMeta = [
        'project' => 'rumo10',
        'commit' => $toSha,
        'functional_commit' => $toSha,
        'data_contract' => 'rumo10-development-v3',
        'data_contracts' => ['rumo10-development-v3'],
        'required_migrations' => [],
        'files' => $fileHashes,
    ];
    $pkgFiles['release.json'] = json_encode($releaseMeta, JSON_THROW_ON_ERROR);
    $stage = $root . '/private/stage-test';
    mkdir($stage . '/payload/app', 0700, true);
    mkdir($stage . '/payload/public', 0700, true);
    foreach ($pkgFiles as $rel => $content) {
        file_put_contents($stage . '/payload/' . $rel, $content);
    }
    $tarPath = $stage . '/release.tar.gz';
    makeTarGz($tarPath, $pkgFiles);
    $pkgSha = digest($tarPath);

    $run = [
        'id' => 555,
        'run_attempt' => 1,
        'run_number' => 42,
        'workflow_id' => 7,
        'head_sha' => $toSha,
        'head_branch' => 'main',
        'event' => 'push',
        'status' => 'completed',
        'conclusion' => 'success',
        'repository' => ['full_name' => $repo],
        'merged_pr' => $opts['merged_pr'] ?? 12,
    ];
    $artifact = [
        'id' => 777,
        'name' => 'release-rumo10-' . $toSha,
        'expired' => false,
        'digest' => 'sha256:' . str_repeat('a', 64),
        'workflow_run' => ['id' => 555, 'head_sha' => $toSha],
    ];
    $reqJobs = ['syntax', 'regression', 'compatibility', 'publisher-safety', 'rumo10-ci-gate', 'artifact'];
    $jobs = array_map(static fn($name) => [
        'name' => $name,
        'status' => 'completed',
        'conclusion' => 'success',
        'head_sha' => $toSha,
        'run_id' => 555,
        '_requested_attempt' => 1,
    ], $reqJobs);
    $envelope = [
        'schema' => 'rumo-pull-v1',
        'project' => 'rumo10',
        'repository' => $repo,
        'branch' => 'main',
        'release_channel' => 'main',
        'commit' => $toSha,
        'source_commit' => $toSha,
        'functional_commit' => $toSha,
        'workflow_id' => 7,
        'run_id' => 555,
        'run_attempt' => 1,
        'run_number' => 42,
        'package_sha256' => $pkgSha,
        'data_contract' => 'rumo10-development-v3',
        'data_contracts' => ['rumo10-development-v3'],
        'required_migrations' => [],
    ];
    record($stage . '/ready.json', compact('run', 'artifact', 'jobs', 'envelope'));

    $fenceScript = $root . '/private/mock-fence.php';
    file_put_contents($fenceScript, "<?php echo json_encode(['ok'=>true,'legacy_publishers_fenced'=>true]);\n");
    $dummyEnv = $root . '/private/app.env';
    file_put_contents($dummyEnv, "APP_ENV=test\n");
    $cfg = [
        'schema' => 'rumo-pull-v1',
        'php' => PHP_BINARY,
        'project' => 'rumo10',
        'repository' => $repo,
        'branch' => 'main',
        'release_channel' => 'main',
        'workflow_id' => 7,
        'required_jobs' => $reqJobs,
        'data_contracts' => ['rumo10-development-v3'],
        'baseline_data_contracts' => ['rumo10-development-v3'],
        'pinned_controls' => [
            $root . '/public/.htaccess' => digest($root . '/public/.htaccess'),
            $root . '/public/index.php' => digest($root . '/public/index.php'),
        ],
        'backup_roots' => ['env' => $dummyEnv],
        'token_file' => $tokenFile,
        'account_mutex' => $root . '/private/account.mutex',
        'private' => $root . '/private',
        'control' => $root . '/control',
        'current' => $root . '/current',
        'public' => $root . '/public',
        'apply_enabled' => true,
        'homologation' => [
            'maintenance' => true,
            'recovery' => true,
            'legacy_publishers_fenced' => true,
            'backup' => true,
        ],
        'request_gate' => [
            'root' => $root . '/gate',
            'coverage_approved' => true,
            'projects' => ['rumo10', 'rumohub'],
            'entrypoints' => [
                $root . '/public/index.php' => digest($root . '/public/index.php'),
                $rumohubEntry => digest($rumohubEntry),
            ],
            'timeout_seconds' => 2,
        ],
        'update_policy' => $policy,
        'main_automation' => [
            'schema' => 'rumo-main-automation-v1',
            'owner_id' => $ownerId,
            'status_token_file' => $statusTokenFile,
            'public_smoke_urls' => [
                'rumo10' => 'https://rumo10.example.test/',
                'rumohub' => 'https://rumohub.example.test/',
            ],
        ],
        'hooks' => [
            'fence' => [
                'script' => $fenceScript,
                'sha256' => digest($fenceScript),
                'args' => [],
            ],
        ],
    ];

    $mergedBy = $opts['merged_by'] ?? $ownerId;
    $backupFails = $opts['backup_fails'] ?? false;
    $failPromoteOnce = $opts['fail_promote_once'] ?? false;
    $pkgShaForHook = $pkgSha;

    $pub = new class($cfg, $repo, $fromSha, $toSha, $prHead, $mergedBy, $backupFails, $failPromoteOnce, $pkgShaForHook) extends DrainPublisher {
        public array $calledHooks = [];
        public bool $ownerApiQueried = false;
        private bool $promotedFailed = false;
        public function __construct(
            private array $testCfg,
            private string $repo,
            private string $fromSha,
            private string $toSha,
            private string $prHead,
            private int $mergedBy,
            private bool $backupFails,
            private bool $failPromoteOnce,
            private string $pkgSha
        ) {
            parent::__construct($testCfg);
        }
        protected function promote(string $a, string $b): bool {
            if ($this->failPromoteOnce && !$this->promotedFailed && str_ends_with(str_replace('\\', '/', $b), 'app/Retirement.php')) {
                $this->promotedFailed = true;
                return false;
            }
            return parent::promote($a, $b);
        }
        protected function source(): GitHub {
            $self = $this;
            return new class($this->testCfg, $this->repo, $this->fromSha, $this->toSha, $this->prHead, $this->mergedBy, $self) extends GitHub {
                public function __construct(
                    array $c,
                    private string $repo,
                    private string $fromSha,
                    private string $toSha,
                    private string $prHead,
                    private int $mergedBy,
                    private object $parentPub
                ) {
                    parent::__construct($c);
                }
                protected function request(string $url, bool $auth, int $limit, ?string $out = null): array {
                    $this->parentPub->ownerApiQueried = true;
                    $path = parse_url($url, PHP_URL_PATH);
                    if (str_contains($path, '/pulls/')) {
                        $pr = [
                            'number' => 12,
                            'state' => 'closed',
                            'merged' => true,
                            'merged_at' => '2026-11-20T12:00:00Z',
                            'merged_by' => ['id' => $this->mergedBy],
                            'base' => ['repo' => ['full_name' => $this->repo], 'ref' => 'main'],
                            'merge_commit_sha' => $this->toSha,
                            'head' => ['sha' => $this->prHead],
                        ];
                        return ['status' => 200, 'body' => json_encode($pr, JSON_THROW_ON_ERROR)];
                    }
                    if (str_contains($path, '/commits/')) {
                        $commit = [
                            'sha' => $this->toSha,
                            'parents' => [['sha' => $this->fromSha], ['sha' => $this->prHead]],
                        ];
                        return ['status' => 200, 'body' => json_encode($commit, JSON_THROW_ON_ERROR)];
                    }
                    throw new RuntimeException('Unexpected GitHub API call: ' . $path);
                }
            };
        }
        protected function assertBackupPrerequisites(bool $classA = false): void {
            // In synthetic tests, backup hook is mocked below.
        }
        protected function hook(string $name, array $request): array {
            $this->calledHooks[] = $name;
            if ($name === 'fence') {
                return ['ok' => true, 'legacy_publishers_fenced' => true];
            }
            if ($name === 'preflight') {
                return ['legacy_publishers_fenced' => true, 'source_unchanged' => true];
            }
            if ($name === 'maintenance') {
                return ['http_status' => 503, 'writes_blocked' => true, 'inflight_writers' => 0, 'new_requests_blocked' => true, 'publisher_exclusive' => true];
            }
            if ($name === 'backup') {
                if ($this->backupFails) {
                    throw new RuntimeException('Backup hook failed: mysqldump error');
                }
                $dumpFile = $request['backup'] . '/dump.sql';
                file_put_contents($dumpFile, "-- synthetic consistent dump\n");
                $dumpHash = digest($dumpFile);
                return [
                    'attempt' => $request['attempt'],
                    'created_at' => time(),
                    'artifact_id' => 777,
                    'package_sha256' => $this->pkgSha,
                    'consistent' => true,
                    'source_uuid' => 'synthetic-mysql',
                    'dump_sha256' => $dumpHash,
                    'files' => [$dumpFile => $dumpHash],
                ];
            }
            if ($name === 'migrate') {
                return ['ok' => true, 'applied' => []];
            }
            if ($name === 'smoke') {
                return ['ok' => true, 'http_status' => 200];
            }
            if ($name === 'restore') {
                return ['ok' => true, 'restored' => true];
            }
            return ['ok' => true];
        }
    };

    return [$root, $stage, $pub, $cfg];
};

// 4A. Cenario PR #12 real: app/Retirement.php alterado, SEM par de SHAs em authorized_transitions, mergeado pelo owner -> SUCESSO!
[$rootA, $stageA, $pubA, $cfgA] = $makeSandbox();
try {
    $resA = $pubA->apply($stageA);
    ok(($resA['state'] ?? null) === 'validated', 'E2E Classe B (app/Retirement.php) aplica e valida sem SHA pin manual');
    ok($pubA->ownerApiQueried === true, 'Receptor consultou API do GitHub para comprovar merge do owner');
    ok(in_array('backup', $pubA->calledHooks, true), 'Classe B executou obrigatoriamente hook de backup');
    ok(file_get_contents($rootA . '/current/app/Retirement.php') === "<?php // ret-v2-owner-approved\n",
        'Arquivo app/Retirement.php atualizado atomicamente');
    $drainA = new RequestDrain($rootA . '/gate');
    ok(!$drainA->closed(), 'Gate reaberto apos validacao');
} finally {
    rrmdir($rootA);
}

// 4B. Mesmo cenario PR #12, mas mergeado por terceiros (merged_by != owner_id) -> BLOQUEADO!
[$rootB, $stageB, $pubB] = $makeSandbox(['merged_by' => 999999]);
try {
    mustFail(
        fn() => $pubB->apply($stageB),
        'Exact main merge by the approved owner not confirmed',
        'E2E bloqueia Classe B quando PR nao foi mergeado pelo owner_id'
    );
    ok(file_get_contents($rootB . '/current/app/Retirement.php') === "<?php // ret-v1\n",
        'Baseline preservado intacto apos bloqueio de merge nao-autorizado');
} finally {
    rrmdir($rootB);
}

// 4C. Alteracao Hard Class C (app/Database.php) mesmo com merge do owner -> BLOQUEADO!
[$rootC, $stageC, $pubC] = $makeSandbox(['database_content' => "<?php // db-v2-unauthorized\n"]);
try {
    mustFail(
        fn() => $pubC->apply($stageC),
        'Class C requires separately proven exclusive window',
        'E2E bloqueia app/Database.php como Classe C mesmo com PR mergeado pelo owner'
    );
    ok($pubC->ownerApiQueried === false, 'Hard Class C falha antes mesmo de aceitar delegacao');
} finally {
    rrmdir($rootC);
}

// 4D. Falha de backup em Classe B -> fail-closed (nada alterado em current/)!
[$rootD, $stageD, $pubD] = $makeSandbox(['backup_fails' => true]);
try {
    mustFail(
        fn() => $pubD->apply($stageD),
        'Backup hook failed',
        'E2E aborta Classe B (fail-closed) quando backup falha'
    );
    ok(file_get_contents($rootD . '/current/app/Retirement.php') === "<?php // ret-v1\n",
        'Nenhum arquivo de producao alterado quando backup falha');
} finally {
    rrmdir($rootD);
}

// 4E. Falha durante promote mid-apply + recuperacao oficial (--recover) restaura baseline e abre gate!
[$rootE, $stageE, $pubE] = $makeSandbox(['fail_promote_once' => true]);
try {
    mustFail(
        fn() => $pubE->apply($stageE),
        'Promotion rename failed',
        'E2E detecta falha durante promote e entra em estado blocked com gate fechado'
    );
    $drainE = new RequestDrain($rootE . '/gate');
    ok($drainE->closed(), 'Gate permanece fechado apos falha mid-apply para seguranca');
    $recE = $pubE->recover();
    $recState = $recE['state'] ?? null;
    ok(in_array($recState, ['recovered', 'validated'], true),
        "Recuperacao oficial (--recover) conclui com estado estavel ({$recState})");
    $expectedRet = $recState === 'recovered' ? "<?php // ret-v1\n" : "<?php // ret-v2-owner-approved\n";
    ok(file_get_contents($rootE . '/current/app/Retirement.php') === $expectedRet,
        'Recuperacao oficial garante integridade do arquivo app/Retirement.php compativel com o estado final');
    ok(!$drainE->closed(), 'Recuperacao oficial reabre gate de requisicoes com seguranca');
} finally {
    rrmdir($rootE);
}

echo "\n=== TODOS OS {$passed} TESTES PASSARAM ===\n";
