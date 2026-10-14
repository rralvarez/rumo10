<?php
declare(strict_types=1);
final class CreationForm {
    public static function render(string $path,?array $existing,string $plan):void {
        echo '<section class="panel auth"><h1>Quem entra em campo?</h1><p><a href="/carreiras">Ver vagas e gerenciar minhas carreiras</a></p>';
        if($existing)echo '<p>Sua carreira atual será arquivada e poderá ser retomada depois.</p>';
        form($path==='/carreira/nova'?'/carreira/nova':'/carreira/criar');
        echo '<label>Nome do jogador<input name="name" class="form-control" minlength="2" maxlength="60" value="'.e($_POST['name']??'').'" required></label>';
        $countries=$plan==='pago'?EntryCountries::countries():['BR'=>'Brasil'];$selected=is_string($_POST['country']??null) && isset($countries[$_POST['country']])?$_POST['country']:'BR';
        echo '<label>País onde começa a carreira<select name="country" class="form-select" required>';
        foreach($countries as $code=>$name)echo '<option value="'.e($code).'" '.($code===$selected || $plan!=='pago'?'selected':'').'>'.e($name).'</option>';
        echo '</select></label><p class="subtle">'.($plan==='pago'?'Seu plano permite começar em qualquer país disponível.':'No plano gratuito, sua carreira começa no Brasil.').'</p>';
        echo '<label>UF de nascimento (Brasil)<select name="uf" class="form-select">';
        foreach(explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO') as $uf)echo '<option '.($uf===($_POST['uf']??'SP')?'selected':'').'>'.e($uf).'</option>';
        echo '</select></label><label>Cidade natal<input name="city" class="form-control" list="city-options" autocomplete="address-level2" minlength="2" maxlength="80" value="'.e($_POST['city']??'').'" required><datalist id="city-options"></datalist></label>';
        echo '<label>Posição<select class="form-select" name="position">';
        foreach(Rules::POSITIONS as $position)echo '<option value="'.e($position).'" '.($position===($_POST['position']??'')?'selected':'').'>'.e(ucfirst($position)).'</option>';
        echo '</select></label>';PlayMode::selector((string)($_POST['mode']??'normal'));
        echo '<p class="subtle">Crie seu jogador e gire a roleta. Depois, escolha entre as ofertas de clubes do país de estreia. No Brasil, buscamos clubes próximos da cidade natal; em SP, o perfil abre a A4 ou a A3. No exterior, a entrada é na divisão mais baixa disponível.</p><button class="btn btn-primary btn-lg">Criar jogador →</button></form></section>';
    }
}
