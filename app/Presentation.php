<?php
declare(strict_types=1);
final class Presentation {
    public static function seasonStory(array $g): string {
        $summary=Game::summary($g);
        if(!empty($g['official'])) {
            $stories=[];foreach($g['competition_results'] as $r)$stories[]=CompetitionView::resultText($r,$g['club']);
            return implode(' ',$stories).' Você entrou em campo em '.$summary['games'].' jogos, marcou '.$summary['goals'].' gols e deu '.$summary['assists'].' assistências.';
        }
        foreach(Schedule::table($g['matches'],$g['teams']) as $i=>$row) if($row['team']===$g['club']) {
            $opening=$i===0?'Seu clube terminou na liderança.':$g['club'].' terminou em '.($i+1).'º lugar.';
            return $opening.' Foram '.$row['points'].' pontos. Você entrou em campo em '.$summary['games'].' jogos, marcou '.$summary['goals'].' gols e deu '.$summary['assists'].' assistências.';
        }
        return 'A temporada terminou. É hora de decidir seu próximo passo.';
    }
    public static function feedback(array $g): void {
        $f=$g['feedback']; if(!$f) return;
        $rolled=isset($f['success']); $success=$rolled && $f['success'];
        $tone=$rolled?($success?'success':'failure'):'neutral';
        $title=$rolled?($success?($f['class']==='excepcional'?'Jogada excepcional!':'Deu certo!'):($f['class']==='falha_grave'?'Não foi desta vez':'Não deu certo')):'Novo capítulo';
        $highlight=$f['highlight']??null;
        if($highlight==='goal') $title='GOOOL! Você marcou!';
        if($highlight==='assist') $title='Assistência sua!';
        $soundAttr = '';
        if ($highlight === 'goal' || $highlight === 'assist') {
            $eventType = ($highlight === 'goal') ? 'GOAL_SCORED' : 'ASSIST_RECORDED';
            if (!empty($f['sound_event_id'])) {
                $soundEventId = $f['sound_event_id'];
            } else {
                $m = (!empty($g['matches']) && in_array($g['stage'] ?? '', ['partida', 'fim_semana'], true)) ? ($g['matches'][Game::currentIndex($g)] ?? null) : null;
                $minute = $m['minute'] ?? 90;
                $round = $m['round'] ?? ($g['round'] ?? 1);
                $season = $g['season'] ?? 1;
                $compId = $m['competition'] ?? ($g['competition_id'] ?? 'c');
                $matchKey = ($m['home'] ?? 'h') . '-' . ($m['away'] ?? 'a');
                $soundEventId = ($g['id']??'0').':s'.$season.':'.$compId.':m'.$round.':'.$matchKey.':min'.$minute.':'.$eventType.':fb';
            }
            $soundAttr = ' data-sound-event="'.$eventType.'" data-sound-event-id="'.e($soundEventId).'"';
        }
        $text=str_replace([': a intenção deu certo.',': a intenção não teve o efeito esperado.'],'.',$f['text']);
        echo '<aside class="action-result result-'.$tone.($highlight==='goal'?' result-goal':($highlight==='assist'?' result-assist':'')).'" data-result-id="'.e($g['id'].':'.$g['version']).'"'.$soundAttr.' aria-live="polite">';
        echo '<div class="result-icon" aria-hidden="true">'.($highlight==='goal'?'⚽':($rolled?($success?'✓':'×'):'★')).'</div><div class="result-copy"><small class="result-status">Resultado salvo</small><h2>'.e($title).'</h2><p>'.e($text).'</p>';
        echo '<div class="result-effects">';
        foreach($f['effects']??[] as $k=>$v) if($v!=0) echo '<span class="effect-chip">'.e(self::label($k)).' '.e($v>=0?'+'.$v:$v).'</span>';
        foreach($f['attribute_changes']??[] as $k=>$v)echo '<span class="effect-chip">'.e(self::label($k)).': '.e($v['before']).' → '.e($v['after']).'</span>';
        echo '</div></div></aside>';
    }
    public static function matchSounds(array $g, array $match): void {
        if (PlayMode::name($g) !== 'normal') return;

        Game::reconcileSoundEvents($match, $g);
        $events = $match['sound_events'] ?? [];
        $whistle = match (true) {
            $g['stage'] === 'partida' && $match['status'] === 'em_andamento' && $match['minute'] === 15 => 'MATCH_WHISTLE_START',
            $g['stage'] === 'fim_semana' && $match['status'] === 'finalizada' => 'MATCH_WHISTLE_END',
            default => null,
        };
        if ($whistle !== null && !in_array($whistle, array_column($events, 'type'), true)) {
            $matchId = implode(':', [
                $g['id'] ?? '0', $g['season'] ?? 1, $match['competition'] ?? ($g['competition_id'] ?? 'c'),
                $match['round'] ?? ($g['round'] ?? 1), $match['home'], $match['away'],
            ]);
            $event = ['type' => $whistle, 'id' => $matchId.':'.$whistle];
            if ($whistle === 'MATCH_WHISTLE_START') array_unshift($events, $event);
            else $events[] = $event;
        }

        foreach ($events as $event) {
            echo '<div data-sound-event="'.e($event['type'] ?? 'GENERIC').'" data-sound-event-id="'.e($event['id']).'" class="sr-only" aria-hidden="true"></div>';
        }
    }

    public static function label(string $k): string {
        return strtr(ucfirst(str_replace('_',' ',$k)),['Confianca'=>'Confiança','Finalizacao'=>'Finalização','Visao'=>'Visão','Resistencia'=>'Resistência','Forca'=>'Força','lesao'=>'lesão','polemica'=>'polêmica','cartao'=>'cartão']);
    }
    public static function renderAggregate(?array $agg): void {
        if (!$agg) return;
        $leg1Home = ClubBadge::name($agg['leg1']['home']);
        $leg1Away = ClubBadge::name($agg['leg1']['away']);
        echo '<div class="aggregate-strip" data-aggregate="true">';
        echo '<div class="aggregate-row">';
        echo '<span class="aggregate-leg1">Ida: <b>'.e($leg1Home).' '.e($agg['leg1']['hg']).' <small>×</small> '.e($agg['leg1']['ag']).' '.e($leg1Away).'</b></span>';
        echo '<span class="aggregate-total">Agregado: <strong>'.e($agg['home_aggregate']).' <small>×</small> '.e($agg['away_aggregate']).'</strong></span>';
        echo '</div>';
        echo '<div class="aggregate-status">';
        if ($agg['finalized']) {
            if ($agg['by_penalties'] && !empty($agg['penalties'])) {
                $penWinner = ClubBadge::name($agg['advanced_id']);
                echo '<span class="status-tag advanced">Classificado nos pênaltis: <b>'.e($penWinner).'</b> ('.e($agg['penalties'][0]).' × '.e($agg['penalties'][1]).')</span>';
            } elseif ($agg['is_tied'] && !empty($agg['advantage_id']) && $agg['advanced_id'] === $agg['advantage_id']) {
                $advName = ClubBadge::name($agg['advanced_id']);
                echo '<span class="status-tag advanced">Classificado por vantagem: <b>'.e($advName).'</b> (melhor campanha)</span>';
            } elseif ($agg['advanced_id']) {
                $winnerName = ClubBadge::name($agg['advanced_id']);
                echo '<span class="status-tag advanced">Classificado: <b>'.e($winnerName).'</b> (agregado '.e($agg['home_aggregate']).' × '.e($agg['away_aggregate']).')</span>';
            } elseif ($agg['is_tied'] && ($agg['rule'] ?? '') === 'penalties') {
                echo '<span class="status-tag penalties">Empate no agregado ('.e($agg['home_aggregate']).' × '.e($agg['away_aggregate']).') · Decisão por pênaltis</span>';
            }
        } else {
            if (!$agg['is_tied']) {
                $leadingName = ClubBadge::name($agg['qualifying_id']);
                echo '<span class="status-tag qualifying">Classificando: <b>'.e($leadingName).'</b></span>';
                if ($agg['advantage_id']) {
                    $advName = ClubBadge::name($agg['advantage_id']);
                    echo '<small class="subtle">Vantagem do empate: '.e($advName).'</small>';
                } else {
                    echo '<small class="subtle">Empate no agregado leva aos pênaltis</small>';
                }
            } else {
                if ($agg['advantage_id']) {
                    $advName = ClubBadge::name($agg['advantage_id']);
                    echo '<span class="status-tag advantage">Empate no agregado ('.e($agg['home_aggregate']).' × '.e($agg['away_aggregate']).') · Vantagem: <b>'.e($advName).'</b></span>';
                } else {
                    echo '<span class="status-tag penalties">Empate no agregado ('.e($agg['home_aggregate']).' × '.e($agg['away_aggregate']).') · Decisão por pênaltis</span>';
                }
            }
        }
        echo '</div></div>';
    }
    public static function playerParticipation(array $g, array $m): array {
        $status = $m['status'] ?? 'em_andamento';
        $minute = (int)($m['minute'] ?? 0);
        $entry = (int)($m['entry'] ?? 0);
        $exit = (int)($m['exit'] ?? 90);
        $partState = $m['participation_state'] ?? null;
        $lineup = $m['lineup'] ?? 'titular';
        $st = $m['stats'] ?? [];
        $isRedCard = ($partState === 'SENT_OFF') || (!empty($st['red_cards']) && $st['red_cards'] > 0) || !empty($m['red_card_minute']);
        $redMinute = (int)($m['red_card_minute'] ?? ($exit < 90 ? $exit : $minute));

        // 1. Atleta suspenso
        if ($partState === 'SUSPENDED' || $lineup === 'suspenso') {
            return [
                'type' => 'suspended',
                'label' => 'Suspenso',
                'class' => 'status-suspended',
                'note_icon' => '🚫',
                'note_title' => 'Atleta suspenso',
                'note_desc' => 'Você cumpre suspensão e não pode atuar nesta partida.',
            ];
        }

        // 2. Atleta não relacionado
        if ($partState === 'NOT_SELECTED' || $lineup === 'não relacionado') {
            return [
                'type' => 'not_selected',
                'label' => 'Não relacionado',
                'class' => 'status-not-selected',
                'note_icon' => '📋',
                'note_title' => 'Não relacionado',
                'note_desc' => 'Você foi poupado devido ao desgaste físico e acompanha a partida de fora.',
            ];
        }

        // 3. Atleta expulso (cartão vermelho)
        if ($isRedCard) {
            $label = $redMinute > 0 ? 'Expulso aos '.$redMinute.'′' : 'Expulso';
            return [
                'type' => 'sent_off',
                'label' => $label,
                'class' => 'status-sent-off',
                'note_icon' => '🟥',
                'note_title' => 'Expulsão de campo',
                'note_desc' => $redMinute > 0 ? "Você foi expulso aos {$redMinute}′ e desfalca a equipe até o encerramento do jogo." : "Você foi expulso e desfalca a equipe até o encerramento do jogo.",
            ];
        }

        // 4. Pré-jogo
        if ($status === 'pre_jogo' || ($g['stage'] ?? '') === 'pre_jogo') {
            $isStarter = ($partState === 'STARTING' || $lineup === 'titular');
            return [
                'type' => 'pre_jogo',
                'label' => $isStarter ? 'Escalado (titular)' : 'No banco (reserva)',
                'class' => $isStarter ? 'status-starting' : 'status-bench',
                'note_icon' => '👕',
                'note_title' => 'Pré-jogo',
                'note_desc' => 'Aquecimento concluído. Prepare-se para o início do confronto.',
            ];
        }

        // 5. Atleta substituído (saiu de campo antes do fim sem expulsão)
        $isSubstituted = ($partState === 'SUBSTITUTED_OUT') || ($exit < 90 && $minute >= $exit);
        if ($isSubstituted) {
            return [
                'type' => 'substituted',
                'label' => 'Substituído aos '.$exit.'′',
                'class' => 'status-subbed',
                'note_icon' => '🔄',
                'note_title' => 'Substituição realizada',
                'note_desc' => 'Você foi substituído aos '.$exit.'′ e acompanha os momentos finais da sua equipe.',
            ];
        }

        // 6. Partida finalizada
        if ($status === 'finalizada' || $partState === 'FULL_TIME' || $minute >= 90) {
            if (($entry >= 90 && $lineup === 'reserva') || ($partState === 'ON_BENCH')) {
                return [
                    'type' => 'unused_bench',
                    'label' => 'Permaneceu no banco',
                    'class' => 'status-bench',
                    'note_icon' => '🪑',
                    'note_title' => 'Partida finalizada',
                    'note_desc' => 'Você permaneceu como opção no banco e não chegou a entrar em campo.',
                ];
            }
            return [
                'type' => 'finished',
                'label' => 'Partida encerrada',
                'class' => 'status-finished',
                'note_icon' => '🏁',
                'note_title' => 'Partida finalizada',
                'note_desc' => 'O árbitro encerrou a partida.',
            ];
        }

        // 7. Atleta no banco de reservas durante a partida
        $isOnBench = ($partState === 'ON_BENCH') || ($lineup === 'reserva' && $minute < $entry);
        if ($isOnBench) {
            return [
                'type' => 'bench',
                'label' => 'No banco de reservas',
                'class' => 'status-bench',
                'note_icon' => '🪑',
                'note_title' => 'No banco de reservas',
                'note_desc' => 'Você inicia como opção no banco e aguarda a decisão do treinador.',
            ];
        }

        // 8. Intervalo de jogo
        if ($status === 'intervalo' || $minute === 45) {
            return [
                'type' => 'intervalo',
                'label' => 'Em campo',
                'class' => 'status-onfield',
                'note_icon' => '⏱️',
                'note_title' => 'Intervalo de jogo (45′)',
                'note_desc' => 'Fim do primeiro tempo. Descanse no vestiário e ajuste as instruções para a etapa final.',
            ];
        }

        // 9. Em campo (padrão quando ativo no jogo)
        return [
            'type' => 'on_field',
            'label' => 'Em campo',
            'class' => 'status-onfield',
            'note_icon' => '⚽',
            'note_title' => 'Em campo',
            'note_desc' => 'Partida em andamento.',
        ];
    }

    public static function score(array $g,array $m): void {
        $status = $m['status'] ?? 'em_andamento';
        $minute = (int)($m['minute'] ?? 0);

        if ($status === 'intervalo') {
            $periodLabel = 'Intervalo';
            $periodClass = 'intervalo';
        } elseif ($status === 'finalizada') {
            $periodLabel = 'Fim de jogo';
            $periodClass = 'finalizada';
        } elseif ($status === 'pre_jogo') {
            $periodLabel = 'Pré-jogo';
            $periodClass = 'pre-jogo';
        } else {
            $periodLabel = ($minute <= 45) ? '1º Tempo' : '2º Tempo';
            $periodClass = ($minute <= 45) ? '1t' : '2t';
        }

        $progressMinute = min(90, max(0, $minute));
        $isHomeUser = ($m['home'] === ($g['club'] ?? ''));
        $isAwayUser = ($m['away'] === ($g['club'] ?? ''));

        $roundText = isset($m['round']) ? 'Rodada '.(int)$m['round'] : (isset($g['round']) ? 'Rodada '.(int)$g['round'] : '');

        echo '<article class="panel match compact-score r10-scoreboard" aria-label="Placar da partida">';
        echo '<div class="match-time">';
        echo '<div class="match-time-left">';
        echo '<span class="match-period-badge badge-'.e($periodClass).'">'.e($periodLabel).'</span>';
        if ($status === 'em_andamento') {
            echo '<span class="match-clock" title="Minuto oficial"><span class="match-live-dot" aria-hidden="true"></span><b>'.e($minute).'′</b></span>';
        } else {
            echo '<span class="match-clock-static"><b>'.e($minute).'′</b></span>';
        }
        echo '</div>';
        if ($roundText !== '') {
            echo '<div class="match-round-tag">'.e($roundText).'</div>';
        }
        echo '</div>';

        echo '<div class="score-row">';
        echo '<div class="score-team team-home'.($isHomeUser ? ' team-user' : '').'">';
        echo '<div class="team-meta">';
        echo '<span class="team-name" title="'.e(ClubBadge::name($m['home'])).'">'.e(ClubBadge::name($m['home'])).'</span>';
        if ($isHomeUser) echo '<span class="team-user-pill">Seu clube</span>';
        echo '</div>';
        echo '<div class="team-badge-wrap">';
        ClubBadge::render($m['home']);
        echo '</div>';
        echo '</div>';

        echo '<div class="score-center">';
        echo '<strong class="score-numbers" aria-label="Placar: '.e($m['hg']).' a '.e($m['ag']).'">'.e($m['hg']).' <small>×</small> '.e($m['ag']).'</strong>';
        echo '</div>';

        echo '<div class="score-team team-away'.($isAwayUser ? ' team-user' : '').'">';
        echo '<div class="team-badge-wrap">';
        ClubBadge::render($m['away']);
        echo '</div>';
        echo '<div class="team-meta">';
        echo '<span class="team-name" title="'.e(ClubBadge::name($m['away'])).'">'.e(ClubBadge::name($m['away'])).'</span>';
        if ($isAwayUser) echo '<span class="team-user-pill">Seu clube</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="match-progress-wrap">';
        echo '<div class="match-progress-track">';
        echo '<progress class="match-progress-bar" value="'.e($progressMinute).'" max="90" aria-label="Progresso da partida: '.e($minute).' minutos">'.e($progressMinute).'/90</progress>';
        echo '<span class="match-progress-pin pin-45" title="Intervalo (45′)"></span>';
        echo '</div>';
        echo '<div class="match-progress-labels" aria-hidden="true">';
        echo '<span>0′</span>';
        echo '<span class="label-half">INT</span>';
        echo '<span>90′</span>';
        echo '</div>';
        echo '</div>';

        $agg = Competition::aggregateContext($g, $m);
        if ($agg) self::renderAggregate($agg);

        $part = self::playerParticipation($g, $m);

        echo '<div class="live-player-stats">';
        echo '<span class="player-field-indicator '.e($part['class']).'">'.e($part['label']).'</span>';
        echo '<div class="player-stats-counts">';
        echo '<span>⚽ <b data-stat="goals">'.e($m['stats']['goals'] ?? 0).'</b> seus gols</span>';
        echo '<span>↗ <b data-stat="assists">'.e($m['stats']['assists'] ?? 0).'</b> assistências</span>';
        echo '</div>';
        echo '</div>';
        echo '</article>';
    }
    public static function radio(array $m): void {
        if($m['status']!=='finalizada') return;
        echo '<details class="panel match-radio"><summary>Rádio do jogo · resumo dos lances</summary>';
        foreach($m['log'] as $l) echo '<p><b>'.e($l['minute']).'′</b> '.e($l['text']).'</p>';
        echo '</details>';
    }
    public static function matchStats(array $g,array $m): void {
        $s=$m['stats'];
        echo '<section class="player-match-stats panel" aria-label="Seu desempenho nesta partida"><p class="tag">SEU DESEMPENHO NESTA PARTIDA</p><div class="stat-strip">';
        $metrics=['goals'=>'Gols','assists'=>'Assistências','shots'=>'Finalizações','completed'=>'Passes certos'];
        if($m['status']==='finalizada' && in_array($g['position'],['zagueiro','lateral','volante'],true))$metrics+=['tackles'=>'Desarmes','fouls'=>'Faltas','cards'=>'Cartões'];
        foreach($metrics as $k=>$label) echo '<div><strong data-stat="'.$k.'">'.e($s[$k]).'</strong><span>'.e($label).'</span></div>';
        echo '</div><p class="subtle">'.e($g['name']).' · '.($m['status']==='finalizada'?'Nota '.e($s['rating']).' · '.e($s['minutes']).' minutos jogados':'Atualizado a cada lance').'</p></section>';
    }
    public static function goalHistory(array $g): void {
        $rows=[];
        foreach(Competition::playerMatches($g) as $m) if($m['status']==='finalizada' && in_array($g['club'],[$m['home'],$m['away']],true)) $rows[]=$m;
        if(!empty($g['official']))$rows=array_reverse($rows);
        else usort($rows,fn($a,$b)=>$b['round']<=>$a['round']);
        $summary=Game::summary($g);
        echo '<section class="panel goal-history"><p class="tag">TEMPORADA '.e($g['season']).'</p><h2>Seus gols no campeonato</h2><div class="stat-strip"><div><strong data-stat="season-goals">'.e($summary['goals']).'</strong><span>Gols marcados</span></div><div><strong>'.e($summary['assists']).'</strong><span>Assistências</span></div><div><strong>'.e($summary['games']).'</strong><span>Jogos em campo</span></div></div>';
        if(!$rows) echo '<p class="subtle">Seu histórico aparecerá após a primeira partida.</p>';
        else {
            echo '<details'.($g['stage']==='fim_semana'?' open':'').'><summary>Gols por rodada</summary><ol class="goal-list">';
            foreach($rows as $m) {
                $opponent=$m['home']===$g['club']?$m['away']:$m['home']; $goals=$m['stats']['goals'];
                $competition=isset($m['competition'])?Competition::catalog()[$m['competition']]['name'].' · ':'';
                echo '<li><div><small>'.e($competition).'RODADA '.e($m['round']).' · '.e($m['hg'].' × '.$m['ag']).'</small><span>'.e($opponent).'</span></div><b class="'.($goals?'has-goals':'').'">'.e($goals).' '.($goals===1?'gol':'gols').'</b></li>';
            }
            echo '</ol></details>';
        }
        echo '</section>';
    }
}
