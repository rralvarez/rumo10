<?php
declare(strict_types=1);

/** Opt-in v5: conditional permanence and campaign reseeding. v1–v4 never enter here. */
final class ConditionalStateCompetition {
    public static function validate(array $c,array $teams):void {
        $r=$c['state_engine'];$l=$r['league']??[];$groups=$l['groups']??[];
        if(($r['version']??null)!==ConfiguredStateCompetition::CONDITIONAL_VERSION||$teams!==($c['teams']??[])||count($teams)!==count(array_unique($teams))||count($groups)<2||array_merge(...$groups)!==$teams||array_filter($groups,fn($g)=>count($g)<2))throw new RuntimeException('Grupos intergrupos inválidos.');
        if(count($teams)!==12||count($groups)!==3||array_map('count',$groups)!==[4,4,4]||($l['rounds']??null)!==8||($l['home_games']??null)!==4||($l['return']??null)!==false)throw new RuntimeException('Contrato condicional requer edição Goiana de doze clubes.');
        $criteria=$r['tiebreakers']??[];
        if(!$criteria||$criteria[0]!=='points'||end($criteria)!=='lots'||count(array_unique($criteria))!==count($criteria)||array_diff($criteria,['points','wins','gd','gf','h2h_points_two','h2h_gd_two','red','yellow','lots']))throw new RuntimeException('Desempates v5 não suportados.');
        if(!is_int($l['rounds']??null)||$l['rounds']<1||!is_int($l['home_games']??null)||$l['home_games']<0||!is_bool($l['return']??null))throw new RuntimeException('Quantidade de jogos intergrupos ausente.');
        $groupOf=[];foreach($groups as $i=>$g)foreach($g as $club)$groupOf[$club]=$i;
        $seen=[];$busy=[];$homeCounts=array_fill_keys($teams,0);$awayCounts=$homeCounts;$rounds=[];
        foreach($l['fixtures']??[] as $f){
            if(!is_array($f)||count($f)!==3)throw new RuntimeException('Tabela intergrupos inválida.');
            [$round,$home,$away]=$f;
            if(!is_int($round)||$round<1||$round>$l['rounds']||!isset($groupOf[$home],$groupOf[$away])||$groupOf[$home]===$groupOf[$away]||isset($busy[$round][$home])||isset($busy[$round][$away])||isset($seen[$home.'|'.$away]))throw new RuntimeException('Adversário/mando/rodada intergrupos inválido.');
            if(!$l['return']&&isset($seen[$away.'|'.$home]))throw new RuntimeException('Confronto intergrupos repetido.');
            $seen[$home.'|'.$away]=true;$busy[$round][$home]=$busy[$round][$away]=true;$homeCounts[$home]++;$awayCounts[$away]++;$rounds[$round]=true;
        }
        foreach($teams as $a)foreach($teams as $b)if($groupOf[$a]!==$groupOf[$b]){
            if($l['return']?(!isset($seen[$a.'|'.$b])):(!isset($seen[$a.'|'.$b])&&!isset($seen[$b.'|'.$a])))throw new RuntimeException('Adversário intergrupos ausente.');
        }
        if(count($rounds)!==$l['rounds'])throw new RuntimeException('Rodada intergrupos ausente.');
        foreach($teams as $club)if($homeCounts[$club]!==$l['home_games']||$homeCounts[$club]+$awayCounts[$club]!==$l['rounds'])throw new RuntimeException('Quantidade de jogos/mandos divergente.');
        $known=['liga'=>count($teams)];$names=[];
        foreach($r['barriers']??[] as $barrier){if(!$barrier)throw new RuntimeException('Barreira vazia.');$new=[];
            foreach($barrier as $p){$name=$p['name']??'';if(!$name||isset($known[$name],$names[$name])||isset($known[$name])||isset($new[$name])||!in_array($p['legs']??null,[1,2],true)||($p['tie_rule']??null)!=='penalties'||!is_array($p['home_phases']??null)||!$p['home_phases']||array_diff($p['home_phases'],array_keys($known)))throw new RuntimeException('Fase v5 inválida.');
                $entries=$p['entries']??[];if(count($entries)<2||count($entries)%2)throw new RuntimeException('Participantes da fase ausentes.');
                foreach($entries as $entry){self::validateEntry($entry,$known,$groups);if(isset($entry['rank_phases'])&&(!$entry['rank_phases']||array_diff($entry['rank_phases'],array_keys($known))))throw new RuntimeException('Campanha de resemeadura inválida.');}
                $indices=[];foreach($p['pairs']??[] as $pair){if(count($pair)!==2||$pair[0]===$pair[1])throw new RuntimeException('Par v5 inválido.');foreach($pair as $index){if(!is_int($index)||$index<0||$index>=count($entries))throw new RuntimeException('Índice v5 inválido.');$indices[]=$index;}}
                sort($indices);if($indices!==range(0,count($entries)-1))throw new RuntimeException('Participação duplicada/ausente na fase.');$new[$name]=count($entries)/2;
            }$known+=$new;
        }
        if(empty($r['barriers'])||empty($r['ranking'])||empty($r['title_phase'])||!isset($known[$r['title_phase']])||$known[$r['title_phase']]!==1||StateResultProof::digest($r['conditional_permanence']??[])!==StateResultProof::digest(['phase'=>'permanencia','initial_positions'=>[9,10],'skip_above'=>6,'omit_return_above'=>3]))throw new RuntimeException('Encerramento v5 incompleto.');
        foreach($r['ranking'] as $block)if(!in_array($block['from']??null,['winner','loser','initial','permanence'],true)||(in_array($block['from'],['winner','loser'],true)&&!isset($known[$block['phase']??'']))||empty($block['rank_phases'])||array_diff($block['rank_phases'],array_keys($known)))throw new RuntimeException('Ranking v5 inválido.');
    }
    private static function validateEntry(array $e,array $known,array $groups):void {
        if(!is_int($e['index']??null)||$e['index']<0)throw new RuntimeException('Índice de origem ausente.');
        if(($e['from']??null)==='initial'){if($e['index']>=array_sum(array_map('count',$groups)))throw new RuntimeException('Classificação inicial inválida.');return;}
        if(($e['from']??null)==='group'){if(!is_int($e['group']??null)||!isset($groups[$e['group']][$e['index']]))throw new RuntimeException('Grupo de origem inválido.');return;}
        if(($e['from']??null)==='group_tail'){if(!is_int($e['skip']??null)||$e['skip']<1||$e['skip']>=min(array_map('count',$groups)))throw new RuntimeException('Corte de grupo inválido.');return;}
        if(!in_array($e['from']??null,['winner','loser'],true)||!isset($known[$e['phase']??''])||$e['phase']==='liga'||$e['index']>=$known[$e['phase']])throw new RuntimeException('Origem v5 inválida.');
    }
    public static function create(array $c,array $teams):array {
        self::validate($c,$teams);$lots=$teams;shuffle($lots);$r=$c['state_engine'];$matches=[];
        foreach($r['league']['fixtures'] as [$round,$home,$away])$matches[]=Schedule::match($round,$home,$away)+['phase'=>'liga','group'=>'intergrupos'];
        usort($matches,fn($a,$b)=>$a['round']<=>$b['round']);
        return ['id'=>$c['id'],'format'=>'configured_state','adapter_version'=>ConfiguredStateCompetition::CONDITIONAL_VERSION,'teams'=>$teams,'phase'=>'liga','groups'=>$r['league']['groups'],'initial_groups'=>$r['league']['groups'],'matches'=>$matches,'phase_start'=>1,'phase_end'=>$r['league']['rounds'],'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'state_stage'=>0,'state_eliminated'=>[],'state_phase_winners'=>[],'state_phase_losers'=>[],'state_phase_ties'=>[]];
    }
    public static function rank(array $t,array $clubs,array $phases):array {
        $copy=$t;$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],$phases,true)));
        return array_column(ConfiguredStateCompetition::standings($copy,$clubs,null,true),'team');
    }
    private static function pool(array $t,array $entries):array {
        $out=[];foreach($entries as $e){
            if($e['from']==='initial')$source=$t['initial_ranking'];
            elseif($e['from']==='group')$source=$t['initial_group_ranking'][$e['group']];
            elseif($e['from']==='group_tail'){$tail=[];foreach($t['initial_group_ranking'] as $g)$tail=array_merge($tail,array_slice($g,$e['skip']));$source=self::rank($t,$tail,['liga']);}
            else $source=$t['state_phase_'.($e['from']==='winner'?'winners':'losers')][$e['phase']]??[];
            if(isset($e['rank_phases']))$source=self::rank($t,$source,$e['rank_phases']);
            if(!isset($source[$e['index']]))throw new RuntimeException('Classificado v5 ausente.');$out[]=$source[$e['index']];
        }if(count($out)!==count(array_unique($out)))throw new RuntimeException('Clube duplicado no ramo v5.');return $out;
    }
    public static function next(array &$t,bool $proof=false):void {
        if($t['done'])return;$c=Competition::catalog()[$t['id']];self::validate($c,$t['teams']);$r=$c['state_engine'];$n=$t;
        if(array_filter($n['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Ramo obrigatório ainda incompleto.');
        if($n['state_stage']===0){$n['initial_ranking']=self::rank($n,$n['teams'],['liga']);$n['initial_group_ranking']=array_map(fn($g)=>self::rank($n,$g,['liga']),$n['initial_groups']);}
        else foreach($r['barriers'][$n['state_stage']-1] as $p)self::resolve($n,$p,$proof);
        self::permanence($n,$n['state_stage'],$proof);
        if(isset($r['barriers'][$n['state_stage']])){
            $start=$n['phase_end'];$end=$start;$busy=[];$combined=[];
            foreach($r['barriers'][$n['state_stage']] as $p){$pool=self::pool($n,$p['entries']);$rank=array_flip(self::rank($n,$pool,$p['home_phases']));$pairs=[];
                foreach($p['pairs'] as [$a,$b]){$pair=[$pool[$a],$pool[$b]];if($rank[$pair[0]]>$rank[$pair[1]])$pair=array_reverse($pair);foreach($pair as $club){if(isset($busy[$club]))throw new RuntimeException('Clube em dois ramos simultâneos.');$busy[$club]=true;}$pairs[]=$pair;}
                $branch=['matches'=>[],'phase_end'=>$start];Competition::knockout($branch,$pairs,$p['name'],$p['legs'],$p['tie_rule']);$n['matches']=array_merge($n['matches'],$branch['matches']);$n['state_phase_ties'][$p['name']]=$branch['ties'];$combined=array_merge($combined,$pairs);$end=max($end,$branch['phase_end']);
            }
            if($n['state_stage']===0&&$n['permanence']['state']==='first_pending'){$n['matches'][]=Schedule::match($start+1,$n['initial_ranking'][10],$n['initial_ranking'][9])+['phase'=>'permanencia','group'=>'permanencia'];$combined[]=[$n['initial_ranking'][10],$n['initial_ranking'][9]];$end=max($end,$start+1);}
            if($n['state_stage']===1&&$n['permanence']['state']==='return_pending'){$n['matches'][]=Schedule::match($start+1,$n['initial_ranking'][9],$n['initial_ranking'][10])+['phase'=>'permanencia','group'=>'permanencia'];$combined[]=[$n['initial_ranking'][9],$n['initial_ranking'][10]];$end=max($end,$start+1);}
            $n['phase_start']=$start+1;$n['phase_end']=$end;$n['groups']=$combined;$n['phase']=$r['barriers'][$n['state_stage']][0]['name'];$n['ties']=[];$n['state_stage']++;usort($n['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
        }else{
            $ranking=[];foreach($r['ranking'] as $block){if($block['from']==='initial'){$ranking[]=$n['initial_ranking'][$block['index']];continue;}if($block['from']==='permanence'){$ranking=array_merge($ranking,$n['permanence']['ranking']);continue;}$clubs=$n['state_phase_'.($block['from']==='winner'?'winners':'losers')][$block['phase']];$ranking=array_merge($ranking,self::rank($n,$clubs,$block['rank_phases']));}
            if(count($ranking)!==count($n['teams'])||count(array_unique($ranking))!==count($ranking)||array_diff($n['teams'],$ranking))throw new RuntimeException('Classificação v5 incompleta/duplicada.');
            $n['final_ranking']=$ranking;$n['champion']=$n['state_phase_winners'][$r['title_phase']][0];if($ranking[0]!==$n['champion'])throw new RuntimeException('Título diverge do ranking.');$n['relegated']=array_slice($ranking,-2);$n['done']=true;
        }$t=$n;
    }
    /** REC arts.19–20: campaign points, not a conventional aggregate tie. */
    private static function permanence(array &$t,int $stage,bool $proof):void {
        $clubs=[$t['initial_ranking'][9],$t['initial_ranking'][10]];
        $copy=$t;$copy['matches']=array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],['liga','permanencia'],true)));
        $rows=ConfiguredStateCompetition::standings($copy,$clubs,null,true);
        $points=array_column($rows,'points','team');$gap=$points[$clubs[0]]-$points[$clubs[1]];
        $played=count(array_filter($t['matches'],fn($m)=>$m['phase']==='permanencia'));
        foreach($t['matches'] as $m)if($m['phase']==='permanencia'&&isset($m['penalties']))throw new RuntimeException('Permanência não admite pênaltis.');
        if($stage===0){$t['permanence']=['state'=>$gap>6?'closed':'first_pending','reason'=>$gap>6?'initial_gap_above_six':'initial_gap_at_most_six','initial_gap'=>$gap,'ranking'=>$gap>6?$clubs:[]];return;}
        if(($t['permanence']['state']??null)==='closed')return;
        if($stage===1){if($played!==1)throw new RuntimeException('Ida de permanência ausente.');$closed=$gap>3;$t['permanence']['state']=$closed?'closed':'return_pending';$t['permanence']['reason']=$closed?'return_omitted_gap_above_three':'return_required';$t['permanence']['after_first_gap']=$gap;if($closed)$t['permanence']['ranking']=$clubs;return;}
        if($stage===2){if($played!==2)throw new RuntimeException('Volta de permanência ausente.');$t['permanence']['state']='closed';$t['permanence']['reason']='two_matches_campaign_tiebreakers';$t['permanence']['ranking']=array_column($rows,'team');}
    }
    private static function resolve(array &$t,array $p,bool $proof):void {
        $view=['phase'=>$p['name'],'matches'=>$t['matches'],'ties'=>$t['state_phase_ties'][$p['name']]];
        if($proof)foreach($view['ties'] as $i=>&$tie){$points=0;$gd=0;$indices=[];
            foreach($view['matches'] as $j=>$m)if($m['phase']===$p['name']&&($m['tie']??null)===$i){$delta=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=($delta<=>0)*3;$gd+=$delta;$indices[]=$j;}
            if(!$indices)throw new RuntimeException('Prova de confronto ausente.');$last=end($indices);
            foreach($indices as $j)if(isset($view['matches'][$j]['penalties'])&&($j!==$last||$points||$gd))throw new RuntimeException('Pênaltis indevidos.');
            if($points||$gd)$tie['winner']=($points?:$gd)>0?$tie['a']:$tie['b'];
            else{$m=$view['matches'][$last];$pens=$m['penalties']??null;if(!is_array($pens)||count($pens)!==2||!is_int($pens[0])||!is_int($pens[1])||min($pens)<0||$pens[0]===$pens[1])throw new RuntimeException('Pênaltis não comprovados.');$tie['winner']=$pens[0]>$pens[1]?$m['home']:$m['away'];}
        }unset($tie);
        $w=Competition::resolve($view);$t['matches']=$view['matches'];$t['state_phase_ties'][$p['name']]=$view['ties'];$t['state_phase_winners'][$p['name']]=$w;$t['state_phase_losers'][$p['name']]=array_values(array_diff(array_merge(...array_map(fn($tie)=>[$tie['a'],$tie['b']],$view['ties'])),$w));
    }
    public static function verify(array $c,array $t):bool {
        try{if(empty($t['done'])||($c['format']??null)!=='configured_state'||($t['format']??null)!=='configured_state'||($t['id']??null)!==$c['id']||$t['teams']!==$c['teams']||$t['adapter_version']!==ConfiguredStateCompetition::CONDITIONAL_VERSION)return false;$lots=$t['lots']??[];$v=array_values($lots);sort($v);if($v!==range(0,count($t['teams'])-1)||array_diff(array_keys($lots),$t['teams']))return false;
            $key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);$saved=[];
            foreach($t['matches'] as $m){if($m['status']!=='finalizada'||isset($saved[$key($m)])||!is_int($m['hg'])||!is_int($m['ag'])||min($m['hg'],$m['ag'])<0||($m['phase']==='liga'&&isset($m['penalties'])))return false;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)if(!is_int($m[$f]??0)||($m[$f]??0)<0)return false;$saved[$key($m)]=$m;}
            $copy=self::create($c,$c['teams']);$copy['lots']=$lots;$seen=[];$guard=0;
            while(!$copy['done']){if(++$guard>20)return false;foreach($copy['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;foreach(['tie','group'] as $meta)if(($source[$meta]??null)!==($m[$meta]??null))return false;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];$seen[$key($m)]=true;}unset($m);self::next($copy,true);}
            foreach(['initial_groups','initial_ranking','initial_group_ranking','final_ranking','champion','relegated','promoted','state_stage','phase','phase_start','phase_end','groups','state_eliminated','state_phase_winners','state_phase_losers','state_phase_ties','permanence'] as $f)if(($copy[$f]??null)!=($t[$f]??null))return false;
            return count($seen)===count($saved);
        }catch(RuntimeException|LogicException|TypeError $e){return false;}
    }
}
