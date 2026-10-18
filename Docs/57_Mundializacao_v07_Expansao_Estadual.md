# Entrega 57 — Distrito Federal executável no piloto local

18/10/2026. Base real local/remota conferida: `2f245a051af2dec186535858847262112137933a`, branch `codex/mundializacao-v07`. Árvore inicialmente limpa, exceto a pasta preexistente `Docs/levantamento-2026-10-15/`, preservada. Continuação por UF; sem nova auditoria mundial.

## Entrega efetiva

**DF integrado ao Game, calendário compartilhado e Repository/PDO.** O Candangão2026 tem **50 jogos-base:45 de liga,4 de semifinal e1 final**. O piloto passa a **oito federações**: AL,PI,RO,BA,ES,PE,RN,DF; **16 competições e2.203 partidas-base** por edição. Foram concluídas três edições Game independentes (Normal/Rápido/Expresso) e uma edição PDO independente. **Nenhuma renovação nacional aplicada; nenhuma sequência de cinco temporadas alegada.**

O jogo extra não foi substituído por sorteio: existe estado persistido `world_pending`, tela explicativa e retomada mediante designação explícita auditada de fixture. A suíte específica provocou empate, criou a partida extra e a disputou com personagem no Game e MySQL, com **90 minutos reais nessa fixture**. Os três universos Game completos tiveram50 jogos DF cada e zero designações extras; o caminho excepcional foi testado separadamente, não contado como outra temporada completa.

A Copa ES permanece **não executável e sem candidatura nacional**: além da divergência do ano, foi identificada composição inicial de nove clubes versus oito na tabela atual. Isso não impediu o percurso DF.

## Fontes e identidades DF

- [REC Candangão2026](https://ffdf.com.br/assets/uploads/176245464195.pdf?v=17652869932): art.2 (dez participantes), PDF p.4; arts.12–15 e13§2 (fases/ranking), p.8; arts.16–22 (final, desempate, simultaneidade, descenso e vagas), p.9.
- [Tabela oficial FFDF](https://www.ffdf.com.br/pt/competicoes/jogos.php?q=1626): primeira fase3675, endpoints públicos `jogos_ajax_ver3.php` observados no próprio HTML. Foram conciliados os45 pares, rodadas e mandos. Não copiar placares históricos nem tratar as fases3676/3677 como tabela inicial.
- Quota federativa de referência: [CBF REC D2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf), art.2 pp.4–6/RNF2026. Aplicação à edição simulada2027 permanece projeção conservadora, não homologação oficial da CBF2027.

SHA-256 do REC: `4b38e9b0ada3cc8fbf2834b18a6437fbfe66837a9f21342647dc08c9aae00054`; tabela pública: `3a26e4fad863c0c7a19d71ff7c7efbcf75d50a0fc9e5315b0ddbef6ca5e5f654`. Referência nova `database/competition-references/br-candangao-2026-v1.json`, com identidades e tabela sem resultados.

Participantes: ARUC,Brasília,Brasiliense,Capital,Ceilândia,Paranoá,Real Brasília,Samambaia,Sobradinho,Gama. Reutilizados os IDs existentes de Brasiliense,Capital,Ceilândia eGama. Seis referências locais de participantes restantes, com fonte e fallback de jogo explicitado; sem novos ratings, cidades, escudos ou elencos e sem escrever catálogo Hub. BFC→Brasília e nomes de SAF conciliados pela tabela/REC, sem confusão com clubes de outros estados.

## Regras implementadas e adaptações declaradas

Liga de turno único; top4 à semifinal1º×4º/2º×3º, ida/volta. Volta na casa do melhor da liga e vantagem da classificação inicial em empate agregado. Final única na Arena BRB Mané Garrincha, pênaltis se necessário. Campeão e vice pelo resultado; 3º/4º pela **campanha apenas da semifinal**; 5º–10º pela liga. Dois últimos têm mérito de descenso, sem troca automática com divisão inferior ausente.

O art.17 termina em **jogo extra com mando/definição FFDF**, após pontos, vitórias, saldo, gols, vermelhos, amarelos e confronto direto. Para dois empatados, o confronto direto usa o resultado do encontro. Se persistir, não usar `lots`. Para três ou mais sem resolução pelos critérios anteriores, o formato do desempate requer decisão federativa: fica pendente, sem inventar mini-liga ou escolher dois clubes arbitrariamente. Essa limitação é deliberada e visível, não um falso resultado final.

**Aprovações expressas do proprietário neste lote** (`approvals.json`):

1. Pausar e exigir designação auditada de confronto/mando/critério do empate; decisões sintéticas identificadas somente nos testes, sem resolução pública automática.
2. Campo neutro: média das expectativas espelhadas já existentes, preservando diferença de força e limites. Para forças iguais, ambos recebem a mesma base. É adaptação do simulador restrita a jogos marcados `neutral`, não novo balanceamento geral.

`MatchVenue` delega partidas normais exatamente à fórmula anterior. Game, NPC e MatchBalance usam a mesma decisão de campo neutro. Ordem nominal de exibição da final deriva do seeding inicial, **convenção do simulador sem vantagem de mando**, não ato histórico do DCO. Nenhuma fórmula de evolução/XP/atributos foi alterada.

A última rodada tem os cinco jogos na mesma data e horário simulados (15h), atendendo conservadoramente à simultaneidade dos decisivos do art.20. Datas são adaptação consolidada, não agenda histórica oficial. O planner reserva a janela como grupo atômico, respeita descanso e rejeita agenda anterior parcial/divergente. As forças dos cinco confrontos ficam fixadas na abertura da janela. O processamento PHP continua sequencial em lotes; a tabela durante o lote é explicitamente parcial e **não há homologação, título ou mérito antes de todas as partidas terminarem**. Não alegar execução de cinco processos simultâneos em tempo real.

## Pendência, auditoria e prova

`ConfiguredStateCompetition` v3 é opt-in só do DF; v1/v2 e definições das sete UFs anteriores preservados. Sorteio não é critério aceito em v3. A transição de fase é atômica em memória: um desempate pendente não deixa ranking pela metade nem duplica eliminados. Resultados/pênaltis já resolvidos são preservados.

`StateAdministration` registra decisão de teste com esquema, chave de pendência, referência, UTC, confronto, campo, mando, critério do empate e digest. A partida extra referencia o digest. A decisão só cria um jogo; não escolhe campeão. Replay reconstrói a decisão e o resultado persistidos. Pênaltis ausentes, iguais ou indevidos não são ressorteados nem aceitos como prova. Empate sem critério fornecido permanece bloqueado; formato com mais de dois clubes ainda exige definição adicional e não é solucionado por este operador de fixture.

A operação de designação é CLI/local, com flags de fixture e de designações de teste. **Nenhum endpoint administrativo público foi criado.** Um POST comum com campos de mando/clubes não registra decisão, comprovado no HTTP. O controle administrativo real de federação não foi implementado nem atribuído a um usuário Hub.

Orçamento ampliado somente pelo builder `CandangaoTrial`, marcador, definições e memberships exatos:2.203-base + até8 extras, teto2.211. Não elevar limite genérico; lote NPC8, guard de processamento preservado. Nenhum worker, fila externa ou reestruturação v0.8.

`StateResultProof` reconstrói tabela, fases, mandos, neutralidade, simultaneidade, jogo extra e decisões. `StateQualification` verifica resultados e exclusões nacionais, então emite até duas candidaturas DF pela classificação final e repasses do art.22. Fonte, carreira, edição e digest ficam no recibo. `FutureSerieDAdmission` continua separando candidatura, vaga residual e inscrição efetiva.

## Matriz de vagas — cada linha é um universo independente

| Evidência | AL | PI | RO | BA | ES | PE | RN | DF | Candidatos estaduais | Vagas estaduais pendentes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| sports.json | 2 | 2 | 1 | 3 | 0 | 2 | 2 | 2 | 14 | 50 |
| PDO Expresso | 2 | 2 | 1 | 3 | 0 | 2 | 2 | 2 | 14 | 50 |
| Game normal | 2 | 2 | 2 | 3 | 1 | 2 | 2 | 2 | 16 | 48 |
| Game rapido | 2 | 2 | 0 | 3 | 1 | 2 | 2 | 2 | 14 | 50 |
| Game expresso | 2 | 2 | 2 | 3 | 0 | 2 | 2 | 2 | 15 | 49 |

A matriz27UF em `df/federations.csv/json` usa **somente sports.json**: 14 estaduais, 50 pendentes e **quatro residuais separadas**. No mesmo save, DF acrescenta duas candidaturas ao subconjunto das sete UFs; não comparar diretamente com a contagem14 de outro save para inferir déficit fixo. Nenhuma soma entre carreiras. **Zero inscrições nacionais efetivadas, zero renovações nacionais.** RN mantém exatamente a política conservadora aprovada `rn-state-d-2027-sim-v2`; a vaga pendente da Copa ES não foi atribuída ao Capixabão.

## Testes reproduzidos

Evidências em `Docs/mundializacao-v07/expansao-estadual/df/`.

| Suíte | Resultado |
|---|---|
| `tests/candangao-v07.php` |45 verificações:45+4+1, mandos, semifinalistas, jogo extra/pendência múltipla, prova/negativos de pênaltis, recibos, neutralidade e janela simultânea/congelamento de força/retry. |
| `tests/candangao-runtime-v07.php` |13.245 verificações; três modos, uma edição completa de2.203 jogos por fixture,50 DF em cada; repouso, NPC, XP real e replay. Não são três temporadas consecutivas. |
| `tests/candangao-mysql-v07.php --local-rollback` |47 verificações; uma edição Expresso, personagem em11 jogos, reload por comando, segundo usuário, nonce divergente, crash/retry do fechamento, lock real1205 e rollback integral. |
| `tests/candangao-admin-replay-v07.php` |4 verificações: repetição da designação preserva timestamp/jogo, conflito rejeitado e flag obrigatória. |
| `tests/candangao-extra-v07.php [--local-rollback]` |5 verificações Game;15 com PDO, incluindo10 PDO/crash/retry/rollback. Jogo extra efetivamente disputado pelo personagem. Os45 placares preparatórios são **fixture sintética de empate**, não resultados reais ou outra temporada completa. |
| `tests/candangao-http-v07.php --local-fixtures` |77 verificações; login descartável/CSRF/replay/isolamento, oito estaduais, pendência, final neutra e nenhum poder administrativo por POST. Fixture removida. |
| `tests/candangao-mobile-v07.cjs` |159 verificações Chrome320/390/1280, pendência/final neutra/tabelas, teclado/Escape, reduced-motion e sem overflow;45 capturas. |
| `bin/test-narrative.py` |24 suítes passaram;1.014 cenas (214+800) e nove atributos preservados. |
| Rechecks pontuais |55 motor,22 roteamento,22 BA,46 ES,40 PE/RN,9 política RN;215 pós-jogo (seis posições/três modos),96 catálogo50/1.000R,32 v0.6.1/feedback. |

Na primeira regressão, a guarda antiga recusou a delegação nova de campo neutro. Foi acrescentada comparação **estrita e reversível da única chamada** contra a base, sem permitir outras alterações de MatchBalance; nova execução das24 passou. A evidência da primeira tentativa foi preservada. No harness de crash extra, a comparação foi ajustada para usar o snapshot MySQL recarregado antes do savepoint, como no fluxo existente; a repetição passou e todas as gravações de fixture foram revertidas. Na revisão final, os três negativos de pênaltis administrativos foram acrescentados e passaram na suíte específica; não se repetiram coortes pesadas sem mudança do caminho válido.

Conquistas desativadas nas fixtures PDO: não alegar concessão de R nessas coortes. Catálogo50/1.000R e pós-jogo/XP têm testes próprios. HTTP é PHP loopback, **não IIS/login central**. Chrome usa HTML autenticado capturado, não aparelho físico/leitor de tela. Apache/HTTPS/UOL/produção não foram testados nesta rodada.

## Custo local e limites de escala

PDO: **267 comandos, 9348 consultas**; p50 415.63ms, p95 656.88ms, p99 908.04ms, pior/fechamento 1519.32ms. Total 117171.88ms; snapshot final 1580940bytes, pico 1936509bytes, pico do processo/harness 146800640bytes. Histórico anterior2bytes nesta primeira edição; nenhuma métrica de crescimento entre temporadas foi fabricada.

Referência PE anterior:266 comandos/9.313 consultas, p95618,72ms, pico1.873.364bytes, personagem7 jogos; agora11 jogos. Não é comparação controlada por seed/carga; não inferir melhoria causal nem desempenho UOL. Não houve normalização/compactação nova de saves, cache persistente, DDL ou migração estrutural.

## Copa ES — investigação atualizada, não ativação

O [PDF vigente](https://futebolcapixaba.com/site/wp-content/uploads/2026/04/Regulamento-COPA-ES-2026.pdf), com adendo02/06, foi novamente baixado e mantém o mesmo SHA `fbd03b512132909770a81ef3190b0534da1b819034e60fdef2a449042e91123e`. O art.11§§1–2 ainda escreve D2026. A [publicação técnica de26/02](https://futebolcapixaba.com/2026/02/fes-publica-tabela-basica-e-regulamento-da-copa-espirito-santo-2026/), a [nota de24/06](https://futebolcapixaba.com/2026/06/vitoria-e-rio-branco-fc-decidem-o-titulo-da-copa-es-sicoob-na-quarta-feira-01/) e a confirmação de02/07 mencionam D2027. Isso sustenta uma **inconsistência documental**, mas não é retificação normativa localizada nem permissão para alterar o ano silenciosamente.

Também há uma divergência de composição: a nota/tabela básica anuncia **nove clubes**, incluindo Capixaba SC; a [página atual](https://futebolcapixaba.com/campeonatos/copa-es-2026/) apresenta **oito clubes** e tabelas de28+4+1 jogos. Não foi localizado nesta pesquisa um ato primário que esclareça a saída/tratamento do nono participante. Não presumir desistência, anular jogos ou descartar Capixaba SC.

Formato conhecido: liga, top4, semifinais ida/volta, final única no Kleber Andrade, pênaltis; mando nominal final por sorteio previsto no REC e ranking dos não finalistas por liga+semifinal. Para ativar só o esporte, faltam reconciliar a composição e a tabela autorizada e persistir o sorteio nominal/ato da final. O vínculo D continuará separado e bloqueado até esclarecimento. **Zero jogos simulados desta copa e zero candidaturas dela**; dados apenas investigados em `copa-es-investigation.json`.

## Segurança, reprodução e recuperação

Flags públicas/permanentes desligadas. Testes definem calendário/fixtures e, explicitamente, `RUMO10_NEUTRAL_SIMULATION=1`/`RUMO10_ADMIN_TEST_DESIGNATIONS=1` **só no processo**. Não editar .env, Hub/rh_*, usuários reais, benefícios, catálogo compartilhado, produção, IIS/Apache, serviços ou workflows. Sem DDL, dump ou restauração; não há migration SQL a executar. A pasta não versionada do proprietário foi preservada.

PHP `C:/PHP/php-8.4.26/php.exe`: `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`, acrescentar `-d extension=pdo_mysql` para MySQL/HTTP. Rodar scripts da tabela. MySQL aceita somente `rumohub` em localhost e reverte fixtures; **não executar HTTP simultaneamente aos testes PDO de contagem global**. HTTP antes de mobile. `python -X utf8 bin/audit-candangao.py` exporta a matriz27UF de um só save. `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/df/regressao` direciona as24 suítes. PDFs/HTML/CSRF/logs privados ficam ignorados em storage; gzip comparado ao JSON bruto antes da remoção da cópia regenerável.

Rollback: flags0, retorno do código à base `2f245a0`, sem restaurar dump sobre progresso posterior. Preservar snapshots/contratos/decisões de teste; não abri-los em versão sem v3. Nenhum save real foi convertido ou depende deste lote. Os campeonatos das sete UFs, seus IDs e a política RN estão preservados em `protected-base.json`.

Próximos bloqueios: decisão formal para desempates administrativos múltiplos/sem critério do empate; CupES (composição e vaga); demais vagas estaduais/obrigações futuras. Não concluir mundialização nem renovar A/B/C/D incompletas. A v0.8 permanece fora do escopo.

Motor/contratos: `1c56e57f355ad7b56226d5c6147d524ef13f8c9f`. Testes/exportador: `d064ecb84390b173d88ff35a04cd7d6a41875b06`. Evidências/documentação em commit separado; SHA remoto de encerramento confirmado após push e informado ao proprietário. Push exclusivamente na branch de trabalho, workflows main-only conferidos. Sem merge ou deploy.

---

## Histórico preservado das continuações anteriores

# Complemento aprovado — candidaturas simuladas RN2027

18/10/2026, após confirmação expressa do proprietário. A política `rn-state-d-2027-sim-v2` seleciona os **dois melhores elegíveis** da classificação final do RN, excluindo os clubes já contemplados por méritos nacionais verificados. É **adaptação do simulador**, não regra oficial CBF2027. Produz somente candidatura pendente: inscrições, consumo de vagas nacionais, recompensas e renovação continuam bloqueados.

Substitui apenas o bloqueio de capacidade zero da política RN descrita no checkpoint abaixo. As definições esportivas2026, fontes, regras PE e os seis percursos Game anteriores não mudaram; esses resultados históricos não foram reescritos. Recibos antigos incompatíveis continuam imutáveis e são rejeitados, não migrados automaticamente. Nenhum save real foi alterado.

Nova fixture independente `rn-approved-policy/results-pdo.json`: RN2, total estadual14, pendentes estaduais50, quatro residuais pendentes; **zero inscrições e zero renovações**. Não agregar esta fixture às anteriores. Evidência em `Docs/mundializacao-v07/expansao-estadual/pe-rn/rn-approved-policy/`.

`tests/rn-qualification-policy-v07.php`:9 verificações puras; com `--local-rollback`,15 verificações, incluindo6 de Repository/PDO/isolamento/rollback. Cobertos corte após exclusões, duplicidade, replay, conflito com recibo antigo e preservação de memberships/recompensas. Não repetir coortes pesadas: motor, contratos de competição, calendário e interface não mudaram (`scope.json`).

Limite observado no harness: emitir o ledger D em memória antes da primeira normalização MySQL pode provocar conflito por ordenação das competições ao recarregar. A fixture foi corrigida para carregar primeiro pelo Repository, como o fluxo PDO existente; depois emitir e persistir os recibos. O caminho inteiramente em memória→primeira persistência não foi corrigido neste complemento e não deve ser usado para backfill. Todas as escritas da tentativa inicial e da repetição foram revertidas. Sem DDL ou alteração de persistência.

Somente piloto local CLI, flags públicas desligadas, Hub/produção intocados. DF e CopaES conservam os bloqueios documentados. Commit/push somente na branch de trabalho.

---

# Entrega 57 — continuação PE e RN integrada ao piloto local

18/10/2026. Base local e remota conferida: `a03e757aced0dff5a5bb318da3fd6f2311449368`, branch `codex/mundializacao-v07`. Abaixo está a continuação atual; o relatório capixaba anterior foi preservado ao final como histórico.

## Resultado efetivo e limites

**Duas federações adicionais com competição esportiva executada: Pernambuco e Rio Grande do Norte.** Sete federações no piloto: AL, PI, RO, BA, ES, PE e RN. São 38 partidas em cada novo estadual, dentro de um universo de **15 competições/2.153 partidas**. Ambos passaram integralmente por Game nos três modos e, separadamente, Repository/PDO com personagem e recarga por comando. Isso não habilita novas carreiras públicas nem conclui continuidade nacional.

PE gera até duas candidaturas por classificação final e exclusões documentadas. **RN tem prova de classificação, mas a distribuição nacional permanece bloqueada:** o art.16 delega os critérios à CBF e não fixa a regra futura. Foi apresentada a proposta de dois melhores elegíveis, sem inscrição; enquanto não aprovada/documentada, `max_candidates=0`. Não se presume autorização nem se inventa seletiva. A competição é executável independentemente desse bloqueio de candidatura.

Na evidência canônica `pe-rn/sports.json`, de uma só carreira:

| UF | AL | PI | RO | BA | ES | PE | RN |
|---|---:|---:|---:|---:|---:|---:|---:|
| Candidatos comprovados/aceitos pela política | 2 | 2 | 2 | 3 | 0 | 2 | 0 |

Total **11 candidaturas estaduais**, **53 vagas estaduais pendentes**, mais **quatro residuais separadas**. Os 28 méritos nacionais não completam a edição. **Zero inscrições nacionais efetivadas e zero renovações nacionais aplicadas.** Cada coorte tem seus próprios resultados, registrados em `game-modes.json` e `mysql-expresso-{pe,rn}.json`; não somar candidatos ou temporadas de saves diferentes. No mesmo save canônico, PE reduz o déficit em dois; RN não reduz vagas enquanto sua política estiver bloqueada.

## Regulamentos e identidade

- [FPF-PE REC 2026](https://www.fpf-pe.com.br/assets/uploads/176499150263.pdf?v=17696371365), arts.11–16, PDF pp.11–14, Anexos A/B pp.29–30; [tabela oficial](https://fpf-pe.com.br/pt/competicoes/jogos.php?q=1651). Os 28 pares/rodadas/mandos foram extraídos do endpoint público usado pela própria página, fase3798; a correspondência dos oito IDs foi conferida na classificação oficial. Não confundir fase3801 (final) com primeira fase. Nenhum placar histórico foi importado.
- [FNF REC 2026](https://www.fnf.org.br/files/REC_Campeonato_Potiguar_2026.pdf), arts.6–17, PDF pp.5–8, Anexo I p.15; [tabela oficial de 2026](https://www.fnf.org.br/tabela/195/estadual-primeira-divisao-2026). A tabela confirma QFC/Potyguar nas posições que o anexo ainda descrevia como campeão/vice da segunda divisão2025. Os 28 pares/rodadas/mandos iniciais foram conferidos, sem copiar resultados.
- [CBF REC Série D2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf), arts.2–4 pp.4–6: quota de referência de duas vagas por PE/RN; classificação estadual de um ano alimenta o seguinte, sujeita às regras e confirmação. Quota não prova automaticamente o repasse específico RN2027.

Regulamentos PE/RN novamente baixados em18/10: hashes idênticos às cópias pesquisadas. Digests, mapeamentos de identidade e os pares sem placares estão em `br-pe-rn-2026-v1.json` e `pe-rn/sources.json`. Reutilizados Náutico, Sport, Retrô, Santa Cruz-PE, Decisão, Maguary, Jaguar, ABC, América-RN e Laguna. Referências locais adicionais estritamente de participantes: Vitória de Santo Antão, Potiguar de Mossoró, QFC, Santa Cruz de Natal, Potyguar Seridoense e Globo. Não há alias Vitória/Vera Cruz ou Santa Cruz-PE/Santa Cruz de Natal. Sem cidades, ratings, imagens ou elencos novos; sem escrita no Hub.

**PE:** liga única28; top2 diretamente à semifinal; playoffs3×6/4×5 em ida/volta; semifinal1º×vencedor4×5 e2º×vencedor3×6; final ida/volta. Voltas pela primeira fase, empates por pontos/saldo/pênaltis. Campeão/vice; derrotados das semifinais e playoffs ordenados pela **primeira fase**, não campanha acumulada. **Sem descenso2026**; 7º/8º não são tratados como rebaixados. A mudança de turnos2027 citada no REC não foi implementada nem declarada continuidade pronta.

**RN:** liga28 e mesmos tamanhos de fases, mas semifinal resemeada: líder contra vencedor de pior colocação inicial, vice contra melhor. Campanha do mando final e ranking3º–6º considera **apenas liga+semifinal**, excluindo playoffs e final. 7º/8º preservados na ordem inicial, com mérito de descenso, sem troca automática de memberships. Desempate inclui confronto direto somente entre dois. A final tem mandos nominais e Arena das Dunas como estádio comum conforme art.17 §§4/6; o simulador não altera a fórmula de força por esse metadado. BID, sanções e homologações administrativas reais não foram certificados.

Datas consolidadas e descanso de três dias são a adaptação simulada já aprovada, não agenda histórica oficial. Regulamentos2026 ficam fixados no snapshot; nenhum contrato futuro foi ativado.

## Implementação e provas

`ConfiguredStateCompetition` recebe duas opções opt-in do contrato v2: base inicial dos eliminados e fases permitidas da campanha; o novo ranking `qualified_campaign` restringe a ordenação acumulada ao prefixo elegível, preservando os dois últimos. Contratos anteriores sem essas opções mantêm seu comportamento e hashes.

`PernambucoPotiguarTrial` compõe `CapixabaTrial`, sem reimplementar os cinco estaduais. Orçamento2.153 autorizado somente com marcador e definições/memberships exatos. Não elevar teto genérico. `StateResultProof` reconstrói os novos formatos com os resultados e pênaltis persistidos, sem sorteio de novos resultados. `StateQualification` exige o contrato certificado; recibos imutáveis e `FutureSerieDAdmission` continuam bloqueando inscrição incompleta.

Preservada a correção `world_processing`. Sem alterações em Game, probabilidades, força, evolução, XP, transferências, autenticação, benefícios, conquistas ou feedback; `protected-base.json` comprova a base. Sem DDL, migrations, backfill ou alteração de saves reais.

## Testes e desempenho

Evidências: `Docs/mundializacao-v07/expansao-estadual/pe-rn/`.

| Teste | Resultado |
|---|---|
| `tests/pe-rn-v07.php` | 40 verificações:38+38, byes, cruzamentos distintos, mandos, ranking, exclusão de playoffs, adulteração, contratos inválidos, provas e recibos idempotentes. |
| `tests/pe-rn-runtime-v07.php` | 25.890 verificações; seis coortes independentes (PE/RN×três modos), uma edição completa de2.153 jogos em cada, personagem em campo, descanso, NPC sem XP do personagem e replay. |
| `tests/pe-rn-mysql-v07.php --local-rollback [--rn]` | 43 verificações em cada UF; duas contas descartáveis por execução, reload por comando, nonce divergente, fechamento/crash/retry/savepoint, lock real1205, isolamento e rollback integral. Uma edição por UF, **não duas temporadas consecutivas**. |
| `tests/pe-rn-http-v07.php --local-fixtures` |123 verificações; login descartável, processamento sem partida ativa, tabelas PE/RN e cinco componentes anteriores, controller/CSRF/duplo POST/isolamento e HTML completo. Fixture removida. |
| `tests/pe-rn-mobile-v07.cjs` | 129 verificações em320/390/1280px: tabelas PE/RN, cinco componentes anteriores, world_processing, teclado/Escape, reduced-motion e ausência de overflow;36 capturas de fixture. |
| `bin/test-narrative.py` |24 suítes passaram,214 legadas+800 novas cenas preservadas; seis posições/três modos pertinentes. |
| Rechecks específicos |55 motor estadual,22 roteamento,22 BA,46 ES,215 pós-jogo,96 catálogo de conquistas,32 v0.6.1. Evidências históricas anteriores restauradas e rechecks separados. |

Conquistas desligadas nas fixtures PDO descartáveis: não alegar que essas coortes provaram concessão de R; catálogo50/1.000R e progressão possuem testes próprios. Banco físico local `rumohub`, sem rh_*. A primeira conexão MySQL no sandbox foi negada por permissão de socket; repetida com permissão apenas para fixture local autorizada. Não houve falha MySQL esportiva oculta. HTTP por PHP loopback, não IIS/login central; mobile usa HTML autenticado capturado em Chrome, não dispositivo físico/leitor de tela. Apache/HTTPS/UOL não testados.

| PDO | Comandos | Consultas | p50ms | p95ms | p99ms | Pior/fechamento ms | Snapshot final bytes | Pico bytes |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| PE | 266 | 9313 | 403.47 | 618.72 | 883.77 | 1624.43 | 1527031 | 1873364 |
| RN | 264 | 9243 | 398.11 | 626.87 | 725.48 | 1817.86 | 1521884 | 1865242 |

Pico de memória do processo/harness PDO:138.412.032bytes em cada UF. AL/PI/RO/BA/ES permanecem íntegros. São76 jogos adicionais em relação ao piloto ES. As coortes não têm a mesma seed/carga/personagem do relatório anterior: não inferir melhora causal de p95 ou snapshot. Sem compactação, worker, cache persistente ou migração de saves da v0.8. Métricas são locais, sem promessa de latência UOL.

## DF e Copa ES: bloqueios específicos

**DF não integrado.** REC art.17 prevê jogo único extra com mando/definição FFDF após esgotar os critérios, sem sorteio. Faltam estado persistido de espera/decisão administrativa, fixture extra retomável/prova, desempate entre três ou mais clubes e tratamento da final neutra/ranking de semifinalistas. Art.20 também exige simultaneidade de jogos decisivos na última rodada. Menor próxima intervenção: suportar decisão federativa auditada no piloto, bloqueando a fase sem designação válida; testar jogo extra no calendário antes de considerar DF jogável. Fonte e detalhes em `documentary-blockers.json`. Não foi contado como federação integrada.

**Copa ES inativa para classificação nacional.** Fontes complementares FES de [24/06](https://futebolcapixaba.com/2026/06/vitoria-e-rio-branco-fc-decidem-o-titulo-da-copa-es-sicoob-na-quarta-feira-01/) e [02/07](https://futebolcapixaba.com/2026/07/rio-branco-fc-comemora-titulo-inedito-da-copa-es-sicoob-e-vaga-para-a-serie-d/) confirmam intenção esportiva D2027. Porém o REC/adendo disponível ainda escreve D2026 no art.11 §§1–2; não foi localizada retificação normativa que substitua esse texto. Conclusão limitada: comunicação operacional posterior é clara, divergência formal persiste. Nenhum campeão histórico foi importado; nenhuma segunda vaga foi concedida por inferência. A execução da copa e sua prova de classificação ainda faltam.

## Segurança, reprodução e rollback

Flags permanentes seguem desligadas; os testes habilitam calendário/fixtures apenas no processo CLI. Produção, IIS, Apache, Hub/API/rh_*, workflows, main e diretório `Docs/levantamento-2026-10-15/` intocados. Sem merge/deploy. Os workflows inspecionados só publicam push em main; esta branch não dispara deploy.

PHP `C:/PHP/php-8.4.26/php.exe`, opções `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; adicionar `-d extension=pdo_mysql` para MySQL/HTTP. Rodar os scripts da tabela; PDO uma vez sem `--rn` e outra com `--rn`, **sem concorrência com HTTP**, porque ele cria/remove fixtures próprias. HTTP antes do mobile. `python -X utf8 bin/audit-pe-rn.py` exporta matriz27UF/recibos do único save `sports.json`. `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/pe-rn/regressao` direciona as24 suítes. Gzip comparado byte a byte com JSON bruto antes de remover a cópia regenerável. HTML/CSRF/logs/dados de conexão ficam em storage privado ignorado.

Rollback não destrutivo: manter flags0 e retornar somente código à base `a03e757`, sem restaurar banco sobre progresso posterior. Não abrir snapshots de teste PE/RN em código sem os contratos; preservá-los. Nenhum save real depende desta entrega. Próximos portões: política RN comprovada/aprovada, DF, CopaES, demais federações e obrigações futuras. Não declarar mundialização ou renovação nacional concluídas.

Implementação: `3d20663ea124a8df771b108d70b80a53880a7b48`. Testes/exportador: `8378627bf61dbcce40fc2ce3b63c930cd98a5ca8`. Evidências/documentação em commit separado; SHA remoto final conferido após push e informado ao proprietário.

---

## Histórico preservado: checkpoint capixaba anterior

# Entrega 57 — Capixabão integrado ao piloto local

18/10/2026. Branch `codex/mundializacao-v07`, base local/remota conferida `1c88bb3e8191772143baf567b03a51bfb316cdc4`. Continuação do lote aprovado da Bahia; não houve auditoria mundial ou reimplementação de AL/PI/RO/BA. A pasta não versionada `Docs/levantamento-2026-10-15/` foi preservada.

## Resultado e portão de conclusão

**Uma federação adicional real entregue nesta continuação: Espírito Santo.** Capixaba Série A 2026 executa 59 jogos no calendário integrado, com personagem nos três modos, persistência Repository/PDO, prova reconstruída e recibo de candidatura nacional. Cinco federações no piloto: AL, PI, RO, BA e ES. A Entrega 57 acumula BA e ES, sem contar pesquisa documental como cobertura.

O critério mínimo desta continuação, pelo menos mais uma federação integrada e testada, foi atendido. PE/RN/DF continuam pendentes de implementação. Não é conclusão da mundialização, das quatro federações priorizadas ou da renovação nacional. Nenhuma carreira pública foi habilitada.

| Universo independente | AL | PI | RO | BA | ES novo | Total estadual | Vagas estaduais pendentes |
|---|---:|---:|---:|---:|---:|---:|---:|
| `es/sports.json` | 2 | 2 | 2 | 3 | 1 | 10 | 54 |
| `es/mysql-expresso.json` | 2 | 2 | 2 | 3 | 1 | 10 | 54 |
| Game Normal | 2 | 2 | 1 | 3 | 1 | 9 | 55 |
| Game Rápido | 2 | 2 | 2 | 3 | 0 | 9 | 55 |
| Game Expresso | 2 | 2 | 1 | 3 | 1 | 9 | 55 |

Não se agregam saves dessas linhas. A matriz de 27 federações `es/federations.csv/json` deriva exclusivamente de `es/sports.json`. Na fixture PDO: 28 méritos nacionais + dez estaduais = 38 candidatos potenciais, déficit 58 = **54 estaduais + quatro residuais**. As quatro residuais permanecem separadas e sem seleção automática. **Zero inscrições efetivadas, zero renovações nacionais.**

## Fontes, identidades e regras da edição-base

Fontes primárias consultadas novamente em 18/10/2026:

- [REC Capixaba Série A 2026](https://futebolcapixaba.com/site/wp-content/uploads/2025/12/Regulamento-Serie-A-2026.pdf), arts. 3–15, PDF pp.3–7. SHA-256 `41d3a886a5f3a95570b4f8bb8d6a14c7c3d20cc95d9a939bce8158e78d5f1a93`, idêntico à cópia preservada.
- [Página/tabela oficial da competição](https://futebolcapixaba.com/campeonatos/capixabao-2026/): os 45 pares, rodadas e mandos da primeira fase coincidem com a captura anterior. Só esses dados estruturais foram transcritos; placares históricos não foram importados.
- [Capixaba SC](https://futebolcapixaba.com/time/s-c-capixaba/) e [SC Brasil Capixaba](https://futebolcapixaba.com/time/sport-c-b-capixaba/) são entidades distintas. Não foi criado alias entre elas. A [página de Rio Branco FC](https://futebolcapixaba.com/time/rio-branco-f-c/) confirma Venda Nova; é distinto de Rio Branco AC/SAF.

Contrato novo e isolado: `database/competition-references/br-capixaba-2026-v1.json`, participantes/aliases e hashes fixados por edição. Preservadas as chaves existentes de Vitória, Rio Branco, Real Noroeste, Porto Vitória e Desportiva. Cinco referências locais adicionais de participantes: Serra, Vilavelhense, Forte, Capixaba SC e Rio Branco VN. Nenhum rating-base, cidade de catálogo, escudo, elenco ou registro do Hub foi alterado.

Disputa: liga única de 45 partidas; oito classificados; quartas ida/volta com vantagem da classificação inicial em empate; semifinal com cruzamentos fixos entre vencedores Q1/Q4 e Q2/Q3; final ida/volta. Mando da volta pela classificação inicial. Semifinal/final empatadas usam pênaltis persistidos. São **45+8+4+2=59 jogos**.

Desempate da liga: vitórias, saldo, gols, confronto direto por pontos somente entre dois ainda empatados, vermelhos, amarelos e sorteio persistido. Duas últimas posições produzem mérito de descenso, sem alteração de memberships. Só campeão/vice são posições finais certificadas para este caminho; a ordenação operacional dos demais não substitui a homologação do DCO e não gera repasse. Essa limitação é visível na tabela.

Datas são simulação consolidada com descanso mínimo de três dias. Não reproduzem horários, simultaneidade televisiva ou datas históricas oficiais. Não se apresentam regras derivadas futuras como regulamento oficial posterior.

## Origem das vagas e repasses

O REC da liga, art.15 §1, atribui **uma** vaga D2027 ao campeão. O art.15 §4 permite vice por acesso comprovado do campeão da Série D para a C. Desistência não é inventada; sem evento confiável, não é gatilho. Não se avança para terceiro colocado nem se escolhe vice automaticamente quando o campeão já tem mérito nacional retido.

`StateQualification` separa quota federativa de referência (**2**) da capacidade deste caminho (**1**), com escopo de posições condicionado ao resultado nacional validado. Mantém exclusões de divisões superiores/retidos e recibos por carreira/edição; uma candidatura pode ser zero, sem preencher artificialmente a vaga. O Rápido acima é um exemplo de recibo sem candidato novo.

A outra origem foi localizada na Copa Espírito Santo, **não implementada** neste lote. Há conflito primário: a [notícia da reunião FES](https://futebolcapixaba.com/2026/01/clubes-e-fes-realizam-a-primeira-reuniao-do-conselho-arbitral-da-copa-es-2026-que-comeca-em-abril/) anuncia D2027, mas o [REC da Copa ES, com adendo de 02/06/2026](https://futebolcapixaba.com/site/wp-content/uploads/2026/04/Regulamento-COPA-ES-2026.pdf), art.11 §§1–2, PDF p.6, ainda escreve D2026. SHA `fbd03b512132909770a81ef3190b0534da1b819034e60fdef2a449042e91123e`. O conflito foi registrado em `es/sources.json`, sem corrigir silenciosamente o ano ou conceder inscrição. Faltam execução da copa, resultados e esclarecimento da edição-alvo. A vaga permanece pendente.

`StateResultProof`, `StateQualification` e `FutureSerieDAdmission` continuam separando resultado comprovado, candidatura pendente e inscrição. O ledger não concede partida, título, XP ou R por candidatura. Contratos anteriores permanecem inalterados.

## Extensões mínimas e interface

- `ConfiguredStateCompetition` v2 passa a aceitar confronto direto **por pontos entre dois**, separado do saldo usado por outros regulamentos. Empate triplo pula esse critério conforme o REC capixaba. v1/v2 anteriores preservados.
- `CapixabaTrial` compõe o universo anterior e acrescenta ES: **13 competições/2.077 partidas**, lote NPC de oito. Orçamento ampliado somente com marcador, definições e memberships exatos. Tetos genéricos e builders anteriores não mudaram.
- O HTTP revelou um bug pré-existente da tela: `Game::currentIndex` era chamado em `world_processing` antes de haver partida ativa do personagem, truncando o HTML mesmo com HTTP 200. A view agora mostra preparação/continuação e tabela em preparação sem exigir partida, sem alterar o estado do calendário. Testes exigem HTML completo e retomada idempotente.
- Não há nova estrutura de persistência, DDL, migração, backfill, atualização de usuários ou conversão de saves. Recibos do piloto são gravados explicitamente pelo serviço/caller na transação do Repository; não existe scan global a cada GET.

## Testes reproduzidos

Evidências em `Docs/mundializacao-v07/expansao-estadual/es/`.
Os inventários e coortes anteriores na pasta raiz/`ba` registram os checkpoints anteriores; a matriz vigente desta continuação é `es/federations.csv/json`.

| Suíte | Resultado |
|---|---|
| `tests/capixaba-v07.php` | 46 verificações: 59 jogos, mandos, vantagem, cruzamentos, pênaltis gravados, adulteração, repasse restrito, capacidade de uma vaga, empate duplo/triplo e orçamento. |
| `tests/capixaba-runtime-v07.php` | 12.489 verificações; três modos com Capixaba SC jogando; uma edição de 2.077 jogos por fixture, reload JSON por comando, descanso e fechamento sem duplicações. |
| `tests/capixaba-mysql-v07.php --local-rollback` | 45 verificações: uma edição Expresso, personagem em nove jogos capixabas, Repository/PDO, dois usuários descartáveis, recarga, nonce/replay, crash injetado, lock real e rollback integral. |
| `tests/capixaba-http-v07.php --local-fixtures` | 69 verificações: login de fixture, processamento sem partida ativa, tabela em preparação, controller, CSRF, duplo POST, isolamento e HTML completo. Fixture removida. PHP loopback, não IIS/login central Hub. |
| `tests/capixaba-mobile-v07.cjs` | 105 verificações; Chrome headless 320/390/1280, teclado, reduced-motion e ausência de overflow; inclui captura da tela de processamento. HTML autenticado capturado, não dispositivo físico ou leitor de tela real. |
| `bin/test-narrative.py` | 24 suítes passaram novamente após a correção da view; 214 snapshots + 800 novas cenas preservados. |
| Bahia / AL-PI-RO / roteamento | 22 / 55 / 22 verificações reexecutadas, com evidências antigas preservadas e novas em `es/rechecks`. |
| Pós-jogo / conquistas / v0.6.1 | 215 / 96 / 32 verificações; seis posições, três modos pertinentes, catálogo 50/1.000 R e feedback/reconciliação. |

Conquistas desativadas na fixture PDO descartável: ela não prova concessão de R. Não houve cinco temporadas nacionais consecutivas: uma edição por modo e uma edição PDO independente, zero renovações. Não foram testados Apache/HTTPS/UOL nesta etapa.

Ocorrências resolvidas: o harness MySQL precisou respeitar processamento antes da estreia; uma substituição do número de competições também alterara o código esperado de lock 1205 para 1305, corrigido no teste e repetido com sucesso. O retorno era o timeout correto, não falha do lock. O truncamento HTTP foi uma falha real de renderização corrigida e retestada. Não há mudança de fórmula esportiva para facilitar testes.

## Métricas e limites

PDO local: **254 comandos, 8.893 consultas**; p50 **487,66 ms**, p95 **764,82 ms**, p99 **978,39 ms**, pior/fechamento **1.997,06 ms**; total **135.557,84 ms**. Snapshot final **1.486.907 bytes**, pico **1.824.009 bytes**, pico do processo/harness **136.314.880 bytes**.

Referência 56: 205 comandos/7.178 consultas, p95 856,58 ms, pico 2.050.545 bytes. Lote BA anterior: 215/7.528, p95 723,94 ms, pico 2.150.179 bytes. Agora há mais 59 jogos mundiais, mas o personagem é outro clube e disputou nove jogos contra 66 da coorte BA/CRB: tamanho menor de snapshot não comprova otimização. Coortes não controladas por seed/carga; não inferir melhoria causal ou desempenho UOL. Nenhum worker, compactação ou reestruturação v0.8 introduzido.

## Pendências, segurança e rollback

PE: byes, ranking dos eliminados e ausência de descenso 2026 ainda sem contrato integrado. RN: campanha com exclusão de playoff e repasses precisam de implementação. DF: jogo extra federativo, mando neutro e ranking de semifinalistas ainda não implementados; não foram substituídos por sorteio. Copa ES e demais obrigações futuras impedem continuidade nacional completa. Não declarar temporada pública íntegra apenas porque esta fixture fecha suas competições implementadas.

Flags persistentes continuam desligadas; fixture CLI define flags somente no próprio processo. Hub/rh_*, produção, IIS/Apache, workflows, main, idade inicial, XP, elencos e direitos de conta intactos. Escritas MySQL limitadas às fixtures revertidas/removidas; sem DDL, dump ou restauração de dados reais.

Rollback não destrutivo: voltar arquivos deste lote à base `1c88bb3`, sem restaurar dump sobre progresso posterior. Preservar eventuais snapshots locais de teste e seus contratos; não tentar abri-los em versão que não suporte o contrato. Nenhum save real foi convertido ou depende do piloto. Workflows conferidos: deploy somente em main, não nesta branch.

## Reprodução

PHP `C:/PHP/php-8.4.26/php.exe`, opções `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; acrescentar `-d extension=pdo_mysql` para MySQL/HTTP. Scripts listados acima na raiz; MySQL aceita somente banco local rumohub. Executar HTTP antes do mobile para produzir capturas privadas de HTML. `python -X utf8 bin/audit-capixaba.py` exporta matriz e fontes a partir de `es/sports.json`, sem agregar saves. PDFs/HTML privados em storage/qa, ignorados pelo Git. `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/es/regressao` direciona a regressão. Gzip comparado ao JSON bruto antes de remover apenas a cópia regenerável.

Commits por implementação, integração/testes e documentação; SHA remoto final confirmado após push e informado no encerramento. Sem merge ou deploy.

Implementação: `13b60849fbc4fb4f0f7a83ba513bcfb47ad7af9b`. Interface/testes de integração: `82ae849e328138f6849170ce20eb8251316ae1d8`. Documentação e evidências em commit separado; o SHA remoto de encerramento é confirmado após o push.
