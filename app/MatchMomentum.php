<?php
declare(strict_types=1);
final class MatchMomentum {
    public const SITUATION=1300;
    public const CONTINUATION=1301;
    public static function valid(array $m):bool {
        $p=$m['possession']??null;
        return $p && $p['minute']===$m['minute'] && $p['score']===[$m['hg'],$m['ag']] && $m['status']==='em_andamento' && $m['minute']>=$m['entry'] && $m['minute']<$m['exit'];
    }
    public static function builtActions(array $g):int {
        if($g['stage']!=='partida')return 0;$m=$g['matches'][Game::currentIndex($g)];
        return self::valid($m)?(int)$m['possession']['actions']:0;
    }
    public static function available(array $g): bool {
        if($g['stage']!=='partida')return false;
        $m=$g['matches'][Game::currentIndex($g)];
        $pending=!empty($m['clear_chance']) || (!empty($m['attack_sequence']) && self::valid($m));
        if(isset($m['possession'])&&!self::valid($m))return false;
        return $pending && $m['status']==='em_andamento' && $m['minute']>=$m['entry'] && $m['minute']<$m['exit'];
    }
    public static function situation(array $g): ?array {
        if(!self::available($g))return null;
        $m=$g['matches'][Game::currentIndex($g)];
        if(empty($m['clear_chance']))return ['id'=>self::CONTINUATION,'text'=>'Você mantém a posse após a boa jogada. Como continua este lance?','options'=>[
            ['text'=>'Combinar um passe curto','action'=>'passe','effects'=>[],'match_event'=>true,'success_text'=>'A combinação mantém a bola e abre espaço à frente.','failure_text'=>'O passe é interceptado e encerra a posse.'],
            ['text'=>'Avançar com a bola','action'=>'drible','effects'=>[],'match_event'=>true,'success_text'=>'Você supera a aproximação e ganha espaço para concluir.','failure_text'=>'O marcador tira a bola e encerra a jogada.'],
            ['text'=>'Finalizar agora','action'=>'chute','effects'=>[],'match_event'=>true,'success_text'=>'Você aproveita o espaço e marca.','failure_text'=>'A finalização não entra.'],
            ['text'=>'Recuar e reorganizar','action'=>'passe','effects'=>[],'match_event'=>true,'end_sequence'=>true,'success_text'=>'Você encontra o apoio atrás e encerra esta tentativa de avanço.','failure_text'=>'A devolução é cortada e a bola fica com o adversário.'],
        ]];
        $options=[
            ['text'=>'Finalizar no canto','action'=>'chute','difficulty'=>45,'chance_bonus'=>8,'success_text'=>'Você coloca a bola no canto, fora do alcance do goleiro.','failure_text'=>'O goleiro fecha o canto e impede sua finalização.'],
            ['text'=>'Bater de primeira','action'=>'chute','difficulty'=>55,'chance_bonus'=>8,'success_text'=>'Você acerta o chute de primeira e vence o goleiro.','failure_text'=>'Você pega mal na bola e o chute de primeira sai para fora.'],
            ['text'=>'Tocar para o companheiro livre','action'=>'passe','difficulty'=>35,'chance_bonus'=>18,'success_text'=>'Seu passe encontra o companheiro livre, que finaliza para o gol.','failure_text'=>'O defensor chega antes do companheiro e corta seu passe.'],
            ['text'=>'Driblar o goleiro','action'=>'drible','difficulty'=>65,'chance_bonus'=>8,'success_text'=>'Você passa pelo goleiro e toca para o gol vazio.','failure_text'=>'O goleiro acompanha seu movimento e tira a bola antes do drible.'],
        ];
        foreach($options as &$o){$o['effects']=[];$o['clear_chance']=true;}unset($o);
        return ['id'=>self::SITUATION,'clear_chance'=>true,'text'=>'Chance clara! Duas boas jogadas abriram a defesa. Você recebe na área, com o goleiro à frente e um companheiro livre.','options'=>$options];
    }
    public static function record(array &$m,bool $success,bool $constructive,bool $scored): bool {
        if(!empty($m['clear_chance']) || !$success || !$constructive || $scored){self::reset($m);return false;}
        if(!self::valid($m))self::reset($m);
        $m['attack_sequence']=min(2,($m['attack_sequence']??0)+1);
        $m['possession']=['minute'=>$m['minute'],'score'=>[$m['hg'],$m['ag']],'actions'=>$m['attack_sequence']];
        if($m['attack_sequence']===2)$m['clear_chance']=true;
        return true;
    }
    public static function reset(array &$m): void {unset($m['clear_chance'],$m['attack_sequence'],$m['possession']);}
}
