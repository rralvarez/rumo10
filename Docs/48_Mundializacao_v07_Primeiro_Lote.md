# v0.7 — primeiro lote: integração local e portão oficial bloqueado

Data: 16/10/2026. Base verificada: `b35dcc5`, branch `codex/mundializacao-v07`; v0.6.1 preservada. Esta entrega é parcial segura: NÃO conclui o primeiro lote brasileiro oficial nem a mundialização.

## Resultado efetivo

O motor real Game/PlayMode/Repository e o renderizador agora aceitam referências fixadas por edição e agenda consolidada. A integração está restrita a fixtures sintéticas CLI locais: duas divisões de quatro clubes existentes e uma copa de oito clubes existentes. Não foram cadastrados clubes, ratings ou competições oficiais novas. Não existe opção pública para converter uma carreira. `RUMO10_WORLD_CALENDAR=0` continua efetivo; produção é rejeitada mesmo com a flag. Nenhum save real foi convertido.

O contrato fixado acompanha o JSON do save. Digest v2 ordena chaves associativas, preservando listas, para sobreviver à normalização JSON do MySQL; leitura v1 continua suportada. O contexto de referência usa try/finally e não vaza entre carreiras. Saves legados continuam no fluxo anterior. Desabilitar a flag em save de fixture retorna indisponibilidade explícita, sem convertê-lo silenciosamente ao legado.

Agenda determinística por carreira/edição, descanso simulado de três dias, resultados NPC na ordem da agenda, avanço de fases e fechamento somente após os torneios obrigatórios. Marcadores persistidos impedem repetir recompensa/título no retry. A integração reaproveita transação, nonce e versionamento do Repository. Foram adicionadas validações de pares, slots e clubes repetidos. O piloto limita agenda e processamento síncrono a 128 eventos/partidas; não demonstra escala mundial na UOL.

Projeções distinguem mínimo garantido de possíveis mata-matas: na fixture, 13 jogos garantidos e até dois adicionais. Sala de Troféus separa títulos principais, etapas e legado não classificado conforme o tipo armazenado; nenhuma conquista anterior foi apagada ou reinterpretada por palpite. Coleta de troféus, XP-base, força, probabilidades, envelhecimento, mercado e Pontos R não foram recalibrados.

## Cobertura e bloqueios reais

| Conjunto | Estado |
|---|---|
| Competições locais/cache | 131 / 154, cache não comprova API atual |
| 23 vínculos brasileiros pendentes | ZERO resolvidos ponta a ponta; 23 bloqueados |
| 9 vínculos internacionais | 9 continuam bloqueados |
| Amazonas B → A | preservado, sem nova correção |
| Liga + copa oficial no site | NÃO liberadas |
| Liga + copa sintéticas no gameplay | integradas e testadas |
| Série D 2026 | política nova incompleta/bloqueada |
| Hub, produção e banco produtivo | intocados |

A relação Alagoas B → A foi documentada: uma promoção e um rebaixamento. O REC B lista seis clubes, incluindo Sete de Setembro; o catálogo usa Sporting-AL. A notícia oficial menciona Sporting Teotônio, mas falta comprovação canônica da identidade/alias para reconciliar os registros sem suposição. Não foi liberado acesso com base apenas nessa notícia.

O REC revisado da Copa Alagoas foi obtido e lido; portanto, acesso ao PDF NÃO é mais o impedimento. Permanecem a tradução/teste do regulamento efetivo, inscrições e identidade dos participantes, dependências nacionais obrigatórias, vagas e política de edições futuras. Não basta inserir uma copa isolada e prometer continuidade falsa.

O avaliador CompetitionQualification valida política versionada, quotas, associação, clubes conhecidos, edição, fonte permitida e digest da evidência. Foi testado com dados sintéticos, não certifica por si só a autenticidade esportiva de uma alegação. A política br-d permanece bloqueada: faltam mapa completo de quotas/ranking, evidências canônicas das 96 inscrições e substituição válida para edições simuladas futuras. O preenchimento paulista do fluxo legado NÃO foi removido: inclusive novas temporadas que ainda usam esse fluxo mantêm a limitação conhecida. Nenhuma lista fictícia foi apresentada como oficial.

## Fontes primárias

Matriz completa: `lote1-fontes.csv/json`; vínculos individualizados: `lote1-continuidades.csv/json`, na pasta deste relatório.

- CBF, REC Série D 2026, art. 2, p. 4–6; art. 6/7, p. 7: https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf
- FAF, Alagoano A 2026, art. 1, p. 2; arts. 11–12, p. 4: https://futeboldealagoas.net/assets/uploads/176832886543.pdf
- FAF, Alagoano B Sub-23 2026, art. 1, p. 2; art. 7 §3, p. 3; art. 14, p. 5–6: https://futeboldealagoas.net/assets/uploads/177223121748.pdf
- FAF, Copa Alagoas 2026 revisado, arts. 1–8, p. 2–4: https://www.futeboldealagoas.net/assets/uploads/177326005939.pdf

Esses documentos não tornam as fixtures um regulamento oficial. Datas e divisões de teste são adaptações explicitamente identificadas. Nenhum resultado histórico real foi importado.

## Testes e evidências

Em `Docs/mundializacao-v07/`:

- `lote1-regressao/suites.json`: 24 suítes existentes passaram; 1.014 cenas preservadas, seis posições, três modos, limites, identidade e benefícios por contratos. Catálogo 50 conquistas/1.000 R preservado.
- `lote1-final-checks.json`: repetição incremental após os últimos guards; inclui fundação, fronteiras e runtime. Contagens do runtime variam com resultados aleatórios; consultar o arquivo, não interpretar diferenças como cenas novas.
- `runtime-tests.json`: 18 temporadas completas, seis posições × três modos; JSON reordenado, isolamento, fechamento e replay.
- `gameplay-five-seasons.json`: cinco temporadas inteiras pelo Game::command, com permanência, divisões, resultados e títulos conforme ocorridos. Não são apenas agendas geradas.
- `transfer-runtime.json`: aceite de proposta e temporada seguinte no motor; RNG controlado na fixture para exercitar a oferta elegível, sem modificar as fórmulas reais. Ofertas naturais podem ser zero.
- `mysql-runtime.json`: cinco temporadas inteiras via Repository/PDO, reload a cada comando, 33 verificações, token repetido/conflitante, duas contas e lock real em segunda conexão. Escritas revertidas por transação; nenhuma DDL. Conquistas desligadas apenas nessa fixture para medir o motor; suítes próprias cobrem sua persistência.
- `http-runtime.json`: 22 verificações com conta descartável, login local em servidor PHP isolado, páginas, CSRF, decisões, repetição e isolamento; limpeza final. Não equivale a login central Hub/IIS autenticado.
- `mobile/`: 39 verificações Chrome e 12 screenshots, 320/390/1280, quatro páginas, sem overflow, menu por teclado. HTML autenticado capturado da fixture; imagens versionadas não contêm usuários reais.
- Pós-jogo: 215; conquistas: 96; v0.6.1 pura: 32; persistência v0.6.1: 25; HTTP v0.6.1: 17 verificações. IIS público: smoke de 15 rotas e CSRF/método. Apache/HTTPS/produção NÃO testados nesta rodada.

Falhas intermediárias não ocultadas: `lote1-incrementais.json` guarda a antiga fronteira v0.6 rejeitando os novos hooks autorizados; teste adaptado para permitir apenas esses hooks, mantendo comparação das fórmulas e demais arquivos. A repetição final está separada. Harness HTTP inicialmente usou CSRF anterior ao login, corretamente rejeitado; corrigido para token autenticado. Harness visual recebeu rota de favicon e teste de gameplay usou a ação inicial correta. Nenhuma falha foi resolvida afrouxando regras esportivas.

## Desempenho local medido

Última coorte PDO: 140–168 comandos e 4.903–5.883 consultas por temporada; 6,96–13,01 segundos para a temporada INTEIRA distribuída em comandos; comando mais lento 113,76 ms; fechamento final 73,02–105,06 ms; snapshots 116.065–168.153 bytes. Esses números não são benchmarks Apache/UOL nem evidência de milhares de jogos em um clique. As consultas por decisão e crescimento do snapshot precisam de revisão antes de ampliar o limite piloto. Novas fases podem acrescentar partidas, portanto a agenda inicial não é a contagem final.

## Reprodução local

Usar PHP 8.4 com mbstring, e pdo_mysql para testes PDO. Exemplos:

```
php tests/world-foundation-v07.php
php tests/world-runtime-v07.php
php tests/world-gameplay-v07.php
php tests/world-transfer-v07.php
php tests/world-mysql-v07.php --local-rollback
php tests/world-http-v07.php --local-fixtures
node tests/world-mobile-v07.cjs
php tests/world-disabled-v07.php
php tests/world-boundary-v07.php
```

Os testes definem flags no próprio processo, não exigem alterar .env. Os scripts MySQL/HTTP exigem explicitamente ambiente local e fixture; ler seus guards antes de executar. O teste visual usa capturas privadas geradas pelo HTTP em storage/qa/v07, não versionadas. A rota normal http://rumo10.local continua no comportamento legado: ela NÃO oferece a copa de teste.

## Migração, recuperação e próximo portão

Nenhuma migration SQL/DDL necessária: estado fica no snapshot JSON existente, dentro da transação de decisão. Nenhum backup/restauração de produção realizado. Não há motivo para restaurar dados reais para desfazer esta entrega: manter flag desligada e reverter o código se necessário. Backups privados, .env, HTML com tokens e dumps não integram os commits.

Próximo lote: fechar identidade e inscrições do eixo escolhido; implementar/testar REC de liga/copa e dependências nacionais; política de admissões por edição e futuras temporadas; então demonstrar cinco temporadas oficiais simuladas antes de disponibilizar a usuários. Sem isso, a flag permanece desligada. Não avançar à etapa continental.

Push autorizado somente à branch de trabalho, condicionado a testes e revisão. Nenhum merge, deploy ou alteração UOL/Hub nesta entrega. SHA remoto final informado na entrega e em recibo separado para não criar autorreferência no commit.
