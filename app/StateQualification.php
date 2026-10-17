<?php
declare(strict_types=1);

/** Sport proof -> pending candidate. Does not grant national entry, XP, R or title. */
final class StateQualification {
    public static function policies():array {return json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-state-d-paths-v1.json'),true,512,JSON_THROW_ON_ERROR)['paths'];}
    public static function evaluate(array $g,string $uf):array {
        $p=self::policies()[$uf]??throw new RuntimeException('Caminho estadual não certificado.');$id=$p['competition'];$pin=$g['world_reference'][$id]??[];$t=$g['competitions'][$id]??[];
        if(($g['season']??null)!==1||!CompetitionReference::verify($pin)||($pin['definition']['edition']??null)!==$p['source_edition']||($pin['definition']['uf']??null)!==$uf||!in_array($p['source'],$pin['definition']['verified_sources']??[],true))throw new RuntimeException('Contrato estadual/edição/fonte não verificáveis.');
        $a=$t['teams']??[];$b=$p['participants'];sort($a);sort($b);if($a!==$b)throw new RuntimeException('Participantes estaduais divergentes.');
        $valid=WorldSeason::scope($g,fn()=>StateResultProof::verify($pin['definition'],$t));if(!$valid)throw new RuntimeException('Classificação estadual diverge dos resultados.');
        $copy=$g;$closure=WorldSeason::scope($copy,static function()use(&$copy){return NationalPyramid::close($copy);});$upper=array_merge($closure['draft_memberships']['br-a'],$closure['draft_memberships']['br-b'],$closure['draft_memberships']['br-c']);$retained=$closure['draft_memberships']['br-d'];$selected=[];$rows=[];
        foreach($t['final_ranking'] as $i=>$club){$reason=in_array($club,$upper,true)?'verified_upper_national_division':(in_array($club,$retained,true)?'already_national_candidate':(count($selected)>=$p['quota']?'quota_filled':null));if($reason===null)$selected[]=$club;$rows[]=['club'=>$club,'rank'=>$i+1,'state'=>$reason===null?'pending_candidate':'not_selected','reason'=>$reason];}
        $scores=array_map(static function($m){$r=[];foreach(['phase','round','home','away','hg','ag','home_red','away_red','home_yellow','away_yellow'] as $f)$r[$f]=$m[$f]??0;$r['penalties']=$m['penalties']??null;return $r;},$t['matches']);
        $row=['schema'=>1,'policy'=>$p['id'],'career_id'=>$g['id'],'uf'=>$uf,'source_edition'=>2026,'target_edition'=>2027,'competition'=>$id,'classification_state'=>'proven_by_results','admission_state'=>'pending_national_edition','quota'=>$p['quota'],'selected'=>$selected,'candidate_count'=>count($selected),'unavailable'=>$p['quota']-count($selected),'rows'=>$rows,'contract'=>$pin['sha256'],'results_digest'=>StateResultProof::digest(['scores'=>$scores,'lots'=>$t['lots'],'ranking'=>$t['final_ranking']]),'national_digest'=>$closure['sha256'],'source'=>$p['source'],'memberships_applied'=>false];$row['sha256']=StateResultProof::digest($row);return $row;
    }
    public static function record(array &$g,string $uf):array {
        $row=self::evaluate($g,$uf);$year=(string)$row['target_edition'];$old=$g['state_qualifications'][$year][$uf]??null;
        if($old!==null){if($old!=$row)throw new RuntimeException('Classificação estadual imutável: conflito.');return $old;}
        $g['state_qualifications'][$year][$uf]=$row;return $row;
    }
}
