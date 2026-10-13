<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$db=Database::connect();$r=new Repository($db);
// Catálogo global de referência, separado dos snapshots das carreiras.
$db->exec('CREATE TABLE IF NOT EXISTS rumo10_elencos_catalogo (clube VARCHAR(160) PRIMARY KEY, consultado DATE NOT NULL, reais INT UNSIGNED NOT NULL, ficticios INT UNSIGNED NOT NULL, notas_publicadas INT UNSIGNED NOT NULL, dados_json JSON NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->beginTransaction();try {
    foreach(ClubSquad::catalog()['clubs'] as $club=>$entry){$json=json_encode(ClubSquad::players($club),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$r->query('INSERT INTO rumo10_elencos_catalogo VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE consultado=VALUES(consultado),reais=VALUES(reais),ficticios=VALUES(ficticios),notas_publicadas=VALUES(notas_publicadas),dados_json=VALUES(dados_json)',[$club,'2026-10-13',$entry['real'],$entry['fictional'],$entry['published_ratings'],$json]);}
    $db->commit();$s=ClubSquad::catalog()['summary'];echo 'OK: '.$s['clubs'].' elencos; '.$s['real_players'].' reais; '.$s['fictional_players']." fictícios. Carreiras preservadas.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
