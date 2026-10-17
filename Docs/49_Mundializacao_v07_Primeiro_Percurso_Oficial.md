# v0.7 — Alagoas: adaptador estadual integrado, continuidade nacional pendente

Atualização: 17/10/2026. Branch `codex/mundializacao-v07`; base real desta continuação: `ab9ff3015c6664531377e19d2aaaebcd50892478`, posterior ao c4d69ff citado no pedido. O relatório anterior permanece no histórico Git desse commit. Não foi repetida a auditoria mundial. A pasta preexistente `Docs/levantamento-2026-10-15/` foi preservada fora dos commits.

**O percurso completo ainda NÃO está liberado.** Agora há adaptação esportiva de Alagoas executada no Game/Repository, com cinco temporadas em memória e cinco em MySQL. Isso é um avanço sobre a entrega anterior, que tinha apenas contratos e validadores de admissão. A execução permanece um teste CLI isolado, com participantes reais e fontes FAF; NÃO é uma carreira oficial disponível no site, nem validação de suas competições nacionais.

## Implementação efetiva

`AlagoasCompetition`, versão `faf-al2026-trial-v1`, é chamado por Competition::tournament/standings/nextPhase/result somente no formato novo `alagoas`. O caminho legado permanece igual. Foram implementados:

- Turno único de oito clubes na Série A e seis na B; copa inicialmente com os 11 participantes efetivos depois da eliminação do Aliança, em grupos de cinco e seis. Folgas não viram partidas.
- Quatro classificados nas ligas; dois por grupo na copa. Semifinais cruzadas na copa e 1×4/2×3 nas ligas; finais conforme vencedores.
- Semifinais/final em ida e volta nas ligas e jogo único na copa; mando de semifinal conforme classificação e de final pela campanha acumulada. Empates usam o resolvedor de pênaltis existente.
- Pontos, vitórias, saldo, gols, confronto direto apenas entre dois empatados, vermelhos, amarelos e sorteio persistido. A campanha acumulada inclui jogos contra adversários eliminados; não é uma tabela apenas dos finalistas.
- Ranking final completo; um acesso da B e uma queda da A; copa não promove clube. Títulos vêm do resultado finalizado e passam pela coleta existente, sem títulos de fase inventados.
- Agenda compartilhada com descanso simulado de três dias, ligada aos comandos reais do jogador e resultados NPC. Referências e clubes complementares estão fixados no snapshot, com hash e versão, sem alteração de catálogo global/Hub.
- Projeções mínimas/potenciais reconhecem o novo formato. Para Zumbi na edição inicial: dez jogos mínimos (cinco B + cinco copa), até seis de mata-mata.

`AlagoasTrial` prepara apenas fixtures CLI locais com flags explícitas. As cinco identidades ausentes são disponibilizadas exclusivamente no contexto dessas fixtures, com nome/UF/país e fonte. Não cria cidades, escudos, jogadores ou ratings oficiais; usa o fallback de força já existente e identificado como provisório. Esse contexto é restaurado por try/finally, sem vazar para outra carreira.

## Participação e futuro simulado

O alias Sporting-AL / Sporting FC / Sporting Teotônio / antigo Sete de Setembro permanece fundamentado na notícia oficial FAF. Não houve renomeação de históricos.

O Ato 003/2026 exclui o Aliança apenas da copa inicial; seus jogos não são gerados e a sanção não é aplicada à Série B. Não foram importados placares ou campeões históricos.

Na renovação da fixture, a copa usa uma política explicitamente simulada: os oito participantes da A anterior mais os quatro semifinalistas da B anterior, distribuídos deterministicamente em dois grupos de seis. O modelo reutiliza as regras esportivas-base de 2026, sem alegar regulamento oficial posterior. A sanção histórica não é carregada automaticamente para os anos seguintes. Acesso/descenso utiliza os resultados produzidos naquela carreira e preserva oito/seis clubes nas ligas.

O ledger `national_qualification_pending` registra candidatos e motivo do bloqueio por temporada, com chave única. Não concede inscrição nacional, R, XP ou título por candidatura. O repasse de vaga da copa é exercitado como candidato, mas a elegibilidade nacional completa ainda não está certificada.

## O que impede a liberação completa

1. Copa do Brasil, Nordeste e Série D não têm, neste percurso, inscrições e calendários nacionais consolidados íntegros. O teste estadual não os simula nem inventa suas vagas. A Série A e a copa não podem prometer destinos nacionais inexistentes.
2. A Série D continua com política candidata bloqueada: RNF/quotas estão documentados, mas falta ledger real completo dos 96 clubes, evidência de mérito, exceções e composição futura. O preenchimento paulista do legado NÃO foi removido; nenhuma inscrição histórica foi regravada.
3. A elegibilidade Sub-23 do elenco completo e as condições de inscrição dos atletas não foram implementadas. O atleta das cinco temporadas vai dos 18 aos 22 anos, o que não prova conformidade dos demais jogadores.
4. A distribuição de mandos da primeira fase da B segundo a classificação anterior ainda não está validada. O calendário usa geração simulada; não deve ser anunciado como reprodução integral do REC. Datas são simuladas, não a tabela oficial de 2026.
5. O piloto suporta este conjunto de 83/88 jogos totais, mas mantém teto de 128. Isso não comprova capacidade para Copa do Brasil + estaduais + demais torneios. É necessário implementar e medir processamento limitado/reentrante antes de ampliar a escala.

Logo, permanecem **0 dos 23 vínculos brasileiros liberados ponta a ponta**, 23 bloqueados e 9 internacionais bloqueados. O vínculo Alagoas foi exercitado no teste regulatório, mas não desbloqueado na estreia ou no mercado de usuários. Não houve expansão continental.

A alternativa Fares Lopes/Ceará, examinada na etapa anterior, também leva à Copa do Brasil e não elimina o bloqueio nacional. Não afirmamos ter descartado todos os estados. Alagoas continua como o eixo mais avançado nesta implementação.

## Testes desta continuação

Evidências novas em `Docs/mundializacao-v07/alagoas-runtime/`:

| Teste | Evidência / resultado |
|---|---|
| Regras FAF, formato, mando, ranking, confronto direto e pênaltis | `rules-tests.json`: 50 verificações |
| Cinco temporadas reais do Game::command, permanecendo no clube | `gameplay-five-seasons.json` |
| Cinco temporadas via Repository/PDO, recarga por comando, duas contas, tokens e lock concorrente | `mysql-runtime.json`: 33 verificações; rollback integral |
| Seis posições × Normal/Rápido/Expresso, uma temporada completa em cada combinação | `modes-tests.json`: 3.168 verificações |
| Aceite de oferta elegível e nova temporada | `transfer-runtime.json`; RNG controlado na fixture, sem alterar mérito/fórmulas |
| Páginas autenticadas, decisão, CSRF, replay, isolamento | `http-runtime.json`: 22 verificações; conta descartável removida |
| Chrome 320/390/1280 e teclado | `mobile/results.json`: 39 verificações; screenshots |
| Regressão geral | `regressao/suites.json`: 24 suítes |

As temporadas do adaptador usam clubes reais e regras estaduais acima; são simulações estaduais isoladas com pendências, NÃO cinco temporadas oficiais completas com Copa do Brasil/Nordeste/Série D. Os testes PDO desativam conquistas somente para a fixture descartável; não provam desbloqueio de R nela. As suítes próprias e a comparação de fronteiras preservam 50 conquistas/1.000 R, XP-base e v0.6.1.

A versão final foi recarregada pelo MySQL após introduzir o identificador do adaptador. Dados de teste foram revertidos, sem DDL, backfill ou atualização de usuário real. Não houve migração de save legado. HTTP usa servidor PHP loopback com login local e Hub desativado, não autenticação central em IIS/Apache. Os testes visuais usam o HTML capturado dessa fixture.

Falha intermediária registrada: a fronteira v0.6 inicialmente rejeitou o novo despacho de Competition. Foi ajustada para retirar somente os hooks do formato novo/contexto, mantendo comparação exata do restante; repetição passou com 11 verificações. Não se afrouxaram fórmulas para corrigir o teste. Regressão do piloto sintético também passou após os hooks novos.

## Desempenho e ambiente

Coorte local final: 4483–5603 consultas/temporada; comando mais lento 188.07 ms; fechamento 57.98–79.36 ms; snapshots 141676–183845 bytes.

Métricas exatas por temporada estão em `mysql-runtime.json`: comandos, consultas, maior duração, comando de fechamento, duração total e bytes do snapshot. Tempo total soma muitos comandos e NÃO é tempo de um único request. Cinco temporadas foram medidas localmente; não há benchmark Apache/UOL. O histórico cresce e os dados de referência complementares ainda se repetem nos contratos, ponto para otimização futura sem alterar saves existentes.

Nenhum teste desta continuação acessou a produção ou modificou Hub/rh_*. O site local habitual continua no legado e não oferece o percurso novo. `RUMO10_WORLD_CALENDAR=0` permanece; não houve mudança do .env, handlers IIS ou configuração Apache.

## Fontes primárias

Contratos e matrizes existentes: `database/competition-references/br-al-2026.json`, `br-d-admission-2026-v2.json` e `Docs/mundializacao-v07/percurso-oficial/sources.*`. Fontes regulatórias foram reconsultadas em 17/10/2026; nenhum resultado histórico importado.

- [FAF, Série A 2026](https://futeboldealagoas.net/assets/uploads/176832886543.pdf): arts.1–12, pp.2–4.
- [FAF, Série B Sub-23](https://futeboldealagoas.net/assets/uploads/177223121748.pdf): arts.1–7, pp.2–3; art.14, pp.5–6.
- [FAF, Copa Alagoas revisada](https://www.futeboldealagoas.net/assets/uploads/177326005939.pdf): arts.1–8, pp.2–4.
- [FAF, Sporting/Sete](https://www.futeboldealagoas.net/novo/post/sporting-teotonio-conquista-o-campeonato-alagoano-serie-b-2026-e-garante-vaga-no-alagoano-centenario-2027): parágrafo explícito da identidade.
- [FAF, Ato 003/2026](https://futeboldealagoas.net/assets/uploads/176962499217.pdf): arts.1–3, p.1, exclusão.
- [CBF, Série D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf): art.2, pp.4–6.
- [CBF, RNF 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/RNF_Ranking_Nacional_das_Federacoes_2026_30b87e87a3.pdf): p.1.

## Reprodução, rollback e próximo portão

PHP 8.4 com mbstring; pdo_mysql para PDO/HTTP. Os scripts exigem banco rumohub local e definem flags no próprio processo:

```
php tests/alagoas-rules-v07.php
php tests/alagoas-gameplay-v07.php
php tests/alagoas-modes-v07.php
php tests/alagoas-mysql-v07.php --local-rollback
php tests/alagoas-transfer-v07.php
php tests/alagoas-http-v07.php --local-fixtures
node tests/alagoas-mobile-v07.cjs
```

Não há migration/DDL. Rollback: manter flags desligadas e reverter commits de código, preservando quaisquer snapshots; não restaurar banco sobre progresso posterior. Fixtures CLI não são convertidas em carreiras reais.

Próximo portão concreto: concluir elegibilidade/mando inicial, admissões e runtime nacional dependente, com política futura explícita; depois validar cinco temporadas do percurso INTEGRAL e só então liberar novas carreiras locais. Retirar qualificações nacionais do produto seria uma mudança de escopo, não aplicada silenciosamente. Não foi necessário inventar vínculo entre estadual e divisão nacional.

Commits por módulo e push exclusivamente para `codex/mundializacao-v07`, com SHA remoto informado na entrega. Sem merge main, deploy UOL, SQL produtivo ou alterações do RumoHub.
