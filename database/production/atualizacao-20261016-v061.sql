-- Rumo10 v0.6 + v0.6.1. Selecionar rumoacame144a99e_rumohub no phpMyAdmin.
-- Somente CREATE TABLE IF NOT EXISTS; sem backfill, UPDATE, DELETE ou tabelas rh_.
-- Executar somente depois do backup de producao validado.

-- Additive. Apply only after backup; no RH writes, no data backfill.
CREATE TABLE IF NOT EXISTS rumo10_conquistas_carreiras (
 hub_usuario_id BIGINT UNSIGNED NOT NULL, carreira_id BIGINT UNSIGNED NOT NULL,
 valida TINYINT UNSIGNED NOT NULL DEFAULT 0, estado_json JSON NOT NULL,
 PRIMARY KEY(hub_usuario_id,carreira_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_conquistas_eventos (
 hub_usuario_id BIGINT UNSIGNED NOT NULL, carreira_id BIGINT UNSIGNED NOT NULL,
 chave CHAR(64) NOT NULL, fonte_json JSON NOT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(hub_usuario_id,carreira_id,chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS rumo10_conquistas (
 hub_usuario_id BIGINT UNSIGNED NOT NULL, conquista_id CHAR(5) NOT NULL,
 carreira_id BIGINT UNSIGNED NOT NULL, pontos_g SMALLINT UNSIGNED NOT NULL,
 evento_chave CHAR(64) NOT NULL, xp_bonus INT UNSIGNED NOT NULL DEFAULT 0,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, notificada TINYINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(hub_usuario_id,conquista_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Deliberately no ON DELETE CASCADE: account reputation survives career deletion.
-- Rollback: set RUMO10_ACHIEVEMENTS=0 and restore previous code. Keep these tables.
-- Physical removal is a separate destructive operation, NOT part of this script.

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
