<?php
declare(strict_types=1);
/** Notas de jogo, não um ranking oficial. Dados ausentes permanecem provisórios. */
final class TeamStrength {
    public static function baseline(string $club,array $members=[]): float {
        $levels=['br-a'=>84,'br-b'=>72,'br-c'=>60,'br-d'=>48,'sp-a1'=>64,'sp-a2'=>53,'sp-a3'=>45,'sp-a4'=>38,'sp-segunda'=>30,
            'ar-1'=>81,'ar-2'=>63,'uy-1'=>64,'uy-2'=>48,'py-1'=>65,'py-2'=>46,'bo-1'=>58,'bo-2'=>39];
        $ids=Competition::clubs()[$club]['competitions']??[];$base=50;
        if($members){$ids=[];foreach($members as $id=>$teams)if(in_array($club,$teams,true))$ids[]=$id;}
        if($ids){$base=0;foreach($ids as $id)$base=max($base,$levels[$id]??(Competition::catalog()[$id]['baseline']??($id==='rr-entrada'?48:32)));}
        return $base;
    }
    public static function results(): array {
        static $data;return $data??=array_merge(json_decode(file_get_contents(dirname(__DIR__).'/database/team-results-2025.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/database/europe-results-2025.json'),true,512,JSON_THROW_ON_ERROR));
    }
    public static function campaign(float $base,int $position,int $teams,int $points,int $games): float {
        $performance=.7*min(100,max(0,$points/max(1,3*$games)*100))+.3*(1-($position-1)/max(1,$teams-1))*100;
        return round(max(20,min(95,$base+.2*($performance-50))),1);
    }
    public static function initial(string $club): float {
        $r=self::results()[$club]??null;
        return $r?self::campaign(self::baseline($club),(int)$r['position'],(int)$r['teams'],(int)$r['points'],(int)$r['games']):self::baseline($club);
    }
    public static function rating(string $club,array $g=[]): float {
        $base=$g['club_strengths'][$club]??self::initial($club);
        $played=array_values(array_filter($g['matches']??[],fn($m)=>$m['status']==='finalizada' && in_array($club,[$m['home'],$m['away']],true)));
        $played=array_slice($played,-5);$points=0;
        foreach($played as $m){$delta=$m['home']===$club?$m['hg']-$m['ag']:$m['ag']-$m['hg'];$points+=$delta>0?3:($delta===0?1:0);}
        return round(max(20,min(95,$base+($played?6*($points/(3*count($played))-.5):0))),1);
    }
    public static function snapshot(array &$m,array $g=[]): void {
        $m['home_overall']??=self::rating($m['home'],$g);$m['away_overall']??=self::rating($m['away'],$g);
    }
    public static function expectations(float $home,float $away): array {
        $difference=max(-60,min(60,$home-$away));
        return [max(.25,min(3.5,1.35*exp($difference/55))),max(.25,min(3.5,1.10*exp(-$difference/55)))];
    }
    public static function goals(float $mean): int {
        $limit=exp(-max(0,$mean));$product=1.;$count=0;
        do{$count++;$product*=random_int(1,1000000)/1000000;}while($product>$limit && $count<30);
        return $count-1;
    }
    public static function nextSeason(array &$g): void {
        $scores=[];
        foreach($g['competitions'] as $id=>$t){
            $rows=$t['format']==='international'?Schedule::table(array_values(array_filter($t['matches'],fn($m)=>in_array($m['phase'],['liga','clausura','intermedio','regular'],true))),$t['teams']):Competition::standings($t,null,'liga');$size=count($rows);
            foreach($rows as $i=>$row){
                if(!$row['played'])continue;$club=$row['team'];
                $score=self::campaign(self::baseline($club,$g['next_memberships']??$g['memberships']),$i+1,$size,(int)$row['points'],(int)$row['played']);
                $scores[$club]=max($scores[$club]??20,$score);
            }
        }
        $g['next_club_strengths']=$scores;
    }
}
