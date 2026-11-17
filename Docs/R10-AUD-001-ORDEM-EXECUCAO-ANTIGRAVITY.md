# R10-AUD-001 | Ordem de execução para Antigravity

**Emissão:** 17/11/2026  
**Coordenação:** Rumo10, Coordenação Técnica  
**Status:** implementação local pendente; código preliminar em branch remota  
**Branch exclusiva:** `feat/aud001-sons-partida`  
**Ponto de partida:** `main` no merge `b541c85d862e64f37d95c50ea7865a167b445e17` da PR #9.  
**Contrato detalhado:** `Docs/R10-AUD-001-Contrato-Sonoro.md`.  

## Missão

Implementar e testar **localmente** a experiência sonora da partida, usando exclusivamente as fontes já aprovadas pelo proprietário. O resultado deve ser entregue na **mesma branch** do GitHub, sem integração em `main`, sem publicação UOL, sem alterações no Cron, no receptor, no banco, nas regras de jogo ou nos saves.

A nova automação de publicação está ativa e o Cron foi homologado com retorno `infrastructure-held` após o merge da PR #9; **a primeira publicação funcional automatizada ainda não foi demonstrada**. Esta implementação será candidata à primeira prova após homologação e autorização expressas.

## Iniciar no repositório local existente

1. Inspecione `git status` e proteja quaisquer alterações locais preexistentes, sem sobrescrevê-las.
2. Faça `git fetch origin` e selecione a branch existente `feat/aud001-sons-partida`, criando o tracking local somente se necessário. Não recrie a branch a partir de `main` nem faça reset destrutivo.
3. Leia `Docs/R10-AUD-001-Contrato-Sonoro.md`, `app/Game.php`, `app/Presentation.php`, `public/assets/audio-engine.js`, `app/PlayMode.php`, `app/MatchDiscipline.php`, `app/MatchParticipation.php`, `public/assets/game.js` e os testes relevantes.
4. Trate as alterações já commitadas na branch como **protótipo a auditar**, não como solução homologada. Corrija onde necessário.

## Decisões aprovadas, não reabrir escolha

Dez efeitos, mas somente **oito arquivos finais OGG**, hospedados em `public/assets/audio/`.

| Efeito(s) | Arquivo | Origem aprovada |
| --- | --- | --- |
| Apito de início (1 disparo), intervalo (2) e fim (3) | `match_whistle.ogg` | Rosa-Orenes256, https://freesound.org/people/Rosa-Orenes256/sounds/538422/ |
| Gol | `goal.ogg` | huubjeroen, https://freesound.org/people/huubjeroen/sounds/113698/ |
| Não gol | `no_goal.ogg` | **OPÇÃO B**, Crowd Disappointment Reaction, Universfield, https://pixabay.com/sound-effects/people-crowd-disappointment-reaction-352718/ |
| Assistência | `assist.ogg` | cognito perceptu, https://freesound.org/people/cognito%20perceptu/sounds/57587/ |
| Cartão amarelo | `yellow_card.ogg` | Rosa-Orenes256, mesma origem do apito |
| Cartão vermelho | `red_card.ogg` | SpliceSound, https://freesound.org/people/SpliceSound/sounds/218318/ |
| Entrada do jogador | `substitution_in.ogg` | NobodyYouKnowOf, https://freesound.org/people/NobodyYouKnowOf/sounds/410868/ |
| Saída do jogador | `substitution_out.ogg` | cognito perceptu, mesma origem da assistência |

**Regra dos apitos, inegociável:** reproduzir sempre o **mesmo buffer da mesma amostra**. Início = 1 instância; intervalo = 2 instâncias; fim = 3 instâncias. Segundo e terceiro disparos iniciam alguns milissegundos depois do anterior, **enquanto o anterior ainda toca**, sem concatenar ou aguardar áudio completo. A implementação preliminar usa espaçamento de 180 ms, ajustável após audição, com instâncias `AudioBufferSourceNode` independentes e ganho controlado contra clipping.

**Não gol:** fonte B já aprovada, sem nova votação. Duração final alvo máxima de 3 segundos, com reação de torcida natural.

## Entrega dos áudios e evidências

- Obter arquivos **originais** de fontes licenciadas, sem extrair gravações de transmissões ou incorporar prévias sem verificar sua qualidade. Se acesso ao Freesound exigir autenticação ou algum download não for possível, documentar exatamente qual fonte está bloqueada e solicitar somente os arquivos originais faltantes ao proprietário; **nunca inventar binários, usar sons alternativos ou gerar clipes silenciosos para encerrar a tarefa**.
- Recortar/normalizar cada arquivo em OGG de até 3 segundos, com fade curto e volume consistente, sem música/locução, respeitando as oito fontes e o mesmo apito para os três momentos.
- Registrar em `Docs/R10-AUD-001-LICENCAS-E-SHA256.md` o autor, página exata, licença verificada, data de acesso, hash SHA-256 da origem, hash SHA-256 do OGG final, duração, trecho/edição e atribuições. A seleção inicial aponta CC0 no Freesound e Pixabay Content License no não gol, **mas é preciso verificar isso novamente antes de distribuir os sons**.
- Usar somente `/assets/audio/*.ogg` no site, sem CDN nem APIs externas. Preservar a CSP existente.
- Não adicionar arquivos de áudio sem licença verificada ao GitHub; caso falte autorização de distribuição, indicar o bloqueio e aguardar decisão do proprietário.

## Auditoria da implementação parcial na branch

Arquivos já modificados: `public/assets/audio-engine.js`, `app/Presentation.php` e `app/Game.php`.

1. **Eventos:** conferir origem e cronologia dos IDs persistentes; preservar deduplicação por lance, estágio e partida; evitar repetir áudio ao atualizar ou reenviar. A mudança atual adiciona `NO_GOAL` em falha de chute e pênalti, e `MATCH_HALFTIME` no período 45. Inspecionar condições de estágio, momentos de transição e eventual ausência de disparo.
2. **Prioridades:** gol prevalece sobre assistência no mesmo lance; não ocultar silenciosamente uma notificação importante por causa de um apito antigo. Auditar a escolha `EVENT_PRIORITY` e os eventos já presentes no DOM, inclusive eventos históricos.
3. **Fila e carregamento:** verificar se `fetch`/decode, `AudioContext.resume`, navegação de página e desbloqueio de autoplay permitem tocar o som depois do clique e não disparam rajadas em páginas seguintes. Em erro 404 de asset, não tocar sintetizador como substituto e registrar de forma diagnóstica sem vazar segredos.
4. **Controles:** preservar mute, volume, preferências em `localStorage`, não tocar enquanto mudo, limitar soma de ganhos para apitos sobrepostos.
5. **Substituições:** `PLAYER_SUBSTITUTION_IN/OUT` podem estar no mapa de áudio, mas só devem tocar quando houver eventos reais, de identidade canônica. Não criar substituições fictícias. Se a F04 ainda não fornecer a informação, reportar claramente `pronto para integrar, evento ainda ausente`.
6. **Três modos:** no Normal, eventos sonoros por ação relevante, sem alteração da lógica esportiva. No Rápido e Expresso, avaliar apenas contexto de resumo/partida real, sem disparar todos os eventos históricos de uma simulação nem alterar a cadência normal do modo. Se não houver evento canônico para um som, não inventar um.
7. **Escopo:** não adicionar música ambiente, torcida contínua, som de trave, trilha, novos eventos fictícios ou lógica de banco. Não expandir AUD-001 para F04/BAL-001/CAR-001.
8. **Integridade:** preservar renderização e comportamento do jogo caso o áudio não esteja disponível. Evitar efeitos duplicados de gol e assistência, inclusive porque `Presentation::feedback` e `Presentation::matchSounds` podem renderizar o mesmo ID.

## Testes locais obrigatórios

- `php -l app/Game.php`, `php -l app/Presentation.php`, `node --check public/assets/audio-engine.js`.
- Executar testes existentes relevantes em `tests/` e adicionar regressões específicas para `NO_GOAL`, intervalo, apitos 1/2/3, deduplicação, eventos não emitidos, prioridades e ausência de rajadas, sem exigir PHP em produção.
- Homologar no IIS local `C:\inetpub\wwwroot\rumo10` com banco MySQL local `rumohub`, **sem limpar ou reiniciar saves**, sem criar segunda instalação paralela.
- Demonstrar visual e auditivamente os oito arquivos: apito único, intervalo com duas instâncias sobrepostas, fim com três, gol, não gol opção B, assistência, cartões, entrada e saída somente se evento canônico real existir.
- Testar erro de carregamento, volume, mute, autoplay bloqueado, recarga, reenviar escolha, navegador desktop/móvel, Normal/Rápido/Expresso e ausência de HTTP externo.
- Reexecutar testes após corrigir qualquer defeito. Não alegar homologação por teste estático isolado.

## Evidência de entrega à coordenação

Relatar em formato objetivo:

1. Hash do último commit da branch e lista de arquivos alterados.
2. Lista exata dos oito OGG finais, tamanho, duração, licenças e SHA256; arquivos ainda faltantes, se houver.
3. Resultados dos testes automáticos e locais, com falhas abertas.
4. Eventos disponíveis e eventos pendentes da F04, sem sobredeclarar conclusão.
5. Resultado da audição de apitos sobrepostos e do não gol B, sujeito ao aceite final do proprietário.
6. Confirmar explicitamente: **nenhum merge na main, nenhum deploy UOL, nenhum Cron alterado e nenhum banco/save alterado**.

**Portão final:** após revisão da coordenação, aceite do proprietário e seu backup manual antes do merge, a autorização do merge na `main` desencadeará a publicação automática. Não abrir PR para merge definitivo sem alcançar esses critérios. O Cron já está ativo. A limpeza de pastas UOL segue suspensa até a primeira publicação funcional comprovada.
