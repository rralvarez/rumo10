<?php
declare(strict_types=1);
/** Synthetic result setup solely to exercise an exact regulatory tie, never historical data. */
function dfExtraFixture(int $id,string $mode='expresso'):array {
    $g=CandangaoTrial::create($id,$mode,'ARUC-DF');$order=array_flip($g['competitions']['df-a1']['teams']);
    foreach($g['competitions']['df-a1']['matches'] as &$m){$a=$order[$m['home']];$b=$order[$m['away']];$tied=($a<2&&$b<2);$m['hg']=$tied?0:($a<$b?2:0);$m['ag']=$tied?0:($b<$a?2:0);$m['status']='finalizada';}unset($m);
    WorldSeason::scope($g,function()use(&$g){ConfiguredStateCompetition::next($g['competitions']['df-a1']);WorldSeason::next($g);});return $g;
}
function dfExtraDesignation(array &$g):void {
    $t=&$g['competitions']['df-a1'];$p=$t['administrative_pending'];StateAdministration::schedule($t,['key'=>$p['key'],'kind'=>'synthetic_test_designation','reference'=>'Explicit test operator: ARUC v Brasilia at neutral fixture ground, penalties if drawn; not an FFDF act','home'=>'ARUC-DF','away'=>'Brasília-DF','venue'=>'Synthetic fixture ground','neutral'=>true,'draw_rule'=>'penalties']);$g['stage']='world_processing';unset($g['world_pending']);
}
function dfTestAction(array $g):array {
    if(in_array($g['stage'],['world_processing','world_pending','competicao_resumo'],true))return ['continue',[]];
    if($g['stage']==='treino'&&PlayMode::name($g)!=='normal')return ['simulate',['plan'=>'equilibrio']];
    if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];if($m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'])return ['advance',[]];}
    return ['choice',['option'=>0]];
}
function dfExtraPlayed(array $g):bool {return ($g['competitions']['df-a1']['state_stage']??0)>0&&count(array_filter($g['competitions']['df-a1']['matches'],fn($m)=>isset($m['administrative_key'])&&$m['status']==='finalizada'&&($m['stats']['minutes']??0)>0))===1;}
