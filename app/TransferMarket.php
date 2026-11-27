<?php
declare(strict_types=1);

final class TransferMarket {
    public const COUNTRIES = ['EN'=>'Inglaterra','ES'=>'Espanha','DE'=>'Alemanha','IT'=>'Itália','FR'=>'França','PT'=>'Portugal','NL'=>'Países Baixos','BE'=>'Bélgica','TR'=>'Turquia','SC'=>'Escócia','AR'=>'Argentina','UY'=>'Uruguai','PY'=>'Paraguai','BO'=>'Bolívia','BR'=>'Brasil','US'=>'Estados Unidos','CA'=>'Canadá','MX'=>'México','CR'=>'Costa Rica','HN'=>'Honduras','GT'=>'Guatemala','SV'=>'El Salvador','NO'=>'Noruega','DK'=>'Dinamarca','GR'=>'Grécia','CY'=>'Chipre','RU'=>'Rússia','SA'=>'Arábia Saudita','EC'=>'Equador','CO'=>'Colômbia','CZ'=>'Tchéquia','PL'=>'Polônia','HU'=>'Hungria','HR'=>'Croácia'];
    public const EXTRA_COUNTRIES = ['NI'=>'Nicarágua','PA'=>'Panamá','HT'=>'Haiti','JM'=>'Jamaica','DO'=>'República Dominicana','SR'=>'Suriname','CL'=>'Chile','PE'=>'Peru','VE'=>'Venezuela','GY'=>'Guiana','ZA'=>'África do Sul','NG'=>'Nigéria','GH'=>'Gana','KE'=>'Quênia','MA'=>'Marrocos','EG'=>'Egito','DZ'=>'Argélia','TN'=>'Tunísia','TZ'=>'Tanzânia','CD'=>'RD Congo','AO'=>'Angola','CI'=>'Costa do Marfim','ZM'=>'Zâmbia','UG'=>'Uganda','RW'=>'Ruanda','CM'=>'Camarões','SN'=>'Senegal','ML'=>'Mali','ET'=>'Etiópia','LY'=>'Líbia','AT'=>'Áustria','AZ'=>'Azerbaijão','CH'=>'Suíça','LI'=>'Liechtenstein','UA'=>'Ucrânia','RS'=>'Sérvia','JP'=>'Japão','IL'=>'Israel','BG'=>'Bulgária','SE'=>'Suécia','IN'=>'Índia','CN'=>'China','TH'=>'Tailândia','AE'=>'Emirados Árabes Unidos','FI'=>'Finlândia','IE'=>'Irlanda','AU'=>'Austrália','NZ'=>'Nova Zelândia','KR'=>'Coreia do Sul','RO'=>'Romênia'];
    public const EUROPE = ['EN','ES','DE','IT','FR','PT','NL','BE','TR','SC','NO','DK','GR','CY','CZ','PL','HU','HR','RU','AT','AZ','CH','LI','UA','RS','IL','BG','SE','FI','IE','RO'];

    public const CONFEDERATION_LABELS = [
        'CONMEBOL' => 'América do Sul (CONMEBOL)',
        'UEFA' => 'Europa (UEFA)',
        'CONCACAF' => 'América do Norte/Central (CONCACAF)',
        'AFC' => 'Ásia/Oceania (AFC)',
        'CAF' => 'África (CAF)',
        'OFC' => 'Oceania (OFC)',
    ];

    public static function country(string $club): string {
        return Competition::clubs()[$club]['country'] ?? 'BR';
    }

    public static function sportingLevel(string $id): int {
        $meta = LeagueStrength::competitionMeta($id);
        if ($meta['kind'] === 'national_league' && $meta['level'] !== null) {
            return min(5, max(1, (int)$meta['level']));
        }
        return 5;
    }

    public static function profile(array $g, ?array $evaluation = null): array {
        $s = Game::summary($g);
        $skills = $g['skills'] ?? [];
        $technical = $skills ? array_sum(array_column($skills, 'value')) / max(1, count($skills)) : 50.0;
        $growth = max(0.0, $technical - (float)($g['season_skill_baseline'] ?? $technical));
        $evaluation ??= SeasonEvaluation::evaluate($g);
        $tackles = (int)($evaluation['totals']['tackles'] ?? 0);
        $def = in_array($g['position'] ?? '', ['goleiro', 'zagueiro', 'lateral', 'volante'], true);
        $production = ($s['goals'] * ($def ? 0.6 : 1.0) + $s['assists'] + ($def ? $tackles * 0.12 : 0.0)) * 90.0 / max(90, (int)$s['minutes']);
        $titles = (int)($evaluation['titles_count'] ?? 0);
        $reputation = array_sum(array_map(static fn($k) => (float)($g['states'][$k] ?? 50), ['confianca_treinador', 'confianca_imprensa', 'apoio_torcida'])) / 3.0;
        $potential = ($g['age'] ?? 20) <= 26 ? max(0.0, min(1.0, (Evolution::ceiling(32, 'passe') - $technical) / 60.0)) : 0.0;

        // Mérito anual baseado exclusivamente na avaliação da última temporada (sem recontar títulos já incluídos)
        $seasonScore = (float)($evaluation['score'] ?? $evaluation['weighted_score'] ?? 0.35);
        $merit = max(0.0, min(1.5, $seasonScore + min(0.08, $growth * 0.025) + max(-0.06, min(0.06, ($reputation - 50.0) / 700.0))));

        $season = (int)($g['season'] ?? 1);
        $origin = self::country((string)($g['club'] ?? ''));
        $continentalOpen = $season >= 2 || $origin !== 'BR' || ($s['games'] >= 2 && $s['minutes'] >= 120 && $merit >= 0.20) || $technical >= 54.0;
        $worldOpen = $season >= 3 || $origin !== 'BR' || ($s['games'] >= 3 && $s['minutes'] >= 270 && $merit >= 0.38) || $technical >= 58.0;

        return $s + [
            'technical' => $technical,
            'growth' => $growth,
            'production' => $production,
            'titles' => $titles,
            'reputation' => $reputation,
            'potential' => $potential,
            'merit' => $merit,
            'abroad' => $continentalOpen,
            'continental_open' => $continentalOpen,
            'world_open' => $worldOpen,
        ];
    }

    public static function compatibleOverallCeiling(array $profile): float {
        $mp = class_exists('BalanceService') ? BalanceService::params('market.quality.tiers.v2') : [];
        $gapBase = (float)($mp['max_ovr_gap_base'] ?? 10.0);
        $gapMerit = min((float)($mp['max_ovr_gap_cap'] ?? 8.0), (float)($mp['max_ovr_gap_merit'] ?? 5.5) * (float)$profile['merit']);
        return (float)$profile['technical'] + $gapBase + $gapMerit + min(4.0, (float)$profile['growth'] * 1.5) + 2.0 * (float)$profile['potential'];
    }

    private static function deterministicShuffle(array &$items, ?callable $rng): void {
        $n = count($items);
        if ($n <= 1) {
            return;
        }
        $rand = $rng ?? static fn(int $min, int $max): int => random_int($min, $max);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = $rand(0, $i);
            if ($j !== $i) {
                [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
            }
        }
    }

    /**
     * Constrói o universo de clubes elegíveis preservando continuidade, compatibilidade de overall
     * e alcance geográfico (nacional, continental e intercontinental global sem restringir à Europa).
     */
    public static function pool(array $g, bool $limited = true, ?callable $rng = null): array {
        $p = self::profile($g);
        $members = $g['next_memberships'] ?? $g['memberships'] ?? [];
        $origin = self::country((string)$g['club']);
        $originRegion = FootballGeography::region($origin);
        $current = (float)($g['next_club_strengths'][$g['club']] ?? TeamStrength::rating($g['club'], $g));
        $preferred = 8.0 + max(0, ((int)$g['season'] - 2)) * min(2.0, $p['merit']) + ($p['merit'] >= 0.90 ? 6.0 : 0.0);
        $capacity = self::compatibleOverallCeiling($p);

        $valid = [];
        $clubIds = [];
        foreach ($members as $id => $teams) {
            $valid[$id] = CompetitionContinuity::status($id, $members)['valid']
                && CompetitionContinuity::status($id, $g['memberships'] ?? $members)['valid'];
            foreach ($teams as $club) {
                $clubIds[$club][] = $id;
            }
        }

        $allValidCatalog = [];
        $groups = ['country' => [], 'region' => [], 'world' => []];
        $catalog = Competition::catalog();

        foreach (Competition::clubs() as $club => $data) {
            if ($club === $g['club'] || !empty($data['reserve']) || empty($clubIds[$club])) {
                continue;
            }
            $ids = $clubIds[$club];
            if (array_filter($ids, static fn($id) => empty($valid[$id]))) {
                continue;
            }
            $id = LeagueStrength::primaryCompetition($ids, $catalog) ?? $ids[0];
            $leagueInfo = LeagueStrength::resolveCompetition($id, $g, $catalog);

            $country = self::country($club);
            $confed = FootballGeography::region($country);
            $foreign = ($country !== $origin);
            $bucket = !$foreign ? 'country' : ($confed === $originRegion ? 'region' : 'world');

            $ovr = (float)($g['next_club_strengths'][$club] ?? TeamStrength::initial($club));
            $delta = $ovr - $current;
            $weight = max(3, (int)round(14 + 22 * $p['merit'] - max(0.0, $delta - $preferred) * 2.0 - max(0.0, -$delta - 10.0)));

            $entry = [
                'club' => $club,
                'tier' => $catalog[$id]['tier'],
                'peso' => $weight,
                'country' => $country,
                'confederation' => $confed,
                'competition' => $id,
                'competition_name' => $leagueInfo['name'],
                'division_scope' => $leagueInfo['scope_label'],
                'league_strength' => $leagueInfo['strength'],
                'league_strength_status' => $leagueInfo['status'],
                'all_competitions' => $ids,
                'sporting_level' => self::sportingLevel($id),
                'overall' => round($ovr, 1),
                'overall_delta' => round($delta, 1),
                'international' => $foreign,
                'region' => $bucket,
                'city' => $data['municipality'] ?? $data['city'] ?? null,
                'reason' => 'A proposta considera o desempenho da última temporada, a compatibilidade esportiva e o espaço projetado no elenco.',
                'preferred' => abs($delta) <= ($delta > 0 ? $preferred : 10.0),
            ];
            $allValidCatalog[] = $entry;

            if ($ovr > $capacity) {
                continue;
            }
            if ($bucket === 'region' && !$p['continental_open']) {
                continue;
            }
            if ($bucket === 'world' && !$p['world_open']) {
                continue;
            }
            $groups[$bucket][] = $entry;
        }

        // Preferência geográfica (não limite obrigatório): se o recorte geográfico inicial tiver < 6 clubes compatíveis,
        // amplia os horizontes continental/intercontinental preservando estritamente o teto obrigatório de overall ($capacity).
        $totalCompatible = count($groups['country']) + count($groups['region']) + count($groups['world']);
        if ($totalCompatible < 6 && count($allValidCatalog) > $totalCompatible) {
            $groups = ['country' => [], 'region' => [], 'world' => []];
            foreach ($allValidCatalog as $entry) {
                if ($entry['overall'] <= $capacity) {
                    $groups[$entry['region']][] = $entry;
                }
            }
        }

        if (!$limited) {
            return array_merge($groups['country'], $groups['region'], $groups['world']);
        }

        // Seleção diversificada de referência (6/2 até a T3; 3/3/2 a partir da T4, cobrindo múltiplas confederações)
        $targets = ((int)$g['season'] <= 3)
            ? ['country' => 6, 'region' => 2, 'world' => 0]
            : ['country' => 3, 'region' => 3, 'world' => 2];
        $pool = [];
        foreach ($groups as &$list) {
            self::deterministicShuffle($list, $rng);
            usort($list, static fn($a, $b) => $b['preferred'] <=> $a['preferred']);
        }
        unset($list);

        foreach ($targets as $bucket => $n) {
            $seenCountries = [];
            $seenConfeds = [];
            for ($i = 0; $i < $n && $groups[$bucket]; $i++) {
                $index = 0;
                if ($bucket === 'world') {
                    foreach ($groups[$bucket] as $j => $cand) {
                        if (!isset($seenConfeds[$cand['confederation']]) && !isset($seenCountries[$cand['country']])) {
                            $index = $j;
                            break;
                        }
                    }
                } elseif ($bucket === 'region') {
                    foreach ($groups[$bucket] as $j => $cand) {
                        if (!isset($seenCountries[$cand['country']])) {
                            $index = $j;
                            break;
                        }
                    }
                }
                $candidate = $groups[$bucket][$index];
                $seenCountries[$candidate['country']] = true;
                $seenConfeds[$candidate['confederation']] = true;
                $pool[] = $candidate;
                array_splice($groups[$bucket], $index, 1);
            }
        }
        foreach (['region', 'country', 'world'] as $bucket) {
            while (count($pool) < 8 && $groups[$bucket]) {
                $pool[] = array_shift($groups[$bucket]);
            }
        }
        return $pool;
    }

    public static function distribution(array $g, array $pool): array {
        $counts = ['country' => 0, 'region' => 0, 'world' => 0];
        $confederations = [];
        $countries = [];
        foreach ($pool as $o) {
            $r = $o['region'] ?? 'country';
            if (isset($counts[$r])) {
                $counts[$r]++;
            }
            $c = $o['country'] ?? self::country($o['club']);
            $countries[$c] = ($countries[$c] ?? 0) + 1;
            $conf = $o['confederation'] ?? FootballGeography::region($c);
            $confederations[$conf] = ($confederations[$conf] ?? 0) + 1;
        }
        $target = ((int)$g['season'] <= 3)
            ? ['country' => 6, 'region' => 2, 'world' => 0]
            : ['country' => 3, 'region' => 3, 'world' => 2];
        return [
            'target' => $target,
            'actual' => $counts,
            'countries' => $countries,
            'confederations' => $confederations,
            'limited' => $counts !== $target,
            'policy' => 'confederation-global-v2',
        ];
    }

    public static function render(array $g): void {
        if (($g['market_draw']['schema'] ?? 1) >= 2) {
            TransferInterest::render($g);
            return;
        }
        $chosen = current(array_filter($g['offers'], static fn($o) => !empty($o['selected'])));
        if ($chosen) {
            Wheel::render($g['offers'], $chosen['club'], 'offer:' . $g['id'] . ':' . $g['season']);
            self::describe($chosen);
        } else {
            echo '<p>Nenhuma proposta se concretizou. Seu próximo capítulo pode continuar no clube atual.</p>';
        }
        if (!empty($g['market_draw']['distribution']['limited'])) {
            echo '<p class="subtle">A cobertura ou elegibilidade não permitiu a distribuição geográfica prevista. Usamos os destinos válidos disponíveis.</p>';
        }
        echo '<div class="transfer-actions">';
        form('/transferencia/responder', $g);
        if ($chosen) {
            echo '<button class="btn btn-primary" name="accept" value="yes">Aceitar proposta</button>';
        }
        echo '<button class="btn ' . ($chosen ? 'btn-outline-light' : 'btn-primary') . '" name="accept" value="no">Permanecer no meu clube</button></form></div>';
        if ($g['offers']) {
            echo '<details><summary>Destinos da roleta e níveis esportivos</summary>';
            foreach ($g['offers'] as $i => $offer) {
                echo '<article><h3>Posição ' . sprintf('%02d', $i + 1) . ' na roleta · ' . e(ClubBadge::name($offer['club'])) . '</h3>';
                self::describe($offer);
                echo '</article>';
            }
            echo '</details>';
        }
    }

    public static function describe(array $offer, bool $showBadge = true): void {
        $country = $offer['country'] ?? self::country($offer['club']);
        $scopeLabel = match ($offer['region'] ?? null) {
            'country' => 'Mercado nacional',
            'region' => 'Continental',
            'world' => 'Intercontinental · ' . ($offer['confederation'] ?? FootballGeography::region($country)),
            default => null,
        };
        echo '<p class="offer-destination">';
        if ($showBadge) {
            ClubBadge::render($offer['club']);
            echo ' ';
        }
        echo '<strong>' . e(EntryCountries::name($country)) . '</strong>';
        if (!empty($offer['city'])) {
            echo ' · ' . e($offer['city']);
        }
        if ($scopeLabel !== null) {
            echo ' <span class="offer-scope-pill">' . e($scopeLabel) . '</span>';
        }
        echo '</p>';
        $assessment = is_array($offer['sporting_assessment'] ?? null) ? $offer['sporting_assessment'] : [];
        $compId = (string)($offer['competition'] ?? ($assessment['target_competition'] ?? ''));
        $compName = $offer['competition_name'] ?? ($assessment['target_league_name'] ?? null);
        $divScope = $offer['division_scope'] ?? null;
        if (($compName === null || $divScope === null) && $compId !== '') {
            $catalogComp = Competition::catalog()[$compId] ?? null;
            if ($compName === null && is_array($catalogComp)) {
                $compName = $catalogComp['name'] ?? $compId;
            }
            if ($divScope === null && is_array($catalogComp)) {
                $divScope = LeagueStrength::competitionMeta($compId, $catalogComp)['scope_label'] ?? null;
            }
        }
        if ($compName !== null && $compName !== '') {
            echo '<p class="offer-competition-line">' . e((string)$compName);
            if (!empty($divScope)) {
                echo ' · ' . e((string)$divScope);
            }
            echo '</p>';
        }
        if (isset($offer['overall'])) {
            $ovrDelta = (float)($offer['overall_delta'] ?? 0);
            $ovrSign = $ovrDelta >= 0 ? '+' : '';
            echo '<p class="offer-overall-line"><strong>Overall ' . e($offer['overall']) . '</strong> · diferença ' . e($ovrSign . number_format($ovrDelta, 1, ',', '.')) . ' em relação ao seu clube</p>';
        }
        $hasLeagueInfo = array_key_exists('league_strength', $offer)
            || !empty($offer['league_strength_status'])
            || array_key_exists('target_league_strength', $assessment)
            || !empty($assessment['target_league_status']);
        if ($hasLeagueInfo) {
            $lStr = $offer['league_strength'] ?? ($assessment['target_league_strength'] ?? null);
            $lStatus = (string)($offer['league_strength_status'] ?? ($assessment['target_league_status'] ?? ''));
            $lDelta = $offer['league_delta'] ?? ($assessment['league_delta'] ?? null);
            if ($lStr !== null) {
                $statusTag = $lStatus === LeagueStrength::STATUS_PROVISIONAL ? ' (referência provisória)' : ($lStatus === LeagueStrength::STATUS_CONFIRMED ? ' (confirmada)' : '');
                echo '<p class="offer-league-line">Força da liga: <strong>' . e(number_format((float)$lStr, 1, ',', '.')) . '</strong>' . e($statusTag);
                if ($lDelta !== null) {
                    $lSign = ((float)$lDelta) >= 0 ? '+' : '';
                    echo ' · diferença ' . e($lSign . number_format((float)$lDelta, 1, ',', '.')) . ' frente à sua liga';
                }
                echo '</p>';
            } elseif ($lStatus === LeagueStrength::STATUS_UNAVAILABLE) {
                echo '<p class="offer-league-line">Força da liga: <strong>indisponível</strong> · classificação conservadora</p>';
            }
        }
        $reason = $offer['reason'] ?? ($assessment['reason'] ?? null);
        if (!empty($reason)) {
            echo '<p class="subtle offer-reason-line">' . e((string)$reason) . '</p>';
        }
    }
}
