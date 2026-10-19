# Entrega 58 — Maranhão executável com portões documentais; Acre conciliado parcialmente

18/10/2026. Base `69ead9b802bb91972f8506e658d6bc6980eb8096`, branch `codex/mundializacao-v07`. As dez federações aprovadas e seus contratos foram preservados. A pasta preexistente `Docs/levantamento-2026-10-15/` permanece intocada e excluída dos commits. Histórico anterior abaixo.

## Resultado efetivo e limites

**Maranhão entrou no Game, calendário e Repository/PDO: 36 partidas quando não existe empate documentalmente indefinido.** São28 de liga,4 semifinais,2 de permanência e2 de final. A integração técnica passa a11 federações; **MA tem portão de homologação e repasses ainda limitado**, não equivale a homologação irrestrita. Acre não foi ativado; não declarar12 federações concluídas.

As fases conhecidas são disputadas mesmo se a permanência ficar indefinida. Ao fim, sem fonte que resolva o empate, o motor conserva resultados e bloqueia homologação, título, candidaturas e avanço. Não sorteia o vencedor, não presume pênaltis e não inventa jogo extra.

Universo do builder:19 competições,2.361–2.363 jogos-base conforme a permanência GO, teto específico2.371 incluindo até8 extras DF. Budget NPC8 e limite genérico preservados. **Zero inscrições nacionais efetivadas, zero renovações nacionais aplicadas.** Não são cinco temporadas consecutivas nem uma liberação pública.

## Fontes MA, edição e participantes

- [REC definitivo FMF2026](https://www.futebolmaranhense.com.br/assets/uploads/17651550147.pdf): art.4, inscrições nacionais, opções e descenso; arts.12–14, liga, semifinais e final; art.22, omissões.
- [Tabela oficial FMF2026](https://www.futebolmaranhense.com.br/assets/uploads/176374611975.pdf):28 confrontos iniciais e mandos/slots das fases seguintes. A linha com horário `16::00` não fez perder um confronto: horários reais não foram importados e a cobertura de28 pares foi conferida.
- [RGC publicado na página de regulamento geral](https://www.futebolmaranhense.com.br/assets/uploads/175039859589.pdf): documento disponibilizado como2025, art.21, três pontos por vitória/um por empate. Não foi identificado nele procedimento que encerre o empate da permanência MA2026. Publicação vigente consultada, sem alegar regulamento oficial futuro.

Hashes, bytes e localizadores em `database/competition-references/br-maranhense-2026-v1.json` e `ma/sources.json`. Nenhum placar histórico foi importado; resultados são ficção do save e datas são simulação do calendário consolidado. BID/licenças/decisões disciplinares reais não certificados.

Oito identidades: IAPE, ITZ Sport, Luminense, Maranhão, Moto Club, Sampaio Corrêa, Imperatriz e Tuntum. Cinco IDs locais reutilizados; ITZ Sport,Luminense,Tuntum são referências isoladas do Rumo10, sem preencher Hub, ratings oficiais, cidades, escudos ou elencos. Não houve fusão por semelhança de nomes.

## Motor opt-in e homologação

`configured-state-v6-ma` entra apenas em `MaranhenseCompetition`. V5 GO, v4 PR e contratos anteriores permanecem intactos. Liga valida todos os28 pares, sete jogos por clube, rodadas1–7, adversários e mandos explícitos. Ranking por pontos,vitórias,saldo,gols pró,confronto direto entre dois,vermelhos,amarelos e sorteio simulado persistido. Não é uma ata de sorteio federativo. Não foi implementada uma nova regra geral de regulamentos.

Semifinais1×4/2×3, volta do1º/2º inicial. Final em ida/volta, volta da melhor campanha liga+semifinais. Mata-matas conhecidos usam pontos,saldo e pênaltis persistidos. Liga e ramos não são recalculados pelo catálogo atual após pin da edição.

Último da liga tem mérito direto de descenso. Sexto/sétimo jogam duas partidas, volta do sexto, em paralelo às semifinais. Se a soma dos pontos das duas partidas produz vencedor inequívoco, esse clube permanece; RGC art.21 sustenta o sistema de pontos. **Se os pontos empatam, a homologação permanece pendente mesmo com saldos diferentes**, pois não se transplantou o desempate específico da semifinal para a permanência.

A final pode ser disputada antes da pendência impedir o fechamento; seu vencedor ainda não recebe título enquanto o resultado completo não puder ser homologado. Quinto/oitavo iniciais preservados; semifinalistas3º/4º por campanha; vencedor/perdedor da permanência6º/7º. Rebaixados são7º/8º finais, sem efetivar troca com divisão inferior não integrada.

A pendência é persistida, tem chave derivada dos resultados e replay sem novas partidas/recompensas. O operador CLI de extra DF **recusa** a pendência MA: não basta um comando sintético para transformar uma omissão em regra oficial. A tela MA explica a ausência de critério, sem dizer que o REC exige jogo extra. Nenhum endpoint administrativo foi criado.

## Série D e opção entre vagas

REC art.4 prevê campeão,vice e3º para D2027, sujeito à disponibilização; os itensVI–VIII também preveem opção do clube quando há múltiplas vagas remanescentes em competições distintas. A matriz conservadora anterior usa quota de referência CBF2026 **MA2, total64**, que não foi ampliada silenciosamente para65.

O caminho deste piloto certifica somente finalistas elegíveis, no máximo2, excluindo méritos nacionais já comprovados. Terceiro e repasses além dos finalistas ficam com motivo `national_option_not_verified`. Isso é um limite conservador explícito da projeção simulada, **não homologação oficial das vagas2027**. Uma terceira vaga e a opção entre torneios precisam de política/inscrição confirmada antes da execução. Se a competição está pendente, nenhuma prova final/candidatura MA é concedida.

`StateResultProof` reconstrói tabela, chaves, mandos, ranking, campeão e descensos quando certificados. `StateQualification` e `FutureSerieDAdmission` mantêm recibos por carreira/edição, idempotência e separação de candidatura/inscrição. Não se combinam resultados de saves diferentes.

## Acre:33/34 explicado, ativação ainda bloqueada

A [tabela oficial da FFAC](https://www.ffac.com.br/competicao/2026/acreanao-sicredi-2026/partidas) atual tem **34 links distintos de partidas**. O [REC, arts.6–9](https://www.ffac.com.br/competicoes/regulamento/43), prevê28 de liga,4 semifinais,1 bronze e1 final, total34. Os dois últimos registros são [966](https://www.ffac.com.br/partida/966) e [967](https://www.ffac.com.br/partida/967). Nenhum placar foi incorporado.

A [notícia dos33 jogos](https://ffac.com.br/noticia/ffac-divulga-a-tabela-detalhada-do-campeonato-acreano2026) não conta a disputa de bronze prevista no REC e presente na contagem da tabela. Esta é uma conciliação documental por confronto das fontes, **não uma errata formal localizada**. Não foi removido o bronze.

Ainda faltam contrato conciliado dos mandos genéricos da final/bronze e procedimento/designação do jogo extra do art.9, especialmente empate entre vários clubes. Mandante de uma partida histórica não prova regra genérica de mando para outros finalistas simulados. A vantagem da semifinal é por igualdade de **pontos**, antes de qualquer saldo, e foi registrada como requisito; não foi ativada por analogia com outro mata-mata.

Alternativas: localizar ato/tabela de designação aplicável aos confrontos simulados; ou implementar pendências auditadas específicas, com confirmação prévia do formato administrativo. Campo neutro ou sorteio não foram escolhidos automaticamente. **Acre: zero jogos no Game/PDO, zero candidaturas novas.** Próximo lote pode aproveitar a conciliação34 sem repetir a pesquisa geral.

## Evidências e testes

Pasta deste incremento: `Docs/mundializacao-v07/expansao-estadual/lote2/ma/`. Coortes e métricas finais abaixo.

### Resultados finais das coortes

| Coorte independente |MA jogos|MA homologado|Candidatos MA|Total estadual|Vagas estaduais pendentes|Residuais|Renovações|
|---|---:|---|---:|---:|---:|---:|---:|
|PDO Expresso|36|sim, sem empate na permanência|1|23|41|4|0|
|Game normal|36|sim|1|21|43|4|0|
|Game expresso|36|sim|0|20|44|4|0|
|Game Rápido|36|não: empate na permanência|não concedidos|não homologado globalmente|não apurado globalmente|4|0|

Cada linha é uma carreira/execução independente, sem agregação. No PDO, o subconjunto das dez UFs anteriores **do mesmo save** tem22 candidatos; MA soma1 e chega23, pendentes42→41. Não comparar esse subtotal com outra execução antiga. `ma/federations.csv/json` exporta27 UFs desse único PDO; o ID efêmero não foi exportado e não foi inventado. O recibo foi gravado,recarregado e repetido pelo Repository antes do rollback. Reprodução: `bin/audit-maranhense-v07.py` exige evidência de coorte concluída; não exporta números de edição bloqueada como homologados.

Normal/Expresso completaram2.363 partidas do universo cada, com19 competições; personagem disputou9/7 partidas MA. Rápido parou depois dos36 jogos MA e237 comandos, preservando a pendência. A contagem global de partidas finalizadas não foi coletada corretamente nessa execução e está **null**, não zero; o contador do harness foi corrigido para execuções futuras. Não se repetiu uma coorte pesada apenas para preencher essa métrica. Os três modos foram exercitados, mas **não houve três edições homologadas**. PDO concluiu uma edição; não são temporadas sequenciais nem renovações.

### Testes e correções

| Suíte | Evidência |
|---|---|
| `tests/maranhense-v07.php` |150 verificações:28 pares,rodadas,semifinais/permanência paralelas,36 jogos,ranking,último direto,prova reconstruída,placares com margens diferentes,empates pendentes,replay e recusa do operador DF para MA. Casos esportivos sintéticos identificados; não são resultados históricos.|
| `tests/maranhense-runtime-v07.php` |9.473 verificações; Normal/Expresso concluídos,Rápido pendente esperado,calendário,provas,recompensas e ausência de renovação.|
| `tests/maranhense-mysql-v07.php --local-rollback` |45 verificações,uma edição com36 MA,recarga por comando,duas contas,nonce,replay divergente,crash após save antes do ledger,retry,lock1205 e rollback integral. Há ramo de teste para persistência de pendência; a execução final medida foi a concluída.|
| `tests/maranhense-http-v07.php --local-fixtures` |141 verificações,login descartável,CSRF,isolamento,HTML completo,11 estaduais,permanência e pendência MA sem promessa de jogo extra. Fixture removida.|
| `tests/maranhense-mobile-v07.cjs` |222 verificações,63 capturas,320/390/1280,teclado/Escape,reduced-motion,sem overflow/erros JS. Pendência MA320 inspecionada visualmente.|
| `bin/test-narrative.py` |24 suítes passaram,1.014 cenas,nove atributos preservados.|
| Rechecks |GO62,negativosPR5,DF45,pós-jogo215(seis posições/três modos),conquistas96(catálogo50/1.000R),feedback/v0.6.1 32; apresentação e resumo repetidos após ajuste de UI.|

A primeira versão do harness herdava a suposição de que toda coorte terminaria: encontrou corretamente a pendência MA e abortou. Foi ajustada para registrar bloqueio esportivo esperado em vez de resolver artificialmente. Outra comparação exigia igualdade de todo o estado após continuar, incluindo a versão que o Game incrementa; agora verifica estado esportivo,pendência e recompensas, permitindo o incremento de versão.

A reconstrução de semifinais revelou um erro novo no adaptador MA: em igualdade de pontos com saldos diferentes, o verificador escolhia somente pelo sinal dos pontos. Corrigido para pontos e depois saldo **apenas nos mata-matas documentados**; caso específico adicionado. A permanência conserva o bloqueio por empate em pontos. Testes específicos passaram após a correção; nenhum motor nacional,GO/PR,força ou probabilidade foi alterado.

Conquistas desativadas em PDO/HTTP: não alegar concessão de R nessas fixtures. Seis posições são cobertura do pós-jogo, não seis temporadas completas MA. HTTP é servidor PHP loopback com conta descartável e Chrome sobre HTML capturado; não IIS,identidade central real,Apache,HTTPS,UOL,leitor de tela ou aparelho físico. Nenhum teste AC no Game/PDO foi declarado.

### Desempenho local

PDO: 290 comandos, 10153 consultas; p50 476.35ms,p95 766.92ms,p99 990.38ms,pior/fechamento 1966.43ms. Total 153568.47ms; snapshot final 1672433bytes,pico 2050157bytes; pico de memória do harness 150994944bytes; histórico anterior 2bytes nesta primeira edição.

| Game concluído |Comandos|p95ms|Piorms|Pico snapshot bytes|
|---|---:|---:|---:|---:|
|normal|389|259.35|1462.68|2027276|
|expresso|292|265.63|1310.77|2042027|

Referência GO anterior:285 comandos/9.978consultas,p95647,63ms,pico2.030.943bytes. Agora há mais36 jogos; algumas suítes compartilharam CPU. Não é comparação controlada nem previsão UOL. Sem curva multi-temporada ou otimização estrutural v0.8.

### Reprodução, recuperação e Git

PHP8.4 com `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; PDO/HTTP acrescentam `-d extension=pdo_mysql`. Executar scripts da tabela. DB somente `rumohub` local e `--local-rollback`. Não executar HTTP ao mesmo tempo que PDO,pois este confere contagens globais. HTTP antes de mobile. Resultados aleatórios podem levar a pendência legítima: nunca resolver automaticamente para forçar um teste concluído.

Regressão: `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote2/ma/regressao`; `python -X utf8 bin/test-narrative.py`. JSON bruto comparado com gzip antes de remover somente a cópia regenerável. PDFs,HTML autenticado,CSRF e logs ficam privados/ignorados em storage. Evidências antigas regeneradas foram copiadas como rechecks novos e restauradas da base,sem tocar a pasta alheia.

`ma/protected-files.json` confere35 arquivos protegidos e contratos anteriores contra a base69ead9b. Lint de14PHP,diff e revisão de segredos feitos. Sem DDL/migration: nenhum backup/restauração ou dado de produção envolvido. Todas as gravações PDO revertidas; fixture HTTP removida.

Rollback não destrutivo: flags públicas0; voltar ao checkpoint69ead9b para runtime público,preservando snapshots de teste v6 junto ao interpretador que os lê. Não restaurar dumps sobre progresso posterior. Sem conversão de saves reais. `.env`,Hub/rh_*,IIS/Apache,serviços,produção e workflows intocados. Workflow continua main-only/manual; push da branch não faz deploy. Commits separados para implementação,testes e evidências:

```text
7e5700817d0acacd2324333ecec36ee7fc23960d test: cover Maranhense completion and documented pending paths
d31023aba3152dd246c075d3e42d74a3d37a9779 feat: integrate Maranhense with guarded permanence homologation
```

SHA remoto confirmado no resumo após push. Sem merge nem v0.8.

---

# Entrega 58 — Goiás executável, interpretador condicional v5

18/10/2026. Continuação da base `500c46cb75e9ed710841d029f7703abfe11f1689`, branch `codex/mundializacao-v07`. O histórico Paraná/pesquisa foi preservado abaixo. A pasta alheia `Docs/levantamento-2026-10-15/` permanece intocada e fora dos commits.

## Resultado esportivo deste incremento

**GO adicionado ao piloto isolado: dez federações AL, PI, RO, BA, ES, PE, RN, DF, PR, GO; dezessete restantes.** São 18 competições no universo de teste. Goiás executa 48 jogos intergrupos, oito quartas, quatro semifinais, duas finais, quatro semifinais de colocação e duas finais de colocação: 68 jogos obrigatórios, mais zero, uma ou duas partidas de permanência. A edição conjunta tem 2.325–2.327 jogos-base; teto específico 2.335 inclui oito extras administrativos opcionais do DF. Budget NPC oito preservado; teto genérico não ampliado.

Não há nova carreira pública, inscrição nacional efetivada ou renovação aplicada. É uma edição por coorte, não cinco temporadas consecutivas. MA e AC permanecem sem competição executável neste checkpoint; os bloqueios específicos estão abaixo.

## Fontes e identidades

- [REC definitivo disponibilizado pela FGF](https://fgf.esp.br/assets/uploads/176833012335.pdf): arts.6–7, grupos e classificação geral; 9–14, cruzamentos, campanha e mandos; 15–18, colocação5–8; 19–20, permanência; 21–25, ranking, descenso e vagas.
- [Tabela oficial Goianão2026](https://www.fgf.esp.br/assets/uploads/file/Tabela%20Goian%C3%A3o%202026.pdf): 48 confrontos, rodadas1–8, mandantes e visitantes. Nenhum resultado histórico importado.

URLs, SHA256, bytes, consulta e localizadores estão no contrato `database/competition-references/br-goiano-2026-v1.json` e em `go/sources.csv`. O REC atual tem SHA diferente do documento pesquisado anteriormente. A comparação textual identificou alterações de condição de jogo/registro no art.4 §§3/5; não tratamos o documento anterior como a versão corrente. BID, suspensões administrativas e elencos reais não são certificados. Não houve alteração de idade, atributos ou geração de jogadores.

GrupoA: Vila Nova, CRAC, Inhumas, Goiatuba. GrupoB: Anápolis, Goiás, Jataiense, Aparecidense. GrupoC: Atlético, ABECAT, Centro Oeste, Anapolina. Nove IDs existentes reutilizados; Jataiense, Centro Oeste e Anapolina têm referências isoladas do Rumo10, sem criar dados no Hub, ratings oficiais, cidades, escudos ou elencos. Fallback já existente explicitamente identificado; origem em `go/participants.csv`.

## Contrato opt-in e decisões condicionais

Somente `configured-state-v5` entra em `ConditionalStateCompetition`; v1/v2/v3/v4 e o arquivo `IntergroupStateCompetition.php` permanecem preservados. A validação confere três grupos de quatro, oito jogos/quatro mandos por clube, cada adversário externo exatamente uma vez, rodadas, duplicidades e dependências das fases. Tabela geral determina os oito classificados, independentemente dos grupos. Não se aplica o corte por grupo do Paraná.

Quartas1×8/2×7/3×6/4×5. Semifinais e playoff5–8 reordenam os respectivos vencedores/perdedores pela campanha liga+quartas. Mandos são separados dos adversários, com volta da melhor campanha conforme a fase. Desempate dos mata-matas por pontos, saldo e pênaltis persistidos; ranking por pontos, vitórias, saldo, gols pró, vermelhos, amarelos e sorteio simulado persistido. Não confundir esse sorteio com ata federativa real.

Permanência é uma máquina de estados registrada no snapshot:

- Diferença inicial10º–11º superior a seis: nenhum jogo criado, 11º descende junto do12º.
- Diferença até seis: cria **somente a ida**, mando do11º, junto à barreira das quartas.
- Depois da ida, soma os pontos da liga e da partida. Diferença superior a três: encerra e **não cria a volta**; não existe partida fantasma/cancelada com XP ou calendário.
- Caso contrário, cria a volta no mando do10º. Decisão por campanha liga+permanência, incluindo os desempates do art.20, sem usar saldo isolado do confronto nem pênaltis.

As duas partidas, quando necessárias, compartilham as barreiras dos demais ramos. Só há avanço após todos os jogos obrigatórios da barreira. Fechamento e decisões são aplicados em cópia validada. `StateResultProof` reconstrói inclusive a ausência regulamentar de jogos; adulteração de placar, mando, rodada, ranking, motivo, campanha ou pênaltis indevidos é recusada. Recarga não sorteia de novo.

Ranking preserva9º/12º iniciais; semifinalistas3º/4º por campanha; playoff define5º–8º; permanência define10º/11º. Descenso é mérito esportivo, não troca efetivada com divisão inferior ainda não integrada. Título principal só no fechamento final. Prêmio secundário de campeão do interior do art.23 não foi criado por inferência geográfica, nem convertido em conquista/XP/R.

## Interface, candidaturas e fronteiras

A classificação mostra tabela geral e grupos intergrupos, explica o corte geral e apresenta a permanência com motivo explícito: sem playoff, ida confirmada, volta necessária ou volta dispensada. Partida do jogador usa sua fase real. Rótulos técnicos do contrato não são apresentados como regras oficiais futuras.

`StateQualification` valida o contrato fixado e a prova reconstruída. Até três elegíveis de GO geram candidaturas, excluídos méritos nacionais já comprovados. A quota2026 é referência para a projeção conservadora simulada2027, não homologação futura: REC arts.24–25 remete condições à CBF. Recibo por carreira/edição, sem somar saves, sem R/XP ou inscrição. A ausência de outras classificatórias/obrigações mantém `FutureSerieDAdmission` e renovação bloqueados.

## Pendências MA/AC e nacionais

MA: [REC definitivo FMF](https://www.futebolmaranhense.com.br/assets/uploads/17651550147.pdf), art.4 confirma último direto e permanência6º×7º em duas partidas, volta do6º. O art.12 explicita pênaltis nas semifinais/final, mas não resolve expressamente o empate da permanência nesse trecho; art.22 reserva omissões ao DCO. A próxima intervenção mínima é conciliar o RGC aplicável ou ato do DCO, além da tabela e escolhas entre vagas nacionais. Não transplantar pênaltis de outra fase por analogia. Zero jogos MA integrados.

AC: [REC FFAC](https://www.ffac.com.br/competicoes/regulamento/43) prevê bronze e vantagem por igualdade de pontos; a [notícia oficial da tabela](https://ffac.com.br/noticia/ffac-divulga-a-tabela-detalhada-do-campeonato-acreano2026) informa33 jogos. A conta com bronze é28+4+1+1=34. Não foi localizada retificação conciliada nesta checagem focal; tabela/mandos, bronze e eventual jogo extra continuam pendentes. Zero jogos AC integrados. Não omitir bronze nem substituir vantagem em pontos por saldo.

Copa ES segue inativa e sua vaga nacional bloqueada. DF mantém50 jogos-base, neutralidade, simultaneidade e pendência administrativa auditada. RN mantém a adaptação conservadora aprovada. SérieD96, C24 futura, CDB128/157 e quatro vagas residuais seguem sem liberação quando faltam inscrições e obrigações. Nenhuma renovação parcial.

## Testes, métricas e reprodução

Evidências deste incremento em `Docs/mundializacao-v07/expansao-estadual/lote2/go/`. Resultados finais e métricas reproduzíveis abaixo. Os testes são fixtures locais, não carreiras reais.

### Contagens finais por universo

| Coorte |AL|PI|RO|BA|ES|PE|RN|DF|PR|GO|Total candidatos|Estaduais pendentes|Residuais|
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| sports859001 |2|2|1|3|0|2|2|2|3|3|20|44|4|
| PDO Expresso |2|2|0|3|1|2|2|2|3|3|20|44|4|
| normal |2|2|2|3|1|2|2|2|3|3|22|42|4|
| rapido |2|2|1|3|1|2|2|2|3|3|21|43|4|
| expresso |2|2|2|3|1|2|2|2|3|3|22|42|4|

Cada linha é um universo independente. No save esportivo859001, GO adiciona3 ao subconjunto anterior **do próprio save**:17→20, pendentes47→44. Isso não é comparação controlada com os18 candidatos de outro checkpoint. As27 UFs estão em `go/federations.csv/json`, reproduzíveis por `bin/audit-goiano-v07.py`. Candidatura não é inscrição: a contagem de admissões efetivadas continua zero.

### Suítes e limites

| Teste | Resultado |
|---|---|
| `tests/goiano-v07.php` |62 verificações:48 confrontos, classificação externa, condições estritas6/3, três edições isoladas68/69/70 com prova reconstruída, adulterações, JSON reordenado e recibos. Inclui probes sintéticos de fronteira identificados, sem tratá-los como resultados oficiais.|
| `tests/goiano-runtime-v07.php` |13.985 verificações; uma edição inteira em cada modo,18 competições,2.327/2.326/2.326 jogos no universo Normal/Rápido/Expresso; Goiás70/69/69; personagem12/9/8 partidas GO. Provas, títulos, descanso, candidaturas e replay.|
| `tests/goiano-mysql-v07.php --local-rollback` |44 verificações; uma edição Expresso,18 competições encerradas,8 partidas do personagem,XP por partidas efetivas,recarga por comando,duas contas,replay divergente,crash antes do ledger,retry,lock1205 e rollback integral.|
| `tests/goiano-http-v07.php --local-fixtures` |83 verificações:autenticação descartável,CSRF,isolamento,retry,HTML completo,dez UFs,grupos PR,tabela geral GO,permanência e pendência DF. Conta/fixture removidas.|
| `tests/goiano-mobile-v07.cjs` |201 verificações;57 capturas,320/390/1280px,teclado/Escape,reduced-motion,sem overflow nem erros JS. Inspeção visual dos estados GO320/390 feita.|
| `bin/test-narrative.py` |24 suítes aprovadas;1.014 cenas,nove atributos e métodos protegidos preservados.|
| Rechecks |PR77,negativosPR5,DF45,pós-jogo215(seis posições,três modos),conquistas96(catálogo50/1.000R),v0.6.1/feedback32.|

A primeira tentativa PDO encontrou comparação estrita de ordem em objeto JSON do contrato. Corrigida com digest canônico sensível aos tipos, insensível à ordem de propriedades; teste negativo de limiares e teste de reordenação acrescentados. A repetição PDO passou e o rollback foi confirmado. Não houve falha esportiva ocultada.

Conquistas desativadas nas fixtures PDO/HTTP; não alegar concessão de R nelas. Seis posições validadas no pós-jogo, não seis coortes completas GO. Chrome usa HTML autenticado capturado em loopback; não é aparelho físico,leitor de tela,IIS,Apache,HTTPS ou produção. A autenticação central real não foi exercitada. Rechecks/exportação nunca somam carreiras distintas.

### Métricas locais

PDO: 285 comandos, 9978 consultas; p50 421.19ms, p95 647.63ms, p99 908.65ms, pior/fechamento 1885.84ms. Total 134955.26ms; snapshot final 1652865bytes, pico 2030943bytes, memória do harness 148897792bytes; histórico anterior 2bytes nesta primeira edição.

| Game |Comandos|p95ms|Piorms|Pico snapshot bytes|
|---|---:|---:|---:|---:|
|normal|413|231.56|1397.79|2027169|
|rapido|294|306.12|1640.58|2036612|
|expresso|287|301.86|1442.86|2033995|

Referência PR:277comandos/9.698consultas,p95703,55ms,pico1.969.120bytes. O novo mundo tem até70 partidas a mais, e coortes compartilharam CPU; não é benchmark controlado. Não concluir melhoria estatística ou desempenho UOL. Não há curva multi-temporada: zero renovações. Sem mudança estrutural de persistência.

### Reprodução e recuperação

PHP8.4 com `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`; acrescentar `-d extension=pdo_mysql` para PDO/HTTP. Executar scripts da tabela; DB somente `rumohub` local, com flag `--local-rollback`. **Não executar HTTP simultaneamente à fixture PDO que compara contagens globais.** HTTP antes de mobile. Node/Chrome/Playwright locais conforme paths do harness; nenhum pacote remoto em runtime. Exportar matriz com `python -X utf8 bin/audit-goiano-v07.py`.

Regressão: `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote2/go/regressao`, depois `python -X utf8 bin/test-narrative.py`. Gzip conferido contra JSON bruto antes de remover só a cópia regenerável. PDFs/HTML/CSRF/logs ficam privados/ignorados em storage. Evidências anteriores regeneradas foram copiadas para rechecks novos e restauradas da base, sem sobrescrever a pasta alheia.

`go/protected-files.json` compara33 arquivos protegidos com a base, incluindo o interpretador v4 e referências anteriores. Lint/diff e revisão de segredos concluídos antes do commit. Workflows continuam main-only/manual: push da branch não faz deploy. Commits de implementação/testes:

```text
b584499048b1e2c62ab6e2456040998bdc31e093 test: verify Goiano gameplay persistence and mobile flows
d07310f104391c56b70b61b1f032fb010088c4b2 feat: integrate Goiano with conditional permanence v5
```

Commit de documentação/evidências separado; SHA remoto confirmado no resumo após push. Sem merge,Hub,DDL,produção ou flags públicas.

Sem DDL/migration ou backup produtivo. Rollback: manter flags públicas0; retornar ao checkpoint anterior para runtime público e preservar snapshots v5 de testes junto ao seu interpretador. Não restaurar dump sobre progresso posterior. `.env`, Hub/rh_*, serviços, IIS/Apache e UOL intocados; nenhum deploy ou merge. Sem v0.8.

---

# Entrega 58 — Paraná executável; lote permanece aberto

18/10/2026. Continuação sobre `e222f3c9de29f6a07246db2f0fbee30ae96d9b27`, branch `codex/mundializacao-v07`. Base local/remota conferida; nenhuma alteração alheia sobrescrita. A pasta preexistente `Docs/levantamento-2026-10-15/` foi preservada. O checkpoint anterior permanece ao final como histórico.

## Entrega efetiva deste checkpoint

**Paranaense 2026 integrado ao Game, calendário e Repository/PDO: 54 jogos completos.** São36 intergrupos,8 quartas,4 descenso,4 semifinais e2 finais. O piloto agora tem **nove federações: AL,PI,RO,BA,ES,PE,RN,DF,PR**; restam18. A edição conjunta tem17 competições e2.257 jogos-base. O teto do builder específico é2.265, incluindo até8 extras administrativos DF já previstos; o limite genérico e o lote NPC8 não foram ampliados.

O mínimo de uma nova federação executável com prova esportiva e persistência foi atingido. **GO,MA,AC não foram integrados e a Entrega58 inteira não está encerrada.** Zero inscrições e zero renovações nacionais; não alegar cinco temporadas consecutivas.

## Fontes, participantes e edição

- [REC Paranaense2026](https://federacaopr.sfo3.digitaloceanspaces.com/wp-content/uploads/2023/09/19162139/REC-1a-Divisao-2026.pdf): arts.8–10 (intergrupos/descenso),11–13 (chaves/mandos),14 (classificação),16–19 (méritos e vagas), anexoI (participantes).
- [RGCP2026 publicado pela FPF](https://federacaopr.sfo3.digitaloceanspaces.com/wp-content/uploads/2026/08/10114658/RGCP-2026.pdf): art.12, PDF pp.7–8. Versão disponibilizada na consulta18/10; não presumir que todas as disposições administrativas sejam idênticas ao texto originalmente publicado antes do campeonato.
- [Tabela pública da FPF](https://www.federacaopr.com.br/competicao/profissional/):36 confrontos da primeira fase, rodadas1–6, com mandante/visitante. Extração limitada a esses campos; **nenhum placar histórico, artilheiro, escudo ou estatística real importado**. Datas do calendário Rumo10 são simuladas e identificadas como adaptação.

As URLs/hashes/bytes/localizadores estão no contrato `database/competition-references/br-paranaense-2026-v1.json` e em `pr/sources.csv`. Uma busca retornou `Tabela-Serie-A-2026.pdf` de22/12/2025, mas o conteúdo era **Amador da Capital**, não o profissional: foi rejeitado, sem entrar no contrato. A primeira leitura HTTP da página profissional retornou403; a URL pública com `www` permitiu a obtenção. Esses cuidados impediram conciliar uma competição errada apenas pelo nome do arquivo.

GrupoA: Athletico,Maringá,Londrina,São Joseense,FC Cascavel,Foz do Iguaçu. GrupoB: Coritiba,Operário,Azuriz,Cianorte,Andraus,Galo Maringá. Nove IDs existentes reutilizados; três identidades ausentes são referências isoladas do jogo: Andraus,Foz eGalo. Não houve merge por semelhança com Hope Internacional ou outro clube. Não foram adicionados ratings,cidades,escudos ou elencos reais; os três usam o fallback existente, explicitamente sem rating oficial. `participants.csv` discrimina a origem. Hub e catálogo compartilhado intocados.

BID,alvarás e homologações administrativas reais não são certificados. Trata-se de **formato esportivo2026 com resultados fictícios do save**, não reconstrução do resultado histórico. Contrato fixado por edição e sem ativação pública ou conversão de saves.

## Interpretador opt-in v4

`ConfiguredStateCompetition` encaminha somente `configured-state-v4` ao novo `IntergroupStateCompetition`. Os caminhos v1/v2/v3 permanecem preservados;18 arquivos de referência anteriores foram comparados com a base (`protected-references.json`).

O contrato define grupos, tabela explícita, número de rodadas e mandos. A validação exige cada adversário do outro grupo, proíbe confrontos internos e duplicados, impede dois jogos do clube na mesma rodada e confere seis partidas/três mandos para cada participante. A classificação de cada grupo contabiliza **os jogos contra adversários externos**, sem filtrar esses resultados. Empates usam pontos,vitórias,saldo,gols,confronto direto somente entre dois,vermelhos,amarelos e ordem de sorteio persistida. Essa última representa sorteio simulado do save, não ata de sorteio público real.

Os classificados são roteados por grupo. Quartas mantêm A1×A4/A2×A3/B1×B4/B2×B3, sem reordenar os adversários por campanha; mando de volta pela liga. Semifinais mantêm os vencedores das quartas dentro de cada grupo e usam campanha liga+quartas para o mando. Final usa campanha das três fases anteriores. Cada confronto resolve por pontos,saldo e pênaltis, conforme REC.

Os quatro não classificados são ranqueados pela liga, com descenso1×4/2×3, ida/volta, volta do melhor inicial. **Quartas e descenso são ramos da mesma barreira**, com rodadas7/8 e sem clube duplicado nos dois ramos. Uma partida pendente em qualquer ramo impede avanço/homologação. O planner consolidado respeita descanso e conflitos; datas não são anunciadas como agenda histórica oficial.

Classificação final: campeão/vice; semifinalistas por liga+quartas+semifinal; eliminados das quartas por liga+quartas; vencedores do descenso em9º/10º e perdedores em11º/12º por liga+descenso. O mérito de descenso nasce dos confrontos, **não dos dois últimos do ranking inicial**. A troca com uma segunda divisão ainda não implementada permanece pendente.

A atualização de fase é feita em cópia e só aplicada após validação completa. `StateResultProof` reconstrói grupos,chaves,mandos,rankings,barreiras,campeão e rebaixados; pênaltis devem estar persistidos e válidos, sem ressorteio na prova. Formato/identidade/limites de fase e grupos adulterados também são recusados. Saves legados não são reinterpretados como v4.

## Interface e integração de candidaturas

A tabela apresenta os grupos da liga com suas campanhas externas. Nas fases paralelas, mostra separadamente **Quartas** e **Permanência / descenso**, com confrontos e pênaltis. O rótulo da partida do jogador usa sua fase efetiva, evitando chamar um jogo de permanência de quartas. Ao encerrar, apresenta a classificação geral e o mérito de descenso pendente. Uma nota técnica em inglês detectada na revisão visual foi retirada da apresentação v4 sem modificar o hash do contrato esportivo já testado.

`StateQualification` aceita o contrato PR fixado, reconstrói a prova e aplica o caminho do art.19. Até três melhores elegíveis geram **candidaturas**, excluídos clubes com méritos nacionais já comprovados. A quota de referência CBF2026 é projetada para o piloto2027; não se apresenta isso como homologação oficial futura. Não são inscrições nem recompensas. Recibos incluem carreira,edição,contrato e digests, com repetição idempotente. `FutureSerieDAdmission` mantém o bloqueio global.

## Contagens reais — universos independentes

| Coorte | AL | PI | RO | BA | ES | PE | RN | DF | PR | Candidatos estaduais | Pendentes estaduais | Residuais |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| sports.json final |2|2|2|3|0|2|2|2|3|18|46|4|
| PDO Expresso |2|2|2|3|0|2|2|2|3|18|46|4|
| Game Normal |2|2|2|3|1|2|2|2|3|19|45|4|
| Game Rápido |2|2|2|3|0|2|2|2|3|18|46|4|
| Game Expresso |2|2|1|3|1|2|2|2|3|18|46|4|

Cada linha tem seu próprio save. Os valores de RO/ES variam por resultados e exclusões. **Não somar linhas** nem atribuir a PR diferenças aleatórias entre coortes. No save da matriz, PR acrescenta exatamente3 candidaturas ao subconjunto anterior do mesmo universo:15→18,pendentes49→46. Isso não transforma os14 candidatos de outra execução antiga em um baseline controlado.

`pr/federations.csv/json` exporta27 UFs de **uma única fixture**, carreira858001; `bin/audit-paranaense-v07.py` reproduz a matriz. **Zero inscrições efetivadas, zero renovações aplicadas em todas as linhas.** As quatro residuais da transição continuam separadas; D96,C24,CDB128/157 e demais obrigações não estão liberadas.

## Testes e evidências

Pasta: `Docs/mundializacao-v07/expansao-estadual/lote2/pr/`.

| Suíte | Resultado |
|---|---|
| `tests/paranaense-v07.php` |77 verificações: liga intergrupos, cobertura/mandos/rodadas, duas classificações externas, ramos obrigatórios,54 jogos, prova reconstruída, adulterações, ranking/descenso,recibos e ausência de inscrições. |
| `tests/paranaense-contract-negatives-v07.php` |5 negativos adicionais: inversão de mando,clube desconhecido,descenso omitido,dependência na mesma barreira e clube nos dois ramos. |
| `tests/paranaense-runtime-v07.php` |13.569 verificações; uma edição completa por modo Normal/Rápido/Expresso,2.257 jogos em cada universo,54 PR,personagem em8 partidas PR por coorte,XP real,replay e descanso consolidado. Não são três temporadas consecutivas. |
| `tests/paranaense-mysql-v07.php --local-rollback` |44 verificações; uma edição Expresso,277 comandos com recarga,personagem em8 jogos,duas contas,nonce/replay,crash entre persistência e ledger,retry,lock real1205 e rollback integral. |
| `tests/paranaense-http-v07.php --local-fixtures` |80 verificações: login descartável,CSRF,isolamento,retry de lote,HTML completo,nove estaduais,grupos PR,ramos quartas/descenso,pendência DF e nenhuma designação administrativa por POST comum. Fixture removida. |
| `tests/paranaense-mobile-v07.cjs` |180 verificações em Chrome320/390/1280,teclado/Escape,reduced-motion,sem overflow;51 capturas. HTML autenticado capturado no loopback. |
| `bin/test-narrative.py` |24 suítes passaram;1.014 cenas e nove atributos preservados. |
| Rechecks |DF45,PE/RN40,pós-jogo215(seis posições/três modos),conquistas96(catálogo50/1.000R),v0.6.1/feedback32. Apresentação e resumo de temporada repetidos após o ajuste de UI. |

A primeira tentativa PDO foi impedida pela permissão de soquete do sandbox antes de conectar; a execução autorizada seguinte passou. A revisão final acrescentou quatro negativos de prova à suíte específica; não foram repetidas coortes pesadas sem mudança do caminho válido. HTTP/mobile foram atualizados após os ajustes de apresentação.

Conquistas desligadas nas fixtures PDO/HTTP: não alegar concessão de R nessas coortes. Catálogo e pós-jogo têm testes próprios. HTTP usa PHP em loopback com login descartável, **não IIS nem identidade central real**. Chrome não equivale a aparelho físico ou leitor de tela. Apache/HTTPS/UOL/produção não foram testados.

## Métricas locais

PDO:277 comandos,9.698 consultas; p50437,57ms,p95703,55ms,p991.524,50ms,pior2.235,44ms. Fechamento605,31ms; total136.174,08ms. Snapshot final1.602.976bytes,pico1.969.120bytes; pico de memória do harness146.800.640bytes. Histórico anterior2bytes nesta primeira edição; não há medição de crescimento multi-temporada.

Game em memória: Normal367 comandos,p95223,85ms,pior1.418,06ms,pico1.943.958bytes; Rápido285,p95257,35ms,pior1.174,18ms,pico1.975.180bytes; Expresso278,p95247,12ms,pior810,22ms,pico1.969.554bytes. Valores completos em `game-modes.json`.

Referência DF anterior:267 comandos,9.348 consultas,p95656,88ms,pico1.936.509bytes. Agora há mais54 jogos e carga/personagem distintos; algumas suítes compartilharam CPU durante a medição. Não é comparação controlada nem projeção UOL. Nenhuma normalização ou reestruturação v0.8 foi introduzida.

## Bloqueios e próximo conjunto

GO aproveita o suporte intergrupos, mas ainda exige playoff5º–8º e permanência condicional, inclusive possível dispensa da volta. Não copiar as barreiras estáticas PR para fingir suporte. MA exige ramo de permanência e critérios/decisões de repasse conciliados. AC continua exigindo vantagem por pontos distinta do saldo,bronze,possível jogo extra e conciliação da tabela. Esses três ainda têm **zero jogos executáveis integrados neste lote**.

Copa ES permanece inativa; composição e ano da vaga seguem sem retificação normativa conciliada. DF conserva o operador de teste auditado e a pendência administrativa real,sem sorteio substituto. RN mantém sua política conservadora aprovada. Nenhuma obrigação nacional foi omitida para forçar renovação.

## Reprodução, Git e recuperação

PHP8.4: `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring -d memory_limit=768M`, acrescentar `-d extension=pdo_mysql` para PDO/HTTP. Scripts da tabela; MySQL somente `rumohub` local e `--local-rollback`. Não executar HTTP simultaneamente à fixture PDO que compara contagens globais. HTTP antes de mobile. Python `-X utf8 bin/audit-paranaense-v07.py`. Regressão: `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote2/pr/regressao`.

Os scripts/artefatos do primeiro checkpoint são históricos; `audit-state-lot2.py` exige o motor daquela base e não substitui a auditoria atual do PR. PDFs,HTML,CSRF e logs privados ficam ignorados em storage; gzip conferido contra o JSON regenerável antes de remover a cópia bruta.

Flags permanentes/públicas desligadas; nenhuma mudança em .env,Hub/rh_*,IIS/Apache,serviços,workflows ou produção. Sem DDL/migration. Todas as gravações das fixtures PDO foram revertidas e as HTTP removidas. Sem conversão de carreiras reais,sem alteração de XP/probabilidade/força/idade/elencos reais.

Rollback: flags0 e retorno ao checkpoint `e222f3c`; preservar eventuais snapshots v4 de teste,que requerem seu interpretador. Não restaurar dump sobre progresso posterior. Este lote não cria dependência em saves reais. Commits separados para motor/contrato,integração/testes e evidências. Push somente em `codex/mundializacao-v07`; workflows main-only conferidos. SHA remoto confirmado no resumo após push. Sem merge ou deploy. A v0.8 permanece fora do escopo.

---

Commits deste checkpoint: motor/contrato `efc226ca30be0a7423580660ef0c4cfc64328c56`; integração/testes `33c6371ccb7a65c0736f15cf7993c2df293af41f`. Evidências/documentação em commit separado.

## Histórico do checkpoint de pesquisa aprovado

# Entrega 58 — checkpoint de pesquisa e diagnóstico, expansão ainda não concluída

18/10/2026. Base local/remota conferida: `b21170ecacbdd47d3ec94c094ab9e6dde3fadc8d`, branch `codex/mundializacao-v07`. Este documento **não encerra a Entrega58 como lote esportivo**. Não foram implementadas novas federações nesta execução. Não confundir os testes abaixo com aceitação de campeonatos novos.

## Estado real e escopo preservado

Oito UFs continuam integradas no piloto anterior: AL,PI,RO,BA,ES,PE,RN,DF. Permanecem19 sem integração. Os motores, contratos esportivos, fontes fixadas e snapshots anteriores não foram modificados. DF conserva50 jogos-base, neutralidade, simultaneidade e pendência administrativa. A política RN não mudou. A pasta preexistente `Docs/levantamento-2026-10-15/` foi preservada.

Foram acrescentados um manifesto documental, um exportador de matriz e testes que reproduzem lacunas concretas do interpretador. **Nenhum deles registra candidaturas, altera o banco, modifica regras de jogo ou habilita flags.** Não houve DDL, migração, escrita no Hub, acesso à UOL ou produção.

## Lote de trabalho selecionado e impedimentos específicos

GO,PR,MA,AC constituem um conjunto de quatro UFs para desenvolvimento, **não quatro implementações concluídas**. GO/PR compartilham a necessidade de adversários intergrupos; GO/PR/MA precisam de ramos esportivos paralelos. AC expõe diferenças que impedem copiar o adaptador genérico de semifinal. A seleção poderá ser revista se documentos complementares demonstrarem melhor viabilidade de outro percurso.

| UF | Evidência primária localizada | O que impede usar o motor atual sem alteração |
|---|---|---|
| GO | [REC Goianão2026](https://www.fgf.esp.br/assets/uploads/file/Regulamento%20Goian%C3%A3o%202026.pdf), arts.5–24 | Primeira fase intergrupos; playoff5º–8º; permanência condicionada a diferença de pontos e possibilidade de dispensar a volta. Não equivale a uma liga seguida apenas da chave do título. |
| PR | [REC Paranaense2026](https://federacaopr.sfo3.digitaloceanspaces.com/wp-content/uploads/2023/09/19162139/REC-1a-Divisao-2026.pdf), arts.8–19 e anexoI | Grupos enfrentam o grupo oposto; descenso paralelo dos quatro não classificados; ranking separa vencedores/perdedores do descenso. RGCP e tabela precisam de conciliação própria. |
| MA | [REC definitivo Maranhense2026](https://www.futebolmaranhense.com.br/assets/uploads/17651550147.pdf?v=177021254186), art.4 e arts.11–14 | Oitavo desce direto; sexto/sétimo disputam permanência. Repasse nacional pode exigir opção do clube entre vagas. Falta contrato para esses ramos/decisões, além da conciliação de tabela e identidades. |
| AC | [REC Acreanão2026](https://www.ffac.com.br/competicoes/regulamento/43), arts.6–10, PDF pp.6–7 | Semifinal aplica vantagem em igualdade de pontos, antes de saldo; há disputa de terceiro lugar e desempate por partida extra. Esses caminhos não existem no adaptador corrente. |

Os PDFs públicos baixados ficam ignorados em `storage/qa/state58/`; hashes, tamanhos, edição, consulta e localizadores estão em `lote2/research.json`. `research.csv` é o índice legível. Não versionar HTML bruto, cookies ou documentos eleitorais como regulamentos esportivos.

GO: a permanência dos arts.19–20 usa campanha acumulada e limiar específico, inclusive após a ida; não modelar como pênaltis convencionais de mata-mata. PR: os jogos contra o outro grupo contam na classificação de cada grupo; filtrar somente confrontos entre seus integrantes produz tabela vazia/incorreta. MA: a [tabela básica](https://www.futebolmaranhense.com.br/conteudo/27/14) foi localizada no anexo `176374611975.pdf`, mas seus28 jogos ainda não foram conciliados com IDs. O REC remete omissões ao DCO; não supor desempate da permanência ou escolha de vaga ausentes do contrato.

AC: o PDF é composto por imagens. Foi gerado OCR privado e conferida visualmente a página6: art.7§1 realmente dá preferência à melhor campanha inicial quando há igualdade de pontos, e §2 prevê bronze. A [notícia da FFAC](https://ffac.com.br/noticia/ffac-divulga-a-tabela-detalhada-do-campeonato-acreano2026) anuncia33 jogos, enquanto28+4+bronze+final somam34 pelo REC. A página pública de partidas também foi localizada. Falta conciliar integralmente a tabela autorizada, eventuais adendos, mandos de bronze/final e procedimento de jogo extra. Não descartar o bronze para fazer a contagem coincidir com a notícia.

MT foi examinado como alternativa, mas não entrou no lote: o PDF oficial consultado apresenta art.6 com um descenso e art.18 com dois; essa divergência precisa de esclarecimento/adendo. A obtenção direta de alguns anexos de MT/PB falhou. O domínio `www` da PB apresentou certificado incompatível; não foi desativada a validação TLS. Isso é limitação de obtenção nesta execução, não prova de inexistência de regulamento. Notícias, referências de busca e catálogo não substituem sua leitura integral.

## Diagnósticos executáveis adicionados

`tests/state-lot2-preflight-v07.php` reproduz três impedimentos com fixtures **sintéticas e isoladas**, sem simular um estadual oficial:

1. A tabela completa de adversários intergrupos é recusada pelo validador atual, que exige liga completa de grupo único para fixtures explícitas.
2. No confronto ida3–0/volta0–1, cada equipe tem uma vitória. O `advantage` atual resolve pelo saldo; a redação acreana exige a equipe de melhor campanha inicial nessa igualdade de pontos. Usar o adaptador atual sem extensão mudaria o regulamento.
3. O roteamento atual rejeita seleção dos perdedores da semifinal para disputa de bronze.

São **três diagnósticos confirmados**, não três correções implementadas. Uma primeira execução do harness mostrou aviso por `phase_end` ausente na fixture; o inicializador foi corrigido e a repetição passou sem aviso. Nenhuma mudança de fórmula foi feita para fazer o teste passar.

`bin/audit-state-lot2.py` confere os quatro hashes quando recebe `--raw-dir`, valida a distinção pesquisa/implementação, lê a coorte-base sem somar saves e compara dez arquivos do motor com a base aprovada. Exporta `audit.json`, `research.csv` e `federations.csv`. Não constitui um certificador automático de regulamentos ou mecanismo de inscrição.

## Contagens e testes — limites explícitos

**Zero novas federações integradas; zero novos jogos de competição oficial; zero novos recibos de candidatura; zero inscrições nacionais; zero renovações nacionais.** Não foi executada nova coorte Game/PDO da Entrega58. Não existem métricas novas de consultas, memória, latência ou snapshot de competições adicionais.

A matriz27UF reproduz, com rótulo de origem, **somente** `df/sports.json` da Entrega57, carreira de fixture857500:14 candidatos estaduais,50 vagas estaduais pendentes e4 residuais separadas. Esses valores não são resultado novo da Entrega58 nem déficit universal de qualquer carreira. Não houve redução demonstrada nesta execução.

As24 suítes de `bin/test-narrative.py` passaram nesta revisão, com resultados em `lote2/regressao/`. Essa regressão protege a base existente; não prova execução de GO/PR/MA/AC. Não foram repetidas coortes volumosas MySQL, HTTP ou mobile sem alteração do runtime/interface. Testes de novas competições nos três modos, persistência, concorrência, crash/retry, rollback e interface **continuam pendentes e obrigatórios antes de homologar o lote**. Não alegar IIS, Apache ou UOL testados.

## Próxima implementação necessária — trabalho ainda não realizado

1. Conciliar participantes/IDs e tabelas oficiais do primeiro par, incluindo documentos complementares e critérios de vagas. Não criar IDs por semelhança de nome.
2. Introduzir uma versão opt-in do interpretador para adversários intergrupos, incluindo validação de cobertura e ranking por grupo com adversários externos. Preservar v1/v2/v3.
3. Implementar ramos de classificação/descenso com condições e provas de encerramento. A competição não pode homologar enquanto houver ramo obrigatório pendente.
4. Para AC, separar vantagem por pontos da vantagem após saldo, seleção de perdedores e ranking por disputa de posição. Concluir conferência dos artigos/tabela antes de ativar contrato.
5. Executar o primeiro conjunto completo no Game/Repository, três modos, provas por carreira e matriz de candidaturas; só depois integrar o conjunto seguinte e repetir a regressão pertinente.

Essas são pendências de desenvolvimento e conciliação, **não uma declaração de impossibilidade técnica**. O checkpoint não satisfaz o critério de ampliação estadual efetiva e a Entrega58 permanece aberta.

## Copa ES, nacional, Git e recuperação

Copa ES permanece inativa. Não foi localizada nem aplicada nesta execução retificação que concilie composição9→8 ou ano da vaga; a investigação anterior está preservada. Sem candidatura pelo segundo caminho capixaba.

D96, quatro residuais iniciais, C24 e CDB128/157 permanecem sujeitos a inscrições/obrigações completas e contratos futuros rotulados como simulação. Nada foi renovado parcialmente. Copas regionais/continentais ausentes continuam impedimentos; nenhuma foi dispensada silenciosamente.

Ambos os workflows de produção foram conferidos: push apenas em `main`, além de disparo manual. Este checkpoint só pode ser enviado à branch `codex/mundializacao-v07`; sem merge/deploy/flags públicas. Rollback consiste em remover/reverter os novos arquivos de diagnóstico/documentação; não há dados nem schema a restaurar. A v0.8 não foi iniciada.

Reprodução: PHP8.4 com `-n -d extension_dir=C:/PHP/php-8.4.26/ext -d extension=mbstring tests/state-lot2-preflight-v07.php`; Python `-X utf8 bin/audit-state-lot2.py`, opcionalmente `--raw-dir storage/qa/state58`. Para a regressão, usar `V06_EVIDENCE_DIR=Docs/mundializacao-v07/expansao-estadual/lote2/regressao`. O SHA remoto deste checkpoint será informado após a conferência do push; não representa uma entrega esportiva concluída.
