<?php
declare(strict_types=1);
/** MLS 2026. Calendário simulado com as quantidades e chaveamento oficiais. */
final class ConcacafCompetition {
    public static function create(array $c,array $teams):array {
        $groups=$c['groups'];
        if(count($teams)!==30 || array_diff($teams,$c['teams']))throw new LogicException('MLS requer as 30 franquias cadastradas.');
        $lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'concacaf','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'titles'=>[]];
        Competition::leaguePhase($t,$groups,'liga',true);
        // Quinze clubes por conferência: rodízio inclui folgas. Seis adversários únicos da outra conferência.
        $offset=$t['phase_end'];
        for($r=0;$r<6;$r++)for($i=0;$i<15;$i++) {
            [$a,$b]=[$groups[0][$i],$groups[1][($i+$r)%15]];
            $t['matches'][]=Schedule::match($offset+$r+1,$r%2?$b:$a,$r%2?$a:$b)+['phase'=>'liga','group'=>'cross'];
        }
        $t['phase_end']=$offset+6;return $t;
    }
    private static function ordered(array $t,array $pair):array {
        usort($pair,fn($a,$b)=>array_search($a,$t['initial_ranking'],true)<=>array_search($b,$t['initial_ranking'],true));return $pair;
    }
    private static function seriesRound(array &$t,array $pairs,int $game):void {
        $start=$t['phase_end']+1;$t['phase_start']=$t['phase_end']=$start;$t['phase']='serie_'.$game;$t['groups']=$pairs;$t['ties']=[];
        foreach($pairs as $i=>[$better,$other])$t['matches'][]=Schedule::match($start,$game===2?$other:$better,$game===2?$better:$other)+['phase'=>$t['phase'],'group'=>(string)($i+1),'series'=>$i];
    }
    public static function next(array &$t):void {
        if($t['phase']==='liga') {
            $t['initial_ranking']=Competition::rank($t,$t['teams'],'liga');
            $t['titles']["Supporters' Shield"]=$t['initial_ranking'][0];
            $t['conference_seeds']=array_map(fn($group)=>array_column(Competition::standings($t,$group,'liga'),'team'),$t['initial_groups']);
            Competition::knockout($t,array_map(fn($r)=>[$r[7],$r[8]],$t['conference_seeds']),'wild_card',1);return;
        }
        if($t['phase']==='wild_card') {
            $w=Competition::resolve($t);$pairs=[];
            foreach($t['conference_seeds'] as $i=>$r)foreach([[$r[0],$w[$i]],[$r[3],$r[4]],[$r[2],$r[5]],[$r[1],$r[6]]] as $pair)$pairs[]=$pair;
            $t['series_pairs']=$pairs;$t['series_wins']=array_fill(0,8,[0,0]);$t['series_winners']=array_fill(0,8,null);
            self::seriesRound($t,$pairs,1);return;
        }
        if(str_starts_with($t['phase'],'serie_')) {
            $game=(int)substr($t['phase'],6);$undecided=[];
            foreach($t['matches'] as &$m)if($m['phase']===$t['phase']) {
                $i=$m['series'];$pair=$t['series_pairs'][$i];
                if($m['hg']===$m['ag']) {
                    $homeWins=random_int(0,1)===1;$loser=random_int(2,4);$m['penalties']=[$homeWins?$loser+1:$loser,$homeWins?$loser:$loser+1];
                    $winner=$homeWins?$m['home']:$m['away'];
                } else $winner=$m['hg']>$m['ag']?$m['home']:$m['away'];
                $m['series_winner']=$winner;$index=$winner===$pair[0]?0:1;$t['series_wins'][$i][$index]++;
                if($t['series_wins'][$i][$index]===2)$t['series_winners'][$i]=$winner;
                else if($game===2)$undecided[$i]=$pair;
            }unset($m);
            if($game===1){self::seriesRound($t,$t['series_pairs'],2);return;}
            if($game===2 && $undecided){self::seriesRound($t,$undecided,3);return;}
            if(in_array(null,$t['series_winners'],true))throw new LogicException('Série MLS incompleta.');
            $w=$t['series_winners'];$pairs=[];for($i=0;$i<8;$i+=2)$pairs[]=self::ordered($t,[$w[$i],$w[$i+1]]);
            Competition::knockout($t,$pairs,'semifinal_conferencia',1);return;
        }
        $w=Competition::resolve($t);
        if($t['phase']==='semifinal_conferencia') {
            Competition::knockout($t,[self::ordered($t,[$w[0],$w[1]]),self::ordered($t,[$w[2],$w[3]])],'final_conferencia',1);return;
        }
        if($t['phase']==='final_conferencia'){Competition::knockout($t,[self::ordered($t,$w)],'mls_cup',1);return;}
        $t['champion']=$w[0];$t['titles']['MLS Cup']=$w[0];$t['done']=true;
    }
}
