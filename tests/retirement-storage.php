<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';$r=new Repository(Database::connect());$accounts=[];$checks=0;
function retireCheck(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
function retireRejected(callable $fn):bool{try{$fn();return false;}catch(InvalidArgumentException){return true;}}
try{
 for($i=0;$i<2;$i++){$r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['retire-storage-'.bin2hex(random_bytes(10)).'@example.invalid',password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT)]);$accounts[]=(int)$r->db->lastInsertId();}[$account,$other]=$accounts;
 $g=Game::legacyCreate('Fixture aposentadoria','São Paulo','SP','meia');$g['age']=35;$g['stage']='treino';$g['matches'][0]['status']='finalizada';$g['matches'][0]['stats']['minutes']=90;$g['matches'][0]['stats']['goals']=2;$g['matches'][0]['stats']['assists']=1;
 $g['story_seed']='fixture-resumo';$g['history'][]=['club'=>'Clube anterior','next_club'=>$g['club'],'season'=>0,'games'=>10,'minutes'=>900,'goals'=>5,'assists'=>3];$g['competition_results']=['teste'=>['name'=>'Competição de fixture','champion'=>$g['club'],'position'=>1,'titles'=>[]]];
 $expected=CareerStats::collect($g);$r->create($account,$g);$saved=$r->load($account);$id=$saved['id'];$key=bin2hex(random_bytes(32));
 // Resumo e expurgo participam da mesma transação: rollback conserva os dados originais.
 $r->db->beginTransaction();$candidate=Game::command($saved,'retire',['confirm'=>'retire']);RetiredCareer::persist($r,$account,$candidate);$r->db->rollBack();
 retireCheck($r->load($account)===$saved && (int)$r->query('SELECT COUNT(*) FROM rumo10_historico_encerrado WHERE carreira_id=?',[$id])->fetchColumn()===0,'Rollback preserva detalhes e não deixa resumo parcial');
 retireCheck(retireRejected(fn()=>RetiredCareer::compactExisting($r,$account,$id,'compact')),'Não compacta carreira em atividade');
 $closed=$r->command($account,'retire',['confirm'=>'retire'],$key,$saved['version']);
 retireCheck(isset($closed['retired_summary']) && $closed['stage']==='aposentado','Resumo substitui universo');retireCheck(CareerStats::collect($closed)['totals']===$expected['totals'],'Preserva totais');retireCheck(CareerStats::collect($closed)['clubs']===$expected['clubs'],'Preserva totais por clube');retireCheck(count($closed['retired_summary']['titles'])===1,'Preserva conquista');
 retireCheck(CareerSlots::quota($r,$account)['used']===0,'Aposentadoria libera vaga');retireCheck($r->command($account,'retire',['confirm'=>'retire'],$key,$saved['version'])===$closed,'Replay sem reconstruir detalhes');retireCheck(retireRejected(fn()=>$r->command($account,'mode',['mode'=>'normal'],$key,$closed['version'])),'Token não autoriza outro comando');
 $sizes=$r->query('SELECT bytes_detalhes,bytes_resumo FROM rumo10_historico_encerrado WHERE carreira_id=?',[$id])->fetch();retireCheck((int)$sizes['bytes_resumo']>0 && (int)$sizes['bytes_resumo']<(int)$sizes['bytes_detalhes'],'Resumo menor que o universo de fixture');
 foreach(['rumo10_universos','rumo10_atributos','rumo10_estados','rumo10_decisoes','rumo10_temporadas','rumo10_transferencias'] as $table)retireCheck((int)$r->query("SELECT COUNT(*) FROM $table WHERE carreira_id=?",[$id])->fetchColumn()===0,'Detalhes removidos '.$table);
 retireCheck(retireRejected(fn()=>RetiredCareer::owned($r,$other,$id)),'Histórico privado');
 $r->create($account,Game::create('Outra carreira','São Paulo','SP','meia'),true);retireCheck(retireRejected(fn()=>$r->restore($account,$id)),'Resumo não pode ser retomado');
 $summary=RetiredCareer::owned($r,$account,$id);retireCheck($summary===$closed['retired_summary'],'Criar outra preserva resumo');retireCheck(!isset($summary['matches']) && !isset($summary['memberships']),'Sem universo escondido no resumo');
 retireCheck(!in_array($id,array_column($r->archives($account),'id')),'Resumo não aparece como arquivo retomável');
 // Fixture de aposentadoria anterior: permanece detalhada até consentimento explícito.
 $old=Game::command($g,'retire',['confirm'=>'retire']);$r->create($account,$old,true);$legacy=$r->load($account);$oldId=$legacy['id'];retireCheck(!isset($legacy['retired_summary']),'Antiga não é compactada pela leitura');
 retireCheck(retireRejected(fn()=>RetiredCareer::compactExisting($r,$account,$oldId,'')),'Legado exige confirmação');retireCheck(retireRejected(fn()=>RetiredCareer::compactExisting($r,$other,$oldId,'compact')),'Não compacta carreira alheia');
 RetiredCareer::compactExisting($r,$account,$oldId,'compact');$compact=$r->load($account);RetiredCareer::compactExisting($r,$account,$oldId,'compact');retireCheck($r->load($account)===$compact,'Compactação antiga idempotente');
 retireCheck(CareerStats::collect($compact)['totals']===$expected['totals'],'Legado preserva estatísticas');
 CareerSlots::delete($r,$account,$oldId,'delete');retireCheck((int)$r->query('SELECT COUNT(*) FROM rumo10_historico_encerrado WHERE carreira_id=?',[$oldId])->fetchColumn()===0,'Exclusão apaga também resumo');
 // Aposentadoria obrigatória pelo comando que encerra a temporada.
 $forced=$g;$forced['age']=39;$forced['stage']='resumo';$r->create($account,$forced);$f=$r->load($account);$f=$r->command($account,'offers',[],bin2hex(random_bytes(32)),$f['version']);retireCheck($f['age']===40 && isset($f['retired_summary']) && $f['retirement']['reason']==='mandatory','Obrigatória também compacta');
 echo "OK: $checks verificações de histórico compacto e aposentadoria.\n";
}finally{foreach($accounts as $account){$ids=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=? UNION SELECT carreira_id FROM rumo10_carreiras_arquivadas WHERE conta_id=? UNION SELECT carreira_id FROM rumo10_historico_encerrado WHERE conta_id=?',[$account,$account,$account])->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id)CareerSlots::delete($r,$account,(int)$id,'delete');$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);}}
