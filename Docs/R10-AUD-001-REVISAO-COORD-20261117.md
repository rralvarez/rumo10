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

## [ATUAL] 4. Auditoria Independente Codex e Correções Conclusivas (17/11/2026)

**Base auditada:** commit `6ac88bb5685f53cc2c34799635376c228393b497`  
**Parecer:** Devolvido para correção de três regressões P1 e uma falha P2.

### 4.1. Apontamentos do Codex e Soluções Adotadas

1. **P1-A | Deduplicação no F5 durante a reprodução (`public/assets/audio-engine.js`):**
   - *Problema:* O evento era registrado em `sessionStorage` e `this.handled` somente no callback assíncrono `onended`. Recarregamentos de página (F5) aos ~159ms de reprodução reexecutavam o som do início.
   - *Solução:* Registro imediato em `sessionStorage` e `this.handled` assim que a chamada `source.start(...)` ou oscilador sintetizado é agendada com sucesso (t=0). Criação de conjunto `this.inFlight` para blindar o agendamento assíncrono durante `bufferFor`.
   - *Evidências:* Teste determinístico 15 (`tests/test-aud001-webaudio.cjs`) e teste E2E 9 no Chrome real contra o IIS aprovados com silêncio absoluto no reload F5.

2. **P1-B | Preservação de eventos durante suspensão do AudioContext (`public/assets/audio-engine.js`):**
   - *Problema:* Se o `AudioContext` estivesse em estado `suspended` pelo navegador (política de autoplay), o evento não podia ser marcado como tratado nem descartado prematuramente.
   - *Solução:* Se `this.ctx.state !== 'running'`, o método retorna status `suspended` sem marcar handled. O evento permanece preservado em `pendingQueue` e o conjunto `inFlight` é limpo. Quando o usuário interage e dispara `AudioEngine.unlock()`, a fila é retomada e o áudio é reproduzido integralmente. Falhas terminais (HTTP 404 e erros de decode) mantêm marcação handled no catch para prevenir loops infinitos.
   - *Evidências:* Teste determinístico 16 aprovado.

3. **P1-C | Restauração de compatibilidade com efeitos legados sintetizados (`public/assets/audio-engine.js`):**
   - *Problema:* Os eventos `BALL_HIT_POST` e `PENALTY_AWARDED`, presentes na `main`, não estavam mapeados para reprodução.
   - *Solução:* Restauração via síntese Web Audio procedural pura (`playWhistle` e `playTone`) sem novos arquivos OGG externos. Efeitos roteados diretamente através do nó mestre (`this.master`), respeitando volume, mudo, prioridade (88 e 82) e deduplicação.
   - *Evidências:* Teste PHP 3.5, teste determinístico 17 e teste E2E 8 no Chrome aprovados.

4. **P2-A | Critério primário de frescor e histórico no mesmo minuto (`app/Presentation.php`):**
   - *Problema:* A verificação antiga baseava-se em `minute < currentMinute`. Em ações sucessivas no mesmo minuto sem avanço de relógio, um evento persistido da ação 1 era considerado fresco na ação 2.
   - *Solução:* A vinculação expressa aos `sound_event_ids` do feedback da última ação é o critério primário e exclusivo de frescor para eventos de partida. Eventos persistidos sem essa vinculação recebem obrigatoriamente `data-sound-historical="1"`, independentemente de igualdade ou avanço de minuto. Apitos derivados do estado atual da partida permanecem frescos durante a transição.
   - *Evidências:* Teste PHP 3.4 (`tests/test-aud001-audio.php`) aprovado.

5. **P2-B | Alinhamento Documental e Cálculo Real do Ganho dos Apitos:**
   - *Correção:* Substituição da menção incorreta a `whistleGain = 0.55` pela fórmula real da implementação: ganho por pulso `gain = 1 / count` (`count` = 1 para início, 2 para intervalo, 3 para fim de jogo) conectado ao nó de ganho mestre (`master gain`). Atualização dos documentos contratuais e marcos históricos.

---

### 4.2. Resultados Objetivos dos Testes

- **PHP CLI (`tests/test-aud001-audio.php`):** **127/127 verificações APROVADAS** (incluindo P2-A, P1-C, P0-4, tríplice evento aos 90 min, 8 arquivos físicos OGG com Vorbis e duração $\le$ 3.0s).
- **Web Audio Determinístico (`tests/test-aud001-webaudio.cjs`):** **98/98 verificações APROVADAS** (incluindo P1-A, P1-B, P1-C, F5 imediato, autoplay unlock, fila mista com OGG e sintetizados, descarte a partir do 4º evento contemporâneo).
- **Chrome E2E com Áudio Real no IIS (`tests/test-aud001-browser-e2e.cjs`):** **31/31 verificações APROVADAS** (resolução portável, HTTP 200 para todos os 8 áudios OGG, playback real com sobreposição, síntese de `BALL_HIT_POST` e `PENALTY_AWARDED`, e deduplicação no reload F5 aos 200ms de reprodução).
- **Regressões Sistêmicas:** UI-001 (5/5), F03 (17/17), Mobile UX (23/23), Placar e Carreira sem regressões.

A branch `feat/aud001-sons-partida` encontra-se 100% pronta e com evidências auditáveis. **Nenhum merge na main foi executado e nenhuma publicação na UOL foi acionada.**

