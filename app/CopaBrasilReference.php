<?php
declare(strict_types=1);

/** Admission contract only. No match, result, membership or reward is created here. */
final class CopaBrasilReference {
    public static function reference():array {
        return json_decode(file_get_contents(dirname(__DIR__).'/database/competition-references/br-copa-annex-2026.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function phases():array {
        // REC arts.2,10,12: entrants, participants, matches, legs.
        return [1=>[28,28,14,1],2=>[74,88,44,1],3=>[4,48,24,1],4=>[0,24,12,1],5=>[20,32,32,2],6=>[0,16,16,2],7=>[0,8,8,2],8=>[0,4,4,2],9=>[0,2,1,1]];
    }
    public static function audit(array $known):array {
        $r=self::reference();$seen=[];$counts=[1=>0,2=>0,3=>0];$ledger=[];$missing=[];
        foreach($r['participants'] as $row){
            $club=$row['club'];if(isset($seen[$club])||!isset($counts[$row['criterion']])||$row['source_edition']!==2026||$row['source']!==$r['source']||!preg_match('/^[A-Z]{2}$/D',$row['uf']))throw new RuntimeException('Anexo Copa do Brasil inconsistente.');
            $seen[$club]=true;$counts[$row['criterion']]++;$status='resolved_existing_id';
            if(!isset($known[$club])){$missing[]=$club;$status='identity_missing_in_supplied_reference';}
            elseif(($known[$club]['country']??'BR')!=='BR'||($known[$club]['uf']??null)!==$row['uf'])throw new RuntimeException('Identidade/UF divergente no anexo: '.$club);
            $ledger[]=$row+['identity_state'=>$status,'qualification_state'=>'annex_confirmed_not_full_merit_audit','key'=>hash('sha256','br-copa|2026|'.$row['annex_key'])];
        }
        if($counts!==[1=>20,2=>4,3=>102]||count($seen)!==126)throw new RuntimeException('Capacidade ou critérios Copa do Brasil divergentes.');
        return ['edition'=>2026,'annex_count'=>126,'criteria'=>$counts,'resolved_ids'=>126-count($missing),'missing_ids'=>$missing,'ledger'=>$ledger,'phases'=>self::phases(),'expected_matches'=>155,'runtime_allowed'=>false,'blockers'=>['identities'=>$missing,'rnc_2026_and_draw_pots'=>'not_reconciled','annex_b_slots_and_home_draw'=>'not_implemented','primary_amendment_review'=>'incomplete','player_registration_bid'=>'not_certified'],'future'=>['edition'=>2027,'capacity'=>128,'regional_champions'=>'Nordeste,Norte,Centro-Oeste,Sul-Sudeste; results required','admission'=>'blocked']];
    }
    /** Checks entry timing, not a replacement for the still missing draw/RNC validator. */
    public static function validateEntry(int $phase,array $winners,array $new,array $entryPhases):void {
        $rule=self::phases()[$phase]??null;
        if(!$rule||count($new)!==$rule[0]||count($winners)+count($new)!==$rule[1]||count(array_unique(array_merge($winners,$new)))!==$rule[1])throw new RuntimeException('Capacidade, duplicata ou entrada por fase inválida.');
        foreach($new as $club)if(($entryPhases[$club]??null)!==$phase)throw new RuntimeException('Entrada tardia/antecipada não comprovada.');
        foreach($winners as $club)if(!isset($entryPhases[$club])||$entryPhases[$club]>=$phase)throw new RuntimeException('Vencedor sem inscrição anterior.');
    }
}
