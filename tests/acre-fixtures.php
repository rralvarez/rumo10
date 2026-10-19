<?php
declare(strict_types=1);
/** Operator inputs for isolated tests only, never real FFAC decisions or public defaults. */
function acTestDesignation(array &$t):void {
 $p=$t['administrative_pending'];$d=['key'=>$p['key'],'kind'=>'synthetic_test_designation','reference'=>'Explicit synthetic test operator input; not an FFAC act'];
 if($p['reason']==='ac_final_venues_require_designation'){$d['games']=[];foreach(['final','bronze'] as $phase){$clubs=$p['context'][$phase];$d['games'][$phase]=['home'=>$clubs[0],'away'=>$clubs[1],'neutral'=>true,'venue'=>'Synthetic neutral test ground'];}}
 elseif(count($p['teams'])===2)$d['game']=['home'=>$p['teams'][0],'away'=>$p['teams'][1],'neutral'=>true,'venue'=>'Synthetic extra test ground'];else throw new RuntimeException('Multi-club AC format remains pending; do not invent it.');
 AcreAdministration::designate($t,$d);
}
function acGameDesignation(array &$g):void {if($g['stage']!=='world_pending')return;$id=$g['world_pending']['competition'];if($id!=='ac-a1')throw new RuntimeException('Independent pending tournament: '.$id);acTestDesignation($g['competitions'][$id]);$g['stage']='world_processing';unset($g['world_pending']);}
function acAction(array $g):array {
 if(in_array($g['stage'],['world_processing','world_pending','competicao_resumo'],true))return ['continue',[]];
 if($g['stage']==='treino'&&PlayMode::name($g)!=='normal')return ['simulate',['plan'=>'equilibrio']];
 if($g['stage']==='partida'){$m=$g['matches'][Game::currentIndex($g)];if($m['status']!=='em_andamento'||$m['minute']<$m['entry']||$m['minute']>=$m['exit'])return ['advance',[]];}
 return ['choice',['option'=>0]];
}
