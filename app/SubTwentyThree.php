<?php
declare(strict_types=1);
/** FAF age checks. No age inference for real people with missing birth dates. */
final class SubTwentyThree {
    public static function assess(array $players,int $year,int $simulatedSeason=1,int $maxOlder=5,int $maxPlayers=23):array {
        if($maxOlder<0||$maxOlder>5||$maxPlayers<11||$maxPlayers>23)throw new InvalidArgumentException('Limites Sub-23 inválidos.');
        $rows=[];$young=[];$older=[];$unknown=[];$seen=[];
        foreach($players as $p){$id=$p['id']??null;if(!$id||isset($seen[$id]))throw new InvalidArgumentException('Atleta ausente ou duplicado.');$seen[$id]=true;$birthYear=null;$basis='unknown';
            if(($p['origin']??null)==='fictional'&&isset($p['simulated_birth_year'])){$birthYear=(int)$p['simulated_birth_year'];$basis='fictional_generated_birth_year';}
            elseif(($p['origin']??null)==='fictional'&&isset($p['age_at_import'])){$birthYear=$year-((int)$p['age_at_import']+$simulatedSeason-1);$basis='fictional_simulated_age';}
            elseif(!empty($p['birthdate'])){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$p['birthdate']);if($d&&$d->format('Y-m-d')===$p['birthdate']){$birthYear=(int)$d->format('Y');$basis='known_birthdate';}}
            $category=$birthYear===null?'unverified':($birthYear>=$year-23?'young':'over_age');$rows[]=['id'=>$id,'category'=>$category,'basis'=>$basis,'birth_year'=>$birthYear];
            if($category==='young')$young[]=$p;elseif($category==='over_age')$older[]=$p;else $unknown[]=$id;
        }
        $sort=static fn($a,$b)=>[($a['position']??'')!=='goleiro',-(float)($a['overall']??0),$a['id']]<=>[($b['position']??'')!=='goleiro',-(float)($b['overall']??0),$b['id']];usort($young,$sort);usort($older,$sort);
        $pool=array_merge($young,array_slice($older,0,$maxOlder));usort($pool,$sort);$selected=array_slice($pool,0,$maxPlayers);$goalkeepers=count(array_filter($players,fn($p)=>($p['position']??'')==='goleiro'));$selectedKeepers=count(array_filter($selected,fn($p)=>($p['position']??'')==='goleiro'));
        return ['reference_year'=>$year,'cutoff_year'=>$year-23,'future_basis'=>$year===2026?'FAF REC 2026 art.14':'simulated_roll_forward_not_official','players'=>$rows,'unverified_real_players'=>$unknown,'selected_ids'=>array_column($selected,'id'),'over_age_selected'=>count(array_filter($selected,fn($p)=>in_array($p['id'],array_column($older,'id'),true))),'valid_matchday'=>count($selected)>=11&&$goalkeepers>=2&&$selectedKeepers>=1&&count($players)>=24,'registration_basis'=>'sports_age_only; real BID/professional registration not certified'];
    }
    public static function prepare(array $players,int $year,int $season,string $club,?int $careerAge=null):array {
        $original=$players;$maxOlder=$careerAge!==null&&$careerAge>23?4:5;$maxPlayers=$careerAge===null?23:22;$added=[];$referenceAudit=self::assess($original,$year,$season,$maxOlder,$maxPlayers);$priority=array_flip($referenceAudit['selected_ids']);
        usort($players,fn($a,$b)=>[!isset($priority[$a['id']]),($a['position']??'')!=='goleiro',$a['id']]<=>[!isset($priority[$b['id']]),($b['position']??'')!=='goleiro',$b['id']]);$players=array_slice($players,0,24);
        for($n=0;$n<=24;$n++){
            $audit=self::assess($players,$year,$season,$maxOlder,$maxPlayers);if($audit['valid_matchday'])return ['audit'=>$audit,'reference_age_audit'=>$referenceAudit['players'],'not_registered_reference_count'=>count($original)-min(24,count($original)),'reference_sha256'=>hash('sha256',json_encode($original,JSON_THROW_ON_ERROR)),'reference_count'=>count($original),'registered_count'=>count($players),'added_fictional_players'=>array_values(array_filter($players,fn($p)=>in_array($p['id'],$added,true))),'career_player_age'=>$careerAge,'official_bid_verified'=>false];
            if($n===24||count($players)>=50)throw new RuntimeException('Elenco não pode ser regularizado sem extrapolar inscrições.');
            $keepers=count(array_filter($players,fn($p)=>($p['position']??'')==='goleiro'&&($p['origin']??'')==='fictional'));
            $id='fictional:sub23:'.substr(hash('sha256',$club.'|'.$year.'|'.$n),0,20);$p=['id'=>$id,'name'=>'Atleta fictício '.($n+1),'origin'=>'fictional','simulated_birth_year'=>$year-18,'position'=>$keepers<2?'goleiro':'meia','overall'=>40,'overall_origin'=>'estimated_simulated_youth_not_official','source'=>null];$players[]=$p;$added[]=$id;
        }
        throw new LogicException('Preparação Sub-23 incompleta.');
    }
}
