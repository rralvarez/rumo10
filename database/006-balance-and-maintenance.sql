-- 006-balance-and-maintenance.sql
-- Sistema de Manutenção Global e Centralização de Fórmulas / Balanceamento

-- 1. Configurações Globais do Sistema (Manutenção)
CREATE TABLE IF NOT EXISTS rumo10_configuracoes_sistema (
    id INT UNSIGNED PRIMARY KEY DEFAULT 1,
    manutencao_ativa TINYINT NOT NULL DEFAULT 0,
    manutencao_titulo VARCHAR(120) NOT NULL DEFAULT 'Estamos preparando a próxima temporada!',
    manutencao_mensagem TEXT NOT NULL,
    manutencao_previsao VARCHAR(80) NULL DEFAULT 'Em breve voltaremos aos gramados.',
    manutencao_inicio TIMESTAMP NULL,
    manutencao_atualizada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    manutencao_atualizada_por VARCHAR(100) NULL DEFAULT 'sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garante o registro padrão único (singleton de configuração)
INSERT IGNORE INTO rumo10_configuracoes_sistema (
    id, manutencao_ativa, manutencao_titulo, manutencao_mensagem, manutencao_previsao, manutencao_atualizada_por
) VALUES (
    1,
    0,
    'Estamos preparando a próxima temporada!',
    'Nosso time está trabalhando nos bastidores para melhorar sua experiência. Em breve você poderá voltar aos gramados e continuar sua carreira.',
    'Previsão: retorno em breve.',
    'instalador'
);

-- 2. Conjuntos de Balanceamento
CREATE TABLE IF NOT EXISTS rumo10_balanceamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(64) UNIQUE NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    estado ENUM('rascunho','homologado','ativo','arquivado') NOT NULL DEFAULT 'rascunho',
    checksum CHAR(64) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    justificativa TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativado_em TIMESTAMP NULL,
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Catálogo Mestre de Fórmulas e Regras
CREATE TABLE IF NOT EXISTS rumo10_formulas (
    id VARCHAR(80) PRIMARY KEY,
    categoria VARCHAR(40) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    expressao_padrao TEXT NOT NULL,
    retorno_tipo ENUM('float','int','bool','json') NOT NULL DEFAULT 'float',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Versões de Fórmulas
CREATE TABLE IF NOT EXISTS rumo10_formula_versoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formula_id VARCHAR(80) NOT NULL,
    versao INT UNSIGNED NOT NULL,
    expressao_compilada JSON NULL,
    estado ENUM('rascunho','homologado','ativo','arquivado') NOT NULL DEFAULT 'rascunho',
    checksum CHAR(64) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    motivo TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativado_em TIMESTAMP NULL,
    UNIQUE KEY uq_formula_versao (formula_id, versao),
    INDEX idx_formula_estado (formula_id, estado),
    FOREIGN KEY (formula_id) REFERENCES rumo10_formulas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Parâmetros Tipados por Versão
CREATE TABLE IF NOT EXISTS rumo10_formula_parametros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    versao_id BIGINT UNSIGNED NOT NULL,
    chave VARCHAR(80) NOT NULL,
    tipo ENUM('float','int','string','json') NOT NULL,
    valor_float DECIMAL(12,4) NULL,
    valor_int BIGINT NULL,
    valor_texto VARCHAR(255) NULL,
    valor_json JSON NULL,
    limite_min DECIMAL(12,4) NULL,
    limite_max DECIMAL(12,4) NULL,
    descricao VARCHAR(255) NULL,
    UNIQUE KEY uq_versao_chave (versao_id, chave),
    FOREIGN KEY (versao_id) REFERENCES rumo10_formula_versoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Associação Balanceamento <-> Fórmulas
CREATE TABLE IF NOT EXISTS rumo10_balanceamento_formulas (
    balanceamento_id BIGINT UNSIGNED NOT NULL,
    formula_id VARCHAR(80) NOT NULL,
    versao_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (balanceamento_id, formula_id),
    FOREIGN KEY (balanceamento_id) REFERENCES rumo10_balanceamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (formula_id) REFERENCES rumo10_formulas(id) ON DELETE RESTRICT,
    FOREIGN KEY (versao_id) REFERENCES rumo10_formula_versoes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Trilha de Auditoria e Governança de Alterações
CREATE TABLE IF NOT EXISTS rumo10_formula_alteracoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formula_id VARCHAR(80) NULL,
    balanceamento_id BIGINT UNSIGNED NULL,
    versao_anterior INT UNSIGNED NULL,
    versao_nova INT UNSIGNED NOT NULL,
    usuario_responsavel VARCHAR(100) NOT NULL,
    acao ENUM('criar','editar_rascunho','homologar','ativar','rollback','alternar_manutencao') NOT NULL,
    diff_json JSON NOT NULL,
    justificativa TEXT NOT NULL,
    ip_origem VARCHAR(45) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
