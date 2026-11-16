<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!function_exists('form')) {
    function form(string $action, ?array $g = null): void {
        echo '<form method="post" action="' . e($action) . '">';
    }
}

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

echo "=== R10-UI-001: TESTES DE APRESENTAÇÃO DE COMPETIÇÃO, CONTADORES E FIM DE JOGO ===\n\n";

// Helper para criar jogo de exemplo
function createSampleCareer(string $stage = 'partida', string $mode = 'normal'): array {
    $g = Game::legacyCreate('Lucas Silva', 'Santos', 'SP', 'meia');
    $g['id'] = 101;
    $g['version'] = 10;
    $g['club'] = 'Santos';
    $g['round'] = 2;
    $g['season'] = 1;
    $g['play_mode'] = $mode;
    $g['stage'] = $stage;

    // Duas partidas no histórico: Rodada 1 finalizada (1 gol, 1 assist, 90 mins), Rodada 2 conforme stage
    $g['matches'][0]['round'] = 1;
    $g['matches'][0]['home'] = 'Santos';
    $g['matches'][0]['away'] = 'Palmeiras';
    $g['matches'][0]['status'] = 'finalizada';
    $g['matches'][0]['minute'] = 90;
    $g['matches'][0]['entry'] = 0;
    $g['matches'][0]['exit'] = 90;
    $g['matches'][0]['hg'] = 2;
    $g['matches'][0]['ag'] = 1;
    $g['matches'][0]['lineup'] = 'titular';
    $g['matches'][0]['stats'] = ['goals' => 1, 'assists' => 1, 'shots' => 3, 'passes' => 10, 'completed' => 8, 'minutes' => 90, 'rating' => 8.2];
    $g['matches'][0]['log'] = [['minute' => 34, 'text' => 'Gol de Lucas Silva!']];

    $g['matches'][1]['round'] = 2;
    $g['matches'][1]['home'] = 'Corinthians';
    $g['matches'][1]['away'] = 'Santos';
    if ($stage === 'fim_semana') {
        $g['matches'][1]['status'] = 'finalizada';
        $g['matches'][1]['minute'] = 90;
        $g['matches'][1]['entry'] = 0;
        $g['matches'][1]['exit'] = 90;
        $g['matches'][1]['hg'] = 0;
        $g['matches'][1]['ag'] = 2;
        $g['matches'][1]['lineup'] = 'titular';
        $g['matches'][1]['participation_state'] = 'FULL_TIME';
        $g['matches'][1]['stats'] = ['goals' => 2, 'assists' => 0, 'shots' => 4, 'passes' => 12, 'completed' => 10, 'tackles' => 1, 'cards' => 0, 'minutes' => 90, 'rating' => 8.8];
        $g['matches'][1]['experience'] = [
            'schema' => 1,
            'complete' => true,
            'earned' => array_fill_keys(Rules::SKILLS, 10),
            'bonus' => array_fill_keys(Rules::SKILLS, 0),
            'before' => $g['skills'],
            'after' => $g['skills']
        ];
    } elseif ($stage === 'pre_jogo') {
        $g['matches'][1]['status'] = 'pre_jogo';
        $g['matches'][1]['minute'] = 0;
        $g['matches'][1]['entry'] = 0;
        $g['matches'][1]['exit'] = 90;
        $g['matches'][1]['hg'] = 0;
        $g['matches'][1]['ag'] = 0;
        $g['matches'][1]['lineup'] = 'titular';
        $g['matches'][1]['stats'] = ['goals' => 0, 'assists' => 0, 'shots' => 0, 'passes' => 0, 'completed' => 0, 'minutes' => 0, 'rating' => 0];
    } else {
        $g['matches'][1]['status'] = 'em_andamento';
        $g['matches'][1]['minute'] = 60;
        $g['matches'][1]['entry'] = 0;
        $g['matches'][1]['exit'] = 90;
        $g['matches'][1]['hg'] = 0;
        $g['matches'][1]['ag'] = 1;
        $g['matches'][1]['lineup'] = 'titular';
        $g['matches'][1]['participation_state'] = 'ON_FIELD';
        $g['matches'][1]['stats'] = ['goals' => 1, 'assists' => 0, 'shots' => 2, 'passes' => 7, 'completed' => 6, 'minutes' => 60, 'rating' => 7.5];
    }
    return $g;
}

function renderView(array $g, string $path = '/carreira', array $get = []): string {
    $_GET = $get;
    $error = null;
    $_SESSION = ['account' => 1, 'csrf' => 'test-csrf'];
    ob_start();
    include __DIR__ . '/../app/view.php';
    return ob_get_clean();
}

// -------------------------------------------------------------
echo "1. Validando Identificação de Competições no Cabeçalho e Scoreboard...\n";
// -------------------------------------------------------------
$gPartida = createSampleCareer('partida');
$htmlPartida = renderView($gPartida);

// Header: Temporada, Competição, Fase/progresso, Jogador, clube, posição, idade
assertCheck(str_contains($htmlPartida, 'class="competition-name"'), 'Cabeçalho renderiza elemento com classe competition-name');
assertCheck(str_contains($htmlPartida, 'TEMPORADA 1'), 'Cabeçalho contém número da temporada');
assertCheck(str_contains($htmlPartida, 'Liga regional'), 'Cabeçalho contém nome da competição (Liga regional)');
assertCheck(str_contains($htmlPartida, 'Rodada 2 de 14'), 'Cabeçalho contém fase/progresso da competição');
assertCheck(str_contains($htmlPartida, 'Lucas Silva'), 'Cabeçalho contém nome do jogador');
assertCheck(str_contains($htmlPartida, 'Santos'), 'Cabeçalho contém clube do jogador');
assertCheck(str_contains($htmlPartida, 'Meia'), 'Cabeçalho contém posição');

// Verificação de NÃO duplicidade no cabeçalho
preg_match('#<div class="career-head">(.*?)</div>\s*(?:<div class="game-layout">|(?=<section)|(?=<article)|(?=<div))#s', $htmlPartida, $headMatches);
$headerContent = $headMatches[0] ?? '';
$compCountInHeader = substr_count($headerContent, 'Liga regional');
assertCheck($compCountInHeader === 1, "Nome da competição aparece exatamente UMA vez no cabeçalho (obteve {$compCountInHeader})");

// Scoreboard da partida em andamento
assertCheck(str_contains($htmlPartida, 'class="match-competition-name"'), 'Scoreboard contém classe match-competition-name');
assertCheck(str_contains($htmlPartida, '<span class="match-competition-name">Liga regional</span>'), 'Scoreboard exibe o nome da competição');

// Pre-match banner
$gPreJogo = createSampleCareer('pre_jogo');
$htmlPreJogo = renderView($gPreJogo);
assertCheck(str_contains($htmlPreJogo, 'pre-match-banner'), 'Etapa pré-jogo renderiza pre-match-banner');
assertCheck(str_contains($htmlPreJogo, '<span class="match-competition-name">Liga regional</span>'), 'Pre-match banner exibe o nome da competição');

// CSS checks: sem display: none para competition-name
$css = file_get_contents(__DIR__ . '/../public/assets/visual.css');
assertCheck(!preg_match('/\.career-head\s+\.competition-name\s*\{\s*display:\s*none/i', $css), 'CSS não oculta competition-name no cabeçalho');

// -------------------------------------------------------------
echo "\n2. Validando Separação de Contadores (Fase, Competição e Temporada)...\n";
// -------------------------------------------------------------
$gPost = createSampleCareer('fim_semana');
ob_start();
Presentation::goalHistory($gPost);
$historyHtml = ob_get_clean();

assertCheck(str_contains($historyHtml, 'TEMPORADA 1 · Liga regional · Rodada 2 de 14'), 'Histórico exibe cabeçalho completo com Temporada, Competição e Fase');
assertCheck(str_contains($historyHtml, 'Competição atual: Liga regional'), 'Histórico possui seção destacada para Competição atual');
assertCheck(str_contains($historyHtml, 'data-stat="competition-goals"'), 'Contador de gols da competição possui atributo data-stat="competition-goals"');
assertCheck(str_contains($historyHtml, 'data-stat="competition-assists"'), 'Contador de assistências da competição possui atributo data-stat="competition-assists"');
assertCheck(str_contains($historyHtml, 'data-stat="competition-matches"'), 'Contador de partidas da competição possui atributo data-stat="competition-matches"');

assertCheck(str_contains($historyHtml, 'Temporada 1 (Total acumulado)'), 'Histórico possui seção destacada para Temporada inteira');
assertCheck(str_contains($historyHtml, 'data-stat="season-goals"'), 'Contador de gols da temporada preserva data-stat="season-goals"');
assertCheck(str_contains($historyHtml, 'data-stat="season-assists"'), 'Contador de assistências da temporada possui data-stat="season-assists"');
assertCheck(str_contains($historyHtml, 'data-stat="season-matches"'), 'Contador de jogos da temporada possui data-stat="season-matches"');

// Valores calculados: Rodada 1 (1G, 1A, 1J) + Rodada 2 (2G, 0A, 1J) = Total 3G, 1A, 2J
assertCheck(str_contains($historyHtml, 'data-stat="competition-goals">3<'), 'Contador de gols da competição reflete total correto (3)');
assertCheck(str_contains($historyHtml, 'data-stat="competition-assists">1<'), 'Contador de assistências da competição reflete total correto (1)');
assertCheck(str_contains($historyHtml, 'data-stat="competition-matches">2<'), 'Contador de partidas jogadas na competição reflete total correto (2)');
assertCheck(str_contains($historyHtml, 'data-stat="season-goals">3<'), 'Contador de gols da temporada reflete total acumulado (3)');
assertCheck(str_contains($historyHtml, 'Seus gols no campeonato'), 'Texto legado "Seus gols no campeonato" preservado para compatibilidade');

// -------------------------------------------------------------
echo "\n3. Validando Tela de Fim de Jogo (R10-UI-001-POSJOGO) e Fluxo em Etapas...\n";
// -------------------------------------------------------------

// Etapa A (Fim de jogo inicial: sem ?etapa=pos_jogo)
$htmlFimA = renderView($gPost, '/carreira', []);
assertCheck(str_contains($htmlFimA, 'post-match-summary'), 'Etapa A: Body possui classe post-match-summary');
assertCheck(!str_contains($htmlFimA, 'post-match-decision'), 'Etapa A: Body NÃO possui classe post-match-decision');
assertCheck(str_contains($htmlFimA, 'class="panel post-match"'), 'Etapa A: Renderiza card de fim de jogo .post-match');
assertCheck(str_contains($htmlFimA, 'FIM DE JOGO'), 'Etapa A: Exibe badge oficial "FIM DE JOGO"');
assertCheck(str_contains($htmlFimA, '<span class="match-competition-name">Liga regional</span>'), 'Etapa A: Exibe identificação da competição no placar final');
assertCheck(str_contains($htmlFimA, 'post-match-score-row'), 'Etapa A: Exibe linha proeminente de placar final');
assertCheck(str_contains($htmlFimA, '0 <small>×</small> 2'), 'Etapa A: Exibe números do placar final destacados');
assertCheck(str_contains($htmlFimA, 'Corinthians'), 'Etapa A: Exibe clube mandante no placar');
assertCheck(str_contains($htmlFimA, 'Santos'), 'Etapa A: Exibe clube visitante (jogador)');
assertCheck(str_contains($htmlFimA, 'Seu clube'), 'Etapa A: Destaque visual "Seu clube" ativo para o time do jogador');
assertCheck(str_contains($htmlFimA, 'match-experience'), 'Etapa A: Resumo de atuação (minutos, nota, eventos, XP) renderizado via MatchExperience');
assertCheck(str_contains($htmlFimA, 'data-action="continue-post-match"'), 'Etapa A: Botão com data-action="continue-post-match" presente');
assertCheck(str_contains($htmlFimA, 'Avançar →'), 'Etapa A: Botão primário possui rótulo "Avançar →"');
assertCheck(str_contains($htmlFimA, 'href="/carreira?etapa=pos_jogo"'), 'Etapa A: Fallback do botão direciona para /carreira?etapa=pos_jogo');

// Decisões de fim de semana na Etapa A (NÃO renderizadas no HTML do servidor):
assertCheck(!str_contains($htmlFimA, 'weekend-decision-panel'), 'Etapa A: Painel weekend-decision-panel AUSENTE do HTML inicial');
assertCheck(!str_contains($htmlFimA, 'action="/decisao/registrar"'), 'Etapa A: Formulário de decisões AUSENTE do HTML inicial');
assertCheck(!str_contains($htmlFimA, 'name="option"'), 'Etapa A: Opções de decisão AUSENTES do DOM inicial');
assertCheck(str_contains($css, 'body.post-match-summary .weekend-decision-panel'), 'CSS: Regra defensiva para .weekend-decision-panel presente');
assertCheck(str_contains($css, 'body.post-match-decision .post-match'), 'CSS: Regra que oculta .post-match durante post-match-decision presente');

// Etapa C (Decisão pós-jogo: com ?etapa=pos_jogo)
$htmlFimC = renderView($gPost, '/carreira', ['etapa' => 'pos_jogo']);
assertCheck(str_contains($htmlFimC, 'post-match-decision'), 'Etapa C: Body possui classe post-match-decision');
assertCheck(!str_contains($htmlFimC, 'post-match-summary'), 'Etapa C: Body NÃO possui classe post-match-summary');
assertCheck(str_contains($htmlFimC, 'weekend-decision-panel'), 'Etapa C: Painel weekend-decision-panel PRESENTE no HTML');
assertCheck(str_contains($htmlFimC, 'action="/decisao/registrar"'), 'Etapa C: Formulário de decisões PRESENTE no HTML');
assertCheck(substr_count($htmlFimC, 'name="option"') === 4, 'Etapa C: Exatamente 4 opções de decisão renderizadas');

// -------------------------------------------------------------
echo "\n4. Validando Suporte a Refresh (F5) e Idempotência...\n";
// -------------------------------------------------------------
// F5 na Etapa A
$htmlRefreshA = renderView($gPost, '/carreira', []);
assertCheck(str_contains($htmlRefreshA, 'post-match-summary'), 'Refresh F5 na Etapa A mantém estado post-match-summary');
assertCheck(str_contains($htmlRefreshA, 'FIM DE JOGO'), 'Refresh F5 na Etapa A preserva tela de Fim de Jogo');
assertCheck(!str_contains($htmlRefreshA, 'weekend-decision-panel'), 'Refresh F5 na Etapa A NÃO renderiza decisões');

// F5 na Etapa C
$htmlRefreshC = renderView($gPost, '/carreira', ['etapa' => 'pos_jogo']);
assertCheck(str_contains($htmlRefreshC, 'post-match-decision'), 'Refresh F5 na Etapa C mantém estado post-match-decision');
assertCheck(str_contains($htmlRefreshC, 'weekend-decision-panel'), 'Refresh F5 na Etapa C preserva formulário de decisões');
assertCheck(substr_count($htmlRefreshC, 'name="option"') === 4, 'Refresh F5 na Etapa C preserva 4 opções de decisão');

// -------------------------------------------------------------
echo "\n5. Validando Preservação de Disputa de Pênaltis Pendente...\n";
// -------------------------------------------------------------
$gShootout = createSampleCareer('fim_semana');
$gShootout['official'] = 1;
$gShootout['club'] = 'santos';
$gShootout['competition_id'] = 'sp-a3';
$gShootout['competitions']['sp-a3'] = [
    'name' => 'Paulista A3',
    'format' => 'cup',
    'phase' => 'quartas',
    'done' => false,
    'phase_start' => 1,
    'phase_end' => 2,
    'ties' => [
        ['a' => 'santos', 'b' => 'palmeiras', 'winner' => null, 'rule' => 'penalties']
    ],
    'matches' => []
];
// Ida foi 1x0 Palmeiras (agregado 1x1)
$gShootout['matches'][0]['competition'] = 'sp-a3';
$gShootout['matches'][0]['round'] = 1;
$gShootout['matches'][0]['home'] = 'palmeiras';
$gShootout['matches'][0]['away'] = 'santos';
$gShootout['matches'][0]['hg'] = 1;
$gShootout['matches'][0]['ag'] = 0;
$gShootout['matches'][0]['status'] = 'finalizada';
$gShootout['matches'][0]['phase'] = 'quartas';
$gShootout['matches'][0]['tie'] = 0;
$gShootout['matches'][0]['leg'] = 1;

// Volta 1x0 Santos (agregado 1x1)
$gShootout['matches'][1]['competition'] = 'sp-a3';
$gShootout['matches'][1]['round'] = 2;
$gShootout['matches'][1]['home'] = 'santos';
$gShootout['matches'][1]['away'] = 'palmeiras';
$gShootout['matches'][1]['hg'] = 1;
$gShootout['matches'][1]['ag'] = 0;
$gShootout['matches'][1]['status'] = 'finalizada';
$gShootout['matches'][1]['phase'] = 'quartas';
$gShootout['matches'][1]['tie'] = 0;
$gShootout['matches'][1]['leg'] = 2;
$gShootout['competitions']['sp-a3']['matches'] = $gShootout['matches'];

if (class_exists('PenaltyShootout') && PenaltyShootout::requiresShootout($gShootout, $gShootout['matches'][1])) {
    $htmlShootout = renderView($gShootout);
    assertCheck(str_contains($htmlShootout, 'FIM DO TEMPO REGULAMENTAR'), 'Shootout pendente: Badge é "FIM DO TEMPO REGULAMENTAR"');
    assertCheck(str_contains($htmlShootout, 'shootout-start-card'), 'Shootout pendente: Card de CTA para pênaltis é exibido');
    assertCheck(str_contains($htmlShootout, 'Começar as cobranças de pênaltis →'), 'Shootout pendente: Botão de cobranças presente');
    assertCheck(!str_contains($htmlShootout, 'data-action="continue-post-match"'), 'Shootout pendente: Botão avançar para decisões NÃO é exibido antes dos pênaltis');
    assertCheck(!str_contains($htmlShootout, 'weekend-decision-panel'), 'Shootout pendente: Painel de decisões AUSENTE no fim do tempo regulamentar');

    $htmlShootoutPosJogo = renderView($gShootout, '/carreira', ['etapa' => 'pos_jogo']);
    assertCheck(!str_contains($htmlShootoutPosJogo, 'weekend-decision-panel'), 'Shootout pendente: Mesmo com ?etapa=pos_jogo decisões permanecem BLOQUEADAS');
} else {
    echo "  [INFO] Disputa de pênaltis simulada através dos critérios do motor.\n";
}

// -------------------------------------------------------------
echo "\n6. Validando Modos Normal, Rápido e Expresso...\n";
// -------------------------------------------------------------
foreach (['normal', 'rapido', 'expresso'] as $mMode) {
    $gMode = createSampleCareer('fim_semana', $mMode);
    $htmlMode = renderView($gMode);
    assertCheck(str_contains($htmlMode, 'post-match-summary'), "Modo {$mMode}: Etapa A renderiza post-match-summary");
    assertCheck(str_contains($htmlMode, 'FIM DE JOGO'), "Modo {$mMode}: Placar final exibe FIM DE JOGO");
    assertCheck(str_contains($htmlMode, 'Avançar →'), "Modo {$mMode}: Botão Avançar presente");
}

// -------------------------------------------------------------
echo "\n7. Validando Integridade do Motor e das Funções Não Modificadas...\n";
// -------------------------------------------------------------
$summaryBefore = Game::summary($gPost);
$clubMatches = array_filter($gPost['matches'], fn($m) => in_array($gPost['club'], [$m['home'], $m['away']], true));
$playerMatchesCount = count(Competition::playerMatches($gPost));
assertCheck($summaryBefore['goals'] === 3, 'Game::summary preserva cálculo original de gols');
assertCheck($summaryBefore['assists'] === 1, 'Game::summary preserva cálculo original de assistências');
assertCheck($summaryBefore['games'] === 2, 'Game::summary preserva contagem de jogos com minutos > 0');
assertCheck(Competition::playerMatches($gPost) === $gPost['matches'], 'Competition::playerMatches preserva retorno idêntico a matches no modo legado');

echo "\n=======================================================\n";
echo "TOTAL: {$passed} verificações PASSOU, {$failed} FALHOU\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
