-- Additive only. Back up rumo10_* first. Never apply implicitly on production.
CREATE TABLE IF NOT EXISTS rumo10_conquistas_reconciliacoes (
 hub_usuario_id BIGINT UNSIGNED NOT NULL, carreira_id BIGINT UNSIGNED NOT NULL,
 versao BIGINT UNSIGNED NOT NULL, dados_json JSON NOT NULL,
 verificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(hub_usuario_id,carreira_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_conquistas_fontes (
 hub_usuario_id BIGINT UNSIGNED NOT NULL, conquista_id CHAR(5) NOT NULL,
 origem VARCHAR(30) NOT NULL, referencia CHAR(64) NOT NULL,
 ocorrido_em DATETIME NULL, verificado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(hub_usuario_id,conquista_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_feedback (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, hub_usuario_id BIGINT UNSIGNED NOT NULL,
 chave CHAR(64) NOT NULL, request_hash CHAR(64) NOT NULL,
 nota TINYINT UNSIGNED NOT NULL, categoria VARCHAR(30) NULL,
 comentario VARCHAR(1000) NOT NULL DEFAULT '', versao VARCHAR(30) NOT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'novo', criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(hub_usuario_id,chave), INDEX(hub_usuario_id,criado_em), INDEX(estado,criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Non-destructive rollback: disable RUMO10_RECONCILIATION/RUMO10_FEEDBACK,
-- restore previous code; retain all three tables and earned G. No DROP/backfill here.
