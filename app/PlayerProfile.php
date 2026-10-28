<?php
declare(strict_types=1);
final class PlayerProfile {
    private const LABELS=['velocidade'=>'Velocidade','resistencia'=>'Resistência','forca'=>'Força','visao_jogo'=>'Visão de jogo','passe'=>'Passe','finalizacao'=>'Finalização','drible'=>'Drible','desarme'=>'Desarme','posicionamento'=>'Posicionamento','energia'=>'Energia','ritmo_jogo'=>'Ritmo de jogo','confianca'=>'Confiança','entrosamento'=>'Entrosamento','confianca_treinador'=>'Treinador','confianca_diretoria'=>'Diretoria','apoio_torcida'=>'Torcida','confianca_imprensa'=>'Imprensa','lideranca'=>'Liderança','foco'=>'Foco','moral_pessoal'=>'Moral','risco_lesao'=>'Lesão','risco_cartao'=>'Cartões','risco_polemica'=>'Polêmica','risco_encontrar_rivais'=>'Conflito com rivais'];
    private static function meter(string $label,float $value,bool $risk=false): void {
        $level=$risk?100-$value:$value;$tone=$level<35?'low':($level<65?'medium':'high');
        echo '<div class="profile-meter tone-'.$tone.'"><div><span>'.e($label).'</span><b>'.e($value).'<small>/100</small></b></div><meter min="0" max="100" value="'.e($value).'" aria-label="'.e($label).'">'.e($value).'</meter></div>';
    }
    public static function render(array $g): void {
        if(isset($g['retired_summary'])){RetiredCareer::render($g['retired_summary']);return;}
        $summary=Game::summary($g);$overall=(int)round(array_sum(array_column($g['skills'],'value'))/count($g['skills']));
        $names=preg_split('/\s+/u',trim($g['name']));$initials=mb_substr($names[0],0,1).(count($names)>1?mb_substr(end($names),0,1):'');
        $position=['zagueiro'=>'ZAG','lateral'=>'LAT','volante'=>'VOL','meia'=>'MEI','ponta'=>'PON','centroavante'=>'CA'][$g['position']];
        echo '<section class="player-profile"><div class="profile-toolbar"><a href="/carreira">← Voltar ao jogo</a><a class="profile-trophies" href="/carreira/trofeus">Sala de Troféus ↗</a></div><div class="profile-page-title"><span class="eyebrow">MEU JOGADOR</span><h2>Sua história em campo</h2><p>Identidade, desempenho e evolução em um só lugar.</p></div><div class="profile-overview"><article class="athlete-card"><div class="athlete-rating"><strong>'.e($overall).'</strong><span>NÍVEL TÉCNICO</span><b>'.e($position).'</b></div><div class="athlete-avatar" aria-hidden="true"><img src="/assets/achievements/carreira.svg?v=2" alt=""></div><div class="athlete-identity"><p class="tag">TEMPORADA '.e($g['season']).'</p><h1>'.e($g['name']).'</h1><p>'.e(ucfirst($g['position'])).' · '.e($g['age']).' anos</p><div class="athlete-club">';
        if(empty($g['starting'])){ClubBadge::render($g['club']);echo '<b>'.e(ClubBadge::name($g['club'])).'</b>';}else echo '<b>À espera do primeiro contrato</b>';
        if($g['age']>=35 && $g['stage']!=='aposentado')echo '<a class="squad-link" href="/carreira/aposentar">Encerrar minha carreira →</a>';
        if(empty($g['starting']))echo '<a class="squad-link" href="/elenco">Ver companheiros de elenco ↗</a>';
        if($g['stage']!=='partida' && empty($g['starting']))echo '<a href="/jogador/localizacao">Atualizar residência ↗</a>';echo '</div><p class="athlete-origin">'.e(CurrentLocation::display($g)).'</p></div></article><section class="profile-condition"><div class="profile-section-heading"><h2>Condição para jogar</h2><span>CONDIÇÃO ATUAL</span></div>';
        foreach(['energia','confianca','ritmo_jogo','entrosamento'] as $key)self::meter(self::LABELS[$key],$g['states'][$key]);
        $energy=$g['states']['energia'];$description=$g['states']['risco_lesao']>85?'Seu risco de lesão está alto. Priorize a recuperação antes da próxima rodada.':($energy<35?'O desgaste está alto. Vale priorizar sua recuperação.':($energy<65?'Você tem disposição, mas precisa administrar o esforço.':'Você está com boa disposição para a próxima rodada.'));
        echo '<p class="condition-caption">'.e($description).'</p></section></div><section class="profile-performance"><div class="profile-section-heading"><h2>Sua temporada</h2><span>EM CAMPO</span></div><div class="profile-stat-grid">';
        foreach(['games'=>'Jogos','goals'=>'Gols','assists'=>'Assistências','average'=>'Nota média'] as $key=>$label)echo '<div><strong>'.e($key==='average' && !$summary['games']?'—':$summary[$key]).'</strong><span>'.e($label).'</span></div>';
        echo '</div></section>';CareerStats::render($g);
        echo '<section class="profile-abilities"><div class="profile-section-heading"><h2>Habilidades e evolução</h2><span>SEU POTENCIAL</span></div>';
        if(PlayerDevelopment::enabled($g)){
            $age=(int)($g['age']??16);
            $phase=$age<=21?'Juventude em aceleração (ganhos de XP potencializados)':($age<=27?'Auge do desenvolvimento (teto potencial em expansão até os 27 anos)':($age<=32?'Maturidade técnica (estabilidade e ápice competitivo)':($age<=36?'Transição e experiência (início do declínio físico gradual)':'Veterano (declínio físico e técnico; leitura de jogo preservada)')));
            $ledger=$g['player_development']['normalization_ledger'][$g['season']]??null;
            $rawXp=$ledger['raw_scaled_xp']??0;
            $appliedXp=$ledger['applied_xp']??0;
            echo '<div class="panel dev-phase-card" style="background:#131c20;border:1px solid #203037;border-radius:10px;padding:1rem;margin-bottom:1.25rem;"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;"><span style="color:#10b981;font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Fase da Carreira: '.e($phase).'</span><span class="badge" style="background:#1e293b;color:#f59e0b;padding:0.35rem 0.65rem;border-radius:999px;font-size:0.75rem;">Temporada '.e($g['season']).'</span></div>';
            if($rawXp>0) echo '<p style="color:#9ca3af;font-size:0.875rem;margin:0.5rem 0 0 0;">Nesta temporada: <strong>'.e($appliedXp).' XP</strong> aplicados em evolução (baseado em <strong>'.e($rawXp).' XP</strong> bruto de treinos e partidas).</p>';
            echo '</div>';
        }
        echo '<div class="ability-groups">';
        foreach(['Técnica'=>['passe','finalizacao','drible','desarme'],'Físico'=>['velocidade','resistencia','forca'],'Leitura de jogo'=>['visao_jogo','posicionamento']] as $title=>$keys){
            $group=(int)round(array_sum(array_map(fn($k)=>$g['skills'][$k]['value'],$keys))/count($keys));
            echo '<details class="ability-group" open><summary><span>'.e($title).'</span><b>'.e($group).'</b></summary><div class="ability-body">';
            foreach($keys as $key){
                $s=$g['skills'][$key];
                self::meter(self::LABELS[$key],$s['value']);
                $threshold=PlayerDevelopment::enabled($g)?PlayerDevelopment::cost($g,$s['value']):Rules::xpThreshold($s['value']);
                $pct=round((min($threshold,$s['xp'])/max(1,$threshold))*100);
                echo '<div class="skill-xp"><span>XP '.e($s['xp']).' / '.e($threshold).' ('.e($pct).'%)</span><span>'.($s['value']>=100?'Nível máximo':(PlayerDevelopment::enabled($g)&&!PlayerDevelopment::eligible($g,$key)?'Evolução limitada nesta fase':'Próximo nível')).'</span></div><progress max="'.e($threshold).'" value="'.e(min($threshold,$s['xp'])).'" aria-label="XP de '.e(self::LABELS[$key]).'"></progress>';
            }
            echo '</div></details>';
        }
        if(isset($g['player_development']['parameters']['normalization']))echo '<p class="profile-footnote">O motor de evolução v3 utiliza normalização e rendimentos decrescentes: cada partida e treino rende XP bruto, convertido em atributos de acordo com seu potencial individual e teto etário.</p>';
        echo '</div><p class="profile-footnote">O nível técnico é a média das nove habilidades. Treinos e decisões acumulam XP para evoluir cada uma.</p></section><details class="profile-secondary"><summary>Vestiário, reputação e riscos</summary><div class="profile-secondary-grid">';
        foreach(['Vestiário'=>['confianca_treinador','confianca_diretoria','lideranca','foco','moral_pessoal'],'Reputação'=>['apoio_torcida','confianca_imprensa'],'Riscos'=>['risco_lesao','risco_cartao','risco_polemica','risco_encontrar_rivais']] as $title=>$keys){echo '<section><h3>'.e($title).'</h3>';foreach($keys as $key)self::meter(self::LABELS[$key],$g['states'][$key],str_starts_with($key,'risco_'));echo '</section>';}
        echo '</div><p class="profile-footnote">Nos riscos, quanto menor o número, melhor.</p></details><div class="profile-bottom"><span>Perfil inicial · '.e($g['handicap']??'a sortear').'</span><a href="/carreira">Continuar minha história →</a></div></section>';
    }
}
