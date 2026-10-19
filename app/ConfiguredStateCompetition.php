<?php
declare(strict_types=1);

/** Opt-in edition contracts. No inference from country, name, tier or legacy format. */
final class ConfiguredStateCompetition {
    public const VERSION='configured-state-v1';
    public const ROUTED_VERSION='configured-state-v2';
    public const ADMIN_VERSION='configured-state-v3';
    public const MARANHENSE_VERSION='configured-state-v6-ma';
    public const CONDITIONAL_VERSION='configured-state-v5';
    public const INTERGROUP_VERSION='configured-state-v4';

    public static function validate(array $c,array $teams):void {
        if(($c['state_engine']['version']??null)===self::MARANHENSE_VERSION){MaranhenseCompetition::validate($c,$teams);return;}
        if(($c['state_engine']['version']??null)===self::CONDITIONAL_VERSION){ConditionalStateCompetition::validate($c,$teams);return;}
        if(($c['state_engine']['version']??null)===self::INTERGROUP_VERSION){IntergroupStateCompetition::validate($c,$teams);return;}
        $r=$c['state_engine']??[];
        if(!in_array($r['version']??null,[self::VERSION,self::ROUTED_VERSION,self::ADMIN_VERSION],true)||$teams!==($c['teams']??[])||count($teams)<2||count(array_unique($teams))!==count($teams))throw new RuntimeException('Contrato estadual configurável inválido.');
        $allowed=['points','wins','gd','gf','h2h_gd_two','red','yellow','lots'];
        if(in_array($r['version']??null,[self::ROUTED_VERSION,self::ADMIN_VERSION],true))$allowed=array_merge($allowed,['h2h_points_group','h2h_gd_group','h2h_points_two']);
        if(($r['version']??null)===self::ADMIN_VERSION){$allowed=array_values(array_diff($allowed,['lots']));$allowed[]='federation_game';}
        $terminal=$r['version']===self::ADMIN_VERSION?'federation_game':'lots';
        if(empty($r['tiebreakers'])||count(array_unique($r['tiebreakers']))!==count($r['tiebreakers'])||array_diff($r['tiebreakers'],$allowed)||$r['tiebreakers'][0]!=='points'||end($r['tiebreakers'])!==$terminal)throw new RuntimeException('Critérios de desempate incompletos.');
        $stages=$r['stages']??[];if(!$stages)throw new RuntimeException('Fases ausentes.');$names=[];
        foreach($stages as $i=>$stage){if(empty($stage['name'])||isset($names[$stage['name']])||!in_array($stage['type']??'', ['league','knockout'],true))throw new RuntimeException('Fase inválida/duplicada.');$names[$stage['name']]=true;
            if($stage['type']==='league'){
                if($i!==0||!is_bool($stage['return']??null))throw new RuntimeException('Liga combinada não suportada neste contrato.');$groups=$stage['groups']??[$teams];if(array_merge(...$groups)!==$teams||array_filter($groups,fn($g)=>count($g)<2))throw new RuntimeException('Grupos inválidos.');
            }else{if(!in_array($stage['legs']??0,[1,2],true)||!in_array($stage['tie_rule']??'', ['penalties','advantage'],true)||!in_array($stage['home_basis']??'', ['initial','campaign'],true)||!in_array($stage['selection']??'', ['initial','group_top','winners','routed'],true)||!isset($stage['pairs']))throw new RuntimeException('Chaveamento não declarado.');
                if(($stage['selection']??'')==='routed'){
                    if(!in_array($r['version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true)||empty($stage['entries'])||!is_array($stage['entries']))throw new RuntimeException('Roteamento requer contrato v2.');
                    foreach($stage['entries'] as $entry){
                        if(!is_array($entry)||!is_int($entry['index']??null)||$entry['index']<0||!in_array($entry['from']??'', ['initial','winner','winner_initial'],true))throw new RuntimeException('Origem do classificado inválida.');
                        $previousKnockouts=array_column(array_filter(array_slice($stages,0,$i),fn($s)=>($s['type']??null)==='knockout'),'name');
                        if($entry['from']!=='initial'&&(!isset($entry['phase'])||!in_array($entry['phase'],$previousKnockouts,true)))throw new RuntimeException('Vencedor deve vir de mata-mata anterior.');
                    }
                    $indices=[];foreach($stage['pairs'] as $pair){if(!is_array($pair)||count($pair)!==2)throw new RuntimeException('Par de classificados inválido.');foreach($pair as $index){if(!is_int($index)||$index<0||$index>=count($stage['entries']))throw new RuntimeException('Índice de classificado inválido.');$indices[]=$index;}}
                    sort($indices);if($indices!==range(0,count($stage['entries'])-1))throw new RuntimeException('Roteamento deve usar cada classificado uma vez.');
                }
            }
        }
        foreach($stages as $stage){if(isset($stage['simultaneous_round'])&&($r['version']!==self::ADMIN_VERSION||$stage['type']!=='league'||!is_int($stage['simultaneous_round'])||$stage['simultaneous_round']<1))throw new RuntimeException('Rodada simultânea inválida.');if(isset($stage['neutral'])&&($r['version']!==self::ADMIN_VERSION||$stage['type']!=='knockout'||$stage['neutral']!==true||$stage['legs']!==1||empty($stage['venue'])))throw new RuntimeException('Campo neutro não declarado.');}
        // New semantics are opt-in; frozen v1/v2 contracts without these keys retain their ranking.
        if(isset($r['campaign_phases'])){
            if(!in_array($r['version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true)||!is_array($r['campaign_phases'])||!$r['campaign_phases']||count(array_unique($r['campaign_phases']))!==count($r['campaign_phases'])||array_diff($r['campaign_phases'],array_keys($names)))throw new RuntimeException('Fases da campanha inválidas.');
        }
        if(isset($r['eliminated_basis'])&&(!in_array($r['version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true)||!in_array($r['eliminated_basis'],$r['version']===self::ADMIN_VERSION?['initial','campaign','phase']:['initial','campaign'],true)))throw new RuntimeException('Base dos eliminados inválida.');
        if(($r['final_ranking']??'')==='qualified_campaign'&&(!in_array($r['version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true)||!isset($r['campaign_phases'])||!is_int($r['ranking_prefix']??null)||$r['ranking_prefix']<2||$r['ranking_prefix']>count($teams)))throw new RuntimeException('Escopo da classificação acumulada inválido.');
        if(!in_array($r['final_ranking']??'', ['campaign','elimination','qualified_campaign'],true)||!is_int($r['relegation']??null)||$r['relegation']<0||$r['relegation']>=count($teams))throw new RuntimeException('Classificação/descenso inválidos.');
    }

    public static function create(array $c,array $teams):array {
        if(($c['state_engine']['version']??null)===self::MARANHENSE_VERSION)return MaranhenseCompetition::create($c,$teams);
        if(($c['state_engine']['version']??null)===self::CONDITIONAL_VERSION)return ConditionalStateCompetition::create($c,$teams);
        if(($c['state_engine']['version']??null)===self::INTERGROUP_VERSION)return IntergroupStateCompetition::create($c,$teams);
        self::validate($c,$teams);$lots=$teams;if($c['state_engine']['version']!==self::ADMIN_VERSION)shuffle($lots);
        $t=['id'=>$c['id'],'format'=>'configured_state','adapter_version'=>self::VERSION,'teams'=>$teams,'phase'=>'','groups'=>[],'initial_groups'=>[],'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'state_stage'=>0,'state_eliminated'=>[]];
        $t['adapter_version']=$c['state_engine']['version'];
        if(in_array($t['adapter_version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true))$t['state_phase_winners']=[];
        self::stage($t,$c);return $t;
    }

    public static function standings(array $t,?array $teams=null,?string $phase=null,bool $campaign=false,bool $certify=false):array {
        $teams??=$t['teams'];$rules=Competition::catalog()[$t['id']]['state_engine'];
        if(in_array($rules['version']??null,[self::INTERGROUP_VERSION,self::CONDITIONAL_VERSION],true))$campaign=true;
        $matches=array_values(array_filter($t['matches'],fn($m)=>(!$phase||$m['phase']===$phase)&&(!$campaign||$phase||!isset($rules['campaign_phases'])||in_array($m['phase'],$rules['campaign_phases'],true))&&($campaign||(in_array($m['home'],$teams,true)&&in_array($m['away'],$teams,true)))));
        $rows=Schedule::table($matches,$teams);foreach($rows as &$row){$row['red']=0;$row['yellow']=0;foreach($matches as $m)if($m['status']==='finalizada')foreach(['home','away'] as $side)if($m[$side]===$row['team']){$row['red']+=$m[$side.'_red']??0;$row['yellow']+=$m[$side.'_yellow']??0;}}unset($row);
        // Group ties by every preceding criterion. H2H is never pairwise for a three-way tie.
        $sort=function(array $bucket,int $at)use(&$sort,$rules,$matches,$t,$phase,$certify):array{
            if(count($bucket)<2||$at>=count($rules['tiebreakers']))return $bucket;$criterion=$rules['tiebreakers'][$at];
            if($criterion==='federation_game'){return StateAdministration::rank($t,$bucket,$phase??'campaign',$certify);}
            if(in_array($criterion,['h2h_gd_two','h2h_points_two'],true)){
                if(count($bucket)!==2)return $sort($bucket,$at+1);$gd=0;[$a,$b]=$bucket;
                foreach($matches as $m)if($m['status']==='finalizada'&&in_array($m['home'],[$a['team'],$b['team']],true)&&in_array($m['away'],[$a['team'],$b['team']],true)){$delta=($m['home']===$a['team']?1:-1)*($m['hg']-$m['ag']);$gd+=$criterion==='h2h_points_two'?($delta<=>0)*3:$delta;}
                return $gd?($gd>0?[$a,$b]:[$b,$a]):$sort($bucket,$at+1);
            }
            if(in_array($criterion,['h2h_points_group','h2h_gd_group'],true)){
                $tied=array_column($bucket,'team');$direct=array_values(array_filter($matches,fn($m)=>in_array($m['home'],$tied,true)&&in_array($m['away'],$tied,true)));$mini=Schedule::table($direct,$tied);$values=array_column($mini,$criterion==='h2h_points_group'?'points':'gd','team');$groups=[];
                foreach($bucket as $row)$groups[$values[$row['team']]][]=$row;
                krsort($groups,SORT_NUMERIC);$out=[];foreach($groups as $group)$out=array_merge($out,$sort($group,$at+1));return $out;
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
                foreach($s['fixtures'] as $f){[$round,$home,$away]=$f;if(!is_int($round)||$round<1||!in_array($home,$t['teams'],true)||!in_array($away,$t['teams'],true)||$home===$away||isset($seen[$round.'|'.$home.'|'.$away])||isset($busy[$round][$home])||isset($busy[$round][$away]))throw new RuntimeException('Tabela inicial inválida.');$seen[$round.'|'.$home.'|'.$away]=true;$busy[$round][$home]=$busy[$round][$away]=true;$pair=[$home,$away];sort($pair);$pairs[implode('|',$pair)][]=$home;$match=Schedule::match($round,$home,$away)+['phase'=>$s['name'],'group'=>'1'];if($rSync=$s['simultaneous_round']??null){if($round===$rSync)$match+=['simultaneous_group'=>$t['id'].'|'.$s['name'].'|'.$round,'kickoff'=>'15:00'];}$t['matches'][]=$match;}
                $n=count($t['teams']);if(count($groups)!==1||count($pairs)!==$n*($n-1)/2)throw new RuntimeException('Tabela inicial incompleta.');foreach($pairs as $homes)if(count($homes)!==($s['return']?2:1)||($s['return']&&count(array_unique($homes))!==2))throw new RuntimeException('Confronto ou mando duplicado.');
                $t['phase_end']=max(array_column($t['matches'],'round'));usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
            }else Competition::leaguePhase($t,$groups,$s['name'],$s['return']);
            return;
        }
        $pool=match($s['selection']){
            'initial'=>$t['initial_ranking']??$t['teams'],
            'winners'=>$t['state_winners']??throw new RuntimeException('Vencedores anteriores ausentes.'),
            'group_top'=>array_merge(...array_map(fn($g)=>array_slice(array_column(self::standings($t,$g,$c['state_engine']['stages'][0]['name']),'team'),0,$s['per_group']),$t['initial_groups'])),
            'routed'=>self::routedPool($t,$s)
        };
        if($s['selection']==='winners')$pool=array_column(self::standings($t,$pool,$s['home_basis']==='initial'?$c['state_engine']['stages'][0]['name']:null,true),'team');
        $pairs=[];$used=[];foreach($s['pairs'] as [$a,$b]){if(!isset($pool[$a],$pool[$b])||$a===$b||isset($used[$pool[$a]])||isset($used[$pool[$b]]))throw new RuntimeException('Cruzamento inválido.');$used[$pool[$a]]=$used[$pool[$b]]=true;$pairs[]=[$pool[$a],$pool[$b]];}
        // Routing fixes opponents; home advantage is a separate ordering decision.
        if($s['selection']==='routed'){
            $rank=array_column(self::standings($t,$t['teams'],$s['home_basis']==='initial'?$c['state_engine']['stages'][0]['name']:null,true),'team');$order=array_flip($rank);
            foreach($pairs as &$pair)if($order[$pair[0]]>$order[$pair[1]])$pair=array_reverse($pair);unset($pair);
        }
        Competition::knockout($t,$pairs,$s['name'],$s['legs'],$s['tie_rule']);
        if(!empty($s['neutral']))foreach($t['matches'] as &$m)if($m['phase']===$s['name']){$m['neutral']=true;$m['venue']=$s['venue'];}unset($m);
    }

    private static function routedPool(array $t,array $stage):array {
        $pool=[];
        foreach($stage['entries'] as $entry){
            $source=$entry['from']==='initial'?($t['initial_ranking']??[]):($t['state_phase_winners'][$entry['phase']]??[]);
            if($entry['from']==='winner_initial'){$order=array_flip($t['initial_ranking']);usort($source,fn($a,$b)=>$order[$a]<=>$order[$b]);}
            if(!isset($source[$entry['index']]))throw new RuntimeException('Classificado de origem ausente.');
            $pool[]=$source[$entry['index']];
        }
        if(count(array_unique($pool))!==count($pool))throw new RuntimeException('Classificado duplicado no roteamento.');
        return $pool;
    }

    public static function next(array &$t):void {
        if(($t['adapter_version']??null)===self::MARANHENSE_VERSION){MaranhenseCompetition::next($t);return;}
        if(($t['adapter_version']??null)===self::CONDITIONAL_VERSION){ConditionalStateCompetition::next($t);return;}
        if(($t['adapter_version']??null)===self::INTERGROUP_VERSION){IntergroupStateCompetition::next($t);return;}
        $next=$t;try{StateAdministration::settle($next);self::advance($next);unset($next['administrative_pending']);$t=$next;}catch(StateAdministrativeTie $e){$t['matches']=$next['matches'];$t['ties']=$next['ties'];$t['administrative_pending']=$e->pending;}
    }

    private static function advance(array &$t):void {
        $c=Competition::catalog()[$t['id']];self::validate($c,$t['teams']);if(($t['adapter_version']??null)!==$c['state_engine']['version'])throw new RuntimeException('Versão do snapshot estadual inválida.');$r=$c['state_engine'];$s=$r['stages'][$t['state_stage']];
        if(array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Fase estadual incompleta.');
        if($s['type']==='league'){$t['initial_ranking']=array_column(self::standings($t,null,$s['name'],false,true),'team');$t['relegated']=$r['relegation']?array_slice($t['initial_ranking'],-$r['relegation']):[];}
        else{$w=Competition::resolve($t);$t['state_winners']=$w;$t['state_eliminated'][]=array_values(array_diff(array_merge(...$t['groups']),$w));if(in_array($r['version'],[self::ROUTED_VERSION,self::ADMIN_VERSION],true))$t['state_phase_winners'][$s['name']]=$w;}
        if(isset($r['stages'][$t['state_stage']+1])){$t['state_stage']++;self::stage($t,$c);return;}
        if($s['type']==='league')$ranking=$t['initial_ranking'];
        else{
            if(count($t['state_winners'])!==1)throw new RuntimeException('Campeão indefinido.');$champion=$t['state_winners'][0];$runner=array_values(array_diff($t['groups'][0],[$champion]));$prefix=array_merge([$champion],$runner);
            if($r['final_ranking']==='campaign')$ranking=array_merge($prefix,array_column(self::standings($t,array_values(array_diff($t['teams'],$prefix)),null,true),'team'));
            elseif($r['final_ranking']==='qualified_campaign'){$eligible=array_values(array_diff(array_slice($t['initial_ranking'],0,$r['ranking_prefix']),$prefix));$ranking=array_merge($prefix,array_column(self::standings($t,$eligible,null,true),'team'));$ranking=array_merge($ranking,array_values(array_diff($t['initial_ranking'],$ranking)));}
            else{$ranking=$prefix;foreach(array_reverse($t['state_eliminated'],true) as $eliminationIndex=>$eliminated)$ranking=array_merge($ranking,array_column(self::standings($t,array_values(array_diff($eliminated,$ranking)),($r['eliminated_basis']??'campaign')==='initial'?$r['stages'][0]['name']:(($r['eliminated_basis']??'campaign')==='phase'?array_values(array_filter($r['stages'],fn($stage)=>$stage['type']==='knockout'))[$eliminationIndex]['name']:null),true,true),'team'));$ranking=array_merge($ranking,array_values(array_diff($t['initial_ranking']??$t['teams'],$ranking)));}
        }
        $t['final_ranking']=$ranking;$t['champion']=$ranking[0];$t['promoted']=array_slice($ranking,0,$r['promotion']??0);$t['done']=true;
    }
}
