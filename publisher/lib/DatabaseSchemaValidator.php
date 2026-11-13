<?php
declare(strict_types=1);

namespace Rumo\Publisher;

use PDO;
use RuntimeException;

final class DatabaseSchemaValidator {
    public static function defaultManifest(): array {
        $path = dirname(__DIR__).'/config/rumo10-schema-manifest.json';
        if (is_file($path)) {
            return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        return [
            'schema' => 'rumo10-schema-manifest-v2',
            'project' => 'rumo10',
            'table_prefix' => 'rumo10_',
            'migrations_table' => 'rumo10_migrations',
            'versions' => [
                '001' => ['tracked_in_migrations' => false, 'tables' => [
                    'rumo10_migrations', 'rumo10_contas', 'rumo10_login_limits', 'rumo10_clubes',
                    'rumo10_carreiras', 'rumo10_atributos', 'rumo10_estados', 'rumo10_temporadas',
                    'rumo10_times', 'rumo10_partidas', 'rumo10_estatisticas', 'rumo10_eventos_partida',
                    'rumo10_situacoes', 'rumo10_opcoes', 'rumo10_decisoes', 'rumo10_ofertas',
                    'rumo10_transferencias'
                ]],
                '002' => ['tracked_in_migrations' => false, 'tables' => [
                    'rumo10_universos', 'rumo10_carreiras_arquivadas', 'rumo10_competicoes', 'rumo10_clubes_oficiais'
                ]],
                '003' => ['tracked_in_migrations' => false, 'tables' => ['rumo10_conta_planos']],
                '004' => ['tracked_in_migrations' => false, 'tables' => ['rumo10_historico_encerrado']],
                '005' => ['tracked_in_migrations' => false, 'tables' => ['rumo10_origem_vagas', 'rumo10_criacoes', 'rumo10_beneficios_outbox']],
                '006' => ['tracked_in_migrations' => true, 'tables' => [
                    'rumo10_configuracoes_sistema', 'rumo10_balanceamentos', 'rumo10_formulas',
                    'rumo10_formula_versoes', 'rumo10_formula_parametros', 'rumo10_balanceamento_formulas',
                    'rumo10_formula_alteracoes'
                ]],
                '006-seed' => ['tracked_in_migrations' => true, 'tables' => []],
                '007' => ['tracked_in_migrations' => true, 'tables' => []],
                '008' => ['tracked_in_migrations' => true, 'tables' => []],
                '009' => ['tracked_in_migrations' => true, 'tables' => [
                    'rumo10_f01_respostas', 'rumo10_f01_partidas', 'rumo10_f01_aparicoes',
                    'rumo10_f01_eventos', 'rumo10_f01_outbox'
                ]],
            ]
        ];
    }

    public static function keyTableContracts(): array {
        return [
            'rumo10_migrations' => [
                'columns' => [
                    'version' => ['type' => 'varchar(40)', 'nullable' => false],
                ],
                'primary_key' => ['version'],
                'unique_keys' => [],
                'indexes' => [],
                'foreign_keys' => [],
            ],
            'rumo10_contas' => [
                'columns' => [
                    'id' => ['type' => 'bigint unsigned', 'nullable' => false, 'extra' => 'auto_increment'],
                    'email' => ['type' => 'varchar(190)', 'nullable' => false],
                    'senha_hash' => ['type' => 'varchar(255)', 'nullable' => false],
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    'email' => ['email'],
                ],
                'indexes' => [],
                'foreign_keys' => [],
            ],
            'rumo10_carreiras' => [
                'columns' => [
                    'id' => ['type' => 'bigint unsigned', 'nullable' => false, 'extra' => 'auto_increment'],
                    'conta_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'nome' => ['type' => 'varchar(60)', 'nullable' => false],
                    'clube' => ['type' => 'varchar(120)', 'nullable' => false],
                    'posicao' => ['type' => 'varchar(20)', 'nullable' => false],
                    'versao' => ['type' => 'int', 'nullable' => false],
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    'conta_id' => ['conta_id'],
                ],
                'indexes' => [],
                'foreign_keys' => [
                    'fk_carreiras_conta' => ['columns' => ['conta_id'], 'ref_table' => 'rumo10_contas', 'ref_columns' => ['id']],
                ],
            ],
            'rumo10_f01_aparicoes' => [
                'columns' => [
                    'appearance_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'match_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'carreira_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'estado_participacao' => ['type' => 'varchar(32)', 'nullable' => false],
                ],
                'primary_key' => ['appearance_uid'],
                'unique_keys' => [
                    'uk_f01_partida_aparicao' => ['match_uid', 'appearance_uid'],
                    'uk_f01_partida_carreira' => ['match_uid', 'carreira_id'],
                ],
                'indexes' => [],
                'foreign_keys' => [
                    'fk_f01_aparicoes_carreira' => ['columns' => ['carreira_id'], 'ref_table' => 'rumo10_carreiras', 'ref_columns' => ['id'], 'on_delete' => 'CASCADE'],
                    'fk_f01_aparicoes_partida' => ['columns' => ['match_uid'], 'ref_table' => 'rumo10_f01_partidas', 'ref_columns' => ['match_uid'], 'on_delete' => 'CASCADE'],
                ],
            ],
            'rumo10_f01_eventos' => [
                'columns' => [
                    'event_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'carreira_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'tipo_evento' => ['type' => 'varchar(64)', 'nullable' => false],
                    'dados_evento_json' => ['type' => 'json', 'nullable' => false],
                ],
                'primary_key' => ['event_uid'],
                'unique_keys' => [
                    'uk_f01_eventos_partida_seq' => ['match_uid', 'sequencia_partida'],
                ],
                'indexes' => [
                    'ix_f01_eventos_tipo' => ['tipo_evento'],
                    'ix_f01_eventos_carreira' => ['carreira_id'],
                ],
                'foreign_keys' => [
                    'fk_f01_eventos_aparicao_composta' => ['columns' => ['match_uid', 'appearance_uid'], 'ref_table' => 'rumo10_f01_aparicoes', 'ref_columns' => ['match_uid', 'appearance_uid'], 'on_delete' => 'CASCADE'],
                    'fk_f01_eventos_carreira' => ['columns' => ['carreira_id'], 'ref_table' => 'rumo10_carreiras', 'ref_columns' => ['id'], 'on_delete' => 'CASCADE'],
                    'fk_f01_eventos_partida' => ['columns' => ['match_uid'], 'ref_table' => 'rumo10_f01_partidas', 'ref_columns' => ['match_uid'], 'on_delete' => 'CASCADE'],
                ],
            ],
            'rumo10_f01_outbox' => [
                'columns' => [
                    'id' => ['type' => 'bigint unsigned', 'nullable' => false, 'extra' => 'auto_increment'],
                    'carreira_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'command_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'chave' => ['type' => 'char(64)', 'nullable' => false],
                    'payload_json' => ['type' => 'json', 'nullable' => false],
                    'estado' => ['type' => "enum('pendente','processado','falha')", 'nullable' => false],
                ],
                'primary_key' => ['id'],
                'unique_keys' => [
                    'uk_f01_outbox_carreira_chave' => ['carreira_id', 'chave'],
                ],
                'indexes' => [
                    'ix_f01_outbox_estado' => ['estado', 'id'],
                ],
                'foreign_keys' => [
                    'fk_f01_outbox_carreira' => ['columns' => ['carreira_id'], 'ref_table' => 'rumo10_carreiras', 'ref_columns' => ['id'], 'on_delete' => 'CASCADE'],
                ],
            ],
            'rumo10_f01_partidas' => [
                'columns' => [
                    'match_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'carreira_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'temporada_numero' => ['type' => 'int', 'nullable' => false],
                    'mandante' => ['type' => 'varchar(120)', 'nullable' => false],
                    'visitante' => ['type' => 'varchar(120)', 'nullable' => false],
                    'modo_resolucao' => ['type' => "enum('normal','rapido','expresso')", 'nullable' => false],
                ],
                'primary_key' => ['match_uid'],
                'unique_keys' => [],
                'indexes' => [
                    'ix_f01_partidas_carreira_temp' => ['carreira_id', 'temporada_numero'],
                    'ix_f01_partidas_batch' => ['batch_uid', 'sequencia_no_batch'],
                    'ix_f01_partidas_legacy' => ['legacy_match_id'],
                ],
                'foreign_keys' => [
                    'fk_f01_partidas_carreira' => ['columns' => ['carreira_id'], 'ref_table' => 'rumo10_carreiras', 'ref_columns' => ['id'], 'on_delete' => 'CASCADE'],
                ],
            ],
            'rumo10_f01_respostas' => [
                'columns' => [
                    'carreira_id' => ['type' => 'bigint unsigned', 'nullable' => false],
                    'chave' => ['type' => 'char(64)', 'nullable' => false],
                    'command_uid' => ['type' => 'char(36)', 'nullable' => false],
                    'request_hash' => ['type' => 'char(64)', 'nullable' => false],
                ],
                'primary_key' => ['carreira_id', 'chave'],
                'unique_keys' => [
                    'uk_f01_respostas_command' => ['command_uid'],
                ],
                'indexes' => [],
                'foreign_keys' => [
                    'fk_f01_respostas_carreira' => ['columns' => ['carreira_id'], 'ref_table' => 'rumo10_carreiras', 'ref_columns' => ['id'], 'on_delete' => 'CASCADE'],
                ],
            ],
        ];
    }

    public static function resolveExpectedTables(array $manifest, array $appliedVersions): array {
        $expected = [];
        $versionsMap = $manifest['versions'] ?? [];
        foreach ($versionsMap as $verKey => $verData) {
            $isTracked = $verData['tracked_in_migrations'] ?? true;
            // Versões não rastreadas (001-005) são o baseline de produção.
            // Versões rastreadas são ativadas se registradas no banco ou se prefixo bater (ex: 010-future-feature -> 010).
            $isActive = !$isTracked;
            if ($isTracked) {
                foreach ($appliedVersions as $av) {
                    if ($av === (string)$verKey || str_starts_with($av, (string)$verKey)) {
                        $isActive = true;
                        break;
                    }
                }
            }
            if ($isActive) {
                foreach ($verData['tables'] ?? [] as $t) {
                    $expected[] = $t;
                }
            }
        }
        return array_values(array_unique($expected));
    }

    public static function validate(PDO $db, ?array $manifest = null, ?array $requiredVersions = null): array {
        $manifest ??= self::defaultManifest();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            return self::validateMySQL($db, $manifest, $requiredVersions);
        }

        if ($driver === 'sqlite') {
            return self::validateSQLite($db, $manifest, $requiredVersions);
        }

        throw new RuntimeException("Unsupported database driver for structural validation: {$driver}");
    }

    private static function validateMySQL(PDO $db, array $manifest, ?array $requiredVersions = null): array {
        $currentDb = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        if ($currentDb === '') {
            throw new RuntimeException('Database name resolution required');
        }

        // 1. Inspecionar migrations registradas
        $appliedVersions = $db->query('SELECT version FROM rumo10_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);

        // Se houver versões explicitamente requeridas pela release / policy, validar presença
        if ($requiredVersions !== null) {
            foreach ($requiredVersions as $reqVer) {
                $verStr = (string)$reqVer;
                if (!in_array($verStr, $appliedVersions, true)) {
                    throw new RuntimeException("Required migration version missing from rumo10_migrations: {$verStr}");
                }
            }
        }

        // 2. Determinar dinamicamente as tabelas esperadas para a evolução atual do banco
        $expectedTables = self::resolveExpectedTables($manifest, $appliedVersions);

        // 3. Consultar tabelas reais com isolamento do prefixo rumo10_ (ignorando rumohub_*)
        $stmt = $db->prepare("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE 'rumo10_%' AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");
        $stmt->execute([$currentDb]);
        $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Validar que todas as tabelas esperadas para as migrações aplicadas estão presentes
        foreach ($expectedTables as $expectedTable) {
            if (!in_array($expectedTable, $actualTables, true)) {
                throw new RuntimeException("Mandatory Rumo10 table missing for applied schema: {$expectedTable}");
            }
        }

        // 4. Validar contratos detalhados das tabelas-chave presentes
        $contracts = self::keyTableContracts();
        foreach ($contracts as $table => $contract) {
            if (in_array($table, $actualTables, true)) {
                self::assertTableStructure($db, $currentDb, $table, $contract);
            }
        }

        return [
            'ok' => true,
            'driver' => 'mysql',
            'database' => $currentDb,
            'schema_version' => end($appliedVersions) ?: 'baseline',
            'table_count' => count($actualTables),
            'expected_table_count' => count($expectedTables),
            'tables' => $actualTables,
            'versions_registered' => $appliedVersions,
        ];
    }

    private static function validateSQLite(PDO $db, array $manifest, ?array $requiredVersions = null): array {
        $appliedVersions = $db->query('SELECT version FROM rumo10_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);

        if ($requiredVersions !== null) {
            foreach ($requiredVersions as $reqVer) {
                $verStr = (string)$reqVer;
                if (!in_array($verStr, $appliedVersions, true)) {
                    throw new RuntimeException("Required migration version missing from rumo10_migrations: {$verStr}");
                }
            }
        }

        $expectedTables = self::resolveExpectedTables($manifest, $appliedVersions);

        $stmt = $db->query("
            SELECT name 
            FROM sqlite_master 
            WHERE type = 'table' AND name LIKE 'rumo10_%'
            ORDER BY name
        ");
        $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $expectedTable) {
            if (!in_array($expectedTable, $actualTables, true)) {
                throw new RuntimeException("Mandatory Rumo10 table missing for applied schema: {$expectedTable}");
            }
        }

        return [
            'ok' => true,
            'driver' => 'sqlite',
            'schema_version' => end($appliedVersions) ?: 'baseline',
            'table_count' => count($actualTables),
            'expected_table_count' => count($expectedTables),
            'tables' => $actualTables,
            'versions_registered' => $appliedVersions,
        ];
    }

    private static function assertTableStructure(PDO $db, string $currentDb, string $table, array $contract): void {
        // Colunas
        $stmt = $db->prepare('
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ');
        $stmt->execute([$currentDb, $table]);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $actualCols = [];
        foreach ($cols as $c) {
            $actualCols[$c['COLUMN_NAME']] = [
                'type' => strtolower($c['COLUMN_TYPE']),
                'nullable' => $c['IS_NULLABLE'] === 'YES',
                'default' => $c['COLUMN_DEFAULT'],
                'extra' => strtolower($c['EXTRA'] ?? ''),
            ];
        }

        foreach ($contract['columns'] as $colName => $expected) {
            if (!isset($actualCols[$colName])) {
                throw new RuntimeException("Table `{$table}` missing column `{$colName}`");
            }
            $actual = $actualCols[$colName];
            if (strtolower($actual['type']) !== strtolower($expected['type'])) {
                throw new RuntimeException("Table `{$table}` column `{$colName}` type mismatch: expected {$expected['type']}, got {$actual['type']}");
            }
            if ($actual['nullable'] !== $expected['nullable']) {
                throw new RuntimeException("Table `{$table}` column `{$colName}` nullability mismatch");
            }
            if (isset($expected['extra']) && !str_contains($actual['extra'], strtolower($expected['extra']))) {
                throw new RuntimeException("Table `{$table}` column `{$colName}` extra mismatch: expected '{$expected['extra']}', got '{$actual['extra']}'");
            }
        }

        // Chave Primária
        if (isset($contract['primary_key']) && !empty($contract['primary_key'])) {
            $priStmt = $db->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
                ORDER BY ORDINAL_POSITION
            ");
            $priStmt->execute([$currentDb, $table]);
            $priCols = $priStmt->fetchAll(PDO::FETCH_COLUMN);
            if ($priCols !== $contract['primary_key']) {
                throw new RuntimeException("Table `{$table}` PRIMARY KEY mismatch: expected " . json_encode($contract['primary_key']) . ", got " . json_encode($priCols));
            }
        }

        // Índices e Unique Keys
        if (!empty($contract['unique_keys']) || !empty($contract['indexes'])) {
            $statStmt = $db->prepare('
                SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
            ');
            $statStmt->execute([$currentDb, $table]);
            $stats = $statStmt->fetchAll(PDO::FETCH_ASSOC);
            $actualIndexes = [];
            foreach ($stats as $st) {
                $idxName = $st['INDEX_NAME'];
                if ($idxName === 'PRIMARY') continue;
                if (!isset($actualIndexes[$idxName])) {
                    $actualIndexes[$idxName] = [
                        'unique' => ((int)$st['NON_UNIQUE']) === 0,
                        'columns' => []
                    ];
                }
                $actualIndexes[$idxName]['columns'][] = $st['COLUMN_NAME'];
            }

            foreach (($contract['unique_keys'] ?? []) as $uName => $uCols) {
                if (!isset($actualIndexes[$uName])) {
                    throw new RuntimeException("Table `{$table}` missing UNIQUE constraint `{$uName}`");
                }
                if ($actualIndexes[$uName]['unique'] !== true) {
                    throw new RuntimeException("Index `{$uName}` on `{$table}` is not UNIQUE");
                }
                if ($actualIndexes[$uName]['columns'] !== $uCols) {
                    throw new RuntimeException("Table `{$table}` UNIQUE `{$uName}` columns mismatch");
                }
            }

            foreach (($contract['indexes'] ?? []) as $idxName => $idxCols) {
                if (!isset($actualIndexes[$idxName])) {
                    throw new RuntimeException("Table `{$table}` missing secondary index `{$idxName}`");
                }
                if ($actualIndexes[$idxName]['columns'] !== $idxCols) {
                    throw new RuntimeException("Table `{$table}` index `{$idxName}` columns mismatch");
                }
            }
        }

        // Foreign Keys
        if (!empty($contract['foreign_keys'])) {
            $fkStmt = $db->prepare("
                SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r 
                  ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.TABLE_NAME = r.TABLE_NAME
                WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION
            ");
            $fkStmt->execute([$currentDb, $table]);
            $fks = $fkStmt->fetchAll(PDO::FETCH_ASSOC);
            $actualFks = [];
            foreach ($fks as $fk) {
                $cname = $fk['CONSTRAINT_NAME'];
                if (!isset($actualFks[$cname])) {
                    $actualFks[$cname] = [
                        'columns' => [],
                        'ref_table' => $fk['REFERENCED_TABLE_NAME'],
                        'ref_columns' => [],
                        'on_delete' => strtoupper($fk['DELETE_RULE'] ?? 'RESTRICT')
                    ];
                }
                $actualFks[$cname]['columns'][] = $fk['COLUMN_NAME'];
                $actualFks[$cname]['ref_columns'][] = $fk['REFERENCED_COLUMN_NAME'];
            }

            foreach ($contract['foreign_keys'] as $fkName => $expFk) {
                $found = false;
                foreach ($actualFks as $actualFk) {
                    if ($actualFk['ref_table'] === $expFk['ref_table'] &&
                        $actualFk['columns'] === $expFk['columns'] &&
                        $actualFk['ref_columns'] === $expFk['ref_columns']) {
                        $expDelete = strtoupper($expFk['on_delete'] ?? 'RESTRICT');
                        if ($actualFk['on_delete'] !== $expDelete) {
                            throw new RuntimeException("Table `{$table}` foreign key `{$fkName}` ON DELETE rule mismatch: expected {$expDelete}, got {$actualFk['on_delete']}");
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new RuntimeException("Table `{$table}` missing foreign key pointing to `{$expFk['ref_table']}`");
                }
            }
        }
    }
}
