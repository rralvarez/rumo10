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

echo "=== RUMO10 | TESTES DE PRIVILEGIOS DE ROTINAS (REVOKE, SHOW_ROUTINE vs mysqldump, FAIL-CLOSED) ===\n\n";

$dbUol = 'rumoacame144a99e_rumohub';
$mysql846 = '8.0.46-cll-lve';
$cpanelEscapedGrant = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER ON `rumoacame144a99e\\_rumohub`.* TO `rumoacame144a99e_app`@`localhost`";
$usageGrant = "GRANT USAGE ON *.* TO `rumoacame144a99e_app`@`localhost`";

// -------------------------------------------------------------------------
// 1. Schemas escapados + MySQL 8.0.46-cll-lve (UOL)
// -------------------------------------------------------------------------
echo "--- 1. Schemas escapados e MySQL 8.0.46-cll-lve ---\n";
$evalEscaped = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelEscapedGrant], $dbUol, $mysql846, 'MySQL Community Server - GPL');
ok($evalEscaped['has_trigger_priv'] === true && $evalEscaped['has_event_priv'] === true,
    'Reconhece TRIGGER e EVENT em schema escapado `rumoacame144a99e\\_rumohub`.*');
ok($evalEscaped['routine_inventory_provable'] === false && $evalEscaped['routine_export_supported'] === false,
    'CREATE ROUTINE / ALTER ROUTINE / EXECUTE no schema NAO comprovam inventario nem exportacao no MySQL 8.0.46');

$evalWrongDb = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelEscapedGrant], 'rumoacame144a99eXrumohub', $mysql846);
ok($evalWrongDb['has_trigger_priv'] === false && $evalWrongDb['has_event_priv'] === false,
    'Schema escapado `rumoacame144a99e\\_rumohub` NAO casa indevidamente com wildcard');

// -------------------------------------------------------------------------
// 2. Tratamento de REVOKE e revogacoes parciais (MySQL 8 partial_revokes)
// -------------------------------------------------------------------------
echo "\n--- 2. Tratamento de REVOKE e revogacoes parciais ---\n";
$evalPartialRevokeSelect = rumoEvaluateMysqlPrivileges([
    "GRANT SELECT, TRIGGER, EVENT ON *.* TO `u`@`localhost`",
    "REVOKE SELECT ON `rumoacame144a99e\\_rumohub`.* FROM `u`@`localhost`",
], $dbUol, $mysql846);
ok($evalPartialRevokeSelect['has_global_select'] === false && $evalPartialRevokeSelect['routine_export_supported'] === false
    && $evalPartialRevokeSelect['routine_inventory_provable'] === false,
    'REVOKE SELECT parcial no schema alvo invalida SELECT global para inventario e exportacao de rotinas');

$evalRevokeTrigger = rumoEvaluateMysqlPrivileges([
    "GRANT ALL PRIVILEGES ON `rumoacame144a99e\\_rumohub`.* TO `u`@`localhost`",
    "REVOKE TRIGGER ON `rumoacame144a99e\\_rumohub`.* FROM `u`@`localhost`",
], $dbUol, $mysql846);
ok($evalRevokeTrigger['has_trigger_priv'] === false && $evalRevokeTrigger['has_event_priv'] === true,
    'REVOKE TRIGGER parcial remove apenas TRIGGER mantendo EVENT');

$evalRevokeShowRoutine = rumoEvaluateMysqlPrivileges([
    "GRANT SHOW_ROUTINE ON *.* TO `u`@`localhost`",
    "REVOKE SHOW_ROUTINE ON *.* FROM `u`@`localhost`",
    $cpanelEscapedGrant,
], $dbUol, $mysql846);
ok($evalRevokeShowRoutine['has_show_routine'] === false && $evalRevokeShowRoutine['routine_inventory_provable'] === false,
    'REVOKE SHOW_ROUTINE ON *.* invalida visibilidade de inventario de rotinas');

$evalRevokeAllPartial = rumoEvaluateMysqlPrivileges([
    "GRANT ALL PRIVILEGES ON *.* TO `u`@`localhost`",
    "REVOKE ALL PRIVILEGES ON `rumoacame144a99e\\_rumohub`.* FROM `u`@`localhost`",
], $dbUol, $mysql846);
ok($evalRevokeAllPartial['has_global_select'] === false && $evalRevokeAllPartial['has_trigger_priv'] === false
    && $evalRevokeAllPartial['has_event_priv'] === false,
    'REVOKE ALL PRIVILEGES parcial no schema alvo revoga SELECT global, TRIGGER e EVENT');

// -------------------------------------------------------------------------
// 3. Separacao SHOW_ROUTINE (leitura de inventario) vs mysqldump --routines (SELECT global)
// -------------------------------------------------------------------------
echo "\n--- 3. Separacao SHOW_ROUTINE vs mysqldump --routines ---\n";
$evalShowOnly = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelEscapedGrant,
    "GRANT SHOW_ROUTINE ON *.* TO `rumoacame144a99e_app`@`localhost`",
], $dbUol, $mysql846);
ok($evalShowOnly['routine_inventory_provable'] === true && $evalShowOnly['routine_export_supported'] === false
    && $evalShowOnly['routine_priv_basis'] === 'global:SHOW_ROUTINE',
    'MySQL 8.0.46 com SHOW_ROUTINE comprova inventario, mas NAO atende requisito SELECT global do mysqldump --routines');
ok(rumoCanProceedWithRoutines($evalShowOnly, 0) === true,
    'SHOW_ROUTINE permite prosseguir quando inventario comprovado tem 0 rotinas');
ok(rumoCanProceedWithRoutines($evalShowOnly, 1) === false,
    'SHOW_ROUTINE BLOQUEIA quando ha rotinas (> 0), pois mysqldump --routines exige SELECT global');

$evalGlobalSelect = rumoEvaluateMysqlPrivileges([
    "GRANT SELECT ON *.* TO `u`@`localhost`",
    $cpanelEscapedGrant,
], $dbUol, $mysql846);
ok($evalGlobalSelect['routine_inventory_provable'] === true && $evalGlobalSelect['routine_export_supported'] === true
    && $evalGlobalSelect['routine_priv_basis'] === 'global:SELECT',
    'MySQL 8.0.46 com SELECT global efetivo comprova inventario E exportacao via mysqldump --routines');
ok(rumoCanProceedWithRoutines($evalGlobalSelect, 0) === true && rumoCanProceedWithRoutines($evalGlobalSelect, 3) === true,
    'SELECT global efetivo permite prosseguir tanto com 0 quanto com N rotinas');

// -------------------------------------------------------------------------
// 4. Diferenciacao de versoes/engines (MySQL 8.0.18, MySQL 5.7, MariaDB 10.6)
// -------------------------------------------------------------------------
echo "\n--- 4. Diferenciacao MySQL 8.0.<20, MySQL 5.7 e MariaDB ---\n";
$evalMysql8018 = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SHOW_ROUTINE ON *.* TO `u`@`localhost`"],
    $dbUol, '8.0.18'
);
ok($evalMysql8018['routine_inventory_provable'] === false,
    'MySQL 8.0.18 (< 8.0.20) nao aceita SHOW_ROUTINE');

$evalMysql8Proc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, $mysql846
);
ok($evalMysql8Proc['routine_inventory_provable'] === false && $evalMysql8Proc['routine_export_supported'] === false,
    'MySQL 8.0.46 rejeita SELECT ON mysql.proc (tabela inexistente no MySQL 8)');

$evalMariaProc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, '10.6.18-MariaDB-cll-lve', 'MariaDB Server'
);
ok($evalMariaProc['routine_inventory_provable'] === true && $evalMariaProc['routine_export_supported'] === true
    && $evalMariaProc['routine_priv_basis'] === 'mysql.proc:SELECT',
    'MariaDB 10.6 aceita SELECT ON mysql.proc para inventario e exportacao');

$evalMariaProcRevoked = rumoEvaluateMysqlPrivileges([
    $usageGrant,
    $cpanelEscapedGrant,
    "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`",
    "REVOKE SELECT ON `mysql`.`proc` FROM `u`@`localhost`",
], $dbUol, '10.6.18-MariaDB-cll-lve', 'MariaDB Server');
ok($evalMariaProcRevoked['routine_inventory_provable'] === false && $evalMariaProcRevoked['routine_export_supported'] === false,
    'MariaDB 10.6 respeita REVOKE SELECT ON mysql.proc');

// -------------------------------------------------------------------------
// 5. Politica fail-closed: sem atestado estatico permanente de ausencia
// -------------------------------------------------------------------------
echo "\n--- 5. Fail-closed: nenhum atestado estatico permanente aceito ---\n";
ok(!function_exists('rumoValidateRoutineAbsenceAttestation'),
    'Atestado estatico permanente de ausencia de rotinas removido (nao aceito sem garantia viva no servidor)');

echo "\n=== TODOS OS {$passed} TESTES DE PRIVILEGIOS DE ROTINAS PASSARAM ===\n";
