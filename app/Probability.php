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
    public static function goalChance(float $chance,float $finishing,bool $clear=false,string $action='chute'): float {
        $cap=min($clear?88:80,25+.65*$finishing);
        return round(max(5,min($cap,$chance-12+.20*($finishing-50)-($action==='drible'?5:0))),2);
    }
    public static function roll(float $p,?callable $rng=null): array {
        if(!is_finite($p)||$p<0||$p>100)throw new InvalidArgumentException('Probabilidade fora da faixa.');
        $rng??=static fn(int $min,int $max):int=>random_int($min,$max);
        $roll=$rng(1,10000);if(!is_int($roll)||$roll<1||$roll>10000)throw new LogicException('RNG de teste inválido.');$success=$roll<=round($p*100);
        $intensity=$rng(1,100);if(!is_int($intensity)||$intensity<1||$intensity>100)throw new LogicException('RNG de teste inválido.');
        return ['chance'=>$p,'roll'=>$roll,'intensity_roll'=>$intensity,'success'=>$success,'class'=>$success?($intensity<=Rules::EXCEPTIONAL_WEIGHT?'excepcional':'sucesso'):($intensity<=Rules::SEVERE_WEIGHT?'falha_grave':'falha')];
    }
    public static function weighted(array $items,?callable $rng=null): array {
        $sum=array_sum(array_column($items,'peso')); if ($sum<1) throw new InvalidArgumentException('Pesos inválidos.');
        $rng??=static fn(int $min,int $max):int=>random_int($min,$max);$r=$rng(1,(int)$sum);if(!is_int($r)||$r<1||$r>$sum)throw new LogicException('RNG de teste inválido.'); foreach ($items as $item) { $r-=$item['peso']; if ($r<=0) return $item; } throw new LogicException('Sorteio inválido.');
    }
}
