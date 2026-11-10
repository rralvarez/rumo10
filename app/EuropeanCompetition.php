<?php
declare(strict_types=1);
final class EuropeanCompetition {
    public static function create(array $c,array $teams):array {
        $lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'european','phase'=>'liga','groups'=>[$teams],'initial_groups'=>[$teams],'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'titles'=>[]];
        Competition::leaguePhase($t,[$teams],'liga',true);
        if($c['id']==='sc-1' || $c['id']==='sc-2') {
            $offset=$t['phase_end'];$extra=Schedule::generate($teams,$c['id']==='sc-2');
            foreach($extra as $m){$m['round']+=$offset;$t['matches'][]=$m+['phase'=>'liga','group'=>'1'];}
            $t['phase_end']=max(array_column($t['matches'],'round'));
        }
        return $t;
    }
    private static function eligible(array $t,array $ranking):array {
        return array_values(array_filter($ranking,fn($club)=>!(Competition::clubs()[$club]['reserve']??false)));
    }
    public static function next(array &$t):void {
        $c=Competition::catalog()[$t['id']];$system=$c['rules']['system'];$phase=$t['phase'];
        if($phase==='liga') {
            $r=Competition::rank($t,$t['teams'],'liga');$t['initial_ranking']=$r;$t['champion']=$r[0];
            $t['relegated']=array_slice($r,-$c['rules']['relegations']);if(!$c['rules']['relegations'])$t['relegated']=[];
            if($t['id']==='sc-1') {
                $t['regular_ranking']=$r;Competition::leaguePhase($t,[array_slice($r,0,6),array_slice($r,6)],'split',false);return;
            }
            if(str_ends_with($t['id'],'-1')) {
                if($system==='barrage')$t['barrage_candidate']=$r[count($r)-3];
                $t['done']=true;return;
            }
            $eligible=self::eligible($t,$r);$direct=$t['id']==='be-2'||$t['id']==='sc-2'?1:2;
            $t['promoted']=array_slice($eligible,0,$direct);$q=array_slice($eligible,$direct);
            if($system==='candidate'){$t['barrage_candidate']=$q[0];$t['done']=true;return;}
            if($system==='four_playoff'||$system==='belgian_playoff') {Competition::knockout($t,Competition::seededPairs(array_slice($q,0,4)),'acesso_semifinal',2);return;}
            if($system==='six_playoff'||$system==='italian_playoff') {
                if($system==='italian_playoff') {
                    $points=array_column(Competition::standings($t,null,'liga'),'points','team');
                    if($points[$q[0]]-$points[$q[1]]>14){$t['promoted'][]=$q[0];$t['done']=true;return;}
                }
                $t['seeded']=array_slice($q,0,2);
                Competition::knockout($t,[[$q[2],$q[5]],[$q[3],$q[4]]],'acesso_preliminar',1,$system==='italian_playoff'?'advantage':'penalties');return;
            }
            if($system==='ladder'||$system==='scottish_playoff') {
                $t['seeded']=[$q[0]];Competition::knockout($t,[[$q[1],$q[2]]],'acesso_preliminar',$system==='ladder'?1:2);return;
            }
            if($system==='turkish_playoff') {
                $t['seeded']=[$q[0]];Competition::knockout($t,[[$q[1],$q[4]],[$q[2],$q[3]]],'acesso_preliminar',1);return;
            }
            if($system==='dutch_playoff') {
                // Seis elegíveis pela classificação geral; títulos de períodos ainda não substituem vagas.
                $t['seeded']=[$q[0]];Competition::knockout($t,[[$q[1],$q[4]],[$q[2],$q[3]]],'acesso_preliminar',2);return;
            }
            throw new LogicException('Regra europeia desconhecida.');
        }
        if($phase==='split') {
            $r=[];foreach($t['groups'] as $group)$r=array_merge($r,array_column(Competition::standings($t,$group,'split'),'team'));
            $t['initial_ranking']=$r;$t['champion']=$r[0];$t['relegated']=[$r[11]];$t['barrage_candidate']=$r[10];$t['done']=true;return;
        }
        $w=Competition::resolve($t);
        if($phase==='acesso_preliminar') {
            if(count($t['seeded'])===2) {
                Competition::knockout($t,[[$t['seeded'][0],$w[1]],[$t['seeded'][1],$w[0]]],'acesso_semifinal',2,$system==='italian_playoff'?'advantage':'penalties');
            } elseif(count($w)===1)Competition::knockout($t,[[$t['seeded'][0],$w[0]]],'acesso_final',$system==='ladder'?1:2);
            else Competition::knockout($t,[$w],'acesso_semifinal',2);
            return;
        }
        if($phase==='acesso_semifinal' && count($w)===2){
            if($system==='italian_playoff')$w=Competition::rank($t,$w,'liga');
            Competition::knockout($t,[$w],'acesso_final',$system==='six_playoff'||$system==='turkish_playoff'?1:2,$system==='italian_playoff'?'advantage':'penalties');return;
        }
        if($phase==='acesso_semifinal' && $system==='turkish_playoff') {Competition::knockout($t,[[$t['seeded'][0],$w[0]]],'acesso_final',1);return;}
        if($phase==='acesso_semifinal' && $system==='dutch_playoff') {Competition::knockout($t,[[$t['seeded'][0],$w[0]]],'acesso_final',2);return;}
        if(count($w)!==1)throw new LogicException('Final de acesso incompleta.');
        if(in_array($system,['ladder','scottish_playoff','dutch_playoff'],true))$t['barrage_candidate']=$w[0];
        else $t['promoted'][]=$w[0];
        $t['done']=true;
    }
    public static function pairs():array {return array_map(fn($country)=>[$country.'-1',$country.'-2'],['en','es','de','it','fr','pt','nl','be','tr','sc']);}
    public static function barrages(array &$g):void {
        foreach(self::pairs() as [$upper,$lower]) {
            if(!isset($g['competitions'][$upper],$g['competitions'][$lower]))continue;
            $a=&$g['competitions'][$upper];$b=&$g['competitions'][$lower];
            if(empty($a['barrage_candidate']) || empty($b['barrage_candidate']) || isset($a['barrage']))continue;
            $tie=['phase_end'=>0,'matches'=>[]];Competition::knockout($tie,[[$a['barrage_candidate'],$b['barrage_candidate']]],'barrage',2);
            foreach($tie['matches'] as &$m)Competition::simulate($m,$g);unset($m);
            $winner=Competition::resolve($tie)[0];$a['barrage']=$b['barrage']=['winner'=>$winner,'matches'=>$tie['matches']];
            if($winner===$b['barrage_candidate']){$a['relegated'][]=$a['barrage_candidate'];$b['promoted'][]=$winner;}
            unset($a,$b);
        }
    }
}
