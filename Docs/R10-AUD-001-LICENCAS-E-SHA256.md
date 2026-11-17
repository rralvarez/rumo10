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
- **Regra de playback:** Execução através de nós `AudioBufferSourceNode` instanciados dinamicamente com ganho atenuado (`whistleGain = 0.55`) para garantir headroom e prevenir clipping digital na sobreposição polifônica.

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
