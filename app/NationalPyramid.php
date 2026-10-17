<?php
declare(strict_types=1);
/** Complete base-year results are not sufficient for future D entries or mandatory cups. */
final class NationalPyramid {
    private static function digest(array $data):string {
        $sort=function($v)use(&$sort){if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=$sort($x);return $v;};return hash('sha256',json_encode($sort($data),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public static function close(array &$g):array {
        foreach($g['competitions'] as $t)if(!$t['done']||array_filter($t['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Calendário obrigatório da fixture ainda incompleto.');
        $copy=$g;$copy['national_trial']['schema']='abc-closure-v1';$abc=NationalRenewal::close($copy);$d=$g['competitions']['br-d'];$pin=$g['world_reference']['br-d'];
        if(!CompetitionReference::verify($pin)||($pin['definition']['d_reference']??null)!=='cbf-d-sports-2026-v1'||!$d['done']||count($d['matches'])!==610||count(array_unique($d['promoted']))!==6||count(array_unique($d['final_ranking']))!==96||array_filter($d['matches'],fn($m)=>$m['status']!=='finalizada'))throw new RuntimeException('Série D sem encerramento verificável.');
        if(!NationalDCompetition::verify($d))throw new RuntimeException('Chaveamento/classificação D diverge dos resultados.');
        if(isset($g['competitions']['br-copa'])&&(!CompetitionReference::verify($g['world_reference']['br-copa'])||!CopaBrasilCompetition::verify($g['competitions']['br-copa'])))throw new RuntimeException('Copa do Brasil sem chaveamento/resultado verificável.');
        if($d['teams']!==$g['memberships']['br-d']||$pin['participation']!==$d['teams']||array_intersect($d['teams'],array_merge($g['memberships']['br-a'],$g['memberships']['br-b'],$g['memberships']['br-c'])))throw new RuntimeException('Inscrição D inconsistente com a pirâmide.');
        $draft=$abc['draft_memberships'];$draft['br-c']=array_values(array_merge($draft['br-c'],$d['promoted']));
        $retained=array_values(array_diff($d['retained_candidates'],$d['promoted']));$draft['br-d']=array_values(array_merge($retained,$g['competitions']['br-c']['relegated']));
        if(count($draft['br-c'])!==24||count($draft['br-d'])!==28||count(array_unique(array_merge(...array_values($draft))))!==92)throw new RuntimeException('Troca C/D ou déficit futuro inconsistente.');
        $scores=[];foreach($d['matches'] as $m){$row=[];foreach(['phase','round','home','away','hg','ag'] as $f)$row[$f]=$m[$f];$row['penalties']=$m['penalties']??null;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)$row[$f]=$m[$f]??0;$scores[]=$row;}
        $proof=self::digest($scores);$closure=['schema'=>1,'source_edition'=>2026,'target_edition'=>2027,'knowledge'=>'simulation_adaptation_not_official_entries','abc_proofs'=>$abc['proofs'],'d_contract'=>$pin['sha256'],'d_results'=>$proof,'d_champion'=>$d['champion'],'d_promoted'=>$d['promoted'],'draft_memberships'=>$draft,'missing_d_entries'=>68,'state'=>'blocked','blockers'=>['future_d_state_selective_and_ranking_results_missing','mandatory_cups_not_covered'],'memberships_applied'=>false];$cupProof=[];foreach($g['competitions']['br-nordeste']['matches']??[] as $m){$r=[];foreach(['phase','round','home','away','hg','ag'] as $f)$r[$f]=$m[$f];$r['penalties']=$m['penalties']??null;foreach(['home_red','away_red','home_yellow','away_yellow'] as $f)$r[$f]=$m[$f]??0;$cupProof[]=$r;}$closure['nordeste_results_sha256']=self::digest($cupProof);$closure['current_nordeste_completed']=isset($g['competitions']['br-nordeste']);$closure['sha256']=self::digest($closure);
        if(isset($g['competitions']['br-copa'])){$cup=$g['competitions']['br-copa'];$closure['copa_brasil']=['contract'=>$g['world_reference']['br-copa']['sha256'],'champion'=>$cup['champion'],'draws'=>$cup['draws'],'results'=>self::digest(array_map(static fn($m)=>[$m['phase'],$m['round'],$m['home'],$m['away'],$m['hg'],$m['ag'],$m['penalties']??null],$cup['matches'])),'completed'=>true];unset($closure['sha256']);$closure['sha256']=self::digest($closure);}
        if(isset($g['national_pyramid_closures']['1'])){if($g['national_pyramid_closures']['1']!=$closure)throw new RuntimeException('Homologação A/B/C/D imutável: conflito.');return $closure;}
        $next=$g;$next['external_qualifications']=$copy['external_qualifications'];$next['national_pyramid_closures']['1']=$closure;
        foreach($d['promoted'] as $club)ExternalQualification::record($next,['source'=>'br-d','source_edition'=>1,'club'=>$club,'target'=>'br-c','target_edition'=>2,'evidence'=>$proof]);
        ExternalQualification::record($next,['source'=>'br-d-champion','source_edition'=>1,'club'=>$d['champion'],'target'=>'br-copa','target_edition'=>2,'evidence'=>$proof]);
        if(isset($g['competitions']['br-nordeste']))ExternalQualification::record($next,['source'=>'br-nordeste-champion','source_edition'=>1,'club'=>$g['competitions']['br-nordeste']['champion'],'target'=>'br-copa','target_edition'=>2,'evidence'=>self::digest($g['competitions']['br-nordeste']['final_ranking'])]);$g=$next;return $closure;
    }
    public static function nextCContract(array $g):array {
        $closed=$g['national_pyramid_closures']['1']??null;if(!$closed)throw new RuntimeException('Resultados nacionais ausentes.');
        $c=$g['world_reference']['br-c']['definition'];$c['teams']=$closed['draft_memberships']['br-c'];$c['groups']=[$c['teams']];$c['edition']='simulation-2';$c['rules']['derived_simulation']='c-2027-from-2026-v1';$c['rules']['home_priority']=array_slice($c['teams'],0,12);$c['rules']['note']='Adaptação simulada: 24 clubes, turno único, oito nos quadrangulares; mandos por ordem fixada. Não é REC oficial 2027.';
        return CompetitionReference::pin($c,'c-2027-from-2026-v1','simulation_adaptation');
    }
    public static function attemptRenewal(array &$g):void {
        $next=$g;self::close($next);throw new RuntimeException('Renovação integral bloqueada: faltam 68 entradas verificáveis na Série D e as copas obrigatórias. Nenhuma divisão foi renovada.');
    }
}
