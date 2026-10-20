<?php
declare(strict_types=1);

/** Opt-in v9. Explicit schedules and phase barriers; never inferred from a state's name. */
final class ProgrammedStateCompetition {
    public const VERSION='configured-state-v9-program';
    public const EXTENDED_VERSION='configured-state-v10-edition';
    public static function supports(mixed $version):bool{return in_array($version,[self::VERSION,self::EXTENDED_VERSION],true);}

    public static function authorizesPartialCertification(array $c):bool{return ($c['state_engine']['closure']['schema']??null)==='certified-outcomes-v1';}

    public static function validate(array $c,array $teams):void {
        $r=$c['state_engine']??[];$initial=$r['initial']??[];
        if(!self::supports($r['version']??null)||($c['edition']??null)!==2026||empty($c['test_fixture'])||$teams!==($c['teams']??null)||count($teams)<2||count(array_unique($teams))!==count($teams))throw new RuntimeException('Contrato v9 inválido.');
        $criteria=$r['tiebreakers']??[];
        if(!$criteria||$criteria[0]!=='points'||end($criteria)!=='lots'||count(array_unique($criteria))!==count($criteria)||array_diff($criteria,array_merge(['points','wins','gd','gf','h2h_points_two','h2h_gd_two','h2h_away_two','red','yellow','disciplinary','lots'],$r['version']===self::EXTENDED_VERSION?['ga']:[])))throw new RuntimeException('Desempates v9 incompletos.');
        foreach(isset($r['ranking_tiebreakers'])?[$r['ranking_tiebreakers']]:[] as $rankCriteria)if(!$rankCriteria||$rankCriteria[0]!=='points'||end($rankCriteria)!=='lots'||array_diff($rankCriteria,array_merge(['points','wins','gd','gf','h2h_points_two','h2h_gd_two','h2h_away_two','red','yellow','disciplinary','lots'],$r['version']===self::EXTENDED_VERSION?['ga']:[])))throw new RuntimeException('Desempate da classificação geral inválido.');
        $groups=$initial['groups']??[$teams];if(array_merge(...$groups)!==$teams||array_filter($groups,fn($g)=>count($g)<2))throw new RuntimeException('Grupos iniciais inválidos.');
        self::schedule($initial,$teams);
        $known=['liga'=>count($teams)];$knownGroups=[];$knownTypes=['liga'=>'league'];
        foreach($r['barriers']??[] as $barrier){if(!$barrier)throw new RuntimeException('Barreira vazia.');$added=[];$addedTypes=[];
            foreach($barrier as $p){$name=$p['name']??'';if(!preg_match('/^[a-z][a-z_]*$/D',$name)||isset($known[$name],$added[$name])||isset($known[$name])||isset($added[$name]))throw new RuntimeException('Fase duplicada/inválida.');
                if(!in_array($p['type']??null,['knockout','league'],true)||empty($p['home_phases'])||array_diff($p['home_phases'],array_keys($known)))throw new RuntimeException('Ramo v9 não suportado.');
                if($p['type']==='knockout'&&(!in_array($p['legs']??null,[1,2],true)||!in_array($p['tie_rule']??null,($r['version']===self::EXTENDED_VERSION?['penalties','advantage','points_penalties','points_pending']:['penalties','advantage','points_penalties']),true)||!in_array($p['home_basis']??null,array_merge(['initial','campaign','entry_order','initial_group_position'],$r['version']===self::EXTENDED_VERSION?['phase_group_position','campaign_average']:[]),true)))throw new RuntimeException('Mata-mata v9 não suportado.');
                if(($p['tie_rule']??null)==='points_pending'&&empty($p['tie_pending_source']))throw new RuntimeException('Desempate pendente sem fonte.');
                if(($p['home_basis']??null)==='phase_group_position'&&(($knownTypes[$p['home_rank_phase']??'']??null)!=='league'))throw new RuntimeException('Posição de grupo sem fase anterior.');
                if(isset($p['only_if_distinct'])&&($r['version']!==self::EXTENDED_VERSION||$p['only_if_distinct']!==true||count($p['entries'])!==2||$p['legs']!==1))throw new RuntimeException('Final condicional inválida.');
                if(isset($p['venue_preserve_home'])&&($r['version']!==self::EXTENDED_VERSION||!is_bool($p['venue_preserve_home'])||empty($p['venue_decision_source'])))throw new RuntimeException('Preservação de mando sem designação.');
                if(isset($p['venue_options'])&&($r['version']!==self::EXTENDED_VERSION||empty($p['venue_decision_source'])||!is_array($p['venue_options'])||!array_is_list($p['venue_options'])||!$p['venue_options']||count(array_unique($p['venue_options']))!==count($p['venue_options'])||array_filter($p['venue_options'],fn($v)=>!is_string($v)||trim($v)===''||strlen($v)>160)))throw new RuntimeException('Recintos permitidos inválidos.');
                if(isset($p['venue_decision_source'])&&($r['version']!==self::EXTENDED_VERSION||$p['type']!=='knockout'||!in_array($p['legs'],[1,2],true)||($p['legs']===2&&empty($p['venue_preserve_home']))||!is_string($p['venue_decision_source'])||$p['venue_decision_source']===''))throw new RuntimeException('Designação de recinto requer contrato v10 e jogo único.');
                if(isset($p['only_when_all_entries_in'])&&($r['version']!==self::EXTENDED_VERSION||$p['type']!=='knockout'||!is_array($p['only_when_all_entries_in'])||array_diff($p['only_when_all_entries_in'],$teams)||($knownTypes[$p['qualifier_phase']??'']??null)!=='knockout'||empty($p['condition_source'])||($p['max_qualifiers']??null)!==2))throw new RuntimeException('Ramo condicional sem contrato comprovado.');
                $entries=$p['entries']??[];if(count($entries)<2||count($entries)%2)throw new RuntimeException('Entradas do ramo ausentes.');
                foreach($entries as $e){if(!is_int($e['index']??null)||$e['index']<0)throw new RuntimeException('Índice da entrada inválido.');$from=$e['from']??null;if($from==='initial'||($from==='participant'&&$r['version']===self::EXTENDED_VERSION)){$limit=count($teams);}elseif($from==='group'&&isset($groups[$e['group']??-1])){$limit=count($groups[$e['group']]);}elseif($from==='phase_group'&&isset($known[$e['phase']??''])&&isset($knownGroups[$e['phase']??''][$e['group']??-1])){$limit=$knownGroups[$e['phase']][$e['group']];}elseif(in_array($from,['winner','loser','phase_rank'],true)&&isset($known[$e['phase']??''])&&$e['phase']!=='liga'){if(($from==='phase_rank')!==($knownTypes[$e['phase']]==='league'))throw new RuntimeException('Origem incompatível com o tipo da fase.');$limit=$known[$e['phase']];}else throw new RuntimeException('Origem do ramo inválida.');if($e['index']>=$limit)throw new RuntimeException('Entrada fora da origem.');}
                if(isset($p['single_leg_if_any'])&&(!is_array($p['single_leg_if_any'])||array_diff($p['single_leg_if_any'],$teams)||empty($p['entry_evidence_source'])||$p['type']!=='knockout'||$p['legs']!==2))throw new RuntimeException('Condição de ida/volta sem inscrições comprovadas.');
                if($p['type']==='league'){$schedule=$p['schedule']??[];$n=count($entries);self::schedule($schedule,range(0,$n-1));if(isset($p['bonus'])){$bonus=$p['bonus'];if(!is_int($bonus['points']??null)||$bonus['points']<1||$bonus['points']>3||empty($bonus['source'])||empty($bonus['candidate_entries'])||count(array_unique($bonus['candidate_entries']))!==count($bonus['candidate_entries']))throw new RuntimeException('Bonificação sem fundamento.');foreach($bonus['candidate_entries'] as $i)if(!is_int($i)||$i<0||$i>=$n)throw new RuntimeException('Clube da bonificação inválido.');}if(isset($p['home_if_best_entry'])){$v=$p['home_if_best_entry'];if(!is_array($v['entries']??null)||count($v['entries'])!==2||$v['entries'][0]===$v['entries'][1]||!in_array($v['default_home']??null,$v['entries'],true)||empty($v['source']))throw new RuntimeException('Mando condicional sem fonte.');foreach($v['entries'] as $i)if(!is_int($i)||$i<0||$i>=$n)throw new RuntimeException('Entrada do mando condicional inválida.');$matches=array_filter($schedule['fixtures'],fn($f)=>in_array($f[1],$v['entries'],true)&&in_array($f[2],$v['entries'],true));if(count($matches)!==1||array_values($matches)[0][1]!==$v['default_home'])throw new RuntimeException('Confronto condicional divergente.');}$knownGroups[$name]=array_map('count',$schedule['groups']??[range(0,$n-1)]);$added[$name]=$n;$addedTypes[$name]='league';continue;}
                $indices=[];foreach($p['pairs']??[] as $pair){if(!is_array($pair)||count($pair)!==2||$pair[0]===$pair[1])throw new RuntimeException('Par inválido.');foreach($pair as $i){if(!is_int($i)||$i<0||$i>=count($entries))throw new RuntimeException('Índice de confronto inválido.');$indices[]=$i;}}sort($indices);if($indices!==range(0,count($entries)-1))throw new RuntimeException('Confrontos não cobrem os classificados.');
                if(isset($p['home_decision_required'])&&($p['home_decision_required']!==true||$p['legs']!==2||empty($p['decision_source'])))throw new RuntimeException('Opção de mando sem fundamento explícito.');
                if(isset($p['seed_by_lots'])&&!is_bool($p['seed_by_lots']))throw new RuntimeException('Sorteio do ramo deve ser explícito.');
                if(isset($p['seed_by_campaign'])&&!is_bool($p['seed_by_campaign']))throw new RuntimeException('Ressemeadura deve ser explícita.');
                $added[$name]=intdiv(count($entries),2);$addedTypes[$name]='knockout';
            }$known+=$added;$knownTypes+=$addedTypes;
        }
        if(!isset($r['closure'])||!is_array($r['closure']))throw new RuntimeException('Contrato precisa declarar o encerramento.');
        if(isset($r['closure']['pending_if_same_winners'])){$v=$r['closure']['pending_if_same_winners'];if($r['version']!==self::EXTENDED_VERSION||!is_array($v['phases']??null)||count($v['phases'])!==2||count(array_unique($v['phases']))!==2||empty($v['source'])||empty($v['reason']))throw new RuntimeException('Pendência de turnos inválida.');foreach($v['phases'] as $phase)if(($known[$phase]??null)!==1||($knownTypes[$phase]??null)!=='knockout')throw new RuntimeException('Turno não produz campeão único.');}
        if(isset($r['closure']['relegation_basis'])&&($r['version']!==self::EXTENDED_VERSION||!in_array($r['closure']['relegation_basis'],['final_ranking','initial'],true)))throw new RuntimeException('Base de descenso não suportada.');
        if(isset($r['closure']['pending'])){
            if(empty($r['closure']['pending']['reason'])||empty($r['closure']['pending']['source']))throw new RuntimeException('Pendência sem justificativa/fonte.');
            if(isset($r['closure']['schema'])){
                if($r['closure']['schema']!=='certified-outcomes-v1'||$r['version']!==self::EXTENDED_VERSION)throw new RuntimeException('Esquema de certificação parcial não suportado.');
                if(empty($r['closure']['title_phase'])||($known[$r['closure']['title_phase']]??0)!==1)throw new RuntimeException('Fase de título da certificação parcial inválida.');
                if(!isset($r['closure']['certified_runner_up'])||$r['closure']['certified_runner_up']!==true)throw new RuntimeException('Vice parcial sem declaração explícita.');
                if(!isset($r['closure']['relegation'])||!is_int($r['closure']['relegation'])||$r['closure']['relegation']<0||$r['closure']['relegation']>=count($teams))throw new RuntimeException('Descenso parcial inválido.');
                if(isset($r['closure']['relegation_basis'])&&$r['closure']['relegation_basis']==='final_ranking')throw new RuntimeException('Classificação final indisponível para descenso parcial.');
                $rPhase=$r['closure']['relegation_phase']??'liga';
                if(!isset($known[$rPhase])||($knownTypes[$rPhase]??null)!=='league')throw new RuntimeException('Fase de descenso parcial inválida.');
            }else{
                if(isset($r['closure']['title_phase'])||isset($r['closure']['certified_runner_up'])||isset($r['closure']['relegation_phase'])||isset($r['closure']['relegation']))throw new RuntimeException('Contrato pendente sem esquema de certificação parcial.');
            }
        }
        else{if(($known[$r['closure']['title_phase']??'']??0)!==1||empty($r['closure']['ranking']))throw new RuntimeException('Título/classificação indefinidos.');foreach($r['closure']['ranking'] as $block){if(!in_array($block['from']??null,['initial','winner','loser','phase_rank','optional_winner','optional_loser'],true)||($block['from']!=='initial'&&!isset($known[$block['phase']??''])))throw new RuntimeException('Classificação final inválida.');}if(!is_int($r['closure']['relegation']??null)||$r['closure']['relegation']<0||$r['closure']['relegation']>=count($teams))throw new RuntimeException('Descenso inválido.');}
    }

    private static function schedule(array $s,array $teams):void {
        if(!is_int($s['rounds']??null)||$s['rounds']<1||!is_int($s['games_per_club']??null)||$s['games_per_club']<1||!in_array($s['coverage']??null,['single_round_robin','double_round_robin','sparse','intergroup','intragroup'],true))throw new RuntimeException('Cobertura da tabela ausente.');
        $groups=$s['groups']??[$teams];if(array_merge(...$groups)!==$teams||array_filter($groups,fn($g)=>count($g)<2))throw new RuntimeException('Grupos da tabela inválidos.');
        $busy=[];$pairs=[];$counts=array_fill_keys($teams,0);$homes=$counts;$rounds=[];$groupOf=[];
        foreach($s['groups']??[$teams] as $index=>$group)foreach($group as $club)$groupOf[$club]=$index;
        foreach($s['fixtures']??[] as $f){if(!is_array($f)||count($f)!==3)throw new RuntimeException('Confronto inicial inválido.');[$round,$home,$away]=$f;$pair=[$home,$away];sort($pair,SORT_STRING);$key=$s['coverage']==='double_round_robin'?$home.'|'.$away:implode('|',$pair);
            if(!is_int($round)||$round<1||$round>$s['rounds']||!isset($counts[$home],$counts[$away])||$home===$away||isset($busy[$round][$home])||isset($busy[$round][$away])||isset($pairs[$key]))throw new RuntimeException('Clube, rodada, mando ou adversário repetido.');
            if(($s['coverage']==='intergroup'&&$groupOf[$home]===$groupOf[$away])||($s['coverage']==='intragroup'&&$groupOf[$home]!==$groupOf[$away]))throw new RuntimeException('Confronto interno em liga intergrupos.');
            $pairs[$key]=true;$busy[$round][$home]=$busy[$round][$away]=true;$counts[$home]++;$counts[$away]++;$homes[$home]++;$rounds[$round]=true;
        }
        if(count($rounds)!==$s['rounds'])throw new RuntimeException('Rodada ausente.');
        foreach($teams as $club){if($counts[$club]!==$s['games_per_club'])throw new RuntimeException('Quantidade de partidas divergente.');if(isset($s['home_counts'])&&(!isset($s['home_counts'][$club])||$homes[$club]!==$s['home_counts'][$club]))throw new RuntimeException('Mandos individuais divergentes.');if(isset($s['home_games'])&&$homes[$club]!==$s['home_games'])throw new RuntimeException('Quantidade de mandos divergente.');}
        if($s['coverage']!=='sparse')foreach($teams as $a)foreach($teams as $b){if($a===$b||($s['coverage']==='intergroup'&&$groupOf[$a]===$groupOf[$b])||($s['coverage']==='intragroup'&&$groupOf[$a]!==$groupOf[$b]))continue;$pair=[$a,$b];sort($pair,SORT_STRING);$key=$s['coverage']==='double_round_robin'?$a.'|'.$b:implode('|',$pair);if(!isset($pairs[$key]))throw new RuntimeException('Adversário obrigatório ausente.');}
    }

    public static function create(array $c,array $teams):array {
        self::validate($c,$teams);$r=$c['state_engine'];$lots=$teams;shuffle($lots);$matches=[];
        foreach($r['initial']['fixtures'] as [$round,$home,$away])$matches[]=Schedule::match($round,$home,$away)+['phase'=>'liga','group'=>'liga'];
        usort($matches,fn($a,$b)=>$a['round']<=>$b['round']);$groups=$r['initial']['groups']??[$teams];
        $t=['id'=>$c['id'],'format'=>'configured_state','adapter_version'=>$r['version'],'teams'=>$teams,'phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>$matches,'phase_start'=>1,'phase_end'=>$r['initial']['rounds'],'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'state_stage'=>0,'state_eliminated'=>[],'state_phase_winners'=>[],'state_phase_losers'=>[],'state_phase_ties'=>[]];
        if(self::authorizesPartialCertification($c)){
            $t['runner_up']=null;
            $t['partial_certification']=null;
        }
        return $t;
    }

    public static function rank(array $t,array $clubs,array $phases,bool $groupPosition=false):array {
        $copy=$t;$copy['program_rank_phases']=$phases;if($groupPosition)$copy['program_rank_context']='group_position';$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],$phases,true)));
        $rank=array_column(ConfiguredStateCompetition::standings($copy,$clubs,null,true),'team');
        if($groupPosition){$places=[];foreach($t['initial_group_ranking'] as $group)foreach($group as $place=>$club)$places[$club]=$place;$buckets=[];foreach($rank as $club)$buckets[$places[$club]][]=$club;ksort($buckets,SORT_NUMERIC);$rank=array_merge(...array_values($buckets));}return $rank;
    }

    public static function standingsForBranch(array $t,array $phase):array {return ConfiguredStateCompetition::standings($t,self::entries($t,$phase['entries']),$phase['name'],true);}

    private static function entries(array $t,array $entries,bool $allowDuplicate=false):array {
        $out=[];foreach($entries as $entry){$from=$entry['from'];$source=match($from){'participant'=>$t['teams'],'initial'=>$t['initial_ranking'],'group'=>$t['initial_group_ranking'][$entry['group']],'phase_rank'=>$t['state_phase_rank'][$entry['phase']],'phase_group'=>$t['state_phase_group_rank'][$entry['phase']][$entry['group']],default=>$t['state_phase_'.($from==='winner'?'winners':'losers')][$entry['phase']]};if(!isset($source[$entry['index']]))throw new RuntimeException('Classificado ausente.');$out[]=$source[$entry['index']];}
        if(!$allowDuplicate&&count(array_unique($out))!==count($out))throw new RuntimeException('Classificado duplicado.');return $out;
    }

    public static function next(array &$t,bool $proof=false):void {
        if($t['done']||isset($t['administrative_pending']))return;
        $c=Competition::catalog()[$t['id']];self::validate($c,$t['teams']);if($t['adapter_version']!==$c['state_engine']['version'])throw new RuntimeException('Snapshot v9 incompatível.');$r=$c['state_engine'];$n=$t;
        if(array_filter($n['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Fase obrigatória incompleta.');
        if($n['state_stage']===0){$n['initial_group_ranking']=array_map(fn($group)=>self::rank($n,$group,['liga']),$n['initial_groups']);$n['initial_ranking']=self::rank($n,$n['teams'],['liga'],($r['initial']['ranking']??null)==='group_position');}
        else foreach($r['barriers'][$n['state_stage']-1] as $branch){self::resolve($n,$branch,$proof);if(isset($n['administrative_pending'])){$t=$n;return;}}
        if(isset($r['barriers'][$n['state_stage']])){
            $active=[];foreach($r['barriers'][$n['state_stage']] as $p){if(!empty($p['only_if_distinct'])){$entries=self::entries($n,$p['entries'],true);if(count(array_unique($entries))===1){$n['state_phase_skipped'][$p['name']]='same_winner';$n['state_phase_winners'][$p['name']]=[$entries[0]];$n['state_phase_losers'][$p['name']]=[];$n['state_phase_ties'][$p['name']]=[];continue;}}if(isset($p['only_when_all_entries_in'])){$qualified=array_merge($n['state_phase_winners'][$p['qualifier_phase']],$n['state_phase_losers'][$p['qualifier_phase']]);$eligible=array_intersect($qualified,$p['only_when_all_entries_in']);if(count($eligible)>$p['max_qualifiers']){$body=['competition'=>$c['id'],'phase'=>$p['name'],'source'=>$p['condition_source'],'reason'=>'conditional_merit_for_multiple_qualifiers_not_documented','results'=>self::resultsDigest($n['matches'])];$n['administrative_pending']=['key'=>StateResultProof::digest($body),'teams'=>array_values($eligible),'reason'=>$body['reason'],'source'=>$body['source'],'state'=>'awaiting_documented_rule','simulation_only'=>true,'evidence'=>$body];$t=$n;return;}if(array_diff(self::entries($n,$p['entries']),$p['only_when_all_entries_in'])){$n['state_phase_skipped'][$p['name']]=true;$n['state_phase_winners'][$p['name']]=$n['state_phase_losers'][$p['name']]=[];$n['state_phase_ties'][$p['name']]=[];continue;}}$active[]=$p;}
            $choices=[];$venues=[];
            foreach($active as $p)if(isset($p['venue_decision_source'])){$pool=self::entries($n,$p['entries']);$homeRank=array_flip(self::homeRank($n,$p,$pool));$pairs=array_map(function($pair)use($pool,$homeRank){$clubs=[$pool[$pair[0]],$pool[$pair[1]]];if($homeRank[$clubs[0]]>$homeRank[$clubs[1]])$clubs=array_reverse($clubs);return $clubs;},$p['pairs']);$venues[$p['name']]=StateVenueAdministration::request($n,$c,$p,$pairs);if(isset($n['administrative_pending'])){$t=$n;return;}}

            foreach($active as $p)if(!empty($p['home_decision_required'])){$pool=self::entries($n,$p['entries']);if(!empty($p['seed_by_lots']))usort($pool,fn($a,$b)=>$n['lots'][$a]<=>$n['lots'][$b]);if(!empty($p['seed_by_campaign']))$pool=self::rank($n,$pool,$p['home_phases']);$choices[$p['name']]=StateProgramAdministration::request($n,$c,$p,$pool);if(isset($n['administrative_pending'])){$t=$n;return;}}
            $start=$n['phase_end'];$end=$start;$busy=[];$combined=[];
            foreach($active as $p){$pool=self::entries($n,$p['entries']);if(!empty($p['seed_by_lots']))usort($pool,fn($a,$b)=>$n['lots'][$a]<=>$n['lots'][$b]);if(!empty($p['seed_by_campaign']))$pool=self::rank($n,$pool,$p['home_phases']);
                if($p['type']==='league'){
                    foreach($pool as $club){if(isset($busy[$club]))throw new RuntimeException('Clube em ramos simultâneos.');$busy[$club]=true;}
                    $fixtures=$p['schedule']['fixtures'];
                    if(isset($p['home_if_best_entry'])){$v=$p['home_if_best_entry'];$best=self::rank($n,array_map(fn($i)=>$pool[$i],$v['entries']),['liga'])[0];if($best!==$pool[$v['default_home']])foreach($fixtures as &$f)if(in_array($f[1],$v['entries'],true)&&in_array($f[2],$v['entries'],true))[$f[1],$f[2]]=[$f[2],$f[1]];unset($f);}
                    foreach($fixtures as [$round,$a,$b])$n['matches'][]=Schedule::match($start+$round,$pool[$a],$pool[$b])+['phase'=>$p['name'],'group'=>$p['name']];
                    $n['state_phase_groups'][$p['name']]=array_map(fn($indices)=>array_map(fn($i)=>$pool[$i],$indices),$p['schedule']['groups']??[range(0,count($pool)-1)]);
                    $end=max($end,$start+$p['schedule']['rounds']);$n['state_phase_ties'][$p['name']]=[];
                    if(isset($p['bonus'])){$eligible=array_map(fn($i)=>$pool[$i],$p['bonus']['candidate_entries']);$best=self::rank($n,$eligible,['liga'])[0];$n['phase_bonus'][$p['name']]=[$best=>$p['bonus']['points']];}
                    $combined[]=$pool;continue;
                }
                $rank=array_flip(self::homeRank($n,$p,$pool));$pairs=[];
                foreach($p['pairs'] as $pairIndex=>[$a,$b]){$pair=[$pool[$a],$pool[$b]];if(isset($venues[$p['name']])){$v=$venues[$p['name']]['games'][$pairIndex];$pair=[$v['home'],$v['away']];}elseif(isset($choices[$p['name']])){if($pair[0]!==$choices[$p['name']]['home_clubs'][$pairIndex])$pair=array_reverse($pair);}elseif($rank[$pair[0]]>$rank[$pair[1]])$pair=array_reverse($pair);foreach($pair as $club){if(isset($busy[$club]))throw new RuntimeException('Clube em ramos simultâneos.');$busy[$club]=true;}$pairs[]=$pair;}
                $branch=['matches'=>[],'phase_end'=>$start];$legs=isset($p['single_leg_if_any'])&&array_intersect($pool,$p['single_leg_if_any'])?1:$p['legs'];Competition::knockout($branch,$pairs,$p['name'],$legs,$p['tie_rule']);foreach($branch['matches'] as &$match){if(isset($p['venue']))$match['venue']=$p['venue'];if(isset($venues[$p['name']])){$v=$venues[$p['name']]['games'][$match['tie']];$match['venue']=$v['venue'];$match['neutral']=$v['neutral'];$match['venue_decision_digest']=$venues[$p['name']]['sha256'];}if(isset($choices[$p['name']]))$match['program_decision_digest']=$choices[$p['name']]['sha256'];}unset($match);$n['matches']=array_merge($n['matches'],$branch['matches']);$n['state_phase_ties'][$p['name']]=$branch['ties'];$combined=array_merge($combined,$pairs);$end=max($end,$branch['phase_end']);
            }
            $n['phase_start']=$start+1;$n['phase_end']=$end;$n['groups']=$combined;$n['phase']=$r['barriers'][$n['state_stage']][0]['name'];$n['ties']=[];$n['state_stage']++;usort($n['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
        }elseif(isset($r['closure']['pending_if_same_winners'])&&count(array_unique(array_map(fn($phase)=>$n['state_phase_winners'][$phase][0],$r['closure']['pending_if_same_winners']['phases'])))===1){
            $body=['competition'=>$c['id'],'reason'=>$r['closure']['pending_if_same_winners']['reason'],'source'=>$r['closure']['pending_if_same_winners']['source'],'results'=>self::resultsDigest($n['matches'])];$n['administrative_pending']=['key'=>StateResultProof::digest($body),'teams'=>[],'reason'=>$body['reason'],'source'=>$body['source'],'state'=>'awaiting_documented_rule','simulation_only'=>true,'evidence'=>$body];
        }elseif(isset($r['closure']['pending'])){
            if(self::authorizesPartialCertification($c)){
                $champion=$n['state_phase_winners'][$r['closure']['title_phase']][0]??null;
                $runnerUp=$n['state_phase_losers'][$r['closure']['title_phase']][0]??null;
                if($champion===null||$runnerUp===null||$champion===$runnerUp||!in_array($champion,$n['teams'],true)||!in_array($runnerUp,$n['teams'],true)){
                    throw new RuntimeException('Finalistas da certificação parcial inválidos.');
                }
                $relegated=[];
                if($r['closure']['relegation']>0){
                    $rPhase=$r['closure']['relegation_phase']??'liga';
                    $descent=$rPhase==='liga'?$n['initial_ranking']:($n['state_phase_rank'][$rPhase]??null);
                    if(!is_array($descent)||count($descent)<$r['closure']['relegation']){
                        throw new RuntimeException('Base de descenso indisponível para certificação.');
                    }
                    $relegated=array_slice($descent,-$r['closure']['relegation']);
                    if(count($relegated)!==$r['closure']['relegation']||count(array_unique($relegated))!==count($relegated)||array_diff($relegated,$n['teams'])||in_array($champion,$relegated,true)||in_array($runnerUp,$relegated,true)){
                        throw new RuntimeException('Descenso parcial diverge das garantias esportivas.');
                    }
                }
                $resultsDigest=self::resultsDigest($n['matches']);
                $contractDigest=StateResultProof::digest($c['state_engine']);
                $receipt=[
                    'schema'=>1,
                    'version'=>'certified-outcomes-v1',
                    'competition'=>$n['id'],
                    'edition'=>$c['edition'],
                    'contract_digest'=>$contractDigest,
                    'results_digest'=>$resultsDigest,
                    'certified_champion'=>$champion,
                    'certified_runner_up'=>$runnerUp,
                    'certified_relegated'=>$relegated,
                    'pending_ranking'=>true,
                    'pending_national_admission'=>true,
                    'homologated'=>false,
                    'reason'=>$r['closure']['pending']['reason'],
                    'source'=>$r['closure']['pending']['source'],
                ];
                $receiptDigest=StateResultProof::digest($receipt);
                $body=['competition'=>$n['id'],'edition'=>$c['edition'],'stage'=>$n['state_stage'],'results_digest'=>$resultsDigest,'contract_digest'=>$contractDigest,'partial_certification'=>$receiptDigest]+$r['closure']['pending'];
                $n['champion']=$champion;
                $n['runner_up']=$runnerUp;
                $n['relegated']=$relegated;
                $n['partial_certification']=$receipt;
                $n['administrative_pending']=['key'=>StateResultProof::digest($body),'reason'=>$body['reason'],'source'=>$body['source'],'state'=>'awaiting_documented_rule','simulation_only'=>true,'teams'=>[],'evidence'=>$body];
            }else{
                $body=['competition'=>$n['id'],'edition'=>$c['edition'],'stage'=>$n['state_stage'],'results_digest'=>self::resultsDigest($n['matches'])]+$r['closure']['pending'];
                $n['administrative_pending']=['key'=>StateResultProof::digest($body),'reason'=>$body['reason'],'source'=>$body['source'],'state'=>'awaiting_documented_rule','simulation_only'=>true,'teams'=>[],'evidence'=>$body];
            }
        }else{
            $ranking=[];foreach($r['closure']['ranking'] as $block){$pool=match($block['from']){'initial'=>$n['initial_ranking'],'phase_rank'=>$n['state_phase_rank'][$block['phase']],default=>$n['state_phase_'.(in_array($block['from'],['winner','optional_winner'],true)?'winners':'losers')][$block['phase']]};$pool=array_values(array_diff($pool,$ranking));if(isset($block['rank_phases']))$pool=self::rank($n,$pool,$block['rank_phases'],($block['rank_basis']??null)==='initial_group_position');$ranking=array_merge($ranking,$pool);}
            if(count($ranking)!==count($n['teams'])||count(array_unique($ranking))!==count($ranking))throw new RuntimeException('Ranking final incompleto.');$champion=$n['state_phase_winners'][$r['closure']['title_phase']][0];if($ranking[0]!==$champion)throw new RuntimeException('Título diverge do ranking.');
            $n['final_ranking']=$ranking;$n['champion']=$champion;$descent=($r['closure']['relegation_basis']??null)==='final_ranking'?$ranking:(isset($r['closure']['relegation_phase'])?$n['state_phase_rank'][$r['closure']['relegation_phase']]:$n['initial_ranking']);$n['relegated']=$r['closure']['relegation']?array_slice($descent,-$r['closure']['relegation']):[];$n['done']=true;
            foreach($r['closure']['secondary_merits']??[] as $name=>$merit){if(($merit['type']??null)==='conditional_interior'){$qual=array_values(array_intersect(array_merge($n['state_phase_winners'][$merit['qualifier_phase']],$n['state_phase_losers'][$merit['qualifier_phase']]),$merit['clubs']));$finalists=array_values(array_intersect(array_merge($n['state_phase_winners'][$merit['final_phase']],$n['state_phase_losers'][$merit['final_phase']]),$merit['clubs']));$winner=count($qual)===1?$qual[0]:(count($finalists)===2?$champion:(count($finalists)===1?$finalists[0]:($n['state_phase_winners'][$merit['playoff_phase']][0]??null)));if($winner===null)throw new RuntimeException('Mérito interior sem fonte suficiente.');$n['secondary_merits'][$name]=$winner;}else $n['secondary_merits'][$name]=!empty($merit['initial_group_leader'])?$n['initial_ranking'][0]:$n['state_phase_winners'][$merit['phase']][0];}
        }$t=$n;
    }

    private static function homeRank(array $t,array $phase,array $pool):array {
        if($phase['home_basis']==='campaign_average'){
            $matches=array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],$phase['home_phases'],true)));$copy=$t;$copy['matches']=$matches;$rows=array_column(ConfiguredStateCompetition::standings($copy,$pool,null,true),null,'team');$criteria=Competition::catalog()[$t['id']]['state_engine']['tiebreakers'];
            usort($pool,function($a,$b)use($rows,$criteria,$matches,$t){$x=$rows[$a];$y=$rows[$b];if(!$x['played']||!$y['played'])throw new RuntimeException('Média de campanha sem partidas.');foreach($criteria as $criterion){if($criterion==='lots')return $t['lots'][$a]<=>$t['lots'][$b];if($criterion==='h2h_points_two'){$direct=0;foreach($matches as $m)if(in_array($m['home'],[$a,$b],true)&&in_array($m['away'],[$a,$b],true))$direct+=($m['home']===$a?1:-1)*(($m['hg']-$m['ag'])<=>0);if($direct)return -($direct<=>0);continue;}$delta=($x[$criterion]*$y['played'])<=>($y[$criterion]*$x['played']);if($delta)return in_array($criterion,['red','yellow'],true)?$delta:-$delta;}return 0;});return $pool;
        }
        if($phase['home_basis']==='phase_group_position'){$copy=$t;$copy['initial_group_ranking']=$t['state_phase_group_rank'][$phase['home_rank_phase']];return self::rank($copy,$pool,$phase['home_phases'],true);}
        return match($phase['home_basis']){'initial'=>$t['initial_ranking'],'entry_order'=>$pool,'initial_group_position'=>self::rank($t,$pool,['liga'],true),default=>self::rank($t,$pool,$phase['home_phases'])};
    }
    private static function resultsDigest(array $matches):string {
        return StateResultProof::digest(array_map(fn($m)=>array_intersect_key($m,array_flip(['phase','round','home','away','status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'])),$matches));
    }
    private static function resolve(array &$t,array $p,bool $proof):void {
        if(isset($t['state_phase_skipped'][$p['name']]))return;
        if($p['type']==='league'){$t['state_phase_rank'][$p['name']]=self::rank($t,self::entries($t,$p['entries']),[$p['name']]);$t['state_phase_group_rank'][$p['name']]=array_map(fn($clubs)=>self::rank($t,$clubs,[$p['name']]),$t['state_phase_groups'][$p['name']]);return;}
        $view=['phase'=>$p['name'],'matches'=>$t['matches'],'ties'=>$t['state_phase_ties'][$p['name']]];
        foreach($view['ties'] as $i=>&$tie){$points=0;$gd=0;$indices=[];foreach($view['matches'] as $j=>$m)if($m['phase']===$p['name']&&($m['tie']??null)===$i){$delta=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=($delta<=>0)*3;$gd+=$delta;$indices[]=$j;}if(!$indices)throw new RuntimeException('Confronto sem prova.');$last=end($indices);$decisive=$points?:(in_array($p['tie_rule'],['points_penalties','points_pending'],true)?0:$gd);if(!$points&&$p['tie_rule']==='points_pending'){$body=['competition'=>$t['id'],'phase'=>$p['name'],'tie'=>$i,'source'=>$p['tie_pending_source'],'reason'=>'tie_criterion_requires_documentary_clarification','results'=>self::resultsDigest($t['matches'])];$t['administrative_pending']=['key'=>StateResultProof::digest($body),'teams'=>[$tie['a'],$tie['b']],'reason'=>$body['reason'],'source'=>$body['source'],'state'=>'awaiting_documented_rule','simulation_only'=>true,'evidence'=>$body];return;}
            foreach($indices as $j)if(isset($view['matches'][$j]['penalties'])&&($j!==$last||$decisive||$p['tie_rule']==='advantage'))throw new RuntimeException('Pênaltis indevidos.');
            if($decisive)$tie['winner']=$decisive>0?$tie['a']:$tie['b'];elseif($p['tie_rule']==='advantage')$tie['winner']=$tie['a'];else{
                $m=&$view['matches'][$last];$pens=$m['penalties']??null;
                if(!$proof&&$pens===null){$homeWins=(bool)random_int(0,1);$loserScore=random_int(2,4);$pens=[$loserScore+($homeWins?1:0),$loserScore+($homeWins?0:1)];$m['penalties']=$pens;$m['log'][]=['minute'=>90,'text'=>'Nos pênaltis: '.$m['home'].' '.$pens[0].' × '.$pens[1].' '.$m['away'].'.'];}
                if(!is_array($pens)||count($pens)!==2||!is_int($pens[0])||!is_int($pens[1])||min($pens)<0||$pens[0]===$pens[1])throw new RuntimeException('Pênaltis sem prova.');$tie['winner']=$pens[0]>$pens[1]?$m['home']:$m['away'];unset($m);
            }
        }unset($tie);
        $w=array_column($view['ties'],'winner');$t['matches']=$view['matches'];$t['state_phase_ties'][$p['name']]=$view['ties'];$t['state_phase_winners'][$p['name']]=$w;$t['state_phase_losers'][$p['name']]=array_values(array_diff(array_merge(...array_map(fn($tie)=>[$tie['a'],$tie['b']],$view['ties'])),$w));
    }

    /** Includes pending snapshots: a verified prefix is not a homologated competition. */
    public static function verify(array $c,array $t):bool {
        try{self::validate($c,$t['teams']??[]);if(($t['id']??null)!==$c['id']||($t['adapter_version']??null)!==$c['state_engine']['version']||($t['format']??null)!=='configured_state'||(empty($t['done'])&&!isset($t['administrative_pending'])))return false;
            $isCertified=self::authorizesPartialCertification($c);
            if($isCertified){
                if(!array_key_exists('runner_up',$t)||!array_key_exists('partial_certification',$t)||!is_array($t['partial_certification']))return false;
            }else{
                if(array_key_exists('runner_up',$t)||array_key_exists('partial_certification',$t))return false;
            }
            $lots=$t['lots']??[];$values=array_values($lots);sort($values);if($values!==range(0,count($t['teams'])-1)||array_diff(array_keys($lots),$t['teams']))return false;
            $leaguePhases=['liga'];foreach($c['state_engine']['barriers']??[] as $barrier)foreach($barrier as $branch)if($branch['type']==='league')$leaguePhases[]=$branch['name'];$key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);$saved=[];foreach($t['matches'] as $m){if($m['status']!=='finalizada'||isset($saved[$key($m)])||!is_int($m['hg'])||!is_int($m['ag'])||min($m['hg'],$m['ag'])<0||(in_array($m['phase'],$leaguePhases,true)&&isset($m['penalties'])))return false;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)if(!is_int($m[$f]??0)||($m[$f]??0)<0)return false;$saved[$key($m)]=$m;}
            $copy=self::create($c,$c['teams']);$copy['lots']=$lots;$copy['program_decisions']=$t['program_decisions']??[];$copy['venue_decisions']=$t['venue_decisions']??[];$seen=[];
            for($guard=0;!$copy['done']&&!isset($copy['administrative_pending']);$guard++){if($guard>30)return false;foreach($copy['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;foreach(['tie','group','venue','neutral','program_decision_digest','venue_decision_digest'] as $meta)if(($source[$meta]??null)!==($m[$meta]??null))return false;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];$seen[$key($m)]=true;}unset($m);self::next($copy,true);}
            $fields=['done','champion','promoted','relegated','final_ranking','initial_ranking','initial_group_ranking','state_phase_rank','state_phase_group_rank','state_phase_groups','phase_bonus','program_decision_keys','venue_decision_keys','state_phase_skipped','secondary_merits','state_stage','phase','phase_start','phase_end','groups','state_phase_winners','state_phase_losers','state_phase_ties','administrative_pending'];
            if($isCertified)$fields=array_merge($fields,['runner_up','partial_certification']);
            foreach($fields as $f){
                if(!array_key_exists($f,$copy)&&!array_key_exists($f,$t))continue;
                if(array_key_exists($f,$copy)!==array_key_exists($f,$t))return false;
                $v1=$copy[$f];$v2=$t[$f];
                if(is_array($v1)&&is_array($v2)){
                    if(array_is_list($v1)){
                        if($v1!==$v2)return false;
                    }else{
                        if($v1!=$v2||StateResultProof::digest($v1)!==StateResultProof::digest($v2))return false;
                    }
                }else{
                    if($v1!==$v2)return false;
                }
            }
            if($isCertified){
                $p=$t['partial_certification'];
                if(!is_int($p['schema']??null)||$p['schema']!==1||($p['version']??null)!=='certified-outcomes-v1'||($p['competition']??null)!==$c['id']||($p['edition']??null)!==$c['edition']||($p['homologated']??null)!==false||($p['pending_ranking']??null)!==true||($p['pending_national_admission']??null)!==true)return false;
                if(!isset($p['certified_relegated'])||!is_array($p['certified_relegated'])||!array_is_list($p['certified_relegated']))return false;
                $expectedRelegated=$copy['partial_certification']['certified_relegated']??null;
                if($p['certified_relegated']!==$expectedRelegated)return false;
                if(!is_array($t['relegated']??null)||!array_is_list($t['relegated'])||$t['relegated']!==$expectedRelegated)return false;
                if(($p['certified_champion']??null)!==$copy['champion']||($p['certified_runner_up']??null)!==$copy['runner_up']||$p['certified_champion']===$p['certified_runner_up'])return false;
                if(($t['champion']??null)!==$copy['champion']||($t['runner_up']??null)!==$copy['runner_up'])return false;
                $expectedRelegationCount=$c['state_engine']['closure']['relegation']??0;
                if(count($p['certified_relegated'])!==$expectedRelegationCount||count(array_unique($p['certified_relegated']))!==$expectedRelegationCount)return false;
                if(array_diff($p['certified_relegated'],$c['teams'])||in_array($p['certified_champion'],$p['certified_relegated'],true)||in_array($p['certified_runner_up'],$p['certified_relegated'],true))return false;
                $evidence=$t['administrative_pending']['evidence']??null;if(!is_array($evidence))return false;
                $contractDigest=StateResultProof::digest($c['state_engine']);
                $resultsDigest=self::resultsDigest($t['matches']);
                $receiptDigest=StateResultProof::digest($p);
                if(($p['contract_digest']??null)!==$contractDigest||($evidence['contract_digest']??null)!==$contractDigest)return false;
                if(($p['results_digest']??null)!==$resultsDigest||($evidence['results_digest']??null)!==$resultsDigest)return false;
                if(($evidence['partial_certification']??null)!==$receiptDigest)return false;
                if(($t['administrative_pending']['key']??null)!==StateResultProof::digest($evidence))return false;
            }
            $decisionKeys=array_keys($t['program_decisions']??[]);$usedKeys=array_keys($copy['program_decision_keys']??[]);sort($decisionKeys);sort($usedKeys);$venueKeys=array_keys($t['venue_decisions']??[]);$usedVenues=array_keys($copy['venue_decision_keys']??[]);sort($venueKeys);sort($usedVenues);return count($seen)===count($saved)&&$decisionKeys===$usedKeys&&$venueKeys===$usedVenues;
        }catch(RuntimeException|LogicException|TypeError $e){return false;}
    }

}
