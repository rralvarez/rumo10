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

echo "=== RUMO10 | TESTES FINAIS DE VISIBILIDADE DE ROTINAS (EXECUTE NO SCHEMA, REVOKE, DDL CONCORRENTE) ===\n\n";

$dbUol = 'rumoacame144a99e_rumohub';
$mysql846 = '8.0.46-cll-lve';
$usageGrant = "GRANT USAGE ON *.* TO `rumoacame144a99e_app`@`localhost`";
$cpanelWithExecute = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER ON `rumoacame144a99e\\_rumohub`.* TO `rumoacame144a99e_app`@`localhost`";
$cpanelWithoutExecute = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER ON `rumoacame144a99e\\_rumohub`.* TO `rumoacame144a99e_app`@`localhost`";

// -------------------------------------------------------------------------
// 1. EXECUTE no schema correto (escapado), inventário vazio vs não vazio
// -------------------------------------------------------------------------
echo "--- 1. EXECUTE efetivo no schema correto (escapado) ---\n";
$evalExecOk = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelWithExecute], $dbUol, $mysql846, 'MySQL Community Server - GPL');
ok($evalExecOk['has_trigger_priv'] === true && $evalExecOk['has_event_priv'] === true && $evalExecOk['has_schema_execute'] === true,
    'Reconhece TRIGGER, EVENT e EXECUTE em schema escapado `rumoacame144a99e\\_rumohub`.*');
ok($evalExecOk['routine_inventory_provable'] === true && $evalExecOk['routine_export_supported'] === false
    && $evalExecOk['routine_priv_basis'] === 'schema:EXECUTE',
    'EXECUTE no schema comprova visibilidade completa do inventario (sem garantir exportacao de definicoes)');
ok(rumoCanProceedWithRoutines($evalExecOk, 0) === true,
    'EXECUTE no schema correto + inventario vazio (0 rotinas) permite dump sem --routines');
ok(rumoCanProceedWithRoutines($evalExecOk, 1) === false,
    'EXECUTE no schema correto + inventario NAO vazio (> 0 rotinas) MANTEM bloqueio sem SELECT global');

// Sem EXECUTE (apenas CREATE ROUTINE / ALTER ROUTINE): nao comprova inventario!
$evalNoExec = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelWithoutExecute], $dbUol, $mysql846);
ok($evalNoExec['has_schema_execute'] === false && $evalNoExec['routine_inventory_provable'] === false,
    'CREATE ROUTINE / ALTER ROUTINE sem EXECUTE NAO comprovam visibilidade do inventario no MySQL 8.0.46');

// -------------------------------------------------------------------------
// 2. EXECUTE em schema incorreto e concessao restrita a uma unica rotina
// -------------------------------------------------------------------------
echo "\n--- 2. Schema incorreto e concessao restrita a uma unica rotina ---\n";
$evalWrongSchema = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelWithoutExecute,
    "GRANT EXECUTE ON `outro_schema`.* TO `rumoacame144a99e_app`@`localhost`",
], $dbUol, $mysql846);
ok($evalWrongSchema['has_schema_execute'] === false && $evalWrongSchema['routine_inventory_provable'] === false,
    'EXECUTE em schema incorreto (`outro_schema`.*) NAO comprova inventario do schema alvo');

$evalWildcardMismatch = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelWithExecute], 'rumoacame144a99eXrumohub', $mysql846);
ok($evalWildcardMismatch['has_schema_execute'] === false && $evalWildcardMismatch['routine_inventory_provable'] === false,
    'Schema escapado `rumoacame144a99e\\_rumohub` NAO casa indevidamente com wildcard');

$evalSingleRoutine = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelWithoutExecute,
    "GRANT EXECUTE ON PROCEDURE `rumoacame144a99e\\_rumohub`.`sp_unica` TO `rumoacame144a99e_app`@`localhost`",
    "GRANT EXECUTE ON FUNCTION `rumoacame144a99e\\_rumohub`.`fn_unica` TO `rumoacame144a99e_app`@`localhost`",
], $dbUol, $mysql846);
ok($evalSingleRoutine['has_schema_execute'] === false && $evalSingleRoutine['routine_inventory_provable'] === false,
    'EXECUTE restrito a uma unica PROCEDURE/FUNCTION NAO comprova inventario completo do schema');

// -------------------------------------------------------------------------
// 3. Revogacao (REVOKE EXECUTE, REVOKE SELECT parcial, REVOKE ALL)
// -------------------------------------------------------------------------
echo "\n--- 3. Revogacoes (REVOKE EXECUTE / SELECT / ALL) ---\n";
$evalRevokeExec = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelWithExecute,
    "REVOKE EXECUTE ON `rumoacame144a99e\\_rumohub`.* FROM `rumoacame144a99e_app`@`localhost`",
], $dbUol, $mysql846);
ok($evalRevokeExec['has_schema_execute'] === false && $evalRevokeExec['routine_inventory_provable'] === false,
    'REVOKE EXECUTE no schema alvo invalida visibilidade do inventario de rotinas');

$evalRevokeGlobalExec = rumoEvaluateMysqlPrivileges([
    "GRANT EXECUTE, TRIGGER, EVENT ON *.* TO `u`@`localhost`",
    "REVOKE EXECUTE ON `rumoacame144a99e\\_rumohub`.* FROM `u`@`localhost`",
], $dbUol, $mysql846);
ok($evalRevokeGlobalExec['has_schema_execute'] === false && $evalRevokeGlobalExec['routine_inventory_provable'] === false,
    'REVOKE EXECUTE parcial no schema alvo invalida EXECUTE global');

$evalRevokeSelect = rumoEvaluateMysqlPrivileges([
    "GRANT SELECT, TRIGGER, EVENT ON *.* TO `u`@`localhost`",
    "REVOKE SELECT ON `rumoacame144a99e\\_rumohub`.* FROM `u`@`localhost`",
], $dbUol, $mysql846);
ok($evalRevokeSelect['has_global_select'] === false && $evalRevokeSelect['routine_export_supported'] === false,
    'REVOKE SELECT parcial no schema alvo invalida SELECT global para exportacao');

// -------------------------------------------------------------------------
// 4. SHOW_ROUTINE, SELECT global e MariaDB preservados
// -------------------------------------------------------------------------
echo "\n--- 4. SHOW_ROUTINE, SELECT global e MariaDB ---\n";
$evalShowOnly = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelWithoutExecute,
    "GRANT SHOW_ROUTINE ON *.* TO `rumoacame144a99e_app`@`localhost`",
], $dbUol, $mysql846);
ok(rumoCanProceedWithRoutines($evalShowOnly, 0) === true && rumoCanProceedWithRoutines($evalShowOnly, 2) === false,
    'SHOW_ROUTINE permite com 0 rotinas e bloqueia quando ha rotinas (> 0)');

$evalGlobalSel = rumoEvaluateMysqlPrivileges(["GRANT SELECT ON *.* TO `u`@`localhost`", $cpanelWithExecute], $dbUol, $mysql846);
ok(rumoCanProceedWithRoutines($evalGlobalSel, 0) === true && rumoCanProceedWithRoutines($evalGlobalSel, 5) === true,
    'SELECT global efetivo permite inventario vazio e exportacao de rotinas presentes');

$evalMariaProc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelWithoutExecute, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, '10.6.18-MariaDB-cll-lve', 'MariaDB Server'
);
ok($evalMariaProc['routine_export_supported'] === true && $evalMariaProc['routine_priv_basis'] === 'mysql.proc:SELECT',
    'MariaDB 10.6 com SELECT ON mysql.proc preserva suporte a exportacao de rotinas');

// -------------------------------------------------------------------------
// 5. Deteccao de DDL concorrente entre verificacao pre-dump e pos-dump
// -------------------------------------------------------------------------
echo "\n--- 5. Verificacao pre-dump vs pos-dump (DDL concorrente) ---\n";
$snapEmpty = ['events' => [], 'routines' => [], 'triggers' => []];
rumoAssertStableInventorySnapshot($snapEmpty, ['events' => [], 'routines' => [], 'triggers' => []]);
ok(true, 'Inventario identico pre-dump e pos-dump e aprovado');

mustFail(
    fn() => rumoAssertStableInventorySnapshot($snapEmpty, ['events' => [], 'routines' => ['PROCEDURE:sp_injected'], 'triggers' => []]),
    'Concurrent DDL detected',
    'Criacao concorrente de rotina durante o dump e detectada e bloqueada (fail-closed)'
);
mustFail(
    fn() => rumoAssertStableInventorySnapshot($snapEmpty, ['events' => ['ev_new'], 'routines' => [], 'triggers' => []]),
    'Concurrent DDL detected',
    'Criacao concorrente de evento durante o dump e detectada e bloqueada (fail-closed)'
);

echo "\n=== TODOS OS {$passed} TESTES DE VISIBILIDADE DE ROTINAS PASSARAM ===\n";
