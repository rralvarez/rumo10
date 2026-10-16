<?php
declare(strict_types=1);

/** Read-only edition admission. Never installs catalog entries or changes a save. */
final class CompetitionParticipation {
    public static function inspect(array $reference,string $competition,int $edition,array $knownClubs,array $capabilities=[]):array {
        $out=['competition'=>$competition,'edition'=>$edition,'declared'=>[],'eligible'=>[],'excluded'=>[],'missing_clubs'=>[],'blockers'=>[],'playable'=>false];
        if(($reference['edition']??null)!==$edition){$out['blockers'][]='edition_not_verified';return $out;}
        $definition=$reference['competitions'][$competition]??null;
        if(!$definition){$out['blockers'][]='competition_not_verified';return $out;}
        if(($reference['status']??null)!=='installed')$out['blockers'][]='reference_not_installed';
        $sources=$reference['sources']??[];$identities=[];
        foreach($reference['identities']??[] as $identity){
            if(empty($identity['source'])||!isset($sources[$identity['source']]))throw new InvalidArgumentException('Identidade sem fonte.');
            foreach($identity['labels'] as $label){
                if(isset($identities[$label]))throw new InvalidArgumentException('Alias ambíguo na edição.');
                $identities[$label]=$identity['club'];
            }
        }
        foreach($definition['source_ids']??[] as $source)if(!isset($sources[$source]))throw new InvalidArgumentException('Regulamento sem fonte.');
        if(empty($definition['source_ids']))throw new InvalidArgumentException('Fonte de participação obrigatória.');
        $seen=[];
        foreach($definition['participants'] as $entry){
            $club=$identities[$entry['label']]??null;
            if(!$club){$out['blockers'][]='unresolved_identity:'.$entry['label'];continue;}
            if(isset($seen[$club]))throw new InvalidArgumentException('Clube duplicado por alias.');
            $seen[$club]=true;$out['declared'][]=$club;
            $sanction=$definition['excluded'][$club]??null;
            if($sanction){
                if(!isset($sources[$sanction['source']]))throw new InvalidArgumentException('Exclusão sem fonte.');
                $out['excluded'][]=['club'=>$club]+$sanction;continue;
            }
            if(!isset($knownClubs[$club])){$out['missing_clubs'][]=$club;continue;}
            $out['eligible'][]=['club'=>$club,'group'=>$entry['group']??null];
        }
        if($out['missing_clubs'])$out['blockers'][]='catalog_participants_missing';
        foreach($definition['required_capabilities']??[] as $key)if(($capabilities[$key]??false)!==true)$out['blockers'][]='capability_missing:'.$key;
        // Data evidence alone must never be mistaken for an installed runtime adapter.
        $out['playable']=!$out['blockers']&&!empty($out['eligible']);return $out;
    }
}
