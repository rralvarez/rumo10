<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};

function getF01HomologatedSchema(): array {
    return [
        "rumo10_f01_aparicoes" => [
            "columns" => [
                "appearance_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "match_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "carreira_id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "condicao_escalacao" => ["type" => "enum('titular','reserva','nao_relacionado')", "nullable" => true, "default" => null],
                "minuto_entrada" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "minuto_saida" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "minutos_jogados" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "estado_participacao" => ["type" => "varchar(32)", "nullable" => false, "default" => null],
                "nota_atuacao" => ["type" => "decimal(4,1)", "nullable" => true, "default" => null],
                "persisted_at_utc" => ["type" => "timestamp(6)", "nullable" => true, "default" => null],
                "projected_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
            ],
            "primary_key" => ["appearance_uid"],
            "foreign_keys" => [
                "fk_f01_aparicoes_carreira" => ["columns" => ["carreira_id"], "ref_table" => "rumo10_carreiras", "ref_columns" => ["id"]],
                "fk_f01_aparicoes_partida" => ["columns" => ["match_uid"], "ref_table" => "rumo10_f01_partidas", "ref_columns" => ["match_uid"]],
            ],
        ],
        "rumo10_f01_eventos" => [
            "columns" => [
                "event_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "match_uid" => ["type" => "char(36)", "nullable" => true, "default" => null],
                "appearance_uid" => ["type" => "char(36)", "nullable" => true, "default" => null],
                "carreira_id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "command_uid" => ["type" => "char(36)", "nullable" => true, "default" => null],
                "tipo_evento" => ["type" => "varchar(64)", "nullable" => false, "default" => null],
                "sequencia_partida" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "minuto_jogo" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "dados_evento_json" => ["type" => "json", "nullable" => false, "default" => null],
                "persisted_at_utc" => ["type" => "timestamp(6)", "nullable" => true, "default" => null],
                "projected_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
            ],
            "primary_key" => ["event_uid"],
            "foreign_keys" => [
                "fk_f01_eventos_aparicao_composta" => ["columns" => ["match_uid", "appearance_uid"], "ref_table" => "rumo10_f01_aparicoes", "ref_columns" => ["match_uid", "appearance_uid"]],
                "fk_f01_eventos_carreira" => ["columns" => ["carreira_id"], "ref_table" => "rumo10_carreiras", "ref_columns" => ["id"]],
                "fk_f01_eventos_partida" => ["columns" => ["match_uid"], "ref_table" => "rumo10_f01_partidas", "ref_columns" => ["match_uid"]],
            ],
        ],
        "rumo10_f01_outbox" => [
            "columns" => [
                "id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "carreira_id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "command_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "chave" => ["type" => "char(64)", "nullable" => false, "default" => null],
                "engine_commit" => ["type" => "varchar(40)", "nullable" => true, "default" => null],
                "total_eventos" => ["type" => "int unsigned", "nullable" => false, "default" => "1"],
                "payload_json" => ["type" => "json", "nullable" => false, "default" => null],
                "estado" => ["type" => "enum('pendente','processado','falha')", "nullable" => false, "default" => "pendente"],
                "tentativas" => ["type" => "int unsigned", "nullable" => false, "default" => "0"],
                "erro" => ["type" => "text", "nullable" => true, "default" => null],
                "received_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
                "persisted_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
                "projected_at_utc" => ["type" => "timestamp(6)", "nullable" => true, "default" => null],
            ],
            "primary_key" => ["id"],
            "foreign_keys" => [
                "fk_f01_outbox_carreira" => ["columns" => ["carreira_id"], "ref_table" => "rumo10_carreiras", "ref_columns" => ["id"]],
            ],
        ],
        "rumo10_f01_partidas" => [
            "columns" => [
                "match_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "carreira_id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "temporada_numero" => ["type" => "int", "nullable" => false, "default" => null],
                "competicao_codigo" => ["type" => "varchar(64)", "nullable" => true, "default" => null],
                "rodada_fase" => ["type" => "varchar(32)", "nullable" => true, "default" => null],
                "mandante" => ["type" => "varchar(120)", "nullable" => false, "default" => null],
                "visitante" => ["type" => "varchar(120)", "nullable" => false, "default" => null],
                "gols_mandante" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "gols_visitante" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "modo_resolucao" => ["type" => "enum('normal','rapido','expresso')", "nullable" => false, "default" => null],
                "batch_uid" => ["type" => "char(36)", "nullable" => true, "default" => null],
                "sequencia_no_batch" => ["type" => "int unsigned", "nullable" => true, "default" => null],
                "legacy_match_id" => ["type" => "bigint unsigned", "nullable" => true, "default" => null],
                "source_kind" => ["type" => "varchar(32)", "nullable" => false, "default" => "canonical_v8_f01"],
                "identity_confidence" => ["type" => "varchar(32)", "nullable" => false, "default" => "exact"],
                "contract_version" => ["type" => "int unsigned", "nullable" => false, "default" => "1"],
                "engine_commit" => ["type" => "varchar(40)", "nullable" => true, "default" => null],
                "received_at_utc" => ["type" => "timestamp(6)", "nullable" => true, "default" => null],
                "persisted_at_utc" => ["type" => "timestamp(6)", "nullable" => true, "default" => null],
                "projected_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
            ],
            "primary_key" => ["match_uid"],
            "foreign_keys" => [
                "fk_f01_partidas_carreira" => ["columns" => ["carreira_id"], "ref_table" => "rumo10_carreiras", "ref_columns" => ["id"]],
            ],
        ],
        "rumo10_f01_respostas" => [
            "columns" => [
                "carreira_id" => ["type" => "bigint unsigned", "nullable" => false, "default" => null],
                "chave" => ["type" => "char(64)", "nullable" => false, "default" => null],
                "command_uid" => ["type" => "char(36)", "nullable" => false, "default" => null],
                "request_hash" => ["type" => "char(64)", "nullable" => false, "default" => null],
                "versao_anterior" => ["type" => "int", "nullable" => false, "default" => null],
                "versao_resultante" => ["type" => "int", "nullable" => false, "default" => null],
                "contract_version" => ["type" => "int unsigned", "nullable" => false, "default" => "1"],
                "resposta_json" => ["type" => "json", "nullable" => false, "default" => null],
                "received_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
                "persisted_at_utc" => ["type" => "timestamp(6)", "nullable" => false, "default" => "CURRENT_TIMESTAMP(6)"],
            ],
            "primary_key" => ["carreira_id", "chave"],
            "foreign_keys" => [
                "fk_f01_respostas_carreira" => ["columns" => ["carreira_id"], "ref_table" => "rumo10_carreiras", "ref_columns" => ["id"]],
            ],
        ],
    ];
}

function assertF01TableStructure(PDO $db, string $table, array $contract): void {
    $currentDb = (string)$db->query("SELECT DATABASE()")->fetchColumn();
    need($currentDb !== '', 'Database name resolution required');

    // 1. Validação de Colunas (nome, tipo, nulidade e valor padrão)
    $stmt = $db->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
    ");
    $stmt->execute([$currentDb, $table]);
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $actualCols = [];
    foreach ($cols as $c) {
        $actualCols[$c['COLUMN_NAME']] = [
            'type' => strtolower($c['COLUMN_TYPE']),
            'nullable' => $c['IS_NULLABLE'] === 'YES',
            'default' => $c['COLUMN_DEFAULT']
        ];
    }

    foreach ($contract['columns'] as $colName => $expected) {
        need(isset($actualCols[$colName]), "Table `{$table}` missing column `{$colName}`");
        $actual = $actualCols[$colName];
        need(strtolower($actual['type']) === strtolower($expected['type']),
            "Table `{$table}` column `{$colName}` type mismatch: expected {$expected['type']}, got {$actual['type']}");
        need($actual['nullable'] === $expected['nullable'],
            "Table `{$table}` column `{$colName}` nullability mismatch: expected " . ($expected['nullable'] ? 'NULL' : 'NOT NULL'));
        
        $expDef = $expected['default'];
        $actDef = $actual['default'];
        if ($expDef !== null) {
            need($actDef !== null, "Table `{$table}` column `{$colName}` default mismatch: expected {$expDef}, got NULL");
            need(strtoupper($expDef) === strtoupper($actDef) || trim($expDef, "'\"") === trim($actDef, "'\""),
                "Table `{$table}` column `{$colName}` default mismatch: expected {$expDef}, got {$actDef}");
        }
    }

    // 2. Validação de Chave Primária
    $priStmt = $db->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
    ");
    $priStmt->execute([$currentDb, $table]);
    $priCols = $priStmt->fetchAll(PDO::FETCH_COLUMN);
    need($priCols === $contract['primary_key'],
        "Table `{$table}` PRIMARY KEY mismatch: expected " . json_encode($contract['primary_key']) . ", got " . json_encode($priCols));

    // 3. Validação de Foreign Keys
    $fkStmt = $db->prepare("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
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
                'ref_columns' => []
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
                $found = true;
                break;
            }
        }
        need($found, "Table `{$table}` missing foreign key pointing to `{$expFk['ref_table']}` on (" . implode(',', $expFk['columns']) . ")");
    }
}

try {
    need(PHP_SAPI==='cli'&&($argv[1]??'')==='migrate'&&($argv[2]??'')==='--profile','Private migration provider only');
    $file=$argv[3];
    need(is_file($file)&&!is_link($file),'Private migration profile required');
    if(PHP_OS_FAMILY!=='Windows')need((fileperms($file)&0077)===0,'Private profile permissions');

    $c=json(file_get_contents($file));
    $q=json(stream_get_contents(STDIN));
    need($q['project']==='rumo10'&&$c['project']==='rumo10','Only Rumo10 incremental migrations');

    $policy=$q['update_policy'];
    need($policy['class']==='B'&&$policy['compatible_writers_ci'],'Compatible CI required');

    $stage=$q['candidate']['stage'].'/payload';
    foreach($policy['migrations'] as $path) {
        need(($c['approved_incremental_migrations'][$path]??null)===digest($stage.'/'.$path),'Unapproved SQL or seed');
    }

    require $c['current'].'/app/bootstrap.php';
    $db=Database::connect();
    need((int)$db->query("SELECT GET_LOCK('rumo10-publisher-migrations',0)")->fetchColumn()===1,'Concurrent migration refused');

    try {
        $versions=$db->query('SELECT version FROM rumo10_migrations')->fetchAll(PDO::FETCH_COLUMN);

        // Migrações 006 (balance e seed)
        if(!in_array('006',$versions,true)){
            $db->exec(file_get_contents($stage.'/database/006-balance-and-maintenance.sql'));
            $db->exec("INSERT INTO rumo10_migrations(version) VALUES('006')");
        }
        $db->beginTransaction();
        if(!in_array('006-seed',$versions,true)){
            require $stage.'/database/seed_balance.php';
            seed_balance_data($db);
            $db->exec("INSERT INTO rumo10_migrations(version) VALUES('006-seed')");
        }

        // Migrações 007 e 008 (DML/parametros transacionais)
        foreach(['007'=>'007-recalibracao-ofensiva.sql','008'=>'008-penalties-balance.sql'] as $version=>$name){
            if(!in_array((string)$version,$versions,true)&&is_file($stage.'/database/'.$name)){
                $db->exec(file_get_contents($stage.'/database/'.$name));
                $db->prepare('INSERT INTO rumo10_migrations(version) VALUES(?)')->execute([(string)$version]);
            }
        }
        need($db->query("SELECT codigo FROM rumo10_balanceamentos WHERE estado='ativo'")->fetchColumn()==='bal-v062-recalibracao-ofensiva','Active balance mismatch');
        need((int)$db->query("SELECT COUNT(*) FROM rumo10_formula_parametros p JOIN rumo10_formula_versoes v ON v.id=p.versao_id WHERE v.formula_id='match.penalties.parameters'")->fetchColumn()>0,'Penalty parameters missing');
        $db->commit();

        // Migration 009: F01 Canonical Telemetry (DDL fora de transação PDO devido a commits implícitos do MySQL)
        $sql009Path = $stage.'/database/009-f01-canonical-telemetry.sql';
        if(is_file($sql009Path)) {
            $expected009Hash = $c['approved_incremental_migrations']['database/009-f01-canonical-telemetry.sql'] ?? null;
            need($expected009Hash !== null && digest($sql009Path) === $expected009Hash, 'Migration 009 SHA-256 integrity validation failed');

            $f01Contract = getF01HomologatedSchema();

            // 1. Inspecionar tabelas existentes para detectar integridade ou incompatibilidade antes de aplicar
            $existingF01 = $db->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existingF01 as $t) {
                if (isset($f01Contract[$t])) {
                    // Se tabela já existe parcialmente, inspecionar que as colunas já presentes não conflitem
                    $stmt = $db->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
                    $stmt->execute([$t]);
                    $existingCols = [];
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ec) {
                        $existingCols[$ec['COLUMN_NAME']] = [
                            'type' => strtolower($ec['COLUMN_TYPE']),
                            'nullable' => $ec['IS_NULLABLE'] === 'YES'
                        ];
                    }
                    foreach ($existingCols as $colName => $actInfo) {
                        if (isset($f01Contract[$t]['columns'][$colName])) {
                            $expInfo = $f01Contract[$t]['columns'][$colName];
                            need(strtolower($actInfo['type']) === strtolower($expInfo['type']),
                                "Incompatible column type in existing table `{$t}`.`{$colName}`: expected {$expInfo['type']}, got {$actInfo['type']}");
                            need($actInfo['nullable'] === $expInfo['nullable'],
                                "Incompatible column nullability in existing table `{$t}`.`{$colName}`");
                        }
                    }
                }
            }

            // 2. Executar SQL DDL (com CREATE TABLE IF NOT EXISTS idempotente) se 009 não estiver registrada
            if(!in_array('009', $versions, true)) {
                $db->exec(file_get_contents($sql009Path));

                // 3. Validação estrutural rigorosa pós-execução de todas as 5 tabelas F01 contra o contrato homologado
                foreach ($f01Contract as $tableName => $tContract) {
                    assertF01TableStructure($db, $tableName, $tContract);
                }

                // 4. Registrar versão 009 SOMENTE após validação estrutural completa
                $db->exec("INSERT INTO rumo10_migrations(version) VALUES('009')");
            } else {
                // Se 009 já constava como registrada, atestar que todas as 5 tabelas estão integralmente conformes
                foreach ($f01Contract as $tableName => $tContract) {
                    assertF01TableStructure($db, $tableName, $tContract);
                }
            }
        } elseif (in_array('database/009-f01-canonical-telemetry.sql', $policy['migrations'], true)) {
            need(false, 'Mandatory migration 009 missing from stage payload');
        }

        $allVersions = $db->query('SELECT version FROM rumo10_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        need(in_array('009', $allVersions, true), 'Release requires validated 009 migration');

        $out=['ok'=>true,'compatible_expansion'=>true,'attempt'=>$q['attempt'],'versions'=>$allVersions];
        record($q['private'].'/migrations-'.$q['attempt'].'.json',$out+['approved_hashes'=>$c['approved_incremental_migrations']]);
        echo json_encode($out,JSON_THROW_ON_ERROR);
    } finally {
        if($db->inTransaction())$db->rollBack();
        $db->query("SELECT RELEASE_LOCK('rumo10-publisher-migrations')");
    }
} catch(Throwable $e) {
    fwrite(STDERR,'Incremental migration blocked: '.$e->getMessage().PHP_EOL);
    exit(1);
}
