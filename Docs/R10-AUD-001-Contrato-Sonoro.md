# R10-AUD-001 | Contrato de áudio e validação local

Status: implementação concluída na branch `feat/aud001-sons-partida`, com todos os 8 arquivos OGG integrados localmente, validados com hashes SHA-256 e suítes completas aprovadas. Nenhuma publicação autorizada.
Decisão do proprietário: 17/11/2026. As dez fontes foram aprovadas; o não gol utiliza exclusivamente a opção B de Universfield.
A aprovação das fontes não substitui a obtenção dos arquivos, confirmação documental de licença e audição do recorte final.

## Arquivos finais

Todos os arquivos a seguir são exigidos em `public/assets/audio/`, hospedados localmente pelo Rumo10, com até 3 segundos, em formato OGG compatível com os navegadores-alvo.

| Arquivo único | Efeito(s) | Gravação original aprovada |
| --- | --- | --- |
| `match_whistle.ogg` | Início (1 disparo), intervalo (2), fim (3) | Rosa-Orenes256, Referee whistle sound.wav, https://freesound.org/people/Rosa-Orenes256/sounds/538422/ |
| `goal.ogg` | Gol | huubjeroen, goalloop.wav, https://freesound.org/people/huubjeroen/sounds/113698/ |
| `no_goal.ogg` | Não gol | Universfield, Crowd Disappointment Reaction, opção B, https://pixabay.com/sound-effects/people-crowd-disappointment-reaction-352718/ |
| `assist.ogg` | Assistência | cognito perceptu, applause.wav, https://freesound.org/people/cognito%20perceptu/sounds/57587/ |
| `yellow_card.ogg` | Cartão amarelo | Rosa-Orenes256, Referee whistle sound.wav, mesmo URL do apito |
| `red_card.ogg` | Cartão vermelho | SpliceSound, Referee whistle blow, gymnasium.wav, https://freesound.org/people/SpliceSound/sounds/218318/ |
| `substitution_in.ogg` | Entrada do jogador | NobodyYouKnowOf, crowd_cheering_and_clapping.wav, https://freesound.org/people/NobodyYouKnowOf/sounds/410868/ |
| `substitution_out.ogg` | Saída do jogador | cognito perceptu, applause.wav, mesmo URL da assistência |

O efeito `match_whistle.ogg` é a MESMA gravação em todos os três momentos. Os disparos não devem ser concatenados. Usar uma instância de `AudioBufferSourceNode` por disparo, com início em `t0 + n * 0,18s`, e sobreposição de playback; 180 ms é ponto inicial de ajuste auditivo, não um intervalo definitivo. Controlar ganho para evitar clipping.

As licenças das fontes Freesound foram apontadas como CC0 na pré-seleção de 16/11. Para Universfield, foi apontada a Pixabay Content License. É necessário reconfirmar a licença e guardar a origem, autor, data de obtenção, hashes SHA-256, duração final, transformação aplicada e créditos quando exigidos. Não distribuir os áudios de origem de forma independente.

## Eventos reais e limites

- `MATCH_WHISTLE_START`, `MATCH_HALFTIME`, `MATCH_WHISTLE_END`: sinalização de período a partir do estado real da partida. Não simular relógio.
- `GOAL_SCORED`, `ASSIST_RECORDED`, `CARD_YELLOW`, `CARD_RED`: eventos existentes do jogo.
- `NO_GOAL`: nova sinalização sonora de finalização desperdiçada ou pênalti desperdiçado, sem mudar probabilidade/resultado.
- `PLAYER_SUBSTITUTION_IN` e `PLAYER_SUBSTITUTION_OUT`: preparados no tocador. O vínculo com substituições reais depende de eventos canônicos da F04. Não criar substituições fictícias para reproduzir áudio.
- `PENALTY_AWARDED`, `WHISTLE_FOUL` e `BALL_HIT_POST`: fora desta seleção de dez efeitos.
- A execução deve ser idempotente por identidade de evento, com controle de mute/volume e desbloqueio por interação real.
- No modo Normal, são utilizados eventos detalhados da partida. Nos modos Rápido e Expresso, NÃO reproduzir toda a coleção de eventos antigos de uma simulação: a aceitação deve demonstrar ausência de rajadas, preservação de saves e relatório correto.

## Homologação antes do merge

1. Colocar os oito OGG finais no ambiente LOCAL. Não comitar referências vazias, placeholders silenciosos ou sons de origem não autorizada.
2. Executar `php -l app/Game.php`, `php -l app/Presentation.php` e `node --check public/assets/audio-engine.js`.
3. Testar início 1x, intervalo 2x e fim 3x, com sobreposição real. Verificar que as fontes compartilham exatamente o mesmo buffer.
4. Testar não gol de chute e pênalti, gol, assistência e cartões. Testar ausência de áudio fictício para substituições sem evento registrado.
5. Testar volume, mute, navegador bloqueando autoplay, refresh, reenvio de decisão e HTTP 404 de arquivo ausente.
6. Testar Normal, Rápido e Expresso, em navegador desktop e móvel. Confirmar que som não altera motor, XP, estatísticas, banco ou save.
7. Validar autoria, licença e SHA-256 de cada amostra e obter aceite do proprietário. Só então abrir ou finalizar PR e realizar merge autorizado na main.
8. O primeiro deploy funcional após o merge deve ser a prova da automação permanente. Conferir recibo UOL e ambos os domínios.

**Estado atual:** todos os oito arquivos OGG aprovados estão presentes localmente em `public/assets/audio/`, verificados por hashes SHA-256 e ffprobe, e validados pelas suítes determinísticas e E2E no IIS. Aguardando homologação auditiva do proprietário e revisão independente. Não executar merge nem publicar na UOL nesta etapa.
