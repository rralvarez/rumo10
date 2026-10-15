<?php
declare(strict_types=1);
/** Residência declarada; não infere nascimento, nacionalidade ou país de estreia. */
final class CurrentLocation {
    public static function render(array $g):void {
        echo '<section class="panel auth"><h1>Atualizar minha residência</h1><p>O cadastro anterior não é tratado como residência confirmada. Esta alteração voluntária preserva sua carreira, ofertas e resultados.</p>';
        form('/jogador/localizacao',$g);echo '<label>País onde mora atualmente<select class="form-select" name="residence_country">';
        foreach(EntryCountries::countries() as $code=>$name)echo '<option value="'.e($code).'" '.($code===($g['residence_country']??'BR')?'selected':'').'>'.e($name).'</option>';
        echo '</select></label><label data-brazil-location>Estado atual<select class="form-select" name="uf">';
        foreach(explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO') as $uf)echo '<option '.($uf===$g['uf']?'selected':'').'>'.e($uf).'</option>';
        echo '</select></label><label data-foreign-location hidden>Região atual<input class="form-control" name="residence_region" maxlength="60" value="'.e($g['residence_region']??'').'"></label><label>Cidade atual<input name="city" class="form-control" list="city-options" value="'.e($g['city']).'" required minlength="2" maxlength="80"><datalist id="city-options"></datalist></label><button class="btn btn-primary">Confirmar residência atual</button></form><a href="/jogador">Voltar ao jogador</a></section>';
    }
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
