<?php
declare(strict_types=1);
/** Audited closure, not permission to omit D or mandatory cups. Lives in save transaction. */
final class NationalRenewal {
    private static function digest(array $data):string {
        $sort=function($v)use(&$sort){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$item)$item=$sort($item);return $v;};
        return hash('sha256',json_encode($sort($data),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    private static function proof(array $g,string $id):array {
        $t=$g['competitions'][$id]??[];$pin=$g['world_reference'][$id]??[];
        if(!CompetitionReference::verify($pin)||($pin['definition']['national_reference']??null)!=='cbf-abc-reference-2026-v1'||($pin['reference_edition']??null)!==2026)throw new RuntimeException('Contrato nacional não homologável.');
        if(empty($t['done'])||count($t['matches'])!==['br-a'=>380,'br-b'=>384,'br-c'=>216][$id]||array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Edição nacional incompleta.');
        $teams=$g['memberships'][$id];if(count($teams)!==20||$t['teams']!==$teams||$pin['participation']!==$teams||count(array_unique($teams))!==20)throw new RuntimeException('Inscrição nacional divergente.');
        $rank=array_column(Competition::standings($t,null,'liga'),'team');if($rank!==$t['initial_ranking'])throw new RuntimeException('Classificação inicial sem prova.');
        $drop=$id==='br-c'?2:4;if($t['relegated']!==array_slice($rank,-$drop))throw new RuntimeException('Descenso divergente do resultado.');
        if($id==='br-b'){$up=array_merge(array_slice($rank,0,2),array_column($t['ties'],'winner'));if($up!==$t['promoted']||$t['champion']!==$rank[0])throw new RuntimeException('Playoffs B sem comprovação.');}
        if($id==='br-a'&&$t['champion']!==$rank[0])throw new RuntimeException('Título A divergente.');
        if($id==='br-c'){$up=[];foreach($t['classification_groups'] as $group)$up=array_merge($up,array_slice($group,0,2));if($up!==$t['promoted']||($t['ties'][0]['winner']??null)!==$t['champion'])throw new RuntimeException('Quadrangular/final C sem comprovação.');foreach($t['classification_groups'] as $group)if(array_column(Competition::standings($t,$group,'quadrangular'),'team')!==$group)throw new RuntimeException('Classificação do quadrangular divergente.');}
        $matches=[];foreach($t['matches'] as $m){$row=[];foreach(['round','phase','home','away','hg','ag','status'] as $f)$row[$f]=$m[$f];foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)$row[$f]=$m[$f]??0;$row['penalties']=$m['penalties']??null;$matches[]=$row;}
        return ['contract_sha256'=>$pin['sha256'],'participants'=>$teams,'ranking'=>$t['final_ranking']??$rank,'initial_ranking'=>$rank,'champion'=>$t['champion'],'promoted'=>$t['promoted'],'relegated'=>$t['relegated'],'results_sha256'=>self::digest($matches)];
    }
    public static function close(array &$g):array {
        if(($g['national_trial']['schema']??null)!=='abc-closure-v1'||($g['season']??null)!==1)throw new RuntimeException('Renovação fora do contrato-base suportado.');
        $proof=[];$all=[];foreach(['br-a','br-b','br-c'] as $id){$proof[$id]=self::proof($g,$id);$all=array_merge($all,$proof[$id]['participants']);}if(count($all)!==count(array_unique($all)))throw new RuntimeException('Clube inscrito em duas divisões nacionais.');
        $draft=[];foreach($proof as $id=>$p)$draft[$id]=$p['participants'];$relations=[];
        foreach([['br-a','br-b'],['br-b','br-c']] as [$upper,$lower]){
            $down=$proof[$upper]['relegated'];$up=$proof[$lower]['promoted'];if(count($down)!==4||count($up)!==4||array_intersect($down,$up))throw new RuntimeException('Vagas recíprocas incompatíveis.');
            $draft[$upper]=array_values(array_merge(array_diff($draft[$upper],$down),$up));$draft[$lower]=array_values(array_merge(array_diff($draft[$lower],$up),$down));
            $relations[]=['upper'=>$upper,'lower'=>$lower,'down'=>$down,'up'=>$up,'homologation'=>'simulated_results_verified','executed'=>false];
        }
        $draft['br-c']=array_values(array_diff($draft['br-c'],$proof['br-c']['relegated']));
        if(count($draft['br-a'])!==20||count($draft['br-b'])!==20||count($draft['br-c'])!==18||count(array_unique(array_merge(...array_values($draft))))!==58)throw new RuntimeException('Balanço de participantes inconsistente.');
        $closed=['schema'=>1,'source_edition'=>2026,'target_edition'=>2027,'target_knowledge'=>'simulation_adaptation_not_official_participants','proofs'=>$proof,'relations'=>$relations,'draft_memberships'=>$draft,'missing_arrivals'=>['br-c'=>6],'expected_capacity'=>['br-a'=>20,'br-b'=>20,'br-c'=>24],'state'=>'blocked','blockers'=>['serie_d_six_promotions_not_verified','serie_c_2027_rules_not_implemented','mandatory_cup_and_external_entries_not_covered'],'memberships_applied'=>false];
        $closed['sha256']=self::digest($closed);$key=(string)$g['season'];
        if(isset($g['national_closures'][$key])){if($g['national_closures'][$key]!=$closed)throw new RuntimeException('Edição homologada é imutável; evidência conflitante.');return $g['national_closures'][$key];}
        $staged=$g;$staged['national_closures'][$key]=$closed;
        foreach([['br-a','br-b','relegated'],['br-c','br-b','promoted'],['br-c','br-d','relegated']] as [$source,$target,$field])foreach($proof[$source][$field] as $club)ExternalQualification::record($staged,['source'=>$source,'source_edition'=>$g['season'],'club'=>$club,'target'=>$target,'target_edition'=>$g['season']+1,'evidence'=>$proof[$source]['results_sha256']]);
        $g=$staged;
        return $closed;
    }
    public static function explanation(array $g):string {
        $closure=$g['national_closures'][(string)$g['season']]??null;
        return $closure?'Resultados de A/B/C homologados na simulação. A próxima edição aguarda seis acessos da Série D, as regras da Série C com 24 clubes e a cobertura das copas e demais inscrições obrigatórias. Nenhum participante foi reposto artificialmente.':'A próxima edição nacional ainda depende de resultados e inscrições verificáveis. Seu progresso está preservado.';
    }
}
