<?php
declare(strict_types=1);
final class MatchMomentum {
    public const SITUATION=1300;
    public static function available(array $g): bool {
        if($g['stage']!=='partida')return false;
        $m=$g['matches'][Game::currentIndex($g)];
        return !empty($m['clear_chance']) && $m['status']==='em_andamento' && $m['minute']>=$m['entry'] && $m['minute']<$m['exit'];
    }
    public static function situation(array $g): ?array {
        if(!self::available($g))return null;
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
        $m['attack_sequence']=min(2,($m['attack_sequence']??0)+1);
        if($m['attack_sequence']<2)return false;
        $m['clear_chance']=true;$m['attack_sequence']=0;return true;
    }
    public static function reset(array &$m): void {unset($m['clear_chance'],$m['attack_sequence']);}
}
