<?php
declare(strict_types=1);
final class Wheel {
    public static function segments(array $items,?string $selected=null): array {
        $sum=array_sum(array_column($items,'peso')); if($sum<=0) throw new InvalidArgumentException('Pesos inválidos.');
        $angle=0; $out=[]; $baseTier=min(array_column($items,'tier')?:[1]);
        foreach($items as $i=>$item) {
            if($item['peso']<=0) throw new InvalidArgumentException('Peso inválido.');
            $span=$item['peso']/$sum*360; $name=$item['club']??$item['nome'];
            $out[]=['number'=>$i+1,'name'=>$name,'start'=>$angle,'span'=>$span,'mid'=>$angle+$span/2,'selected'=>$name===$selected,'better'=>isset($item['tier']) && $item['tier']>$baseTier];
            $angle+=$span;
        }
        return $out;
    }
    public static function render(array $items,?string $selected=null,?string $id=null,bool $interactive=false): void {
        $segments=self::segments($items,$selected); $winner=null;
        foreach($segments as $s) if($s['selected']) $winner=$s;
        if($selected!==null && !$winner) throw new InvalidArgumentException('Resultado não pertence à roleta.');
        if($interactive && $winner)throw new InvalidArgumentException('Roleta já sorteada não pode ter botão de giro.');
        $target=$winner?fmod(360-$winner['mid']+360,360):0;
        $palette=['#234d48','#2c3f59','#4b4058','#4d4938','#284a59','#493d43','#304e42','#3d465c'];
        echo '<section class="career-wheel" data-wheel-id="'.e($id??'idle').'" data-wheel-angle="'.e($target).'" data-wheel-selected="'.($winner?'1':'0').'">';
        echo '<div class="wheel-stage"><span class="wheel-pointer" aria-hidden="true"></span><div class="wheel-rim"><svg class="wheel-rotor" viewBox="0 0 360 360" role="img" aria-label="Roleta de carreira com setores proporcionais aos pesos"><defs><radialGradient id="wheel-light"><stop offset="0" stop-color="#ffffff" stop-opacity="0"/><stop offset=".65" stop-color="#ffffff" stop-opacity=".06"/><stop offset="1" stop-color="#000000" stop-opacity=".35"/></radialGradient></defs><g transform="rotate('.e($target).' 180 180)">';
        foreach($segments as $i=>$s) {
            $a=deg2rad($s['start']-90); $b=deg2rad($s['start']+$s['span']-90); $mid=deg2rad($s['mid']-90);
            $d=$s['span']>=359.999?'M180 16 A164 164 0 1 1 180 344 A164 164 0 1 1 180 16 Z':sprintf('M180 180 L%.4f %.4f A164 164 0 %d 1 %.4f %.4f Z',180+164*cos($a),180+164*sin($a),$s['span']>180?1:0,180+164*cos($b),180+164*sin($b));
            $color=$s['better']?'#49683c':$palette[$i%count($palette)];
            echo '<path d="'.e($d).'" fill="'.$color.'" stroke="#b2c9c3" stroke-opacity=".28" stroke-width="1.5"><title>'.e($s['name']).'</title></path>';
            echo '<text x="'.e(180+115*cos($mid)).'" y="'.e(180+115*sin($mid)).'" fill="#edf5ed" font-size="19" font-weight="700" text-anchor="middle" dominant-baseline="central">'.e(sprintf('%02d',$s['number'])).'</text>';
        }
        echo '<circle cx="180" cy="180" r="164" fill="url(#wheel-light)"/><circle cx="180" cy="180" r="161" fill="none" stroke="#ebf4de" stroke-opacity=".15"/></g></svg>';
        if($interactive)echo '<button class="wheel-hub wheel-trigger" type="submit" aria-label="Girar roleta e sortear meu perfil"><strong>GIRAR</strong><span>MEU PERFIL</span></button>';
        else echo '<div class="wheel-hub" aria-hidden="true"><strong>R10</strong><span>CARREIRA</span></div>';
        echo '</div></div>';
        if($interactive)echo '<p class="wheel-hint">Toque em <b>GIRAR</b>, no centro da roleta.</p>';
        else echo '<div class="wheel-outcome" aria-live="polite"><small class="wheel-status">'.($winner?'RESULTADO CONFIRMADO':'SEU PRÓXIMO CAPÍTULO').'</small><h3 class="wheel-winner">'.e($winner?$winner['name']:'Pronto para girar').'</h3><button class="wheel-skip" type="button" hidden>Pular animação</button></div>';
        echo '<details class="wheel-candidates"><summary>Ver opções da roleta</summary><ol class="wheel-legend">';
        foreach($segments as $s) echo '<li class="'.($s['selected']?'wheel-winning-item':'').'"><span>'.e(sprintf('%02d',$s['number'])).'</span><b>'.e($s['name']).'</b></li>';
        echo '</ol></details></section>';
    }
}
