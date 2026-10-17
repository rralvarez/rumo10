<?php
declare(strict_types=1);
/** Pending merit is not an entry, match, title or reward. State lives in the save. */
final class ExternalQualification {
    public static function record(array &$g,array $event):string {
        foreach(['source','source_edition','club','target','target_edition','evidence'] as $field)if(empty($event[$field]))throw new InvalidArgumentException('Classificação externa sem evidência/identidade.');
        if(!is_int($event['source_edition'])||!is_int($event['target_edition'])||$event['source_edition']<1||$event['target_edition']<1||!is_string($event['evidence'])||!preg_match('/^[a-f0-9]{64}$/D',$event['evidence']))throw new InvalidArgumentException('Edição ou evidência inválida.');
        $identity=[];foreach(['source','source_edition','club','target','target_edition'] as $field)$identity[]=$event[$field];
        $key=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
        if(isset($g['external_qualifications'][$key])){if($g['external_qualifications'][$key]['event']!=$event)throw new RuntimeException('Evento de classificação conflitante.');return $key;}
        $g['external_qualifications'][$key]=['event'=>$event,'state'=>'pending','consumed_entry'=>null];return $key;
    }
    public static function consume(array &$g,string $key,array $verifiedEntry):void {
        $row=$g['external_qualifications'][$key]??null;if(!$row)throw new InvalidArgumentException('Classificação ausente.');$event=$row['event'];
        if(($verifiedEntry['verified']??false)!==true||empty($verifiedEntry['evidence'])||empty($verifiedEntry['key'])||($verifiedEntry['club']??null)!==$event['club']||($verifiedEntry['competition']??null)!==$event['target']||($verifiedEntry['edition']??null)!==$event['target_edition'])throw new InvalidArgumentException('Inscrição de destino não verificada.');
        if(($g['season']??null)!==$event['target_edition']||empty($g['competitions'][$event['target']]['matches'])||!in_array($event['club'],$g['memberships'][$event['target']]??[],true)||!CompetitionReference::verify($g['world_reference'][$event['target']]??[]))throw new InvalidArgumentException('Destino ainda não executável nesta edição.');
        if($row['state']==='consumed'){if($row['consumed_entry']!=$verifiedEntry)throw new RuntimeException('Consumo conflitante.');return;}
        $g['external_qualifications'][$key]['state']='consumed';$g['external_qualifications'][$key]['consumed_entry']=$verifiedEntry;
    }
    public static function availability(bool $implemented,bool $evidence,bool $calendar,bool $externalExecutable,array $mandatoryUncovered=[]):array {
        return ['competition'=>($implemented&&$evidence&&$calendar)?'playable':'unavailable','external_qualification'=>$externalExecutable?'executable':'pending','career_continuation'=>$implemented&&$evidence&&$calendar&&!$mandatoryUncovered,'mandatory_uncovered'=>$mandatoryUncovered];
    }
}
