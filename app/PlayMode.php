<?php
declare(strict_types=1);
final class PlayMode {
    public const MODES=['normal'=>'Normal · decisões em cada situação','rapido'=>'Rápido · uma decisão por jogo','expresso'=>'Expresso · uma estratégia por temporada'];
    public const TITLES=['normal'=>'Normal','rapido'=>'Rápido','expresso'=>'Expresso'];
    public const PLANS=[
        'protagonismo'=>['Buscar protagonismo','Priorizar finalizações e dribles; nos defensores, antecipações e desarmes.'],
        'coletivo'=>['Jogar pelo coletivo','Priorizar passes e entrosamento com os companheiros.'],
        'evolucao'=>['Investir na evolução','Priorizar treino técnico e variar as ações em campo.'],
        'equilibrio'=>['Preservar o equilíbrio','Priorizar recuperação e ações com maior chance de execução.'],
    ];
    public static function name(array $g): string {return $g['play_mode']??'normal';}
    public static function canChange(array $g): bool {return false;}
    public static function validate(string $mode): string {
        if(!isset(self::MODES[$mode]))throw new InvalidArgumentException('Escolha um modo de jogo válido.');
        return $mode;
    }
    public static function change(array $g,string $mode): array {
        throw new InvalidArgumentException('O modo é escolhido ao criar o jogador e permanece fixo nesta carreira.');
    }
    // Percorre os mesmos comandos e a mesma curva de XP, sem multiplicador por modo.
    public static function simulate(array $g,string $plan): array {
        return WorldSeason::scope($g,fn()=>self::runSimulation($g,$plan));
    }
    private static function runSimulation(array $g,string $plan): array {
        $mode=self::name($g);
        if($mode==='normal' || $g['stage']!=='treino' || !isset(self::PLANS[$plan]))throw new InvalidArgumentException('Estratégia incompatível com a etapa atual.');
        $version=$g['version'];$season=$g['season'];$before=Game::summary($g);$skills=$g['skills'];$played=[];$decisions=0;$attempts=[];
        for($step=0;$step<3000;$step++) {
            if($g['season']!==$season)throw new LogicException('A simulação não pode renovar contratos.');
            if(in_array($g['stage'],['resumo','aposentado'],true))break;
            if($g['stage']==='world_processing'){if($mode==='expresso')$g['world_resume_plan']=$plan;break;}
            if($g['stage']==='competicao_resumo') {
                if($mode==='rapido')break;
                $g=Game::command($g,'continue',[],true);continue;
            }
            if($mode==='rapido' && $played && $g['stage']==='treino')break;
            if($g['stage']==='partida') {
                $m=$g['matches'][Game::currentIndex($g)];
                $advance=$m['status']!=='em_andamento' || $m['minute']<$m['entry'] || $m['minute']>=$m['exit'];
                $action=$advance?'advance':'choice';
            } elseif(in_array($g['stage'],['treino','pre_jogo','fim_semana'],true))$action='choice';
            else throw new LogicException('Etapa inesperada na simulação.');
            $input=[];
            if($action==='choice'){$input=['option'=>self::option($g,$plan)];$decisions++;}
            // Capture o jogo antes de afterWeekend mudar calendário/fase.
            if($g['stage']==='fim_semana') {
                $m=$g['matches'][Game::currentIndex($g)];
                $played[]=['home'=>$m['home'],'away'=>$m['away'],'hg'=>$m['hg'],'ag'=>$m['ag'],'stats'=>$m['stats'],'log'=>$m['log'],'competition'=>Competition::label($g),'experience'=>$m['experience']??null];
            }
            $context=['stage'=>$g['stage'],'round'=>$g['round'],'competition'=>$g['competition_id']??'legacy','minute'=>$g['stage']==='partida'?$g['matches'][Game::currentIndex($g)]['minute']:null];
            $g=Game::command($g,$action,$input,true);
            if($action==='choice')$attempts[]=$context+array_intersect_key($g['feedback']??[],array_flip(['situation','option','chance','roll','intensity_roll','success','class','effects','rules','conversion_chance','conversion_roll','narrative_selection','probability_trace']));
        }
        if($step===3000)throw new RuntimeException('A simulação excedeu o limite seguro; nada foi salvo.');
        $after=Game::summary($g);$growth=[];
        foreach($skills as $key=>$s)if($g['skills'][$key]['value']!==$s['value'])$growth[$key]=$g['skills'][$key]['value']-$s['value'];
        $g['pace_report']=['mode'=>$mode,'plan'=>$plan,'season'=>$season,'matches'=>$played,'decisions'=>$decisions,'attempts'=>$attempts,'growth'=>$growth,'before'=>$before,'after'=>$after];
        $g['feedback']=null;$g['version']=$version+1;
        return $g;
    }
    private static function option(array $g,string $plan): int {
        $options=Game::choices($g);$scores=[];
        foreach($options as $i=>$o) {
            $score=0.;$effects=$o['effects'];
            if($g['stage']==='partida') {
                $preferred=$plan==='coletivo'?'passe':(in_array($g['position'],['zagueiro','lateral','volante'],true)?'desarme':'chute');
                if(in_array($plan,['protagonismo','coletivo'],true))$score+=($o['action']===$preferred?30:0);
                elseif($plan==='equilibrio')$score+=Game::chance($g,$o)/2;
                // Evolução varia a prática e nunca força uma ação inexistente no contexto.
            }
            foreach($effects as $key=>$delta) {
                if(isset($g['skills'][$key]))$score+=max(0,$delta)*($plan==='evolucao'?4:1);
                elseif(str_starts_with($key,'risco_'))$score-=$delta*2;
                elseif($key==='energia')$score+=$delta*($plan==='equilibrio' || $g['states']['energia']<40?3:1);
                elseif($key==='entrosamento')$score+=$delta*($plan==='coletivo'?3:1);
                else $score+=$delta;
            }
            $scores[$i]=$score;
        }
        $best=max($scores);$candidates=array_keys(array_filter($scores,fn($score)=>$score===$best));
        return $candidates[random_int(0,count($candidates)-1)];
    }
    public static function selector(string $mode='normal'): void {
        echo '<label>Ritmo da carreira<select class="form-select" name="mode">';
        foreach(self::MODES as $key=>$label)echo '<option value="'.e($key).'" '.($key===$mode?'selected':'').'>'.e($label).'</option>';
        echo '</select></label>';
    }
    public static function controls(array $g): void {
        // Ritmo escolhido uma única vez no formulário de criação do jogador.
    }
    public static function report(array $g): void {
        $r=$g['pace_report']??null;if(!$r)return;
        echo '<article class="panel pace-report"><p class="tag">'.($r['mode']==='expresso'?'TEMPORADA SIMULADA':'APITO FINAL').'</p>';
        $last=$r['matches']?end($r['matches']):null;
        if($r['mode']==='rapido' && $last) {
            echo '<h2>'.e($last['home'].' '.$last['hg'].' × '.$last['ag'].' '.$last['away']).'</h2><p>Você: '.e($last['stats']['goals']).' gols · '.e($last['stats']['assists']).' assistências · '.e($last['stats']['minutes']).' min · nota '.e($last['stats']['rating']).'</p>';
            MatchExperience::render($last);
            if(!$last['stats']['minutes'])echo '<p>Você não entrou em campo nesta partida.</p>';
        } else echo '<h2>'.count($r['matches']).' jogos simulados</h2><p>Temporada '.$r['season'].' · '.e(self::PLANS[$r['plan']][0]).'</p><p>'.e($r['after']['goals']).' gols · '.e($r['after']['assists']).' assistências · nota média '.e($r['after']['average']).'</p>';
        foreach($r['growth'] as $key=>$value)echo '<span class="effect-chip">'.e(ucfirst(str_replace('_',' ',$key))).' +'.e($value).'</span> ';
        echo '<details><summary>Ver jogos e destaques</summary>';
        foreach($r['matches'] as $m) {
            echo '<p><b>'.e($m['home'].' '.$m['hg'].' × '.$m['ag'].' '.$m['away']).'</b><br><small>'.e($m['competition']).' · você: '.e($m['stats']['goals']).' gols, '.e($m['stats']['assists']).' assistências</small></p>';
            if($r['mode']==='expresso')MatchExperience::render($m);
            foreach($m['log'] as $l)if(str_contains($l['text'],'Gol do seu jogador!') || str_contains($l['text'],'assistência!'))echo '<p class="pace-highlight">'.e($l['minute']).'′ '.e($l['text']).'</p>';
        }
        echo '</details></article>';
    }
    public static function decision(array $g): void {
        $express=self::name($g)==='expresso';
        $m=$g['matches'][Game::currentIndex($g)];
        echo '<article class="panel pace-decision"><p class="tag">'.($express?'EXPRESSO · ESTRATÉGIA DA TEMPORADA':'RÁPIDO · PRÓXIMA PARTIDA').'</p><h2>'.($express?'Qual será seu foco?':e($m['home'].' × '.$m['away'])).'</h2>';
        if($express)echo '<p>Vamos simular '.(Game::summary($g)['games']?'o restante da temporada':'esta temporada').', incluindo todas as suas competições. Ao final, você escolhe seu próximo contrato.</p>';
        else echo '<p>Escolha sua abordagem. Treino, preparação e lances serão resolvidos com esse foco.</p>';
        form('/carreira/simular',$g);
        foreach(self::PLANS as $key=>$p)echo '<button class="choice" name="plan" value="'.e($key).'"><span><strong>'.e($p[0]).'</strong><small>'.e($p[1]).'</small></span></button>';
        echo '</form></article>';
    }
}
