<?php
declare(strict_types=1);
// Install this entry point alongside the DEP-058 bundle as bootstrap-dep067.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$phase = '';
try {
    $options = getopt('', ['phase:', 'profile:', 'approve:', 'action:', 'source:']);
    $phase = $options['phase'] ?? '';
    $blockedReceiver = in_array($phase, ['plan-blocked-receiver', 'install-blocked-receiver'], true);
    if ($blockedReceiver) {
        foreach ([
            'trusted/Core.php' => 'dbda12db5d861c8b99e8cfba46b64b86c94a1f3022ad098108cb0c835bebdd29',
            'trusted/RequestDrain.php' => '92dbbc71ec50c7ee60d2ac85231051a0f738e4ea916e3e5e7482a697b25bc7fe',
            'lib/Bootstrap.php' => 'b5d15f0fe6755ac1faec13f7c271ac08284e25947e62ecd46760fd9a1a259f84',
        ] as $file => $expected) {
            $path = __DIR__.'/'.$file;
            if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== $expected) {
                throw new RuntimeException('Unexpected DEP-058 dependency: '.$file);
            }
        }
    }
    require __DIR__.'/trusted/Core.php';
    require __DIR__.'/trusted/RequestDrain.php';
    require __DIR__.'/lib/Bootstrap.php';
    if ($blockedReceiver) {
        require __DIR__.'/lib/BlockedReceiver.php';
        $result = Dep058\updateBlockedReceiver(
            $options['profile'] ?? '', $options['source'] ?? '',
            $phase === 'install-blocked-receiver' ? ($options['approve'] ?? '') : null,
            'fd5720626d7a72b9a808212700660d5dab8698c5200975d49a0e6942dfae2af7',
        );
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }
    if (!in_array($phase, ['preflight', 'install-infra', 'verify'], true) || !isset($options['profile'])) {
        throw new RuntimeException('Use --phase=preflight|install-infra|verify --profile=private.json');
    }
    $bootstrap = new Dep058\Bootstrap(__DIR__, $options['profile']);
    $result = match ($phase) {
        'preflight' => $bootstrap->preflight(),
        'install-infra' => $bootstrap->install($options['approve'] ?? ''),
        'verify' => $bootstrap->verify($options['approve'] ?? '', $options['action'] ?? 'inspect'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $error) {
    if ($phase === 'preflight') {
        echo json_encode([
            'phase' => 'preflight',
            'integrity' => 'not_verified',
            'technical_pending' => ['PREFLIGHT_CHECK_FAILED'],
            'production_authorization' => 'not_evaluated',
            'install_ready' => false,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
    }
    fwrite(STDERR, 'DEP058 BLOCKED: '.$error->getMessage().PHP_EOL);
    exit(1);
}
