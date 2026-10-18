<?php
declare(strict_types=1);

/** Read-only conservative proposal approved 17/10/2026. Never applies memberships.
 * National merits are rebuilt from finalized results; catalogue presence is not merit.
 * Federation qualifiers require a separately verified edition policy and result receipt.
 */
final class FutureSerieDAdmission {
    /** Optional snapshot ledger, persisted by the caller's existing transaction.
     * Does not apply admission, consume qualification, change a slot or grant a reward.
     */
    public static function record(array &$g):array {
        $row=self::audit($g);unset($row['input_unchanged']);
        $canonical=function($v)use(&$canonical){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=$canonical($x);return $v;};
        $row['sha256']=hash('sha256',json_encode($canonical($row),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $key=(string)$row['target_edition'];
        if(isset($g['future_d_admissions'][$key])){if($g['future_d_admissions'][$key]!=$row)throw new RuntimeException('Ledger de candidaturas D imutável: conflito.');return $g['future_d_admissions'][$key];}
        $g['future_d_admissions'][$key]=$row;return $row;
    }
    public static function audit(array $g):array {
        $before=$g;$closure=WorldSeason::scope($g,static function()use(&$g){return NationalPyramid::close($g);});
        $policy=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-d-admission-2026-v2.json'),true,512,JSON_THROW_ON_ERROR);
        $ledger=[];$selected=[];$upper=array_merge($closure['draft_memberships']['br-a'],$closure['draft_memberships']['br-b'],$closure['draft_memberships']['br-c']);
        foreach($closure['draft_memberships']['br-d'] as $club){
            if(in_array($club,$upper,true)||isset($selected[$club]))throw new RuntimeException('Candidato D em divisão superior ou duplicado.');
            $selected[$club]=true;$from=in_array($club,$g['competitions']['br-c']['relegated'],true)?'br-c':'br-d';
            $ledger[]=['key'=>hash('sha256',$g['id'].'|2027|'.$club),'club'=>$club,'source'=>$from,'source_edition'=>2026,'target_edition'=>2027,'state'=>'national_merit_candidate_not_enrolled','evidence'=>$closure['sha256']];
        }
        $nationalSelected=$selected;$stateSelected=[];$deficits=[];foreach($policy['federation_ranking'] as $uf=>$rank){$quota=$policy['quotas']['federation:'.$uf];$deficits[$uf]=['reference_2026_quota'=>$quota,'accepted'=>0,'missing'=>$quota,'state'=>'future_rule_and_finalized_qualifying_results_required'];}
        $stateReceipts=$g['state_qualifications']['2027']??[];$stateProofs=$stateReceipts?StateQualification::evaluateMany($g,array_keys($stateReceipts)):[];
        foreach($stateReceipts as $uf=>$receipt){$proof=$stateProofs[$uf];if($receipt!=$proof||$proof['quota']!==$deficits[$uf]['reference_2026_quota'])throw new RuntimeException('Recibo estadual ou quota divergentes.');
            foreach($proof['selected'] as $club){if(isset($selected[$club])||in_array($club,$upper,true))throw new RuntimeException('Vaga estadual duplicada.');$selected[$club]=true;$stateSelected[]=$club;$ledger[]=['key'=>hash('sha256',$g['id'].'|2027|'.$uf.'|'.$club),'club'=>$club,'source'=>$proof['competition'],'source_edition'=>2026,'target_edition'=>2027,'state'=>'state_merit_candidate_not_enrolled','evidence'=>$proof['sha256']];}
            $deficits[$uf]['accepted']=$proof['candidate_count'];$deficits[$uf]['missing']=$proof['unavailable'];$deficits[$uf]['state']='sports_candidates_proven_admission_pending';
        }
        // Retain rejected/unevaluated candidates as evidence, never select a champion by name.
        foreach($g['competitions'] as $id=>$t){$pin=$g['world_reference'][$id]??[];$c=$pin['definition']??[];
            if(($c['country']??null)!=='BR'||($c['scope']??null)!=='state')continue;
            if(isset($g['state_qualifications']['2027'][$c['uf']??''])&&($g['state_qualifications']['2027'][$c['uf']]['competition']??null)===$id)continue; // Certified path already has its selected and rejected ranking rows in the receipt.
            if(!CompetitionReference::verify($pin)||empty($t['done'])||array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Estadual sem edição/resultados fechados verificáveis.');
            foreach($t['final_ranking']??[] as $i=>$club){$reason=isset($selected[$club])?'already_national_candidate':(in_array($club,$upper,true)?'upper_division_ineligible':'federation_qualification_rule_not_certified');
                $ledger[]=['key'=>hash('sha256',$g['id'].'|2027|'.$id.'|'.$club),'club'=>$club,'source'=>$id,'source_edition'=>2026,'target_edition'=>2027,'rank'=>$i+1,'state'=>'not_selected','reason'=>$reason,'evidence'=>$pin['sha256']];
            }
        }
        return ['schema'=>1,'policy'=>'conservative-evidence-only-approved-20261017','knowledge'=>'future_simulation_proposal_not_official_2027','career_id'=>$g['id'],'source_edition'=>2026,'target_edition'=>2027,'capacity_proposed'=>96,'national_candidates'=>array_keys($nationalSelected),'state_candidates'=>$stateSelected,'candidate_count'=>count($selected),'missing_total'=>96-count($selected),'federations'=>$deficits,'federation_missing_total'=>array_sum(array_column($deficits,'missing')),'residual_missing'=>96-count($selected)-array_sum(array_column($deficits,'missing')),'quotas_status'=>'2026_reference_not_certified_2027','blocked'=>true,'memberships_applied'=>false,'ledger'=>$ledger,'source_results_digest'=>$closure['sha256'],'input_unchanged'=>$before===$g];
    }
}
