CREATE TABLE IF NOT EXISTS rumo10_origem_vagas (
 carreira_id BIGINT UNSIGNED PRIMARY KEY, tipo ENUM('BASE','INDICACAO','LEGADO','PAGO') NOT NULL,
 credito_id BIGINT UNSIGNED NULL UNIQUE, reserva CHAR(64) NULL UNIQUE,
 referencia VARCHAR(120) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(carreira_id) REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_criacoes (
 conta_id BIGINT UNSIGNED NOT NULL, chave CHAR(64) NOT NULL, carreira_id BIGINT UNSIGNED NOT NULL,
 request_hash CHAR(64) NOT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(conta_id,chave), FOREIGN KEY(conta_id) REFERENCES rumo10_contas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_beneficios_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conta_id BIGINT UNSIGNED NOT NULL,
 carreira_id BIGINT UNSIGNED NOT NULL, evento ENUM('primeira_carreira','consumir_credito') NOT NULL,
 chave CHAR(64) NOT NULL UNIQUE, credito_id BIGINT UNSIGNED NULL, reserva CHAR(64) NULL,
 estado ENUM('pendente','confirmado') NOT NULL DEFAULT 'pendente', tentativas INT UNSIGNED NOT NULL DEFAULT 0,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(conta_id) REFERENCES rumo10_contas(id) ON DELETE CASCADE,
 INDEX(conta_id,estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO rumo10_origem_vagas(carreira_id,tipo)
 SELECT id,CASE WHEN ordem<=2 THEN 'BASE' ELSE 'LEGADO' END FROM (
 SELECT c.id,ROW_NUMBER() OVER(PARTITION BY COALESCE(c.conta_id,a.conta_id) ORDER BY c.id) AS ordem
 FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id
 WHERE NOT EXISTS(SELECT 1 FROM rumo10_historico_encerrado h WHERE h.carreira_id=c.id)
 ) origens;
