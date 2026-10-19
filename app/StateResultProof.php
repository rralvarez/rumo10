<?php
declare(strict_types=1);

/** Rebuild an existing supported state adapter, using saved shootouts, never new results. */
final class StateResultProof {
    public static function digest(array $data):string {
        $sort=function($v)use(&$sort){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=$sort($x);return $v;};
        return hash('sha256',json_encode($sort($data),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public static function verify(array $definition,array $t):bool {
        if(($definition['state_engine']['version']??null)===ConfiguredStateCompetition::MARANHENSE_VERSION)return MaranhenseCompetition::verify($definition,$t);
        if(($definition['state_engine']['version']??null)===ConfiguredStateCompetition::CONDITIONAL_VERSION)return ConditionalStateCompetition::verify($definition,$t);
        if(($definition['state_engine']['version']??null)===ConfiguredStateCompetition::INTERGROUP_VERSION)return IntergroupStateCompetition::verify($definition,$t);
        $configured=($definition['format']??null)==='configured_state';
        if(!in_array($definition['format']??null,['alagoas','configured_state'],true)||($t['format']??null)!==$definition['format']||empty($t['done'])||$t['teams']!==$definition['teams'])return false;
        if($configured&&($t['adapter_version']??null)!==($definition['state_engine']['version']??null))return false;
        $lots=array_values($t['lots']??[]);sort($lots);if($lots!==range(0,count($t['teams'])-1)||array_diff($t['teams'],array_keys($t['lots'])))return false;
        $key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);$saved=[];
        $leaguePhases=$configured?array_column(array_filter($definition['state_engine']['stages'],fn($s)=>$s['type']==='league'),'name'):['liga'];
        foreach($t['matches'] as $m){if($m['status']!=='finalizada'||isset($saved[$key($m)])||!is_int($m['hg'])||!is_int($m['ag'])||$m['hg']<0||$m['ag']<0||(in_array($m['phase'],$leaguePhases,true)&&isset($m['penalties'])))return false;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)if(!is_int($m[$f]??0)||($m[$f]??0)<0)return false;$saved[$key($m)]=$m;}
        foreach($t['matches'] as $m)if(isset($m['administrative_key'])){
            $decision=$t['administrative_decisions'][$m['administrative_key']]??null;if(!$decision)return false;
            if($m['hg']!==$m['ag']){if(isset($m['penalties']))return false;}
            else{$penalties=$m['penalties']??null;if(($decision['draw_rule']??null)!=='penalties'||!is_array($penalties)||count($penalties)!==2||!is_int($penalties[0])||!is_int($penalties[1])||min($penalties)<0||$penalties[0]===$penalties[1])return false;}
        }
        try{$r=$configured?ConfiguredStateCompetition::create($definition,$t['teams']):AlagoasCompetition::create($definition,$t['teams']);$r['lots']=$t['lots'];$seen=[];
            while(!$r['done']){
                foreach($r['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;$seen[$key($m)]=true;if(($definition['state_engine']['version']??null)===ConfiguredStateCompetition::ADMIN_VERSION)foreach(['neutral','venue','simultaneous_group','kickoff','administrative_key','administrative_decision_digest'] as $meta)if(($m[$meta]??null)!==($source[$meta]??null))return false;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];}unset($m);
                foreach($r['ties'] as $i=>&$tie){$points=0;$gd=0;$indices=[];foreach($r['matches'] as $j=>$m)if($m['phase']===$r['phase']&&($m['tie']??null)===$i){$d=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=$d>0?3:($d<0?-3:0);$gd+=$d;$indices[]=$j;}
                    if(!$indices)return false;$last=end($indices);foreach($indices as $j)if(isset($r['matches'][$j]['penalties'])&&($j!==$last||$points||$gd))return false;
                    if($points||$gd)$tie['winner']=($points?:$gd)>0?$tie['a']:$tie['b'];
                    elseif(($tie['rule']??'penalties')==='advantage'){if(isset($r['matches'][$last]['penalties']))return false;$tie['winner']=$tie['a'];}
                    else{$m=$r['matches'][$last];$p=$m['penalties']??null;if(!is_array($p)||count($p)!==2||!is_int($p[0])||!is_int($p[1])||min($p)<0||$p[0]===$p[1])return false;$tie['winner']=$p[0]>$p[1]?$m['home']:$m['away'];}
                }unset($tie);if($configured){ConfiguredStateCompetition::next($r);if(isset($r['administrative_pending'])){$decision=$t['administrative_decisions'][$r['administrative_pending']['key']]??null;if(!$decision)return false;StateAdministration::replay($r,$decision);}}else AlagoasCompetition::next($r);
            }
            if($configured)foreach(['initial_groups','initial_ranking','state_stage','state_eliminated','phase'] as $f)if(($r[$f]??null)!==($t[$f]??null))return false;
            // MySQL JSON may reorder phase object keys; winner list order remains significant.
            if($configured&&in_array($definition['state_engine']['version']??null,[ConfiguredStateCompetition::ROUTED_VERSION,ConfiguredStateCompetition::ADMIN_VERSION],true)&&($r['state_phase_winners']??null)!=($t['state_phase_winners']??null))return false;
            if(($definition['state_engine']['version']??null)===ConfiguredStateCompetition::ADMIN_VERSION&&($r['administrative_decisions']??[])!=($t['administrative_decisions']??[]))return false;
            return count($seen)===count($saved)&&$r['final_ranking']===$t['final_ranking']&&$r['champion']===$t['champion']&&$r['relegated']===$t['relegated']&&$r['promoted']===$t['promoted'];
        }catch(RuntimeException|LogicException $e){return false;}
    }
}
