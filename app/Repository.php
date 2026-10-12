<?php
declare(strict_types=1);
final class Repository {
    public function __construct(public PDO $db) {}
    public function query(string $sql,array $args=[]): PDOStatement { $s=$this->db->prepare($sql); $s->execute($args); return $s; }
    public function load(int $account,bool $lock=false): ?array {
        $c=$this->query('SELECT * FROM rumo10_carreiras WHERE conta_id=?'.($lock?' FOR UPDATE':''),[$account])->fetch(); if(!$c) return null;
        $g=['id'=>(int)$c['id'],'name'=>$c['nome'],'city'=>$c['cidade'],'uf'=>$c['uf'],'position'=>$c['posicao'],'age'=>(int)$c['idade'],'season'=>(int)$c['temporada'],'round'=>(int)$c['rodada'],'stage'=>$c['etapa'],'club'=>$c['clube'],'tier'=>(int)$c['tier'],'handicap'=>$c['handicap'],'version'=>(int)$c['versao'],'feedback'=>json_decode($c['feedback_json']??'null',true),'skills'=>[],'states'=>[],'matches'=>[],'history'=>[],'offers'=>[],'teams'=>[]];
        foreach($this->query('SELECT * FROM rumo10_atributos WHERE carreira_id=?',[$g['id']]) as $a) $g['skills'][$a['habilidade']]=['value'=>(int)$a['valor'],'xp'=>(int)$a['xp']];
        foreach($this->query('SELECT * FROM rumo10_estados WHERE carreira_id=?',[$g['id']]) as $a) $g['states'][$a['indicador']]=(int)$a['valor'];
        $season=$this->query('SELECT id FROM rumo10_temporadas WHERE carreira_id=? AND numero=?',[$g['id'],$g['season']])->fetchColumn();
        $g['teams']=$this->query('SELECT nome FROM rumo10_times WHERE temporada_id=? ORDER BY nome',[$season])->fetchAll(PDO::FETCH_COLUMN);
        foreach($this->query('SELECT m.*,s.minutos,s.gols,s.assistencias,s.passes,s.passes_certos,s.finalizacoes,s.desarmes,s.faltas,s.cartoes,s.nota FROM rumo10_partidas m JOIN rumo10_estatisticas s ON s.partida_id=m.id WHERE m.temporada_id=? ORDER BY m.rodada,m.id',[$season]) as $m) {
            $st=[]; foreach(self::statMap() as $key=>$col) $st[$key]=$key==='rating'?(float)$m[$col]:(int)$m[$col];
            $log=[]; foreach($this->query('SELECT minuto,texto FROM rumo10_eventos_partida WHERE partida_id=? ORDER BY sequencia',[$m['id']]) as $l) $log[]=['minute'=>(int)$l['minuto'],'text'=>$l['texto']];
            $g['matches'][]=['round'=>(int)$m['rodada'],'home'=>$m['mandante'],'away'=>$m['visitante'],'hg'=>(int)$m['gols_mandante'],'ag'=>(int)$m['gols_visitante'],'minute'=>(int)$m['minuto'],'status'=>$m['status'],'lineup'=>$m['escalacao'],'entry'=>(int)$m['entrada'],'exit'=>(int)$m['saida'],'stats'=>$st,'log'=>$log];
        }
        foreach($this->query('SELECT resumo_json FROM rumo10_temporadas WHERE carreira_id=? AND finalizada=1 ORDER BY numero',[$g['id']]) as $h) $g['history'][]=json_decode($h['resumo_json'],true);
        foreach($this->query('SELECT * FROM rumo10_ofertas WHERE temporada_id=? ORDER BY clube',[$season]) as $o) $g['offers'][]=['club'=>$o['clube'],'tier'=>(int)$o['tier'],'peso'=>(int)$o['peso'],'selected'=>(bool)$o['selecionada']];
        return $g;
    }
    private static function statMap(): array { return ['minutes'=>'minutos','goals'=>'gols','assists'=>'assistencias','passes'=>'passes','completed'=>'passes_certos','shots'=>'finalizacoes','tackles'=>'desarmes','fouls'=>'faltas','cards'=>'cartoes','rating'=>'nota']; }
    public function create(int $account,array $g): void {
        $this->db->beginTransaction(); try {
            $this->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
            if($this->load($account)) throw new InvalidArgumentException('Você já tem uma carreira.');
            $this->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?)',[$account,$g['name'],$g['city'],$g['uf'],$g['position'],$g['age'],$g['stage'],$g['club']]);
            $g['id']=(int)$this->db->lastInsertId(); $this->save($g); $this->db->commit();
        } catch(Throwable $e) { if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function command(int $account,string $action,array $input,string $key,int $version): array {
        if(!preg_match('/^[a-f0-9]{64}$/',$key)) throw new InvalidArgumentException('Token inválido.');
        $hash=hash('sha256',json_encode([$action,$input],JSON_THROW_ON_ERROR));
        $this->db->beginTransaction(); try {
            $g=$this->load($account,true); if(!$g) throw new InvalidArgumentException('Crie seu personagem.');
            $old=$this->query('SELECT request_hash FROM rumo10_decisoes WHERE carreira_id=? AND chave=?',[$g['id'],$key])->fetchColumn();
            if($old) { if(!hash_equals($old,$hash)) throw new InvalidArgumentException('Token já usado para outra ação.'); $this->db->commit(); return $g; }
            if($version!==$g['version']) throw new InvalidArgumentException('Esta tela ficou desatualizada. Recarregue antes de escolher.');
            $before=['skills'=>$g['skills'],'states'=>$g['states'],'season'=>$g['season'],'round'=>$g['round'],'stage'=>$g['stage']];
            $next=Game::command($g,$action,$input); $this->save($next); $f=$next['feedback']??[];
            $audit=['before'=>$before,'after'=>['skills'=>$next['skills'],'states'=>$next['states']],'feedback'=>$f];
            $this->query('INSERT INTO rumo10_decisoes(carreira_id,chave,comando,request_hash,versao_anterior,chance,sorteio,classe,regras,resultado_json) VALUES(?,?,?,?,?,?,?,?,?,?)',[$g['id'],$key,$action,$hash,$version,$f['chance']??null,$f['roll']??null,$f['class']??null,Rules::VERSION,json_encode($audit,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $persisted=$this->load($account); $this->db->commit(); return $persisted;
        } catch(Throwable $e) { if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function save(array $g): void {
        $id=$g['id'];
        $this->query('UPDATE rumo10_carreiras SET idade=?,temporada=?,rodada=?,etapa=?,clube=?,tier=?,handicap=?,versao=?,feedback_json=? WHERE id=?',[$g['age'],$g['season'],$g['round'],$g['stage'],$g['club'],$g['tier'],$g['handicap'],$g['version'],json_encode($g['feedback'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$id]);
        foreach($g['skills'] as $k=>$a) $this->query('INSERT INTO rumo10_atributos VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE valor=?,xp=?',[$id,$k,$a['value'],$a['xp'],$a['value'],$a['xp']]);
        foreach($g['states'] as $k=>$v) $this->query('INSERT INTO rumo10_estados VALUES(?,?,?) ON DUPLICATE KEY UPDATE valor=?',[$id,$k,$v,$v]);
        $this->query('INSERT IGNORE INTO rumo10_temporadas(carreira_id,numero,clube) VALUES(?,?,?)',[$id,$g['season'],$g['club']]);
        $season=$this->query('SELECT id FROM rumo10_temporadas WHERE carreira_id=? AND numero=?',[$id,$g['season']])->fetchColumn();
        foreach($g['teams'] as $t) $this->query('INSERT IGNORE INTO rumo10_times VALUES(?,?)',[$season,$t]);
        foreach($g['matches'] as $m) {
            $args=[$season,$m['round'],$m['home'],$m['away'],$m['hg'],$m['ag'],$m['minute'],$m['status'],$m['lineup'],$m['entry'],$m['exit']];
            $this->query('INSERT INTO rumo10_partidas(temporada_id,rodada,mandante,visitante,gols_mandante,gols_visitante,minuto,status,escalacao,entrada,saida) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE gols_mandante=?,gols_visitante=?,minuto=?,status=?,escalacao=?,entrada=?,saida=?',array_merge($args,array_slice($args,4)));
            $mid=$this->query('SELECT id FROM rumo10_partidas WHERE temporada_id=? AND rodada=? AND mandante=?',[$season,$m['round'],$m['home']])->fetchColumn();
            $stats=array_values($m['stats']); // Ordem explícita, independente da ordem dos dados carregados.
            $stats=[]; foreach(self::statMap() as $key=>$col) $stats[]=$m['stats'][$key];
            $this->query('INSERT INTO rumo10_estatisticas VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE minutos=?,gols=?,assistencias=?,passes=?,passes_certos=?,finalizacoes=?,desarmes=?,faltas=?,cartoes=?,nota=?',array_merge([$mid],$stats,$stats));
            foreach($m['log'] as $seq=>$l) $this->query('INSERT IGNORE INTO rumo10_eventos_partida VALUES(?,?,?,?)',[$mid,$seq,$l['minute'],$l['text']]);
        }
        foreach($g['offers'] as $o) $this->query('INSERT INTO rumo10_ofertas VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE peso=?,selecionada=?',[$season,$o['club'],$o['tier'],$o['peso'],(int)$o['selected'],$o['peso'],(int)$o['selected']]);
        foreach($g['history'] as $h) {
            $this->query('UPDATE rumo10_temporadas SET finalizada=1,resumo_json=? WHERE carreira_id=? AND numero=?',[json_encode($h,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$id,$h['season']]);
            $this->query('INSERT IGNORE INTO rumo10_transferencias(carreira_id,temporada,origem,destino) VALUES(?,?,?,?)',[$id,$h['season'],$h['club'],$h['next_club']]);
        }
    }
}
