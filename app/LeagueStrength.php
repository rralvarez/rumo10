<?php
declare(strict_types=1);

/**
 * Resolução centralizada de força das competições e classificação esportiva multidimensional (R10-CAR-003-R1).
 *
 * - Integra-se exclusivamente aos contratos existentes de Competition, TeamStrength e BalanceService.
 * - Prepara consumo direto de futuras avaliações homologadas do RumoHub/RumoRating sem alterar a lógica central.
 * - Distingue referências confirmadas, legadas (catálogo/pirâmide), provisórias e indisponíveis.
 * - Aplica tratamento conservador quando faltam dados sobre uma competição, sem presumir superioridade.
 */
final class LeagueStrength {
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_LEGACY = 'legacy';
    public const STATUS_PROVISIONAL = 'provisional';
    public const STATUS_UNAVAILABLE = 'unavailable';

    private const PYRAMID_LEVELS = [
        'br-a' => 84.0,
        'br-b' => 72.0,
        'br-c' => 60.0,
        'br-d' => 48.0,
        'sp-a1' => 64.0,
        'sp-a2' => 53.0,
        'sp-a3' => 45.0,
        'sp-a4' => 38.0,
        'sp-segunda' => 30.0,
        'ar-1' => 81.0,
        'ar-2' => 63.0,
        'uy-1' => 64.0,
        'uy-2' => 48.0,
        'py-1' => 65.0,
        'py-2' => 46.0,
        'bo-1' => 58.0,
        'bo-2' => 39.0,
        'am-a1' => 42.0,
        'rr-entrada' => 48.0,
    ];

    /**
     * Identifica pirâmide, divisão numérica e natureza (nacional vs estadual/copa) de uma competição.
     *
     * @return array{pyramid:string,level:?int,kind:string,scope_label:string}
     */
    public static function competitionMeta(string $id, ?array $comp = null): array {
        $comp ??= Competition::catalog()[$id] ?? [];
        $family = (string)($comp['family'] ?? '');

        if (preg_match('/^br-([abcd])$/', $id, $m)) {
            $lvl = strpos('abcd', $m[1]) + 1;
            return ['pyramid' => 'br', 'level' => $lvl, 'kind' => 'national_league', 'scope_label' => "{$lvl}ª divisão nacional"];
        }
        if (preg_match('/^sp-(a[1-4]|segunda)$/', $id, $m)) {
            $lvl = $m[1] === 'segunda' ? 5 : (int)substr($m[1], 1);
            return ['pyramid' => 'sp', 'level' => $lvl, 'kind' => 'state_league', 'scope_label' => "{$lvl}ª divisão estadual"];
        }
        if ($id === 'am-a1' || $id === 'am-entrada') {
            $lvl = $id === 'am-a1' ? 1 : 2;
            return ['pyramid' => 'am', 'level' => $lvl, 'kind' => 'state_league', 'scope_label' => "{$lvl}ª divisão estadual"];
        }
        if (str_ends_with($id, '-entrada') || $family === 'state') {
            return ['pyramid' => $id, 'level' => 2, 'kind' => 'state_league', 'scope_label' => 'estadual de acesso'];
        }
        if (preg_match('/^([a-z]{2})-(\d+)$/', $id, $m)) {
            $lvl = (int)$m[2];
            return ['pyramid' => $m[1], 'level' => $lvl, 'kind' => 'national_league', 'scope_label' => "{$lvl}ª divisão nacional"];
        }
        if (str_contains($id, 'cup') || str_contains($id, 'copa')) {
            return ['pyramid' => $id, 'level' => null, 'kind' => 'cup', 'scope_label' => 'copa'];
        }
        return ['pyramid' => $id, 'level' => null, 'kind' => 'other', 'scope_label' => 'competição oficial'];
    }

    /**
     * Prioridade estrutural para selecionar a competição principal de um clube quando disputa torneios simultâneos
     * (ex.: Brasileirão Série A tem precedência sobre o Campeonato Paulista na avaliação do patamar da liga).
     */
    public static function competitionPriority(string $id, ?array $comp = null): int {
        $meta = self::competitionMeta($id, $comp);
        return match ($meta['kind']) {
            'national_league' => 10 + (int)($meta['level'] ?? 5),
            'state_league' => 50 + (int)($meta['level'] ?? 5),
            'cup' => 80,
            default => 90,
        };
    }

    /**
     * Seleciona a competição principal de referência entre várias competições simultâneas de um clube.
     */
    public static function primaryCompetition(array $ids, array $catalog = []): ?string {
        if ($ids === []) {
            return null;
        }
        $catalog = $catalog ?: Competition::catalog();
        $sorted = array_values(array_unique(array_map('strval', $ids)));
        usort($sorted, static function (string $a, string $b) use ($catalog): int {
            $pa = self::competitionPriority($a, $catalog[$a] ?? null);
            $pb = self::competitionPriority($b, $catalog[$b] ?? null);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $sa = self::resolveCompetition($a, [], $catalog)['strength'] ?? 0.0;
            $sb = self::resolveCompetition($b, [], $catalog)['strength'] ?? 0.0;
            return ($sb <=> $sa) ?: strcmp($a, $b);
        });
        return $sorted[0];
    }

    /**
     * Resolve a força esportiva e o grau de confiabilidade de uma competição.
     *
     * @return array{
     *   competition_id:string,
     *   name:string,
     *   strength:?float,
     *   status:string,
     *   source:string,
     *   kind:string,
     *   pyramid:string,
     *   division:?int,
     *   scope_label:string,
     *   conservative:bool
     * }
     */
    public static function resolveCompetition(string $id, array $g = [], ?array $catalog = null): array {
        $catalog ??= Competition::catalog();
        $comp = $catalog[$id] ?? null;
        $meta = self::competitionMeta($id, $comp);
        $name = (string)($comp['name'] ?? $id);

        if ($comp === null) {
            return [
                'competition_id' => $id,
                'name' => $name,
                'strength' => null,
                'status' => self::STATUS_UNAVAILABLE,
                'source' => 'missing_competition',
                'kind' => $meta['kind'],
                'pyramid' => $meta['pyramid'],
                'division' => $meta['level'],
                'scope_label' => $meta['scope_label'],
                'conservative' => true,
            ];
        }

        // 1. Contrato futuro RumoHub/RumoRating confirmado
        $ratingStatus = strtolower((string)($comp['strength_status'] ?? $comp['rating_status'] ?? ''));
        foreach (['confirmed_strength', 'league_strength', 'strength_rating', 'rating'] as $field) {
            if (isset($comp[$field]) && is_numeric($comp[$field])) {
                $val = (float)$comp[$field];
                if ($val >= 20.0 && $val <= 99.0 && ($ratingStatus === '' || $ratingStatus === 'confirmed')) {
                    return [
                        'competition_id' => $id,
                        'name' => $name,
                        'strength' => round($val, 1),
                        'status' => self::STATUS_CONFIRMED,
                        'source' => 'catalog_' . $field,
                        'kind' => $meta['kind'],
                        'pyramid' => $meta['pyramid'],
                        'division' => $meta['level'],
                        'scope_label' => $meta['scope_label'],
                        'conservative' => false,
                    ];
                }
            }
        }

        // 2. Baseline explícito do catálogo ou nível de pirâmide conhecido (referência legada homologada)
        if (isset($comp['baseline']) && is_numeric($comp['baseline'])) {
            return [
                'competition_id' => $id,
                'name' => $name,
                'strength' => round((float)$comp['baseline'], 1),
                'status' => self::STATUS_LEGACY,
                'source' => 'catalog_baseline',
                'kind' => $meta['kind'],
                'pyramid' => $meta['pyramid'],
                'division' => $meta['level'],
                'scope_label' => $meta['scope_label'],
                'conservative' => false,
            ];
        }
        if (isset(self::PYRAMID_LEVELS[$id])) {
            return [
                'competition_id' => $id,
                'name' => $name,
                'strength' => self::PYRAMID_LEVELS[$id],
                'status' => self::STATUS_LEGACY,
                'source' => 'pyramid_baseline',
                'kind' => $meta['kind'],
                'pyramid' => $meta['pyramid'],
                'division' => $meta['level'],
                'scope_label' => $meta['scope_label'],
                'conservative' => false,
            ];
        }

        // 3. Média provisória dos clubes participantes quando há referências individuais reais (sem inventar ranking)
        $teams = $g['next_memberships'][$id] ?? $g['memberships'][$id] ?? $comp['teams'] ?? [];
        $clubRatings = [];
        $clubsCatalog = Competition::clubs();
        foreach ($teams as $club) {
            if (isset($g['next_club_strengths'][$club]) && is_numeric($g['next_club_strengths'][$club])) {
                $clubRatings[] = (float)$g['next_club_strengths'][$club];
            } elseif (isset($clubsCatalog[$club]['reference_overall']) && is_numeric($clubsCatalog[$club]['reference_overall'])) {
                $clubRatings[] = (float)$clubsCatalog[$club]['reference_overall'];
            }
        }
        if (count($clubRatings) >= 2) {
            $avg = array_sum($clubRatings) / count($clubRatings);
            return [
                'competition_id' => $id,
                'name' => $name,
                'strength' => round(max(20.0, min(95.0, $avg)), 1),
                'status' => self::STATUS_PROVISIONAL,
                'source' => 'participants_reference_avg',
                'kind' => $meta['kind'],
                'pyramid' => $meta['pyramid'],
                'division' => $meta['level'],
                'scope_label' => $meta['scope_label'],
                'conservative' => true,
            ];
        }

        // 4. Sem referência confiável -> indisponível (tratamento estritamente conservador)
        return [
            'competition_id' => $id,
            'name' => $name,
            'strength' => null,
            'status' => self::STATUS_UNAVAILABLE,
            'source' => 'unavailable',
            'kind' => $meta['kind'],
            'pyramid' => $meta['pyramid'],
            'division' => $meta['level'],
            'scope_label' => $meta['scope_label'],
            'conservative' => true,
        ];
    }

    /**
     * Classifica multidimensionalmente um candidato frente ao clube e liga atuais do atleta.
     *
     * @return array{
     *   tier:int,
     *   tier_label:string,
     *   club_overall:float,
     *   current_overall:float,
     *   overall_delta:float,
     *   origin_competition:?string,
     *   origin_league_name:?string,
     *   origin_league_strength:?float,
     *   origin_league_status:string,
     *   target_competition:?string,
     *   target_league_name:?string,
     *   target_league_strength:?float,
     *   target_league_status:string,
     *   league_delta:?float,
     *   composite_delta:float,
     *   div_step:?int,
     *   conservative_fallback:bool,
     *   reason:string
     * }
     */
    public static function assess(array $g, array $c, ?array $ctx = null): array {
        $mp = class_exists('BalanceService') ? BalanceService::params('market.quality.tiers.v2') : [];
        $destMinPlayer = (float)($mp['destaque_min_player_ovr'] ?? 54.0);
        $destMinClub = (float)($mp['destaque_min_club_ovr'] ?? 62.0);
        $eliteMinPlayer = (float)($mp['elite_min_player_ovr'] ?? 68.0);
        $eliteMinClub = (float)($mp['elite_min_club_ovr'] ?? 76.0);

        $wClub = (float)($mp['w_club_delta'] ?? 0.55);
        $wLeague = (float)($mp['w_league_delta'] ?? 0.45);
        $leagueDropBlockSup = (float)($mp['league_drop_block_superior'] ?? -8.0);
        $leagueDropForceInf = (float)($mp['league_drop_force_inferior'] ?? -14.0);
        $leagueStepUpMin = (float)($mp['league_step_up_min'] ?? 6.0);
        $leagueStepUpMaxOvrDrop = (float)($mp['league_step_up_max_ovr_drop'] ?? -4.0);
        $superiorCompMin = (float)($mp['superior_composite_min'] ?? 2.0);
        $inferiorCompMax = (float)($mp['inferior_composite_max'] ?? -2.5);
        $destMinLeague = (float)($mp['destaque_min_league'] ?? 64.0);
        $eliteMinLeague = (float)($mp['elite_min_league'] ?? 76.0);

        $skills = $g['skills'] ?? [];
        $playerOvr = $ctx[0] ?? ($skills ? array_sum(array_column($skills, 'value')) / max(1, count($skills)) : 50.0);
        $currentOvr = $ctx[1] ?? (float)($g['next_club_strengths'][$g['club']] ?? TeamStrength::rating($g['club'], $g));
        $originCountry = $ctx[2] ?? (!empty($g['official']) ? TransferMarket::country((string)$g['club']) : 'BR');
        $currentDivs = $ctx[3] ?? [];
        $clubDivs = $ctx[4] ?? [];
        $originLeague = $ctx[5] ?? null;

        $club = (string)($c['club'] ?? '');
        $ovr = (float)($c['overall'] ?? 50.0);
        $delta = (float)($c['overall_delta'] ?? ($ovr - $currentOvr));
        $cc = (string)($c['country'] ?? $originCountry);

        if (empty($g['official'])) {
            $tier = self::classifySameLeague($playerOvr, $currentOvr, $ovr, $delta, null, $mp);
            return [
                'tier' => $tier,
                'tier_label' => TransferInterest::TIERS[$tier],
                'club_overall' => round($ovr, 1),
                'current_overall' => round($currentOvr, 1),
                'overall_delta' => round($delta, 1),
                'origin_competition' => null,
                'origin_league_name' => 'Liga regional',
                'origin_league_strength' => null,
                'origin_league_status' => self::STATUS_LEGACY,
                'target_competition' => null,
                'target_league_name' => 'Liga regional',
                'target_league_strength' => null,
                'target_league_status' => self::STATUS_LEGACY,
                'league_delta' => 0.0,
                'composite_delta' => round($delta, 1),
                'div_step' => 0,
                'conservative_fallback' => false,
                'reason' => self::buildReason($tier, $delta, 0.0, null, null, null, false),
            ];
        }

        $originIdsFromMembers = [];
        if ($ctx === null) {
            $members = $g['next_memberships'] ?? $g['memberships'] ?? [];
            foreach ($members as $mId => $mTeams) {
                if (in_array($g['club'], $mTeams, true)) {
                    $originIdsFromMembers[] = (string)$mId;
                    $currentDivs[] = TransferInterest::division((string)$mId);
                }
            }
            if ($originIdsFromMembers === [] && !empty($g['season_competitions'])) {
                $originIdsFromMembers = array_map('strval', $g['season_competitions']);
            }
            if ($currentDivs === [] && !empty($g['competition_id'])) {
                $currentDivs[] = TransferInterest::division((string)$g['competition_id']);
            }
        }

        $catalog = Competition::catalog();
        if ($originLeague === null) {
            $originIds = $clubDivs[$g['club']] ?? ($originIdsFromMembers !== [] ? $originIdsFromMembers : (!empty($g['competition_id']) ? [$g['competition_id']] : []));
            $originCompId = self::primaryCompetition($originIds, $catalog);
            $originLeague = $originCompId !== null ? self::resolveCompetition($originCompId, $g, $catalog) : [
                'competition_id' => '',
                'name' => 'Competição atual',
                'strength' => null,
                'status' => self::STATUS_UNAVAILABLE,
                'kind' => 'other',
                'pyramid' => '',
                'division' => null,
                'scope_label' => 'competição',
                'conservative' => true,
            ];
        }

        $targetIds = $c['all_competitions'] ?? ($clubDivs[$club] ?? (!empty($c['competition']) ? [$c['competition']] : []));
        $targetCompId = self::primaryCompetition($targetIds, $catalog) ?? (string)($c['competition'] ?? '');
        $targetLeague = $targetCompId !== '' ? self::resolveCompetition($targetCompId, $g, $catalog) : [
            'competition_id' => '',
            'name' => 'Competição sem referência',
            'strength' => null,
            'status' => self::STATUS_UNAVAILABLE,
            'kind' => 'other',
            'pyramid' => '',
            'division' => null,
            'scope_label' => 'competição',
            'conservative' => true,
        ];

        $divStep = null;
        if ($cc === $originCountry) {
            foreach ($targetIds as $candComp) {
                $d = TransferInterest::division((string)$candComp);
                foreach ($currentDivs as [$pyr, $lvl]) {
                    if ($lvl !== null && $d[0] === $pyr && $d[1] !== null) {
                        $step = $d[1] - $lvl;
                        if ($divStep === null || abs($step) < abs($divStep)) {
                            $divStep = $step;
                        }
                    }
                }
            }
        }

        $origStr = $originLeague['strength'];
        $targStr = $targetLeague['strength'];
        $leagueDelta = ($origStr !== null && $targStr !== null) ? round($targStr - $origStr, 1) : null;
        $conservative = ($targetLeague['status'] === self::STATUS_UNAVAILABLE) || ($originLeague['status'] === self::STATUS_UNAVAILABLE);

        // Quando falta referência de competição, nunca presumir superioridade esportiva.
        if ($conservative || $leagueDelta === null) {
            $tier = ($delta <= -2.0 || ($divStep !== null && $divStep >= 1)) ? 0 : 1;
            return [
                'tier' => $tier,
                'tier_label' => TransferInterest::TIERS[$tier],
                'club_overall' => round($ovr, 1),
                'current_overall' => round($currentOvr, 1),
                'overall_delta' => round($delta, 1),
                'origin_competition' => $originLeague['competition_id'] ?: null,
                'origin_league_name' => $originLeague['name'],
                'origin_league_strength' => $origStr,
                'origin_league_status' => $originLeague['status'],
                'target_competition' => $targetLeague['competition_id'] ?: null,
                'target_league_name' => $targetLeague['name'],
                'target_league_strength' => $targStr,
                'target_league_status' => $targetLeague['status'],
                'league_delta' => $leagueDelta,
                'composite_delta' => round($delta, 1),
                'div_step' => $divStep,
                'conservative_fallback' => true,
                'reason' => self::buildReason($tier, $delta, $leagueDelta, $divStep, $originLeague, $targetLeague, true),
            ];
        }

        $compositeDelta = round($wClub * $delta + $wLeague * $leagueDelta, 1);

        // Regra RF02: queda significativa na qualidade da liga impede classificação superior/destaque/elite
        // e rebaixa para Inferior quando o recuo competitivo é severo (ex.: BR Série A -> Guatemala / Guiana).
        if ($leagueDelta <= $leagueDropForceInf) {
            $tier = 0;
        } elseif ($leagueDelta <= $leagueDropBlockSup) {
            $tier = ($compositeDelta <= $inferiorCompMax || ($divStep !== null && $divStep >= 1 && $delta < 3.0)) ? 0 : 1;
        } elseif ($divStep !== null && $divStep >= 1 && $delta < 2.0) {
            // Retrocesso de divisão na mesma pirâmide nacional sem salto expressivo de força
            $tier = 0;
        } else {
            // Avaliação multidimensional integrada
            $leagueSupportsElite = $targStr >= $eliteMinLeague
                || ($targetLeague['status'] === self::STATUS_PROVISIONAL && $leagueDelta >= 0.0 && $ovr >= $eliteMinClub);
            $isElite = $playerOvr >= $eliteMinPlayer
                && $ovr >= $eliteMinClub
                && $leagueSupportsElite
                && ($targetLeague['division'] ?? 1) === 1
                && $leagueDelta >= -3.0
                && ($compositeDelta >= 6.5 || ($ovr >= 88.0 && $targStr >= 84.0 && $compositeDelta >= 4.0) || ($currentOvr >= 88.0 && $ovr >= 88.0 && $leagueDelta >= -3.0 && $delta >= -2.5));

            $isDestaque = !$isElite
                && $playerOvr >= $destMinPlayer
                && $ovr >= $destMinClub
                && $targStr >= $destMinLeague
                && ($targetLeague['division'] ?? 1) <= 2
                && $leagueDelta >= -5.0
                && ($compositeDelta >= 4.0 || ($ovr >= 80.0 && $targStr >= 74.0 && $compositeDelta >= 3.0) || ($leagueDelta >= 10.0 && $delta >= -2.0) || ($currentOvr >= 88.0 && $ovr >= 85.0 && $delta >= -5.0 && $leagueDelta >= -4.0));

            $isSuperior = !$isElite && !$isDestaque && (
                $compositeDelta >= $superiorCompMin
                || ($divStep !== null && $divStep <= -1 && $delta >= -1.5 && $leagueDelta >= 4.0)
                || ($leagueDelta >= $leagueStepUpMin && $delta >= $leagueStepUpMaxOvrDrop && $compositeDelta >= 1.2)
                || ($currentOvr >= 88.0 && $ovr >= 84.0 && $delta >= -1.0 && $leagueDelta >= -2.0)
            );

            $isInferior = !$isElite && !$isDestaque && !$isSuperior && (
                $compositeDelta <= $inferiorCompMax
                || ($delta <= -3.0 && $leagueDelta <= 2.0)
                || ($divStep !== null && $divStep >= 1 && $delta < 0.0)
            );

            $tier = $isElite ? 4 : ($isDestaque ? 3 : ($isSuperior ? 2 : ($isInferior ? 0 : 1)));
        }

        return [
            'tier' => $tier,
            'tier_label' => TransferInterest::TIERS[$tier],
            'club_overall' => round($ovr, 1),
            'current_overall' => round($currentOvr, 1),
            'overall_delta' => round($delta, 1),
            'origin_competition' => $originLeague['competition_id'] ?: null,
            'origin_league_name' => $originLeague['name'],
            'origin_league_strength' => $origStr,
            'origin_league_status' => $originLeague['status'],
            'target_competition' => $targetLeague['competition_id'] ?: null,
            'target_league_name' => $targetLeague['name'],
            'target_league_strength' => $targStr,
            'target_league_status' => $targetLeague['status'],
            'league_delta' => $leagueDelta,
            'composite_delta' => $compositeDelta,
            'div_step' => $divStep,
            'conservative_fallback' => false,
            'reason' => self::buildReason($tier, $delta, $leagueDelta, $divStep, $originLeague, $targetLeague, false),
        ];
    }

    private static function classifySameLeague(float $playerOvr, float $currentOvr, float $ovr, float $delta, ?int $divStep, array $mp): int {
        $destMinPlayer = (float)($mp['destaque_min_player_ovr'] ?? 54.0);
        $destMinClub = (float)($mp['destaque_min_club_ovr'] ?? 62.0);
        $eliteMinPlayer = (float)($mp['elite_min_player_ovr'] ?? 68.0);
        $eliteMinClub = (float)($mp['elite_min_club_ovr'] ?? 76.0);

        if ($playerOvr >= $eliteMinPlayer && $ovr >= $eliteMinClub
            && ($delta >= 7.5 || ($ovr >= 88.0 && $delta >= 5.0) || ($currentOvr >= 88.0 && $ovr >= 88.0 && $delta >= -2.5))) {
            return 4;
        }
        if ($playerOvr >= $destMinPlayer && $ovr >= $destMinClub
            && ($delta >= 4.5 || ($ovr >= 80.0 && $delta >= 3.5) || ($currentOvr >= 88.0 && $ovr >= 85.0 && $delta >= -5.0))) {
            return 3;
        }
        if ($delta >= 2.0 || ($divStep !== null && $divStep <= -1 && $delta >= 0.5) || ($currentOvr >= 88.0 && $ovr >= 84.0 && $delta >= -1.0)) {
            return 2;
        }
        if ($delta <= -2.5 || ($divStep !== null && $divStep >= 1 && $delta < 0.0)) {
            return 0;
        }
        return 1;
    }

    private static function buildReason(
        int $tier,
        float $ovrDelta,
        ?float $leagueDelta,
        ?int $divStep,
        ?array $originLeague,
        ?array $targetLeague,
        bool $conservative
    ): string {
        $ovrTxt = ($ovrDelta >= 0 ? '+' : '') . number_format($ovrDelta, 1, ',', '.');
        if ($conservative) {
            return "Classificação conservadora ({$ovrTxt} de overall do clube): referência de força da competição indisponível no catálogo atual.";
        }
        $lgTxt = $leagueDelta !== null ? (($leagueDelta >= 0 ? '+' : '') . number_format($leagueDelta, 1, ',', '.')) : '0,0';
        $divNote = '';
        if ($divStep !== null && $divStep < 0) {
            $divNote = ' com avanço de divisão';
        } elseif ($divStep !== null && $divStep > 0) {
            $divNote = ' com recuo de divisão';
        }

        if ($leagueDelta !== null && $leagueDelta <= -8.0 && $ovrDelta > 0) {
            return "Apesar de {$ovrTxt} no overall do clube, a liga de destino tem força {$lgTxt} abaixo da sua liga atual, caracterizando patamar " . TransferInterest::TIERS[$tier] . ".";
        }
        if ($leagueDelta !== null && $leagueDelta >= 6.0 && $ovrDelta < 0) {
            return "Mesmo com {$ovrTxt} no overall do clube, a transferência representa salto de {$lgTxt} na força da liga{$divNote}.";
        }
        if ($tier >= 3) {
            return "Combina clube ({$ovrTxt} de overall) e liga ({$lgTxt} de força relativa){$divNote} compatíveis com a faixa " . TransferInterest::TIERS[$tier] . ".";
        }
        if ($tier === 2) {
            return "Evolução esportiva combinada ({$ovrTxt} de overall do clube e {$lgTxt} na força da liga{$divNote}).";
        }
        if ($tier === 0) {
            return "Patamar inferior pela combinação de overall do clube ({$ovrTxt}) e força relativa da liga ({$lgTxt}){$divNote}.";
        }
        return "Equilíbrio competitivo entre overall do clube ({$ovrTxt}) e força da liga ({$lgTxt}){$divNote}.";
    }
}
