<?php
declare(strict_types=1);
final class Repository {
    /**
     * Gancho exclusivo para testes automatizados (injeção de falha transacional controlada).
     * NUNCA preenchido em tempo de execução normal/produção.
     * @var (callable(array $next, string $action): void)|null
     */
    public static $testPostProcessHook = null;

    /**
     * Gancho exclusivo para teste de atomicidade SQL pós-escritas (OS-F01-P1-007 §3).
     * Disparado APÓS save(), outbox e decisoes serem gravados no banco, mas imediatamente ANTES do INSERT em f01_respostas.
     * @var (callable(int $careerId, string $key, PDO $db): void)|null
     */
    public static $testPreResponseHook = null;

    public function __construct(public PDO $db) {}
    public function query(string $sql,array $args=[]): PDOStatement { $start=RequestMetrics::start();try{$s=$this->db->prepare($sql); $s->execute($args); return $s;}finally{RequestMetrics::end('mysql',$start);} }
    public function load(int $account,bool $lock=false): ?array {
        $c=$this->query('SELECT * FROM rumo10_carreiras WHERE conta_id=?'.($lock?' FOR UPDATE':''),[$account])->fetch(); if(!$c) return null;
        $summary=$this->query('SELECT dados_json FROM rumo10_historico_encerrado WHERE carreira_id=? AND conta_id=?',[$c['id'],$account])->fetchColumn();
        if($summary)return RetiredCareer::state(json_decode($summary,true,512,JSON_THROW_ON_ERROR));
        $snapshot=$this->query('SELECT estado_json FROM rumo10_universos WHERE carreira_id=?',[$c['id']])->fetchColumn();
        if($snapshot) return json_decode($snapshot,true,512,JSON_THROW_ON_ERROR);
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
    public function create(int $account,array $g,bool $replace=false,?string $creationKey=null): void {
        $central=CareerBenefits::enabled();$allocation=[];$committed=false;
        $creationKey??=bin2hex(random_bytes(32));
        if(!preg_match('/^[a-f0-9]{64}$/',$creationKey))throw new InvalidArgumentException('Recarregue o formulário de criação.');
        $requestHash=hash('sha256',json_encode(array_intersect_key($g,array_flip(['name','city','uf','position','play_mode','starting_country','residence_country','residence_region'])),JSON_THROW_ON_ERROR));
        $this->db->beginTransaction();try {
            $this->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
            if($central){$prior=$this->query('SELECT request_hash FROM rumo10_criacoes WHERE conta_id=? AND chave=?',[$account,$creationKey])->fetchColumn();if($prior){if(!hash_equals($prior,$requestHash))throw new InvalidArgumentException('Este formulário já criou uma carreira com outros dados.');$this->db->commit();return;}}
            $quota=CareerSlots::quota($this,$account);
            if(!empty($g['starting'])){EntryCountries::validate($g['starting_country']??'BR',$quota['plan']);if(!EntryCountries::entryStatus($g['starting_country']??'BR',$g['entry_uf']??$g['uf'])['valid'])throw new InvalidArgumentException('Destino de estreia temporariamente indisponível por falta de continuidade.');}
            if($central)$allocation=CareerBenefits::allocate($this,$account,$quota,hash('sha256','create|'.$account.'|'.$creationKey));
            elseif($quota['used']>=$quota['limit'])throw new InvalidArgumentException('Limite de '.$quota['limit'].' carreiras atingido.');
            $old=$this->load($account,true);if($old && !$replace)throw new InvalidArgumentException('Você já tem uma carreira.');
            if($old)$this->archive($account,$old['id']);
            $this->query('INSERT INTO rumo10_carreiras(conta_id,nome,cidade,uf,posicao,idade,etapa,clube) VALUES(?,?,?,?,?,?,?,?)',[$account,$g['name'],$g['city'],$g['uf'],$g['position'],$g['age'],$g['stage'],$g['club']]);
            $g['id']=(int)$this->db->lastInsertId();$this->save($g);
            if($central){$this->query('INSERT INTO rumo10_criacoes(conta_id,chave,carreira_id,request_hash) VALUES(?,?,?,?)',[$account,$creationKey,$g['id'],$requestHash]);CareerBenefits::queue($this,$account,$g['id'],$allocation);}
            $this->db->commit();$committed=true;
            if($central)CareerBenefits::flush($this,$account);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($central && !$committed)CareerBenefits::release($allocation);throw $e;}
    }
    private function archive(int $account,int $id): void {
        $this->query('INSERT INTO rumo10_carreiras_arquivadas(carreira_id,conta_id) VALUES(?,?) ON DUPLICATE KEY UPDATE arquivada_em=CURRENT_TIMESTAMP',[$id,$account]);
        $this->query('UPDATE rumo10_carreiras SET conta_id=NULL WHERE id=? AND conta_id=?',[$id,$account]);
    }
    public function archives(int $account): array {
        return $this->query('SELECT c.id,c.nome,c.clube,c.temporada,a.arquivada_em FROM rumo10_carreiras_arquivadas a JOIN rumo10_carreiras c ON c.id=a.carreira_id WHERE a.conta_id=? AND c.conta_id IS NULL AND NOT EXISTS(SELECT 1 FROM rumo10_historico_encerrado h WHERE h.carreira_id=c.id) ORDER BY a.arquivada_em DESC',[$account])->fetchAll();
    }
    public function restore(int $account,int $id): void {
        $this->db->beginTransaction();try {
            $this->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
            $target=$this->query('SELECT c.id FROM rumo10_carreiras_arquivadas a JOIN rumo10_carreiras c ON c.id=a.carreira_id WHERE a.conta_id=? AND a.carreira_id=? AND c.conta_id IS NULL FOR UPDATE',[$account,$id])->fetchColumn();
            if(!$target)throw new InvalidArgumentException('Carreira arquivada não encontrada.');
            if($this->query('SELECT carreira_id FROM rumo10_historico_encerrado WHERE carreira_id=?',[$id])->fetchColumn())throw new InvalidArgumentException('Esta carreira tem apenas um resumo e não pode ser retomada.');
            $current=$this->load($account,true);if($current)$this->archive($account,$current['id']);
            $this->query('UPDATE rumo10_carreiras SET conta_id=? WHERE id=?',[$account,$id]);$this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function command(int $account,string $action,array $input,string $key,int $version,?string $receivedAtUtc=null): array {
        if(!preg_match('/^[a-f0-9]{64}$/',$key)) throw new InvalidArgumentException('Token inválido.');
        $receivedAt = $receivedAtUtc ?? CanonicalIdentity::utcMicroTimestamp();
        $hash=hash('sha256',json_encode([$action,$input],JSON_THROW_ON_ERROR));
        $this->db->beginTransaction(); try {
            $this->query("SET time_zone = '+00:00'");
            $this->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
            $start=RequestMetrics::start();$g=$this->load($account,true);RequestMetrics::end('load',$start); if(!$g) throw new InvalidArgumentException('Crie seu personagem.');
            $closing=$g['retired_summary']['closing_command']??null;
            if($closing && hash_equals($closing['key'],$key)) {if(!hash_equals($closing['hash'],$hash))throw new InvalidArgumentException('Token já usado para outra ação.');$this->db->commit();return $g;}

            // F01: Verificação em rumo10_f01_respostas para idempotência com envelope canônico
            $cachedResp=$this->query('SELECT request_hash,resposta_json FROM rumo10_f01_respostas WHERE carreira_id=? AND chave=?',[$g['id'],$key])->fetch();
            if($cachedResp) {
                if(!hash_equals($cachedResp['request_hash'],$hash)) throw new InvalidArgumentException('Token já usado para outra ação.');
                $this->db->commit();
                $respEnvelope=json_decode($cachedResp['resposta_json'],true,512,JSON_THROW_ON_ERROR);
                return $respEnvelope['game_state'] ?? $g;
            }

            $old=$this->query('SELECT request_hash FROM rumo10_decisoes WHERE carreira_id=? AND chave=?',[$g['id'],$key])->fetchColumn();
            if($old) { if(!hash_equals($old,$hash)) throw new InvalidArgumentException('Token já usado para outra ação.'); $this->db->commit(); return $g; }
            if($version!==$g['version']) throw new InvalidArgumentException('Esta tela ficou desatualizada. Recarregue antes de escolher.');
            $before=['skills'=>$g['skills'],'states'=>$g['states'],'season'=>$g['season'],'round'=>$g['round'],'stage'=>$g['stage']];
            $careerId=$g['id'];$start=RequestMetrics::start();$next=Game::command($g,$action,$input);RequestMetrics::end('engine',$start);unset($g);

            // Injeção de falha controlada exclusiva para testes de atomicidade (OS-F01-P1-006 §3)
            if (self::$testPostProcessHook !== null) {
                (self::$testPostProcessHook)($next, $action);
            }

            Achievements::persist($this,$account,$next);
            if($next['stage']==='aposentado' && $before['stage']!=='aposentado') {
                RetiredCareer::persist($this,$account,$next,['key'=>$key,'hash'=>$hash]);unset($next);
                $persisted=$this->load($account);$this->db->commit();return $persisted;
            }
            // F01: Geração de Command UID e outbox/envelope sob a mesma transação
            // A extração ocorre ANTES de save() para que os identificadores canônicos gerados (match_uid, app_uid, ev_uid, batch_uid)
            // sejam incorporados e persistidos no próprio estado do jogo ($next) e no outbox simultaneamente
            $commandUid=CanonicalIdentity::uuidV4();
            $telemetryEnvelope=F01Telemetry::extractEnvelope($before,$next,$action,$input,$commandUid,$key,$receivedAt);
            $engineCommit=CanonicalIdentity::currentEngineCommit();
            if($telemetryEnvelope !== null) {
                $totalEvs = (int)($telemetryEnvelope['total_eventos'] ?? 1);
                $this->query(
                    'INSERT INTO rumo10_f01_outbox (carreira_id, command_uid, chave, engine_commit, total_eventos, payload_json, estado, received_at_utc, persisted_at_utc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))',
                    [$careerId, $commandUid, $key, $engineCommit, $totalEvs, json_encode($telemetryEnvelope, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE), 'pendente', $receivedAt]
                );
            }

            if($action==='relocate')$this->query('UPDATE rumo10_carreiras SET cidade=?,uf=? WHERE id=?',[$next['city'],$next['uf'],$careerId]);
            $start=RequestMetrics::start();$this->save($next);RequestMetrics::end('save',$start); $f=$next['feedback']??[];
            $audit=['before'=>$before,'after'=>['skills'=>$next['skills'],'states'=>$next['states']],'feedback'=>$f];
            if($action==='simulate' && isset($next['pace_report']))$audit['simulation']=$next['pace_report'];
            if($action==='mode')$audit['mode']=PlayMode::name($next);
            $this->query('INSERT INTO rumo10_decisoes(carreira_id,chave,comando,request_hash,versao_anterior,chance,sorteio,classe,regras,resultado_json) VALUES(?,?,?,?,?,?,?,?,?,?)',[$careerId,$key,$action,$hash,$version,$f['chance']??null,$f['roll']??null,$f['class']??null,Rules::VERSION,json_encode($audit,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);

            unset($next,$audit,$f); // Libere o universo antes de reler o JSON normalizado pelo MySQL.
            $persisted=$this->load($account);

            // F01: Persistência da resposta versionada
            if (self::$testPreResponseHook !== null) {
                (self::$testPreResponseHook)($careerId, $key, $this->db);
            }

            $envelopeResposta=[
                'contract_version'=>1,
                'command_uid'=>$commandUid,
                'carreira_id'=>$careerId,
                'versao_anterior'=>$version,
                'versao_resultante'=>$persisted['version'],
                'game_state'=>$persisted,
            ];
            $this->query(
                'INSERT INTO rumo10_f01_respostas (carreira_id, chave, command_uid, request_hash, versao_anterior, versao_resultante, contract_version, resposta_json, received_at_utc, persisted_at_utc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))',
                [$careerId, $key, $commandUid, $hash, $version, $persisted['version'], 1, json_encode($envelopeResposta, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE), $receivedAt]
            );

            $this->db->commit();return $persisted;
        } catch(Throwable $e) { if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function save(array $g): void {
        $id=$g['id'];
        $this->query('UPDATE rumo10_carreiras SET idade=?,temporada=?,rodada=?,etapa=?,clube=?,tier=?,handicap=?,versao=?,feedback_json=? WHERE id=?',[$g['age'],$g['season'],$g['round'],$g['stage'],$g['club'],$g['tier'],$g['handicap'],$g['version'],json_encode($g['feedback'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$id]);
        foreach($g['skills'] as $k=>$a) $this->query('INSERT INTO rumo10_atributos VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE valor=?,xp=?',[$id,$k,$a['value'],$a['xp'],$a['value'],$a['xp']]);
        foreach($g['states'] as $k=>$v) $this->query('INSERT INTO rumo10_estados VALUES(?,?,?) ON DUPLICATE KEY UPDATE valor=?',[$id,$k,$v,$v]);
        if(!empty($g['official']) || $g['stage']==='aposentado' || isset($g['story_seed']) || isset($g['play_mode']) || isset($g['initial_profile']) || isset($g['market_draw']) || isset($g['progression_version'])) {
            $json=json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
            // O universo pode ter dezenas de MB: vincular o mesmo JSON duas vezes duplica buffers do PDO.
            $this->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?) ON DUPLICATE KEY UPDATE estado_json=VALUES(estado_json)',[$id,$json]);
            return;
        }
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
        if(isset($g['story_seed']) || isset($g['play_mode']) || isset($g['initial_profile']) || isset($g['market_draw']) || isset($g['progression_version'])) {
            $json=json_encode($g,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
            $this->query('INSERT INTO rumo10_universos(carreira_id,estado_json) VALUES(?,?) ON DUPLICATE KEY UPDATE estado_json=VALUES(estado_json)',[$id,$json]);
        }
    }
}
