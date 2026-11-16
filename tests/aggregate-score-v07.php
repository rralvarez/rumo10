<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

function ok(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("FALHA: $msg");
}

function fixtureMatch(int $round, string $home, string $away, array $overrides = []): array {
    return array_merge(Schedule::match($round, $home, $away), $overrides);
}

echo "Iniciando bateria de testes do placar agregado (v0.7)...\n";

$checks = 0;

// =========================================================================
// 1. Jogo de Ida (1ª partida): não inventa agregado nem resultado anterior
// =========================================================================
$t1 = [
    'id' => 'sp-a1',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        fixtureMatch(15, 'santos', 'sao-paulo', ['phase' => 'quartas', 'round' => 15, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 60, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'sao-paulo', 'santos', ['phase' => 'quartas', 'round' => 16, 'tie' => 0, 'status' => 'agendada', 'hg' => 0, 'ag' => 0]),
    ]
];

$mLeg1 = $t1['matches'][0];
$agg1 = Competition::tieContext($t1, $mLeg1);
ok($agg1 === null, 'Jogo de ida não deve retornar contexto de agregado');
$checks++;

// =========================================================================
// 2. Jogo de Volta antes do início (pre_jogo): Ida finalizada 1x2
// =========================================================================
$t2 = [
    'id' => 'sp-a1',
    'phase' => 'quartas',
    'ties' => [
        ['a' => 'sao-paulo', 'b' => 'santos', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        fixtureMatch(15, 'santos', 'sao-paulo', ['phase' => 'quartas', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 2]),
        fixtureMatch(16, 'sao-paulo', 'santos', ['phase' => 'quartas', 'round' => 16, 'tie' => 0, 'status' => 'agendada', 'minute' => 0, 'hg' => 0, 'ag' => 0]),
    ]
];

$mLeg2Pre = $t2['matches'][1];
$aggPre = Competition::tieContext($t2, $mLeg2Pre);
ok($aggPre !== null, 'Jogo de volta com ida finalizada deve retornar contexto de agregado');
ok($aggPre['is_second_leg'] === true, 'Identificado como segundo jogo');
ok($aggPre['home_id'] === 'sao-paulo' && $aggPre['away_id'] === 'santos', 'IDs reais dos clubes preservados');
ok($aggPre['leg1']['home'] === 'santos' && $aggPre['leg1']['away'] === 'sao-paulo', 'Mandante da ida preservado');
ok($aggPre['leg1']['hg'] === 1 && $aggPre['leg1']['ag'] === 2, 'Gols da ida corretos (1 x 2)');
ok($aggPre['home_leg1_goals'] === 2, 'São Paulo marcou 2 na ida');
ok($aggPre['away_leg1_goals'] === 1, 'Santos marcou 1 na ida');
ok($aggPre['home_aggregate'] === 2 && $aggPre['away_aggregate'] === 1, 'Agregado inicial é 2 x 1');
ok($aggPre['is_tied'] === false, 'Agregado inicial não está empatado');
ok($aggPre['qualifying_id'] === 'sao-paulo', 'São Paulo está se classificando antes de começar');
ok($aggPre['rule'] === 'penalties', 'Regra da competição é pênaltis');
$checks += 11;

// =========================================================================
// 3. Jogo de Volta em andamento: Atualização em tempo real lance a lance
// =========================================================================
// 3.1: Santos abre o placar (0 x 1) -> Agregado empata em 2 x 2
$mLeg2Live1 = $t2['matches'][1];
$mLeg2Live1['status'] = 'em_andamento';
$mLeg2Live1['minute'] = 35;
$mLeg2Live1['hg'] = 0;
$mLeg2Live1['ag'] = 1;

$aggLive1 = Competition::tieContext($t2, $mLeg2Live1);
ok($aggLive1['home_aggregate'] === 2, 'São Paulo tem 2 no agregado (2 na ida + 0 na volta)');
ok($aggLive1['away_aggregate'] === 2, 'Santos tem 2 no agregado (1 na ida + 1 na volta)');
ok($aggLive1['is_tied'] === true, 'Empate no agregado detectado em tempo real');
ok($aggLive1['qualifying_id'] === null, 'Sob regra de pênaltis, empate não dá vaga antecipada');
$checks += 4;

// 3.2: Santos amplia para 0 x 2 -> Santos vira o agregado para 2 x 3
$mLeg2Live2 = $mLeg2Live1;
$mLeg2Live2['minute'] = 65;
$mLeg2Live2['ag'] = 2;

$aggLive2 = Competition::tieContext($t2, $mLeg2Live2);
ok($aggLive2['home_aggregate'] === 2, 'São Paulo 2 no agregado');
ok($aggLive2['away_aggregate'] === 3, 'Santos 3 no agregado');
ok($aggLive2['is_tied'] === false, 'Não está empatado');
ok($aggLive2['qualifying_id'] === 'santos', 'Santos virou e está se classificando no momento');
$checks += 4;

// 3.3: São Paulo desconta para 1 x 2 -> Agregado empata em 3 x 3
$mLeg2Live3 = $mLeg2Live2;
$mLeg2Live3['minute'] = 75;
$mLeg2Live3['hg'] = 1;

$aggLive3 = Competition::tieContext($t2, $mLeg2Live3);
ok($aggLive3['home_aggregate'] === 3, 'São Paulo 3 no agregado (2 + 1)');
ok($aggLive3['away_aggregate'] === 3, 'Santos 3 no agregado (1 + 2)');
ok($aggLive3['is_tied'] === true, 'Re-empate no agregado');
$checks += 3;

// 3.4: São Paulo empata o jogo em 2 x 2 -> São Paulo retoma a liderança do agregado (4 x 3)
$mLeg2Live4 = $mLeg2Live3;
$mLeg2Live4['minute'] = 88;
$mLeg2Live4['hg'] = 2;

$aggLive4 = Competition::tieContext($t2, $mLeg2Live4);
ok($aggLive4['home_aggregate'] === 4, 'São Paulo 4 no agregado (2 + 2)');
ok($aggLive4['away_aggregate'] === 3, 'Santos 3 no agregado (1 + 2)');
ok($aggLive4['is_tied'] === false, 'Desempatou');
ok($aggLive4['qualifying_id'] === 'sao-paulo', 'São Paulo volta a estar se classificando');
$checks += 4;

// =========================================================================
// 4. Critério de Vantagem do Empate (Advantage rule)
// =========================================================================
$tAdv = [
    'id' => 'sp-a2',
    'phase' => 'semifinal',
    'ties' => [
        ['a' => 'ponte-preta', 'b' => 'xv-piracicaba', 'winner' => null, 'rule' => 'advantage']
    ],
    'matches' => [
        fixtureMatch(15, 'xv-piracicaba', 'ponte-preta', ['phase' => 'semifinal', 'round' => 15, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 1, 'ag' => 0]),
        fixtureMatch(16, 'ponte-preta', 'xv-piracicaba', ['phase' => 'semifinal', 'round' => 16, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 80, 'hg' => 1, 'ag' => 0]),
    ]
];

$mAdv = $tAdv['matches'][1];
$aggAdv = Competition::tieContext($tAdv, $mAdv);
ok($aggAdv['is_tied'] === true, 'Agregado empatado em 1 x 1');
ok($aggAdv['rule'] === 'advantage', 'Regra é advantage');
ok($aggAdv['advantage_id'] === 'ponte-preta', 'Ponte Preta possui vantagem do empate');
ok($aggAdv['qualifying_id'] === 'ponte-preta', 'Com vantagem, Ponte Preta se classifica no empate');
$checks += 4;

// =========================================================================
// 5. Encerramento (Finalizada): Decisão no agregado, pênaltis e vantagem
// =========================================================================
// 5.1: Vitória simples no agregado
$mFin1 = $mLeg2Live4;
$mFin1['status'] = 'finalizada';
$mFin1['minute'] = 90;
$aggFin1 = Competition::tieContext($t2, $mFin1);
ok($aggFin1['finalized'] === true, 'Partida finalizada');
ok($aggFin1['advanced_id'] === 'sao-paulo', 'São Paulo avançou no agregado (4 x 3)');
ok($aggFin1['by_penalties'] === false, 'Não foi por pênaltis');
$checks += 3;

// 5.2: Empate e decisão nos pênaltis
$mFinPens = $mLeg2Live3; // 1 x 2 na volta, agregado 3 x 3
$mFinPens['status'] = 'finalizada';
$mFinPens['minute'] = 90;
$mFinPens['penalties'] = [5, 4]; // São Paulo 5 x 4 Santos nos pênaltis
$aggFinPens = Competition::tieContext($t2, $mFinPens);
ok($aggFinPens['finalized'] === true, 'Finalizada com pênaltis');
ok($aggFinPens['by_penalties'] === true, 'Identificado avanço por pênaltis');
ok($aggFinPens['advanced_id'] === 'sao-paulo', 'São Paulo avançou nos pênaltis (5 x 4)');
$checks += 3;

// 5.3: Empate e classificação por vantagem
$mFinAdv = $tAdv['matches'][1];
$mFinAdv['status'] = 'finalizada';
$mFinAdv['minute'] = 90;
$aggFinAdv = Competition::tieContext($tAdv, $mFinAdv);
ok($aggFinAdv['finalized'] === true, 'Finalizada por vantagem');
ok($aggFinAdv['by_penalties'] === false, 'Sem pênaltis');
ok($aggFinAdv['advanced_id'] === 'ponte-preta', 'Ponte Preta avançou pela vantagem');
$checks += 3;

// =========================================================================
// 6. Inversão de Mandos: Ordem inversa de mandante na ida/volta
// =========================================================================
$tInv = [
    'id' => 'br-d',
    'phase' => 'oitavas',
    'ties' => [
        ['a' => 'clube-alpha', 'b' => 'clube-beta', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => [
        // Alpha foi mandante no jogo 1 e venceu por 3 x 1
        fixtureMatch(10, 'clube-alpha', 'clube-beta', ['phase' => 'oitavas', 'round' => 10, 'tie' => 0, 'status' => 'finalizada', 'minute' => 90, 'hg' => 3, 'ag' => 1]),
        // Beta é mandante no jogo 2 e vence por 1 x 0
        fixtureMatch(11, 'clube-beta', 'clube-alpha', ['phase' => 'oitavas', 'round' => 11, 'tie' => 0, 'status' => 'em_andamento', 'minute' => 60, 'hg' => 1, 'ag' => 0]),
    ]
];

$mInv = $tInv['matches'][1];
$aggInv = Competition::tieContext($tInv, $mInv);
ok($aggInv['home_id'] === 'clube-beta', 'Mandante do jogo 2 é Beta');
ok($aggInv['away_id'] === 'clube-alpha', 'Visitante do jogo 2 é Alpha');
ok($aggInv['home_leg1_goals'] === 1, 'Beta marcou 1 no jogo 1 (como visitante)');
ok($aggInv['away_leg1_goals'] === 3, 'Alpha marcou 3 no jogo 1 (como mandante)');
ok($aggInv['home_aggregate'] === 2, 'Beta tem 2 no agregado (1 + 1)');
ok($aggInv['away_aggregate'] === 3, 'Alpha tem 3 no agregado (3 + 0)');
ok($aggInv['qualifying_id'] === 'clube-alpha', 'Alpha está se classificando (3 x 2)');
$checks += 7;

// =========================================================================
// 7. Renderização HTML no componente Presentation::score e Presentation::renderAggregate
// =========================================================================
ob_start();
Presentation::renderAggregate($aggLive1);
$htmlLive = ob_get_clean();

ok(str_contains($htmlLive, 'class="aggregate-strip"'), 'HTML contém aggregate-strip');
ok(str_contains($htmlLive, 'Ida:'), 'HTML exibe resultado da ida');
ok(str_contains($htmlLive, 'Agregado:'), 'HTML exibe placar agregado');
ok(str_contains($htmlLive, '2 <small>×</small> 2'), 'HTML exibe números do agregado');
ok(str_contains($htmlLive, 'Decisão por pênaltis'), 'HTML informa critério de pênaltis');
$checks += 5;

ob_start();
Presentation::renderAggregate($aggLive2);
$htmlLive2 = ob_get_clean();
ok(str_contains($htmlLive2, 'Classificando: <b>'.ClubBadge::name($aggLive2['qualifying_id']).'</b>'), 'HTML informa time se classificando em tempo real');
ok(str_contains($htmlLive2, '2 <small>×</small> 3'), 'HTML exibe placar agregado atualizado');
$checks += 2;

ob_start();
Presentation::renderAggregate($aggFinPens);
$htmlFinPens = ob_get_clean();
ok(str_contains($htmlFinPens, 'Classificado nos pênaltis: <b>'.ClubBadge::name($aggFinPens['advanced_id']).'</b> (5 × 4)'), 'HTML pós-jogo informa campeão nos pênaltis');
$checks += 1;

ob_start();
Presentation::renderAggregate($aggFinAdv);
$htmlFinAdv = ob_get_clean();
ok(str_contains($htmlFinAdv, 'Classificado por vantagem: <b>'.ClubBadge::name($aggFinAdv['advanced_id']).'</b> (melhor campanha)'), 'HTML pós-jogo informa classificação por vantagem');
$checks += 1;

// =========================================================================
// 8. Integração completa com o Game array em Game::currentIndex e view.php
// =========================================================================
$gSample = Game::legacyCreate('Jogador de teste','São Paulo','SP','meia');
$gSample['official'] = true;
$gSample['competition_id'] = 'sp-a1';
$gSample['club'] = 'sao-paulo';
$gSample['competitions'] = ['sp-a1' => $t2];
$gSample['matches'] = $t2['matches'];
$gSample['matches'][1] = $mLeg2Live1;
$gSample['round'] = 16;
$gSample['stage'] = 'partida';

ob_start();
Presentation::score($gSample, $gSample['matches'][1]);
$htmlScore = ob_get_clean();

ok(str_contains($htmlScore, 'class="aggregate-strip"'), 'Presentation::score renderiza aggregate-strip');
ok(str_contains($htmlScore, 'Ida:'), 'Presentation::score exibe resultado da ida');
ok(str_contains($htmlScore, 'Agregado: <strong>2 <small>×</small> 2</strong>'), 'Presentation::score exibe agregado');
$checks += 3;

// 8.1 Validação estrutural rigorosa do pre-match-banner (OS-R/R10-UI-001-CI05)
function validatePreMatchBanner(
    string $html,
    string $expectedComp,
    int $expectedRound,
    ?int $expectedHomeAgg = null,
    ?int $expectedAwayAgg = null
): array {
    // 1. Localiza especificamente o elemento <article> cuja lista de classes contenha pre-match-banner
    if (!preg_match_all('#<article\b(?:\s+[^>]*?)?\s+class=(["\'])(.*?)\1[^>]*>#si', $html, $articleMatches, PREG_OFFSET_CAPTURE)) {
        return ['ok' => false, 'error' => 'Elemento <article> com classe pre-match-banner não foi localizado'];
    }

    $targetMatch = null;
    foreach ($articleMatches[2] as $idx => $classAttrMatch) {
        $classes = preg_split('/\s+/', trim($classAttrMatch[0]));
        if (in_array('pre-match-banner', $classes, true)) {
            $targetMatch = $articleMatches[0][$idx];
            break;
        }
    }

    if ($targetMatch === null) {
        return ['ok' => false, 'error' => 'Elemento <article> com classe pre-match-banner não foi localizado'];
    }

    $openTagStart = $targetMatch[1];
    $openTagEnd = $openTagStart + strlen($targetMatch[0]);
    $restHtml = substr($html, $openTagEnd);

    // 2. Validação explícita de fechamento (sem auto-repair)
    $closePos = strpos($restHtml, '</article>');
    if ($closePos === false) {
        return ['ok' => false, 'error' => 'Banner pré-jogo não possui tag de fechamento </article> correspondente'];
    }

    // Rejeita abertura de outro <article> antes do fechamento do banner atual
    $nextOpenArticle = strpos($restHtml, '<article');
    if ($nextOpenArticle !== false && $nextOpenArticle < $closePos) {
        return ['ok' => false, 'error' => 'Banner pré-jogo não foi fechado antes da abertura de um novo <article>'];
    }

    $bannerInner = substr($restHtml, 0, $closePos);
    $bannerFull = substr($html, $openTagStart, ($openTagEnd - $openTagStart) + $closePos + strlen('</article>'));

    // 3. Validação estrutural com DOMDocument e DOMXPath sobre o banner isolado
    $doc = new DOMDocument();
    // Prefixo XML com declaração UTF-8 para parsing exato de entidades e caracteres especiais
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $bannerFull, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($doc);

    $articleNodes = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " pre-match-banner ")]');
    if ($articleNodes->length === 0) {
        return ['ok' => false, 'error' => 'Elemento <article> com classe pre-match-banner não foi localizado pelo parser estrutural'];
    }
    $articleNode = $articleNodes->item(0);

    // 4. Badge badge-pre-jogo restrito ao banner contendo estritamente 'PRÓXIMA PARTIDA'
    $badgeNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " badge-pre-jogo ")]', $articleNode);
    if ($badgeNodes->length === 0) {
        return ['ok' => false, 'error' => 'Banner não contém elemento com classe badge-pre-jogo'];
    }
    $badgeText = trim($badgeNodes->item(0)->textContent);
    if ($badgeText !== 'PRÓXIMA PARTIDA') {
        return ['ok' => false, 'error' => "Elemento badge-pre-jogo contém '{$badgeText}', esperado 'PRÓXIMA PARTIDA'"];
    }

    // 5. match-competition-name contendo exatamente a competição esperada
    $compNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " match-competition-name ")]', $articleNode);
    if ($compNodes->length === 0) {
        return ['ok' => false, 'error' => 'Banner não contém elemento <span class="match-competition-name">'];
    }
    $actualComp = trim($compNodes->item(0)->textContent);
    if ($actualComp !== $expectedComp) {
        return ['ok' => false, 'error' => "Elemento match-competition-name contém '{$actualComp}', esperado '{$expectedComp}'"];
    }

    // 6. match-round-tag contendo a rodada esperada
    $roundNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " match-round-tag ")]', $articleNode);
    if ($roundNodes->length === 0) {
        return ['ok' => false, 'error' => 'Banner não contém elemento <span class="match-round-tag">'];
    }
    $actualRoundText = trim($roundNodes->item(0)->textContent);
    if (!preg_match('/\bRODADA\s+' . $expectedRound . '\b/u', $actualRoundText)) {
        return ['ok' => false, 'error' => "Elemento match-round-tag não contém 'RODADA {$expectedRound}' (obteve '{$actualRoundText}')"];
    }

    // 7. Placar agregado e relação estrita de ancestralidade pai-filho
    if ($expectedHomeAgg !== null || $expectedAwayAgg !== null) {
        // Container aggregate-strip obrigatoriamente dentro do banner
        $stripNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " aggregate-strip ")]', $articleNode);
        if ($stripNodes->length === 0) {
            return ['ok' => false, 'error' => 'Banner não contém container .aggregate-strip do placar agregado'];
        }
        $stripNode = $stripNodes->item(0);

        // aggregate-total obrigatoriamente como descendente de aggregate-strip
        $totalNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " aggregate-total ")]', $stripNode);
        if ($totalNodes->length === 0) {
            return ['ok' => false, 'error' => 'Container .aggregate-strip não apresenta elemento .aggregate-total como seu descendente'];
        }

        $totalText = trim($totalNodes->item(0)->textContent);
        if (!preg_match('/(\d+)\s*(?:[×xX-]|\D+)\s*(\d+)/u', $totalText, $scoreMatch)) {
            return ['ok' => false, 'error' => 'Container .aggregate-strip não contém valores numéricos do placar agregado'];
        }

        $actualHomeAgg = (int)$scoreMatch[1];
        $actualAwayAgg = (int)$scoreMatch[2];

        if ($expectedHomeAgg !== null && $actualHomeAgg !== $expectedHomeAgg) {
            return ['ok' => false, 'error' => "Placar agregado mandante no banner é {$actualHomeAgg}, esperado {$expectedHomeAgg}"];
        }
        if ($expectedAwayAgg !== null && $actualAwayAgg !== $expectedAwayAgg) {
            return ['ok' => false, 'error' => "Placar agregado visitante no banner é {$actualAwayAgg}, esperado {$expectedAwayAgg}"];
        }
    }

    return ['ok' => true, 'banner' => $bannerInner];
}

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void { echo '<form method="post" action="'.e($path).'">'; }
}

$path = '/carreira';
$error = null;
$_SESSION = ['account' => 0, 'csrf' => 'preview'];

// pre_jogo
$gPre = $gSample;
$gPre['stage'] = 'pre_jogo';
$gPre['matches'][1] = $mLeg2Pre;
$g = $gPre;
ob_start();
require dirname(__DIR__).'/app/view.php';
$htmlViewPre = ob_get_clean();

// P1. HTML real homologado (Paulistão A1, Rodada 16, Agregado 2x1)
$expectedCompPre = Competition::label($gPre);
$expectedRoundPre = 16;
$expectedHomeAggPre = 2; // São Paulo (mandante da volta, 2 gols na ida)
$expectedAwayAggPre = 1; // Santos (visitante da volta, 1 gol na ida)

$resPre = validatePreMatchBanner($htmlViewPre, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok($resPre['ok'], 'P1: view.php renderiza pre-match-banner estruturalmente válido, com badge, competição, rodada e agregado correto (' . ($resPre['error'] ?? '') . ')');
ok(str_contains($resPre['banner'], 'class="match-period-badge badge-pre-jogo"'), 'P1: elemento badge-pre-jogo restrito ao banner');
ok(str_contains($resPre['banner'], 'class="match-competition-name"'), 'P1: elemento match-competition-name restrito ao banner');
ok(str_contains($resPre['banner'], 'class="match-round-tag"'), 'P1: elemento match-round-tag restrito ao banner');
ok(str_contains($resPre['banner'], 'class="aggregate-strip"'), 'P1: container aggregate-strip restrito ao banner');
$checks += 5;

// =========================================================================
// 8.2 Testes negativos obrigatórios de integridade estrutural (OS-R/R10-UI-001-CI03 / CI04 / CI05)
// =========================================================================

// N1. Banner sem fechamento (sem </article> ou abre outro <article> antes de fechar)
$htmlN1_sem_fechamento = str_replace('</article>', '', $htmlViewPre);
$resN1_a = validatePreMatchBanner($htmlN1_sem_fechamento, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN1_a['ok'], 'N1-A: Validador rejeita banner sem tag de fechamento </article>');

$htmlN1_abre_outro = preg_replace('#</article>#', '<article class="panel other-panel">Outro</article>', $htmlViewPre, 1);
$resN1_b = validatePreMatchBanner($htmlN1_abre_outro, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN1_b['ok'], 'N1-B: Validador rejeita banner que abre outro <article> sem fechar o anterior');

// N2. Competição incorreta em match-competition-name, mesmo que o nome esperado apareça fora
$htmlN2 = str_replace(
    '<span class="match-competition-name">' . e($expectedCompPre) . '</span>',
    '<span class="match-competition-name">Copa Fictícia</span><div class="outside-comp">' . e($expectedCompPre) . '</div>',
    $htmlViewPre
);
$resN2 = validatePreMatchBanner($htmlN2, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN2['ok'], 'N2: Validador rejeita competição incorreta em match-competition-name mesmo com nome fora');

// N3. Placar agregado fora do banner
$htmlN3 = preg_replace('#<div class="aggregate-strip".*?(?=<div class="live-player-stats">)#s', '', $htmlViewPre);
$htmlN3 .= '<div class="aggregate-strip" data-aggregate="true"><span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span></div>';
$resN3 = validatePreMatchBanner($htmlN3, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN3['ok'], 'N3: Validador rejeita placar agregado presente apenas fora do banner');

// N4. Rodada fora do banner (ausente de match-round-tag e presente fora)
$htmlN4 = preg_replace('#<span class="match-round-tag">.*?</span>#s', '', $htmlViewPre, 1);
$htmlN4 = str_replace('</article>', '</article><span class="match-round-tag"> · RODADA 16</span>', $htmlN4);
$resN4 = validatePreMatchBanner($htmlN4, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN4['ok'], 'N4: Validador rejeita rodada presente apenas fora do banner');

// N5. Agregado incorreto (valores numéricos divergentes dos esperados pela fixture)
$htmlN5 = str_replace('2 <small>×</small> 1', '4 <small>×</small> 0', $htmlViewPre);
$resN5 = validatePreMatchBanner($htmlN5, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN5['ok'], 'N5: Validador rejeita placar agregado com valores incorretos no banner');

// N6. Agregado vazio (container aggregate-strip presente porém sem resultado agregado)
$htmlN6 = preg_replace('#<span class="aggregate-total">.*?</span>#s', '<span class="aggregate-total"></span>', $htmlViewPre);
$resN6 = validatePreMatchBanner($htmlN6, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN6['ok'], 'N6: Validador rejeita container aggregate-strip vazio ou sem placar');

// N7. Badge ausente (texto "PRÓXIMA PARTIDA" no banner porém fora do elemento badge-pre-jogo)
$htmlN7 = str_replace('class="match-period-badge badge-pre-jogo"', 'class="custom-plain-text"', $htmlViewPre);
$resN7 = validatePreMatchBanner($htmlN7, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN7['ok'], 'N7: Validador rejeita texto "PRÓXIMA PARTIDA" fora do badge badge-pre-jogo');

// N8. aggregate-strip vazio e aggregate-total correto fora dele, mas dentro do mesmo artigo
$htmlN8 = preg_replace(
    '#<span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span>#s',
    '',
    $htmlViewPre
);
$htmlN8 = str_replace(
    '</div><div class="live-player-stats">',
    '</div><span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span><div class="live-player-stats">',
    $htmlN8
);
ok(str_contains($htmlN8, '<div class="aggregate-strip" data-aggregate="true">'), 'N8: mutação preservou container aggregate-strip no artigo');
ok(str_contains($htmlN8, '<span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span>'), 'N8: mutação inseriu aggregate-total fora do container no artigo');
$resN8 = validatePreMatchBanner($htmlN8, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN8['ok'], 'N8: Validador rejeita aggregate-total posicionado fora de aggregate-strip mesmo dentro do banner');

// N9. Artigo válido de outra classe antes do verdadeiro pre-match-banner (deve aceitar o banner correto)
$htmlN9 = '<article class="panel other-article"><h3>Notícia Pré-Jogo</h3><p>Clássico decisivo hoje à tarde.</p></article>' . $htmlViewPre;
ok(str_starts_with($htmlN9, '<article class="panel other-article">'), 'N9: mutação inseriu artigo anterior válido');
$resN9 = validatePreMatchBanner($htmlN9, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok($resN9['ok'], 'N9: Validador aceita pre-match-banner quando precedido por outro <article> válido (' . ($resN9['error'] ?? '') . ')');

// N10. aggregate-total com valores incorretos dentro de aggregate-strip, enquanto o valor correto está fora
$htmlN10 = str_replace('2 <small>×</small> 1', '0 <small>×</small> 0', $htmlViewPre);
$htmlN10 .= '<div class="outside-aggregate"><span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span></div>';
ok(str_contains($htmlN10, '0 <small>×</small> 0'), 'N10: mutação alterou aggregate-total interno para valor incorreto');
ok(str_contains($htmlN10, 'outside-aggregate'), 'N10: mutação inseriu aggregate-total correto fora do container');
$resN10 = validatePreMatchBanner($htmlN10, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN10['ok'], 'N10: Validador rejeita aggregate-total interno incorreto mesmo com valor correto fora');

// N11. aggregate-total com valores corretos posicionado totalmente fora do artigo pré-jogo
$htmlN11 = preg_replace('#<div class="aggregate-strip".*?(?=<div class="live-player-stats">)#s', '', $htmlViewPre);
$htmlN11 = str_replace('</article>', '</article><aside><div class="aggregate-strip" data-aggregate="true"><span class="aggregate-total">Agregado: <strong>2 <small>×</small> 1</strong></span></div></aside>', $htmlN11);
ok(str_contains($htmlN11, '<aside><div class="aggregate-strip"'), 'N11: mutação inseriu aggregate-strip exclusivamente fora do artigo');
$resN11 = validatePreMatchBanner($htmlN11, $expectedCompPre, $expectedRoundPre, $expectedHomeAggPre, $expectedAwayAggPre);
ok(!$resN11['ok'], 'N11: Validador rejeita aggregate-total posicionado totalmente fora do artigo pré-jogo');
$checks += 12;

// fim_semana
$gPost = $gSample;
$gPost['stage'] = 'fim_semana';
$gPost['matches'][1] = $mFinPens;
$g = $gPost;
ob_start();
require dirname(__DIR__).'/app/view.php';
$htmlViewPost = ob_get_clean();
ok(str_contains($htmlViewPost, 'class="aggregate-strip"'), 'view.php renderiza aggregate-strip no fim_semana');
ok(str_contains($htmlViewPost, 'Classificado nos pênaltis:'), 'view.php pós-jogo exibe avanço nos pênaltis');
$checks += 2;

echo "OK: $checks verificações do placar agregado aprovadas com sucesso!\n";
