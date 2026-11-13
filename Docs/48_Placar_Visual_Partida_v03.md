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
  - `tests/test-f03-corrections.php` (OK - 224 verificações cobrindo CSP e situações de participação)
  - `tests/presentation.php` (OK)
  - `tests/aggregate-score-v07.php` (OK - 63 verificações)
  - `tests/test-mobile-ux-flow.php` (OK - 17 verificações)
  - `tests/test-offensive-icons-and-balance.php` (OK - 25 verificações)
  - `tests/penalty-shootout-v07.php` (OK - 46 verificações)
  - `tests/compact-results.php` (OK)
  - `tests/normal-xp.php` (OK - 369 verificações)
- Capturas de evidência salvas em `Docs/f03-evidencias/` (1200px, 390px e 320px).

## Correções Pontuais (OS-COR-F03)

### 1. Barra de progresso da partida (Compatibilidade CSP do IIS)
- **Causa raiz:** O IIS impõe `style-src 'self'`, bloqueando `style="width: ...%"` inline e preenchendo 100% da barra inadvertidamente.
- **Solução:** Substituição por elemento HTML5 nativo `<progress class="match-progress-bar" value="..." max="90">` sem atributos `style` inline.
- **Comportamento visual:** Proporcionalidade estrita aos 30′ (~33.3%), 45′ (50%), 60′ (~66.7%) e 90′ (100%), com valor visual restrito a `0..90` e minuto real preservado no display do relógio.
- **Estilização:** CSS externo em `visual.css` com pseudo-elementos `::-webkit-progress-bar`, `::-webkit-progress-value` e `::-moz-progress-bar`, preservando responsividade e acessibilidade.

### 2. Situação real do jogador (Consistência entre Placar e Card de Avanço)
- **Causa raiz:** Divergência no uso de heurísticas baseadas em minutos que exibiam titular em campo antes do apito inicial, previsão de entrada aos 90′ para atletas indisponíveis e jogadores expulsos rotulados como substituídos.
- **Solução:** Implementação do método canônico `Presentation::playerParticipation(array $g, array $m)`, consumido uniformemente pelo placar (`Presentation::score`) e pelo card de avanço (`view.php`).
- **Estados mapeados:**
  - `pre_jogo`: Exibe `Escalado (titular)` ou `No banco (reserva)`, nunca indicando `Em campo` pré-jogo.
  - `banco`: Exibe `No banco de reservas` durante a partida sem anunciar minutos antecipadamente. Ao término, identifica `Permaneceu no banco`.
  - `suspenso` / `não relacionado`: Identificados como `Suspenso` e `Não relacionado`, sem falsa entrada aos 90′.
  - `expulso`: Identificado com clareza como `Expulso aos X′` (ou `Expulso`), ícone 🟥 e nunca como `Substituído`.
  - `substituído`: Exibe `Substituído aos X′`, ícone 🔄.
  - `saves legados`: Fallback gracioso com inferência robusta quando `participation_state` não estiver presente.


