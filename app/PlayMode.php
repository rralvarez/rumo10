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
        $mode=self::name($g);
        if($mode==='normal' || $g['stage']!=='treino' || !isset(self::PLANS[$plan]))throw new InvalidArgumentException('Estratégia incompatível com a etapa atual.');
        $version=$g['version'];$season=$g['season'];$before=Game::summary($g);$skills=$g['skills'];$played=[];$decisions=0;$attempts=[];
        for($step=0;$step<3000;$step++) {
            if($g['season']!==$season)throw new LogicException('A simulação não pode renovar contratos.');
            if(in_array($g['stage'],['resumo','aposentado'],true))break;
            if($g['stage']==='competicao_resumo') {
                if($mode==='rapido')break;
                $g=Game::command($g,'continue',[],true);continue;
            }
            if($mode==='rapido' && $played && $g['stage']==='treino')break;
            // A disputa pendente bloqueia escolhas narrativas; usar o fluxo de penalidades existente.
            if($g['stage']==='fim_semana' && PenaltyShootout::requiresShootout($g,$g['matches'][Game::currentIndex($g)])) {
                $g=Game::command($g,'shootout_start',[],true);continue;
            }
            if($g['stage']==='penaltis') {
                if(($g['shootout']['status']??'')==='completed')$g=Game::command($g,'shootout_finish',[],true);
                elseif(PenaltyShootout::isProtagonistTurn($g)){
                    $choices=Game::choices($g);$best=0;foreach($choices as $j=>$choice)if($choice['chance']>$choices[$best]['chance'])$best=$j;
                    $g=Game::command($g,'choice',['option'=>$best],true);$decisions++;
                }else $g=Game::command($g,'shootout_advance',[],true);
                continue;
            }
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
                if(in_array($plan,['protagonismo','coletivo'],true)||(PlayerDevelopment::enabled($g)&&$plan==='evolucao'))$score+=($o['action']===$preferred?30:0);
                elseif($plan==='equilibrio')$score+=Game::chance($g,$o)/2;
                // Evolução varia a prática e nunca força uma ação inexistente no contexto.
            }
            foreach($effects as $key=>$delta) {
                if(isset($g['skills'][$key]))$score+=max(0,$delta)*($plan==='evolucao'?4:1);
                elseif(str_starts_with($key,'risco_'))$score-=$delta*2;
                elseif($key==='energia')$score+=$delta*($plan==='equilibrio' || $g['states']['energia']<40?3:(PlayerDevelopment::enabled($g)&&$plan==='evolucao'?.15:1));
                elseif($key==='entrosamento')$score+=$delta*($plan==='coletivo'?3:1);
                else $score+=$delta*(PlayerDevelopment::enabled($g)&&$plan==='evolucao'?.15:1);
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
            echo '<h2>'.e($last['home'].' '.$last['hg'].' × '.$last['ag'].' '.$last['away']).'</h2>';
            MatchExperience::render($last);
            if(!$last['stats']['minutes'])echo '<p class="subtle">Você não entrou em campo nesta partida.</p>';
        } else {
            echo '<h2>'.count($r['matches']).' jogos simulados</h2><p>Temporada '.$r['season'].' · '.e(self::PLANS[$r['plan']][0]).'</p>';
            self::renderSeasonSummary($r);
        }
        foreach($r['growth'] as $key=>$value)echo '<span class="effect-chip">'.e(ucfirst(str_replace('_',' ',$key))).' +'.e($value).'</span> ';
        echo '<details><summary>Ver jogos e destaques</summary>';
        foreach($r['matches'] as $m) {
            echo '<p><b>'.e($m['home'].' '.$m['hg'].' × '.$m['ag'].' '.$m['away']).'</b><br><small>'.e($m['competition']).' · você: '.e($m['stats']['goals']).' gols, '.e($m['stats']['assists']).' assistências</small></p>';
            if($r['mode']==='expresso')MatchExperience::render($m);
            foreach($m['log'] as $l)if(str_contains($l['text'],'Gol do seu jogador!') || str_contains($l['text'],'assistência!'))echo '<p class="pace-highlight">'.e($l['minute']).'′ '.e($l['text']).'</p>';
        }
        echo '</details></article>';
    }
    private static function renderSeasonSummary(array $r): void {
        $matches = $r['matches'] ?? [];
        $totalMatches = count($matches);

        $totMinutes = 0;
        $countMinutes = 0;

        $totGoals = 0;
        $countGoals = 0;

        $totAssists = 0;
        $countAssists = 0;

        $totShots = 0;
        $countShots = 0;

        $sumRating = 0.0;
        $ratedMatches = 0;
        $eligibleMatches = 0;

        $totXp = 0;
        $countMeasuredXp = 0;

        foreach($matches as $m) {
            $stats = $m['stats'] ?? [];

            // Minutos
            if(array_key_exists('minutes', $stats) && $stats['minutes'] !== null) {
                $countMinutes++;
                $totMinutes += (int)$stats['minutes'];
            }

            // Gols
            if(array_key_exists('goals', $stats) && $stats['goals'] !== null) {
                $countGoals++;
                $totGoals += (int)$stats['goals'];
            }

            // Assistências
            if(array_key_exists('assists', $stats) && $stats['assists'] !== null) {
                $countAssists++;
                $totAssists += (int)$stats['assists'];
            }

            // Finalizações
            if(array_key_exists('shots', $stats) && $stats['shots'] !== null) {
                $countShots++;
                $totShots += (int)$stats['shots'];
            }

            // Nota média: somente entre partidas com participação e avaliação válida
            $mins = (int)($stats['minutes'] ?? 0);
            if($mins > 0) {
                $eligibleMatches++;
                if(isset($stats['rating']) && $stats['rating'] !== null && (float)$stats['rating'] > 0) {
                    $sumRating += (float)$stats['rating'];
                    $ratedMatches++;
                }
            }

            // XP: medição confiável requer complete=true E estrutura válida de earned (array)
            $exp = $m['experience'] ?? null;
            if(is_array($exp) && !empty($exp['complete']) && isset($exp['earned']) && is_array($exp['earned'])) {
                $countMeasuredXp++;
                $totXp += array_sum($exp['earned']);
            }
        }

        echo '<section class="match-experience" aria-label="Resumo das partidas simuladas">';
        echo '<div class="match-protagonist-strip" role="group" aria-label="Estatísticas do jogador nas partidas simuladas">';

        // 1. Minutos
        $minPartial = ($countMinutes > 0 && $countMinutes < $totalMatches);
        $minLabel = 'Minutos' . ($minPartial ? '<span class="stat-sublabel">Medição parcial</span>' : '');
        if($countMinutes === 0) {
            echo '<div class="stat-chip stat-chip-minutes" aria-label="Minutos indisponíveis">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg><span class="stat-value">—</span></div>';
            echo '<span class="stat-label">Minutos</span>';
            echo '</div>';
        } else {
            $chipAria = 'Minutos jogados: '.$totMinutes . ($minPartial ? ' (medição parcial)' : '');
            echo '<div class="stat-chip stat-chip-minutes" aria-label="'.e($chipAria).'">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg><span class="stat-value">'.e($totMinutes).'</span></div>';
            echo '<span class="stat-label">'.$minLabel.'</span>';
            echo '</div>';
        }

        // 2. Nota média
        if($ratedMatches > 0) {
            $avgRating = round($sumRating / $ratedMatches, 1);
            $ratingStr = number_format($avgRating, 1, ',', '.');
            $hasMissingEligible = ($eligibleMatches > $ratedMatches);
            $ratingLabelText = $hasMissingEligible ? 'Média das partidas avaliadas' : 'Nota média';
            $ratingSublabel = $hasMissingEligible ? '<span class="stat-sublabel">Média das partidas avaliadas</span>' : '';
            $ratingAria = $hasMissingEligible ? 'Nota média: '.$ratingStr.' (Média das partidas avaliadas)' : 'Nota média do jogador: '.$ratingStr;
            echo '<div class="stat-chip stat-chip-rating" aria-label="'.e($ratingAria).'">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span class="stat-value">'.e($ratingStr).'</span></div>';
            echo '<span class="stat-label">Nota média'.$ratingSublabel.'</span>';
            echo '</div>';
        } else {
            echo '<div class="stat-chip stat-chip-rating" aria-label="Nota média indisponível">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span class="stat-value">—</span></div>';
            echo '<span class="stat-label">Nota média</span>';
            echo '</div>';
        }

        // 3. Gols
        $goalsPartial = ($countGoals > 0 && $countGoals < $totalMatches);
        $goalsText = ($totGoals === 1 ? 'Gol' : 'Gols');
        $goalsLabelHtml = $goalsText . ($goalsPartial ? '<span class="stat-sublabel">Medição parcial</span>' : '');
        if($countGoals === 0) {
            echo '<div class="stat-chip stat-chip-goals" aria-label="Gols indisponíveis">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7l3 2v3.5l-3 2-3-2V9z"/><path d="M12 3v4M15 9l3.5-1.5M15 12.5l3.5 1.5M12 14.5v4.5M9 12.5L5.5 14M9 9L5.5 7.5"/></svg><span class="stat-value">—</span></div>';
            echo '<span class="stat-label">Gols</span>';
            echo '</div>';
        } else {
            $chipAria = $totGoals.' '.$goalsText . ($goalsPartial ? ' (medição parcial)' : '');
            echo '<div class="stat-chip stat-chip-goals" aria-label="'.e($chipAria).'">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7l3 2v3.5l-3 2-3-2V9z"/><path d="M12 3v4M15 9l3.5-1.5M15 12.5l3.5 1.5M12 14.5v4.5M9 12.5L5.5 14M9 9L5.5 7.5"/></svg><span class="stat-value">'.e($totGoals).'</span></div>';
            echo '<span class="stat-label">'.$goalsLabelHtml.'</span>';
            echo '</div>';
        }

        // 4. Assistências
        $assistsPartial = ($countAssists > 0 && $countAssists < $totalMatches);
        $assistsText = ($totAssists === 1 ? 'Assistência' : 'Assistências');
        $assistsLabelHtml = $assistsText . ($assistsPartial ? '<span class="stat-sublabel">Medição parcial</span>' : '');
        if($countAssists === 0) {
            echo '<div class="stat-chip stat-chip-assists" aria-label="Assistências indisponíveis">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 17l4-1 6 3h5a2 2 0 0 0 2-2v-1l-3-4-5-1-4 2-5 3z"/><path d="M10 11l-2 3M13 12l-1 3"/></svg><span class="stat-value">—</span></div>';
            echo '<span class="stat-label">Assistências</span>';
            echo '</div>';
        } else {
            $chipAria = $totAssists.' '.$assistsText . ($assistsPartial ? ' (medição parcial)' : '');
            echo '<div class="stat-chip stat-chip-assists" aria-label="'.e($chipAria).'">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 17l4-1 6 3h5a2 2 0 0 0 2-2v-1l-3-4-5-1-4 2-5 3z"/><path d="M10 11l-2 3M13 12l-1 3"/></svg><span class="stat-value">'.e($totAssists).'</span></div>';
            echo '<span class="stat-label">'.$assistsLabelHtml.'</span>';
            echo '</div>';
        }

        // 5. Finalizações
        $shotsPartial = ($countShots > 0 && $countShots < $totalMatches);
        $shotsText = ($totShots === 1 ? 'Finalização' : 'Finalizações');
        $shotsLabelHtml = $shotsText . ($shotsPartial ? '<span class="stat-sublabel">Medição parcial</span>' : '');
        if($countShots === 0) {
            echo '<div class="stat-chip stat-chip-shots" aria-label="Finalizações indisponíveis">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="3" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="21"/><line x1="3" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="21" y2="12"/></svg><span class="stat-value">—</span></div>';
            echo '<span class="stat-label">Finalizações</span>';
            echo '</div>';
        } else {
            $chipAria = $totShots.' '.$shotsText . ($shotsPartial ? ' (medição parcial)' : '');
            echo '<div class="stat-chip stat-chip-shots" aria-label="'.e($chipAria).'">';
            echo '<div class="stat-chip-top"><svg class="stat-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="3" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="21"/><line x1="3" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="21" y2="12"/></svg><span class="stat-value">'.e($totShots).'</span></div>';
            echo '<span class="stat-label">'.$shotsLabelHtml.'</span>';
            echo '</div>';
        }

        echo '</div>';

        // Bloco de XP: Medido zero (0 XP), Parcial (subtotal + Medição parcial) ou Indisponível (—)
        echo '<div class="match-xp-summary">';
        echo '<div class="xp-badge">';
        echo '<svg class="xp-icon" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
        if($countMeasuredXp === 0) {
            echo '<span class="xp-val">—</span>';
            echo '<span class="xp-desc">· XP registrado nas partidas simuladas (indisponível)</span>';
        } else {
            $isPartialXp = ($countMeasuredXp < $totalMatches);
            $xpValPrefix = ($totXp > 0 ? '+' : '');
            $xpDesc = '· XP registrado nas partidas simuladas' . ($isPartialXp ? ' (Medição parcial)' : '');
            echo '<span class="xp-val">'.e($xpValPrefix.$totXp.' XP').'</span>';
            echo '<span class="xp-desc">'.e($xpDesc).'</span>';
        }
        echo '</div>';
        echo '</div>';

        echo '</section>';
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
