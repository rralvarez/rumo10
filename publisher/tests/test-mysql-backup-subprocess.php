<?php
declare(strict_types=1);

define('RUMO_BACKUP_HOOK_LIB_ONLY', true);
require_once dirname(__DIR__).'/bin/mysql-backup-hook.php';

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

echo "=== RUMO10 | TESTES DE SUBPROCESSO DO MYSQLDUMP (SUCESSO, ERRO, TIMEOUT, SINAL) ===\n\n";

$tmp = sys_get_temp_dir() . '/r10-bk-subproc-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$nullIn = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

try {
    // 1. Resolucao de timeout configuravel e compatibilidade com supervisor
    echo "--- 1. Timeout configuravel e compatibilidade com supervisor ---\n";
    ok(rumoResolveDumpTimeoutSeconds([]) === 300, 'Timeout padrao e 300s');
    ok(rumoResolveDumpTimeoutSeconds(['dump_timeout_seconds' => 420]) === 420, 'Respeita dump_timeout_seconds configuravel');
    ok(rumoResolveDumpTimeoutSeconds(['dump_timeout_seconds' => 3]) === 10, 'Impõe limite minimo seguro (10s)');
    ok(rumoResolveDumpTimeoutSeconds(['dump_timeout_seconds' => 9999]) === 900, 'Impõe limite maximo seguro (900s)');
    ok(rumoResolveDumpTimeoutSeconds(['dump_timeout_seconds' => 300, 'supervisor_timeout_seconds' => 180]) === 165,
        'Ajusta dump_timeout_seconds abaixo de supervisor_timeout_seconds (folga de 15s)');

    // 2. Matriz de classificacao (proc_get_status vs proc_close, sinal, indisponivel)
    echo "\n--- 2. Preservacao de proc_get_status() e proc_close() + sinais ---\n";
    $cClose0 = rumoClassifySubprocessOutcome(false, false, 0, -1, 0);
    ok($cClose0['ok'] === true && $cClose0['reason'] === 'ok' && $cClose0['exit_code'] === 0,
        'Preserva exit code 0 de proc_close() quando proc_get_status() retorna -1');
    $cClose7 = rumoClassifySubprocessOutcome(false, false, 0, -1, 7);
    ok($cClose7['ok'] === false && $cClose7['reason'] === 'non_zero_exit' && $cClose7['exit_code'] === 7,
        'Preserva exit code 7 de proc_close() quando proc_get_status() retorna -1');
    $cUnavail = rumoClassifySubprocessOutcome(false, false, 0, -1, -1);
    ok($cUnavail['ok'] === false && $cUnavail['reason'] === 'exit_code_unavailable' && $cUnavail['exit_code'] === -1,
        'Diferencia codigo de saida indisponivel (-1/-1) como falha-fechada');
    $cSig9 = rumoClassifySubprocessOutcome(false, true, 9, -1, -1);
    ok($cSig9['ok'] === false && $cSig9['reason'] === 'signaled' && $cSig9['termsig'] === 9,
        'Diferencia encerramento por sinal (SIGKILL=9)');
    $cTo = rumoClassifySubprocessOutcome(true, true, 15, -1, -1);
    ok($cTo['ok'] === false && $cTo['reason'] === 'timeout',
        'Prioriza classificacao timeout quando prazo e excedido');

    // 3. Subprocesso REAL com sucesso
    echo "\n--- 3. Subprocesso real: sucesso ---\n";
    $outOk = $tmp . '/ok.sql';
    $errOk = $tmp . '/ok.err';
    $resOk = rumoRunBackupSubprocess(
        [PHP_BINARY, '-r', 'echo "-- sql dump\nINSERT INTO t VALUES (1);\n";'],
        $nullIn, $outOk, $errOk, 15, 'test-ok', $tmp
    );
    ok($resOk['ok'] === true && $resOk['exit_code'] === 0 && $resOk['reason'] === 'ok'
        && $resOk['output_bytes'] > 20 && $resOk['duration_seconds'] >= 0.0,
        'Subprocesso real com exit 0 retorna duracao, tamanho do arquivo e exit_code=0');

    // 4. Subprocesso REAL com falha (exit 2) + sanitizacao de stderr + log persistente
    echo "\n--- 4. Subprocesso real: erro real + sanitizacao ---\n";
    $outErr = $tmp . '/err.sql';
    $errErr = $tmp . '/err.err';
    mustFail(
        fn() => rumoRunBackupSubprocess(
            [PHP_BINARY, '-r', 'echo "-- partial"; fwrite(STDERR, "mysqldump: Access denied password=SuperSecret123 token abcdef123\n"); exit(2);'],
            $nullIn, $outErr, $errErr, 15, 'test-error', $tmp
        ),
        'mysqldump failed (code 2',
        'Subprocesso real com exit 2 falha com codigo exato e duracao/tamanho'
    );
    $logText = file_get_contents($tmp . '/backup-last-error.log');
    ok(str_contains($logText, 'Reason: non_zero_exit') && str_contains($logText, 'Exit: 2')
        && str_contains($logText, 'password=[REDACTED]') && !str_contains($logText, 'SuperSecret123')
        && str_contains($logText, 'OutputBytes: 10'),
        'Erro real registra Reason, Exit, Duration, OutputBytes e stderr sanitizado sem vazar senha');

    // 5. Subprocesso REAL com timeout (encerramento seguro + nunca aceita dump parcial)
    echo "\n--- 5. Subprocesso real: timeout e encerramento seguro ---\n";
    $outTo = $tmp . '/timeout.sql';
    $errTo = $tmp . '/timeout.err';
    mustFail(
        fn() => rumoRunBackupSubprocess(
            [PHP_BINARY, '-r', 'echo "-- partial dump before hang\n"; flush(); sleep(10);'],
            $nullIn, $outTo, $errTo, 1, 'test-timeout', $tmp
        ),
        'mysqldump timed out after',
        'Subprocesso real que excede timeout e encerrado com seguranca e reportado como timeout'
    );
    $logText2 = file_get_contents($tmp . '/backup-last-error.log');
    ok(str_contains($logText2, 'Reason: timeout') && str_contains($logText2, 'Timeout: 1s'),
        'Log registra Reason: timeout e limite configurado');

    // 6. Subprocesso REAL encerrado externamente (terminacao forcada)
    echo "\n--- 6. Subprocesso real: encerramento forcado (sinal/externo) ---\n";
    $outSig = $tmp . '/sig.sql';
    $errSig = $tmp . '/sig.err';
    $sigCaught = null;
    try {
        rumoRunBackupSubprocess(
            [PHP_BINARY, '-r', 'echo "-- partial"; flush(); sleep(10);'],
            $nullIn, $outSig, $errSig, 15, 'test-signal', $tmp,
            static function ($proc, int $pid): void {
                usleep(150000);
                @proc_terminate($proc, 9);
            }
        );
    } catch (Throwable $e) {
        $sigCaught = $e->getMessage();
    }
    ok($sigCaught !== null && (str_contains($sigCaught, 'terminated by signal') || str_contains($sigCaught, 'mysqldump failed (code')),
        'Subprocesso real encerrado externamente durante execucao falha-fechado (nunca aceito como sucesso): ' . ($sigCaught ?? 'none'));

    echo "\n=== TODOS OS {$passed} TESTES DE SUBPROCESSO PASSARAM ===\n";
} finally {
    foreach (glob($tmp . '/*') ?: [] as $f) @unlink($f);
    @rmdir($tmp);
}
