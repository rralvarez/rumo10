<?php
declare(strict_types=1);
final class Schedule {
    public static function generate(array $teams,bool $return=true): array {
        $n=count($teams);
        if($n<2 || $n%2 || count(array_unique($teams))!==$n) throw new InvalidArgumentException('São necessárias equipes distintas em número par.');
        $matches=[]; $ring=$teams;
        for($r=1;$r<$n;$r++) {
            for($i=0;$i<$n/2;$i++) {
                [$h,$a]=[$ring[$i],$ring[$n-1-$i]]; if($r%2===0) [$h,$a]=[$a,$h];
                $legs=[[$r,$h,$a]]; if($return) $legs[]=[$r+$n-1,$a,$h];
                foreach($legs as [$round,$home,$away]) $matches[]=self::match($round,$home,$away);
            }
            $last=array_pop($ring); array_splice($ring,1,0,[$last]);
        }
        usort($matches,fn($a,$b)=>$a['round']<=>$b['round']); return $matches;
    }
    public static function match(int $round,string $home,string $away): array {
        return ['round'=>$round,'home'=>$home,'away'=>$away,'hg'=>0,'ag'=>0,'minute'=>0,'status'=>'agendada','lineup'=>'','entry'=>0,'exit'=>0,'stats'=>self::stats(),'log'=>[]];
    }
    public static function stats(): array { return ['minutes'=>0,'goals'=>0,'assists'=>0,'passes'=>0,'completed'=>0,'shots'=>0,'tackles'=>0,'fouls'=>0,'cards'=>0,'rating'=>0]; }
    public static function table(array $matches, array $teams): array {
        $rows=[]; foreach($teams as $t) $rows[$t]=['team'=>$t,'played'=>0,'points'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'gf'=>0,'ga'=>0,'gd'=>0];
        foreach($matches as $m) if($m['status']==='finalizada') {
            foreach([[$m['home'],$m['hg'],$m['ag']],[$m['away'],$m['ag'],$m['hg']]] as [$t,$gf,$ga]) {
                if(!isset($rows[$t]))continue;
                $rows[$t]['played']++; $rows[$t]['gf']+=$gf; $rows[$t]['ga']+=$ga; $rows[$t]['gd']+=$gf-$ga;
                $key=$gf>$ga?'wins':($gf===$ga?'draws':'losses'); $rows[$t][$key]++; $rows[$t]['points']+=($gf>$ga?3:($gf===$ga?1:0));
            }
        }
        usort($rows,fn($a,$b)=>($b['points']<=>$a['points'])?:($b['wins']<=>$a['wins'])?:($b['gd']<=>$a['gd'])?:($b['gf']<=>$a['gf'])?:strcmp($a['team'],$b['team'])); return $rows;
    }
}
