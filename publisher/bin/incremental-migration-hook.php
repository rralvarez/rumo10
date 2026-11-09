<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/Core.php';
use function Rumo\Publisher\{need,json,digest,record};

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

            $f01RequiredTables = [
                'rumo10_f01_respostas' => ['carreira_id','chave','command_uid','request_hash','versao_anterior','versao_resultante','contract_version','resposta_json'],
                'rumo10_f01_partidas' => ['match_uid','carreira_id','temporada_numero','mandante','visitante','modo_resolucao','identity_confidence','contract_version'],
                'rumo10_f01_aparicoes' => ['appearance_uid','match_uid','carreira_id','estado_participacao'],
                'rumo10_f01_eventos' => ['event_uid','carreira_id','tipo_evento','dados_evento_json'],
                'rumo10_f01_outbox' => ['id','carreira_id','command_uid','chave','payload_json','estado']
            ];

            // 1. Inspecionar tabelas existentes para detectar integridade ou incompatibilidade
            $existingF01 = $db->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existingF01 as $t) {
                if (isset($f01RequiredTables[$t])) {
                    $cols = $db->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($f01RequiredTables[$t] as $col) {
                        need(in_array($col, $cols, true), "Incompatible structure detected in existing table `{$t}`: missing column `{$col}`");
                    }
                }
            }

            // 2. Executar SQL DDL (com CREATE TABLE IF NOT EXISTS idempotente) se 009 não estiver registrada
            if(!in_array('009', $versions, true)) {
                $db->exec(file_get_contents($sql009Path));

                // 3. Validação estrutural pós-execução das 5 tabelas e colunas essenciais
                $createdTables = $db->query("SHOW TABLES LIKE 'rumo10_f01_%'")->fetchAll(PDO::FETCH_COLUMN);
                foreach (array_keys($f01RequiredTables) as $reqTable) {
                    need(in_array($reqTable, $createdTables, true), "Post-migration table missing: `{$reqTable}`");
                    $cols = $db->query("SHOW COLUMNS FROM `{$reqTable}`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($f01RequiredTables[$reqTable] as $col) {
                        need(in_array($col, $cols, true), "Post-migration structure incomplete: table `{$reqTable}` missing `{$col}`");
                    }
                }

                // 4. Registrar versão 009 SOMENTE após validação estrutural completa
                $db->exec("INSERT INTO rumo10_migrations(version) VALUES('009')");
            } else {
                // Se 009 já constava como registrada, atestar que todas as 5 tabelas estão de fato presentes
                foreach (array_keys($f01RequiredTables) as $reqTable) {
                    need(in_array($reqTable, $existingF01, true), "Recorded migration 009 missing physical table `{$reqTable}`");
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
