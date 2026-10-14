<?php
declare(strict_types=1);
final class CareerStats {
    /** Históricos concluídos + temporada atual exatamente uma vez. */
    public static function collect(array $g):array {
        if(isset($g['retired_summary'])){
            $data=$g['retired_summary']['statistics'];$keys=['games'=>0,'goals'=>0,'assists'=>0,'minutes'=>0];
            $data['totals']=array_replace($keys,$data['totals']);
            $data['clubs']=array_map(fn($r)=>['club'=>$r['club']]+array_replace($keys,array_intersect_key($r,$keys)),$data['clubs']);
            $data['seasons']=array_map(fn($r)=>['club'=>$r['club'],'season'=>$r['season'],'current'=>false]+array_replace($keys,array_intersect_key($r,$keys)),$data['seasons']);return $data;
        }
        $rows=[];$clubs=[];$total=['games'=>0,'goals'=>0,'assists'=>0,'minutes'=>0];
        foreach($g['history']??[] as $h)$rows[]=['club'=>$h['club'],'season'=>$h['season'],'current'=>false]+array_intersect_key($h,$total);
        if(empty($g['starting']))$rows[]=['club'=>$g['club'],'season'=>$g['season'],'current'=>$g['stage']!=='aposentado']+array_intersect_key(Game::summary($g),$total);
        foreach($rows as $row) {
            $club=$row['club'];$clubs[$club]??=['club'=>$club,'games'=>0,'goals'=>0,'assists'=>0,'minutes'=>0];
            foreach($total as $key=>$_){$value=(int)($row[$key]??0);$total[$key]+=$value;$clubs[$club][$key]+=$value;}
        }
        return ['totals'=>$total,'clubs'=>array_values($clubs),'seasons'=>$rows];
    }
    public static function render(array $g):void {
        $data=self::collect($g);echo '<section class="profile-performance career-statistics"><div class="profile-section-heading"><h2>Carreira inteira</h2><span>TODOS OS CLUBES</span></div><div class="profile-stat-grid">';
        foreach(['games'=>'Jogos','goals'=>'Gols','assists'=>'Assistências'] as $key=>$label)echo '<div><strong data-career-stat="'.e($key).'">'.e($data['totals'][$key]).'</strong><span>'.e($label).'</span></div>';
        echo '<div><strong>'.count($data['clubs']).'</strong><span>Clubes</span></div></div><details class="profile-career-history"><summary>Por clube e temporada</summary>';
        if(!$data['clubs'])echo '<p>Seu histórico começa com o primeiro clube.</p>';
        else {
            echo '<div class="table-responsive"><table class="table"><thead><tr><th>Clube</th><th><abbr title="Jogos em campo">J</abbr></th><th><abbr title="Gols">G</abbr></th><th><abbr title="Assistências">A</abbr></th></tr></thead><tbody>';
            foreach($data['clubs'] as $row){echo '<tr><td>';ClubBadge::render($row['club']);echo ' '.e(ClubBadge::name($row['club'])).'</td>';foreach(['games','goals','assists'] as $key)echo '<td>'.e($row[$key]).'</td>';echo '</tr>';}
            echo '</tbody></table></div><ol class="profile-season-list">';
            foreach(array_reverse($data['seasons']) as $row)echo '<li><b>Temporada '.e($row['season']).' · '.e(ClubBadge::name($row['club'])).'</b>'.($row['current']?' <small>em andamento</small>':'').'<span>'.e($row['games']??0).' jogos · '.e($row['goals']??0).' gols · '.e($row['assists']??0).' assistências</span></li>';
            echo '</ol><p class="profile-footnote">Totais das temporadas salvas e da temporada atual. Carreiras arquivadas são personagens separados.</p>';
        }
        echo '</details></section>';
    }
}
