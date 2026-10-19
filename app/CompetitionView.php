<?php
declare(strict_types=1);
final class CompetitionView {
    public static function creationClubs(): void {
        $seen=[];$catalog=Competition::catalog();
        foreach(['sp-segunda','sp-a4','sp-a3','sp-a2','sp-a1','br-d','br-c','br-b','br-a'] as $id) {
            $names=array_values(array_diff($catalog[$id]['teams'],$seen));if(!$names)continue;
            echo '<optgroup label="'.e($catalog[$id]['name']).'">';
            foreach($names as $club)echo '<option value="'.e($club).'"'.($club==='São Caetano-SP'?' selected':'').'>'.e($club).'</option>';
            echo '</optgroup>';$seen=array_merge($seen,$names);
        }
    }
    public static function table(array $rows,string $club): void {
        echo '<div class="table-responsive"><table class="table"><thead><tr><th>Clube</th><th>PTS</th><th>J</th><th>V</th><th>SG</th></tr></thead><tbody>';
        foreach($rows as $i=>$row) {
            echo '<tr><td>';ClubBadge::render($row['team']);echo ' '.e(($i+1).'. '.ClubBadge::name($row['team'])).($row['team']===$club?' ★':'').'</td>';
            foreach(['points','played','wins','gd'] as $key)echo '<td>'.e($row[$key]).'</td>';
            echo '</tr>';
        }echo '</tbody></table></div>';
    }
    public static function standings(array $g): void {
        $t=$g['competitions'][$g['competition_id']];$t['matches']=$g['matches'];
        echo '<h2>Classificação · '.e(Competition::label($g)).'</h2><p class="subtle">'.e(Competition::phaseLabel($g)).' · resultados da sua carreira</p>';
        if($t['format']==='configured_state'){
            echo '<p class="subtle">Datas simuladas. As vagas nacionais e a continuidade estadual ainda dependem da validação das próximas competições.</p>';
            if((Competition::catalog()[$t['id']]['uf']??null)==='RO')echo '<p class="subtle">Neste piloto, as posições de campeão e vice podem fundamentar vagas nacionais. Um repasse além dos finalistas permanece pendente de confirmação.</p>';
            if(array_filter($t['matches'],fn($m)=>isset($m['simultaneous_group'])))echo '<p class="subtle">Rodada decisiva na mesma data e horário simulados. Resultados são processados em lotes; classificação e méritos só são homologados após todos os jogos. Uma tabela durante o lote é parcial.</p>';
            $qualificationNote=Competition::catalog()[$t['id']]['rules']['qualification_note']??null;
            if($qualificationNote&&!in_array($t['adapter_version']??null,[ConfiguredStateCompetition::INTERGROUP_VERSION,ConfiguredStateCompetition::CONDITIONAL_VERSION],true))echo '<p class="subtle">'.e($qualificationNote).'</p>';
        }
        if(($t['adapter_version']??null)===ConfiguredStateCompetition::CONDITIONAL_VERSION){
            echo '<p class="subtle">Goiano: os oito melhores da classificação geral avançam, independentemente do grupo. A permanência considera a campanha acumulada e pode ter zero, uma ou duas partidas.</p>';
            if($t['state_stage']===0){echo '<h3>Classificação geral · primeira fase</h3>';self::table(ConfiguredStateCompetition::standings($t,$t['teams'],'liga'),$g['club']);}
            if(isset($t['permanence'])){echo '<h3>Permanência</h3><p>'.e(match($t['permanence']['reason']){'initial_gap_above_six'=>'Sem playoff: diferença inicial superior a seis pontos.','return_omitted_gap_above_three'=>'Volta dispensada: diferença acumulada superior a três pontos.','return_required'=>'Volta necessária; decisão pela campanha acumulada.','two_matches_campaign_tiebreakers'=>'Duas partidas realizadas; decisão pela campanha acumulada.',default=>'Somente a ida está confirmada; a volta depende do resultado.'}).'</p>';foreach($t['matches'] as $m)if($m['phase']==='permanencia')echo '<p>'.e(ClubBadge::name($m['home'])).' '.($m['status']==='finalizada'?e($m['hg'].' × '.$m['ag']):'×').' '.e(ClubBadge::name($m['away'])).'</p>';}
        }
        if(in_array($t['adapter_version']??null,[ConfiguredStateCompetition::INTERGROUP_VERSION,ConfiguredStateCompetition::CONDITIONAL_VERSION],true)&&$t['state_stage']>0){
            if($t['done']){echo '<h3>Classificação final</h3><ol>';foreach($t['final_ranking'] as $club)echo '<li>'.e(ClubBadge::name($club)).(in_array($club,$t['relegated'],true)?' · mérito de descenso, edição seguinte pendente':'').'</li>';echo '</ol>';}
            else{$rules=Competition::catalog()[$t['id']]['state_engine'];foreach($rules['barriers'][$t['state_stage']-1] as $branch){echo '<h3>'.e($branch['name']==='descenso'?'Permanência / descenso':ucfirst($branch['name'])).'</h3>';foreach($t['state_phase_ties'][$branch['name']] as $i=>$tie){echo '<article class="panel"><h4>'.e(ClubBadge::name($tie['a']).' × '.ClubBadge::name($tie['b'])).'</h4>';foreach($t['matches'] as $m)if($m['phase']===$branch['name']&&($m['tie']??null)===$i){echo '<p>'.e(ClubBadge::name($m['home'])).' <b>'.($m['status']==='finalizada'?e($m['hg'].' × '.$m['ag']):'×').'</b> '.e(ClubBadge::name($m['away'])).'</p>';if(isset($m['penalties']))echo '<p>Nos pênaltis: '.e(implode(' × ',$m['penalties'])).'</p>';}if($tie['winner'])echo '<p>Vencedor do confronto: '.e(ClubBadge::name($tie['winner'])).'</p>';echo '</article>';}}}
        } elseif($t['ties']) {
            foreach($t['ties'] as $i=>$tie) {
                echo '<article class="panel"><h3>'.e($tie['a'].' × '.$tie['b']).'</h3>';
                foreach($t['matches'] as $m)if($m['phase']===$t['phase'] && ($m['tie']??null)===$i) {
                    echo '<p>'.e(ClubBadge::name($m['home'])).' <b>'.($m['status']==='finalizada'?e($m['hg'].' × '.$m['ag']):'×').'</b> '.e(ClubBadge::name($m['away'])).'</p>';
                    if(!empty($m['neutral']))echo '<p class="subtle">Campo neutro · '.e($m['venue']??'Local designado').'</p>';
                    if(isset($m['penalties']))echo '<p>Nos pênaltis: '.e(implode(' × ',$m['penalties'])).'</p>';
                }
                if($tie['winner'])echo '<p>Classificado: <b>'.e($tie['winner']).'</b></p>';
                echo '</article>';
            }
        } else {
            foreach($t['groups'] as $i=>$group) {
                $group=array_values(array_diff($group,['__folga__']));$own=in_array($g['club'],$group,true);
                if(!$own)echo '<details class="panel"><summary>Grupo '.e($i+1).'</summary>';
                elseif(count($t['groups'])>1)echo '<h3>Seu grupo · '.e($i+1).'</h3>';
                $tableState=$t;
                if($t['format']==='amazonense'&&$t['phase']==='liga')$tableState['matches']=array_values(array_filter($t['matches'],fn($m)=>($m['am_turn']??0)===$t['am_turn']));
                self::table(Competition::standings($tableState,$group,$t['phase']),$g['club']);
                if(!$own)echo '</details>';
            }
        }
        if($t['done'])echo '<p class="panel">Campeão: <b>'.e($t['champion']).'</b></p>';
        echo '<details class="panel"><summary>Calendário e resultados do meu clube</summary>';
        foreach($t['matches'] as $m)if(in_array($g['club'],[$m['home'],$m['away']],true))echo '<p><small>'.e($m['phase'].' · '.$m['round']).'</small><br>'.e(ClubBadge::name($m['home'])).' <b>'.($m['status']==='finalizada'?e($m['hg'].' × '.$m['ag']):'×').'</b> '.e(ClubBadge::name($m['away'])).'</p>';
        echo '</details>';
        foreach($g['competition_results'] as $result)echo '<p>'.e(self::resultText($result,$g['club'])).'</p>';
    }
    public static function resultText(array $r,string $club): string {
        $s=$r['name'].': ';
        if($r['champion']===$club)$s.='seu clube conquistou o título! ';
        else $s.='campeão '.$r['champion'].'. ';
        if(($r['sports_format']??null)==='copa_brasil')return $s.($r['champion']===$club?'':(($r['phase_reached']??0)===9?'Seu clube chegou à final.':'Seu clube foi eliminado na '.(int)$r['phase_reached'].'ª fase.'));
        if(($r['continuity']['reason']??null)==='state_future_memberships_and_mandatory_calendar_pending'){
            $s.='Classificação final simulada: '.$r['position'].'º lugar. ';
            if(!empty($r['relegation_pending']))$s.='O clube terminou em posição de descenso; a próxima edição estadual ainda está pendente. ';
            if(!empty($r['access_pending']))$s.='O mérito de acesso aguarda uma edição seguinte validada. ';
            return $s;
        }
        if($r['promoted'])$s.='Acesso conquistado.'.(!empty($r['access_pending'])?' A divisão seguinte deste estado ainda não está disponível no protótipo.':'');
        elseif($r['relegated'])$s.=!empty($r['relegation_pending'])?'Seu clube terminou na zona de rebaixamento. A terceira divisão ainda não está disponível; permanecer no clube repete a divisão cadastrada.':'Seu clube foi rebaixado.';
        else $s.='Seu clube terminou em '.$r['position'].'º '.(!empty($r['group'])?'no grupo '.$r['group']:'na primeira fase').'.';
        return $s;
    }
    public static function recap(array $g): void {
        $r=$g['competition_results'][$g['competition_id']];
        echo '<article class="panel"><p class="tag">COMPETIÇÃO ENCERRADA</p><h2>'.e($r['champion']===$g['club']?'É campeão!':'O campeonato terminou').'</h2><p>'.e(self::resultText($r,$g['club'])).'</p>';
        if(!empty($r['access_pending']))echo '<p class="subtle">Se permanecer neste clube, a próxima temporada repetirá a divisão cadastrada. Você também poderá avaliar propostas de outros clubes.</p>';
        if(!empty($r['continuity']['simulation_ceiling']))echo '<p class="subtle">Na próxima temporada, este clube continua no mesmo campeonato. A divisão superior ainda não está disponível no jogo.</p>';
        $note=Competition::catalog()[$g['competition_id']]['rules']['note']??null;
        if($note)echo '<p class="subtle">'.e($note).'</p>';
        $next=$g['season_competitions'][$g['competition_cursor']+1]??null;
        if($next)echo '<p>Agora vem o '.e(Competition::catalog()[$next]['name']).'. Seu clube continua contando com você.</p>';
        echo '<form method="post" action="/competicao/continuar"><input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'"><input type="hidden" name="version" value="'.e($g['version']).'"><input type="hidden" name="key" value="'.bin2hex(random_bytes(32)).'"><button class="btn btn-primary btn-lg">'.($next?'Começar próximo campeonato →':'Continuar minha carreira →').'</button></form></article><details class="panel"><summary>Ver campanha</summary>';
        self::standings($g);Presentation::goalHistory($g);echo '</details>';
        if(!empty($r['titles'])){echo '<details class="panel"><summary>Campeões dos torneios</summary>';foreach($r['titles'] as $title=>$champion)echo '<p>'.e($title).': <b>'.e($champion).'</b></p>';echo '</details>';}
    }
}
