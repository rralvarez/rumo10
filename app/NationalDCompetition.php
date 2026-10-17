<?php
declare(strict_types=1);
/** Opt-in D 2026 sports adapter. Not a certificate of BID or future admission. */
final class NationalDCompetition {
    public static function create(array $c,array $teams):array {
        $groups=$c['groups'];$flat=array_merge(...$groups);$a=$flat;$b=$teams;sort($a);sort($b);
        if(($c['edition']??null)!==2026||count($teams)!==96||count(array_unique($teams))!==96||count($groups)!==16||array_filter($groups,fn($g)=>count($g)!==6)||$a!==$b)throw new RuntimeException('Inscrição/grupos D 2026 incompletos.');
        $ref=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-d-sports-2026.json'),true,512,JSON_THROW_ON_ERROR);
        if($groups!==$ref['groups']||($c['d_reference']??null)!==$ref['id'])throw new RuntimeException('Grupos D sem contrato comprovado.');
        $lots=$teams;shuffle($lots);$t=['id'=>$c['id'],'teams'=>$teams,'format'=>'national_d','phase'=>'liga','groups'=>$groups,'initial_groups'=>$groups,'matches'=>[],'phase_start'=>1,'phase_end'=>10,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'eliminated'=>[]];
        foreach($groups as $i=>$group)foreach(Schedule::generate($group,true) as $m)$t['matches'][]=$m+['phase'=>'liga','group'=>(string)($i+1)];usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);return $t;
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null):array {
        if($phase==='liga'){$copy=$t;foreach($copy['matches'] as &$m)if($m['phase']==='liga')$m['phase']='quadrangular';unset($m);return NationalCCompetition::standings($copy,$teams,'quadrangular');}
        return NationalCCompetition::standings($t,$teams,$phase);
    }
    public static function order(array $t,array $teams):array {
        // Cumulative totals include opponents already eliminated, not only the candidates.
        $rows=array_column(NationalCCompetition::standings($t),null,'team');
        usort($teams,function($a,$b)use($rows,$t){foreach(['points','wins','gd','gf'] as $f)if($cmp=$rows[$b][$f]<=>$rows[$a][$f])return $cmp;foreach(['red','yellow'] as $f)if($cmp=$rows[$a][$f]<=>$rows[$b][$f])return $cmp;return $t['lots'][$a]<=>$t['lots'][$b];});return $teams;
    }
    private static function pairs(array &$t,array $pairs,string $phase,bool $seedHost=true):void {
        if($seedHost)foreach($pairs as &$pair)$pair=self::order($t,$pair);unset($pair);
        Competition::knockout($t,$pairs,$phase);
    }
    public static function resolve(array &$t):array {
        $out=[];foreach($t['ties'] as $i=>&$tie){if($tie['winner']===null){$gd=0;$last=null;foreach($t['matches'] as $j=>$m)if($m['phase']===$t['phase']&&($m['tie']??null)===$i){if($m['status']!=='finalizada')throw new RuntimeException('Confronto D incompleto.');$gd+=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$last=$j;}
            if(!empty($tie['access_playoff'])){$points=0;foreach($t['matches'] as $m)if($m['phase']===$t['phase']&&($m['tie']??null)===$i){$d=($m['home']===$tie['a']?1:-1)*($m['hg']-$m['ag']);$points+=$d>0?3:($d<0?-3:0);}$tie['winner']=($points?:$gd)>=0?$tie['a']:$tie['b'];}
            elseif($gd)$tie['winner']=$gd>0?$tie['a']:$tie['b'];
            else{$m=&$t['matches'][$last];if(isset($m['penalties'])){[$h,$a]=$m['penalties'];if(!is_int($h)||!is_int($a)||$h<0||$a<0||$h===$a)throw new RuntimeException('Pênaltis D inválidos.');$tie['winner']=$h>$a?$m['home']:$m['away'];}else{$tie['winner']=random_int(0,1)?$tie['a']:$tie['b'];$lose=random_int(2,4);$m['penalties']=$m['home']===$tie['winner']?[$lose+1,$lose]:[$lose,$lose+1];}unset($m);}
        }$out[]=$tie['winner'];}unset($tie);return $out;
    }
    public static function next(array &$t):void {
        if($t['phase']==='liga'){$ranks=[];$eliminated=[];foreach($t['initial_groups'] as $group){$r=array_column(self::standings($t,$group,'liga'),'team');$ranks[]=$r;$eliminated=array_merge($eliminated,array_slice($r,4));}$t['initial_ranking']=self::order($t,$t['teams']);$t['eliminated']['liga']=self::order($t,$eliminated);$pairs=[];for($i=0;$i<16;$i+=2){[$a,$b]=[$ranks[$i],$ranks[$i+1]];$pairs=array_merge($pairs,[[$a[0],$b[3]],[$b[1],$a[2]],[$b[0],$a[3]],[$a[1],$b[2]]]);}self::pairs($t,$pairs,'segunda',false);return;}
        $phase=$t['phase'];$w=self::resolve($t);$played=array_merge(...array_map(fn($p)=>[$p['a'],$p['b']],$t['ties']));$losers=array_values(array_diff($played,$w));
        if($phase==='segunda'||$phase==='terceira'){$t['eliminated'][$phase]=self::order($t,$losers);$pairs=[];foreach(array_chunk($w,8) as $block)foreach([[0,5],[1,4],[2,7],[3,6]] as [$a,$b])$pairs[]=[$block[$a],$block[$b]];if($phase==='segunda')$t['retained_candidates']=$w;self::pairs($t,$pairs,$phase==='segunda'?'terceira':'oitavas');return;}
        if($phase==='oitavas'){$t['eliminated'][$phase]=self::order($t,$losers);$r=self::order($t,$w);$t['block_one']=$r;self::pairs($t,[[$r[0],$r[7]],[$r[3],$r[4]],[$r[1],$r[6]],[$r[2],$r[5]]],'quartas');return;}
        if($phase==='quartas'){$t['promoted']=$w;$r=self::order($t,$losers);$t['block_two']=$r;self::pairs($t,[[$w[0],$w[1]],[$w[2],$w[3]],[$r[0],$r[3]],[$r[1],$r[2]]],'semifinal');$t['ties'][2]['access_playoff']=true;$t['ties'][3]['access_playoff']=true;return;}
        if($phase==='semifinal'){$t['promoted']=array_merge($t['promoted'],array_slice($w,2));$t['playoff_winners']=self::order($t,array_slice($w,2));$t['playoff_losers']=self::order($t,array_diff($t['block_two'],$w));$t['semi_losers']=self::order($t,array_diff(array_slice($played,0,4),array_slice($w,0,2)));self::pairs($t,[[$w[0],$w[1]]],'final');return;}
        if($phase!=='final')throw new RuntimeException('Fase D desconhecida.');
        $t['champion']=$w[0];$t['final_ranking']=array_merge($w,$losers,$t['semi_losers'],$t['playoff_winners'],$t['playoff_losers'],$t['eliminated']['oitavas'],$t['eliminated']['terceira'],$t['eliminated']['segunda'],$t['eliminated']['liga']);
        if(count($t['final_ranking'])!==96||count(array_unique($t['final_ranking']))!==96||count(array_unique($t['promoted']))!==6)throw new RuntimeException('Classificação final D inconsistente.');$t['done']=true;
    }
    public static function result(array $t,string $club):array {
        if(!$t['done']||!in_array($club,$t['teams'],true))throw new RuntimeException('Resultado D sem prova.');
        return ['name'=>Competition::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'promoted'=>false,'promotion_merit'=>in_array($club,$t['promoted'],true),'access_pending'=>in_array($club,$t['promoted'],true),'relegated'=>false,'relegation_pending'=>false,'continuity'=>['valid'=>false,'reason'=>'next_edition_admission_pending'],'titles'=>[]];
    }
    /** Rebuild every bracket from finalized scores; never re-roll a recorded shootout. */
    public static function verify(array $t):bool {
        if(!$t['done']||count($t['matches'])!==610)return false;
        $saved=[];$key=fn($m)=>implode('|',[$m['phase'],$m['round'],$m['home'],$m['away']]);
        foreach($t['matches'] as $m){if(isset($saved[$key($m)])||$m['status']!=='finalizada')return false;$saved[$key($m)]=$m;}
        $r=self::create(Competition::catalog()[$t['id']],$t['teams']);$r['lots']=$t['lots'];
        while(!$r['done']){foreach($r['matches'] as &$m){if($m['status']==='finalizada')continue;$source=$saved[$key($m)]??null;if(!$source)return false;foreach(['status','hg','ag','home_red','away_red','home_yellow','away_yellow','penalties'] as $f)if(isset($source[$f]))$m[$f]=$source[$f];}unset($m);
            if($r['phase']==='semifinal')foreach($r['matches'] as $m)if($m['phase']==='semifinal'&&($m['tie']??0)>=2&&isset($m['penalties']))return false;
            self::next($r);
        }
        return $r['promoted']===$t['promoted']&&$r['final_ranking']===$t['final_ranking']&&$r['champion']===$t['champion'];
    }
}
