<?php
declare(strict_types=1);

/** Opt-in edition contracts. No inference from country, name, tier or legacy format. */
final class ConfiguredStateCompetition {
    public const VERSION='configured-state-v1';

    public static function validate(array $c,array $teams):void {
        $r=$c['state_engine']??[];
        if(($r['version']??null)!==self::VERSION||$teams!==($c['teams']??[])||count($teams)<2||count(array_unique($teams))!==count($teams))throw new RuntimeException('Contrato estadual configurável inválido.');
        $allowed=['points','wins','gd','gf','h2h_gd_two','red','yellow','lots'];
        if(empty($r['tiebreakers'])||count(array_unique($r['tiebreakers']))!==count($r['tiebreakers'])||array_diff($r['tiebreakers'],$allowed)||$r['tiebreakers'][0]!=='points'||end($r['tiebreakers'])!=='lots')throw new RuntimeException('Critérios de desempate incompletos.');
        $stages=$r['stages']??[];if(!$stages)throw new RuntimeException('Fases ausentes.');$names=[];
        foreach($stages as $i=>$stage){if(empty($stage['name'])||isset($names[$stage['name']])||!in_array($stage['type']??'', ['league','knockout'],true))throw new RuntimeException('Fase inválida/duplicada.');$names[$stage['name']]=true;
            if($stage['type']==='league'){
                if($i!==0||!is_bool($stage['return']??null))throw new RuntimeException('Liga combinada não suportada neste contrato.');$groups=$stage['groups']??[$teams];if(array_merge(...$groups)!==$teams||array_filter($groups,fn($g)=>count($g)<2))throw new RuntimeException('Grupos inválidos.');
            }else{if(!in_array($stage['legs']??0,[1,2],true)||!in_array($stage['tie_rule']??'', ['penalties','advantage'],true)||!in_array($stage['home_basis']??'', ['initial','campaign'],true)||!in_array($stage['selection']??'', ['initial','group_top','winners'],true)||!isset($stage['pairs']))throw new RuntimeException('Chaveamento não declarado.');}
        }
        if(!in_array($r['final_ranking']??'', ['campaign','elimination'],true)||!is_int($r['relegation']??null)||$r['relegation']<0||$r['relegation']>=count($teams))throw new RuntimeException('Classificação/descenso inválidos.');
    }

    public static function create(array $c,array $teams):array {
        self::validate($c,$teams);$lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'format'=>'configured_state','adapter_version'=>self::VERSION,'teams'=>$teams,'phase'=>'','groups'=>[],'initial_groups'=>[],'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'state_stage'=>0,'state_eliminated'=>[]];
        self::stage($t,$c);return $t;
    }

    public static function standings(array $t,?array $teams=null,?string $phase=null,bool $campaign=false):array {
        $teams??=$t['teams'];$rules=Competition::catalog()[$t['id']]['state_engine'];
        $matches=array_values(array_filter($t['matches'],fn($m)=>(!$phase||$m['phase']===$phase)&&($campaign||(in_array($m['home'],$teams,true)&&in_array($m['away'],$teams,true)))));
        $rows=Schedule::table($matches,$teams);foreach($rows as &$row){$row['red']=0;$row['yellow']=0;foreach($matches as $m)if($m['status']==='finalizada')foreach(['home','away'] as $side)if($m[$side]===$row['team']){$row['red']+=$m[$side.'_red']??0;$row['yellow']+=$m[$side.'_yellow']??0;}}unset($row);
        // Group ties by every preceding criterion. H2H is never pairwise for a three-way tie.
        $sort=function(array $bucket,int $at)use(&$sort,$rules,$matches,$t):array{
            if(count($bucket)<2||$at>=count($rules['tiebreakers']))return $bucket;$criterion=$rules['tiebreakers'][$at];
            if($criterion==='h2h_gd_two'){
                if(count($bucket)!==2)return $sort($bucket,$at+1);$gd=0;[$a,$b]=$bucket;
                foreach($matches as $m)if($m['status']==='finalizada'&&in_array($m['home'],[$a['team'],$b['team']],true)&&in_array($m['away'],[$a['team'],$b['team']],true))$gd+=($m['home']===$a['team']?1:-1)*($m['hg']-$m['ag']);
                return $gd?($gd>0?[$a,$b]:[$b,$a]):$sort($bucket,$at+1);
            }
            $groups=[];foreach($bucket as $row){$value=$criterion==='lots'?$t['lots'][$row['team']]:$row[$criterion];$groups[$value][]=$row;}
            if(in_array($criterion,['red','yellow','lots'],true))ksort($groups,SORT_NUMERIC);else krsort($groups,SORT_NUMERIC);$out=[];foreach($groups as $group)$out=array_merge($out,$sort($group,$at+1));return $out;
        };
        return $sort($rows,0);
    }

    private static function stage(array &$t,array $c):void {
        $s=$c['state_engine']['stages'][$t['state_stage']];
        if($s['type']==='league'){
            $groups=$s['groups']??[$t['teams']];$t['initial_groups']=$groups;
            if(isset($s['fixtures'])){
                $t['phase']=$s['name'];$t['groups']=$groups;$t['ties']=[];$seen=[];$busy=[];$pairs=[];
                foreach($s['fixtures'] as $f){[$round,$home,$away]=$f;if(!is_int($round)||$round<1||!in_array($home,$t['teams'],true)||!in_array($away,$t['teams'],true)||$home===$away||isset($seen[$round.'|'.$home.'|'.$away])||isset($busy[$round][$home])||isset($busy[$round][$away]))throw new RuntimeException('Tabela inicial inválida.');$seen[$round.'|'.$home.'|'.$away]=true;$busy[$round][$home]=$busy[$round][$away]=true;$pair=[$home,$away];sort($pair);$pairs[implode('|',$pair)][]=$home;$t['matches'][]=Schedule::match($round,$home,$away)+['phase'=>$s['name'],'group'=>'1'];}
                $n=count($t['teams']);if(count($groups)!==1||count($pairs)!==$n*($n-1)/2)throw new RuntimeException('Tabela inicial incompleta.');foreach($pairs as $homes)if(count($homes)!==($s['return']?2:1)||($s['return']&&count(array_unique($homes))!==2))throw new RuntimeException('Confronto ou mando duplicado.');
                $t['phase_end']=max(array_column($t['matches'],'round'));usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
            }else Competition::leaguePhase($t,$groups,$s['name'],$s['return']);
            return;
        }
        $pool=match($s['selection']){
            'initial'=>$t['initial_ranking']??$t['teams'],
            'winners'=>$t['state_winners']??throw new RuntimeException('Vencedores anteriores ausentes.'),
            'group_top'=>array_merge(...array_map(fn($g)=>array_slice(array_column(self::standings($t,$g,$c['state_engine']['stages'][0]['name']),'team'),0,$s['per_group']),$t['initial_groups']))
        };
        if($s['selection']==='winners')$pool=array_column(self::standings($t,$pool,$s['home_basis']==='initial'?$c['state_engine']['stages'][0]['name']:null,true),'team');
        $pairs=[];$used=[];foreach($s['pairs'] as [$a,$b]){if(!isset($pool[$a],$pool[$b])||$a===$b||isset($used[$pool[$a]])||isset($used[$pool[$b]]))throw new RuntimeException('Cruzamento inválido.');$used[$pool[$a]]=$used[$pool[$b]]=true;$pairs[]=[$pool[$a],$pool[$b]];}
        Competition::knockout($t,$pairs,$s['name'],$s['legs'],$s['tie_rule']);
    }

    public static function next(array &$t):void {
        $c=Competition::catalog()[$t['id']];self::validate($c,$t['teams']);if(($t['adapter_version']??null)!==self::VERSION)throw new RuntimeException('Versão do snapshot estadual inválida.');$r=$c['state_engine'];$s=$r['stages'][$t['state_stage']];
        if(array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Fase estadual incompleta.');
        if($s['type']==='league'){$t['initial_ranking']=array_column(self::standings($t,null,$s['name']),'team');$t['relegated']=$r['relegation']?array_slice($t['initial_ranking'],-$r['relegation']):[];}
        else{$w=Competition::resolve($t);$t['state_winners']=$w;$t['state_eliminated'][]=array_values(array_diff(array_merge(...$t['groups']),$w));}
        if(isset($r['stages'][$t['state_stage']+1])){$t['state_stage']++;self::stage($t,$c);return;}
        if($s['type']==='league')$ranking=$t['initial_ranking'];
        else{
            if(count($t['state_winners'])!==1)throw new RuntimeException('Campeão indefinido.');$champion=$t['state_winners'][0];$runner=array_values(array_diff($t['groups'][0],[$champion]));$prefix=array_merge([$champion],$runner);
            if($r['final_ranking']==='campaign')$ranking=array_merge($prefix,array_column(self::standings($t,array_values(array_diff($t['teams'],$prefix)),null,true),'team'));
            else{$ranking=$prefix;foreach(array_reverse($t['state_eliminated']) as $eliminated)$ranking=array_merge($ranking,array_column(self::standings($t,array_values(array_diff($eliminated,$ranking)),null,true),'team'));$ranking=array_merge($ranking,array_values(array_diff($t['initial_ranking']??$t['teams'],$ranking)));}
        }
        $t['final_ranking']=$ranking;$t['champion']=$ranking[0];$t['promoted']=array_slice($ranking,0,$r['promotion']??0);$t['done']=true;
    }
}
