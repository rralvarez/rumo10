<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/lib/DrainPublisher.php';
require_once dirname(__DIR__).'/lib/GitHub.php';
require_once dirname(__DIR__).'/lib/LegacyFence.php';

use Rumo\Publisher\{DrainPublisher, GitHub, LegacyFence, MainAutomation, Publisher, RequestDrain, UpdatePolicy};
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

echo "=== R10-PUBLISHER | TESTES DIRECIONADOS AOS 3 ACHADOS DO CODEX (P0 + P1 + P1) ===\n\n";

$ownerId = 35331184;
$repo = 'rralvarez/rumo10';
$c0 = '5d1052f9d85c550e38b6a36e4197cc5592fe85e2'; // PR #11 (instalado na UOL)
$c1 = 'eb07334612f4e814bfec6f0a62eb605e7ffadb37'; // PR #12 (altera app/Retirement.php)
$c2 = 'b73d3c43800ce482e806099b79195de1952efcde'; // PR #14 (altera app/Game.php)
$pr12Head = str_repeat('1', 40);
$pr14Head = str_repeat('2', 40);
$jobsOk = [['name' => 'compatibility', 'status' => 'completed', 'conclusion' => 'success']];

$basePolicy = [
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
$mainCfg = ['schema' => 'rumo-main-automation-v1', 'owner_id' => $ownerId];

// -------------------------------------------------------------------------
// ACHADO P0: Preservacao do contrato real do receptor da UOL (4cb11dc)
// -------------------------------------------------------------------------
echo "--- 1. [P0] Compatibilidade com receptor produtivo da UOL ---\n";
ok(method_exists(Publisher::class, 'assertAutomaticStage'),
    'Publisher preserva assertAutomaticStage() exigido pelo DrainPublisher da UOL');
$rfBackup = new ReflectionMethod(Publisher::class, 'assertBackupPrerequisites');
ok($rfBackup->getNumberOfRequiredParameters() === 0 && $rfBackup->getNumberOfParameters() === 0,
    'assertBackupPrerequisites() preserva assinatura da UOL (sem bypass de Classe A)');
$rfProof = new ReflectionMethod(MainAutomation::class, 'proof');
ok($rfProof->getNumberOfParameters() === 3,
    'MainAutomation::proof() exige backup consistente para todas as releases (Classes A e B)');

// -------------------------------------------------------------------------
// ACHADO P1-A: Hard Class C independente de protected_contract_paths + teste misto
// -------------------------------------------------------------------------
echo "\n--- 2. [P1] Hard Class C independente de protected_contract_paths + teste misto ---\n";
$oldFiles = [
    'files' => [
        'app/bootstrap.php' => hash('sha256', 'boot-v1'),
        'app/Game.php' => hash('sha256', 'game-v1'),
        'app/Retirement.php' => hash('sha256', 'ret-v1'),
        'app/Database.php' => hash('sha256', 'db-v1'),
        'app/Repository.php' => hash('sha256', 'repo-v1'),
        'public/index.php' => hash('sha256', 'idx-v1'),
        'public/.htaccess' => hash('sha256', 'hta-v1'),
    ],
];
$delegable = MainAutomation::delegableClassBPaths($mainCfg);

// Politica SEM protected_contract_paths: Hard Class C ainda deve bloquear tudo!
$emptyProtectedPolicy = array_replace($basePolicy, ['protected_contract_paths' => []]);
foreach (['app/Repository.php', 'app/Database.php', 'app/bootstrap.php', 'public/index.php', 'public/.htaccess'] as $hardPath) {
    $mod = ['files' => array_replace($oldFiles['files'], [$hardPath => hash('sha256', 'tampered')])];
    $res = UpdatePolicy::assess($oldFiles, $mod, $emptyProtectedPolicy, $jobsOk, $c0, $c1, $delegable);
    ok($res['class'] === 'C', "Hard Class C bloqueia {$hardPath} mesmo com protected_contract_paths vazio");
}

// Teste misto obrigatorio: app/Retirement.php + app/Repository.php (com e sem protected_contract_paths)
$mixedRetRepo = ['files' => array_replace($oldFiles['files'], [
    'app/Retirement.php' => hash('sha256', 'ret-v2'),
    'app/Repository.php' => hash('sha256', 'repo-v2'),
])];
$resMixFull = UpdatePolicy::assess($oldFiles, $mixedRetRepo, $basePolicy, $jobsOk, $c0, $c1, $delegable);
ok($resMixFull['class'] === 'C', 'Teste misto Retirement.php + Repository.php permanece Classe C (politica completa)');

$policyMissingRepo = array_replace($basePolicy, ['protected_contract_paths' => ['app/Retirement.php']]);
$resMixOmitted = UpdatePolicy::assess($oldFiles, $mixedRetRepo, $policyMissingRepo, $jobsOk, $c0, $c1, $delegable);
ok($resMixOmitted['class'] === 'C', 'Teste misto Retirement.php + Repository.php permanece Classe C mesmo se Repository.php for omitido de protected_contract_paths');

// -------------------------------------------------------------------------
// ACHADO P1-B: Vinculacao por caminho ao merge autenticado que o introduziu
// -------------------------------------------------------------------------
echo "\n--- 3. [P1] Vinculacao por caminho ao merge que introduziu a alteracao ---\n";

$makeHistoryGitHub = static function (array $commits, array $compares, array $pullsByCommit, array $prDetails) use ($repo, $ownerId): GitHub {
    $tmpTok = tempnam(sys_get_temp_dir(), 'r10tok');
    file_put_contents($tmpTok, 'synthetic-token');
    $cfg = [
        'repository' => $repo,
        'token_file' => $tmpTok,
        'main_automation' => ['owner_id' => $ownerId],
    ];
    return new class($cfg, $commits, $compares, $pullsByCommit, $prDetails, $tmpTok) extends GitHub {
        public function __construct(
            array $c,
            private array $commits,
            private array $compares,
            private array $pullsByCommit,
            private array $prDetails,
            private string $tmpTok
        ) {
            parent::__construct($c);
        }
        public function __destruct() { @unlink($this->tmpTok); }
        protected function request(string $url, bool $auth, int $limit, ?string $out = null): array {
            $path = parse_url($url, PHP_URL_PATH);
            if (preg_match('#/compare/([a-f0-9]{40})\.\.\.([a-f0-9]{40})$#', $path, $m)) {
                $key = $m[1].'...'.$m[2];
                need(isset($this->compares[$key]), 'Mock compare missing: '.$key);
                return ['status' => 200, 'body' => json_encode($this->compares[$key], JSON_THROW_ON_ERROR)];
            }
            if (preg_match('#/commits/([a-f0-9]{40})/pulls$#', $path, $m)) {
                $list = $this->pullsByCommit[$m[1]] ?? [];
                return ['status' => 200, 'body' => json_encode($list, JSON_THROW_ON_ERROR)];
            }
            if (preg_match('#/commits/([a-f0-9]{40})$#', $path, $m)) {
                need(isset($this->commits[$m[1]]), 'Mock commit missing: '.$m[1]);
                return ['status' => 200, 'body' => json_encode($this->commits[$m[1]], JSON_THROW_ON_ERROR)];
            }
            if (preg_match('#/pulls/(\d+)$#', $path, $m)) {
                $num = (int)$m[1];
                need(isset($this->prDetails[$num]), 'Mock PR missing: '.$num);
                return ['status' => 200, 'body' => json_encode($this->prDetails[$num], JSON_THROW_ON_ERROR)];
            }
            throw new RuntimeException('Unexpected API path: '.$path);
        }
    };
};

// 3A: Acumulado legitimo: C0 -> C1 (PR #12 owner altera Retirement.php) -> C2 (PR #14 owner altera Game.php)
$ghLegit = $makeHistoryGitHub(
    [
        $c1 => ['sha' => $c1, 'parents' => [['sha' => $c0], ['sha' => $pr12Head]]],
        $c2 => ['sha' => $c2, 'parents' => [['sha' => $c1], ['sha' => $pr14Head]]],
    ],
    [
        "{$c0}...{$c2}" => ['status' => 'ahead', 'merge_base_commit' => ['sha' => $c0]],
        "{$c1}...{$c2}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Game.php']]],
        "{$c0}...{$c1}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Retirement.php']]],
    ],
    [
        $c1 => [['number' => 12, 'state' => 'closed', 'merged_at' => '2026-11-20T12:00:00Z', 'merge_commit_sha' => $c1, 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main']]],
        $c2 => [['number' => 14, 'state' => 'closed', 'merged_at' => '2026-11-21T12:00:00Z', 'merge_commit_sha' => $c2, 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main']]],
    ],
    [
        12 => ['number' => 12, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-20T12:00:00Z', 'merged_by' => ['id' => $ownerId], 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main'], 'merge_commit_sha' => $c1, 'head' => ['sha' => $pr12Head]],
        14 => ['number' => 14, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-21T12:00:00Z', 'merged_by' => ['id' => $ownerId], 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main'], 'merge_commit_sha' => $c2, 'head' => ['sha' => $pr14Head]],
    ]
);
$proofsLegit = $ghLegit->assertOwnerMergedDelegatedPaths($c0, $c2, ['app/Retirement.php']);
ok(($proofsLegit['app/Retirement.php'][0]['pr'] ?? null) === 12 && ($proofsLegit['app/Retirement.php'][0]['commit'] ?? null) === $c1,
    'Diferenca acumulada C0->C1(PR #12)->C2(PR #14) vincula app/Retirement.php exatamente ao PR #12 do owner');

// 3B: Ataque de lavagem: C1 altera Retirement.php por TERCEIRO (id=999999), depois owner faz merge C2 alterando Game.php
$ghLaunder = $makeHistoryGitHub(
    [
        $c1 => ['sha' => $c1, 'parents' => [['sha' => $c0], ['sha' => $pr12Head]]],
        $c2 => ['sha' => $c2, 'parents' => [['sha' => $c1], ['sha' => $pr14Head]]],
    ],
    [
        "{$c0}...{$c2}" => ['status' => 'ahead', 'merge_base_commit' => ['sha' => $c0]],
        "{$c1}...{$c2}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Game.php']]],
        "{$c0}...{$c1}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Retirement.php']]],
    ],
    [
        $c1 => [['number' => 12, 'state' => 'closed', 'merged_at' => '2026-11-20T12:00:00Z', 'merge_commit_sha' => $c1, 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main']]],
        $c2 => [['number' => 14, 'state' => 'closed', 'merged_at' => '2026-11-21T12:00:00Z', 'merge_commit_sha' => $c2, 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main']]],
    ],
    [
        12 => ['number' => 12, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-20T12:00:00Z', 'merged_by' => ['id' => 999999], 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main'], 'merge_commit_sha' => $c1, 'head' => ['sha' => $pr12Head]],
        14 => ['number' => 14, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-21T12:00:00Z', 'merged_by' => ['id' => $ownerId], 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main'], 'merge_commit_sha' => $c2, 'head' => ['sha' => $pr14Head]],
    ]
);
mustFail(
    fn() => $ghLaunder->assertOwnerMergedDelegatedPaths($c0, $c2, ['app/Retirement.php']),
    'Exact main merge by the approved owner not confirmed',
    'Merge posterior do owner (C2) NAO autoriza alteracao anterior de terceiro (C1) em app/Retirement.php'
);

// 3C: Push direto em C1 (sem PR, 1 unico pai), seguido de merge do owner em C2 -> BLOQUEADO!
$ghDirectPush = $makeHistoryGitHub(
    [
        $c1 => ['sha' => $c1, 'parents' => [['sha' => $c0]]],
        $c2 => ['sha' => $c2, 'parents' => [['sha' => $c1], ['sha' => $pr14Head]]],
    ],
    [
        "{$c0}...{$c2}" => ['status' => 'ahead', 'merge_base_commit' => ['sha' => $c0]],
        "{$c1}...{$c2}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Game.php']]],
        "{$c0}...{$c1}" => ['status' => 'ahead', 'files' => [['filename' => 'app/Retirement.php']]],
    ],
    [
        $c1 => [],
        $c2 => [['number' => 14, 'state' => 'closed', 'merged_at' => '2026-11-21T12:00:00Z', 'merge_commit_sha' => $c2, 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main']]],
    ],
    [
        14 => ['number' => 14, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-21T12:00:00Z', 'merged_by' => ['id' => $ownerId], 'base' => ['repo' => ['full_name' => $repo], 'ref' => 'main'], 'merge_commit_sha' => $c2, 'head' => ['sha' => $pr14Head]],
    ]
);
mustFail(
    fn() => $ghDirectPush->assertOwnerMergedDelegatedPaths($c0, $c2, ['app/Retirement.php']),
    'Exact merged main PR not found',
    'Push direto anterior (C1) em app/Retirement.php permanece bloqueado mesmo apos merge posterior do owner (C2)'
);

// -------------------------------------------------------------------------
// 4. LegacyFence estrito preservado (sem alteracoes)
// -------------------------------------------------------------------------
echo "\n--- 4. LegacyFence estrito preservado ---\n";
$fenceProfile = [
    'manual_channels_fenced' => true,
    'repositories' => [
        'rralvarez/rumo10' => ['branch' => 'main', 'approved_active' => []],
        'rralvarez/rumohub' => ['branch' => 'main', 'approved_active' => []],
    ],
];
$makeFence = static fn(?string $extraState = null) => static function (string $r, string $path) use ($extraState): array {
    if (str_contains($path, '/actions/workflows?')) {
        $w = [
            ['id' => 1, 'path' => '.github/workflows/production.yml', 'state' => 'disabled_manually'],
            ['id' => 2, 'path' => '.github/workflows/production-ftps.yml', 'state' => 'disabled_manually'],
        ];
        if ($extraState !== null) {
            $w[] = ['id' => 424312740, 'path' => '.github/workflows/audit-escudos-20261119.yml', 'state' => $extraState];
        }
        return ['total_count' => count($w), 'workflows' => $w];
    }
    return ['total_count' => 0, 'workflow_runs' => []];
};
ok(LegacyFence::verify($fenceProfile, $makeFence('disabled_manually'))['ok'] === true,
    'LegacyFence estrito aprova com workflows auxiliares em disabled_manually');
mustFail(
    fn() => LegacyFence::verify($fenceProfile, $makeFence('active')),
    'Unapproved active workflow',
    'LegacyFence estrito bloqueia qualquer workflow ativo nao-pinado'
);

// -------------------------------------------------------------------------
// 5. E2E DrainPublisher (base real UOL 4cb11dc):
//    5A: Classe A (PR #14) executa backup obrigatorio + assertAutomaticStage
//    5B: Classe B (PR #12 - Retirement.php) sem SHA pin manual executa backup obrigatorio
//    5C: Misto Retirement.php + Repository.php bloqueado em assertAutomaticStage
//    5D: Falha de backup em Classe B aborta fail-closed
// -------------------------------------------------------------------------
echo "\n--- 5. E2E DrainPublisher (UOL real: Classe A backup obrigatorio, PR #12, misto, fail-closed) ---\n";

$makeSandbox = static function (array $opts = []) use ($ownerId, $repo, $c0, $c1, $pr12Head, $basePolicy): array {
    $root = sys_get_temp_dir() . '/r10-uol-r3-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    foreach (['private', 'control', 'current/app', 'current/public', 'public'] as $sub) {
        mkdir($root . '/' . $sub, 0700, true);
    }
    RequestDrain::provision($root . '/gate');

    $curApp = $root . '/current/app';
    file_put_contents($curApp . '/bootstrap.php', "<?php // boot-v1\n");
    file_put_contents($curApp . '/Game.php', "<?php // game-v1\n");
    file_put_contents($curApp . '/PlayerDevelopment.php', "<?php // v3\n");
    file_put_contents($curApp . '/Retirement.php', "<?php // ret-v1\n");
    file_put_contents($curApp . '/Repository.php', "<?php // repo-v1\n");
    file_put_contents($curApp . '/Database.php', "<?php // db-v1\n");
    file_put_contents($root . '/current/public/index.php', "<?php // idx-v1\n");
    file_put_contents($root . '/current/public/.htaccess', "RewriteEngine On\n");
    file_put_contents($root . '/public/index.php', "<?php // idx-v1\n");
    file_put_contents($root . '/public/.htaccess', "RewriteEngine On\n");
    $rumohubEntry = $root . '/rumohub-index.php';
    file_put_contents($rumohubEntry, "<?php // hub\n");

    $tokenFile = $root . '/private/token.txt';
    $statusTokenFile = $root . '/private/status-token.txt';
    $dummyEnv = $root . '/private/app.env';
    file_put_contents($tokenFile, 'synthetic-token');
    file_put_contents($statusTokenFile, 'synthetic-status-token');
    file_put_contents($dummyEnv, "APP_ENV=test\n");

    $manifest = [
        'commit' => $c0,
        'checksums' => [
            'app/bootstrap.php' => digest($curApp . '/bootstrap.php'),
            'app/Game.php' => digest($curApp . '/Game.php'),
            'app/PlayerDevelopment.php' => digest($curApp . '/PlayerDevelopment.php'),
            'app/Retirement.php' => digest($curApp . '/Retirement.php'),
            'app/Repository.php' => digest($curApp . '/Repository.php'),
            'app/Database.php' => digest($curApp . '/Database.php'),
            'public/index.php' => digest($root . '/public/index.php'),
            'public/.htaccess' => digest($root . '/public/.htaccess'),
        ],
    ];
    record($root . '/current/build-manifest.json', $manifest);
    record($root . '/current/release.json', ['project' => 'rumo10', 'commit' => $c0, 'files' => $manifest['checksums']]);
    record($root . '/private/installed.json', [
        'release_channel' => 'main',
        'commit' => $c0,
        'functional_commit' => $c0,
        'artifact_id' => 100,
        'run_id' => 10,
        'run_number' => 10,
        'package_sha256' => str_repeat('0', 64),
        'manifest_sha256' => digest($root . '/current/release.json'),
        'build_manifest_sha256' => digest($root . '/current/build-manifest.json'),
    ]);

    $targetCommit = $opts['commit'] ?? $c1;
    $pkgFiles = [
        'app/bootstrap.php' => "<?php // boot-v1\n",
        'app/Game.php' => $opts['game_content'] ?? "<?php // game-v1\n",
        'app/PlayerDevelopment.php' => "<?php // v3\n",
        'app/Retirement.php' => $opts['retirement_content'] ?? "<?php // ret-v2-pr12\n",
        'app/Repository.php' => $opts['repository_content'] ?? "<?php // repo-v1\n",
        'app/Database.php' => "<?php // db-v1\n",
        'public/index.php' => "<?php // idx-v1\n",
        'public/.htaccess' => "RewriteEngine On\n",
    ];
    $fileHashes = [];
    foreach ($pkgFiles as $rel => $content) $fileHashes[$rel] = hash('sha256', $content);
    ksort($fileHashes);
    $pkgFiles['release.json'] = json_encode([
        'project' => 'rumo10',
        'commit' => $targetCommit,
        'functional_commit' => $targetCommit,
        'data_contract' => 'rumo10-development-v3',
        'data_contracts' => ['rumo10-development-v3'],
        'required_migrations' => [],
        'files' => $fileHashes,
    ], JSON_THROW_ON_ERROR);

    $stage = $root . '/private/stage-test';
    mkdir($stage . '/payload/app', 0700, true);
    mkdir($stage . '/payload/public', 0700, true);
    foreach ($pkgFiles as $rel => $content) file_put_contents($stage . '/payload/' . $rel, $content);
    $tarPath = $stage . '/release.tar.gz';
    makeTarGz($tarPath, $pkgFiles);
    $pkgSha = digest($tarPath);

    $run = [
        'id' => 555, 'run_attempt' => 1, 'run_number' => 42, 'workflow_id' => 7,
        'head_sha' => $targetCommit, 'head_branch' => 'main', 'event' => 'push',
        'status' => 'completed', 'conclusion' => 'success',
        'repository' => ['full_name' => $repo], 'merged_pr' => 12,
    ];
    $artifact = [
        'id' => 14564933268, 'name' => 'release-rumo10-' . $targetCommit,
        'expired' => false, 'digest' => 'sha256:' . str_repeat('a', 64),
        'workflow_run' => ['id' => 555, 'head_sha' => $targetCommit],
    ];
    $reqJobs = ['syntax', 'regression', 'compatibility', 'publisher-safety', 'rumo10-ci-gate', 'artifact'];
    $jobs = array_map(static fn($name) => [
        'name' => $name, 'status' => 'completed', 'conclusion' => 'success',
        'head_sha' => $targetCommit, 'run_id' => 555, '_requested_attempt' => 1,
    ], $reqJobs);
    $envelope = [
        'schema' => 'rumo-pull-v1', 'project' => 'rumo10', 'repository' => $repo,
        'branch' => 'main', 'release_channel' => 'main', 'commit' => $targetCommit,
        'source_commit' => $targetCommit, 'functional_commit' => $targetCommit,
        'workflow_id' => 7, 'run_id' => 555, 'run_attempt' => 1, 'run_number' => 42,
        'package_sha256' => $pkgSha, 'data_contract' => 'rumo10-development-v3',
        'data_contracts' => ['rumo10-development-v3'], 'required_migrations' => [],
    ];
    record($stage . '/ready.json', compact('run', 'artifact', 'jobs', 'envelope'));

    $fenceScript = $root . '/private/mock-fence.php';
    file_put_contents($fenceScript, "<?php echo json_encode(['ok'=>true,'legacy_publishers_fenced'=>true]);\n");

    $cfg = [
        'schema' => 'rumo-pull-v1', 'php' => PHP_BINARY, 'project' => 'rumo10',
        'repository' => $repo, 'branch' => 'main', 'release_channel' => 'main',
        'workflow_id' => 7, 'required_jobs' => $reqJobs,
        'data_contracts' => ['rumo10-development-v3'], 'baseline_data_contracts' => ['rumo10-development-v3'],
        'pinned_controls' => [
            $root . '/public/.htaccess' => digest($root . '/public/.htaccess'),
            $root . '/public/index.php' => digest($root . '/public/index.php'),
        ],
        'backup_roots' => ['env' => $dummyEnv],
        'token_file' => $tokenFile, 'account_mutex' => $root . '/private/account.mutex',
        'private' => $root . '/private', 'control' => $root . '/control',
        'current' => $root . '/current', 'public' => $root . '/public',
        'apply_enabled' => true,
        'homologation' => ['maintenance' => true, 'recovery' => true, 'legacy_publishers_fenced' => true, 'backup' => true],
        'request_gate' => [
            'root' => $root . '/gate', 'coverage_approved' => true, 'projects' => ['rumo10', 'rumohub'],
            'entrypoints' => [$root . '/public/index.php' => digest($root . '/public/index.php'), $rumohubEntry => digest($rumohubEntry)],
            'timeout_seconds' => 2,
        ],
        'update_policy' => $opts['policy'] ?? $basePolicy,
        'main_automation' => ['schema' => 'rumo-main-automation-v1', 'owner_id' => $ownerId, 'status_token_file' => $statusTokenFile],
        'hooks' => ['fence' => ['script' => $fenceScript, 'sha256' => digest($fenceScript), 'args' => []]],
    ];

    $backupFails = $opts['backup_fails'] ?? false;
    $pub = new class($cfg, $repo, $c0, $targetCommit, $pr12Head, $ownerId, $backupFails, $pkgSha) extends DrainPublisher {
        public array $calledHooks = [];
        public bool $preflightBackupCalled = false;
        public function __construct(
            private array $tCfg, private string $repo, private string $fromSha, private string $toSha,
            private string $prHead, private int $ownerId, private bool $backupFails, private string $pkgSha
        ) { parent::__construct($tCfg); }
        protected function assertBackupPrerequisites(): void { $this->preflightBackupCalled = true; }
        protected function source(): GitHub {
            return new class($this->tCfg, $this->repo, $this->fromSha, $this->toSha, $this->prHead, $this->ownerId) extends GitHub {
                public function __construct(array $c, private string $repo, private string $fromSha, private string $toSha, private string $prHead, private int $ownerId) { parent::__construct($c); }
                protected function request(string $url, bool $auth, int $limit, ?string $out = null): array {
                    $path = parse_url($url, PHP_URL_PATH);
                    if (str_contains($path, '/compare/')) {
                        return ['status' => 200, 'body' => json_encode([
                            'status' => 'ahead', 'merge_base_commit' => ['sha' => $this->fromSha],
                            'files' => [['filename' => 'app/Retirement.php']],
                        ], JSON_THROW_ON_ERROR)];
                    }
                    if (str_ends_with($path, '/pulls')) {
                        return ['status' => 200, 'body' => json_encode([[
                            'number' => 12, 'state' => 'closed', 'merged_at' => '2026-11-20T12:00:00Z',
                            'merge_commit_sha' => $this->toSha, 'base' => ['repo' => ['full_name' => $this->repo], 'ref' => 'main'],
                        ]], JSON_THROW_ON_ERROR)];
                    }
                    if (str_contains($path, '/pulls/12')) {
                        return ['status' => 200, 'body' => json_encode([
                            'number' => 12, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-11-20T12:00:00Z',
                            'merged_by' => ['id' => $this->ownerId], 'base' => ['repo' => ['full_name' => $this->repo], 'ref' => 'main'],
                            'merge_commit_sha' => $this->toSha, 'head' => ['sha' => $this->prHead],
                        ], JSON_THROW_ON_ERROR)];
                    }
                    if (str_contains($path, '/commits/')) {
                        return ['status' => 200, 'body' => json_encode([
                            'sha' => $this->toSha, 'parents' => [['sha' => $this->fromSha], ['sha' => $this->prHead]],
                        ], JSON_THROW_ON_ERROR)];
                    }
                    throw new RuntimeException('Unexpected API: '.$path);
                }
            };
        }
        protected function hook(string $name, array $request): array {
            $this->calledHooks[] = $name;
            if ($name === 'preflight') return ['legacy_publishers_fenced' => true, 'source_unchanged' => true];
            if ($name === 'maintenance') return ['http_status' => 503, 'writes_blocked' => true, 'inflight_writers' => 0, 'new_requests_blocked' => true, 'publisher_exclusive' => true];
            if ($name === 'backup') {
                if ($this->backupFails) throw new RuntimeException('Backup hook failed: mysqldump error');
                $dumpFile = $request['backup'] . '/dump.sql';
                file_put_contents($dumpFile, "-- consistent dump\n");
                $h = digest($dumpFile);
                return ['attempt' => $request['attempt'], 'created_at' => time(), 'artifact_id' => 14564933268,
                    'package_sha256' => $this->pkgSha, 'consistent' => true, 'source_uuid' => 'uol-mysql',
                    'dump_sha256' => $h, 'files' => [$dumpFile => $h]];
            }
            if ($name === 'smoke') return ['ok' => true, 'http_status' => 200];
            return ['ok' => true];
        }
    };
    return [$root, $stage, $pub];
};

// 5A: Classe A ordinaria (estilo PR #14) -> backup obrigatorio preservado!
[$rootA, $stageA, $pubA] = $makeSandbox(['game_content' => "<?php // game-v2-f04\n", 'retirement_content' => "<?php // ret-v1\n"]);
try {
    $resA = $pubA->apply($stageA);
    ok(($resA['state'] ?? null) === 'validated', 'E2E Classe A aplica e valida automaticamente');
    ok($pubA->preflightBackupCalled && in_array('backup', $pubA->calledHooks, true),
        'E2E Classe A preserva backup automatico obrigatorio (contrato UOL)');
} finally { rrmdir($rootA); }

// 5B: PR #12 (eb073346, artefato 14564933268, app/Retirement.php) sem SHA pin manual!
[$rootB, $stageB, $pubB] = $makeSandbox();
try {
    $resB = $pubB->apply($stageB);
    ok(($resB['state'] ?? null) === 'validated', 'E2E PR #12 (eb073346 / artefato 14564933268) publica sem SHA pin manual');
    ok($pubB->preflightBackupCalled && in_array('backup', $pubB->calledHooks, true),
        'E2E PR #12 executa preflight + backup obrigatorio + drenagem');
    ok(file_get_contents($rootB . '/current/app/Retirement.php') === "<?php // ret-v2-pr12\n",
        'E2E PR #12 promove app/Retirement.php atomicamente');
} finally { rrmdir($rootB); }

// 5C: Misto Retirement.php + Repository.php (mesmo sem Repository.php em protected_contract_paths) -> BLOQUEADO!
[$rootC, $stageC, $pubC] = $makeSandbox([
    'retirement_content' => "<?php // ret-v2\n",
    'repository_content' => "<?php // repo-v2-forbidden\n",
    'policy' => array_replace($basePolicy, ['protected_contract_paths' => ['app/Retirement.php']]),
]);
try {
    mustFail(
        fn() => $pubC->apply($stageC),
        'Structural change or Class C',
        'E2E bloqueia release mista Retirement.php + Repository.php antes mesmo de fechar o gate'
    );
    ok(file_get_contents($rootC . '/current/app/Repository.php') === "<?php // repo-v1\n",
        'Baseline intacto apos bloqueio de release mista');
} finally { rrmdir($rootC); }

// 5D: Falha de backup em Classe B -> fail-closed!
[$rootD, $stageD, $pubD] = $makeSandbox(['backup_fails' => true]);
try {
    mustFail(
        fn() => $pubD->apply($stageD),
        'Backup hook failed',
        'E2E aborta Classe B (fail-closed) quando backup falha'
    );
    ok(file_get_contents($rootD . '/current/app/Retirement.php') === "<?php // ret-v1\n",
        'Nenhum arquivo alterado quando backup falha');
} finally { rrmdir($rootD); }

echo "\n=== TODOS OS {$passed} TESTES DIRECIONADOS PASSARAM ===\n";
