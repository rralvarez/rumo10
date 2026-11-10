# Expansão narrativa v1 — entrega local de 16/10/2026

Não publicado. Sem push/deploy, migrations ou acesso à UOL. Hub permanece intocado.

## Contrato e diagnóstico

Documento obrigatório `Rumo10_Expansao_Narrativa_Especificacao_v1_0.txt` lido integralmente.
Os documentos 38/40 e HEADs foram conferidos antes de implementar. Diagnóstico completo dos
214 registros em [41_Diagnostico_Narrativa_v1.md](41_Diagnostico_Narrativa_v1.md).

Commit documental `e533f4d`; motor e primeiro lote `60a9564`; expansão completa `d3eeed0`. Somente locais,
na branch `codex/expansao-narrativa-v1`. Base anterior `dbda309`; remoto jogo `c3d19ca`.
Ampliação dos testes em `501eae9`; relatórios/evidências em commit documental separado.
Hub conferido novamente ao encerrar: HEAD `3b11f285dac1f80870054a115277a4db2c4c45e7`, sem alterações.

## Contagens verificadas e entrega editorial

Preservar os 214 sem alterar respostas anteriores. Nenhum é aprovado automaticamente para a meta.
Foram escritas **800 cenas novas, 100 por módulo**, com **3.200 alternativas**. O catálogo
nominal possui **1.014 registros / 4.056 alternativas**. Quatro fallbacks neutros ficam fora
da meta. A distinção operacional é obrigatória: **755 novas ativas / 45 inativas**. Não foram
integrados torneios, eventos de arbitragem ou prorrogação para tornar as 45 elegíveis.

| Módulo | Novas | Ativas | Inativas | Alternativas novas |
|---|---:|---:|---:|---:|
| T01 técnico | 100 | 100 | 0 | 400 |
| T02 físico/tático | 100 | 100 | 0 | 400 |
| P01 pré-jogo | 100 | 100 | 0 | 400 |
| J01 ofensiva | 100 | 100 | 0 | 400 |
| J02 defensiva | 100 | 100 | 0 | 400 |
| J03 especial | 100 | 55 | 45 | 400 |
| F01 pós-jogo/imprensa | 100 | 100 | 0 | 400 |
| F02 pessoal/carreira | 100 | 100 | 0 | 400 |

Por stage novo: treino 200, pré-jogo 100, partida 300 (255 ativas), fim de semana 200.
Os oito lotes de cem foram concluídos e compilados. A meta de catálogo foi atingida;
**cem cenas ativas em todos os módulos não foi atingida**. O bloqueio é concreto no J03:
45 cenas exigem fonte persistida para faltas/pênaltis, acréscimos, revisão, substituição,
inferioridade numérica, virada, regulamento ou torneio futuro. Remover gates para inflar
a disponibilidade produziria fatos inventados. IDs e motivos estão em `cenas-inativas.csv`.

Manuscritos literais `database/editorial/lote-*.txt`: cada cabeçalho tem ID explícito e texto;
cada uma das quatro linhas traz código, intenção, alternativa, sucesso e falha específicos.
O compilador não gera frases. JSONs por módulo em `database/scenes/` são versionados.
Overrides de efeitos por cena permitem corrigir incoerências de contexto sem mudar fórmulas.

Lotes finalizados (IDs explícitos e estáveis):

| Lote | IDs | Distribuição |
|---|---|---|
| 01 | 2000–2099 | T01 5, T02 5, P01 5, J01 25, J02 35, J03 15, F01 5, F02 5 |
| 02 | 2100–2199 | T01 95, T02 5 |
| 03 | 2200–2299 | T02 90, P01 10 |
| 04 | 2300–2399 | P01 85, F01 15 |
| 05 | 2400–2499 | F01 80, F02 20 |
| 06 | 2500–2599 | F02 75, J01 25 |
| 07 | 2600–2699 | J01 50, J02 50 |
| 08 | 2700–2799 | J02 15, J03 85 |

Situações realmente futuras podem ser escritas inativas com `future_competition`; não contar
como elegíveis em contextos atuais. Não escrever falsa rivalidade, clima, evento disciplinar
ou contratação para aumentar cobertura. Nenhuma necessidade de atingir os números justifica isso.

## Implementação local

- Contrato fechado em `NarrativeCatalog`, contextos com fonte em `NarrativeContext`.
- Gate desconhecido falha fechado. Clima, benefícios, rivalidade, pressão e contexto de torneio
  sem adaptador não podem ficar ativos. Isso não significa inexistência de toda informação
  de competição no jogo; significa ausência de uma classificação narrativa verificada.
- `Scenes::current` conserva a resolução dos 214 antigos; snapshot novo precede revisão de arquivo.
  Cenas antigas já ativas continuam até a decisão; bloqueios aplicam-se só à próxima seleção.
- 37 antigos com pressuposto sem fonte ficam fora de novas seleções. Forma individual e resultados
  ruins da equipe usam fontes diferentes. Reserva nova exige confirmação estável na regra da rodada 4;
  indisponibilidade física não vira banco. Não se alterou o cálculo publicado da escalação.
- Seleção determinística usa seed/chave/competição, memória de IDs/famílias e frequência sazonal
  e da carreira. Canal raro em 10% quando existem comuns e raras; novidade não aumenta esse canal.
- Auditoria inclui candidatos/pesos/roll/fallback no Normal e nas tentativas Rápido/Expresso.
- A única integração de placar é o metadado `match_event` substituindo a dependência de faixa de ID
  para cenas novas. `Game::chance` e `Game::effects` permanecem literalmente iguais à base.
- Fórmulas e arquivos de probabilidade, xG, XP, força, evolução, transferências e benefícios protegidos
  por hashes/testes. Nenhuma tabela, conta ou API do Hub foi alterada.
- CSS considera o menu fixo no scroll/foco e compacta somente painéis novos. Falha anterior foi
  capturada; teste visual passou após a correção.

Contrato, tipos, fontes e reservas inativas: [CONTRATO.md](narrativa-v1/CONTRATO.md).
Não há coleta externa, cadastro de clubes, alteração de ratings ou consulta a contas reais.

## Revisão editorial e limitações mensuradas

O diagnóstico inicial preserva 214 cenas, 856 opções e 69 grupos repetidos nos textos efetivamente
resolvidos. IDs 10/11 já eram inativos. As 37 restrições adicionais só afetam novas seleções;
uma decisão antiga já apresentada não é substituída nem retificada.

Nas 800 novas: 800 enunciados diferentes; zero alternativa/sucesso/falha duplicados dentro
da mesma cena. Há **78 grupos de textos curtos repetidos entre cenas** — rótulos e respostas
como recuperação da bola. Eles estão enumerados com ID/opção; não são 78 cenas duplicadas,
mas continuam sendo uma oportunidade de revisão estilística. Não se afirmou unicidade global
de todos os desfechos nem de vetores de efeitos. Os contextos e dilemas, não sinônimos, são
a unidade de autoria dos manuscritos.

A triagem lexical de enunciados usa Jaccard >=0,40 e terminou sem alertas. Dois alertas foram
revistos com mudança de conteúdo: 2428 passou a tratar de um recorte de fala que parecia culpar
um colega; 2642 passou a tratar de um pedido no pé que muda para corrida. Não houve mera troca
de adjetivos. Ausência de alerta lexical não é certificação automática de originalidade semântica.

O CSV de alternativas expõe também excepcional/falha grave: usam a descrição específica de
sucesso/falha normal quando não há texto alternativo, preservando a intensidade mecânica existente.
Arcos persistentes e contextos sem fonte não foram ativados para completar números.

## Evidências finais

- **18/18 suítes**, lint de **11 arquivos PHP**, `git diff --check` sem erro.
- Teste principal: **89.821 verificações**, incluindo igualdade exata dos 214 resolvidos antigos,
  quatro escolhas/desfechos distintos por cena e testemunha de elegibilidade de cada ID novo ativo.
- **43 arquivos protegidos inalterados**, além dos corpos de `Game::chance` e `Game::effects`.
- Execução integral inicial: **90 temporadas / 1.260 partidas**, plano equilíbrio. Ampliação
  posterior: **162 temporadas / 2.268 partidas**, equilíbrio/protagonismo/coletivo, três amostras
  por posição/modo/plano, **12.116 decisões automáticas auditadas**, 15 gols e 111 assistências
  reconciliados. Não são carreiras de usuários reais. `suites-addendum.json` distingue essa
  ampliação da execução integral registrada em `suites.json`; o teste principal também ganhou
  oito verificações explícitas dos mínimos por módulo depois da execução integral.
- **500 rodadas por posição**, seis posições, dez temporadas por coorte. Repetição de ID em dez
  aparições: 0%; em vinte: 4,4–7,4%. Famílias: 11–14,4% em dez, 23,4–26,4% em vinte; zero fallback.
- Canal raro sintético: **492/5.000**, com e sem memória. As cenas novas não foram artificialmente
  classificadas como raras para melhorar o resultado do teste.
- **33 verificações visuais/interação**, duas larguras, zoom 150%, foco e um único POST diante
  de duas tentativas de envio na interface. Capturas em `narrativa-v1/mobile/`.

Elegibilidade comum, partida aos 70 minutos, empate, titular, sem cartão/gol/risco especial
(inclui legadas aptas; o CSV separa outros cenários):

| Posição | Total | J01 | J02 | J03 |
|---|---:|---:|---:|---:|
| Zagueiro | 198 | 82 | 103 | 13 |
| Lateral | 225 | 108 | 104 | 13 |
| Volante | 200 | 83 | 104 | 13 |
| Meia | 213 | 111 | 89 | 13 |
| Ponta | 213 | 111 | 89 | 13 |
| Centroavante | 211 | 111 | 87 | 13 |

Esses resultados superam os mínimos de 60 totais, 20 ofensivas e 20 defensivas para cada posição.
Banco antes da entrada, personagem substituído e partida finalizada não recebem cena de campo.

## Testes e reprodução

```powershell
& 'C:\Users\rodri\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' -X utf8 bin/compile-narrative-lot.py
& 'C:\Users\rodri\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' -X utf8 bin/test-narrative.py
& 'C:\Users\rodri\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe' -X utf8 bin/report-narrative.py
node tests/narrative-mobile.cjs
```

Os scripts usam PHP 8.4.26/mbstring, desligam Hub/benefícios **somente no processo de teste**,
e não abrem banco. O relatório `suites.json` registra cada saída/código real.
Coortes finais: 162 temporadas sintéticas de 14 jogos; cada política é a mesma nos três modos.
Rolls de resultado usam `random_int`, portanto os resultados não são pareados por seed.
Igualdade mecânica de chance/efeitos/XP é testada separadamente com estado e roll idênticos.
Equilíbrio terminou sem gols/assistências nesta amostra; protagonismo e coletivo exercitaram
esses eventos na ampliação. Resultados agregados por plano/modo e valores finais de habilidades
estão no JSON. Não usar a média sobre seis posições como previsão para um centroavante.

Elegibilidade real por posição/contexto em CSV/JSON. Repetição: 500 rodadas por posição,
dez temporadas sintéticas, IDs/famílias medidos nas janelas 10/20. Não confundir famílias
com IDs nem inflar variedade renomeando famílias. Raridade testada com e sem memória.

Mobile: HTML de `app/view.php` renderizado pelo PHP CLI e CSS/JS reais em Chrome headless.
Transporte interceptado em memória, sem rede/banco. O servidor loopback ficou inacessível
neste ambiente; não declarar teste novo IIS/Apache. Viewports 320×640, 390×844, zoom 150%,
foco por Tab e duplo envio de interface. Não houve teste em celular físico/leitor de tela.

## Arcos, migrations e recuperação

Arcos permanecem proposta controlada: reserva/disputa de espaço, diálogo com comissão,
elenco e futebol/família. No máximo dois, gatilhos verificáveis, expiração por rodada,
fechamento sem promessa de escalação e sem XP extra. Não foram ativados arcos automáticos,
nem retorno de lesão sem evento clínico persistido.

Nenhuma migration necessária: snapshot e memória são campos opcionais do JSON já salvo pelo
repositório. Integração MySQL nova não foi executada; roundtrip JSON e caminhos legados são testados
sem alterar usuários/benefícios locais. Não houve teste novo de publicação Apache.

Backup de código anterior privado `storage/private/narrative-v1/code-before.zip`.
Rollback local precisa preservar os quatro fallbacks e os IDs já gravados: nunca remover o catálogo
de um save que contém cenas novas. Preferir desligar seleção nova mantendo o leitor de snapshots.
Não resetar carreira, reimportar dados ou desfazer a publicação crescimento v1.0.

## Arquivos e revisão

- Código: `app/NarrativeCatalog.php`, `NarrativeContext.php`, `NarrativeSelection.php`, `Scenes.php`,
  integrações pontuais em `Game.php`, `PlayMode.php`, `view.php`; ajuste em `public/assets/game.css`.
- Dados: `database/editorial/lote-01.txt` a `lote-08.txt`, overrides do lote 01,
  `database/scenes/{t01,t02,p01,j01,j02,j03,f01,f02}.json`, metadados dos 214 legados.
- Ferramentas: `bin/audit-narrative.py`, `compile-narrative-lot.py`, `test-narrative.py`,
  `report-narrative.py`; testes `narrative*.php`, `narrative-mobile.cjs` e adaptação das três
  suítes que tinham contagem antiga fixa. Nenhum teste anterior foi removido.
- Auditorias: `auditoria-214.csv`, `auditoria-expansao.csv`, `alternativas-expansao.csv`,
  `matriz-modulos.csv`, `cenas-inativas.csv`, `repeticoes-baseline.json`, `repeticoes-expansao.json`.
- Evidências: `suites.json`, `suites-addendum.json`, `test-contract.json`, `protected-files.json`, `protected-methods.json`,
  `lint.json`, `coortes-modos.json`, `repeticao-500.json`, elegibilidades e capturas mobile.

Para suspender novas seleções localmente mantendo saves com IDs novos, alterar apenas a entrada
de cenas novas em `Scenes::eligible` para não incluí-las; conservar `Scenes::current`, o catálogo,
snapshots e resolvedor. Não fazer checkout do motor antigo sobre carreiras já contendo IDs novos.
Uma publicação futura exige aprovação específica, backup de saves e smoke tests IIS/Apache/MySQL.

**Status:** entrega local validada, oito lotes no catálogo. Os 45 bloqueios, repetição de textos
curtos e limites de ambiente estão explicitados. Nenhum push, deploy ou alteração na UOL Host.
