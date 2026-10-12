<?php
declare(strict_types=1);
final class Presentation {
    public static function feedback(array $g): void {
        $f=$g['feedback']; if(!$f) return;
        $rolled=isset($f['success']); $success=$rolled && $f['success'];
        $tone=$rolled?($success?'success':'failure'):'neutral';
        $title=$rolled?($success?($f['class']==='excepcional'?'Jogada excepcional!':'Deu certo!'):($f['class']==='falha_grave'?'Não foi desta vez':'Não deu certo')):'Novo capítulo';
        echo '<aside class="action-result result-'.$tone.'" data-result-id="'.e($g['id'].':'.$g['version']).'" aria-live="polite">';
        echo '<div class="result-icon" aria-hidden="true">'.($rolled?($success?'✓':'×'):'★').'</div><div class="result-copy"><small class="result-status">Resultado salvo</small><h2>'.e($title).'</h2><p>'.e($f['text']).'</p>';
        echo '<button type="button" class="skip-result" hidden>Pular animação</button><details class="result-details"><summary>Ver efeitos</summary>';
        foreach($f['effects']??[] as $k=>$v) echo '<span class="effect-chip">'.e(str_replace('_',' ',$k)).' '.e($v>=0?'+'.$v:$v).'</span>';
        echo '</details></div></aside>';
    }
    public static function matchStats(array $g,array $m): void {
        $s=$m['stats'];
        echo '<section class="player-match-stats panel" aria-label="Seu desempenho nesta partida"><p class="tag">SEU DESEMPENHO NESTA PARTIDA</p><div class="stat-strip">';
        foreach(['goals'=>'Gols','assists'=>'Assistências','shots'=>'Finalizações','completed'=>'Passes certos'] as $k=>$label) echo '<div><strong data-stat="'.$k.'">'.e($s[$k]).'</strong><span>'.e($label).'</span></div>';
        echo '</div><p class="subtle">'.e($g['name']).' · '.($m['status']==='finalizada'?'Nota '.e($s['rating']).' · '.e($s['minutes']).' minutos jogados':'Atualizado a cada lance').'</p></section>';
    }
    public static function goalHistory(array $g): void {
        $rows=[];
        foreach($g['matches'] as $m) if($m['status']==='finalizada' && in_array($g['club'],[$m['home'],$m['away']],true)) $rows[]=$m;
        usort($rows,fn($a,$b)=>$b['round']<=>$a['round']);
        $summary=Game::summary($g);
        echo '<section class="panel goal-history"><p class="tag">TEMPORADA '.e($g['season']).'</p><h2>Seus gols no campeonato</h2><div class="stat-strip"><div><strong data-stat="season-goals">'.e($summary['goals']).'</strong><span>Gols marcados</span></div><div><strong>'.e($summary['assists']).'</strong><span>Assistências</span></div><div><strong>'.e($summary['games']).'</strong><span>Jogos em campo</span></div></div>';
        if(!$rows) echo '<p class="subtle">Seu histórico aparecerá após a primeira partida.</p>';
        else {
            echo '<details'.($g['stage']==='fim_semana'?' open':'').'><summary>Gols por rodada</summary><ol class="goal-list">';
            foreach($rows as $m) {
                $opponent=$m['home']===$g['club']?$m['away']:$m['home']; $goals=$m['stats']['goals'];
                echo '<li><div><small>RODADA '.e($m['round']).' · '.e($m['hg'].' × '.$m['ag']).'</small><span>'.e($opponent).'</span></div><b class="'.($goals?'has-goals':'').'">'.e($goals).' '.($goals===1?'gol':'gols').'</b></li>';
            }
            echo '</ol></details>';
        }
        echo '</section>';
    }
}
