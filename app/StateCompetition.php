<?php
declare(strict_types=1);
/** Calendários estaduais declarados no catálogo; partidas e classificações são simuladas. */
final class StateCompetition {
    public static function create(array $c,array $teams): array {
        $groups=$teams===$c['teams']?$c['groups']:[$teams];$lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'state','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[]];
        $r=$c['rules'];
        if(($r['mode']??'')==='direct_final') {
            $t['initial_ranking']=$teams;Competition::knockout($t,[$teams],'final',2);return $t;
        }
        if(!empty($r['cross']) && count($groups)===2 && count($groups[0])===count($groups[1])) {
            $n=count($groups[0]);
            for($round=0;$round<$n;$round++)for($i=0;$i<$n;$i++) {
                $pair=[$groups[0][$i],$groups[1][($i+$round)%$n]];
                if(($round+$i)%2)$pair=array_reverse($pair);
                $t['matches'][]=Schedule::match($round+1,...$pair)+['phase'=>'liga','group'=>'cruzado'];
            }
            $t['phase_end']=$n;
        } else Competition::leaguePhase($t,$groups,'liga',$r['group_returns']??$r['return']);
        return $t;
    }
    private static function groupRanks(array $t): array {
        return array_map(fn($group)=>Competition::rank($t,$group,'liga'),$t['initial_groups']);
    }
    private static function qualified(array $t,array $r): array {
        if(count($t['initial_groups'])===1)return array_slice($t['initial_ranking'],0,$r['qualify']);
        $qualified=[];$thirds=[];
        foreach(self::groupRanks($t) as $i=>$ranking) {
            $n=is_array($r['per_group'])?$r['per_group'][$i]:$r['per_group'];
            $qualified=array_merge($qualified,array_slice($ranking,0,$n));
            if(isset($ranking[$n]))$thirds[]=$ranking[$n];
        }
        if(!empty($r['best_thirds'])) {
            $rows=array_column(Competition::standings($t,null,'liga'),null,'team');
            // Chaves com tamanhos diferentes: comparar aproveitamento, não pontos brutos.
            usort($thirds,function($a,$b)use($rows,$t){
                foreach(['points','wins','gd','gf'] as $key) {
                    $cmp=($rows[$b][$key]/max(1,$rows[$b]['played']))<=>($rows[$a][$key]/max(1,$rows[$a]['played']));if($cmp)return $cmp;
                }return $t['lots'][$a]<=>$t['lots'][$b];
            });$qualified=array_merge($qualified,array_slice($thirds,0,$r['best_thirds']));
        }
        return $qualified;
    }
    private static function finishLeague(array &$t,array $ranking,array $r): void {
        $t['champion']=$ranking[0];$t['promoted']=array_slice($ranking,0,$r['access']);$t['done']=true;
    }
    public static function next(array &$t): void {
        $r=Competition::catalog()[$t['id']]['rules'];$mode=$r['mode']??'knockout';$phase=$t['phase'];$rule=$r['tie_rule']??'penalties';
        if($phase==='liga') {
            $rank=Competition::rank($t,$t['teams'],'liga');$t['initial_ranking']=$rank;
            if($mode==='turn_winners') {
                $split=$t['phase_end']/2;$a=$t;$b=$t;
                $a['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['round']<=$split));
                $b['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['round']>$split));
                $winners=[Competition::rank($a,$t['teams'],'liga')[0],Competition::rank($b,$t['teams'],'liga')[0]];
                if($winners[0]===$winners[1]){self::finishLeague($t,array_merge([$winners[0]],array_values(array_diff($rank,[$winners[0]]))),$r);return;}
                $t['promoted']=$winners;Competition::knockout($t,[$winners],'final',$r['final_legs']);return;
            }
            if($r['qualify']===0 && count($t['initial_groups'])===1){self::finishLeague($t,$rank,$r);return;}
            $q=self::qualified($t,$r);$groups=self::groupRanks($t);
            if($mode==='final_league'){Competition::leaguePhase($t,[$q],'hexagonal',false);return;}
            if($mode==='group_playoff') {
                $t['direct_semis']=[$groups[0][0],$groups[1][0]];
                Competition::knockout($t,[[$groups[0][1],$groups[1][2]],[$groups[1][1],$groups[0][2]]],'playoff',$r['playoff_legs']);return;
            }
            if($mode==='playoff') {
                $t['direct_semis']=array_slice($rank,0,2);Competition::knockout($t,[[$rank[2],$rank[5]],[$rank[3],$rank[4]]],'playoff',$r['playoff_legs']);return;
            }
            $pairs=[];
            if(!empty($r['same_group_pairs'])) {
                foreach($groups as $group)$pairs=array_merge($pairs,Competition::seededPairs(array_slice($group,0,$r['per_group'])));
            } elseif(count($groups)===2 && !is_array($r['per_group']??null)) {
                $n=$r['per_group']??0;
                for($i=0;$i<$n;$i++)$pairs[]=$i<$n/2?[$groups[0][$i],$groups[1][$n-1-$i]]:[$groups[1][$n-1-$i],$groups[0][$i]];
            } elseif($mode==='pe_first' || $t['id']==='pa-entrada') {
                $n=$r['per_group'];for($g=0;$g<count($groups);$g+=2)for($i=0;$i<$n;$i++)$pairs[]=[$groups[$g][$i],$groups[$g+1][$n-1-$i]];
            } elseif($t['id']==='ma-entrada')$pairs=[[$groups[0][0],$groups[2][1]],[$groups[0][1],$groups[1][1]],[$groups[2][0],$groups[0][2]],[$groups[1][0],$groups[0][3]]];
            else $pairs=Competition::seededPairs(Competition::rank($t,$q));
            $next=match(count($q)){16=>'oitavas',8=>'quartas',4=>'semifinal',2=>'final',default=>throw new LogicException('Número de classificados inválido.')};
            Competition::knockout($t,$pairs,$next,$next==='final'?$r['final_legs']:$r['legs'],$rule);
            if($t['id']==='rj-entrada') {
                $others=array_values(array_diff($t['teams'],$q));$x=Competition::seededPairs(Competition::rank($t,$others));
                foreach($x as [$a,$b]) {
                    $i=count($t['ties']);$t['ties'][]=['a'=>$a,'b'=>$b,'winner'=>null,'rule'=>'advantage','group_x'=>true];
                    foreach([[$t['phase_start'],$b,$a],[$t['phase_end'],$a,$b]] as [$round,$home,$away])$t['matches'][]=Schedule::match($round,$home,$away)+['phase'=>$next,'group'=>'X','tie'=>$i];
                }usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
            }return;
        }
        if(in_array($phase,['hexagonal','quadrangular'],true)) {self::finishLeague($t,Competition::rank($t,$t['groups'][0],$phase),$r);return;}
        $winners=Competition::resolve($t);
        if($t['id']==='rj-entrada' && $phase==='quartas') {
            $winners=[];$t['preliminary_next']=[];
            foreach($t['ties'] as $tie) {
                if(!empty($tie['group_x']))$t['preliminary_next'][]=$tie['winner']===$tie['a']?$tie['b']:$tie['a'];
                else $winners[]=$tie['winner'];
            }
        }
        if($phase==='final') {
            $t['champion']=$winners[0];$t['done']=true;
            if($r['access']===1)$t['promoted']=$winners;
            elseif($mode==='direct_final')$t['promoted']=$t['teams'];return;
        }
        if($phase==='playoff') {
            [$a,$b]=$t['direct_semis'];Competition::knockout($t,[[$a,$winners[1]],[$b,$winners[0]]],'semifinal',$r['legs'],$rule);return;
        }
        if($mode==='pe_first' && $phase==='quartas') {Competition::leaguePhase($t,[$winners],'quadrangular',false);return;}
        if(count($winners)===$r['access'])$t['promoted']=$winners;
        $next=match($phase){'oitavas'=>'quartas','quartas'=>'semifinal','semifinal'=>'final',default=>throw new LogicException('Fase estadual inválida.')};
        Competition::knockout($t,Competition::seededPairs(Competition::rank($t,$winners)),$next,$next==='final'?$r['final_legs']:$r['legs'],$next==='final'?'penalties':$rule);
    }
}
