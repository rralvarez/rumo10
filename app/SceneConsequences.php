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
     * Parâmetros configuráveis de penalidade de autoconfiança (BalanceService: confidence.balance.v2).
     *
     * @return array{tech_fail_normal:int,tech_fail_severe:int,decisive_fail_normal:int,decisive_fail_severe:int}
     */
    public static function failureParams():array {
        $p=class_exists('BalanceService')?BalanceService::params('confidence.balance.v2'):[];
        return [
            'tech_fail_normal'=>(int)($p['tech_fail_normal']??-1),
            'tech_fail_severe'=>(int)($p['tech_fail_severe']??-3),
            'decisive_fail_normal'=>(int)($p['decisive_fail_normal']??-2),
            'decisive_fail_severe'=>(int)($p['decisive_fail_severe']??-4),
        ];
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
        $fp=self::failureParams();
        $techNormal=$fp['tech_fail_normal'];
        $techSevere=$fp['tech_fail_severe'];
        $decNormal=$fp['decisive_fail_normal'];
        $decSevere=$fp['decisive_fail_severe'];
        $code=$o['code']??null;
        $act=$o['action']??'';
        $isDecisive=!empty($o['clear_chance']) || !empty($o['decisive_opportunity']) || $id===1300 || $id===1401 || ($stage==='partida' && $id===7 && $act==='chute');
        if($stage==='partida' && $isDecisive){
            return [['confianca'=>$decNormal],['confianca'=>$decSevere]];
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
            'tecnica'=>[['foco'=>-1],['foco'=>-2,'confianca'=>$techNormal]],
            'passe','chute','drible','desarme'=>$stage==='partida'
                ?[['confianca'=>$techNormal],['confianca'=>$techSevere]]
                :[['foco'=>-1],['foco'=>-2,'confianca'=>$techNormal]],
            'social'=>$stage==='partida'
                ?[['foco'=>-1],['foco'=>-2,'confianca_treinador'=>-1]]
                :(isset($o['effects']['entrosamento'])?[['entrosamento'=>-1],['entrosamento'=>-2]]:[['moral_pessoal'=>-1],['moral_pessoal'=>-2]]),
            'ousar'=>[['confianca'=>-2],['confianca'=>-4]],
            default=>match(true){
                $stage==='partida' && in_array($act,['passe','chute','drible','desarme'],true)=>[['confianca'=>$techNormal],['confianca'=>$techSevere]],
                $stage==='treino'=>[['foco'=>-1],['foco'=>-2,'confianca'=>$techNormal]],
                default=>[['confianca'=>-2],['confianca'=>-4]],
            },
        };
    }

    /**
     * Reconcilia alternativas vindas de catálogos JSON (2000+) ou situações especiais em tempo de execução,
     * ajustando apenas o componente de autoconfiança sem descartar efeitos legítimos sobre outros estados/atributos.
     */
    public static function reconcileCatalogOption(array $o,string $stage,int $id=0):array {
        $fp=self::failureParams();
        $techNormal=$fp['tech_fail_normal'];
        $techSevere=$fp['tech_fail_severe'];
        $decNormal=$fp['decisive_fail_normal'];
        $decSevere=$fp['decisive_fail_severe'];
        $code=$o['code']??null;
        $act=$o['action']??'';
        $isDecisive=!empty($o['clear_chance']) || !empty($o['decisive_opportunity']) || $id===1300 || $id===1401 || ($stage==='partida' && $id===7 && $act==='chute');
        if($stage==='partida' && $isDecisive){
            $o['decisive_opportunity']=true;
            $fe=is_array($o['failure_effects']??null)?$o['failure_effects']:[];
            $sfe=is_array($o['severe_failure_effects']??null)?$o['severe_failure_effects']:$fe;
            $fe['confianca']=$decNormal;
            $sfe['confianca']=$decSevere;
            $o['failure_effects']=$fe;
            $o['severe_failure_effects']=$sfe;
            return $o;
        }
        if($stage==='partida' && in_array($act,['passe','chute','drible','desarme'],true)){
            if(!array_key_exists('failure_effects',$o) || !is_array($o['failure_effects'])){
                $o['failure_effects']=['confianca'=>$techNormal];
            } elseif(($o['failure_effects']['confianca']??null)===-2 || ($o['failure_effects']['confianca']??null)===-1){
                $o['failure_effects']['confianca']=$techNormal;
            }
            if(!array_key_exists('severe_failure_effects',$o) || !is_array($o['severe_failure_effects'])){
                $sfe=$o['failure_effects'];
                if(($sfe['confianca']??null)===$techNormal){
                    $sfe['confianca']=$techSevere;
                }
                $o['severe_failure_effects']=$sfe;
            } elseif(($o['severe_failure_effects']['confianca']??null)===-4 || ($o['severe_failure_effects']['confianca']??null)===-3){
                $o['severe_failure_effects']['confianca']=$techSevere;
            }
            return $o;
        }
        $fe=$o['failure_effects']??null;
        if(is_array($fe) && ($fe['confianca']??null)===-2 && !isset($o['effects']['confianca'])){
            $sfe=is_array($o['severe_failure_effects']??null)?$o['severe_failure_effects']:$fe;
            if($code==='posicionamento' || ($code==='social' && $stage==='partida')){
                unset($fe['confianca']);
                $fe['foco']=min((int)($fe['foco']??0),-1);
                if(($sfe['confianca']??null)===-4 || ($sfe['confianca']??null)===-2){
                    unset($sfe['confianca']);
                }
                $sfe['foco']=min((int)($sfe['foco']??0),-2);
                $sfe['confianca_treinador']=min((int)($sfe['confianca_treinador']??0),-1);
                $o['failure_effects']=$fe;
                $o['severe_failure_effects']=$sfe;
            } elseif(in_array($code,['forca','resistencia','velocidade'],true)){
                unset($fe['confianca']);
                $fe['ritmo_jogo']=min((int)($fe['ritmo_jogo']??0),-1);
                if(($sfe['confianca']??null)===-4 || ($sfe['confianca']??null)===-2){
                    unset($sfe['confianca']);
                }
                $sfe['ritmo_jogo']=min((int)($sfe['ritmo_jogo']??0),-2);
                $o['failure_effects']=$fe;
                $o['severe_failure_effects']=$sfe;
            } elseif($stage!=='partida' && in_array($code,['tecnica','passe','chute','drible','desarme'],true)){
                unset($fe['confianca']);
                $fe['foco']=min((int)($fe['foco']??0),-1);
                if(($sfe['confianca']??null)===-4 || ($sfe['confianca']??null)===-2 || !array_key_exists('confianca',$sfe)){
                    $sfe['confianca']=$techNormal;
                }
                $sfe['foco']=min((int)($sfe['foco']??0),-2);
                $o['failure_effects']=$fe;
                $o['severe_failure_effects']=$sfe;
            }
        }
        return $o;
    }
}
