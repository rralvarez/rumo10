<?php
declare(strict_types=1);
/** Curva de jogo, não modelo médico. Auge entre 27 e 32; progresso depende de XP. */
final class Evolution {
    public const PEAK=32;
    public static function ceiling(int $age,string $skill):int {
        if($age<=32)return min(100,65+(int)floor(max(0,$age-18)*35/9));
        $years=$age-32;
        return max(40,100-(in_array($skill,['velocidade','resistencia','forca'],true)?2*$years:(in_array($skill,['visao_jogo','posicionamento'],true)?intdiv($years,3):$years)));
    }
    public static function gain(array &$g,string $skill,int $xp):int {
        if(PlayerDevelopment::enabled($g))return PlayerDevelopment::gain($g,$skill,$xp);
        $s=&$g['skills'][$skill];$ceiling=self::ceiling($g['age'],$skill);
        // Legados acima do novo teto não são rebaixados retroativamente; também não ganham mais pontos acima dele.
        if($g['age']>=33 && in_array($skill,['velocidade','resistencia','forca'],true))return 0;
        if($s['value']>=$ceiling){$s['xp']=min($s['xp'],Rules::xpThreshold($s['value'])-1);return 0;}
        $rate=$g['age']<=23?1.4:($g['age']<=26?1.2:($g['age']<=32?1:($g['age']<=34?.8:.6)));
        $earned=max(0,(int)round($xp*$rate));
        $g['season_xp_earned'][$g['season']]=($g['season_xp_earned'][$g['season']]??0)+$earned;
        $s['xp']+=$earned;
        while($s['value']<$ceiling && $s['xp']>=Rules::xpThreshold($s['value'])){$s['xp']-=Rules::xpThreshold($s['value']);$s['value']++;}
        if($s['value']===$ceiling)$s['xp']=min($s['xp'],Rules::xpThreshold($s['value'])-1);
        MatchExperience::record($g,$skill,$earned);
        return $earned;
    }
    public static function birthday(array &$g):array {
        if(PlayerDevelopment::enabled($g))return PlayerDevelopment::birthday($g);
        $changes=[];if(($g['aging_applied_age']??0)>=$g['age'])return $changes;$g['aging_applied_age']=$g['age'];if($g['age']<=32)return $changes;
        foreach($g['skills'] as $key=>&$s) {
            $loss=in_array($key,['velocidade','resistencia','forca'],true)?($g['age']<=34?1:($g['age']<=37?2:3)):(in_array($key,['visao_jogo','posicionamento'],true)?0:($g['age']>=38?1:0));
            $before=$s['value'];$s['value']=max(0,$before-$loss);if($before!==$s['value'])$changes[$key]=$s['value']-$before;
        }unset($s);return $changes;
    }
}
