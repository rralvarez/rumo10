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
            $o['failure_effects']=match($code){
                'recuperar','reclamar'=>[],
                'lazer'=>['moral_pessoal'=>-1],
                'apoio'=>isset($o['effects']['entrosamento'])?['entrosamento'=>-2]:['moral_pessoal'=>-2],
                'liderar'=>isset($o['effects']['entrosamento'])?['entrosamento'=>-2]:['lideranca'=>-1],
                'imprensa'=>['confianca_imprensa'=>-2,'risco_polemica'=>2],
                'disciplina','tatico','visao'=>['foco'=>-2],
                default=>['confianca'=>-2],
            };
            $o['severe_failure_effects']=array_map(fn($v)=>$v<0?$v*2:$v,$o['failure_effects']);
        }
        static $editorial;$editorial??=require dirname(__DIR__).'/database/weekend-outcomes.php';
        if(isset($editorial[$id][$index])){[$o['success_text'],$o['failure_text']]=$editorial[$id][$index];}
        // O texto também deve refletir o efeito escolhido para uma tentativa frustrada.
        if($code==='disciplina' && $o['failure_text']==='Você se distrai e termina sem organizar o que precisava fazer. Isso abala sua confiança.')$o['failure_text']='Você se distrai e termina sem organizar o que precisava fazer. Precisa recuperar o foco.';
        unset($o['custom_effects']);return $o;
    }
}
