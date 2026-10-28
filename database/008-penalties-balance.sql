-- 008-penalties-balance.sql
-- Fórmulas e Parâmetros Centralizados para Disputa de Pênaltis
-- Criação da fórmula match.penalties.parameters no catálogo do Rumo10

INSERT INTO rumo10_formulas (id, categoria, nome, descricao, expressao_padrao, retorno_tipo)
VALUES
('match.penalties.parameters', 'partida', 'Parâmetros de Conversão em Disputa de Pênaltis', 'Taxa base, influência de atributos do cobrador, goleiro, cansaço, pressão e opções táticas.', 'p = clamp(0.15, 0.95, base + fin_mod + str_mod + conf_mod - fatigue - pressure + option_mod)', 'json')
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), expressao_padrao=VALUES(expressao_padrao);

INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em)
VALUES ('match.penalties.parameters', 1, 'ativo', SHA2('match.penalties.parameters|v1', 256), 'Antigravity', 'Parâmetros calibrados para disputa de pênaltis regulamentar', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE estado='ativo', ativado_em=CURRENT_TIMESTAMP;


INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'base_conversion','float',0.75,'Taxa base estatística média de conversão em disputas oficiais',0.50,0.90 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'w_finishing','float',0.0035,'Peso por ponto de finalização acima/abaixo de 50',0.0010,0.0100 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'w_strength','float',0.0020,'Peso da força na potência da cobrança',0.0005,0.0050 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'w_gk_pos','float',0.0030,'Impacto do posicionamento/leitura do goleiro',0.0010,0.0080 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'w_confidence','float',0.0020,'Impacto da confiança do protagonista',0.0005,0.0050 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'pressure_penalty','float',0.05,'Penalização psicológica sob morte súbita ou cobrança decisiva',0.00,0.15 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'fatigue_penalty','float',0.0010,'Redução por ponto de energia abaixo de 100',0.0000,0.0030 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'risk_special_bonus','float',0.06,'Bônus de cobrança especial colocada ou cavadinha',0.01,0.15 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'risk_special_fail','float',0.12,'Risco adicional de erro em cobrança especial',0.05,0.25 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT INTO rumo10_formula_parametros (versao_id,chave,tipo,valor_float,descricao,limite_min,limite_max) SELECT id,'virtual_kicker_strength_weight','float',0.0025,'Modulação da força coletiva para cobradores virtuais',0.0005,0.0080 FROM rumo10_formula_versoes WHERE formula_id='match.penalties.parameters' AND versao=1 ON DUPLICATE KEY UPDATE chave=VALUES(chave);
INSERT IGNORE INTO rumo10_balanceamento_formulas (balanceamento_id,formula_id,versao_id) SELECT b.id,'match.penalties.parameters',v.id FROM rumo10_balanceamentos b CROSS JOIN rumo10_formula_versoes v WHERE v.formula_id='match.penalties.parameters' AND v.versao=1;
