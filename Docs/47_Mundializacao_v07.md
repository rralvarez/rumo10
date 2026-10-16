# Mundialização v0.7 — etapa A e fundação local

## Estado real e isolamento

Base confirmada: **820187b78ba5ddac336df8fc333cdf69c8ec8e3c**, v0.6.1 concluída e publicada, com confirmação do proprietário de login/carreiros/perfil/conquistas/feedback. O remoto da branch anterior coincide com a base. Nova branch de trabalho: **codex/mundializacao-v07**. A pasta antiga não versionada Docs/levantamento-2026-10-15 foi preservada. Não havia código não commitado de outra tarefa. Workflows automáticos continuam restritos a main. Nenhum acesso a FTP, banco produtivo, API efetiva do Hub ou modificação de rh_* nesta etapa.

## Recontagem reproduzível

- 130 definições nos sete JSONs versionados + um complemento amazonense = **131 competições locais**.
- **154 competições no cache local do Hub**; 24 IDs existem somente no cache, um complemento somente no jogo. Não se afirma que a API atual entregue esses mesmos registros.
- **1.758 clubes locais** e 73 códigos de país/associação nos registros; isso não equivale a 73 países integralmente jogáveis nem a validação federativa completa.
- 22 pares de continuidade implementados, **23 acessos declarados sem superior** e **32 destinos bloqueados** pelo validador atual. Os nove restantes são vínculos estrangeiros ausentes.
- Os 23 anteriores continuam pendentes; nenhum foi liberado silenciosamente. Amazonense B→A preservado.

Reprodução: php -n -d memory_limit=768M bin/audit-world-v07.php. O processo desativa a API Hub e não conecta ao banco. Saídas: inventory.csv/json, cached-hub-inventory.json, continuity-gaps.csv/json, graph-issues.json, calendar-audit.json, summary.json e edition-contracts.json em Docs/mundializacao-v07/. Cada definição exporta regras registradas, participantes, escopo, fontes originais e lacunas. Federação, nível oficial, elegibilidade externa e regras não comprovadas permanecem nulos/desconhecidos; tier é apenas o nível simulado. Não confundir inventário concluído com regulamentos oficialmente validados.

## Matriz dos 23 vínculos

| Origem | Competição registrada | Vagas registradas, não revalidadas | Destino |
|---|---|---:|---|
| ac-entrada | Acreano Série B | 2 | Pendente de fonte/participantes/implementação |
| al-entrada | Alagoano Segunda Divisão | 1 | Pendente de fonte/participantes/implementação |
| ap-entrada | Amapaense Série B | 2 | Pendente de fonte/participantes/implementação |
| ba-entrada | Baiano Série B | 2 | Pendente de fonte/participantes/implementação |
| ce-entrada | Cearense Série C | 2 | Pendente de fonte/participantes/implementação |
| df-entrada | Candangão Série B | 2 | Pendente de fonte/participantes/implementação |
| es-entrada | Capixaba Série B | 2 | Pendente de fonte/participantes/implementação |
| go-entrada | Goiano Terceira Divisão | 2 | Pendente de fonte/participantes/implementação |
| ma-entrada | Maranhense Série B | 2 | Pendente de fonte/participantes/implementação |
| mt-entrada | Mato-grossense Segunda Divisão | 2 | Pendente de fonte/participantes/implementação |
| ms-entrada | Sul-Mato-Grossense Série B | 2 | Pendente de fonte/participantes/implementação |
| mg-entrada | Mineiro Segunda Divisão | 2 | Pendente de fonte/participantes/implementação |
| pa-entrada | Paraense Série A3 | 2 | Pendente de fonte/participantes/implementação |
| pb-entrada | Paraibano Segunda Divisão | 2 | Pendente de fonte/participantes/implementação |
| pr-entrada | Paranaense Terceira Divisão | 2 | Pendente de fonte/participantes/implementação |
| rj-entrada | Carioca Série C | 4 | Pendente de fonte/participantes/implementação |
| rn-entrada | Potiguar Segunda Divisão | 2 | Pendente de fonte/participantes/implementação |
| rs-entrada | Gaúcho Série B | 2 | Pendente de fonte/participantes/implementação |
| sc-entrada | Catarinense Série C | 1 | Pendente de fonte/participantes/implementação |
| se-entrada | Sergipano Série A2 | 2 | Pendente de fonte/participantes/implementação |
| to-entrada | Tocantinense Segunda Divisão | 2 | Pendente de fonte/participantes/implementação |
| ro-entrada | Rondoniense Série B | 2 | Pendente de fonte/participantes/implementação |
| pi-entrada | Piauiense Série B | 2 | Pendente de fonte/participantes/implementação |

As 23 linhas também estão no CSV com edição, impeditivo e ação. Não se atribuiu nome/ID de destino nem número de vagas oficial sem prova. O primeiro candidato de pesquisa é Ceará, cuja divisão superior B existe em documento oficial, mas ainda depende da validação completa da C e do conflito de edição da B. Nenhum desses destinos foi ativado.

Outros vínculos ausentes: at-2, az-2, ch-2, ua-2, rs-2, jp-2, il-2, bg-2, se-2. Não confundir rs-entrada (Rio Grande do Sul) com rs-1/2 (Sérvia), nem se-entrada (Sergipe) com se-1/2 (Suécia).

## Incompatibilidades e decisão aprovada

1. Competition::startSeason/continueSeason usam um cursor sequencial. Schedule::match tem rodada local, sem data comum. Não há calendário simultâneo de liga/copa, descanso compartilhado ou classificação continental implementada.
2. Competition::completeWorld preenche vagas da Série D com clubes classificados nas divisões paulistas até completar 96, sem ledger federativo/edição/ranking. Não representa o critério oficial da CBF. O trecho foi identificado, **não alterado nesta fundação**, para não mudar os saves publicados antes de existir substituição validada.
3. Regras são lidas do catálogo vigente durante fases/temporadas; snapshots esportivos não fixam uma edição imutável de todos os regulamentos. Não basta acrescentar uma propriedade no save: todos os motores de fases precisam consumir o contrato fixado.
4. Resultados de todas as memberships são simulados no fechamento. Expansão indiscriminada aumenta trabalho síncrono e snapshots. Sem política de inscrição e agenda, não se devem adicionar competições obrigatórias.
5. Sala de Troféus diferencia kind de título geral/fase, mas inclui ambos na lista e no total exibido. A integração futura deve separar contagens e consumir apenas os tipos oficiais adequados. Premiações individuais e 17 conquistas futuras permanecem inativas.

O proprietário aprovou nesta conversa: **calendário consolidado simulado**, com datas/descanso/conflitos declarados como adaptação e preservação dos legados até transição segura. Essa aprovação não autoriza produção nem dispensa validação esportiva por edição.

## Implementação efetiva da fundação (não ativada no jogo)

- CompetitionReference: cria contratos imutáveis por cópia com edição, versão, categoria de conhecimento e digest; fonte verificada obrigatória para classificação official_dated. Valida grafos, destinos ausentes, ciclos de promoção, duplicidades, vagas, mistura de pirâmides e clubes em duas divisões. Promoção e classificação são tipos diferentes.
- CompetitionCalendar: agenda compartilhada por clube, intervalo mínimo parametrizável, chaves estáveis por save/temporada/competição/jogo, preservação de datas persistidas, rejeição de duplicidade/corrupção/inserção retroativa e limite de janela anual. Data inicial é parametrizada para ciclos janeiro/dezembro ou julho/junho; isso NÃO reproduz calendário oficial.
- Auditor: exporta contratos de referência para as 131 definições atuais e todas as fases iniciais, sem gerar novas inscrições ou salvar partidas reais.

**Os novos contratos ainda NÃO são consumidos por Competition, Repository, mercado, XP ou conquistas.** Nenhuma carreira foi convertida. A agenda funciona como módulo testado isoladamente; não é ainda uma temporada jogável com copas. Não há migração, instalador ou DDL nesta entrega. Não foi concluído um lote vertical de mundialização e não se declara o pedido v0.7 encerrado.

## Fontes primárias e conflitos

Matriz estruturada: sources.csv/json.

- [CBF — REC Série D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf), arts.2, 6 e 7: critérios de inscrição, acesso à C e vaga do campeão na Copa do Brasil seguinte. O preenchimento paulista automático do motor não tem suporte nessa regra.
- [FCF — REC Série B](https://www.futebolcearense.com.br/2010/downloads/arquivos/arquivo_10406.pdf), arts.1/20 e anexo A: divisão B e ligação com A/C. Conflito de metadados: capa 2025, corpo 2026. Não importado.
- [FCF — REC Série A 2026](https://www.futebolcearense.com.br/2010/downloads/arquivos/arquivo_10358.pdf), art.18: continuidade A/B. Leitura parcial não comprova todos os critérios nem revisões.
- Página de publicação da C retornou conteúdo genérico/antigo; evidência recusada como regulamento. Paulista A4 encontrado como fonte candidata, leitura integral pendente. Nenhuma inferência a partir de snippets foi usada para liberar vagas.

Não se pesquisaram ainda todos os regulamentos das 131 competições. Não há alegação de Brasil completo, países ativados, continental/mundial implementado ou ranking oficial novo.

## Testes, preservação e desempenho

162 verificações de referência/agenda aprovadas. Cinco agendas anuais testadas para clubes compartilhando liga/copa, idempotência, descanso, fontes e isolamento. **Não são cinco temporadas completas de gameplay**, tampouco prova de migration.

As 24 suítes existentes foram executadas: 22 passaram inicialmente; duas detectaram contratos de teste desatualizados da v0.6.1 (classe do menu e superfície visual de CareerSlots/PlayerProfile). Corrigidos somente os testes: o menu agora admite classes combinadas/ícone; as duas superfícies são comparadas exatamente ao commit publicado 820187b antes de aceitar a diferença antiga. As duas passaram na repetição. Saída original de falhas preservada em regressao/suites.json; repetições e outras suítes em incremental-tests.json. Não se escondeu a primeira falha nem se alterou o motor para satisfazer o teste.

Fronteiras v0.6, 215 verificações pós-jogo, 96 de conquistas e 32 v0.6.1 aprovadas. As 1.014 cenas e estados de ativação permanecem intactos; ver preservation.json para a comparação dos arquivos originais.

Criação das 131 fases iniciais: 20.233 jogos, cerca de 6,18 MB somando snapshots iniciais e 41,314 ms na amostra registrada. Isso mede apenas geração em memória, não fechamento global, PDO, renderização, campanha completa nem limite de hospedagem. Zero conflito de um mesmo clube na mesma rodada dentro das fases iniciais; conflito ENTRE campeonatos não é detectável no motor antigo por ausência de datas.

Ainda pendentes: cinco temporadas completas com múltiplas competições e mercado, teste de migração PDO/concorrência da agenda, UI e IIS autenticado da v0.7, custo de fechamento global, projeções de utilização, títulos validados, copas, qualificações e demais países. Não houve teste Apache/HTTPS da v0.7 nem acesso ao ambiente produtivo.

## Próximos lotes, portões e recuperação

1. Finalizar fonte/edição/participantes das continuidades brasileiras; manter bloqueio onde faltar prova. A maior divisão existente não é prova de conexão estadual→nacional.
2. Integrar contratos fixados em todos os motores de fase e persistência, por feature flag local desligada por padrão; somente novas carreiras ou fronteira de temporada explicitamente validada.
3. Substituir a seleção da próxima partida por agenda compartilhada, com eventos estruturados e idempotência transacional. Só então conectar liga + primeira copa validada.
4. Remover o preenchimento paulista automático apenas junto da política explícita de qualificação/inscrição e ledger de transição. Preservar inscrições/resultados legados; não recalcular temporadas concluídas.
5. Completar o lote vertical brasileiro com cinco temporadas, MySQL rollback/concorrência, títulos e UX. Depois ampliar qualificações continentais e destinos por evidência.

Nesta fundação: países/competições ativados **zero**, clubes adicionados **zero**, novas vagas/classificações **zero**, conquistas futuras ativadas **zero**. Rollback é retirar os arquivos novos não conectados; nenhum schema/save precisa ser restaurado. Backups privados de produção da publicação anterior NÃO devem ser usados para testes v0.7.

Git: commits da auditoria/fundação e push somente na branch segura após lint/testes; registrar SHA final na entrega. Nenhum merge em main ou deploy.
