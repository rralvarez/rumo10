<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$r=new Repository(Database::connect());$accounts=[];$checks=0;
function slotCheck(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function slotRejected(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException){return true;}}
try {
 for($i=0;$i<2;$i++){$r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['slots-'.bin2hex(random_bytes(12)).'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);$accounts[]=(int)$r->db->lastInsertId();}
 [$free,$paid]=$accounts;$r->query("INSERT INTO rumo10_conta_planos(conta_id,plano) VALUES(?,'pago')",[$paid]);
 slotCheck(CareerSlots::quota($r,$free)['limit']===3,'Gratuito usa três');slotCheck(CareerSlots::quota($r,$paid)['limit']===10,'Pago usa dez');
 for($i=0;$i<3;$i++)$r->create($free,Game::legacyCreate('Fixture '.$i,'São Paulo','SP','meia'),$i>0);
 $rows=CareerSlots::listing($r,$free);slotCheck(count($rows)===3,'Arquivadas contam');$active=$r->load($free);$activeId=$active['id'];unset($active);
 slotCheck(slotRejected(fn()=>$r->create($free,Game::legacyCreate('Excedente','São Paulo','SP','meia'),true)),'Quarta rejeitada');slotCheck((int)$r->load($free)['id']===$activeId,'Bloqueio não arquiva nem troca a atual');
 $victim=(int)$rows[1]['id'];slotCheck(slotRejected(fn()=>CareerSlots::delete($r,$paid,$victim,'delete')),'Não exclui carreira alheia');slotCheck(slotRejected(fn()=>CareerSlots::delete($r,$free,$victim,'')),'Exige confirmação');
 CareerSlots::delete($r,$free,$victim,'delete');slotCheck(CareerSlots::quota($r,$free)['used']===2,'Exclusão libera vaga');
 foreach(['rumo10_carreiras','rumo10_temporadas','rumo10_decisoes','rumo10_atributos','rumo10_estados','rumo10_transferencias','rumo10_universos','rumo10_carreiras_arquivadas'] as $table){$key=$table==='rumo10_carreiras'?'id':'carreira_id';slotCheck((int)$r->query("SELECT COUNT(*) FROM $table WHERE $key=?",[$victim])->fetchColumn()===0,'Dependência removida '.$table);}
 $r->create($free,Game::legacyCreate('Reposição','São Paulo','SP','meia'),true);slotCheck(CareerSlots::quota($r,$free)['used']===3,'Pode criar após excluir');
 for($i=0;$i<10;$i++)$r->create($paid,Game::create('Pago '.$i,'São Paulo','SP','meia'),$i>0);
 slotCheck(CareerSlots::quota($r,$paid)['used']===10,'Dez carreiras pagas');slotCheck(slotRejected(fn()=>$r->create($paid,Game::create('Onze','São Paulo','SP','meia'),true)),'Décima primeira rejeitada');
 $archived=CareerSlots::listing($r,$paid)[1]['id'];$r->restore($paid,(int)$archived);slotCheck(CareerSlots::quota($r,$paid)['used']===10,'Selecionar não consome vaga');
 $r->query("UPDATE rumo10_conta_planos SET plano='gratuito' WHERE conta_id=?",[$paid]);slotCheck(CareerSlots::quota($r,$paid)['used']===10,'Mudança de plano preserva carreiras');slotCheck(slotRejected(fn()=>$r->create($paid,Game::create('Bloqueado','São Paulo','SP','meia'),true)),'Plano menor bloqueia novas');
 echo "OK: $checks verificações de vagas, propriedade e exclusão.\n";
}finally{foreach($accounts as $account){foreach(CareerSlots::listing($r,$account) as $c)CareerSlots::delete($r,$account,(int)$c['id'],'delete');$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}}
