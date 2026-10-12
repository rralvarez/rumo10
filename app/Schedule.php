<?php
declare(strict_types=1);
final class Schedule {
    public static function generate(array $teams): array {
        if(count($teams)!==8 || count(array_unique($teams))!==8) throw new InvalidArgumentException('São necessárias oito equipes distintas.');
        $matches=[]; $ring=$teams;
        for($r=1;$r<=7;$r++) {
            for($i=0;$i<4;$i++) {
                [$h,$a]=[$ring[$i],$ring[7-$i]]; if($r%2===0) [$h,$a]=[$a,$h];
                foreach([[$r,$h,$a],[$r+7,$a,$h]] as [$round,$home,$away]) $matches[]=['round'=>$round,'home'=>$home,'away'=>$away,'hg'=>0,'ag'=>0,'minute'=>0,'status'=>'agendada','lineup'=>'','entry'=>0,'exit'=>0,'stats'=>self::stats(),'log'=>[]];
            }
            $last=array_pop($ring); array_splice($ring,1,0,[$last]);
        }
        usort($matches,fn($a,$b)=>$a['round']<=>$b['round']); return $matches;
    }
    public static function stats(): array { return ['minutes'=>0,'goals'=>0,'assists'=>0,'passes'=>0,'completed'=>0,'shots'=>0,'tackles'=>0,'fouls'=>0,'cards'=>0,'rating'=>0]; }
    public static function table(array $matches, array $teams): array {
        $rows=[]; foreach($teams as $t) $rows[$t]=['team'=>$t,'played'=>0,'points'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'gf'=>0,'ga'=>0,'gd'=>0];
        foreach($matches as $m) if($m['status']==='finalizada') {
            foreach([[$m['home'],$m['hg'],$m['ag']],[$m['away'],$m['ag'],$m['hg']]] as [$t,$gf,$ga]) {
                $rows[$t]['played']++; $rows[$t]['gf']+=$gf; $rows[$t]['ga']+=$ga; $rows[$t]['gd']+=$gf-$ga;
                $key=$gf>$ga?'wins':($gf===$ga?'draws':'losses'); $rows[$t][$key]++; $rows[$t]['points']+=($gf>$ga?3:($gf===$ga?1:0));
            }
        }
        usort($rows,fn($a,$b)=>($b['points']<=>$a['points'])?:($b['wins']<=>$a['wins'])?:($b['gd']<=>$a['gd'])?:($b['gf']<=>$a['gf'])?:strcmp($a['team'],$b['team'])); return $rows;
    }
}
