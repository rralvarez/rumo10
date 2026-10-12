<?php
declare(strict_types=1);
final class Probability {
    public static function chance(float $a, float $c, float $e, float $r, float $d, float $o=50): float {
        foreach ([$a,$c,$e,$r,$d,$o] as $v) if ($v<0 || $v>100) throw new InvalidArgumentException('Fator fora da faixa.');
        return round(max(5,min(95,50+.65*($a-50)+.12*($c-50)+.10*($e-50)+.10*($r-50)-.55*($d-50)-.30*($o-50))),2);
    }
    public static function effective(array $skills, string $type): float {
        $a=0; foreach (Rules::WEIGHTS[$type] as $k=>$w) $a+=$skills[$k]['value']*$w; return $a;
    }
    public static function roll(float $p): array {
        $roll=random_int(1,10000); $success=$roll<=round($p*100);
        $intensity=random_int(1,100);
        return ['chance'=>$p,'roll'=>$roll,'intensity_roll'=>$intensity,'success'=>$success,'class'=>$success?($intensity<=Rules::EXCEPTIONAL_WEIGHT?'excepcional':'sucesso'):($intensity<=Rules::SEVERE_WEIGHT?'falha_grave':'falha')];
    }
    public static function weighted(array $items): array {
        $sum=array_sum(array_column($items,'peso')); if ($sum<1) throw new InvalidArgumentException('Pesos inválidos.');
        $r=random_int(1,(int)$sum); foreach ($items as $item) { $r-=$item['peso']; if ($r<=0) return $item; } throw new LogicException('Sorteio inválido.');
    }
}
