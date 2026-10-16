<?php
declare(strict_types=1);

/** Audited entry point for a future replacement; not called by the legacy filler. */
final class SerieDAdmission {
    public static function evaluate(array $policy,array $claims,array $knownClubs,array $context):array {
        $blocked=static fn(string $reason)=>['blocked'=>true,'accepted'=>[],'rejected'=>[],'missing'=>[$reason],'policy'=>$policy['id']??null];
        if(($policy['edition']??null)!==2026||($policy['target']??null)!=='br-d')return $blocked('unsupported_reference_edition');
        if(($policy['status']??null)!=='verified')return $blocked('edition_claim_ledger_not_verified');
        if(($policy['capacity']??null)!==96||($policy['quotas']['relegated_c']??null)!==4||($policy['quotas']['retained_or_ranking']??null)!==28)return $blocked('criterion_capacity_mismatch');
        if(($context['verified']??false)!==true||($context['edition']??null)!==($policy['edition']??null)||empty($context['source_id'])||!in_array($context['source_id'],$policy['allowed_context_sources']??[],true))return $blocked('upper_division_memberships_not_verified');
        $upper=$context['upper_division_clubs']??null;if(!is_array($upper)||count($upper)!==count(array_unique($upper)))return $blocked('invalid_upper_memberships');
        $rank=$policy['federation_ranking']??[];
        $positions=array_values($rank);sort($positions);
        if(count($rank)!==27||count(array_unique($rank))!==27||$positions!==range(1,27))return $blocked('federation_ranking_not_complete');
        foreach($rank as $uf=>$position){$expected=$position===1?4:($position<=9?3:2);if(($policy['quotas']['federation:'.$uf]??null)!==$expected)return $blocked('federation_quota_mismatch');}
        $filtered=[];$rejected=[];
        foreach($claims as $claim){
            $club=$claim['club']??'';$quota=$claim['quota']??'';$reason=null;
            if(in_array($club,$upper,true))$reason='already_in_upper_national_division';
            elseif(str_starts_with($quota,'federation:')&&($knownClubs[$club]['uf']??null)!==substr($quota,11))$reason='federation_mismatch';
            elseif(!isset($policy['criterion_buckets'][$claim['criterion']??''])||$policy['criterion_buckets'][$claim['criterion']]!==($quota==='retained_or_ranking'?'retained_or_ranking':($quota==='relegated_c'?'relegated_c':'federation')))$reason='criterion_mismatch';
            elseif(($claim['priority']??null)!==($claim['criterion']??null)||!is_int($claim['rank']??null)||$claim['rank']<1)$reason='criterion_priority_or_rank_invalid';
            if($reason)$rejected[]=['club'=>$club,'reason'=>$reason];else $filtered[]=$claim;
        }
        $result=CompetitionQualification::evaluate($policy,$filtered,$knownClubs);$result['rejected']=array_merge($rejected,$result['rejected']);return $result;
    }
}
