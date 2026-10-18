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
