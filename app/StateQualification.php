<?php
declare(strict_types=1);

/** Sport proof -> pending candidate. Does not grant national entry, XP, R or title. */
final class StateQualification {
    public static function policies():array {
        $base=json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-d-paths-v1.json'),true,512,JSON_THROW_ON_ERROR)['paths'];
        $batch=StateBatchTrial::reference()['paths'];$expansion=StateExpansionTrial::reference()['paths'];$capixaba=CapixabaTrial::reference()['paths'];if(array_intersect_key($base,$batch)||array_intersect_key($base+$batch,$expansion)||array_intersect_key($base+$batch+$expansion,$capixaba))throw new RuntimeException('Política estadual duplicada.');$pr=PernambucoPotiguarTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba,$pr))throw new RuntimeException('Política estadual duplicada.');$df=CandangaoTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr,$df))throw new RuntimeException('Política estadual duplicada.');$parana=ParanaenseTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr+$df,$parana))throw new RuntimeException('Política estadual duplicada.');$go=GoianoTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr+$df+$parana,$go))throw new RuntimeException('Política estadual duplicada.');$ma=MaranhenseTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr+$df+$parana+$go,$ma))throw new RuntimeException('Política estadual duplicada.');$ac=AcreTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr+$df+$parana+$go+$ma,$ac))throw new RuntimeException('Política estadual duplicada.');$rs=GauchoTrial::reference()['paths'];if(array_intersect_key($base+$batch+$expansion+$capixaba+$pr+$df+$parana+$go+$ma+$ac,$rs))throw new RuntimeException('Política estadual duplicada.');return $base+$batch+$expansion+$capixaba+$pr+$df+$parana+$go+$ma+$ac+$rs;
    }
    public static function evaluate(array $g,string $uf):array {return self::evaluateMany($g,[$uf])[$uf];}
    /** One national reconstruction per batch; no persistent cache or trusted caller-provided digest. */
    public static function evaluateMany(array $g,array $ufs):array {
        if(!$ufs||count($ufs)!==count(array_unique($ufs)))throw new RuntimeException('Lote estadual vazio/duplicado.');
        $copy=$g;$closure=WorldSeason::scope($copy,static function()use(&$copy){return NationalPyramid::close($copy);});$out=[];
        foreach($ufs as $uf)$out[$uf]=self::evaluateWithClosure($g,$uf,$closure);return $out;
    }
    private static function evaluateWithClosure(array $g,string $uf,array $closure):array {
        $p=self::policies()[$uf]??throw new RuntimeException('Caminho estadual não certificado.');$id=$p['competition'];$pin=$g['world_reference'][$id]??[];$t=$g['competitions'][$id]??[];
        if(($g['season']??null)!==1||!CompetitionReference::verify($pin)||($pin['definition']['edition']??null)!==$p['source_edition']||($pin['definition']['uf']??null)!==$uf||!in_array($p['source'],$pin['definition']['verified_sources']??[],true))throw new RuntimeException('Contrato estadual/edição/fonte não verificáveis.');
        $a=$t['teams']??[];$b=$p['participants'];sort($a);sort($b);if($a!==$b)throw new RuntimeException('Participantes estaduais divergentes.');
        if(($pin['definition']['format']??null)==='configured_state'){
            $expected=StateBatchTrial::reference()['definitions'][$id]??StateExpansionTrial::reference()['definitions'][$id]??CapixabaTrial::reference()['definitions'][$id]??PernambucoPotiguarTrial::reference()['definitions'][$id]??CandangaoTrial::reference()['definitions'][$id]??ParanaenseTrial::reference()['definitions'][$id]??GoianoTrial::reference()['definitions'][$id]??MaranhenseTrial::reference()['definitions'][$id]??AcreTrial::reference()['definitions'][$id]??GauchoTrial::reference()['definitions'][$id]??null;
            if(!$expected||StateResultProof::digest($expected)!==StateResultProof::digest($pin['definition']))throw new RuntimeException('Contrato configurável diverge da edição certificada.');
        }
        $valid=WorldSeason::scope($g,fn()=>StateResultProof::verify($pin['definition'],$t));if(!$valid)throw new RuntimeException('Classificação estadual diverge dos resultados.');
        $upper=array_merge($closure['draft_memberships']['br-a'],$closure['draft_memberships']['br-b'],$closure['draft_memberships']['br-c']);$retained=$closure['draft_memberships']['br-d'];$selected=[];$rows=[];
        $ranks=self::eligiblePositions($p,$t,$g['competitions']['br-d']['promoted']??[]);$capacity=$p['max_candidates']??$p['quota'];
        if(!is_int($capacity)||$capacity<0||$capacity>$p['quota'])throw new RuntimeException('Capacidade do caminho inválida.');
        foreach($t['final_ranking'] as $i=>$club){$reason=$i>=($p['ranking_prefix']??count($t['final_ranking']))?($p['excluded_rank_reason']??'ranking_position_not_certified'):(in_array($club,$upper,true)?'verified_upper_national_division':(in_array($club,$retained,true)?'already_national_candidate':(!in_array($i,$ranks,true)?'rank_not_assigned_by_path_rule':(count($selected)>=$capacity?'quota_filled':null))));if($reason===null)$selected[]=$club;$rows[]=['club'=>$club,'rank'=>$i+1,'state'=>$reason===null?'pending_candidate':'not_selected','reason'=>$reason];}
        $scores=array_map(static function($m){$r=[];foreach(['phase','round','home','away','hg','ag','home_red','away_red','home_yellow','away_yellow'] as $f)$r[$f]=$m[$f]??0;$r['penalties']=$m['penalties']??null;return $r;},$t['matches']);
        $row=['schema'=>1,'policy'=>$p['id'],'career_id'=>$g['id'],'uf'=>$uf,'source_edition'=>2026,'target_edition'=>2027,'competition'=>$id,'classification_state'=>'proven_by_results','admission_state'=>'pending_national_edition','quota'=>$p['quota'],'selected'=>$selected,'candidate_count'=>count($selected),'unavailable'=>$p['quota']-count($selected),'rows'=>$rows,'contract'=>$pin['sha256'],'results_digest'=>StateResultProof::digest(['scores'=>$scores,'lots'=>$t['lots'],'ranking'=>$t['final_ranking']]),'national_digest'=>$closure['sha256'],'source'=>$p['source'],'memberships_applied'=>false];$row['sha256']=StateResultProof::digest($row);return $row;
    }
    public static function record(array &$g,string $uf):array {return self::recordMany($g,[$uf])[$uf];}
    /** Pure rule scope, not a result proof or admission. Production caller uses pinned policies. */
    public static function eligiblePositions(array $policy,array $t,array $promotedFromD):array {
        return match($policy['selection_mode']??'ranking'){
            'ranking'=>range(0,count($t['final_ranking'])-1),
            'champion_or_vice_on_c_access'=>in_array($t['champion'],$promotedFromD,true)?[1]:[0],
            default=>throw new RuntimeException('Regra de repasse não suportada.')
        };
    }
    public static function recordMany(array &$g,array $ufs):array {
        $rows=self::evaluateMany($g,$ufs);$next=$g;
        foreach($rows as $uf=>$row){$year=(string)$row['target_edition'];$old=$g['state_qualifications'][$year][$uf]??null;if($old!==null&&$old!=$row)throw new RuntimeException('Classificação estadual imutável: conflito.');$next['state_qualifications'][$year][$uf]=$old??$row;}
        $g=$next;return $rows;
    }
}
