<?php
declare(strict_types=1);

/** Rebuild an existing supported state adapter, using saved shootouts, never new results. */
final class StateResultProof {
    public static function digest(array $data):string {
        $sort=function($v)use(&$sort){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=$sort($x);return $v;};
        return hash('sha256',json_encode($sort($data),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public static function verify(array $definition,array $t):bool {
        if(($definition['format']??null)!=='alagoas'||empty($t['done'])||$t['teams']!==$definition['teams'])return false;
        $lots=array_values($t['lots']??[]);sort($lots);if($lots!==range(0,count($t['teams'])-1)||array_diff($t['teams'],array_keys($t['lots'])))return false;
        $key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);$saved=[];
        foreach($t['matches'] as $m){if($m['status']!=='finalizada'||isset($saved[$key($m)])||!is_int($m['hg'])||!is_int($m['ag'])||$m['hg']<0||$m['ag']<0||($m['phase']==='liga'&&isset($m['penalties'])))return false;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)if(!is_int($m[$f]??0)||($m[$f]??0)<0)return false;$saved[$key($m)]=$m;}
        try{$r=AlagoasCompetition::create($definition,$t['teams']);$r['lots']=$t['lots'];$seen=[];
            while(!$r['done']){
                foreach($r['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;$seen[$key($m)]=true;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];}unset($m);
                foreach($r['ties'] as $i=>&$tie){$points=0;$gd=0;$indices=[];foreach($r['matches'] as $j=>$m)if($m['phase']===$r['phase']&&($m['tie']??null)===$i){$d=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=$d>0?3:($d<0?-3:0);$gd+=$d;$indices[]=$j;}
                    if(!$indices)return false;$last=end($indices);foreach($indices as $j)if(isset($r['matches'][$j]['penalties'])&&($j!==$last||$points||$gd))return false;
                    if($points||$gd)$tie['winner']=($points?:$gd)>0?$tie['a']:$tie['b'];
                    else{$m=$r['matches'][$last];$p=$m['penalties']??null;if(!is_array($p)||count($p)!==2||!is_int($p[0])||!is_int($p[1])||min($p)<0||$p[0]===$p[1])return false;$tie['winner']=$p[0]>$p[1]?$m['home']:$m['away'];}
                }unset($tie);AlagoasCompetition::next($r);
            }
            return count($seen)===count($saved)&&$r['final_ranking']===$t['final_ranking']&&$r['champion']===$t['champion']&&$r['relegated']===$t['relegated'];
        }catch(RuntimeException|LogicException $e){return false;}
    }
}
