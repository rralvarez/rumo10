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
