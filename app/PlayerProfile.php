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
        echo '<section class="player-profile"><div class="profile-toolbar"><a href="/carreira">← Voltar ao jogo</a><span>MEU JOGADOR</span></div><div class="profile-overview"><article class="athlete-card"><div class="athlete-rating"><strong>'.e($overall).'</strong><span>NÍVEL TÉCNICO</span><b>'.e($position).'</b></div><div class="athlete-avatar" aria-hidden="true"><svg viewBox="0 0 180 200"><path d="M48 12 72 25H108L132 12 176 50 153 87 136 76V186H44V76L27 87 4 50Z"/></svg><span>'.e(mb_strtoupper($initials)).'</span></div><div class="athlete-identity"><p class="tag">TEMPORADA '.e($g['season']).'</p><h1>'.e($g['name']).'</h1><p>'.e(ucfirst($g['position'])).' · '.e($g['age']).' anos</p><div class="athlete-club">';
        if(empty($g['starting'])){ClubBadge::render($g['club']);echo '<b>'.e($g['club']).'</b>';}else echo '<b>À espera do primeiro contrato</b>';
        echo '<p class="subtle">Auge potencial aos 32 anos · aposentadoria voluntária aos 35 · encerramento aos 40.</p>';
        if($g['age']>=35 && $g['stage']!=='aposentado')echo '<a class="squad-link" href="/carreira/aposentar">Encerrar minha carreira →</a>';
        if(empty($g['starting']))echo '<a class="squad-link" href="/elenco">Ver companheiros de elenco ↗</a>';
        echo '</div><p class="athlete-origin">'.e($g['city'].' · '.$g['uf']).'</p></div></article><section class="profile-condition"><div class="profile-section-heading"><h2>Pronto para jogar?</h2><span>CONDIÇÃO ATUAL</span></div>';
        foreach(['energia','confianca','ritmo_jogo','entrosamento'] as $key)self::meter(self::LABELS[$key],$g['states'][$key]);
        $energy=$g['states']['energia'];$description=$g['states']['risco_lesao']>85?'Seu risco de lesão está alto. Priorize a recuperação antes da próxima rodada.':($energy<35?'O desgaste está alto. Vale priorizar sua recuperação.':($energy<65?'Você tem disposição, mas precisa administrar o esforço.':'Você está com boa disposição para a próxima rodada.'));
        echo '<p class="condition-caption">'.e($description).'</p></section></div><section class="profile-performance"><div class="profile-section-heading"><h2>Sua temporada</h2><span>EM CAMPO</span></div><div class="profile-stat-grid">';
        foreach(['games'=>'Jogos','goals'=>'Gols','assists'=>'Assistências','average'=>'Nota média'] as $key=>$label)echo '<div><strong>'.e($key==='average' && !$summary['games']?'—':$summary[$key]).'</strong><span>'.e($label).'</span></div>';
        echo '</div></section>';CareerStats::render($g);echo '<section class="profile-abilities"><div class="profile-section-heading"><h2>Habilidades e evolução</h2><span>SEU POTENCIAL</span></div><div class="ability-groups">';
        foreach(['Técnica'=>['passe','finalizacao','drible','desarme'],'Físico'=>['velocidade','resistencia','forca'],'Leitura de jogo'=>['visao_jogo','posicionamento']] as $title=>$keys){
            $group=(int)round(array_sum(array_map(fn($k)=>$g['skills'][$k]['value'],$keys))/count($keys));
            echo '<details class="ability-group"><summary><span>'.e($title).'</span><b>'.e($group).'</b></summary><div class="ability-body">';
            foreach($keys as $key){$s=$g['skills'][$key];self::meter(self::LABELS[$key],$s['value']);$threshold=Rules::xpThreshold($s['value']);echo '<div class="skill-xp"><span>XP '.e($s['xp']).' / '.e($threshold).'</span><span>'.($s['value']>=100?'Nível máximo':'Próximo nível').'</span></div><progress max="'.e($threshold).'" value="'.e(min($threshold,$s['xp'])).'" aria-label="XP de '.e(self::LABELS[$key]).'"></progress>';}
            echo '</div></details>';
        }
        echo '</div><p class="profile-footnote">O nível técnico é a média das nove habilidades. Treinos e decisões acumulam XP para evoluir cada uma.</p></section><details class="profile-secondary"><summary>Vestiário, reputação e riscos</summary><div class="profile-secondary-grid">';
        foreach(['Vestiário'=>['confianca_treinador','confianca_diretoria','lideranca','foco','moral_pessoal'],'Reputação'=>['apoio_torcida','confianca_imprensa'],'Riscos'=>['risco_lesao','risco_cartao','risco_polemica','risco_encontrar_rivais']] as $title=>$keys){echo '<section><h3>'.e($title).'</h3>';foreach($keys as $key)self::meter(self::LABELS[$key],$g['states'][$key],str_starts_with($key,'risco_'));echo '</section>';}
        echo '</div><p class="profile-footnote">Nos riscos, quanto menor o número, melhor.</p></details><div class="profile-bottom"><span>Perfil inicial · '.e($g['handicap']??'a sortear').'</span><a href="/carreira">Continuar minha história →</a></div></section>';
    }
}
