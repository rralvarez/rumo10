<?php
declare(strict_types=1);
/** Explicit neutral fixture only. All ordinary matches use the unchanged established formula. */
final class MatchVenue {
    public static function expectations(array $m):array {
        if(empty($m['neutral']))return TeamStrength::expectations($m['home_overall'],$m['away_overall']);
        if(config('APP_ENV')==='production'||config('RUMO10_WORLD_FIXTURES','0')!=='1'||config('RUMO10_NEUTRAL_SIMULATION','0')!=='1')throw new RuntimeException('Campo neutro requer política simulada habilitada apenas na fixture local.');
        $forward=TeamStrength::expectations($m['home_overall'],$m['away_overall']);$reverse=TeamStrength::expectations($m['away_overall'],$m['home_overall']);
        // Average the established home/away baselines, with mirrored strengths and existing caps.
        return [($forward[0]+$reverse[1])/2,($forward[1]+$reverse[0])/2];
    }
}
