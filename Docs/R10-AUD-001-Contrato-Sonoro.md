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

O efeito `match_whistle.ogg` é a MESMA gravação em todos os três momentos. Os disparos não devem ser concatenados. Usar uma instância de `AudioBufferSourceNode` por disparo, com início em `t0 + n * 0,18s`, e sobreposição de playback; 180 ms é ponto inicial de ajuste auditivo, não um intervalo definitivo. O controle de atenuação para evitar clipping é calculado proporcionalmente pelo número de disparos simultâneos (`gain = 1 / count` por pulso, sendo 1 no início, 2 no intervalo e 3 no fim), roteado ao nó mestre (`master gain`).

As licenças das fontes Freesound foram apontadas como CC0 na pré-seleção de 16/11. Para Universfield, foi apontada a Pixabay Content License. É necessário reconfirmar a licença e guardar a origem, autor, data de obtenção, hashes SHA-256, duração final, transformação aplicada e créditos quando exigidos. Não distribuir os áudios de origem de forma independente.

## Eventos reais e limites

- `MATCH_WHISTLE_START`, `MATCH_HALFTIME`, `MATCH_WHISTLE_END`: sinalização de período a partir do estado real da partida. Não simular relógio.
- `GOAL_SCORED`, `ASSIST_RECORDED`, `CARD_YELLOW`, `CARD_RED`: eventos existentes do jogo.
- `NO_GOAL`: nova sinalização sonora de finalização desperdiçada ou pênalti desperdiçado, sem mudar probabilidade/resultado.
- `PLAYER_SUBSTITUTION_IN` e `PLAYER_SUBSTITUTION_OUT`: preparados no tocador e integrados a `MatchParticipation`. O vínculo com substituições reais depende de eventos canônicos da F04. Não criar substituições fictícias para reproduzir áudio.
- `BALL_HIT_POST` e `PENALTY_AWARDED`: mantidos como efeitos legados sintetizados proceduralmente via Web Audio nativo (sem arquivos OGG adicionais na distribuição), preservando estrita compatibilidade retroativa com o motor esportivo.
- `WHISTLE_FOUL`: fora desta seleção.
- A execução deve ser idempotente por identidade de evento, com deduplicação imediata no agendamento do playback (t=0) via `sessionStorage`, controle de mute/volume e desbloqueio por interação real.
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

---

## Histórico de Auditorias e Status Atual

> **[HISTÓRICO - 17/11/2026 Manhã]:** Etapa preliminar com 7 arquivos OGG entregues; pendência da obtenção de `no_goal.ogg` (P0-1) e correção da prioridade na fila (P0-2/P0-3).
> **[HISTÓRICO - 17/11/2026 Tarde]:** Integração de `no_goal.ogg` concluída pelo proprietário, correção da classificação de cartão recente (P0-4) e portabilidade E2E.
> **[HISTÓRICO - 17/11/2026 Auditoria Codex R1]:** Apontamentos P1-A (deduplicação no F5 durante reprodução), P1-B (preservação na suspensão do AudioContext), P1-C (restauração de BALL_HIT_POST e PENALTY_AWARDED) e P2-A (histórico no mesmo minuto).
> **[HISTÓRICO - 17/11/2026 Auditoria Codex R2]:** P1-A e P1-B confirmados aprovados; eliminação do teto arbitrário da fila para preservar todos os eventos contemporâneos legítimos (P1-C R2); ciclo de vida de `current_command_sound_event_ids` independente de feedback para cobrir `advance` aos 60 min (P2-A R2); e correção da pendência residual de mute durante download assíncrono.
> **[HISTÓRICO - 17/11/2026 Auditoria Codex R3]:** P1-A, P1-B, P2-A e mute aprovados. Resolução definitiva do defeito de timing do P1-C através da centralização temporal monotônica (`nextAllowedPlayTime` e despachante defensivo de timer único `scheduleFlush`), blindando o intervalo de 250 ms contra cliques do usuário ou invocações de `unlock()` durante a pausa entre sons.
> **[ATUAL - 17/11/2026 Auditoria Codex R4]:** Correção do encerramento artificial dos efeitos sintetizados legados (`PENALTY_AWARDED` e `BALL_HIT_POST`), substituindo `setTimeout` por eventos reais de término `ended` dos respectivos `OscillatorNode` e blindando o ciclo de vida contra suspensões e interações.

**Estado atual pós-auditoria R4 (Candidato a Codex R5):** Todas as correções foram integradas e aprovadas com rigor máximo: PHP 143/143, Web Audio 182/182, Chrome E2E 60/60 no IIS, UI-001 76/76, F03 298/298 e Carreira 27.985/27.985. Os oito arquivos OGG aprovados estão presentes localmente em `public/assets/audio/`, verificados por hashes SHA-256 e ffprobe. Aguardando auditoria independente Codex R5. Não executar merge nem publicar na UOL nesta etapa.
