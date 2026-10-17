<?php
declare(strict_types=1);

/** Product-approved direction, NOT admission or renewal. Results come from this save. */
final class SerieDFuturePlan {
    public static function build(array $g):array {
        $audit=FutureSerieDAdmission::audit($g);$closure=$g['national_pyramid_closures']['1']??throw new RuntimeException('Fechamento nacional necessário.');
        $excluded=array_merge(...array_values($closure['draft_memberships']));$residual=[];
        foreach($g['competitions']['br-d']['final_ranking'] as $club){if(in_array($club,$excluded,true))continue;$residual[]=$club;if(count($residual)===4)break;}
        if(count($residual)!==4)throw new RuntimeException('Ranking D sem quatro candidatos residuais distintos.');
        return ['policy'=>'br-d-future-evidence-only-v1','source_edition'=>2026,'target_edition'=>2027,'national_candidates'=>$audit['national_candidates'],'national_count'=>28,'residual_proposal'=>$residual,'residual_count'=>4,'residual_method'=>'simulated finalized D ranking; adaptation proposed, not official RNC','federation_slots'=>$audit['federations'],'remaining_if_residual_proposal_accepted'=>64,'current_missing_verified_entries'=>$audit['missing_total'],'base_capacity_check'=>28+4+64,'following_capacity_check'=>28+4+64,'following_c_to_d'=>4,'following_d_to_c'=>4,'source_digest'=>$audit['source_results_digest'],'blocked'=>true,'memberships_applied'=>false,'activation'=>'requires complete state/selective pathways, future cups, four-slot adapters and atomic renewal tests'];
    }
}
