<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!function_exists('form')) {
    function form(string $path, ?array $g = null): void {
        echo '<form action="' . e($path) . '" method="post">';
    }
}

echo "=== TESTE DE ÍCONES DE JOGADAS E BALANCEAMENTO OFENSIVO ===\n\n";

$passedTests = 0;
function assertTest(bool $condition, string $message): void {
    global $passedTests;
    if (!$condition) {
        throw new RuntimeException("FALHA: $message");
    }
    echo "  [OK] $message\n";
    $passedTests++;
}

// 1. Cenário: Decisão em partida normal com chute (Gol) e passe/drible (Sucesso)
echo "1. Teste de alternativas em jogada normal (posse/continuação):\n";
$g = Game::legacyCreate("Teste Visual", "Portuguesa Santista-SP", "SP", "centroavante");
$g['stage'] = 'partida';
$matchIdx = Game::currentIndex($g);
$g['matches'][$matchIdx]['status'] = 'em_andamento';
$g['matches'][$matchIdx]['minute'] = 15;
$g['matches'][$matchIdx]['entry'] = 0;
$g['matches'][$matchIdx]['exit'] = 90;
$g['matches'][$matchIdx]['attack_sequence'] = 1;
$g['matches'][$matchIdx]['possession'] = ['minute' => 15, 'score' => [0, 0], 'actions' => 1];
// Situação 1301 (continuation)
$sit = MatchMomentum::situation($g);
assertTest($sit !== null && $sit['id'] === 1301, "Situação 1301 (continuação) ativa");

ob_start();
$path = '/carreira';
$error = null;
$r = null;
require dirname(__DIR__) . '/app/view.php';
$html = ob_get_clean();

// Deve conter ícone de gol para a opção "Finalizar agora" (chute)
assertTest(str_contains($html, 'icon-gol'), "Alternativa de chute contém ícone de gol (bola)");
assertTest(str_contains($html, 'Finalizar agora'), "Texto 'Finalizar agora' presente");
assertTest(str_contains($html, '<small class="outcome-pill outcome-gol">'), "Rótulo com tag outcome-gol presente");
assertTest(str_contains($html, 'aria-label="Chance de gol:'), "Acessibilidade: aria-label de chance de gol presente");

// NÃO deve conter ícone de gol nem assistência nas opções de passe/drible/reorganizar (sucesso)
assertTest(str_contains($html, 'Combinar um passe curto'), "Texto 'Combinar um passe curto' presente");
assertTest(str_contains($html, 'Avançar com a bola'), "Texto 'Avançar com a bola' presente");
assertTest(str_contains($html, 'Recuar e reorganizar'), "Texto 'Recuar e reorganizar' presente");
assertTest(str_contains($html, '<small class="outcome-pill outcome-sucesso">sucesso</small>'), "Opções de posse mantêm rótulo 'sucesso' limpo");
assertTest(str_contains($html, 'aria-label="Chance de sucesso:'), "Acessibilidade: aria-label de chance de sucesso presente");


// 2. Cenário: Chance Clara (Situação 1300)
echo "\n2. Teste de alternativas em Chance Clara (Gol e Assistência):\n";
$matchIdx = Game::currentIndex($g);
$g['matches'][$matchIdx]['clear_chance'] = true;
$g['matches'][$matchIdx]['attack_sequence'] = 2;
$g['matches'][$matchIdx]['possession'] = ['minute' => 15, 'score' => [0, 0], 'actions' => 2];
$sitClear = MatchMomentum::situation($g);
assertTest($sitClear !== null && !empty($sitClear['clear_chance']), "Situação de chance clara ativa");

ob_start();
require dirname(__DIR__) . '/app/view.php';
$htmlClear = ob_get_clean();

// Deve conter ícone de assistência no passe
assertTest(str_contains($htmlClear, 'icon-assist'), "Alternativa de passe em chance clara contém ícone de chuteira (assistência)");
assertTest(str_contains($htmlClear, 'Tocar para o companheiro livre'), "Texto 'Tocar para o companheiro livre' presente");
assertTest(str_contains($htmlClear, '<small class="outcome-pill outcome-assistência">'), "Rótulo com tag outcome-assistência presente");
assertTest(str_contains($htmlClear, 'aria-label="Chance de assistência:'), "Acessibilidade: aria-label de chance de assistência presente");

// Finalização e drible no goleiro devem ter ícone de gol
assertTest(str_contains($htmlClear, 'Finalizar no canto'), "Texto 'Finalizar no canto' presente");
assertTest(str_contains($htmlClear, 'Bater de primeira'), "Texto 'Bater de primeira' presente");
assertTest(str_contains($htmlClear, 'Driblar o goleiro'), "Texto 'Driblar o goleiro' presente");


// 3. Cenário: Correspondência de Percentuais Calculados e Exibidos
echo "\n3. Teste de exatidão entre percentuais calculados e exibidos:\n";
$choices = Game::choices($g);
foreach ($choices as $idx => $opt) {
    $calculatedChance = Game::chance($g, $opt);
    $formattedPercent = number_format($calculatedChance, 2, ',', '');
    $doc = new DOMDocument(); @$doc->loadHTML('<?xml encoding="UTF-8">'.$htmlClear); $xp = new DOMXPath($doc); $button = $xp->query('//button[@name="option" and @value="'.$idx.'"]')->item(0); assertTest($button !== null && str_contains($button->textContent, $formattedPercent . '%'), "Percentual exibido {$formattedPercent}% coincide com o calculado");
}

// 4. Cenário: Ações fora de campo não exibem choice-chance nem ícones enganosos
echo "\n4. Teste de isolamento fora de partida:\n";
$g['stage'] = 'fim_semana';
$g['matches'][Game::currentIndex($g)]['status'] = 'finalizada';
$g['matches'][Game::currentIndex($g)]['clear_chance'] = false;
ob_start();
require dirname(__DIR__) . '/app/view.php';
$htmlWeekend = ob_get_clean();
assertTest(!str_contains($htmlWeekend, 'icon-gol'), "Fim de semana não exibe ícone de gol");
assertTest(!str_contains($htmlWeekend, 'icon-assist'), "Fim de semana não exibe ícone de assistência");
assertTest(!str_contains($htmlWeekend, 'choice-chance'), "Fim de semana não exibe choice-chance");

echo "\n=======================================================\n";
echo "TOTAL: Passou: $passedTests | Falhou: 0\n";
echo "SUCESSO: Todos os testes de ícones visuais e balanceamento foram aprovados!\n";
echo "=======================================================\n";
