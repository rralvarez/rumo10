<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require 'C:/inetpub/wwwroot/rumohub/app/bootstrap.php';
if(config('APP_ENV','local')!=='local')throw new RuntimeException('Fixture somente local.');
putenv('HUB_BENEFITS_ENABLED=1');$db=Database::connect();if($db->query('SELECT DATABASE()')->fetchColumn()!=='rumohub')throw new RuntimeException('Banco inesperado.');
$tag='qa-growth-'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(20));$users=[];$accounts=[];$admin=0;$checks=0;
function benef(bool $ok,string $what):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($what);}
function deny(callable $op,int $status):void{try{$op();}catch(HubHttpError $e){benef($e->status===$status,'Rejeição adequada');return;}throw new RuntimeException('Operação inválida aceita');}
function qx(string $sql,array $args=[]):PDOStatement{global $db;return HubBenefits::q($db,$sql,$args);}
try{
 $email=$tag.'-admin@example.invalid';qx('INSERT INTO rh_admins(email,senha_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$admin=(int)$db->lastInsertId();
 qx("INSERT INTO rh_usuarios(email,senha_hash,plano,admin_hub) VALUES(?,?,'pago',1)",[$email,password_hash($password,PASSWORD_DEFAULT)]);$owner=(int)$db->lastInsertId();$users[]=$owner;
 benef(!HubBenefits::effective($db,$owner,'rumo10')['paid'],'Pago global não é licença do jogo');
 $nonce=bin2hex(random_bytes(32));HubBenefits::grant($db,$admin,$owner,['rumo10'],'Fixture local de cortesia',null,$nonce);HubBenefits::grant($db,$admin,$owner,['rumo10'],'Fixture local de cortesia',null,$nonce);
 benef((int)qx('SELECT COUNT(*) FROM rh_beneficios_jogo WHERE usuario_id=?',[$owner])->fetchColumn()===1,'Concessão idempotente');
 benef(HubBenefits::effective($db,$owner,'rumo10')['paid'],'Cortesia Rumo10 válida');
 $profile=HubUsers::get($db,$owner);benef(HubUsers::forClient($profile,'rumo10')['plan']==='pago' && HubUsers::forClient($profile,'produto-inexistente')['plan']==='gratuito','Plano devolvido por cliente, nunca licença universal');
 foreach($db->query("SELECT slug FROM rh_hub_games WHERE slug<>'rumo10'") as $row)benef(!HubBenefits::effective($db,$owner,$row['slug'])['paid'],'Outro jogo não liberado automaticamente');
 deny(fn()=>HubBenefits::grant($db,$admin,$owner,['rumo10'],'Fixture divergente sem exposição',null,$nonce),409);
 $id=(int)qx('SELECT id FROM rh_beneficios_jogo WHERE usuario_id=?',[$owner])->fetchColumn();HubBenefits::revoke($db,$admin,$id,'Encerramento da fixture',bin2hex(random_bytes(32)),'revogar');benef(!HubBenefits::effective($db,$owner,'rumo10')['paid'],'Revogação específica');
 deny(fn()=>HubBenefits::grant($db,$admin,$owner,['rumo10'],'Fixture validade passada','2020-01-01 00:00:00',bin2hex(random_bytes(32))),422);
 $expiryKey=bin2hex(random_bytes(32));HubBenefits::grant($db,$admin,$owner,['rumo10'],'Fixture de expiração',gmdate('Y-m-d H:i:s',time()+3600),$expiryKey);
 qx("UPDATE rh_beneficios_jogo SET fim='2020-01-01 00:00:00' WHERE usuario_id=? AND chave=?",[$owner,'grant:'.hash('sha256',$expiryKey.'|rumo10')]);
 benef(!HubBenefits::effective($db,$owner,'rumo10')['paid'],'Expiração retira apenas o direito vigente');
 $otherGame=$db->query("SELECT slug FROM rh_hub_games WHERE slug<>'rumo10' LIMIT 1")->fetchColumn();
 if($otherGame){HubBenefits::grant($db,$admin,$owner,[$otherGame],'Fixture de seleção explícita',null,bin2hex(random_bytes(32)));benef(HubBenefits::effective($db,$owner,$otherGame)['paid'] && !HubBenefits::effective($db,$owner,'rumo10')['paid'],'Outro produto somente com seleção explícita, sem promover Rumo10');}
 $invite=HubReferrals::status($db,$owner)['token'];benef(strlen($invite)===64 && HubReferrals::status($db,$owner)['token']===$invite,'Convite estável e imprevisível');
 deny(fn()=>HubReferrals::attribute($db,$owner,$invite),422);
 $friend=HubUsers::register($db,$tag.'-friend@example.invalid',$password,'qa-local',$invite);$users[]=$friend['id'];
 $oldLogin=HubUsers::api($db,'/api/v1/identity/login',['email'=>$email,'password'=>$password,'origin'=>'qa-local','invite'=>$invite],'rumo10');
 benef($oldLogin['user']['id']===$owner && (int)qx('SELECT COUNT(*) FROM rh_indicacoes WHERE convidado_id=?',[$owner])->fetchColumn()===0,'Login de conta existente ignora convite e não cria atribuição');
 $otherInvite=HubReferrals::status($db,$friend['id'])['token'];deny(fn()=>HubUsers::register($db,$tag.'-friend@example.invalid',$password,'qa-local',$otherInvite),422);
 benef((int)qx('SELECT convidante_id FROM rh_indicacoes WHERE convidado_id=?',[$friend['id']])->fetchColumn()===$owner,'Outro link não reatribui conta já cadastrada');
 benef(HubReferrals::status($db,$owner)['available']===0,'Cadastro isolado não gera crédito');
 deny(fn()=>HubReferrals::qualify($db,$friend['id'],999999999,bin2hex(random_bytes(32))),409);
 benef(HubReferrals::status($db,$owner)['available']===0,'Formulário sem carreira persistida não qualifica');
 qx('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',[$tag.'-friend@example.invalid',password_hash($password,PASSWORD_DEFAULT)]);$account=(int)$db->lastInsertId();$accounts[]=$account;qx('INSERT INTO rumo10_identidades VALUES(?,?)',[$account,$friend['id']]);
 qx("INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,'Fixture','São Paulo','SP','meia',18,'handicap','Sem clube')",[$account]);$career=(int)$db->lastInsertId();$key=bin2hex(random_bytes(32));qx('INSERT INTO rumo10_criacoes(conta_id,chave,carreira_id,request_hash) VALUES(?,?,?,?)',[$account,$key,$career,hash('sha256','fixture')]);
 HubReferrals::qualify($db,$friend['id'],$career,$key);HubReferrals::qualify($db,$friend['id'],$career,$key);benef(HubReferrals::status($db,$owner)['available']===1,'Primeira carreira gera exatamente um crédito');
 $public=HubReferrals::status($db,$owner);benef(!str_contains(json_encode($public,JSON_THROW_ON_ERROR),'@'),'E-mails nunca são expostos no status de indicação');
 HubReferrals::consent($db,$friend['id'],'Apelido de fixture',true);benef(HubReferrals::status($db,$owner)['items'][0]['reference']==='Apelido de fixture','Nome somente com consentimento');HubReferrals::consent($db,$friend['id'],'',false);benef(str_starts_with(HubReferrals::status($db,$owner)['items'][0]['reference'],'convite #'),'Retirada do consentimento remove nome');
 $reserveKey=bin2hex(random_bytes(32));$reservation=HubReferrals::reserve($db,$owner,$reserveKey);benef(HubReferrals::reserve($db,$owner,$reserveKey)===$reservation,'Reserva idempotente');benef(HubReferrals::status($db,$owner)['available']===0,'Reserva não pode ser gasta duas vezes');
 deny(fn()=>HubReferrals::settle($db,$owner,$reservation['credit_id'],$reserveKey,$career),409);
 HubReferrals::settle($db,$owner,$reservation['credit_id'],$reserveKey,null);benef(HubReferrals::status($db,$owner)['available']===1,'Falha antes da criação libera apenas reserva não consumida');
 $retry=HubReferrals::reserve($db,$owner,$reserveKey);benef($retry['credit_id']===$reservation['credit_id'],'Retry depois de rollback pode reservar novamente sem colisão no ledger');
 qx('UPDATE rh_creditos_indicacao SET reservado_em=UTC_TIMESTAMP()-INTERVAL 16 MINUTE WHERE id=? AND reserva=?',[$retry['credit_id'],$reserveKey]);
 benef(HubReferrals::status($db,$owner)['available']===1,'Status recupera reserva órfã mesmo com saldo inicialmente zero');
 qx('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',[$tag.'-owner@example.invalid',password_hash($password,PASSWORD_DEFAULT)]);$ownerAccount=(int)$db->lastInsertId();$accounts[]=$ownerAccount;qx('INSERT INTO rumo10_identidades VALUES(?,?)',[$ownerAccount,$owner]);
 $reserveKey=bin2hex(random_bytes(32));$reservation=HubReferrals::reserve($db,$owner,$reserveKey);
 qx("INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,'Fixture extra','São Paulo','SP','meia',18,'handicap','Sem clube')",[$ownerAccount]);$extra=(int)$db->lastInsertId();
 qx("INSERT INTO rumo10_origem_vagas(carreira_id,tipo,credito_id,reserva) VALUES(?,'INDICACAO',?,?)",[$extra,$reservation['credit_id'],$reserveKey]);qx("INSERT INTO rumo10_beneficios_outbox(conta_id,carreira_id,evento,chave,credito_id,reserva) VALUES(?,?,'consumir_credito',?,?,?)",[$ownerAccount,$extra,hash('sha256',$reserveKey),$reservation['credit_id'],$reserveKey]);
 HubReferrals::settle($db,$owner,$reservation['credit_id'],$reserveKey,$extra);HubReferrals::settle($db,$owner,$reservation['credit_id'],$reserveKey,$extra);benef(HubReferrals::status($db,$owner)['used']===1,'Consumo definitivo e idempotente');
 $retiring=Game::legacyCreate('Fixture aposentada','São Paulo','SP','meia');$retiring['id']=$extra;$retiring['age']=35;$retiring['stage']='treino';$retiring=Game::command($retiring,'retire',['confirm'=>'retire']);
 $db->beginTransaction();RetiredCareer::persist(new Repository($db),$ownerAccount,$retiring);$db->commit();
 benef(HubReferrals::status($db,$owner)['available']===0 && HubReferrals::status($db,$owner)['used']===1,'Aposentadoria compactada não devolve crédito');
 benef(count(CareerSlots::listing(new Repository($db),$ownerAccount))===0,'Resumo aposentado libera vaga sem se tornar retomável');
 qx('DELETE FROM rumo10_carreiras WHERE id=?',[$extra]);HubReferrals::settle($db,$owner,$reservation['credit_id'],$reserveKey,null);benef(HubReferrals::status($db,$owner)['available']===0,'Exclusão não devolve crédito');
 benef((int)qx("SELECT COUNT(*) FROM rh_indicacoes_ledger WHERE usuario_id=? AND acao='consumir'",[$owner])->fetchColumn()===1,'Ledger não duplica consumo');
 $indication=(int)qx('SELECT id FROM rh_indicacoes WHERE convidante_id=?',[$owner])->fetchColumn();HubReferrals::block($db,$admin,$indication,'Revisão da fixture consumida',bin2hex(random_bytes(32)));
 benef(HubReferrals::status($db,$owner)['used']===1 && HubReferrals::status($db,$owner)['items'][0]['state']==='em revisão','Revisão antifraude preserva crédito já consumido e marca a indicação');
 echo "OK: $checks verificações MySQL locais de benefícios por jogo, cortesia, cadastro, comprovação, replay, reserva, consumo e privacidade.\n";
}finally{
 if($db->inTransaction())$db->rollBack();
 foreach($accounts as $aid){qx('DELETE FROM rumo10_carreiras WHERE conta_id=?',[$aid]);qx('DELETE FROM rumo10_contas WHERE id=?',[$aid]);}
 foreach($users as $uid){qx('DELETE a FROM rh_beneficios_audit a JOIN rh_beneficios_jogo b ON b.id=a.beneficio_id WHERE b.usuario_id=?',[$uid]);qx('DELETE FROM rh_beneficios_jogo WHERE usuario_id=?',[$uid]);qx('DELETE FROM rh_indicacoes_ledger WHERE usuario_id=?',[$uid]);qx('DELETE FROM rh_creditos_indicacao WHERE usuario_id=?',[$uid]);}
 foreach($users as $uid){qx('DELETE FROM rh_indicacoes WHERE convidante_id=? OR convidado_id=?',[$uid,$uid]);qx('DELETE FROM rh_convites WHERE usuario_id=?',[$uid]);qx('DELETE FROM rh_primeiras_carreiras WHERE usuario_id=?',[$uid]);qx('DELETE FROM rh_perfis_publicos WHERE usuario_id=?',[$uid]);qx('DELETE FROM rh_usuarios WHERE id=?',[$uid]);}
 if($admin)qx('DELETE FROM rh_admins WHERE id=?',[$admin]);
}
