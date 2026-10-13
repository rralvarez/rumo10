<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$r=new Repository(Database::connect());$account=null;$checks=0;
function entryDbCheck(bool $ok,string $message):void {global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
try {
    $r->query('INSERT INTO rumo10_contas(email,senha_hash) VALUES(?,?)',['entrada-'.bin2hex(random_bytes(8)).'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $account=(int)$r->db->lastInsertId();
    foreach([['Aracaju','SE'],['Vilhena','RO'],['Londrina','PR']] as [$city,$uf]) {
        $r->create($account,Game::create('Teste entrada',$city,$uf,'meia'),true);$g=$r->load($account);
        entryDbCheck($g['starting'] && !$g['matches'],'Sem clube antes da roleta');
        $key=bin2hex(random_bytes(32));$g=$r->command($account,'handicap',[],$key,$g['version']);
        entryDbCheck($r->load($account)===$g,'Ofertas geográficas persistidas');
        entryDbCheck(count($g['initial_candidates'])===($uf==='RO'?2:3),'Quantidade real de clubes');
        entryDbCheck($r->command($account,'handicap',[],$key,$g['version']-1)===$g,'Replay conserva sorteio e ofertas');
        $key=bin2hex(random_bytes(32));$version=$g['version'];$chosen=$g['initial_candidates'][0]['club'];
        $g=$r->command($account,'join',['option'=>'0'],$key,$version);
        entryDbCheck($g['club']===$chosen && !$g['starting'],'Contrato inicial salvo');
        entryDbCheck($r->command($account,'join',['option'=>'0'],$key,$version)===$g,'Replay não recria calendário');
        entryDbCheck($r->load($account)===$g,'Calendário exato após reload');
        $g=$r->command($account,'choice',['option'=>'1'],bin2hex(random_bytes(32)),$g['version']);
        entryDbCheck($g['stage']==='pre_jogo' && $r->load($account)===$g,'Treino estadual persistido');
    }
    echo "OK: $checks verificações MySQL de entrada em SE/RO/PR.\n";
} finally {
    if($account) {
        $ids=$r->query('SELECT id FROM rumo10_carreiras WHERE conta_id=? UNION SELECT carreira_id FROM rumo10_carreiras_arquivadas WHERE conta_id=?',[$account,$account])->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id) {
            $r->query('DELETE FROM rumo10_carreiras_arquivadas WHERE carreira_id=?',[$id]);
            foreach(['rumo10_decisoes','rumo10_atributos','rumo10_estados'] as $table)$r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
            $r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
        }$r->query('DELETE FROM rumo10_contas WHERE id=?',[$account]);
    }
}
