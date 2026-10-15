<?php
declare(strict_types=1);
final class EntryCountries {
    public static function leagues():array {
        static $map;if($map!==null)return $map;$map=[];$clubs=Competition::clubs();
        foreach(Competition::catalog() as $id=>$league){
            if($league['family']!=='international' || count($league['teams'])<2)continue;
            foreach($league['teams'] as $name){$club=$clubs[$name]??null;if(!$club || !empty($club['reserve']))continue;$country=$club['country']??'BR';if($country==='BR')continue;$map[$country][$id]=$league['tier'];}
        }
        foreach($map as &$leagues)asort($leagues,SORT_NUMERIC);unset($leagues);return $map;
    }
    public static function name(string $country):string{return (TransferMarket::COUNTRIES+TransferMarket::EXTRA_COUNTRIES)[$country]??$country;}
    public static function countries():array {
        static $list;if($list!==null)return $list;$list=['BR'=>'Brasil'];foreach(array_keys(self::leagues()) as $code)$list[$code]=self::name($code);
        $normalized=fn($name)=>strtr(mb_strtolower($name),['á'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','ú'=>'u','ç'=>'c']);uasort($list,fn($a,$b)=>strcmp($normalized($a),$normalized($b)));return $list;
    }
    public static function validate(string $country,string $plan):string {
        if($country!=='BR' && $plan!=='pago')throw new InvalidArgumentException('O plano gratuito permite começar no Brasil. Outros países estão disponíveis no plano pago.');
        if(!isset(self::countries()[$country]))throw new InvalidArgumentException('Escolha um país disponível para começar a carreira.');return $country;
    }
    public static function division(string $country):string {
        $leagues=self::leagues()[$country]??[];if(!$leagues)throw new InvalidArgumentException('País sem competição de entrada.');return array_key_first($leagues);
    }
    public static function candidates(string $country,string $division,string $seed):array {
        if(!isset(self::leagues()[$country][$division]))throw new InvalidArgumentException('Competição de entrada inválida para este país.');
        $pool=[];$clubs=Competition::clubs();
        foreach(Competition::catalog()[$division]['teams'] as $name){$club=$clubs[$name]??null;if(!$club || ($club['country']??'BR')!==$country || !empty($club['reserve']))continue;$pool[]=['club'=>$name,'country'=>$country,'city'=>$club['city']??self::name($country),'uf'=>$country,'distance_km'=>null];}
        usort($pool,fn($a,$b)=>strcmp(hash('sha256',$seed.':'.$a['club']),hash('sha256',$seed.':'.$b['club'])));return array_slice($pool,0,3);
    }
    public static function create(string $name,string $city,string $uf,string $position,string $country,string $plan,string $residence='BR',string $region='',string $entryUf='SP'):array {
        self::validate($country,$plan);
        $g=Game::legacyCreate($name,$residence==='BR'?$city:'São Paulo',$residence==='BR'?$uf:'SP',$position);
        $g=CurrentLocation::apply($g,$residence,$city,$residence==='BR'?$uf:$region,$entryUf);
        $g=Onboarding::prepare($g);$g['starting_country']=$country;return $g;
    }
}
