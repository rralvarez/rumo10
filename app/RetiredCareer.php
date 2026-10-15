<?php
declare(strict_types=1);
/** Resumo imutável. Não contém partidas, universo de NPCs nem log de decisões. */
final class RetiredCareer {
    public static function build(array $g,?array $closing=null):array {
        if($g['stage']!=='aposentado')throw new InvalidArgumentException('Só uma carreira aposentada pode ser compactada.');
        $stats=CareerStats::collect($g);foreach($stats['seasons'] as &$s)$s['current']=false;unset($s);
        $competitions=[];$titles=[];
        $history=$g['competition_history']??[];$history[]=['season'=>$g['season'],'results'=>$g['competition_results']??[]];
        $clubsBySeason=array_column($stats['seasons'],'club','season');
        foreach($history as $edition)foreach($edition['results'] as $id=>$result){$season=(int)$edition['season'];$club=$clubsBySeason[$season]??$g['club'];$key=$season.'|'.$id;
            $competitions[$key]=['season'=>$season,'competition'=>$id,'club'=>$club]+array_intersect_key($result,array_flip(['name','champion','position','promoted','relegated','titles']));
            if(($result['champion']??null)===$club && empty($result['titles']))$titles[$key.'|champion']=['season'=>$season,'competition'=>$id,'club'=>$club,'title'=>$result['name']??$id];
            foreach($result['titles']??[] as $title=>$winner)if($winner===$club)$titles[$key.'|'.$title]=['season'=>$season,'competition'=>$id,'club'=>$club,'title'=>$title];
        }
        $skills=array_column($g['skills']??[],'value');
        return ['schema'=>2,'trophies'=>Trophies::collect($g),'identity'=>array_intersect_key($g,array_flip(['id','name','city','uf','position','age','season','club','version','location_kind','residence_country','residence_region','legacy_location_record'])),'retirement'=>$g['retirement']??['reason'=>'legacy','age'=>$g['age'],'season'=>$g['season'],'club'=>$g['club'],'totals'=>$stats['totals']],'statistics'=>$stats,'competitions'=>array_values($competitions),'titles'=>array_values($titles),'transition_reference'=>['technical_average'=>$skills?round(array_sum($skills)/count($skills),1):0,'seasons_played'=>count($stats['seasons'])],'closing_command'=>$closing];
    }
    /** Dentro da transação e lock da conta/carreira; salvar resumo antes de apagar detalhes. */
    public static function persist(Repository $r,int $account,array $g,?array $closing=null):void {
        $id=(int)$g['id'];if($r->query('SELECT carreira_id FROM rumo10_historico_encerrado WHERE carreira_id=?',[$id])->fetchColumn())return;
        $summary=self::build($g,$closing);$json=json_encode($summary,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);
        $bytes=(int)$r->query('SELECT COALESCE(OCTET_LENGTH(estado_json),0) FROM rumo10_universos WHERE carreira_id=?',[$id])->fetchColumn();
        $r->query('INSERT INTO rumo10_historico_encerrado(carreira_id,conta_id,dados_json,bytes_detalhes,bytes_resumo) VALUES(?,?,?,?,?)',[$id,$account,$json,$bytes,strlen($json)]);
        $r->query('UPDATE rumo10_carreiras SET idade=?,temporada=?,etapa=?,clube=?,versao=?,feedback_json=NULL WHERE id=?',[$g['age'],$g['season'],'aposentado',$g['club'],$g['version'],$id]);
        CareerSlots::purgeDetails($r,$id);
    }
    public static function state(array $summary):array {
        return $summary['identity']+['stage'=>'aposentado','retirement'=>$summary['retirement'],'retired_summary'=>$summary,'matches'=>[],'teams'=>[],'history'=>[],'skills'=>[],'states'=>[],'offers'=>[],'feedback'=>null,'starting'=>false,'official'=>false];
    }
    public static function owned(Repository $r,int $account,int $id):array {
        $json=$r->query('SELECT dados_json FROM rumo10_historico_encerrado WHERE carreira_id=? AND conta_id=?',[$id,$account])->fetchColumn();if(!$json)throw new InvalidArgumentException('Resumo não encontrado na sua conta.');return json_decode($json,true,512,JSON_THROW_ON_ERROR);
    }
    public static function compactExisting(Repository $r,int $account,int $id,string $confirm):void {
        if($confirm!=='compact')throw new InvalidArgumentException('Confirme a remoção dos detalhes da carreira aposentada.');
        $r->db->beginTransaction();try{$r->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);CareerSlots::owned($r,$account,$id,true);
            if(!$r->query('SELECT carreira_id FROM rumo10_historico_encerrado WHERE carreira_id=?',[$id])->fetchColumn()){
                $json=$r->query('SELECT estado_json FROM rumo10_universos WHERE carreira_id=?',[$id])->fetchColumn();if(!$json)throw new InvalidArgumentException('Detalhes legados não localizados; carreira preservada.');$g=json_decode($json,true,512,JSON_THROW_ON_ERROR);if((int)($g['id']??0)!==$id)throw new InvalidArgumentException('Identidade do universo divergente; carreira preservada.');self::persist($r,$account,$g);
            }$r->db->commit();
        }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
    }
    public static function listing(Repository $r,int $account):void {
        $rows=$r->query('SELECT h.carreira_id,c.nome,c.clube,c.temporada FROM rumo10_historico_encerrado h JOIN rumo10_carreiras c ON c.id=h.carreira_id WHERE h.conta_id=? ORDER BY h.encerrada_em DESC,h.carreira_id DESC',[$account])->fetchAll();
        if(!$rows)return;echo '<section class="panel"><h2>Histórico de carreiras encerradas</h2><p>Resumos preservados, sem ocupar vagas e sem possibilidade de retomar.</p>';
        foreach($rows as $c)echo '<p><a href="/carreira/resumo?career='.e($c['carreira_id']).'">'.e($c['nome']).' · '.e($c['clube']).'</a></p>';echo '</section>';
    }
    public static function confirmation(Repository $r,int $account,int $id):void {
        $c=CareerSlots::owned($r,$account,$id);$stage=$r->query('SELECT etapa FROM rumo10_carreiras WHERE id=?',[$id])->fetchColumn();if($stage!=='aposentado')throw new InvalidArgumentException('A carreira ainda não está aposentada.');
        echo '<section class="panel"><h1>Guardar resumo e liberar vaga</h1><p>'.e($c['nome']).'</p><p>Preservaremos totais gerais, números por clube e temporada e resultados das competições. Partidas, eventos de gols, escolhas e o universo detalhado serão apagados permanentemente.</p>';form('/carreira/compactar');echo '<input type="hidden" name="career" value="'.e($id).'"><label><input type="checkbox" name="confirm" value="compact" required> Confirmo a remoção permanente dos detalhes.</label><button class="btn btn-primary">Guardar resumo e liberar vaga</button></form><a href="/carreiras">Cancelar</a></section>';
    }
    public static function render(array $data):void {
        $g=self::state($data);echo '<section class="panel"><p class="tag">CARREIRA ENCERRADA</p><h1>'.e($g['name']).'</h1><p>'.e(ucfirst($g['position'])).' · '.e($g['age']).' anos · '.e($g['club']).'</p><p>Resumo permanente. Partidas, gols individuais e decisões detalhadas foram removidos. Esta carreira não ocupa vaga e não pode ser retomada.</p>';CareerStats::render($g);
        if($data['titles']){echo '<h2>Conquistas da equipe</h2><ul>';foreach($data['titles'] as $t)echo '<li>Temporada '.e($t['season']).' · '.e($t['title']).' · '.e($t['club']).'</li>';echo '</ul>';}
        if($data['competitions']){echo '<details><summary>Resultados por competição</summary><ul>';foreach($data['competitions'] as $c)echo '<li>Temporada '.e($c['season']).' · '.e($c['name']??$c['competition']).' · posição '.e($c['position']??'—').' · campeão '.e($c['champion']??'—').'</li>';echo '</ul></details>';}
        echo '<p><a class="btn btn-primary" href="/carreira/trofeus?career='.e($g['id']).'">Sala de Troféus</a></p>';echo '<p><a class="btn btn-primary" href="/carreiras">Minhas carreiras</a> <a href="/carreira/nova">Criar nova carreira</a></p><p><a href="/carreira/excluir?career='.e($g['id']).'">Excluir também este resumo</a></p></section>';
    }
}
