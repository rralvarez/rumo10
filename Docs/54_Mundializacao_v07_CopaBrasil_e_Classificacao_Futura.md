# Entrega 54 — Copa do Brasil executável e direção da classificação futura

17/10/2026. Branch `codex/mundializacao-v07`; base local/remota conferida `9a73e9fb621e49cb914f27e9be036e32401d60ef`. Relatório 53 lido; levantamento não versionado anterior preservado. Workflows continuam por push apenas em main e não foram alterados. Sem Hub/rh_*, usuários reais, .env, IIS, Apache, UOL, merge ou deploy.

**Resultado:** Copa do Brasil 2026 executável na fixture CLI local, integrada a Alagoas, A/B/C/D e Nordeste: **nove competições e 1.888 jogos**, incluindo os **155 da Copa do Brasil**. Há gameplay nos três modos, personagem efetivamente atuando, NPCs, título, persistência PDO e recuperação. **Zero renovações nacionais aplicadas.** Não há cinco temporadas nacionais consecutivas; cada coorte terminou uma edição independente. Runtime público continua desligado.

## 1. Copa do Brasil: implementação e identidade

`CopaBrasilCompetition` tem formato próprio, sem substituir formatos/saves legados. O complemento `br-copa-sports-2026.json`, gerado por `bin/build-copa-brasil-sports.py`, fixa:

- 126 participantes do anexo anterior, os 14 confrontos iniciais, oito potes, posições de mando e cruzamentos do Anexo B; RNC dos 98 clubes relevantes para as entradas posteriores/quinta fase.
- Identidades locais das doze lacunas anteriores: Vasco-AC, Independente-AP, Atlético de Alagoinhas-BA, Desportiva Ferroviária-ES, Porto Vitória-ES, FC Pantanal-MS, Bragantino-PA, Castanhal-PA, Boavista-RJ, Ji-Paraná-RO, América-SE e Capital-TO. Nome formal, UF e fonte do anexo acompanham as chaves; não se confundem homônimos. Penedense já vinha do complemento Alagoas. No escopo completo são **126/126 IDs resolvidos**.
- Esses complementos são exclusivos do contexto da fixture e não novos cadastros no Hub/global. Não certificam IDs numéricos CBF, cidade, escudo ou overall. Elencos ausentes usam o fallback fictício identificado já existente; não são importações de atletas reais. Nenhuma nota-base ou fórmula foi editada.

Os resultados são sempre ficção da carreira. Sorteios simulados usam seed persistida e ordenação por hash, reproduzível após recarga; não reproduzem o sorteio histórico. Inicial: os 28 clubes seguem os confrontos publicados pela CBF e mandos sorteados. Segunda: oito potes de onze, A×H/B×G/C×F/D×E e mandos por slot. Terceira/quarta: cruzamentos fixos; os quatro clubes do critério 2 entram nos grupos 59/70/71/82 com seus mandos. Quinta: entram os vinte da A e os blocos são definidos pelo RNC, sem usar overall. Sexta/sétima: sorteios abertos; oitava/final seguem o chaveamento. Datas são adaptação do calendário compartilhado com descanso de três dias.

| Fase | Novos inscritos | Disputantes | Jogos | Pernas |
|---|---:|---:|---:|---:|
| 1 | 28 | 28 | 14 | 1 |
| 2 | 74 | 88 | 44 | 1 |
| 3 | 4 | 48 | 24 | 1 |
| 4 | 0 | 24 | 12 | 1 |
| 5 | 20 | 32 | 32 | 2 |
| 6 | 0 | 16 | 16 | 2 |
| 7 | 0 | 8 | 8 | 2 |
| 8 | 0 | 4 | 4 | 2 |
| 9 | 0 | 2 | 1 | 1 |

Empates: pênaltis em jogo único; pontos/saldo e pênaltis em duas pernas. Para duas partidas, a decisão por saldo usada pelo resolvedor D é equivalente à sequência pontos/saldo. Reaproveita o placar de pênaltis salvo; rejeita pênaltis fora de contexto. Verificador reconstrói todos os confrontos/resultados com a seed e rejeita adulteração de mando, placar, campeão, sorteio, pênaltis ou duplicação.

Título nasce da final concluída. Não há acesso de divisão nem vaga continental concedida pela simples vitória. A ordem de eliminados da mesma fase usa RNC apenas como apresentação simulada, não classificação geral oficial. A interface informa a fase real de eliminação/final, sem dizer que todos terminaram na primeira fase.

`NationalTrial::createWithCopaBrasil` prepara o conjunto isolado. `NationalPyramid` inclui contrato, sorteios, placares e campeão da copa na homologação imutável. `OpportunityForecast` contabiliza jogos garantidos pela fase de entrada e mata-matas opcionais: CRB 1+11, participante da A 2+7, participante inicial 1+12. Não altera curva de utilização ou mérito do mercado; ofertas futuras seguem bloqueadas.

### Limites da fidelidade

Não certifica BID, prazos/limites de inscrições por troca de clube, suspensões individuais ou laudos de estádio. A final é única; sua designação de estádio pela CBF não foi reproduzida. A ordenação home/away é administrativa na simulação; a fórmula de vantagem do mandante existente não foi recalibrada para campo neutro. Isso é aproximação conhecida, não alegação de reprodução administrativa integral do REC. Estádios/alterações de capacidade não ativam licenciamento. Nenhum resultado histórico foi importado.

## 2. Série D futura: proposta concreta e decisões

O proprietário aprovou nesta conversa:

1. Completar estaduais/seletivas **oficiais**, com participantes/regulamentos verificados e partidas NPC; não criar torneios artificiais por UF para preencher vagas.
2. Direção de adaptação a partir da edição simulada 2027: **quatro acessos D→C e quatro descensos C→D**, mantendo C24. Não muda os seis acessos efetivos da D2026 nem os dois descensos C2026.

Contrato de proposta: `br-d-future-proposal-v1.json`. Ele está **inativo**. Não basta alterar números: os futuros adaptadores D/C ainda precisam implementar e testar os quatro slots. Congelamento de quotas RNF2026 é explicitamente adaptação versionada, não RNF oficial futuro.

| Transição proposta | Retidos D | Descensos C | Federações | Residual | Total |
|---|---:|---:|---:|---:|---:|
| 2026→2027 | 26 | 2 | 64 | 4 | 96 |
| Seguintes, com adaptação 4↔4 | 28 | 4 | 64 | 0 | 96 |

Prioridade: méritos nacionais primeiro; depois caminhos estaduais/seletivos certificados por edição, respeitando a quota de cada UF. Se o clube já estiver retido ou em A/B/C, repassar pela classificação elegível daquele caminho, sem duplicar vaga. Insuficiência de resultados bloqueia a renovação inteira, sem recorrer ao catálogo/overall/sorteio. Desempates reutilizam critérios e sorteio final persistidos pelo adaptador esportivo; não se sorteia de novo na admissão.

Para os quatro residuais de transição, `SerieDFuturePlan` apresenta **proposta** dos quatro mais bem classificados da D simulada ainda elegíveis e não selecionados. É alternativa determinística ao RNC histórico residual, rotulada adaptação e **não ativada como inscrição**. A direção geral foi aprovada; a certificação/ativação dessa regra detalhada ainda depende do lote de admissão. O plano usa somente o resultado fechado da carreira, mostra fontes/digest, preserva o save e é reexecutável sem duplicação.

Déficit efetivamente verificado continua **68**. O plano identifica quatro candidatos residuais propostos e deixa **64 vagas estaduais** por resolver caso essa proposta seja adotada. Não declara essas quatro como inscrições aprovadas nem as 64 como preenchidas. As 27 federações precisam de política classificatória certificada; ter uma competição estadual cadastrada ou finalizada não prova por si a vaga. Alagoas possui resultados esportivos, mas sua seleção/repasses nacionais ainda não estão certificados no novo avaliador.

Alternativas e consequências: completar os caminhos oficiais exige os participantes e os torneios necessários de cada federação e mais partidas NPC/medição de carga; seletivas artificiais seriam mais curtas, mas mudariam o universo esportivo e **não foram a opção escolhida**. Enquanto os caminhos oficiais não estiverem completos, o comportamento correto continua sendo o bloqueio.

## 3. Renovação e obrigações

Não foi implementada aplicação parcial nem repetição de memberships. A/B/C/D continuam homologadas apenas como propostas, sem mover clubes. A Copa do Brasil 2026 e Nordeste2026 estão cobertas para CRB na fixture atual. Faltam caminhos estaduais da D, formatos futuros C/D, renovação positiva atômica e obrigações futuras/regionais/continentais ainda não integralmente auditadas.

Copa do Brasil2027 tem 128 participantes e depende dos campeões das novas copas regionais previstas no REC; não reutiliza por inércia os 126 nem omite torneios para liberar a carreira. A homologação de 2026 não equivale a inscrição futura. Mercado e nova carreira pública permanecem bloqueados nesse percurso. Os23 vínculos estaduais e nove internacionais anteriormente bloqueados não foram liberados.

## 4. Testes e evidências

Diretório `Docs/mundializacao-v07/copa-brasil-executavel/`:

- `sports-contracts.json`: **286 verificações**, nove fases/155 jogos, identidades, mandos especiais, entradas/potes inválidos, pênaltis, reconstrução/adulterações, título, textos e projeções. Usa placares zerados sintéticos para exercitar pênaltis, não resultados históricos.
- `game-modes.json`: **11.376 verificações**; Normal/Rápido/Expresso, uma edição cada, 1.888 jogos por edição. CRB atuou na Copa: 5/1/3 jogos, 450/90/210 minutos nessas amostras. NPCs não somam estatísticas próprias; XP de partida corresponde aos jogos com minutos; replay preserva recompensas/homologação. Resultados aleatórios diferentes não demonstram desbalanceamento entre modos.
- `mysql-expresso.json`: **37 verificações** na coorte final Repository/PDO com recarga por comando, duas contas, lock real em segunda conexão, nonce divergente/replay, crash após save/antes do ledger, retry e rollback integral. Uma edição nacional, zero renovações. Conquistas desligadas nessa fixture: não prova concessão de R nela. A quantidade de checks de XP varia com as partidas da campanha; a repetição anterior teve39.
- `future-d-plan.json`: **11 verificações**, equações de capacidade, seleção proposta determinística por resultado, exclusões/duplicidade, bloqueio e ausência de mutação. Fixture NPC isolada de contratos, não segunda temporada nacional.
- `http-runtime.json`: **27 verificações** de login descartável, CSRF, controller/replay, isolamento e chaveamento específico da Copa na fase em que CRB efetivamente entra. Fixture limpa ao final.
- `mobile/results.json`: **48 verificações**, Chrome 320/390/1280, teclado e overflow; capturas também do chaveamento CDB. Inspeção visual da captura 390. É HTML autenticado de PHP loopback, não dispositivo físico/IIS.
- `regressao/suites.json`: **24 suítes**, 1.014 cenas e contratos anteriores preservados. Adicionais: fronteiras11/24; catálogo50/1.000R (96 checks), feedback32, pós-jogo215/seis posições e três modos, flag desligada503 sem conversão. A coorte completa CDB usa meia; não se alegam dezoito novas temporadas de todas as posições.

Falhas intermediárias: comparação estrita de arrays do ledger após normalização JSON MySQL; corrigido o harness com igualdade estrita antes da gravação e comparação de valores independente da ordem após recarga. Nenhuma fórmula/XP alterada. No smoke específico da Copa, a fixture visual foi inicialmente apontada para a fase1, da qual CRB não participa, e foi corrigida para a fase2. Repetições finais passaram; fixtures com falha foram revertidas/limpas. Não esconder esses incidentes como sucessos iniciais.

## 5. Escala e reprodução

Teto1888 é exceção fechada para o conjunto exato de nove IDs e memberships96/20/126, apenas CLI/flags locais. Teto genérico1152/padrão128 permanece; orçamento de oito NPCs por continuação, sem worker externo. Os testes verificam descanso e agenda idempotente; o planner rejeita ultrapassar um ano.

Métricas reais por modo no JSON: Normal960 comandos; Rápido264; Expresso199. Picos de snapshot aproximadamente1,95/1,97/2,06MB nas coortes Game. PDO final:193 comandos,6.758 consultas; p50 **446,42ms**, p95 **878,70ms**, p99 **1.506,73ms**; pior comando/fechamento **1.648,23ms**; duração total98.342,19ms. Snapshot final1.661.195 bytes, pico2.016.971; homologação10.872 bytes. Histórico nacional anterior ainda vazio (2bytes); **não há medição de crescimento em temporadas2/3/5**. Resultados locais, com cargas de teste variáveis; não benchmark controlado UOL/Apache nem prova de melhoria sobre entrega53.

Há repetição do contrato esportivo no pin e no torneio do snapshot. Proposta para otimização posterior: referência por digest com resolução versionada e leitura dos saves antigos, sem restauração/regravação em massa. Não foi aplicada nesta entrega; permanece junto do planejamento de persistência da v0.8.

PHP8.4/mbstring; pdo_mysql nos testes do banco local `rumohub`. Flags são definidas pelo processo, sem editar .env. Exemplos:

```
python bin/build-copa-brasil-sports.py
php tests/copa-brasil-sports-v07.php
php tests/copa-brasil-runtime-v07.php
php tests/copa-brasil-mysql-v07.php --local-rollback
php tests/serie-d-future-plan-v07.php
php tests/copa-brasil-http-v07.php --local-fixtures
node tests/copa-brasil-mobile-v07.cjs
```

Regressão: `V06_EVIDENCE_DIR` aponta para o diretório desta entrega ao executar `bin/test-narrative.py`. Relatórios antigos gerados foram copiados e originais preservados; gzip conferido antes de remover o JSON volumoso gerado. Sem DDL/migration/backfill. Rollback: manter flags desligadas e retornar o código, preservando campos aditivos; não abrir fixtures `copa_brasil` em versão antiga nem restaurar banco sobre progresso posterior. Saves reais nunca foram convertidos.

## 6. Fontes e próximo portão

Consultadas17/10/2026, fontes primárias:

- [REC Copa do Brasil2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf): arts.2,10–24, AnexosA/B; participantes PDFpp.19–21, chaves/mandos22–26.
- [CBF, critérios do sorteio27/01](https://www.cbf.com.br/futebol-brasileiro/noticias/selecao-base-masculina/a/sorteio-da-copa-do-brasil-sera-nesta-quarta-feira-confira-os-criterios): 14confrontos, oito potes, posições dos vencedores.
- [RNC2026 CBF](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/RNC_Ranking_Nacional_dos_Clubes_2026_27e24418e7.pdf): pp.1–3, edição23/12/2025. Usado como ranking, não overall.
- REC D/RNF2026: links e posições no contrato da proposta; fatos de2026 separados das adaptações futuras. Não há alegação de regulamento oficial2027.

Próximo lote: políticas classificatórias por federação e seus torneios NPC; adaptadores futuros C24/D com quatro slots; Copa do Brasil128 e demais obrigações futuras; somente depois aplicação atômica, duas renovações e cinco temporadas da mesma carreira. V0.8 permanece planejada; idade/XP/elencos/normalização não foram alterados.

**Flags usuais `RUMO10_WORLD_CALENDAR=0` e `RUMO10_WORLD_FIXTURES=0`. Sem liberação pública, Hub, produção ou deploy. A Copa2026 é jogável no piloto; a carreira nacional renovável ainda não está concluída.**

## Git

Código/testes: `06c59442bd80ac38af2410ae7ba49776693efe5b` (Copa do Brasil) e `ab945e28b825a80b5d92e4bdb77e6da2f77b2331` (direção futura D). Documentação/evidências em commit separado. Push exclusivo para `origin/codex/mundializacao-v07`, SHA final conferido no encerramento; sem force, merge/main ou deploy.
