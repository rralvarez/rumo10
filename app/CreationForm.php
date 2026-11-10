<?php
declare(strict_types=1);
final class CreationForm {
    public static function render(string $path,?array $existing,string $plan):void {
        echo '<section class="panel auth"><h1>Quem entra em campo?</h1><p><a href="/carreiras">Ver vagas e gerenciar minhas carreiras</a></p>';
        if($existing)echo '<p>Sua carreira atual será arquivada e poderá ser retomada depois.</p>';
        form($path==='/carreira/nova'?'/carreira/nova':'/carreira/criar');echo '<input type="hidden" name="creation_key" value="'.e($_POST['creation_key']??bin2hex(random_bytes(32))).'">';
        echo '<label>Nome do jogador<input name="name" class="form-control" minlength="2" maxlength="60" value="'.e($_POST['name']??'').'" required></label>';
        $countries=$plan==='pago'?EntryCountries::countries():['BR'=>'Brasil'];$selected=is_string($_POST['country']??null) && isset($countries[$_POST['country']])?$_POST['country']:'BR';
        echo '<label>País onde começa a carreira<select name="country" class="form-select" required>';
        foreach($countries as $code=>$name){$available=$code==='BR'||EntryCountries::entryStatus($code)['valid'];echo '<option value="'.e($code).'" '.($code===$selected || $plan!=='pago'?'selected':'').($available?'':' disabled').'>'.e($name).($available?'':' · temporariamente indisponível').'</option>';}
        echo '</select></label><p class="subtle">'.($plan==='pago'?'Seu plano permite começar em qualquer país disponível.':'No plano gratuito, sua carreira começa no Brasil.').'</p>';
        echo '<p class="subtle">Alguns destinos de estreia estão temporariamente indisponíveis enquanto corrigimos a continuidade das competições. Informe sua residência corretamente; ela não precisa ser alterada por causa dessa restrição.</p><label>País onde mora atualmente<select name="residence_country" class="form-select">';
        foreach(EntryCountries::countries() as $code=>$name)echo '<option value="'.e($code).'" '.($code===($_POST['residence_country']??'BR')?'selected':'').'>'.e($name).'</option>';
        echo '</select></label><label data-brazil-location>Estado onde mora atualmente<select name="uf" class="form-select">';
        foreach(explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO') as $uf)echo '<option '.($uf===($_POST['uf']??'SP')?'selected':'').'>'.e($uf).'</option>';
        echo '</select></label><label data-foreign-location hidden>Estado ou região atual<input name="residence_region" class="form-control" maxlength="60" value="'.e($_POST['residence_region']??'').'"></label><label data-foreign-location hidden>Estado da estreia, se começar no Brasil<select name="entry_uf" class="form-select">';
        foreach(explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO') as $uf)echo '<option '.($uf===($_POST['entry_uf']??'SP')?'selected':'').'>'.e($uf).'</option>';
        echo '</select></label><label>Cidade onde mora atualmente<input name="city" class="form-control" list="city-options" autocomplete="address-level2" minlength="2" maxlength="80" value="'.e($_POST['city']??'').'" required><datalist id="city-options"></datalist></label>';
        echo '<label>Posição<select class="form-select" name="position">';
        foreach(Rules::POSITIONS as $position)echo '<option value="'.e($position).'" '.($position===($_POST['position']??'')?'selected':'').'>'.e(ucfirst($position)).'</option>';
        echo '</select></label>';PlayMode::selector((string)($_POST['mode']??'normal'));
        echo '<p class="subtle">Sua residência é independente do país de estreia e não define nacionalidade. No Brasil, usamos a cidade atual para sugerir clubes próximos quando temos coordenadas. Para quem mora fora, oferecemos clubes do estado de estreia sem inventar distâncias. Em SP, o perfil abre a A4 ou a A3; no exterior, a divisão mais baixa disponível.</p><button class="btn btn-primary btn-lg">Criar jogador →</button></form></section>';
    }
}
