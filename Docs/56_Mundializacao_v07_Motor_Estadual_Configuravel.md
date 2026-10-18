# Entrega 56 — motor estadual configurável

17/10/2026. Base confirmada local/remota: `492f92b7e58042a0dd474e4b4dc38694468c7222`, branch `codex/mundializacao-v07`. Continuação da entrega 55, sem reimplementação de Alagoas ou Copa do Brasil.

## Resultado e limite do lote

**Piauí e Rondônia incorporados ao piloto local**, com contratos de edição, tabela inicial transcrita de fonte primária, disputa completa, classificação reconstruída e candidaturas à Série D. Alagoas permanece no adaptador anterior. Nenhum dos três é nova carreira pública liberada.

| Evidência / universo independente | AL | PI | RO | Total estadual | Vagas estaduais pendentes |
|---|---:|---:|---:|---:|---:|
| `sports.json`, execução final de contratos | 2 | 2 | 1 | 5 | 59 |
| Game Normal, Teresina | 2 | 2 | 1 | 5 | 59 |
| Game Rápido, Genus | 2 | 2 | 2 | 6 | 58 |
| Game Expresso, CRB | 2 | 2 | 2 | 6 | 58 |
| Repository/PDO Expresso, CRB | 2 | 2 | 2 | 6 | 58 |

Não se agregam candidatos de saves distintos. A diferença em RO é proposital: quando um finalista já tem mérito nacional, não se escolhe um terceiro colocado com ordenação não certificada. **O lote acrescenta três ou quatro candidatos comprovados**, conforme os resultados do save, aos dois de AL. A matriz das 27 federações usa explicitamente a execução final `sports.json`: cinco candidatos, 59 vagas estaduais ainda pendentes. Não é promessa de seis classificados em qualquer carreira.

**Zero inscrições nacionais efetivadas e zero renovações nacionais aplicadas.** Uma edição nacional completa por fixture, não cinco temporadas consecutivas. Com 28 méritos nacionais anteriores, o universo `sports.json` possui 33 candidatos e déficit total 63 (59 estaduais + quatro residuais da transição). O PDO possui 34 candidatos potenciais/58 vagas estaduais pendentes, sem aplicar inscrição. As quatro residuais permanecem proposta separada.

## 1. Motor reutilizável e exceções

`ConfiguredStateCompetition`, versão `configured-state-v1`, recebe contrato completo por edição. Reutiliza `Schedule::table`, geração de liga, `Competition::knockout` e `Competition::resolve`, calendário compartilhado e processamento NPC existentes. Não altera o adaptador de Alagoas; suas peculiaridades continuam protegidas pelos testes anteriores.

Funcionalidades executadas:

- Liga em turno único ou ida/volta, grupos explicitamente inscritos e tabelas iniciais fixadas por edição.
- Liga seguida de mata-mata; mata-mata isolado; cruzamentos por índices declarados, classificação inicial, classificados por grupo ou vencedores anteriores.
- Um/dois jogos, mando por campanha ou classificação inicial, decisão por pontos/saldo e pênaltis ou vantagem **quando declarada**. Não se aplica regra de gol fora.
- Desempate ordenado por contrato: pontos, vitórias, saldo, gols, confronto direto entre exatamente dois clubes, cartões e sorteio persistido. Empate triplo não é resolvido por comparação par a par de confronto direto.
- Méritos de promoção/descenso dentro da edição. Trocas de memberships e renovação estadual não são aplicadas sem a divisão recíproca validada. Resultado do piloto sinaliza continuidade/descenso pendentes.
- Reconstrução sem sortear novos placares/pênaltis; recibos por carreira/edição/UF, replay e lote atômico em memória, persistido pela transação existente do Repository.

Os modos liga pura, grupos e mata-mata isolado foram exercitados também em **fixtures sintéticas identificadas**, não em novos campeonatos oficiais. Não é um interpretador universal: segunda liga posterior ao mata-mata, regras especiais de grupos cruzados, playoffs assimétricos e descenso por grupo precisam de contratos/adaptadores específicos. O contrato rejeita recursos não suportados, não tenta inferi-los pelo nome da competição.

`Competition.php` ganhou somente quatro dispatches opt-in para o novo formato. O teste de fronteira v0.6 remove exatamente essas quatro linhas e compara o arquivo legado integralmente, além dos hooks anteriores. Fórmulas, competição legada, XP-base e probabilidades permanecem preservados.

## 2. Edições, participantes e fontes

Fontes consultadas em 17/10/2026. Regras datadas e participantes reais de 2026; **resultados, campeões e candidaturas abaixo são ficção do save**. Nenhum placar histórico foi importado.

### Piauí — `pi-a1`, Piauiense Série A 2026

Oito IDs: `Altos-PI`, `Atlético Piauiense-PI`, `Parnahyba-PI`, `Oeirense-PI`, `Teresina-PI`, `Piauí-PI`, `Fluminense-PI`, `Corisabbá-PI`.

- [REC atualmente vinculado pela FFP](https://ffp-pi.com.br/assets/uploads/178043156618.pdf), [listagem oficial](https://ffp-pi.com.br/conteudo/?q=92&sc=9): arts.3,7–11,17,21; PDF pp.1,3–4,6,8. Turno único, quatro semifinalistas, ida/volta nas decisões, mando final por campanha; dois descensos. Índice final considera campanha para os não finalistas; desempate sem confronto direto.
- [Tabela oficial de sete rodadas](https://www.ffp-pi.com.br/assets/uploads/176306443317.pdf), [publicação FFP](https://www.ffp-pi.com.br/noticias/v/1433): 28 pares/mandos iniciais preservados; mais seis partidas eliminatórias = **34 jogos**.
- Art.11 §3 indica campeão/vice para D2027. Exclusões por mérito nacional e repasses usam classificação verificável, com a política conservadora já aprovada e a referência CBF da entrega 55. A inscrição nacional continua pendente.
- O REC tem referências editoriais a 2025 no art.11; cabeçalho, edição listada e §3 identificam 2026/2027. Isso foi registrado, sem reescrever o documento como regulamento de outra edição.

### Rondônia — `ro-a1`, Rondoniense Série A 2026

Sete IDs: `Porto Velho-RO`, `Genus-RO`, `Barcelona-RO`, `Rondoniense-RO`, `Ji-Paraná-RO`, `Guaporé-RO`, `União Cacoalense-RO`. Gazin Porto Velho e os nomes do Anexo A foram conciliados aos IDs; não houve alteração de clubes no Hub. IDs novos deste lote são referências locais da fixture, com fallback de rating já existente, **não novos overalls oficiais**.

- [REC vinculado pela FFER](https://drive.google.com/file/d/1IiXRE3n6xqyn0Y0Rs-IMcyxg0DxW3be9/view), [listagem oficial](https://ffer.com.br/Publicacao.aspx?id=178275): arts.3,5–6,11–17, Anexo A; PDF pp.2–3,5–6,11. Sete clubes, liga ida/volta, quatro semifinalistas; confronto direto antes de saldo e apenas para empate entre dois clubes; último da liga em posição de descenso.
- [Tabela completa](https://drive.google.com/file/d/1-V8mvdYmrGmSDNqF_AcWc4TDARHxum7z/view), [publicação FFER](https://www.ffer.com.br/Publicacao.aspx?id=645189) e [Comunicado 001/DCO/FFER/2026](https://drive.google.com/file/d/10bIvsGAow8D1IDqpb4BLaAXcDmKHQ54I/view): 42 jogos iniciais, quatro semifinais e duas finais = **48 jogos**. Art.14 usa singular, mas art.15 e referências 47/48 da tabela comprovam dois jogos na final.
- Art.5 indica campeão/vice às vagas D2027. **Somente o prefixo de duas posições é certificado para candidatura**. A ordem operacional dos semifinalistas não virou regra oficial de terceiro lugar; se o repasse exigir posição inferior, a vaga permanece indisponível. Política `ranking_prefix=2`, com rejeição rastreável.

As datas do planner são adaptação simulada aprovada, com descanso mínimo de três dias. Pares/mandos da primeira fase são reais; não se afirma reprodução das datas oficiais ou das inversões cronológicas do comunicado. Atas administrativas, desistências, BID, licenciamento, condições financeiras, publicação de registros e disciplina individual completa dos elencos não são certificados por estes adaptadores.

## 3. Provas, integração e saves

`br-state-batch-2026-v1.json` separa definições e políticas por UF. `StateResultProof` despacha para adaptador antigo ou configurável, confere calendário, classificação, mandos, pênaltis, cartões, lotes, versão e estrutura de fases. `StateQualification` exige pin íntegro, fonte, participantes e identidade exata do novo contrato; reemitir um pin com critérios alterados não permite qualificar.

`evaluateMany` reconstrói o fechamento nacional uma vez para o lote, sem aceitar digest fornecido pelo chamador e sem cache persistente que possa ficar obsoleto. `recordMany` valida tudo antes de aplicar qualquer recibo: conflito em uma UF não deixa outra parcialmente gravada. `FutureSerieDAdmission` reconstrói as provas do lote e exclui duplicidade com os 28 méritos nacionais, A/B/C seguintes e outras candidaturas.

Exemplo da execução final `sports.json`: AL selecionou CSE/Murici; PI Parnahyba/Piauí; RO União Cacoalense. As referências de resultados, ranking, rejeições e hashes estão no arquivo. Não são classificados históricos reais.

Recibos AL anteriores continuam verificáveis; a suíte da entrega 55 passou novamente. Ledgers históricos imutáveis não são sobrescritos para acrescentar novos candidatos; é necessário tratamento explícito de versão antes de eventual reconciliação. Não houve migração, conversão automática, DDL, regravação de save real ou alteração de campos narrativos.

`StateBatchTrial` constrói somente fixture CLI com flags locais. Onze competições, **1.970 jogos**: as nove anteriores/1.888 + PI34 + RO48. O teto maior é exceção limitada ao conjunto exato e aos contratos/participantes do lote; oito NPCs por continuação, teto genérico preservado. Não há endpoint público para criar essa fixture.

## 4. Cobertura das federações e renovação

Matriz atualizada: `motor-estadual/federations.csv` e `federations.json`, 27 UFs/64 vagas. Preserva o inventário anterior e acrescenta IDs, participantes/fontes e limites PI/RO; não certifica retroativamente os outros 24 caminhos.

| Caminho | Quota | Comprovados na matriz final | Pendentes |
|---|---:|---:|---:|
| Alagoas, adaptador preservado | 2 | 2 | 0 |
| Piauí, configurável | 2 | 2 | 0 |
| Rondônia, finalistas certificados | 2 | 1 | 1 |
| Demais 24 federações | 58 | 0 | 58 |
| Total | 64 | 5 | 59 |

Bloqueios nacionais: 58 caminhos/vagas das outras federações, eventual repasse RO sem prova, quatro residuais da transição, adaptador D futuro, inscrições e contratos futuros de copas obrigatórias. Copa Norte indicada pela FFER não foi implementada nem tratada como dispensa. Nordeste/futuras vagas estaduais e demais obrigações não estão integralmente cobertas; onze competições na fixture não equivalem ao universo brasileiro completo.

C24/quatro descensos, D96/quatro acessos e Copa do Brasil128/157 jogos aprovados permanecem com os estados da entrega 55; nenhuma regra oficial de 2026 foi alterada. Não há renovação parcial A/B/C/D, mercado novo ou vaga nacional efetiva. **Não foram feitas duas renovações ou cinco temporadas da mesma carreira.**

## 5. Testes e incidentes

Evidências em `Docs/mundializacao-v07/motor-estadual/`:

- `sports.json`: **54 verificações** de contratos/disputa, liga/grupos/mata-mata/combinação, pontos/mandos, H2H antes do saldo em RO e ausente em PI, empate triplo, cartões/lotes, pênaltis/vantagem, adulterações, quotas, orçamento, lote atômico, replay e equivalência de provas individuais/em lote.
- `game-modes.json`: **11.862 verificações**, três modos, uma edição de 1.970 jogos cada, calendários simultâneos, descanso, NPCs, títulos/fechamento e replay. Personagem atuou por Teresina no Normal (nove jogos estaduais), Genus no Rápido (14), CRB no Expresso (11 em AL e cinco partidas CDB). Não recebeu partidas CDB quando não inscrito. Os universos foram inicializados separadamente, não renovados.
- `mysql-expresso.json`: **41 verificações**, uma edição Expresso, recarga por comando, duas contas descartáveis, isolamento, lock real, nonce divergente, crash entre save/ledger, retry, rollback, persistência/replay dos recibos e bloqueio da renovação. Conquistas desligadas nessa fixture: não prova concessão de R nessa coorte.
- `regressao/suites.json`: **24 suítes, todas com exit0**, 1.014 cenas preservadas. Adicionais: pós-jogo215/seis posições/três modos; catálogo de conquistas96/50 objetivos/1.000R; feedback32; fronteiras11+24; save com flag desligada503 sem conversão.
- `http-runtime.json`: **29 verificações**, PHP loopback autenticado com fixture e limpeza, CSRF, duplo POST/replay, isolamento, liga/CDB e chaves PI/RO. Não é login central IIS ou Apache.
- `mobile/results.json`: **72 verificações** Chrome 320/390/1280, teclado/reduced-motion, ausência de overflow e erros JS; inclui as classificações PI/RO com conteúdo efetivamente renderizado. Captura390 RO inspecionada visualmente. Não se alega aparelho físico ou UOL.
- `presentation.json`: seis verificações adicionais; o resumo do formato novo identifica classificação final simulada e descenso pendente, sem chamar o resultado de primeira fase ou prometer repetição da divisão. As páginas de RO explicam o limite de repasse além dos finalistas. HTTP/mobile foram reexecutados após esse ajuste de apresentação, sem mudança do motor.

Incidentes corrigidos no harness: colisão entre variável de recibos e função de quantil no teste Game; contagem PDO herdada de nove, corrigida para onze; fixture visual inicialmente trocava a competição mantendo CRB não inscrito, gerando HTML incompleto. A fixture passou a escolher um clube efetivamente presente na chave e exigir nome da competição/HTML completo e heading visível. Fixtures MySQL com falha foram revertidas; HTTP limpo. Nenhuma fórmula foi alterada para fazer teste passar. O guard legado foi ampliado somente para os quatro dispatches novos exatos.

## 6. Desempenho e propostas v0.8

PDO local: **205 comandos, 7.178 consultas**, p50 **422,77ms**, p95 **856,58ms**, p99 **1.221,51ms**; pior comando **2.862,34ms**, fechamento **1.596,34ms**, total **104.572,02ms**. Snapshot final **1.709.041 bytes**, pico **2.050.545**, fechamento10.915, histórico anterior2. Pico de memória do processo PDO150.994.944 bytes, incluindo checkpoints/cópias do harness; não representa só a aplicação em produção.

Referência da entrega55: 200 comandos/7.003 consultas, p95 871,52ms, snapshot final1.531.616/pico2.009.066 bytes. O lote acrescentou 82 partidas e alterou a campanha do personagem; números não constituem comparação controlada de velocidade. O snapshot final cresceu177.425 bytes nessa amostra, sem projetar isso linearmente por cinco temporadas.

Prova de três UFs, três execuções: chamadas separadas **8.335,76ms**, lote **2.723,80ms**, com igualdade de recibos testada. É observação local sob carga variável, não benchmark UOL nem promessa universal. Nenhuma consulta SQL é feita pela reconstrução em memória; a melhoria evita fechar a mesma pirâmide uma vez por UF. Os dados estáticos continuam repetidos no snapshot/pins, sem compactação estrutural.

Propostas para v0.8, não executadas: resolver contratos imutáveis por digest com leitor legado; guardar agregados de classificação validados por digest/versão do resultado; persistir placares NPC em blocos menores sem duplicar contratos e checkpoints; medir consultas redundantes de Repository antes de modificar a transação; arquivamento versionado de edições. Qualquer cache deverá invalidar com alteração de placar/cartões/lotes/pin, sem confiar apenas no flag `done`.

Não há medições temporadas2/3/5, pois a continuidade não está pronta. Não se alteraram XP, força, probabilidades, aging, elencos ou regras planejadas da v0.8 para reduzir latência.

## 7. Reprodução, recuperação e Git

PHP8.4 com mbstring; pdo_mysql no teste MySQL local `rumohub`; Python e Chrome/Playwright para evidências. Exemplos:

```
python bin/build-state-batch-v07.py
php tests/configured-state-v07.php
python bin/audit-state-batch-v07.py
php tests/configured-state-runtime-v07.php
php tests/configured-state-mysql-v07.php --local-rollback
php tests/configured-state-http-v07.php --local-fixtures
node tests/configured-state-mobile-v07.cjs
```

Regressão: definir `V06_EVIDENCE_DIR` para `Docs/mundializacao-v07/motor-estadual/regressao` e executar `python bin/test-narrative.py`. Evidência grande comprimida, gzip conferido; saídas históricas regeneradas copiadas para este lote e restauradas nos paths anteriores.

Sem DDL/migration, nenhum backup/restauração de produção. Rollback não destrutivo: flags usuais desligadas, retornar código e manter campos aditivos; não tentar abrir fixture `configured_state` em versão sem o adaptador e não restaurar dump sobre progresso posterior. Banco de fixtures revertido/limpo; usuários/saves reais, .env, RumoHub/rh_*, IIS/Apache e produção preservados. O diretório anterior não versionado `Docs/levantamento-2026-10-15/` não integra os commits.

Workflows conferidos: deploy por push somente `main`. Commits/push exclusivos da branch de trabalho, sem force/merge/deploy; SHA final registrado na resposta após conferência remota.

Commits de implementação: `368a24c` (motor, prova e apresentação) e `888643e` (PI/RO, contratos e lote de classificação). Testes, relatório e evidências em commit separado.
