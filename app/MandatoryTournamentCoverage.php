<?php
declare(strict_types=1);
final class MandatoryTournamentCoverage {
    public static function audit(array $g):array {
        $r=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-cup-obligations-2026.json'),true,512,JSON_THROW_ON_ERROR);$clubs=array_values(array_unique(array_merge(...array_values($g['memberships']))));$rows=[];
        foreach($clubs as $club){$rows[$club]=[];foreach(['br-copa'=>'confirmed_copa_do_brasil','br-nordeste'=>'confirmed_nordeste'] as $id=>$list){$confirmed=in_array($club,$r[$list],true);$entered=in_array($club,$g['memberships'][$id]??[],true);$t=$g['competitions'][$id]??null;
            $rows[$club][$id]=['evidence'=>$confirmed?'confirmed_base_2026':($id==='br-nordeste'?'not_listed_in_closed_base_annex':'unassessed_not_exempt'),'calendar'=>$entered&&$t?'present':'absent','completed'=>$entered&&$t&&!empty($t['done']),'required_uncovered'=>$confirmed&&!$entered];}
        }
        return ['edition'=>2026,'scope'=>'base_2026_only_not_legal_certification','all_obligations_known'=>false,'other_regional_and_continental_entries'=>'not_fully_audited','clubs'=>$rows,'public_continuation_allowed'=>false];
    }
}
