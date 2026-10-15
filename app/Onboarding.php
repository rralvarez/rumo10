<?php
declare(strict_types=1);
final class Onboarding {
    public const ENTRY=['Talento de rua'=>'sp-a3','Disciplina de base'=>'sp-a3','Recomeço difícil'=>'sp-a4','Explosão e ansiedade'=>'sp-a4'];
    public static function division(string $uf,string $profile,string $country='BR'): string {
        if($country!=='BR')return EntryCountries::division($country);
        if($uf==='SP')return self::ENTRY[$profile];
        $id=strtolower($uf).'-entrada';
        if(!isset(Competition::catalog()[$id]))throw new InvalidArgumentException('UF sem divisão de entrada.');
        return $id;
    }
    public static function municipalities(): array {
        static $data;return $data??=json_decode(file_get_contents(dirname(__DIR__).'/database/municipalities.json'),true,512,JSON_THROW_ON_ERROR);
    }
    private static function normalized(string $name): string {
        $name=mb_strtolower(trim($name));$name=strtr($name,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c','’'=>'',"'"=>'']);
        return preg_replace('/\s+/u',' ',$name);
    }
    public static function origin(string $city,string $uf): array {
        foreach(self::municipalities() as $m)if($m['uf']===$uf && self::normalized($m['name'])===self::normalized($city))return $m;
        throw new InvalidArgumentException('Escolha uma cidade válida da UF informada. Use o nome do município, não o bairro.');
    }
    public static function prepare(array $g): array {
        if(($g['residence_country']??'BR')==='BR'){$g['origin']=self::origin($g['city'],$g['uf']);$g['city']=$g['origin']['name'];}
        $g['official']=true;$g['starting']=true;$g['starting_country']='BR';$g['entry_seed']=bin2hex(random_bytes(16));$g['club']='Sem clube';$g['teams']=[];$g['matches']=[];
        $g['season_competitions']=[];$g['competitions']=[];$g['competition_results']=[];$g['initial_candidates']=[];
        return $g;
    }
    public static function distance(array $a,array $b): float {
        $lat=deg2rad($b['lat']-$a['lat']);$lon=deg2rad($b['lon']-$a['lon']);
        $h=sin($lat/2)**2+cos(deg2rad($a['lat']))*cos(deg2rad($b['lat']))*sin($lon/2)**2;
        return 6371*2*asin(min(1,sqrt($h)));
    }
    public static function candidates(array $origin,string $division): array {
        if(!in_array($division,['sp-a3','sp-a4'],true) && !str_ends_with($division,'-entrada'))throw new InvalidArgumentException('Divisão de entrada inválida.');
        $locations=json_decode(file_get_contents(dirname(__DIR__).'/database/starting-clubs.json'),true,512,JSON_THROW_ON_ERROR);$pool=[];
        foreach(Competition::catalog()[$division]['teams'] as $club) {
            $location=$locations[$club];$known=isset($origin['lat'],$origin['lon']);$pool[]=['club'=>$club,'city'=>$location['name'],'uf'=>$location['uf'],'distance_km'=>$known?round(self::distance($origin,$location),1):null];
        }
        usort($pool,fn($a,$b)=>($a['distance_km']<=>$b['distance_km'])?:strcmp($a['club'],$b['club']));return array_slice($pool,0,3);
    }
    public static function render(array $g): void {
        if($g['handicap'] && str_starts_with($g['feedback']['text']??'','Perfil inicial:')) { echo '<div class="entry-draw">';Wheel::render(Rules::HANDICAPS,$g['handicap'],'handicap:'.$g['id']);echo '</div>'; }
        $competition=Competition::catalog()[$g['entry_division']];$division=$competition['name'];$count=count($g['initial_candidates']);$country=$g['starting_country']??'BR';$heading=$count===1?'Uma porta se abriu':($count===2?'Duas portas se abriram':'Três portas se abriram');
        echo '<article class="panel entry-offers"><p class="tag">SEU PRIMEIRO CLUBE · '.e($division).'</p><h2>'.$heading.'</h2>';
        if($country==='BR' && isset($g['origin']['lat'],$g['origin']['lon']))echo '<p>Escolha onde começar. Estes são os clubes mais próximos de '.e($g['city'].' / '.$g['uf']).' nesta divisão.</p><p class="subtle">Distâncias aproximadas em linha reta entre cidades.</p>';
        elseif($country==='BR')echo '<p>Oportunidades no estado escolhido para sua estreia. Sua residência no exterior não foi usada para inventar distâncias.</p>';
        else echo '<p>Escolha seu primeiro clube em '.e(EntryCountries::name($country)).'. Estas oportunidades vieram da divisão de entrada.</p>';
        if(!empty($competition['rules']['note']))echo '<p class="subtle">'.e($competition['rules']['note']).'</p>';
        form('/carreira/clube-inicial',$g);
        foreach($g['initial_candidates'] as $i=>$candidate) {
            echo '<button class="choice club-choice" name="option" value="'.e($i).'">';ClubBadge::render($candidate['club']);
            echo '<span><strong>'.e(ClubBadge::name($candidate['club'])).'</strong><small>'.e($candidate['city']);if($candidate['distance_km']!==null)echo ' · cerca de '.e(round($candidate['distance_km'])).' km';echo '</small></span></button>';
        }
        echo '</form></article>';
    }
}
