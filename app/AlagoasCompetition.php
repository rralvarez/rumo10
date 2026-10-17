<?php
declare(strict_types=1);

/** Sports adapter for frozen FAF 2026 references; no historical result imports. */
final class AlagoasCompetition {
    public const VERSION='faf-al2026-trial-v1';
    public static function create(array $c,array $teams):array {
        if(($c['rules']['adapter_version']??null)!==self::VERSION)throw new RuntimeException('Versão esportiva de Alagoas não suportada.');
        if(!in_array($c['rules']['kind']??null,['a','b','cup'],true))throw new InvalidArgumentException('Tipo de competição de Alagoas inválido.');
        $groups=$c['rules']['kind']==='cup'?($teams===$c['teams']?$c['groups']:array_chunk($teams,6)):[$teams];
        if(array_merge(...$groups)!==$teams)throw new InvalidArgumentException('Grupos de Alagoas não correspondem às inscrições.');
        $lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'adapter_version'=>self::VERSION,'teams'=>$teams,'format'=>'alagoas','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[]];
        if($c['rules']['kind']==='b'&&!empty($c['rules']['initial_home_fixture'])){foreach($c['rules']['initial_home_fixture'] as $f)$t['matches'][]=Schedule::match($f['round'],$f['home'],$f['away'])+['phase'=>'liga','group'=>'1'];$t['phase_end']=5;}
        elseif($c['rules']['kind']==='b'){$rounds=[[[3,4],[0,5],[1,2]],[[5,3],[2,0],[4,1]],[[0,3],[2,4],[1,5]],[[3,1],[5,2],[0,4]],[[1,0],[4,5],[2,3]]];foreach($rounds as $round=>$pairs)foreach($pairs as [$h,$a])$t['matches'][]=Schedule::match($round+1,$teams[$h],$teams[$a])+['phase'=>'liga','group'=>'1'];$t['phase_end']=5;}
        else Competition::leaguePhase($t,$groups,'liga',false);return $t;
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null,bool $campaign=false):array {
        $teams??=$t['teams'];$matches=array_values(array_filter($t['matches'],fn($m)=>(!$phase||$m['phase']===$phase)&&($campaign||(in_array($m['home'],$teams,true)&&in_array($m['away'],$teams,true)))));
        $rows=Schedule::table($matches,$teams);$counts=[];
        foreach($rows as &$row){$row['red']=0;$row['yellow']=0;foreach($matches as $m)if($m['status']==='finalizada')foreach(['home','away'] as $side)if($m[$side]===$row['team']){$row['red']+=$m[$side.'_red']??0;$row['yellow']+=$m[$side.'_yellow']??0;}$key=implode(':',[$row['points'],$row['wins'],$row['gd'],$row['gf']]);$counts[$key]=($counts[$key]??0)+1;}unset($row);
        usort($rows,function($a,$b)use($matches,$counts,$t){
            foreach(['points','wins','gd','gf'] as $k)if($cmp=$b[$k]<=>$a[$k])return $cmp;
            $key=implode(':',[$a['points'],$a['wins'],$a['gd'],$a['gf']]);
            if($counts[$key]===2){$gd=0;foreach($matches as $m)if($m['status']==='finalizada'&&in_array($m['home'],[$a['team'],$b['team']],true)&&in_array($m['away'],[$a['team'],$b['team']],true))$gd+=($m['home']===$a['team']?1:-1)*($m['hg']-$m['ag']);if($gd)return -$gd;}
            return ($a['red']<=>$b['red'])?:($a['yellow']<=>$b['yellow'])?:($t['lots'][$a['team']]<=>$t['lots'][$b['team']]);
        });return $rows;
    }
    public static function next(array &$t):void {
        if(($t['adapter_version']??null)!==self::VERSION)throw new RuntimeException('Snapshot de Alagoas sem versão suportada.');
        $rules=Competition::catalog()[$t['id']]['rules'];$cup=$rules['kind']==='cup';
        if($t['phase']==='liga'){
            $t['initial_ranking']=array_column(self::standings($t,null,'liga'),'team');
            if($cup){$groups=array_map(fn($g)=>array_column(self::standings($t,$g,'liga'),'team'),$t['initial_groups']);$pairs=[[$groups[0][0],$groups[1][1]],[$groups[1][0],$groups[0][1]]];}
            else{$r=$t['initial_ranking'];$pairs=[[$r[0],$r[3]],[$r[1],$r[2]]];if($rules['kind']==='a')$t['relegated']=array_slice($r,-1);}
            Competition::knockout($t,$pairs,'semifinal',$cup?1:2);return;
        }
        $winners=Competition::resolve($t);
        if($t['phase']==='semifinal'){
            $t['semifinal_losers']=array_values(array_diff(array_merge(...$t['groups']),$winners));
            $winners=array_column(self::standings($t,$winners,null,true),'team');
            Competition::knockout($t,[$winners],'final',$cup?1:2);return;
        }
        if($t['phase']!=='final')throw new LogicException('Fase de Alagoas inválida.');
        $t['champion']=$winners[0];$t['done']=true;if($rules['kind']==='b')$t['promoted']=$winners;
        $runner=array_values(array_diff($t['groups'][0],$winners));$others=array_column(self::standings($t,$t['semifinal_losers'],null,true),'team');
        $t['final_ranking']=array_merge($winners,$runner,$others,array_values(array_diff($t['initial_ranking'],array_merge($winners,$runner,$others))));
    }
}
