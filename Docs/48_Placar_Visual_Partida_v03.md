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
  - `tests/test-f03-corrections.php` (OK - 264 verificações cobrindo CSP, estados reais, precedência e minutos de substituição R4)
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

## Correção Final de Estados (OS-COR-F03-R3)

### 1. Não inventar informações de participação
- **Expulsão:** Exibe `"Expulso aos X′"` exclusivamente quando `red_card_minute` estiver explicitamente registrado no servidor. Se o minuto não estiver disponível, apresenta apenas `"Expulso"`, sem preencher com minuto atual ou saída de campo.
- **Atleta não relacionado:** Descrição neutra `"Você não foi relacionado para esta partida e acompanha o jogo de fora."`, sem inferir desgaste físico, lesão ou suspensão não comprovados no estado.
- **Eventos históricos:** Minuto atual da partida não é utilizado para preencher horários desconhecidos de eventos passados.

### 2. Encerramento somente pelo status efetivo do servidor
- **Status do servidor:** Partida encerrada é determinada estritamente por `$status === 'finalizada'`.
- **Acréscimos / Minuto >= 90:** Partida com `$status === 'em_andamento'` e minuto 93′ permanece ativa em 2º Tempo com o atleta em campo (`ON_FIELD`), sem declarar falso encerramento. O limite de 90 restringe-se exclusivamente ao preenchimento visual da barra de progresso.

## Correção de Precedência e Substituição (OS-COR-F03-R4)

### 1. Precedência do status da partida sobre stage
- O `$status` efetivo da partida (`$m['status']`) tem precedência absoluta sobre `$g['stage']`.
- Se `$m['status'] === 'em_andamento'`, a partida nunca é apresentada como `"Partida encerrada"` ou `"Escalado (titular)"`, mesmo se `$g['stage']` contiver transitoriamente `fim_semana` ou `pre_jogo`.
- `$g['stage']` atua exclusivamente como fallback caso `$m['status']` esteja ausente ou vazio.

### 2. Minuto verdadeiro da substituição (inclusive >= 90′)
- Quando `participation_state === 'SUBSTITUTED_OUT'`, o minuto registrado em `exit` é preservado e exibido fielmente (ex: `"Substituído aos 90′"` ou `"Substituído aos 92′"`).
- O limite `< 90` foi preservado exclusivamente na inferência heurística de saves legados sem estado canônico de substituição.
- Quando não houver minuto confiável em `exit`, apresenta apenas `"Substituído"`, sem criar horários fictícios.

## Correção da Seleção do Painel na Página Completa (OS-COR-F03-R5)

### 1. Seleção do painel da partida por status em andamento (`app/view.php`)
- **Problema:** Na renderização da página completa em `app/view.php`, a ramificação principal que selecionava o placar e as decisões da partida avaliava estritamente `elseif($g['stage']==='partida'):`. Se a partida estivesse com `$m['status'] === 'em_andamento'` e `$g['stage']` divergente (como `pre_jogo` ou `fim_semana`), o painel da partida não era selecionado, caindo inadvertidamente no banner pré-jogo ou no painel de pós-jogo.
- **Solução:** A condição foi ajustada para `elseif(($m && ($m['status']??'')==='em_andamento') || $g['stage']==='partida'): Presentation::score($g,$m);`, garantindo que qualquer partida com status efetivo em andamento renderize o painel da partida (placar, relógio, progresso e escolhas/card de avanço).
- **Validação:** Homologado na página completa através de `tests/test-f03-corrections.php` nos 4 cenários da R5:
  1. `status=em_andamento`, `stage=partida` -> Placar + decisões em campo.
  2. `status=em_andamento`, `stage=pre_jogo` -> Placar + decisões em campo (sem banner pré-jogo).
  3. `status=em_andamento`, `stage=fim_semana` (93′) -> Placar + decisões em campo (sem painel de apito final).
  4. Estados confirmados não em andamento:
     - 4A. `status=pre_jogo`, `stage=pre_jogo` -> Banner pré-jogo exibido (sem placar em andamento).
     - 4B. `status=finalizada`, `stage=fim_semana` -> Painel pós-jogo com apito final (sem placar em andamento).




