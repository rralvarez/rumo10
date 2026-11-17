# R10-AUD-001 | Licenças, Proveniência e Hashes SHA-256

**Data de verificação:** 17/11/2026  
**Repositório:** `rralvarez/rumo10`  
**Branch:** `feat/aud001-sons-partida`  
**Destino de distribuição:** `public/assets/audio/`

---

## 1. Inventário de Arquivos e Hashes SHA-256

| # | Arquivo OGG | Efeito(s) Atribuído(s) | Duração | Tamanho | SHA-256 do OGG Final | Origem / Amostra Original |
|---|---|---|---|---|---|---|
| 1 | `match_whistle.ogg` | Início (1x), Intervalo (2x), Fim (3x) | 0.54s | 9.857 B | `b85499315a2ed418fbb7c651e3b2a53be4507076e4585183661799194ddc97f9` | Rosa-Orenes256 (`538422`) |
| 2 | `yellow_card.ogg` | Cartão amarelo (advertência) | 0.54s | 9.857 B | `9943e9be0333a7df151f3e3dfa0e5959d8b8f402b523f21f1339a393fe069775` | Rosa-Orenes256 (`538422`) |
| 3 | `goal.ogg` | Gol marcado | 2.80s | 38.414 B | `6151eff7869e5a24019f0d2820c6acefdde58cdc58074bcc8e6ae84ffe7bfc84` | huubjeroen (`113698`) |
| 4 | `assist.ogg` | Assistência registrada | 2.80s | 46.796 B | `2a2c7bce7f8b28b6d035a716d5660f6cf08988531fbb3a0bfd9dce973fc8a800` | cognito perceptu (`57587`) |
| 5 | `substitution_out.ogg` | Saída do atleta de campo | 2.50s | 41.734 B | `788fa6cbecc9a04dcecb77b24f6876cd8a2afa32876e6645ff8c271fd5e8ae6e` | cognito perceptu (`57587`) |
| 6 | `red_card.ogg` | Cartão vermelho (expulsão) | 2.50s | 23.326 B | `01e382b354aa76fee1acc105e148c2cfe073962c3d266913f107a6fc86b8c656` | SpliceSound (`218318`) |
| 7 | `substitution_in.ogg` | Entrada do atleta em campo | 2.80s | 49.860 B | `1d9e91273033211f1ed0d6f3c6fbf67f4d155f6b0073697d75b4c508068eb01a` | NobodyYouKnowOf (`410868`) |
| 8 | `no_goal.ogg` | Finalização desperdiçada / Pênalti perdido | 2.95s | 40.128 B | `d9d1972ba0905a32dd67a3c73a24705c39232a70ddd8b6a6f9ded866cecfa302` | Universfield (`352718`) |

---

## 2. Detalhamento de Proveniência, Licenças e Edições

### 2.1. Apito de Partida (`match_whistle.ogg`)
- **Efeitos mapeados:** `MATCH_WHISTLE_START` (1 disparo), `MATCH_HALFTIME` (2 disparos sobrepostos em t0 e t0+180ms), `MATCH_WHISTLE_END` (3 disparos sobrepostos em t0, t0+180ms e t0+360ms).
- **Autor:** Rosa-Orenes256
- **Título original:** `Referee whistle sound.wav`
- **Página exata:** https://freesound.org/people/Rosa-Orenes256/sounds/538422/
- **Licença verificada:** Creative Commons 0 (CC0 1.0 Universal - Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `1bc56409d3439a16fd54bc8fa83affb7e2bc69519b15ac49979d924e895910bf`
- **SHA-256 do OGG final:** `b85499315a2ed418fbb7c651e3b2a53be4507076e4585183661799194ddc97f9`
- **Duração final:** 0.54s (recorte cirúrgico do sopro nítido com fade-out de 0.05s).
- **Regra de playback:** Execução através de nós `AudioBufferSourceNode` instanciados dinamicamente com atenuação proporcional calculada dinamicamente pelo número de pulsos simultâneos (`gain = 1 / count` por pulso, sendo `count = 1` no início, `count = 2` no intervalo e `count = 3` no fim de partida), roteados através do nó mestre (`master gain`) e respeitando o controle de volume/mudo para garantir headroom e prevenir clipping digital na sobreposição polifônica.

### 2.2. Cartão Amarelo (`yellow_card.ogg`)
- **Efeito mapeado:** `CARD_YELLOW`
- **Autor:** Rosa-Orenes256
- **Título original:** `Referee whistle sound.wav` (mesma gravação aprovada)
- **Página exata:** https://freesound.org/people/Rosa-Orenes256/sounds/538422/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `1bc56409d3439a16fd54bc8fa83affb7e2bc69519b15ac49979d924e895910bf`
- **SHA-256 do OGG final:** `9943e9be0333a7df151f3e3dfa0e5959d8b8f402b523f21f1339a393fe069775`
- **Duração final:** 0.54s.

### 2.3. Gol Marcado (`goal.ogg`)
- **Efeito mapeado:** `GOAL_SCORED`
- **Autor:** huubjeroen
- **Título original:** `goalloop.wav`
- **Página exata:** https://freesound.org/people/huubjeroen/sounds/113698/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `0abbf6108b66c93ec29c594e9a98c11d992e4d3e1cd4519de6a4ab70a0655a36`
- **SHA-256 do OGG final:** `6151eff7869e5a24019f0d2820c6acefdde58cdc58074bcc8e6ae84ffe7bfc84`
- **Duração final:** 2.80s (explosão de comemoração de torcida, início imediato, fade-out suave ao atingir 2.8s).

### 2.4. Assistência Registrada (`assist.ogg`)
- **Efeito mapeado:** `ASSIST_RECORDED`
- **Autor:** cognito perceptu
- **Título original:** `applause.wav`
- **Página exata:** https://freesound.org/people/cognito%20perceptu/sounds/57587/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `a0ea664b28c787822381c10eddbc77a220ab66e89b312536775c92d9313fbd26`
- **SHA-256 do OGG final:** `2a2c7bce7f8b28b6d035a716d5660f6cf08988531fbb3a0bfd9dce973fc8a800`
- **Duração final:** 2.80s (aplausos calorosos da torcida com fade-out de 0.2s).

### 2.5. Saída de Campo do Atleta (`substitution_out.ogg`)
- **Efeito mapeado:** `PLAYER_SUBSTITUTION_OUT`
- **Autor:** cognito perceptu
- **Título original:** `applause.wav` (mesma gravação aprovada)
- **Página exata:** https://freesound.org/people/cognito%20perceptu/sounds/57587/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `a0ea664b28c787822381c10eddbc77a220ab66e89b312536775c92d9313fbd26`
- **SHA-256 do OGG final:** `788fa6cbecc9a04dcecb77b24f6876cd8a2afa32876e6645ff8c271fd5e8ae6e`
- **Duração final:** 2.50s (aplausos de reconhecimento de saída com fade-out de 0.2s).

### 2.6. Cartão Vermelho / Expulsão (`red_card.ogg`)
- **Efeito mapeado:** `CARD_RED`
- **Autor:** SpliceSound
- **Título original:** `Referee whistle blow, gymnasium.wav`
- **Página exata:** https://freesound.org/people/SpliceSound/sounds/218318/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `7392e39a07d47ffbbf174677e7b82ab91fe936dbcf17a9c8d947ca763d1ad0b3`
- **SHA-256 do OGG final:** `01e382b354aa76fee1acc105e148c2cfe073962c3d266913f107a6fc86b8c656`
- **Duração final:** 2.50s (apito grave, prolongado e estridente de expulsão).

### 2.7. Entrada em Campo do Atleta (`substitution_in.ogg`)
- **Efeito mapeado:** `PLAYER_SUBSTITUTION_IN`
- **Autor:** NobodyYouKnowOf
- **Título original:** `crowd_cheering_and_clapping.wav`
- **Página exata:** https://freesound.org/people/NobodyYouKnowOf/sounds/410868/
- **Licença verificada:** CC0 1.0 Universal (Domínio Público).
- **Data de verificação:** 17/11/2026.
- **SHA-256 do arquivo original:** `1a9bff552c657a8cb0f0c67abb7e81a4bd2b39619be4cce0fa35de2848f1a081`
- **SHA-256 do OGG final:** `1d9e91273033211f1ed0d6f3c6fbf67f4d155f6b0073697d75b4c508068eb01a`
- **Duração final:** 2.80s (palmas e vibração da torcida pela entrada em campo).

### 2.8. Não Gol / Finalização Desperdiçada (`no_goal.ogg`)
- **Efeito mapeado:** `NO_GOAL` (chute para fora/defendido e pênalti desperdiçado)
- **Autor:** Universfield
- **Título original:** `universfield-crowd-disappointment-reaction-352718.mp3`
- **Página oficial:** https://pixabay.com/sound-effects/people-crowd-disappointment-reaction-352718/
- **Licença verificada:** Pixabay Content License (Livre para uso comercial e não comercial em plataformas e aplicações; proibida a redistribuição autônoma ou revenda isolada do arquivo de áudio).
- **Data de fornecimento pelo proprietário:** 17/11/2026.
- **SHA-256 do arquivo original (MP3):** `15e496de50c195819f16ea7324b504c59b416e7a0e81c07d665648ca40d80805`
- **SHA-256 do OGG final:** `d9d1972ba0905a32dd67a3c73a24705c39232a70ddd8b6a6f9ded866cecfa302`
- **Formato:** Ogg Vorbis | Estéreo | 48.000 Hz | 2,950 s | 40.128 bytes
- **Processamento:** corte de 3,12s para 2,95s com fade-out linear de 2,60s a 2,95s; sem alteração artificial de ganho; pico medido de aproximadamente -4,2 dBFS (ffmpeg volumedetect); ferramenta: ffmpeg/libvorbis.

---

## 3. Resolução do Bloqueio de Obtenção (P0-1)

- O download direto do Pixabay via CLI/curl havia sido impedido por barreira anti-robô (Cloudflare Turnstile, mitigação `Cf-Mitigated: challenge`, HTTP 403).
- Em cumprimento estrito às diretrizes de conformidade do projeto e à determinação da Coordenação Técnica de não forjar áudio substituto nem burlar proteções de rede, a integração aguardou a intervenção autorizada do proprietário.
- O proprietário realizou o download do MP3 autêntico pelo navegador em 17/11/2026 e forneceu o pacote `R10-AUD-001-no_goal-integracao.zip`.
- O hash SHA-256 do arquivo integrado corresponde estritamente ao artefato auditado (`d9d1972ba0905a32dd67a3c73a24705c39232a70ddd8b6a6f9ded866cecfa302`), com inventário de 8 arquivos OGG 100% completo e operacional.

---

## 4. Efeitos Legados Sintetizados em Web Audio (P1-C)

Para assegurar estrita retrocompatibilidade com marcações e regras esportivas legadas sem requerer arquivos de áudio adicionais na distribuição, dois efeitos permanecem sintetizados proceduralmente através de osciladores nativos da Web Audio API (`AudioEngine.playTone` e `AudioEngine.playWhistle`), roteados diretamente através do nó de ganho mestre (`this.master`):

1. **`BALL_HIT_POST` (Bola na Trave):**
   - **Mecanismo:** Oscilador de onda triangular a 160 Hz (duração 0.15s, ganho $0.8 \times \text{vol}$) seguido de oscilador senoidal a 80 Hz (duração 0.25s, ganho $0.9 \times \text{vol}$, offset +0.05s) simulando o impacto do poste e ressonância da trave.
   - **Prioridade:** 88 (precede `NO_GOAL` na fila de reprodução).
   - **Duração total:** 0.30s.

2. **`PENALTY_AWARDED` (Pênalti Assinalado):**
   - **Mecanismo:** Oscilador senoidal a 2.500 Hz com rampa exponencial descendente até $0.95 \times 2.500$ Hz (duração 0.35s, ganho atenuado linear com fade-out exponencial).
   - **Prioridade:** 82 (precede advertências e expulsões disciplinares).
   - **Duração total:** 0.35s.

Ambos os efeitos sintetizados respeitam o volume global, o modo mudo, a deduplicação em `sessionStorage` e a fila ordenada contemporânea.

---

## 5. Auditoria Independente Codex e Correções Conclusivas

Após a auditoria independente do commit `6ac88bb5`, foram identificados e sanados quatro apontamentos técnicos:

1. **P1-A | Deduplicação no F5 durante a reprodução:**
   - *Causa:* O registro em `sessionStorage` e `this.handled` ocorria apenas no evento assíncrono `onended`. Uma recarga de página (F5) em meio ao playback reexecutava o áudio.
   - *Solução:* Registro imediato em `this.handled` e `sessionStorage` no agendamento do primeiro disparo (`source.start(...)` ou oscilador). Adição de conjunto `this.inFlight` para blindar operações assíncronas.
   - *Evidência:* Teste determinístico 15 e teste E2E 9 no Chrome com recarga ativa aprovados.

2. **P1-B | Preservação de eventos durante suspensão do AudioContext:**
   - *Causa:* Se o `AudioContext` estivesse suspenso pelo navegador (política de autoplay), o evento não podia ser marcado como tratado precocemente nem descartado.
   - *Solução:* Verificação de `this.ctx.state !== 'running'`; retenção na `pendingQueue` sem gravação em `sessionStorage`, com liberação e reprodução imediata quando `AudioEngine.unlock()` é chamado pelo gesto do usuário. Falhas terminais (404/decode) continuam marcadas para evitar loops.
   - *Evidência:* Teste determinístico 16 aprovado.

3. **P1-C | Restauração de compatibilidade com efeitos sintetizados:**
   - *Causa:* Os efeitos sintetizados legados (`BALL_HIT_POST` e `PENALTY_AWARDED`) haviam sido omitidos no mapa de sons naturais.
   - *Solução:* Restauração via Web Audio puro conectados a `this.master`, com prioridades canônicas (88 e 82) e fila ordenada.
   - *Evidência:* Testes PHP 3.5, determinístico 17 e teste E2E 8 aprovados.

4. **P2-A | Critério primário de frescor e histórico no mesmo minuto:**
   - *Causa:* A mera igualdade `minute == currentMinute` em `Presentation.php` permitia que eventos persistidos de ações anteriores no mesmo minuto fossem reclassificados indevidamente como frescos.
   - *Solução:* Identidade expressa vinculada aos `sound_event_ids` do feedback da última ação como critério primário de frescor. Eventos persistidos sem essa vinculação recebem `data-sound-historical="1"`. Apitos de estado atual da partida permanecem frescos durante a transição.
   - *Evidência:* Teste PHP 3.4 aprovado.

---

## 6. Auditoria Independente Codex R2 e Correções Definitivas

1. **P1-C R2 | Fila Contemporânea Completa sem Descarte Arbitrário:**
   - Removido o limite posicional `slice(0, 3)`. Todos os eventos legítimos não-históricos e deduplicados por ID são agendados e reproduzidos na ordem de prioridade com espaçamento de 250ms.
   - Testado o cenário real quádruplo: Gol (100) + Pênalti Concedido (82) + Substituição (70) + Apito Final (50), com preservação e reprodução integral de todos os 4 sons.

2. **P2-A R2 | Ciclo de Vida de `current_command_sound_event_ids` Desacoplado de Feedback:**
   - No comando `advance`, o jogador pode entrar em campo aos 60 min enquanto `$g['feedback']` permanece `null`.
   - `Game::command()` inicializa `$g['current_command_sound_event_ids'] = []`, `emitSoundEvent()` adiciona cada ID emitido no comando, e `Presentation::matchSounds()` consome essa coleção como evidência primária de frescor quando `feedback` é `null`. No comando seguinte, a coleção é reiniciada e o evento passa a ser histórico.

3. **P2 Residual | Blindagem de Mute Durante Download Assíncrono:**
   - Em `AudioEngine.setMuted(true)`, todos os IDs em `inFlight` são marcados tratados.
   - Em `playDirect()`, se `this.handled.has(id)` for verdadeiro após `await bufferFor()`, o áudio retorna `{ status: 'cancelled' }` sem executar `source.start()`, impedindo vazamento de som caso o usuário desative o mute antes do término do download.
