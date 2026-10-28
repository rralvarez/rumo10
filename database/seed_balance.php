<?php
declare(strict_types=1);

/**
 * Seed das fórmulas e parâmetros vigentes no código homologado.
 */
function seed_balance_data(PDO $pdo): void {
    // 1. Cadastrar fórmulas no catálogo
    $formulas = [
        [
            'id' => 'dev.v3.parameters',
            'categoria' => 'evolucao',
            'nome' => 'Evolução Individual v3 e Normalização Marginal',
            'descricao' => 'Parâmetros de XP anual, rendimento logarítmico, custos de nível e taxas por faixa etária.',
            'expressao_padrao' => 'budget = scale * log1p(raw_base / scale) * rate * learning',
            'retorno_tipo' => 'int',
            'params' => [
                ['chave' => 'annual_base', 'tipo' => 'int', 'valor_int' => 410, 'min' => 200, 'max' => 800, 'desc' => 'XP anual base para atletas com minutos suficientes'],
                ['chave' => 'annual_merit', 'tipo' => 'int', 'valor_int' => 175, 'min' => 50, 'max' => 500, 'desc' => 'XP anual variável condicionado à nota e contribuição'],
                ['chave' => 'normalization_scale', 'tipo' => 'float', 'valor_float' => 250.0, 'min' => 50, 'max' => 1000, 'desc' => 'Escala logarítmica de conversão marginal decrescente'],
                ['chave' => 'cost_base', 'tipo' => 'int', 'valor_int' => 20, 'min' => 10, 'max' => 50, 'desc' => 'Custo base de XP para evolução de 1 ponto de atributo'],
                ['chave' => 'cost_divisor', 'tipo' => 'int', 'valor_int' => 5, 'min' => 1, 'max' => 20, 'desc' => 'Divisor de atributo para incremento do custo de nível'],
                ['chave' => 'ceiling_open_age', 'tipo' => 'int', 'valor_int' => 27, 'min' => 21, 'max' => 32, 'desc' => 'Idade em que o teto potencial é completamente liberado'],
                ['chave' => 'aging_acceleration', 'tipo' => 'float', 'valor_float' => 0.075, 'min' => 0.01, 'max' => 0.25, 'desc' => 'Aceleração quadrática do declínio físico por ano'],
            ]
        ],
        [
            'id' => 'match.rating',
            'categoria' => 'partida',
            'nome' => 'Nota de Desempenho na Partida',
            'descricao' => 'Cálculo da nota individual do protagonista no apito final (0 a 10).',
            'expressao_padrao' => 'clamp(0, 10, base + goals*w_goal + assists*w_assist + tackles*w_tackle + completed*w_pass - errors*w_err - cards*w_card)',
            'retorno_tipo' => 'float',
            'params' => [
                ['chave' => 'base', 'tipo' => 'float', 'valor_float' => 6.0, 'min' => 4.0, 'max' => 8.0, 'desc' => 'Nota base ao entrar em campo'],
                ['chave' => 'w_goal', 'tipo' => 'float', 'valor_float' => 0.70, 'min' => 0.2, 'max' => 1.5, 'desc' => 'Bônus por gol marcado'],
                ['chave' => 'w_assist', 'tipo' => 'float', 'valor_float' => 0.50, 'min' => 0.1, 'max' => 1.0, 'desc' => 'Bônus por assistência concedida'],
                ['chave' => 'w_tackle_def', 'tipo' => 'float', 'valor_float' => 0.35, 'min' => 0.1, 'max' => 0.8, 'desc' => 'Bônus por desarme para defensores/volantes'],
                ['chave' => 'w_tackle_off', 'tipo' => 'float', 'valor_float' => 0.15, 'min' => 0.05, 'max' => 0.5, 'desc' => 'Bônus por desarme para atacantes e meias'],
                ['chave' => 'w_pass_ok', 'tipo' => 'float', 'valor_float' => 0.10, 'min' => 0.02, 'max' => 0.3, 'desc' => 'Bônus por passe certo'],
                ['chave' => 'w_pass_err', 'tipo' => 'float', 'valor_float' => 0.15, 'min' => 0.05, 'max' => 0.5, 'desc' => 'Penalidade por passe errado'],
                ['chave' => 'w_card', 'tipo' => 'float', 'valor_float' => 0.60, 'min' => 0.2, 'max' => 1.5, 'desc' => 'Penalidade por cartão recebido'],
            ]
        ],
        [
            'id' => 'match.venue.expectations',
            'categoria' => 'partida',
            'nome' => 'Expectativas de Gols Mandante e Visitante',
            'descricao' => 'Médias lambda de Poisson condicionadas à diferença de ratings e mando de campo.',
            'expressao_padrao' => 'lambda_home = 1.35 * exp(diff/35); lambda_away = 1.10 * exp(-diff/35)',
            'retorno_tipo' => 'json',
            'params' => [
                ['chave' => 'home_factor', 'tipo' => 'float', 'valor_float' => 1.35, 'min' => 0.8, 'max' => 2.0, 'desc' => 'Constante base de expectativa de gols do mandante'],
                ['chave' => 'away_factor', 'tipo' => 'float', 'valor_float' => 1.10, 'min' => 0.5, 'max' => 1.8, 'desc' => 'Constante base de expectativa de gols do visitante'],
                ['chave' => 'exp_divisor', 'tipo' => 'float', 'valor_float' => 35.0, 'min' => 15.0, 'max' => 70.0, 'desc' => 'Divisor exponencial da diferença de forças'],
                ['chave' => 'clamp_min', 'tipo' => 'float', 'valor_float' => 0.15, 'min' => 0.05, 'max' => 0.5, 'desc' => 'Piso de gols esperados por time'],
                ['chave' => 'clamp_max', 'tipo' => 'float', 'valor_float' => 4.00, 'min' => 2.5, 'max' => 8.0, 'desc' => 'Teto de gols esperados por time'],
            ]
        ],
        [
            'id' => 'match.balance.quotas',
            'categoria' => 'partida',
            'nome' => 'Participação Ofensiva e Contribuição do Protagonista',
            'descricao' => 'Quota de ataque do jogador por posição e impacto técnico no coletivo.',
            'expressao_padrao' => 'share = pos_share; contrib = 1 + clamp(-0.12, 0.12, (tech - opp)/250) * min/90',
            'retorno_tipo' => 'json',
            'params' => [
                ['chave' => 'share_ca', 'tipo' => 'float', 'valor_float' => 0.60, 'min' => 0.3, 'max' => 0.85, 'desc' => 'Fatia de ataque do centroavante'],
                ['chave' => 'share_pt', 'tipo' => 'float', 'valor_float' => 0.55, 'min' => 0.3, 'max' => 0.80, 'desc' => 'Fatia de ataque do ponta'],
                ['chave' => 'share_me', 'tipo' => 'float', 'valor_float' => 0.48, 'min' => 0.25, 'max' => 0.70, 'desc' => 'Fatia de ataque do meia'],
                ['chave' => 'share_lt', 'tipo' => 'float', 'valor_float' => 0.35, 'min' => 0.15, 'max' => 0.55, 'desc' => 'Fatia de ataque do lateral'],
                ['chave' => 'share_def', 'tipo' => 'float', 'valor_float' => 0.30, 'min' => 0.10, 'max' => 0.50, 'desc' => 'Fatia de ataque do volante e zagueiro'],
                ['chave' => 'team_boost_max', 'tipo' => 'float', 'valor_float' => 0.12, 'min' => 0.02, 'max' => 0.30, 'desc' => 'Impacto máximo da superioridade do protagonista na equipe'],
            ]
        ],
        [
            'id' => 'match.shots.base',
            'categoria' => 'partida',
            'nome' => 'Probabilidades Base de Finalização',
            'descricao' => 'Probabilidades a priori de gol por tipologia de lance.',
            'expressao_padrao' => 'logit = ln(base / (1 - base)) + components',
            'retorno_tipo' => 'json',
            'params' => [
                ['chave' => 'shot_long', 'tipo' => 'float', 'valor_float' => 0.035, 'min' => 0.01, 'max' => 0.10, 'desc' => 'Conversão base de chute de longa distância'],
                ['chave' => 'shot_difficult', 'tipo' => 'float', 'valor_float' => 0.075, 'min' => 0.02, 'max' => 0.20, 'desc' => 'Conversão base de chute difícil sob pressão'],
                ['chave' => 'shot_normal', 'tipo' => 'float', 'valor_float' => 0.180, 'min' => 0.08, 'max' => 0.35, 'desc' => 'Conversão base de finalização padrão'],
                ['chave' => 'shot_clear', 'tipo' => 'float', 'valor_float' => 0.380, 'min' => 0.20, 'max' => 0.60, 'desc' => 'Conversão base de chance clara na área'],
                ['chave' => 'shot_one_on_one', 'tipo' => 'float', 'valor_float' => 0.430, 'min' => 0.25, 'max' => 0.70, 'desc' => 'Conversão base no mano a mano com o goleiro'],
            ]
        ],
        [
            'id' => 'market.interest.scenarios',
            'categoria' => 'mercado',
            'nome' => 'Distribuição de Oportunidades no Mercado',
            'descricao' => 'Pesos dos cenários de propostas em função do mérito na temporada.',
            'expressao_padrao' => 'weights = f(merit)',
            'retorno_tipo' => 'json',
            'params' => [
                ['chave' => 'weight_no_offer_base', 'tipo' => 'int', 'valor_int' => 65, 'min' => 20, 'max' => 100, 'desc' => 'Peso base de nenhuma proposta (sem mérito)'],
                ['chave' => 'weight_no_offer_slope', 'tipo' => 'int', 'valor_int' => 35, 'min' => 10, 'max' => 60, 'desc' => 'Redução de nenhuma proposta por unidade de mérito'],
                ['chave' => 'weight_one_base', 'tipo' => 'int', 'valor_int' => 30, 'min' => 10, 'max' => 60, 'desc' => 'Peso base de uma proposta'],
                ['chave' => 'weight_two_base', 'tipo' => 'int', 'valor_int' => 10, 'min' => 5, 'max' => 40, 'desc' => 'Peso base de duas propostas'],
                ['chave' => 'weight_national_base', 'tipo' => 'int', 'valor_int' => 5, 'min' => 1, 'max' => 30, 'desc' => 'Peso base de mercado nacional'],
            ]
        ],
    ];

    // 2. Criar ou obter o conjunto de balanceamento ativo
    $code = 'bal-v061-v3-oficial';
    $stmt = $pdo->prepare("SELECT id FROM rumo10_balanceamentos WHERE codigo = ?");
    $stmt->execute([$code]);
    $balanceId = $stmt->fetchColumn();

    if (!$balanceId) {
        $checksum = hash('sha256', $code . '|' . time());
        $stmt = $pdo->prepare("INSERT INTO rumo10_balanceamentos (codigo, nome, descricao, versao, estado, checksum, autor, justificativa, ativado_em) VALUES (?, ?, ?, 1, 'ativo', ?, 'sistema', 'Conjunto inicial homologado das Entregas 60 e 61', CURRENT_TIMESTAMP)");
        $stmt->execute([$code, 'Balanceamento Oficial 2026 (Evolução v3 e Motor Integrado)', 'Configuração de referência aprovada com idade 16 anos, evolução individual v3, cache de ratings e notas de partida.', $checksum]);
        $balanceId = (int)$pdo->lastInsertId();
    }

    foreach ($formulas as $f) {
        // Inserir fórmula mestre
        $stmt = $pdo->prepare("INSERT INTO rumo10_formulas (id, categoria, nome, descricao, expressao_padrao, retorno_tipo) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao)");
        $stmt->execute([$f['id'], $f['categoria'], $f['nome'], $f['descricao'], $f['expressao_padrao'], $f['retorno_tipo']]);

        // Inserir versão 1 ativa
        $vChecksum = hash('sha256', $f['id'] . '|v1');
        $stmt = $pdo->prepare("INSERT INTO rumo10_formula_versoes (formula_id, versao, estado, checksum, autor, motivo, ativado_em) VALUES (?, 1, 'ativo', ?, 'sistema', 'Versão inicial homologada', CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE ativado_em=CURRENT_TIMESTAMP");
        $stmt->execute([$f['id'], $vChecksum]);

        $stmt = $pdo->prepare("SELECT id FROM rumo10_formula_versoes WHERE formula_id = ? AND versao = 1");
        $stmt->execute([$f['id']]);
        $versaoId = (int)$stmt->fetchColumn();

        // Inserir parâmetros tipados
        foreach ($f['params'] as $p) {
            $stmt = $pdo->prepare("INSERT INTO rumo10_formula_parametros (versao_id, chave, tipo, valor_float, valor_int, limite_min, limite_max, descricao) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE valor_float=VALUES(valor_float), valor_int=VALUES(valor_int), limite_min=VALUES(limite_min), limite_max=VALUES(limite_max)");
            $stmt->execute([
                $versaoId,
                $p['chave'],
                $p['tipo'],
                $p['valor_float'] ?? null,
                $p['valor_int'] ?? null,
                $p['min'] ?? null,
                $p['max'] ?? null,
                $p['desc'] ?? null
            ]);
        }

        // Vincular ao conjunto de balanceamento
        $stmt = $pdo->prepare("INSERT IGNORE INTO rumo10_balanceamento_formulas (balanceamento_id, formula_id, versao_id) VALUES (?, ?, ?)");
        $stmt->execute([$balanceId, $f['id'], $versaoId]);
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    seed_balance_data(Database::connect());
    echo "Seed de balanceamento concluído com sucesso!\n";
}

