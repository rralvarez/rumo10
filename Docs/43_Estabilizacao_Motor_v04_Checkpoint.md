# Estabilização v0.4 — checkpoint local, 16/10/2026

**Pacote em andamento. Este documento não declara conclusão das roletas, mercado, continuidade ou loading.**
Contrato: texto anexado v0.4, seção 14 prevalecente. Base inspecionada `a5bb59e`, branch
`codex/expansao-narrativa-v1`. Sem reset, push, deploy, UOL, edição do Hub ou migration.
Commits locais de código: `1c1bb03` (execução) e `5cadb73` (reprodução/diagnóstico).
`Docs/levantamento-2026-10-15/` é preexistente e não foi incluído nos commits.

## Diagnóstico reproduzido e correção implementada

UI chama `Game::chance`; comando calculava a mesma função e `Probability::roll` decidia o
resultado. Um chute bem-sucedido já produz um único gol em `fieldDecision`, com `match_event`
nas cenas novas. Não havia divergência de unidade percentual entre UI e sorteio nem uma
segunda rolagem para converter chute bem-sucedido. O colapso estava **antes do sorteio**.

O cálculo antigo era atributo/estados/dificuldade/adversário → `goalChance` →
`MatchBalance::eventChance`. O último estágio limitava uma EXECUÇÃO já criada à cota
`100 * xG_da_equipe * participação_do_personagem / 7`, ainda multiplicada por
`0,4 + 0,6 * chance/100` e limitada pelo saldo coletivo. A cota era reaplicada à chance clara;
o saldo gasto poderia levá-la a zero. Nenhum multiplicador próprio do modo Normal foi encontrado.

Código original isolado do commit `a5bb59e`, sem banco, reproduziu:

| Habilidades uniformes; clubes 50×50 | Chute normal antes | Depois | Chance clara antes | Depois |
|---|---:|---:|---:|---:|
| 45 | 6,51% | 16,71% | 7,80% | 35,90% |
| 65 | 8,29% | 26,77% | 9,69% | 50,51% |
| 85 | 10,08% | 39,98% | 11,42% | 65,03% |

Estados: energia 85, confiança/ritmo 50; titular, entrada 0, saída 90, minuto 35, chute no
canto para a chance clara. A linha 45 usa todas as habilidades em 45 para controlar fatores;
não equivale exatamente ao centroavante padrão, cuja finalização inicial é 55.
27 comparações e componentes completos em `estabilizacao-v04/execucao.json`.

**Correção da própria evidência:** o primeiro `baseline.json` deixou saída em zero. Sua coluna
`clear` selecionava uma cena social e NÃO comprova chance clara. Não foi sobrescrito: preserva
hashes/esquema/contagens originais. A comparação válida vem de `antes-corrigido.json`, obtida
com código anterior isolado, validando entrada/saída, ação chute e `MatchMomentum::available`.

## Fórmula experimental atual

`ActionResolution` concentra o cálculo; `Game::chance` é seu adaptador. Não altera prosa,
resolvedor de snapshots, gates, pesos, sementes ou escolha narrativa.

- `Q`: atributos ponderados de `Rules::WEIGHTS` para a ação. Drible que conclui cara a cara
  combina 60% dessa qualidade e 40% de Finalização.
- `O`: média das nove habilidades; `T` e `A`: overalls salvos da própria equipe/adversário.
- `R = 0,50 O + 0,25 T + 0,25 (100-A)` — insumo, não percentual.
- Bases de execução: longe 0,035; difícil 0,075; normal 0,18; clara 0,38; cara a cara 0,43;
  passe ao companheiro livre numa chance clara 0,50.
- `z = log(base/(1-base)) + 0,024(Q-50) + 0,012(R-50) + 0,006(energia-75)`
  `+ 0,004(confiança-50) + 0,004(ritmo-50) - 0,008(dificuldade-referência)`.
- `P = 100/(1+exp(-z))`, limitado a 0,5–90%. Referência: chute normal/longe 65,
  difícil 80, clara 45, drible cara a cara 65, assistência clara 35.
- Outras ações em campo: fórmula anterior com adversário neutro 50 mais `0,4(R-50)`;
  limites 5–95%. Evita aplicar adversário duas vezes. Fora do campo, fórmula anterior intacta.

Valores são calibração de jogo, não estatística oficial. Não há dado confirmado de goleiro/
unidade defensiva neste fluxo: usa overall coletivo com `opponent_unit_source=collective_fallback`.
Classe longe/difícil usa mapeamento mecânico explícito por ID/opção, sem interpretar palavras
em runtime. Opções não mapeadas permanecem normais; ampliar essa revisão é uma pendência.

A oportunidade já classificada clara recebe a base clara, sem somar o antigo `chance_bonus`
depois. Essa promoção já remunera a construção; não há bônus residual ilimitado. **A regra de
sequência/posse ainda precisa de revisão:** `MatchMomentum` existente pode acumular ações em
segmentos diferentes. Não foi inventado um ID de posse para afirmar que isso foi resolvido.

O orçamento xG continua compensando a expectativa coletiva via `MatchBalance::book` e
`collectiveMean`; não limita mais a execução apresentada. Não se alterou força-base, XP,
envelhecimento, transferências ou resultados de partidas já encerradas. Rastreio dos componentes
vai no feedback/registro existente e nas tentativas automáticas, sem dados pessoais.
`Probability::roll` aceita RNG por chamada para teste; produção continua com `random_int`.
Essa injeção cobre a rolagem primária, não todas as fontes aleatórias da temporada.

## Fronteiras, persistência e ambiente

Backup de código: `storage/private/stabilization-v04/code-before.zip`, privado e ignorado.
Baseline de 97 arquivos em `estabilizacao-v04/baseline.json`: somente Game, Probability e
PlayMode mudaram nesse conjunto; classe nova ActionResolution separada. `mechanics-boundary`
falha se outro arquivo protegido mudar sem revisão explícita.
O runner antigo agora identifica as exceções intencionais Probability/chance, mas mantém
invariância de efeitos/XP e dos demais arquivos, e exige as novas suítes. O teste anterior
que exigia chance clara <30 foi substituído pelo contrato 30–60 no mesmo cenário e assistência
mais provável; não foi simplesmente removido.

MySQL local: configuração checada antes da conexão, host restrito a loopback e banco
`rumohub`. Inspeção de esquema em transação READ ONLY, rollback ao terminar: 3 carreiras,
3 universos, 0 resumos aposentados. Não leu e-mails/senhas, não editou tabelas, não abriu
conexão de produção. O primeiro socket foi bloqueado pelo sandbox; a leitura local passou
com permissão ampliada. Não foi feito backup SQL integral nem teste de restauração nesta etapa.
Sem migrations: as informações novas cabem no JSON/auditoria existentes.

`Repository::command` já trava conta/carreira, verifica chave+hash+versão e grava em transação.
Esse caminho foi inspecionado, mas **a concorrência/retry em MySQL ainda não foi retestada**.
Testes puros exercitaram sucesso/falha com rolagem controlada e um único gol; não substituem
duas requisições concorrentes. Não foram alterados usuários, benefícios ou tabelas `rh_*`.

## Testes executados nesta rodada

- 20/20 suítes passaram: as 18 anteriores + `execution-v04` + `mechanics-boundary`.
- Regressão narrativa: 89.821 verificações; 214 resolvidos antigos idênticos, 800 novos,
  755 ativos/45 inativos, snapshots e elegibilidade preservados.
- Execução: 1.088 verificações; **135 cenários × 10.000 sorteios = 1.350.000**, RNG controlado,
  tolerância de seis desvios-padrão. Três níveis, três forças próprias, três adversários,
  cinco classes. Monotonicidade de atributo/time/adversário e igualdade dos três modos.
- Coortes atuais: **162 temporadas / 2.268 partidas**, 200.780 minutos do personagem,
  307 finalizações, 142 decisões claras, 63 gols e 171 assistências. Diferenciar gols do
  personagem, gols coletivos e oportunidades; estes não são totais de gols das partidas.
  JSON contém denominadores/estatísticas por posição, plano e modo e chance/roll de cada
  decisão em campo. Resultados são amostrais; não provam equivalência estatística absoluta
  nem representam usuários reais. Antes/depois das coortes usa sorteios independentes.
- **33 verificações visuais/interação** passaram novamente, Chrome headless com HTML real,
  transporte em memória, 320×640, 390×844, zoom 150%, foco e duplo envio de interface.
  Capturas/resultado em `regressao/mobile/`; não foi QA em IIS, Apache ou celular físico.

Evidências novas ficam em `estabilizacao-v04/regressao/`; evidências históricas do documento
42 permanecem no commit original, sem reescrever seus resultados como se fossem novos.
Nenhum teste novo de persistência IIS/Apache foi declarado.

## Amazonas e inventário inicial — ainda sem correção de continuidade

`audit-competitions-v04.php` varreu os **130 campeonatos dos JSONs versionados**; não é
certificação do catálogo efetivo servido pela API do Hub. Detectou 24 acessos declarados
sem vínculo superior. Inventário por competição em `competicoes-inventario.json`, com
edição, participantes, referência, tier interno, vínculo e alertas. Não é ainda um validador
completo de ciclos, rebaixamento e próxima edição.

No Amazonas: `am-entrada`, nome **Amazonense Série B**, edição 2026, sete clubes conhecidos,
`rules.access=1`, sem vínculo superior e `tier=1` interno. Esse tier é de simulação e não
prova primeira divisão oficial. `Competition::completeWorld` não tem par amazonense;
`Competition::result` ainda pode anunciar acesso enquanto a próxima temporada repete a B.
É uma lacuna de cadastro/vínculo; não há prova de que um importador tenha falhado.

A Série A 2026 oficial existe, com oito participantes, conforme a
[tabela publicada pela FAF](https://fafamazonas.com.br/site/noticia/faf-divulga-tabela-completa-de-jogos-do-barezao-2026/)
e o [regulamento oficial](https://fafamazonas.com.br/site/arquivos/download/arqeditor/REG.pdf),
consultados em 16/10/2026. A fonte distingue Série A de Série B; não se deve renomear a B.
Ainda falta comparar o catálogo efetivo local do Hub, calendário/participantes e capacidade
de conectar a A sem editar o Hub nesta rodada. **Não foi importada nem habilitada promoção.**

## Mapa de dependências e retomada obrigatória

| Mudança | Serviços/persistência | Testes/risco |
|---|---|---|
| Chance/gol | Game, ActionResolution, Probability, MatchBalance, PlayMode; JSON/decisões | execution-v04, finishing, momentum, balance, coortes; gol/placar |
| Sequência/posse | MatchMomentum, segmentos e interrupções Game | Ainda pendente; impedir benefício entre posses sem alterar catálogo |
| Força/XP | TeamStrength, Progression, Evolution, Rules | Sem mudança de fórmula; regressões preservadas |
| Mercado/calendário | TransferMarket, Competition, State/European/WorldCompetition, elenco | Ainda pendente; 0–3 propostas, ficar, minutos, continuidade |
| Narrativa | Scenes, Narrative*, JSON/editorial e snapshots | Imutabilidade de 97 arquivos/baseline e suíte narrativa |
| UI/roletas | Wheel, view, game.js/css, Repository/rotas | Ainda pendente; loading, timeout, mobile, idempotência real |

**Ordem para continuar, sem repetir a carga inicial:**
1. Corrigir sequência para mesma posse/lance com expiração verificável. Reconciliar orçamento
   coletivo após maior conversão; ampliar coortes de clubes fracos/fortes com protagonista.
2. Resolver Amazonas e demais vínculos: fonte oficial, catálogo efetivo, política reversível
   de teto implementado se a importação for incompatível com a fronteira do Hub; bloquear
   novos destinos inválidos, preservar campeões/históricos e testar cinco temporadas.
3. Avaliação por posição/minutos, suavização e histórico; sete cenários dinâmicos e 0–3
   ofertas distintas, permanecer, previsões de participação/calendário salvas.
4. Roleta inicial com efeitos/antes-depois; loading acessível com erro/timeout e backend
   idempotente; medir PHP/seleção/MySQL/renderização antes de otimizar.
5. Reexecutar regressões e testes reais locais descartáveis; não tocar produção.

Reprodução principal:
```powershell
& 'C:\Users\rodri\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' -X utf8 bin/test-narrative.py
& 'C:\PHP\php-8.4.26\php.exe' -n -d extension_dir=C:\PHP\php-8.4.26\ext -d extension=mbstring bin/audit-competitions-v04.php
```
Não reexecutar `mechanics-baseline.php` para sobrescrever a baseline. A reprodução anterior
usa o arquivo privado extraído do Git a5bb59e; não usa banco. Não rodar instaladores/SQL.
