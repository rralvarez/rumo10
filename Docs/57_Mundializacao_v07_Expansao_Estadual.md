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
