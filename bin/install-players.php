<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$db=Database::connect();$r=new Repository($db);
// Catálogo global de referência, separado dos snapshots das carreiras.
$db->exec('CREATE TABLE IF NOT EXISTS rumo10_elencos_catalogo (clube VARCHAR(160) PRIMARY KEY, consultado DATE NOT NULL, reais INT UNSIGNED NOT NULL, ficticios INT UNSIGNED NOT NULL, notas_publicadas INT UNSIGNED NOT NULL, dados_json JSON NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS rumo10_jogadores_catalogo (id VARCHAR(100) PRIMARY KEY, nome VARCHAR(160) NOT NULL, nascimento DATE NULL, origem VARCHAR(16) NOT NULL, posicao VARCHAR(32) NOT NULL, overall_referencia SMALLINT UNSIGNED NOT NULL, origem_overall VARCHAR(40) NOT NULL, consultado DATE NOT NULL, dados_json JSON NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$db->exec('CREATE TABLE IF NOT EXISTS rumo10_elenco_jogadores (clube VARCHAR(120) NOT NULL, jogador_id VARCHAR(100) NOT NULL, edicao VARCHAR(16) NOT NULL, PRIMARY KEY(clube,jogador_id), FOREIGN KEY(clube) REFERENCES rumo10_clubes_oficiais(codigo), FOREIGN KEY(jogador_id) REFERENCES rumo10_jogadores_catalogo(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$checked=ClubSquad::catalog()['summary']['checked']??'2026-10-14';
$db->beginTransaction();try {
    foreach(ClubSquad::catalog()['clubs'] as $club=>$entry){
        $players=ClubSquad::players($club);$json=json_encode($players,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$r->query('INSERT INTO rumo10_elencos_catalogo VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE consultado=VALUES(consultado),reais=VALUES(reais),ficticios=VALUES(ficticios),notas_publicadas=VALUES(notas_publicadas),dados_json=VALUES(dados_json)',[$club,$checked,$entry['real'],$entry['fictional'],$entry['published_ratings'],$json]);
        // Apenas vínculos do catálogo de referência; nenhuma simulação ou carreira modifica estes dados.
        $r->query('DELETE FROM rumo10_elenco_jogadores WHERE clube=?',[$club]);
        foreach($players as $p){
            $r->query('INSERT INTO rumo10_jogadores_catalogo VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),nascimento=VALUES(nascimento),origem=VALUES(origem),posicao=VALUES(posicao),overall_referencia=VALUES(overall_referencia),origem_overall=VALUES(origem_overall),consultado=VALUES(consultado),dados_json=VALUES(dados_json)',[$p['id'],$p['name'],$p['birthdate'],$p['origin'],$p['position'],$p['overall'],$p['overall_origin'],$p['checked'],json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $r->query('INSERT INTO rumo10_elenco_jogadores VALUES(?,?,?)',[$club,$p['id'],Competition::clubs()[$club]['edition']??'2026']);
        }
    }
    $db->commit();$s=ClubSquad::catalog()['summary'];echo 'OK: '.$s['clubs'].' elencos; '.$s['real_players'].' reais; '.$s['fictional_players']." fictícios. Carreiras preservadas.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
