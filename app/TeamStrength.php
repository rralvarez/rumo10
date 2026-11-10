<?php
declare(strict_types=1);
/** Notas de jogo, não um ranking oficial. Dados ausentes permanecem provisórios. */
final class TeamStrength {
    public static function baseline(string $club,array $members=[]): float {
        $levels=['br-a'=>84,'br-b'=>72,'br-c'=>60,'br-d'=>48,'sp-a1'=>64,'sp-a2'=>53,'sp-a3'=>45,'sp-a4'=>38,'sp-segunda'=>30,
            'ar-1'=>81,'ar-2'=>63,'uy-1'=>64,'uy-2'=>48,'py-1'=>65,'py-2'=>46,'bo-1'=>58,'bo-2'=>39];
        $ids=Competition::clubs()[$club]['competitions']??[];$base=50;
        if($members){$ids=[];foreach($members as $id=>$teams)if(in_array($club,$teams,true))$ids[]=$id;}
        if($ids){$base=0;foreach($ids as $id)$base=max($base,$levels[$id]??(Competition::catalog()[$id]['baseline']??($id==='rr-entrada'?48:32)));}
        return $base;
    }
    public static function results(): array {
        static $data;return $data??=array_merge(json_decode(file_get_contents(dirname(__DIR__).'/database/team-results-2025.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/europe-results-2025.json'),true,512,JSON_THROW_ON_ERROR));
    }
    public static function campaign(float $base,int $position,int $teams,int $points,int $games): float {
        $performance=.7*min(100,max(0,$points/max(1,3*$games)*100))+.3*(1-($position-1)/max(1,$teams-1))*100;
        return round(max(20,min(95,$base+.2*($performance-50))),1);
    }
    public static function initial(string $club): float {
        if(isset(Competition::clubs()[$club]['reference_overall']))return (float)Competition::clubs()[$club]['reference_overall'];
        $r=self::results()[$club]??null;
        return $r?self::campaign(self::baseline($club),(int)$r['position'],(int)$r['teams'],(int)$r['points'],(int)$r['games']):self::baseline($club);
    }
    /** @var list<array{cache: array<string, float>, snapshot: array, club_forms: array<string, string>, hits: int, misses: int}> */
    private static array $ratingCacheStack = [];

    public static function isRatingCacheActive(): bool {
        return !empty(self::$ratingCacheStack);
    }

    public static function openRatingCache(array $g = [], bool $immutable = false): void {
        $matches = $g['matches'] ?? [];
        $snap = self::buildSnapshot($matches);
        self::$ratingCacheStack[] = [
            'immutable' => $immutable,
            'cache' => [],
            'snapshot' => $snap,
            'matches_count' => count($matches),
            'clubs_in_matches' => self::buildClubsInMatches($matches),
            'club_forms' => self::buildFormsFromSnapshot($snap),
            'hits' => 0,
            'misses' => 0,
        ];
    }

    public static function closeRatingCache(array $g = []): void {
        if (empty(self::$ratingCacheStack)) return;
        $ctx = array_pop(self::$ratingCacheStack);
        if (($ctx['immutable'] ?? false) && !empty($g)) {
            $matches = $g['matches'] ?? [];
            if (!self::isSnapshotEqual($matches, $ctx['snapshot'])) {
                throw new \LogicException('Immutability contract violated in rating cache');
            }
        }
    }

    public static function clearRatingCache(): void {
        if (!empty(self::$ratingCacheStack)) {
            $idx = array_key_last(self::$ratingCacheStack);
            self::$ratingCacheStack[$idx]['cache'] = [];
            self::$ratingCacheStack[$idx]['snapshot'] = [];
            self::$ratingCacheStack[$idx]['matches_count'] = 0;
            self::$ratingCacheStack[$idx]['clubs_in_matches'] = [];
            self::$ratingCacheStack[$idx]['club_forms'] = [];
        }
    }

    public static function invalidateRating(?string $club = null): void {
        if (empty(self::$ratingCacheStack)) return;
        $idx = array_key_last(self::$ratingCacheStack);
        if ($club === null) {
            self::$ratingCacheStack[$idx]['cache'] = [];
        } else {
            $prefix = $club . '|';
            foreach (self::$ratingCacheStack[$idx]['cache'] as $k => $_) {
                if (str_starts_with($k, $prefix)) {
                    unset(self::$ratingCacheStack[$idx]['cache'][$k]);
                }
            }
        }
    }

    public static function ratingCacheMetrics(): array {
        if (empty(self::$ratingCacheStack)) return ['active' => false, 'depth' => 0, 'hits' => 0, 'misses' => 0, 'size' => 0];
        $top = self::$ratingCacheStack[array_key_last(self::$ratingCacheStack)];
        return [
            'active' => true,
            'depth' => count(self::$ratingCacheStack),
            'hits' => $top['hits'],
            'misses' => $top['misses'],
            'size' => count($top['cache']),
        ];
    }

    public static function withRatingCache(callable $fn, array $g = [], bool $immutable = false): mixed {
        self::openRatingCache($g, $immutable);
        try {
            return $fn();
        } finally {
            self::closeRatingCache($g);
        }
    }

    private static function buildSnapshot(array $matches): array {
        $snap = [];
        foreach ($matches as $m) {
            $snap[] = [
                (string)($m['home'] ?? ''),
                (string)($m['away'] ?? ''),
                (string)($m['status'] ?? ''),
                (int)($m['hg'] ?? 0),
                (int)($m['ag'] ?? 0),
            ];
        }
        return $snap;
    }

    private static function buildClubsInMatches(array $matches): array {
        $clubs = [];
        foreach ($matches as $m) {
            $h = $m['home'] ?? '';
            $a = $m['away'] ?? '';
            if ($h !== '') $clubs[$h] = true;
            if ($a !== '') $clubs[$a] = true;
        }
        return $clubs;
    }

    private static function buildFormsFromSnapshot(array $snap): array {
        $playedByClub = [];
        foreach ($snap as $m) {
            if ($m[2] === 'finalizada') {
                $h = $m[0];
                $a = $m[1];
                if ($h !== '') $playedByClub[$h][] = $m;
                if ($a !== '') $playedByClub[$a][] = $m;
            }
        }
        $forms = [];
        foreach ($playedByClub as $c => $played) {
            $last5 = array_slice($played, -5);
            $points = 0;
            foreach ($last5 as $m) {
                $delta = $m[0] === $c ? ($m[3] - $m[4]) : ($m[4] - $m[3]);
                $points += $delta > 0 ? 3 : ($delta === 0 ? 1 : 0);
            }
            $forms[$c] = count($last5) . ':' . $points;
        }
        return $forms;
    }

    private static function isSnapshotEqual(array $matches, array $snapshot): bool {
        if (count($matches) !== count($snapshot)) return false;
        foreach ($matches as $i => $m) {
            $s = $snapshot[$i];
            if (
                ($m['home'] ?? '') !== $s[0] ||
                ($m['away'] ?? '') !== $s[1] ||
                ($m['status'] ?? '') !== $s[2] ||
                ((int)($m['hg'] ?? 0)) !== $s[3] ||
                ((int)($m['ag'] ?? 0)) !== $s[4]
            ) {
                return false;
            }
        }
        return true;
    }

    private static function syncFormIndex(array &$ctx, array $g): void {
        $matches = $g['matches'] ?? [];
        if (!self::isSnapshotEqual($matches, $ctx['snapshot'])) {
            $ctx['snapshot'] = self::buildSnapshot($matches);
            $ctx['matches_count'] = count($matches);
            $ctx['clubs_in_matches'] = self::buildClubsInMatches($matches);
            $ctx['club_forms'] = self::buildFormsFromSnapshot($ctx['snapshot']);
            $ctx['cache'] = [];
        }
    }

    public static function rating(string $club,array $g=[]): float {
        $anchor = self::initial($club);
        if (!empty(self::$ratingCacheStack)) {
            $idx = array_key_last(self::$ratingCacheStack);
            $ctx = &self::$ratingCacheStack[$idx];
            if (!$ctx['immutable']) {
                self::syncFormIndex($ctx, $g);
            }
            $strength = $g['club_strengths'][$club] ?? 'null';
            $formKey = $ctx['club_forms'][$club] ?? '0:0';
            $key = $club . '|' . $anchor . '|' . $strength . '|' . $formKey;
            if (isset($ctx['cache'][$key])) {
                $ctx['hits']++;
                return $ctx['cache'][$key];
            }
            $ctx['misses']++;
            $val = self::calculateRawRating($club, $g);
            $ctx['cache'][$key] = $val;
            return $val;
        }
        return self::calculateRawRating($club, $g);
    }
    public static function calculateRawRating(string $club, array $g = []): float {
        $anchor = self::initial($club);
        $base=max($anchor-4,min($anchor+4,$g['club_strengths'][$club]??$anchor));

        $played=array_values(array_filter($g['matches']??[],fn($m)=>$m['status']==='finalizada' && in_array($club,[$m['home'],$m['away']],true)));
        $played=array_slice($played,-5);$points=0;
        foreach($played as $m){$delta=$m['home']===$club?$m['hg']-$m['ag']:$m['ag']-$m['hg'];$points+=$delta>0?3:($delta===0?1:0);}
        return round(max(20,min(95,$base+($played?6*($points/(3*count($played))-.5):0))),1);
    }
    public static function snapshot(array &$m,array $g=[]): void {
        $m['home_overall']??=self::rating($m['home'],$g);$m['away_overall']??=self::rating($m['away'],$g);
    }
    public static function expectations(float $home,float $away): array {
        $difference=max(-60,min(60,$home-$away));
        $p = class_exists('BalanceService') ? BalanceService::params('match.venue.expectations') : [];
        $hFactor = (float)($p['home_factor'] ?? 1.35);
        $aFactor = (float)($p['away_factor'] ?? 1.10);
        $div = (float)($p['exp_divisor'] ?? 35.0);
        $cMin = (float)($p['clamp_min'] ?? 0.15);
        $cMax = (float)($p['clamp_max'] ?? 4.00);
        return [max($cMin,min($cMax,$hFactor*exp($difference/$div))),max($cMin,min($cMax,$aFactor*exp(-$difference/$div)))];
    }
    public static function goals(float $mean): int {
        $limit=exp(-max(0,$mean));$product=1.;$count=0;
        do{$count++;$product*=random_int(1,1000000)/1000000;}while($product>$limit && $count<30);
        return $count-1;
    }
    public static function nextSeason(array &$g): void {
        $scores=[];
        foreach($g['competitions'] as $id=>$t){
            $rows=in_array($t['format'],['international','world'],true)?Schedule::table(array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],['liga','clausura','intermedio','regular'],true))),$t['teams']):Competition::standings($t,null,'liga');$size=count($rows);
            foreach($rows as $i=>$row){
                if(!$row['played'])continue;$club=$row['team'];
                $anchor=self::initial($club);$campaign=self::campaign($anchor,$i+1,$size,(int)$row['points'],(int)$row['played']);
                $previous=$g['club_strengths'][$club]??$anchor;$score=round(max($anchor-4,min($anchor+4,.8*$previous+.2*$campaign)),1);
                $scores[$club]=max($scores[$club]??20,$score);
            }
        }
        $g['next_club_strengths']=$scores;
    }
}
