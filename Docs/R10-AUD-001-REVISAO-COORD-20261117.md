# R10-AUD-001 | Revisão da Coordenação Técnica

Data: 17/11/2026  
Base auditada: `feat/aud001-sons-partida` em `93d3491b31b5743edb87a50250ab03709192e1ba`  
Estado: **ENTREGA PARCIAL, NÃO HOMOLOGADA, NÃO AUTORIZADA PARA MERGE**.  
Destino: mesma branch, sem mudanças na main, UOL, Cron, banco de produção ou saves.

## Evidências verificadas diretamente no GitHub

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
