<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function momentumCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function momentumGame(int $minute=15):array{
    $g=Game::legacyCreate('Teste de jogada','São Paulo','SP','centroavante');$g['id']=0;$g['stage']='partida';$g['states']['risco_lesao']=0;
    $i=Game::currentIndex($g);$g['matches'][$i]['status']='em_andamento';$g['matches'][$i]['minute']=$minute;$g['matches'][$i]['entry']=0;$g['matches'][$i]['exit']=90;
    return $g;
}
// Roteiro determinístico de duas ações bem executadas, pelo processamento real de campo.
$field=new ReflectionMethod(Game::class,'fieldDecision');$g=momentumGame();$i=Game::currentIndex($g);
$o=['text'=>'Atacar o espaço','action'=>'social','effects'=>[]];$roll=['success'=>true,'class'=>'sucesso'];
for($n=0;$n<2;$n++){$g['feedback']=$roll+['text'=>'Você ganha espaço.','effects'=>[]];$field->invokeArgs(null,[&$g,$o,$roll,1118,0]);}
momentumCheck(MatchMomentum::available($g),'Duas ações constroem chance clara.');
momentumCheck($g['matches'][$i]['minute']===35,'Chance imediata sem pular mais um segmento.');
momentumCheck(str_contains($g['feedback']['text'],'chance clara de gol'),'Sequência narrada.');
momentumCheck(Game::situation($g)['id']===MatchMomentum::SITUATION,'Situação prioritária.');
$reload=json_decode(json_encode($g,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
momentumCheck(Game::choices($reload)===Game::choices($g),'Refresh conserva quatro escolhas.');
$normal=['action'=>'chute','effects'=>[]];momentumCheck(Game::chance($g,Game::choices($g)[0])>Game::chance($g,$normal),'Chance maior que chute comum.');
foreach(Game::choices($g) as $choice)momentumCheck(Game::chance($g,$choice)<=90,'Chance não garante gol.');
$seen=[0=>[false=>0,true=>0],1=>[false=>0,true=>0],2=>[false=>0,true=>0],3=>[false=>0,true=>0]];
// Comandos reais com sorteios: todo sucesso da chance clara produz exatamente um gol ou uma assistência.
foreach(range(0,3) as $option)for($n=0;$n<100;$n++){
    $sample=$g;$choices=Game::choices($sample);$p=Game::chance($sample,$choices[$option]);$old=$sample['matches'][$i]['stats'];
    $result=Game::command($sample,'choice',['option'=>$option]);$st=$result['matches'][$i]['stats'];$success=$result['feedback']['success'];$seen[$option][$success]++;
    momentumCheck($result['feedback']['chance']===$p,'Porcentagem exibida é a chance sorteada.');
    momentumCheck($st['goals']-$old['goals']===($success && $option!==2?1:0),'Gol pessoal corresponde ao único sorteio.');
    momentumCheck($st['assists']-$old['assists']===($success && $option===2?1:0),'Assistência corresponde ao único sorteio.');
    momentumCheck(($result['feedback']['highlight']??null)===($success?($option===2?'assist':'goal'):null),'Destaque corresponde ao resultado.');
    momentumCheck(!MatchMomentum::available($result) && empty($result['matches'][$i]['attack_sequence']),'Uma chance é consumida uma única vez.');
    momentumCheck($result['matches'][$i]['minute']===45,'Retoma o relógio e o intervalo.');
}
foreach($seen as $outcomes)momentumCheck($outcomes[true]>0 && $outcomes[false]>0,'Gols e oportunidades perdidas continuam possíveis.');
$m=Schedule::match(1,'A','B');MatchMomentum::record($m,true,true,false);momentumCheck(!MatchMomentum::record($m,false,true,false) && empty($m['attack_sequence']),'Falha quebra sequência.');
MatchMomentum::record($m,true,true,false);momentumCheck(!MatchMomentum::record($m,true,false,false) && empty($m['attack_sequence']),'Conversa ou reclamação não constrói ataque.');
MatchMomentum::record($m,true,true,false);momentumCheck(!MatchMomentum::record($m,true,true,true) && empty($m['attack_sequence']),'Gol ou assistência encerra construção.');
foreach(['intervalo','finalizada'] as $status){$sample=$g;$sample['matches'][$i]['status']=$status;momentumCheck(!MatchMomentum::available($sample),'Sem chance fora do jogo em andamento.');}
$sample=$g;$sample['matches'][$i]['exit']=35;momentumCheck(!MatchMomentum::available($sample),'Sem chance após saída de campo.');
$sample=$g;$sample['stage']='treino';momentumCheck(!MatchMomentum::available($sample),'Treino não usa sequência de partida.');
$late=momentumGame(80);$late['matches'][Game::currentIndex($late)]['attack_sequence']=1;$late['feedback']=$roll+['text'=>'Você ganha espaço.','effects'=>[]];$field->invokeArgs(null,[&$late,$o,$roll,1118,0]);
momentumCheck(MatchMomentum::available($late) && $late['matches'][Game::currentIndex($late)]['minute']===80,'Chance aos 80 antes do apito final.');
$tired=momentumGame();$tired['states']['energia']=16;$tired['matches'][Game::currentIndex($tired)]['attack_sequence']=1;$tired['feedback']=$roll+['text'=>'Você ganha espaço.','effects'=>[]];$field->invokeArgs(null,[&$tired,$o,$roll,1118,0]);
momentumCheck(!MatchMomentum::available($tired) && empty($tired['matches'][Game::currentIndex($tired)]['clear_chance']),'Saída por desgaste cancela oportunidade.');
$fresh=momentumGame();momentumCheck(!MatchMomentum::available($fresh) && !isset($fresh['matches'][Game::currentIndex($fresh)]['attack_sequence']),'Outra partida começa sem sequência anterior.');
$sample=momentumGame();$sample['active_situation']=['id'=>1118,'key'=>Scenes::key($sample)];momentumCheck(str_contains(Game::choices($sample)[1]['success_text'],'devolução'),'Tabela tem descrição própria.');
echo "OK: $checks verificações de sequência, chance elevada, gol, assistência, falha e consumo único.\n";
if(in_array('--mysql',$argv??[],true)){
    $r=new Repository(Database::connect());$account=null;$career=null;
    try{
        $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['momentum-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$account=(int)$r->db->lastInsertId();
        Scenes::prepare($g);$r->create($account,$g);$saved=$r->load($account);$career=$saved['id'];
        momentumCheck(MatchMomentum::available($saved),'MySQL conserva oportunidade pendente.');
        momentumCheck(Game::choices($saved)===Game::choices($g),'MySQL conserva escolhas e chances.');
        $key=bin2hex(random_bytes(32));$version=$saved['version'];$result=$r->command($account,'choice',['option'=>0],$key,$version);
        momentumCheck($r->load($account)===$result,'Reload exato após finalizar oportunidade.');
        momentumCheck($r->command($account,'choice',['option'=>0],$key,$version)===$result,'Replay não duplica finalização ou gol.');
        momentumCheck(!MatchMomentum::available($result),'Oportunidade consumida após persistência.');
        try{$r->command($account,'choice',['option'=>0],bin2hex(random_bytes(32)),$version);throw new RuntimeException('Aceitou tela desatualizada.');}catch(InvalidArgumentException){momentumCheck(true,'Tela antiga recusada.');}
        echo "OK: MySQL conserva chance clara, resultado, replay e proteção de versão.\n";
    }finally{
        if($account){if($career)CareerSlots::delete($r,$account,$career,'delete');$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}
    }
}
