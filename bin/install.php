<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $db=Database::connect();
    $before=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Banco existente confirmado. Tabelas externas preservadas: '.count(array_filter($before,fn($t)=>!str_starts_with($t,'rumo10_'))).PHP_EOL;
    $sql=file_get_contents(dirname(__DIR__).'/database/001.sql');
    foreach(explode(';',$sql) as $statement) {
        $statement=trim($statement); if(!$statement) continue;
        if(!preg_match('/^CREATE TABLE IF NOT EXISTS rumo10_[a-z_]+ /',$statement)) throw new RuntimeException('Migration fora do prefixo.');
        $db->exec($statement.' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $r=new Repository($db); $r->query('INSERT IGNORE INTO rumo10_migrations(version) VALUES(?)',['001']);
    $db->beginTransaction();
    $clubs=json_decode(file_get_contents(dirname(__DIR__).'/database/clubs.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($clubs as $c) {
        $r->query('INSERT INTO rumo10_clubes(slug,nome,municipio,origem,cor_primaria_nome,cor_primaria_hex,cor_secundaria_nome,cor_secundaria_hex,cor_terciaria_nome,cor_terciaria_hex,fonte_cores_url,verificacao) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=?,fonte_cores_url=?',[$c['slug'],$c['nome'],$c['municipio'],$c['origem'],$c['cor_primaria_nome'],$c['cor_primaria_hex'],$c['cor_secundaria_nome'],$c['cor_secundaria_hex'],$c['cor_terciaria_nome'],$c['cor_terciaria_hex'],$c['fonte_cores_url'],$c['verificacao'],$c['nome'],$c['fonte_cores_url']]);
    }
    $all=require dirname(__DIR__).'/database/situations.php';
    foreach($all as $id=>$s) {
        $r->query('INSERT INTO rumo10_situacoes VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE texto=?,versao=?',[$id,$s[0],$s[1],Rules::VERSION,$s[1],Rules::VERSION]);
        foreach($s[2] as $i=>$o) $r->query('INSERT INTO rumo10_opcoes VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE texto=?,efeitos_json=?',[$id,$i,$o[0],json_encode($o[1],JSON_THROW_ON_ERROR),$o[0],json_encode($o[1],JSON_THROW_ON_ERROR)]);
    }
    $db->commit();
    $after=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); if(array_diff($before,$after)) throw new RuntimeException('Inventário inesperado.');
    echo "Migration 001 e seed: 21 clubes, 12 situações e 48 opções. Nenhuma conta demo com senha pública. Registre sua conta na interface.\n";
} catch(Throwable $e) { if(isset($db) && $db->inTransaction()) $db->rollBack(); fwrite(STDERR,'Instalação não concluída. Código: '.$e->getCode().'. Confira conexão e privilégios locais; sem credenciais no log.'.PHP_EOL); exit(1); }
