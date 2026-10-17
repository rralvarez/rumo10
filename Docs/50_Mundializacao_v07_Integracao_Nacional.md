# v0.7 — integração nacional e fechamento incremental de Alagoas

Entrega local de 17/10/2026. Base conferida: `ea8624b51682cc5f34e91c13e9cd2a961e09e6b8`, branch `codex/mundializacao-v07`. Nenhuma alteração do Hub, tabelas rh_*, produção, .env, configuração IIS/Apache ou usuários reais. O levantamento não versionado anterior foi preservado.

**Resultado:** cinco temporadas estaduais atualizadas e uma edição completa da Série B nacional integrada ao calendário de Alagoas. A edição combinada executa 467 partidas: 83 estaduais e 384 da Série B, incluindo playoffs. É integração local de teste, não liberação de uma carreira nacional completa. A próxima edição nacional permanece indisponível; a mundialização não está concluída. Não foram simuladas cinco temporadas nacionais.

## 1. Elegibilidade estadual

`SubTwentyThree` avalia todos os atletas do elenco de referência. Jogador real sem nascimento válido fica como não verificável: `age_at_import` não é usado como prova. Para fictícios, ano/idade simulados são identificados como tais. O corte de referência 2026 é nascimento a partir de 2003; o avanço anual posterior é adaptação explícita, não regulamento oficial futuro.

A lista de jogo tem até 23 atletas, no máximo cinco acima do corte e mínimo de onze disponíveis. Há reserva de vaga para o personagem, inclusive de uma exceção acima da idade quando necessário. O elenco do simulador mantém mínimo de 24 e dois goleiros; há ao menos um goleiro elegível nos relacionados. Quando faltam jovens fictícios, o save pode gerar complementos explicitamente fictícios. Não modifica nascimento, nota-base ou cadastro de jogador real; o modelo de força não passa a usar novas notas oficiais inventadas.

A auditoria completa da referência, os IDs relacionados, o hash da fonte e os complementos fictícios ficam em `squad_registrations`, por edição. O pool inscrito pode ser um subconjunto da referência, limitado a 50, sem excluir o restante do catálogo. Os registros deixam explícito `official_bid_verified=false`: não são certificação de BID/contrato profissional, nem autenticação com a CBF. NPCs continuam sendo simulados pelo modelo coletivo existente, não por escalação individual detalhada.

## 2. Mandos e edição fixada

Os quinze mandos da primeira fase da B foram conferidos na tabela final oficial FAF, p.1, refs.001–015, atualizada em 01/06/2026. Apenas rodada, mandante e visitante foram extraídos; nenhum placar histórico foi importado. A correspondência Sporting FC/antigo Sete permanece nos aliases anteriores.

Fonte: [Tabela detalhada FAF](https://futeboldealagoas.net/assets/uploads/178302506438.pdf). O link antigo indexado retornou 404; a tabela vigente foi localizada pelo portal da competição. `database/competition-references/al-b-homes-2026.json` registra a substituição da fonte e os confrontos.

A renovação arquiva os contratos anteriores em `world_reference_history` e fixa novos contratos `simulated-faf2026-reuse-v2`. Não consulta silenciosamente a edição vigente do JSON para reescrever partidas encerradas. A política futura ordena os clubes remanescentes pela campanha simulada e usa um template com três mandos para os três prioritários e dois para os demais. Isso é adaptação do simulador; não afirma inscrição/mando oficial posterior. Datas permanecem consolidadas e simuladas, com descanso de três dias.

## 3. Competição, classificação e carreira são estados separados

`ExternalQualification` mantém:

- disponibilidade do componente esportivo;
- classificação externa pendente ou consumida;
- continuidade da carreira, considerando torneios obrigatórios descobertos/inscritos e ainda não cobertos.

Uma classificação futura pendente pode coexistir com competição estadual executável. Ela não cria inscrição, partida, título, R ou XP. Se já houver torneio obrigatório sem cobertura no calendário, a carreira não pode simplesmente ignorá-lo. A função de disponibilidade registra a razão; não é um mecanismo para declarar licenciada qualquer competição.

O ledger usa identidade estável por fonte/edição/clube/destino/edição-alvo, rejeita evidências conflitantes e sobrevive à ordem de chaves JSON do MySQL. O consumo exige inscrição verificada, edição correta, participação já existente, calendário e contrato válido no destino. Consumo não insere uma vaga por conta própria. O teste de pendente → consumido utiliza destino sintético suportado; NÃO significa que a Copa do Brasil de uma temporada futura foi implementada.

As candidaturas anteriores de Alagoas foram preservadas, e o ledger novo foi acrescentado sem backfill massivo. A interface mostra as classificações do personagem como pendentes. Na integração nacional, acesso e descenso são méritos calculados e registrados, mas a movimentação para destinos ainda ausentes não é aplicada.

## 4. Primeira integração nacional efetiva

Reutiliza o ID `br-b`, os vinte clubes existentes e o motor `b_playoff`; não duplica competição. O REC CBF confirma 38 rodadas, título por pontos, dois acessos diretos e dois por playoffs 3º×6º/4º×5º. Os confrontos são de ida/volta, com retorno na casa do melhor colocado e desempate por pontos, saldo e classificação anterior, sem pênaltis.

[REC Série B 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_B_2026_85d55b9f72.pdf): art.3/Anexo A, pp.4/16; arts.5 e 11–14, pp.6/9–10. A tabela CBF também confirma os participantes. O título testado nasce dos 384 resultados finalizados. Quatro méritos de acesso e quatro de descenso são registrados, sem conexão fictícia com a promoção estadual.

O CRB disputa A estadual, Copa Alagoas e Série B nacional no mesmo calendário, sem sobreposição/descanso insuficiente. Os elencos de inscrição simulada nacionais respeitam 25–50 atletas (REC art.6), com dois goleiros pelo contrato do jogo. Referências maiores que cinquenta não são apagadas: uma lista simulada de inscrição é separada delas. Complementos são fictícios, não atletas reais inventados. BID, contratos e demais condições legais não são certificados.

Esta edição combinada é preparada por `NationalTrial` somente no teste CLI local. `world_runtime` continua protegido por ambiente/flags. Não há conversão de saves nem nova estreia pública. A janela de transferências/renovação dessa fixture nacional fica bloqueada com mensagem explícita; não mantém fraudulentamente os mesmos vinte clubes na edição seguinte. O calendário integral de todos os torneios obrigatórios de uma carreira real ainda precisa de auditoria/implementação.

### Matriz de escopo nacional

| Competição | Estado nesta entrega |
|---|---|
| Série A | catálogo/motor legado existentes; não integrada nem validada neste novo calendário |
| Série B | uma edição integrada e testada; destinos/renovação nacionais indisponíveis |
| Série C | catálogo/motor legado existentes; continuidade não validada neste incremento |
| Série D | política nova continua bloqueada; preenchimento paulista legado preservado |
| Copa do Brasil | classificação pendente; runtime não implementado nesta entrega |
| Copa do Nordeste | não implementada nesta entrega; nenhuma vaga concedida |

Inventário focado: `national-inventory.json`. Cache Hub não é prova de API atual. Nenhuma competição nacional não testada é declarada concluída. Os 23 vínculos brasileiros e 9 internacionais não foram liberados para estreias/mercado público.

## 5. Série D: limite preservado

`SerieDAdmission` e a política v2 anteriores continuam disponíveis como base, com RNF/quotas verificadas, porém bloqueadas. Faltam ledger completo e canônico de elegibilidade dos 96 clubes, exceções/seleções, ranking residual e política futura de participação apoiada nos resultados do save. Não foi substituído o preenchimento paulista de `Competition::completeWorld`; retirar essa rotina agora quebraria saves sem sucessor validado. A implementação deste incremento não declara corrigida a Série D.

## 6. Escalabilidade e persistência

O limite padrão de 128 permanece. A integração nacional isolada admite até 512 partidas, com orçamento explícito de oito NPCs por continuação. Ao atingir o orçamento, salva `world_processing` e o cursor/placares dentro da transação existente. O jogador continua por POST autenticado, CSRF, versão e chave idempotente. Retry não reprocessa jogos ou recompensas. Não depende de cron, worker permanente ou serviço externo.

A agenda é reaproveitada dentro de cada lote e reconstruída quando uma fase acrescenta jogos, em vez de a reconstruir a cada resultado NPC. Normal/Rápido/Expresso reconhecem a etapa intermediária. O plano do Expresso pode ser retomado após o lote; a continuação técnica não é uma nova escolha esportiva.

Ao encerrar a edição nacional, usa-se `Competition::compactResults` existente: só remove campos vazios/zerados de NPCs. Resultados, cartões, pênaltis, logs com conteúdo e estatísticas do personagem são preservados. Não compacta saves reais em massa nem remove gols/títulos.

Métricas reais ficam em `national-mysql.json`, `national-runtime.json` e `state-mysql.json`: latência por comando/fechamento, consultas, snapshot final e pico. Cerca de 35 consultas por comando e regravação do snapshot completo continuam sendo custos importantes; não houve alegação de eliminação desses custos. A execução de uma edição inteira soma centenas de requests e não deve ser confundida com um único request de cem segundos. No PDO nacional final foram 620 comandos, 21.703 consultas, máximo de 242,33 ms por comando e 160,65 ms no fechamento. Snapshot final: 508.225 bytes; pico: 780.351 bytes. A edição inteira levou 99,24 segundos distribuídos entre os comandos. Os números são locais, não um benchmark UOL/Apache.

## 7. Testes e evidências

Diretório: `Docs/mundializacao-v07/integracao-nacional/`.

- `contracts-tests.json`: 68 verificações de idade, mandos, idempotência, consumo em destino suportado e independência dos estados.
- `state-five-seasons.json` / `state-mysql.json`: cinco temporadas estaduais completas atualizadas, incluindo mandos futuros e registros Sub-23; PDO com recarga, duas contas, retry/conflito de token, lock em outra conexão e rollback integral (33 verificações).
- `national-runtime.json`: uma edição combinada, 467 partidas, título nacional, playoffs, calendário, replay e bloqueio da renovação; 956 verificações.
- `national-mysql.json`: uma edição combinada via Repository/PDO, com recarga, lock/isolamento e rollback (17 verificações).
- `national-modes.json`: três modos concluíram a edição combinada (2.832 verificações). A rotação das seis posições continua coberta pelas regressões e pelo piloto anterior, não por dezoito novas edições nacionais.
- `http-runtime.json`: 26 verificações HTTP autenticadas com fixture descartável, incluindo continuação e replay do lote. Conta removida ao final.
- `mobile/`: 39 verificações Chrome, 320/390/1280; capturas do HTML autenticado loopback, não dispositivo físico.
- `regressao/suites.json`: 24 suítes existentes passaram; 1.014 cenas preservadas. Pós-jogo 215, catálogo de conquistas 96, v0.6.1 pura 32 e fronteiras 11/24 passaram separadamente.

As suítes PDO desativam conquistas somente nas fixtures; não são prova de concessão de R nessa coorte. Não se alteraram os 50 objetivos/1.000 R, fórmulas de XP/probabilidade, força, envelhecimento, identidade ou benefícios. Apache/produção e login central Hub no IIS não foram testados nesta rodada; HTTP autenticado usou PHP loopback com Hub desligado.

Falhas encontradas e corrigidas: template futuro inicialmente passou inteiros a Schedule::match; em seguida o teste de quotas mostrou que a agenda genérica não era balanceada para seis clubes. Foi substituída apenas na adaptação futura da B por template 3/3/3/2/2/2, sem modificar Schedule global. A inscrição nacional também revelou referências com mais de cinquenta atletas: agora referência e lista de inscrição são separadas, sem apagar jogadores. As fixtures com erro foram revertidas; as repetições finais passaram. Esses achados não foram escondidos nem resolvidos com alteração das probabilidades.

## 8. Reprodução, recuperação e publicação

PHP 8.4, mbstring e pdo_mysql; banco local rumohub, scripts com guards de ambiente. Exemplos:

```
php tests/national-contracts-v07.php
php tests/national-state-seasons-v07.php
php tests/national-state-mysql-v07.php --local-rollback
php tests/national-runtime-v07.php
php tests/national-mysql-v07.php --local-rollback
php tests/national-modes-v07.php
php tests/national-http-v07.php --local-fixtures
node tests/national-mobile-v07.cjs
```

Não executar em produção. Flags são definidas pelos testes, sem mudar .env. Sem DDL/migration/backfill; novos estados/ledgers ficam no JSON do save de teste. Rollback de código com flags desligadas, sem restaurar banco sobre progresso posterior. Não houve backup/restauração produtiva.

Próximo lote: validar inscrição e continuidade nacionais por edição, incluindo Série D; integrar destinos A/C e torneios obrigatórios antes da renovação nacional; só depois habilitar nova carreira local de usuário fora do piloto. A separação de classificação pendente permite avançar componentes independentes, mas não autoriza omitir torneios já obrigatórios.

Commits organizados e push apenas a `codex/mundializacao-v07`; SHA remoto confirmado na entrega. Nenhum merge em main, deploy UOL, migration produtiva ou alteração do RumoHub. As funcionalidades anteriores continuam no fluxo legado; os dados reais não foram usados como fixtures.
