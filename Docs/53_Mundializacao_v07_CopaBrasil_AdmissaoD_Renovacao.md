# v0.7 — Copa do Brasil e admissão futura D: checkpoint parcial

17/10/2026. Base local/remota verificada: `af64e98ad78ced42cfd9e828a7aa5ca78f00feb8`, branch `codex/mundializacao-v07`. Entregas 48–52 consultadas; somente `Docs/levantamento-2026-10-15/` preexistia não versionado e foi preservado. Workflows de publicação continuam restritos a push em main; nenhum workflow foi editado.

**Não conclui a renovação nacional nem a mundialização.** Nesta continuação foram implementados componentes independentes de admissão/auditoria. Copa do Brasil: **zero partidas executadas**, motor ainda ausente. Uma edição A/B/C/D + Nordeste + Alagoas foi novamente concluída via Repository/PDO; **zero renovações aplicadas e zero sequência de cinco temporadas nacionais**. Flags públicas permanecem desligadas.

## Implementação efetiva

- `br-copa-annex-2026.json`: transcrição dos 126 participantes do Anexo A, com UF, critério, origem, edição, fonte e localização por registro. Não cria clubes, ratings ou inscrições no runtime. Critérios: 20 da A, quatro de campeonatos anteriores, 102 estaduais. O anexo confirma participação, não certifica individualmente a posição estadual, seletiva, substituições, BID ou licenciamento.
- `CopaBrasilReference`: confere duplicatas, critérios/capacidade, identidade/UF; contrato das nove fases, entradas e 155 jogos previstos. Rejeita entradas antecipadas/tardias, ausência de inscrição e duplicações. É um contrato de capacidade, **não sorteador, chaveamento ou torneio jogável**. As vagas individuais da primeira/segunda fase ainda não são atribuídas: dependem do RNC/potes conciliados.
- `FutureSerieDAdmission`: auditoria conservadora aprovada pelo proprietário nesta conversa. Reconstrói os méritos nacionais usando o fechamento validado; separa candidatos nacionais, estaduais não selecionados e motivos. Ledger opcional no snapshot, chave estável, digest canônico, conflito/replay, sem inscrição efetiva ou recompensa. Não há rota pública nem integração automática em saves. A persistência do ledger foi testada explicitamente na transação da fixture, não adicionada a um comando público do Repository.
- `MandatoryTournamentCoverage`: usa agora o anexo completo; ausência de torneio não é cobertura mesmo com membership existente. Em edições futuras retorna `unassessed_not_exempt`, sem copiar a certificação de 2026. Regionais/continentais restantes continuam desconhecidas, não dispensadas.

## Conciliação de identidades e Copa do Brasil

113 IDs resolvidos no catálogo global fornecido ao teste; 114 com o complemento de Alagoas já existente (Penedense). Faltam 12 chaves nesse contexto:

Vasco-AC; Independente-AP; Atlético de Alagoinhas-BA; Desportiva Ferroviária-ES; Porto Vitória-ES; FC Pantanal-MS; Bragantino-PA; Castanhal-PA; Boavista-RJ; Ji-Paraná-RO; América-SE; Capital-TO.

São identidades presentes no anexo, **não clubes inventados nem comprovação de que estejam ausentes do Hub/API**: o teste usa a referência local com Hub desligado. Não foram equiparadas automaticamente a homônimos de outra UF. As chaves ainda ausentes são propostas de referência, não novos IDs instalados. CSV completo: `Docs/mundializacao-v07/copa-brasil-admissao-d/copa-participantes.csv`.

Falta implementar/conferir: referências locais dessas identidades; RNC 2026, potes e sorteios; posições de mando e cruzamentos do Anexo B; resolução esportiva das nove fases, replay e integração Game/Repository; revisão completa de alterações oficiais e condições de inscrição. O estádio/BID não foi certificado. **Esses são trabalhos restantes de implementação e conciliação, não uma alegação de impossibilidade das fontes.** Nenhum torneio sintético de capacidade recebeu o nome de copa oficial jogada.

O REC fixa 128 participantes a partir de 2027 e seis entradas pelo critério de campeões, incluindo Norte, Centro-Oeste e Sul-Sudeste. Não basta repetir os 126 de 2026; essas dependências e as classificações estaduais ainda precisam de resultados verificáveis no universo.

## Série D futura e renovação

A política aprovada é conservadora: apenas mérito nacional ou classificação estadual/seletiva comprovada no save; nenhuma seleção por catálogo, preenchimento paulista ou sorteio. A aprovação **não transforma quotas de 2026 em regra oficial de 2027**, nem certifica seletivas estaduais ainda não modeladas.

O fechamento atual produz 26 candidatos de retenção D e dois descensos C: 28. Déficit permanece **68**. O novo ledger discrimina 64 vagas de referência por 27 federações e quatro residuais; regras futuras, fontes/rankings residuais e resultados classificatórios ainda faltam. Todos os estaduais observados sem política certificada ficam não selecionados com motivo. Não há seleção positiva de novas vagas estaduais nesta entrega.

`NationalPyramid`, `NationalRenewal` e o preenchimento paulista legado não foram alterados. Trocas A/B/C/D, C24 e recomposição D continuam propostas bloqueadas. O caminho positivo de renovação atômica, arquivo da edição e criação de calendário seguinte permanece ausente; não foi disfarçado por repetição de participantes. Nenhum calendário/mercado novo foi habilitado. Relações públicas: 23 estaduais e nove internacionais continuam bloqueadas.

## Evidências e testes reproduzidos

Pasta: `Docs/mundializacao-v07/copa-brasil-admissao-d/`.

| Evidência | Resultado |
|---|---|
| `contracts.json` | 62 verificações: anexo, fases/capacidades, duplicatas/entrada inválida, candidaturas, ledger/replay/conflito, resultados adulterados, estadual incompleto, ausência de torneio e edição futura |
| `copa-global.json`, `copa-pilot.json`, CSV | 126 registros; 113/114 IDs resolvidos nos respectivos contextos; bloqueios explícitos |
| `future-d.json` | 28 candidatos nacionais, déficit 68, motivos estaduais; sem inscrições aplicadas |
| `mandatory-coverage.json` | Obrigações por clube, desconhecido não significa dispensa |
| `mysql-expresso.json` | 30 verificações, uma edição nacional existente, reload por comando, duas contas, lock real, crash após save/antes do ledger, retry, rollback integral |
| `regressao/suites.json` | 24 suítes passaram; 1.014 cenas, três modos e contratos anteriores preservados |
| `http-runtime.json` | 26 verificações HTTP autenticadas em PHP loopback; fixture removida |
| `mobile/results.json` | 39 verificações Chrome 320/390/1280, teclado, sem overflow; HTML da fixture autenticada |

Adicionais executados: fronteiras v0.6 (11), fronteiras v0.7 (24), conquistas (96; 50/1.000 R), feedback v0.6.1 puro (32), pós-jogo (215; seis posições/três modos), flag desligada com 503 sem converter save. Não houve alteração de fórmulas, XP-base, probabilidade, força, posse, mercado, autenticação ou benefícios. A coorte PDO desativa conquistas na fixture; não comprova concessão de R nela.

PDO Expresso: 180 comandos, 6.303 consultas; p50 **350,12 ms**, p95 **687,42 ms**, p99 **1.217,76 ms**, pior comando/fechamento **2.852,61 ms**; duração da edição **76.531,71 ms**. Snapshot final **1.713.405 bytes**, pico **1.916.437 bytes**; fechamento 6.344 bytes, histórico nacional anterior vazio (2 bytes). Medidas da edição existente de 1.733 jogos, **sem Copa do Brasil**, sem temporadas 2/3/5. Não são benchmark controlado de melhoria sobre a entrega 52: amostras/resultados/quantidades de comandos variam. Não mediu UOL.

O teste do ledger faz gravação explícita apenas no JSON da carreira descartável, recarrega pelo Repository e reverte por savepoint; não afirma ter testado uma nova transação pública de renovação. Nenhuma migration/DDL ou backup/restauração foi necessária. Não foram alterados usuários reais ou rh_*.

O novo smoke HTTP foi inicialmente impedido antes da execução por indisponibilidade da revisão automática de aprovação (limite de uso). Quando a revisão voltou a responder, a tentativa autorizada passou: 26 verificações, fixture limpa. Chrome passou em 39 verificações, com novas capturas 320/390/1280 e inspeção da carreira em 390. Sem mudança de interface neste lote; o menu fixo continua exigindo rolagem para alcançar algumas alternativas. Isso não constitui auditoria completa de leitor de tela ou dispositivo físico. IIS, Apache, HTTPS/produção e login central Hub não foram testados nem modificados.

## Reprodução e recuperação

PHP 8.4 com mbstring; pdo_mysql para a coorte local, banco `rumohub`, host loopback. Os scripts ativam flags somente no processo e recusam produção. Não editar .env.

```
python bin/build-copa-brasil-annex.py
php tests/copa-brasil-reference-v07.php
php tests/copa-brasil-admission-mysql-v07.php --local-rollback
python bin/test-narrative.py
```

Definir `V06_EVIDENCE_DIR` para guardar a regressão no diretório desta entrega. O runner gera três relatórios legados: foram copiados para esta evidência e os originais preservados. Coorte volumosa tem gzip conferido contra o JSON original antes de remover somente o original gerado.

Rollback: manter flags desligadas e retornar o código, sem restaurar banco sobre progresso posterior. Os testes PDO revertem todas as gravações próprias; não há schema a remover. Se um consumidor futuro persistir `future_d_admissions`, manter o campo aditivo preservado: ele não altera memberships/recompensas. Não há deploy, merge ou migração produtiva autorizados/executados.

## Fontes e menor próximo lote

Consultadas 17/10/2026. [REC CBF Copa do Brasil 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf): arts.2,10–24 e Anexos A/B, PDF pp.19–26; [publicação técnica CBF](https://www.cbf.com.br/futebol-brasileiro/noticias/undefined/cuiaba-saf-x-fluminense/cbf-divulga-tabela-basica-plano-geral-de-acoes-e-regulamento-especifico-da-copa-do-brasil-2026), 06/01; [critérios do sorteio CBF](https://www.cbf.com.br/futebol-brasileiro/noticias/selecao-base-masculina/a/sorteio-da-copa-do-brasil-sera-nesta-quarta-feira-confira-os-criterios), 27/01 (consulta indexada; acesso direto alternou 502). REC D/RNF e fontes das partidas mantidos nos arquivos versionados da entrega 52; não houve nova certificação de quotas 2027.

Próximo lote mínimo: concluir identidades/potes/matriz de chaves da copa e seu adaptador executável; integrar uma edição Game/PDO com personagem/NPC/título; mapear regionais obrigatórias por clube e políticas classificatórias por federação. Só após admissões/obrigações completas implementar aplicação atômica da renovação e testar duas renovações/cinco temporadas da mesma carreira. Otimização possível, ainda não aplicada: referências imutáveis deduplicadas por digest com leitura retrocompatível, evitando repetir contratos em snapshots futuros. Não alterar saves em massa para isso.

**Portão de encerramento não atingido.** Esta é uma entrega parcial de contratos/auditoria, com runtime público bloqueado; não é Copa do Brasil jogável ou renovação nacional concluída.

## Git

Commits de código: `c07d35fc5237adfba84035f09a3530d29cbd4d4c` (anexo/contratos/cobertura) e `8e7ecf535ea39d377f12eac7f5c501a99ea191aa` (ledger D/testes). Evidências/documentação seguem em commit separado. Destino exclusivo autorizado: `origin/codex/mundializacao-v07`; confirmação do SHA remoto no encerramento. Nenhum merge/main/deploy.
