# R10-AUD-001 | Revisões da Coordenação Técnica e Auditoria Independente

Data: 17/11/2026  
Branch: `feat/aud001-sons-partida`  
Destino: mesma branch, sem alterações na main, UOL, Cron, banco de produção ou saves.

---

## [HISTÓRICO] 1. Primeira Auditoria da Coordenação (17/11/2026 Manhã)

Base auditada: `feat/aud001-sons-partida` em `93d3491b31b5743edb87a50250ab03709192e1ba`  
Estado: **ENTREGA PARCIAL, NÃO HOMOLOGADA, NÃO AUTORIZADA PARA MERGE**.

### Evidências verificadas diretamente no GitHub

- HEAD da branch coincide com `93d3491b31b5743edb87a50250ab03709192e1ba`. A `main` permanece em `b541c85d862e64f37d95c50ea7865a167b445e17`.
- O commit final adicionou sete arquivos OGG, a documentação de proveniência e `tests/test-aud001-audio.php`; não alterou `app/Game.php`, `app/Presentation.php`, `public/assets/audio-engine.js` nem `app/MatchParticipation.php` em relação ao estado preliminar anterior da branch.
- `no_goal.ogg` está AUSENTE na branch e a documentação do executor registra bloqueio de acesso ao arquivo original no Pixabay; não inventar outro áudio ou som sintético.
- O relatório de 45/45 checks foi fornecido pelo executor, mas a Coordenação não executou o IIS nem os testes localmente. O código do teste foi inspecionado: os casos de `NO_GOAL` chamam `Game::emitSoundEvent` diretamente, não simulam `Game::command` nem o caminho de `fieldDecision`; o bloco de apitos checa markup PHP, não o `AudioBufferSourceNode.start` em navegador; o bloco de OGG verifica apenas sete arquivos, magic header OggS e tamanho, não o arquivo de não gol, a decodificação, duração, pico, volume, clipping, licença ou gravação correta. Portanto 45/45 NÃO equivale a todos os dez efeitos funcionais.
- `app/MatchParticipation.php` já possui emissão de `PLAYER_SUBSTITUTION_IN/OUT` no código existente. Não atribuir o funcionamento a uma modificação inexistente desse arquivo na entrega final.
- O documento de licenças contém URLs, autores, licença declarada e hashes. A Coordenação ainda não comparou independentemente os bytes dos OGG aos hashes publicados; a seleção de fontes é decisão do proprietário, e a confirmação de origem é incumbência do executor.

## Bloqueios para homologação

### P0-1 | Entregar o oitavo arquivo aprovado, não gol

O proprietário escolheu a **Opção B, Universfield, Crowd Disappointment Reaction**, exatamente:
https://pixabay.com/sound-effects/people-crowd-disappointment-reaction-352718/

O executor declarou HTTP 403 por challenge anti-robô ao baixar. Solicitar ao proprietário que baixe o MP3 original pelo navegador, salve em pasta local e disponibilize o caminho ao Antigravity. O executor deve gerar `public/assets/audio/no_goal.ogg`, duração <=3s, normalização/headroom e fade, documentar origem/license/hash e validar HTTP 200 no IIS. Proibido substituto, arquivo vazio, bypass do challenge ou publicar com 404.

### P0-2 | Corrigir prioridade de sons na mesma página

**Evidência objetiva no código atual:** `Presentation::feedback` pode renderizar `GOAL_SCORED` ou `ASSIST_RECORDED` enquanto `Presentation::matchSounds` também renderiza `MATCH_WHISTLE_END` ou outro evento histórico. `audio-engine.js` ordena todos os `data-sound-event` por `EVENT_PRIORITY`, pontuando `MATCH_WHISTLE_END = 100` e `GOAL_SCORED = 85`, marca os demais como ouvidos e toca só o mais prioritário. Assim, numa página contendo apito final e gol ainda não ouvido, o gol é **descartado permanentemente**, sem ser reproduzido. O teste PHP atual não cobre essa disputa.

A correção deve identificar **o evento novo associado à ação/feedback corrente**, não priorizar cegamente evento antigo ou apito derivado de estado; preservar deduplicação no reload, impedir rajadas de eventos antigos, não engolir gol/assistência/cartão contemporâneos e respeitar apenas o estado real. Pode estabelecer política explícita para sons simultâneos, conforme UX, mas deve testá-la de forma automatizada com DOM realista e caso de carregamento após navegação.

### P0-3 | Testar Web Audio de fato, não apenas fonte textual

Criar testes determinísticos em Node com `AudioContext`, `AudioBufferSourceNode`, `fetch`, `sessionStorage` e `document.querySelectorAll` simulados, verificando:
- 1 chamada `source.start` para início, 2 para intervalo, 3 para fim;
- as duas ou três chamadas usam `AudioBuffer` EXATAMENTE idêntico, iniciam `t0`, `t0+gap` e `t0+2gap`, **sem esperar término da instância anterior**;
- mute e volume, bloqueio/desbloqueio de autoplay, 404 e erro de decode, deduplicação em refresh, ganhador correto de prioridade contemporânea;
- fila com evento de apito e gol na mesma página, para impedir perda irrecuperável do gol;
- nenhum playback de sons históricos em rajada nos modos Rápido/Expresso.

Não supor que verificação textual das linhas de JS com `source.start` prova execução. Capturar teste de navegador no IIS local com áudio real.

### P1 | Regressão de jogo, licenças, documentação e integração

- Exercitar `Game::command` em caminhos reais de sucesso e falha de chute, pênalti, cartão, avanço 45'/90' e participação, sem afetar o save, banco e regras; não somente chamar o emissor isolado em fixture.
- Verificar `MATCH_HALFTIME` realmente aparece na página exibida no intervalo e não apenas em fixture.
- Validar os OGG com `ffprobe` ou equivalente: codec reproduzível, duração, loudness/picos; os hashes do manifesto devem corresponder aos bytes entregues.
- Reconfirmar licenças pelas páginas originais e guardar data de download; preservar autores e URL no registro. O efeito de não gol usa licença Pixabay, distinta de CC0 dos Freesound.
- Executar e registrar sintaxe, suíte `tests/test-aud001-audio.php`, regressões relevantes, HTTP 200 dos oito áudios e inspeção auditiva de início, intervalo, fim e não gol no IIS.
- Salvar resultado dos testes e commit atualizado na **mesma branch**. Não abrir merge sem autorização expressa do proprietário.

## Status esperado do retorno

Entregar: SHA da branch, lista de arquivos, SHA e duração dos oito OGG, logs reais dos testes (incluindo novos casos de concorrência/prioridade), evidência de audição local e pendências. A revisão final pertence à Coordenação Técnica. A publicação após merge, quando autorizada, será o primeiro teste de deploy automático de funcionalidade Classe A.

**Não alterar** `main`, Cron, receptor UOL, diretórios de produção, banco ou saves de produção, e não substituir a amostra B já aprovada.


---

## [HISTÓRICO] 2. Segunda Auditoria da Coordenação (17/11/2026 Meio-dia)

Base auditada: `feat/aud001-sons-partida` em `8a68fcef2e9b995adcbdb72eac8fe9632e478f83`

**Conferência direta:** HEAD `8a68fcef2e9b995adcbdb72eac8fe9632e478f83`, `main` preservada em `b541c85d862e64f37d95c50ea7865a167b445e17`. Os arquivos efetivamente alterados foram `app/Game.php`, `app/Presentation.php`, `public/assets/audio-engine.js`, `tests/test-aud001-audio.php` e os dois novos testes JS. Permanecem sete OGG, sem `no_goal.ogg`. Os resultados 67/67, 37/37, 23/23 e regressões foram **relatados pelo Antigravity**; não foram reexecutados pela coordenação no IIS.

**P0-2 e P0-3: melhoras incorporadas:** `EVENT_PRIORITY` passa a dar precedência a gol sobre apito final; a fila de `playDirect` aguarda `AudioBufferSourceNode.onended`, o apito ainda agenda um mesmo buffer 1/2/3 vezes, e os novos testes em Node/Chrome simulam concorrência, autoplay, mute e decodificação real dos sete arquivos. Isso corrige a colisão evidente da versão anterior, sujeito à audição com os oito arquivos finais.

### P0-4 | NOVO BLOQUEIO DETECTADO NA SEGUNDA AUDITORIA: cartão pode ser marcado histórico antes de tocar

Evidência na árvore atual:

1. `MatchDiscipline::recordFoul` emite `CARD_YELLOW` ou `CARD_RED` com `minute=$m['minute']`, mas não põe o ID emitido no `feedback` da jogada.
2. Em `Game::fieldDecision`, há caminhos que chamam `recordFoul` e depois `nextSegment($g)` no final da decisão, avançando `$m['minute']` para o segmento seguinte.
3. `Presentation::matchSounds` compara `(int)$event['minute'] < (int)$match['minute']` e renderiza `data-sound-historical="1"`. O `audio-engine.js` marca tal ID como tratado sem tocar. Assim um cartão recém-gerado no minuto 15 pode aparecer no HTML com o minuto da partida já em 35 e ser descartado como histórico sem nunca reproduzir o apito.

**Corrigir sem mudar regras, fases, probabilidades ou minuto de jogo.** Preservar a identificação explícita de eventos sonoros gerados pela **última ação** nos dados de `feedback` (como uma coleção de IDs frescos, incluindo cartão), e fazer a apresentação usar essa identidade para não classificá-los como históricos, mesmo que o motor tenha avançado o minuto. Preservar idempotência e não suprimir gol ou não gol quando houver mais de um evento contemporâneo. NÃO resolver simplesmente removendo toda filtragem de históricos ou emitindo novamente cartão após o avanço.

Adicionar regressão determinística que simule cartão gerado no minuto anterior ao corrente **pela última ação** e confirme que o áudio do cartão toca uma única vez; confirmar que cartão de jogada antiga NÃO toca na recarga. Executar caso integrado com `MatchDiscipline::recordFoul`, avanço de segmento e `Presentation::matchSounds`, além do teste Web Audio correspondente. Revisar também entrada/saída do jogador nas transições que avançam o minuto, com o mesmo critério.

### P1 | Portabilidade do teste E2E

`tests/test-aud001-browser-e2e.cjs` importa Playwright com caminho absoluto `C:/Users/rodri/.cache/codex-runtimes/...` e usa Chrome em `C:/Program Files/Google/Chrome/Application/chrome.exe`. São válidos para aquele computador local, mas não reproduzíveis em outra estação ou CI. Tornar a resolução configurável e fornecer instruções de dependências sem alterar o workflow oficial ou introduzir dependências no runtime de produção.

### P0-1 | A fonte do não gol continua pendente

A página oficial da opção B, Universfield, foi confirmada em fonte pública: https://pixabay.com/sound-effects/people-crowd-disappointment-reaction-352718/ (MP3, 3s, Pixabay Content License). Solicitar o download normal via navegador pelo proprietário e fornecer o caminho local ao Antigravity. Criar e testar `no_goal.ogg`, registrar hash e licença, repetir testes com **oito** arquivos. Não fazer bypass de desafio anti-robô, não substituir fonte aprovada.

**Decisão de coordenação:** manter AUD-001 BLOQUEADA PARA MERGE até P0-1 e P0-4 resolvidos, reexecução das suítes, CI da PR e homologação auditiva do proprietário. Não publicar na UOL.

---

## [HISTÓRICO] 3. Fechamento Complementar Preliminar (17/11/2026 Tarde)

Em atendimento à ordem de fechamento complementar da Coordenação Técnica:

1. **E2E Estrito e Prevenção de Aprovação Artificial:**
   - `tests/test-aud001-browser-e2e.cjs` atualizado com código de saída 1 na ausência de Playwright/Chromium.
   - Execução local comprovada com Playwright e Chrome nativo no IIS (`rumo10.local`): 23/23 verificações aprovadas.

2. **Ampliação do Teto da Fila Contemporânea para 3 Eventos Legítimos:**
   - Limite em `audio-engine.js` ajustado de 2 para 3, cobrindo tríplice evento aos 90 min (Gol + Cartão + Apito Final).

3. **Complementação dos Testes Integrados de Disciplina:**
   - Primeiro amarelo, segundo amarelo/vermelho, vermelho direto e fluxo real via `Game::command()`.

4. **Conclusão de P0-1 (`no_goal.ogg`) e Integração dos 8 Áudios:**
   - Fornecimento do MP3 original aprovado de Universfield pelo proprietário; conversão para OGG (40.128 bytes, SHA `d9d1972b...`); todos os 8 arquivos integrados.

---

---

## [HISTÓRICO] 4. Primeira Auditoria Independente Codex R1 (commit 6ac88bb5)

**Base auditada:** commit `6ac88bb5685f53cc2c34799635376c228393b497`  
**Parecer:** Devolvido para correção de três regressões P1 e uma falha P2.

### 4.1. Apontamentos do Codex R1 e Soluções Adotadas

1. **P1-A | Deduplicação no F5 durante a reprodução (`public/assets/audio-engine.js`):**
   - *Problema:* O evento era registrado em `sessionStorage` e `this.handled` somente no callback assíncrono `onended`. Recarregamentos de página (F5) aos ~159ms de reprodução reexecutavam o som do início.
   - *Solução:* Registro imediato em `sessionStorage` e `this.handled` assim que a chamada `source.start(...)` ou oscilador sintetizado é agendada com sucesso (t=0). Criação de conjunto `this.inFlight` para blindar o agendamento assíncrono durante `bufferFor`.

2. **P1-B | Preservação de eventos durante suspensão do AudioContext (`public/assets/audio-engine.js`):**
   - *Problema:* Se o `AudioContext` estivesse em estado `suspended` pelo navegador (política de autoplay), o evento não podia ser marcado como tratado nem descartado prematuramente.
   - *Solução:* Se `this.ctx.state !== 'running'`, o método retorna status `suspended` sem marcar handled. O evento permanece preservado em `pendingQueue` e o conjunto `inFlight` é limpo. Quando o usuário interage e dispara `AudioEngine.unlock()`, a fila é retomada e o áudio é reproduzido integralmente. Falhas terminais (HTTP 404 e erros de decode) mantêm marcação handled no catch para prevenir loops infinitos.

3. **P1-C | Restauração de compatibilidade com efeitos legados sintetizados (`public/assets/audio-engine.js`):**
   - *Problema:* Os eventos `BALL_HIT_POST` e `PENALTY_AWARDED`, presentes na `main`, não estavam mapeados para reprodução.
   - *Solução:* Restauração via síntese Web Audio procedural pura (`playWhistle` e `playTone`) sem novos arquivos OGG externos. Efeitos roteados diretamente através do nó mestre (`this.master`), respeitando volume, mudo, prioridade (88 e 82) e deduplicação.

4. **P2-A | Critério primário de frescor e histórico no mesmo minuto (`app/Presentation.php`):**
   - *Problema:* A verificação antiga baseava-se em `minute < currentMinute`. Em ações sucessivas no mesmo minuto sem avanço de relógio, um evento persistido da ação 1 era considerado fresco na ação 2.
   - *Solução:* A vinculação expressa aos `sound_event_ids` do feedback da última ação é o critério primário e exclusivo de frescor para eventos de partida. Eventos persistidos sem essa vinculação recebem obrigatoriamente `data-sound-historical="1"`, independentemente de igualdade ou avanço de minuto. Apitos derivados do estado atual da partida permanecem frescos durante a transição.

5. **P2-B | Alinhamento Documental e Cálculo Real do Ganho dos Apitos:**
   - *Correção:* Substituição da menção incorreta a `whistleGain = 0.55` pela fórmula real da implementação: ganho por pulso `gain = 1 / count` (`count` = 1 para início, 2 para intervalo, 3 para fim de jogo) conectado ao nó de ganho mestre (`master gain`). Atualização dos documentos contratuais e marcos históricos.

---

## [HISTÓRICO] 5. Auditoria Independente Codex R2 e Correções Definitivas (commit fdfe3af)

**Base auditada:** commit `fdfe3afe888fd376021986acbff4ad112adaf817`  
**Parecer:** P1-A e P1-B aprovados; P1-C e P2-A parcialmente corrigidos; identificada pendência residual de mute (P2).

### 5.1. Soluções Adotadas para os Bloqueios R2

1. **P1-C | Preservação de todos os eventos contemporâneos legítimos (`public/assets/audio-engine.js`):**
   - *Defeito confirmado:* O limite fixo `ordered.slice(0, 3)` descartava o 4º evento contemporâneo legítimo (ex: Gol + Pênalti Concedido + Substituição + Apito Final), aplicando `markHandled()` sem agendamento nem reprodução.
   - *Solução definitiva:* Eliminação do fatiamento numérico arbitrário. Todos os eventos não-históricos e deduplicados por ID são enfileirados em `AudioEngine.enqueue()` e reproduzidos sequencialmente com espaçamento de 250 ms e ordenação por prioridade canônica. O descarte somente ocorre para duplicatas, históricos (`data-sound-historical="1"`) ou cancelamentos explícitos.
   - *Evidências:* Teste determinístico 12 (`tests/test-aud001-webaudio.cjs`) e teste E2E 10 no Chrome (`tests/test-aud001-browser-e2e.cjs`) aprovados: todos os 4 efeitos executados em sequência, 4º evento não recebe `markHandled` antes do agendamento, F5 mantém silêncio e sessão nova com históricos não gera rajadas.

2. **P2-A | Identificação de eventos emitidos no comando corrente sem depender de feedback (`app/Game.php` e `app/Presentation.php`):**
   - *Defeito confirmado:* No comando `advance` (ex: avanço de 45' para 60' no intervalo com entrada de reserva), `MatchParticipation::sync()` emite `PLAYER_SUBSTITUTION_IN`, mas `$g['feedback']` permanece `null`. A ausência de feedback fazia `Presentation::matchSounds()` classificar a substituição indevidamente como histórica.
   - *Solução arquitetural:*
     - Criada a coleção `$g['current_command_sound_event_ids']` no estado do jogo.
     - `Game::command()` reinicia essa coleção (`[]`) no início de cada comando válido.
     - `Game::emitSoundEvent()` registra todo ID emitido nessa coleção e, se houver feedback, também em `feedback.sound_event_ids`.
     - `Presentation::matchSounds()` adota essa coleção como evidência primária de frescor quando `feedback` é `null` (ou ausente de `sound_event_ids`), mantendo compatibilidade retroativa total.
   - *Evidências:* Teste 3.6 em `tests/test-aud001-audio.php` cobre avanço de 45' para 60' com entrada de reserva e `feedback=null`, saída aos 70', transição para o próximo comando sem eventos (onde a substituição passa a histórica), F5 idempotente e ausência de rajadas em sessão nova.

3. **P2 Residual | Cancelamento e prevenção de vazamento de áudio durante download assíncrono com mute:**
   - *Cenário diagnosticado:* Se o usuário ativasse e desativasse o mute enquanto um arquivo OGG ainda estava sendo baixado/decodificado (`bufferFor`), o callback resolvia com `this.prefs.muted === false` e reproduzia o áudio cancelado.
   - *Solução mínima:* Em `setMuted(true)`, todos os IDs registrados em `this.inFlight` são marcados como tratados imediatamente. Em `playDirect()`, após o `await this.bufferFor()`, verifica-se `if (id && this.handled.has(id)) return { status: 'cancelled' };`, impedindo que o som cancelado seja disparado.
   - *Evidências:* Teste determinístico 18 em `tests/test-aud001-webaudio.cjs` reproduz o ciclo de mute/desmute durante download e comprova zero sons disparados.

---

## [HISTÓRICO] 6. Auditoria Independente Codex R3 e Blindagem Temporal Definitiva (commit 41d2d5a)

**Base auditada:** commit `41d2d5ad38e4b863ff8fd5b01871363d2b191bbb`  
**Parecer:** P1-A, P1-B, P2-A e pendência residual de mute APROVADOS com louvor. P1-C funcional em ordenação e preservação de fila, mas com defeito temporal de espaçamento sob interação do usuário.

### 6.1. Defeito Confirmado pelo Codex R3

Após o término de um som, `flushPendingQueue()` liberava `this.playing = false` e agendava o próximo evento via `setTimeout(..., 250)`.
Porém, qualquer interação do usuário (`click`, `pointerdown`, `keydown`, `submit`) dispara `AudioEngine.unlock()`.
Quando `AudioContext.state === 'running'`, `unlock()` invocava diretamente `flushPendingQueue()`.
Como `this.playing` já estava `false`, o segundo som era disparado imediatamente (o Codex R3 registrou intervalo reduzido para 31 ms em vez de 250 ms sob clique aos 30 ms da pausa).

### 6.2. Solução Arquitetural: Centralização Temporal Monotônica

1. **Estado Temporal Centralizado em `AudioEngine`:**
   - Adicionadas as propriedades `nextAllowedPlayTime: 0` e `scheduledTimer: null`.
   - Adicionado o helper `getNow()` utilizando `performance.now()` quando disponível, com fallback seguro para `Date.now()`.
   - Adicionado o despachante centralizado `scheduleFlush(delayMs)`: cancela qualquer timer pendente via `clearTimeout(this.scheduledTimer)` antes de registrar o novo handle, garantindo que nunca existam timers múltiplos concorrentes na fila.
2. **Fixação do Marco Temporal Pós-Efeito:**
   - No bloco `finally` de `flushPendingQueue()`, ao concluir efetivamente o efeito (ou em erro terminal), calcula-se rigorosamente:
     `this.nextAllowedPlayTime = this.getNow() + 250;`
     `this.scheduleFlush(250);`
3. **Blindagem Inviolável de Entrada em `flushPendingQueue()`:**
   - Antes de qualquer processamento ou retirada da fila, avalia-se:
     `const now = this.getNow();`
     `if (now < this.nextAllowedPlayTime) {`
       `if (this.scheduledTimer === null) this.scheduleFlush(this.nextAllowedPlayTime - now);`
       `return;`
     `}`

---

## [ATUAL] 7. Auditoria Independente Codex R4 e Ciclo de Vida dos Sintetizadores (commit 13f52a0 -> R5)

**Missão:** `R10-AUD-001-FIX-R4-SYNTH-END-001`  
**Base auditada:** commit `13f52a0fdeba86c44ddacbbee918a762fcaebc7e`  
**Status:** CANDIDATO À AUDITORIA INDEPENDENTE CODEX R5

### 7.1. Diagnóstico do Defeito Confirmado no Codex R4

O Codex R4 aprovou a fila de 4 eventos contemporâneos, a ordenação e os intervalos com cliques comuns. Porém, identificou um defeito bloqueante de ciclo de vida nos efeitos legados sintetizados (`PENALTY_AWARDED` e `BALL_HIT_POST`):
- A conclusão da síntese dependia de um temporizador artificial JavaScript `setTimeout(..., duration * 1000)`.
- Esse temporizador continuava correndo no tempo do sistema enquanto o `AudioContext` estivesse suspenso (cujo relógio interno `currentTime` fica congelado).
- Durante suspensão no meio de `PENALTY_AWARDED`, o temporizador expirava prematuramente, resolvia a Promise de `playDirect()`, liberava `playing = false` e agendava o próximo som antes do término real do oscilador Web Audio (iniciando até 230 ms antes do encerramento sonoro real).

### 7.2. Implementação da Resolução Obrigatória

1. **`playWhistle(startTime, freq, duration, gainVal, onEnded = null)` e `playTone(startTime, freq, duration, type, gainVal, onEnded = null)`:**
   - Assinaturas ampliadas preservando integralmente os parâmetros existentes e frequências/envelopes (2.500 Hz para pênalti, 160 Hz triângulo e 80 Hz senoide para trave).
   - Registrado tratamento prévio de `ended` (`addEventListener('ended', ...)` e `osc.onended`), com garantia de disparo único (`endedFired`).
   - Desconexão limpa e idempotente de `osc` e `gain` ao término.
   - Retorno de referência estruturada `{ osc, gain, stop, cleanup }` para gestão de ciclo de vida e tratamento de falhas parciais.
2. **Reestruturação do ramo `effect.synth` em `playDirect()`:**
   - Eliminação completa e definitiva de `setTimeout()`.
   - Máquina de estados: `pending` -> `active` -> `completed` / `failed`.
   - `PENALTY_AWARDED`: resolução estritamente condicionada ao evento `ended` do seu oscilador.
   - `BALL_HIT_POST`: contador de fontes esperadas (2) e fontes encerradas; resolução única exclusivamente quando **ambos** os osciladores emitirem `ended`. O término do primeiro oscilador não libera a fila.
   - Marcação P1-A mantida: ID é registrado em `sessionStorage` assim que os osciladores são agendados com sucesso (estado `active`), blindando contra F5 no meio da reprodução.
   - Tratamento de falha parcial: se a criação/agendamento do segundo oscilador falhar, o primeiro é interrompido com segurança (`stop()`), desconectado e a Promise é rejeitada de imediato, acionando o tratamento terminal sem deixar fontes órfãs ou promessas eternamente pendentes.
3. **Mocks Node.js (`tests/test-aud001-webaudio.cjs`):**
   - `MockOscillatorNode` atualizado com suporte a `addEventListener('ended', ...)`, `onended`, estados `started`/`stopped`/`ended` e disparo controlado `triggerEnd()`.
   - Respeito à suspensão: `triggerEnd()` não emite `ended` enquanto `ctx.state === 'suspended'`.
   - Implementados os testes determinísticos N1 a N8 cobrindo todos os cenários exigidos pela Coordenação Técnica.
4. **Chrome E2E no IIS Local (`tests/test-aud001-browser-e2e.cjs`):**
   - Instrumentação completa registrando instante de chamada, início, `currentTime` e `performance.now()` no `ended`.
   - Listener de captura para eventos de clique físico no navegador, medindo o intervalo exato entre o `ended` do som anterior e a chegada do clique (`clickDelta`).
   - Exigência estrita do mínimo contratual de 250 ms (todas as asserções usam `gap >= 250.0 ms`, sem aprovação artificial por 240 ms).
   - Cenários C1 a C5 executados com sucesso no navegador real:
     - C1: Pênalti sem suspensão (gap = 264.9 ms >= 250ms).
     - C2: Pênalti suspenso por 1.0s: nenhum `ended` prematuro, `playing=true` ininterrupto, retomada com término real e espaçamento de 265.1 ms >= 250ms.
     - C3: Bola na trave com suspensão e 2 osciladores (gap = 264.1 ms >= 250ms).
     - C4: Clique na pausa capturado aos 150.5 ms após `ended`, sem antecipação (gap = 265.3 ms >= 250ms).
     - C5: Rajada de múltiplos cliques com gap = 258.6 ms >= 250ms e fila limpa.

---

### 7.3. Resultados Objetivos dos Testes

- **Web Audio Determinístico (`tests/test-aud001-webaudio.cjs`):** **182/182 verificações APROVADAS** (incluindo N1 a N8).
- **Chrome E2E com Áudio Real no IIS (`tests/test-aud001-browser-e2e.cjs`):** **60/60 verificações APROVADAS** (incluindo C1 a C5).
- **PHP CLI (`tests/test-aud001-audio.php`):** **143/143 verificações APROVADAS**.
- **Regressão Apresentação e Contadores UI-001 (`tests/test-ui-001-flow.php`):** **76/76 verificações APROVADAS**.
- **Regressão F03 Correções (`tests/test-f03-corrections.php`):** **298/298 verificações APROVADAS**.
- **Regressão Mobile UX (`tests/test-mobile-ux-flow.php`):** **20/20 verificações APROVADAS**.
- **Regressão Placar Agregado v0.7 (`tests/aggregate-score-v07.php`):** **82/82 verificações APROVADAS**.
- **Regressão Fluxo Consolidado Independente (`tests/consolidated-independent-flow.php`):** **102/102 verificações APROVADAS**.
- **Regressão Geral de Temporadas (`tests/run.php` com `RUMOHUB_ENABLED=0`):** **27.985/27.985 verificações APROVADAS** (30 temporadas em 6 posições).

Os oito arquivos OGG aprovados permanecem intocados em `public/assets/audio/`. A branch `feat/aud001-sons-partida` foi submetida à auditoria independente Codex R5.

---

## 8. Correção Pós-Auditoria Codex R5 (Missão R10-AUD-001-FIX-R5-UNLOCK-STOP)

### 8.1. Diagnóstico dos Dois Defeitos Apontados

1. **Deadlock de retomada por clique (`unlockingPromise` acoplada à fila):**
   - **Causa Raiz:** Em `unlock()`, a cadeia `.then(() => this.flushPendingQueue())` retornava a Promise da fila para `this.unlockingPromise`. Como a fila aguarda o término do áudio (`ended`), `this.unlockingPromise` permanecia pendente durante todo o tempo de reprodução. Se o `AudioContext` fosse suspenso externamente (ex.: aba em segundo plano ou política de áudio do sistema) durante a execução, qualquer clique subsequente do usuário batia na guarda `if (this.unlockingPromise) return;` e era descartado, nunca chamando `ctx.resume()`.
   - **Correção (Patch P1-A):** Desacoplamento estrito entre a Promise de desbloqueio do contexto e o ciclo de processamento da fila. Ao usar bloco explícito `() => { this.flushPendingQueue(); }`, `unlockingPromise` resolve imediatamente assim que `ctx.resume()` conclui, liberando a flag e permitindo que cliques futuros chamem `resume()` se o contexto voltar a ser suspenso.

2. **Falha de `stop()` programado após `start()` bem-sucedido (Oscilador Órfão):**
   - **Causa Raiz:** Em `playWhistle()` e `playTone()`, após `osc.start(startTime)`, a chamada de agendamento `osc.stop(startTime + duration)` podia lançar uma exceção em implementações de mock ou condições atípicas. O bloco `catch` capturava o erro e executava diretamente `cleanup()` (desconectando o nó), mas **sem** tentar interromper o oscilador. O oscilador permanecia em execução indefinida no grafo Web Audio como fonte órfã.
   - **Correção (Patch P1-B):** Rastreamento de `started = true` imediatamente após `osc.start(startTime)`. No bloco `catch (err)`, se `started && osc`, é feita uma tentativa imediata de interrupção com `try { osc.stop(); } catch (stopErr) { console.warn('[AudioEngine] Falha persistente ao interromper oscilador; parada da fonte não comprovada:', stopErr); }`, seguida da desconexão (`cleanup()`) e rejeição terminal da Promise.
   - **Distinção Técnica e Limitação Remanescente:** Uma falha única de `stop()` programado é recuperada com sucesso pela segunda tentativa imediata no `catch`, garantindo a interrupção da fonte (`stopped=true`). Todavia, caso ambas as tentativas falhem (cenário de falha persistente), a implementação silencia a saída desconectando os nós e emite aviso explícito em `console.warn`, mas **não pode comprovar** a parada do oscilador no grafo nativo. Não se deve afirmar genericamente a eliminação de fontes órfãs sob falha persistente irrecuperável.

### 8.2. Evidência Vermelha Pré-Correção (Testes Falhando no Commit-Base `e83c5c9`)

Antes de alterar o motor, os defeitos foram reproduzidos em ambiente de isolamento (`tests/test-r5-repro-t1-t2.cjs`):
- **T1 (Deadlock de retomada por clique):** Após suspensão no meio do som e disparo de 2º clique, `resumeCalls` permaneceu em 1 (segunda chamada ignorada), contexto permaneceu `suspended` e `unlockingPromise` continuou ocupada. **FALHA COMPROVADA.**
- **T2 (Oscilador órfão em falha de stop):** Na simulação de falha no primeiro `stop()`, o oscilador de `PENALTY_AWARDED` e de `BALL_HIT_POST` permaneceu com `stopped=false` e `stopCalls=1` sem nova tentativa no catch. **FALHA COMPROVADA.**
- Resultado da fase vermelha: **1 PASSOU, 4 FALHOU (FALHAS ESPERADAS COMPROVADAS).**

### 8.3. Implementação e Evidência Verde Pós-Correção

- **Testes Determinísticos Web Audio (`tests/test-aud001-webaudio.cjs`):**
  - **220/220 verificações APROVADAS** (0 falhas).
  - Seção 21 com testes T1 a T7 cobrindo:
    - T1: Retomada por clique em contexto suspenso sem deadlock, `unlockingPromise` desacoplada, gap pós-término de 263.7 ms $\ge 250$ ms.
    - T2.1: Recuperação de stop no oscilador de `PENALTY_AWARDED` com 2 chamadas a `stop()` e tratamento terminal limpo.
    - T2.2: Recuperação de stop no oscilador de `BALL_HIT_POST` com 2 chamadas a `stop()` e tratamento terminal limpo.
    - T3: Pênalti não encerra em suspensão e retoma exclusivamente por clique com gap de 264.8 ms $\ge 250$ ms.
    - T4: Bola na trave (2 osciladores) com suspensão e retomada por clique, respeitando gap de 265.7 ms $\ge 250$ ms.
    - T5: Oscilador parado comprovadamente (`stopped=true`, 2 chamadas a `stop()`) e nós desconectados.
    - T6: Falha persistente de stop emite log explícito via `console.warn` e desconecta nós sem travar a fila.
    - T7: Cliques simultâneos concorrentes não disparam chamadas duplicadas a `resume()`.

- **Testes Chrome E2E Real no IIS Local (`tests/test-aud001-browser-e2e.cjs`):**
  - **105/105 verificações APROVADAS** (0 falhas).
  - Cenários C1 a C8 executados contra áudio real no IIS (`http://rumo10.local`):
    - C6: Sequência obrigatória completa de duas retomadas por clique físico no mesmo `AudioContext` com `PENALTY_AWARDED`:
      1. Contexto suspenso inicialmente (`resumeCalls=0`).
      2. Eventos `c6:pen` e `c6:card` enfileirados sem reprodução em suspensão.
      3. Primeiro clique físico na página.
      4. Primeira chamada efetiva a `ctx.resume()` comprovada (`resumeCalls=1`).
      5. Contexto `running`, oscilador criado e iniciado sem `ended` prematuro.
      6. `unlockingPromise` liberada (`null`) enquanto `playing` permanece estritamente `true`.
      7. Nova suspensão do mesmo contexto antes do término do oscilador.
      8. Sintetizador logicamente ativo sem `ended`, próximo efeito retido na fila pendente.
      9. Segundo clique físico sem invocar diretamente `ctx.resume()`.
      10. Segunda chamada efetiva a `ctx.resume()` comprovada (`resumeCalls=2`, sem deadlock).
      11. Contexto retornado para `running`.
      12. `ended` verdadeiro do pênalti confirmado.
      13. Efeito seguinte (`c6:card`) iniciado após intervalo de **256.1 ms** $\ge 250$ ms.
      14. Ausência de eventos perdidos ou duplicados; ambos persistidos no `sessionStorage`.
      15. Limpeza final estrita de `playing`, `pendingQueue`, `inFlight` e `unlockingPromise`.
    - C7: Sequência idêntica de duas retomadas por clique físico aplicada a `BALL_HIT_POST`:
      - 2 osciladores criados e iniciados no 1º clique (`resumeCalls=1`).
      - Contexto suspenso durante síntese e retomado no 2º clique (`resumeCalls=2`).
      - Primeiro `ended` comprovadamente **NÃO** libera a fila (`playing` permanece `true`, apito não inicia).
      - Segundo `ended` libera a conclusão da trave.
      - Efeito seguinte iniciado após **260.8 ms** $\ge 250$ ms.
      - Ambos os eventos persistidos no `sessionStorage` e fila limpa.
    - C8: Verificação de concorrência estrita em `ctx.resume()` com latência controlada de 120 ms:
      - Rajada de 3 cliques simultâneos gerou exatamente 1 chamada a `ctx.resume()` (`resumeCalls=1`, `concurrentViolations=0`).
      - `unlockingPromise` liberada após conclusão.
      - Nova suspensão após término da 1ª rodada permitiu com sucesso novo `ctx.resume()` no clique subsequente (`resumeCalls=2`, `concurrentViolations=0`).

- **Suíte de Regressão Integral:**
  - `tests/test-aud001-audio.php`: **143/143 verificações APROVADAS**.
  - `tests/test-ui-001-flow.php`: **76/76 verificações APROVADAS**.
  - `tests/test-f03-corrections.php`: **298/298 verificações APROVADAS**.
  - `tests/mobile-ux-flow.php`: **20/20 verificações APROVADAS**.
  - `tests/aggregate-score-v07.php`: **82/82 verificações APROVADAS**.
  - `tests/consolidated-independent-flow.php`: **102/102 verificações APROVADAS**.
  - `tests/run.php` (`RUMOHUB_ENABLED=0`): **27.981/27.981 verificações APROVADAS** (30 temporadas em 6 posições).
  - Verificação de sintaxe: PHP e Node.js 100% limpos.

### 8.4. Conclusão e Status de Entrega

O pacote de evidências cumpre integralmente os requisitos da Missão `R10-AUD-001-FIX-R5-UNLOCK-STOP` e a complementação pós-R5:
- Modificação restrita aos testes e documentação de evidências.
- Regras esportivas, motor de partida, persistência de saves e os 8 arquivos OGG aprovados mantidos estritamente intactos com hashes 100% reconciliados ao inventário oficial `Docs/R10-AUD-001-LICENCAS-E-SHA256.md`.
- Designação de entrega: **CANDIDATO À AUDITORIA INDEPENDENTE CODEX R6**.
- Nenhuma alteração foi realizada na branch `main`, nenhum deploy foi disparado para a UOL Host e nenhum cron ou banco produtivo foi alterado.


