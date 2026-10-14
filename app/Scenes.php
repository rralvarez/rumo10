<?php
declare(strict_types=1);
final class Scenes {
    public static function catalog(): array {
        static $all;if($all!==null)return $all;$all=[];
        foreach(require dirname(__DIR__).'/database/situations.php' as $id=>$s)$all[$id]=['id'=>$id,'stage'=>$s[0],'text'=>$s[1],'legacy'=>$s];
        foreach(json_decode(file_get_contents(dirname(__DIR__).'/database/scenes.json'),true,512,JSON_THROW_ON_ERROR) as $s)$all[$s['id']]=$s;
        foreach([1200=>'Você começa no banco. O treinador diz que pode chamar você para mudar o ritmo do jogo.',1201=>'A comissão planeja colocar você no segundo tempo e pede que fique pronto para entrar.'] as $id=>$text)$all[$id]=['id'=>$id,'stage'=>'pre_jogo','text'=>$text,'bench'=>true,'options'=>[['code'=>'apoio','text'=>'Apoiar quem começa jogando'],['code'=>'tatico','text'=>'Observar o adversário enquanto espera'],['code'=>'disciplina','text'=>'Seguir o aquecimento combinado'],['code'=>'reclamar','text'=>'Reclamar por ter ficado no banco']]];
        return $all;
    }
    private static function bench(array $g): bool {return $g['states']['energia']<40 || $g['states']['confianca_treinador']<35 || $g['round']%4===0;}
    public static function key(array $g): string {
        $minute=0;if($g['stage']==='partida')$minute=$g['matches'][Game::currentIndex($g)]['minute'];
        return $g['season'].':'.$g['round'].':'.$g['stage'].':'.$minute;
    }
    public static function eligible(array $g): array {
        if(!in_array($g['stage'],['treino','pre_jogo','partida','fim_semana'],true))return [];
        if($g['stage']==='partida') {
            $m=$g['matches'][Game::currentIndex($g)];
            if($m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit'])return [];
        }
        $pool=[];
        foreach(self::catalog() as $id=>$s) {
            if($s['stage']!==$g['stage'])continue;
            if($g['stage']==='pre_jogo' && (bool)($s['bench']??($id===6))!==self::bench($g))continue;
            if(in_array($id,[10,11],true))continue; // Não presumir patrocinador ou rival confirmado.
            if($g['stage']==='partida') {
                if($id===7 && ($m['minute']!==80 || $m['hg']!==$m['ag']))continue;
                if($id===8 && $m['minute']!==35)continue;
                if($id===9 && !array_filter($m['log'],fn($l)=>$l['minute']===$m['minute'] && str_contains($l['text'],'Gol adversário')))continue;
                if($id>=1100 && $id<=1124 && in_array($g['position'],['zagueiro','volante'],true))continue;
                if($id>=1125 && $id<=1139 && in_array($g['position'],['meia','ponta','centroavante'],true))continue;
                if($id===1143 && $g['states']['risco_lesao']<35)continue;
                if($id===1149 && $m['minute']<60)continue;
            }
            $pool[]=$id;
        }return $pool;
    }
    public static function prepare(array &$g): void {
        $g['story_seed']??=bin2hex(random_bytes(16));$key=self::key($g);
        if(($g['active_situation']['key']??'')===$key)return;
        $pool=self::eligible($g);if(!$pool){unset($g['active_situation']);return;}
        $seen=$g['seen_situations'][$g['stage']]??[];$fresh=array_values(array_diff($pool,array_slice($seen,-10)));if($fresh)$pool=$fresh;
        $hash=hash('sha256',$g['story_seed'].':'.$key);$id=$pool[hexdec(substr($hash,0,7))%count($pool)];
        $g['active_situation']=['id'=>$id,'key'=>$key];
    }
    public static function remember(array &$g,?array $s): void {
        if(!$s)return;$g['seen_situations'][$g['stage']][]=$s['id'];$g['seen_situations'][$g['stage']]=array_slice($g['seen_situations'][$g['stage']],-24);
    }
    public static function current(array $g): ?array {
        $active=$g['active_situation']??null;if(!$active || $active['key']!==self::key($g))return null;
        $s=self::catalog()[$active['id']];
        if(isset($s['legacy'])) {
            $legacy=$s['legacy'];$options=[];
            foreach($legacy[2] as $i=>$o)$options[]=['text'=>$o[0],'effects'=>$o[1],'action'=>match($s['id']){7=>['chute','passe','drible','social'][$i],9=>['drible','social','social','desarme'][$i],1=>$i===1?'chute':'social',2=>'passe',default=>'social'}];
        } else $options=array_map(fn($o)=>self::option($o,$s['stage']),$s['options']);
        return ['id'=>$s['id'],'text'=>$s['text'],'options'=>$options,'bench'=>(bool)($s['bench']??($s['id']===6))];
    }
    private static function option(array $o,string $stage): array {
        $presets=[
            'passe'=>['passe',['passe'=>3,'energia'=>-3]],'chute'=>['chute',['finalizacao'=>3,'energia'=>-5]],
            'drible'=>['drible',['drible'=>3,'energia'=>-5,'risco_lesao'=>2]],'desarme'=>['desarme',['desarme'=>3,'energia'=>-4]],
            'tecnica'=>['social',['passe'=>2,'foco'=>3,'energia'=>-3]],'tatico'=>['social',['visao_jogo'=>2,'posicionamento'=>2,'foco'=>3]],
            'posicionamento'=>['social',['posicionamento'=>3,'ritmo_jogo'=>2]],'visao'=>['social',['visao_jogo'=>3,'foco'=>3]],
            'forca'=>['social',['forca'=>3,'energia'=>-6]],'resistencia'=>['social',['resistencia'=>3,'energia'=>-6]],
            'velocidade'=>['social',['velocidade'=>3,'energia'=>-6,'risco_lesao'=>2]],
            'recuperar'=>['social',['energia'=>8,'risco_lesao'=>-4]],'apoio'=>['social',['entrosamento'=>6,'moral_pessoal'=>3]],
            'liderar'=>['social',['lideranca'=>5,'entrosamento'=>3,'energia'=>-2]],'disciplina'=>['social',['foco'=>5,'confianca_treinador'=>3]],
            'imprensa'=>['social',['confianca_imprensa'=>5,'apoio_torcida'=>3]],'lazer'=>['social',['moral_pessoal'=>5,'energia'=>5]],
            'ousar'=>['social',['confianca'=>6,'energia'=>-7,'risco_lesao'=>3]],'reclamar'=>['social',['confianca'=>3,'confianca_treinador'=>-6,'risco_polemica'=>5]],
            'social'=>['social',['foco'=>3,'entrosamento'=>3]],
        ];
        [$action,$effects]=$presets[$o['code']]??throw new LogicException('Alternativa sem mecânica.');
        if($stage==='partida' && in_array($action,['passe','chute','drible','desarme'],true))$effects=[];
        $success=match($o['code']){'recuperar'=>'Você cuida da recuperação sem forçar o corpo.','apoio'=>'Sua atitude fortalece a relação com os companheiros.','liderar'=>'Os companheiros seguem sua orientação.','disciplina'=>'Você mantém o foco e faz o que combinou.','reclamar'=>'Você é ouvido, mas a reclamação desgasta sua relação com o treinador.','ousar'=>'Tomar a iniciativa aumenta sua confiança.','imprensa'=>'Sua mensagem foi bem recebida.','lazer'=>'Você consegue relaxar um pouco.',default=>'Você consegue fazer o que planejou.'};
        return ['text'=>$o['text'],'action'=>$action,'effects'=>$effects,'success_text'=>$success,'failure_text'=>'A tentativa não sai como você esperava.'];
    }
}
