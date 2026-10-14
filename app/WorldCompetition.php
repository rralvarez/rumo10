<?php
declare(strict_types=1);
/** Calendários de protótipo dos mercados adicionais. Ver regras/notas de cada catálogo. */
final class WorldCompetition {
    public static function create(array $c,array $teams):array {
        $lots=$teams;shuffle($lots);
        $t=['id'=>$c['id'],'teams'=>$teams,'format'=>'world','phase'=>'liga','groups'=>[$teams],'initial_groups'=>[$teams],'matches'=>[],'phase_start'=>1,'phase_end'=>0,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'titles'=>[]];
        $short=str_starts_with($c['rules']['system'],'short_');
        Competition::leaguePhase($t,[$teams],'liga',!$short || $c['id']!=='mx-1' && $c['id']!=='co-1');
        if($c['rules']['system']==='cpl') {
            $offset=$t['phase_end'];foreach(Schedule::generate($teams,true) as $m){$m['round']+=$offset;$t['matches'][]=$m+['phase'=>'liga','group'=>'1'];}$t['phase_end']=28;
        }return $t;
    }
    private static function splitRank(array $t):array {
        $out=[];foreach($t['groups'] as $group)$out=array_merge($out,array_column(Competition::standings($t,$group,'split'),'team'));return $out;
    }
    public static function next(array &$t):void {
        $c=Competition::catalog()[$t['id']];$system=$c['rules']['system'];$phase=$t['phase'];
        if($phase==='liga' || $phase==='clausura') {
            $r=Competition::rank($t,$t['teams'],$phase);$t['initial_ranking']=$r;
            if($system==='cpl'){$t['titles']['CPL Shield']=$r[0];Competition::knockout($t,Competition::seededPairs(array_slice($r,0,4)),'cpl_semifinal',2);return;}
            if(str_starts_with($system,'short_')) {
                $t['short_stage']=$phase==='liga'?'Apertura':'Clausura';$t['short_ranking']=$r;
                $size=$system==='short_four'?4:8;$qualified=array_slice($r,0,$size);
                Competition::knockout($t,Competition::seededPairs($qualified),strtolower($t['short_stage']).'_'.($size===4?'semifinal':'quartas'),2,$t['id']==='mx-1'?'advantage':'penalties');return;
            }
            if(str_starts_with($system,'split')) {
                $sizes=match($system){'split'=>[6,6],'split_greece'=>[4,4,6],'split_ecuador'=>[6,4,6],'split_cyprus'=>[6,8],default=>throw new LogicException('Split desconhecido')};
                $groups=[];$offset=0;foreach($sizes as $size){$groups[]=array_slice($r,$offset,$size);$offset+=$size;}
                Competition::leaguePhase($t,$groups,'split',true);return;
            }
            $t['champion']=$r[0];$t['done']=true;return;
        }
        if($phase==='split'){$t['initial_ranking']=self::splitRank($t);$t['champion']=$t['initial_ranking'][0];$t['done']=true;return;}
        $w=Competition::resolve($t);
        if($system==='cpl') {
            if(count($w)===2){usort($w,fn($a,$b)=>array_search($a,$t['initial_ranking'],true)<=>array_search($b,$t['initial_ranking'],true));Competition::knockout($t,[$w],'cpl_final',1);return;}
            $t['champion']=$w[0];$t['titles']['North Star Cup']=$w[0];$t['done']=true;return;
        }
        if(count($w)>1) {
            usort($w,fn($a,$b)=>array_search($a,$t['short_ranking'],true)<=>array_search($b,$t['short_ranking'],true));
            Competition::knockout($t,Competition::seededPairs($w),strtolower($t['short_stage']).'_'.(count($w)===4?'semifinal':'final'),2,$t['id']==='mx-1' && count($w)>2?'advantage':'penalties');return;
        }
        $t['titles'][$t['short_stage']]=$w[0];
        if($t['short_stage']==='Apertura') {
            Competition::leaguePhase($t,[$t['teams']],'clausura',$t['id']!=='mx-1' && $t['id']!=='co-1');
            foreach($t['matches'] as &$m)if($m['phase']==='clausura')[$m['home'],$m['away']]=[$m['away'],$m['home']];unset($m);return;
        }
        $t['champion']=$w[0];$t['done']=true;
    }
}
