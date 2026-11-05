-- =====================================================================
-- RUMO10 | MIGRATION ADITIVA VERSIONADA: 009-f01-canonical-telemetry.sql
-- Feature: R10-F01 (Identidade Canônica e Rastreabilidade de Partidas e Eventos)
-- Baseline de Origem: Commit f23c2c3 (V8 Instalada)
-- Natureza: Estritamente aditiva. Não altera nem remove tabelas existentes.
-- Conformidade: Portão P0 Homologado (OS-P0-006 / Erratas V2, V3 e V4)
-- =====================================================================

-- 1. Tabela de Respostas Idempotentes Versionadas (Contrato B)
CREATE TABLE IF NOT EXISTS rumo10_f01_respostas (
    carreira_id BIGINT UNSIGNED NOT NULL,
    chave CHAR(64) NOT NULL,
    command_uid CHAR(36) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    versao_anterior INT NOT NULL,
    versao_resultante INT NOT NULL,
    contract_version INT UNSIGNED NOT NULL DEFAULT 1,
    resposta_json JSON NOT NULL,
    received_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    persisted_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (carreira_id, chave),
    UNIQUE KEY uk_f01_respostas_command (command_uid),
    CONSTRAINT fk_f01_respostas_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela Canônica de Partidas da F01 (Contratos D e E)
CREATE TABLE IF NOT EXISTS rumo10_f01_partidas (
    match_uid CHAR(36) NOT NULL,
    carreira_id BIGINT UNSIGNED NOT NULL,
    temporada_numero INT NOT NULL,
    competicao_codigo VARCHAR(64) NULL DEFAULT NULL,
    rodada_fase VARCHAR(32) NULL DEFAULT NULL,
    mandante VARCHAR(120) NOT NULL,
    visitante VARCHAR(120) NOT NULL,
    gols_mandante INT UNSIGNED NULL DEFAULT NULL,
    gols_visitante INT UNSIGNED NULL DEFAULT NULL,
    modo_resolucao ENUM('normal', 'rapido', 'expresso') NOT NULL,
    batch_uid CHAR(36) NULL,
    sequencia_no_batch INT UNSIGNED NULL,
    legacy_match_id BIGINT UNSIGNED NULL,
    source_kind VARCHAR(32) NOT NULL DEFAULT 'canonical_v8_f01',
    identity_confidence VARCHAR(32) NOT NULL DEFAULT 'exact',
    contract_version INT UNSIGNED NOT NULL DEFAULT 1,
    engine_commit VARCHAR(40) NULL DEFAULT NULL,
    received_at_utc TIMESTAMP(6) NULL,
    persisted_at_utc TIMESTAMP(6) NULL,
    projected_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (match_uid),
    INDEX ix_f01_partidas_carreira_temp (carreira_id, temporada_numero),
    INDEX ix_f01_partidas_batch (batch_uid, sequencia_no_batch),
    INDEX ix_f01_partidas_legacy (legacy_match_id),
    CONSTRAINT fk_f01_partidas_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela Canônica de Aparições do Protagonista (Contrato D)
CREATE TABLE IF NOT EXISTS rumo10_f01_aparicoes (
    appearance_uid CHAR(36) NOT NULL,
    match_uid CHAR(36) NOT NULL,
    carreira_id BIGINT UNSIGNED NOT NULL,
    condicao_escalacao ENUM('titular', 'reserva', 'nao_relacionado') NULL DEFAULT NULL,
    minuto_entrada INT UNSIGNED NULL DEFAULT NULL,
    minuto_saida INT UNSIGNED NULL DEFAULT NULL,
    minutos_jogados INT UNSIGNED NULL DEFAULT NULL,
    estado_participacao VARCHAR(32) NOT NULL,
    nota_atuacao DECIMAL(4,1) NULL,
    persisted_at_utc TIMESTAMP(6) NULL,
    projected_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (appearance_uid),
    UNIQUE KEY uk_f01_partida_aparicao (match_uid, appearance_uid),
    UNIQUE KEY uk_f01_partida_carreira (match_uid, carreira_id),
    CONSTRAINT fk_f01_aparicoes_partida FOREIGN KEY (match_uid) 
        REFERENCES rumo10_f01_partidas(match_uid) ON DELETE CASCADE,
    CONSTRAINT fk_f01_aparicoes_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabela Canônica de Eventos Auditáveis da Partida (Contratos D e E)
CREATE TABLE IF NOT EXISTS rumo10_f01_eventos (
    event_uid CHAR(36) NOT NULL,
    match_uid CHAR(36) NULL,
    appearance_uid CHAR(36) NULL,
    carreira_id BIGINT UNSIGNED NOT NULL,
    command_uid CHAR(36) NULL,
    tipo_evento VARCHAR(64) NOT NULL,
    sequencia_partida INT UNSIGNED NULL,
    minuto_jogo INT UNSIGNED NULL,
    dados_evento_json JSON NOT NULL,
    persisted_at_utc TIMESTAMP(6) NULL,
    projected_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (event_uid),
    UNIQUE KEY uk_f01_eventos_partida_seq (match_uid, sequencia_partida),
    INDEX ix_f01_eventos_tipo (tipo_evento),
    INDEX ix_f01_eventos_carreira (carreira_id),
    CONSTRAINT fk_f01_eventos_partida FOREIGN KEY (match_uid) 
        REFERENCES rumo10_f01_partidas(match_uid) ON DELETE CASCADE,
    CONSTRAINT fk_f01_eventos_aparicao_composta FOREIGN KEY (match_uid, appearance_uid) 
        REFERENCES rumo10_f01_aparicoes(match_uid, appearance_uid) ON DELETE CASCADE,
    CONSTRAINT fk_f01_eventos_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Buffer de Outbox Transacional Local da F01 (Contratos D e F)
CREATE TABLE IF NOT EXISTS rumo10_f01_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    carreira_id BIGINT UNSIGNED NOT NULL,
    command_uid CHAR(36) NOT NULL,
    chave CHAR(64) NOT NULL,
    engine_commit VARCHAR(40) NULL DEFAULT NULL,
    total_eventos INT UNSIGNED NOT NULL DEFAULT 1,
    payload_json JSON NOT NULL,
    estado ENUM('pendente', 'processado', 'falha') NOT NULL DEFAULT 'pendente',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    erro TEXT NULL,
    received_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    persisted_at_utc TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    projected_at_utc TIMESTAMP(6) NULL,
    PRIMARY KEY (id),
    INDEX ix_f01_outbox_estado (estado, id),
    UNIQUE KEY uk_f01_outbox_carreira_chave (carreira_id, chave),
    CONSTRAINT fk_f01_outbox_carreira FOREIGN KEY (carreira_id) 
        REFERENCES rumo10_carreiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
