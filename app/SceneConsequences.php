<?php
declare(strict_types=1);
final class SceneConsequences {
    public static function option(array $o,string $stage,int $id,int $index):array {
        $code=$o['code']??null;
        static $legacySuccess;$legacySuccess??=require dirname(__DIR__).'/database/legacy-successes.php';
        if(isset($legacySuccess[$id][$index]) && !isset($o['success_text']))$o['success_text']=$legacySuccess[$id][$index];
        if($code==='liderar')unset($o['effects']['energia']);
        if($code==='tecnica' && !isset($o['custom_effects'])){$o['effects']=['drible'=>2,'foco'=>3,'energia'=>-3];}
        if($code==='disciplina' && $stage==='fim_semana' && !isset($o['custom_effects']))$o['effects']=['foco'=>3];
        if($code==='ousar' && $stage==='pre_jogo')$o['effects']=['confianca'=>4,'entrosamento'=>2];
        if($code==='ousar' && $stage==='fim_semana')$o['effects']=['confianca'=>3,'risco_polemica'=>3];
        if($code==='reclamar')$o['effects']=['confianca_treinador'=>-3,'risco_polemica'=>3];
        if($code==='social' && $stage==='partida'){$o['effects']=['posicionamento'=>1];$o['success_text']='Você ajusta sua posição e mantém espaço para a próxima jogada.';}
        // Aquecimento não equivale a uma sessão completa de treino técnico/físico.
        if($stage==='pre_jogo')foreach($o['effects'] as $key=>&$value){
            if(in_array($key,Rules::SKILLS,true) && $value>0)$value=min(1,$value);
            if($key==='energia' && $value<0)$value=(int)ceil($value/2);
        }unset($value);
        static $overrides;$overrides??=require dirname(__DIR__).'/database/scene-consequences.php';
        $o=array_replace($o,$overrides[$id][$index]??[]);
        static $contextEffects;$contextEffects??=require dirname(__DIR__).'/database/weekend-consequences.php';
        $o=array_replace($o,$contextEffects[$id][$index]??[]);
        static $contextOutcomes;$contextOutcomes??=require dirname(__DIR__).'/database/context-outcomes.php';
        $o=array_replace($o,$contextOutcomes[$id][$index]??[]);
        if($code==='reclamar' && !isset($overrides[$id][$index]['success_text']))$o['success_text']='Você é ouvido, mas o tom da cobrança deixa a conversa mais tensa.';
        if(!array_key_exists('failure_effects',$o)){
            [$o['failure_effects'],$o['severe_failure_effects']]=self::contextualFailure($o,$stage,$id);
        }
        static $editorial;$editorial??=require dirname(__DIR__).'/database/weekend-outcomes.php';
        if(isset($editorial[$id][$index])){[$o['success_text'],$o['failure_text']]=$editorial[$id][$index];}
        // O texto também deve refletir o efeito escolhido para uma tentativa frustrada.
        if($code==='disciplina' && $o['failure_text']==='Você se distrai e termina sem organizar o que precisava fazer. Isso abala sua confiança.')$o['failure_text']='Você se distrai e termina sem organizar o que precisava fazer. Precisa recuperar o foco.';
        unset($o['custom_effects']);return $o;
    }

    /**
     * Classifica penalidades de falha conforme natureza esportiva e psicológica da ação (R10-BAL-001-R2):
     * - erro técnico normal vs grave em campo;
     * - falha em oportunidade decisiva;
     * - decisão tática/espacial frustrada (foco / confiança do treinador);
     * - falha física ou de repetição de treino sem relação direta com autoconfiança;
     * - exposição egoica ('ousar') ou comportamento inadequado.
     */
    public static function contextualFailure(array $o,string $stage,int $id=0):array {
        $code=$o['code']??null;
        $act=$o['action']??'';
        $isDecisive=!empty($o['clear_chance']) || !empty($o['decisive_opportunity']) || $id===1300 || $id===1401 || ($stage==='partida' && $id===7 && $act==='chute');
        if($stage==='partida' && $isDecisive){
            return [['confianca'=>-2],['confianca'=>-4]];
        }
        return match($code){
            'recuperar','reclamar'=>[[],[]],
            'lazer'=>[['moral_pessoal'=>-1],['moral_pessoal'=>-2]],
            'apoio'=>isset($o['effects']['entrosamento'])?[['entrosamento'=>-2],['entrosamento'=>-4]]:[['moral_pessoal'=>-2],['moral_pessoal'=>-4]],
            'liderar'=>isset($o['effects']['entrosamento'])?[['entrosamento'=>-2],['entrosamento'=>-4]]:[['lideranca'=>-1],['lideranca'=>-2]],
            'imprensa'=>[['confianca_imprensa'=>-2,'risco_polemica'=>2],['confianca_imprensa'=>-4,'risco_polemica'=>2]],
            'disciplina','tatico','visao'=>[['foco'=>-2],['foco'=>-4]],
            'posicionamento'=>[['foco'=>-1],['foco'=>-2,'confianca_treinador'=>-1]],
            'forca','resistencia','velocidade'=>[['ritmo_jogo'=>-1],['ritmo_jogo'=>-2]],
            'tecnica'=>[['foco'=>-1],['foco'=>-2,'confianca'=>-1]],
            'passe','chute','drible','desarme'=>$stage==='partida'
                ?[['confianca'=>-1],['confianca'=>-3]]
                :[['foco'=>-1],['foco'=>-2,'confianca'=>-1]],
            'social'=>$stage==='partida'
                ?[['foco'=>-1],['foco'=>-2,'confianca_treinador'=>-1]]
                :(isset($o['effects']['entrosamento'])?[['entrosamento'=>-1],['entrosamento'=>-2]]:[['moral_pessoal'=>-1],['moral_pessoal'=>-2]]),
            'ousar'=>[['confianca'=>-2],['confianca'=>-4]],
            default=>match(true){
                $stage==='partida' && in_array($act,['passe','chute','drible','desarme'],true)=>[['confianca'=>-1],['confianca'=>-3]],
                $stage==='treino'=>[['foco'=>-1],['foco'=>-2,'confianca'=>-1]],
                default=>[['confianca'=>-2],['confianca'=>-4]],
            },
        };
    }

    /**
     * Reconcilia alternativas vindas de catálogos JSON (2000+) ou situações especiais em tempo de execução,
     * substituindo apenas o fallback genérico herdado (-2/-4 em confiança) quando a semântica não é de autoconfiança.
     */
    public static function reconcileCatalogOption(array $o,string $stage,int $id=0):array {
        $code=$o['code']??null;
        $act=$o['action']??'';
        $isDecisive=!empty($o['clear_chance']) || !empty($o['decisive_opportunity']) || $id===1300 || $id===1401 || ($stage==='partida' && $id===7 && $act==='chute');
        if($stage==='partida' && $isDecisive){
            $o['decisive_opportunity']=true;
            $o['failure_effects']=['confianca'=>-2];
            $o['severe_failure_effects']=['confianca'=>-4];
            return $o;
        }
        if($stage==='partida' && in_array($act,['passe','chute','drible','desarme'],true)){
            if(!isset($o['failure_effects']) || ($o['failure_effects']['confianca']??null)===-2)$o['failure_effects']=['confianca'=>-1];
            if(!isset($o['severe_failure_effects']) || ($o['severe_failure_effects']['confianca']??null)===-4)$o['severe_failure_effects']=['confianca'=>-3];
            return $o;
        }
        if(($o['failure_effects']??null)===['confianca'=>-2]){
            if($code==='posicionamento' || ($code==='social' && $stage==='partida')){
                $o['failure_effects']=['foco'=>-1];
                $o['severe_failure_effects']=['foco'=>-2,'confianca_treinador'=>-1];
            } elseif(in_array($code,['forca','resistencia','velocidade'],true)){
                $o['failure_effects']=['ritmo_jogo'=>-1];
                $o['severe_failure_effects']=['ritmo_jogo'=>-2];
            } elseif($stage!=='partida' && in_array($code,['tecnica','passe','chute','drible','desarme'],true)){
                $o['failure_effects']=['foco'=>-1];
                $o['severe_failure_effects']=['foco'=>-2,'confianca'=>-1];
            }
        }
        return $o;
    }
}
