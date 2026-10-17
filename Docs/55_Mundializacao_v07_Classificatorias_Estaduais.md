# Entrega 55 — classificatórias estaduais

17/10/2026. Base `dc7126333f86a894ae5d7097ef3c8f7185462377`, branch `codex/mundializacao-v07`. Continuação da entrega 54; Copa do Brasil 2026 e calendário existentes reaproveitados. Este lote não conclui a mundialização nem libera carreiras públicas.

## Resultado verificável

- **2 das 64 vagas estaduais possuem candidatos esportivamente comprovados na fixture**, provenientes do Alagoano A 2026. **62 vagas estaduais continuam pendentes**. Não são duas inscrições nacionais efetivadas.
- A auditoria passa de 28 para **30 candidatos**: 28 méritos nacionais já existentes + 2 estaduais. Para chegar a 96 faltam **66 candidatos verificados: 62 estaduais + 4 residuais da transição inicial**. A proposta residual continua separada, sem inscrição automática.
- **Zero novas inscrições na Série D, zero renovações nacionais e uma edição nacional consecutiva por carreira de teste**. Não foram executadas duas renovações ou cinco temporadas nacionais. Resultados de três modos não são três temporadas da mesma carreira.
- Nenhum campeonato estadual novo foi criado. O motor de Alagoas já validado ganhou prova reconstruída de classificação, repasse e ledger de candidaturas. Demais federações não foram liberadas por mera presença no catálogo.
- Série C simulada de 24 clubes/quatro descensos executada isoladamente. Série D futura/quatro acessos, Copa do Brasil 128 e renovação Nordeste ainda não executáveis.

## Regras e fontes

Fontes primárias consultadas em 17/10/2026:

1. [FAF, REC Alagoano Série A 2026](https://futeboldealagoas.net/assets/uploads/176832886543.pdf), arts.1 e 5–10, PDF pp.2–4: oito participantes, classificação final e desempates; art.10, campeão e vice para Série D 2027, com repasse pela classificação aos elegíveis. A Copa Alagoas não foi transformada artificialmente em classificatória para estas duas vagas.
2. [CBF, REC Série D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf), arts.2–3, PDF pp.4–6: 64 vagas estaduais, RNF, critérios estaduais/seletivos e substituições. Seletiva não equivale a torneio arbitrário: exige as condições/autorizações do REC.
3. [CBF, RNF 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/RNF_Ranking_Nacional_das_Federacoes_2026_30b87e87a3.pdf): SP quatro, posições 2–9 três, demais duas. Reutilizado o documento da admissão já conciliada.

O direito esportivo AL 2026→2027 vem do REC estadual. O fechamento nacional, resultados e repasses são **simulados dentro do save**. Congelar quotas 2026 em temporadas posteriores é adaptação proposta, não regulamento oficial futuro. Este adaptador aceita somente edição-base fixada e temporada 1; não perpetua automaticamente a regra em 2028+. BID, licenciamento e aceite federativo não são verificados pelo simulador: candidato comprovado não significa inscrição aceita.

Participantes AL: ASA, Coruripe, CSA, CRB, CSE, Cruzeiro Arapiraca, Murici e Penedense, com IDs preservados. Identidades Sporting/Sete de Setembro e a Série B não foram reescritas. Divisão inferior afeta a próxima edição estadual, mas não foi inventada como condição destas duas vagas.

## Matriz das 27 federações

Inventário reproduzível em `classificatorias-estaduais/federations.json` e CSV: competições locais, IDs, formato, edição, quantidade de participantes e fontes cadastradas por UF. O inventário não certifica que todos os regulamentos cadastrados foram integralmente revistos nesta rodada. Nas 26 UFs restantes é necessária verificação primária de caminho classificatório, participantes, repasses, seletivas e adaptador esportivo específico; um formato genérico que produz tabela não comprova conformidade com REC.

| UF | Vagas | Candidatos comprovados | Caminho / bloqueio |
|---|---:|---:|---|
| SP | 4 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| RJ | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| MG | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| RS | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| PR | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| CE | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| GO | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| SC | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| BA | 3 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| PE | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| AL | 2 | 2 | Alagoano A; art.10; inscrição nacional pendente |
| PA | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| MT | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| AM | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| RN | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| PB | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| MA | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| SE | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| DF | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| PI | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| ES | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| TO | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| AC | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| RO | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| RR | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| MS | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |
| AP | 2 | 0 | Regra classificatória/participantes/adaptador ainda não certificados |

AL reutiliza classificação final do adaptador existente. Nas outras UFs, inscrição/candidatura permanece indisponível; presença de divisão inferior ou seletiva não foi presumida. A prioridade seguinte deve ser determinada pelos regulamentos/participantes mais completos, sem liberar um lote de 26 campeonatos simultaneamente.

## Implementação e integridade

- `StateResultProof`: reconstrói o campeonato a partir dos resultados finalizados, mandos, cartões, sorteio de desempate persistido e pênaltis gravados; não gera placares ou disputa de pênaltis nova. Rejeita ranking divergente, partida repetida, mando trocado e números inválidos. Digest canônico inclui resultados, lotes e classificação.
- `StateQualification`: política por edição em `br-state-d-paths-v1.json`, contrato fixado/fonte/participantes conferidos. Homologação nacional existente fornece exclusões: clubes das divisões superiores e candidatos nacionais já retidos não ocupam nova vaga estadual. Repasse usa a classificação final; não overall, catálogo ou sorteio de clubes.
- `state_qualifications[2027][AL]`: recibo imutável por carreira/edição/UF, prova e rejeições por posição, chaves/digest, sem efeito em memberships, recompensas ou títulos. O chamador persiste na transação/lock existente do Repository; não há endpoint de cliente aceitando identidade arbitrária.
- `FutureSerieDAdmission`: verifica novamente o recibo antes de aproveitá-lo; separa candidato nacional, estadual e vaga ausente. Um caminho certificado não recebe outra linha contraditória de rejeição genérica. `SerieDFuturePlan` exclui também os candidatos estaduais da proposta residual.
- Ledgers antigos permanecem imutáveis. Um recibo de admissão anterior que descreva 28 candidatos não é sobrescrito silenciosamente por um novo recibo de 30: conflito exige tratamento explícito futuro. Nenhum backfill/save real foi executado. A homologação histórica com déficit 68 permanece histórica; a nova auditoria de candidatos, separada, informa 66.

O fluxo está ligado à auditoria de admissão e exercitado após o fechamento real Game/Repository nas fixtures. **Não foi incluído acionamento automático em carreiras públicas**, nem aplicação de inscrição ou renovação. O catálogo, rotina paulista legada e motores publicados permanecem preservados.

## Contratos futuros: implementação versus planejamento

| Componente | Estado real |
|---|---|
| C 2026, 20 clubes, dois descensos | Preservado |
| C futura, 24 clubes, quatro acessos e quatro descensos | Adaptador isolado `c-2027-four-down-v2`, 302 jogos completos testados; não integrado à renovação |
| D futura, 96 clubes/quatro acessos | Plano dimensional de 606 jogos; falta adaptador futuro executável e 62 classificações estaduais |
| Quatro vagas residuais de transição | Proposta por resultado da D, sem aplicação ou inscrição |
| Copa do Brasil futura, 128 clubes | Contrato de capacidade: 36/84/48 nas três primeiras fases, nove fases, 157 jogos; **adaptação aprovada pelo proprietário em 17/10 para desenvolvimento posterior, inativa** |
| Nordeste e demais obrigações futuras | Inscrições/contratos incompletos; desconhecido não significa dispensado |
| Renovação A/B/C/D | Bloqueada integralmente; nenhuma troca parcial |

Cup128 não é uma copa executável neste lote: faltam inscritos, novos campeões regionais, política de sorteios/ranking, adaptador e persistência. As equações de fases são testes de contrato, não disputa do torneio. As regras oficiais de 2026 continuam intactas.

## Testes e evidências

Diretório `Docs/mundializacao-v07/classificatorias-estaduais/`:

- `contracts.json`: 35 verificações; candidaturas AL, repasse/exclusões, adulteração, recibo/edição/carreira incompatíveis, replay, C24/quatro descensos, C2026 preservada, matriz 27/64 e capacidades futuras. Mede também duração e pico de memória do processo desta suíte NPC, não de uma temporada PDO.
- `game-modes.json`: 11.376 verificações, Normal/Rápido/Expresso, uma edição completa de 1.888 partidas por fixture, incluindo liga/copas e prova AL. XP/replay, cobertura do personagem e descanso preservados. Três modos não demonstram cinco temporadas consecutivas.
- `mysql-expresso.json`: 38 verificações na execução final; uma edição Expresso via Repository/PDO com recarga a cada comando, duas contas descartáveis, lock real em segunda conexão, nonce/replay, crash após save/antes do ledger, retry, prova estadual, persistência do recibo e rollback integral. Execução anterior teve 43 checks: a quantidade varia com as partidas efetivamente jogadas. Conquistas desligadas nessa fixture: não prova nova concessão de R.
- `regressao/suites.json`: 24 suítes passaram, 1.014 cenas (214 anteriores + 800 novas), contratos anteriores preservados. Pós-jogo adicional 215 verificações/seis posições/três modos; conquistas 96/50 objetivos/1.000 R; feedback 32; fronteiras 11+24; flag desligada retorna 503 sem converter save.
- `http-runtime.json`: 27 verificações de fixture autenticada, CSRF, isolamento, controller/replay e chaveamento CDB; limpeza ao terminar. Regressão HTTP existente, não nova rota pública de candidatura.
- `mobile/results.json`: 48 verificações Chrome em 320/390/1280, teclado, ausência de overflow e erros JS; capturas de carreira, jogador, classificação e troféus. HTML autenticado capturado de PHP loopback, **não IIS, Apache, UOL ou aparelho físico**.

As coortes Game completas usam a posição da fixture existente; seis posições são cobertas pela regressão/pós-jogo, não por dezoito novas temporadas nacionais. Nenhuma fórmula de força, gol, XP, aging ou mercado foi alterada. Nenhuma conclusão estatística nova de equilíbrio é inferida dos placares aleatórios destas fixtures.

## Desempenho e limites

Métricas por modo em `game-modes.json`; métricas finais PDO em `mysql-expresso.json`, medidas localmente: 200 comandos, 7.003 consultas, p50 453,29 ms, p95 871,52 ms, p99 1.284,22 ms, pior comando/fechamento 1.660,35 ms; total 105.057,74 ms. Snapshot final 1.531.616 bytes, pico 2.009.066; fechamento 10.940 bytes, histórico anterior 2 bytes. A suíte NPC de contratos consumiu pico de processo 23.068.672 bytes; não há perfil de memória PDO por temporada. Variações de campanha e carga local impedem alegar ganho controlado contra a entrega 54.

A prova estadual reconstrói a competição e revalida o fechamento nacional: é operação de homologação explícita, não scan em cada GET público. Não foi acrescentado worker ou aumento indiscriminado de teto. Sem medição de crescimento em temporadas 2/3/5, pois a renovação ainda está bloqueada; não se alega escala multianual ou desempenho UOL.

A continuidade depende de 62 vagas estaduais, decisão/implementação das quatro residuais, D futura executável e inscrições/contratos de copas obrigatórias futuras. Não foram retirados torneios obrigatórios para viabilizar um teste. Mercado não ganhou destinos sem calendário. A otimização versionada de contratos/snapshot permanece planejamento, sem regravação de saves.

## Reprodução e recuperação

PHP 8.4 com mbstring; pdo_mysql para banco local `rumohub`. Suítes MySQL exigem ambiente local, fixtures descartáveis e rollback; flags somente no processo. Exemplos:

```
php bin/audit-state-paths-v07.php
php tests/state-qualification-v07.php
php tests/state-qualification-runtime-v07.php
php tests/state-qualification-mysql-v07.php --local-rollback
php tests/copa-brasil-http-v07.php --local-fixtures
node tests/copa-brasil-mobile-v07.cjs
```

Definir `V07_MOBILE_EVIDENCE_DIR` para o diretório mobile desta entrega; `V06_EVIDENCE_DIR` para `classificatorias-estaduais/regressao` ao rodar `python bin/test-narrative.py`. Os JSONs volumosos de coortes foram comprimidos e a igualdade gzip/original conferida. Relatórios históricos regenerados foram preservados no diretório novo e restaurados nas localizações anteriores.

**Sem migration/DDL, sem alterações rh_*/Hub, usuários reais, .env, serviços, IIS/Apache ou produção.** Rollback: manter flags usuais desligadas e retornar código, preservando campos aditivos; não restaurar dumps sobre progresso posterior. Fixtures criadas pelo teste são revertidas/limpas. Snapshot novo não deve ser editado para simular inscrição ou liberar renovação.

## Git e próximo lote

Workflows inspecionados: push de deploy restrito a `main`; esta branch não publica UOL. Remoto inicial confirmado igual à base. Commits de código: 0e2c38 (classificação/prova) e 73f197b (preparação futura inativa). Testes/documentação em commit separado; SHA final confirmado no encerramento. Apenas push para `codex/mundializacao-v07`, sem merge/force/deploy.

Próximo lote: certificar um pequeno conjunto adicional de classificatórias estaduais com fontes e resultados, depois D futura e copas obrigatórias; só então renovação atômica e coorte de cinco temporadas. V0.8 continua apenas planejada.
