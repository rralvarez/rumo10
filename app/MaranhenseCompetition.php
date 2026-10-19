<?php
declare(strict_types=1);

/** Opt-in MA2026. An unresolved permanence tie blocks homologation, never invents penalties. */
final class MaranhenseCompetition {
    public static function validate(array $c,array $teams):void {
        $r=$c['state_engine'];$l=$r['league']??[];$groups=$l['groups']??[];
        if(($r['version']??null)!==ConfiguredStateCompetition::MARANHENSE_VERSION||$teams!==$c['teams']||count($teams)!==8||count(array_unique($teams))!==8||$groups!==[$teams]||($l['rounds']??null)!==7||($l['return']??null)!==false)throw new RuntimeException('Contrato Maranhense inválido.');
        if(($r['tiebreakers']??null)!==['points','wins','gd','gf','h2h_gd_two','red','yellow','lots'])throw new RuntimeException('Desempates Maranhenses divergentes.');
        $seen=[];$busy=[];$played=array_fill_keys($teams,0);
        foreach($l['fixtures']??[] as $fixture){if(count($fixture)!==3)throw new RuntimeException('Tabela inválida.');[$round,$home,$away]=$fixture;$pair=[$home,$away];sort($pair);$key=implode('|',$pair);if(!is_int($round)||$round<1||$round>7||$home===$away||!isset($played[$home],$played[$away])||isset($seen[$key],$busy[$round][$home])||isset($seen[$key])||isset($busy[$round][$home])||isset($busy[$round][$away]))throw new RuntimeException('Adversário/mando/rodada inválido.');$seen[$key]=true;$busy[$round][$home]=$busy[$round][$away]=true;$played[$home]++;$played[$away]++;}
        if(count($seen)!==28||array_filter($played,fn($n)=>$n!==7))throw new RuntimeException('Tabela incompleta.');
        $known=['liga'=>count($teams)];$names=[];
        foreach($r['barriers']??[] as $barrier){if(!$barrier)throw new RuntimeException('Barreira vazia.');$new=[];
            foreach($barrier as $p){$name=$p['name']??'';if(!$name||isset($known[$name],$names[$name])||isset($known[$name])||isset($new[$name])||!in_array($p['legs']??null,[1,2],true)||!in_array($p['tie_rule']??null,['penalties','pending_federation'],true)||!is_array($p['home_phases']??null)||!$p['home_phases']||array_diff($p['home_phases'],array_keys($known)))throw new RuntimeException('Fase v5 inválida.');
                $entries=$p['entries']??[];if(count($entries)<2||count($entries)%2)throw new RuntimeException('Participantes da fase ausentes.');
                foreach($entries as $entry){self::validateEntry($entry,$known,$groups);if(isset($entry['rank_phases'])&&(!$entry['rank_phases']||array_diff($entry['rank_phases'],array_keys($known))))throw new RuntimeException('Campanha de resemeadura inválida.');}
                $indices=[];foreach($p['pairs']??[] as $pair){if(count($pair)!==2||$pair[0]===$pair[1])throw new RuntimeException('Par v5 inválido.');foreach($pair as $index){if(!is_int($index)||$index<0||$index>=count($entries))throw new RuntimeException('Índice v5 inválido.');$indices[]=$index;}}
                sort($indices);if($indices!==range(0,count($entries)-1))throw new RuntimeException('Participação duplicada/ausente na fase.');$new[$name]=count($entries)/2;
            }$known+=$new;
        }
        if(empty($r['barriers'])||empty($r['ranking'])||empty($r['title_phase'])||!isset($known[$r['title_phase']])||$known[$r['title_phase']]!==1||($r['relegation_phase']??null)!=='permanencia')throw new RuntimeException('Encerramento v5 incompleto.');
        foreach($r['ranking'] as $block)if(!in_array($block['from']??null,['winner','loser','initial'],true)||(in_array($block['from'],['winner','loser'],true)&&!isset($known[$block['phase']??'']))||empty($block['rank_phases'])||array_diff($block['rank_phases'],array_keys($known)))throw new RuntimeException('Ranking v5 inválido.');
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
        foreach($r['league']['fixtures'] as [$round,$home,$away])$matches[]=Schedule::match($round,$home,$away)+['phase'=>'liga','group'=>'1'];
        usort($matches,fn($a,$b)=>$a['round']<=>$b['round']);
        return ['id'=>$c['id'],'format'=>'configured_state','adapter_version'=>ConfiguredStateCompetition::MARANHENSE_VERSION,'teams'=>$teams,'phase'=>'liga','groups'=>$r['league']['groups'],'initial_groups'=>$r['league']['groups'],'matches'=>$matches,'phase_start'=>1,'phase_end'=>$r['league']['rounds'],'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'state_stage'=>0,'state_eliminated'=>[],'state_phase_winners'=>[],'state_phase_losers'=>[],'state_phase_ties'=>[]];
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

        if(isset($r['barriers'][$n['state_stage']])){
            $start=$n['phase_end'];$end=$start;$busy=[];$combined=[];
            foreach($r['barriers'][$n['state_stage']] as $p){$pool=self::pool($n,$p['entries']);$rank=array_flip(self::rank($n,$pool,$p['home_phases']));$pairs=[];
                foreach($p['pairs'] as [$a,$b]){$pair=[$pool[$a],$pool[$b]];if($rank[$pair[0]]>$rank[$pair[1]])$pair=array_reverse($pair);foreach($pair as $club){if(isset($busy[$club]))throw new RuntimeException('Clube em dois ramos simultâneos.');$busy[$club]=true;}$pairs[]=$pair;}
                $branch=['matches'=>[],'phase_end'=>$start];Competition::knockout($branch,$pairs,$p['name'],$p['legs'],$p['tie_rule']);$n['matches']=array_merge($n['matches'],$branch['matches']);$n['state_phase_ties'][$p['name']]=$branch['ties'];$combined=array_merge($combined,$pairs);$end=max($end,$branch['phase_end']);
            }
            $n['phase_start']=$start+1;$n['phase_end']=$end;$n['groups']=$combined;$n['phase']=$r['barriers'][$n['state_stage']][0]['name'];$n['ties']=[];$n['state_stage']++;usort($n['matches'],fn($a,$b)=>$a['round']<=>$b['round']);
        }else{
            if(isset($n['unresolved_permanence'])){$n['administrative_pending']=$n['unresolved_permanence'];$t=$n;return;}
            $ranking=[];foreach($r['ranking'] as $block){if($block['from']==='initial'){$ranking[]=$n['initial_ranking'][$block['index']];continue;}$clubs=$n['state_phase_'.($block['from']==='winner'?'winners':'losers')][$block['phase']];$ranking=array_merge($ranking,self::rank($n,$clubs,$block['rank_phases']));}
            if(count($ranking)!==count($n['teams'])||count(array_unique($ranking))!==count($ranking)||array_diff($n['teams'],$ranking))throw new RuntimeException('Classificação v5 incompleta/duplicada.');
            $n['final_ranking']=$ranking;$n['champion']=$n['state_phase_winners'][$r['title_phase']][0];if($ranking[0]!==$n['champion'])throw new RuntimeException('Título diverge do ranking.');$n['relegated']=array_slice($ranking,-2);$n['done']=true;
        }$t=$n;
    }
    private static function resolve(array &$t,array $p,bool $proof):void {
        $view=['phase'=>$p['name'],'matches'=>$t['matches'],'ties'=>$t['state_phase_ties'][$p['name']]];
        if($p['tie_rule']==='pending_federation'){
            foreach($view['ties'] as &$tie){$points=0;$gd=0;$scores=[];foreach($view['matches'] as $m)if($m['phase']===$p['name']){if(isset($m['penalties']))throw new RuntimeException('Pênaltis não autorizados na permanência.');$delta=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=($delta<=>0)*3;$gd+=$delta;$scores[]=[$m['round'],$m['home'],$m['away'],$m['hg'],$m['ag']];}
                if(!$points){$clubs=[$tie['a'],$tie['b']];sort($clubs);$t['unresolved_permanence']=['key'=>StateResultProof::digest(['competition'=>$t['id'],'phase'=>$p['name'],'scores'=>$scores]),'phase'=>$p['name'],'teams'=>$clubs,'reason'=>'ma_permanence_tie_rule_not_verified','state'=>'awaiting_federation_decision','source'=>'REC MA2026 art.4 §2 and art.22; published RGC does not settle tie','simulation_only'=>true];return;}
                $tie['winner']=$points>0?$tie['a']:$tie['b'];
            }unset($tie);$w=array_column($view['ties'],'winner');$t['state_phase_ties'][$p['name']]=$view['ties'];$t['state_phase_winners'][$p['name']]=$w;$t['state_phase_losers'][$p['name']]=array_values(array_diff(array_merge(...array_map(fn($tie)=>[$tie['a'],$tie['b']],$view['ties'])),$w));return;
        }

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
        try{if(empty($t['done'])||($c['format']??null)!=='configured_state'||($t['format']??null)!=='configured_state'||($t['id']??null)!==$c['id']||$t['teams']!==$c['teams']||$t['adapter_version']!==ConfiguredStateCompetition::MARANHENSE_VERSION)return false;$lots=$t['lots']??[];$v=array_values($lots);sort($v);if($v!==range(0,count($t['teams'])-1)||array_diff(array_keys($lots),$t['teams']))return false;
            $key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);$saved=[];
            foreach($t['matches'] as $m){if($m['status']!=='finalizada'||isset($saved[$key($m)])||!is_int($m['hg'])||!is_int($m['ag'])||min($m['hg'],$m['ag'])<0||($m['phase']==='liga'&&isset($m['penalties'])))return false;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)if(!is_int($m[$f]??0)||($m[$f]??0)<0)return false;$saved[$key($m)]=$m;}
            $copy=self::create($c,$c['teams']);$copy['lots']=$lots;$seen=[];$guard=0;
            while(!$copy['done']){if(++$guard>20)return false;foreach($copy['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;foreach(['tie','group'] as $meta)if(($source[$meta]??null)!==($m[$meta]??null))return false;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];$seen[$key($m)]=true;}unset($m);self::next($copy,true);}
            foreach(['initial_groups','initial_ranking','initial_group_ranking','final_ranking','champion','relegated','promoted','state_stage','phase','phase_start','phase_end','groups','state_eliminated','state_phase_winners','state_phase_losers','state_phase_ties','unresolved_permanence','administrative_pending'] as $f)if(($copy[$f]??null)!=($t[$f]??null))return false;
            return count($seen)===count($saved);
        }catch(RuntimeException|LogicException|TypeError $e){return false;}
    }
}
