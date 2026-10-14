<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function entryCountryCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function entryCountryRejected(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException){return true;}}
$countries=EntryCountries::countries();entryCountryCheck(isset($countries['BR'],$countries['EN'],$countries['AR']),'Brasil e mercados externos disponíveis.');
foreach($countries as $country=>$label){
    entryCountryCheck(EntryCountries::validate($country,'pago')===$country,'Pago acessa país do catálogo.');
    if($country!=='BR')entryCountryCheck(entryCountryRejected(fn()=>EntryCountries::validate($country,'gratuito')),'Gratuito não inicia no exterior.');
    $g=EntryCountries::create('Teste países','São Paulo','SP','meia',$country,'pago');entryCountryCheck($g['stage']==='handicap' && $g['club']==='Sem clube' && !$g['initial_candidates'],'Criação vem antes da roleta e das ofertas.');
    $g=Game::command($g,'handicap');entryCountryCheck($g['stage']==='clube_inicio' && count($g['initial_candidates'])>=1 && count($g['initial_candidates'])<=3,'Até três clubes após roleta.');
    foreach($g['initial_candidates'] as $offer){entryCountryCheck(TransferMarket::country($offer['club'])===$country,'Oferta pertence ao país escolhido.');entryCountryCheck(in_array($offer['club'],Competition::catalog()[$g['entry_division']]['teams'],true),'Oferta pertence à divisão selecionada.');}
    if($country!=='BR'){
        $tiers=EntryCountries::leagues()[$country];entryCountryCheck(Competition::catalog()[$g['entry_division']]['tier']===min($tiers),'Entrada na divisão mais baixa disponível.');
        entryCountryCheck(EntryCountries::candidates($country,$g['entry_division'],$g['entry_seed'])===$g['initial_candidates'],'Reload não muda ofertas.');
        foreach($g['initial_candidates'] as $offer)entryCountryCheck($offer['distance_km']===null,'Não inventa proximidade no exterior.');
    }
    $joined=Game::command($g,'join',['option'=>0]);entryCountryCheck($joined['stage']==='treino' && count($joined['matches'])>0 && TransferMarket::country($joined['club'])===$country,'Cada país oferecido inicia calendário jogável.');unset($joined);
}
entryCountryCheck(entryCountryRejected(fn()=>EntryCountries::validate('XX','pago')),'País inexistente rejeitado.');
function form(string $path,?array $g=null):void{echo '<form action="'.e($path).'" method="post">';}
$_POST=[];ob_start();CreationForm::render('/carreira/nova',null,'gratuito');$free=ob_get_clean();ob_start();CreationForm::render('/carreira/nova',null,'pago');$paid=ob_get_clean();
preg_match('/<select name="country".*?<\/select>/s',$free,$match);entryCountryCheck(substr_count($match[0],'<option')===1 && str_contains($match[0],'value="BR"'),'Formulário gratuito só permite Brasil.');
preg_match('/<select name="country".*?<\/select>/s',$paid,$match);entryCountryCheck(substr_count($match[0],'<option')===count($countries),'Pago vê todos os países disponíveis.');
entryCountryCheck(!str_contains($free,'name="club"') && !str_contains($paid,'name="club"'),'Clube não é escolhido antes da roleta.');
echo "OK: $checks verificações, ".count($countries)." países, planos, fluxo e ofertas.\n";
if(in_array('--mysql',$argv??[],true)){
    $r=new Repository(Database::connect());$account=null;
    try{
        $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['countries-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$account=(int)$r->db->lastInsertId();
        $foreign=EntryCountries::create('Teste no exterior','São Paulo','SP','meia','EN','pago');
        entryCountryCheck(entryCountryRejected(fn()=>$r->create($account,$foreign)),'Repositório recusa plano pago forjado na criação.');
        entryCountryCheck($r->load($account)===null,'Recusa não cria carreira.');
        $r->query("INSERT INTO rumo10_conta_planos(conta_id,plano) VALUES(?,'pago')",[$account]);$r->create($account,$foreign);$g=$r->load($account);
        entryCountryCheck($g['starting_country']==='EN' && $g['stage']==='handicap','País persistido antes da roleta.');
        $key=bin2hex(random_bytes(32));$version=$g['version'];$g=$r->command($account,'handicap',[],$key,$version);
        entryCountryCheck($r->load($account)===$g && $r->command($account,'handicap',[],$key,$version)===$g,'Ofertas persistidas e replay idempotente.');
        $g=$r->command($account,'join',['option'=>0],bin2hex(random_bytes(32)),$g['version']);
        entryCountryCheck($g['stage']==='treino' && TransferMarket::country($g['club'])==='EN' && count($g['matches'])>0,'Escolha gera campeonato estrangeiro jogável.');
        $r->query("UPDATE rumo10_conta_planos SET plano='gratuito' WHERE conta_id=?",[$account]);
        entryCountryCheck(entryCountryRejected(fn()=>$r->create($account,$foreign,true)) && $r->load($account)===$g,'Novo bloqueio não arquiva ou altera carreira existente.');
        $g=$r->command($account,'choice',['option'=>0],bin2hex(random_bytes(32)),$g['version']);entryCountryCheck($g['stage']==='pre_jogo','Mudança de plano não bloqueia carreira estrangeira já criada.');
        echo "OK: validação do plano real no MySQL, calendário estrangeiro, persistência e preservação.\n";
    }finally{if($account){foreach(CareerSlots::listing($r,$account) as $career)CareerSlots::delete($r,$account,(int)$career['id'],'delete');$r->query('DELETE FROM rumo10_conta_planos WHERE conta_id=?',[$account]);$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}}
}
