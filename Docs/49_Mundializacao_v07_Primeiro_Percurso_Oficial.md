# v0.7 — primeiro percurso oficial: admissão fundamentada e bloqueio de ativação

Data: 16/10/2026. Branch: `codex/mundializacao-v07`. Base conferida: `c4d69fff21851a647697c996f848234f80bc4ed2`. Único conteúdo preexistente não versionado: `Docs/levantamento-2026-10-15/`, preservado e excluído dos commits. Workflows automáticos limitados a main; sem deploy pela branch de trabalho.

**Resultado: critério de conclusão ainda não alcançado. ZERO percursos oficiais ativados.** Esta entrega resolve a evidência de identidade e acrescenta contratos/validadores de admissão; não converte o piloto sintético em percurso oficial nem anuncia liga+copa oficial jogável. Não houve alteração do motor esportivo, saves reais, Hub, produção, credenciais ou .env.

## Escolha e descobertas desta continuação

Alagoas continua sendo o percurso prioritário: o material já reunido permite fechar a identificação e localizar precisamente as exceções da edição. Não foi repetida a auditoria mundial. A alternativa examinada, Taça Fares Lopes/Ceará 2026, também concede vaga na Copa do Brasil; trocar de estado não elimina essa dependência. Não alegamos que todos os demais estados foram descartados ou que nenhum percurso brasileiro seja possível.

### Identidade: impedimento documental resolvido

A notícia oficial da FAF afirma expressamente a continuidade do antigo Sete de Setembro para Sporting FC, utilizado também como Sporting Teotônio. Ela é suficiente para o alias de referência esportiva desta edição; não é necessário inventar um identificador federativo numérico para registrar essa correspondência. O diagnóstico anterior era excessivamente restritivo nesse ponto.

`database/competition-references/br-al-2026.json` associa esses rótulos a `Sporting-AL`, que já existe no jogo. Não altera o cadastro global, cores, cidade, escudo ou nomes em históricos. O alias não é aplicado automaticamente a outras edições.

### Copa: o REC nominal não basta

O Ato 003/2026 da FAF, de 27/01, elimina o Desportivo Aliança e manda cancelar seus jogos. A homologação confirma essa situação. Portanto, há 12 nomes no REC, mas 11 equipes efetivas após a exclusão; não se pode simplesmente gerar dois grupos de seis com todos inscritos. Não importamos o campeão ou qualquer resultado histórico.

O contrato conserva a lista nominal e registra a exclusão separadamente, com data e fonte. O validador não aplica essa sanção à Série B nem a 2027. Regulamento de referência e atos posteriores precisam compor o mesmo contrato efetivo de edição.

### Participantes e dependências

A Série B tem seis identidades resolvidas no catálogo local. Na Série A faltam quatro chaves canônicas: Coruripe-AL, Cruzeiro Arapiraca-AL, Murici-AL e Penedense-AL; a copa acrescenta Dimensão Saúde-AL. Essas cinco chaves também não constam no cache Hub lido nesta conferência, o que NÃO comprova ausência na API atual. Não consultamos nem modificamos a API do Hub. Não criamos avaliações de força para preencher as ausências.

O acesso B→A e o descenso A→B têm uma vaga cada na referência oficial. Continuam sem ativação ponta a ponta. A versão atual do runtime aceita apenas fixtures; faltam o adaptador esportivo de Alagoas, confrontos diretos nos desempates, mando da final pela campanha acumulada, elegibilidade Sub-23 e a composição futura das inscrições.

A Série A prevê qualificações nacionais condicionadas aos critérios CBF, e a copa prevê Copa do Brasil, inclusive repasse de vaga e obrigação de participação estadual. Essas vagas não podem virar promoções, ofertas de mercado ou inscrições presumidas. A Copa do Brasil 2026 possui 126 equipes/nove fases; a CBF informa 155 partidas. O limite atual do piloto é 128 partidas, antes mesmo de agregar estaduais. Liberar a copa exige ampliar de forma medida o processamento consolidado, não apenas retirar um guard.

## Série D: avanço implementado e limitação exata

A política candidata `br-d-admission-2026-v2.json` registra RNF de 27 federações e quotas estaduais verificadas: 64 vagas, com outras categorias completando 96. Não é uma lista certificada de clubes. As categorias de retenção e ranking residual compartilham o saldo, com prioridade de critérios; não são duas cotas adicionais independentes.

`SerieDAdmission` exige contexto nacional e ledger aprovados, impede sobreposição com divisões superiores, rejeita UF de vaga incompatível, prioridade alterada, edição trocada e capacidade reduzida para esconder faltantes. Reaproveita `CompetitionQualification`, sem reescrevê-lo. Os 96 claims usados no teste são explicitamente sintéticos.

A política real continua `blocked`, sem fontes de claims/contexto liberadas. Faltam prova de cada inscrição, permissões de seletivas, vínculo canônico dos clubes, ranking residual, desistências e política futura. A edição 2026 não é aplicada ao mérito estadual de 2026 para fabricar automaticamente a Série D de 2027.

O preenchimento paulista em `Competition::completeWorld` permanece intacto no fluxo legado, inclusive nas novas temporadas desse fluxo. Não foi corrigido no gameplay: removê-lo sem substituição íntegra quebraria continuidade. Nenhuma inscrição histórica foi reescrita. O novo validador não é chamado automaticamente pelo legado.

## Código entregue

- `app/CompetitionParticipation.php`: consulta pura por edição; aliases explícitos, fontes, exclusões, participantes ausentes e capacidades faltantes. Referência não instalada permanece não jogável mesmo se capacidades forem declaradas pelo chamador. Nenhuma rota recebe parâmetros para instalar contratos.
- `app/SerieDAdmission.php`: validação específica da referência 2026 antes do avaliador genérico; nenhuma escrita/DDL.
- `database/competition-references/br-al-2026.json`: participantes nominais, alias, sanção e dependências; somente referência.
- `database/competition-references/br-d-admission-2026-v2.json`: política candidata com RNF/quotas, bloqueada. A política anterior foi preservada.
- `tests/official-admission-v07.php` e `tests/official-admission-mysql-v07.php`: invariantes e roundtrip JSON real no MySQL.
- Evidências e fontes: `Docs/mundializacao-v07/percurso-oficial/`.

## Testes executados nesta rodada

| Verificação | Resultado |
|---|---|
| Admissão, identidade, exclusão, duplicatas, edição e quotas | 36 verificações passaram |
| MySQL local, JSON normalizado, transação revertida | 8 passaram; 2 SELECTs; zero DML/DDL |
| Regressão existente | 24 suítes passaram, incluindo 1.014 cenas, modos, mercado e identidade/slots |
| Fundação de referência/calendário | 163 verificações passaram |
| Fronteiras de fórmulas/catálogo/Hub | 24 passaram |
| Pós-jogo / conquistas / v0.6.1 pura | 215 / 96 / 32 passaram |
| HTTP autenticado com fixture local descartável | 22 passaram; fixture removida |
| Chrome 320/390/1280 | 39 passaram; screenshots no diretório de evidências |

Resultados reproduzíveis: `incremental-tests.json`, `admission-tests.json`, `admission-mysql.json`, `regressao/suites.json`, `http-runtime.json`, `mobile/results.json`. O smoke HTTP é PHP loopback com login local e Hub desligado; não prova login central Hub nem Apache/IIS autenticado. Visual usa HTML capturado dessa fixture, não conta de terceiros. Houve bloqueio inicial do sandbox ao socket local; repetição autorizada no mesmo banco local passou. Não foi uma falha da aplicação nem exigiu alterar serviço.

**Cinco temporadas oficiais NÃO executadas:** não existe percurso oficial ativado para fazê-lo com honestidade. As cinco temporadas PDO/sintéticas do relatório 48 não são reapresentadas como aceite oficial. Concorrência/locks, mercado, títulos e promoção do percurso oficial continuam pendentes; as regressões do motor existente não substituem isso. Nenhum título/R/XP foi concedido por estes dados de referência.

Não existe medição de fechamento de temporada oficial ampliada nesta rodada. O desempenho do piloto anterior continua registrado no relatório 48 e não é projeção UOL. Os validadores novos são locais e não estão no caminho de decisão do jogador, portanto não acrescentam consultas por clique ao gameplay atual. O teste MySQL novo só mede a integridade do roundtrip; não é benchmark de uma temporada.

## Fontes primárias e rastreabilidade

Matriz CSV/JSON em `percurso-oficial/sources.*`; recibos com SHA-256 e tamanho em `source-receipts.json`. PDFs e HTML de origem foram guardados somente em storage privado, fora dos commits. As fontes provam fatos datados; não importam resultados históricos nem autorizam uma edição futura oficial.

- [FAF, Série A 2026](https://futeboldealagoas.net/assets/uploads/176832886543.pdf): arts.1–12, pp.2–4.
- [FAF, Série B Sub-23 2026](https://futeboldealagoas.net/assets/uploads/177223121748.pdf): arts.1–7, pp.2–3; art.14, pp.5–6.
- [FAF, Copa Alagoas revisada](https://www.futeboldealagoas.net/assets/uploads/177326005939.pdf): arts.1–8, pp.2–4.
- [FAF, identidade Sporting](https://www.futeboldealagoas.net/novo/post/sporting-teotonio-conquista-o-campeonato-alagoano-serie-b-2026-e-garante-vaga-no-alagoano-centenario-2027): parágrafo explícito sobre o antigo Sete de Setembro.
- [FAF, Ato 003/2026](https://futeboldealagoas.net/assets/uploads/176962499217.pdf): arts.1–3, p.1.
- [FAF, homologação da copa](https://futeboldealagoas.net/assets/uploads/177567932470.pdf): p.1, nota da eliminação; resultados não importados.
- [CBF, REC Série D 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Brasileiro_Serie_D_2026_2def4ff8f2.pdf): art.2, pp.4–6.
- [CBF, RNF 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/RNF_Ranking_Nacional_das_Federacoes_2026_30b87e87a3.pdf): p.1, ordem das federações.
- [CBF, REC Copa do Brasil 2026](https://stcbfsiteprdimgbrs.blob.core.windows.net/img-site/cdn/REC_Copa_do_Brasil_2026_66989a5426.pdf): arts.2–3 e capítulo do formato; 26 páginas.
- [CBF, partidas da Copa do Brasil](https://www.cbf.com.br/futebol-brasileiro/noticias/liga-de-desenvolvimento/sub-14-feminino/cbf-divulga-cotas-da-copa-do-brasil-2026-a-mais-democratica-da-historia): publicação de 04/02/2026, total informado de jogos.
- [FCF, alternativa Fares Lopes](https://www.futebolcearense.com.br/2011/noticia_ver.asp?id=19069): publicação de 02/10/2026, dependência da Copa do Brasil.

## Menor próxima intervenção necessária

1. Implementar o adaptador de Alagoas sobre estes contratos, acrescentando as cinco identidades locais comprovadas sem inventar ratings oficiais; respeitar a exclusão efetiva e grupos desiguais. Isso pode ser testado isoladamente, mas não é liberação do percurso completo.
2. Fechar o ledger nacional e o adaptador/calendário de Copa do Brasil, Série D e Nordeste necessários ao percurso; processar partidas em lotes limitados/reentrantes no mesmo PHP/MySQL, sem worker externo. Há dependências de escala e inscrições, não um único alias pendente.
3. Implementar política futura explicitamente simulada, usando regulamento-base fixado e resultados do próprio save. Não é obrigatório esperar REC oficial de 2027 para criar uma adaptação declarada; é obrigatório não alegar que a adaptação é a regra oficial futura, nem carregar sanções históricas automaticamente.
4. Só então rodar cinco temporadas completas oficiais simuladas via Repository/MySQL, incluindo locks/replay, títulos e mercado, e considerar ativação local. Se o proprietário preferir reduzir o produto a estaduais sem suas vagas nacionais, isso é mudança de escopo e precisará de aprovação explícita; não foi aplicado silenciosamente.

Permanecem 23 vínculos brasileiros bloqueados e 9 internacionais; nenhum destino novo liberado. A identidade de um vínculo foi esclarecida, não o vínculo resolvido ponta a ponta. A etapa continental não começou.

## Recuperação e publicação

Sem migrations, DDL, backfill ou mudança em saves; não houve necessidade de backup para DDL inexistente. A flag permanece desligada. Reverter os commits de referência/validadores/documentação remove este incremento sem restaurar banco ou apagar progresso posterior. `http://rumo10.local` continua no fluxo anterior e não oferece Copa Alagoas.

Reprodução: PHP 8.4 com mbstring; para MySQL, pdo_mysql e configuração local já existente:

```
php tests/official-admission-v07.php
php tests/official-admission-mysql-v07.php --local-readonly
php tests/world-http-v07.php --local-fixtures
node tests/world-mobile-v07.cjs
```

Os dois últimos exercitam o piloto sintético existente, não um percurso oficial. Suítes de regressão: definir V06_EVIDENCE_DIR para uma pasta local de evidências e executar `python bin/test-narrative.py`. Não executar scripts em produção.

Commits e SHA remoto final constam na entrega. Push exclusivamente à branch de trabalho após testes; sem merge/main, deploy UOL, SQL produtivo ou alteração do RumoHub. Apache/HTTPS de produção não foram testados nesta rodada.
