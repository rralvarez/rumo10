<?php
declare(strict_types=1);

/**
 * Serviço centralizado de fórmulas e balanceamento do Rumo10.
 * Fornece acesso tipado e seguro com cache em memória e fallback nativo garantido.
 * NUNCA executa código arbitrário via eval().
 */
final class BalanceService {
    private static ?array $activeCache = null;
    private static ?array $activeMetadata = null;
    private static bool $fallbackActive = false;

    // Fallbacks nativos estritos (idênticos às fórmulas e constantes homologadas)
    private const NATIVE_FALLBACKS = [
        'dev.v3.parameters' => [
            'annual_base' => 410,
            'annual_merit' => 175,
            'normalization_scale' => 250.0,
            'cost_base' => 20,
            'cost_divisor' => 5,
            'ceiling_open_age' => 27,
            'aging_acceleration' => 0.075,
        ],
        'match.rating' => [
            'base' => 6.0,
            'w_goal' => 0.70,
            'w_assist' => 0.50,
            'w_tackle_def' => 0.35,
            'w_tackle_off' => 0.15,
            'w_pass_ok' => 0.10,
            'w_pass_err' => 0.15,
            'w_card' => 0.60,
        ],
        'match.venue.expectations' => [
            'home_factor' => 1.35,
            'away_factor' => 1.10,
            'exp_divisor' => 35.0,
            'clamp_min' => 0.15,
            'clamp_max' => 4.00,
        ],
        'match.balance.quotas' => [
            'share_ca' => 0.60,
            'share_pt' => 0.55,
            'share_me' => 0.48,
            'share_lt' => 0.35,
            'share_def' => 0.30,
            'team_boost_max' => 0.12,
        ],
        'match.shots.weights' => [
            'w_finishing' => 0.45,
            'w_positioning' => 0.25,
            'w_strength' => 0.15,
            'w_dribbling' => 0.10,
            'w_vision' => 0.05,
        ],
        'match.shots.superiority' => [
            'factor' => 0.012,
            'clamp' => 0.30,
            'quality_factor' => 0.022,
            'confidence_factor' => 0.0020,
        ],
        'match.opportunity.frequency' => [
            'mult_ca' => 1.8,
            'mult_pt' => 1.5,
            'mult_me' => 1.1,
            'mult_def' => 0.5,
        ],
        'match.shots.base' => [
            'shot_long' => 0.030,
            'shot_difficult' => 0.070,
            'shot_normal' => 0.170,
            'shot_clear' => 0.380,
            'shot_one_on_one' => 0.420,
        ],
        'market.interest.scenarios' => [
            'weight_no_offer_base' => 65,
            'weight_no_offer_slope' => 35,
            'weight_one_base' => 30,
            'weight_one_slope' => 10,
            'weight_two_base' => 10,
            'weight_two_slope' => 18,
            'weight_national_base' => 5,
            'weight_national_slope' => 25,
            'weight_continental_base' => 2,
            'weight_continental_slope' => 15,
            'weight_international_base' => 1,
            'weight_international_slope' => 8,
            'weight_star_base' => 1,
            'weight_star_slope' => 5,
        ],
        'market.opportunity.forecast' => [
            'related_base' => 0.65,
            'related_scale' => 0.33,
            'related_divisor' => 12.0,
            'starter_offset' => 2.0,
            'starter_divisor' => 9.0,
            'sub_rate' => 0.80,
        ],
    ];

    public static function resetCache(): void {
        self::$activeCache = null;
        self::$activeMetadata = null;
        self::$fallbackActive = false;
    }

    public static function isFallbackActive(): bool {
        return self::$fallbackActive;
    }

    public static function isDevelopmentV3Active(): bool {
        return true; // Padrão da versão consolidada
    }

    public static function activeBalance(): ?array {
        self::ensureLoaded();
        return self::$activeMetadata;
    }

    public static function switchActiveBalance(string $code, ?PDO $db = null): bool {
        $db ??= Database::connect();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT id FROM rumo10_balanceamentos WHERE codigo = ?");
            $stmt->execute([$code]);
            $targetId = $stmt->fetchColumn();
            if (!$targetId) {
                throw new InvalidArgumentException("Conjunto de balanceamento '{$code}' não encontrado.");
            }

            $db->exec("UPDATE rumo10_balanceamentos SET estado='arquivado' WHERE estado='ativo'");
            $upStmt = $db->prepare("UPDATE rumo10_balanceamentos SET estado='ativo', ativado_em=CURRENT_TIMESTAMP WHERE id=?");
            $upStmt->execute([(int)$targetId]);

            $db->commit();
            self::resetCache();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function rollbackActiveBalance(?PDO $db = null): array {
        $db ??= Database::connect();
        $db->beginTransaction();
        try {
            $stmt = $db->query("SELECT id, codigo, nome, versao FROM rumo10_balanceamentos WHERE estado='arquivado' ORDER BY ativado_em DESC, id DESC LIMIT 1");
            $prev = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new RuntimeException("Nenhum balanceamento arquivado disponível para rollback.");
            }

            $db->exec("UPDATE rumo10_balanceamentos SET estado='arquivado' WHERE estado='ativo'");
            $upStmt = $db->prepare("UPDATE rumo10_balanceamentos SET estado='ativo', ativado_em=CURRENT_TIMESTAMP WHERE id=?");
            $upStmt->execute([(int)$prev['id']]);

            $db->commit();
            self::resetCache();
            return $prev;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function params(string $formulaId): array {
        self::ensureLoaded();
        return self::$activeCache[$formulaId] ?? (self::NATIVE_FALLBACKS[$formulaId] ?? []);
    }

    public static function param(string $formulaId, string $key, float|int|null $default = null): float|int {
        self::ensureLoaded();
        if (isset(self::$activeCache[$formulaId][$key])) {
            return self::$activeCache[$formulaId][$key];
        }
        if (isset(self::NATIVE_FALLBACKS[$formulaId][$key])) {
            return self::NATIVE_FALLBACKS[$formulaId][$key];
        }
        if ($default !== null) {
            return $default;
        }
        throw new InvalidArgumentException("Parâmetro de balanceamento não encontrado: {$formulaId}.{$key}");
    }

    /**
     * Carrega as configurações ativas do MySQL sob lock de memória de processo.
     */
    private static function ensureLoaded(): void {
        if (self::$activeCache !== null) {
            return;
        }

        self::$activeCache = [];
        self::$activeMetadata = null;

        try {
            if (!class_exists('Database')) {
                self::useFallback();
                return;
            }

            $db = Database::connect();
            // Verifica se a tabela existe
            $tblCheck = $db->query("SHOW TABLES LIKE 'rumo10_balanceamentos'")->fetchColumn();
            if (!$tblCheck) {
                self::useFallback();
                return;
            }

            // Seleciona o balanceamento ativo
            $stmt = $db->query("SELECT id, codigo, nome, versao, checksum, ativado_em FROM rumo10_balanceamentos WHERE estado = 'ativo' ORDER BY ativado_em DESC, id DESC LIMIT 1");
            $balance = $stmt->fetch();

            if (!$balance) {
                self::useFallback();
                return;
            }

            self::$activeMetadata = $balance;

            // Busca os parâmetros vinculados ao balanceamento ativo
            $sql = "SELECT bf.formula_id, fp.chave, fp.tipo, fp.valor_float, fp.valor_int, fp.valor_texto, fp.valor_json, fp.limite_min, fp.limite_max
                    FROM rumo10_balanceamento_formulas bf
                    JOIN rumo10_formula_parametros fp ON fp.versao_id = bf.versao_id
                    WHERE bf.balanceamento_id = ?";
            $pStmt = $db->prepare($sql);
            $pStmt->execute([(int)$balance['id']]);
            $rows = $pStmt->fetchAll();

            foreach ($rows as $r) {
                $val = match($r['tipo']) {
                    'float' => (float)$r['valor_float'],
                    'int' => (int)$r['valor_int'],
                    'json' => json_decode((string)$r['valor_json'], true, 512, JSON_THROW_ON_ERROR),
                    default => $r['valor_texto']
                };

                // Aplica clamps de segurança
                if (is_numeric($val)) {
                    if ($r['limite_min'] !== null && $val < (float)$r['limite_min']) {
                        $val = is_int($val) ? (int)$r['limite_min'] : (float)$r['limite_min'];
                    }
                    if ($r['limite_max'] !== null && $val > (float)$r['limite_max']) {
                        $val = is_int($val) ? (int)$r['limite_max'] : (float)$r['limite_max'];
                    }
                }

                self::$activeCache[$r['formula_id']][$r['chave']] = $val;
            }

            // Mescla com os fallbacks para qualquer fórmula/chave ausente no banco
            foreach (self::NATIVE_FALLBACKS as $fId => $defaults) {
                if (!isset(self::$activeCache[$fId])) {
                    self::$activeCache[$fId] = $defaults;
                } else {
                    foreach ($defaults as $k => $v) {
                        if (!isset(self::$activeCache[$fId][$k])) {
                            self::$activeCache[$fId][$k] = $v;
                        }
                    }
                }
            }

        } catch (Throwable $e) {
            // Em caso de qualquer falha no banco, garante estabilidade sem parar o jogo
            error_log("rumo10: Falha ao carregar balanceamento do MySQL, utilizando fallback nativo: " . $e->getMessage());
            self::useFallback();
        }
    }

    private static function useFallback(): void {
        self::$activeCache = self::NATIVE_FALLBACKS;
        self::$activeMetadata = [
            'id' => 0,
            'codigo' => 'bal-nativo-fallback',
            'nome' => 'Balanceamento Nativo Estático (Fallback de Contingência)',
            'versao' => 1,
            'checksum' => 'native-approved-defaults',
            'ativado_em' => date('Y-m-d H:i:s')
        ];
        self::$fallbackActive = true;
    }
}
