<?php
declare(strict_types=1);
final class Retirement {
    public const VOLUNTARY=35,MANDATORY=40;
    public static function eligible(array $g):bool {return $g['age']>=35 && $g['stage']!=='aposentado' && $g['stage']!=='partida';}
    public static function close(array &$g,string $reason):void {
        $g['retirement']=['reason'=>$reason,'age'=>$g['age'],'season'=>$g['season'],'club'=>$g['club'],'totals'=>CareerStats::collect($g)['totals']];
        $g['stage']='aposentado';$g['offers']=[];$g['feedback']=['text'=>$reason==='mandatory'?'Aos 40 anos, você encerrou sua carreira nos gramados.':'Você decidiu encerrar sua carreira nos gramados.'];
    }
    public static function render(array $g):void {
        echo '<article class="panel"><p class="tag">CARREIRA ENCERRADA</p><h1>'.e($g['name']).'</h1><p>'.e($g['age']).' anos · '.e($g['club']).'</p><p>'.e(($g['retirement']['reason']??'')==='mandatory'?'Você chegou ao limite de 40 anos. Sua história e suas conquistas permanecem salvas.':'Você se aposentou. Sua história e suas conquistas permanecem salvas.').'</p><a class="btn btn-primary" href="/carreira/nova">Começar uma nova história →</a> <a href="/jogador">Ver meu jogador</a></article>';CareerStats::render($g);
    }
    public static function confirmation(array $g):void {
        if($g['stage']==='aposentado'){self::render($g);return;}
        echo '<section class="panel"><h1>Encerrar minha carreira</h1><p>Você tem '.e($g['age']).' anos. A aposentadoria voluntária está disponível a partir dos 35. Seu histórico será preservado, mas este personagem não voltará a disputar partidas.</p>';
        if(!self::eligible($g)){echo '<p>Conclua a partida em andamento ou aguarde completar 35 anos.</p><a href="/carreira">Voltar</a></section>';return;}
        form('/carreira/aposentar',$g);echo '<label><input type="checkbox" name="confirm" value="retire" required> Quero encerrar definitivamente esta carreira.</label><button class="btn btn-primary">Confirmar aposentadoria</button></form><a href="/jogador">Continuar jogando</a></section>';
    }
}
