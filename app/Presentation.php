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
        $text=str_replace([': a intenção deu certo.',': a intenção não teve o efeito esperado.'],'.',$f['text']);
        echo '<aside class="action-result result-'.$tone.($highlight==='goal'?' result-goal':($highlight==='assist'?' result-assist':'')).'" data-result-id="'.e($g['id'].':'.$g['version']).'" aria-live="polite">';
        echo '<div class="result-icon" aria-hidden="true">'.($highlight==='goal'?'⚽':($rolled?($success?'✓':'×'):'★')).'</div><div class="result-copy"><small class="result-status">Resultado salvo</small><h2>'.e($title).'</h2><p>'.e($text).'</p>';
        echo '<div class="result-effects">';
        foreach($f['effects']??[] as $k=>$v) if($v!=0) echo '<span class="effect-chip">'.e(self::label($k)).' '.e($v>=0?'+'.$v:$v).'</span>';
        foreach($f['attribute_changes']??[] as $k=>$v)echo '<span class="effect-chip">'.e(self::label($k)).': '.e($v['before']).' → '.e($v['after']).'</span>';
        echo '</div></div></aside>';
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
    public static function score(array $g,array $m): void {
        echo '<article class="panel match compact-score"><div class="match-time">'.e(str_replace('_',' ',strtoupper($m['status']))).' · '.e($m['minute']).'′</div><div class="score-row"><span class="score-team">';ClubBadge::render($m['home']);echo '<span>'.e(ClubBadge::name($m['home'])).'</span></span><strong>'.e($m['hg']).' <small>×</small> '.e($m['ag']).'</strong><span class="score-team">';ClubBadge::render($m['away']);echo '<span>'.e(ClubBadge::name($m['away'])).'</span></span></div>';
        $agg = Competition::aggregateContext($g, $m);
        if ($agg) self::renderAggregate($agg);
        echo '<div class="live-player-stats"><span>⚽ <b data-stat="goals">'.e($m['stats']['goals']).'</b> seus gols</span><span>↗ <b data-stat="assists">'.e($m['stats']['assists']).'</b> assistências</span></div></article>';
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
