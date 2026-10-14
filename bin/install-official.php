<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
try {
    $db=Database::connect();$r=new Repository($db);
    foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/002.sql')) as $sql) {
        $sql=trim($sql);if(!$sql)continue;
        if(!preg_match('/^(CREATE TABLE IF NOT EXISTS rumo10_[a-z_]+ |ALTER TABLE rumo10_carreiras MODIFY conta_id )/',$sql))throw new RuntimeException('Migration fora do prefixo.');
        $db->exec($sql);
    }
    $db->exec('CREATE TABLE IF NOT EXISTS rumo10_catalogo_clubes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(120) NOT NULL UNIQUE, FOREIGN KEY(codigo) REFERENCES rumo10_clubes_oficiais(codigo) ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->beginTransaction();
    foreach(Competition::catalog() as $id=>$c) {
        $json=json_encode($c,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $r->query('INSERT INTO rumo10_competicoes VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=?,formato=?,dados_json=?,fonte_url=?',[$id,$c['name'],2026,$c['format'],$json,$c['source'],$c['name'],$c['format'],$json,$c['source']]);
    }
    foreach(Competition::clubs() as $id=>$c) {
        $json=json_encode($c,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $r->query('INSERT INTO rumo10_clubes_oficiais VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE dados_json=?,fonte_url=?',[$id,$c['name'],$c['uf'],$json,$c['source'],$json,$c['source']]);
        $r->query('INSERT IGNORE INTO rumo10_catalogo_clubes(codigo) VALUES(?)',[$id]);
    }
    $r->query('INSERT IGNORE INTO rumo10_migrations(version) VALUES(?)',['002']);$db->commit();
    echo 'OK: migration 002, '.count(Competition::clubs()).' clubes, '.count(Competition::catalog())." competições. Carreiras existentes preservadas.\n";
} catch(Throwable $e) {
    if(isset($db) && $db->inTransaction())$db->rollBack();fwrite(STDERR,'Migration 002 não concluída: '.$e::class.' código '.$e->getCode().PHP_EOL);exit(1);
}
