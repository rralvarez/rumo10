<?php
declare(strict_types=1);
/** REC 2026. Groups/participants pinned; dates and initial fixture order simulated. */
final class NordesteCompetition {
    public static function create(array $c,array $teams):array {
        $r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-nordeste-2026.json'),true,512,JSON_THROW_ON_ERROR);$flat=array_merge(...$r['groups']);$a=$teams;$b=$flat;sort($a);sort($b);
        if(($c['edition']??null)!==2026||$c['groups']!==$r['groups']||$a!==$b||count(array_unique($teams))!==20)throw new RuntimeException('Inscrições ou grupos Nordeste 2026 divergentes.');
        $lots=$teams;shuffle($lots);$t=['id'=>$c['id'],'teams'=>$teams,'format'=>'nordeste','phase'=>'liga','groups'=>$r['groups'],'initial_groups'=>$r['groups'],'matches'=>[],'phase_start'=>1,'phase_end'=>5,'done'=>false,'champion'=>null,'promoted'=>[],'relegated'=>[],'ties'=>[],'lots'=>array_flip($lots),'rounds'=>[],'eliminated'=>[]];
        foreach([0,2] as $i)for($round=0;$round<5;$round++)for($j=0;$j<5;$j++){$x=$r['groups'][$i][$j];$y=$r['groups'][$i+1][($j+$round)%5];$t['matches'][]=Schedule::match($round+1,$round%2?$y:$x,$round%2?$x:$y)+['phase'=>'liga','group'=>(string)($i+1)];}usort($t['matches'],fn($a,$b)=>$a['round']<=>$b['round']);return $t;
    }
    public static function standings(array $t,?array $teams=null,?string $phase=null):array {
        if($phase)$t['matches']=array_values(array_filter($t['matches'],fn($m)=>$m['phase']===$phase));
        $rows=NationalCCompetition::standings($t);return array_values(array_filter($rows,fn($r)=>in_array($r['team'],$teams??$t['teams'],true)));
    }
    private static function pairs(array &$t,array $pairs,string $phase,int $legs=2):void {foreach($pairs as &$pair)$pair=NationalDCompetition::order($t,$pair);unset($pair);Competition::knockout($t,$pairs,$phase,$legs);}
    public static function next(array &$t):void {
        if($t['phase']==='liga'){$r=[];$el=[];foreach($t['initial_groups'] as $group){$rank=array_column(self::standings($t,$group,'liga'),'team');$r[]=$rank;$el=array_merge($el,array_slice($rank,2));}$t['initial_ranking']=NationalDCompetition::order($t,$t['teams']);$t['eliminated']['liga']=NationalDCompetition::order($t,$el);Competition::knockout($t,[[$r[0][0],$r[2][1]],[$r[1][0],$r[3][1]],[$r[2][0],$r[0][1]],[$r[3][0],$r[1][1]]],'quartas',1);return;}
        $phase=$t['phase'];$teams=array_merge(...array_map(fn($p)=>[$p['a'],$p['b']],$t['ties']));$w=Competition::resolve($t);$lost=array_values(array_diff($teams,$w));
        if($phase==='quartas'){$t['eliminated']['quartas']=NationalDCompetition::order($t,$lost);self::pairs($t,[[$w[0],$w[3]],[$w[1],$w[2]]],'semifinal');return;}
        if($phase==='semifinal'){$t['eliminated']['semifinal']=NationalDCompetition::order($t,$lost);self::pairs($t,[[$w[0],$w[1]]],'final');return;}
        if($phase!=='final')throw new RuntimeException('Fase Nordeste desconhecida.');$t['champion']=$w[0];$t['final_ranking']=array_merge($w,$lost,$t['eliminated']['semifinal'],$t['eliminated']['quartas'],$t['eliminated']['liga']);$t['done']=true;
    }
    public static function result(array $t,string $club):array {if(!$t['done']||!in_array($club,$t['teams'],true))throw new RuntimeException('Resultado Nordeste sem conclusão.');return ['name'=>Competition::catalog()[$t['id']]['name'],'champion'=>$t['champion'],'position'=>array_search($club,$t['final_ranking'],true)+1,'group'=>null,'promoted'=>false,'relegated'=>false,'access_pending'=>false,'relegation_pending'=>false,'continuity'=>['valid'=>false,'reason'=>'future_cup_entries_pending'],'titles'=>[]];}
}
