<?php
declare(strict_types=1);
/** Curva de jogo, não modelo médico. Auge potencial aos 32; progresso depende de XP. */
final class Evolution {
    public const PEAK=32;
    public static function ceiling(int $age,string $skill):int {
        if($age<=32)return min(100,65+(int)floor(max(0,$age-18)*35/14));
        $years=$age-32;
        return max(40,100-(in_array($skill,['velocidade','resistencia','forca'],true)?2*$years:(in_array($skill,['visao_jogo','posicionamento'],true)?intdiv($years,3):$years)));
    }
    public static function gain(array &$g,string $skill,int $xp):int {
        $s=&$g['skills'][$skill];$ceiling=self::ceiling($g['age'],$skill);
        // Legados acima do novo teto não são rebaixados retroativamente; também não ganham mais pontos acima dele.
        if($s['value']>=$ceiling){$s['xp']=min($s['xp'],Rules::xpThreshold($s['value'])-1);return 0;}
        $rate=$g['age']<=23?1.2:($g['age']<=28?1:($g['age']<=32?.8:($g['age']<=34?.6:.4)));
        $earned=max(0,(int)round($xp*$rate));
        if(PlayMode::name($g)==='normal')$earned*=2;
        $s['xp']+=$earned;
        while($s['value']<$ceiling && $s['xp']>=Rules::xpThreshold($s['value'])){$s['xp']-=Rules::xpThreshold($s['value']);$s['value']++;}
        if($s['value']===$ceiling)$s['xp']=min($s['xp'],Rules::xpThreshold($s['value'])-1);
        return $earned;
    }
    public static function birthday(array &$g):array {
        $changes=[];if($g['age']<=32)return $changes;
        foreach($g['skills'] as $key=>&$s) {
            $loss=in_array($key,['velocidade','resistencia','forca'],true)?2:(in_array($key,['visao_jogo','posicionamento'],true)?(($g['age']-32)%3===0?1:0):1);
            $before=$s['value'];$s['value']=max(0,$before-$loss);if($before!==$s['value'])$changes[$key]=$s['value']-$before;
        }unset($s);return $changes;
    }
}
