-- 007-recalibracao-ofensiva.sql
-- Recalibração de Finalizações, Superioridade Individual e Participação Ofensiva
-- Cria nova versão de balanceamento (versão 2) sem sobrescrever o histórico da versão 1.

-- 1. Inserir ou atualizar catálogo mestre de fórmulas
INSERT INTO rumo10_formulas (id, categoria, nome, descricao, expressao_padrao, retorno_tipo)
VALUES
('match.shots.weights', 'partida', 'Pesos Técnicos da Finalização', 'Distribuição de pesos dos atributos para a qualidade de finalização.', 'quality = 0.45*fin + 0.25*pos + 0.15*for + 0.10*dri + 0.05*vis', 'json'),
('match.shots.superiority', 'partida', 'Superioridade Individual e Sensibilidade', 'Impacto da vantagem individual do jogador diante do adversário e modulação de estados.', 'bonus = clamp(-clamp, clamp, factor * (quality - opponent))', 'json'),
('match.opportunity.frequency', 'partida', 'Frequência de Oportunidades Posicionais', 'Multiplicadores de oportunidade de finalização em partida por posição tática.', 'weight = base_weight * pos_mult', 'json')
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), expressao_padrao=VALUES(expressao_padrao);

-- 2. Criar novo conjunto de balanceamento: bal-v062-recalibracao-ofensiva (versão 2)
-- Arquivar versão anterior se ativa
UPDATE rumo10_balanceamentos SET estado = 'arquivado' WHERE estado = 'ativo';

INSERT INTO rumo10_balanceamentos (codigo, nome, descricao, versao, estado, checksum, autor, justificativa, ativado_em)
VALUES (
    'bal-v062-recalibracao-ofensiva',
    'Recalibração de Finalizações e Participação Ofensiva v2',
    'Nova calibração da qualidade de chute (45/25/15/10/5), bônus de superioridade individual contra adversário, ajuste de conversão base e frequência de chances por posição.',
    2,
    'ativo',
    SHA2('bal-v062-recalibracao-ofensiva|v2|20261027', 256),
    'Antigravity',
    'Correção da baixa taxa de oportunidades e desconexão entre overall/atributos do atleta e rendimento em campo.',
    CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;

-- 3. Inserir versões das fórmulas para este balanceamento
-- 3.1 match.shots.weights (versao 2)
INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em)
VALUES ('match.shots.weights', 2, 'ativo', SHA2('match.shots.weights|v2', 256), 'Antigravity', 'Pesos 45/25/15/10/5 homologados', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;

-- 3.2 match.shots.superiority (versao 2)
INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em)
VALUES ('match.shots.superiority', 2, 'ativo', SHA2('match.shots.superiority|v2', 256), 'Antigravity', 'Superioridade individual líquida e sensibilidade logit calibrada', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;

-- 3.3 match.opportunity.frequency (versao 2)
INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em)
VALUES ('match.opportunity.frequency', 2, 'ativo', SHA2('match.opportunity.frequency|v2', 256), 'Antigravity', 'Multiplicadores de frequência por posição de ataque', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;

-- 3.4 match.shots.base (versao 2)
INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em)
VALUES ('match.shots.base', 2, 'ativo', SHA2('match.shots.base|v2', 256), 'Antigravity', 'Ajuste fino de conversões base (normal 0.22, clara 0.44, 1v1 0.48)', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;

-- 4. Parâmetros das fórmulas
-- Parâmetros match.shots.weights v2
INSERT INTO rumo10_formula_parametros (versao_id, chave, tipo, valor_float, limite_min, limite_max, descricao)
SELECT v.id, p.chave, p.tipo, p.valor_float, p.limite_min, p.limite_max, p.descricao
FROM rumo10_formula_versoes v
JOIN (
    SELECT 'w_finishing' AS chave, 'float' AS tipo, 0.4500 AS valor_float, 0.2000 AS limite_min, 0.7000 AS limite_max, 'Peso do atributo finalização' AS descricao UNION ALL
    SELECT 'w_positioning', 'float', 0.2500, 0.1000, 0.4000, 'Peso do posicionamento ofensivo' UNION ALL
    SELECT 'w_strength', 'float', 0.1500, 0.0500, 0.3000, 'Peso da força física' UNION ALL
    SELECT 'w_dribbling', 'float', 0.1000, 0.0500, 0.2500, 'Peso da habilidade de drible' UNION ALL
    SELECT 'w_vision', 'float', 0.0500, 0.0000, 0.1500, 'Peso da visão de jogo'
) p
WHERE v.formula_id = 'match.shots.weights' AND v.versao = 2
ON DUPLICATE KEY UPDATE valor_float=VALUES(valor_float);

-- Parâmetros match.shots.superiority v2
INSERT INTO rumo10_formula_parametros (versao_id, chave, tipo, valor_float, limite_min, limite_max, descricao)
SELECT v.id, p.chave, p.tipo, p.valor_float, p.limite_min, p.limite_max, p.descricao
FROM rumo10_formula_versoes v
JOIN (
    SELECT 'factor' AS chave, 'float' AS tipo, 0.0180 AS valor_float, 0.0050 AS limite_min, 0.0400 AS limite_max, 'Fator de sensibilidade da diferença individual' AS descricao UNION ALL
    SELECT 'clamp', 'float', 0.4000, 0.1000, 0.7500, 'Teto absoluto de bônus/penalidade de superioridade' UNION ALL
    SELECT 'quality_factor', 'float', 0.0280, 0.0100, 0.0500, 'Sensibilidade logit da qualidade do chute' UNION ALL
    SELECT 'confidence_factor', 'float', 0.0025, 0.0010, 0.0100, 'Sensibilidade da confiança sem punição excessiva'
) p
WHERE v.formula_id = 'match.shots.superiority' AND v.versao = 2
ON DUPLICATE KEY UPDATE valor_float=VALUES(valor_float);

-- Parâmetros match.opportunity.frequency v2
INSERT INTO rumo10_formula_parametros (versao_id, chave, tipo, valor_float, limite_min, limite_max, descricao)
SELECT v.id, p.chave, p.tipo, p.valor_float, p.limite_min, p.limite_max, p.descricao
FROM rumo10_formula_versoes v
JOIN (
    SELECT 'mult_ca' AS chave, 'float' AS tipo, 3.5000 AS valor_float, 1.0000 AS limite_min, 6.0000 AS limite_max, 'Multiplicador de chances para Centroavante' AS descricao UNION ALL
    SELECT 'mult_pt', 'float', 2.8000, 1.0000, 5.0000, 'Multiplicador de chances para Ponta' UNION ALL
    SELECT 'mult_me', 'float', 1.8000, 1.0000, 3.5000, 'Multiplicador de chances para Meia' UNION ALL
    SELECT 'mult_def', 'float', 1.0000, 0.5000, 2.0000, 'Multiplicador de chances para Volante/Lateral/Zagueiro'
) p
WHERE v.formula_id = 'match.opportunity.frequency' AND v.versao = 2
ON DUPLICATE KEY UPDATE valor_float=VALUES(valor_float);

-- Parâmetros match.shots.base v2
INSERT INTO rumo10_formula_parametros (versao_id, chave, tipo, valor_float, limite_min, limite_max, descricao)
SELECT v.id, p.chave, p.tipo, p.valor_float, p.limite_min, p.limite_max, p.descricao
FROM rumo10_formula_versoes v
JOIN (
    SELECT 'shot_normal' AS chave, 'float' AS tipo, 0.2200 AS valor_float, 0.1000 AS limite_min, 0.3500 AS limite_max, 'Conversão base de finalização padrão' AS descricao UNION ALL
    SELECT 'shot_clear', 'float', 0.4400, 0.2500, 0.6500, 'Conversão base de finalização em chance clara' UNION ALL
    SELECT 'shot_one_on_one', 'float', 0.4800, 0.3000, 0.7000, 'Conversão base de finalização mano a mano' UNION ALL
    SELECT 'shot_difficult', 'float', 0.0800, 0.0300, 0.2000, 'Conversão base de chute difícil sob pressão' UNION ALL
    SELECT 'shot_long', 'float', 0.0400, 0.0100, 0.1000, 'Conversão base de chute de longa distância'
) p
WHERE v.formula_id = 'match.shots.base' AND v.versao = 2
ON DUPLICATE KEY UPDATE valor_float=VALUES(valor_float);

-- 5. Vincular todas as versões ativas ao conjunto bal-v062-recalibracao-ofensiva
INSERT INTO rumo10_balanceamento_formulas (balanceamento_id, formula_id, versao_id)
SELECT b.id, v.formula_id, v.id
FROM rumo10_balanceamentos b
JOIN rumo10_formula_versoes v ON v.estado = 'ativo'
WHERE b.codigo = 'bal-v062-recalibracao-ofensiva'
ON DUPLICATE KEY UPDATE versao_id=VALUES(versao_id);
