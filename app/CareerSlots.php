<?php
declare(strict_types=1);
/** Vagas incluem a carreira selecionada e todas as arquivadas retomáveis. */
final class CareerSlots {
    public static function listing(Repository $r,int $account):array {
        return $r->query('SELECT c.id,c.nome,c.clube,c.temporada,c.etapa,(c.conta_id=?) AS selecionada FROM rumo10_carreiras c LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id WHERE (c.conta_id=? OR (c.conta_id IS NULL AND a.conta_id=?)) AND NOT EXISTS(SELECT 1 FROM rumo10_historico_encerrado h WHERE h.carreira_id=c.id) ORDER BY selecionada DESC,c.id DESC',[$account,$account,$account])->fetchAll();
    }
    public static function quota(Repository $r,int $account):array {
        if(CareerBenefits::enabled())return CareerBenefits::quota($r,$account);
        $plan=$r->query('SELECT plano FROM rumo10_conta_planos WHERE conta_id=?',[$account])->fetchColumn()?:'gratuito';
        return ['plan'=>$plan,'limit'=>$plan==='pago'?10:3,'used'=>count(self::listing($r,$account))];
    }
    public static function owned(Repository $r,int $account,int $id,bool $lock=false):array {
        $c=$r->query('SELECT c.id,c.nome,c.clube,c.temporada FROM rumo10_carreiras c WHERE c.id=? AND (c.conta_id=? OR (c.conta_id IS NULL AND EXISTS(SELECT 1 FROM rumo10_carreiras_arquivadas a WHERE a.carreira_id=c.id AND a.conta_id=?)))'.($lock?' FOR UPDATE':''),[$id,$account,$account])->fetch();
        if(!$c)throw new InvalidArgumentException('Carreira não encontrada na sua conta.');return $c;
    }
    public static function delete(Repository $r,int $account,int $id,string $confirm):void {
        if($confirm!=='delete')throw new InvalidArgumentException('Confirme a exclusão permanente da carreira.');
        $r->db->beginTransaction();try {
            $r->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);self::owned($r,$account,$id,true);
            self::purgeDetails($r,$id);
            $r->query('DELETE FROM rumo10_carreiras WHERE id=?',[$id]);
            $r->db->commit();
        }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
    }
    /** Chamar somente em transação, depois de verificar proprietário e bloquear carreira. */
    public static function purgeDetails(Repository $r,int $id):void {
            // Lista fixa de dependências do jogo, inclusive armazenamento legado. Catálogo compartilhado preservado.
            foreach(['rumo10_eventos_partida','rumo10_estatisticas'] as $table)$r->query("DELETE x FROM $table x JOIN rumo10_partidas p ON p.id=x.partida_id JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?",[$id]);
            $r->query('DELETE p FROM rumo10_partidas p JOIN rumo10_temporadas t ON t.id=p.temporada_id WHERE t.carreira_id=?',[$id]);
            foreach(['rumo10_times','rumo10_ofertas'] as $table)$r->query("DELETE x FROM $table x JOIN rumo10_temporadas t ON t.id=x.temporada_id WHERE t.carreira_id=?",[$id]);
            foreach(['rumo10_temporadas','rumo10_decisoes','rumo10_atributos','rumo10_estados','rumo10_transferencias'] as $table)$r->query("DELETE FROM $table WHERE carreira_id=?",[$id]);
            $r->query('DELETE FROM rumo10_universos WHERE carreira_id=?',[$id]);
    }
    public static function render(Repository $r,int $account):void {
        $q=self::quota($r,$account);echo '<section class="panel"><h1>Minhas carreiras</h1><p>'.e($q['used']).' de '.e($q['limit']).' vagas · plano '.e($q['plan']).'</p><p class="subtle">Carreiras pausadas e arquivadas também ocupam uma vaga.</p>';
        if(CareerBenefits::enabled())echo '<p>Vagas-base: 2 · extras por indicação em uso: '.e($q['extras']).' / 3 · créditos disponíveis: '.e($q['credits']).' · utilizados ou em confirmação: '.e($q['credits_used']).'</p><a class="btn btn-primary" href="/convites">Convidar amigos</a><p class="subtle">Carreiras anteriores foram preservadas. Créditos consumidos não voltam após exclusão ou aposentadoria.</p>';
        foreach(self::listing($r,$account) as $c){echo '<article class="panel"><h2>'.e($c['nome']).'</h2><p>'.e($c['clube']).' · temporada '.e($c['temporada']).'</p>';if($c['selecionada'])echo '<a class="btn btn-primary" href="/carreira">Continuar</a>';else{form('/carreira/restaurar');echo '<input type="hidden" name="career" value="'.e($c['id']).'"><button class="btn btn-primary">Selecionar carreira</button></form>';}
            if(CareerBenefits::enabled()){$origin=$r->query('SELECT tipo,credito_id FROM rumo10_origem_vagas WHERE carreira_id=?',[$c['id']])->fetch();$safeReference='Indicação confirmada';foreach($q['referrals']['items'] as $item)if(($item['credit_id']??null)==($origin['credito_id']??-1))$safeReference=str_starts_with($item['reference'],'convite #')?'Indicação confirmada • '.$item['reference']:'Vaga conquistada por indicação de '.$item['reference'];$label=match($origin['tipo']??'LEGADO'){'BASE'=>'Vaga-base','PAGO'=>'Criada com acesso pago ou cortesia','INDICACAO'=>$safeReference,default=>'Vaga legada preservada'};echo '<p>'.e($label).'</p>';}
            if($c['etapa']==='aposentado')echo '<p><a href="/carreira/compactar?career='.e($c['id']).'">Guardar resumo e liberar vaga</a></p>';
            echo '<p><a href="/carreira/excluir?career='.e($c['id']).'">Excluir esta carreira</a></p></article>';
        }
        if($q['used']<$q['limit'] && (!CareerBenefits::enabled() || $q['plan']==='pago' || $q['used']<5))echo '<a class="btn btn-primary" href="/carreira/nova">Criar nova carreira</a>';else echo '<p>Para criar outra carreira, é preciso espaço disponível e, nas vagas extras gratuitas, um novo crédito de indicação.</p>';echo '</section>';RetiredCareer::listing($r,$account);
    }
    public static function confirmation(array $c):void {
        echo '<section class="panel"><h1>Excluir carreira?</h1><p><strong>'.e($c['nome']).'</strong> · '.e($c['clube']).'</p><p>Todos os dados desta carreira serão apagados permanentemente, incluindo partidas, escolhas, estatísticas e histórico. Essa ação não pode ser desfeita.</p>';form('/carreira/excluir');echo '<input type="hidden" name="career" value="'.e($c['id']).'"><label><input type="checkbox" name="confirm" value="delete" required> Confirmo a exclusão permanente desta carreira</label><button class="btn btn-danger">Excluir permanentemente</button></form><a href="/carreiras">Cancelar</a></section>';
    }
}
