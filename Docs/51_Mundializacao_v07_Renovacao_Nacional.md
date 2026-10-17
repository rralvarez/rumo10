# v0.7 — renovação nacional: checkpoint de homologação A/B/C

17/10/2026. Base local e remota conferida: `fa95e683d373b18087797e21d4f7e62a9dfeefc9`, branch `codex/mundializacao-v07`. Um checkout, sem commits posteriores na abertura. Preservado o diretório preexistente não versionado `Docs/levantamento-2026-10-15`. Flags normais de calendário/fixtures conferidas em `0`; .env intocado. Workflows de deploy por push restritos a main. Este trabalho não autoriza nem executou deploy.

**Entrega parcial explícita:** uma edição integrada A/B/C + Alagoas finalizada e homologada no piloto local, com **1.063 partidas** (A 380, B 384, C 216, estaduais 83). Há plano verificável de trocas recíprocas A↔B e B↔C, mas não aplicação das memberships nem criação da edição seguinte. **Zero renovações nacionais; não há carreira nacional completa liberada.** Cinco temporadas completas testadas são estaduais e estão separadas das coortes nacionais.

## 1. Lote de contratos e grafo

`database/competition-references/br-abc-2026.json` fixa os 60 participantes já existentes, fontes, limites de inscrição, enlaces e capacidade de destino. Não duplica clubes/competições, não muda catálogo global nem lê o catálogo vigente para repor participantes entre requests: o piloto persiste contratos por edição.

| Relação | Estado do novo piloto |
|---|---|
| A → B / B → A | resultados de ambas disputados; troca de quatro por quatro homologada como proposta |
| B → C / C → B | resultados de ambas disputados; troca de quatro por quatro homologada como proposta |
| C → D / D → C | bloqueada; D não disputada/validada neste universo; dois descensos e seis acessos necessários |
| Torneios obrigatórios | Copa do Brasil e Nordeste do CRB comprovadas, runtime ausente; demais inscrições externas também exigem cobertura |

A C não pode continuar artificialmente com vinte clubes: o fechamento deixa um draft de dezoito, pendente dos seis ingressantes da D para atingir 24. Esse draft é diagnóstico, não uma lista efetiva ou inscrição oficial de 2027. A e B têm drafts de vinte, mas não são aplicados parcialmente para esconder a falta da C/D/copas.

Fontes primárias, consultadas em 17/10/2026:

- [REC A 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_SA_rie_A_2026_v15_12_2025_final_02692c1077.pdf): arts.3,8,9,14–15; Anexo A, PDF p.16.
- [REC B 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_B_2026_85d55b9f72.pdf): arts.3,5–6,11–14; Anexo A, PDF p.16. Título por pontos não é título de playoff/acesso.
- [REC C 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_C_2026_d2552ddc00.pdf): arts.2,5,7,12–26,42; Anexos A/B, PDF pp.16–18.
- [CBF: transição da C](https://www.cbf.com.br/futebol-brasileiro/noticias/detalhes/competicoes-campeonato-brasileiro-serie-b/cbf-publica-documentos-tecnicos-da-serie-c-de-2026): capacidade seguinte de 24; não é lista de participantes do save.

O REC C art.5 imprime o destino “B 2026”; o art.25 refere 2027. Registramos a inconsistência, sem alegar errata oficial. O enlace do simulador usa próxima edição. Anexo B imprime Guarani/SC, mas o Anexo A identifica Guarani/SP; preservado o ID Guarani-SP já existente. Nenhum resultado histórico foi importado.

## 2. Motor e homologação

`NationalTrial::createABC` prepara somente fixture CLI com A/B/C e estaduais. `NationalCCompetition` é um adaptador exclusivo desse contrato, acionado por quatro dispatches explícitos em Competition. Todo o código legado restante permanece igual; a fronteira v0.6 verifica isso removendo apenas os quatro hooks exatos.

A C tem fase inicial, quadrangulares, final, ranking completo e dois méritos de descenso. Os mandos iniciais 10/9 respeitam as prioridades do Anexo B; a ordem de confrontos/datas é simulada, não a tabela oficial. Desempates e mando da final são próprios da fase. A final gera um título geral; acesso/quadrangular não gera taça adicional. O adaptador não aceita uma suposta edição futura com 24 participantes sem implementação específica.

`NationalRenewal::close` roda dentro do comando/transação existentes, após todos os torneios terminarem. Confere contratos, participantes disjuntos, quantidade de jogos, resultados finalizados, classificação, playoffs, promoção/descenso e campeão. Persiste `national_closures[season]`: snapshots de participantes/classificações, hashes de contratos e resultados, propostas recíprocas, déficit, bloqueios e selo imutável. O registro distingue homologação simulada de execução de acesso.

Um replay preserva exatamente o selo; resultado/contrato/ranking alterado causa erro. Ordenação das chaves JSON do MySQL não quebra a comparação. Classificações externas novas continuam pendentes; não viram partidas/vagas/recompensas. A escrita em memória também é preparada antes de ser aplicada, evitando estado parcial em conflito de evidência. A transação PDO permanece responsável pela atomicidade completa com o ledger de comandos.

Não foi implementado o passo de executar as trocas, arquivar uma edição nacional anterior e iniciar a próxima. Fazer isso agora omitiria dependências. A tela de encerramento e o erro de tentativa de mercado informam as razões, sem apagar o progresso. Mercado internacional segue bloqueado nesse piloto. Não foram alterados força, probabilidades, evolução, XP-base, posse ou ritmo de progressão.

Inscrições simuladas de A/B/C usam listas separadas do catálogo, com 25–50 atletas e dois goleiros pelo contrato do jogo. **BID, contratos, licenciamento, janelas e transferências intratemporada não são certificados.** Não anunciar elegibilidade legal completa. Sem mudanças em jogadores ou ratings do Hub.

## 3. Série D e copas

Novo `br-d-annex-2026.json`: os 96 IDs existentes conciliados com o Anexo A, com critério por clube. Contagens efetivas: 4 pelo critério 1, 64 pelo 2, 14 pelo 3 e 14 pelo 4. O limite de 28 do critério 3 não é quantidade efetiva obrigatória. Quotas estaduais do critério 2 conferidas contra RNF/política v2. Isso avança a evidência da edição-base, **não ativa a nova admissão**.

[REC D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf): arts.2–3, Anexo A, PDF pp.16–18; art.14 prevê diretriz específica de grupos após confirmações. Faltam evidências complementares de seletivas/substituições, validação do runtime por edição e política de vagas futuras produzidas pelos resultados do save. `SerieDAdmission` e política v2 preservados/bloqueados. `Competition::completeWorld` foi comparado byte a byte normalizado com a base: preenchimento paulista legado **intocado**.

[REC Copa do Brasil](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf), art.2/Anexo A: 126 participantes-base, entradas em fases distintas; previsão de 128 na edição seguinte. CRB consta do Anexo A (PDF p.19). Faltam ledger completo de inscrições/substituições, sorteios/chaves, fases/runtime e política futura.

[Tabela oficial Nordeste](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/Tabela_Detalhada_1_Fase_Copa_do_Nordeste_2026_29740e7e40.pdf), PDF pp.1–2, refs.004/012/022/035/042, confirma CRB. A notícia técnica oficial indexada foi localizada, mas sua abertura retornou 502; o REC completo do Nordeste não foi validado neste lote. A [CBF também confirma a exclusão de participantes continentais](https://www.cbf.com.br/futebol-brasileiro/noticias/copa-nordeste-masculino/a/copa-do-nordeste-semifinais-tem-inicio-nesta-quarta-feira). Geografia ou candidatura estadual não bastam para inscrição.

`cup-dependencies.json` separa essas provas dos itens ausentes. Nenhuma copa nova foi ativada, nem troféu, R ou XP concedido por migração/pesquisa. Os 23 vínculos estaduais e nove estrangeiros não foram liberados. As rotas antigas do produto não foram convertidas.

## 4. Escala e desempenho

O limite padrão segue 128; o construtor CLI explícito A/B/C pode solicitar 1.152. Orçamento de oito NPCs por continuação, reaproveitando `world_processing`, cursor, nonce/versão, transação e retomada existentes. Sem worker/cron externo. Normal/Rápido/Expresso encerraram os seis torneios, com retomadas de lote e agenda após playoffs/final.

| Métrica PDO local | Base relatório 50, B+estaduais | Novo piloto A/B/C+estaduais |
|---|---:|---:|
| Comandos | 620 | 734 |
| Consultas | 21.703 | 25.693 |
| Maior comando | 242,33 ms | 877,73 ms |
| p50 / p95 | não medidos | 258,72 / 401,12 ms |
| Fechamento | 160,65 ms | 394,24 ms |
| Pico do snapshot | 780.351 bytes | 1.263.388 bytes |
| Snapshot final | 508.225 bytes | 886.930 bytes |

Ledger de homologação: 5.942 bytes; competition_history nacional ainda vazio (2 bytes), pois **não houve renovação**. Edição inteira: 212,08 s distribuídos entre os comandos. Medição observacional local, com outras regressões em execução, participantes/placares distintos; não é benchmark controlado ou medição UOL/Apache. Persistem cerca de 35 consultas e regravação do snapshot por comando. Não há prova de custo estável após múltiplas renovações nacionais. A compactação existente remove apenas campos vazios de NPCs; nenhuma normalização nova de banco foi feita.

## 5. Testes reproduzidos

Evidências: `Docs/mundializacao-v07/renovacao-nacional/`.

- `contracts.json`: 121 verificações de mandos, desempates, ranking/título C, conciliação D, trocas propostas, duplicidades, finais pendentes, edição futura recusada, selo, alterações conflitantes e compactação.
- `runtime.json`: uma edição gameplay A/B/C+Alagoas, 1.063 partidas, 2.166 verificações; zero renovações.
- `modes.json`: três modos, uma edição nacional por coorte, 6.411 verificações. Não são três temporadas consecutivas da mesma carreira.
- `mysql.json`: 25 verificações; uma edição completa com Repository/PDO e recarga por comando, segunda conta intacta, lock concorrente 1205, nonce/replay divergente, falha injetada depois do save e antes do ledger do comando, recuperação por savepoint e retry idempotente. A tentativa de renovar sem dependências é recusada e preserva o save. Tudo revertido.
- `state-mysql.json`: cinco temporadas estaduais completas, 33 verificações, recarga/isolamento/lock/rollback. Não contam como cinco temporadas nacionais.
- `http-runtime.json`: 26 verificações autenticadas em PHP loopback com conta descartável removida: continuations, CSRF, nonce e isolamento de parâmetros de conta. Hub desligado na fixture.
- `mobile/results.json`: 39 verificações Chrome 320/390/1280, capturas do HTML autenticado loopback, teclado e sem rolagem horizontal; não dispositivo físico.
- `regressao/suites.json`: 24 suítes aprovadas, 1.014 cenas preservadas. `final-checks.json`: fundação de calendário 163; flag desligada; fronteiras 11/24; pós-jogo 215; 50 conquistas/1.000 R (96 verificações); v0.6.1 32; contratos estaduais 68.

As fixtures PDO desativam concessão de conquistas; a coorte não comprova nova concessão de R. O catálogo/regras publicados foram preservados e testados separadamente. Não houve teste novo de login Hub real, IIS autenticado, Apache/HTTPS ou produção; não alegamos isso. O jogador do piloto nacional é CRB/meia; seis posições são cobertas nas regressões existentes, não por dezoito novas carreiras nacionais.

A injeção de crash usa savepoints na fixture de transação externa com commit/rollback interceptados, para não modificar dados reais; isso testa o ponto de falha do Repository, não um desligamento físico do MySQL. Homologação nacional não permite renewal crash posterior porque a renovação permanece corretamente indisponível.

## 6. Reprodução, rollback e próximo lote

PHP 8.4 local, mbstring; PDO MySQL nos testes correspondentes. Executar somente no banco local `rumohub`; scripts verificam host/ambiente e usam fixtures. Exemplos:

```
php tests/national-renewal-contracts-v07.php
php tests/national-renewal-runtime-v07.php
php tests/national-renewal-modes-v07.php
php tests/national-renewal-mysql-v07.php --local-rollback
php tests/national-renewal-state-mysql-v07.php --local-rollback
php tests/national-renewal-http-v07.php --local-fixtures
node tests/national-renewal-mobile-v07.cjs
```

Nenhuma DDL, migration, backfill ou alteração de schema: novos dados pertencem ao JSON de fixtures rumo10. Sem backup/restauração de produção. Rollback seguro: manter flags desligadas e retornar o código; não restaurar banco sobre progresso posterior. Não tentar abrir fixtures national_c com o código antigo: os saves reais nunca foram convertidos.

Próximo lote mínimo: validar D por edição/grupos/entrada e seis acessos no universo; contrato derivado explícito de C com 24; implementar a cobertura das copas obrigatórias do clube; só então executar atomicamente as trocas, arquivar a edição, gerar a próxima e demonstrar duas renovações e cinco temporadas nacionais. Sem essas provas, não há autorização técnica para liberar nova carreira pública.

Commits e push somente da branch `codex/mundializacao-v07`, SHA remoto confirmado na entrega. Sem merge, deploy FTP/UOL, código/API/schema/dados RumoHub, tabelas rh_* ou banco produtivo. **O critério de aceite da carreira nacional ainda não foi atingido; este é um checkpoint seguro com progresso mensurável.**
