# RUMO10 | F03 | Placar Visual da Partida

## Objetivo e Escopo
Melhorar a apresentação visual das partidas do Rumo10, mantendo integralmente a mecânica atual de decisões e avanço do jogo. O servidor permanece como fonte única da verdade; não há simulação minuto a minuto, cronômetro em tempo real, novas etapas de jogabilidade ou requisições periódicas adicionais.

## Implementações Realizadas

1. **Placar Visual Destacado**:
   - Escudos oficiais/provisórios com dimensões ampliadas e nítidas (42px no desktop, 30px no mobile).
   - Nomes dos clubes com hierarquia tipográfica, contraste equilibrado e identificação clara do clube do protagonista via tag `SEU CLUBE`.
   - Dígitos de gols proeminentes (38px no desktop, 26px no mobile) com `font-variant-numeric: tabular-nums` e sutil animação CSS `scoreDigitPop`.

2. **Minuto Real e Período da Partida**:
   - Leitura direta do minuto autoritativo do servidor (`$m['minute']`).
   - Indicação dinâmica de período (`1º Tempo`, `2º Tempo`, `Intervalo` aos 45′, `Fim de jogo` aos 90′).
   - Indicador visual ao vivo (`.match-live-dot`) com pulsação suave em partidas em andamento.

3. **Barra Discreta de Progresso (0 a 90 min)**:
   - Progresso percentual calculado exatamente com base no minuto atual: `min(100, max(0, round(($minute / 90) * 100)))%`.
   - Marcador visual para o intervalo (`INT` aos 45′).
   - Rótulos discretos de 0′, INT e 90′ com atributos ARIA completos para acessibilidade (`role="progressbar"`).

4. **Apresentação de Acontecimentos e Decisões**:
   - Status claro do protagonista no rodapé do placar (`Em campo`, `No banco (entra aos X′)`, `Substituído aos X′`).
   - Preservação dos contadores de gols e assistências com atributos `data-stat="goals"` e `data-stat="assists"`.
   - Card contextual para momentos de avanço sem decisão em campo (intervalo, reserva no banco ou jogador já substituído), informando claramente o motivo do botão de avanço.

5. **Animações Discretas e Responsividade**:
   - Animações CSS pontuais para carregamento do placar e pulsos sutis em lances de gol/assistência (`scoreGlowPulse`).
   - Suporte a `@media (prefers-reduced-motion: reduce)` desabilitando animações e transições.
   - Layout responsivo adaptado para computador e celular (ordenação mobile mantida, espaçamentos compactos e legibilidade em viewports a partir de 320px e 390px).

## Verificação e Homologação
- Baterias de testes executadas e aprovadas:
  - `tests/presentation.php` (OK)
  - `tests/aggregate-score-v07.php` (OK - 63 verificações)
  - `tests/test-mobile-ux-flow.php` (OK - 17 verificações)
  - `tests/test-offensive-icons-and-balance.php` (OK - 25 verificações)
  - `tests/penalty-shootout-v07.php` (OK - 46 verificações)
  - `tests/compact-results.php` (OK)
  - `tests/normal-xp.php` (OK - 369 verificações)
- Capturas de evidência salvas em `Docs/f03-evidencias/`.
