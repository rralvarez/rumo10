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
