<?php
declare(strict_types=1);
/** Residência declarada; não infere nascimento, nacionalidade ou país de estreia. */
final class CurrentLocation {
    public static function label(array $g):string {return ($g['location_kind']??'legacy')==='current'?'Cidade atual':'Cidade informada (cadastro anterior)';}
    public static function apply(array $g,string $country,string $city,string $region,string $entryUf='SP'):array {
        if(!isset(EntryCountries::countries()[$country]) || mb_strlen($city)<2 || mb_strlen($city)>80 || mb_strlen($region)>60)throw new InvalidArgumentException('Confira país, estado e cidade atuais.');
        if($country==='BR'){$origin=Onboarding::origin($city,strtoupper($region));$g['city']=$origin['name'];$g['uf']=$origin['uf'];$g['origin']=$origin;}
        else {$g['city']=$city;$g['uf']='EX';$g['origin']=['name'=>$city,'uf'=>'EX','lat'=>null,'lon'=>null];}
        if(!in_array($entryUf,explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'),true))throw new InvalidArgumentException('Escolha o estado da estreia no Brasil.');
        $g['residence_country']=$country;$g['residence_region']=$region;$g['location_kind']='current';$g['entry_uf']=$country==='BR'?$g['uf']:$entryUf;
        return $g;
    }
    public static function display(array $g):string {return self::label($g).': '.$g['city'].' · '.(($g['residence_country']??'BR')==='BR'?$g['uf']:EntryCountries::name($g['residence_country']));}
}
