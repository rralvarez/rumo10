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

echo "=== RUMO10 | TESTES DE PRIVILEGIOS DE ROTINAS MYSQL / MARIADB ===\n\n";

$dbUol = 'rumoacame144a99e_rumohub';
$cpanelEscapedGrant = "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER ON `rumoacame144a99e\\_rumohub`.* TO `rumoacame144a99e_app`@`localhost`";
$usageGrant = "GRANT USAGE ON *.* TO `rumoacame144a99e_app`@`localhost`";

// -------------------------------------------------------------------------
// 1. Parser de schemas escapados (padrao cPanel/UOL)
// -------------------------------------------------------------------------
echo "--- 1. Schemas escapados ---\n";
$evalEscaped = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelEscapedGrant], $dbUol, '8.0.36', 'MySQL Community Server');
ok($evalEscaped['has_trigger_priv'] === true && $evalEscaped['has_event_priv'] === true,
    'Reconhece TRIGGER e EVENT em schema escapado `rumoacame144a99e\\_rumohub`.*');
ok($evalEscaped['has_routine_definition_priv'] === false && $evalEscaped['has_schema_routine_ddl_only'] === true,
    'NAO assume que CREATE ROUTINE / ALTER ROUTINE / EXECUTE garantem leitura de definicoes no MySQL 8');

$evalWrongDb = rumoEvaluateMysqlPrivileges([$usageGrant, $cpanelEscapedGrant], 'rumoacame144a99eXrumohub', '8.0.36', 'MySQL Community Server');
ok($evalWrongDb['has_trigger_priv'] === false && $evalWrongDb['has_event_priv'] === false,
    'Schema escapado `rumoacame144a99e\\_rumohub` NAO casa indevidamente com wildcard (rumoacame144a99eXrumohub)');

// -------------------------------------------------------------------------
// 2. Diferenciacao MySQL 8.0.20+ vs MySQL 8.0.<20 vs MySQL 5.7 vs MariaDB
// -------------------------------------------------------------------------
echo "\n--- 2. Diferenciacao de engines e versoes ---\n";

// MySQL 8.0.36 / 8.4.2
$evalMysql8Show = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SHOW_ROUTINE ON *.* TO `rumoacame144a99e_app`@`localhost`"],
    $dbUol, '8.0.36', 'MySQL Community Server'
);
ok($evalMysql8Show['has_routine_definition_priv'] === true && $evalMysql8Show['routine_priv_basis'] === 'global:SHOW_ROUTINE',
    'MySQL 8.0.36 aceita global SHOW_ROUTINE ON *.*');

$evalMysql84Select = rumoEvaluateMysqlPrivileges(
    ["GRANT SELECT ON *.* TO `u`@`localhost`", $cpanelEscapedGrant],
    $dbUol, '8.4.2', 'MySQL Community Server'
);
ok($evalMysql84Select['has_routine_definition_priv'] === true && $evalMysql84Select['routine_priv_basis'] === 'global:SELECT',
    'MySQL 8.4.2 aceita global SELECT ON *.*');

$evalMysql8SchemaAll = rumoEvaluateMysqlPrivileges(
    [$usageGrant, "GRANT ALL PRIVILEGES ON `rumoacame144a99e\\_rumohub`.* TO `u`@`localhost`"],
    $dbUol, '8.0.36', 'MySQL Community Server'
);
ok($evalMysql8SchemaAll['has_trigger_priv'] === true && $evalMysql8SchemaAll['has_event_priv'] === true && $evalMysql8SchemaAll['has_routine_definition_priv'] === false,
    'MySQL 8.0.36 rejeita ALL PRIVILEGES apenas no nivel do schema (ON `db`.*) para definicoes de rotinas');

$evalMysql8MysqlProc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, '8.0.36', 'MySQL Community Server'
);
ok($evalMysql8MysqlProc['has_routine_definition_priv'] === false,
    'MySQL 8.0.36 rejeita SELECT ON `mysql`.`proc` (tabela removida no MySQL 8)');

// MySQL 8.0.18 (< 8.0.20: SHOW_ROUTINE nao existia)
$evalMysql8018Show = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SHOW_ROUTINE ON *.* TO `u`@`localhost`"],
    $dbUol, '8.0.18', 'MySQL Community Server'
);
ok($evalMysql8018Show['has_routine_definition_priv'] === false,
    'MySQL 8.0.18 (< 8.0.20) nao aceita SHOW_ROUTINE (introduzido somente no 8.0.20)');

$evalMysql8018GlobalAll = rumoEvaluateMysqlPrivileges(
    ["GRANT ALL PRIVILEGES ON *.* TO `u`@`localhost`"],
    $dbUol, '8.0.18', 'MySQL Community Server'
);
ok($evalMysql8018GlobalAll['has_routine_definition_priv'] === true && $evalMysql8018GlobalAll['routine_priv_basis'] === 'global:ALL_PRIVILEGES',
    'MySQL 8.0.18 aceita ALL PRIVILEGES ON *.*');

// MySQL 5.7
$evalMysql57Proc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, '5.7.44', 'MySQL Community Server'
);
ok($evalMysql57Proc['has_routine_definition_priv'] === true && $evalMysql57Proc['routine_priv_basis'] === 'mysql.proc:SELECT',
    'MySQL 5.7.44 aceita SELECT ON `mysql`.`proc`');

// MariaDB 10.6 / 10.11
$evalMariaProc = rumoEvaluateMysqlPrivileges(
    [$usageGrant, $cpanelEscapedGrant, "GRANT SELECT ON `mysql`.`proc` TO `u`@`localhost`"],
    $dbUol, '10.6.18-MariaDB-cll-lve', 'MariaDB Server'
);
ok($evalMariaProc['server']['is_mariadb'] === true && $evalMariaProc['has_routine_definition_priv'] === true && $evalMariaProc['routine_priv_basis'] === 'mysql.proc:SELECT',
    'MariaDB 10.6.18 aceita SELECT ON `mysql`.`proc`');

$evalMariaSchemaOnly = rumoEvaluateMysqlPrivileges(
    [$usageGrant, "GRANT ALL PRIVILEGES ON `rumoacame144a99e\\_rumohub`.* TO `u`@`localhost`"],
    $dbUol, '10.6.18-MariaDB-cll-lve', 'MariaDB Server'
);
ok($evalMariaSchemaOnly['has_routine_definition_priv'] === false,
    'MariaDB 10.6.18 rejeita ALL PRIVILEGES apenas no nivel do schema para leitura completa de mysql.proc');

// -------------------------------------------------------------------------
// 3. Evidencia administrativa confiavel de ausencia de rotinas (fail-closed)
// -------------------------------------------------------------------------
echo "\n--- 3. Evidencia administrativa de ausencia de rotinas (fail-closed) ---\n";
$srvMysql8 = rumoParseDbServerVersion('8.0.36', 'MySQL Community Server');
$uuid = '11111111-2222-3333-4444-555555555555';
$now = strtotime('2026-11-24T12:00:00Z');
$validAttestation = [
    'schema' => 'rumo-routine-absence-attestation-v1',
    'database' => $dbUol,
    'server_engine' => 'mysql',
    'server_version' => '8.0.36',
    'source_uuid' => $uuid,
    'attested_routine_count' => 0,
    'verification_method' => 'cpanel_privileged_admin_inspection',
    'attested_by' => 'owner',
    'attested_at' => '2026-11-24T10:00:00Z',
];

$receipt = rumoValidateRoutineAbsenceAttestation($validAttestation, $dbUol, $srvMysql8, $uuid, 0, $now);
ok($receipt['mode'] === 'administrative_absence_attestation' && $receipt['attested_routine_count'] === 0,
    'Aceita evidencia administrativa completa e amarrada ao servidor/versao/UUID quando count visivel == 0');

// Rotinas presentes (>0) contradizem o atestado -> BLOQUEIO obrigatorio!
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation($validAttestation, $dbUol, $srvMysql8, $uuid, 2, $now),
    'contradicted by 2 visible routine',
    'Rejeita atestado de ausencia quando ha rotinas visiveis (> 0) no database'
);

// Declaracoes nao verificadas / campos divergentes -> BLOQUEIO fail-closed!
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(['skip_routines' => true], $dbUol, $srvMysql8, $uuid, 0, $now),
    'Unsupported routine absence attestation schema',
    'Rejeita declaracao informal nao verificada (skip_routines=true)'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['database' => 'other_db']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'database mismatch',
    'Rejeita atestado emitido para outro database'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['server_engine' => 'mariadb']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'server engine mismatch',
    'Rejeita atestado com engine divergente'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['server_version' => '8.0.35']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'server version mismatch',
    'Rejeita atestado com versao divergente'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['source_uuid' => 'other-uuid']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'source_uuid mismatch',
    'Rejeita atestado com source_uuid divergente'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['attested_routine_count' => 1]), $dbUol, $srvMysql8, $uuid, 0, $now),
    'attested_routine_count === 0',
    'Rejeita atestado com attested_routine_count != 0'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['attested_at' => '2026-11-24T13:00:00Z']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'non-future attested_at',
    'Rejeita atestado com timestamp futuro'
);
mustFail(
    fn() => rumoValidateRoutineAbsenceAttestation(array_replace($validAttestation, ['expires_at' => '2026-11-24T11:00:00Z']), $dbUol, $srvMysql8, $uuid, 0, $now),
    'has expired',
    'Rejeita atestado expirado'
);

echo "\n=== TODOS OS {$passed} TESTES DE PRIVILEGIOS DE ROTINAS PASSARAM ===\n";
