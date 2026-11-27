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
    private static array $linkedFormulas = [];

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
        'match.rating.v2' => [
            'base' => 6.00,
            'w_goal_att' => 0.90,
            'w_goal_mid' => 1.02,
            'w_goal_sup' => 1.12,
            'w_goal_def' => 1.20,
            'hat_trick_bonus' => 0.15,
            'poker_bonus' => 0.15,
            'w_assist_creative' => 0.78,
            'w_assist_other' => 0.70,
            'w_assist_def' => 0.80,
            'multi_assist_bonus' => 0.15,
            'shot_eff_good_bonus' => 0.15,
            'shot_eff_perfect_bonus' => 0.25,
            'w_shot_miss' => 0.06,
            'zero_goal_high_shots_pen' => 0.20,
            'w_pass_ok' => 0.14,
            'w_pass_ok_mid' => 0.16,
            'pass_ok_cap' => 0.85,
            'pass_acc_high_bonus' => 0.24,
            'pass_acc_perfect_bonus' => 0.34,
            'w_pass_err' => 0.14,
            'w_pass_err_def' => 0.18,
            'w_tackle_cb' => 0.54,
            'w_tackle_dm' => 0.52,
            'w_tackle_fb' => 0.48,
            'w_tackle_gk' => 0.50,
            'w_tackle_att' => 0.22,
            'tackle_tier1_bonus' => 0.30,
            'tackle_tier2_bonus' => 0.25,
            'w_save_gk' => 0.55,
            'save_tier1_bonus' => 0.35,
            'save_tier2_bonus' => 0.25,
            'clean_sheet_gk' => 1.15,
            'clean_sheet_cb' => 0.90,
            'clean_sheet_def' => 0.75,
            'low_ga_cb_gk' => 0.25,
            'low_ga_def' => 0.15,
            'high_ga_gk_step' => 0.30,
            'high_ga_cb_step' => 0.22,
            'high_ga_def_step' => 0.15,
            'clean_def_discipline_bonus' => 0.25,
            'w_foul' => 0.08,
            'w_yellow' => 0.42,
            'w_second_yellow_red' => 1.40,
            'w_direct_red' => 1.65,
            'w_penalty_missed' => 0.45,
            'win_bonus' => 0.00,
            'loss_penalty' => 0.00,
            'full_factor_minutes' => 75.0,
        ],
        'season.evaluation.v2' => [
            'sample_minutes' => 720.0,
            'sample_games' => 10.0,
            'w_minutes_conf' => 0.65,
            'w_games_conf' => 0.35,
            'rating_divisor' => 2.55,
            'w_rating' => 0.52,
            'w_production' => 0.22,
            'w_regularity' => 0.10,
            'w_opponent' => 0.06,
            'title_major_first' => 0.12,
            'title_major_extra' => 0.06,
            'title_stage' => 0.04,
            'title_cap' => 0.20,
            'band_regular_min' => 0.30,
            'band_good_min' => 0.55,
            'band_excellent_min' => 0.80,
            'band_exceptional_min' => 1.05,
        ],
        'market.quality.tiers.v2' => [
            'target_offers' => 3,
            'mu_inferior' => 0.08,
            'mu_equivalente' => 0.40,
            'mu_superior' => 0.70,
            'mu_destaque' => 0.96,
            'mu_elite' => 1.22,
            'sigma' => 0.28,
            'min_weight' => 2,
            'weight_scale' => 100,
            'max_ovr_gap_base' => 10.0,
            'max_ovr_gap_merit' => 5.5,
            'max_ovr_gap_cap' => 8.0,
            'destaque_min_player_ovr' => 54.0,
            'destaque_min_club_ovr' => 62.0,
            'elite_min_player_ovr' => 68.0,
            'elite_min_club_ovr' => 76.0,
            'w_club_delta' => 0.55,
            'w_league_delta' => 0.45,
            'league_drop_block_superior' => -8.0,
            'league_drop_force_inferior' => -14.0,
            'league_step_up_min' => 6.0,
            'league_step_up_max_ovr_drop' => -4.0,
            'superior_composite_min' => 2.0,
            'inferior_composite_max' => -2.5,
            'destaque_min_league' => 64.0,
            'elite_min_league' => 76.0,
        ],
        'confidence.balance.v2' => [
            'tech_fail_normal' => -1,
            'tech_fail_severe' => -3,
            'decisive_fail_normal' => -2,
            'decisive_fail_severe' => -4,
            'sent_off_penalty' => -2,
            'zero_trap_floor_normal' => 10,
            'zero_trap_floor_severe' => 8,
            'goal_att_low' => 4,
            'goal_att_mid' => 3,
            'goal_def_low' => 5,
            'goal_def_mid' => 4,
            'goal_high' => 2,
            'goal_very_high' => 1,
            'assist_creator_very_low' => 4,
            'assist_low' => 3,
            'assist_creator_mid' => 3,
            'assist_other_mid' => 2,
            'assist_high' => 1,
            'def_tier1_low' => 2,
            'def_tier1_mid' => 1,
            'def_tier1_cap' => 75,
            'def_tier2_low' => 2,
            'def_tier2_high' => 1,
            'def_tier2_cap' => 85,
            'clean_sheet_bonus' => 1,
            'clean_sheet_cap' => 78,
            'exceptional_play_bonus' => 1,
            'exceptional_play_cap' => 65,
            'exceptional_training_bonus' => 1,
            'exceptional_training_cap' => 42,
            'post_full_minutes' => 60.0,
            'post_ceiling' => 90,
            'regularity_streak_step' => 3,
            'regularity_min_rating' => 6.7,
            'regularity_min_minutes' => 45,
            'regularity_bonus' => 1,
            'regularity_cap' => 75,
            'season_title_major' => 5,
            'season_title_stage' => 2,
            'season_promotion' => 4,
            'season_achievement_cap' => 6,
            'season_relegation_penalty' => -2,
            'offseason_target_ref' => 42,
            'offseason_rate_normal' => 0.25,
            'offseason_rate_poor' => 0.15,
            'offseason_cap_normal' => 6,
            'offseason_cap_poor' => 3,
            'offseason_cool_threshold' => 88,
            'offseason_cool_cap' => 3,
        ],
    ];

    public static function resetCache(): void {
        self::$activeCache = null;
        self::$activeMetadata = null;
        self::$fallbackActive = false;
        self::$linkedFormulas = [];
    }

    public static function isFallbackActive(): bool {
        return self::$fallbackActive;
    }

    public static function isDevelopmentV3Active(): bool {
        return config('RUMO10_DEVELOPMENT_V2','0') === '1';
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

    public static function effectiveFormulas(): array {
        self::ensureLoaded();
        $formulas = [];
        foreach (self::$activeCache as $id => $values) {
            $linked = self::$linkedFormulas[$id] ?? null;
            $parameters = [];
            foreach ($values as $key => $value) {
                $fromDatabase = isset($linked['stored'][$key]);
                $parameters[$key] = ['value'=>$value, 'source'=>$fromDatabase ? 'MySQL' : 'fallback PHP',
                    'stored'=>$fromDatabase ? $linked['stored'][$key]['value'] : null,
                    'type'=>$fromDatabase ? $linked['stored'][$key]['type'] : null];
            }
            $formulas[$id] = ['version'=>$linked['version'] ?? null, 'version_id'=>$linked['version_id'] ?? null,
                'parameters'=>$parameters];
        }
        return $formulas;
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
            $sql = "SELECT bf.formula_id, fv.versao AS version, bf.versao_id AS version_id, fp.chave, fp.tipo, fp.valor_float, fp.valor_int, fp.valor_texto, fp.valor_json, fp.limite_min, fp.limite_max
                    FROM rumo10_balanceamento_formulas bf
                    JOIN rumo10_formula_versoes fv ON fv.id = bf.versao_id
                    LEFT JOIN rumo10_formula_parametros fp ON fp.versao_id = fv.id
                    WHERE bf.balanceamento_id = ?";
            $pStmt = $db->prepare($sql);
            $pStmt->execute([(int)$balance['id']]);
            $rows = $pStmt->fetchAll();

            foreach ($rows as $r) {
                $id = $r['formula_id'];
                self::$linkedFormulas[$id] ??= ['version'=>(int)$r['version'], 'version_id'=>(int)$r['version_id'], 'stored'=>[]];
                self::$activeCache[$id] ??= [];
                if ($r['chave'] === null) continue;
                $val = match($r['tipo']) {
                    'float' => (float)$r['valor_float'],
                    'int' => (int)$r['valor_int'],
                    'json' => json_decode((string)$r['valor_json'], true, 512, JSON_THROW_ON_ERROR),
                    default => $r['valor_texto']
                };

                // Mantém o valor persistido disponível para edição, inclusive quando houver clamp.
                self::$linkedFormulas[$id]['stored'][$r['chave']] = ['value'=>$val, 'type'=>$r['tipo']];

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
        self::$linkedFormulas = [];
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
