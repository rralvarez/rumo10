<?php
declare(strict_types=1);
/** Opt-in 2026 C contract. Does not change legacy C tournaments. Dates are simulated. */
final class NationalCCompetition {
    public static function result(array $t,string $club):array {
        if(empty($t['done'])||!in_array($club,$t['teams'],true))throw new RuntimeException('Resultado C sem conclusão/participação.');
        return ['name'=>Competition::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'promoted'=>false,'relegated'=>false,'promotion_merit'=>in_array($club,$t['promoted'],true),'access_pending'=>in_array($club,$t['promoted'],true),'relegation_pending'=>in_array($club,$t['relegated'],true),'continuity'=>CompetitionContinuity::status($t['id']),'titles'=>[]];
    }
    public static function create(array $c,array $teams):array {
        $n=count($teams);$future=$n===24&&($c['edition']??null)==='simulation-2'&&($c['rules']['derived_simulation']??null)==='c-2027-from-2026-v1';
        if(($n!==20&&!$future)||$teams!==$c['teams'])throw new RuntimeException('Série C: somente a edição-base comprovada está implementada.');
        $priority=$c['rules']['home_priority'];$order=array_merge($priority,array_values(array_diff($teams,$priority)));
        if(count($priority)!==$n/2||count(array_unique($order))!==$n)throw new RuntimeException('Prioridade de mando incompleta.');
        $indices=array_flip($order);$matches=[];
        foreach(Schedule::generate($order,false) as $m){$a=$indices[$m['home']];$b=$indices[$m['away']];if(($b-$a+$n+1)%($n+1)>$n/2)[$m['home'],$m['away']]=[$m['away'],$m['home']];$matches[]=$m+['phase'=>'liga','group'=>'1'];}
        $lots=$teams;shuffle($lots);
        return ['id'=>$c['id'],'teams'=>$teams,'format'=>'national_c','phase'=>'liga','groups'=>[$teams],'initial_groups'=>[$teams],'matches'=>$matches,'phase_start'=>1,'phase_end'=>$n-1,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[]];
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null):array {
        $teams??=$t['teams'];$matches=array_values(array_filter($t['matches'],fn($m)=>(!$phase||$m['phase']===$phase)&&in_array($m['home'],$teams,true)&&in_array($m['away'],$teams,true)));
        $rows=Schedule::table($matches,$teams);$counts=[];
        foreach($rows as &$r){$r['red']=0;$r['yellow']=0;foreach($matches as $m)if($m['status']==='finalizada')foreach(['home','away'] as $side)if($m[$side]===$r['team']){$r['red']+=$m[$side.'_red']??0;$r['yellow']+=$m[$side.'_yellow']??0;}$key=implode(':',[$r['points'],$r['wins'],$r['gd'],$r['gf']]);$counts[$key]=($counts[$key]??0)+1;}unset($r);
        usort($rows,function($a,$b)use($t,$phase,$matches,$counts){foreach(['points','wins','gd','gf'] as $f)if($cmp=$b[$f]<=>$a[$f])return $cmp;
            $key=implode(':',[$a['points'],$a['wins'],$a['gd'],$a['gf']]);if($phase==='quadrangular'&&$counts[$key]===2){$gd=0;foreach($matches as $m)if($m['status']==='finalizada'&&in_array($m['home'],[$a['team'],$b['team']],true)&&in_array($m['away'],[$a['team'],$b['team']],true))$gd+=($m['home']===$a['team']?1:-1)*($m['hg']-$m['ag']);if($gd)return -$gd;}
            return ($a['red']<=>$b['red'])?:($a['yellow']<=>$b['yellow'])?:($t['lots'][$a['team']]<=>$t['lots'][$b['team']]);});return $rows;
    }
    private static function cumulative(array $t,array $teams,bool $host=false):array {
        $rows=array_column(self::standings($t),null,'team');
        usort($teams,function($a,$b)use($rows,$t,$host){foreach($host?['points','wins','gd']:['points','wins','gd','gf'] as $f)if($cmp=$rows[$b][$f]<=>$rows[$a][$f])return $cmp;if(!$host){foreach(['red','yellow'] as $f)if($cmp=$rows[$a][$f]<=>$rows[$b][$f])return $cmp;}return $t['lots'][$a]<=>$t['lots'][$b];});return $teams;
    }
    public static function next(array &$t):void {
        if($t['phase']==='liga'){$r=array_column(self::standings($t,null,'liga'),'team');$t['initial_ranking']=$r;$t['relegated']=array_slice($r,-2);Competition::leaguePhase($t,[[$r[0],$r[3],$r[4],$r[7]],[$r[1],$r[2],$r[5],$r[6]]],'quadrangular');return;}
        if($t['phase']==='quadrangular'){$groups=[];foreach($t['groups'] as $group)$groups[]=array_column(self::standings($t,$group,'quadrangular'),'team');$t['promoted']=[$groups[0][0],$groups[0][1],$groups[1][0],$groups[1][1]];$t['classification_groups']=$groups;
            Competition::knockout($t,[self::cumulative($t,[$groups[0][0],$groups[1][0]],true)],'final');return;}
        if($t['phase']!=='final')throw new RuntimeException('Fase C desconhecida.');
        $winner=Competition::resolve($t)[0];$finalists=[$t['ties'][0]['a'],$t['ties'][0]['b']];$g=$t['classification_groups'];
        // Exclude final games from the cumulative comparisons for places 3–8.
        $before=$t;$before['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['phase']!=='final'));
        $t['final_ranking']=array_merge([$winner],array_values(array_diff($finalists,[$winner])),self::cumulative($before,[$g[0][1],$g[1][1]]),self::cumulative($before,[$g[0][2],$g[0][3],$g[1][2],$g[1][3]]),array_slice($t['initial_ranking'],8));
        $t['champion']=$winner;$t['done']=true;
    }
}
