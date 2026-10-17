# v0.7 — Série D executável, C seguinte e copa obrigatória: checkpoint local

17/10/2026. Base local/remota conferida `c781ff6c94eb5cafc0946dcdcadfa3e44f128ea1`; branch `codex/mundializacao-v07`, um checkout. Relatórios 48–51 consultados. Apenas `Docs/levantamento-2026-10-15/` preexistia fora do Git e foi preservado. Flags usuais calendário/fixtures continuam `0`; .env, Hub/rh_*, produção, IIS e Apache intocados. Workflows por push restritos a main; nenhum merge/deploy executado.

**Resultado parcial:** Série D 2026 e Copa do Nordeste completas no novo motor local, junto a A/B/C e Alagoas: **1.733 jogos numa edição por carreira de teste**. Há também uma coorte com personagem disputando efetivamente a D pelo CSA, não só partidas NPC. Contrato C24 simulado executado isoladamente. **Zero renovações nacionais aplicadas; cinco temporadas nacionais consecutivas NÃO foram executadas.** O percurso público permanece indisponível.

## 1. Matriz por edição

| Competição | Base 2026 no piloto | Próxima edição |
|---|---|---|
| A | 20 clubes, 380 jogos; motor já existente preservado | proposta recíproca com B; não executada |
| B | 20 clubes, 384 jogos incluindo playoffs | proposta recíproca com A/C; não executada |
| C | 20 clubes, 216 jogos | 24 candidatos derivados de B/C/D; contrato simulado de 302 jogos testado isoladamente |
| D | 96 clubes, 610 jogos, seis méritos de acesso | 26 candidatos de retenção + dois descensos da C = 28; faltam 68 entradas verificáveis para a capacidade derivada de 96 |
| Nordeste | 20 clubes, 60 jogos, título após final | novas inscrições dependem de estaduais/rankings/exclusões continentais; bloqueadas |
| Copa do Brasil | obrigações conhecidas registradas; runtime ausente | bloqueia a continuidade integral |
| Alagoas | módulos anteriores preservados; 83 jogos iniciais | continuidade estadual já existente, sem inventar acesso nacional |

As quantidades futuras da D descrevem um **plano derivado de simulação**, não uma lista oficial de 2027 ou reserva automática de vagas. Não são copiados por inércia os mesmos 96 clubes. Nenhuma entrada nova é preenchida com times paulistas, lista do catálogo ou sorteio.

## 2. Série D — fontes e motor

`br-d-sports-2026.json` referencia os dezesseis grupos já presentes no catálogo, agora conferidos contra a [tabela detalhada CBF de 25/03/2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/Tabela_Detalhada_Brasileiro_Serie_D_2026_1f9d105d94.pdf), PDF pp.1–2, refs.001–048. Grêmio Sampaio/GAS, Operário VG/Operário-MT e demais nomes usam os IDs existentes. `br-d-annex-2026.json` anterior preserva a conciliação individual, critério, UF, fonte e edição. Nenhum clube/cidade/rating foi criado.

[REC D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf), arts.6,15–21 e Anexo B, PDF pp.20–22: grupos, cruzamentos B/C/D, ordenação do bloco das quartas, semifinal, playoff e final implementados. Mandos iniciais equilibrados 5/5, ordem/datas simuladas; retorno no mata-mata pela regra de fase/campanha. Só resultados finalizados definem campeão e seis acessos. Saída/retorno à D futura depende da nova admissão, sem inventar rebaixamento para uma divisão nacional inferior.

**Conflito registrado:** art.17 menciona saldo/pênaltis incluindo playoffs, mas art.21 §3–5 descreve especificamente pontos, saldo e vantagem de classificação no playoff. O adaptador aplica a disposição específica do art.21 nesses dois confrontos; não a apresenta como errata oficial. Demais eliminatórias usam saldo e pênaltis. Datas e interpretações estão rotuladas no contrato, sem certificação administrativa completa.

`NationalDCompetition` tem dispatch próprio, sem modificar o formato legado d_knockout. Valida 96 IDs únicos, dezesseis grupos completos e edição-base. `verify` reconstrói todo o chaveamento a partir de placares/cartões/pênaltis persistidos, rejeitando adversário/mando/classificação divergente e reaproveitando o desempate gravado. Não sorteia novamente um pênalti reconhecido. O playoff concede acesso, não uma taça; a final concede o único título geral.

Anexo e tabela comprovam lista/grupos, **não BID, contratos profissionais, licenciamento, todas as permissões de seletiva ou eventuais substituições posteriores**. Registros de elenco continuam simulados, não são autenticação com a CBF. A [publicação oficial da FNF](https://www.fnf.org.br/cbf-define-os-grupos-do-campeonato-brasileiro-serie-d) também explicita a retenção dos que chegam à terceira fase, excluídos do plano futuro da D os seis que sobem. A admissão futura completa continua pendente; `SerieDAdmission`/política v2 não foram falsamente ativados.

## 3. C24 e renovação: o que foi e não foi aplicado

`NationalPyramid::close` exige todos os torneios da fixture finalizados, reaproveita as provas A/B/C e verifica o caminho completo da D. Persiste `national_pyramid_closures`: digests por competição/contrato, participantes derivados, campeão/acessos D, prova de resultados do Nordeste e bloqueios. Preserva os ledgers externos anteriores, acrescentando méritos; não consome candidatura como inscrição.

As propostas A↔B e B↔C permanecem; a C recebe os seis clubes que realmente venceram o acesso D no save e fecha em 24. Não se buscam clubes no catálogo durante a troca. `nextCContract` fixa `simulation-2`, versão `c-2027-from-2026-v1`, knowledge `simulation_adaptation`: turno de 24, 23 rodadas, oito nos quadrangulares e final, 302 jogos. Prioridade de mandos 12/11 em ordem fixada é adaptação explícita; não é REC oficial posterior. A capacidade de 24 e manutenção do formato têm [referência CBF já registrada no relatório 51](https://www.cbf.com.br/futebol-brasileiro/noticias/detalhes/competicoes-campeonato-brasileiro-serie-b/cbf-publica-documentos-tecnicos-da-serie-c-de-2026).

Esse contrato foi disputado somente numa fixture de regras, **não como segunda temporada da carreira**. As memberships efetivas continuam as de 2026. `attemptRenewal` calcula em cópia e recusa a aplicação integral enquanto faltam entradas D/copas; testes confirmam que a recusa não altera nenhuma divisão. O caminho positivo de aplicar/arquivar/gerar calendário da temporada seguinte **não foi implementado/liberado**, pois falta o conjunto de evidências/torneios. Não há ledger de renovação executada nem alegação de crash test após uma renovação bem-sucedida. O mercado continua impedido de oferecer esse calendário futuro incompleto.

O preenchimento paulista de `Competition::completeWorld` é idêntico ao checkpoint; nenhuma rotina legada foi retirada, nenhum save/histórico foi migrado ou convertido. Os 23 vínculos estaduais e nove internacionais permanecem sem nova liberação pública.

## 4. Nordeste e matriz de obrigações

Foi localizado o [REC Nordeste 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Nordeste_2026_113589c009.pdf), arts.2,5,7,10–22 e Anexos A/B, PDF pp.14–15, superando o bloqueio de acesso ao documento da rodada anterior. A [tabela CBF atualizada em 31/03](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/Tabela_Detalhada_Copa_do_Nordeste_2026_31_03_9c5998f5b7.pdf) identifica participantes e letras dos grupos.

`NordesteCompetition`: quatro grupos de cinco, jogos cruzados A×B/C×D, 3/2 mandos, cinquenta jogos iniciais, quartas únicas, semifinais/final em duas pernas, desempates/mando por fase e ranking final completo. A ordem inicial/datas é simulada, não reprodução das datas históricas. Nenhum resultado real foi importado. Campeão entra no ledger apenas como candidato à Copa do Brasil seguinte, sem inscrição/recompensa por candidatura. A copa usa um tier interno de compatibilidade com as rotinas existentes; isso não é divisão/rating oficial nem mudança da fórmula de XP. A Sala de Troféus ainda usa seu agrupamento nacional legado para essa copa regional; o contrato registra scope regional.

`MandatoryTournamentCoverage` e `br-cup-obligations-2026.json` geram matriz para **cada clube da fixture**, em `mandatory-coverage.json`. Nordeste tem anexo fechado de vinte; Copa do Brasil tem neste lote 28 presenças já comprovadas (vinte da A, quatro alagoanos e quatro do critério de campeões anteriores). Restante é marcado como não avaliado, nunca dispensado. Outras regionais/continentais não estão integralmente auditadas. CRB: Nordeste concluído no calendário; **Copa do Brasil obrigatória ausente**.

[REC Copa do Brasil](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf), art.2/Anexo A, sustenta essas presenças. Continuam faltando conciliação completa de 126 inscrições, critérios/substituições, chaves e entradas por fase, runtime e futuras vagas simuladas. Não foi criada uma copa fictícia com esse nome. Desconhecido não significa opcional; a carreira pública permanece bloqueada.

## 5. Escala e medições

O teto padrão 128 e o limite genérico do piloto 1.152 permanecem. Há duas exceções CLI identificadas e fechadas: A/B/C/D+Alagoas até 1.673 (1.063+610); com Nordeste até 1.733 (+60). O guard exige o conjunto específico de IDs/memberships/schema, além das validações dos adaptadores. Isso não aumenta genericamente todos os calendários nem comprova escala mundial. Mantido orçamento de oito NPCs por continuação, com cursor/nonce/versão/transação persistidos e retomada em todos os modos. Sem worker permanente ou serviço externo.

Métricas detalhadas: `runtime-with-nordeste.json`, `player-d-runtime.json`, `modes.json` e coorte PDO final. A regravação do snapshot e cerca de 35 consultas por comando continuam como custos relevantes; não houve normalização/compactação nova. Somente a compactação existente de campos vazios de NPCs é reaproveitada, preservando placares, pênaltis, cartões e estatísticas do personagem.

Coorte em memória CRB: 844 comandos; 176 lotes; maior comando 1.310,69 ms; snapshot final 1.385.673 e pico 1.846.926 bytes. PDO final Expresso: 176 comandos, 6163 consultas; p50 396.72 ms, p95 1186.27 ms, pior comando 4723.02 ms; fechamento 3225.48 ms; snapshot final 1806325 bytes, pico 1879792 bytes. Ledger de homologação 6293 bytes; histórico de edições nacionais anteriores 2 bytes (ainda vazio). Modos e cargas diferentes não constituem benchmark controlado com a base Normal do relatório 51 (734 comandos, 25.693 consultas, p95 401,12 ms, pico 1.263.388 bytes). Não há medições nacionais após temporadas 2/3/5: **bloqueadas**, não estimadas. Portanto, não se demonstrou crescimento estável de histórico nacional nem segurança de cinco temporadas na UOL.

## 6. Testes e integridade

Evidências em `Docs/mundializacao-v07/serie-d-renovacao-copas/`:

- `contracts.json`: 664 verificações D/Nordeste/C24, grupos/edição/duplicata/mando, reconstrução, playoff, título/acesso separados, cobertura e bloqueio sem alteração parcial. A C24 foi uma edição NPC isolada de regras.
- `runtime-with-nordeste.json`: uma edição CRB no Game, 3.519 verificações e 1.733 jogos; replay preserva XP, classificações e homologação.
- `player-d-runtime.json`: uma edição independente com personagem CSA disputando minutos na D pelo Game; 3.520 verificações. Não soma uma segunda temporada da carreira CRB.
- `modes.json`: Normal/Rápido/Expresso concluíram uma edição cada, 10.431 verificações; não são três temporadas consecutivas.
- `http-runtime.json`: 26 verificações autenticadas PHP loopback, CSRF, nonce/replay/isolamento, conta descartável removida.
- `mobile/results.json`: 39 verificações em Chrome 320/390/1280, teclado e ausência de overflow, usando HTML da fixture autenticada; não dispositivo físico.
- `regressao/suites.json`: 24 suítes existentes passaram, 1.014 cenas preservadas. Testes adicionais de fronteiras, pós-jogo, conquistas e feedback registrados separadamente.

A coorte PDO final passou com 25 verificações, no modo Expresso, com recarga por comando, duas contas, lock em segunda conexão, falha injetada após save/antes do ledger e replay, rollback integral. A execução Normal inicial foi cancelada porque tinha carregado classes anteriores à adição do verificador; não conta como sucesso final. Outra tentativa Expresso enviou choice em vez de simulate na primeira etapa, foi corretamente rejeitada e revertida; o harness foi corrigido, não a regra do jogo. `intermediate-failures.json` registra os incidentes. Cancelar o processo da fixture identificada fechou sua conexão e reverteu sua transação; nenhum serviço IIS foi reiniciado.

Conquistas estão desligadas nas fixtures PDO; essa coorte não comprova nova concessão de R. Catálogo 50/1.000 R e pós-jogo/XP são verificados nas suítes próprias. Não houve alteração das fórmulas de força/probabilidade/posse, XP-base/envelhecimento, cenários de mercado/roleta, identidade, direitos ou Hub. Não houve teste novo de IIS autenticado central, Apache, HTTPS ou produção.

## 7. Reprodução, recuperação e próximo portão

```
php tests/serie-d-contracts-v07.php
php tests/serie-d-cup-runtime-v07.php
php tests/serie-d-player-v07.php
php tests/serie-d-cup-modes-v07.php
php tests/serie-d-cup-mysql-expresso-v07.php --local-rollback
php tests/serie-d-cup-http-v07.php --local-fixtures
node tests/serie-d-cup-mobile-v07.cjs
```

PHP 8.4 com mbstring; PDO MySQL nos testes correspondentes. Os scripts verificam banco rumohub/host local/ambiente e usam apenas fixtures. A transação externa e savepoints interceptam commits para reverter tudo; o teste de falha não equivale a desligar fisicamente o servidor. A renovação recusada preserva o snapshot; não há crash/retry de renovação aplicada porque ela continua indisponível.

Sem SQL/DDL/migration/backfill, sem backup/restauração produtiva. Rollback: flags desligadas e retorno de código; não restaurar banco sobre progresso posterior. Fixtures com formatos novos exigem seu adaptador, mas nenhum save real foi convertido. Arquivos privados, tokens, HTML autenticado e dumps não entram no Git.

Próximos portões: entradas futuras da D derivadas de resultados/seleções verificáveis, Copa do Brasil completa e demais obrigações do clube, novas entradas/exclusões do Nordeste; então implementar aplicação atômica/arquivo/ledger individual e agenda seguinte, mercado elegível, duas renovações e cinco temporadas nacionais da mesma carreira em PDO. **Ainda não há autorização técnica para habilitar o percurso a usuários locais.**

Commits organizados e push somente à branch de trabalho, com SHA remoto confirmado na entrega. Sem merge em main, FTP/UOL, deploy, alteração RumoHub/rh_* ou produção. A mundialização não está encerrada.
