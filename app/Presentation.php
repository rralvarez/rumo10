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
        $feedbackEvent = $f['sound_event'] ?? ($highlight === 'goal' ? 'GOAL_SCORED' : ($highlight === 'assist' ? 'ASSIST_RECORDED' : null));
        if ($feedbackEvent) {
            $eventType = $feedbackEvent;
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
            $soundAttr = ' data-sound-event="'.$eventType.'" data-sound-event-id="'.e($soundEventId).'" data-sound-source="action"';
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
            $g['stage'] === 'partida' && $match['status'] === 'intervalo' && $match['minute'] === 45 => 'MATCH_HALFTIME',
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

        $currentMinute = (int)($match['minute'] ?? 90);
        if ($g['feedback'] !== null && isset($g['feedback']['sound_event_ids']) && is_array($g['feedback']['sound_event_ids'])) {
            $freshEventIds = $g['feedback']['sound_event_ids'];
        } else {
            $freshEventIds = $g['current_command_sound_event_ids'] ?? [];
        }
        if (!empty($g['feedback']['sound_event_id']) && !in_array($g['feedback']['sound_event_id'], $freshEventIds, true)) {
            $freshEventIds[] = $g['feedback']['sound_event_id'];
        }

        foreach ($events as $event) {
            $type = $event['type'] ?? 'GENERIC';
            $id = (string)($event['id'] ?? '');
            $isCurrentStateWhistle = ($whistle !== null && $type === $whistle);

            if ($isCurrentStateWhistle) {
                // O apito derivado do estado atual da partida é contemporâneo à transição
                $isHistorical = false;
            } elseif ($id !== '' && in_array($id, $freshEventIds, true)) {
                // Eventos expressamente vinculados à ação recém-concluída são sempre frescos,
                // mesmo que o motor já tenha avançado o minuto da partida
                $isHistorical = false;
            } else {
                // Eventos persistidos não pertencentes ao feedback da ação atual (ou apitos de períodos anteriores)
                // são históricos, independentemente de igualdade ou avanço do relógio da partida
                $isHistorical = true;
            }
            $histAttr = $isHistorical ? ' data-sound-historical="1"' : '';
            echo '<div data-sound-event="'.e($type).'" data-sound-event-id="'.e($id).'"'.$histAttr.' class="sr-only" aria-hidden="true"></div>';
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
        $status = $m['status'] ?? null;
        if ($status === null || $status === '') {
            $stage = $g['stage'] ?? '';
            if ($stage === 'pre_jogo') $status = 'pre_jogo';
            elseif ($stage === 'fim_semana') $status = 'finalizada';
            else $status = 'em_andamento';
        }

        $minute = (int)($m['minute'] ?? 0);
        $entry = (int)($m['entry'] ?? 0);
        $partState = $m['participation_state'] ?? null;
        $lineup = $m['lineup'] ?? 'titular';
        $st = $m['stats'] ?? [];
        $isRedCard = MatchParticipation::isSentOff($m);
        $hasRedMinute = isset($m['red_card_minute']) && is_numeric($m['red_card_minute']) && (int)$m['red_card_minute'] > 0;
        $redMinute = $hasRedMinute ? (int)$m['red_card_minute'] : null;

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
        if ($partState === 'NOT_SELECTED' || in_array($lineup, ['não relacionado', 'nao_relacionado'], true)) {
            return [
                'type' => 'not_selected',
                'label' => 'Não relacionado',
                'class' => 'status-not-selected',
                'note_icon' => '📋',
                'note_title' => 'Não relacionado',
                'note_desc' => 'Você não foi relacionado para esta partida e acompanha o jogo de fora.',
            ];
        }

        // 3. Atleta expulso (cartão vermelho)
        if ($isRedCard) {
            $label = $redMinute !== null ? 'Expulso aos '.$redMinute.'′' : 'Expulso';
            $desc = $redMinute !== null
                ? "Você foi expulso aos {$redMinute}′ e desfalca a equipe até o encerramento do jogo."
                : "Você foi expulso e desfalca a equipe até o encerramento do jogo.";
            return [
                'type' => 'sent_off',
                'label' => $label,
                'class' => 'status-sent-off',
                'note_icon' => '🟥',
                'note_title' => 'Expulsão de campo',
                'note_desc' => $desc,
            ];
        }

        // 4. Pré-jogo (precedência estrita do status da partida sobre stage)
        if ($status === 'pre_jogo') {
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

        // 5. Atleta substituído (utiliza minuto real inclusive >= 90 para canonical SUBSTITUTED_OUT)
        $isSubstituted = false;
        $exitMinute = null;
        $exitReason = (string)($m['exit_reason'] ?? '');

        if ($partState === 'SUBSTITUTED_OUT') {
            $isSubstituted = true;
            if (isset($m['exit']) && is_numeric($m['exit']) && (int)$m['exit'] > 0) {
                $exitMinute = (int)$m['exit'];
            }
        } else {
            $hasLegacySubMarker = in_array($exitReason, ['injury', 'fatigue', 'tactical'], true)
                || !empty($m['substituido'])
                || !empty($m['sub_out_recorded'])
                || ($m['lineup'] ?? '') === 'substituído';
            if (isset($m['exit']) && is_numeric($m['exit'])) {
                // Inferência legada (sem estado canônico): exclusivamente quando exit < 90 e minute >= exit
                $rawExit = (int)$m['exit'];
                if ($rawExit > 0 && $rawExit < 90 && $minute >= $rawExit) {
                    $isSubstituted = true;
                    $exitMinute = $rawExit;
                }
            }
            if (!$isSubstituted && $hasLegacySubMarker) {
                $isSubstituted = true;
                if (isset($m['exit']) && is_numeric($m['exit']) && (int)$m['exit'] > 0 && (int)$m['exit'] < 90) {
                    $exitMinute = (int)$m['exit'];
                }
            }
        }

        if ($isSubstituted) {
            $label = $exitMinute !== null ? 'Substituído aos '.$exitMinute.'′' : 'Substituído';
            $desc = $exitMinute !== null
                ? 'Você foi substituído aos '.$exitMinute.'′ e acompanha os momentos finais da sua equipe.'
                : 'Você foi substituído e acompanha os momentos finais da sua equipe.';
            return [
                'type' => 'substituted',
                'label' => $label,
                'class' => 'status-subbed',
                'note_icon' => '🔄',
                'note_title' => 'Substituição realizada',
                'note_desc' => $desc,
            ];
        }

        // 6. Partida finalizada (precedência estrita do status da partida sobre stage)
        if ($status === 'finalizada') {
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
        $isLegacyUnenteredBench = (!$partState && in_array($lineup, ['reserva', 'banco'], true) && !MatchParticipation::canActOnField($m));
        $isOnBench = ($partState === 'ON_BENCH') || ($lineup === 'reserva' && $minute < $entry) || $isLegacyUnenteredBench;
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
        if ($status === 'intervalo' || ($minute === 45 && $status !== 'em_andamento')) {
            return [
                'type' => 'intervalo',
                'label' => 'Em campo',
                'class' => 'status-onfield',
                'note_icon' => '⏱️',
                'note_title' => 'Intervalo de jogo (45′)',
                'note_desc' => 'Fim do primeiro tempo. Descanse no vestiário e ajuste as instruções para a etapa final.',
            ];
        }

        // 9. Regra autoritativa única para interação esportiva em campo
        if (!MatchParticipation::canActOnField($m)) {
            if ($partState === 'STARTING') {
                return [
                    'type' => 'pre_jogo',
                    'label' => 'Escalado (titular)',
                    'class' => 'status-starting',
                    'note_icon' => '👕',
                    'note_title' => 'Aguardando entrada em campo',
                    'note_desc' => 'Avance a partida para acompanhar a sequência do jogo.',
                ];
            }
            return [
                'type' => 'off_field',
                'label' => 'Fora de campo',
                'class' => 'status-bench',
                'note_icon' => '⏱️',
                'note_title' => 'Acompanhando a partida',
                'note_desc' => 'Você não está em campo neste momento. Avance a partida para continuar.',
            ];
        }

        // 10. Em campo (confirmado pela regra autoritativa, inclusive acréscimos)
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
        $status = $m['status'] ?? null;
        if ($status === null || $status === '') {
            $stage = $g['stage'] ?? '';
            if ($stage === 'pre_jogo') $status = 'pre_jogo';
            elseif ($stage === 'fim_semana') $status = 'finalizada';
            else $status = 'em_andamento';
        }
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
        echo '<div class="match-meta-right">';
        echo '<span class="match-competition-name">'.e(Competition::label($g)).'</span>';
        if ($roundText !== '') {
            echo '<span class="match-round-tag"> · '.e($roundText).'</span>';
        }
        echo '</div>';
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

        $compGoals = 0;
        $compAssists = 0;
        $compMatches = 0;
        if (!empty($g['matches'])) {
            foreach ($g['matches'] as $cm) {
                if (($cm['status'] ?? '') === 'finalizada' && in_array($g['club'], [$cm['home'], $cm['away']], true)) {
                    $st = $cm['stats'] ?? [];
                    $compGoals += (int)($st['goals'] ?? 0);
                    $compAssists += (int)($st['assists'] ?? 0);
                    if ((int)($st['minutes'] ?? 0) > 0) {
                        $compMatches++;
                    }
                }
            }
        }

        echo '<section class="panel goal-history">';
        echo '<p class="tag">TEMPORADA '.e($g['season']).' · '.e(Competition::label($g)).' · '.e(Competition::phaseLabel($g)).'</p>';
        echo '<h2>Seus gols no campeonato</h2>';
        echo '<div class="history-stat-group">';
        echo '<h3 class="history-stat-title">Competição atual: '.e(Competition::label($g)).'</h3>';
        echo '<div class="stat-strip" aria-label="Estatísticas na competição atual">';
        echo '<div><strong data-stat="competition-goals">'.e($compGoals).'</strong><span>Gols na competição</span></div>';
        echo '<div><strong data-stat="competition-assists">'.e($compAssists).'</strong><span>Assistências</span></div>';
        echo '<div><strong data-stat="competition-matches">'.e($compMatches).'</strong><span>Partidas jogadas</span></div>';
        echo '</div></div>';
        echo '<div class="history-stat-group">';
        echo '<h3 class="history-stat-title">Temporada '.e($g['season']).' (Total acumulado)</h3>';
        echo '<div class="stat-strip" aria-label="Estatísticas na temporada">';
        echo '<div><strong data-stat="season-goals">'.e($summary['goals']).'</strong><span>Gols na temporada</span></div>';
        echo '<div><strong data-stat="season-assists">'.e($summary['assists']).'</strong><span>Assistências</span></div>';
        echo '<div><strong data-stat="season-matches">'.e($summary['games']).'</strong><span>Jogos em campo</span></div>';
        echo '</div></div>';
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

    public static function annualRecap(array $g): void {
        $season = (int)($g['season'] ?? 1);
        $name = (string)($g['name'] ?? 'Jogador');
        $club = (string)($g['club'] ?? '');
        $clubName = ClubBadge::name($club);

        $summary = Game::summary($g);
        $eval = class_exists('SeasonEvaluation') ? SeasonEvaluation::current($g) : ['totals' => []];

        $games = (int)($summary['games'] ?? ($summary['played'] ?? 0));
        $goals = (int)($summary['goals'] ?? 0);
        $assists = (int)($summary['assists'] ?? 0);
        $minutes = (int)($summary['minutes'] ?? 0);
        $rating = (float)($summary['average'] ?? 6.0);
        $ratingFormatted = number_format($rating, 2, ',', '.');
        $tackles = (int)($eval['totals']['tackles'] ?? 0);
        $isDefensive = in_array($g['position'] ?? '', ['zagueiro', 'lateral', 'volante'], true);

        echo '<article class="panel annual-recap-panel">';

        // 1. Identificação da temporada
        echo '<div class="recap-header">';
        echo '<p class="tag">TEMPORADA CONCLUÍDA · BALANÇO ANUAL</p>';
        echo '<h1>Temporada ' . $season . ' · ' . e($name) . '</h1>';
        echo '<div class="recap-club-line">';
        ClubBadge::render($club);
        echo '<span>' . e(ucfirst((string)($g['position'] ?? 'jogador'))) . ' · <b>' . e($clubName) . '</b> · Calendário competitivo encerrado</span>';
        echo '</div>';
        echo '</div>';

        // 2. Desempenho individual na temporada
        echo '<section class="recap-section recap-individual">';
        echo '<h2 class="recap-section-title">Desempenho individual na temporada</h2>';
        echo '<div class="recap-stats-grid" role="group" aria-label="Estatísticas do jogador na temporada">';
        echo '<div class="recap-stat-card"><strong data-stat="recap-games">' . $games . '</strong><span>' . ($games === 1 ? 'Partida' : 'Partidas') . '</span></div>';
        echo '<div class="recap-stat-card"><strong data-stat="recap-goals">' . $goals . '</strong><span>' . ($goals === 1 ? 'Gol' : 'Gols') . '</span></div>';
        echo '<div class="recap-stat-card"><strong data-stat="recap-assists">' . $assists . '</strong><span>' . ($assists === 1 ? 'Assistência' : 'Assistências') . '</span></div>';
        echo '<div class="recap-stat-card"><strong data-stat="recap-rating">' . $ratingFormatted . '</strong><span>Nota média</span></div>';
        echo '<div class="recap-stat-card"><strong data-stat="recap-minutes">' . $minutes . '′</strong><span>Minutos</span></div>';
        if ($isDefensive || $tackles > 0) {
            echo '<div class="recap-stat-card"><strong data-stat="recap-tackles">' . $tackles . '</strong><span>' . ($tackles === 1 ? 'Desarme' : 'Desarmes') . '</span></div>';
        }
        echo '</div>';
        echo '</section>';

        // 3. Resultados das competições da temporada
        echo '<section class="recap-section recap-competitions">';
        echo '<h2 class="recap-section-title">Competições disputadas</h2>';
        if (!empty($g['official'])) {
            $results = $g['competition_results'] ?? [];
            if (!$results) {
                echo '<p class="recap-comp-detail subtle">Não há classificação detalhada disponível para esta temporada.</p>';
            } else {
                foreach ($results as $compId => $r) {
                    $cName = $r['name'] ?? (Competition::catalog()[$compId]['name'] ?? $compId);
                    $champ = $r['champion'] ?? '';
                    $isChamp = ($champ !== '' && $champ === $club);
                    $promoted = !empty($r['promoted']);
                    $relegated = !empty($r['relegated']);
                    $pos = (isset($r['position']) && is_numeric($r['position']) && (int)$r['position'] > 0) ? (int)$r['position'] : null;
                    $grp = !empty($r['group']) ? (int)$r['group'] : null;

                    echo '<div class="recap-comp-item">';
                    echo '<div class="recap-comp-name">' . e($cName) . '</div>';
                    if ($isChamp) {
                        echo '<p class="recap-comp-detail recap-comp-champion">🏆 <b>Título conquistado!</b> Seu clube foi o campeão desta edição.</p>';
                    } else {
                        if ($champ !== '') {
                            echo '<p class="recap-comp-detail">Campeão: <b>' . e(ClubBadge::name($champ)) . '</b></p>';
                        }
                        if ($promoted) {
                            echo '<p class="recap-comp-detail text-success"><b>Acesso conquistado!</b> Vaga garantida na divisão superior.</p>';
                        } elseif ($relegated) {
                            echo '<p class="recap-comp-detail text-danger"><b>Rebaixamento:</b> Terminou na zona de descenso.</p>';
                        } elseif ($pos !== null) {
                            echo '<p class="recap-comp-detail">Terminou em <b>' . $pos . 'º lugar</b>' . ($grp !== null ? ' no Grupo ' . $grp . '.' : ' na primeira fase.') . '</p>';
                        } else {
                            echo '<p class="recap-comp-detail subtle">Não há classificação detalhada disponível para esta temporada.</p>';
                        }
                    }
                    if (!empty($r['access_pending'])) {
                        echo '<p class="subtle">A divisão seguinte deste estado ainda não está disponível no protótipo.</p>';
                    }
                    if (!empty($r['continuity']['simulation_ceiling'])) {
                        echo '<p class="subtle">A divisão superior ainda não está disponível no jogo.</p>';
                    }
                    if (!empty($r['relegation_pending'])) {
                        echo '<p class="subtle">A terceira divisão ainda não está disponível; permanecer no clube repete a divisão cadastrada.</p>';
                    }
                    echo '</div>';
                }
            }
        } else {
            // Modo legado com liga regional
            $rawTeams = is_array($g['teams'] ?? null) ? array_values($g['teams']) : [];
            $rawMatches = is_array($g['matches'] ?? null) ? $g['matches'] : [];
            $validTeams = $rawTeams !== [] && count(array_filter($rawTeams, 'is_string')) === count($rawTeams);
            $teamCount = $validTeams ? count($rawTeams) : 0;
            $expectedMatchCount = ($teamCount >= 2 && ($teamCount % 2 === 0) && count(array_unique($rawTeams)) === $teamCount)
                ? $teamCount * ($teamCount - 1)
                : 0;
            $calendarComplete = false;
            if ($expectedMatchCount > 0 && $club !== '' && in_array($club, $rawTeams, true) && count($rawMatches) === $expectedMatchCount) {
                $teamSet = array_fill_keys($rawTeams, true);
                $completedPairs = [];
                $allValid = true;
                foreach ($rawMatches as $m) {
                    if (!is_array($m) || ($m['status'] ?? '') !== 'finalizada') {
                        $allValid = false;
                        break;
                    }
                    $home = $m['home'] ?? '';
                    $away = $m['away'] ?? '';
                    if (!is_string($home) || !is_string($away) || $home === '' || $away === '' || $home === $away || !isset($teamSet[$home], $teamSet[$away])) {
                        $allValid = false;
                        break;
                    }
                    $pairKey = $home . "\0" . $away;
                    if (isset($completedPairs[$pairKey])) {
                        $allValid = false;
                        break;
                    }
                    $completedPairs[$pairKey] = true;
                }
                $calendarComplete = $allValid && count($completedPairs) === $expectedMatchCount;
            }
            $table = $calendarComplete ? Schedule::table($rawMatches, $rawTeams) : [];
            $foundPos = null;
            $foundPts = null;
            foreach ($table as $i => $row) {
                if (($row['team'] ?? '') === $club) {
                    $foundPos = $i + 1;
                    $foundPts = (int)($row['points'] ?? 0);
                    break;
                }
            }
            echo '<div class="recap-comp-item">';
            echo '<div class="recap-comp-name">Liga regional</div>';
            if (!$calendarComplete || $foundPos === null) {
                echo '<p class="recap-comp-detail subtle">Não há classificação detalhada disponível para esta temporada.</p>';
            } else {
                $legacyChamp = $table[0]['team'] ?? '';
                if ($legacyChamp === $club) {
                    echo '<p class="recap-comp-detail recap-comp-champion">🏆 <b>Título conquistado!</b> Seu clube terminou na liderança com ' . $foundPts . ' pontos.</p>';
                } else {
                    if ($legacyChamp !== '') {
                        echo '<p class="recap-comp-detail">Campeão: <b>' . e(ClubBadge::name($legacyChamp)) . '</b></p>';
                    }
                    echo '<p class="recap-comp-detail">Terminou em <b>' . $foundPos . 'º lugar</b> com ' . $foundPts . ' pontos.</p>';
                }
            }
            echo '</div>';
        }
        echo '</section>';

        // 4. Conquistas e Títulos do ano
        echo '<section class="recap-section recap-trophies">';
        echo '<h2 class="recap-section-title">Títulos e conquistas da temporada</h2>';
        $allTrophies = class_exists('Trophies') ? Trophies::collect($g) : [];
        $seasonTrophies = array_values(array_filter($allTrophies, fn($t) => ($t['season'] ?? 0) === $season && ($t['club'] ?? '') === $club));
        if ($seasonTrophies) {
            echo '<div class="recap-trophies-list">';
            foreach ($seasonTrophies as $tr) {
                echo '<span class="recap-trophy-badge">🏆 ' . e($tr['title'] ?? 'Campeão') . '</span>';
            }
            echo '</div>';
        } else {
            echo '<p class="subtle">Nenhum título conquistado nesta temporada.</p>';
        }
        echo '</section>';

        // 5. Evolução técnica
        echo '<section class="recap-section recap-evolution">';
        echo '<h2 class="recap-section-title">Evolução do jogador</h2>';
        $skills = $g['skills'] ?? [];
        $currentTech = $skills ? array_sum(array_column($skills, 'value')) / count($skills) : 50.0;
        $baseline = $g['season_skill_baseline'] ?? null;
        if ($baseline !== null && is_numeric($baseline)) {
            $delta = round($currentTech - (float)$baseline, 1);
            if ($delta > 0) {
                $growthText = 'Média técnica: <b>' . number_format($currentTech, 1, ',', '.') . '</b> <span class="text-success">(+' . number_format($delta, 1, ',', '.') . ' na temporada)</span>';
            } elseif ($delta < 0) {
                $growthText = 'Média técnica: <b>' . number_format($currentTech, 1, ',', '.') . '</b> <span class="text-danger">(' . number_format($delta, 1, ',', '.') . ' na temporada)</span>';
            } else {
                $growthText = 'Média técnica: <b>' . number_format($currentTech, 1, ',', '.') . '</b> <span class="subtle">(estável na temporada)</span>';
            }
            echo '<p>' . $growthText . '</p>';
        } else {
            echo '<p>Média técnica: <b>' . number_format($currentTech, 1, ',', '.') . '</b></p>';
        }
        if (!empty($g['pace_report']['growth'])) {
            echo '<div class="result-effects">';
            foreach ($g['pace_report']['growth'] as $k => $v) {
                if ($v > 0) {
                    echo '<span class="effect-chip">' . e(self::label($k)) . ' +' . (int)$v . '</span> ';
                }
            }
            echo '</div>';
        }
        echo '</section>';

        // 6. Próximo passo / Janela de transferências ou Aposentadoria Compulsória
        $isRetiring = ($g['age'] ?? 0) >= 39;
        if ($isRetiring) {
            echo '<div class="recap-market-cta recap-retirement-cta">';
            echo '<span class="tag">DESPEDIDA DOS GRAMADOS · ÚLTIMA TEMPORADA</span>';
            echo '<h3>Encerramento da Carreira Profissional</h3>';
            echo '<p>Esta foi a sua última temporada como jogador de futebol profissional. Ao completar 40 anos, seu ciclo competitivo nos gramados chega ao fim.</p>';
            echo '<p class="recap-retirement-notice">Ao concluir sua carreira, o jogo guardará permanentemente suas estatísticas, clubes, temporadas e conquistas no histórico. Os detalhes das partidas, eventos individuais e decisões serão removidos e não poderão ser recuperados.</p>';
            echo '<p class="subtle recap-retirement-distinction">A conclusão da carreira preserva seu resumo permanente no histórico e libera sua vaga ativa — diferente da opção de excluir o resumo permanente, que apaga definitivamente todo o registro histórico.</p>';
            echo '<a class="btn btn-primary btn-lg btn-retire-career" href="/carreira/aposentar">Concluir minha carreira →</a>';
            echo '</div>';
        } else {
            echo '<div class="recap-market-cta">';
            echo '<h3>Janela de transferências</h3>';
            echo '<p>O ano de competições está concluído. Você pode optar por renovar com o <b>' . e($clubName) . '</b> ou conhecer as propostas de outros clubes para a próxima temporada.</p>';
            if (function_exists('form')) {
                form('/transferencia/roleta', $g);
            } else {
                echo '<form method="post" action="/transferencia/roleta">';
                echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
                echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
                echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
            }
            echo '<button class="btn btn-primary btn-lg" type="submit">Ver minha proposta →</button>';
            echo '</form>';
            echo '</div>';
        }

        echo '</article>';

        // 7. Detalhes de histórico e partidas complementares (recolhidos)
        echo '<details class="panel season-recap"><summary>Ver histórico de partidas e gols</summary>';
        if (!empty($g['pace_report'])) {
            PlayMode::report($g);
        }
        $storyText = (!empty($g['official']) || (!empty($calendarComplete) && ($foundPos ?? null) !== null))
            ? self::seasonStory($g)
            : 'A temporada terminou. Você entrou em campo em ' . $games . ' jogos, marcou ' . $goals . ' gols e deu ' . $assists . ' assistências.';
        echo '<p>' . e($storyText) . '</p>';
        echo '<p>Nota média: ' . e($ratingFormatted) . '</p>';
        echo '<a href="/classificacao">Classificação final e calendário →</a>';
        self::goalHistory($g);
        echo '</details>';
    }
}
