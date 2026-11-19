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
        $compId = $g['competition_id'] ?? '';
        $t = $g['competitions'][$compId] ?? null;
        if (!$t) return;
        $t['matches'] = $g['matches'] ?? ($t['matches'] ?? []);
        echo '<h2>Classificação · '.e(Competition::label($g)).'</h2><p class="subtle">'.e(Competition::phaseLabel($g)).' · resultados da sua carreira</p>';
        if($t['ties']) {
            foreach($t['ties'] as $i=>$tie) {
                echo '<article class="panel"><h3>'.e($tie['a'].' × '.$tie['b']).'</h3>';
                foreach($t['matches'] as $m)if($m['phase']===$t['phase'] && ($m['tie']??null)===$i) {
                    echo '<p>'.e(ClubBadge::name($m['home'])).' <b>'.($m['status']==='finalizada'?e($m['hg'].' × '.$m['ag']):'×').'</b> '.e(ClubBadge::name($m['away'])).'</p>';
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
        if($r['promoted'])$s.='Acesso conquistado.'.(!empty($r['access_pending'])?' A divisão seguinte deste estado ainda não está disponível no protótipo.':'');
        elseif($r['relegated'])$s.=!empty($r['relegation_pending'])?'Seu clube terminou na zona de rebaixamento. A terceira divisão ainda não está disponível; permanecer no clube repete a divisão cadastrada.':'Seu clube foi rebaixado.';
        else $s.='Seu clube terminou em '.$r['position'].'º '.(!empty($r['group'])?'no grupo '.$r['group']:'na primeira fase').'.';
        return $s;
    }
    public static function recap(array $g): void {
        $compId = $g['competition_id'] ?? '';
        $r = $g['competition_results'][$compId] ?? null;
        $compName = (is_array($r) && isset($r['name'])) ? $r['name'] : (Competition::catalog()[$compId]['name'] ?? Competition::label($g));
        $userClub = $g['club'] ?? '';
        $hasResult = is_array($r);

        $champion = $hasResult ? ($r['champion'] ?? '') : '';
        $isChampion = ($hasResult && $champion !== '' && $champion === $userClub);
        $isPromoted = $hasResult && !empty($r['promoted']);
        $isRelegated = $hasResult && !empty($r['relegated']);
        $position = ($hasResult && isset($r['position']) && is_numeric($r['position'])) ? (int)$r['position'] : null;
        $group = ($hasResult && !empty($r['group']) && is_numeric($r['group'])) ? (int)$r['group'] : null;

        $nextId = $g['season_competitions'][$g['competition_cursor'] + 1] ?? null;
        $nextName = $nextId ? (Competition::catalog()[$nextId]['name'] ?? $nextId) : null;

        echo '<article class="panel competition-recap-panel">';

        // 1. Tag de status e cabeçalho principal
        if ($isChampion) {
            echo '<p class="tag">🏆 TÍTULO CONQUISTADO · COMPETIÇÃO ENCERRADA</p>';
            echo '<h2>É campeão! ' . e(ClubBadge::name($userClub)) . ' conquista o título!</h2>';
        } else {
            echo '<p class="tag">COMPETIÇÃO ENCERRADA</p>';
            echo '<h2>O campeonato terminou · ' . e($compName) . '</h2>';
        }

        // 2. Desfecho esportivo comprovado da competição
        echo '<div class="recap-outcome">';
        if (!$hasResult) {
            echo '<p class="recap-status-line subtle">O resultado detalhado desta competição não está disponível nos dados da carreira.</p>';
        } elseif ($isChampion) {
            echo '<p class="recap-highlight-lead">Parabéns! Seu clube levantou a taça de campeão de <b>' . e($compName) . '</b>.</p>';
        } else {
            if ($champion !== '') {
                echo '<p class="recap-champion-line">Campeão da edição: <b>' . e(ClubBadge::name($champion)) . '</b></p>';
            }
            if ($isPromoted) {
                echo '<p class="recap-status-line recap-status-promoted"><b>Acesso conquistado!</b> ' . e(ClubBadge::name($userClub)) . ' garantiu vaga na divisão superior.</p>';
            } elseif ($isRelegated) {
                echo '<p class="recap-status-line recap-status-relegated"><b>Rebaixamento:</b> ' . e(ClubBadge::name($userClub)) . ' terminou na zona de descenso.</p>';
            } elseif ($position !== null) {
                $posText = 'Seu clube terminou em <b>' . $position . 'º lugar</b>' . ($group !== null ? ' no Grupo ' . $group . '.' : ' na primeira fase.');
                echo '<p class="recap-status-line">' . $posText . '</p>';
            }
        }
        echo '</div>';

        // 3. Ressalvas regulamentares e notas existentes
        if ($hasResult) {
            if (!empty($r['access_pending'])) {
                echo '<p class="subtle">Se permanecer neste clube, a próxima temporada repetirá a divisão cadastrada. Você também poderá avaliar propostas de outros clubes.</p>';
            }
            if (!empty($r['continuity']['simulation_ceiling'])) {
                echo '<p class="subtle">Na próxima temporada, este clube continua no mesmo campeonato. A divisão superior ainda não está disponível no jogo.</p>';
            }
            if (!empty($r['relegation_pending'])) {
                echo '<p class="subtle">A terceira divisão ainda não está disponível; permanecer no clube repete a divisão cadastrada.</p>';
            }
        }
        $note = Competition::catalog()[$compId]['rules']['note'] ?? null;
        if ($note) {
            echo '<p class="subtle">' . e($note) . '</p>';
        }

        // 4. Próximo passo e ação do jogador
        echo '<div class="recap-next-step">';
        if ($nextName) {
            echo '<p class="recap-transition-text"><b>Próximo desafio:</b> Agora vem o <b>' . e($nextName) . '</b>. Seu clube continua contando com você na temporada.</p>';
            $buttonText = 'Começar próximo campeonato: ' . e($nextName) . ' →';
        } else {
            echo '<p class="recap-transition-text"><b>Calendário encerrado:</b> As competições deste ano foram concluídas. Veja o balanço da sua temporada e prepare seu próximo passo.</p>';
            $buttonText = 'Concluir temporada e ver retrospectiva anual →';
        }

        // 5. Formulário de continuidade (preserva rota, versão, csrf e chave de idempotência)
        echo '<form method="post" action="/competicao/continuar">';
        echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
        echo '<input type="hidden" name="version" value="' . e($g['version'] ?? 1) . '">';
        echo '<input type="hidden" name="key" value="' . bin2hex(random_bytes(32)) . '">';
        echo '<button class="btn btn-primary btn-lg" type="submit">' . $buttonText . '</button>';
        echo '</form>';
        echo '</div>';

        echo '</article>';

        // 6. Detalhes de campanha e classificação (recolhidos para não poluir o painel principal)
        echo '<details class="panel"><summary>Ver campanha e classificação detalhada</summary>';
        self::standings($g);
        Presentation::goalHistory($g);
        echo '</details>';

        // 7. Campeões de torneios internacionais/secundários, se houver
        if ($hasResult && !empty($r['titles'])) {
            echo '<details class="panel"><summary>Campeões dos torneios</summary>';
            foreach ($r['titles'] as $title => $championName) {
                echo '<p>' . e($title) . ': <b>' . e($championName) . '</b></p>';
            }
            echo '</details>';
        }
    }
}
